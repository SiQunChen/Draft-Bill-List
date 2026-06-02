<?php
require_once('test_db/draft_bill_list_bill_mod_db.php');

$id = $_GET['id'] ?? $_POST['bill_id'] ?? '';
$deb_num = $_GET['deb_num'] ?? $_POST['deb_num'] ?? '';
$return_url = $_GET['return_url'] ?? '';

if (!$id || !$deb_num) {
    die("ID and Debit Number are required.");
}

try {
    $result = getBillData($id, $deb_num);
    $bill = $result['bill'];
    $disbursements = $result['disbursements'];
    $dis_ledes_code = $result['dis_ledes_code'];
} catch (Exception $e) {
    die($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>Modify Bill <?php echo $deb_num; ?></title>

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
    <div id="sidebar-wrapper">
        <div class="sidebar-nav">

            <!-- 搜尋條件內容 -->
            <div class="search-con">
                <div class="heading">
                    <h2>Modify Bill</h2>
                </div>

                <div class="form-group">
                    <label class="col-half">Set LEDES code</label>
                    <select name="set_ledes_code" id="set_ledes_code" style="width: 120px;" onchange="changeLedesCode(this)">
                        <option value=""></option>
                        <?php foreach ($dis_ledes_code as $code) : ?>
                            <option value="<?php echo htmlspecialchars($code['dis_ledes_code']); ?>">
                                <?php echo htmlspecialchars($code['dis_ledes_code'] . ' ' . $code['dis_ledes_content']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="s-form-bot">
                    <button type="button" id="btn-modify" onclick="submitModify()">
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
                        <i class="glyphicon glyphicon-search">Modify Bill</i>
                    </a>

                </div>
            </div>

            <!-- 頁籤結束 -->

            <div class="clear"></div>
        </div>
    </div>
    <!-- 側邊搜尋內容結束-->

    <div id="winkler-container">
        <form id="billForm" method="post" action="">
            <input type="hidden" name="action" value="update">

            <div style="display: flex; width: 100%; align-items: flex-start; justify-content: space-between;">
                <!-- Disbursements -->
                <table class="table table-hover table-bordered">
                    <thead>
                        <tr class="bg-primary">
                            <th class="text-center">Remove</th>
                            <th class="text-center">No charge</th>
                            <th class="text-center">Show as legal service</th>
                            <th class="text-center">LEDES Code</th>
                            <th class="text-center">代墊編號</th>
                            <th class="text-center">案號</th>
                            <th class="text-center">代墊項目</th>
                            <th class="text-center">台幣金額</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disbursements as $row) :
                            $disb_id = $row['id'];
                        ?>
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="remove_id[]" value="<?php echo $disb_id; ?>">
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" name="nocharge_id[]" value="<?php echo $disb_id; ?>" <?php echo $row['nocharge_flag'] == 1 ? 'checked' : ''; ?>>
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" name="show_ls_id[]" value="<?php echo $disb_id; ?>" <?php echo $row['show_as_legal_service_flag'] == 1 ? 'checked' : ''; ?>>
                                </td>
                                <td class="text-center">
                                    <select name="ledes_code_<?php echo $disb_id; ?>" class="form-control ledes-select">
                                        <option value=""></option>
                                        <?php foreach ($dis_ledes_code as $code) : ?>
                                            <option value="<?php echo htmlspecialchars($code['dis_ledes_code']); ?>"
                                                <?php echo $row['dis_ledes_code'] == $code['dis_ledes_code'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($code['dis_ledes_code'] . ' ' . $code['dis_ledes_content']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="text-center">
                                    <?php echo htmlspecialchars($disb_id); ?>
                                </td>
                                <td class="text-center"><?php echo htmlspecialchars($row['case_num']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($row['disb_name']); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($row['ntd_amount']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <!-- End Disbursements -->
            </div>

            <!-- 標題 -->
            <div class="block-hv100">
                <div class="all-heading">
                    <h3>
                        <?php
                        echo 'Modify Bill ' . htmlspecialchars($deb_num);
                        ?>
                    </h3>
                </div>

                <div class="table-responsive">
                    <div class="form-horizontal">
                        <div class="form-group">
                            <label class="col-md-2 control-label">Bill ID</label>
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="bill_id" value="<?php echo htmlspecialchars($id); ?>" readonly>
                            </div>

                            <label class="col-md-2 control-label">Draft Created</label>
                            <div class="col-md-4">
                                <input type="date" class="form-control" name="draft_created" value="<?php echo htmlspecialchars($bill['draft_created']); ?>" readonly>
                            </div>
                        </div>

                        <?php if ($bill['mid_flag']) : ?>
                            <div class="form-group">
                                <label class="col-md-2 control-label">MID Type</label>
                                <div class="col-md-4">
                                    <select name="mid_type" class="form-control">
                                        <option value="0" <?php echo $bill['mid_bill_type'] == 0 ? 'selected' : ''; ?>>Unassigned</option>
                                        <option value="1" <?php echo $bill['mid_bill_type'] == 1 ? 'selected' : ''; ?>>Registratibility Search</option>
                                        <option value="2" <?php echo $bill['mid_bill_type'] == 2 ? 'selected' : ''; ?>>Application</option>
                                        <option value="3" <?php echo $bill['mid_bill_type'] == 3 ? 'selected' : ''; ?>>Prosecution to Registration</option>
                                        <option value="4" <?php echo $bill['mid_bill_type'] == 4 ? 'selected' : ''; ?>>Conflicts/Infringements</option>
                                        <option value="5" <?php echo $bill['mid_bill_type'] == 5 ? 'selected' : ''; ?>>Oppositions/Cancellations</option>
                                        <option value="6" <?php echo $bill['mid_bill_type'] == 6 ? 'selected' : ''; ?>>Renewal</option>
                                        <option value="7" <?php echo $bill['mid_bill_type'] == 7 ? 'selected' : ''; ?>>Licensing/Assignment</option>
                                        <option value="8" <?php echo $bill['mid_bill_type'] == 8 ? 'selected' : ''; ?>>Administration</option>
                                        <option value="9" <?php echo $bill['mid_bill_type'] == 9 ? 'selected' : ''; ?>>Assignments</option>
                                        <option value="10" <?php echo $bill['mid_bill_type'] == 10 ? 'selected' : ''; ?>>Customs Issues</option>
                                        <option value="11" <?php echo $bill['mid_bill_type'] == 11 ? 'selected' : ''; ?>>Design Protection</option>
                                        <option value="12" <?php echo $bill['mid_bill_type'] == 12 ? 'selected' : ''; ?>>Domain Names</option>
                                        <option value="13" <?php echo $bill['mid_bill_type'] == 13 ? 'selected' : ''; ?>>External Licensing</option>
                                        <option value="14" <?php echo $bill['mid_bill_type'] == 14 ? 'selected' : ''; ?>>Litigation</option>
                                        <option value="15" <?php echo $bill['mid_bill_type'] == 15 ? 'selected' : ''; ?>>NPD Filing/Protection</option>
                                        <option value="16" <?php echo $bill['mid_bill_type'] == 16 ? 'selected' : ''; ?>>Protective Use</option>
                                        <option value="17" <?php echo $bill['mid_bill_type'] == 17 ? 'selected' : ''; ?>>Searching</option>
                                        <option value="18" <?php echo $bill['mid_bill_type'] == 18 ? 'selected' : ''; ?>>Watching Service</option>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php
                        $is_foreign = ($bill['billing_currency'] == 'English (USD)' || $bill['billing_currency'] == 'English (EUR)');
                        ?>

                        <div class="form-group">
                            <label class="col-md-2 control-label">Debit Note Number</label>
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="deb_num" value="<?php echo htmlspecialchars($bill['deb_num']); ?>" readonly>
                            </div>

                            <?php if ($is_foreign) : ?>
                                <label class="col-md-2 control-label">Foreign Currency</label>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="foreign_currency" value="<?php echo htmlspecialchars($bill['currency2']); ?>" readonly>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="col-md-2 control-label">Legal Services</label>
                            <div class="col-md-4">
                                <?php if ($is_foreign) : ?>
                                    <input type="text" class="form-control" name="foreign_legal" value="<?php echo htmlspecialchars($bill['foreign_legal2']); ?>">
                                <?php else : ?>
                                    <input type="text" class="form-control" name="legal_services" value="<?php echo htmlspecialchars($bill['legal_services']); ?>">
                                <?php endif; ?>
                            </div>

                            <label class="col-md-2 control-label">Disbursements</label>
                            <div class="col-md-4">
                                <?php if ($is_foreign) : ?>
                                    <input type="text" class="form-control" name="foreign_disbs" value="<?php echo htmlspecialchars($bill['foreign_disbs2']); ?>" readonly>
                                <?php else : ?>
                                    <input type="text" class="form-control" name="disbursements" value="<?php echo htmlspecialchars($bill['disbs']); ?>" readonly>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-md-2 control-label">Discount</label>
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="discount" value="<?php echo htmlspecialchars($bill['discount']); ?>">
                            </div>

                            <label class="col-md-2 control-label">Undiscounted Legal Services</label>
                            <div class="col-md-4">
                                <?php if ($is_foreign) : ?>
                                    <input type="text" class="form-control" name="foreign_undiscount_legal" value="<?php echo htmlspecialchars($bill['foreign_undiscount_legal2']); ?>" readonly>
                                <?php else : ?>
                                    <input type="text" class="form-control" name="undiscounted_legal_services" value="<?php echo htmlspecialchars($bill['undiscounted_legal_services']); ?>" readonly>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-md-2 control-label">Total</label>
                            <div class="col-md-4">
                                <?php if ($is_foreign) : ?>
                                    <input type="text" class="form-control" name="foreign_total" value="<?php echo htmlspecialchars($bill['foreign_total2']); ?>" readonly>
                                <?php else : ?>
                                    <input type="text" class="form-control" name="total" value="<?php echo htmlspecialchars($bill['total']); ?>" readonly>
                                <?php endif; ?>
                            </div>

                            <?php if (!$is_foreign) : ?>
                                <label class="col-md-2 control-label">USD Total</label>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="usd_total" value="<?php echo htmlspecialchars($bill['usd_total']); ?>" readonly>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="col-md-2 control-label">Bill Narrative</label>
                            <div class="col-md-10">
                                <textarea name="bill_narrative" class="form-control" rows="10"><?php echo htmlspecialchars($bill['bill_narrative']); ?></textarea>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-md-2 control-label">Notes [client does not see this]</label>
                            <div class="col-md-10">
                                <textarea name="notes" class="form-control" rows="10"><?php echo htmlspecialchars($bill['remark']); ?></textarea>
                            </div>
                        </div>

                        <!-- Hidden submit button for JS to trigger -->
                        <input type="submit" id="hidden_submit" style="display:none;">
                    </div>
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
        })

        function changeLedesCode(selectObj) {
            var value = selectObj.value;
            $('.ledes-select').val(value);
        }

        function submitModify() {
            var form = document.getElementById('billForm');
            var formData = new FormData(form);

            var btn = document.getElementById('btn-modify');
            var originalText = btn.innerText;
            btn.innerText = 'Updating...';
            btn.disabled = true;

            fetch('test_db/draft_bill_list_bill_mod_db.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Update successful!');
                        // Optionally reload to reflect changes
                        location.reload();
                    } else {
                        alert('Update failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred during update: ' + error.message);
                })
                .finally(() => {
                    btn.innerText = originalText;
                    btn.disabled = false;
                });
        }

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