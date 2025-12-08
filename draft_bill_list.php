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

    <div id="winkler-container">
        <!-- 標題 -->
        <div class="block-hv100">
            <div class="all-heading">
                <h3>
                    <?php
                    require_once('test_db/draft_bill_list_db.php');

                    $result_data = [];
                    $totals = [];

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
                                        $total_count = $totals[strtolower($current_currency_flag)]['count'];
                                        if ($current_currency_flag !== 'TWD') {
                                            $total_legal = number_format($totals[strtolower($current_currency_flag)]['legal'], 2);
                                            $total_disbs = number_format($totals[strtolower($current_currency_flag)]['disbs'], 2);
                                            $total_total = number_format($totals[strtolower($current_currency_flag)]['total'], 2);
                                        } else {
                                            $total_legal = number_format($totals[strtolower($current_currency_flag)]['legal']);
                                            $total_disbs = number_format($totals[strtolower($current_currency_flag)]['disbs']);
                                            $total_total = number_format($totals[strtolower($current_currency_flag)]['total']);
                                        }
                                        echo "<tr style='background-color: d1e7dd;'>
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

                                // --- ATI 欄位顯示邏輯 ---
                                // 如果需要顯示 ATI (根據後端 show_ati 標記)
                                $ati_class = ($row['show_ati'] == 1) ? '' : 'posthidden';

                                echo "<tr>
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
                                        <td class='text-left'>{$row['show_oc']}</td>
                                        <td class='text-left'>{$row['show_ati']}</td>
                                        <td class='text-left'></td>
                                        <td class='text-left'></td>
                                    </tr>";
                            }

                            // --- 處理最後一個幣別的小計 ---
                            if ($current_currency_flag != null) {
                                $total_count = $totals[strtolower($current_currency_flag)]['count'];
                                if ($current_currency_flag !== 'TWD') {
                                    $total_legal = number_format($totals[strtolower($current_currency_flag)]['legal'], 2);
                                    $total_disbs = number_format($totals[strtolower($current_currency_flag)]['disbs'], 2);
                                    $total_total = number_format($totals[strtolower($current_currency_flag)]['total'], 2);
                                } else {
                                    $total_legal = number_format($totals[strtolower($current_currency_flag)]['legal']);
                                    $total_disbs = number_format($totals[strtolower($current_currency_flag)]['disbs']);
                                    $total_total = number_format($totals[strtolower($current_currency_flag)]['total']);
                                }

                                echo "<tr style='background-color: d1e7dd;'>
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
                        <!-- <th>
                        </th>
                        <th class='text-center'>
                            小計
                        </th>
                        <th>
                        </th>
                        <th>
                        </th>
                        <?php
                        echo "<th class='text-center'>" . number_format($total_service_amount) . "</th>";
                        echo "<th class='text-center'>" . number_format($total_disbs_amount) . "</th>";
                        echo "<th class='text-center'>" . number_format($total_amount) . "</th>";
                        ?>
                        <th>
                        </th>
                        <th>
                        </th>
                        <th>
                        </th>
                        <th>
                        </th>
                        <th>
                        </th>
                        <th>
                        </th> -->
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
</body>

</html>