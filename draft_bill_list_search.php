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

            <form id="action-form" method="POST" action="draft_bill_list_action_db.php" role="form">
                <!-- Sent Date -->
                <div class="form-group">
                    <label for="sent_date" class="col-half">Sent Date</label>
                    <input type="date" class="col-half" name="sent_date" id="sent_date">
                </div>

                <!-- Discount(%) -->
                <div class="form-group">
                    <label for="discount" class="col-half">Discount(%)</label>
                    <input type="number" min="0" max="100" step="1" class="col-half" name="discount" id="discount">
                </div>

                <div class="s-form-bot">
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
                <button type="button" onclick="location.href='draft_bill_list_apply.php'">
                    申請開立收據
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