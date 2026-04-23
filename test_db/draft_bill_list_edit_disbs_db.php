<?php

/**
 * Disbursements 後端 API
 * 處理 Draft Bill Edit 頁面的 Disbursements Modal 功能
 */
require_once("db23.ini");

/**
 * 讀取 Disbursements 清單
 * 
 * @param string $case_num 案件編號
 * @param string $deb_num 帳單編號
 * @return array 包含 disbursements 清單和統計資訊
 */
function getDisbursements($case_num, $deb_num) {
    $dblink = @pg_connect(DB_CONNECT23);
    if (!$dblink) {
        throw new Exception("無法連接到資料庫");
    }

    try {
        // 查詢 disbursements (對應 disb_init.pl 的 update mode)
        $sql = "SELECT * FROM disbursements 
                WHERE case_num = $1 
                AND ((billed_flag = 0 AND deb_num = $2) 
                     OR (deb_num IS NOT NULL AND billed_flag = 2))
                ORDER BY date DESC";
        $res = pg_query_params($dblink, $sql, [$case_num, $deb_num]);

        $disbursements = [];
        while ($row = pg_fetch_assoc($res)) {
            // 格式化金額
            $row['ntd_amount_formatted'] = number_format($row['ntd_amount']);
            $disbursements[] = $row;
        }

        // 計算 NTD 總計 (只計算 show_flag = 1 的)
        $sql_total = "SELECT COALESCE(SUM(ntd_amount), 0) AS ntd_total 
                      FROM disbursements 
                      WHERE case_num = $1 
                      AND deb_num = $2
                      AND show_flag = 1
                      AND billed_flag = 0";
        $res_total = pg_query_params($dblink, $sql_total, [$case_num, $deb_num]);
        $total_row = pg_fetch_assoc($res_total);
        $ntd_total = floatval($total_row['ntd_total']);

        return [
            'disbursements' => $disbursements,
            'ntd_total' => number_format($ntd_total),
            'records' => count($disbursements)
        ];
    } finally {
        if ($dblink) {
            pg_close($dblink);
        }
    }
}



/**
 * 重新計算並更新 bills 表的 disbursements 金額
 * 對應 disb_confirm.pl 的計算邏輯
 */
function updateBillsDisbursements($dblink, $case_num, $deb_num) {
    // 計算 NTD 和外幣 disbursements 總計
    $sql = "SELECT 
                COALESCE(SUM(ntd_amount), 0) AS ntd_total,
                COALESCE(SUM(foreign_amount2), 0) AS foreign_total 
            FROM disbursements
            WHERE billed_flag = 0
            AND show_flag = 1
            AND nocharge_flag = -1
            AND case_num = $1
            AND deb_num = $2";

    $res = pg_query_params($dblink, $sql, [$case_num, $deb_num]);
    $row = pg_fetch_assoc($res);

    $ntd_total = floatval($row['ntd_total']);
    $foreign_total = floatval($row['foreign_total']);

    // 更新 bills 表
    $sql_update = "UPDATE bills SET 
                   disbs = $1, 
                   foreign_disbs2 = $2 
                   WHERE deb_num = $3";

    pg_query_params($dblink, $sql_update, [$ntd_total, $foreign_total, $deb_num]);

    // 重新計算 total
    $sql_bill = "SELECT * FROM bills WHERE deb_num = $1";
    $res_bill = pg_query_params($dblink, $sql_bill, [$deb_num]);
    $bill = pg_fetch_assoc($res_bill);

    if ($bill) {
        $legal_services = floatval($bill['legal_services']);
        $trans_services = floatval($bill['trans_services']);
        $total = $legal_services + $ntd_total + $trans_services;

        $foreign_legal = floatval($bill['foreign_legal2']);
        $foreign_total_bill = $foreign_legal + $foreign_total;

        $x_rate = floatval($bill['x_rate']) ?: 1;
        $usd_total = $total / $x_rate;

        $sql_update_total = "UPDATE bills SET 
                            total = $1,
                            usd_total = $2,
                            foreign_total2 = $3
                            WHERE deb_num = $4";

        pg_query_params($dblink, $sql_update_total, [$total, $usd_total, $foreign_total_bill, $deb_num]);
    }
}

/**
 * 批量更新 Disbursements
 * 
 * @param array $postData 包含 disbursements 陣列的資料
 * @return array 更新結果
 */
function updateDisbursements($postData) {
    $dblink = @pg_connect(DB_CONNECT23);
    if (!$dblink) {
        throw new Exception("無法連接到資料庫");
    }

    try {
        pg_query($dblink, "BEGIN");

        $disbursements = $postData['disbursements'] ?? [];

        // 如果是 JSON 字串則解碼 (以防萬一)
        if (is_string($disbursements)) {
            $disbursements = json_decode($disbursements, true);
        }

        if (empty($disbursements) || !is_array($disbursements)) {
            throw new Exception("沒有可儲存的資料");
        }

        // 取得第一筆資料的 case_num 和 deb_num 用於最後重新計算
        $firstKey = array_key_first($disbursements);
        $group_case_num = $disbursements[$firstKey]['case_num'] ?? '';
        $group_deb_num = $disbursements[$firstKey]['deb_num'] ?? '';

        foreach ($disbursements as $data) {
            $id = $data['id'] ?? null;
            if (!$id) continue;

            $date = $data['date'] ?? '';
            $initials = $data['initials'] ?? '';
            $narrative = $data['narrative'] ?? '';
            $check_bills = !empty($data['check_bills']) ? 1 : 0;

            // No Charge 邏輯
            // 前端傳送 show_flag = 1 表示 No charge 被勾選 (對應 isset($postData['show_flag']))
            $is_no_charge = !empty($data['show_flag']);

            if ($is_no_charge) {
                // No charge 被勾選
                $show_flag = -1;
                $nocharge_flag = 1;
                $billed_flag = 2;
            } else {
                // No charge 沒勾選
                $show_flag = 1;
                $nocharge_flag = -1;
                $billed_flag = ($group_deb_num !== '') ? 0 : -1;
            }

            // deb_num 處理 & Remove 邏輯
            $current_deb_num = $data['deb_num'] ?? $group_deb_num;
            if ($check_bills == 1) {
                // Remove: 1. deb_num 改為 null, 2. billed_flag 改為 -1
                $deb_num_update = null;
                $billed_flag = -1;
            } else {
                $deb_num_update = $current_deb_num;
            }

            // Legal service flag
            $show_as_legal_service_flag = !empty($data['show_as_legal_service_flag']) ? 1 : -1;

            // 更新 database
            $sql_update = "UPDATE disbursements SET
                           date = $1,
                           initials = $2,
                           narrative = $3,
                           show_flag = $4,
                           nocharge_flag = $5,
                           billed_flag = $6,
                           show_as_legal_service_flag = $7,
                           check_bills = $8,
                           deb_num = $9
                           WHERE id = $10";

            $res = pg_query_params($dblink, $sql_update, [
                $date,
                $initials,
                $narrative,
                $show_flag,
                $nocharge_flag,
                $billed_flag,
                $show_as_legal_service_flag,
                $check_bills,
                $deb_num_update,
                $id
            ]);

            if (!$res) {
                throw new Exception("更新 disbursement ID $id 失敗");
            }
        }

        // 重新計算並更新 bills 表
        if ($group_deb_num !== '') {
            updateBillsDisbursements($dblink, $group_case_num, $group_deb_num);
        }

        pg_query($dblink, "COMMIT");

        return [
            'success' => true,
            'message' => '批量更新成功'
        ];
    } catch (Exception $e) {
        pg_query($dblink, "ROLLBACK");
        throw $e;
    } finally {
        if ($dblink) {
            pg_close($dblink);
        }
    }
}

// API Router
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);

    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });

    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {


            case 'update':
                $result = updateDisbursements($_POST);
                echo json_encode($result);
                break;

            default:
                throw new Exception("未知的操作: $action");
        }
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
