<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>Draft Bill List</title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/winkler.css">
    <link rel="stylesheet" href="css/winkler-rwd.css">
    <link rel="stylesheet" href="css/left-search.css">
</head>

<body data-spy="scroll" data-target=".amanda-nav">
    <?php
    require_once("menu.php");
    ?>

    <!-- 側邊搜尋內容 -->
    <?php
    require_once("draft_bill_list_search.php");
    ?>
    <!-- 側邊搜尋內容結束-->

    <div id="winkler-container" class="active">
        <!-- 標題 -->
        <div class="block-hv100">
            <div class="all-heading">
                <h3>
                    <?php
                    require_once('test_db/draft_bill_list_db.php');

                    $result_data = [];
                    $totals = [];
                    $can_reset = false;

                    if ($_SERVER["REQUEST_METHOD"] == "GET") {
                        $case_number = isset($_GET['case_number']) ? $_GET['case_number'] : '';
                        $match_or_like = isset($_GET['match_or_like']) ? $_GET['match_or_like'] : 'like';
                        $case_manager = isset($_GET['case_manager']) ? $_GET['case_manager'] : '';

                        // 簡單顯示查詢條件
                        if ($case_number != '' && $case_manager != '') {
                            echo "Case Number: $case_number | Case Manager: $case_manager";
                        } elseif ($case_number != '') {
                            echo "Case Number: $case_number";
                        } elseif ($case_manager != '') {
                            echo "Case Manager: $case_manager";
                        } else {
                            echo "Default";
                        }

                        try {
                            // 呼叫函數取得資料
                            $api_result = getData($case_number, $match_or_like, $case_manager);
                            $result_data = $api_result['rows'];
                            $totals = $api_result['totals'];

                            // 取得權限旗標
                            if (isset($api_result['can_reset'])) {
                                $can_reset = $api_result['can_reset'];
                            }
                        } catch (Exception $e) {
                            $errorMessage = $e->getMessage();
                            echo "<script>alert(" . json_encode($errorMessage) . ");</script>";
                        }
                    }
                    ?>
                </h3>
            </div>

            <div class="table-responsive">
                <table class="table hv1-table table-hover  ">
                    <thead>
                        <tr>
                            <th class="text-center"><input type="checkbox" id="select_all"></th>
                            <th class="text-center">Created</th>
                            <th class="text-center">Case Num</th>
                            <th class="text-center">Manager</th>
                            <th class="text-center">Debit Note</th>
                            <th class="text-center">Legal Services</th>
                            <th class="text-center">Disbs</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Edit</th>
                            <th class="text-center">Billing Note</th>
                            <th class="text-center">OC Invoice</th>
                            <th class="text-center">ATI Category</th>
                            <th class="text-center">Retainer</th>
                            <th class="text-center">Reset</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        $current_currency_flag = null; // 用來追蹤當前幣別區塊

                        if (!empty($result_data)) {
                            foreach ($result_data as $key => $row) {
                                $deb_num = $row['deb_num'];
                                $id = $row['id'];

                                // --- 幣別分組標題顯示邏輯 ---
                                // 根據 billing_currency 決定顯示文字
                                if ($row['billing_currency'] == 'English (USD)') {
                                    $currency_label = 'USD';
                                } elseif ($row['billing_currency'] == 'English (EUR)') {
                                    $currency_label = 'EUR';
                                } else {
                                    $currency_label = 'TWD';
                                }

                                // --- 總計顯示邏輯 ---
                                if ($currency_label !== $current_currency_flag) {
                                    // 取得當前幣別的總數
                                    if ($current_currency_flag !== null) {
                                        $total_legal = $totals[strtolower($current_currency_flag)]['fmt_legal'];
                                        $total_disbs = $totals[strtolower($current_currency_flag)]['fmt_disbs'];
                                        $total_total = $totals[strtolower($current_currency_flag)]['fmt_total'];
                                        $total_count = $totals[strtolower($current_currency_flag)]['count'];
                                        echo "<tr style='background-color: d1e7dd;'>
                                                <td></td>
                                                <td></td>
                                                <td class='text-left'>Total ({$current_currency_flag})</td>
                                                <td class='text-right'>{$total_count}</td>
                                                <td></td>
                                                <td class='text-right'>{$total_legal}</td>
                                                <td class='text-right'>{$total_disbs}</td>
                                                <td class='text-right'>{$total_total}</td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>";
                                    }

                                    // 更新當前幣別
                                    $current_currency_flag = $currency_label;
                                    echo "<tr style='background-color: fff3cd;'><th colspan='15'><h4 class='text-center'>$currency_label</h4></th></tr>";
                                }

                                // --- 數值顯示邏輯 (根據幣別選擇顯示欄位) ---
                                if ($currency_label == 'USD' || $currency_label == 'EUR') {
                                    $display_legal = $row['fmt_foreign_show_legal'] . '<br>' . $row['currency2'];
                                    $display_disbs = $row['fmt_foreign_show_disbs'] . '<br>' . $row['currency2'];
                                    $display_total = $row['fmt_foreign_total'] . '<br>' . $row['currency2'];
                                } else {
                                    $display_legal = $row['fmt_show_legal'];
                                    $display_disbs = $row['fmt_show_disbs'];
                                    $display_total = $row['fmt_total'];
                                }

                                // --- OC Invoice 欄位顯示邏輯 ---
                                $oc_invoice_html = '';
                                if ($row['show_oc'] == 1) {
                                    $oc_invoice_html .= "<label style='font-weight:normal; cursor:pointer;'>";
                                    $oc_invoice_html .= "<input type='radio' name='invoice_exp_status_{$id}' value='expected'> Expected";
                                    $oc_invoice_html .= "</label><br>";

                                    $oc_invoice_html .= "<label style='font-weight:normal; cursor:pointer;'>";
                                    $oc_invoice_html .= "<input type='radio' name='invoice_exp_status_{$id}' value='cancel'> Cancel";
                                    $oc_invoice_html .= "</label>";
                                }

                                // --- ATI 欄位顯示邏輯 ---
                                $ati_html = "";

                                if ($row['show_ati'] == 1) {
                                    // 預先取得現有值
                                    $ati_cate1 = isset($row['ati_cate1']) ? $row['ati_cate1'] : '';
                                    $ati_cate2 = isset($row['ati_cate2']) ? $row['ati_cate2'] : '';
                                    $ati_cate12 = isset($row['ati_cate12']) ? $row['ati_cate12'] : '';
                                    $ati_cate22 = isset($row['ati_cate22']) ? $row['ati_cate22'] : '';
                                    $ati_cate13 = isset($row['ati_cate13']) ? $row['ati_cate13'] : '';
                                    $ati_cate23 = isset($row['ati_cate23']) ? $row['ati_cate23'] : '';

                                    $new_matter = isset($row['new_matter']) && $row['new_matter'] == 1 ? 'checked' : '';
                                    $new_matter2 = isset($row['new_matter2']) && $row['new_matter2'] == 1 ? 'checked' : '';
                                    $new_matter3 = isset($row['new_matter3']) && $row['new_matter3'] == 1 ? 'checked' : '';

                                    $project_owner = isset($row['project_owner']) ? $row['project_owner'] : '';
                                    $class_count = isset($row['class_count']) ? $row['class_count'] : '';
                                    $azn_budget_status = isset($row['azn_budget_status']) ? $row['azn_budget_status'] : '';

                                    $ati_html .= "<div class='ati-container' data-id='{$id}'>";

                                    // 1. 頂部通用欄位
                                    $ati_html .= "
                                        <div>
                                            <label style='font-weight:normal'>
                                                <input type='checkbox' name='new_matter_{$id}' value='1' {$new_matter}> <strong>New MVE</strong>
                                            </label>
                                        </div>
                                        <div>
                                            Project Owner: <input type='text' class='form-control' style='width: 180px; display:inline-block;' name='project_owner_{$id}' value='{$project_owner}'>
                                        </div>
                                        <div>
                                            Class(es): <input type='text' class='form-control' style='width: 80px; display:inline-block;' name='class_count_{$id}' value='{$class_count}'>
                                        </div>
                                        <div style='margin-top: 5px; margin-bottom: 5px;'>
                                            <button type='button' class='btn btn-sm btn-success btn-add-ati'>＋</button>
                                            <button type='button' class='btn btn-sm btn-danger btn-remove-ati'>－</button>
                                        </div>
                                    ";

                                    // 2. 第一組分類 (Set 1)
                                    $ati_html .= "
                                        <div class='ati-set-block ati-set-1 well well-sm' style='margin-bottom: 5px;'>
                                            <strong>Set 1</strong><br>
                                            <select class='form-control ati-cate1' style='width: auto; max-width: 100%;' name='ati_cate1_{$id}' data-set='1' data-selected='{$ati_cate1}'>
                                                <option value=''>-- Select 1st --</option>
                                            </select>
                                            <div style='margin-top: 2px;'></div>
                                            <select class='form-control ati-cate2' style='width: auto; max-width: 100%;' name='ati_cate2_{$id}' data-selected='{$ati_cate2}'>
                                                <option value=''>-- Select 2nd --</option>
                                            </select>
                                            <div style='margin-top: 2px;'></div>
                                            <select class='form-control ati-status' style='width: auto; max-width: 100%;' name='azn_budget_status_{$id}' data-selected='{$azn_budget_status}'>
                                                <option value=''>-- Status --</option>
                                            </select>
                                        </div>
                                    ";

                                    // 3. 第二組分類 (Set 2)
                                    $display2 = ($ati_cate12 != '') ? '' : 'display:none;';
                                    $ati_html .= "
                                        <div class='ati-set-block ati-set-2 well well-sm' style='margin-bottom: 5px; {$display2}'>
                                            <strong>Set 2</strong> 
                                            <br>
                                            <label style='font-weight:normal;'>
                                                <input type='checkbox' name='new_matter2_{$id}' value='1' {$new_matter2}> New MVE
                                            </label><br>
                                            <select class='form-control ati-cate1' style='width: auto; max-width: 100%;' name='ati_cate12_{$id}' data-set='2' data-selected='{$ati_cate12}'>
                                                <option value=''>-- Select 1st --</option>
                                            </select>
                                            <div style='margin-top: 2px;'></div>
                                            <select class='form-control ati-cate2' style='width: auto; max-width: 100%;' name='ati_cate22_{$id}' data-selected='{$ati_cate22}'>
                                                <option value=''>-- Select 2nd --</option>
                                            </select>
                                        </div>
                                    ";

                                    // 4. 第三組分類 (Set 3)
                                    $display3 = ($ati_cate13 != '') ? '' : 'display:none;';
                                    $ati_html .= "
                                        <div class='ati-set-block ati-set-3 well well-sm' style='margin-bottom: 5px; {$display3}'>
                                            <strong>Set 3</strong> 
                                            <br>
                                            <label style='font-weight:normal;'>
                                                <input type='checkbox' name='new_matter3_{$id}' value='1' {$new_matter3}> New MVE
                                            </label><br>
                                            <select class='form-control ati-cate1' style='width: auto; max-width: 100%;' name='ati_cate13_{$id}' data-set='3' data-selected='{$ati_cate13}'>
                                                <option value=''>-- Select 1st --</option>
                                            </select>
                                            <div style='margin-top: 2px;'></div>
                                            <select class='form-control ati-cate2' style='width: auto; max-width: 100%;' name='ati_cate23_{$id}' data-selected='{$ati_cate23}'>
                                                <option value=''>-- Select 2nd --</option>
                                            </select>
                                        </div>
                                    ";

                                    $ati_html .= "</div>"; // End ati-container
                                }

                                // --- Retainer 欄位顯示邏輯 ---
                                $retainer_html = "";

                                // Part A: 顯示 Retainer 案件編號
                                if (!empty($row['retainer_case_num'])) {
                                    $retainer_html .= htmlspecialchars($row['retainer_case_num']) . "<br>";
                                }

                                // Part B: 顯示餘額 (根據帳單幣別判斷)
                                // 邏輯：若非 USD 也非 EUR，則視為台幣；否則顯示外幣
                                if ($row['billing_currency'] != 'English (USD)' && $row['billing_currency'] != 'English (EUR)') {
                                    $retainer_html .= "TWD";
                                    // TWD 模式
                                    // fmt_retainer_ntd 來自後端 draft_bill_list_db.php 的處理
                                    if (!empty($row['fmt_retainer_ntd'])) {
                                        $retainer_html .= " " . $row['fmt_retainer_ntd'];
                                    }
                                } else {
                                    // 外幣模式
                                    if (!empty($row['fmt_retainer_foreign'])) {
                                        $f_curr = isset($row['retainer_foreign_currency']) ? $row['retainer_foreign_currency'] : '';
                                        $retainer_html .= $f_curr . " " . $row['fmt_retainer_foreign'];
                                    }
                                }

                                // Part C: 顯示扣抵輸入框 (Minus retainer)
                                // 判斷條件：只要 (台幣餘額 > 0) 或 (外幣餘額 > 0) 就顯示
                                $raw_r_ntd = isset($row['retainer_ntd']) ? floatval($row['retainer_ntd']) : 0;
                                $raw_r_foreign = isset($row['retainer_foreign']) ? floatval($row['retainer_foreign']) : 0;

                                if ($raw_r_ntd > 0 || $raw_r_foreign > 0) {
                                    // 檢查是否已有設定的扣抵金額，若無則預設為 0
                                    $r_amount_val = (isset($row['retainer_amount']) && $row['retainer_amount'] !== '') ? $row['retainer_amount'] : '0';
                                    $r_input_name = "retainer_amount_" . $row['id'];

                                    $retainer_html .= "<br><div style='margin-top:5px;'>Minus retainer</div>";
                                    // 使用 Bootstrap 樣式 input
                                    $retainer_html .= "<input type='text' class='form-control input-sm' style='width: 80px; display:inline-block;' name='{$r_input_name}' value='{$r_amount_val}'>";
                                }

                                // --- 新增：Reset 按鈕顯示邏輯 ---
                                $reset_html = "";
                                if ($can_reset) {
                                    // 參考你的 update 連結格式，使用 http://billing/cgi-bin/...
                                    // 若你的環境不需要完整網域，也可以只寫 /cgi-bin/draft_reset.pl
                                    $reset_html = "<a href='http://billing/cgi-bin/draft_reset.pl?deb_num={$deb_num}'>Reset</a>";
                                }

                                // --- 輸出表格行 ---
                                echo "<tr>
                                        <td class='text-center'><input type='checkbox' name='row_check_box[]' value='{$row['id']}'></td>
                                        <td class='text-left'>{$row['draft_created']}</td>
                                        <td class='text-left'>{$row['case_num']}</td>
                                        <td class='text-left'>{$row['case_manager']}</td>
                                        <td class='text-left'>{$deb_num}</td>
                                        <td class='text-right'>{$display_legal}</td>
                                        <td class='text-right'>{$display_disbs}</td>
                                        <td class='text-right'>{$display_total}</td>
                                        <td class='text-left'>
                                            <a href='http://billing/cgi-bin/bill_edit.pl?deb_num={$deb_num}'>Update</a><br>
                                            <a href='http://billing/cgi-bin/disb_new.pl?deb_num={$deb_num}'>Add Disbursements</a>
                                        </td>
                                        <td class='text-left'>{$row['billing_note']}</td>
                                        <td class='text-left'>{$oc_invoice_html}</td>
                                        <td class='text-left'>{$ati_html}</td>
                                        <td class='text-left'>{$retainer_html}</td>
                                        <td class='text-left'>{$reset_html}</td>
                                    </tr>";
                            }

                            // --- 處理最後一個幣別的小計 ---
                            if ($current_currency_flag != null) {
                                $total_legal = $totals[strtolower($current_currency_flag)]['fmt_legal'];
                                $total_disbs = $totals[strtolower($current_currency_flag)]['fmt_disbs'];
                                $total_total = $totals[strtolower($current_currency_flag)]['fmt_total'];
                                $total_count = $totals[strtolower($current_currency_flag)]['count'];

                                echo "<tr style='background-color: d1e7dd;'>
                                        <td></td>
                                        <td></td>
                                        <td class='text-left'>Total ({$current_currency_flag})</td>
                                        <td class='text-right'>{$total_count}</td>
                                        <td></td>
                                        <td class='text-right'>{$total_legal}</td>
                                        <td class='text-right'>{$total_disbs}</td>
                                        <td class='text-right'>{$total_total}</td>
                                        <td></td><td></td><td></td><td></td><td></td><td></td>
                                    </tr>";
                            }
                        } else {
                            echo "無資料";
                        }
                        ?>
                    </tbody>

                    <tfoot>
                        <th></th>
                        <th></th>
                        <th class='text-left'>
                            小計 (TWD)
                        </th>
                        <?php
                        echo "<th class='text-right'>" . $totals['all']['count'] . "</th>";
                        ?>
                        <th></th>
                        <?php
                        echo "<th class='text-right'>" . $totals['all']['fmt_legal'] . "</th>";
                        echo "<th class='text-right'>" . $totals['all']['fmt_disbs'] . "</th>";
                        echo "<th class='text-right'>" . $totals['all']['fmt_total'] . "</th>";
                        ?>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="/js/nav-topfix.js"></script>
    <script type='text/javascript' src="/js/search.js"></script>

    <script type="text/javascript">
        $('nav').affix({
            offset: {
                top: 50,
            }
        })
        $(document.body).on('hidden.bs.modal', function() {
            $('#myModal').removeData('bs.modal')
        });

        //Edit SL: more universal
        $(document).on('hidden.bs.modal', function(e) {
            $(e.target).removeData('bs.modal');
        });

        // 全選/取消全選功能
        $('#select_all').on('click', function() {
            var isChecked = $(this).prop('checked');
            $('input[name="row_check_box[]"]').prop('checked', isChecked);
        });

        // 當個別複選框狀態改變時，同步更新全選框狀態
        $(document).on('change', 'input[name="row_check_box[]"]', function() {
            var totalCheckboxes = $('input[name="row_check_box[]"]').length;
            var checkedCheckboxes = $('input[name="row_check_box[]"]:checked').length;
            $('#select_all').prop('checked', totalCheckboxes === checkedCheckboxes);
        });
    </script>

    <script>
        // 定義 ATI 資料結構 (Configuration)
        const ATI_CONFIG = {
            // 定義第一層與第二層的關係
            categories: {
                "Clearance": [
                    "Knockout Search", "Prelim Search", "Full Search", "Logo Searches",
                    "Clearance Investigation & Deep Dive", "以下為舊分類", "Amazon", "AWS",
                    "Digital", "Media, Advertising & Content Protection (IMDb)",
                    "Private Label, Retail & Consumables", "Prime"
                ],
                "Other Special Projects": [
                    "Knockout Search", "Prelim Search", "Full Search",
                    "Logo Searches", "Clearance Investigation & Deep Dive"
                ],
                "Trademark Filing": [
                    "App Filing (Amazon)", "App Filing (AWS)", "App Filing (Digital)",
                    "App Filing (Media, Advertising & Content Protection)",
                    "App Filing (Private Label, Retail & Consumables)", "App Filing (Prime)"
                ],
                "Trademark Filing w/ Priority": [
                    "App Filing (Amazon)", "App Filing (AWS)", "App Filing (Digital)",
                    "App Filing (Media, Advertising & Content Protection)",
                    "App Filing (Private Label, Retail & Consumables)", "App Filing (Prime)"
                ],
                "Trademark Prosecution": [
                    "Office Action", "Statement/Declaration of Use/Extensons", "Appeal",
                    "Publication", "Registration", "Renewal", "Power of Attorney",
                    "Co-existence", "Recordal", "Division", "Priority Claim"
                ],
                "Enforcement and Disputes": [
                    "Acquisition/Co-existence", "Opposition/Invalidation", "Litigation",
                    "Abuse Complaint", "Cancellation", "Domain name", "Infringement/Policing",
                    "Possible Opposition", "C&D letter", "Misuse", "Appeal",
                    "Investigation/Analysis", "Power of Attorney", "以下為舊分類",
                    "Co-existence", "Watch Notice"
                ],
                "Trade Dress": [
                    "App Filing (Amazon)", "App Filing (AWS)", "App Filing (Digital)",
                    "App Filing (Media, Advertising & Content Protection)",
                    "App Filing (Private Label, Retail & Consumables)", "App Filing (Prime) "
                ],
                "General": [
                    "Amazon", "AWS", "Clearance Investigation & Deep Dive", "Digital",
                    "Media, Advertising & Content Protection (IMDb)",
                    "Private Label, Retail & Consumables", "Prime"
                ],
                "Corporate": []
            },
            // 定義 Status (根據第一層分類決定要顯示哪一組 Status)
            status_mapping: {
                "Trademark Filing": "trademark",
                "Trademark Filing w/ Priority": "trademark",
                "Trademark Prosecution": "trademark",
                "Enforcement and Disputes": "disputes"
            },
            status_options: {
                "trademark": [
                    "Filed", "Registered", "Published", "Allowed", "Abandoned", "Suspended",
                    "Acknowledged", "Instructed", "To Be Filed", "Cautionary Notice",
                    "Withdrawn", "Rejected", "Lapsed"
                ],
                "disputes": [
                    "Resolved", "Inactive", "Active", "Monitoring"
                ]
            }
        };

        $(document).ready(function() {

            // --- 1. 先綁定事件監聽 (Event Binding) ---
            // 必須在觸發 change 之前就先準備好監聽，這樣才能收到訊號
            $(document).on('change', '.ati-cate1', function() {
                const $select1 = $(this);
                const category = $select1.val();
                const $container = $select1.closest('.ati-set-block');
                const $select2 = $container.find('.ati-cate2');
                const $statusSelect = $container.find('.ati-status');

                // A. 更新第二層選單 (2nd)
                // 嘗試取得 PHP 帶入的預設值 (HTML attribute)
                const savedSelect2 = $select2.attr('data-selected');

                $select2.empty().append('<option value="">-- Select 2nd --</option>');

                if (category && ATI_CONFIG.categories[category]) {
                    $.each(ATI_CONFIG.categories[category], function(index, value) {
                        $select2.append($('<option>', {
                            value: value,
                            text: value
                        }));
                    });
                }

                // 如果有預設值，且該值存在於新的選項中，就選取它
                if (savedSelect2 && $select2.find(`option[value='${savedSelect2}']`).length > 0) {
                    $select2.val(savedSelect2);
                    // 選取完後移除 data 屬性，避免下次切換分類時又誤判自動帶入
                    $select2.removeAttr('data-selected');
                }

                // B. 更新狀態選單 (Status)
                if ($statusSelect.length > 0) {
                    const savedStatus = $statusSelect.attr('data-selected');

                    $statusSelect.empty().append('<option value="">-- Status --</option>');

                    const statusType = ATI_CONFIG.status_mapping[category];
                    if (statusType && ATI_CONFIG.status_options[statusType]) {
                        $.each(ATI_CONFIG.status_options[statusType], function(index, value) {
                            $statusSelect.append($('<option>', {
                                value: value,
                                text: value
                            }));
                        });
                    }

                    if (savedStatus && $statusSelect.find(`option[value='${savedStatus}']`).length > 0) {
                        $statusSelect.val(savedStatus);
                        $statusSelect.removeAttr('data-selected');
                    }
                }
            });

            // --- 2. 處理 [+] / [-] 按鈕 ---
            $(document).on('click', '.btn-add-ati', function() {
                const $container = $(this).closest('.ati-container');
                const $set2 = $container.find('.ati-set-2');
                const $set3 = $container.find('.ati-set-3');

                if ($set2.is(':hidden')) {
                    $set2.show();
                } else if ($set3.is(':hidden')) {
                    $set3.show();
                }
            });

            $(document).on('click', '.btn-remove-ati', function() {
                const $container = $(this).closest('.ati-container');
                const $set2 = $container.find('.ati-set-2');
                const $set3 = $container.find('.ati-set-3');

                if ($set3.is(':visible')) {
                    resetAndHide($set3);
                } else if ($set2.is(':visible')) {
                    resetAndHide($set2);
                }
            });

            function resetAndHide($block) {
                $block.hide();
                $block.find('select').val('').trigger('change');
                $block.find('input[type="checkbox"]').prop('checked', false);
            }

            // --- 3. 最後才執行初始化 (Initialization) ---
            // 這裡觸發 change 時，上方的監聽器已經準備好了，所以 2nd 和 Status 會被正確更新
            $('.ati-cate1').each(function() {
                const $select1 = $(this);
                const selectedValue = $select1.attr('data-selected'); // 使用 attr 確保讀取原始 HTML 值

                // 填入第一層選項
                $.each(ATI_CONFIG.categories, function(key, value) {
                    $select1.append($('<option>', {
                        value: key,
                        text: key
                    }));
                });

                // 如果有預設值，選中它並觸發 change
                if (selectedValue) {
                    $select1.val(selectedValue).trigger('change');
                    $select1.removeAttr('data-selected'); // 清除標記
                }
            });

        });
    </script>
</body>

</html>