<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once("db23.ini");

function getData($case_number, $match_or_like, $case_manager, $sort_key = 'case_num', $sort_order = 'ASC', $target_ids = []) {
    // 1. 資料庫連接
    $dblink = @pg_connect(DB_CONNECT23);
    if (!$dblink) {
        throw new Exception("無法連接到資料庫");
    }

    try {
        // --- A. 新增：權限檢查邏輯 (Reset 按鈕) ---
        $can_reset = false;
        $user_ip = $_SERVER['REMOTE_ADDR'];

        // 規則 1: 網段檢查 (192.168.150.x)
        if (strpos($user_ip, '192.168.150.') === 0) {
            $can_reset = true;
        } else {
            // 規則 2: 資料庫白名單檢查 (permission & 1)
            // 注意：這裡假設 permission 是整數型態
            $sql_ip = "SELECT 1 FROM ip_addr WHERE ip_addr = $1 AND (permission & 1) = 1";
            $res_ip = pg_query_params($dblink, $sql_ip, [$user_ip]);
            if ($res_ip && pg_num_rows($res_ip) > 0) {
                $can_reset = true;
            }
        }

        // --- (保留你原本的 SQL 條件建構邏輯，這部分寫得很好) ---
        $conditions = [];
        $params = [];
        $param_index = 1;

        if (!empty($target_ids)) {
            $in_placeholders = [];
            foreach ($target_ids as $id) {
                if (is_numeric($id)) {
                    $in_placeholders[] = "$" . $param_index;
                    $params[] = $id;
                    $param_index++;
                }
            }
            if (!empty($in_placeholders)) {
                $conditions[] = "bills.id IN (" . implode(', ', $in_placeholders) . ")";
            }
        }

        // 處理 case_number
        if ($case_number !== '') {
            $values = array_filter(array_map(function ($val) {
                return strtoupper(trim($val));
            }, explode(',', $case_number)));
            if (!empty($values)) {
                if ($match_or_like === 'match') {
                    $in_placeholders = [];
                    foreach ($values as $val) {
                        $in_placeholders[] = "$" . $param_index;
                        $params[] = $val;
                        $param_index++;
                    }
                    $conditions[] = "bills.case_num IN (" . implode(', ', $in_placeholders) . ")";
                } elseif ($match_or_like === 'like') {
                    $or_conditions = [];
                    foreach ($values as $val) {
                        $or_conditions[] = "bills.case_num LIKE $" . $param_index;
                        $params[] = $val . '%';
                        $param_index++;
                    }
                    $conditions[] = "(" . implode(' OR ', $or_conditions) . ")";
                }
            }
        }

        // 處理 case_manager
        if ($case_manager !== '') {
            $values = array_filter(array_map(function ($val) {
                return strtoupper(trim($val));
            }, explode(',', $case_manager)));
            if (!empty($values)) {
                $in_placeholders = [];
                foreach ($values as $val) {
                    $in_placeholders[] = "$" . $param_index;
                    $params[] = $val;
                    $param_index++;
                }
                $conditions[] = "(cases.case_manager IN (" . implode(', ', $in_placeholders) . ") OR bills.bills_case_manager IN (" . implode(', ', $in_placeholders) . "))";
            }
        }

        // 組合 SQL
        $where_clause = (count($conditions) > 0) ? implode(' AND ', $conditions) : '1=1';

        // --- B. 新增：排序邏輯 ---

        // 1. 白名單對應 (安全性：防止 SQL Injection)
        // 前端傳來的 key => 資料庫實際欄位
        $sort_mapping = [
            'created'       => 'bills.draft_created',
            'case_num'      => 'bills.case_num',
            'manager'       => 'cases.case_manager',
            'deb_num'       => 'bills.deb_num',
            'legal_services' => 'bills.legal_services',
            'disbs'         => 'bills.disbs'
        ];

        // 2. 驗證與預設值
        if (!array_key_exists($sort_key, $sort_mapping)) {
            $sort_key = 'case_num'; // 非法參數則回退到預設
        }
        $real_sort_col = $sort_mapping[$sort_key];

        $sort_order = strtoupper($sort_order);
        if ($sort_order !== 'ASC' && $sort_order !== 'DESC') {
            $sort_order = 'ASC';
        }

        // 3. 組合 ORDER BY
        // 關鍵：為了維持前端的 Subtotal 顯示正常，必須先排幣別，再排使用者選的欄位
        // 如果使用者選的是 total (金額)，通常希望能由大到小，這由前端傳入 DESC 控制
        $order_clause = "
            CASE 
                WHEN cases.billing_currency = 'English (USD)' THEN 2 
                WHEN cases.billing_currency = 'English (EUR)' THEN 3 
                ELSE 1 
            END ASC, 
            $real_sort_col $sort_order
        ";

        $sql = "SELECT 
                    bills.*, 
                    cases.case_manager, 
                    cases.case_num, 
                    cases.billing_note, 
                    cases.retainer_num, 
                    cases.pppoc_status, 
                    cases.retainer_case_num, 
                    cases.party_en_name_billing, 
                    client_pay_total.temp_twd_total_amount as retainer_ntd,
                    client_pay_total.temp_foreign_total_amount as retainer_foreign,
                    client_pay_total.currency as retainer_currency,
                    (CASE WHEN bills.draft_created >= '2022-11-26' THEN 1 ELSE 0 END) AS currency_flag,
                    COALESCE(deduct.sum_twd, 0) AS deduct_twd,
                    COALESCE(deduct.sum_foreign, 0) AS deduct_foreign
                FROM bills 
                LEFT JOIN cases ON bills.case_num = cases.case_num
                LEFT JOIN client_pay_total ON cases.retainer_case_num = client_pay_total.case_num
                -- 取得該筆帳單已抵扣的金額
                LEFT JOIN (
                    SELECT 
                        bills_case_num, 
                        SUM(twd_amount) AS sum_twd, 
                        SUM(foreign_amount) AS sum_foreign
                    FROM client_pay_history
                    GROUP BY bills_case_num
                ) AS deduct ON cases.case_num = deduct.bills_case_num
                WHERE 
                    (bills.deb_num LIKE 'A2006%' OR bills.deb_num LIKE 'A2007%' OR bills.deb_num LIKE 'A2008%' OR bills.deb_num LIKE 'A2009%' OR bills.deb_num LIKE 'A201%' OR bills.deb_num LIKE 'A202%') 
                    AND bills.sent IS NULL 
                    AND bills.bill_status = 0 
                    AND $where_clause 
                ORDER BY 
                    $order_clause;";

        $result = pg_query_params($dblink, $sql, $params);
        if (!$result) {
            throw new Exception("查詢執行失敗: " . pg_last_error($dblink));
        }

        // --- 初始化統計變數 ---
        $totals = [
            'twd' => ['legal' => 0, 'disbs' => 0, 'total' => 0, 'count' => 0],
            'usd' => ['legal' => 0, 'disbs' => 0, 'total' => 0, 'count' => 0],
            'eur' => ['legal' => 0, 'disbs' => 0, 'total' => 0, 'count' => 0],
            'all' => ['legal' => 0, 'disbs' => 0, 'total' => 0, 'count' => 0]
        ];

        $processed_rows = [];

        // --- 迴圈處理每筆資料 (對應 Perl 的 while loop) ---
        while ($row = pg_fetch_assoc($result)) {
            // 1. 初始化顯示用的數值 (預設等於原始數值)
            $row['show_as_legal_flag'] = false;
            $row['show_as_legal_foreign_flag'] = false;
            $row['show_legal_services'] = $row['legal_services'];
            $row['show_disbs'] = $row['disbs'];
            $row['show_foreign_legal_services'] = $row['foreign_legal2'];
            $row['show_foreign_disbs'] = $row['foreign_disbs2'];
            $row['show_oc'] = 0;
            $row['show_ati'] = 0;
            $row['display_oc_status'] = '';

            // 2. 統計金額邏輯
            if ($row['billing_currency'] == 'English (USD)') {
                $totals['usd']['legal'] += floatval($row['foreign_legal2']);
                $totals['usd']['disbs'] += floatval($row['foreign_disbs2']);
                $totals['usd']['total'] += floatval($row['foreign_total2']);
                $totals['usd']['count']++;
            } elseif ($row['billing_currency'] == 'English (EUR)') {
                $totals['eur']['legal'] += floatval($row['foreign_legal2']);
                $totals['eur']['disbs'] += floatval($row['foreign_disbs2']);
                $totals['eur']['total'] += floatval($row['foreign_total2']);
                $totals['eur']['count']++;
            } else {
                $totals['twd']['legal'] += floatval($row['legal_services']);
                $totals['twd']['disbs'] += floatval($row['disbs']);
                $totals['twd']['total'] += floatval($row['total']);
                $totals['twd']['count']++;
            }
            $totals['all']['legal'] += floatval($row['legal_services']);
            $totals['all']['disbs'] += floatval($row['disbs']);
            $totals['all']['total'] += floatval($row['total']);
            $totals['all']['count']++;

            // 3. 特殊顯示邏輯 (PPP, TDG, BMT, KA, VY)
            if (in_array($row['retainer_num'], ['PPP', 'TDG', 'BMT'])) {
                $row['ati_show_status'] = 'postshowntd';
                $row['show_oc'] = 1;
                $row['show_ati'] = 1;
            }
            if ((substr($row['case_num'], 0, 3) == 'GIM' || substr($row['case_num'], 0, 3) == 'GNT') && $row['bills_case_manager'] == 'KA') {
                $row['ati_show_status'] = 'postshowntd';
                $row['show_oc'] = 1;
                $row['show_ati'] = 1;
            }
            if ($row['bills_case_manager'] == 'VY' || $row['bills_case_manager'] == 'KA') {
                $row['ati_show_status'] = 'postshowntd';
                $row['show_oc'] = 1;
            }

            // 4. 子查詢：檢查是否有需要轉列為法律服務費的支出 (TWD)
            $sql_disb = "SELECT SUM(ntd_amount) as show_sum FROM disbursements 
                         WHERE billed_flag = 0 AND show_flag = 1 AND show_as_legal_service_flag = 1 
                         AND nocharge_flag = -1 AND deb_num = $1";
            $res_disb = pg_query_params($dblink, $sql_disb, [$row['deb_num']]);
            $disb_rec = pg_fetch_assoc($res_disb);

            if ($disb_rec && $disb_rec['show_sum']) {
                $row['show_legal_services'] = $row['legal_services'] + $disb_rec['show_sum'];
                $row['show_disbs'] = $row['disbs'] - $disb_rec['show_sum'];
                $row['show_as_legal_flag'] = true;
            }

            // 5. 子查詢：外幣支出轉列
            $sql_disb_foreign = "SELECT SUM(foreign_amount2) as show_foreign_sum FROM disbursements 
                                 WHERE billed_flag = 0 AND show_flag = 1 AND show_as_legal_service_flag = 1 
                                 AND nocharge_flag = -1 AND deb_num = $1";
            $res_disb_f = pg_query_params($dblink, $sql_disb_foreign, [$row['deb_num']]);
            $disb_rec_f = pg_fetch_assoc($res_disb_f);

            if ($disb_rec_f && $disb_rec_f['show_foreign_sum'] > 0) {
                $row['show_foreign_legal_services'] = $row['foreign_legal2'] + $disb_rec_f['show_foreign_sum'];
                $row['show_foreign_disbs'] = $row['foreign_disbs2'] - $disb_rec_f['show_foreign_sum'];
                $row['show_as_legal_foreign_flag'] = true;
            }

            // 6. 子查詢：檢查 OC 發票期待 (tr table)
            $sql_tr = "SELECT * FROM tr WHERE invoice_exp_status = '1' AND deb_num = $1";
            $res_tr = pg_query_params($dblink, $sql_tr, [$row['deb_num']]);
            if (pg_num_rows($res_tr) >= 1) {
                $row['display_oc_status'] = '**OC Invoice Expected';
            }

            // 7. 格式化數值 (Formatted Strings) 供前端直接顯示
            // 台幣格式化 (整數)
            $row['fmt_legal_original'] = number_format($row['legal_services']);
            $row['fmt_disbs_original'] = number_format($row['disbs']);
            $row['fmt_show_legal'] = number_format($row['show_legal_services']);
            $row['fmt_show_disbs'] = number_format($row['show_disbs']);
            $row['fmt_total'] = number_format($row['total']);

            // 外幣格式化 (小數點後2位)
            $row['fmt_foreign_legal_original'] = number_format($row['foreign_legal2'], 2);
            $row['fmt_foreign_disbs_original'] = number_format($row['foreign_disbs2'], 2);
            $row['fmt_foreign_show_legal'] = number_format($row['show_foreign_legal_services'], 2);
            $row['fmt_foreign_show_disbs'] = number_format($row['show_foreign_disbs'], 2);
            $row['fmt_foreign_total'] = number_format($row['foreign_total2'], 2);

            // 總計格式化
            $totals['twd']['fmt_legal'] = number_format($totals['twd']['legal']);
            $totals['twd']['fmt_disbs'] = number_format($totals['twd']['disbs']);
            $totals['twd']['fmt_total'] = number_format($totals['twd']['total']);
            $totals['usd']['fmt_legal'] = number_format($totals['usd']['legal'], 2);
            $totals['usd']['fmt_disbs'] = number_format($totals['usd']['disbs'], 2);
            $totals['usd']['fmt_total'] = number_format($totals['usd']['total'], 2);
            $totals['eur']['fmt_legal'] = number_format($totals['eur']['legal'], 2);
            $totals['eur']['fmt_disbs'] = number_format($totals['eur']['disbs'], 2);
            $totals['eur']['fmt_total'] = number_format($totals['eur']['total'], 2);
            $totals['all']['fmt_legal'] = number_format($totals['all']['legal']);
            $totals['all']['fmt_disbs'] = number_format($totals['all']['disbs']);
            $totals['all']['fmt_total'] = number_format($totals['all']['total']);

            $processed_rows[] = $row;
        }

        // 回傳資料與總計
        return [
            'rows' => $processed_rows,
            'totals' => $totals,
            'can_reset' => $can_reset
        ];
    } finally {
        if (isset($dblink) && is_resource($dblink)) {
            pg_close($dblink);
        }
    }
}
