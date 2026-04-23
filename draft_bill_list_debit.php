<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
</div>

<div class="modal-body">
    <!--內容開始-->
    <div class="container-fluid">
        <div class="row">
            <!-- 清單 -->
            <div class="col-sm-12  col-xs-12">
                <div class="heading">
                    <h2>Debit Note:<?php echo $_GET['deb_num'] ?></h2>
                </div>

                <?php
                require_once('test_db/receipts_list_db.php');
                $results = getReceiptsDetail($_GET['is_paid'], $_GET['payment_id'], $_GET['deb_num']);
                // print_r($results);
                ?>

                <div role="tabpanel">
                    <!-- 標籤面板：標籤區 -->
                    <ul class="nav nav-tabs" role="tablist">
                        <li role="presentation" class="active">
                            <a href="#tab1" aria-controls="tab1" role="tab" data-toggle="tab">Disbs Detail</a>
                        </li>
                        <!-- <li role="presentation">
                            <a href="#tab4" aria-controls="tab4" role="tab" data-toggle="tab">Bill Narrative</a>
                        </li>
                        <li role="presentation">
                            <a href="#tab2" aria-controls="tab2" role="tab" data-toggle="tab">Disbursements</a>
                        </li>
                        <li role="presentation">
                            <a href="#tab3" aria-controls="tab3" role="tab" data-toggle="tab">Fee Earner</a>
                        </li> -->
                    </ul>

                    <!-- 標籤面板：內容區 -->
                    <div class="tab-content tab-content-winkler">
                        <!-- Disbs Detail 的內容 -->
                        <div role="tabpanel" class="tab-pane active" id="tab1">
                            <div class="table-responsive">
                                <table class="table-hover table table-bordered">
                                    <thead>
                                        <tr class="th-1">
                                            <th style="text-align: center"><input type="checkbox" name="select_all" onchange="toggleAll(this, 'detail_row_check_box')" <?php echo $_GET['index'] == '' ? 'checked' : '' ?>></th>
                                            <th>Case Number</th>
                                            <th>Date</th>
                                            <th>Disb Name</th>
                                            <th>NTD Amount</th>
                                            <th>Currency</th>
                                            <th>Foreign Amount</th>
                                        </tr>
                                    </thead>

                                    <!-- 資料呈現有二組的底色間隔資料 -->
                                    <!-- 單一組ID的資料 -->
                                    <tbody>
                                        <?php
                                        $color = 0;
                                        $idArray = explode(',', $_GET['id']);

                                        foreach ($results as $result) {
                                            // 判斷 id，設定 checked
                                            if (in_array($result['id'], $idArray)) {
                                                $checked = '';
                                            } else {
                                                $checked = 'checked';
                                            }

                                            // 如果是台幣，不顯示幣別、外幣金額
                                            if ($_GET['currency'] == 'TWD') {
                                                $currency = '';
                                                $foreign_amount = '';
                                            } else {
                                                $currency = $result['currency2'];
                                                $foreign_amount = number_format(round($result['foreign_amount2'], 2), 2);
                                            }

                                            // 決定表格行的 class
                                            $row_class = ($color == 0) ? "" : "class='th-gary'";

                                            // 如果 data 的 show_as_legal_service_flag 是 1，不顯示 chechkbox 並反白
                                            if ($result['show_as_legal_service_flag'] == '1') {
                                                $checkbox_html = "";
                                                $row_class = "style='background-color: #e0e0e0; color: #999;'";
                                            } else {
                                                $checkbox_html = "<input type='checkbox' name='detail_row_check_box[]' value='{$result['id']},{$result['ntd_amount']},{$foreign_amount}' $checked>";
                                            }

                                            // 輸出表格行
                                            echo "<tr $row_class>
                                                <td class='text-center'>$checkbox_html</td>
                                                <td>{$result['case_num']}</td>
                                                <td>{$result['date']}</td>
                                                <td>{$result['disb_name']}</td>
                                                <td class='td-lindent5'>" . number_format($result['ntd_amount']) . "</td>
                                                <td>{$currency}</td>
                                                <td class='td-lindent5'>{$foreign_amount}</td>
                                            </tr>";

                                            // 切換顏色
                                            $color = ($color == 0) ? 1 : 0;
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- End 清單 -->
        </div>
    </div><!--End 內容開始-->

    <div class="modal-footer">
        <button type="button" class="btn btn-primary" onclick="saveSelections('<?php echo $_GET['deb_num']; ?>')">Save</button>
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
    </div>
</div>

<script>
    function saveSelections(deb_num) {
        // 選取未打勾的 Checkbox
        const uncheckedItems = [];
        const checkboxes = document.querySelectorAll("input[name='detail_row_check_box[]']");

        checkboxes.forEach((checkbox) => {
            if (!checkbox.checked) {
                // 解析 value，拆成 id 和 amount
                let [id, amount, foreign_amount] = checkbox.value.split(',');

                uncheckedItems.push({
                    id: id.trim(), // id (字串)
                    amount: parseFloat(amount), // amount (轉換為數字)
                    foreign_amount: parseFloat(foreign_amount) // foreign_amount (轉換為數字)
                });
            }
        });

        // 如果有未勾選項目，儲存到 Local Storage
        if (uncheckedItems.length > 0) {
            localStorage.setItem('uncheckedItems_' + deb_num, JSON.stringify(uncheckedItems));
        } else {
            // 如果全部勾選，刪除對應的 localStorage key
            const localStorageKey = 'uncheckedItems_' + deb_num;
            if (localStorage.getItem(localStorageKey)) {
                localStorage.removeItem(localStorageKey);
            }
        }

        // 測試輸出到 Console (可選)
        // console.log('Unchecked Items Saved:', uncheckedItems);

        // 更新頁面
        loadListData();

        // 關閉 Modal
        $('#modal-id').modal('hide');
    }
</script>