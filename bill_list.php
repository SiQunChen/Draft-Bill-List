<html lang="en">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
  <title>Sent Bill List</title>

  <!-- Bootstrap -->
  <link rel="stylesheet" href="css/bootstrap.css">
  <link rel="stylesheet" href="css/winkler.css">
  <link rel="stylesheet" href="css/winkler-rwd.css">
  <link rel="stylesheet" href="css/left-search.css">
  <link rel="stylesheet" href="css/winkler-from.css">

  <!--[if lt IE 9]>
   <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
   <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
  <![endif]-->
  <!-- <script>
  if (window.history && window.history.pushState) {
    window.history.pushState('forward', null, window.location.href);
    window.onpopstate = function() {
      window.history.pushState('forward', null, window.location.href);
    };
  }
</script> -->
</head>

<body data-spy="scroll" data-target=".amanda-nav">
  <?php
  error_reporting(E_ERROR);
  require_once("menu.php");
  $year = date("Y");
  $month = date("m");
  ?>

  <SCRIPT LANGUAGE="javascript">
    function Donotbill(url) {
      if (confirm("Are you sure? ")) {
        document.location = url;
      }
    }
  </SCRIPT>


  <!-- 側邊搜尋內容 -->
  <div id="sidebar-wrapper">
    <div class="sidebar-nav">

      <!-- 搜尋條件內容 -->
      <div class="search-con">
        <div class="heading">
          <h2>Sent Bill List</h2>
        </div>

        <form method="POST" ACTION="bill_list.php" role="form">
          <div class="form-group">
            <label>Case Number:</label>
            <input type="text" name="case_num" class="col-half" placeholder="Case Number">
            <BR>
            <input type="radio" name='comp' value='Match' checked>Match
            <input type="radio" name='comp' value='Like'>Like<BR>
          </div>
          <div class="form-group">
            <label class="type-red">AND/OR</label>
            <BR>
            <label class="col-half">Invoice Number:</label>
            <input type="text" name="deb_num" class="form-control" placeholder="Invoice Number">
          </div>

          <div class="form-group">
            <label class="type-red">AND/OR</label>
            <BR>
            <label>Case Manager:</label>
            <input type="text" name="case_manager" class="form-control" placeholder="Case Manager">
          </div>

          <div class="form-group">
            <label class="type-red">AND/OR</label>
            <BR>
            <label>Bills Company Name:</label>
            <input type="text" name="party_bills" class="form-control" placeholder="Bills Company Name">
          </div>

          <div class="form-group">
            <label>Select Year and Month:</label>
            <BR>
            <select class="" name="year">
              <?php
              for ($year = date('Y'); $year >= 2016; $year--) {
                if ($year == date("Y")) {
                  echo "<option selected>" . $year . "</option>";
                } else {
                  echo "<option>" . $year . "</option>";
                }
              }
              ?>
            </select>
            <select name="month" id="select">
              <?php
              $month_list = ['ALL', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', 'Q1', 'Q2', 'Q3', 'Q4'];
              foreach ($month_list as $month) {
                if ($month == date("m")) {
                  echo "<option selected>" . $month . "</option>";
                } else {
                  echo "<option>" . $month . "</option>";
                }
              }
              ?>
            </select>
          </div>

          <div class="s-form-bot">
            <button TYPE="SUBMIT" NAME="sent_bills_submit" value="Search">List</button>
          </div>
        </form>
      </div>

      <!-- 搜尋條件內容結束 -->

      <!-- 頁籤 -->

      <div class="search-btn">
        <div class="sidebar-colse">

          <!-- search.js控製申縮的id在這 -->
          <a id="menu-close" href="#" class="btn btn-default btn-lg btn-winkier toggle">
            <i class="glyphicon glyphicon-search">Sent Bill List</i>
          </a>

        </div>
      </div>

      <!-- 頁籤結束 -->

      <div class="clear"></div>
    </div>
  </div>

  <!-- 側邊搜尋內容結束-->

  <!--搜尋內容開始-->
  <?php
  require_once('test_db/bill_list_db.php');
  if ($_GET['comp'] != '') {
    $case_num = $_GET['case_num'];
    $deb_num = $_GET['deb_num'];
    $case_manager = $_GET['case_manager'];
    $party_bills = $_GET['party_bills'];
    $year = $_GET['year'];
    $month = $_GET['month'];
    $comp = $_GET['comp'];
    $sort_key = $_GET['sort_key'];
    $curr_status = $_GET['curr_status'];
    $curr_status = ($curr_status == '') ? 'ASC' : $curr_status;
  } else {
    $case_num = ($_SERVER["REQUEST_METHOD"] == "POST") ? $_POST['case_num'] : '';
    $deb_num = ($_SERVER["REQUEST_METHOD"] == "POST") ? $_POST['deb_num'] : '';
    $case_manager = ($_SERVER["REQUEST_METHOD"] == "POST") ? $_POST['case_manager'] : '';
    $party_bills = ($_SERVER["REQUEST_METHOD"] == "POST") ? $_POST['party_bills'] : '';
    $year = ($_SERVER["REQUEST_METHOD"] == "POST") ? $_POST['year'] : date('Y');
    $month = ($_SERVER["REQUEST_METHOD"] == "POST") ? $_POST['month'] : date('m');
    $comp = ($_SERVER["REQUEST_METHOD"] == "POST") ? $_POST['comp'] : 'Match';
    $sort_key = 'case_manager';
    $curr_status = 'ASC';
  }
  $result = getBillList($case_num, $comp, $deb_num, $case_manager, $party_bills, $year, $month);
  if ($curr_status == 'ASC') {
    if ($sort_key != '') {
      usort($result['result'], function ($a, $b) use ($sort_key) {
        return $a[$sort_key] <=> $b[$sort_key];
      });
    }
    $curr_status = ($curr_status == 'ASC') ? 'DESC' : 'ASC';
  } elseif ($curr_status == 'DESC') {
    if ($sort_key != '') {
      usort($result['result'], function ($a, $b) use ($sort_key) {
        return $b[$sort_key] <=> $a[$sort_key];
      });
    }
    $curr_status = ($curr_status == 'ASC') ? 'DESC' : 'ASC';
  }
  ?>

  <div id="winkler-container"><!-- 這裡跟著變動大小的div -->
    <div class="row">
      <!-- Total -->
      <table class="table table-hover table-bordered">
        <thead>
          <tr>
            <th colspan="5" class="bg-primary">Total</th>
          </tr>
        </thead>
        <thead>
          <tr>
            <th>Period</th>
            <th><?php echo $result['bill_sent_total']['bill_year'] ?></th>
            <th class="td"><?php echo $result['bill_sent_total']['bill_month'] ?></th>
            <th class="td"><?php echo $result['bill_sent_total']['week_start'] . " ~ " . $result['bill_sent_total']['today'] ?></th>
            <th class="td"><?php echo $result['bill_sent_total']['today'] ?></th>
          </tr>
        </thead>
        <tbody>
          <tr class="tr">
            <th>Bills Issued</th>
            <td><?php echo number_format($result['bill_sent_total']['bill_year_count']) ?></td>
            <td><?php echo number_format($result['bill_sent_total']['bill_month_count']) ?></td>
            <td><?php echo number_format($result['bill_sent_total']['week_count']) ?></td>
            <td><?php echo number_format($result['bill_sent_total']['day_count']) ?></td>
          </tr>
          <tr class="tr">
            <th>Total Billed</th>
            <td><?php echo number_format($result['bill_sent_total']['bill_year_total']) ?></td>
            <td><?php echo number_format($result['bill_sent_total']['bill_month_total']) ?></td>
            <td><?php echo number_format($result['bill_sent_total']['week_total']) ?></td>
            <td><?php echo number_format($result['bill_sent_total']['day_total']) ?></td>
          </tr>
        </tbody>
      </table>
      <!--End Total -->
    </div>

    <!-- 標題 -->
    <div class="block-hv100">
      <div class="all-heading">
        <h3>
          <form method="post" name="bonus" action="http://192.168.0.75/bill_list/export_excel.php">
            Serach: <?php echo $year . "-" . $month ?>

            <input name="year" type="hidden" value="<?php echo $year; ?>">
            <input name="month" type="hidden" value="<?php echo $month; ?>">
            <input name="comp" type="hidden" value="<?php echo $comp; ?>">
            <input name="case_num" type="hidden" value="<?php echo $case_num; ?>">
            <input name="deb_num" type="hidden" value="<?php echo $deb_num; ?>">
            <input name="party_bills" type="hidden" value="<?php echo $pary_bills; ?>">
            <input name="case_manager" type="hidden" value="<?php echo $case_manager; ?>">
            <input name="sort_key" type="hidden" value="<?php echo $sort_key; ?>">
            <button NAME="mve_submit">Export Excel</button>

            <!--       <input name="mode" type="submit" value="Export Excel" class="btn-default">-->
          </form>
        </h3>
      </div>

      <!-- 第一段內容 -->
      <div class="table-responsive">
        <table class="table hv1-table table-hover  ">
          <thead>
            <tr>
              <th nowrap class="text-center">Detail</th>
              <th nowrap><a href="bill_list.php?op=sort&curr_status=<?php echo $curr_status ?>&sort_key=case_manager&total=<?php echo $result['total'] ?>&usd_total=<?php echo $result['usd_total'] ?>&legal_services=<?php echo $result['legal_services'] ?>&disbs=<?php echo $result['disbs'] ?>&case_num=<?php echo $case_num ?>&case_manager=<?php echo $case_manager ?>&comp=<?php echo $comp ?>&year=<?php echo $year ?>&month=<?php echo $month ?>">
                  Manager
                  <?php
                  if ($sort_key == 'case_manager' && $curr_status == 'ASC') {
                    echo "<img src='images/down-b.png' />";
                  }
                  if ($sort_key == 'case_manager' && $curr_status == 'DESC') {
                    echo "<img src='images/up-b.png' />";
                  }
                  ?>
                </a></th>
              <th nowrap><a href="bill_list.php?op=sort&curr_status=<?php echo $curr_status ?>&sort_key=case_num&total=<?php echo $result['total'] ?>&usd_total=<?php echo $result['usd_total'] ?>&legal_services=<?php echo $result['legal_services'] ?>&disbs=<?php echo $result['disbs'] ?>&case_num=<?php echo $case_num ?>&case_manager=<?php echo $case_manager ?>&comp=<?php echo $comp ?>&year=<?php echo $year ?>&month=<?php echo $month ?>">
                  Case No.
                  <?php
                  if ($sort_key == 'case_num' && $curr_status == 'ASC') {
                    echo "<img src='images/down-b.png' />";
                  }
                  if ($sort_key == 'case_num' && $curr_status == 'DESC') {
                    echo "<img src='images/up-b.png' />";
                  }
                  ?>
                </a></th>
              <th><a href="bill_list.php?op=sort&curr_status=<?php echo $curr_status ?>&sort_key=deb_num&total=<?php echo $result['total'] ?>&usd_total=<?php echo $result['usd_total'] ?>&legal_services=<?php echo $result['legal_services'] ?>&disbs=<?php echo $result['disbs'] ?>&case_num=<?php echo $case_num ?>&case_manager=<?php echo $case_manager ?>&comp=<?php echo $comp ?>&year=<?php echo $year ?>&month=<?php echo $month ?>">
                  Debit Note
                  <?php
                  if ($sort_key == 'deb_num' && $curr_status == 'ASC') {
                    echo "<img src='images/down-b.png' />";
                  }
                  if ($sort_key == 'deb_num' && $curr_status == 'DESC') {
                    echo "<img src='images/up-b.png' />";
                  }
                  ?>
                </a></th>
              <th nowrap class="text-right"><a href="bill_list.php?op=sort&curr_status=<?php echo $curr_status ?>&sort_key=legal_services&total=<?php echo $result['total'] ?>&usd_total=<?php echo $result['usd_total'] ?>&legal_services=<?php echo $result['legal_services'] ?>&disbs=<?php echo $result['disbs'] ?>&case_num=<?php echo $case_num ?>&case_manager=<?php echo $case_manager ?>&comp=<?php echo $comp ?>&year=<?php echo $year ?>&month=<?php echo $month ?>">
                  Services
                  <?php
                  if ($sort_key == 'legal_services' && $curr_status == 'ASC') {
                    echo "<img src='images/down-b.png' />";
                  }
                  if ($sort_key == 'legal_services' && $curr_status == 'DESC') {
                    echo "<img src='images/up-b.png' />";
                  }
                  ?>
                </a></th>
              <th nowrap class="text-right"><a href="bill_list.php?op=sort&curr_status=<?php echo $curr_status ?>&sort_key=disbs&total=<?php echo $result['total'] ?>&usd_total=<?php echo $result['usd_total'] ?>&legal_services=<?php echo $result['legal_services'] ?>&disbs=<?php echo $result['disbs'] ?>&case_num=<?php echo $case_num ?>&case_manager=<?php echo $case_manager ?>&comp=<?php echo $comp ?>&year=<?php echo $year ?>&month=<?php echo $month ?>">
                  Disbs
                  <?php
                  if ($sort_key == 'disbs' && $curr_status == 'ASC') {
                    echo "<img src='images/down-b.png' />";
                  }
                  if ($sort_key == 'disbs' && $curr_status == 'DESC') {
                    echo "<img src='images/up-b.png' />";
                  }
                  ?>
                </a></th>
              <th class="text-right"><a href="bill_list.php?op=sort&curr_status=<?php echo $curr_status ?>&sort_key=total&total=<?php echo $result['total'] ?>&usd_total=<?php echo $result['usd_total'] ?>&legal_services=<?php echo $result['legal_services'] ?>&disbs=<?php echo $result['disbs'] ?>&case_num=<?php echo $case_num ?>&case_manager=<?php echo $case_manager ?>&comp=<?php echo $comp ?>&year=<?php echo $year ?>&month=<?php echo $month ?>">
                  Total
                  <?php
                  if ($sort_key == 'total' && $curr_status == 'ASC') {
                    echo "<img src='images/down-b.png' />";
                  }
                  if ($sort_key == 'total' && $curr_status == 'DESC') {
                    echo "<img src='images/up-b.png' />";
                  }
                  ?>
                </a></th>
              <th nowrap class="text-right"><a href="bill_list.php?op=sort&curr_status=<?php echo $curr_status ?>&sort_key=usd_total&total=<?php echo $result['total'] ?>&usd_total=<?php echo $result['usd_total'] ?>&legal_services=<?php echo $result['legal_services'] ?>&disbs=<?php echo $result['disbs'] ?>&case_num=<?php echo $case_num ?>&case_manager=<?php echo $case_manager ?>&comp=<?php echo $comp ?>&year=<?php echo $year ?>&month=<?php echo $month ?>">
                  USD Total
                  <?php
                  if ($sort_key == 'usd_total' && $curr_status == 'ASC') {
                    echo "<img src='images/down-b.png' />";
                  }
                  if ($sort_key == 'usd_total' && $curr_status == 'DESC') {
                    echo "<img src='images/up-b.png' />";
                  }
                  ?>
                </a></th>
              <th nowrap><a href="bill_list.php?op=sort&curr_status=<?php echo $curr_status ?>&sort_key=sent&total=<?php echo $result['total'] ?>&usd_total=<?php echo $result['usd_total'] ?>&legal_services=<?php echo $result['legal_services'] ?>&disbs=<?php echo $result['disbs'] ?>&case_num=<?php echo $case_num ?>&case_manager=<?php echo $case_manager ?>&comp=<?php echo $comp ?>&year=<?php echo $year ?>&month=<?php echo $month ?>">
                  Sent
                  <?php
                  if ($sort_key == 'sent' && $curr_status == 'ASC') {
                    echo "<img src='images/down-b.png' />";
                  }
                  if ($sort_key == 'sent' && $curr_status == 'DESC') {
                    echo "<img src='images/up-b.png' />";
                  }
                  ?>
                </a></th>
              <th nowrap> Real Sent</th>
            </tr>
          </thead>

          <tbody>
            <?php
            $total_ln = 0;
            $color = 0;
            foreach ($result['result'] as $row) {
              $total_ln += 1;
              if ($color == 0) {
            ?>
                <tr>
                <?php
              } elseif ($color == 1) {
                ?>
                <tr class="th-gary">
                <?php
              }
              $color = ($color == 0) ? 1 : 0;
                ?>
                <td class="text-center"><a href="bill_detail.php?deb_num=<?php echo $row['deb_num'] ?>" class="btn-sm btn-info btn-r15" data-toggle="modal" data-target="#modal-id"><i class="glyphicon glyphicon-th-list"></i></a></td>

                <td>
                  <?php
                  if ($row['bills_case_manager'] != '') {
                    echo $row['bills_case_manager'];
                  } else {
                    echo $row['case_manager'];
                  }
                  ?>
                </td>
                <td><?php echo $row['case_num'] ?></td>
                <td><?php echo $row['deb_num'] ?></td>
                <td class="text-right">
                  <?php
                  echo number_format($row['legal_services']);
                  if ($row['show_legal_services'] > 0) {
                  ?>
                    <BR>
                    <font color="RED" size="1"> <B>
                        (<?php echo number_format($row['show_legal_services']) ?>)
                      </B></font>
                  <?php } ?>
                </td>
                <td class="text-right">
                  <?php
                  echo number_format($row['disbs']);
                  if ($row['show_disbs'] > 0) {
                  ?>
                    <BR>
                    <font color="RED" size="1"> <B>
                        (<?php number_format($row['show_disbs']) ?>)
                      </B></font>
                  <?php } ?>
                </td>
                <?php
                if ($row['discount'] > 0) {
                ?>
                  <td class="text-right"><?php echo number_format($row['total']) ?>
                    (after <?php echo number_format($row['discount'], 2) ?> % discount)</td>
                <?php
                } else {
                ?>
                  <td class="text-right"><?php echo number_format($row['total']) ?></td>
                <?php } ?>
                <td class="text-right"><?php echo number_format($row['usd_total'], 2) ?></td>
                <td><?php echo $row['sent'] ?></td>
                <td><?php echo $row['current_sent_date'] ?></td>
                </tr>
              <?php
            }
              ?>

          </tbody>
          <tfoot>
            <tr class="th-1">
              <th nowrap>Total</th>
              <th nowrap><?php echo $total_ln ?></th>
              <th nowrap></th>
              <th></th>
              <th nowrap class="text-right"><?php echo number_format($result['legal_services']) ?> </th>
              <th nowrap class="text-right"><?php echo number_format($result['disbs']) ?></th>
              <th class="text-right"><?php echo number_format($result['total']) ?></th>
              <th nowrap class="text-right"><?php echo number_format($result['usd_total'], 2) ?></th>
              <th nowrap></th>
              <th nowrap></th>
            </tr>
          </tfoot>
        </table>
      </div>
      <!-- End 清單 -->

    </div>
  </div>

  <!-- Modal -->

  <div class="modal fade" id="modal-id">
    <div class="modal-dialog modal-width">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
          <h4 class="modal-title">Debit Note</h4>
        </div>
        <div class="modal-body">
          Loading...
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-info" data-dismiss="modal">關閉</button>
        </div>
      </div>
    </div>
  </div>


  <!--End Modal -->


  <!--搜尋內容結束-->
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