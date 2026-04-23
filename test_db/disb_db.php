<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\OAuth;
use League\OAuth2\Client\Provider\Google;

if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 0);
    session_set_cookie_params(86400);
    session_start();
}
require("db23.ini");

function beginEnter() {
    $date = ('Y-m-d');
    $sql = "SELECT initials FROM hr 
            WHERE status = 1
            ORDER BY initials";
    $dblink23 = @pg_pconnect(DB_CONNECT23);
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗: $error";
        exit;
    }
    $ar_initials = pg_fetch_all($result);

    $sql = "SELECT disb_name, disb_code 
             FROM disb 
             WHERE disb_code >= 200
             ORDER BY disb_code";
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗: $error";
        exit;
    }
    $disb_codes = pg_fetch_all($result);
    foreach ($disb_codes as &$code) {
        $code['disb_code'] = preg_replace('/^1/', '2', $code['disb_code']);
    }

    $search_year = date('Y');
    $search_month = date('m');

    $sql = "SELECT ntd FROM xrate WHERE year='$search_year' AND month='$search_month'";
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗: $error";
        exit;
    }
    $data = pg_fetch_assoc($result);
    $xrate_usd = $data['ntd'];

    $sql = "SELECT ntd FROM xrate_eur WHERE year='$search_year' AND month='$search_month'";
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗: $error";
        exit;
    }
    $data = pg_fetch_assoc($result);
    $xrate_eur = $data['ntd'];

    $final_arr = [
        'people' => $ar_initials,
        'disb_codes' => $disb_codes,
        'xrate_usd' => $xrate_usd,
        'xrate_eur' => $xrate_eur
    ];
    return $final_arr;
}

function getBillInfo($deb_num) {
    $dblink23 = @pg_pconnect(DB_CONNECT23);
    $sql = "SELECT case_num FROM bills WHERE deb_num = '$deb_num'";
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        return false;
    }
    return pg_fetch_assoc($result);
}

/**
 * 新增支出記錄 (支援多筆案號)
 * @param array $data 支出資料陣列
 * @return string 處理結果訊息
 */
function insertDisb($data) {
    $dblink23 = @pg_pconnect(DB_CONNECT23);

    // 驗證必填欄位
    if (substr($data['disb_code'], 0, 1) !== '2') {
        return "Error: Please begin all codes with 2.";
    }
    if (empty($data['initials'])) {
        return "You did not enter your initials. Please fill in the Initials field.";
    }

    // 解析多筆案號
    $case_num_list = parseCaseNumbers(strtoupper(trim($data['case_num'])));
    if (empty($case_num_list)) {
        return "Error: case number input data error";
    }

    // 處理每筆案號
    $results = ['success' => 0, 'fail' => 0, 'messages' => []];
    foreach ($case_num_list as $case_num) {
        $data['case_num'] = $case_num;
        $msg = processSingleDisb($dblink23, $data);

        if (strpos($msg, 'successfully') !== false) {
            $results['success']++;
        } else {
            $results['fail']++;
        }
        $results['messages'][] = $msg;
    }

    // 回傳結果
    if (count($case_num_list) == 1) {
        return $results['messages'][0];
    }
    return sprintf(
        "Total: %d cases. Success: %d, Failed: %d. %s",
        count($case_num_list),
        $results['success'],
        $results['fail'],
        implode(" | ", $results['messages'])
    );
}

/**
 * 解析案號 (支援範圍和列表)
 * @param string $input 案號輸入
 * @return array 案號列表
 */
function parseCaseNumbers($input) {
    $list = [];

    // 使用 '~' 表示範圍
    if (strpos($input, '~') !== false) {
        list($start, $end) = array_map('trim', explode('~', $input));
        $end = (int)$end;

        if (strpos($start, '-') === false) {
            // 格式: ABC123~125
            $prefix = preg_replace('/\d+$/', '', $start);
            $begin = (int)preg_replace('/^[a-zA-Z]*0*/', '', $start);
        } else {
            // 格式: ABC-1~5
            $parts = explode('-', $start);
            $begin = (int)array_pop($parts);
            $prefix = implode('-', $parts) . '-';
        }

        for ($i = $begin; $i <= $end; $i++) {
            $list[] = $prefix . $i;
        }
    }
    // 使用 ',' 或空白分隔
    elseif (preg_match('/[,\s]/', $input)) {
        foreach (preg_split('/[,\s]+/', $input) as $part) {
            if (($part = trim($part)) !== '') {
                $list[] = $part;
            }
        }
    }
    // 單一案號
    else {
        $list[] = $input;
    }

    return $list;
}

/**
 * 解析日期取得年月
 * @param string $date 日期字串
 * @return array [year, month]
 */
function parseDateYearMonth($date) {
    $patterns = [
        '/^(\d{4})-(\d{1,2})/' => [1, 2],
        '/^(\d{4})\/(\d{1,2})/' => [1, 2],
    ];

    foreach ($patterns as $pattern => $indices) {
        if (preg_match($pattern, $date, $m)) {
            return [(int)$m[$indices[0]], (int)$m[$indices[1]]];
        }
    }

    if (strlen($date) >= 6) {
        return [(int)substr($date, 0, 4), (int)substr($date, 4, 2)];
    }

    return [(int)date('Y'), (int)date('m')];
}



/**
 * 處理單筆支出記錄
 */
function processSingleDisb($dblink23, $data) {
    $case_num = $data['case_num'];
    $escaped_case_num = pg_escape_string($dblink23, $case_num);

    // 1. 檢查案件狀態
    $sql = "SELECT * FROM cases WHERE case_num = '$escaped_case_num'";
    $result = @pg_query($dblink23, $sql);
    if (!$result || pg_num_rows($result) != 1) {
        return "Error: $case_num is not a valid case number.";
    }
    $case = pg_fetch_assoc($result);

    if (!empty($case['case_close_date'])) {
        return "$case_num: This case number closed already.";
    }

    // 2. 調整 disb_code
    $disb_code = $data['disb_code'];
    if (empty($case['bill_country'])) {
        $disb_code = preg_replace('/^2/', '1', $disb_code);
    }

    // 3. 檢查發票號碼重複
    if (!empty($data['counsel_invoice'])) {
        $escaped_invoice = pg_escape_string($dblink23, $data['counsel_invoice']);
        $sql = "SELECT 1 FROM disbursements WHERE counsel_invoice = '$escaped_invoice' AND case_num = '$escaped_case_num' AND disb_code = '$disb_code' LIMIT 1";
        $result = @pg_query($dblink23, $sql);
        if ($result && pg_num_rows($result) >= 1) {
            return "$case_num: This Invoice number duplicate.";
        }
    }

    // 4. 驗證 disb_code
    $sql = "SELECT disb_name FROM disb WHERE disb_code = '$disb_code'";
    $result = @pg_query($dblink23, $sql);
    if (!$result || pg_num_rows($result) != 1) {
        return "$disb_code is not a valid disbursement code.";
    }
    $disb_name = pg_fetch_result($result, 0, 'disb_name');

    // 5. 計算 currency2/foreign_amount2
    $currency2 = $data['currency2'];
    $foreign_amount2 = $data['foreign_amount2'];
    if (empty($currency2)) {
        list($year, $month) = parseDateYearMonth($data['date']);
        $is_eur = ($case['billing_currency'] == 'English (EUR)');
        $x_rate = $is_eur ? getXRateEur($dblink23, $year, $month) : getXRate($dblink23, $year, $month);
        $currency2 = $is_eur ? 'EUR' : 'USD';
        $foreign_amount2 = round($data['ntd_amount'] / $x_rate, 2);
    }

    // 6. 處理 nocharge_flag, show_flag, billed_flag
    if ($data['nocharge_flag'] == 1) {
        $nocharge_flag = 1;
        $show_flag = -1;
        $billed_flag = 2;
    } else {
        $nocharge_flag = -1;
        $show_flag = 1;
        $billed_flag = 0;
    }

    // 7. 組裝參數
    $params = [
        'deb_num' => $data['deb_num'],
        'case_num' => $case_num,
        'date' => $data['date'],
        'disb_code' => $disb_code,
        'disb_name' => $disb_name,
        'ntd_amount' => $data['ntd_amount'],
        'notes' => $data['notes'] ?? '',
        'currency' => $data['currency'] ?? '',
        'foreign_amount' => $data['foreign_amount'] ?? 0,
        'x_rate' => (!empty($data['currency'])) ? ($data['x_rate'] ?? 0) : 0,
        'currency2' => $currency2,
        'foreign_amount2' => $foreign_amount2,
        'counsel_area' => $data['counsel_area'] ?? '',
        'counsel_name' => $data['counsel_name'] ?? '',
        'invoice_date' => $data['invoice_date'] ?? '',
        'counsel_invoice' => $data['counsel_invoice'] ?? '',
        'show_as_legal_service_flag' => $data['show_as_legal_service_flag'],
        'paydate' => $data['paydate'] ?? '',
        'bpm_date' => $data['bpm_date'] ?? '',
        'initials' => $data['initials'],
        'narrative' => $data['narrative'] ?? '',
        'nocharge_flag' => $nocharge_flag,
        'show_flag' => $show_flag,
        'billed_flag' => $billed_flag,
        'dis_case_manager' => $case['case_manager'],
        'dis_partner' => $case['partner'],
        'dis_partner2' => $case['partner2'],
    ];

    // 有 disbs_id_relation 時加入
    if (!empty($data['disbs_id_relation'])) {
        $params['disbs_id_relation'] = $data['disbs_id_relation'];
    }

    // 8. 執行插入
    $is_translation = in_array($disb_code, ['131', '231']);
    if ($is_translation) {
        $params['total'] = $data['rate'] * $data['num_of_chars'];
        $params['rate'] = $data['rate'];
        $params['num_of_chars'] = $data['num_of_chars'];
        $rv = ins_statement('translations', $params, $dblink23);
    } else {
        $rv = ins_statement('disbursements', $params, $dblink23);
    }

    if ($rv != 1) {
        return "Failed to insert $case_num the disbursement.";
    }

    $msg = "You successfully inserted $case_num the disbursement.";



    // 10. 自動關聯草稿帳單
    if ($data['mode'] == 'new_dis' && empty($data['deb_num'])) {
        $msg .= autoLinkToDraftBill($dblink23, $case, $case_num, $data['date']);
    }

    // 11. 檢查重複
    if (in_array($disb_code, ['110', '210'])) {
        $msg .= checkDuplicateDisb($dblink23, $case_num, $disb_code, $data['ntd_amount']);
    }

    return $msg;
}

/**
 * 自動關聯到草稿帳單
 */
function autoLinkToDraftBill($dblink23, $case, $case_num, $date) {
    $target_managers = ['MD', 'GK', 'PO', 'SE'];
    if (!in_array($case['case_manager'], $target_managers)) {
        return '';
    }

    $escaped = pg_escape_string($dblink23, $case_num);
    $sql = "SELECT deb_num, draft_created FROM bills WHERE case_num = '$escaped' AND sent IS NULL AND bill_status = '0' ORDER BY id DESC LIMIT 1";
    $result = @pg_query($dblink23, $sql);

    if (!$result || pg_num_rows($result) != 1) {
        return '';
    }

    $bill = pg_fetch_assoc($result);
    $draft_time = strtotime($bill['draft_created']);
    $disb_time = strtotime($date);

    if ($draft_time === false || $disb_time === false || $draft_time < $disb_time) {
        return '';
    }

    // 取得剛插入的 ID
    $seq_result = @pg_query($dblink23, "SELECT last_value FROM disbursements_id_seq");
    if (!$seq_result) return '';

    $dis_id = pg_fetch_result($seq_result, 0, 'last_value');
    $deb_num_bills = $bill['deb_num'];
    $msg = '';



    // 更新 disbursement
    $sql = "UPDATE disbursements SET deb_num = '$deb_num_bills', billed_flag = '0' WHERE id = '$dis_id'";
    if (@pg_query($dblink23, $sql)) {
        $msg .= " Disbursement linked to $deb_num_bills.";
    }

    return $msg;
}

/**
 * 檢查重複支出
 */
function checkDuplicateDisb($dblink23, $case_num, $disb_code, $ntd_amount) {
    $escaped = pg_escape_string($dblink23, $case_num);
    $sql = "SELECT date FROM disbursements WHERE case_num = '$escaped' AND disb_code = '$disb_code' AND ntd_amount = '$ntd_amount' AND nocharge_flag = '-1' ORDER BY id DESC LIMIT 1 OFFSET 1";
    $result = @pg_query($dblink23, $sql);

    if ($result && pg_num_rows($result) == 1) {
        return " Warning: Data duplicate. Date: " . pg_fetch_result($result, 0, 'date');
    }
    return '';
}

// 取得 USD 匯率
function getXRate($dblink23, $year, $month) {
    $month = str_pad($month, 2, '0', STR_PAD_LEFT);
    $sql = "SELECT ntd FROM xrate WHERE year = '$year' AND month = '$month'";
    $result = @pg_query($dblink23, $sql);
    if ($result) {
        $row = pg_fetch_assoc($result);
        if ($row && isset($row['ntd'])) {
            return (float)$row['ntd'];
        }
    }
    return 30; // 預設匯率
}

// 取得 EUR 匯率
function getXRateEur($dblink23, $year, $month) {
    $month = str_pad($month, 2, '0', STR_PAD_LEFT);
    $sql = "SELECT ntd FROM xrate_eur WHERE year = '$year' AND month = '$month'";
    $result = @pg_query($dblink23, $sql);
    if ($result) {
        $row = pg_fetch_assoc($result);
        if ($row && isset($row['ntd'])) {
            return (float)$row['ntd'];
        }
    }
    return 33; // 預設匯率
}

function ins_statement($table, $params, $dblink23) {
    $col_list = '';
    $value_list = '';
    $bind_values = array();
    $iam_country_all = [];
    $iam_matters_all = [];
    $iam_item_all = [];


    foreach ($params as $key => $value) {
        if (is_numeric($value) && $value === '') {
            $value = '0';
        }
        if ($value === '') {
            $value = 'NULL';
        }

        if (isset($value)) {
            if (($key == 'paydate' && $value == 'NULL') ||
                ($key == 'bpm_date' && $value == 'NULL') ||
                ($key == 'invoice_date' && $value == 'NULL') ||
                $key == 'id'
            ) {
                continue;
            }
            if ($table == 'tr' && ($key == 'billing_currency' || $key == 'tr_status' || $key == 'tr_submit' || $key == 'ati_cate1' || $key == 'ati_cate2' || $key == 'ati_cate12' || $key == 'ati_cate22' || $key == 'ati_cate13' || $key == 'ati_cate23' || $key == 'azn_budget_status' || $key == 'dispute_outcome')) {
                continue;
            }
            if ($table == 'cases' && ($key == 'showati23' || $key == 'showati12' || $key == 'showati22' || $key == 'action')) {
                continue;
            }
            $col_list .= ",$key";
            $value_list .= ',' . ($value === 'NULL' ? $value : '\'' . pg_escape_string($dblink23, $value) . '\'');
        }
    }

    if ($table == 'cases') {
        $col_list .= ",iam_country_str,iam_matters_str,iam_item_str";
        $value_list .= ',\'' . pg_escape_string($dblink23, $iam_country_all[$params['iam_country']]) . '\',';
        $value_list .= '\'' . pg_escape_string($dblink23, $iam_matters_all[$params['iam_matters']]) . '\',';
        $value_list .= '\'' . pg_escape_string($dblink23, $iam_item_all[$params['iam_item']]) . '\'';
    }

    $col_list = ltrim($col_list, ',');
    $value_list = ltrim($value_list, ',');

    $statement = "INSERT INTO $table ($col_list) VALUES ($value_list)";
    $result = pg_query($dblink23, $statement);


    if ($result) {
        $rv = pg_affected_rows($result);
    } else {
        echo "Error inserting data: " . pg_last_error($dblink23);
        $rv = 0;
    }

    return $rv;
}


function getDisbList($date_type, $case_num, $deb_num, $fee_earners, $disb_name, $unbill, $case_like, $deb_like, $sort_key, $order_like, $bpm_appnum, $start, $end) {
    $dblink23 = @pg_pconnect(DB_CONNECT23);
    $sql = '';
    $sql_num = '';
    $sql_num_pay = '';
    $my_order = " ORDER BY date DESC,case_num ";
    $sql_initial = '';
    $sql_initial_pay = '';
    $sql_disb_name = '';
    $sql_disb_name_pay = '';
    $sql_unbill = '';
    $sql_date = '';
    $sql_bpm_appnum = '';
    $where_and = ' WHERE ';

    if ($case_num != '') {
        if ($case_like == 'Like') {
            $sql_num = $where_and . "case_num LIKE '$case_num%' ";
            $where_and = ' AND ';
        } else {
            $sql_num = $where_and . "case_num = '$case_num'  ";
            $where_and = ' AND ';
        }
    } elseif ($deb_num != '') {
        if ($deb_like == 'Like') {
            $sql_num = $where_and . "disbursements.deb_num LIKE '$deb_num%'  ";
            $where_and = ' AND ';
        } else {
            $sql_num = $where_and . "disbursements.deb_num = '$deb_num'  ";
            $where_and = ' AND ';
        }
    }
    if ($bpm_appnum != '') {
        $sql_num = $sql_num . $where_and . "disbursements.bpm_appnum ='$bpm_appnum' ";
        $where_and = ' AND ';
    }

    $sql_order = 'DESC';
    if ($order_like == 'Forward') {
        $sql_order = ' ';
    }
    if ($sort_key == 'date') {
        $my_order = " ORDER BY date $sql_order,case_num ";
    } elseif ($sort_key == 'case_num') {
        $my_order = " ORDER BY case_num $sql_order,date $sql_order";
    } elseif ($sort_key == 'deb_num') {
        $my_order = " ORDER BY deb_num $sql_order,date $sql_order";
    }

    if ($start != '' && $end != '') {
        $sql_date = $where_and . "$date_type BETWEEN '$start' AND '$end'";
        $where_and = ' AND ';
    } elseif ($start != '' && $end == '') {
        $sql_date = $where_and . "$date_type >= '$start' ";
        $where_and = ' AND ';
    } elseif ($start == '' && $end != '') {
        $sql_date = $where_and . "$date_type <= '$end' ";
        $where_and = ' AND ';
    }

    if ($date_type == 'only') {
        $sql_date = preg_replace('/only/', 'paydate', $sql_date);
    }

    $fee_earners = strtoupper($fee_earners);
    if ($fee_earners != 'ALL') {
        $sql_initial = $where_and . " initials='$fee_earners' ";
        $sql_initial_pay = " AND initials='$fee_earners' ";
        $where_and = ' AND ';
    }

    if ($disb_name != 0) {
        $e_d = '1' . $disb_name;
        $c_d = '2' . $disb_name;
        $sql_disb_name = $where_and . "(disb_code = '$e_d' or disb_code = '$c_d')   ";
        $sql_disb_name_pay = " AND (disb_code = '$e_d' or disb_code = '$c_d')   ";
        $where_and = ' AND ';
    }

    if ($unbill == 1) {
        $sql_unbill = $where_and . "( disbursements.deb_num is NULL OR disbursements.deb_num ='' ) AND billed_flag='-1' ";
        $where_and = ' AND ';
    }

    $payment_voucher = [];
    if ($date_type == 'voucher_date') {
        $sql = "SELECT disbursements.*,disbs_payments.voucher_date,disbs_payments.pay_amount FROM disbs_payments LEFT JOIN disbursements ON
             ( disbs_payments.disbs_ref_id=disbursements.id   )
             $sql_num $sql_date 
             $sql_initial_pay 
             $sql_disb_name_pay 
             $my_order";
    } elseif ($date_type == 'paydate') {
        $sql_disbs = ' ';
        $sql_num_change = $sql_num;
        $sql_num_change = preg_replace('/case_num/', 'disbursements.case_num', $sql_num_change);
        $sql = "SELECT disbursements.id,disbursements.deb_num,disbursements.case_num,disbursements.date,disbursements.disb_name,disbursements.ntd_amount,disbursements.initials,disbursements.nocharge_flag,disbursements.show_as_legal_service_flag,disbursements.paydate,payments.voucher_date FROM disbursements LEFT JOIN payments ON (disbursements.deb_num=payments.deb_num)
                     $sql_num_change
                 $sql_date 
                 $sql_initial 
                 $sql_disb_name 
                     $sql_unbill 
                     $sql_disbs
                     GROUP BY disbursements.id,disbursements.deb_num,disbursements.case_num,disbursements.date,disbursements.disb_name,disbursements.ntd_amount,disbursements.initials,disbursements.nocharge_flag,disbursements.show_as_legal_service_flag,disbursements.paydate,payments.voucher_date
                     $my_order";
    } else {
        if ($sql_num == '' &&  $sql_date == '' &&  $sql_initial == '' &&  $sql_disb_name == '' && $sql_unbill == '') {
            $year = date('Y');
            $start = date('Y-m-d');
            $end = $start;
            $sql_date = $where_and . "$date_type BETWEEN '$start' AND '$end'";
            $where_and = ' AND ';
        }
        $sql = "SELECT * FROM disbursements 
                     $sql_num 
                 $sql_date 
                 $sql_initial 
                 $sql_disb_name 
                     $sql_unbill 
                     $my_order";
    }
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗: $error";
        exit;
    }
    $rows = pg_num_rows($result);

    $DISB = [];
    while ($disb = pg_fetch_assoc($result)) {
        array_push($DISB, $disb);
    }

    if ($date_type == 'voucher_date') {
        $sql = "SELECT SUM(disbursements.ntd_amount) FROM disbs_payments LEFT JOIN disbursements ON
             ( disbs_payments.disbs_ref_id=disbursements.id   )
             $sql_num $sql_date 
             $sql_initial_pay 
             $sql_disb_name_pay";
    } else {
        $sql = "SELECT SUM(ntd_amount) FROM disbursements
             $sql_num 
             $sql_date 
             $sql_initial
             $sql_disb_name
             $sql_unbill 
             $where_and
             nocharge_flag = -1
             AND show_flag = 1";
    }
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗: $error";
        exit;
    }
    $row = pg_fetch_row($result);
    $ntd_total_actual = $row[0];

    if ($date_type == 'voucher_date') {
        $sql_num = preg_replace('/disbursements/', 'disbs_payments', $sql_num, 1);
        $sql = "SELECT SUM(pay_amount) FROM disbs_payments
                     $sql_num  $sql_date 
                     $sql_initial_pay 
                     $sql_disb_name_pay";
    } else {
        $sql = "SELECT SUM(ntd_amount) FROM disbursements
             $sql_num 
             $sql_date 
             $sql_initial
             $sql_disb_name
             $sql_unbill";
    }
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗: $error";
        exit;
    }
    $row = pg_fetch_row($result);
    $ntd_total = $row[0];
    $hr_list = getHrList($dblink23);
    array_unshift($hr_list, 'ALL');
    $arr = getDisbCode($dblink23);
    $disb_code_list = $arr[0];
    $disb_name_list = $arr[1];
    array_unshift($disb_name_list, 'ALL');
    array_unshift($disb_code_list, '0');

    $final_arr = [
        'disbs' => $DISB,
        'ntd_total' => $ntd_total,
        'ntd_total_actual' => $ntd_total_actual,
        'records' => $rows,
        'hr_list' => $hr_list,
        'disb_name_list' => $disb_name_list,
        'disb_code_list' => $disb_code_list
    ];
    return $final_arr;
}


function getHrList($dblink23) {
    $sql  = "SELECT initials FROM hr WHERE status =1 ORDER BY initials";
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗: $error";
        exit;
    }
    $hr_list = [];
    while ($row = pg_fetch_assoc($result)) {
        array_push($hr_list, $row);
    }
    return $hr_list;
}

function getDisbCode($dblink23) {
    $sql  = "SELECT disb_code,disb_name FROM disb WHERE disb_code >= '100' AND disb_code < '200'  AND disb_name !='' ORDER BY disb_name";
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗: $error";
        exit;
    }
    $i = 0;
    $disb_name_list = [];
    $disb_code_list = [];
    while ($rows = pg_fetch_assoc($result)) {
        list($item0, $item1) = $rows;
        array_push($disb_name_list, $item1);
        $item0 = preg_replace('/^1/', '', $item0);
        array_push($disb_code_list, $item0);
    }
    $arr = [$disb_code_list, $disb_name_list];
    return $arr;
}

function deleteDisb($id, $deb_num, $case_num, $start, $end) {
    $dblink23 = @pg_pconnect(DB_CONNECT23);
    $sql = "SELECT * FROM disbursements WHERE id = '$id'";
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗1: $error";
        exit;
    }
    $disbs = pg_fetch_assoc($result);

    $sql = del_statement('disbursements', $id, $dblink23);
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗2: $error";
        exit;
    }

    $sql = "UPDATE bills SET disbs= 
    (SELECT sum(ntd_amount) FROM disbursements WHERE deb_num='$deb_num' AND nocharge_flag='-1' AND show_flag='1') WHERE deb_num='$deb_num'";
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗3: $error";
        exit;
    }

    $addr = $_SESSION['ip'];
    $ip_addr = ip_addr();
    $disbs['ip_name'] = $ip_addr[$addr];
    $disbs['ip_addr'] = $addr;
    mailTo($disbs, [], 'disbs_delete');

    if ($deb_num != '') {
        $sql = "SELECT SUM (ntd_amount) FROM
            disbursements
            WHERE billed_flag = 0
            AND show_flag = 1
            AND deb_num = '$deb_num'";
        $result = @pg_query($dblink23, $sql);
        if (!$result) {
            $error = pg_last_error($dblink23);
            echo "SQL 執行失敗4: $error";
            exit;
        }
        $disb = pg_fetch_assoc($result);
        $sum = (isset($disb['sum'])) ? $disb['sum'] : 0;

        $sql = "UPDATE bills SET disbs= $sum
             WHERE deb_num = '$deb_num'";
        $result = @pg_query($dblink23, $sql);
        if (!$result) {
            $error = pg_last_error($dblink23);
            echo "SQL 執行失敗5: $error";
            exit;
        }
    }
}

function del_statement($table, $id, $dblink23) {
    $p_key = get_p_key($dblink23, $table);
    $statement = "DELETE FROM $table WHERE $p_key = $id";
    return $statement;
}

function get_p_key($dblink23, $table) {
    $sql = "
        SELECT a.attname as column_name,
       format_type(a.atttypid, a.atttypmod) as data_type,
       i.indisprimary as is_primary,
       a.attnum as column_position
        FROM   pg_index i
        JOIN   pg_attribute a ON a.attnum = ANY(i.indkey)
            AND a.attrelid = i.indrelid
        WHERE  i.indrelid = '"  . pg_escape_string($table) . "'::regclass
        AND    i.indisprimary
        ORDER BY a.attnum;
    ";
    $result = @pg_query($dblink23, $sql);
    $primaryKey = null;
    $re = pg_fetch_all($result);
    $primaryKey = (isset($re[0]['column_name'])) ? $re[0]['column_name'] : $primaryKey;

    return $primaryKey;
}

function ip_addr() {
    $ip_addr = [];

    $ip_addr['192.168.0.87'] = 'PH';
    $ip_addr['192.168.0.110'] = 'is-bak1';
    $ip_addr['192.168.0.111'] = 'is-bak2-x270';
    $ip_addr['192.168.0.112'] = 'is-bak3-x270';
    $ip_addr['192.168.0.113'] = 'is-bak4-x270';
    $ip_addr['192.168.0.114'] = 'is-bak5-x270';
    $ip_addr['192.168.0.115'] = 'is-bak6-x270';
    $ip_addr['192.168.0.116'] = 'is-bak-t450';
    $ip_addr['192.168.0.117'] = 'is-bak7-x280';
    $ip_addr['192.168.0.118'] = 'is-bak8-x270';
    $ip_addr['192.168.0.119'] = 'is-bak9-x270';
    $ip_addr['192.168.0.120'] = 'is-bak7-x260';
    $ip_addr['192.168.0.121'] = 'is-bak10';
    $ip_addr['192.168.0.122'] = 'is-bak11-x390';
    $ip_addr['192.168.0.123'] = 'fc-t480-nb';
    $ip_addr['192.168.0.124'] = 'fc-t480-wire';
    $ip_addr['192.168.0.125'] = 'jo-t480-nb';
    $ip_addr['192.168.0.126'] = 'jo-t480-wire';
    $ip_addr['192.168.0.128'] = 'lh-nb-wire';
    $ip_addr['192.168.0.133'] = 'IR-NB-T14';
    $ip_addr['192.168.0.134'] = 'IR-wire-T14';
    $ip_addr['192.168.0.135'] = 'FC-NB-T480';
    $ip_addr['192.168.0.136'] = 'FC-wire-T480';
    $ip_addr['192.168.0.137'] = 'SV-NB-X13';
    $ip_addr['192.168.0.138'] = 'SV-wire-X13';
    $ip_addr['192.168.0.139'] = 'SS-NB-T14';
    $ip_addr['192.168.0.140'] = 'SS-wire-T14';
    $ip_addr['192.168.0.141'] = 'HC-PC';
    $ip_addr['192.168.0.142'] = 'JL-PC';
    $ip_addr['192.168.0.143'] = 'SE-NB-X280';
    $ip_addr['192.168.0.144'] = 'SE-wire-X280';
    $ip_addr['192.168.0.145'] = 'PA-NB-T14';
    $ip_addr['192.168.0.146'] = 'PA-wire-T14';
    $ip_addr['192.168.0.147'] = 'OW-NB-X280';
    $ip_addr['192.168.0.148'] = 'OW-wire-X280';
    $ip_addr['192.168.0.149'] = 'EC-NB-T480';
    $ip_addr['192.168.0.150'] = 'EC-wire-T480';
    $ip_addr['192.168.0.151'] = 'TH-PC';
    $ip_addr['192.168.0.152'] = 'BW-NB-L15';
    $ip_addr['192.168.0.153'] = 'BW-wire-L15';
    $ip_addr['192.168.0.154'] = 'SL-PC';
    $ip_addr['192.168.0.155'] = 'pc-isbak1';
    $ip_addr['192.168.0.156'] = 'IC-NB-T470';
    $ip_addr['192.168.0.157'] = 'IC-wire-T470';
    $ip_addr['192.168.0.158'] = 'KU-NB-T14';
    $ip_addr['192.168.0.159'] = 'KU-wire-T14';
    $ip_addr['192.168.0.160'] = 'LC-NB-T14';
    $ip_addr['192.168.0.161'] = 'LC-wire-T14';
    $ip_addr['192.168.0.162'] = 'BELL';
    $ip_addr['192.168.0.163'] = 'PW-NB-T14';
    $ip_addr['192.168.0.164'] = 'PW-wire-T14';
    $ip_addr['192.168.0.165'] = 'PT-PC';
    $ip_addr['192.168.0.166'] = 'TN-PC';
    $ip_addr['192.168.0.167'] = 'JM-NB-T14';
    $ip_addr['192.168.0.168'] = 'JM-wire-T14';
    $ip_addr['192.168.0.169'] = 'LY-NB-X390';
    $ip_addr['192.168.0.170'] = 'LY-wire-X390';
    $ip_addr['192.168.0.171'] = 'PD-NB-P14S';
    $ip_addr['192.168.0.172'] = 'PD-wire-P14S';
    $ip_addr['192.168.0.173'] = 'CI-PC';
    $ip_addr['192.168.0.174'] = 'EW-PC';
    $ip_addr['192.168.0.175'] = 'MG-NB-T14';
    $ip_addr['192.168.0.176'] = 'MG-wire-T14';
    $ip_addr['192.168.0.177'] = 'YI-PC';
    $ip_addr['192.168.0.178'] = 'RJ-PC';
    $ip_addr['192.168.0.179'] = 'tlxeon-pc';
    $ip_addr['192.168.0.180'] = 'IR-PC';
    $ip_addr['192.168.0.181'] = 'AN-PC';
    $ip_addr['192.168.0.182'] = 'EA-PC';
    $ip_addr['192.168.0.183'] = 'AU-NB-X13';
    $ip_addr['192.168.0.184'] = 'AU-wire-X13';
    $ip_addr['192.168.0.185'] = 'BC-NB-X13';
    $ip_addr['192.168.0.186'] = 'BC-wire-X13';
    $ip_addr['192.168.0.187'] = 'KA-NB-X390';
    $ip_addr['192.168.0.188'] = 'KA-wire-X390';
    $ip_addr['192.168.0.189'] = 'NL-NB-T14';
    $ip_addr['192.168.0.190'] = 'NL-wire-T14';
    $ip_addr['192.168.0.191'] = 'EH-PC';
    $ip_addr['192.168.0.192'] = 'JP-PC';
    $ip_addr['192.168.0.193'] = 'PI-PC';
    $ip_addr['192.168.0.194'] = 'HM-NB-X13';
    $ip_addr['192.168.0.195'] = 'HM-wire-X13';
    $ip_addr['192.168.0.196'] = '';
    $ip_addr['192.168.0.197'] = 'ZH-PC';
    $ip_addr['192.168.0.198'] = 'CC-NB-T480';
    $ip_addr['192.168.0.199'] = '';
    $ip_addr['192.168.0.200'] = 'CC-USB';
    $ip_addr['192.168.0.201'] = 'CC-wire-X1C';
    $ip_addr['192.168.0.202'] = 'TL-ubuntu';
    $ip_addr['192.168.0.203'] = 'CL-NB-D15';
    $ip_addr['192.168.0.204'] = 'CL-wire-D15';
    $ip_addr['192.168.0.205'] = 'CN-NB-X13';
    $ip_addr['192.168.0.206'] = 'CN-wire-X13';
    $ip_addr['192.168.0.207'] = 'HC-NB-X13';
    $ip_addr['192.168.0.208'] = 'HC-wire-X13';
    $ip_addr['192.168.0.209'] = 'TR-NB-T14';
    $ip_addr['192.168.0.210'] = 'TR-wire-T14';
    $ip_addr['192.168.0.211'] = 'YN-NB-X13';
    $ip_addr['192.168.0.212'] = 'YN-wire-X13';
    $ip_addr['192.168.0.213'] = 'PD-NB-home';
    $ip_addr['192.168.0.214'] = 'GB-NB-X1C';
    $ip_addr['192.168.0.215'] = 'GB-wire-X1C';
    $ip_addr['192.168.0.216'] = 'GB-usb';
    $ip_addr['192.168.0.217'] = 'WS-NB-X390';
    $ip_addr['192.168.0.218'] = 'WS-wire-X390';
    $ip_addr['192.168.0.219'] = 'JS-NB-x13';
    $ip_addr['192.168.0.220'] = 'JS-wire-x13';
    $ip_addr['192.168.0.221'] = 'CA-NB-X13';
    $ip_addr['192.168.0.222'] = 'CA-wire-X13';
    $ip_addr['192.168.0.223'] = 'EL-NB-X270';
    $ip_addr['192.168.0.224'] = 'EL-NB-X1C';
    $ip_addr['192.168.0.225'] = 'EL-wire-X1C';
    $ip_addr['192.168.0.226'] = 'YK-NB-X13';
    $ip_addr['192.168.0.227'] = 'YK-wire-X13';
    $ip_addr['192.168.0.228'] = 'YC-PC';
    $ip_addr['192.168.0.229'] = 'VY-PC';
    $ip_addr['192.168.0.230'] = 'PC-NB-X13';
    $ip_addr['192.168.0.231'] = 'PC-wire-X13';
    $ip_addr['192.168.0.232'] = 'HE-NB-X390';
    $ip_addr['192.168.0.233'] = 'HE-wire-X390';
    $ip_addr['192.168.0.234'] = 'MD-NB-T14';
    $ip_addr['192.168.0.235'] = 'MD-wire-T14';
    $ip_addr['192.168.0.236'] = '';
    $ip_addr['192.168.0.237'] = '';
    $ip_addr['192.168.0.238'] = 'GK-NB-X1C';
    $ip_addr['192.168.0.239'] = 'GK-wire-X1C';
    $ip_addr['192.168.0.240'] = 'MU-NB-X13';
    $ip_addr['192.168.0.241'] = 'MU-wire-X13';
    $ip_addr['192.168.0.242'] = 'VY-NB-X280';
    $ip_addr['192.168.0.243'] = 'VY-NB-X13';
    $ip_addr['192.168.0.244'] = 'VY-wire-X13';
    $ip_addr['192.168.0.245'] = 'YF-PC';
    $ip_addr['192.168.0.246'] = 'AW-PC';
    $ip_addr['192.168.0.247'] = 'PH-NB-T470P';
    $ip_addr['192.168.0.248'] = 'PH-wire-T470P';
    $ip_addr['192.168.0.249'] = 'NK-NB-X390';
    $ip_addr['192.168.0.250'] = 'NK-wire-X390';
    return $ip_addr;
}

function mailTo($data, $data_new, $types) {
    require_once('vendor/autoload.php');

    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 465;

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;

    // $mail->SMTPDebug = 3;

    $mail->SMTPAuth = true;
    $mail->AuthType = 'XOAUTH2';

    $email = 'phsiao@winklerpartners.com';
    $clientId = '801036448131-ajfkchcst6ns01sqnlbiqqks6au3ep2i.apps.googleusercontent.com';
    $clientSecret = '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';
    $refreshToken = '1//0ehu3BbwYuaJwCgYIARAAGA4SNwF-L9IrnICq-3R7Z5s2Rvl7YsXefTM5ikS2h-sJUoc-Ea98HXQm3v3Suy-ukJ0ypxWmLhesKZs';

    $provider = new Google(
        [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
        ]
    );

    $mail->setOAuth(
        new OAuth(
            [
                'provider' => $provider,
                'clientId' => $clientId,
                'clientSecret' => $clientSecret,
                'refreshToken' => $refreshToken,
                'userName' => $email,
            ]
        )
    );

    $fc_ac_account = '';
    $content = '';
    if ($types != 'ar_list' && $data['bpm_rownum'] != '') {
        $fc_ac_account = ',fchang\@winklerpartners.com ,shung\@winklerpartners.com';
    }
    if ($types == "disbs_delete") {
        $init = $data['initials'];
        $case_num = $data['case_num'];
        $ip_name = $data['ip_name'];
        $ip_addr = $data['ip_addr'];
        $deb_num = $data['deb_num'];
        $date = $data['date'];
        $disb_name = $data['disb_name'];
        $ntd_amount = $data['ntd_amount'];
        $mail_to = "phsiao\@winklerpartners.com,$init\@winklerpartners.com $fc_ac_account";
        $subject = "[Delete disbursements] $case_num ";
        $content .= "<html>\n <body>\n";
        $content .= "Entered by: $ip_name <BR>";
        $content .= "IP address: $ip_addr <BR><BR>";
        $content .= "Deleted disbursement data as below:\n ";
        $content .= "<table border=1 CELLPADDING=5 width=80%>\n ";
        $content .= "<tr bgcolor='#D4E4C7'>\n ";
        $content .= "<td>Case Num</td><td>Deb Num</td><td>Date</td><td>Disbursement</td><td>Amount</td>\n ";
        $content .= "</tr>\n ";
        $content .= "<tr>\n ";
        $content .= "<td>$case_num</td><td>$deb_num</td><td>$date</td>
                        <td>$disb_name</td><td>$ntd_amount</td>\n ";
        $content .= "</tr>\n ";
        $content .= "</table>\n ";
        $content .= "</body> \n </html>\n ";
    } elseif ($types == "disbs_update") {
        $acmail = '';
        $ac_amount_flag = 0;
        $ac_show_legal_flag = 0;
        $ac_charge_flag = 0;
        $ea_paydate_flag = 0;
        $ea_note_flag = 0;


        $init = $data['initials'];
        $new_init = $data_new['initials'];
        $case_num = $data['case_num'];
        $new_case_num = $data_new['case_num'];
        $ip_name = $data['ip_name'];
        $ip_addr = $data['ip_addr'];
        $deb_num = $data['deb_num'];
        $date = $data['date'];
        $new_date = $data_new['date'];
        $new_deb_num = $data_new['deb_num'];
        $disb_code = $data['disb_code'];
        $new_disb_code = $data_new['disb_code'];
        $disb_name = $data['disb_name'];
        $new_disb_name = $data_new['disb_name'];
        $ntd_amount = $data['ntd_amount'];
        $new_ntd_amount = $data_new['ntd_amount'];
        $narrative = $data['narrative'];
        $new_narrative = $data_new['narrative'];
        $notes = $data['notes'];
        $new_notes = $data_new['notes'];
        $currency = $data['currency'];
        $new_currency = $data_new['currency'];
        $currency2 = $data['currency2'];
        $new_currency2 = $data_new['currency2'];
        $foreign_amount = $data['foreign_amount'];
        $new_foreign_amount = $data_new['foreign_amount'];
        $foreign_amount2 = $data['foreign_amount2'];
        $new_foreign_amount2 = $data_new['foreign_amount2'];
        $counsel_name = $data['counsel_name'];
        $new_counsel_name = $data_new['counsel_name'];
        $counsel_invoice = $data['counsel_invoice'];
        $new_counsel_invoice = $data_new['counsel_invoice'];
        $invoice_date = $data['invoice_date'];
        $new_invoice_date = $data_new['invoice_date'];
        $paydate = $data['paydate'];
        $new_paydate = $data_new['paydate'];
        $bpm_rownum = $data['bpm_rownum'];
        $new_bpm_rownum = $data_new['bpm_rownum'];
        $bpm_appnum = $data['bpm_appnum'];
        $new_bpm_appnum = $data_new['bpm_appnum'];
        $disbs_id_relation = $data['disbs_id_relation'];


        if ($data_new['disb_code'] == 108 || $data_new['disb_code'] == 208 || $data_new['disb_code'] == 110 || $data_new['disb_code'] == 210 || $data_new['disb_code'] == 116 || $data_new['disb_code'] == 216 || $data_new['disb_code'] == 117 || $data_new['disb_code'] == 217 || $data_new['disb_code'] == 118 || $data_new['disb_code'] == 218 ||  $data_new['disb_code'] == 121 || $data_new['disb_code'] == 221 || $data_new['disb_code'] == 125 || $data_new['disb_code'] == 225) {
            $mail_to = "phsiao\@winklerpartners.com,$init\@winklerpartners.com,$new_init\@winklerpartners.com $fc_ac_account";
            $acmail = "shung\@winklerpartners.com";
        } else {
            $mail_to = "phsiao\@winklerpartners.com,$init\@winklerpartners.com,$new_init\@winklerpartners.com $fc_ac_account";
        }
        $subject = "[Update disbursements] $case_num ";
        $content .= "<html>\n <body>\n";
        $content .= "Entered by: $ip_name <BR>";
        $content .= "IP address: $ip_addr <BR><BR>";
        $content .= "Original disbursement data as below:\n ";
        $content .= "<table border=1 CELLPADDING=5>\n ";
        $content .= "<tr bgcolor='#F6D7C5'>\n ";
        $content .= "<td ></td><td>Original data</td><td>Update data</td>\n ";
        $content .= "</tr>\n ";
        $content .= "<tr>\n ";
        $content .= "<td bgcolor='#D4E4C7'>Deb Num</td><td>$deb_num</td><td>$new_deb_num</td>\n ";
        $content .= "</tr>\n ";
        $content .= "<tr>\n ";
        $content .= "<td bgcolor='#D4E4C7'>Case Number</td><td>$case_num</td><td>$new_case_num</td>\n ";
        $content .= "</tr>\n ";
        $content .= "<tr>\n ";
        if ($new_date != $date) {
            $bgcolor = " bgcolor=red ";
        } else {
            $bgcolor = "";
        }
        $content .= "<td bgcolor='#D4E4C7'>Date</td><td>$date</td><td $bgcolor>$new_date</td>\n ";
        $content .= "</tr>\n ";
        $content .= "<tr >\n ";
        if ($data_new['disb_code'] != $disb_code) {
            $bgcolor = " bgcolor=red ";
        } else {
            $bgcolor = "";
        }
        $content .= "<td bgcolor='#D4E4C7'>Disbursement code</td><td>$disb_code($disb_name)</td><td $bgcolor>$new_disb_code($new_disb_name)</td>\n ";
        $content .= "</tr>\n ";
        $content .= "<tr >\n ";
        if ($new_ntd_amount != $ntd_amount) {
            $ac_amount_flag = 1;
            $bgcolor = " bgcolor=red ";
        } else {
            $bgcolor = "";
        }
        $content .= "<td bgcolor='#D4E4C7'>Amount</td><td>ntd_amount<td $bgcolor>new_ntd_amount</td></td>\n ";
        $content .= "</tr>\n ";
        $content .= "<tr>\n ";
        if ($new_narrative != $narrative) {
            $bgcolor = " bgcolor=red ";
        } else {
            $bgcolor = "";
        }
        $content .= "<td bgcolor='#D4E4C7'>Narrative</td><td>$narrative</td><td $bgcolor>$new_narrative</td>\n ";
        $content .= "</tr>\n ";
        $content .= "<tr>\n ";
        if ($new_notes != $notes) {
            $bgcolor = " bgcolor=red ";
            $ea_note_flag = 1;
        } else {
            $bgcolor = "";
        }
        $content .= "<td bgcolor='#D4E4C7'>Notes</td><td>$notes</td><td $bgcolor>$new_notes</td>\n ";
        $content .= "</tr>\n ";
        $content .= "<tr>\n ";
        if ($$new_currency != $currency) {
            $bgcolor = " bgcolor=red ";
        } else {
            $bgcolor = "";
        }
        $content .= "<td bgcolor='#D4E4C7'>Currency</td><td>$currency $foreign_amount</td><td $bgcolor>$$new_currency $new_foreign_amount</td>\n ";
        $content .= "</tr>\n ";

        $content .= "<tr>\n ";
        if ($new_currency2 == '\'NULL\'') {
            $new_currency2 = '';
        }
        if ($new_foreign_amount2 == '\'NULL\'') {
            $new_foreign_amount2 = '';
        }
        if ($new_currency2 != $currency2 || $new_foreign_amount2 != $foreign_amount2) {
            $bgcolor = " bgcolor=red ";
        } else {
            $bgcolor = "";
        }
        $content .= "<td bgcolor='#D4E4C7'>Currency2</td><td>$currency2   $foreign_amount2</td><td $bgcolor>$new_currency2 $new_foreign_amount2</td>\n ";
        $content .= "</tr>\n ";


        $content .= "<tr>\n ";
        if ($data_new['show_flag'] != $data['show_flag']) {
            $bgcolor = " bgcolor=red ";
            $ac_charge_flag = 1;
        } else {
            $bgcolor = "";
        }
        if ($data['show_flag'] == 1) {
            $content .= "<td bgcolor='#D4E4C7'>No charge</td><td>NO</td>\n ";
        } else {
            $content .= "<td bgcolor='#D4E4C7'>No charge</td><td>YES</td>\n ";
        }
        if ($data_new['show_flag'] == 1) {
            $content .= "<td $bgcolor>NO</td>\n ";
        } else {
            $content .= "<td $bgcolor>YES</td>\n ";
        }
        $content .= "</tr>\n ";

        $content .= "<tr>\n ";
        if ($new_counsel_name != $counsel_name) {
            $bgcolor = " bgcolor=red ";
        } else {
            $bgcolor = "";
        }
        $content .= "<td bgcolor='#D4E4C7'>Counsel Name</td><td>$counsel_name</td><td $bgcolor>$new_counsel_name</td>\n ";
        $content .= "</tr>\n ";

        $content .= "<tr>\n ";
        if ($new_invoice_date == 'NULL') {
            $new_invoice_date = '';
        }
        if ($new_invoice_date != $invoice_date) {
            $bgcolor = " bgcolor=red ";
        } else {
            $bgcolor = "";
        }
        $content .= "<td bgcolor='#D4E4C7'>Counsel Invoice Date</td><td>$invoice_date</td><td $bgcolor>$new_invoice_date</td>\n ";
        $content .= "</tr>\n ";

        $content .= "<tr>\n ";
        if ($new_counsel_invoice != $counsel_invoice) {
            $bgcolor = " bgcolor=red ";
        } else {
            $bgcolor = "";
        }
        $content .= "<td bgcolor='#D4E4C7'>Counsel Invoice</td><td>$counsel_invoice</td><td $bgcolor>$new_counsel_invoice</td>\n ";
        $content .= "</tr>\n ";


        $content .= "<tr>\n ";
        if ($data_new['show_as_legal_service_flag'] != $data['show_as_legal_service_flag']) {
            $bgcolor = " bgcolor=red ";
            $ac_show_legal_flag = 1;
        } else {
            $bgcolor = "";
        }
        if ($data['show_as_legal_service_flag'] == -1) {
            $content .= "<td bgcolor='#D4E4C7'>Bill Show as Legal Service</td><td>NO</td>\n ";
        } else {
            $content .= "<td bgcolor='#D4E4C7'>Bill Show as Legal Service</td><td>YES</td>\n ";
        }
        if ($data_new['show_as_legal_service_flag'] == -1) {
            $content .= "<td $bgcolor>NO</td>\n ";
        } else {
            $content .= "<td $bgcolor>YES</td>\n ";
        }
        $content .= "</tr>\n ";
        $content .= "<tr>\n ";
        if ($new_paydate == '\'NULL\'') {
            $new_paydate = '';
        }
        if ($new_paydate != $paydate) {
            $bgcolor = " bgcolor=red ";
            $ea_paydate_flag = 1;
        } else {
            $bgcolor = "";
        }
        $content .= "<td bgcolor='#D4E4C7'>Payment Date</td><td>$paydate</td><td  $bgcolor>$new_paydate</td>\n ";
        $content .= "</tr>\n ";

        $content .= "<tr>\n ";
        if ($data_new['initials'] != $data['initials']) {
            $bgcolor = " bgcolor=red ";
        } else {
            $bgcolor = "";
        }
        $content .= "<td bgcolor='#D4E4C7'>Initials</td><td>$init</td><td  $bgcolor>$new_init</td>\n ";
        $content .= "</tr>\n ";

        $content .= "<tr>\n ";
        $content .= "<td bgcolor='#D4E4C7'>BPM RowNumber</td><td>$bpm_rownum</td><td>$new_bpm_rownum</td>\n ";
        $content .= "</tr>\n ";
        $content .= "<tr>\n ";
        $content .= "<td bgcolor='#D4E4C7'>BPM 申請單號</td><td>$bpm_appnum</td><td>$new_bpm_appnum</td>\n ";
        $content .= "</tr>\n ";

        $content .= "<tr>\n ";
        if ($data_new['disbs_id_relation'] != $disbs_id_relation) {
            $bgcolor = " bgcolor=red ";
            $ea_paydate_flag = 1;
        } else {
            $bgcolor = "";
        }
        $tmp_disbs_relation = $data_new['disbs_id_relation'];
        if ($tmp_disbs_relation == "'NULL'") {
            $tmp_disbs_relation = '';
        }
        $content .= "<td bgcolor='#D4E4C7'>Disbs ID Relation</td><td>$disbs_id_relation</td><td $bgcolor>$tmp_disbs_relation</td>\n ";
        $content .= "</tr>\n ";
        $content .= "</table>\n ";

        $content .= "</body> \n </html>\n ";

        if ($acmail !== '' && ($ac_amount_flag == 1 || $ac_show_legal_flag == 1 || $ac_charge_flag == 1)) {
            $mail_to = $mail_to . ',' . $acmail;
        }

        if (preg_match('/ea@winklerpartners.com/i', $mail_to) && $ea_paydate_flag == 0 && $ea_note_flag == 0) {
            $mail_to = preg_replace('/ea@winklerpartners.com/i', '', $mail_to);
        }
    }

    $mail_to = str_replace("\\", "", $mail_to);
    $recipients = explode(",", $mail_to);



    $subject = "[updated outstanding bills/draft bills/unbilled hours/unbilled disbursement/ client's retainer or other credit] ";

    $mail->setFrom($email, 'Pon Hsiao');
    foreach ($recipients as $recipient) {
        $mail->addAddress(trim($recipient));
    }
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $content;

    if (!$mail->send()) {
        echo $mail_to . "<BR>";
        echo 'Mailer Error: ' . $mail->ErrorInfo;
    }
}

function editRecord($id, $case_num, $deb_num, $start, $end) {
    $dblink23 = @pg_pconnect(DB_CONNECT23);
    $sql = "SELECT billing_currency,disbursements.* FROM disbursements  LEFT JOIN cases  ON (disbursements.case_num=cases.case_num ) WHERE id = '$id'";
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗1: $error";
        exit;
    }
    $disb = pg_fetch_assoc($result);
    $rv = pg_num_rows($result);
    $disb_id = $id;
    if ($rv != 1) {
        echo '<script>alert("Too many disbursements were retrieved");location.href = "disb_report.php";  </script>';
        exit();
    }
    $disb['disb_code'] = preg_replace('/^1/', '2', $disb['disb_code']);

    $lock = '';
    if ($deb_num != '') {
        $lock = 'readonly';
    }

    $currency_list = ["", "USD", "EUR", "AUD", "HKD", "SGD", "JPY", "NZD", "GBP", "CNY", "CAD"];
    $currency_list2 = ["USD", "EUR"];
    $arr = getDisbCode($dblink23);
    $disb_code_list = $arr[0];
    $disb_name_list = $arr[1];
    $hr_list = getHrList($dblink23);
    $ar_initials = $hr_list;
    foreach ($hr_list as &$each) {
        $each = $each['initials'];
    }
    array_unshift($disb_name_list, 'ALL');
    array_unshift($disb_code_list, '0');
    array_unshift($hr_list, 'ALL');

    $x_rate2 = 0;
    $temp1 = explode('-', $disb['date']);
    $year = $temp1[0];
    $month = $temp1[1];
    if ($disb['currency2'] == '') {
        if ($disb['billing_currency'] == 'English (EUR)') {
            $sql = "SELECT ntd FROM xrate_eur 
                WHERE  year = '$year' 
                AND month = '$month'";
            $result = @pg_query($dblink23, $sql);
            if (!$result) {
                $error = pg_last_error($dblink23);
                echo "SQL 執行失敗1: $error";
                exit;
            }
            $xrate = pg_fetch_assoc($result);
            $x_rate2 = $xrate['ntd'];
            $disb['currency2'] = 'EUR';
            $disb['foreign_amount2'] = round(($disb['ntd_amount'] / $x_rate2), 2);
        } elseif ($disb['billing_currency'] == 'English (USD)') {
            $sql = "SELECT ntd FROM xrate 
                WHERE  year = '$year' 
                AND month = '$month'";
            $result = @pg_query($dblink23, $sql);
            if (!$result) {
                $error = pg_last_error($dblink23);
                echo "SQL 執行失敗1: $error";
                exit;
            }
            $xrate = pg_fetch_assoc($result);
            $x_rate2 = $xrate['ntd'];
            $disb['currency2'] = 'USD';
            $disb['foreign_amount2'] = round(($disb['ntd_amount'] / $x_rate2), 2);
        }
    } elseif ($disb['foreign_amount2'] == 0 || $disb['foreign_amount2'] == '') {
        if ($disb['billing_currency'] == 'English (EUR)') {
            $sql = "SELECT ntd FROM xrate_eur 
                WHERE  year = '$year' 
                AND month = '$month'";
            $result = @pg_query($dblink23, $sql);
            if (!$result) {
                $error = pg_last_error($dblink23);
                echo "SQL 執行失敗1: $error";
                exit;
            }
            $xrate = pg_fetch_assoc($result);
            $x_rate2 = $xrate['ntd'];
            $disb['currency2'] = 'EUR';
            $disb['foreign_amount2'] = round(($disb['ntd_amount'] / $x_rate2), 2);
        } elseif ($disb['billing_currency'] == 'English (USD)') {
            $sql = "SELECT ntd FROM xrate 
                WHERE  year = '$year' 
                AND month = '$month'";
            $result = @pg_query($dblink23, $sql);
            if (!$result) {
                $error = pg_last_error($dblink23);
                echo "SQL 執行失敗1: $error";
                exit;
            }
            $xrate = pg_fetch_assoc($result);
            $x_rate2 = $xrate['ntd'];
            $disb['currency2'] = 'USD';
            $disb['foreign_amount2'] = round(($disb['ntd_amount'] / $x_rate2), 2);
        }
    } else {
        if ($disb['currency2'] == 'EUR') {
            $sql = "SELECT ntd FROM xrate_eur 
                WHERE  year = '$year' 
                AND month = '$month'";
            $result = @pg_query($dblink23, $sql);
            if (!$result) {
                $error = pg_last_error($dblink23);
                echo "SQL 執行失敗1: $error";
                exit;
            }
            $xrate = pg_fetch_assoc($result);
            $x_rate2 = $xrate['ntd'];
        } else {
            $sql = "SELECT ntd FROM xrate 
                WHERE  year = '$year' 
                AND month = '$month'";
            $result = @pg_query($dblink23, $sql);
            if (!$result) {
                $error = pg_last_error($dblink23);
                echo "SQL 執行失敗1: $error";
                exit;
            }
            $xrate = pg_fetch_assoc($result);
            $x_rate2 = $xrate['ntd'];
        }
    }

    $final_arr = [
        'disb' => $disb,
        'lock' => $lock,
        'currency_list' => $currency_list,
        'currency_list2' => $currency_list2,
        'hr_list' => $hr_list,
        'people' => $ar_initials,
        'disb_name_list' => $disb_name_list,
        'disb_code_list' => $disb_code_list,
        'x_rate2' => $x_rate2
    ];
    return $final_arr;
}

function updateRecord($lock, $id, $case_num, $deb_num, $fee_earners, $date, $disb_code, $ntd_amount, $show_flag, $narrative, $currency, $foreign_amount, $currency2, $foreign_amount2, $x_rate, $initials, $counsel_name, $counsel_area, $invoice_date, $counsel_invoice, $show_as_legal_service_flag, $paydate, $disbs_id_relation, $bpm_date, $nocharge_flag, $billed_flag, $notes) {
    $dblink23 = @pg_pconnect(DB_CONNECT23);
    if ($lock == 'readonly') {
    } else {
        if ($show_flag == -1) {
            $nocharge_flag = 1;
            $billed_flag = 2;
        } else {
            $show_flag = 1;
            $nocharge_flag = -1;
            $billed_flag = -1;
        }
    }
    $show_as_legal_service_flag = ($show_as_legal_service_flag == '') ? -1 : $show_as_legal_service_flag;

    if (!preg_match('/^2/', $disb_code)) {
        echo '<script>alert("Please begin all codes with 2.");location.href = "disb_list.php";  </script>';
        exit();
    }
    $sec_email = $initials . '@winklerpartners.com';

    $sql = "SELECT * FROM cases  WHERE case_num = '$case_num'";
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗1: $error";
        exit;
    }
    $rv = pg_num_rows($result);
    $case = pg_fetch_assoc($result);
    if (!($rv)) {
        echo '<script>alert("' . $case_num . ' is not a valid case number.");location.href = "disb_list.php";  </script>';
        exit();
    }

    $case_manager = $case['case_manager'];
    $partner = $case['partner'];
    $partner2 = $case['partner2'];

    $sql = "SELECT * FROM disb  WHERE disb_code = '$disb_code'";
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗1: $error";
        exit;
    }
    $rv = pg_num_rows($result);
    if (!($rv)) {
        echo '<script>alert("' . $disb_code . ' is not a valid case number.");location.href = "disb_list.php";  </script>';
        exit();
    }

    if (empty($case['bill_country'])) {
        $disb_code = preg_replace('/^2/', '1', $disb_code);
    }

    $sql = "SELECT * FROM disb WHERE disb_code = '$disb_code'";
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗1: $error";
        exit;
    }
    $disb_meta = pg_fetch_assoc($result);
    $disb_name = $disb_meta['disb_name'];
    $disb_id = $id;

    $sql = "SELECT * FROM disbursements WHERE id = '$id'";
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗1: $error";
        exit;
    }
    $disbs = pg_fetch_assoc($result);

    if ($paydate == '') {
        $paydate = "NULL";
        $dis_case_manager = "NULL";
        $dis_partner = "NULL";
        $dis_partner2 = "NULL";
    } else {
        $dis_case_manager = $case_manager;
        $dis_partner = $partner;
        $dis_partner2 = $partner2;
    }
    $invoice_date = ($invoice_date == '') ? 'NULL' : $invoice_date;
    $x_rate = ($x_rate == '') ? 0 : $x_rate;
    $bpm_date = ($bpm_date == '') ? 'NULL' : $bpm_date;
    $currency2 = ($currency2 == '') ? 'NULL' : $currency2;
    $foreign_amount2 = ($foreign_amount2 == '') ? 'NULL' : $foreign_amount2;
    $disbs_id_relation = ($disbs_id_relation == '') ? 'NULL' : $disbs_id_relation;

    if ($counsel_invoice != '') {
        $sql = "SELECT * FROM disbursements  WHERE counsel_invoice = '$counsel_invoice' AND case_num='$case_num' AND disb_code='$disb_code'";
        $result = @pg_query($dblink23, $sql);
        if (!$result) {
            $error = pg_last_error($dblink23);
            echo "SQL 執行失敗1: $error";
            exit;
        }
        $row_count = pg_num_rows($result);
        if ($row_count >= 2) {
            echo '<script>alert("This Invoice number duplicate.");location.href = "disb_list.php";  </script>';
            exit();
        }
    }
    $param = [
        'case_num' => $case_num,
        'deb_num' => $deb_num,
        'fee_earners' => $fee_earners,
        'date' => $date,
        'disb_code' => $disb_code,
        'ntd_amount' => $ntd_amount,
        'show_flag' => $show_flag,
        'narrative' => $narrative,
        'currency' => $currency,
        'foreign_amount' => $foreign_amount,
        'currency2' => $currency2,
        'foreign_amount2' => $foreign_amount2,
        'x_rate' => $x_rate,
        'initials' => $initials,
        'counsel_name' => $counsel_name,
        'counsel_area' => $counsel_area,
        'invoice_date' => $invoice_date,
        'counsel_invoice' => $counsel_invoice,
        'show_as_legal_service_flag' => $show_as_legal_service_flag,
        'paydate' => $paydate,
        'disbs_id_relation' => $disbs_id_relation,
        'bpm_date' => $bpm_date,
        'nocharge_flag' => $nocharge_flag,
        'billed_flag' => $billed_flag,
        'dis_case_manager' => $dis_case_manager,
        'disb_name' => $disb_name,
        'dis_partner' => $dis_partner,
        'id' => $id,
        'notes' => $notes,
        'dis_partner2' => $dis_partner2
    ];

    $rv = updateDisb('disbursements', $param, $dblink23);
    if ($rv != 1) {
        echo '<script>alert("Update disbursemenmt fail");location.href = "disb_list.php";  </script>';
        exit();
    }
    $sql = "SELECT * FROM bills WHERE case_num = '$case_num' AND sent is NULL AND bill_status ='0'";
    $result = @pg_query($dblink23, $sql);
    if (!$result) {
        $error = pg_last_error($dblink23);
        echo "SQL 執行失敗1: $error";
        exit;
    }
    if ($bill_temp = pg_fetch_assoc($result)) {
        $draft_created = $bill_temp['draft_created'];
    }

    $date_flag = 0;

    $temp_t1 = strtotime($draft_created);
    $temp_t2 = strtotime($date);
    if ($temp_t1 >= $temp_t2) {
        $date_flag = 1;
    }

    if ($deb_num != '') {
        $date_flag = 0;
    }

    if (($case_manager == 'MD' || $case_manager == 'GK' || $case_manager == 'PO' || $case_manager == 'SE' || $case_manager == 'VY') && $date_flag == 1) {
        $ntd_amount_temp = $ntd_amount;
        $sql = "SELECT * FROM bills WHERE case_num = '$case_num' AND sent is NULL AND bill_status ='0'";
        $result = @pg_query($dblink23, $sql);
        if (!$result) {
            $error = pg_last_error($dblink23);
            echo "SQL 執行失敗1: $error";
            exit;
        }
        if ($bill = pg_fetch_assoc($result)) {
            $deb_num_bills = $bill['deb_num'];
            if ($show_flag != -1) {
                $sql = "UPDATE bills set disbs=disbs+$ntd_amount_temp WHERE deb_num='$deb_num_bills'";
                $result = @pg_query($dblink23, $sql);
                if (!$result) {
                    $error = pg_last_error($dblink23);
                    echo "SQL 執行失敗1: $error";
                    exit;
                }
                $rv = pg_affected_rows($result);
                if ($rv != 1) {
                    echo '<script>alert("bills Update fail  Contact PH.");location.href = "disb_insert.php";  </script>';
                    exit();
                }
            }
            $sql = "UPDATE disbursements set deb_num='$deb_num_bills',billed_flag='0' WHERE id='$id'";
            $result = @pg_query($dblink23, $sql);
            if (!$result) {
                $error = pg_last_error($dblink23);
                echo "SQL 執行失敗1: $error";
                exit;
            }
            $rv = pg_affected_rows($result);
            if ($rv != 1) {
                echo '<script>alert("disbursement Update fail  Contact PH.");location.href = "disb_insert.php";  </script>';
                exit();
            }
        }
    }
    $addr = $_SESSION['ip'];
    $ip_addr = ip_addr();
    $disbs['ip_name'] = $ip_addr[$addr];
    $disbs['ip_addr'] = $addr;
    mailTo($disbs, $param, 'disbs_update');
}



function updateDisb($table, $param, $dblink23) {
    $p_key = get_p_key($dblink23, $table);
    if ($p_key == '') {
        if ($table == 'cases') {
            $p_key = 'case_num';
        }
    }


    $sql = "SELECT * FROM $table LIMIT 1";
    $result = pg_query($dblink23, $sql);

    if (!$result) {
        echo "Error: Unable to execute query\n";
        return;
    }

    $fields = pg_num_fields($result);
    $field_names = array();
    for ($i = 0; $i < $fields; $i++) {
        $field_names[] = pg_field_name($result, $i);
    }

    $param_keys = array_keys($param);
    $seen = array_flip($field_names);

    $param_keys_only = array();
    foreach ($param_keys as $item) {
        if (!isset($seen[$item])) {
            $param_keys_only[] = $item;
        }
    }

    $retained_params = array();
    foreach ($param_keys_only as $item) {
        if (preg_match('/_$/', $item)) {
            $retained_params[$item] = $param[$item];
            unset($param[$item]);
        } else {
            unset($param[$item]);
        }
    }

    $update_list = '';
    foreach ($param as $key => $value) {
        if ($key == $p_key) {
            continue;
        }
        if (is_null($value)) {
            $update_list .= "," . $key . " = NULL";
        } elseif ($value == 'NULL') {
            $update_list .= "," . $key . " = NULL";
        } else {
            $escaped_value = pg_escape_string($dblink23, $value);
            $update_list .= ",$key = '$escaped_value'";
        }
    }

    if ($table == 'cases') {
        if (isset($param['iam_country'])) {
            $escaped_value = pg_escape_string($dblink23, $param['iam_country']);
            $update_list .= ",iam_country_str = '$escaped_value'";
        }
        if (isset($param['iam_matters'])) {
            $escaped_value = pg_escape_string($dblink23, $param['iam_matters']);
            $update_list .= ",iam_matters_str = '$escaped_value'";
        }
        if (isset($param['iam_item'])) {
            $escaped_value = pg_escape_string($dblink23, $param['iam_item']);
            $update_list .= ",iam_item_str = '$escaped_value'";
        }
    }

    $update_list = ltrim($update_list, ',');

    $escaped_p_key = pg_escape_string($dblink23, $param[$p_key]);

    $update_query = "UPDATE $table SET $update_list WHERE $p_key = '$escaped_p_key'";

    $result = pg_query($dblink23, $update_query);

    if ($result === false) {
        $error = pg_last_error($dblink23);
        echo $update_query . "<BR>";
        echo "SQL 執行失敗1: $error";
        exit;
    }

    $rv = pg_affected_rows($result);
    return $rv;
}
