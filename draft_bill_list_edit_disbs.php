<?php

/**
 * Disbursements Modal 內容
 * 用於 draft_bill_list_edit.php 的 Disbursements 編輯視窗
 */
require_once('test_db/draft_bill_list_edit_disbs_db.php');

$case_num = $_GET['case_num'] ?? '';
$deb_num = $_GET['deb_num'] ?? '';

if (!$case_num || !$deb_num) {
    die("缺少必要參數");
}

try {
    $data = getDisbursements($case_num, $deb_num);
    $disbursements = $data['disbursements'];
    $ntd_total = $data['ntd_total'];
    $records = $data['records'];
} catch (Exception $e) {
    die($e->getMessage());
}
?>

<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
    <h4 class="modal-title">Disbursements - <?php echo htmlspecialchars($case_num); ?></h4>
</div>

<div class="modal-body">
    <div class="container-fluid">
        <!-- 統計資訊 -->
        <div class="row" style="margin-bottom: 15px;">
            <div class="col-sm-6">
                <strong>Number of Disbursements:</strong> <?php echo $records; ?>
            </div>
            <div class="col-sm-6 text-right">
                <strong>Total NTD$ Amount:</strong> <?php echo $ntd_total; ?>
            </div>
        </div>

        <!-- Disbursements 清單 -->
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead>
                    <tr class="bg-primary">
                        <th class="text-center">Case</th>
                        <th class="text-center">Date</th>
                        <th class="text-center">Disbursement</th>
                        <th class="text-center">NT$ Amount</th>
                        <th class="text-center">Currency</th>
                        <th class="text-center">Foreign Amount</th>
                        <th class="text-center">Entered by</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($disbursements as $disb): ?>
                        <?php
                        if ($disb['check_bills'] == 1) {
                            continue;
                        }

                        $rowBg = ($disb['show_flag'] == 1) ? 'background-color: #BBBBBB;' : '';
                        $noChargeChecked = ($disb['show_flag'] == -1) ? 'checked' : '';
                        $legalServiceChecked = ($disb['show_as_legal_service_flag'] == 1) ? 'checked' : '';
                        $removeDisabled = (!empty($disb['bpm_rownum']) && !empty($disb['bpm_appnum'])) ? 'disabled title="已關聯 BPM，無法移除"' : '';
                        ?>
                        <tr style="<?php echo $rowBg; ?>">
                            <td>
                                <?php echo htmlspecialchars($disb['case_num']); ?>
                                <input type="hidden" class="disb-case-num" value="<?php echo htmlspecialchars($disb['case_num']); ?>">
                                <input type="hidden" class="disb-id" value="<?php echo $disb['id']; ?>">
                                <input type="hidden" class="disb-deb-num" value="<?php echo htmlspecialchars($disb['deb_num']); ?>">
                                <input type="hidden" class="disb-code" value="<?php echo htmlspecialchars($disb['disb_code']); ?>">
                            </td>
                            <td>
                                <input type="date" class="form-control disb-date" value="<?php echo htmlspecialchars($disb['date']); ?>" style="width: 110px;">
                            </td>
                            <td>
                                <span class="disb-code-display"><?php echo htmlspecialchars($disb['disb_code']); ?></span>
                                <?php echo htmlspecialchars($disb['disb_name']); ?>
                            </td>
                            <td class="text-right">
                                <?php echo $disb['ntd_amount_formatted']; ?>
                                <input type="hidden" class="disb-ntd-amount" value="<?php echo $disb['ntd_amount']; ?>">
                            </td>
                            <td class="text-center">
                                <?php echo htmlspecialchars($disb['currency2']); ?>
                            </td>
                            <td class="text-right">
                                <?php echo $disb['foreign_amount2'] ? number_format($disb['foreign_amount2'], 2) : ''; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($disb['initials']); ?>
                            </td>
                        </tr>
                        <tr style="<?php echo $rowBg; ?>">
                            <td colspan="4">
                                <input type="text" class="form-control disb-narrative" value="<?php echo htmlspecialchars($disb['narrative']); ?>">
                            </td>
                            <td colspan="3">
                                <label class="checkbox-inline">
                                    <input type="checkbox" class="disb-no-charge" <?php echo $noChargeChecked; ?> onclick="keepcount(this)"> No charge
                                </label>
                                <label class="checkbox-inline">
                                    <input type="checkbox" class="disb-legal-service" <?php echo $legalServiceChecked; ?> onclick="keepcount(this)"> Legal service
                                </label>
                                <label class="checkbox-inline">
                                    <input type="checkbox" class="disb-remove" <?php echo $removeDisabled; ?>> Remove
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($disbursements)): ?>
                        <tr>
                            <td colspan="7" class="text-center">無 Disbursements 資料</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-footer" style="padding-right: 30px; padding-bottom: 20px;">
    <button type="button" class="btn btn-default" data-dismiss="modal" style="margin-right: 10px;">Close</button>
    <button type="button" class="btn btn-primary btn-save-all">Save</button>
</div>

<script>
    // No charge 和 Legal service 互斥檢查
    function keepcount(checkbox) {
        var row = $(checkbox).closest('tr');
        var noCharge = row.find('.disb-no-charge');
        var legalService = row.find('.disb-legal-service');

        if (noCharge.is(':checked') && legalService.is(':checked')) {
            alert("'No charge' and 'Legal service' Only choose one");
            $(checkbox).prop('checked', false);
            return false;
        }
    }

    // 批量儲存按鈕
    $(document).on('click', '.btn-save-all', function() {
        var btn = $(this);
        var disbursements = [];
        var hasError = false;

        // 遍歷每一行資料
        // 注意：每一筆資料佔用兩列 (tr)
        // 第一列包含 .disb-id
        $('#disbModal table tbody tr').each(function() {
            var row = $(this);

            if (row.find('.disb-id').length > 0) {
                var prevRow = row;
                var nextRow = row.next('tr');

                var id = prevRow.find('.disb-id').val();
                var case_num = prevRow.find('.disb-case-num').val();
                var deb_num = prevRow.find('.disb-deb-num').val();
                var disb_code = prevRow.find('.disb-code').val();
                var date = prevRow.find('.disb-date').val();
                var initials = prevRow.find('.disb-initials').val();
                var ntd_amount = prevRow.find('.disb-ntd-amount').val();

                var narrative = nextRow.find('.disb-narrative').val();
                var isNoCharge = nextRow.find('.disb-no-charge').is(':checked');
                var isLegalService = nextRow.find('.disb-legal-service').is(':checked');
                var isRemove = nextRow.find('.disb-remove').is(':checked');

                // 金額超過 10000 且要 No charge 時警告
                if (parseFloat(ntd_amount) >= 10000 && isNoCharge) {
                    alert("Case " + case_num + " (" + disb_code + "): 當代墊費用有超過 $10,000 時，如果要進行 No charge，請以Email的方式徵詢案件協調合夥人或代理合夥人同意, 同時副本給財務部FC，由FC進行變更");
                    hasError = true;
                    return false; // break loop
                }

                disbursements.push({
                    id: id,
                    case_num: case_num,
                    deb_num: deb_num,
                    date: date,
                    initials: initials,
                    narrative: narrative,
                    show_flag: isNoCharge ? 1 : 0,
                    show_as_legal_service_flag: isLegalService ? 1 : 0,
                    check_bills: isRemove ? 1 : 0
                });
            }
        });

        if (hasError) return;

        if (disbursements.length === 0) {
            // 如果沒有資料列 (例如本來就空)，直接關閉或不做事
            // 但這裡也可能是使用者沒改任何東西就按 Save
            // 不過既然是 "Save all"，即使沒變動也可以送(雖然浪費)，或者列表為空。
            // 列表為空時長度為0
            if ($('#disbModal table tbody tr').length <= 1 && $('#disbModal table tbody tr td[colspan="7"]').length > 0) {
                // 確實沒資料
                return;
            }
            // 否則繼續
        }

        // 發送請求
        btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: 'test_db/draft_bill_list_edit_disbs_db.php',
            method: 'POST',
            data: {
                action: 'update',
                disbursements: disbursements
            },
            success: function(response) {
                if (response.success) {
                    $('#disbModal').modal('hide');
                    location.reload();
                } else {
                    alert('儲存失敗: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('儲存發生錯誤: ' + error);
            },
            complete: function() {
                btn.prop('disabled', false).text('Save');
            }
        });
    });
</script>