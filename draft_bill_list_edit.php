<?php
require_once('test_db/draft_bill_list_edit_db.php');

$deb_num = $_GET['deb_num'] ?? '';
$return_url = $_GET['return_url'] ?? '';

if (!$deb_num) {
    die("Debit Number is required.");
}

try {
    $data = getBillData($deb_num);
    $bill = $data['bill'];
    $case = $data['case'];
    $narrative = $data['narrative'];
    $transactions = $data['transactions'];
    $ledes_codes = $data['ledes_codes'];
    $ledes_activity_codes = $data['ledes_activity_codes'];
    $fee_earner_summary = $data['fee_earner_summary'];
} catch (Exception $e) {
    die($e->getMessage());
}

// Helper for currency
$currency = 'NTD';
if ($case['billing_currency'] == 'English (USD)') {
    $currency = 'USD';
} elseif ($case['billing_currency'] == 'English (EUR)') {
    $currency = 'EUR';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>Edit Bill <?php echo $bill['case_num']; ?></title>

    <!-- Bootstrap -->
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/winkler.css">
    <link rel="stylesheet" href="css/winkler-rwd.css">
    <link rel="stylesheet" href="css/left-search.css">
</head>

<style>
    /* Hide Unchecked 按鈕 */
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
</style>

<body data-spy="scroll" data-target=".amanda-nav">
    <?php
    require_once("menu.php");
    ?>

    <!-- 側邊搜尋內容 -->
    <div id="sidebar-wrapper">
        <div class="sidebar-nav">

            <!-- 搜尋條件內容 -->
            <div class="search-con">
                <div class="heading">
                    <h2>Edit Bill</h2>
                </div>

                <div class="s-form-bot">
                    <a href="http://billing-dev/cgi-bin/case.pl?action=Edit%20Case&case_num=<?php echo $bill['case_num']; ?>&deb_num=<?php echo $deb_num; ?>">
                        <button type="button">
                            Update Case
                        </button>
                    </a>
                </div>

                <div class="form-group">
                    <label class="col-half">Set LEDES code</label>
                    <select name="set_all_ledes_code" style="width: 120px;">
                        <?php foreach ($ledes_codes as $code => $content): ?>
                            <option value="<?php echo $code; ?>">
                                <?php echo $code . ' ' . $content; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="s-form-bot">
                    <button type="button" id="btn-update" onclick="submitUpdate()">
                        Update
                    </button>
                </div>

                <div style="text-align: center;">
                    <button type="button" id="btn-back" onclick="goBackAndRefresh()" style="width: 50%; background-color: #6c757d; color: white;">
                        <i class="glyphicon glyphicon-arrow-left"></i> Back
                    </button>
                </div>
            </div>

            <!-- 搜尋條件內容結束 -->

            <!-- 頁籤 -->

            <div class="search-btn">
                <div class="sidebar-colse">

                    <!-- search.js控製申縮的id在這 -->
                    <a id="menu-close" href="#" class="btn btn-default btn-lg btn-winkier toggle">
                        <i class="glyphicon glyphicon-search">Edit Bill</i>
                    </a>

                </div>
            </div>

            <!-- 頁籤結束 -->

            <div class="clear"></div>
        </div>
    </div>
    <!-- 側邊搜尋內容結束-->

    <div id="winkler-container">
        <div class="row">
            <!-- Fee Earner Summary -->
            <div class="col-md-7">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered">
                        <thead>
                            <tr class="bg-primary">
                                <th class="text-center">Fee Earner</th>
                                <th class="text-center">Rate</th>
                                <th class="text-center">Recorded Amount</th>
                                <th class="text-center">Recorded Hours</th>
                                <th class="text-center">Internal Hours</th>
                                <th class="text-center">Share</th>
                                <th class="text-center">Bonus 4%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fee_earner_summary as $fe): ?>
                                <tr>
                                    <td><?php echo $fe['initials']; ?></td>
                                    <td class="text-center"><?php echo $fe['rate']; ?></td>
                                    <td class="text-right"><?php echo $fe['in_total']; ?></td>
                                    <td class="text-right"><?php echo $fe['bill_hours']; ?></td>
                                    <td class="text-right"><?php echo $fe['internal_hours']; ?></td>
                                    <td class="text-center"><?php echo $fe['share']; ?></td>
                                    <td class="text-right"><?php echo $fe['bonus']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- End Fee Earner Summary -->

            <!-- Bill Summary -->
            <div class="col-md-5">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered">
                        <thead>
                            <tr class="bg-success">
                                <?php if ($bill['discount'] > 0): ?>
                                    <th class="text-center">Undiscounted Legal Services</th>
                                <?php endif; ?>
                                <th class="text-center">Legal Services</th>
                                <th class="text-center"><a href="#" onclick="openDisbursementsModal(); return false;">Disbursements</a></th>
                                <th class="text-center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($bill['discount'] > 0): ?>
                                <td class="text-right">
                                    <?php
                                    if ($currency == 'NTD') {
                                        echo "NTD " . number_format($bill['undiscounted_legal_services']);
                                    } else {
                                        echo $bill['currency2'] . " " . number_format($bill['foreign_undiscount_legal2'], 2);
                                    }
                                    ?>
                                </td>
                            <?php endif; ?>
                            <td class="text-right">
                                <?php
                                if ($currency == 'NTD') {
                                    echo "NTD " . number_format($bill['legal_services']);
                                } else {
                                    echo $bill['currency2'] . " " . number_format($bill['foreign_legal2'], 2);
                                }
                                ?>
                            </td>
                            <td class="text-right">
                                <?php
                                if ($currency == 'NTD') {
                                    echo "NTD " . number_format($bill['disbs']);
                                } else {
                                    echo $bill['currency2'] . " " . number_format($bill['foreign_disbs2'], 2);
                                }
                                ?>
                            </td>
                            <td class="text-right">
                                <?php
                                if ($currency == 'NTD') {
                                    echo "NTD " . number_format($bill['total']);
                                } else {
                                    echo $bill['currency2'] . " " . number_format($bill['foreign_total2'], 2);
                                }
                                ?>
                            </td>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- End Bill Summary -->
        </div>

        <!-- 標題 -->
        <div class="block-hv100">
            <div class="all-heading">
                <h3>
                    <?php
                    echo 'Draft Bill ' . $bill['case_num'] . ' - ' . $deb_num;
                    ?>

                    <div class="pull-right">
                        Show Billed Only
                        <label class="switch">
                            <input type="checkbox" id="hide_unchecked">
                            <span class="slider"></span>
                        </label>
                    </div>
                </h3>
            </div>

            <div class="table-responsive">
                <form id="bill-form" method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="deb_num" value="<?php echo $deb_num; ?>">
                    <table class="table hv1-table table-hover  ">
                        <thead>
                            <tr>
                                <th class="text-center" width="6%">id</th>
                                <th class="text-center">Emp</th>
                                <th class="text-center">Date</th>
                                <th class="text-center">Time</th>
                                <th class="text-center" width="8%">Internal</th>
                                <th class="text-center" width="8%">Billing</th>
                                <th class="text-center" width="5%">No Charge</th>
                                <th class="text-center" width="5%">Show</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $output = "";
                            $count = 0;

                            foreach ($transactions as $tr) {
                                $count++;
                                $rowId = "row_" . $count;
                                $showFlag = ($tr['show_flag'] == 1) ? "checked" : "";
                                $noChargeFlag = ($tr['nocharge_flag'] != -1) ? "checked" : "";
                                $hideClass = ($showFlag == 'checked') ? '' : 'can-hide';

                                // 預先處理 Ledes Codes 的下拉選單
                                $ledes_options = "";
                                foreach ($ledes_codes as $code => $content) {
                                    $selected = ($code == $tr['ledes_code']) ? "selected" : "";
                                    $ledes_options .= "<option value='{$code}' {$selected}>{$code} {$content}</option>";
                                }

                                // 預先處理 Activity Codes 的下拉選單
                                $activity_options = "";
                                foreach ($ledes_activity_codes as $code => $content) {
                                    $selected = ($code == $tr['ledes_activity_code']) ? "selected" : "";
                                    $activity_options .= "<option value='{$code}' {$selected}>{$code} {$content}</option>";
                                }

                                // 將內容累加到 $output 字串
                                $output .= "
                                <tr id='{$rowId}' class='{$hideClass}'>
                                    <td rowspan='2'>
                                        <input type='text' name='id_{$tr['id']}' value='{$tr['id']}' readonly class='form-control readonly'>
                                        <input type='hidden' name='initials_{$tr['id']}' value='{$tr['initials']}'>
                                        <br>
                                        {$tr['case_num']}
                                        <input type='hidden' name='show_rate_{$tr['id']}' value='{$tr['show_rate']}'>
                                    </td>
                                    <td>
                                        <input type='text' name='show_initials_{$tr['id']}' value='{$tr['show_initials']}' readonly class='form-control readonly' style='width: 60px; display: inline;'>
                                        {$tr['rate']}
                                    </td>
                                    <td>
                                        <input type='text' name='date_{$tr['id']}' value='{$tr['date']}' readonly class='form-control readonly'>
                                    </td>
                                    <td>
                                        <input type='text' value='{$tr['bill_time']}' readonly class='form-control readonly' style='width: 60px;'>
                                    </td>
                                    <td>
                                        <input type='text' name='internal_time_{$tr['id']}' value='{$tr['internal_time']}' class='form-control'>
                                    </td>
                                    <td>
                                        <input type='text' name='charge_{$tr['id']}' value='{$tr['charge']}' class='form-control'>
                                    </td>
                                    <td class='text-center'>
                                        <input type='checkbox' id='nocharge_flag_{$tr['id']}' name='nocharge_flag_{$tr['id']}' value='1' {$noChargeFlag} onclick='check_show_nocharge(this, {$tr['id']})'>
                                    </td>
                                    <td class='text-center'>
                                        <input type='checkbox' id='show_flag_{$tr['id']}' name='show_flag_{$tr['id']}' value='1' {$showFlag} class='show-checkbox' onclick='check_show_nocharge(this, {$tr['id']})'>
                                    </td>
                                </tr>
                                <tr class='tr-sub {$hideClass}'>
                                    <td colspan='3'>
                                        <textarea name='nar_2_{$tr['id']}' rows='3' class='form-control narrative-area'>{$tr['nar_2']}</textarea>
                                    </td>
                                    <td colspan='4' class='bg-warning'>
                                        <label>Task code</label>
                                        <input type='hidden' name='id_num_a[]' value='{$tr['id']}'>
                                        <select name='ledes_code_{$tr['id']}' class='form-control ledes-select'>
                                            {$ledes_options}
                                        </select>

                                        <label style='margin-top: 5px;'>Activity code</label>
                                        <select name='ledes_activity_code_{$tr['id']}' class='form-control'>
                                            {$activity_options}
                                        </select>
                                    </td>
                                </tr>";
                            }

                            // 最後一次 echo 輸出全部內容
                            echo "{$output}";
                            ?>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
    </div>

    <!-- Disbursements Modal -->
    <div class="modal fade" id="disbModal" tabindex="-1" role="dialog" aria-labelledby="disbModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <!-- Modal 內容由 AJAX 載入 -->
                <div class="modal-body text-center">
                    <p>載入中...</p>
                </div>
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
        // Hide Unchecked 按鈕控制
        $(document).ready(function() {
            $('#hide_unchecked').on('change', function() {
                if ($(this).is(':checked')) {
                    // 隱藏所有帶有 can-hide class 的列
                    $('.can-hide').hide();
                } else {
                    // 顯示所有列
                    $('.can-hide').show();
                }
            });
        });
    </script>

    <script>
        function check_show_nocharge(currentCheckbox, id) {
            var noCharge = document.getElementById('nocharge_flag_' + id);
            var show = document.getElementById('show_flag_' + id);

            if (!noCharge.checked && !show.checked) {
                alert('不能同時取消 No Charge 和 Show');
                currentCheckbox.checked = true;
            }
        }
    </script>

    <script>
        // 連動 LEDES Code 功能
        $(document).ready(function() {
            // 監聽左側 sidebar 的 "Set LEDES code" 下拉選單
            $('select[name="set_all_ledes_code"]').on('change', function() {
                // 取得使用者選取的值
                var selectedValue = $(this).val();

                // 找到表格中所有的 Task code 下拉選單 (class="ledes-select") 並設定為相同的值
                $('.ledes-select').val(selectedValue);
            });
        });
    </script>

    <script>
        // Update 按鈕提交處理
        function submitUpdate() {
            var form = document.getElementById('bill-form');
            var formData = new FormData(form);

            // 顯示載入中
            var btn = document.getElementById('btn-update');
            var originalText = btn.innerText;
            btn.innerText = '更新中...';
            btn.disabled = true;

            fetch('test_db/draft_bill_list_edit_db.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('更新成功！');
                        // 重新載入頁面以顯示更新後的資料
                        location.reload();
                    } else {
                        alert('更新失敗: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('更新發生錯誤: ' + error.message);
                })
                .finally(() => {
                    btn.innerText = originalText;
                    btn.disabled = false;
                });
        }
    </script>

    <script>
        // 開啟 Disbursements Modal
        function openDisbursementsModal() {
            var case_num = '<?php echo addslashes($bill["case_num"]); ?>';
            var deb_num = '<?php echo addslashes($deb_num); ?>';

            // 載入 Modal 內容
            $('#disbModal .modal-content').html('<div class="modal-body text-center"><p>載入中...</p></div>');
            $('#disbModal').modal('show');

            $.ajax({
                url: 'draft_bill_list_edit_disbs.php',
                method: 'GET',
                data: {
                    case_num: case_num,
                    deb_num: deb_num
                },
                success: function(response) {
                    $('#disbModal .modal-content').html(response);
                },
                error: function(xhr, status, error) {
                    $('#disbModal .modal-content').html('<div class="modal-body"><p class="text-danger">載入失敗: ' + error + '</p></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div>');
                }
            });
        }

        // Modal 關閉後重新載入頁面以更新金額
        $('#disbModal').on('hidden.bs.modal', function() {
            location.reload();
        });
    </script>
    <script>
        function goBackAndRefresh() {
            var returnUrl = <?php echo json_encode($return_url); ?>;
            if (returnUrl) {
                window.location.href = returnUrl;
            } else {
                window.history.back();
            }
        }
    </script>
</body>

</html>