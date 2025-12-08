#! /usr/bin/perl -w
#
# 檔案名稱：bill_draft_list.pl
# 功能說明：帳單草稿列表管理系統
# 主要功能：
#   1. 列出未寄送的帳單草稿
#   2. 更新帳單資訊（折扣、ATI 類別、OC 發票期待等）
#   3. 套用寄送日期並更新相關記錄
#   4. 處理 Retainer 相關資訊
#   5. 排序帳單列表
#
# 載入 HR 模組（人力資源相關功能）
use HR;
# 載入 BN 模組（獎金計算相關功能）
use BN;
# 載入 CGI 模組（處理網頁請求）
use CGI;
# 錯誤訊息輸出到瀏覽器
use CGI::Carp qw(fatalsToBrowser);
# 資料庫連接介面
use DBI;
# 樣板處理引擎
use Template;
# 資料傾印工具（除錯用）
use Data::Dumper;
# 高精度時間函數
use Time::HiRes qw(gettimeofday);
# 時間轉換函數
use Time::Local;
# 資料序列化儲存
use Storable;
# 啟用嚴格模式
use strict;
# 載入 UT 工具模組
use UT;

# 取得樣板物件
my $template = Template->HR::get_template();
# 建立 CGI 查詢物件
my $query = new CGI;
# 設定字元編碼為 UTF-8
$query->charset('utf8');
# 輸出 HTTP 標頭
print $query->header(-charset=>'utf8');
# 連接資料庫
my $dbh = HR::DBConnect();
# 如果資料庫連接失敗則終止程式
unless ($dbh) {
die $dbh;
}

# 取得所有表單參數
my $params  = $query->Vars;
#die Dumper $query, $params;
# 定義狀態機：根據不同的操作執行對應的函數
my %States = (
   'List Draft Bills'           => \&list_draft_bills,      # 列出草稿帳單
   'Update and Save'         => \&update_draft_bills,    # 更新並儲存帳單
   'Apply Sent Date'                => \&update_draft_bills,    # 套用寄送日期
   'sort_bills'                 => \&sort_bills,         # 排序帳單
           );

# 取得使用者提交的操作類型
my $page = $params->{'bill_list_submit'};
# 根據操作類型執行對應的函數
if($States{$page}){
    $States{$page}->($query, $params, $dbh); 
}else {
    # 預設顯示草稿帳單列表
    $States{'List Draft Bills'}->($query, $params, $dbh);
}
# 中斷資料庫連接
$dbh->disconnect;



# 函數：列出草稿帳單
sub list_draft_bills {
  my $query = shift;      # CGI 查詢物件
  my $params = shift;     # 表單參數
  my $dbh = shift;        # 資料庫連接
  my $case_manager= $params->{case_manager};  # 案件經理
  my $case_num= $params->{case_num};          # 案件編號
  my $sql_case_manager='';  # SQL 條件：案件經理
  my $sql_case_num='';      # SQL 條件：案件編號
  my $ati_show_status='posthidden';  # ATI 顯示狀態預設隱藏


### 20220709 讀取 IP 位址權限表
  my %ip_addr;  # 儲存有權限的 IP 位址
  my $sql = qq{SELECT * FROM  ip_addr WHERE ( permission & 1 ) = 1
               ORDER BY ip_addr };
  my $sth = $dbh->prepare($sql);
  my $rv = $sth->execute;
  # 將有權限的 IP 位址存入雜湊表
  while (my $result = $sth->fetchrow_hashref) {
    my $temp = $result->{ip_addr};
    $ip_addr{$temp} =1; 
  }


  # 如果有指定案件經理，建立 SQL 篩選條件
  if ($case_manager ne '')
  {
     my @temp = split (/,/,$case_manager);  # 用逗號分割多個案件經理
     for (my $i=0;$i <=$#temp;$i++)
     {
         $temp[$i]=uc($temp[$i]);  # 轉換為大寫
#         if ( $temp[$i] eq 'MD' || $temp[$i] eq 'PD' || $temp[$i] eq 'SE' ) {
#           $ati_show_status='postshowntd'; 
#         }
         # 建立 OR 條件組合
         if ($sql_case_manager ne '')
         { 
#           $sql_case_manager .=" OR cases.case_manager='$temp[$i]' ";
           $sql_case_manager .=" OR bills.bills_case_manager='$temp[$i]' ";
         } else { 
#           $sql_case_manager =" cases.case_manager='$temp[$i]' ";
           $sql_case_manager = " bills.bills_case_manager='$temp[$i]' ";
         }
     }
     $sql_case_manager =' AND ('. $sql_case_manager .')';
     
  } 
  # 如果有指定案件編號，建立 SQL 篩選條件
  if  ($case_num ne '') 
  {
     my @temp = split (/,/,$case_num);  # 用逗號分割多個案件編號
     for (my $i=0;$i <=$#temp;$i++)
     {
         $temp[$i]=uc($temp[$i]);  # 轉換為大寫
         # 建立 LIKE 條件（模糊查詢）
         if ($sql_case_num ne '')
         {
           $sql_case_num .=" OR cases.case_num like '$temp[$i]%' ";
         } else {
           $sql_case_num = " cases.case_num like '$temp[$i]%' ";
         }
     }
     $sql_case_num =' AND ('. $sql_case_num .')';

#     $case_num=uc($case_num);
#     $sql_case_num =" AND cases.case_num like '$case_num%' ";
  }
#  my $sql = qq{SELECT bills.*,cases.case_manager,cases.case_num,cases.billing_note,retainer_num,pppoc_status,retainer_num,party_en_name_billing,cases.billing_currency, ( CASE WHEN draft_created >='2022-11-26' THEN 1 
  # 主要 SQL 查詢：取得草稿帳單資料，併同案件資訊
  my $sql = qq{SELECT bills.*,cases.case_manager,cases.case_num,cases.billing_note,retainer_num,pppoc_status,retainer_case_num,retainer_foreign,retainer_foreign_currency,retainer_ntd,party_en_name_billing, ( CASE WHEN draft_created >='2022-11-26' THEN 1 
                                             WHEN draft_created <'2022-11-26' THEN 0
                                             END  ) AS currency_flag,0 AS show_oc ,0 AS show_ati 
               FROM  bills left join cases on bills.case_num=cases.case_num
               WHERE (deb_num  LIKE 'A2006%' 
                     OR deb_num LIKE 'A2007%'
                     OR deb_num LIKE 'A2008%' 
                     OR deb_num LIKE 'A2009%' 
                     OR deb_num LIKE 'A201%' 
                     OR deb_num LIKE 'A202%') 
               AND sent IS NULL  
               AND bill_status = 0            
               $sql_case_manager 
               $sql_case_num 
               ORDER BY CASE WHEN cases.billing_currency='English (USD)' THEN 2 WHEN cases.billing_currency='English (EUR)' THEN 3 ELSE 1 END, bills.case_num};

###               ORDER BY currency_flag,CASE WHEN bills.billing_currency='English (USD)' THEN 2 ELSE 1 END,bills.case_num  };
###               ORDER BY currency_flag,CASE WHEN cases.billing_currency='English (USD)' THEN 2 ELSE 1 END,bills.case_num };
###               ORDER BY CASE WHEN cases.billing_currency='English (USD)' THEN 2 WHEN cases.billing_currency='English (EUR)' THEN 3 ELSE 1 END, bills.case_num};
#die Dumper $sql;
#####AND sent IS NULL AND (legal_services > 0 OR disbs >0) 
#####AND total > 100            
###               AND total >= 0            


  # 執行 SQL 查詢
  my $sth = $dbh->prepare($sql);
  my $rv = $sth->execute;
  my $list;
  my @record;  # 儲存查詢結果的陣列
  my $count = 0;  # 記錄計數
  my $total_count = 0;  # 總記錄數
  my $total_count_twd = 0;  # 台幣記錄數
  my $total_count_usd = 0;  # 美元記錄數
  my $total_count_eur = 0;  # 歐元記錄數
  my $total_legal=0;  # 總法律服務費
  my $total_disbursement=0;  # 總支出費用
  my $total_ntd=0;  # 總台幣金額
  my $total_legal_twd=0;  # 台幣法律服務費
  my $total_disbursement_twd=0;  # 台幣支出費用
  my $total_twd=0;  # 台幣總計
  my $total_legal_usd=0;  # 美元法律服務費
  my $total_disbursement_usd=0;  # 美元支出費用
  my $total_usd=0;  # 美元總計
  my $total_legal_eur=0;  # 歐元法律服務費
  my $total_disbursement_eur=0;  # 歐元支出費用
  my $total_eur=0;  # 歐元總計
 
  # 迴圈處理每筆帳單資料
  while (my $result = $sth->fetchrow_hashref) {
    # 根據計費幣別累計不同的總計
    if ( $result->{billing_currency} eq  'English (USD)'  ) {
      # 美元帳單
      $total_legal_usd += $result->{foreign_legal2};
      $total_disbursement_usd += $result->{foreign_disbs2};
      $total_usd += $result->{foreign_total2};
      $total_count_usd++;
    } elsif ( $result->{billing_currency} eq  'English (EUR)'  ) {
      # 歐元帳單
      $total_legal_eur += $result->{foreign_legal2};
      $total_disbursement_eur += $result->{foreign_disbs2};
      $total_eur += $result->{foreign_total2};
      $total_count_eur++;
    } else {
      # 台幣帳單
      $total_legal_twd += $result->{legal_services};
      $total_disbursement_twd += $result->{disbs};
      $total_twd += $result->{total}; 
      $total_count_twd++;
    }
    $total_count++;
    $total_legal += $result->{legal_services};
    $total_disbursement += $result->{disbs};
    
    $total_ntd += $result->{total}; 
    # 檢查是否為特殊客戶（PPP, TDG, BMT）
    if ( $result->{retainer_num} eq 'PPP' || $result->{retainer_num} eq 'TDG'  || $result->{retainer_num} eq 'BMT' ) {
      $ati_show_status='postshowntd'; 
      $result->{show_oc}=1;   # 顯示 OC 欄位
      $result->{show_ati}=1;  # 顯示 ATI 欄位
    }
    # 檢查 GIM/GNT 案件且經理為 KA 的特殊狀況
    if ( ( substr($result->{case_num},0,3) eq 'GIM' || substr($result->{case_num},0,3) eq 'GNT' ) && $result->{bills_case_manager} eq 'KA' ) {
      $ati_show_status='postshowntd'; 
      $result->{show_oc}=1;
      $result->{show_ati}=1;
    }
    # 檢查是否為 VY 或 KA 經理的案件
    if ( $result->{bills_case_manager} eq 'VY' || $result->{bills_case_manager} eq 'KA' ) {
      $ati_show_status='postshowntd'; 
      $result->{show_oc}=1;
    }

#   my $sql = qq {SELECT case_manager FROM cases WHERE case_num = '$result->{case_num}'};
#   my $sth = $dbh->prepare( $sql);
#   my $rv = $sth->execute;
#   my $case = $sth->fetchrow_hashref;
#####   if (($case->{case_manager} eq 'CL') 
#####    and
#####       ($result->{legal_services} == 0)
#####    and ($result->{disbs} < 1000)
#####){next};
   $count++;  # 記錄數加一
    my $even_status = HR::even($count);  # 判斷奇偶數（用於列表顏色）
    $result->{total} = HR::commify($result->{total});  # 格式化總金額（加上千位分隔符）
   
### 取得支出費用中計為法律服務費的部分
    my $sql = qq[SELECT SUM (ntd_amount) as show_sum  FROM
               disbursements
               WHERE billed_flag = 0
               AND show_flag = 1
               AND show_as_legal_service_flag = 1
               AND nocharge_flag = -1
               AND deb_num = '$result->{deb_num}'];
  

   my $sth = $dbh->prepare ($sql);
   $sth->execute;
   my $disb_rec = $sth->fetchrow_hashref;
   # 如果有需要顯示為法律服務費的支出
   if  ($disb_rec->{show_sum}) {
       $result->{show_legal_services} = $result->{legal_services} + $disb_rec->{show_sum};
       $result->{show_disbs} = $result->{disbs} -  $disb_rec->{show_sum};
   }
### 外幣支出費用中計為法律服務費的部分
   my $sql = qq[SELECT SUM (foreign_amount2) as show_foreign_sum  FROM
               disbursements
               WHERE billed_flag = 0
               AND show_flag = 1
               AND show_as_legal_service_flag = 1
               AND nocharge_flag = -1
               AND deb_num = '$result->{deb_num}'];
  

   my $sth = $dbh->prepare ($sql);
   $sth->execute;
   my $disb_rec = $sth->fetchrow_hashref;
   # 如果有外幣金額需要顯示為法律服務費
   if  ($disb_rec->{show_foreign_sum} >0 ) {
       $result->{show_foreign_legal_services} = $result->{foreign_legal2} + $disb_rec->{show_foreign_sum};
       $result->{show_foreign_disbs} = $result->{foreign_disbs2} -  $disb_rec->{show_foreign_sum};
   }



### 檢查是否有 OC 發票期待
   my $sql = qq[SELECT * FROM tr 
                WHERE invoice_exp_status = '1' AND deb_num='$result->{deb_num}'];

   my $sth = $dbh->prepare ($sql);
   $sth->execute;
   my $display_oc_status='';  # OC 顯示狀態
   # 如果有記錄，設定 OC 發票期待訊息
   if ( $sth->rows >=1) {
     $display_oc_status='**OC Invoice Expected';
   }
  
#		    foreign_disbs2     => HR::commify($result->{foreign_disbs2}),
#		    show_foreign_disbs => HR::commify($result->{show_foreign_disbs}),
#		    foreign_legal2     => HR::commify($result->{foreign_legal2}),
#		    show_foreign_legal_services => HR::commify($result->{show_foreign_legal_services}),
#		    foreign_total2     => $result->{foreign_total2},
#		    currency2          => $result->{currency2},
### 20230821 新增讀取 retainer_foreign_currency, retainer_foreign, retainer_ntd
   my ($retainer_foreign,$retainer_foreign_currency,$retainer_ntd);
   # 如果有 retainer 相關資料
   if ( $result->{retainer_case_num} ne '' || $result->{retainer_ntd} >0 || $result->{retainer_foreign} >0 ) {
#     $retainer_foreign = HR::commify($result->{retainer_foreign},2);
#     $retainer_foreign_currency = $result->{retainer_foreign_currency};
#     $retainer_ntd = HR::commify($result->{retainer_ntd});
      my $case_num_temp = $result->{retainer_case_num};
      if ( $case_num_temp eq '' ) {
        $case_num_temp = $result->{case_num};
      }
     # 從 cases 表讀取 retainer 資訊
     my $sql = qq[SELECT * FROM cases WHERE case_num='$case_num_temp']; 
     my $sth = $dbh->prepare ($sql);
     $sth->execute;
     my $case_rec = $sth->fetchrow_hashref;
     $retainer_foreign = HR::commify($case_rec->{retainer_foreign},2);
     $retainer_foreign_currency = $case_rec->{retainer_foreign_currency};
     $retainer_ntd = HR::commify($case_rec->{retainer_ntd});
   }


     # 將該筆記錄推送到陣列
     push @record, { id   	       => $result->{id},
		    draft_created      => $result->{draft_created},
    		    case_num 	       => $result->{case_num},
		    deb_num	       => $result->{deb_num},
    		    sent 	       => $result->{sent},
		    disbs              => HR::commify($result->{disbs}),
		    show_disbs         => HR::commify( $result->{show_disbs}),
		    legal_services     => HR::commify($result->{legal_services}),
		    show_legal_service => HR::commify($result->{show_legal_services}),
		    total              => $result->{total},
                    discount         => $result->{discount},
		    case_manager     => $result->{case_manager},
		    billing_note     => $result->{billing_note},
		    billing_person_email     => $result->{billing_person_email},
		    ati_cate1          => $result->{ati_cate1},
		    ati_cate2          => $result->{ati_cate2},
		    retainer_num       => $result->{retainer_num},
		    retainer_case_num  => $result->{retainer_case_num},
		    retainer_foreign  => $retainer_foreign,
		    retainer_foreign_currency  => $retainer_foreign_currency,
		    retainer_ntd  => $retainer_ntd,
		    even             => $even_status,
		    display_oc_status => $display_oc_status,
		    pppoc_status => $result->{pppoc_status},
		    ati_cate12     => $result->{ati_cate12},
		    ati_cate22     => $result->{ati_cate22},
		    ati_cate13     => $result->{ati_cate13},
		    ati_cate23     => $result->{ati_cate23},
		    ati_type     => $result->{ati_type},
		    ati_type2     => $result->{ati_type2},
		    ati_type3     => $result->{ati_type3},
		    new_matter     => $result->{new_matter},
		    new_matter2    => $result->{new_matter2},
		    new_matter3    => $result->{new_matter3},
		    class_count    => $result->{class_count},
		    project_owner    => $result->{project_owner},
		    party_en_name_billing    => $result->{party_en_name_billing},
		    azn_budget_status    => $result->{azn_budget_status},
                    billing_currency    => $result->{billing_currency},
                    foreign_disbs2     => HR::commify($result->{foreign_disbs2},1,2),
                    show_foreign_disbs => HR::commify($result->{show_foreign_disbs},1,2),
                    foreign_legal2     => HR::commify($result->{foreign_legal2},1,2),
                    show_foreign_legal_services => HR::commify($result->{show_foreign_legal_services},1,2),
                    foreign_total2     => $result->{foreign_total2},
                    currency2          => $result->{currency2},
                    currency_flag          => $result->{currency_flag},
                    show_oc          => $result->{show_oc},
                    show_ati          => $result->{show_ati},
                    retainer_amount          => $result->{retainer_amount},
		  };
  }#while
#                   case_manager     => $case->{case_manager},
  my $output;
  my $today = UT::get_todays_date;  # 取得今天日期
  my $time = gettimeofday;  # 取得高精度時間戳
  # 查詢 2003 年帳單總計
  my $sql = qq{SELECT SUM (total)FROM  bills 
               WHERE deb_num ~ '^A2003'
               AND sent IS NULL  
               };
   
# $sql  =  UT::format_sql ($sql);

#die Dumper @record; 

  $sth = $dbh->prepare($sql);
  $rv = $sth->execute;
my  $sum =  $sth->fetchrow_hashref;
my $total =  $sum->{sum};

  my $addr = $ENV{'REMOTE_ADDR'};  # 取得使用者 IP 位址
  # 準備樣板變數
  my $vars = {
	      result  => \@record, 
              today   => $today,
              time_cache     => $time,
              total       =>  HR::commify($total),
              in_manager       => $case_manager,
              in_case_num_dir  => $case_num,
              total_count => $total_count ,
              total_legal => HR::commify($total_legal),
              total_disbursement => HR::commify($total_disbursement),
              total_ntd => HR::commify($total_ntd),
              total_count_twd => $total_count_twd ,
              total_legal_twd   => HR::commify($total_legal_twd),
              total_disbursement_twd => HR::commify($total_disbursement_twd),
              total_twd => HR::commify($total_twd),
              total_count_usd => $total_count_usd ,
              total_legal_usd   => HR::commify($total_legal_usd,1,2),
              total_disbursement_usd => HR::commify($total_disbursement_usd,1,2),
              total_usd => HR::commify($total_usd,1,2),
              total_count_eur => $total_count_eur ,
              total_legal_eur   => HR::commify($total_legal_eur,1,2),
              total_disbursement_eur => HR::commify($total_disbursement_eur,1,2),
              total_eur => HR::commify($total_eur,1,2),
              addr	=> $addr, 
              ip_addr	=> \%ip_addr, 
              ati_show_status    => $ati_show_status,
	     };
  my $cache_file = "/var/www/billing/qicom_cache/" . $time;  # 快取檔案路徑
  store($vars, $cache_file);  # 儲存變數到快取檔案
# die Dumper $vars; 

  # 處理樣板並輸出
  $template->process('bill_draft_list.html',$vars)
       || die "Template process failed: ", $template->error(), "\n";


}# sub


# 函數：更新草稿帳單
sub update_draft_bills {
  my $query = shift;    # CGI 查詢物件
  my $params = shift;   # 表單參數
  my $dbh = shift;      # 資料庫連接

  my $in_manager=  $params->{in_manager};          # 經理名稱（用於返回）
  my $in_case_num_dir=  $params->{in_case_num_dir};  # 案件編號（用於返回）
  my $retainer_num=  $params->{retainer_num};      # Retainer 編號
  # 迴圈處理所有參數
  for my $key (keys %$params) {
    
    # 如果是勾選框參數（apply_ckBx）
    if ($key =~ /apply_ckBx/) {
  
      if ($params->{$key} eq 'yes') {
	my @param_pair = split (/ /,$key);
        my $id = $param_pair[1];  # 取得帳單 ID
	unless ($id) {die};
### 檢查 rainmakers 表中 share 值總和是否為 100
        my $sql =qq [ SELECT sum(share) AS total FROM bills LEFT JOIN rainmakers ON ( bills.case_num = rainmakers.case_num) WHERE bills.id='$id' ];  
        my $sth = $dbh->prepare($sql);
        my $rv =  $sth->execute;
        my $bills = $sth->fetchrow_hashref;
        # 如果 share 總和不等於 100，顯示錯誤
        if ( $bills->{total} !=100 ) {
          my $sql = qq [SELECT * FROM bills WHERE id = '$id'];
          my $sth = $dbh->prepare($sql);
          $sth->execute;
          my $bill = $sth->fetchrow_hashref;
          my $deb_num = $bill->{deb_num};
          die "Error! Please check '$deb_num' share value of client credit. " 
        }
#        die 'Error! Please check share value of client credit. ' if ( $bills->{total} !=100 );
        update_bills ($id, $params,$dbh);  # 執行更新
      }
    }
  }
 
  # 顯示完成訊息並提供返回連結
  print qq[The draft bills were updated. Back to <a href="../cgi-bin/bill_draft_list.pl?case_manager=$in_manager&case_num=$in_case_num_dir">Draft Bill List</a>.];
  
}


# 函數：更新帳單
sub update_bills {
   my $id = shift;       # 帳單 ID
   my $params = shift;   # 表單參數
   my $dbh = shift;      # 資料庫連接

#   my $sent_date = $params->{sent_date};
   my $discount = $params->{discount};  # 折扣
   my $invoice_exp_status = $params->{invoice_exp_status};  # OC 發票期待狀態
   my $pppoc_cancel = $params->{pppoc_cancel};  # 取消 PPP OC
   my $pppoc_add = $params->{pppoc_add};        # 新增 PPP OC
   my $ra = 'retainer_amount_'.$id;
   my $retainer_amount = $params->{$ra};  # Retainer 金額

   # 從資料庫取得帳單資料
   my $sql = qq [SELECT * FROM bills WHERE id = '$id'];
   my $sth = $dbh->prepare($sql);
   $sth->execute;
   my $bill = $sth->fetchrow_hashref;
   my $deb_num = $bill->{deb_num};          # 帳單編號
   my $case_num = $bill->{case_num};        # 案件編號
   my $legal_services = $bill->{legal_services};  # 法律服務費
   $sth->finish;

   # 如果不是「套用寄送日期」操作
   if ($params->{bill_list_submit} ne 'Apply Sent Date') {
### 新增 OC 發票期待
     my $oc_invoice='invoice_exp_status_'.$id;   
     # 如果要設定 OC 發票期待
     if ($invoice_exp_status ==1 || $params->{$oc_invoice} ==1 ) { 
       $sql = qq{UPDATE tr 
                 SET invoice_exp_status = '1'
                 WHERE id IN (SELECT id FROM tr WHERE deb_num='$deb_num' ORDER BY id limit 1)};
       my $sth = $dbh->prepare($sql);
       my $rv =  $sth->execute;
#   unless ($rv == 1) {die "Something went wrong with 
#                           the update: $rv rows were affected"}
     # 如果要取消 OC 發票期待
     } elsif ($invoice_exp_status == 2 || $params->{$oc_invoice} ==2 ) { 
       $sql = qq{UPDATE tr 
                 SET invoice_exp_status = '0'
                 WHERE deb_num='$deb_num' };
       my $sth = $dbh->prepare($sql);
       $sth->execute || {die "Something went wrong with the update fail"};
     }


### 新增 ATI 類別 => 開始
     my $ati1='ati_cate1_'.$id;   
     my $ati2='ati_cate2_'.$id;   
     my $ati12='ati_cate12_'.$id;   
     my $ati22='ati_cate22_'.$id;   
     my $ati13='ati_cate13_'.$id;   
     my $ati23='ati_cate23_'.$id;   
#   my $ati_type='ati_type_'.$id;   
#   my $ati_type2='ati_type2_'.$id;   
#   my $ati_type3='ati_type3_'.$id;   
     my $new_matter='new_matter_'.$id;   
     my $new_matter2='new_matter2_'.$id;   
     my $new_matter3='new_matter3_'.$id;   
     my $azn_budget_status='azn_budget_status_'.$id;   
     my $project_owner='project_owner_'.$id;   
     my $class_count='class_count_'.$id;   
     # 設定 new_matter 預設值
     $params->{$new_matter} =0 if ( $params->{$new_matter} != 1) ; 
     $params->{$new_matter2} =0 if ( $params->{$new_matter2} != 1) ; 
     $params->{$new_matter3} =0 if ( $params->{$new_matter3} != 1) ; 
     $params->{$class_count} = int $params->{$class_count};  # 轉換為整數

     # 驗證 class_count 是否為非負數
     unless ( $params->{$class_count} >=0) {
       $params->{$class_count} =0; 
     } 
#             ati_type = '$params->{$ati_type}',
#             ati_type2 = '$params->{$ati_type2}',
#             ati_type3 = '$params->{$ati_type3}',
     # 更新 bills 表的 ATI 相關欄位
     $sql = qq{UPDATE bills 
               SET ati_cate1 = '$params->{$ati1}',
               ati_cate2 = '$params->{$ati2}',
               ati_cate12 = '$params->{$ati12}',
               ati_cate22 = '$params->{$ati22}',
               ati_cate13 = '$params->{$ati13}',
               ati_cate23 = '$params->{$ati23}',
               new_matter = '$params->{$new_matter}',
               new_matter2 = '$params->{$new_matter2}',
               new_matter3 = '$params->{$new_matter3}',
               project_owner = '$params->{$project_owner}',
               class_count = '$params->{$class_count}',
               azn_budget_status = '$params->{$azn_budget_status}'
               WHERE id = $id};
     my $sth = $dbh->prepare($sql);
     my $rv =  $sth->execute;
     unless ($rv == 1) {die "Something went wrong with 
                           the update: $rv rows were affected"}
### 新增 PPP OC  => 開始
     # 如果要新增 PPP OC
     if ($pppoc_add ==1 ) { 
       $sql = qq{UPDATE cases 
                 SET pppoc_status = '1'
                 WHERE case_num='$case_num'};
       my $sth = $dbh->prepare($sql);
       my $rv =  $sth->execute;
       $sth->execute || {die "Something went wrong with the update cases table fail"};
     }
     # 如果要取消 PPP OC
     if ($pppoc_cancel ==1 ) { 
       $sql = qq{UPDATE cases 
                 SET pppoc_status = '0'
                 WHERE case_num='$case_num'};
       my $sth = $dbh->prepare($sql);
       my $rv =  $sth->execute;
       $sth->execute || {die "Something went wrong with the update cases table fail"};
     }

### 新增 PPP OC => 結束

   }
   
   # 如果折扣為空，設為 undefined
   if ($discount eq '') {
     undef($discount);
   }
#   if (($sent_date) and (defined($discount))) {
#     $sql = qq{UPDATE bills 
#               SET sent = '$params->{sent_date}',
#                           discount =  $params->{discount}
#                           WHERE id = $id};
#     my $sth = $dbh->prepare($sql);
#     my $rv =  $sth->execute;
#     unless ($rv == 1) {die "Something went wrong with 
#                             the update: $rv rows were affected"}
# 
#     $sql = qq{UPDATE bills 
#               SET original_legal_services='$bill->{legal_services}'
#               WHERE id = $id};
##   BN::calc_bonus($dbh,$deb_num);
#     my $sth = $dbh->prepare($sql);
#     my $rv =  $sth->execute;
#     unless ($rv == 1) {die "Something went wrong with 
#                             the update: $rv rows were affected"}

#   if ($params->{bill_list_submit} ne 'Apply Sent Date') {
### 
     # 如果有定義折扣，執行更新
     if (defined($discount)) {
       $sql = qq{UPDATE bills 
                 SET discount = $params->{discount}
                 WHERE id = $id};
       my $sth = $dbh->prepare($sql);
       my $rv =  $sth->execute;
       unless ($rv == 1) {die "Something went wrong with 
                             the update: $rv rows were affected"}
     }
     # 如果 retainer 金額大於等於 0 且有定義，更新資料庫
     if ($retainer_amount >=0 && defined($retainer_amount) ) {
       $sql = qq{UPDATE bills 
                 SET retainer_amount = '$retainer_amount'
                 WHERE id = $id};
       my $sth = $dbh->prepare($sql);
       my $rv =  $sth->execute;
       unless ($rv == 1) {die "Update retainer_amount,Something went wrong with 
                             the update: $rv rows were affected"}
       
     }
    
#   }

#   if ($params->{sent_date}) {
   # 如果是「套用寄送日期」或本次更新有填寄送日期
   if ($params->{bill_list_submit} eq 'Apply Sent Date' || ( $params->{bill_list_submit} eq 'Update Draft Bills' && $params->{sent_date} ne '' )  ) {

### 檢查法律服務費 >0 且 TR 記錄數 >0
     # 如果法律服務費大於 0
     if ( $legal_services >0 ) {
       my $sql = qq [ SELECT * FROM tr WHERE  deb_num='$deb_num' AND nocharge_flag ='-1' ];
       my $sth = $dbh->prepare($sql);
       $sth->execute;
       my $rows = $sth->rows;
       # 如果沒有時間記錄，顯示錯誤訊息並返回
       if ( $rows == 0 ) {
         print "<font color='red'>  $deb_num Legal Service >0. But Time record is empty </font><BR>";
         return;
       }
     }

### get cases table retainer_num
     my $sql = qq [SELECT cases.retainer_num FROM bills LEFT JOIN cases ON (bills.case_num=cases.case_num ) WHERE bills.id = '$id'];
     my $sth = $dbh->prepare($sql);
     $sth->execute;
     my $cases_data2 = $sth->fetchrow_hashref;
     $sth->finish;
     my $bills_retainer_num=$cases_data2->{retainer_num}; 
        
### 20230427 SQL change to here 
     my $sql = qq [SELECT * FROM cases WHERE case_num = '$case_num'];
     my $sth = $dbh->prepare($sql);
     $sth->execute;
     my $cases_data = $sth->fetchrow_hashref;

### updae bills table data
     if ( $params->{sent_date} eq'' ) {
       $params->{sent_date} =  UT::get_todays_date;
     }

### 20230728 yourref add '
     $cases_data->{party_en_name_billing} =~ s/'/''/g;


     $sql = qq{UPDATE bills 
               SET sent = '$params->{sent_date}',original_legal_services='$bill->{legal_services}',bills_retainer_num='$bills_retainer_num',party_en_name_bills='$cases_data->{party_en_name_billing}'
               WHERE id = $id};
# BN::calc_bonus($dbh, $deb_num);
     my $sth = $dbh->prepare($sql);
     my $rv =  $sth->execute;
     unless ($rv == 1) {die "Something went wrong with 
                            the update: $rv rows were affected"}

### Insert bills_current_sent table data
     my $addr = $ENV{'REMOTE_ADDR'};
     $sql = qq{INSERT INTO bills_current_sent(deb_num,sent_ip_address) values('$bill->{deb_num}','$addr' )};
     my $sth = $dbh->prepare($sql);
     my $rv =  $sth->execute;
     unless ($rv == 1) {die "Insert bills_current_sent table fail: $sql $rv rows were affected"; }

### 20230820 add insert data to send_email table
#     if ( $cases_data ->{retainer_foreign} >0 || $cases_data ->{retainer_ntd} >0 || $cases_data ->{retainer_case_num} ne ''   ) {
     if ( $retainer_amount >0 ) {
       $sql = qq{INSERT INTO send_email(case_num,deb_num) values('$bill->{case_num}','$bill->{deb_num}' )};
       my $sth = $dbh->prepare($sql);
       my $rv =  $sth->execute;
       unless ($rv == 1) {die "Insert send_email table fail: $sql $rv rows were affected"; }
     }

### Select bills table data
     my $sql = qq [SELECT bills.*,cases.retainer_num FROM bills LEFT JOIN cases ON (bills.case_num=cases.case_num ) WHERE bills.id = '$id'];
     my $sth = $dbh->prepare($sql);
     $sth->execute;
     my $bills = $sth->fetchrow_hashref;
     $sth->finish;
   
     my @tmp = split($bills->{sent},'-');
     my ($retainer_year,$tmp_month) = split('-',$bills->{sent});
     $tmp_month =~ s/^0//;
     my $retainer_quarter = int($tmp_month/3+0.9);

     my $retainer_num = $bills->{retainer_num};
### Write retainer_his and retainer_total table 
     update_retainer ($retainer_year,$retainer_quarter,$retainer_num,$bills,$dbh);

### Insert rainmakers data to rainmakers_his
     my $sql="SELECT bills.case_num,deb_num,sent,initials,share,rain_type  FROM bills LEFT JOIN rainmakers ON ( bills.case_num=rainmakers.case_num )  WHERE deb_num='$deb_num' ORDER BY sent,bills.case_num,rain_type,rainmakers.id";

     my $sth=$dbh->prepare($sql);
     $sth->execute;

     while ( my $tr = $sth->fetchrow_hashref ) {
       my $sql2;
       if ( $tr->{rain_type} ne '') {
         $sql2="SELECT * FROM rainmakers_his WHERE rainmakers_his_deb_num='$tr->{deb_num}' AND rainmakers_his_initials='$tr->{initials}' AND  rainmakers_his_rain_type='$tr->{rain_type}'  ";
       } else {
         $sql2="SELECT * FROM rainmakers_his WHERE rainmakers_his_deb_num='$tr->{deb_num}' AND rainmakers_his_initials='$tr->{initials}' AND ( rainmakers_his_rain_type='$tr->{rain_type}' OR rainmakers_his_rain_type IS NULL  ) ";
       }
       my $sth2=$dbh->prepare($sql2);
       $sth2->execute;

       my $tmp=$sth2->rows ;
       if ( $sth2->rows ==0) {
         my $sql3="INSERT INTO rainmakers_his(rainmakers_his_deb_num,rainmakers_his_initials,rainmakers_his_share,rainmakers_his_rain_type) VALUES ('$tr->{deb_num}','$tr->{initials}','$tr->{share}','$tr->{rain_type}') ";
#         print "$sql3\n";
         my $sth3=$dbh->prepare($sql3);
         $sth3->execute;
       }
     }

### 2022090 add party_en_name_bills data to cleint_firm table
#     my $client='';
#     my $firm='';
#     my $client_status=0;
#     my $firm_status=0;
#     my (%language_c,%currency_c,%language_f,%currency_f);


#     if ( $bill->{party_en_name_bills} ne '') {
#       if ( $bill->{party_en_name_bills} =~ /c\/o/i ) {
#         my @temp = split(/c\/o/i, $bill->{party_en_name_bills});
#         $client= trim($temp[0]);
#         $firm= trim($temp[1]);
#         $client_status=1;
#         $firm_status=1;
#       } else {
#         my $party_en_name_cases = $cases_data->{party_en_name};
#         my $case_client='';
#         my $case_firm='';
#         if ( $party_en_name_cases =~ /c\/o/i ) {
#           my @temp = split(/c\/o/i, $party_en_name_cases);
#           $case_client= trim($temp[0]);
#           $case_firm= trim($temp[1]);
#           if ( trim($bill->{party_en_name_bills}) =~ /$case_firm/i ) {
#             $firm_status=1;
#             $firm = $case_firm;
#           } else {
#             if ( defined $bill->{party_en_name_bills} && $bill->{party_en_name_bills} ne ''  ) {
#               $client= trim($bill->{party_en_name_bills});
#             } else {
#               $client= $case_client;
#             }
#             $client_status=1;
#           }
#         } else {
#           my $temp_party_en_name_bills = trim($bill->{party_en_name_bills});
#           if ( defined $temp_party_en_name_bills && $temp_party_en_name_bills ne '') {
#             $client= trim($bill->{party_en_name_bills});
#           } else {
#             $client= $cases_data->{party_en_name}
#           }
#           $client_status=1;
#         }
#       }
#     }

#     if ( $client_status ==1 ) {
#       my $cf_id; 
#       if ( $cases_data->{billing_currency} eq 'English (USD/TWD)-英文帳戶' ){
#         $language_c{$client} = 'English';
#         $currency_c{$client} = 'TWD/USD';
#       } elsif ( $cases_data->{billing_currency} eq 'English (USD/TWD)-雙語帳戶' )  {
#         $language_c{$client} = 'English';
#         $currency_c{$client} = 'TWD/USD';
#       } elsif ( $cases_data->{billing_currency} eq 'Chinese (TWD)' )  {
#         $language_c{$client} = 'Chinese';
#         $currency_c{$client} = 'TWD';
#       } elsif ( $cases_data->{billing_currency} eq 'English (USD)' )  {
#         $language_c{$client} = 'English';
#         $currency_c{$client} = 'USD';
#       } elsif ( $cases_data->{billing_currency} eq 'English (TWD)' )  {
#         $language_c{$client} = 'English';
#         $currency_c{$client} = 'TWD';
#       } elsif ( $cases_data->{billing_currency} eq 'English (EUR)' )  {
#         $language_c{$client} = 'English';
#         $currency_c{$client} = 'EUR';
#       } else {
#         if ( $language_c{$client} eq '' ) {
#           $language_c{$client} = '';
#           $currency_c{$client} = '';
#         }
#       }
       
#       $sql = "SELECT cf_id FROM client_firm WHERE party_name='$client' ";
#       $sth=$dbh->prepare($sql);
#       $sth->execute;
#       if ( $sth->rows ==0) {
#         $sql = "SELECT cf_id FROM client_firm WHERE cf_id like 'C%' ORDER BY cf_id DESC limit 1 ";
#         $sth=$dbh->prepare($sql);
#         $sth->execute;
#         if (my $data = $sth->fetchrow_hashref ) {
#           $cf_id = $data->{cf_id};
#           $cf_id =~ s/C//i;
#           $cf_id +=1;
#           $cf_id  = sprintf "C%06d",$cf_id ;
#         }
#         $sql=" INSERT INTO client_firm(cf_id,party_name,display_language,display_currency) values('$cf_id','$client','$language_c{$client}','$currency_c{$client}') ";
#         $sth=$dbh->prepare($sql);
#         $sth->execute;
#         if ($sth->errstr) {
#           print "$case_num =>$sth->errstr\n ";
#         }
#       }
#     }

#     if ( $firm_status ==1 ) {
#       my $cf_id; 
#       if ( $cases_data->{billing_currency} eq 'English (USD/TWD)-英文帳戶' ){
#         $language_f{$firm} = 'English';
#         $currency_f{$firm} = 'TWD/USD';
#       } elsif ( $cases_data->{billing_currency} eq 'English (USD/TWD)-雙語帳戶' )  {
#         $language_f{$firm} = 'English';
#         $currency_f{$firm} = 'TWD/USD';
#       } elsif ( $cases_data->{billing_currency} eq 'Chinese (TWD)' )  {
#         $language_f{$firm} = 'Chinese';
#         $currency_f{$firm} = 'TWD';
#       } elsif ( $cases_data->{billing_currency} eq 'English (USD)' )  {
#         $language_f{$firm} = 'English';
#         $currency_f{$firm} = 'USD';
#       } elsif ( $cases_data->{billing_currency} eq 'English (TWD)' )  {
#         $language_f{$firm} = 'English';
#         $currency_f{$firm} = 'TWD';
#       } elsif ( $cases_data->{billing_currency} eq 'English (EUR)' )  {
#         $language_f{$firm} = 'English';
#         $currency_f{$firm} = 'EUR';
#       } else {
#         if ( $language_f{$firm} eq '' ) {
#           $language_f{$firm} = '';
#           $currency_f{$firm} = '';
#         }
#       }
#       $sql = "SELECT cf_id FROM client_firm WHERE party_name='$client' ";
#       $sth=$dbh->prepare($sql);
#       $sth->execute;
#       if ( $sth->rows ==0) {
#         $sql = "SELECT cf_id FROM client_firm WHERE cf_id like 'F%' ORDER BY cf_id DESC limit 1 ";
#         $sth=$dbh->prepare($sql);
#         $sth->execute;
#         if (my $data = $sth->fetchrow_hashref ) {
#           $cf_id = $data->{cf_id};
#           $cf_id =~ s/F//i;
#           $cf_id +=1;
#           $cf_id  = sprintf "F%06d",$cf_id ;
#         }
#         $sql=" INSERT INTO client_firm(cf_id,party_name,display_language,display_currency) values('$cf_id','$firm','$language_f{$firm}','$currency_f{$firm}') ";
#         $sth=$dbh->prepare($sql);
#         $sth->execute;
#         if ($sth->errstr) {
#           print "$case_num =>$sth->errstr\n ";
#         }
#       }
#     }

   }
}


# 函數：更新 retainer 歷史記錄
sub update_retainer {
  my $retainer_year = shift;     # retainer 年份
  my $retainer_quarter = shift;  # retainer 季度
  my $retainer_num = shift;      # retainer 編號
  my $bills = shift;             # 帳單資料
  my $dbh = shift;               # 資料庫連接
  my ($sql,$currency,$in_case_num);

### 寫入 retainer_his 和 retainer_total 表 
  # 如果是 PPP, TDG 或 BMT 客戶
  if ( $retainer_num eq 'PPP' || $retainer_num eq 'TDG' || $retainer_num eq 'BMT' ) {
    $currency='TWD';        # 幣別為台幣
    $in_case_num='AZN00210';  # 預設案件編號
  }
  # 插入 retainer 歷史記錄
  $sql="INSERT INTO retainer_his (retainer_his_num,retainer_his_date,retainer_his_year,retainer_his_quarter,retainer_his_deb_num,retainer_his_rec_ntd,retainer_his_currency,retainer_his_foreign_amt) values('$retainer_num',now(),'$retainer_year','$retainer_quarter','$bills->{deb_num}','$bills->{total}','$currency','$bills->{total}')";
  my $sth=$dbh->prepare($sql);
  my $rv = $sth->execute();
  # 如果插入成功
  if ($rv ==1 ) {
### 首先將 bills 表的 retainer_flag 更新為 1
    my $sql = qq[UPDATE bills SET retainer_flag='1'  WHERE deb_num ='$bills->{deb_num}' ];
    my $sth = $dbh->prepare($sql);
    my $rv = $sth->execute();
    # 如果更新失敗
    if ( $sth->rows ==0 ) {
      die "Please contact system administrator immediately.
           The update retainer_flag was not entered. Error code:$rv $sth $DBI::errstr";
    }
### 更新 retainer_total 表 
    my $use_total=$bills->{total};  # 本次使用的金額
    my $sql = qq[SELECT * FROM retainer_total WHERE retainer_num ='$retainer_num' AND retainer_year ='$retainer_year' AND retainer_quarter ='$retainer_quarter' ];
    my $sth = $dbh->prepare($sql);
    my $rv = $sth->execute();
    # 如果已有 retainer 記錄
    if (my $retainer_data = $sth->fetchrow_hashref) {
      # 計算剩餘和已使用金額
      my $remain_rec_ntd = $retainer_data->{remain_rec_ntd} - $use_total;
      my $remain_rec_foreign_amt = $retainer_data->{remain_rec_foreign_amt} - $use_total;
      my $used_rec_ntd = $retainer_data->{used_rec_ntd} + $use_total;
      my $used_rec_foreign_amt = $retainer_data->{used_rec_foreign_amt} + $use_total;
      $sql = qq[ UPDATE retainer_total SET remain_rec_ntd ='$remain_rec_ntd',remain_rec_foreign_amt ='$remain_rec_foreign_amt',used_rec_ntd ='$used_rec_ntd',used_rec_foreign_amt ='$used_rec_foreign_amt' WHERE retainer_num ='$retainer_num' AND retainer_year ='$retainer_year' AND retainer_quarter ='$retainer_quarter'];
      $sth = $dbh->prepare($sql);
      $rv = $sth->execute();
    } else {
      # 如果沒有記錄，建立新的 retainer_total 記錄
      my $remain_total = $use_total * -1;  # 剩餘為負數
      $sql = qq[INSERT INTO retainer_total(retainer_num,retainer_year,retainer_quarter,total_rec_ntd,total_currency,total_rec_foreign_amt,used_rec_ntd,used_currency,used_rec_foreign_amt,remain_rec_ntd,remain_currency,remain_rec_foreign_amt,retainer_rate,retainer_case_num)
                VALUES ('$retainer_num','$retainer_year','$retainer_quarter','0','$currency','0','$use_total','$currency','$use_total','$remain_total','$currency','$remain_total','1','$in_case_num') ];
      $sth = $dbh->prepare($sql);
      $rv = $sth->execute();
    }  

  # 如果插入 retainer 歷史失敗，顯示錯誤
  } else { 
    die "Please contact system administrator immediately.
         The Insert retainer_total was not entered. Error code:$rv $sth $DBI::errstr";
  }

}


# 函數：排序帳單
sub sort_bills {
  my $query = shift;  # CGI 查詢物件
  my $time = $query->param('time');  # 快取時間戳
  my $vars = retrieve("/var/www/billing/qicom_cache/$time");  # 從快取取回資料
  unless ($vars) {die};  # 如果無法取回快取，終止程式
  my $rec_ref  = $vars->{result};
  my @record =   @$rec_ref;
  my $sort_key =  $query->param('sort_key');  # 取得排序鍵

  my $case_manager =  $query->param('case_manager');
  my $case_num =  $query->param('case_num');


  # 根據排序鍵執行不同的排序方式
  if ($sort_key eq 'case_num') {
    @record  = sort{$a->{$sort_key} cmp  $b->{$sort_key}} @record;  # 按案件編號排序
  
  }elsif ($sort_key eq 'sent'){
    @record  = sort{conv_date($a->{$sort_key}) cmp   conv_date($b->{$sort_key})}                    @record;  # 按寄送日期排序
  
  }elsif ($sort_key eq 'draft_created'){
    @record  = sort{conv_date($b->{$sort_key}) cmp   conv_date($a->{$sort_key})}                    @record;  # 按草稿建立日期排序（逆序）
  }elsif ($sort_key eq 'case_manager') {
    @record  = sort{$a->{$sort_key} cmp  $b->{$sort_key}} @record;  # 按案件經理排序

  }elsif ($sort_key eq 'total') {
    @record  = sort{decommify($b->{$sort_key}) <=>  decommify($a->{$sort_key})} @record;  # 按總額排序（由大到小）
  
  }elsif ($sort_key eq 'deb_num') {
    @record  = sort{$a->{$sort_key} cmp  $b->{$sort_key}} @record;  # 按帳單編號排序
  
  }elsif ($sort_key eq 'id') {
    @record  = sort{decommify($b->{$sort_key}) <=>  decommify($a->{$sort_key})} @record;  # 按 ID 排序
  }


{my $count = 0;
 # 重新設定奇偶數標記
 for (@record) {
   $count++;
   my $rec = $_;
   $rec->{even} = HR::even($count);
 }

}

# 準備樣板變數
my $vars = {
	    result => \@record,
            in_manager       => $case_manager,
            in_case_num_dir  => $case_num,
	    time_cache     => $time, 
	   };


#my $cache_file = "/var/www/billing/qicom_cache/" . $time;
#store($vars, $cache_file);
my $output = '';
# 處理樣板並輸出
$template->process('bill_draft_list.html',$vars );
exit;  # 結束程式

}



# 函數：移除千位分隔符
sub decommify {
  my $string = shift;
  $string =~ s/,//g;  # 移除所有逗號
  return $string;
}



# 函數：轉換日期為時間戳（用於排序）
sub conv_date {
  my $date = shift;
  my @record = split(/-/, $date);  # 分割日期字串
  my $year = $record[0]-1900;
  my $month = $record[1]-1;
  my $day = $record[2];

  my $time = timelocal(0,0,0,$day, $month,$year);  # 轉換為 Unix 時間戳
  return $time; 
}



sub calc_bonus  {
  my @problems = ();
  my $id = shift;
  my $sth = $dbh->prepare(<<SQL);
  SELECT * FROM bills  WHERE id =  $id

SQL

$sth->execute;
my $bill = $sth->fetchrow_hashref;
  unless ($bill) {die " The bill has been updated. Bonus not calculated."}
if ($bill->{case_num} =~ /^MID/){next} 
  my $sql = qq[DELETE FROM bonuses WHERE deb_num = '$bill->{deb_num}'];
  my $sth = $dbh->prepare($sql);
  my $rv = $sth->execute; 
  $sql = qq {SELECT * FROM cases 
	      WHERE case_num =  '$bill->{case_num}' 
	      };
  $sth = $dbh->prepare($sql);
  $rv = $sth->execute;
  unless ($rv) {next}
  my $case = $sth->fetchrow_hashref;
    
  if (($case->{narrative} =~ /Patent Matters/i) or
      ($case->{case_type_name} eq 'Patent') or
      ($case->{case_type_name} eq 'PT'))
    {
      my $sql = qq[SELECT SUM (ntd_amount) FROM disbursements 
                       WHERE deb_num = '$bill->{deb_num}'
                       AND (disb_code = 116 OR  disb_code = 216)];
      $sth = $dbh->prepare($sql);
      $sth->execute;
      my $rec = $sth->fetchrow_hashref;
      $bill->{bonus_legal_services} -= $rec->{sum} if ($rec->{sum});

    }
  if ($case->{hourly}) {
    
  }elsif (($case->{hourly})and ($case->{flat_fee})){
    
  }elsif ($case->{flat_fee}){
      
    #check if we have received payment
    my $sql = qq[SELECT SUM (rec_ntd) FROM payments
                 WHERE deb_num =  '$bill->{deb_num}'
                ];
      
    my $sth = $dbh->prepare($sql);
    my $rv = $sth->execute;
            
    my $rec = $sth->fetchrow_hashref;
      
    if ($rec->{sum}) {
      $bill->{legal_services} = $rec->{sum} if ($rec->{sum})
      
    }else{
      next;
    }
   
  }

  if ($bill->{trans_services}) 
               {$bill->{legal_services} += $bill->{trans_services}};
    
  # who worked on the case ?

  my $sql = qq {SELECT DISTINCT initials FROM tr 
	        WHERE deb_num =  '$bill->{deb_num}'
               AND show_initials != 'VE' 
	      };
    
  my $sth = $dbh->prepare($sql);
  my $rv = $sth->execute;
  my $imputed_total = 0;
  {
    my $sql = qq{SELECT sum (internal_time * rate * 1000)  
                       FROM tr 
	               WHERE deb_num =  '$bill->{deb_num}'
                       AND initials != 'VE'  
	      };
    
    my $sth = $dbh->prepare($sql);
    my  $rv = $sth->execute;
         
    my $total = $sth->fetchrow_hashref;
    $imputed_total = $total->{sum};
  }
      
  while (my $fee_earner = $sth->fetchrow_hashref) {
    print "$fee_earner->{initials}\t";
    my $sql = qq {SELECT sum (internal_time * rate * 1000)  FROM tr 
	      WHERE deb_num =  '$bill->{deb_num}' 
	      AND initials =   '$fee_earner->{initials}' 
	      };
    print $sql, "\n\n";    
    my $sth = $dbh->prepare($sql);
    my $rv = $sth->execute;
    my $earnings = $sth->fetchrow_hashref;
    my $earned =  $earnings->{sum};
    unless ($earned) {$earned = 0}
    print "\t $earned" if ($earned);
    if ($earned) {
      
      my $share =  $earned/$imputed_total if  ($imputed_total > 0);
      if ($imputed_total == 0) {
	push (@problems, "$bill->{case_num}\t$bill->{deb_num}\t$bill->{legal_services}");   
      }
      $share = sprintf("%.3f",$share);
      print "\t $imputed_total \t $share\t";
      unless (defined($bill->{legal_services})) {
	push (@problems, "$bill->{case_num}\t$bill->{deb_num}\t$bill->{legal_services}");   
      }
      
      my $bonus =  $share * ($bill->{bonus_legal_services} * .04);
      $bonus = sprintf("%.0f",$bonus);
      print "$bonus\n";
      my $sql = qq{INSERT INTO bonus 
                         (deb_num,share,distro_1, distro_2, initials) 
                       VALUES ('$bill->{deb_num}', $share, 
                       $bonus, $bonus,'$fee_earner->{initials}')};
              
      my $sth = $dbh->prepare($sql);
      my $rv = $sth->execute;
      unless ($rv == 1) {print "$bill->{deb_num} has a problem"}

    }  
  }
}

# 函數：去除字串首尾空白
sub trim($)
{
    my $string = shift;
    $string =~ s/^\s+//;  # 去除前導空白
    $string =~ s/\s+$//;  # 去除後續空白
    return $string;
}

