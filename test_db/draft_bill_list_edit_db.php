<?php
require_once("db23.ini");

function getBillData($deb_num) {
    $dblink = @pg_connect(DB_CONNECT23);
    if (!$dblink) {
        throw new Exception("無法連接到資料庫");
    }

    try {
        // 1. Fetch Bill Details
        $sql_bill = "SELECT * FROM bills WHERE deb_num = $1";
        $res_bill = pg_query_params($dblink, $sql_bill, [$deb_num]);
        $bill = pg_fetch_assoc($res_bill);

        if (!$bill) {
            throw new Exception("找不到帳單: " . $deb_num);
        }

        $case_num = $bill['case_num'];

        // 2. Fetch Case Details
        $sql_case = "SELECT * FROM cases WHERE case_num = $1";
        $res_case = pg_query_params($dblink, $sql_case, [$case_num]);
        $case = pg_fetch_assoc($res_case);

        // 3. Fetch Case Summary (Narrative)
        $sql_summary = "SELECT * FROM case_summaries WHERE case_num = $1";
        $res_summary = pg_query_params($dblink, $sql_summary, [$case_num]);
        $case_summary = pg_fetch_assoc($res_summary);
        $narrative = $case_summary ? $case_summary['narrative'] : '';

        // 4. Fetch LEDES Codes
        $case_num3 = substr($case_num, 0, 3);

        // LEDES Codes
        $sql_ledes = "SELECT * FROM tr_ledes_code WHERE case_num = $1 ORDER BY ledes_code";
        $res_ledes = pg_query_params($dblink, $sql_ledes, [$case_num3]);
        $ledes_codes = [];
        while ($row = pg_fetch_assoc($res_ledes)) {
            $ledes_codes[$row['ledes_code']] = $row['ledes_content'];
        }

        // LEDES Activity Codes
        $sql_activity = "SELECT * FROM tr_ledes_activity_code WHERE ledes_a_case_num = $1 ORDER BY ledes_activity_code";
        $res_activity = pg_query_params($dblink, $sql_activity, [$case_num3]);
        $ledes_activity_codes = [];
        while ($row = pg_fetch_assoc($res_activity)) {
            $ledes_activity_codes[$row['ledes_activity_code']] = $row['ledes_activity_content'];
        }

        // 5. Fetch Transactions (TR)
        $sql_tr = "SELECT * FROM tr WHERE deb_num = $1 ORDER BY date, id";
        $res_tr = pg_query_params($dblink, $sql_tr, [$deb_num]);
        $transactions = [];
        while ($row = pg_fetch_assoc($res_tr)) {
            $transactions[] = $row;
        }

        // 6. Calculate Fee Earner Summary
        // Logic adapted from bill_edit.pl

        // Calculate Total Internal Amount (for share calculation)
        $sql_total = "SELECT SUM(internal_time * rate * 1000) AS total FROM tr WHERE deb_num = $1";
        $res_total = pg_query_params($dblink, $sql_total, [$deb_num]);
        $total_row = pg_fetch_assoc($res_total);
        $total_internal_amount = $total_row['total'] ? floatval($total_row['total']) : 0;

        // Calculate Work Bonus Total (4% of Legal Services)
        $legal_services = floatval($bill['legal_services']);
        $work_bonus_total = $legal_services * 0.04;

        // Group by Initials and Rate
        $sql_fe = "SELECT 
                    initials, 
                    rate, 
                    SUM(internal_time * rate * 1000) AS in_total, 
                    SUM(internal_time) AS internal, 
                    SUM(bill_time) AS bill 
                   FROM tr 
                   WHERE deb_num = $1 
                   GROUP BY initials, rate 
                   ORDER BY initials";
        $res_fe = pg_query_params($dblink, $sql_fe, [$deb_num]);

        $fee_earner_summary = [];
        while ($row = pg_fetch_assoc($res_fe)) {
            $in_total = floatval($row['in_total']);
            $share = 0;
            if ($total_internal_amount > 0) {
                $share = ($in_total / $total_internal_amount) * 100;
            }

            $share_total = $work_bonus_total * $share / 100;

            $fee_earner_summary[] = [
                'initials' => $row['initials'],
                'rate' => $row['rate'],
                'in_total' => number_format($in_total),
                'bill_hours' => $row['bill'],
                'internal_hours' => $row['internal'],
                'share' => number_format($share, 2) . '%',
                'bonus' => number_format($share_total, 2)
            ];
        }

        return [
            'bill' => $bill,
            'case' => $case,
            'narrative' => $narrative,
            'ledes_codes' => $ledes_codes,
            'ledes_activity_codes' => $ledes_activity_codes,
            'transactions' => $transactions,
            'fee_earner_summary' => $fee_earner_summary,
            'total_internal_amount' => $total_internal_amount
        ];
    } finally {
        if ($dblink) {
            pg_close($dblink);
        }
    }
}

/**
 * 更新帳單資料
 * 整合原本的 LEDES Update 和 Update 功能
 * 
 * @param string $deb_num 帳單編號
 * @param array $postData 表單資料
 * @return array 更新結果
 */
function updateBillData($deb_num, $postData) {
    $dblink = @pg_connect(DB_CONNECT23);
    if (!$dblink) {
        throw new Exception("無法連接到資料庫");
    }

    try {
        // 開始交易
        pg_query($dblink, "BEGIN");

        // 1. 解析表單資料，取得所有 TR ID
        $tr_ids = [];
        foreach ($postData as $key => $value) {
            if (preg_match('/^id_(\d+)$/', $key, $matches)) {
                $tr_ids[] = $matches[1];
            }
        }

        // 2. 更新每筆 TR 紀錄
        foreach ($tr_ids as $id) {
            $show_initials = $postData["show_initials_$id"] ?? null;
            $internal_time = $postData["internal_time_$id"] ?? null;
            $charge = $postData["charge_$id"] ?? null;
            $nar_2 = $postData["nar_2_$id"] ?? null;
            $ledes_code = $postData["ledes_code_$id"] ?? null;
            $ledes_activity_code = $postData["ledes_activity_code_$id"] ?? null;

            // NoCharge 和 Show 標記處理
            // 如果 checkbox 沒有勾選，則不會在 POST 資料中出現
            $nocharge_flag = isset($postData["nocharge_flag_$id"]) ? 1 : -1;
            $show_flag = isset($postData["show_flag_$id"]) ? 1 : -1;

            // 建立更新 SQL
            $sql = "UPDATE tr SET 
                    show_initials = $1,
                    internal_time = $2,
                    charge = $3,
                    nar_2 = $4,
                    ledes_code = $5,
                    ledes_activity_code = $6,
                    nocharge_flag = $7,
                    show_flag = $8
                    WHERE id = $9";

            $result = pg_query_params($dblink, $sql, [
                $show_initials,
                $internal_time,
                $charge,
                $nar_2,
                $ledes_code,
                $ledes_activity_code,
                $nocharge_flag,
                $show_flag,
                $id
            ]);

            if (!$result) {
                throw new Exception("更新 TR 紀錄失敗: ID $id");
            }
        }

        // 3. 取得 Bill 資料
        $sql_bill = "SELECT * FROM bills WHERE deb_num = $1";
        $res_bill = pg_query_params($dblink, $sql_bill, [$deb_num]);
        $bill = pg_fetch_assoc($res_bill);

        if (!$bill) {
            throw new Exception("找不到帳單: $deb_num");
        }

        $case_num = $bill['case_num'];

        // 4. 取得 Case 資料
        $sql_case = "SELECT * FROM cases WHERE case_num = $1";
        $res_case = pg_query_params($dblink, $sql_case, [$case_num]);
        $case = pg_fetch_assoc($res_case);

        // 5. 重新計算 Legal Services
        // 計算未折扣法律服務費 (台幣)
        $sql_legal = "SELECT 
                      COALESCE(SUM(charge * show_rate * 1000), 0) AS legal_services
                      FROM tr 
                      WHERE deb_num = $1 
                      AND billed_flag = 0 
                      AND nocharge_flag = -1 
                      AND show_flag = 1";
        $res_legal = pg_query_params($dblink, $sql_legal, [$deb_num]);
        $legal_row = pg_fetch_assoc($res_legal);

        $legal_services = floatval($legal_row['legal_services']);

        // 外幣法律服務費 = 台幣 / 匯率
        $x_rate2 = floatval($bill['x_rate2']) ?: 1;
        $legal_services_foreign = ($x_rate2 > 0) ? round($legal_services / $x_rate2, 2) : 0;

        // 6. 重新計算 Disbursements
        $sql_disb = "SELECT 
                     COALESCE(SUM(ntd_amount), 0) AS ntd_disbs,
                     COALESCE(SUM(foreign_amount2), 0) AS foreign_disbs
                     FROM disbursements 
                     WHERE deb_num = $1 
                     AND billed_flag = 0 
                     AND nocharge_flag = -1";
        $res_disb = pg_query_params($dblink, $sql_disb, [$deb_num]);
        $disb_row = pg_fetch_assoc($res_disb);

        $disb_total = 0;
        $disb_total_foreign = 0;

        // 如果 case 設定為 includes_disbursements，則不計算支出
        if (!$case['includes_disbursements']) {
            $disb_total = floatval($disb_row['ntd_disbs']);
            $disb_total_foreign = floatval($disb_row['foreign_disbs']);
        }

        // 7. 計算總計
        $billing_currency = $bill['billing_currency'];
        // x_rate2 已在上方計算外幣法律服務費時宣告
        $x_rate = floatval($bill['x_rate']) ?: 1;
        $trans_services = floatval($bill['trans_services']);

        // 如果是外幣帳單，使用匯率換算
        if ($billing_currency == 'English (USD)' || $billing_currency == 'English (EUR)') {
            $legal_services = round($x_rate2 * $legal_services_foreign);
        }

        // 處理 flat_fee
        if ($case['flat_fee']) {
            $flat_fee = floatval($case['flat_fee']);
            $flat_fee_foreign = floatval($case['flat_fee_foreign'] ?? 0);
            $legal_services = $flat_fee;
            $legal_services_foreign = $flat_fee_foreign;
            $total = $disb_total + $flat_fee + $trans_services;
        } else {
            $total = $disb_total + $legal_services + $trans_services;
        }

        $foreign_total = $disb_total_foreign + $legal_services_foreign;
        $usd_total = ($x_rate > 0) ? $total / $x_rate : 0;

        // 8. 更新 Bills 表
        $sql_update_bill = "UPDATE bills SET 
                            undiscounted_legal_services = $1,
                            legal_services = $2,
                            disbs = $3,
                            total = $4,
                            usd_total = $5,
                            foreign_undiscount_legal2 = $6,
                            foreign_disbs2 = $7,
                            foreign_total2 = $8
                            WHERE deb_num = $9";

        $result = pg_query_params($dblink, $sql_update_bill, [
            $legal_services,      // undiscounted_legal_services (同 legal_services，除非有折扣)
            $legal_services,
            $disb_total,
            $total,
            $usd_total,
            $legal_services_foreign,
            $disb_total_foreign,
            $foreign_total,
            $deb_num
        ]);

        if (!$result) {
            throw new Exception("更新 Bills 表失敗");
        }

        // 提交交易
        pg_query($dblink, "COMMIT");

        return [
            'success' => true,
            'message' => '帳單更新成功',
            'updated_count' => count($tr_ids),
            'legal_services' => $legal_services,
            'disbs' => $disb_total,
            'total' => $total
        ];
    } catch (Exception $e) {
        // 回滾交易
        pg_query($dblink, "ROLLBACK");
        throw $e;
    } finally {
        if ($dblink) {
            pg_close($dblink);
        }
    }
}

// API Router - 處理 POST 請求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 禁用 HTML 錯誤輸出，改用 JSON
    ini_set('display_errors', 0);
    error_reporting(E_ALL);

    // 設定錯誤處理器
    set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });

    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'update':
                $deb_num = $_POST['deb_num'] ?? '';
                if (!$deb_num) {
                    throw new Exception("缺少帳單編號");
                }
                $result = updateBillData($deb_num, $_POST);
                echo json_encode($result);
                break;

            default:
                throw new Exception("未知的操作: $action");
        }
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
    exit;
}
