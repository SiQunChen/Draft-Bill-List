<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once("db23.ini");

function getBillData($id, $deb_num) {
    // 建立資料庫連線
    $dblink = @pg_connect(DB_CONNECT23);
    if (!$dblink) {
        throw new Exception("無法連接到資料庫");
    }

    try {
        // 1. 取得帳單詳細資訊（包含案件資訊）
        $sql_bill = "SELECT cases.billing_currency, bills.* 
                     FROM bills 
                     LEFT JOIN cases ON (bills.case_num = cases.case_num) 
                     WHERE id = $1";
        $res_bill = pg_query_params($dblink, $sql_bill, [$id]);
        $bill = pg_fetch_assoc($res_bill);

        if (!$bill) {
            throw new Exception("找不到帳單: ID $id");
        }

        // 根據案件編號前綴判斷 mid_flag
        $case_num = $bill['case_num'];
        $bill['mid_flag'] = 0;
        if (preg_match('/^(MID|NUP|MFN|SNL|MFT)/', $case_num)) {
            $bill['mid_flag'] = 1;
        }

        // 2. 取得 LEDES 代碼
        $case_num3 = substr($case_num, 0, 3);
        $sql_ledes = "SELECT * FROM dis_ledes_code WHERE case_num = $1 ORDER BY dis_ledes_code";
        $res_ledes = pg_query_params($dblink, $sql_ledes, [$case_num3]);
        $dis_ledes_code = [];
        while ($row = pg_fetch_assoc($res_ledes)) {
            $dis_ledes_code[] = $row;
        }

        // 3. 取得代墊費用 (Disbursements)
        $sql_disbs = "SELECT * FROM disbursements WHERE deb_num = $1 ORDER BY id";
        $res_disbs = pg_query_params($dblink, $sql_disbs, [$deb_num]);
        $disbursements = [];
        while ($row = pg_fetch_assoc($res_disbs)) {
            $disbursements[] = $row;
        }

        return [
            'bill' => $bill,
            'disbursements' => $disbursements,
            'dis_ledes_code' => $dis_ledes_code
        ];
    } finally {
        // 確保連線在結束時關閉
        if ($dblink) {
            pg_close($dblink);
        }
    }
}

function updateBill($postData) {
    $dblink = @pg_connect(DB_CONNECT23);
    if (!$dblink) {
        throw new Exception("無法連接到資料庫");
    }

    $id = $postData['bill_id'];
    $deb_num = $postData['deb_num'];

    if (!$id || !$deb_num) {
        throw new Exception("缺少必要參數: bill_id 或 deb_num");
    }

    pg_query($dblink, "BEGIN");

    try {
        // --- 0. 取得帳單資料供計算用 ---
        $sql_get_bill = "SELECT bills.*, cases.billing_currency
                         FROM bills LEFT JOIN cases ON (bills.case_num = cases.case_num)
                         WHERE bills.id = $1";
        $res_get_bill = pg_query_params($dblink, $sql_get_bill, [$id]);
        $current_bill = pg_fetch_assoc($res_get_bill);
        $is_foreign = ($current_bill['billing_currency'] == 'English (USD)' || $current_bill['billing_currency'] == 'English (EUR)');

        // --- 1. 更新 Disbursements ---
        // 先取得該帳單目前所有的 disbursements
        $sql_disbs = "SELECT id FROM disbursements WHERE deb_num = $1";
        $res_disbs = pg_query_params($dblink, $sql_disbs, [$deb_num]);

        $remove_ids = $postData['remove_id'] ?? [];
        $nocharge_ids = $postData['nocharge_id'] ?? [];
        $show_ls_ids = $postData['show_ls_id'] ?? [];

        while ($row = pg_fetch_assoc($res_disbs)) {
            $disb_id = $row['id'];

            // A. 移除 (從帳單中剔除，設 deb_num = NULL, billed_flag = -1)
            if (in_array($disb_id, $remove_ids)) {
                $sql_remove = "UPDATE disbursements SET deb_num = NULL, billed_flag = -1 WHERE id = $1";
                pg_query_params($dblink, $sql_remove, [$disb_id]);
                continue; // 已移除，不需更新後續欄位
            }

            // B. 更新其他欄位
            $updates = [];

            // LEDES Code
            if (isset($postData["ledes_code_{$disb_id}"])) {
                $updates['dis_ledes_code'] = $postData["ledes_code_{$disb_id}"];
            }

            // Flags - 與 edit_disbs 邏輯同步
            if (in_array($disb_id, $nocharge_ids)) {
                $updates['nocharge_flag'] = 1;
                $updates['show_flag'] = -1;
                $updates['billed_flag'] = 2;
            } else {
                $updates['nocharge_flag'] = -1;
                $updates['show_flag'] = 1;
                $updates['billed_flag'] = 0;
            }
            $updates['show_as_legal_service_flag'] = in_array($disb_id, $show_ls_ids) ? 1 : -1;

            pg_update($dblink, 'disbursements', $updates, ['id' => $disb_id]);
        }

        // --- 2. 重新計算 Disbursements 總額 ---
        $sql_new_disbs = "SELECT 
                                COALESCE(SUM(ntd_amount), 0) as total_disbs, 
                                COALESCE(SUM(foreign_amount2), 0) as total_foreign_disbs 
                          FROM disbursements 
                          WHERE billed_flag = 0
                          AND show_flag = 1
                          AND nocharge_flag = -1
                          AND deb_num = $1";
        $res_new_disbs = pg_query_params($dblink, $sql_new_disbs, [$deb_num]);
        $row_new_disbs = pg_fetch_assoc($res_new_disbs);
        $new_disbs_amount = floatval($row_new_disbs['total_disbs']);
        $new_foreign_disbs_amount = floatval($row_new_disbs['total_foreign_disbs']);

        // --- 3. 更新 Bills 表 ---
        // 處理金額欄位，移除可能的 ',' 和 '.00'
        $legal_services = str_replace(',', '', $postData['legal_services'] ?? '0');
        $discount = str_replace(',', '', $postData['discount'] ?? '0');
        $mid_bill_type = $postData['mid_type'] ?? 0;
        $bill_narrative = $postData['bill_narrative'] ?? '';
        $remark = $postData['notes'] ?? '';

        // 計算 Total (User Input Legal Service + Calculated Disbs - User Input Discount)
        $total = floatval($legal_services) + $new_disbs_amount - floatval($discount);

        // 準備更新欄位
        $update_fields = [
            'mid_bill_type' => $mid_bill_type,
            'legal_services' => $legal_services,
            'disbs' => $new_disbs_amount, // 使用重新計算的總額
            'discount' => $discount,
            'total' => $total, // 使用重新計算的 total
            'bill_narrative' => $bill_narrative,
            'remark' => $remark
        ];

        // 處理外幣欄位
        if ($is_foreign) {
            // 外幣帳單：從表單取得 foreign_legal
            if (isset($postData['foreign_legal'])) {
                $update_fields['foreign_legal2'] = str_replace(',', '', $postData['foreign_legal']);
            }
        } else {
            // 台幣帳單：foreign_legal2 = legal_services / x_rate
            $x_rate = floatval($current_bill['x_rate']) ?: 1;
            $update_fields['foreign_legal2'] = round(floatval($legal_services) / $x_rate, 2);
        }
        $update_fields['foreign_disbs2'] = $new_foreign_disbs_amount;

        // 計算 foreign_total2
        $foreign_legal2_val = $update_fields['foreign_legal2'] ?? floatval($current_bill['foreign_legal2']);
        $update_fields['foreign_total2'] = $foreign_legal2_val + $new_foreign_disbs_amount;

        $msg = pg_update($dblink, 'bills', $update_fields, ['id' => $id]);
        if ($msg === false) {
            throw new Exception("更新 bills 失敗");
        }

        pg_query($dblink, "COMMIT");
        return true;
    } catch (Exception $e) {
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
                $id = $_POST['bill_id'] ?? '';
                if (!$id) {
                    throw new Exception("缺少 Bill ID");
                }
                updateBill($_POST);
                echo json_encode([
                    'success' => true,
                    'message' => 'Bill updated successfully!'
                ]);
                break;

            default:
                throw new Exception("未知的操作: $action");
        }
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'file' => $e->getFile(), // Debug purpose
            'line' => $e->getLine()  // Debug purpose
        ]);
    }
    exit;
}
