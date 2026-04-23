<?php
error_reporting(E_ERROR);

require_once('test_db/disb_db.php');
session_start();

// --- 處理返回網址 (Return URL) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['return_url'])) {
    $return_url = $_POST['return_url'];
} else {
    $return_url = $_GET['return_url'] ?? '';
}
// ----------------------------------------------

// 1. 初始化變數與判斷模式
$deb_num = isset($_GET['deb_num']) ? $_GET['deb_num'] : '';
$is_late_disb = ($deb_num != '');
$prefill_case_num = '';

// 如果是補登模式，先去資料庫抓這張單號對應的 case_num
if ($is_late_disb) {
    $bill_info = getBillInfo($deb_num);
    if ($bill_info) {
        $prefill_case_num = $bill_info['case_num'];
    }
}

// 2. 處理表單提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 接收 POST 的 deb_num
    $post_deb_num = isset($_POST['deb_num']) ? $_POST['deb_num'] : '';

    $result_word = insertDisb([
        'mode' => $_POST['mode'] ?? 'new_dis',
        'disb_code' => $_POST['disb_code'],
        'case_num' => $_POST['case_num'],
        'counsel_invoice' => $_POST['counsel_invoice'] ?? '',
        'paydate' => $_POST['paydate'] ?? '',
        'initials' => $_POST['initials'],
        'show_as_legal_service_flag' => $_POST['show_as_legal_service_flag'] ?? -1,
        'currency2' => $_POST['currency2'] ?? '',
        'date' => $_POST['date'],
        'ntd_amount' => $_POST['ntd_amount'],
        'foreign_amount' => $_POST['foreign_amount'] ?? 0,
        'nocharge_flag' => $_POST['nocharge_flag'] ?? -1,
        'disbs_id_relation' => $_POST['disbs_id_relation'] ?? '',
        'notes' => $_POST['notes'] ?? '',
        'currency' => $_POST['currency'] ?? '',
        'foreign_amount2' => $_POST['foreign_amount2'] ?? 0,
        'counsel_area' => $_POST['counsel_area'] ?? '',
        'counsel_name' => $_POST['counsel_name'] ?? '',
        'invoice_date' => $_POST['invoice_date'] ?? '',
        'bpm_date' => $_POST['bpm_date'] ?? '',
        'narrative' => $_POST['narrative'] ?? '',
        'x_rate' => $_POST['x_rate'] ?? 0,
        'deb_num' => $post_deb_num,
        'rate' => $_POST['rate'] ?? 0,
        'num_of_chars' => $_POST['num_of_chars'] ?? 0,
    ]);

    // 3. 根據模式決定跳轉位置
    $redirect_url = ($post_deb_num != '') ? $return_url : 'disb_insert.php';
    $is_success = strpos($result_word, 'successfully') !== false;

    // AJAX 請求：回傳 JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $is_success,
        'message' => $result_word,
        'redirect' => $is_success ? $redirect_url : null,
    ]);
    exit();
}

$result = beginEnter();
?>
<!DOCTYPE html>
<html lang="zh-TW">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <title>New Disbursements</title>

    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/winkler.css">
    <link rel="stylesheet" href="css/winkler-rwd.css">
    <link rel="stylesheet" href="css/left-search.css">
</head>

<body data-spy="scroll" data-target=".amanda-nav">
    <?php
    require_once("menu.php");
    ?>

    <div class="winkler-container-nosearch">
        <div class="all-heading">
            <h3>
                New Disbursements
                <?php if ($is_late_disb): ?>
                    for draft debit note number <?php echo htmlspecialchars($deb_num); ?>
                <?php endif; ?>
            </h3>
        </div>

        <form name="disbs" accept-charset="utf8" method="POST" action="" onsubmit="handleSubmit(event)">
            <input type="hidden" name="in_manager" value="">
            <input type="hidden" name="in_case_num_dir" value="">
            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">

            <?php if ($is_late_disb): ?>
                <input type="hidden" name="deb_num" value="<?php echo htmlspecialchars($deb_num); ?>">
                <input type="hidden" name="billed_flag" value="0">
            <?php endif; ?>

            <div class="form-horizontal">
                <div class="form-group">
                    <label class="col-md-2 control-label">Case Number</label>
                    <div class="col-md-4">
                        <div class="input-group">
                            <?php if ($is_late_disb): ?>
                                <input type="text" class="form-control" name="case_num" value="<?php echo $prefill_case_num; ?>" readonly>
                            <?php else: ?>
                                <input type="text" class="form-control" name="case_num" value="">
                            <?php endif; ?>
                            <input type="hidden" name="mode" value="new_dis">
                        </div>
                    </div>

                    <label class="col-md-2 control-label">Date (請填入發生日期)</label>
                    <div class="col-md-4">
                        <input type="date" class="form-control" name="date" value="<?php echo date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-md-2 control-label">Account Code</label>
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" name="disb_code" class="form-control" value="" id="account_code">
                        </div>
                    </div>
                    <label class="col-md-2 control-label">NTD$ Amount</label>
                    <div class="col-md-4">
                        <input type="text" name="ntd_amount" class="form-control" size="10" id="ntd" value=""
                            oninput="document.forms['disbs'].elements['ntd_amount'].value = this.value">
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes" class="col-md-2 control-label">Notes (Client won't see this)</label>
                    <div class="col-md-10">
                        <textarea class="form-control" id="notes" name="notes" rows="3" cols="50"></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-md-2 control-label">Currency</label>
                    <div class="col-md-10">
                        <div class="form-inline">
                            <select name="currency" class="form-control">
                                <option value="" selected></option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="AUD">AUD</option>
                                <option value="HKD">HKD</option>
                                <option value="SGD">SGD</option>
                                <option value="JPY">JPY</option>
                                <option value="NZD">NZD</option>
                                <option value="GBP">GBP</option>
                                <option value="CNY">CNY</option>
                                <option value="CAD">CAD</option>
                            </select>
                            <input name="foreign_amount" class="form-control" size="10" value="0" onChange="account_money()">
                            Rate
                            <input name="x_rate" class="form-control" size="10" value="" onChange="account_money()">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-md-2 control-label">Currency2</label>
                    <div class="col-md-10">
                        <div class="form-inline">
                            <select name="currency2" class="form-control" onChange="change_currency2()">
                                <option value="" selected></option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                            </select>
                            <input name="foreign_amount2" class="form-control" size="10" value="0" onChange="account_money2()">
                            <span id="rate2_data"></span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-md-2 control-label">Counsel Name</label>
                    <div class="col-md-4">
                        <input type="text" name="counsel_name" class="form-control" value="">
                    </div>
                    <label class="col-md-2 control-label">Counsel City</label>
                    <div class="col-md-4">
                        <input type="text" name="counsel_area" class="form-control" value="">
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-md-2 control-label">Counsel Invoice Date</label>
                    <div class="col-md-4">
                        <input type="date" name="invoice_date" class="form-control" value="">
                    </div>
                    <label class="col-md-2 control-label">Counsel Invoice</label>
                    <div class="col-md-4">
                        <input type="text" name="counsel_invoice" class="form-control" value="">
                    </div>
                </div>

                <div class="form-group">
                    <label for="nocharge_flag" class="col-md-2 control-label">No charge</label>
                    <div class="col-md-4">
                        <div class="checkbox">
                            <input type="checkbox" id="nocharge_flag" name="nocharge_flag" style="margin-left:5px; transform: scale(1.5);" value="1" onClick="return KeepCount()">
                        </div>
                    </div>
                    <label for="show_as_legal_service_flag" class="col-md-2 control-label">Bill Show as Legal Service</label>
                    <div class="col-md-4">
                        <div class="checkbox">
                            <input type="checkbox" id="show_as_legal_service_flag" style="margin-left:5px; transform: scale(1.5);" name="show_as_legal_service_flag" value="1" onClick="return KeepCount()">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-md-2 control-label">Payment Date</label>
                    <div class="col-md-4">
                        <input type="date" name="paydate" class="form-control" value="">
                    </div>

                    <?php
                    $current_user = isset($_SESSION['initial']) ? $_SESSION['initial'] : '';
                    ?>
                    <label class="col-md-2 control-label">Initials:</label>
                    <div class="col-md-4">
                        <?php if (!empty($current_user)): ?>
                            <input type="text" class="form-control" name="initials" value="<?php echo htmlspecialchars($current_user); ?>" readonly>
                        <?php else: ?>
                            <select class="form-control form-width-98" name="initials">
                                <option></option>
                                <?php foreach ($result['people'] as $person) { ?>
                                    <option><?php echo $person['initials'] ?></option>
                                <?php } ?>
                            </select>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-md-2 control-label">ID Relation:</label>
                    <div class="col-md-4">
                        <input type="text" name="disbs_id_relation" class="form-control" value="">
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-md-2 control-label">BPM Date</label>
                    <div class="col-md-10">
                        <div class="form-inline">
                            <input type="date" class="form-control" name="bpm_date" value="">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="narrative" class="col-md-2 control-label">Narrative</label>
                    <div class="col-md-10">
                        <textarea name="narrative" class="form-control" id="narrative" rows="3"></textarea>
                    </div>
                </div>

                <div class="c-form-bot">
                    <input type="submit" name="disb_submit" value="Submit Disbursement" class="btn btn-primary">
                </div>
            </div>
        </form>
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
        });

        // 統一處理 Modal 關閉事件
        $(document).on('hidden.bs.modal', function(e) {
            $(e.target).removeData('bs.modal');
        });
    </script>

    <script>
        var isSubmitting = false;

        function account_money() {
            var rate = document.disbs.x_rate.value;
            var foreign_amount = document.disbs.foreign_amount.value;
            if (document.disbs.currency.value != '') {
                document.disbs.ntd_amount.value = Math.round(rate * foreign_amount);
                document.getElementById('ntd').readOnly = true;
            } else {
                document.getElementById('ntd').readOnly = false;
            }
        }

        function checkdata() {
            var start_day = document.disbs.date.value;
            var now = new Date();
            var mon = 1 + now.getMonth();
            var year = now.getFullYear();
            var today = year + '/' + mon + '/' + now.getDate();

            if (start_day.length < 8) {
                alert("請輸入正確日期格式，如：2011/07/02 or 2011-07-02 or 20110702");
                return false;
            }
            if (start_day.match(/-/g)) {
                start_day = start_day.replace(/-/g, "\/");
            }
            if (!start_day.match(/\//g)) {
                start_day = start_day.substring(0, 4) + '/' + start_day.substring(4, 6) + '/' + start_day.substring(6, 8);
            }

            var fd1 = new Date(start_day);
            var fd2 = new Date(today);
            var gap = fd2.getTime() - fd1.getTime();

            if (Math.floor(gap / (1000 * 60 * 60 * 24)) > 180 || Math.floor(gap / (1000 * 60 * 60 * 24)) < -30) {
                alert('超出可輸入的時間範圍');
                return false;
            }

            if (document.disbs.ntd_amount.value == '' || document.disbs.ntd_amount.value != parseInt(document.disbs.ntd_amount.value)) {
                alert('NTD$ amount must integer');
                return false;
            }

            if (!document.disbs.disb_code.value.startsWith('2')) {
                alert('please begin all code with 2');
                return false;
            }

            if (document.disbs.initials.value == '') {
                alert('initials must has content');
                return false;
            }

            return true;
        }

        function handleSubmit(event) {
            event.preventDefault();

            if (isSubmitting) return;
            if (!checkdata()) return;

            isSubmitting = true;
            var submitBtn = document.querySelector('input[name="disb_submit"]');
            if (submitBtn) {
                submitBtn.value = 'Submitting...';
                submitBtn.style.opacity = "0.6";
            }

            var formData = new FormData(document.disbs);

            fetch('', {
                    method: 'POST',
                    body: formData,
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    alert(data.message);
                    if (data.success) {
                        location.href = data.redirect;
                    } else {
                        // 失敗：留在原頁面，解鎖按鈕讓使用者可以再次提交
                        isSubmitting = false;
                        if (submitBtn) {
                            submitBtn.value = 'Submit Disbursement';
                            submitBtn.style.opacity = "1";
                        }
                    }
                })
                .catch(function() {
                    alert('網路錯誤，請重試');
                    isSubmitting = false;
                    if (submitBtn) {
                        submitBtn.value = 'Submit Disbursement';
                        submitBtn.style.opacity = "1";
                    }
                });
        }

        function KeepCount() {
            var NewCount = 0;
            if (document.disbs.nocharge_flag.checked) {
                NewCount = NewCount + 1;
            }
            if (document.disbs.show_as_legal_service_flag.checked) {
                NewCount = NewCount + 1;
            }
            if (NewCount == 2) {
                alert('Only choose one');
                // document.disbs; // 移除這行無效程式碼
                return false;
            }
        }

    </script>

    <script>
        function account_money2() {
            var usd_rate = <?php echo (float)($result['xrate_usd'] ?? 0) ?>;
            var eur_rate = <?php echo (float)($result['xrate_eur'] ?? 0) ?>;
            var foreign_amount2 = document.disbs.foreign_amount2.value;

            if (document.disbs.currency2.value == 'USD') {
                document.disbs.ntd_amount.value = Math.round(usd_rate * foreign_amount2);
                document.getElementById('ntd').readOnly = true;
            } else if (document.disbs.currency2.value == 'EUR') {
                document.disbs.ntd_amount.value = Math.round(eur_rate * foreign_amount2);
                document.getElementById('ntd').readOnly = true;
            } else {
                document.getElementById('ntd').readOnly = false;
            }
        }

        function change_currency2() {
            var usd_rate = <?php echo (float)($result['xrate_usd'] ?? 0) ?>;
            var eur_rate = <?php echo (float)($result['xrate_eur'] ?? 0) ?>;
            var textc = '';
            var displaySpan = document.getElementById("rate2_data");

            if (document.disbs.currency2.value == 'USD') {
                textc = 'Rate: ' + usd_rate;
                displaySpan.textContent = textc;
            } else if (document.disbs.currency2.value == 'EUR') {
                textc = 'Rate: ' + eur_rate;
                displaySpan.textContent = textc;
            } else {
                displaySpan.textContent = "";
            }
        }
    </script>
</body>

</html>