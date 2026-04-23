<div id="sidebar-wrapper">
    <div class="sidebar-nav">

        <!-- 搜尋條件內容 -->
        <div class="search-con">
            <div class="heading">
                <h2>Draft Bill List</h2>
            </div>

            <form method="GET" action="draft_bill_list.php" role="form">
                <!-- Case Number -->
                <div class="form-group">
                    <label for="case_number" class="col-half">Case Number</label>
                    <input type="text" class="col-half" name="case_number" id="case_number">
                </div>
                <div class="form-group">
                    <label class="col-half" style="width: 100%;">
                        <input type="radio" name="match_or_like" id="match" value="match" checked>
                        <label for="match" style="margin-right: 13px;">Match</label>

                        <input type="radio" name="match_or_like" id="like" value="like">
                        <label for="like">Like</label>
                    </label>
                </div>

                <!-- Case Manager -->
                <div class="form-group">
                    <label class="col-half">Case Manager</label>
                    <input type="text" class="col-half" name="case_manager" id="case_manager">
                </div>

                <div class="s-form-bot">
                    <!-- List 按鈕：送出表單 -->
                    <button type="submit" name="list" value="list">
                        List
                    </button>
                </div>
            </form>

            <hr style="border-color: #345a6c">

            <form id="action-form" method="POST" action="test_db/draft_bill_list_action_db.php" role="form">
                <!-- Sent Date -->
                <div class="form-group">
                    <label for="sent_date" class="col-half">Sent Date</label>
                    <input type="date" class="col-half" name="sent_date" id="sent_date" min="2008-01-01" max="2100-01-01" value="<?php echo date('Y-m-d'); ?>">
                </div>

                <!-- Discount(%) -->
                <div class="form-group">
                    <label for="discount" class="col-half">Discount(%)</label>
                    <input type="number" min="0" max="100" step="1" class="col-half" name="discount" id="discount" value="0">
                </div>

                <?php
                // 1. 取得目前篩選的 Case Manager (轉大寫以利比對)
                $in_manager = isset($_GET['case_manager']) ? strtoupper(trim($_GET['case_manager'])) : '';

                // 2. 定義允許顯示的名單
                $oc_managers = ['MD', 'SE', 'PD', 'GK', 'VY', 'KA', 'BC'];
                $ppp_managers = ['MD', 'SE', 'PD', 'GK', 'BC'];
                ?>

                <?php if (in_array($in_manager, $oc_managers)): ?>
                    <div class="form-group">
                        <label class="col-half">OC Invoice</label>
                        <label for="oc_invoice_expected"><input type="radio" name="oc_invoice" id="oc_invoice_expected" value="expected">Expected</label>
                        <label class="col-half"></label>
                        <label for="oc_invoice_cancel"><input type="radio" name="oc_invoice" id="oc_invoice_cancel" value="cancel">Cancel</label>
                    </div>
                <?php endif; ?>

                <?php if (in_array($in_manager, $ppp_managers)): ?>
                    <div class="form-group">
                        <label class="col-half">PPP OC</label>
                        <label for="pppoc_expected"><input type="radio" name="pppoc" id="pppoc_expected" value="expected">Expected</label>
                        <label class="col-half"></label>
                        <label for="pppoc_cancel"><input type="radio" name="pppoc" id="pppoc_cancel" value="cancel">Cancel</label>
                    </div>
                <?php endif; ?>

                <div style="text-align: center;">
                    <!-- Update 按鈕 -->
                    <button type="button" id="btn-update" style="margin-right: 15px;">
                        Update
                    </button>

                    <!-- Apply 按鈕 -->
                    <button type="button" id="btn-apply">
                        Apply
                    </button>
                </div>
            </form>

            <hr style="border-color: #345a6c">

            <!-- 申請開立收據按鈕 -->
            <div style="text-align: center;">
                <button type="button" id="btn-receipt" data-toggle="modal" data-target="#receiptModal">
                    申請開立收據
                </button>
            </div>

            <hr style="border-color: #345a6c">

            <div style="text-align: center;">
                <button type="button" id="btn-export" onclick="exportExcel()" style="width: 50%; background-color: #28a745; color: white;">
                    <i class="glyphicon glyphicon-download-alt"></i> Export Excel
                </button>
            </div>
        </div>

        <!-- 搜尋條件內容結束 -->

        <!-- 頁籤 -->

        <div class="search-btn">
            <div class="sidebar-colse">

                <!-- search.js控製申縮的id在這 -->
                <a id="menu-close" href="#" class="btn btn-default btn-lg btn-winkier toggle">
                    <i class="glyphicon glyphicon-search">Draft Bill List</i>
                </a>

            </div>
        </div>

        <!-- 頁籤結束 -->

        <div class="clear"></div>
    </div>
</div>