<?php

/**
 * Retainer 預收款進階分配 Modal
 * 用於 draft_bill_list.php 的預收款分配視窗
 */
?>

<!-- Retainer Distribution Modal -->
<div class="modal fade" id="retainerModal" tabindex="-1" role="dialog" aria-labelledby="retainerModalLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="retainerModalLabel">
                    <i class="glyphicon glyphicon-credit-card"></i>
                    預收款金額分配
                </h4>
            </div>

            <div class="modal-body">
                <!-- 預收款餘額資訊 -->
                <div class="well well-sm">
                    <div class="row">
                        <div class="col-sm-5">
                            <strong>預收款來源案號：</strong>
                            <span id="retainer-case-num" class="text-primary"></span>
                        </div>

                        <div class="col-sm-7 text-right">

                            <span style="margin-right: 20px; display: inline-block;">
                                <strong>帳單金額：</strong>
                                <span id="retainer-bill-amount" class="text-primary" style="font-size: 18px; font-weight: bold;"></span>
                            </span>

                            <span style="display: inline-block;">
                                <strong>總預收款餘額：</strong>
                                <span id="retainer-remain" class="text-success" style="font-size: 18px; font-weight: bold;"></span>
                            </span>

                        </div>
                    </div>
                </div>

                <!-- 功能按鈕區（排序與自動分配） -->
                <div style="margin-bottom: 15px; display: flex; align-items: center;">
                    <div class="btn-group">
                        <button type="button" class="btn btn-default btn-sm btn-sort active" data-sort="date">
                            <i class="glyphicon glyphicon-time"></i> 日期 (FIFO)
                        </button>
                        <button type="button" class="btn btn-default btn-sm btn-sort" data-sort="date-desc">
                            <i class="glyphicon glyphicon-time"></i> 日期 (LIFO)
                        </button>
                        <button type="button" class="btn btn-default btn-sm btn-sort" data-sort="amount-asc">
                            <i class="glyphicon glyphicon-sort-by-attributes"></i> 金額 ↑
                        </button>
                        <button type="button" class="btn btn-default btn-sm btn-sort" data-sort="amount-desc">
                            <i class="glyphicon glyphicon-sort-by-attributes-alt"></i> 金額 ↓
                        </button>
                    </div>

                    <button type="button" class="btn btn-success btn-sm" id="btn-auto-distribute" style="margin-left: 15px;">
                        <i class="glyphicon glyphicon-flash"></i> Auto-Distribute
                    </button>
                </div>

                <!-- 案件列表 -->
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover table-bordered" id="retainer-list-table">
                        <thead style="background-color: #f5f5f5;">
                            <tr>
                                <th class="text-center" style="width: 50px;">
                                    <button type="button" class="btn btn-default btn-sm" id="btn-lock-all" title="全部鎖定">
                                        <i class="glyphicon glyphicon-lock"></i>
                                    </button>
                                </th>
                                <th class="text-center">案號</th>
                                <th class="text-center">編號</th>
                                <th class="text-center">日期</th>
                                <th class="text-center">餘額</th>
                                <th class="text-center" style="width: 150px;">抵扣金額</th>
                            </tr>
                        </thead>
                        <tbody id="retainer-list-body">
                            <!-- 動態載入 -->
                        </tbody>
                    </table>
                </div>

                <!-- 加載中提示 -->
                <div id="retainer-loading" class="text-center" style="padding: 30px; display: none;">
                    <i class="glyphicon glyphicon-refresh glyphicon-spin" style="font-size: 24px;"></i>
                    <p>載入中...</p>
                </div>

                <!-- 無資料提示 -->
                <div id="retainer-no-data" class="text-center text-muted" style="padding: 30px; display: none;">
                    <i class="glyphicon glyphicon-info-sign" style="font-size: 24px;"></i>
                    <p>目前沒有可分配的草稿帳單</p>
                </div>
            </div>

            <div class="modal-footer" style="padding: 20px 30px;">
                <!-- 總計與警告 -->
                <div class="pull-left" style="text-align: left;">
                    <div style="font-size: 16px;">
                        <strong>總抵扣金額：</strong>
                        <span id="total-allocated" style="font-weight: bold;">0</span>
                        <span id="allocated-currency"></span>
                    </div>
                    <div id="over-bill-warning" class="text-danger" style="display: none; margin-top: 5px;">
                        <i class="glyphicon glyphicon-warning-sign"></i>
                        <strong>已超出帳單金額 <span id="over-bill-amount"></span></strong>
                    </div>
                    <div id="over-retainer-warning" class="text-danger" style="display: none; margin-top: 5px;">
                        <i class="glyphicon glyphicon-warning-sign"></i>
                        <strong>已超出預收款餘額 <span id="over-retainer-amount"></span></strong>
                    </div>
                </div>

                <div class="pull-right">
                    <button type="button" class="btn btn-default" data-dismiss="modal">關閉</button>
                    <button type="button" class="btn btn-primary" id="btn-confirm-allocation" disabled>
                        <i class="glyphicon glyphicon-ok"></i> 確認抵扣
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
    #retainerModal .locked-row {
        background-color: #fcf8e3 !important;
    }

    #retainerModal .btn-lock {
        padding: 4px 8px;
    }

    #retainerModal .btn-lock.locked {
        color: #d9534f;
    }

    #retainerModal .btn-lock.unlocked {
        color: #999;
    }

    #retainerModal .allocation-input {
        width: 100%;
        text-align: right;
    }

    #retainerModal .insufficient {
        color: #d9534f !important;
        font-weight: bold;
    }

    .glyphicon-spin {
        animation: spin 1s infinite linear;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
</style>

<script>
    $(document).ready(function() {
        // 儲存當前資料
        let billsCaseNum = '';
        let debNum = '';
        let billTotal = 0;

        let retainerData = {
            remain: 0,
            currency: 'TWD',
            retainers: []
        };

        // 當 Modal 開啟時載入資料
        $('#retainerModal').on('show.bs.modal', function(e) {
            const button = $(e.relatedTarget);

            // 取得帳單資訊
            billsCaseNum = button.data('bills-case-num');
            debNum = button.data('deb-num');
            billTotal = parseFloat(button.data('total')) || 0;

            // 取得預收款資訊
            const retainerCaseNum = button.data('retainer-case');
            const amount = parseFloat(button.data('retainer-amount')) || 0;
            const currency = button.data('retainer-currency') || '';
            const deduct = parseFloat(button.data('deduct')) || 0;

            if (!retainerCaseNum) {
                alert('缺少預收款案號參數');
                return;
            }

            // 顯示來源案號
            $('#retainer-case-num').text(retainerCaseNum);

            // 更新餘額顯示與資料
            retainerData.remain = amount + deduct;
            retainerData.currency = currency;
            if (currency !== 'TWD') {
                $('#retainer-remain').text((amount + deduct).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ' ' + currency);

                $('#retainer-bill-amount').text(billTotal.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ' ' + currency);
            } else {
                $('#retainer-remain').text('TWD ' + (amount + deduct).toLocaleString('en-US'));
                $('#retainer-bill-amount').text('TWD ' + billTotal.toLocaleString('en-US'));
            }
            $('#allocated-currency').text(retainerData.currency);

            // 載入帳單列表
            loadRetainerData(retainerCaseNum, billsCaseNum, currency);
        });

        // 載入預收款資料
        function loadRetainerData(retainerCaseNum, billsCaseNum, currency) {
            $('#retainer-loading').show();
            $('#retainer-list-body').empty();
            $('#retainer-no-data').hide();

            $.ajax({
                url: 'test_db/draft_bill_list_retainer_db.php',
                type: 'GET',
                data: {
                    action: 'get_retainers',
                    retainer_case_num: retainerCaseNum,
                    bills_case_num: billsCaseNum,
                    currency: currency
                },
                dataType: 'json',
                success: function(response) {
                    $('#retainer-loading').hide();

                    if (response.success) {
                        // 儲存帳單資料
                        retainerData.retainers = response.retainers.map(retainer => ({
                            ...retainer
                        }));

                        if (retainerData.retainers.length === 0) {
                            $('#retainer-no-data').show();
                        } else {
                            $('.btn-sort[data-sort="date"]').trigger('click');
                            // 自動分配一次
                            $('#btn-auto-distribute').trigger('click');
                        }
                    } else {
                        alert('載入失敗：' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    $('#retainer-loading').hide();
                    alert('載入發生錯誤：' + error);
                }
            });
        }

        // 渲染帳單表格
        function renderRetainersTable() {
            const tbody = $('#retainer-list-body');
            tbody.empty();

            retainerData.retainers.forEach((retainer, index) => {
                const lockedClass = retainer.is_locked ? 'locked-row' : '';
                const lockIcon = retainer.is_locked ? 'glyphicon-lock locked' : 'glyphicon-lock unlocked';
                const readonly = retainer.is_locked ? 'readonly' : '';

                const row = `
                <tr class="${lockedClass}" data-index="${index}">
                    <td class="text-center">
                        <button type="button" class="btn btn-default btn-sm btn-lock ${retainer.is_locked ? 'locked' : 'unlocked'}" data-index="${index}">
                            <i class="glyphicon ${lockIcon}"></i>
                        </button>
                    </td>
                    <td>${retainer.case_num}</td>
                    <td>${retainer.deb_num}</td>
                    <td class="text-center">${retainer.date || '-'}</td>
                    <td class="text-right">${retainer.fmt_remain} ${retainer.currency}</td>
                    <td>
                        <input type="text" inputmode="decimal" class="form-control allocation-input" 
                               data-index="${index}" 
                               value="${formatAllocationDisplay(retainer.allocated_amount, retainer.currency)}" 
                               ${readonly}>
                    </td>
                </tr>
            `;
                tbody.append(row);
            });

            updateTotals();
        }

        // 將金額數值格式化為帶千分位的字串（供 input 顯示用）
        function formatAllocationDisplay(value, currency) {
            const num = parseFloat(value) || 0;
            if (currency === 'TWD') {
                return num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            } else {
                return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }


        // 排序按鈕
        $(document).on('click', '.btn-sort', function() {
            $('.btn-sort').removeClass('active');
            $(this).addClass('active');

            const sortType = $(this).data('sort');

            retainerData.retainers.sort((a, b) => {
                let diff = 0;
                switch (sortType) {
                    case 'date':
                        diff = new Date(a.date) - new Date(b.date);
                        break;
                    case 'date-desc':
                        diff = new Date(b.date) - new Date(a.date);
                        break;
                    case 'amount-asc':
                        diff = a.remain - b.remain;
                        break;
                    case 'amount-desc':
                        diff = b.remain - a.remain;
                        break;
                }

                // 次要排序：帳單編號從小到大
                if (diff === 0) {
                    return (a.deb_num || '').localeCompare(b.deb_num || '');
                }
                return diff;
            });

            renderRetainersTable();
        });

        // 鎖定/解鎖按鈕
        $(document).on('click', '.btn-lock', function() {
            const index = $(this).data('index');
            retainerData.retainers[index].is_locked = !retainerData.retainers[index].is_locked;
            renderRetainersTable();
            updateLockAllIcon();
        });

        // 全選鎖定/解鎖
        $(document).on('click', '#btn-lock-all', function() {
            const anyUnlocked = retainerData.retainers.some(r => !r.is_locked);

            retainerData.retainers.forEach(retainer => {
                retainer.is_locked = anyUnlocked;
            });

            renderRetainersTable();
            updateLockAllIcon();
        });

        // 更新全選鎖定圖示
        function updateLockAllIcon() {
            const allLocked = retainerData.retainers.length > 0 && retainerData.retainers.every(r => r.is_locked);
            const anyLocked = retainerData.retainers.some(r => r.is_locked);
            const btn = $('#btn-lock-all');
            const icon = btn.find('i');

            if (allLocked) {
                btn.addClass('locked').removeClass('unlocked');
                icon.addClass('locked').removeClass('unlocked');
                btn.css('color', '#d9534f');
            } else {
                btn.removeClass('locked').addClass('unlocked');
                icon.removeClass('locked').addClass('unlocked');
                btn.css('color', '#999');
            }
        }

        // 金額輸入變更（即時更新）
        $(document).on('input', '.allocation-input', function() {
            const index = $(this).data('index');
            const raw = $(this).val().replace(/,/g, '');
            const value = parseFloat(raw) || 0;
            retainerData.retainers[index].allocated_amount = value;
            updateTotals();
        });

        // 聚焦時還原純數字，方便編輯
        $(document).on('focus', '.allocation-input', function() {
            const raw = $(this).val().replace(/,/g, '');
            $(this).val(raw === '0' ? '' : raw);
        });

        // 失焦時重新加上千分位格式
        $(document).on('blur', '.allocation-input', function() {
            const index = $(this).data('index');
            const currency = retainerData.retainers[index].currency;
            const value = retainerData.retainers[index].allocated_amount;
            $(this).val(formatAllocationDisplay(value, currency));
        });

        // Auto-Distribute 按鈕
        $('#btn-auto-distribute').on('click', function() {
            let bill_total = billTotal;

            // 先扣除已鎖定的金額
            retainerData.retainers.forEach(retainer => {
                if (retainer.is_locked) {
                    bill_total -= parseFloat(retainer.allocated_amount) || 0;
                }
            });

            // 分配給未鎖定的案件（按當前順序）
            retainerData.retainers.forEach(retainer => {
                if (!retainer.is_locked) {
                    const remain = parseFloat(retainer.remain) || 0;
                    if (bill_total >= remain) {
                        retainer.allocated_amount = remain;
                        bill_total -= remain;
                    } else if (bill_total > 0) {
                        retainer.allocated_amount = bill_total;
                        bill_total = 0;
                    } else {
                        retainer.allocated_amount = 0;
                    }
                }
            });

            renderRetainersTable();
        });

        // 更新總計
        function updateTotals() {
            let total = 0;
            retainerData.retainers.forEach(retainer => {
                if (retainer.is_locked) {
                    total += parseFloat(retainer.allocated_amount) || 0;
                }
            });

            const decimal = retainerData.currency === 'TWD' ? 0 : 2;

            $('#total-allocated').text(total.toLocaleString('en-US', {
                minimumFractionDigits: decimal,
                maximumFractionDigits: decimal
            }));

            let hasError = false;

            // 檢查是否超過帳單金額
            if (total > billTotal) {
                const overAmount = total - billTotal;
                $('#over-bill-amount').text(overAmount.toLocaleString('en-US', {
                    minimumFractionDigits: decimal,
                    maximumFractionDigits: decimal
                }) + ' ' + retainerData.currency);

                $('#over-bill-warning').show();
                hasError = true;
            } else {
                $('#over-bill-warning').hide();
            }

            // 檢查是否超過預收款餘額
            if (total > retainerData.remain) {
                const overAmount = total - retainerData.remain;
                $('#over-retainer-amount').text(overAmount.toLocaleString('en-US', {
                    minimumFractionDigits: decimal,
                    maximumFractionDigits: decimal
                }) + ' ' + retainerData.currency);

                $('#over-retainer-warning').show();
                hasError = true;
            } else {
                $('#over-retainer-warning').hide();
            }

            // 根據是否有任何錯誤，統一處理按鈕與樣式
            if (hasError) {
                $('#total-allocated').addClass('insufficient');
                $('#btn-confirm-allocation').prop('disabled', true);
            } else {
                $('#total-allocated').removeClass('insufficient');
                $('#btn-confirm-allocation').prop('disabled', false);
            }
        }

        // 確認分配按鈕
        $('#btn-confirm-allocation').on('click', function() {
            if (!confirm('確定要進行抵扣嗎？')) {
                return;
            }

            // 準備要送出的資料（包含所有資料，讓後端判斷是否刪除）
            const dataToSave = retainerData.retainers.map(r => ({
                id: r.id,
                case_num: r.case_num,
                bills_case_num: billsCaseNum,
                deb_num: debNum,
                payment_type: r.payment_type,
                payment_method: r.payment_method,
                bank_account: r.bank_account,
                currency: r.currency,
                rate: r.rate,
                fmt_remain: r.remain,
                allocated_amount: r.allocated_amount,
                is_locked: r.is_locked
            }));

            // 送出到後端
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="glyphicon glyphicon-refresh glyphicon-spin"></i> 處理中...');

            $.ajax({
                url: 'test_db/draft_bill_list_retainer_db.php',
                type: 'POST',
                data: {
                    action: 'save_allocation',
                    retainers: JSON.stringify(dataToSave)
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.message || '抵扣成功');
                        $('#retainerModal').modal('hide');
                        location.reload();
                    } else {
                        alert('儲存失敗：' + (response.message || '未知錯誤'));
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = '發生錯誤：' + error;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg += '\n詳細訊息：' + xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        // 嘗試解析非 JSON 的錯誤回應
                        try {
                            const json = JSON.parse(xhr.responseText);
                            if (json.message) {
                                errorMsg += '\n詳細訊息：' + json.message;
                            }
                        } catch (e) {
                            // 無法解析就不顯示額外內容
                        }
                    }
                    alert(errorMsg);
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="glyphicon glyphicon-ok"></i> 確認抵扣');
                }
            });
        });
    });
</script>