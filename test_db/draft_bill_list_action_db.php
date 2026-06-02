<?php
// draft_bill_list_action_db.php
// 功能：處理帳單草稿的更新 (Update) 與寄送 (Apply)
// 包含 Rainmaker 檢查、ATI 更新、Retainer 處理、OC Invoice 與 PPP OC 狀態更新

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
    // 兼容 JS submit 模擬的情況 (如果 JS append 了 hidden input)
    if (isset($_POST['update']) || (isset($_REQUEST['update']) && $_REQUEST['update'] == 'true')) {
        $action = 'update';
    } elseif (isset($_POST['apply']) || (isset($_REQUEST['apply']) && $_REQUEST['apply'] == 'true')) {
        $action = 'apply';
    } else {
        die("未知的操作請求");
    }
}

// 取得全域參數
$sent_date = isset($_POST['sent_date']) ? trim($_POST['sent_date']) : '';
$discount_global = isset($_POST['discount']) ? trim($_POST['discount']) : ''; // 全域折扣輸入
$ids = isset($_POST['row_check_box']) ? $_POST['row_check_box'] : [];

// [修正 1] 取得 OC Invoice 全域設定 (配合前端 name="oc_invoice")
// 值可能為 'expected' 或 'cancel'
$oc_invoice_global = $_POST['oc_invoice'] ?? null;

// [修正 2] 取得 PPP OC 全域設定 (配合前端 name="pppoc")
// 值可能為 'expected' 或 'cancel'
$pppoc_global = $_POST['pppoc'] ?? null;

try {
    // ---------------------------------------------------------
    // 動作一：UPDATE (僅更新資料，不寄出)
    // ---------------------------------------------------------
    if ($action == 'update') {

        pg_query($dblink, "BEGIN"); // 開啟交易

        foreach ($ids as $id) {
            $id = intval($id);

            // 1. 查詢帳單基本資訊
            $sql_bill = "SELECT deb_num, case_num, legal_services FROM bills WHERE id = $1 FOR UPDATE";
            $res_bill = pg_query_params($dblink, $sql_bill, [$id]);
            $bill = pg_fetch_assoc($res_bill);
            if (!$bill) continue;

            $deb_num = $bill['deb_num'];
            $case_num = $bill['case_num'];

            // =========================================================
            // 邏輯補強 A：Rainmaker 業績分配檢查
            // =========================================================
            check_rainmaker_total($dblink, $case_num, $deb_num);

            // =========================================================
            // [修正] 邏輯補強 B：PPP OC 狀態更新 (Cases Table)
            // 根據 radio button 的值判斷 ('expected' => 1, 'cancel' => 0)
            // =========================================================
            if ($pppoc_global === 'expected') {
                $sql_ppp = "UPDATE cases SET pppoc_status = '1' WHERE case_num = $1";
                pg_query_params($dblink, $sql_ppp, [$case_num]);
            } elseif ($pppoc_global === 'cancel') {
                $sql_ppp = "UPDATE cases SET pppoc_status = '0' WHERE case_num = $1";
                pg_query_params($dblink, $sql_ppp, [$case_num]);
            }

            // 2. 取得個別參數並進行驗證
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

            // 驗證 Class Count 不可小於 0
            $class_count = intval($_POST["class_count_{$id}"] ?? 0);
            if ($class_count < 0) $class_count = 0;

            $azn_budget_status = $_POST["azn_budget_status_{$id}"] ?? null;

            // 取得個別列的 OC Invoice 設定
            $invoice_exp_status = $_POST["invoice_exp_status_{$id}"] ?? null;

            // [修正] 若個別沒有設定，則使用全域設定 (Sidebar Radio)
            if (empty($invoice_exp_status) && !empty($oc_invoice_global)) {
                $invoice_exp_status = $oc_invoice_global;
            }

            $retainer_amount = $_POST["retainer_amount_{$id}"] ?? null;

            // 處理折扣
            $discount = ($discount_global !== '') ? $discount_global : 0;
            if (!is_numeric($discount) || $discount < 0 || $discount > 100 || floor($discount) != $discount) {
                throw new Exception("錯誤：帳單 $deb_num 的折扣 (Discount) 必須是 0 到 100 之間的整數。");
            }

            // 3. 更新 OC Invoice Expected (TR Table)
            // 邏輯：'expected' -> 1, 'cancel' -> 0
            if ($invoice_exp_status === 'expected') {
                $sql_tr = "UPDATE tr SET invoice_exp_status = '1' 
                           WHERE id IN (SELECT id FROM tr WHERE deb_num = $1 ORDER BY id LIMIT 1)";
                pg_query_params($dblink, $sql_tr, [$deb_num]);
            } elseif ($invoice_exp_status === 'cancel') {
                $sql_tr = "UPDATE tr SET invoice_exp_status = '0' WHERE deb_num = $1";
                pg_query_params($dblink, $sql_tr, [$deb_num]);
            }

            // 4. 更新 Bills 表
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

            if ($discount !== null) {
                $update_fields[] = "discount = $" . $p_idx++;
                $params[] = $discount;
            }

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
        echo "<script>alert('草稿已更新 (Updated)'); window.location.href = document.referrer;</script>";
    }

    // ---------------------------------------------------------
    // 動作二：APPLY (寄出帳單)
    // ---------------------------------------------------------
    elseif ($action == 'apply') {
        pg_query($dblink, "BEGIN");

        foreach ($ids as $id) {
            $id = intval($id);

            // 1. 鎖定並取得帳單與案件資料
            $sql_bill = "SELECT b.*, c.retainer_num, c.party_en_name_billing, c.retainer_foreign, c.retainer_ntd, c.retainer_case_num, c.billing_currency 
                         FROM bills b 
                         LEFT JOIN cases c ON b.case_num = c.case_num 
                         WHERE b.id = $1 FOR UPDATE OF b";
            $res_bill = pg_query_params($dblink, $sql_bill, [$id]);
            $bill = pg_fetch_assoc($res_bill);

            if (!$bill) throw new Exception("找不到帳單 ID: $id");

            $deb_num = $bill['deb_num'];
            $case_num = $bill['case_num'];
            $legal_services = $bill['legal_services'];

            // 2. Rainmaker 業績分配檢查（總和必須為 100）
            check_rainmaker_total($dblink, $case_num, $deb_num);

            // 3. 更新 bills 表：壓上 sent 日期、記錄原始金額與 retainer 資訊
            $bills_retainer_num = $bill['retainer_num'];
            $party_en_name = $bill['party_en_name_billing'];

            // 取得 retainer_amount（使用資料庫中的值，使用者需先按 Update 儲存）
            $final_retainer_amt = $bill['retainer_amount'];
            if ($final_retainer_amt === null || $final_retainer_amt === '') {
                $final_retainer_amt = 0;
            }

            $sql_apply = "UPDATE bills SET 
                            sent = $1, 
                            original_legal_services = $2, 
                            bills_retainer_num = $3, 
                            party_en_name_bills = $4,
                            retainer_amount = $5
                          WHERE id = $6";

            $res_apply = pg_query_params($dblink, $sql_apply, [
                $sent_date,
                $legal_services,
                $bills_retainer_num,
                $party_en_name,
                $final_retainer_amt,
                $id
            ]);

            if (!$res_apply) throw new Exception("更新帳單狀態失敗: " . pg_last_error($dblink));

            // 4. 記錄送出 IP 至 bills_current_sent 表
            $initial = $_SESSION['initial'] ?? '';
            $user_ip = $_SERVER['REMOTE_ADDR'];
            $sql_log = "INSERT INTO bills_current_sent (deb_num, sent_ip_address, initials) VALUES ($1, $2, $3)";
            pg_query_params($dblink, $sql_log, [$deb_num, $user_ip, $initial]);

            // 5. 若有預收款金額，寫入 send_email 表觸發通知
            if ($final_retainer_amt > 0) {
                $sql_email = "INSERT INTO send_email (case_num, deb_num) VALUES ($1, $2)";
                pg_query_params($dblink, $sql_email, [$case_num, $deb_num]);
            }

            // 6. 寫入 retainer_his 並更新 retainer_total 表
            $date_parts = explode('-', $sent_date);
            $r_year = $date_parts[0];
            $r_month = intval($date_parts[1]);
            $r_quarter = floor($r_month / 3.0 + 0.9);

            update_retainer_logic($dblink, $r_year, $r_quarter, $bills_retainer_num, $bill, $deb_num);

            // 7. 將 Rainmaker 業績資料寫入 rainmakers_his 表
            $sql_rain = "SELECT bills.case_num, deb_num, sent, initials, share, rain_type 
                         FROM bills 
                         LEFT JOIN rainmakers ON (bills.case_num = rainmakers.case_num) 
                         WHERE deb_num = $1 
                         ORDER BY sent, bills.case_num, rain_type, rainmakers.id";
            $res_rain = pg_query_params($dblink, $sql_rain, [$deb_num]);

            while ($row_r = pg_fetch_assoc($res_rain)) {
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

            // =========================================================
            // 8. Payments + Disbs_Payments 寫入 (預收款抵扣消帳)
            //    先扣 disbs（按 disb_code 優先順序），剩餘再扣 services
            // =========================================================
            // 查詢 client_pay_history 中已 Applied 的抵扣紀錄
            $sql_cph = "SELECT * FROM client_pay_history 
                         WHERE bills_case_num = $1 
                         AND payment_type = 'Retainer' 
                         AND payment_status = 'Applied' 
                         AND status = 0";
            $res_cph = pg_query_params($dblink, $sql_cph, [$case_num]);

            while ($cph = pg_fetch_assoc($res_cph)) {
                $cph_twd_amount = floatval($cph['twd_amount'] ?? 0);
                $cph_foreign_amount = floatval($cph['foreign_amount'] ?? 0);
                $cph_currency = $cph['currency'] ?? 'TWD';
                $cph_payment_method = $cph['payment_method'] ?? '';
                $cph_bank_account = $cph['bank_account'] ?? '';
                $cph_case_num = $cph['case_num'] ?? '';
                $cph_rate = floatval($cph['rate'] ?? 0);
                // 取得該筆 Applied 所對應的原始 Retainer (Received) 的 record_date
                $cph_relation_id = $cph['relation_id'] ?? null;
                $cph_record_date = '';
                if ($cph_relation_id) {
                    $sql_retainer = "SELECT record_date FROM client_pay_history
                                     WHERE id = $1
                                       AND payment_type = 'Retainer'
                                       AND payment_status = 'Received'
                                     LIMIT 1";
                    $res_retainer = pg_query_params($dblink, $sql_retainer, [$cph_relation_id]);
                    if ($res_retainer && pg_num_rows($res_retainer) > 0) {
                        $row_retainer = pg_fetch_assoc($res_retainer);
                        $cph_record_date = $row_retainer['record_date'] ?? '';
                    }
                }

                // 判斷帳單是否為外幣
                $billing_currency = $bill['billing_currency'] ?? '';
                $is_foreign_bill = ($billing_currency == 'English (USD)' || $billing_currency == 'English (EUR)');
                // 判斷抵扣的預收款是否也是相同外幣
                $is_foreign_receipt = ($cph_currency != 'TWD');
                $is_both_foreign = ($is_foreign_bill && $is_foreign_receipt);

                // --- 組合 payments 欄位 ---
                $bill_legal_services = floatval($bill['legal_services'] ?? 0);
                $bill_disbs = floatval($bill['disbs'] ?? 0);
                $bill_total = floatval($bill['total'] ?? 0);
                $bill_usd_total = floatval($bill['usd_total'] ?? 0);
                $bill_x_rate = floatval($bill['x_rate'] ?? 0);
                $bill_x_rate2 = floatval($bill['x_rate2'] ?? 0);
                $bill_foreign_total2 = floatval($bill['foreign_total2'] ?? 0);
                $bill_currency2 = $bill['currency2'] ?? '';

                // notes: record_date + 空格 + 被抵扣案號 + 空格 + "預收款沖抵帳單"
                $pay_notes = $cph_record_date . ' ' . $cph_case_num . ' 預收款沖抵帳單';

                // voucher_date & date_bank: 放 client_pay_history 的 record_date
                $pay_voucher_date = $cph_record_date;
                $pay_date_bank = $cph_record_date;

                // 取得當前年份用於 remit
                $current_year = date('Y', strtotime($sent_date));

                // 呼叫 get_check_remit 取得 check_num 或 remit_num
                $cr_result = get_check_remit($dblink, $cph_payment_method, $current_year);

                // =========================================================
                // 先查詢 disbursements，按 disb_code 優先順序 + id ASC 排序
                // 用於計算先扣 disbs 再扣 services 的分配邏輯
                // =========================================================
                $sql_disbs = "SELECT id, date, disb_code, disb_name, ntd_amount, bpm_rownum 
                              FROM disbursements 
                              WHERE deb_num = $1 
                              AND billed_flag = 0 
                              AND nocharge_flag = -1
                              ORDER BY
                                CASE disb_code
                                  WHEN '110' THEN 1
                                  WHEN '132' THEN 2
                                  WHEN '121' THEN 3
                                  WHEN '116' THEN 4
                                  WHEN '108' THEN 5
                                  WHEN '125' THEN 6
                                  WHEN '102' THEN 7
                                  WHEN '117' THEN 8
                                  WHEN '118' THEN 9
                                  WHEN '120' THEN 10
                                  WHEN '127' THEN 11
                                  ELSE 12
                                END,
                                id ASC";
                $res_disbs = pg_query_params($dblink, $sql_disbs, [$deb_num]);

                // 將 disbursements 存入陣列，以便先計算分配再寫入
                $disbs_rows = [];
                while ($disb = pg_fetch_assoc($res_disbs)) {
                    $disbs_rows[] = $disb;
                }

                // =========================================================
                // 計算抵扣分配：先扣 disbs，剩餘再扣 services
                // =========================================================
                if ($is_both_foreign) {
                    // 外幣：可用抵扣金額以台幣計算
                    $available = $cph_twd_amount > 0 ? $cph_twd_amount : ($cph_foreign_amount * $bill_x_rate2);
                } else {
                    // 台幣：可用抵扣金額
                    $available = $cph_twd_amount;
                }

                // 逐筆扣 disbs
                $actual_disbs_total = 0;
                $disbs_pay_amounts = []; // 記錄每筆 disbursement 實際扣除的金額

                foreach ($disbs_rows as $idx => $disb) {
                    $disb_ntd = floatval($disb['ntd_amount']);
                    if ($available >= $disb_ntd) {
                        // 全額扣除此筆 disbursement
                        $disb_pay = $disb_ntd;
                    } else {
                        // 部分扣除（剩餘金額不夠）
                        $disb_pay = max(0, $available);
                    }
                    $available -= $disb_pay;
                    $actual_disbs_total += $disb_pay;
                    $disbs_pay_amounts[$idx] = $disb_pay;
                }

                // 剩餘金額分配給 services
                $pay_disbs = $actual_disbs_total;
                $pay_legal_services = min($available, $bill_legal_services); // available 是扣完 disbs 後的剩餘

                if ($is_both_foreign) {
                    // =============================================
                    // 外幣消帳邏輯
                    // =============================================
                    $pay_rec_ntd = round($cph_foreign_amount * $cph_rate);  // 外幣 * rec_x_rate，取整寫入 integer 欄位
                    $pay_rec_usd = round($cph_foreign_amount, 2); // 可能是部分
                    $pay_method = $cph_payment_method;
                    // pay_legal_services 和 pay_disbs 已在上面計算
                    $pay_rec_x_rate = $cph_rate;
                    $pay_sub_retainer = -1 * $cph_foreign_amount;  // 負數
                    $pay_sub_retainer_ntd = round($pay_sub_retainer * $cph_rate); // sub_retainer * rec_x_rate，負數，取整
                    $pay_currency = $cph_currency;
                    $pay_bank_account = $cph_bank_account;
                    $pay_rec_other_rate = $bill_x_rate2;
                    $pay_foreign_amount = $cph_foreign_amount;
                    $pay_bills_currency = $bill_currency2;
                    $pay_rec_bank = round($pay_rec_x_rate * $pay_foreign_amount);
                    // foreign_legal / foreign_disbs => 來自 bills 外幣欄位
                    $pay_foreign_legal  = floatval($bill['foreign_legal2'] ?? 0);
                    $pay_foreign_disbs  = floatval($bill['foreign_disbs2'] ?? 0);

                    // exchange_gain_loss 計算
                    $pay_exchange_gain_loss = 0;
                    if (
                        $cph_payment_method == 'A' || $cph_payment_method == 'B' ||
                        (($cph_payment_method == 'C' || $cph_payment_method == 'D' ||
                            $cph_payment_method == 'E' || $cph_payment_method == 'G') &&
                            ($pay_rec_other_rate != $pay_rec_x_rate))
                    ) {
                        $pay_exchange_gain_loss = round($pay_foreign_amount * $pay_rec_x_rate - $pay_legal_services - $pay_disbs);
                    }

                    // remit / check_num
                    $pay_check_num = null;
                    $pay_remit_num = null;
                    if ($cr_result['type'] == 'C') {
                        $pay_check_num = $cr_result['number'];
                    } else {
                        $pay_remit_num = $cr_result['number'];
                    }

                    $sql_ins_pay = "INSERT INTO payments (
                                        case_num, deb_num, rec_date, rec_ntd, method, notes,
                                        legal_services, disbs, rec_usd, rec_x_rate,
                                        voucher_date, date_bank, check_num, remit_num,
                                        sub_retainer, sub_retainer_ntd, currency, bank_account,
                                        rec_other_rate, foreign_amount, bills_currency,
                                        exchange_gain_loss,
                                        with_tax, rec_bank, other_loss_gain,
                                        sub_temp_pay, sub_temp_pay_ntd, bank_fee_dom,
                                        foreign_legal, foreign_disbs
                                    ) VALUES (
                                        $1, $2, $3, $4, $5, $6,
                                        $7, $8, $9, $10,
                                        $11, $12, $13, $14,
                                        $15, $16, $17, $18,
                                        $19, $20, $21,
                                        $22,
                                        0, $23, 0,
                                        0, 0, 0,
                                        $24, $25
                                    ) RETURNING id";

                    $res_ins_pay = pg_query_params($dblink, $sql_ins_pay, [
                        $case_num,           // $1
                        $deb_num,            // $2
                        $sent_date,          // $3
                        $pay_rec_ntd,        // $4
                        $pay_method,         // $5
                        $pay_notes,          // $6
                        $pay_legal_services, // $7
                        $pay_disbs,          // $8
                        $pay_rec_usd,        // $9
                        $pay_rec_x_rate,     // $10
                        $pay_voucher_date,   // $11
                        $pay_date_bank,      // $12
                        $pay_check_num,      // $13
                        $pay_remit_num,      // $14
                        $pay_sub_retainer,     // $15
                        $pay_sub_retainer_ntd, // $16
                        $pay_currency,         // $17
                        $pay_bank_account,     // $18
                        $pay_rec_other_rate,   // $19
                        $pay_foreign_amount,   // $20
                        $pay_bills_currency,   // $21
                        $pay_exchange_gain_loss, // $22
                        $pay_rec_bank,         // $23
                        $pay_foreign_legal,    // $24
                        $pay_foreign_disbs     // $25
                    ]);
                } else {
                    // =============================================
                    // 台幣消帳邏輯
                    // =============================================
                    $pay_rec_ntd = $cph_twd_amount;  // 抵扣金額
                    $pay_method = $cph_payment_method;
                    // pay_legal_services 和 pay_disbs 已在上面計算
                    $pay_rec_usd = ($bill_x_rate > 0) ? round(($pay_legal_services + $pay_disbs) / $bill_x_rate, 2) : round($bill_usd_total, 2);
                    $pay_rec_x_rate = $cph_rate;
                    $pay_sub_retainer_ntd = -1 * $cph_twd_amount;  // 負數
                    $pay_currency = 'USD';
                    $pay_bank_account = $cph_bank_account;
                    $pay_rec_other_rate = $bill_x_rate;
                    $pay_foreign_amount = ($bill_x_rate > 0) ? ($pay_legal_services + $pay_disbs) / $bill_x_rate : $bill_usd_total;
                    $pay_bills_currency = 'USD';

                    // remit / check_num
                    $pay_check_num = null;
                    $pay_remit_num = null;
                    if ($cr_result['type'] == 'C') {
                        $pay_check_num = $cr_result['number'];
                    } else {
                        $pay_remit_num = $cr_result['number'];
                    }

                    $sql_ins_pay = "INSERT INTO payments (
                                        case_num, deb_num, rec_date, rec_ntd, method, notes,
                                        legal_services, disbs, rec_usd, rec_x_rate,
                                        voucher_date, date_bank, check_num, remit_num,
                                        sub_retainer_ntd, currency, bank_account,
                                        rec_other_rate, foreign_amount, bills_currency,
                                        exchange_gain_loss,
                                        with_tax, rec_bank, other_loss_gain,
                                        sub_retainer, sub_temp_pay, sub_temp_pay_ntd, bank_fee_dom
                                    ) VALUES (
                                        $1, $2, $3, $4, $5, $6,
                                        $7, $8, $9, $10,
                                        $11, $12, $13, $14,
                                        $15, $16, $17,
                                        $18, $19, $20,
                                        0,
                                        0, 0, 0,
                                        0, 0, 0, 0
                                    ) RETURNING id";

                    $res_ins_pay = pg_query_params($dblink, $sql_ins_pay, [
                        $case_num,           // $1
                        $deb_num,            // $2
                        $sent_date,          // $3
                        $pay_rec_ntd,        // $4
                        $pay_method,         // $5
                        $pay_notes,          // $6
                        $pay_legal_services, // $7
                        $pay_disbs,          // $8
                        $pay_rec_usd,        // $9
                        $pay_rec_x_rate,     // $10
                        $pay_voucher_date,   // $11
                        $pay_date_bank,      // $12
                        $pay_check_num,      // $13
                        $pay_remit_num,      // $14
                        $pay_sub_retainer_ntd, // $15
                        $pay_currency,         // $16
                        $pay_bank_account,     // $17
                        $pay_rec_other_rate,   // $18
                        $pay_foreign_amount,   // $19
                        $pay_bills_currency    // $20
                    ]);
                }

                if (!$res_ins_pay) {
                    throw new Exception("Insert payments failed: " . pg_last_error($dblink));
                }

                // 取得剛插入的 payments ID
                $pay_row = pg_fetch_assoc($res_ins_pay);
                $payments_id = $pay_row['id'];

                // =========================================================
                // 寫入 disbs_payments（使用計算後的 pay_amount）
                // =========================================================
                foreach ($disbs_rows as $idx => $disb) {
                    $disb_pay_amount = $disbs_pay_amounts[$idx]; // 實際消帳金額（可能是部分）
                    $disb_bpm_rownum = intval($disb['bpm_rownum']) * -1; // 負數

                    $sql_ins_dp = "INSERT INTO disbs_payments (
                                        disbs_ref_id, payments_ref_id, case_num, deb_num,
                                        date, payment_date, voucher_date,
                                        disb_code, disb_name, amount, pay_amount, bpm_rownum
                                    ) VALUES (
                                        $1, $2, $3, $4,
                                        $5, $6, $7,
                                        $8, $9, $10, $11, $12
                                    )";

                    $res_dp = pg_query_params($dblink, $sql_ins_dp, [
                        $disb['id'],
                        $payments_id,
                        $case_num,
                        $deb_num,
                        $disb['date'],
                        $sent_date,         // payment_date = rec_date = sent_date
                        $pay_voucher_date,  // voucher_date = client_pay_history 的 record_date
                        $disb['disb_code'],
                        $disb['disb_name'],
                        $disb['ntd_amount'],
                        $disb_pay_amount,
                        $disb_bpm_rownum
                    ]);

                    if (!$res_dp) {
                        throw new Exception("Insert disbs_payments failed: " . pg_last_error($dblink));
                    }
                }

                // =========================================================
                // 更新 client_pay_history 的 status 為 1，代表此紀錄已押 sent_date
                // =========================================================
                $sql_cph_status = "UPDATE client_pay_history SET status = 1 WHERE id = $1";
                $res_cph_status = pg_query_params($dblink, $sql_cph_status, [$cph['id']]);
                if (!$res_cph_status) {
                    throw new Exception("Update client_pay_history status failed: " . pg_last_error($dblink));
                }
            }
        }

        pg_query($dblink, "COMMIT");
        echo "<script>alert('帳單已成功套用日期並寄出 (Applied Sent Date)'); window.location.href = document.referrer;</script>";
    }
} catch (Exception $e) {
    pg_query($dblink, "ROLLBACK");
    $msg = addslashes($e->getMessage());
    echo "<script>alert('操作失敗：$msg'); history.back();</script>";
} finally {
    if ($dblink) pg_close($dblink);
}


// =====================================================================
// 輔助函數區
// =====================================================================

function check_rainmaker_total($dblink, $case_num, $deb_num) {
    $sql_share = "SELECT SUM(share) as total_share FROM rainmakers WHERE case_num = $1";
    $res_share = pg_query_params($dblink, $sql_share, [$case_num]);
    $row_share = pg_fetch_assoc($res_share);

    $total = $row_share ? floatval($row_share['total_share']) : 0;

    if ($total != 100) {
        throw new Exception("錯誤：帳單 $deb_num 的 Rainmaker 業績分配總和為 $total (必須是 100)。請檢查該案件的 Credit 設定。");
    }
}

function update_retainer_logic($dblink, $retainer_year, $retainer_quarter, $retainer_num, $bill_data, $deb_num) {
    if ($retainer_num === null) {
        $retainer_num = '';
    }

    $currency = isset($bill_data['billing_currency']) && strpos($bill_data['billing_currency'], 'USD') !== false ? 'USD' : 'TWD';

    if (in_array($retainer_num, ['PPP', 'TDG', 'BMT'])) {
        $currency = 'TWD';
        $in_case_num = 'AZN00210';
    } else {
        $in_case_num = $bill_data['retainer_case_num'];
    }

    $total_amount = $bill_data['total'];

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

    $sql_bf = "UPDATE bills SET retainer_flag = '1' WHERE deb_num = $1";
    $res_bf = pg_query_params($dblink, $sql_bf, [$deb_num]);
    if (!$res_bf) throw new Exception("Update bills retainer_flag failed.");

    $use_total = $total_amount;

    $sql_rt_check = "SELECT * FROM retainer_total 
                     WHERE retainer_num = $1 AND retainer_year = $2 AND retainer_quarter = $3
                     FOR UPDATE";
    $res_rt = pg_query_params($dblink, $sql_rt_check, [$retainer_num, $retainer_year, $retainer_quarter]);

    if ($rt_data = pg_fetch_assoc($res_rt)) {
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

/**
 * 產生 check_num 或 remit_num
 *
 * @param resource $dblink PostgreSQL connection
 * @param string $method 付款方式 (A/B/C/D/E/F/G)
 * @param string $current_year 目標年度
 * @return array ['type' => 'C' or 'R', 'number' => 'C0001' or 'R0001']
 */
function get_check_remit($dblink, $method, $current_year) {
    // 依 method 判斷 type
    if ($method == 'A' || $method == 'C') {
        $cr = 'C';
    } elseif (in_array($method, ['B', 'D', 'E', 'F', 'G'])) {
        $cr = 'R';
    } else {
        $cr = 'R'; // 預設
    }

    // 查詢目前最大編號
    if ($cr == 'C') {
        $sql = "SELECT check_num AS cr_num FROM payments 
                WHERE check_num LIKE 'C%' 
                AND rec_date >= $1 AND rec_date <= $2 
                ORDER BY check_num DESC LIMIT 1";
    } else {
        $sql = "SELECT remit_num AS cr_num FROM payments 
                WHERE remit_num LIKE 'R%' 
                AND rec_date >= $1 AND rec_date <= $2 
                ORDER BY remit_num DESC LIMIT 1";
    }

    $start_date = $current_year . '-01-01';
    $end_date = $current_year . '-12-31';
    $res = pg_query_params($dblink, $sql, [$start_date, $end_date]);
    $row = pg_fetch_assoc($res);

    $cr_num_str = $row ? $row['cr_num'] : '';

    // 解析數字部分
    if ($cr == 'C') {
        $num = intval(preg_replace('/^C0*/', '', $cr_num_str));
    } else {
        $num = intval(preg_replace('/^R0*/', '', $cr_num_str));
    }
    $num++;

    // 格式化編號 (至少4位數)
    $formatted = $cr . str_pad($num, 4, '0', STR_PAD_LEFT);

    return [
        'type' => $cr,
        'number' => $formatted
    ];
}
