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

<style>
    /* Show ATI 按鈕 */
    .switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
        vertical-align: middle;
        margin-left: 10px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        -webkit-transition: .4s;
        transition: .4s;
        border-radius: 34px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        -webkit-transition: .4s;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked+.slider {
        background-color: #2196F3;
    }

    input:checked+.slider:before {
        -webkit-transform: translateX(26px);
        -ms-transform: translateX(26px);
        transform: translateX(26px);
    }

    /* 修正 icon 會跑出標題列的 bug */
    .hv1-table thead,
    .hv1-table thead th {
        z-index: 999 !important;
    }
</style>

<body data-spy="scroll" data-target=".amanda-nav">
    <?php
    require_once("menu.php");
    ?>

    <!-- 側邊搜尋內容 -->
    <?php
    require_once("draft_bill_list_search.php");
    ?>
    <!-- 側邊搜尋內容結束-->

    <div id="winkler-container">
        <!-- 標題 -->
        <div class="block-hv100">
            <div class="all-heading">
                <h3>
                    <?php
                    require_once('test_db/draft_bill_list_db.php');
                    require_once("test_db/syslog_db.php");

                    // 初始化預設值，避免下方 HTML 報錯
                    $result_data = [];
                    $totals = [];
                    $can_reset = false;

                    // 設定預設排序參數
                    $sort_key = isset($_GET['sort_key']) ? $_GET['sort_key'] : 'case_num';
                    $sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'ASC';

                    // 初始化查詢參數
                    $case_number = isset($_GET['case_number']) ? $_GET['case_number'] : '';
                    $match_or_like = isset($_GET['match_or_like']) ? $_GET['match_or_like'] : 'like';
                    $case_manager = isset($_GET['case_manager']) ? $_GET['case_manager'] : '';

                    // 檢查權限
                    $initial = $_SESSION['initial'] ?? '';
                    $has_permission = checkPrivacy($initial, 'Draft_bill_list_apply_sent');
                    $today = date('Y-m-d');
                    $return_url = urlencode('draft_bill_list.php?' . $_SERVER['QUERY_STRING']);

                    if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['case_number'])) {
                        // 顯示查詢條件
                        echo "Case Number: $case_number | Case Manager: $case_manager";

                        try {
                            // 呼叫函數取得資料
                            $api_result = getData($case_number, $match_or_like, $case_manager, $sort_key, $sort_order);
                            $result_data = $api_result['rows'];
                            $totals = $api_result['totals'];

                            // print_r($result_data);

                            // 取得權限旗標
                            if (isset($api_result['can_reset'])) {
                                $can_reset = $api_result['can_reset'];
                            }
                        } catch (Exception $e) {
                            $errorMessage = $e->getMessage();
                            echo "<script>alert(" . json_encode($errorMessage) . ");</script>";
                        }
                    } else {
                        echo "Default";
                    }
                    ?>

                    <div class="pull-right">
                        Show ATI
                        <label class="switch">
                            <input type="checkbox" id="show_ati" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                </h3>
            </div>

            <div class="table-responsive">
                <table class="table hv1-table table-hover  ">
                    <thead>
                        <?php
                        function getSortLink($label, $columnKey, $currentSortKey, $currentSortOrder) {
                            // 複製目前的 GET 參數 (保留搜尋條件)
                            $params = $_GET;

                            // 設定新的排序鍵
                            $params['sort_key'] = $columnKey;

                            // 設定排序方向邏輯
                            if ($columnKey === $currentSortKey) {
                                // 如果點擊的是當前排序欄位，則反轉方向
                                $params['sort_order'] = ($currentSortOrder === 'ASC') ? 'DESC' : 'ASC';
                            } else {
                                $params['sort_order'] = 'ASC';
                            }

                            // 決定箭頭圖示
                            $icon = '';
                            if ($columnKey === $currentSortKey) {
                                $icon = ($currentSortOrder === 'ASC') ? '▲' : '▼';
                            }

                            // 建立 URL
                            $url = "?" . http_build_query($params);

                            // 回傳完整的 HTML 連結
                            return "<a href='{$url}' style='color:inherit; text-decoration:none;'>{$label}{$icon}</a>";
                        }
                        ?>
                        <tr>
                            <th class="text-center"><input type="checkbox" id="select_all"></th>
                            <th class="text-center">SN</th>
                            <th class="text-center"><?php echo getSortLink('Created', 'created', $sort_key, $sort_order); ?></th>
                            <th class="text-center"><?php echo getSortLink('Case Num', 'case_num', $sort_key, $sort_order); ?></th>
                            <th class="text-center"><?php echo getSortLink('Manager', 'manager', $sort_key, $sort_order); ?></th>
                            <th class="text-center"><?php echo getSortLink('Debit Note', 'deb_num', $sort_key, $sort_order); ?></th>
                            <th class="text-center"><?php echo getSortLink('Legal Services', 'legal_services', $sort_key, $sort_order); ?></th>
                            <th class="text-center"><?php echo getSortLink('Disbs', 'disbs', $sort_key, $sort_order); ?></th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Edit</th>
                            <th class="text-center">Billing Note</th>
                            <th class="text-center col-show-ati">OC Invoice</th>
                            <th class="text-center col-show-ati">ATI Category</th>
                            <th class="text-center">Retainer</th>
                            <th class="text-center">Reset</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        if (!empty($result_data)) {
                            $current_currency_flag = null; // 用來追蹤當前幣別區塊

                            foreach ($result_data as $key => $row) {
                                $sn = $key + 1;
                                $deb_num = $row['deb_num'];
                                $id = $row['id'];
                                $pppoc_status = $row['pppoc_status'];

                                if ($pppoc_status) {
                                    $pppoc_html = "<br><label style='color:red;'>PPP OC</label>";
                                } else {
                                    $pppoc_html = "";
                                }

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
                                                <td></td>
                                                <td class='text-left'>Total ({$current_currency_flag})</td>
                                                <td class='text-right'>{$total_count}</td>
                                                <td></td>
                                                <td class='text-right'>{$total_legal}</td>
                                                <td class='text-right'>{$total_disbs}</td>
                                                <td class='text-right'>{$total_total}</td>
                                                <td></td>
                                                <td></td>
                                                <td class='col-show-ati'></td>
                                                <td class='col-show-ati'></td>
                                                <td></td>
                                                <td></td>
                                            </tr>";
                                    }

                                    // 更新當前幣別
                                    $current_currency_flag = $currency_label;
                                    echo "<tr style='background-color: fff3cd;'><th colspan='15'><h4 class='text-center'>$currency_label</h4></th></tr>";
                                }

                                // --- 數值顯示邏輯 (根據幣別選擇顯示欄位) ---
                                $discount_html = "";
                                if (isset($row['discount']) && $row['discount'] > 0) {
                                    $discount_html = "<br><span style='font-size: 12px; color: red;'>(" . $row['discount'] . "% off)</span>";
                                }

                                if ($currency_label == 'USD' || $currency_label == 'EUR') {
                                    // show_as_legal
                                    if ($row['show_as_legal_foreign_flag']) {
                                        $raw_legal = $row['fmt_foreign_legal_original'] . " (" . $row['fmt_foreign_show_legal'] . ")";
                                        $display_disbs = $row['fmt_foreign_disbs_original'] . " (" . $row['fmt_foreign_show_disbs'] . ")";
                                    } else {
                                        $raw_legal = $row['fmt_foreign_show_legal'];
                                        $display_disbs = $row['fmt_foreign_show_disbs'];
                                    }

                                    // 紅字檢查
                                    if (floatval(str_replace(',', '', $raw_legal)) == 0) {
                                        $raw_legal = "<span style='color: red;'>{$raw_legal}</span>";
                                    }

                                    $display_legal = $raw_legal . '<br>' . $row['currency2'] . $discount_html;
                                    $display_disbs .= '<br>' . $row['currency2'];
                                    $display_total = $row['fmt_foreign_total'] . '<br>' . $row['currency2'];
                                } else {
                                    if ($row['show_as_legal_flag']) {
                                        $raw_legal = $row['fmt_legal_original'] . " (" . $row['fmt_show_legal'] . ")";
                                        $display_disbs = $row['fmt_disbs_original'] . " (" . $row['fmt_show_disbs'] . ")";
                                    } else {
                                        $raw_legal = $row['fmt_show_legal'];
                                        $display_disbs = $row['fmt_show_disbs'];
                                    }

                                    // 紅字檢查
                                    if (floatval(str_replace(',', '', $raw_legal)) == 0) {
                                        $raw_legal = "<span style='color: red;'>{$raw_legal}</span>";
                                    }

                                    $display_legal = $raw_legal . $discount_html;
                                    $display_total = $row['fmt_total'];
                                }

                                if (isset($row['display_oc_status']) && !empty($row['display_oc_status'])) {
                                    $display_disbs .= "<br><span style='color:red; font-weight:bold; font-size:12px;'>" . $row['display_oc_status'] . "</span>";
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

                                // 判斷預收款餘額 (根據帳單幣別判斷)
                                $retainer_currency = $row['retainer_currency'];
                                $retainer_amount = $retainer_currency == 'TWD' ? $row['retainer_ntd'] : $row['retainer_foreign'];

                                // 已抵扣金額
                                $deduct = $retainer_currency == 'TWD' ? $row['deduct_twd'] : $row['deduct_foreign'];

                                // 只有在有預收款時才顯示
                                if ($retainer_amount + $deduct > 0) {
                                    if ($deduct > 0) {
                                        $retainer_html .= "<input type='text' class='form-control retainer-input' 
                                                                value='" . $deduct . "' 
                                                                style='width:80px; display:inline-block;'
                                                                readonly
                                                            >";
                                    }

                                    // 按鈕
                                    $bill_total = ($currency_label === 'TWD') ? $row['total'] : $row['foreign_total2'];
                                    $retainer_html .= "<button type='button' class='btn btn-sm btn-info'
                                                            data-toggle='modal' data-target='#retainerModal' 
                                                            data-bills-case-num='{$row['case_num']}'
                                                            data-deb-num='{$deb_num}'
                                                            data-total='{$bill_total}'
                                                            data-retainer-case='{$row['retainer_case_num']}'
                                                            data-retainer-amount='{$retainer_amount}'
                                                            data-retainer-currency='{$retainer_currency}'
                                                            data-deduct='{$deduct}'>
                                                            <i class='glyphicon glyphicon-list'></i> Manage
                                                        </button>";
                                }

                                // --- 新增：Reset 按鈕顯示邏輯 ---
                                $reset_html = "";
                                if ($can_reset || true) {
                                    $reset_html = "<a href='test_db/draft_bill_list_reset_db.php?deb_num={$deb_num}' 
                                                        class='btn btn-sm btn-danger' 
                                                        onclick='return confirm(\"確定要 Reset 嗎？\");'>
                                                        <i class='glyphicon glyphicon-refresh'></i> Reset
                                                    </a>";
                                }

                                // --- 輸出表格行 ---
                                echo "<tr>
                                        <td class='text-center'><input type='checkbox' name='row_check_box[]' value='{$row['id']}'></td>
                                        <td class='text-center'>$sn</td>
                                        <td class='text-left'>{$row['draft_created']}</td>
                                        <td class='text-left'>{$row['case_num']}</td>
                                        <td class='text-left'>{$row['case_manager']}</td>
                                        <td class='text-left'><a href='http://slashlaw-new/draft_bill_list_bill_mod.php?id={$row['id']}&deb_num={$deb_num}&return_url={$return_url}'>{$deb_num}</a>{$pppoc_html}</td>
                                        <td class='text-right'>{$display_legal}</td>
                                        <td class='text-right'>{$display_disbs}</td>
                                        <td class='text-right'>{$display_total}</td>
                                        <td class='text-left'>
                                            <a href='http://slashlaw-new/draft_bill_list_edit.php?deb_num={$deb_num}&return_url={$return_url}' 
                                            class='btn btn-sm btn-primary' 
                                            style='margin-bottom: 5px;'>
                                            <i class='glyphicon glyphicon-edit'></i> Edit
                                            </a>
                                            <br>
                                            <a href='http://slashlaw-new/disb_insert.php?deb_num={$deb_num}&return_url={$return_url}' 
                                            class='btn btn-sm btn-success'>
                                            <i class='glyphicon glyphicon-plus'></i> Add Disbs
                                            </a>
                                        </td>
                                        <td class='text-left'>{$row['billing_note']}</td>
                                        <td class='text-left col-show-ati'>{$oc_invoice_html}</td>
                                        <td class='text-left col-show-ati'>{$ati_html}</td>
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
                                        <td></td>
                                        <td class='text-left'>Total ({$current_currency_flag})</td>
                                        <td class='text-right'>{$total_count}</td>
                                        <td></td>
                                        <td class='text-right'>{$total_legal}</td>
                                        <td class='text-right'>{$total_disbs}</td>
                                        <td class='text-right'>{$total_total}</td>
                                        <td></td>
                                        <td></td>
                                        <td class='col-show-ati'></td>
                                        <td class='col-show-ati'></td>
                                        <td></td>
                                        <td></td>
                                    </tr>";
                            }
                        }
                        ?>
                    </tbody>

                    <tfoot>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th class='text-left'>
                            小計 (TWD)
                        </th>
                        <?php
                        $all_count = isset($totals['all']['count']) ? $totals['all']['count'] : 0;
                        echo "<th class='text-right'>" . $all_count . "</th>";
                        ?>
                        <th></th>
                        <?php
                        $all_legal = isset($totals['all']['fmt_legal']) ? $totals['all']['fmt_legal'] : 0;
                        $all_disbs = isset($totals['all']['fmt_disbs']) ? $totals['all']['fmt_disbs'] : 0;
                        $all_total = isset($totals['all']['fmt_total']) ? $totals['all']['fmt_total'] : 0;

                        echo "<th class='text-right'>" . $all_legal . "</th>";
                        echo "<th class='text-right'>" . $all_disbs . "</th>";
                        echo "<th class='text-right'>" . $all_total . "</th>";
                        ?>
                        <th></th>
                        <th></th>
                        <th class='col-show-ati'></th>
                        <th class='col-show-ati'></th>
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
    </script>

    <script>
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

    <script>
        // Show ATI 按鈕控制
        $(document).ready(function() {
            $('#show_ati').on('change', function() {
                if ($(this).is(':checked')) {
                    // 如果 Switch 是開啟的，顯示欄位
                    $('.col-show-ati').show();
                } else {
                    // 如果 Switch 是關閉的，隱藏欄位
                    $('.col-show-ati').hide();
                }
            });
        });
    </script>

    <script>
        // 處理 Update 和 Apply 的送出邏輯
        $(document).ready(function() {
            // 將 PHP 變數傳遞給 JS
            const hasPermission = <?php echo json_encode((bool)$has_permission); ?>;
            const Today = "<?php echo $today; ?>";

            // 通用的送出函式
            function submitBillAction(actionName) {
                var $form = $('#action-form'); // 抓取 Sidebar 的表單
                var checkedRows = $('input[name="row_check_box[]"]:checked');

                // 1. 檢查是否有勾選任何項目
                if (checkedRows.length === 0) {
                    alert('請至少勾選一筆帳單 (Please select at least one bill).');
                    return;
                }

                // 2. 二次確認 (如果是 Apply 動作)
                if (actionName === 'apply') {
                    var sentDateVal = $('#sent_date').val();
                    if (!sentDateVal) {
                        alert('請選擇 Sent Date 才能執行 Apply。\n(Please select a Sent Date.)');
                        $('#sent_date').focus();
                        return;
                    }

                    if (!hasPermission && sentDateVal !== Today) {
                        alert('權限不足：您只能將 Sent Date 設定為今天。\n(Permission denied: You can only set the Sent Date to today.)');
                        $('#sent_date').focus();
                        return;
                    }

                    if (!confirm('確定要寄出帳單並押上日期嗎？\n(Are you sure you want to apply the sent date?)')) {
                        return;
                    }
                }

                // 3. 遍歷每一個被勾選的 checkbox
                checkedRows.each(function() {
                    var id = $(this).val(); // 取得帳單 ID

                    // A. 複製 ID (必須)
                    $form.append('<input type="hidden" name="row_check_box[]" value="' + id + '">');

                    // B. 複製 ATI 相關欄位 (Input, Select)
                    // 使用 data-id 屬性來精準定位該 ID 對應的輸入框
                    // 注意：我們只複製 "有值" 或 "被選中" 的資料，以減輕 payload

                    // 複製所有的 text input, number input, hidden input (例如 project_owner, class_count, retainer_amount)
                    // 這裡特別加上 retainer_amount 的選取
                    $('.ati-container[data-id="' + id + '"] input[type="text"], input[name="retainer_amount_' + id + '"]').each(function() {
                        $form.append('<input type="hidden" name="' + $(this).attr('name') + '" value="' + $(this).val() + '">');
                    });

                    // 複製 Select 下拉選單 (ATI Category, Status)
                    $('.ati-container[data-id="' + id + '"] select').each(function() {
                        $form.append('<input type="hidden" name="' + $(this).attr('name') + '" value="' + $(this).val() + '">');
                    });

                    // 複製 Checkbox (New Matter) - 只有被勾選的才送出
                    $('.ati-container[data-id="' + id + '"] input[type="checkbox"]:checked').each(function() {
                        $form.append('<input type="hidden" name="' + $(this).attr('name') + '" value="1">');
                    });

                    // C. 複製 Radio Button (OC Invoice Expected/Cancel)
                    var ocRadio = $('input[name="invoice_exp_status_' + id + '"]:checked');
                    if (ocRadio.length > 0) {
                        $form.append('<input type="hidden" name="' + ocRadio.attr('name') + '" value="' + ocRadio.val() + '">');
                    }
                });

                // 4. 加入動作類型參數 (模擬按鈕的 name="update" 或 name="apply")
                // 因為我們是用 JS submit，原本按鈕的 name 不會被傳送，所以要手動加
                $form.append('<input type="hidden" name="' + actionName + '" value="true">');

                // 5. 正式送出表單
                $form.submit();
            }

            // 綁定按鈕點擊事件
            $('#btn-update').on('click', function() {
                submitBillAction('update');
            });

            $('#btn-apply').on('click', function() {
                submitBillAction('apply');
            });
        });
    </script>

    <script>
        function exportExcel() {
            // 1. 取得所有被勾選的 checkbox
            var checkedRows = $('input[name="row_check_box[]"]:checked');

            // 2. 檢查是否有勾選
            if (checkedRows.length === 0) {
                alert('請至少勾選一筆資料以進行匯出。\n(Please select at least one row to export.)');
                return;
            }

            // 3. 收集 ID
            var ids = [];
            checkedRows.each(function() {
                ids.push($(this).val());
            });

            // 4. 將 ID 陣列轉為逗號分隔的字串 (例如: "101,102,105")
            var ids_string = ids.join(',');

            // 5. 透過 GET 請求跳轉下載 (在新分頁開啟，避免影響當前頁面)
            var url = 'test_db/draft_bill_list_excel.php?ids=' + encodeURIComponent(ids_string);

            window.open(url, '_blank');
        }
    </script>

    <!-- 申請開立收據 Modal -->
    <?php require_once("draft_bill_list_create_receipt.php"); ?>

    <!-- 預收款進階分配 Modal -->
    <?php require_once("draft_bill_list_retainer.php"); ?>
</body>

</html>