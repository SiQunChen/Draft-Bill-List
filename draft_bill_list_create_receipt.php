<!-- Receipt Application Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" role="dialog" aria-labelledby="receiptModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="receiptModalLabel">申請開立收據</h4>
            </div>

            <form id="receipt-form">
                <div class="modal-body">
                    <!-- 已勾選項目列表 -->
                    <div class="form-group">
                        <label>已勾選帳單 <span id="selected-count" class="badge">0</span></label>
                        <div id="selected-bills-list" style="max-height: 150px; overflow-y: auto; background: #f5f5f5; padding: 10px; border-radius: 4px;">
                            <em>尚未勾選任何帳單</em>
                        </div>
                    </div>

                    <hr>

                    <!-- Initials (必填) -->
                    <div class="form-group">
                        <label for="receipt-initials">Initials <span style="color: red;">*</span></label>
                        <input type="text" class="form-control" id="receipt-initials" name="initials" placeholder="請輸入 initials" value="<?php echo isset($_SESSION['initial']) ? htmlspecialchars($_SESSION['initial']) : ''; ?>" required>
                    </div>

                    <!-- Note (選填) -->
                    <div class="form-group">
                        <label for="receipt-notes">Note <span style="color: #888;">(選填)</span></label>
                        <textarea class="form-control" id="receipt-notes" name="notes" rows="3" placeholder="備註說明..."></textarea>
                    </div>

                    <!-- Hidden: bill_ids -->
                    <div id="hidden-bill-ids"></div>
                </div>

                <div class="modal-footer" style="padding-right: 30px; padding-bottom: 20px;">
                    <button type="button" class="btn btn-default" data-dismiss="modal">取消</button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-receipt">送出申請</button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // 當 Modal 開啟時，收集已勾選的帳單
        $('#receiptModal').on('show.bs.modal', function(e) {
            var checkedRows = $('input[name="row_check_box[]"]:checked');
            var count = checkedRows.length;

            // 更新數量 badge
            $('#selected-count').text(count);

            // 清空舊的隱藏欄位
            $('#hidden-bill-ids').empty();

            if (count === 0) {
                $('#selected-bills-list').html('<em style="color: red;">請先勾選至少一筆帳單！</em>');
                $('#btn-submit-receipt').prop('disabled', true);
            } else {
                var listHtml = '<ul style="margin: 0; padding-left: 20px;">';
                checkedRows.each(function() {
                    var billId = $(this).val();
                    // 找到對應的 deb_num (在同一行的 td 內)
                    var debNum = $(this).closest('tr').find('td:eq(5)').text().trim().split('\n')[0].trim();
                    listHtml += '<li>' + debNum + '</li>';

                    // 建立隱藏欄位
                    $('#hidden-bill-ids').append(
                        '<input type="hidden" name="bill_ids[]" value="' + billId + '">'
                    );
                });
                listHtml += '</ul>';
                $('#selected-bills-list').html(listHtml);
                $('#btn-submit-receipt').prop('disabled', false);
            }
        });

        // 表單送出處理
        $('#receipt-form').on('submit', function(e) {
            e.preventDefault();

            var initials = $('#receipt-initials').val().trim();
            if (!initials) {
                alert('Initials 為必填欄位！');
                $('#receipt-initials').focus();
                return;
            }

            var checkedCount = $('input[name="bill_ids[]"]').length;
            if (checkedCount === 0) {
                alert('請先勾選至少一筆帳單！');
                return;
            }

            // 送出表單
            var formData = $(this).serialize();

            $.ajax({
                url: 'test_db/draft_bill_list_create_receipt_db.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                beforeSend: function() {
                    $('#btn-submit-receipt').prop('disabled', true).text('送出中...');
                },
                success: function(response) {
                    if (response.success) {
                        alert('申請成功！申請單號：' + response.sec_id + '\n\n' + response.message);
                        $('#receiptModal').modal('hide');
                        // 清空表單
                        $('#receipt-form')[0].reset();
                    } else {
                        alert('申請失敗：' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    alert('發生錯誤：' + error);
                },
                complete: function() {
                    $('#btn-submit-receipt').prop('disabled', false).text('送出申請');
                }
            });
        });
    });
</script>