<?php
### menu.php

// 檢查 Session 是否尚未啟動，只有在沒啟動時才執行 session_start
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$menu_performance = $menu_cases = $menu_bills = $menu_tr = $menu_bonus = 'dropdown';
$menu_loc = 1;
if ($menu_loc == 1) {
  $menu_performance = 'dropdown active';
} else if ($menu_loc == 2) {
  $menu_cases = 'dropdown active';
} else if ($menu_loc == 3) {
  $menu_bills = 'dropdown active';
}

$categories = [
  'performance' => ['perf', 'trans_report', 'key_metric_cases'],
  'report'      => ['ati_category_list', 'bcorp', 'retainer', 'top_client', 'check_rain', 'ppp', 'top_co', 'donot', 'probono', 'clientr', 'manager_report', 'partner_report', 'fee_earner.php', 'bad_debt', 'mve_report', 'fee_earner_partner'],
  'bonus'       => ['bonus_list', 'bonus_check', 'payment_list', 'payment_detail', 'gen_bonus_report'],
  'finance'     => ['fund', 'practice_', 'trust', 'ar_list', 'update_payments', 'payment_', 'pay_list', 'single_ar', 'receipt_sec', 'receipts', 'income_wht'],
  'tr'          => ['total_tr', 'time_rec_history', 'tr_prog', 'time_rec'],
  'case'        => ['wip_list', 'case_', 'cases_all_list'],
  'bills'       => ['bill_list', 'disb_insert', 'disb_list', 'disb_update', 'disb_counsel_list', 'disb_payment', 'ar_dev']
];

$dropdown_class = [
  'performance' => 'dropdown',
  'report'      => 'dropdown',
  'bonus'       => 'dropdown',
  'finance'     => 'dropdown',
  'tr'          => 'dropdown',
  'case'        => 'dropdown',
  'bills'       => 'dropdown'
];

$url = strtoupper($_SERVER['REQUEST_URI']);
$matched_categories = [];

foreach ($categories as $name => $keywords) {
  foreach ($keywords as $keyword) {
    $pos = strpos($url, strtoupper($keyword));
    if ($pos !== false) {
      $matched_categories[$name] = $pos;
      break;
    }
  }
}

asort($matched_categories);
$active_category = key($matched_categories);

if (isset($active_category)) {
  $dropdown_class[$active_category] = 'dropdown active';
}

?>

<nav class="navbar navbar-default" role="navigation">
  <div class="navbar-header">
    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-ex1-collapse">
      <span class="sr-only">選單切換</span>
      <span class="icon-bar"></span>
      <span class="icon-bar"></span>
      <span class="icon-bar"></span>
    </button>
    <a class="navbar-brand" href="index.php">
      <image src="image/logo.png">
    </a>
  </div>

  <!-- 手機隱藏選單區 -->
  <div class="collapse navbar-collapse navbar-ex1-collapse amanda-nav">
    <!-- 左選單 -->
    <ul class="nav navbar-nav">
      <li class="<?php echo $dropdown_class['performance'] ?>">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">Performance<b class="caret"></b></a>
        <ul class="dropdown-menu">
          <?php
          //這裡是report顯示的內容
          $performance_name_list = [
            "Performance Report" => "<li><a href='perf_simple.php'>Performance Report</a></li>",
            "Performance Year Report" => "<li><a href='perf_year.php'>Performance Year Report</a></li>",
            "Performance Report for case" => "<li><a href='perf_cases_report.php'>Performance Report for case</a></li>",
            "Performance Report for case list" => "<li><a href='perf_cases_list_detail_report.php'>Performance Report for case list</a></li>",
            "Translation Report" => "<li><a href='trans_report.php'>Translation Report</a></li>",
            "Performance Search" => "<li><a href='perf_report_search.php'>Performance Search</a></li>",
            "Case Financial Analysis" => "<li><a href='key_metric_cases.php'>Case Financial Analysis</a></li>",
            "Performance Report fo RJ" => "<li><a href='perf_simple_rj.php'>Performance Report fo RJ</a></li>",
            "Performance Report for Amazon" => "<li><a href='perf_azn_case.php'>Performance Report for Amazon</a></li>"
          ];


          $sort_name = array_keys($performance_name_list);
          usort($sort_name, function ($a, $b) {
            return strcasecmp($a, $b);
          });
          foreach ($sort_name as $name) {
            echo $performance_name_list[$name];
          }
          ?>
        </ul>
      </li>
      <li class="<?php echo $dropdown_class['case'] ?>">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">Cases<b class="caret"></b></a>
        <ul class="dropdown-menu">
          <li><a href="cases_all_list.php">All Cases List</a></li>
          <li><a href="cases_notused_list.php">Avaliable Client Code</a></li>
          <li><a href="case_managed_main.php">Case managers</a></li>
          <li><a href="case_main.php">Cases</a></li>
          <li><a href="conflict_main.php">Conflict check</a></li>
          <li><a href="" style="color: red;">Search Do not bill</a></li>
          <li><a href="" style="color: red;">代墊費輸入</a></li>
          <li><a href="wip_list.php">WIP Case List</a></li>
        </ul>
      </li>
      <li class="<?php echo $dropdown_class['bills'] ?>">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">Bills<b class="caret"></b></a>
        <ul class="dropdown-menu">
          <li><a href="ar_dev.php">Batch AR</a></li>
          <li><a href="draft_bill_list.php">Draft Bill List</a></li>
          <li><a href="bill_list.php">Sent Bills</a></li>
          <li><a href="ar_dev_total.php">Outstanding Debit Notes</a></li>
          <li><a href="disb_list.php">Disbursements</a></li>
          <li><a href="disb_insert.php">Insert Disbursements</a></li>
          <li><a href="disb_counsel_list.php">Out Counsel Disbursements</a></li>
          <li><a href="disb_payment.php">Disbursements Payments</a></li>
          <li><a href="" style="color: red;">Export file</a></li>
          <li><a href="bad_debt.php">Search bad debt</a></li>
        </ul>
      </li>
      <li class="<?php echo $dropdown_class['tr'] ?>">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">Time Records<b class="caret"></b></a>
        <ul class="dropdown-menu">
          <li><a href="time_rec.php">Time Records</a></li>
          <li><a href="time_rec_history.php">Time Record Search</a></li>
          <li><a href="total_tr.php">Total Time Record</a></li>
          <li><a href="tr_prog_list.php">TR Progress Report</a></li>
        </ul>
      </li>
      <li class="<?php echo $dropdown_class['bonus'] ?>">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">Bonus<b class="caret"></b></a>
        <ul class="dropdown-menu">
          <li><a href="bonus_list.php">Bonuses Report</a></li>
          <li><a href="bonus_check.php">Bonuses Check</a></li>
          <li><a href="payment_list.php">引案獎金</a></li>
          <li><a href="gen_bonus_report.php">行政人員 Bonuses Report</a></li>
        </ul>
      </li>
      <li class="<?php echo $dropdown_class['finance'] ?>">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">Finance<b class="caret"></b></a>
        <ul class="dropdown-menu">
          <li><a href="http://billing-dev/cgi-bin/cfph_search.pl" style="color: red;">Client & Firm</a></li>
          <li><a href="client_payment.php">Client Payment</a></li>
          <li><a href="disbursements_balance.php">Disbursements Balance</a></li>
          <li><a href="fund.php">Fund Targets</a></li>
          <li><a href="income_wht.php">Income WHT</a></li>
          <li><a href="http://billing-dev/cgi-bin/metrics.pl" style="color: orange;">Key Metrics</a></li>
          <li><a href="payment_main.php">Payment</a></li>
          <li><a href="practice_list.php">Practice Area</a></li>
          <li><a href="receipts.php">Receipts</a></li>
          <li><a href="receipts_report.php">Receipts Report</a></li>
          <li><a href="single_ar.php">Single AR</a></li>
          <li><a href="trust.php">Trust Accout List</a></li>
          <li><a href="ar_list.php">Unbilled</a></li>
          <li><a href="update_payments_bank.php">Update Payments Rate/Bank Account</a></li>
          <li><a href="update_payments_remit.php">Update Payments Remit</a></li>
          <li><a href="payment_to_income.php">Update Payments To Income</a></li>
          <li><a href="voucher.php">Voucher</a></li>
          <li><a href="receipt_sec.php">開立收據</a></li>
          <li><a href="#" onclick="window.open('http://slashlaw-dev/credit_win.php', '引案獎金清單', 'height=350,width=300');" style="color: orange;">引案獎金清單</a></li>
        </ul>
      </li>

      <li class="<?php echo $dropdown_class['report'] ?>">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">Report<b class="caret"></b></a>
        <ul class="dropdown-menu">
          <?php
          //這裡是report顯示的內容
          $report_name_list = [
            "ATI Category Report" => "<li><a href='ati_category_list.php'>ATI Category Report</a></li>",
            "B Corp Search" => "<li><a href='bcorp_list_do.php'>B Corp Search</a></li>",
            "Pro bono Search" => "<li><a href='probono_list_do.php'>Pro bono Search</a></li>",
            "Case Manager Report" => "<li><a href='manager_report.php'>Case Manager Report</a></li>",
            "Fee Earner" => "<li><a href='fee_earner.php'>Fee Earner</a></li>",
            "Fee Earner for Partner" => "<li><a href='fee_earner_partner.php'>Fee Earner for Partner</a></li>",
            "Client Credits Reports" => "<li><a href='clientr_credits_report.php'>Client Credits Reports</a></li>",
            "Partner Reports" => "<li><a href='partner_report_do.php'>Partner Reports</a></li>",
            "Do Not Bill List" => "<li><a href='donot_list.php'>Do Not Bill List</a></li>",
            "Top C/O" => "<li><a href='top_co.php'>Top C/O</a></li>",
            "Top Company" => "<li><a href='top_company_do.php'>Top Company</a></li>",
            "Top Clients" => "<li><a href='top_clients.php'>Top Clients</a></li>",
            "BMT Financial Analysis" => "<li><a href='retainer_year_do.php'>BMT Financial Analysis</a></li>",
            "Check Rainmakers Report" => "<li><a href='check_rain_list.php'>Check Rainmakers Report</a></li>",
            "MVE Report" => "<li><a href='mve_report.php'>MVE Report</a></li>",
            "BMT Report" => "<li><a href='ppp_search.php'>BMT Report</a></li>"
          ];


          $sort_name = array_keys($report_name_list);
          usort($sort_name, function ($a, $b) {
            return strcasecmp($a, $b);
          });
          foreach ($sort_name as $name) {
            echo $report_name_list[$name];
          }
          ?>
          <!-- <li><a href="ati_category_list.php">ATI Category Report</a></li>
     <li><a href="bcorp_list.php">B Corp Search</a></li>
     <li><a href="probono_list.php">Pro bono Search</a></li>
     <li><a href="manager_report.php">Case Manager Report</a></li>
     <li><a href="fee_earner.php">Fee Earner</a></li>
     <li><a href="fee_earner_partner.php">Fee Earner for Partner</a></li>
     <li><a href="clientr_credits_report.php">Client Credits Reports</a></li>
     <li><a href="partner_report.php">Partner Reports</a></li>
     <li><a href="donot_list.php">Do Not Bill</a></li>
     <li><a href="top_clients.php">Top Client</a></li>
     <li><a href="top_co.php">Top C/O</a></li>
     <li><a href="top_company.php">Top Company</a></li>
     <li><a href="retainer_year.php">BMT Financial Analysis</a></li>
     <li><a href="check_rain_list.php">Check Rainmakers Report</a></li> -->

          <!-- slashlaw-dev -->


        </ul>
      </li>

    </ul>

    <!-- 行政選單 -->
    <ul class="nav navbar-nav navbar-right">

      <!-- 總務部 Start -->
      <li class="dropdown">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">行政部<b class="caret"></b></a>
        <ul class="dropdown-menu">
          <li>
            <div class="menu-department-menutitle">秘書部</div>
            <ul class="department">
              <li><a href="http://issued/" target="_blank">發文系統</a></li>
              <li><a href="lawyer_list_edit.php">受僱律師承辦訴訟案件明細表</a></li>
              <li><a href="lawyer_list.php">受僱律師承辦訴訟案件明細查詢</a></li>
            </ul>
          </li>

          <li>
            <div class="menu-department-menutitle">財務部</div>
            <ul class="department">
              <li><a href="http://bpm/" target="_blank">BPM表單申請</a></li>
            </ul>
          </li>

          <li>
            <div class="menu-department-menutitle">總務部</div>
            <ul class="department">
              <li><a href="https://goo.gl/JSk19v" target="_blank">填寫國際電話單</a></li>
            </ul>
          </li>

          <li>
            <div class="menu-department-menutitle">綠辦</div>
            <ul class="department">
              <li><a href="http://192.168.0.75/green_report/green_report.php" target="_blank">Green Report</a></li>
            </ul>
          </li>

          <li>
            <div class="menu-department-menutitle">人事部</div>
            <ul class="department">
              <li><a href="https://cloud.smoothhr.com/security/40/login.aspx?target=/Portal/40/Default.aspx" target="_blank">打卡/請假系統</a></li>
            </ul>
          </li>

          <li>
            <div class="menu-department-menutitle">其他</div>
            <ul class="department">
              <li><a href="http://officewiki.wpoffice.com/cgi-bin/oddmuse/officewiki.pl/Roadmap_中英文版" target="_blank">Roadmap 中英文版</a></li>
              <li><a href="http://officewiki.wpoffice.com/cgi-bin/oddmuse/officewiki.pl/Roadmap_%e8%8b%b1%e6%96%87%e7%89%88" target="_blank">Roadmap English version</a></li>
              <li><a href="http://officewiki" target="_blank">OfficeWiki</a></li>
              <li><a href=""></a></li>
            </ul>
          </li>

        </ul>
      </li>


      <!-- 常用 Start -->
      <li class="dropdown">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">常用<b class="caret"></b></a>
        <ul class="dropdown-menu">
          <li><a href="">
          <li><a href="">選項一</a></li></a>
      </li>
      <li><a href="">
      <li><a href="">選項二</a></li></a></li>
    </ul>
    </li>

    <form id="logoutForm" action="logout.php" method="post" style="display:none;">
      <input type="hidden" name="csrf" value="<?php htmlspecialchars($_SESSION['csrf'], ENT_QUOTES) ?>">
    </form>

    <?PHP
    if (isset($_SESSION['initial'])) {
      echo "<li><a href='#' onclick='return confirmLogout()'><span class='glyphicon glyphicon-user' aria-hidden='true'>" . $_SESSION['initial'] . "</span></a></li>";
    } else {
      echo "<li><a href='#' onclick='return confirmLogout()'><span class='glyphicon glyphicon-user' aria-hidden='true'>???</span></a></li>";
    }
    ?>
    <script type="text/javascript">
      function confirmLogout() {
        var confirmation = confirm("確定登出嗎?");
        if (confirmation) {
          document.getElementById('logoutForm').submit(); // 用 POST 送出
        } else {
          return false;
        }
      }
    </script>
    </ul>

  </div>
  <!-- 手機隱藏選單區結束 -->
</nav>