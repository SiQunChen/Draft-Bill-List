<div id="sidebar-wrapper" class="active">
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
                    <hr style="border-color: #345a6c">

                    <!-- 申請開立收據按鈕 -->
                    <button type="button" onclick="location.href='draft_bill_list_apply.php'" style="margin-right: 20px;">
                        申請開立收據
                    </button>
                </div>
            </form>
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