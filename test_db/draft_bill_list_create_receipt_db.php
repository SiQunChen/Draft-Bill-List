<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// 回傳 JSON 格式
header('Content-Type: application/json; charset=utf-8');

require_once("db23.ini");
require_once("draft_bill_list_db.php"); // 引入 getData() 函數

// PHPMailer 引入
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\OAuth;
use League\OAuth2\Client\Provider\Google as GoogleProvider;

require_once('../vendor/autoload.php');

// --- 主程式開始 ---
try {
    // 1. 取得 POST 參數
    $initials = isset($_POST['initials']) ? trim($_POST['initials']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $bill_ids = isset($_POST['bill_ids']) ? $_POST['bill_ids'] : [];

    // 2. 驗證必填欄位
    if (empty($initials)) {
        throw new Exception("Initials 為必填欄位");
    }

    if (empty($bill_ids) || !is_array($bill_ids)) {
        throw new Exception("請至少勾選一筆帳單");
    }

    // 3. 使用 getData() 函數取得完整帳單資料
    // 參數：case_number='', match_or_like='match', case_manager='', sort_key='case_num', sort_order='ASC', target_ids
    $result_data = getData('', 'match', '', 'case_num', 'ASC', $bill_ids);

    if (empty($result_data['rows'])) {
        throw new Exception("無法取得帳單資料，請重新選擇");
    }

    // 4. 從查詢結果中提取 deb_num 和 case_num
    $deb_nums = [];
    $case_nums = [];

    foreach ($result_data['rows'] as $row) {
        $deb_nums[] = $row['deb_num'];
        $case_nums[] = $row['case_num'];
    }

    // 5. 連接資料庫（用於寫入資料）
    $dblink = @pg_connect(DB_CONNECT23);
    if (!$dblink) {
        throw new Exception("無法連接到資料庫");
    }

    // 5. INSERT 到 receipt_sec 表 (主檔)
    $sql_insert_sec = "INSERT INTO receipt_sec(initials, note) VALUES($1, $2)";
    $result_sec = pg_query_params($dblink, $sql_insert_sec, [$initials, $notes]);

    if (!$result_sec) {
        throw new Exception("寫入 receipt_sec 失敗: " . pg_last_error($dblink));
    }

    // 6. 取得新建的 sec_id
    $sql_get_id = "SELECT currval('receipt_sec_id_seq')";
    $result_id = pg_query($dblink, $sql_get_id);

    if (!$result_id || !($row_id = pg_fetch_array($result_id))) {
        throw new Exception("無法取得申請單號");
    }

    $sec_id = $row_id[0];

    // 7. 批次 INSERT 到 receipt_sec_deb 表 (明細)
    $display_deb_num = '';
    foreach ($deb_nums as $index => $deb_num) {
        $case_num = $case_nums[$index];

        $sql_insert_deb = "INSERT INTO receipt_sec_deb(sec_id, deb_num) VALUES($1, $2)";
        $result_deb = pg_query_params($dblink, $sql_insert_deb, [$sec_id, $deb_num]);

        if (!$result_deb) {
            throw new Exception("寫入 receipt_sec_deb 失敗: " . pg_last_error($dblink));
        }

        $display_deb_num .= $case_num . ' , ' . $deb_num . "\n";
    }

    // 8. 發送 Email 通知
    $email_status = sendReceiptEmail($sec_id, $initials, $notes, $display_deb_num);

    // 9. 關閉資料庫連線
    pg_close($dblink);

    // 10. 回傳成功結果
    $message = $email_status ? '郵件發送成功' : '郵件發送失敗，但資料已儲存';
    echo json_encode([
        'success' => true,
        'sec_id' => $sec_id,
        'message' => $message
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}


/**
 * 發送收據申請通知 Email
 * 
 * @param int $sec_id 申請單號
 * @param string $initials 申請人縮寫
 * @param string $notes 備註
 * @param string $display_deb_num 帳單列表字串
 * @return bool 發送是否成功
 */
function sendReceiptEmail($sec_id, $initials, $notes, $display_deb_num) {
    // 檢查 PHPMailer 類別是否可用
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer') && !class_exists('PHPMailer')) {
        error_log("PHPMailer class not found. Email not sent for sec_id: {$sec_id}");
        return false;
    }

    // 將換行符轉換為 HTML 格式
    $notes_html = nl2br(htmlspecialchars($notes));
    $display_html = nl2br(htmlspecialchars($display_deb_num));

    $body = "<html lang=\"\">
                <head>
                    <meta charset=\"utf-8\">
                </head>
                <body>Hi Frances,<BR><BR> 
                {$initials} 申請開立收據，申請單號為  {$sec_id} ，案號及帳單號碼如下：<BR>{$display_html} <BR>
                備註：{$notes_html}
                </body>
            </html>";

    $subject = "申請開立收據開立通知信。申請人：{$initials} ，申請單號：{$sec_id}";
    $applicant_email = strtolower($initials) . '@winklerpartners.com';

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->Port = 465;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->SMTPAuth = true;
        $mail->AuthType = 'XOAUTH2';

        $email = 'phsiao@winklerpartners.com';
        $clientId = '244757155540-a7gj7buc0bi2ogm55a664ducq5f96jg7.apps.googleusercontent.com';
        $clientSecret = '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';
        $refreshToken = '1//0esOVUfkQpAOXCgYIARAAGA4SNwF-L9IrMEkQo1mHakfbpUyN6EYw8Ndr7egUUdTU2i0LbR_SNPvs4RjKTNcWG95PHK7XcBRJ2Jo';

        // 檢查 OAuth 相關類別
        if (class_exists('League\OAuth2\Client\Provider\Google') && class_exists('PHPMailer\PHPMailer\OAuth')) {
            $provider = new \League\OAuth2\Client\Provider\Google([
                'clientId' => $clientId,
                'clientSecret' => $clientSecret,
            ]);

            $mail->setOAuth(new \PHPMailer\PHPMailer\OAuth([
                'provider' => $provider,
                'clientId' => $clientId,
                'clientSecret' => $clientSecret,
                'refreshToken' => $refreshToken,
                'userName' => $email,
            ]));
        } else {
            // OAuth 類別不存在，記錄錯誤
            error_log("OAuth classes not found. Email not sent for sec_id: {$sec_id}");
            return false;
        }

        $mail->setFrom($email, 'Pon Hsiao');
        $mail->addAddress('phsiao@winklerpartners.com', 'Pon Hsiao');
        $mail->addAddress('fchang@winklerpartners.com', 'Frances');
        $mail->addAddress($applicant_email, $initials);

        $mail->CharSet = "utf-8";
        $mail->Encoding = "base64";
        $mail->IsHTML(true);
        $mail->WordWrap = 50;

        $mail->Subject = $subject;
        $mail->Body = $body;

        return $mail->Send();
    } catch (\Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}
