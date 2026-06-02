<?php
session_start();

/**
 * Retainer 預收款分配後端 API
 * 處理 Draft Bill List 頁面的預收款進階分配功能
 */
require_once("db23.ini");

/**
 * 取得預收款餘額及可使用的所有預收款
 * 
 * @param string $retainer_case_num 預收款來源案號 (e.g., 'ADB007')
 * @return array [
 *   'remain_twd_amount' => 預收款 TWD 餘額,
 *   'remain_foreign_amount' => 外幣餘額,
 *   'currency' => 外幣幣別,
 *   'retainers' => [所有預收款列表]
 * ]
 */
function getRetainers($retainer_case_num, $bills_case_num, $currency) {
    try {
        $dblink = @pg_connect(DB_CONNECT23);
        if (!$dblink) {
            throw new Exception("無法連接到資料庫");
        }

        // 查詢此 bills_case_num 已抵扣的預收款金額
        $deduct_map = [];

        if (!empty($bills_case_num)) {
            // 取出該 bills_case_num 的資料，並依 relation_id 加總金額
            $sql_bills = "SELECT 
                            relation_id,
                            SUM(CASE WHEN bills_case_num = $1 THEN twd_amount ELSE 0 END) as current_twd,
                            SUM(CASE WHEN bills_case_num = $1 THEN foreign_amount ELSE 0 END) as current_foreign,
                            SUM(CASE WHEN bills_case_num != $1 OR bills_case_num IS NULL THEN twd_amount ELSE 0 END) as other_twd,
                            SUM(CASE WHEN bills_case_num != $1 OR bills_case_num IS NULL THEN foreign_amount ELSE 0 END) as other_foreign
                          FROM client_pay_history 
                          WHERE relation_id IS NOT NULL
                          AND payment_type = 'Retainer'
                          AND payment_status = 'Applied'
                          AND status = 0
                          GROUP BY relation_id";

            $res_bills = pg_query_params($dblink, $sql_bills, [$bills_case_num]);

            if ($res_bills) {
                while ($row_bill = pg_fetch_assoc($res_bills)) {
                    $deduct_map[$row_bill['relation_id']] = [
                        'current' => [
                            'twd'     => floatval($row_bill['current_twd'] ?? 0),
                            'foreign' => floatval($row_bill['current_foreign'] ?? 0)
                        ],
                        'other' => [
                            'twd'     => floatval($row_bill['other_twd'] ?? 0),
                            'foreign' => floatval($row_bill['other_foreign'] ?? 0)
                        ],
                    ];
                }
            }
        }

        // 查詢此案號的所有預收款
        $sql_retainer = "SELECT 
                            id,
                            case_num,
                            deb_num,
                            payment_type,
                            payment_method,
                            bank_account,
                            currency,
                            remain_twd_amount,
                            remain_foreign_amount,
                            rate,
                            record_date
                        FROM client_pay_history 
                        WHERE payment_type = 'Retainer'
                        AND payment_status = 'Received'
                        AND case_num = $1
                        AND currency = $2";

        $res_retainer = pg_query_params($dblink, $sql_retainer, [$retainer_case_num, $currency]);

        if (!$res_retainer) {
            throw new Exception("查詢帳單失敗: " . pg_last_error($dblink));
        }

        $retainers = [];
        while ($row = pg_fetch_assoc($res_retainer)) {
            // 根據帳單幣別決定顯示金額
            $is_foreign = $row['currency'] != 'TWD' ? true : false;

            // 根據 bills_case_num 的查詢結果，決定預設抵扣金額及是否鎖定
            $current_id = $row['id'];
            $deduct_data = $deduct_map[$current_id]['current'][$is_foreign ? 'foreign' : 'twd'] ?? 0;
            $allocated_amount = 0; // 預設為0
            $is_locked = false; // 預設不鎖定
            if (!empty($deduct_data)) {
                $allocated_amount = $deduct_data;
                $is_locked = true;
            }

            // 顯示預收款剩餘金額
            $remain = $is_foreign ?
                floatval($row['remain_foreign_amount'] ?? 0) - floatval($deduct_map[$current_id]['other']['foreign'] ?? 0) :
                floatval($row['remain_twd_amount'] ?? 0) - floatval($deduct_map[$current_id]['other']['twd'] ?? 0);
            $fmt_remain = $is_foreign ? number_format($remain, 2) : number_format($remain, 0);

            // 如果顯示金額為0，則不顯示
            if ($remain == 0) {
                continue;
            }

            $retainers[] = [
                'id' => $row['id'],
                'case_num' => $row['case_num'],
                'deb_num' => $row['deb_num'],
                'payment_type' => $row['payment_type'],
                'payment_method' => $row['payment_method'],
                'bank_account' => $row['bank_account'],
                'currency' => $row['currency'],
                'rate' => $row['rate'],
                'remain' => $remain,
                'fmt_remain' => $fmt_remain,
                'date' => $row['record_date'],
                'allocated_amount' => $allocated_amount,
                'is_locked' => $is_locked
            ];
        }

        return [
            'success' => true,
            'retainer_case_num' => $retainer_case_num,
            'retainers' => $retainers,
            'retainer_count' => count($retainers)
        ];
    } finally {
        if ($dblink) {
            pg_close($dblink);
        }
    }
}

/**
 * 儲存預收款抵扣分配
 * 將已鎖定的預收款資料寫入 client_pay_history
 * 
 * @param array $retainers 已鎖定的預收款陣列
 * @return array 結果
 */
function saveAllocation($retainers) {
    if (empty($retainers) || !is_array($retainers)) {
        throw new Exception("沒有需要儲存的抵扣資料");
    }

    $dblink = @pg_connect(DB_CONNECT23);
    if (!$dblink) {
        throw new Exception("無法連接到資料庫");
    }

    try {
        pg_query($dblink, "BEGIN");

        foreach ($retainers as $retainer) {
            $case_num = $retainer['case_num'] ?? '';
            $bills_case_num = $retainer['bills_case_num'] ?? '';
            $deb_num = $retainer['deb_num'] ?? '';
            $payment_type = $retainer['payment_type'] ?? 'Retainer';
            $payment_status = 'Applied';
            $payment_method = $retainer['payment_method'] ?? '';
            $bank_account = $retainer['bank_account'] ?? '';
            $currency = $retainer['currency'] ?? 'TWD';
            $allocated_amount = floatval($retainer['allocated_amount'] ?? 0);
            $rate = floatval($retainer['rate'] ?? 1);
            $relation_id = $retainer['id'] ?? null;
            $is_locked = $retainer['is_locked'] ?? false;
            $record_date = date('Y-m-d');

            // 根據幣別決定 foreign/twd 欄位的值
            if ($currency === 'TWD') {
                $foreign_amount = 0;
                $twd_amount = $allocated_amount;
            } else {
                $foreign_amount = $allocated_amount;
                $twd_amount = 0;
            }

            // --- 檢查是否已存在 ---
            $check_sql = "SELECT id, twd_amount, foreign_amount FROM client_pay_history
                          WHERE case_num = $1 
                            AND bills_case_num = $2 
                            AND deb_num = $3 
                            AND payment_type = $4 
                            AND payment_status = $5 
                            AND payment_method = $6 
                            AND bank_account = $7 
                            AND currency = $8 
                            AND relation_id = $9 
                            AND status = $10";

            $check_params = [
                $case_num,
                $bills_case_num,
                $deb_num,
                $payment_type,
                $payment_status,
                $payment_method,
                $bank_account,
                $currency,
                $relation_id,
                0
            ];

            $check_result = pg_query_params($dblink, $check_sql, $check_params);

            // ==========================================
            //  情境 A：解除鎖定或金額為 0 -> 刪除並還原
            // ==========================================
            if (!$is_locked || $allocated_amount == 0) {
                if ($check_result && pg_num_rows($check_result) > 0) {
                    $existing_row = pg_fetch_assoc($check_result);
                    $existing_id = $existing_row['id'];
                    $old_twd_amount = floatval($existing_row['twd_amount']);
                    $old_foreign_amount = floatval($existing_row['foreign_amount']);

                    // 1. 還原總額 (Total)
                    $restore_total_sql = "UPDATE client_pay_total
                                          SET temp_twd_total_amount = temp_twd_total_amount + $1,
                                              temp_foreign_total_amount = temp_foreign_total_amount + $2,
                                              update_time = CURRENT_TIMESTAMP
                                          WHERE case_num = $3";

                    $res_total_restore = pg_query_params($dblink, $restore_total_sql, [
                        $old_twd_amount,
                        $old_foreign_amount,
                        $case_num
                    ]);

                    if (!$res_total_restore) {
                        throw new Exception("還原總額失敗: " . pg_last_error($dblink));
                    }

                    // 2. 刪除紀錄
                    $delete_sql = "DELETE FROM client_pay_history WHERE id = $1";
                    $res_delete = pg_query_params($dblink, $delete_sql, [$existing_id]);

                    if (!$res_delete) {
                        throw new Exception("刪除紀錄失敗: " . pg_last_error($dblink));
                    }
                }
                continue;
            }

            // ==========================================
            //  情境 B：資料存在，執行差額更新
            // ==========================================
            if ($check_result && pg_num_rows($check_result) > 0) {
                $existing_row = pg_fetch_assoc($check_result);
                $existing_id = $existing_row['id'];

                // 1. 取得舊金額
                $old_twd_amount = floatval($existing_row['twd_amount']);
                $old_foreign_amount = floatval($existing_row['foreign_amount']);

                // 2. 計算差額 (新 - 舊)
                $diff_twd = $twd_amount - $old_twd_amount;
                $diff_foreign = $foreign_amount - $old_foreign_amount;

                // 如果金額完全沒變，直接跳過
                if ($diff_twd == 0 && $diff_foreign == 0) {
                    continue;
                }

                // 3. 更新抵扣紀錄
                $update_history_sql = "UPDATE client_pay_history 
                                       SET foreign_amount = $1, 
                                           twd_amount = $2
                                       WHERE id = $3";
                $update_result = pg_query_params($dblink, $update_history_sql, [
                    $foreign_amount,
                    $twd_amount,
                    $existing_id
                ]);

                if (!$update_result) {
                    throw new Exception("更新歷史金額失敗: " . pg_last_error($dblink));
                }

                // 4. 根據差額更新 client_pay_total
                $sql_update_total_diff = "UPDATE client_pay_total
                                          SET temp_twd_total_amount = temp_twd_total_amount - $1,
                                              temp_foreign_total_amount = temp_foreign_total_amount - $2,
                                              update_time = CURRENT_TIMESTAMP
                                          WHERE case_num = $3";

                $res_total = pg_query_params($dblink, $sql_update_total_diff, [
                    $diff_twd,
                    $diff_foreign,
                    $case_num
                ]);

                if (!$res_total) {
                    throw new Exception("更新總額失敗: " . pg_last_error($dblink));
                }
                continue;
            }

            // ==========================================
            //  情境 C：資料不存在，執行 INSERT
            // ==========================================
            $insert_sql = "INSERT INTO client_pay_history (
                                case_num,
                                bills_case_num,
                                deb_num, 
                                payment_type,
                                payment_status, 
                                payment_method,
                                bank_account,
                                currency,
                                foreign_amount,
                                twd_amount,
                                rate,
                                relation_id,
                                record_date,
                                income_status,
                                initials,
                                status
                            ) VALUES (
                                $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16
                            )";

            $insert_params = [
                $case_num,
                $bills_case_num,
                $deb_num,
                $payment_type,
                $payment_status,
                $payment_method,
                $bank_account,
                $currency,
                $foreign_amount,
                $twd_amount,
                $rate,
                $relation_id,
                $record_date,
                0,
                $_SESSION['initial'] ?? '',
                0
            ];

            $result = pg_query_params($dblink, $insert_sql, $insert_params);
            if (!$result) {
                throw new Exception("寫入失敗: " . pg_last_error($dblink));
            }

            // 更新 client_pay_total
            $sql_update_total = "UPDATE client_pay_total
                                SET
                                    temp_twd_total_amount = temp_twd_total_amount - $1,
                                    temp_foreign_total_amount = temp_foreign_total_amount - $2,
                                    update_time = CURRENT_TIMESTAMP
                                WHERE case_num = $3";
            $update_total_result = pg_query_params($dblink, $sql_update_total, [
                $twd_amount,
                $foreign_amount,
                $case_num
            ]);
            if (!$update_total_result) {
                throw new Exception("更新總額失敗: " . pg_last_error($dblink));
            }
        }

        pg_query($dblink, "COMMIT");

        return [
            'success' => true,
            'message' => "抵扣成功"
        ];
    } catch (Throwable $e) {
        pg_query($dblink, "ROLLBACK");
        throw $e;
    } finally {
        if ($dblink) {
            pg_close($dblink);
        }
    }
}

// API Router - 只有在有 action 參數時才處理
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!empty($action)) {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);

    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });

    header('Content-Type: application/json; charset=utf-8');

    try {
        switch ($action) {
            case 'get_retainers':
                $retainer_case_num = $_GET['retainer_case_num'] ?? '';
                $bills_case_num = $_GET['bills_case_num'] ?? '';
                $currency = $_GET['currency'] ?? '';
                if (empty($retainer_case_num)) {
                    throw new Exception("缺少 retainer_case_num 參數");
                }
                $result = getRetainers($retainer_case_num, $bills_case_num, $currency);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                break;

            case 'save_allocation':
                $retainersJson = $_POST['retainers'] ?? '';
                $retainers = json_decode($retainersJson, true);
                if (empty($retainers) || !is_array($retainers)) {
                    throw new Exception("沒有需要儲存的抵扣資料");
                }
                $result = saveAllocation($retainers);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                break;

            default:
                throw new Exception("未知的操作: $action");
        }
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
