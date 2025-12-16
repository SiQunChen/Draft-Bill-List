<?php
// draft_bill_list_action_db.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("db23.ini");

// 建立資料庫連接
$dblink = @pg_connect(DB_CONNECT23);
if (!$dblink) {
    die("資料庫連接失敗: " . pg_last_error());
}

// 取得操作類型
$action = '';
if (isset($_POST['update'])) {
    $action = 'update';
} elseif (isset($_POST['apply'])) {
    $action = 'apply';
} else {
    die("未知的操作請求");
}

// 取得全域參數
$sent_date = isset($_POST['sent_date']) ? $_POST['sent_date'] : '';
$discount_global = isset($_POST['discount']) ? $_POST['discount'] : '';
$ids = isset($_POST['row_check_box']) ? $_POST['row_check_box'] : []; // 這是使用者勾選的帳單 ID 陣列

// 如果沒有勾選任何項目
if (empty($ids)) {
    echo "<script>alert('請至少勾選一筆帳單。'); history.back();</script>";
    exit;
}

try {
    // ---------------------------------------------------------
    // 動作一：UPDATE (僅更新資料，不寄出)
    // ---------------------------------------------------------
    if ($action == 'update') {

        pg_query($dblink, "BEGIN"); // 開啟交易

        foreach ($ids as $id) {
            $id = intval($id);

            // 1. 取得該列的個別參數
            $discount = $discount_global; // 如果全域有填，優先使用全域，否則這裡可以擴充接收個別折扣

            // ATI 相關欄位
            $ati_cate1 = $_POST["ati_cate1_{$id}"] ?? null;
            $ati_cate2 = $_POST["ati_cate2_{$id}"] ?? null;
            $ati_cate12 = $_POST["ati_cate12_{$id}"] ?? null;
            $ati_cate22 = $_POST["ati_cate22_{$id}"] ?? null;
            $ati_cate13 = $_POST["ati_cate13_{$id}"] ?? null;
            $ati_cate23 = $_POST["ati_cate23_{$id}"] ?? null;

            $new_matter = isset($_POST["new_matter_{$id}"]) ? 1 : 0;
            $new_matter2 = isset($_POST["new_matter2_{$id}"]) ? 1 : 0;
            $new_matter3 = isset($_POST["new_matter3_{$id}"]) ? 1 : 0;

            $project_owner = $_POST["project_owner_{$id}"] ?? null;
            $class_count = intval($_POST["class_count_{$id}"] ?? 0);
            if ($class_count < 0) $class_count = 0;

            $azn_budget_status = $_POST["azn_budget_status_{$id}"] ?? null;
            $invoice_exp_status = $_POST["invoice_exp_status_{$id}"] ?? null; // radio: expected or cancel
            $retainer_amount = $_POST["retainer_amount_{$id}"] ?? null;

            // 2. 查詢帳單基本資訊
            $sql_bill = "SELECT deb_num, case_num FROM bills WHERE id = $1";
            $res_bill = pg_query_params($dblink, $sql_bill, [$id]);
            $bill = pg_fetch_assoc($res_bill);
            if (!$bill) continue;

            $deb_num = $bill['deb_num'];

            // 3. 更新 OC Invoice Expected (TR Table)
            if ($invoice_exp_status === 'expected') {
                // 更新第一筆符合的 TR
                $sql_tr = "UPDATE tr SET invoice_exp_status = '1' 
                           WHERE id IN (SELECT id FROM tr WHERE deb_num = $1 ORDER BY id LIMIT 1)";
                pg_query_params($dblink, $sql_tr, [$deb_num]);
            } elseif ($invoice_exp_status === 'cancel') {
                $sql_tr = "UPDATE tr SET invoice_exp_status = '0' WHERE deb_num = $1";
                pg_query_params($dblink, $sql_tr, [$deb_num]);
            }

            // 4. 更新 Bills 表 (ATI, Discount, Retainer Amount)
            $update_fields = [
                "ati_cate1 = $1",
                "ati_cate2 = $2",
                "ati_cate12 = $3",
                "ati_cate22 = $4",
                "ati_cate13 = $5",
                "ati_cate23 = $6",
                "new_matter = $7",
                "new_matter2 = $8",
                "new_matter3 = $9",
                "project_owner = $10",
                "class_count = $11",
                "azn_budget_status = $12"
            ];
            $params = [
                $ati_cate1,
                $ati_cate2,
                $ati_cate12,
                $ati_cate22,
                $ati_cate13,
                $ati_cate23,
                $new_matter,
                $new_matter2,
                $new_matter3,
                $project_owner,
                $class_count,
                $azn_budget_status
            ];
            $p_idx = 13;

            // 折扣邏輯
            if ($discount !== '' && $discount !== null) {
                $update_fields[] = "discount = $" . $p_idx++;
                $params[] = $discount;
            }

            // Retainer Amount 邏輯
            if ($retainer_amount !== '' && $retainer_amount !== null && $retainer_amount >= 0) {
                $update_fields[] = "retainer_amount = $" . $p_idx++;
                $params[] = $retainer_amount;
            }

            $sql_update = "UPDATE bills SET " . implode(', ', $update_fields) . " WHERE id = $" . $p_idx;
            $params[] = $id;

            $res_upd = pg_query_params($dblink, $sql_update, $params);
            if (!$res_upd) {
                throw new Exception("Update bills failed for ID: $id. " . pg_last_error($dblink));
            }
        }

        pg_query($dblink, "COMMIT");
        echo "<script>alert('草稿已更新 (Updated)'); location.href='draft_bill_list.php';</script>";
    }

    // ---------------------------------------------------------
    // 動作二：APPLY (寄出帳單，押日期，寫入歷史)
    // ---------------------------------------------------------
    elseif ($action == 'apply') {

        // 日期處理：若未填寫則預設今天
        if (empty($sent_date)) {
            $sent_date = date('Y-m-d');
        } else {
            // Perl 邏輯中的日期檢查較寬鬆，這裡確保格式正確即可
            // 也可以加入檢查是否為未來日期等邏輯
        }

        // 必須開啟交易，因為涉及多個資料表連動
        pg_query($dblink, "BEGIN");

        foreach ($ids as $id) {
            $id = intval($id);
            $retainer_amount = $_POST["retainer_amount_{$id}"] ?? null;

            // 1. 鎖定並取得帳單資料
            $sql_bill = "SELECT b.*, c.retainer_num, c.party_en_name_billing, c.retainer_foreign, c.retainer_ntd, c.retainer_case_num 
                         FROM bills b 
                         LEFT JOIN cases c ON b.case_num = c.case_num 
                         WHERE b.id = $1 FOR UPDATE";
            $res_bill = pg_query_params($dblink, $sql_bill, [$id]);
            $bill = pg_fetch_assoc($res_bill);

            if (!$bill) throw new Exception("找不到帳單 ID: $id");

            $deb_num = $bill['deb_num'];
            $case_num = $bill['case_num'];
            $legal_services = $bill['legal_services'];

            // 2. 驗證：如果有法律服務費，必須有 TR (Time Record) 紀錄
            if ($legal_services > 0) {
                $sql_check_tr = "SELECT 1 FROM tr WHERE deb_num = $1 AND nocharge_flag = '-1' LIMIT 1";
                $res_check = pg_query_params($dblink, $sql_check_tr, [$deb_num]);
                if (pg_num_rows($res_check) == 0) {
                    throw new Exception("錯誤：帳單 $deb_num 有法律服務費，但找不到對應的時間紀錄 (Time Record)。無法寄出。");
                }
            }

            // 3. 更新 bills 表：押上 sent 日期
            // 注意：Party Name 若有單引號需處理，但 pg_query_params 會自動處理跳脫，無需手動取代
            $bills_retainer_num = $bill['retainer_num'];
            $party_en_name = $bill['party_en_name_billing'];

            $sql_apply = "UPDATE bills SET 
                            sent = $1, 
                            original_legal_services = $2, 
                            bills_retainer_num = $3, 
                            party_en_name_bills = $4,
                            retainer_amount = $5
                          WHERE id = $6";

            // 若 retainer_amount 沒填，保持原值或設為 0 (依 Perl 邏輯若有填才更新，這裡假設 Apply 時確認最終金額)
            $final_retainer_amt = ($retainer_amount !== '' && $retainer_amount !== null) ? $retainer_amount : $bill['retainer_amount'];

            $res_apply = pg_query_params($dblink, $sql_apply, [
                $sent_date,
                $legal_services,
                $bills_retainer_num,
                $party_en_name,
                $final_retainer_amt,
                $id
            ]);

            if (!$res_apply) throw new Exception("更新帳單狀態失敗: " . pg_last_error($dblink));

            // 4. 記錄操作 IP (bills_current_sent)
            $user_ip = $_SERVER['REMOTE_ADDR'];
            $sql_log = "INSERT INTO bills_current_sent (deb_num, sent_ip_address) VALUES ($1, $2)";
            pg_query_params($dblink, $sql_log, [$deb_num, $user_ip]);

            // 5. 觸發 Email (如果 Retainer Amount > 0)
            if ($final_retainer_amt > 0) {
                $sql_email = "INSERT INTO send_email (case_num, deb_num) VALUES ($1, $2)";
                pg_query_params($dblink, $sql_email, [$case_num, $deb_num]);
            }

            // 6. 更新 Retainer 歷史紀錄 (移植 update_retainer 函數邏輯)
            // 計算年份與季度
            $date_parts = explode('-', $sent_date); // YYYY-MM-DD
            $r_year = $date_parts[0];
            $r_month = intval($date_parts[1]);
            // 季度計算邏輯：(month / 3 + 0.9) 取整數 -> 1,2,3 => 1; 4,5,6 => 2 ...
            $r_quarter = floor($r_month / 3.0 + 0.9);

            // 呼叫內部函數處理 Retainer 邏輯
            update_retainer_logic($dblink, $r_year, $r_quarter, $bills_retainer_num, $bill, $deb_num);

            // 7. 記錄 Rainmakers 歷史 (snapshot)
            // 將此帳單當下的 rainmaker 分配比例寫入歷史檔
            $sql_rain = "SELECT bills.case_num, deb_num, sent, initials, share, rain_type 
                         FROM bills 
                         LEFT JOIN rainmakers ON (bills.case_num = rainmakers.case_num) 
                         WHERE deb_num = $1 
                         ORDER BY sent, bills.case_num, rain_type, rainmakers.id";
            $res_rain = pg_query_params($dblink, $sql_rain, [$deb_num]);

            while ($row_r = pg_fetch_assoc($res_rain)) {
                // 檢查是否已存在 (防止重複執行 Apply)
                $sql_check_rh = "SELECT 1 FROM rainmakers_his 
                                 WHERE rainmakers_his_deb_num = $1 
                                 AND rainmakers_his_initials = $2 
                                 AND (rainmakers_his_rain_type IS NOT DISTINCT FROM $3)";

                $res_check_rh = pg_query_params($dblink, $sql_check_rh, [
                    $row_r['deb_num'],
                    $row_r['initials'],
                    $row_r['rain_type']
                ]);

                if (pg_num_rows($res_check_rh) == 0) {
                    $sql_ins_rh = "INSERT INTO rainmakers_his 
                                   (rainmakers_his_deb_num, rainmakers_his_initials, rainmakers_his_share, rainmakers_his_rain_type) 
                                   VALUES ($1, $2, $3, $4)";
                    pg_query_params($dblink, $sql_ins_rh, [
                        $row_r['deb_num'],
                        $row_r['initials'],
                        $row_r['share'],
                        $row_r['rain_type']
                    ]);
                }
            }
        }

        pg_query($dblink, "COMMIT");
        echo "<script>alert('帳單已成功套用日期並寄出 (Applied Sent Date)'); location.href='draft_bill_list.php';</script>";
    }
} catch (Exception $e) {
    pg_query($dblink, "ROLLBACK");
    $msg = addslashes($e->getMessage());
    echo "<script>alert('操作失敗：$msg'); history.back();</script>";
} finally {
    if ($dblink) pg_close($dblink);
}


// ---------------------------------------------------------------------
// 輔助函數：處理 Retainer 歷史更新邏輯 (移植自 Perl sub update_retainer)
// ---------------------------------------------------------------------
function update_retainer_logic($dblink, $retainer_year, $retainer_quarter, $retainer_num, $bill_data, $deb_num) {

    // 預設參數
    $currency = isset($bill_data['billing_currency']) && strpos($bill_data['billing_currency'], 'USD') !== false ? 'USD' : 'TWD'; // 簡單判斷
    // Perl 邏輯：若是 PPP, TDG, BMT，強制設定
    if (in_array($retainer_num, ['PPP', 'TDG', 'BMT'])) {
        $currency = 'TWD';
        $in_case_num = 'AZN00210';
    } else {
        // 其他案件保留原邏輯 (Perl 中其他案件的 in_case_num 未明確強制指定，通常依賴 table default 或 null)
        $in_case_num = $bill_data['retainer_case_num'];
    }

    $total_amount = $bill_data['total'];

    // 1. 插入 retainer_his
    $sql_his = "INSERT INTO retainer_his 
                (retainer_his_num, retainer_his_date, retainer_his_year, retainer_his_quarter, retainer_his_deb_num, retainer_his_rec_ntd, retainer_his_currency, retainer_his_foreign_amt) 
                VALUES ($1, NOW(), $2, $3, $4, $5, $6, $7)";

    $res_his = pg_query_params($dblink, $sql_his, [
        $retainer_num,
        $retainer_year,
        $retainer_quarter,
        $deb_num,
        $total_amount,
        $currency,
        $total_amount
    ]);

    if (!$res_his) {
        throw new Exception("Insert retainer_his failed: " . pg_last_error($dblink));
    }

    // 2. 更新 bills table 的 retainer_flag
    $sql_bf = "UPDATE bills SET retainer_flag = '1' WHERE deb_num = $1";
    $res_bf = pg_query_params($dblink, $sql_bf, [$deb_num]);
    if (!$res_bf) {
        throw new Exception("Update bills retainer_flag failed.");
    }

    // 3. 更新 retainer_total 表 (計算餘額)
    $use_total = $total_amount;

    // 檢查是否已有該季度紀錄
    $sql_rt_check = "SELECT * FROM retainer_total 
                     WHERE retainer_num = $1 AND retainer_year = $2 AND retainer_quarter = $3";
    $res_rt = pg_query_params($dblink, $sql_rt_check, [$retainer_num, $retainer_year, $retainer_quarter]);

    if ($rt_data = pg_fetch_assoc($res_rt)) {
        // 已有紀錄：更新餘額 (remain 減少，used 增加)
        $remain_rec_ntd = $rt_data['remain_rec_ntd'] - $use_total;
        $remain_rec_foreign_amt = $rt_data['remain_rec_foreign_amt'] - $use_total;
        $used_rec_ntd = $rt_data['used_rec_ntd'] + $use_total;
        $used_rec_foreign_amt = $rt_data['used_rec_foreign_amt'] + $use_total;

        $sql_rt_upd = "UPDATE retainer_total SET 
                        remain_rec_ntd = $1, 
                        remain_rec_foreign_amt = $2, 
                        used_rec_ntd = $3, 
                        used_rec_foreign_amt = $4 
                       WHERE retainer_num = $5 AND retainer_year = $6 AND retainer_quarter = $7";

        $res_rt_upd = pg_query_params($dblink, $sql_rt_upd, [
            $remain_rec_ntd,
            $remain_rec_foreign_amt,
            $used_rec_ntd,
            $used_rec_foreign_amt,
            $retainer_num,
            $retainer_year,
            $retainer_quarter
        ]);
        if (!$res_rt_upd) throw new Exception("Update retainer_total failed.");
    } else {
        // 沒有紀錄：建立新紀錄 (餘額為負數，表示已使用)
        $remain_total = $use_total * -1;

        $sql_rt_ins = "INSERT INTO retainer_total 
                       (retainer_num, retainer_year, retainer_quarter, total_rec_ntd, total_currency, total_rec_foreign_amt, used_rec_ntd, used_currency, used_rec_foreign_amt, remain_rec_ntd, remain_currency, remain_rec_foreign_amt, retainer_rate, retainer_case_num)
                       VALUES ($1, $2, $3, 0, $4, 0, $5, $6, $7, $8, $9, $10, 1, $11)";

        $res_rt_ins = pg_query_params($dblink, $sql_rt_ins, [
            $retainer_num,
            $retainer_year,
            $retainer_quarter,
            $currency,
            $use_total,
            $currency,
            $use_total,
            $remain_total,
            $currency,
            $remain_total,
            $in_case_num
        ]);
        if (!$res_rt_ins) throw new Exception("Insert retainer_total failed.");
    }
}
