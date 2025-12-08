<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>Draft Bill List</title>

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
    <?php
    require_once("draft_bill_list_search.php");
    ?>
    <!-- 側邊搜尋內容結束-->

    <div id="winkler-container" class="active">
        <!-- 標題 -->
        <div class="block-hv100">
            <div class="all-heading">
                <h3>申請開立收據</h3>
            </div>

            <div class="table-responsive">
                <form method="POST" action="test_db/draft_bill_list_apply_db.php">
                    <div class="form-horizontal">
                        <!-- Initials -->
                        <div class="form-group" style="margin-right: 0px;">
                            <label for="initials" class="col-md-2 control-label">Initials</label>
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="initials" name="initials" placeholder="Initials" required>
                            </div>
                        </div>

                        <!-- Note -->
                        <div class="form-group" style="margin-right: 0px;">
                            <label for="note" class="col-md-2 control-label">Note</label>
                            <div class="col-md-8">
                                <textarea class="form-control" id="note" name="note" rows="5" cols="50"></textarea>
                            </div>
                        </div>

                        <!-- Apply Button -->
                        <div class="c-form-bot">
                            <button type="submit" name="apply" value="apply" class="btn btn-primary">
                                Apply
                            </button>
                        </div>
                    </div>
                </form>
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
</body>

</html>