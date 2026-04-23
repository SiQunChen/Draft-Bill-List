#!/usr/bin/perl
### disb_new.pl

use DBI;
use DBD::Pg;
use DBD::Sybase;
use CGI;
use CGI::Carp qw(fatalsToBrowser);
use HR;
use UT;
use Template;
use Time::localtime;
use strict;
use Data::Dumper;
use Date::Parse;

my $dbh = HR::DBConnect();
my $query = new CGI;
print $query->header(-charset=>'utf8');

#print $query->start_html(-style=>{'src'=>'../stylesheet/wp.css'},);

my $params = $query->Vars;

$params->{case_num} = uc($params->{case_num});
#die Dumper $params;
my %States = (
	   'begin_enter'          =>  \&begin_entry,
           'Submit Disbursement'  =>  \&insert_record,
	   'edit'                 =>  \&edit_record,
	   'edit_bill'            =>  \&edit_record_bill,
	   'delete'               =>  \&delete_record,
	   'Update Disbursement'  =>  \&update_record,
           'add_late'             =>  \&add_late_record,
           );
my  $page = $params->{'disb_submit'};

if($States{$page}){
    $States{$page}->($query, $params);
}else {
    my $page = 'begin_enter';
    $States{$page}->($query, $params);
   # print $query->redirect('error.pl?msg=This page does not exist');
   # exit();
}


sub begin_entry {
 my $query = shift;
 my $params = shift;
 my %prefs = $query->cookie('disb_prefs');

### Get disb code,today and template
# my ($disb_names, $date, $template) = prep_new_entry();
 my $date = UT::get_todays_date;

### Get HR table data and status =1
 my $sql = qq[SELECT initials FROM hr 
              WHERE status = 1
              ORDER BY initials];

 my $sth = $dbh->prepare($sql);
 my $rv = $sth->execute;
 my $ar_initials = $sth->fetchall_arrayref;

#die Dumper $ar_initials;
#now get the select names and codes
 if ($prefs{language} eq 'chinese') {
   $sql = qq[SELECT disb_name, disb_code 
             FROM disb 
             WHERE disb_code >= 200
             ORDER BY disb_code];
 }else {
   $sql = qq[SELECT disb_name, disb_code 
             FROM disb 
             WHERE disb_code <= 200
             ORDER BY disb_code];
 }
 $sth = $dbh->prepare($sql);
 $rv = $sth->execute;
 my $disb_codes = $sth->fetchall_arrayref;
 for (@$disb_codes) {
   $_->[1] =~ s/^1/2/;
 }

### 20220806 Get USD, EUR etc. rate
 my $search_year = UT::get_current_year;
 my $search_month = UT::get_current_month;

### Get USD rate
 $sql=qq[SELECT ntd FROM xrate WHERE year='$search_year' AND month='$search_month' ];
 $sth = $dbh->prepare ($sql);
 $sth->execute;
 my $data_db = $sth->fetchrow_hashref;
 my $xrate_usd = $data_db->{ntd};

### Get EUR rate
 $sql=qq[SELECT ntd FROM xrate_eur WHERE year='$search_year' AND month='$search_month' ];
 $sth = $dbh->prepare ($sql);
 $sth->execute;
 my $data_db = $sth->fetchrow_hashref;
 my $xrate_eur = $data_db->{ntd};
 


### Get acc_codes table data, but not use
# $sql = qq[SELECT * FROM acc_codes ORDER BY prim_code ];
# $sth = $dbh->prepare($sql);
# $rv = $sth->execute;
# my $codes = $sth->fetchall_arrayref;
# for (@$codes) {
#   $_->[9] .= "$_->[2] $_->[4] $_->[5]";
#   $_->[2]  .= "\t$_->[5]";
# }

### below not use
##get the voucher number;
#  my $voucher_num = 0;
#{
#my $user = 'sa';
#my $passwd = 'sa2000';
#my % attr = (
#	     PrintError => 0,
#	     RaiseError => 0
#	     );
#my $dbh = DBI->connect('DBI:Sybase:server=WEB-SERVER;database=DATAWIN',
#$user, $passwd);
##$dw_dbh dw = DataWin
##unless ($dbh) {die "No connection was made."};
#my $date = UT::get_todays_date;
#$date =~ s/-(\d)-/-0$1-/g;
#$date =~ s/-//g;
#$sql = qq[SELECT max(bb02) FROM fbm02 WHERE bb13 = '$date'];
##$sth = $dbh->prepare($sql);
#$sth->execute;
#my $rec = $sth->fetchrow_hashref;
#$voucher_num = $rec->{'COL(1)'};
#$voucher_num =~ /(\d)$/;
#my $digit = $1;
#$digit++;
#$voucher_num =~ s/(\d)$/$digit/;
# }

 my %vars = (date       => $date,
             case_num   => $params->{case_num},
             initials   => $params->{initials},
             people     => $ar_initials,
             disb_codes => $disb_codes,
             widget      =>$prefs{widget},
             mode	  =>"new_dis",
             xrate_usd	  =>$xrate_usd,
             xrate_eur	  =>$xrate_eur,
#	     disb_names => $disb_names,
#             prim_codes  =>$codes,
#             voucher_num =>$voucher_num,
	   );
 my $vars = \%vars;

### Get ip address data
# my $file = '';
# my $agent = $query->user_agent();
# my $ip = $query->remote_host;
# use Socket;
# my $ipaddr = inet_aton($ip);
# my $hostname = gethostbyaddr($ipaddr,AF_INET);
# if($hostname eq 'cpu-023'){
#if ($agent  =~ /^Dillo/) {
#if($ip eq '192.168.0.91'){
#  $file = 'disb_copy_new.html';
#    $file = 'disb_new_eva.html'; 
#}else {
#    $file = 'disb_new_eva.html';		      
#} 

 my $file = 'disb_new_eva.html';		      
 my $template =  HR::get_template;
 $template->process($file, $vars)
    || die "Template process failed: ", $template->error(), "\n";
}

sub insert_record {
 my $query = shift;
 my $params =shift;

 my $mode=$params->{mode};
### in_manager and in_case_num_dir in new disbursements not use. It is for Draft bill list use
 my $in_manager=$params->{in_manager};
 my $in_case_num_dir=$params->{in_case_num_dir};

### below not use
# my $ip = $query->remote_host;
# if ($ip =~/^192.168.0.(252|166)/){
#   insert_disb_acc($query, $params);
#   return;
# }

### below not use and any file
# my $ntd_value = 0;
# my $copies;
# if ($params->{num_of_copies}) { # this is a photocopy record
#   $params->{disb_code} =204;
#   $params->{initials} ='XX';
#   $ntd_value = $params->{num_of_copies} * 3;
#   $copies = $params->{num_of_copies};
#   $params->{ntd_amount} = $ntd_value;
# }

 unless ($params->{disb_code} =~/^2/) {
   print "Error: Please begin all codes with 2.<p>";
   $query->delete_all();

   $query->append(-name=>'disb_submit',-values=>['begin_enter']);
   print $query->a({-href=>$query->url(-query=>1)}, "Try again");
   exit; 
 }
 $params->{case_num} = uc($params->{case_num});

### check case_num case_close_date  => start
 my $sql = qq[SELECT case_close_date FROM cases  WHERE case_num = '$params->{case_num}'];
 my $sth = $dbh->prepare($sql);
 $sth->execute();
 my $case_close_date = $sth->fetchrow() ;
 if ($case_close_date ne '') {
   print "This case number closed already. IF you need to re-open a case and enter new time records, please use the 'case edit' function on slashlaw and uncheck the 'Case Closed?' checkbox. ";
   exit;
 }
### check case_num case_close_date  => end



#Does the case exist? Check
# increase, input one data, copy to lot of case num
 my $origin_case_num=$params->{case_num};
 my $begin=-1;
 my $end=-1;
 my $dash=-1;
 my @temp;
 my @temp1;
### ','~ input multi case
 if ( $origin_case_num =~ /~/) {
   @temp = split /~/,$origin_case_num;
   chomp($temp[0]);
   chomp($temp[1]);
   $temp[0] =~ s/\s*$//;
   $temp[1] =~ s/^\s*//;
   $temp[1] =~ s/\s*$//;
   $end=$temp[1];
   unless ( $temp[0] =~ /-/ )
   {
     $dash=0;
     $begin =$temp[0];
     $begin =~ s/^[a-zA-Z]*//;
     $begin =~ s/^[0]*//;
   } else {
     $dash=1;
     @temp1 = split /-/,$temp[0];
     $temp1[1] =~ s/^\s*//;
     $temp1[1] =~ s/\s*$//;
     $begin =$temp1[1];
   }
   $params->{case_num}=$temp[0];
 }
### ',' input multi case
 if ($origin_case_num =~ /,/ || $origin_case_num =~ /\s/  ) {
   @temp1 = split /,|\s/,$origin_case_num;
   foreach (@temp1) {
     next if ($_ eq '') ;
     push @temp,$_;
   } 
   $begin=1;
   $end=$#temp+1;
   $end=int($end);
   $dash=2;
   $params->{case_num}=$temp[0];
 }

########## check case_num is valid or not valid 
 my $sql = qq[SELECT * FROM cases  WHERE case_num = '$params->{case_num}'];
 my $sth = $dbh->prepare($sql);
 $sth->execute();
 my $rv = $sth->rows;

 unless ($rv == 1) { 
   print "Error: $params->{case_num} is not a valid case number<p>";
   $query->delete_all();
   $query->append(-name=>'disb_submit',-values=>['begin_enter']);
   print $query->a({-href=>$query->url(-query=>1)}, "Try again");
   exit; 
 };

##########################

 my $case =  $sth->fetchrow_hashref;
# check if the case code is valid, check country
 unless ($case->{bill_country}) {
   $params->{disb_code} =~ s/^2/1/;
 }

### check invoice number   => start
 if ( $params->{counsel_invoice} ne '') {
   my $sql = qq[SELECT * FROM disbursements  WHERE counsel_invoice = '$params->{counsel_invoice}' AND case_num = '$params->{case_num}' AND disb_code='$params->{disb_code}' ];
   my $sth = $dbh->prepare($sql);
   $sth->execute();
###my $counsel_invoice = $sth->fetchrow() ;
   my $row_count = $sth->rows;
   if ($row_count >=1 ) {
     print "This Invoice number duplicate. ";
     exit;
   }
 }
### check invoice number   => end

### add case_manager ,partner and partner2
# my $case_manager_data =$case->{case_manager} ;
# my $partner_data=$case->{partner};
# my $partner2_data =$case->{partner2};
#die Dumper $params;
 if ($params->{paydate} ne '') {
    $params->{dis_case_manager} = $case->{case_manager} ;
    $params->{dis_partner} = $case->{partner} ;
    $params->{dis_partner2} = $case->{partner2} ;
 }

### check disb code valid
 $sql = qq[SELECT * FROM disb  WHERE disb_code = '$params->{disb_code}'];
 $sth = $dbh->prepare($sql);
 $sth->execute();
 $rv = $sth->rows;
 unless ($rv == 1) { die "$params->{disb_code} is not a valid disbursement code."};

### read disb_name of disb table write to $params->{disb_name}
 my $disb =  $sth->fetchrow_hashref;
 $params->{disb_name} = $disb->{disb_name};

 unless ($params->{initials}){die "You did not enter your initials.\nPlease press the back button on your browser and fill in the the Initials field. Thanks.\n"};
 $sql = qq[SELECT * FROM disbursements WHERE disb_code = '$params->{disb_code}'];
 $sth = $dbh->prepare($sql);
 $sth->execute();
 my $disb_meta = $sth->fetchrow_hashref;

### I don't know why read disbursements table data write to disb table
#$params->{disb_name} = $disb_meta->{disb_name};
#if ($params->{show_as_legal_service_flag}) {$params->{show_as_legal_service_flag} = 1} 
 if ($params->{show_as_legal_service_flag}) {
   $params->{show_as_legal_service_flag} = 1;
 } 

 if ($begin ==-1) {
   $begin=$end=1;
 }

 $sql ='';
# Use for loop,addtional more data 
 my $i;
 if ($end <$begin) {
   print "case number input data error <BR>";
   exit 1;
 }

##### Add multi data
 for ($i=$begin;$i<=$end;$i++) {
### get bills draft_date
   my $sql = qq[SELECT * FROM bills WHERE case_num = '$params->{case_num}' AND sent is NULL AND bill_status ='0'];
   my $sth = $dbh->prepare($sql);
   $sth->execute();
   my $draft_created;
   if (my $bill_temp = $sth->fetchrow_hashref) {
     $draft_created = $bill_temp->{draft_created};           
   }

### check case_num
   $sql = qq[SELECT * FROM cases  WHERE case_num = '$params->{case_num}'];
   $sth = $dbh->prepare($sql);
   $sth->execute();
   $rv = $sth->rows;

   unless ($rv == 1) { 
     print "Error: $params->{case_num} is not a valid case number<p>";
     if ($dash ==0) {
       $params->{case_num}++;
     } elsif ($dash ==1) {
       my $ii=$i;
       $ii++;
       $params->{case_num}=$temp1[0].'-'.$ii;
     } elsif ($dash ==2) {
       $params->{case_num}=$temp[$i];
     }
     next; 
   };


### add get case_manager
   my $case_manager;
   my $case_sql;
   if ( $case_sql = $sth->fetchrow_hashref) {
     $case_manager = $case_sql->{case_manager};           
   }
### add 第二外幣
   
   if ( $params->{currency2}  eq '') {
     my @temp1 = split /-/,$params->{date};
     my $year = $temp1[0];
     my $month = $temp1[1];
     if ( $year ==0 || $year eq ''|| $year < 0 || $month ==0 || $month eq ''|| $month < 0) {
       @temp1 = split /\//,$params->{date};
       $year = $temp1[0];
       $month = $temp1[1];
       if ( $year ==0 || $year eq ''|| $year < 0 || $month ==0 || $month eq ''|| $month < 0) {
         $year=substr($params->{date},0,4);
         $month=substr($params->{date},4,2);
         if ( $year ==0 || $year eq ''|| $year < 0 || $month ==0 || $month eq ''|| $month < 0) {
           $year=UT::get_current_year;
           $month=UT::get_current_month;
         }
       }   
     }   
     if ( $case_sql->{billing_currency} eq 'English (EUR)') {
       my $x_rate2 =  UT::get_x_rate_eur($year,$month); 
       $params->{currency2} = 'EUR';
       $params->{foreign_amount2} = HR::ph_round(( $params->{ntd_amount} /$x_rate2),2);
     } else {
       my $x_rate2 =  UT::get_x_rate($year,$month); 
       $params->{currency2} = 'USD';
       $params->{foreign_amount2} = HR::ph_round(( $params->{ntd_amount} /$x_rate2),2);
     }
   }

#  unless ($params->{show_flag}){$params->{show_flag} = -1}
   if ($params->{show_flag} == -1 ) {
     $params->{nocharge_flag} = 1;
##### add billed_flag 
     $params->{billed_flag} = 2;
   } else {
     $params->{show_flag} = 1;
     $params->{nocharge_flag} = -1;
   }
### 20240727 add disbs_id_relation
 if ($params->{disbs_id_relation} eq '') {
#   $params->{disbs_id_relation} ="'NULL'";
   delete($params->{disbs_id_relation});
 } 

#die Dumper $begin,$end,@temp,$case_manager,$params ;
   if ($params->{disb_code} ==  131 ||  $params->{disb_code}==231) {
     my $total = $params->{rate} * $params->{num_of_chars};
     $params->{total} = $total; 
     $sth = HR::ins_statement('translations', $params, $dbh);
   }else {
     $sth = HR::ins_statement('disbursements', $params, $dbh);
   }
   $rv = $sth->execute();
   $rv = $sth->rows;
   if ($rv ==1 ) {
### add disb_code ='210' display duplicate 
     if  ($params->{disb_code} == 110 || $params->{disb_code} == 210 ) {
### search disbursments
        my $sql = qq[SELECT * FROM disbursements WHERE case_num = '$params->{case_num}' AND disb_code ='$params->{disb_code}' AND ntd_amount='$params->{ntd_amount}' AND nocharge_flag='-1'  ORDER BY id DESC limit 1 offset 1 ];
        my $sth = $dbh->prepare($sql);
        $sth->execute();
        my $row_count = $sth->rows;
        if ( $row_count == 1) {
          my $disbs = $sth->fetchrow_hashref;
          print "You succesfully inserted $params->{case_num} the disbursement.<BR><font color='red'>But data duplicate,Date:$disbs->{date},Payment Date:$disbs->{paydate}</font>  <p>";
        } else {
          print "You succesfully inserted $params->{case_num} the disbursement.<p>";
        } 
     } else {
          print "You succesfully inserted $params->{case_num} the disbursement.<p>";
     } 

     my $date_flag=0;
### search time record has data 
     my $sql = qq[SELECT * FROM tr WHERE case_num = '$params->{case_num}' AND billed_flag ='0' order by date DESC limit 1 ];
     my $sth = $dbh->prepare($sql);
     $sth->execute();
     if (my $trs = $sth->fetchrow_hashref) {
##### Get date
       my $tr_date = $trs->{date};
#       my $temp_t1=str2time($tr_date);   
       my $temp_t1=str2time($draft_created);   
       my $temp_t2=str2time($params->{date});
       if ($temp_t1 >= $temp_t2 ) {
         $date_flag=1;    
       } 
     }
##### add new disbursement check
#die Dumper $mode,$date_flag,$case_manager,$params ;
     if ( $mode eq 'new_dis' && ( $case_manager eq 'MD' || $case_manager eq 'GK' || $case_manager eq 'PO' || $case_manager eq 'SE' ) && $date_flag==1 ) {
##### disbursement add to debnum and change disb
##### Get disbursement id 
       my $dis_id;
       my $sql = qq[ SELECT last_value FROM disbursements_id_seq ]; 
       my $sth = $dbh->prepare($sql);
       $sth->execute();
       if (my $disb_h = $sth->fetchrow_hashref) {
         $dis_id = $disb_h->{last_value};
       } else {
         die Dumper "Search disbursement id fail Contact PH. $sql<p>";
       } 

##### Search bills tables get deb_num
       my $ntd_amount_temp = $params->{ntd_amount};
       my $foreign_amount2_temp = $params->{foreign_amount2};
       my $sql = qq[SELECT * FROM bills WHERE case_num = '$params->{case_num}' AND sent is NULL AND bill_status ='0'  ORDER BY id DESC];
       my $sth = $dbh->prepare($sql);
       $sth->execute();
       if (my $bill = $sth->fetchrow_hashref) {
##### Get deb_num
         my $deb_num_bills = $bill->{deb_num};           
         $params->{case_manager} = $case_manager;
#         $params->{deb_num} = $deb_num_bills;

##### Update bills dibs total
         if ($params->{show_flag} != -1 ) {   
           my $sql = qq[UPDATE bills set disbs=disbs+$ntd_amount_temp,foreign_disbs2=foreign_disbs2+$foreign_amount2_temp WHERE deb_num='$deb_num_bills'];
           my $sth = $dbh->prepare($sql);
           my $rv = $sth->execute;
           if ($rv ==1) {
             print "You succesfully update $deb_num_bills the bills. $sql<p>";
           } else {
             die Dumper "bills Update fail  Contact PH. $sql<p>";
           }
         }

##### Update disbursement deb_num and billed_flag total
         my $sql=qq[ UPDATE disbursements set deb_num='$deb_num_bills',billed_flag='0' WHERE id='$dis_id' ];
         my $sth = $dbh->prepare($sql);
         my $rv = $sth->execute;
         if ($rv ==1) {
           print "You succesfully update $deb_num_bills the disbursement. $sql<p>";
         } else {
           die Dumper "disbursement Update fail  Contact PH. $sql<p>";
         }
           
##### send mail for add disbursements content after draft bill
#         HR::mail_to ($params, "", "","disbs_add");
         
       }
     }
   } else {
     my $err=$sth->err;
     my $errstr=$sth->errstr;
     print "Fail inserted $params->{case_num} the disbursement. $err,$errstr<p>";
   }
#   print "$i = $params->{case_num} <BR>dash =$dash<BR> ";
   
   if ($dash ==0) {
     $params->{case_num}++;
   } elsif ($dash ==1) {
     my $ii=$i;
     $ii++;
     $params->{case_num}=$temp1[0].'-'.$ii;
   } elsif ($dash ==2) {
     $params->{case_num}=$temp[$i];
   }
 }

 if ($rv ==1 ) {
   print "You succesfully inserted the disbursement.<p>";
#  if($copies) {
#    print "$params->{case_num} will be charged NTD $ntd_value for $copies copies.<p>";
#  }
  
   my $initials = $params->{initials};
### if insert disbursement success,  check deb_num and initials is not null , display return screen
   if ( ($params->{deb_num}) and ($params->{initials}) ){
     my $deb_num = $params->{deb_num};
     $query->delete_all();

     $query->append(-name=>'disb_submit',-values=>['add_late_record']);
     $query->append(-name=>'initials',-values=>[$initials]);
     $query->append(-name=>'deb_num',-values=>[$deb_num]);

     print $query->a({-href=>"../cgi-bin/disb_new.pl?disb_submit=add_late&initials=$initials&deb_num=$deb_num&in_manager=$in_manager&in_case_num_dir=$in_case_num_dir"},"Enter another disbursement");
     print "<p>";
     print $query->a({-href=>"../cgi-bin/bill_draft_list.pl?case_manager=$in_manager&case_num=$in_case_num_dir"}, "Go back to draft bill list");
     print "<p>";
     print $query->a({-href=>"http://slashlaw/"},"Go back to slashlaw ");
   }else {
     $query->append(-name=>'disb_submit',-values=>['begin_enter']);
     print $query->a({-href=>"../cgi-bin/disb_new.pl?disb_submit=begin_enter&initials=$initials"},"Enter another disbursement");
   }
#  print "<p>";
 }else {
   print "Disbursement not added.<BR>";

 }

 if ($params->{refer_url}) {
   my $url = "$params->{refer_url}";
   print $query->a({-href=>$url}, "Go back to draft bill");
 }
}


### add edit record for bill
sub edit_record_bill {
 my $query = shift;
 my $params =shift;

### Select disbursements table. Search key id 
 my $sql = qq[SELECT * FROM disbursements WHERE id = '$params->{id}'];
 my $sth = $dbh->prepare($sql);
 $sth->execute();
 my $rv = $sth->rows;
 my $disb_id = $params->{id};
 unless ($rv ==1) { die "Too many disbursements were retrieved"};
 my $disb =  $sth->fetchrow_hashref();
 $disb->{disb_code} =~ s/^1/2/;
 $sth->finish;

### Select disb table data 
 $sql = qq[SELECT * FROM disb ORDER by disb_name];
 $sth = $dbh->prepare($sql);
 $sth->execute();
 my @disb_names = ();

### new table
# while (my $name = ($sth->fetchrow_array)[0]) {
 while (my $name = ($sth->fetchrow_array)[2]) {
   push (@disb_names, $name);
}
my ($start, $end) = ($params->{_start},$params->{_end});

# dectect debit note ==> start
  my $lock='';
  if ($params->{deb_num})
  {
     $lock=qq[readonly]; 
#     $lock=qq[readonly="readonly"]; 
#     $lock=qq[disabled="disabled"]; 
  }
# dectect debit note ==> end
##### add person =>start
  my $sql = qq[SELECT initials FROM hr
               WHERE status = 1
               ORDER BY initials];

  my $sth = $dbh->prepare($sql);
  my $rv = $sth->execute;
  my $ar_initials = $sth->fetchall_arrayref;
##### add person =>end


  my %vars = (
	      disb            =>$disb,
	      disb_names      =>\@disb_names,
              lock            => $lock,
              people          => $ar_initials,
              fee_earners     => "draft_bill",
	     );

 my $vars = \%vars;
#die Dumper $vars;
 my $template =  HR::get_template;


  my $file = 'disb_update.html';
  $template->process($file, $vars)
    || die "Template process failed: ", $template->error(), "\n";

}




sub edit_record {
 my $query = shift;
 my $params =shift;

### 20230607 change sql , Add billing_currency
# my $sql = qq[SELECT * FROM disbursements WHERE id = '$params->{id}'];
 my $sql = qq[SELECT billing_currency,disbursements.* FROM disbursements  LEFT JOIN cases  ON (disbursements.case_num=cases.case_num ) WHERE id = '$params->{id}'];
 my $sth = $dbh->prepare($sql);
 $sth->execute();
 my $rv = $sth->rows;
 my $disb_id = $params->{id};
 unless ($rv ==1) { die "Too many disbursements were retrieved"};
 my $disb =  $sth->fetchrow_hashref();
 $disb->{disb_code} =~ s/^1/2/;
 $sth->finish;

### old table
##$sql = qq[SELECT * FROM disb_names ORDER by disb_name];
# $sql = qq[SELECT * FROM disb ORDER by disb_name];
# $sth = $dbh->prepare($sql);
# $sth->execute();
# my @disb_names = ();

### new table
## while (my $name = ($sth->fetchrow_array)[0]) {
# while (my $name = ($sth->fetchrow_array)[2]) {
#   push (@disb_names, $name);
#}
my ($start, $end) = ($params->{_start},$params->{_end});

# dectect debit note ==> start
  my $lock='';
  if ($params->{deb_num})
  {
     $lock=qq[readonly]; 
#     $lock=qq[readonly="readonly"]; 
#     $lock=qq[disabled="disabled"]; 
  }
# dectect debit note ==> end
##### add person =>start
#  my $sql = qq[SELECT initials FROM hr
#               WHERE status = 1
#               ORDER BY initials];

#  my $sth = $dbh->prepare($sql);
#  my $rv = $sth->execute;
#  my $ar_initials = $sth->fetchall_arrayref;
##### add person =>end
### 20230607 add search cases table get billing_currency
# my $sql = qq[SELECT billing_currency FROM cases
#              WHERE case_num = '$disb->{case_num}'];

# my $sth = $dbh->prepare($sql);
# my $rv = $sth->execute;
# my $tmp_ref = $sth->fetchrow_hashref();
# my $billing_currency = $tmp_ref->{billing_currency};



##### add currency_list
 my @currency_list=("","USD","EUR","AUD","HKD","SGD","JPY","NZD","GBP","CNY","CAD");

##### add currency_list2
 my @currency_list2=("USD","EUR");

##### Get disbursements code and name
 my ($disb_code_list,$disb_name_list)=HR::get_disb_codes;
 unshift  @$disb_name_list,'ALL';
 unshift  @$disb_code_list,'0';

 my @hr_list=HR::get_hr_initials;
 my @ar_initials =\@hr_list;
 unshift  @hr_list,'ALL';
### 20230607 add 第二外幣
 my $x_rate2 = 0; 
 my @temp1 = split /-/,$disb->{date};
 my $year = $temp1[0];  
 my $month = $temp1[1];  
 if ( $disb->{currency2} eq '') {
   if ( $disb->{billing_currency} eq 'English (EUR)') {
     $x_rate2 =  UT::get_x_rate_eur($year,$month); 
      $disb->{currency2} = 'EUR';
      $disb->{foreign_amount2} = HR::ph_round(( $disb->{ntd_amount} /$x_rate2),2);
    } elsif ( $disb->{billing_currency} eq 'English (USD)') {
      $x_rate2 =  UT::get_x_rate($year,$month); 
      $disb->{currency2} = 'USD';
      $disb->{foreign_amount2} = HR::ph_round(( $disb->{ntd_amount} /$x_rate2),2);
    }
 } elsif ($disb->{foreign_amount2} ==0 || $disb->{foreign_amount2} eq '') {
    if ( $disb->{billing_currency} eq 'English (EUR)') {
      $x_rate2 =  UT::get_x_rate_eur($year,$month);
      $disb->{currency2} = 'EUR';
      $disb->{foreign_amount2} = HR::ph_round(( $disb->{ntd_amount} /$x_rate2),2);
    } elsif ( $disb->{billing_currency} eq 'English (USD)') {
      $x_rate2 =  UT::get_x_rate($year,$month);
      $disb->{currency2} = 'USD';
      $disb->{foreign_amount2} = HR::ph_round(( $disb->{ntd_amount} /$x_rate2),2);
    }
#die Dumper $x_rate2,$disb->{ntd_amount},$disb->{billing_currency},$disb->{foreign_amount2},$sql;
 } else {
#    if ( $disb->{billing_currency} eq 'English (EUR)') {
#      $x_rate2 =  UT::get_x_rate_eur($year,$month);
#    } elsif ( $disb->{billing_currency} eq 'English (USD)') {
#    } elsif ( $disb->{billing_currency} eq 'English (USD)') {
    if ( $disb->{currency2} eq 'EUR') {
      $x_rate2 =  UT::get_x_rate_eur($year,$month);
    } else {
      $x_rate2 =  UT::get_x_rate($year,$month);
    }
 }

#die Dumper $x_rate2,$disb->{ntd_amount},$disb->{billing_currency},$disb->{foreign_amount2},$sql;
 $disb->{ntd_amount} = HR::commify($disb->{ntd_amount});

 my $template =  HR::get_template();
 my %vars = (
	      disb            => $disb,
	      start_          => $start,
              end_            => $end,
              lock            => $lock,
	      currency_list   =>\@currency_list,
	      currency_list2  =>\@currency_list2,
	      hr_list         =>\@hr_list,
              people          => \@ar_initials,
              date_type       => $params->{date_type},
              fee_earners     => $params->{fee_earners},
              case_num_one    => $params->{case_num_one},
              in_manager      => $params->{in_manager},
              in_case_num_dir => $params->{in_case_num_dir},
              disb_name => $params->{disb_name},
              disb_name_list   => $disb_name_list,
              disb_code_list   => $disb_code_list,
              items3           => 'active', 
              x_rate2          => $x_rate2,
	     );

 my $vars = \%vars;

 my $file = 'disb_update.html';
 $template->process($file, $vars) || die "Template process failed: ", $template->error(), "\n";

}

sub delete_record {
 my $query = shift;
 my $params =shift;
 my $url = "../cgi-bin/disb_list.pl?start=$params->{_start}&end=$params->{_end}&case_num=$params->{case_num}&deb_num=$params->{deb_num}&fee_earners=$params->{fee_earners}&date_type=$params->{date_type}";
  
###### add get disbursements value => start
 my $sql = qq[SELECT * FROM disbursements WHERE id = '$params->{id}'];
 my $sth = $dbh->prepare($sql);
 my $result = $sth->execute;
 my $disbs = $sth->fetchrow_hashref;
###### add get disbursements value => end

# delete disbursements
 my $sql =  HR::del_statement ('disbursements', $params->{id}, $dbh);
 my $sth = $dbh->prepare($sql);
 my $rv = $sth->execute();

### count disbs total
 my $sql =qq[UPDATE bills SET disbs= 
(SELECT sum(ntd_amount) FROM disbursements WHERE deb_num='$params->{deb_num}' AND nocharge_flag='-1' AND show_flag='1') WHERE deb_num='$params->{deb_num}'];
 my $sth = $dbh->prepare($sql);
 my $rv = $sth->execute();


 my $template = HR::get_template();


### add get IP address
 my $addr = $ENV{'REMOTE_ADDR'};
 my $ip_addr=HR::ip_addr();
 $disbs->{ip_name} = $ip_addr->{$addr};
 $disbs->{ip_addr} = $addr;

##### send mail for deleted disbursements content
 HR::mail_to ($disbs, "", "","disbs_delete");

##### count bills table disbs field => start
 if ($params->{deb_num} ne '') {
   $sql = qq{SELECT SUM (ntd_amount) FROM
             disbursements
             WHERE billed_flag = 0
             AND show_flag = 1
             AND deb_num = '$params->{deb_num}'};
   my $sth = $dbh->prepare ($sql);
   my $rv = $sth->execute();
   my $disb = $sth->fetchrow_hashref;
   unless ($disb->{sum}) {$disb->{sum} = 0};

   $sql = qq{UPDATE bills SET disbs= $disb->{sum}
             WHERE deb_num = '$params->{deb_num}'};
   $sth = $dbh->prepare ($sql);
   $rv = $sth->execute();
 }
##### count bills table disbs field => end
 my %vars = (url => $url);
 my $vars = \%vars;
 my $file = 'back_to_slash.html';
 $template->process($file, $vars)
   || die "Template process failed: ", $template->error(), "\n";
}


sub update_record {
# use Mail::Sendmail;
 my $query = shift;
 my $params =shift;

 if ($params->{lock} eq 'readonly') {
#    delete($params->{show_as_legal_service_flag});
#    delete($params->{show_flag});
 } else {
    if ($params->{show_flag} == -1 ){
       $params->{nocharge_flag} = 1;
##### add billed_flag 
       $params->{billed_flag} = 2;
    } else {
       $params->{show_flag} = 1;
       $params->{nocharge_flag} = -1;
       $params->{billed_flag} = -1;
    }
    unless ($params->{show_as_legal_service_flag}) {$params->{show_as_legal_service_flag} = -1};
 } 

 unless ($params->{disb_code} =~/^2/) {
   die "Please begin all codes with 2.";
 }
### where from summary 
 $params->{summary} =~ s/'/''/g;
 my $sec_mail = $params->{initials}.'@winklerpartners.com';

### check case_num valid
 my $sql = qq[SELECT * FROM cases  WHERE case_num = '$params->{case_num}'];
 my $sth = $dbh->prepare($sql);
 $sth->execute();
 my $rv = $sth->rows;
 unless ($rv) { die "$params->{case_num} is not a valid case number."};
 my $case =  $sth->fetchrow_hashref;

### add get case_manager , partner and partner2
 my $case_manager = $case->{case_manager};
 my $partner = $case->{partner};
 my $partner2 = $case->{partner2};

### check disb_code is valid
 $sql = qq[SELECT * FROM disb  WHERE disb_code = '$params->{disb_code}'];
 $sth = $dbh->prepare($sql);
 $sth->execute();
 $rv = $sth->rows;
 unless ($rv == 1) { die "$params->{disb_code} is not a valid disbursement code."};
 
 unless ($case->{bill_country}) {
   $params->{disb_code} =~ s/^2/1/;
 }
### get disb_code relation data
 $sql = qq[SELECT * FROM disb WHERE disb_code = '$params->{disb_code}'];
 $sth = $dbh->prepare($sql);
 $sth->execute();
 my $disb_meta = $sth->fetchrow_hashref;
 $params->{disb_name} = $disb_meta->{disb_name};
 my $case_num = $params->{case_num};
 my $disb_id = $params->{id};
 my $date = $params->{date};
 my $disb_code = $params->{disb_code};
 $params->{ntd_amount} =~ s/,//g;
 my $ntd_amount = $params->{ntd_amount};
 $sth->finish;

 my $url='';
 if ($params->{fee_earners} ne 'draft_bill' )
 {
   $url = "../cgi-bin/disb_list.pl?start=$params->{start_}&end=$params->{end_}&case_num=$params->{case_num_one}&deb_num=$params->{deb_num}&fee_earners=$params->{fee_earners}&date_type=$params->{date_type}";  
 } else {
   $url = "../cgi-bin/bill_mod.pl?id=$params->{case_num_one}&deb_num=$params->{deb_num}&in_manager=$params->{in_manager}&in_case_num_dir=$params->{in_case_num_dir}&date_type=$params->{date_type}";
 }
######  get disbursements value => start
 my $sql = qq[SELECT * FROM disbursements WHERE id = '$params->{id}'];
 my $sth = $dbh->prepare($sql);
 my $result = $sth->execute;
 my $disbs = $sth->fetchrow_hashref;
######  get disbursements value => end
 
### add detect ''
 if ($params->{paydate} eq '') {
   $params->{paydate}="'NULL'";
   $params->{dis_case_manager}="'NULL'";
   $params->{dis_partner}="'NULL'";
   $params->{dis_partner2}="'NULL'";
 } else {
   $params->{dis_case_manager}=$case_manager;
   $params->{dis_partner}=$partner;
   $params->{dis_partner2}=$partner2;
 }
 if ($params->{invoice_date} eq '') {
   $params->{invoice_date}='NULL';
 }
 if ($params->{x_rate} eq '') {
   $params->{x_rate} =0;
 } 
 if ($params->{bpm_date} eq '') {
   $params->{bpm_date} ="'NULL'";
 } 
 if ($params->{currency2} eq '') {
   $params->{currency2} ="'NULL'";
 } 
 if ($params->{foreign_amount2} eq '') {
   $params->{foreign_amount2} ="'NULL'";
 } 
### 20240727 add disbs_id_relation
 if ($params->{disbs_id_relation} eq '') {
   $params->{disbs_id_relation} ="'NULL'";
 } 

# Update disbursements

### check invoice number   => start
 if ( $params->{counsel_invoice} ne '') {
   my $sql = qq[SELECT * FROM disbursements  WHERE counsel_invoice = '$params->{counsel_invoice}' AND case_num='$params->{case_num}' AND disb_code='$params->{disb_code}'];
   my $sth = $dbh->prepare($sql);
   $sth->execute();
   my $row_count = $sth->rows;
   if ($row_count >=2 ) {
     print "This Invoice number duplicate. ";
     exit;
   }
 }
### check invoice number   => end
#die Dumper $params;

### add get IP address
# my $addr = $ENV{'REMOTE_ADDR'};
# my $ip_addr=HR::ip_addr();
# $disbs->{ip_name} = $ip_addr->{$addr};
# $disbs->{ip_addr} = $addr;
#### if disbs is no charge and ntd_amount >=10000
### change nocharge_flag -1 to 1 and 
# if ( $disbs->{nocharge_flag} == -1 && $params->{nocharge_flag} == 1 &&  $params->{ntd_amount} >=10000   ) {
##  HR::mail_to ($disbs,$params, "","disbs_check");
#   my $template =  HR::get_template;
#   my %vars = (url =>  $url);
#   my $vars = \%vars;
#   my $file = 'disbs_check.html';
#   $template->process($file, $vars)
#    || die "Template process failed: ", $template->error(), "\n";
# }

 $sth = HR::update('disbursements', $params, $dbh);
 $rv = $sth->execute(); 
 if ($rv !=1 ) {
   my $err=$sth->err;
   my $errstr=$sth->errstr;
   print " Update disbursemenmt fail $err, $errstr\n";
   exit;
 } 
### add check update disbursements date

### get bills draft_date
 my $sql = qq[SELECT * FROM bills WHERE case_num = '$params->{case_num}' AND sent is NULL AND bill_status ='0'];
 my $sth = $dbh->prepare($sql);
 $sth->execute();
 my $draft_created;
 if (my $bill_temp = $sth->fetchrow_hashref) {
   $draft_created = $bill_temp->{draft_created};
 }

 my $date_flag=0;

### check update date 
 my $temp_t1=str2time($draft_created);
 my $temp_t2=str2time($params->{date});
 if ($temp_t1 >= $temp_t2 ) {
   $date_flag=1;
 }

###detect deb_num is empty
 if (  $params->{deb_num} ne '' ) {
   $date_flag=0;
 } 
#die Dumper $date_flag;

##### add new disbursement check
 if ( ( $case_manager eq 'MD' || $case_manager eq 'GK' || $case_manager eq 'PO' || $case_manager eq 'SE' ) && $date_flag==1 ) {
##### disbursement add to debnum and change disb
##### Search bills tables get deb_num
   my $ntd_amount_temp = $params->{ntd_amount};
   my $sql = qq[SELECT * FROM bills WHERE case_num = '$params->{case_num}' AND sent is NULL AND bill_status ='0'];
   my $sth = $dbh->prepare($sql);
   $sth->execute();
   if (my $bill = $sth->fetchrow_hashref) {
##### Get deb_num
     my $deb_num_bills = $bill->{deb_num};
#      $params->{case_manager} = $case_manager;
##### Update bills dibs total
     if ($params->{show_flag} != -1 ) {
       my $sql = qq[UPDATE bills set disbs=disbs+$ntd_amount_temp WHERE deb_num='$deb_num_bills'];
       my $sth = $dbh->prepare($sql);
       my $rv = $sth->execute;
       if ($rv ==1) {
         print "You succesfully update $deb_num_bills the bills. $sql<p>";
       } else {
         die Dumper "bills Update fail  Contact PH. $sql<p>";
       }
     }
##### Update disbursement deb_num and billed_flag total
     my $sql=qq[ UPDATE disbursements set deb_num='$deb_num_bills',billed_flag='0' WHERE id='$params->{id}' ];
     my $sth = $dbh->prepare($sql);
     my $rv = $sth->execute;
     if ($rv ==1) {
       print "You succesfully update $deb_num_bills the disbursement. $sql<p>";
     } else {
       die Dumper "disbursement Update fail  Contact PH. $sql<p>";
     }
   }
 }


### add get IP address
 my $addr = $ENV{'REMOTE_ADDR'};
 my $ip_addr=HR::ip_addr();
 $disbs->{ip_name} = $ip_addr->{$addr};
 $disbs->{ip_addr} = $addr;
#### if disbs is charge and ntd_amount < 10000
# if ( $params->{nocharge_flag} == -1 &&  $params->{ntd_amount} <10000   ) {
#die Dumper $disbs,$params;
   HR::mail_to ($disbs,$params, "","disbs_update");
# }

##### send mail for deleted disbursements content
##### maybe delete be blow
# if ( ($disbs->{paydate} eq $params->{paydate}) and ($disbs->{notes} eq $params->{notes}) ) { 
###    delete($params->{show_as_legal_service_flag});
###    delete($params->{show_flag});
#   if (!defined($params->{show_flag}) ) {
#     $params->{show_flag} = $disbs->{show_flag};
#     $params->{show_as_legal_service_flag} = $disbs->{show_as_legal_service_flag};
#   } 
###die Dumper $disbs,$params;
###    HR::mail_to ($disbs,$params, "","disbs_update");
# }
 my $template =  HR::get_template;
 my %vars = (url =>  $url);
 my $vars = \%vars;
 my $file = 'back_to_slash.html';
 $template->process($file, $vars)
    || die "Template process failed: ", $template->error(), "\n";
}


sub add_late_record {
# A case manager (especially TM manager) 
# needs to add a disbursement to a draft bill
  
 my $query = shift;
 my $params = shift;
  
 my ($disb_names, $date, $template) = prep_new_entry();
  
 my $sql = qq[SELECT * FROM bills 
               WHERE  deb_num = '$params->{deb_num}'];
 my $sth = $dbh->prepare($sql);
 my $rv = $sth->execute();
 my $bill = $sth->fetchrow_hashref;
 unless ($rv ==1) { die "More than one bill 
                         was retrieved for $params->{deb_num}. Please contact
                         system administrator immediately:$!";
                  };
##### add person =>start
 my $sql = qq[SELECT initials FROM hr
              WHERE status = 1
              ORDER BY initials];

 my $sth = $dbh->prepare($sql);
 my $rv = $sth->execute;
 my $ar_initials = $sth->fetchall_arrayref;
##### add person =>end

### 20240328 Get USD, EUR etc. rate
 my $search_year = UT::get_current_year;
 my $search_month = UT::get_current_month;

### Get USD rate
 $sql=qq[SELECT ntd FROM xrate WHERE year='$search_year' AND month='$search_month' ];
 $sth = $dbh->prepare ($sql);
 $sth->execute;
 my $data_db = $sth->fetchrow_hashref;
 my $xrate_usd = $data_db->{ntd};

### Get EUR rate
 $sql=qq[SELECT ntd FROM xrate_eur WHERE year='$search_year' AND month='$search_month' ];
 $sth = $dbh->prepare ($sql);
 $sth->execute;
 my $data_db = $sth->fetchrow_hashref;
 my $xrate_eur = $data_db->{ntd};



  my $refer_url = $query->cookie('refer_url');
  my %vars = (   date => $date,
	         disb_names => $disb_names,
	         case_num   => $bill->{case_num},
	         initials   => $params->{initials},
	         action     => 'add_late_disb',
		 deb_num    => $params->{deb_num},
                 people     => $ar_initials,
                 refer_url  => $refer_url,
                 in_manager =>  $params->{in_manager},
                 in_case_num_dir=> $params->{in_case_num_dir}, 
                 xrate_usd    =>$xrate_usd,
                 xrate_eur    =>$xrate_eur,
	   );
  my $vars = \%vars;
  #die Dumper($vars);
#  my $file = 'disb_new_late.html';
  my $file = 'disb_new_eva.html';
  $template->process($file, \%vars)
    || die "Template process failed: ", $template->error(), "\n";

}


sub prep_new_entry {

 my $sql = qq[SELECT * FROM disb ORDER by disb_code];
 my $sth = $dbh->prepare($sql);
 $sth->execute();
 my @disb_names = ();
 while (my @name = $sth->fetchrow_array) {
   if ($name[1] =~ /^2/) {
     push (@disb_names, \@name);
   }
 }
 
 my $tm = localtime;
 my ($year, $month, $day, $hour, $min, $sec) =  ($tm->year+1900,($tm->mon)+1, 
                                                 $tm->mday, $tm->hour, $tm->min, $tm->sec);
 my $DATE_SEP = '-';
 my $date = $year . $DATE_SEP . $month . $DATE_SEP. $day;
  
 my $template =  HR::get_template;
 return (\@disb_names, $date,$template);
}


sub insert_disb_acc {
my $user = 'sa';
my $passwd = 'sa2000';

my % attr = (
	     PrintError => 1,
	     RaiseError => 1
	     );



my $dw_dbh = DBI->connect('DBI:Sybase:server=WEB-SERVER;database=DATAWIN',
$user, $passwd);
#$dw_dbh dw = DataWin
unless ($dw_dbh) {die "No connection was made."};
my $query = shift;

my $params = shift;

#check the case code;

my $sql = qq[SELECT case_num FROM cases WHERE case_num = '$params->{case_num}'];
my $sth = $dbh->prepare($sql);
my $rv = $sth->execute;
unless ($rv == 1) {

die "$params->{case_num} is not a valid case number. Please go back and try again.";

}


#insert the parameters to the disbursment table in billing
my $subcode =0;
if ($params->{acc_code}) {
    my @fields = split(/\t/, $params->{acc_code});
 
    my $sql = qq{SELECT sub_code FROM acc_codes WHERE prim_code = '$fields[0]'
    AND desc_en =  '$fields[1]'};
   
    my $sth = $dbh->prepare($sql);
    $sth->execute;
    my $sub_codes = $sth->fetchall_arrayref;
    $params->{acc_code} = $fields[0];
    $subcode =  $sub_codes->[0];
    $params->{disb_code_sec} =  $subcode->[0];
}     


my $sth = HR::ins_statement('disbursements', $params, $dbh);
#die UT::format_sql ($sql);
my $rv = $sth->execute;
unless ($rv) { die "The expenditure was not entered:$DBI::errstr.\n$sql"}


print "The expenditure was entered successfuly.";
#Map to Accounting systems field names


my %fields = (bb01 =>  $params->{comp_code},
              bb02 =>  $params->{voucher_num},
	      bb050 =>  $params->{acc_code},
	      bb051 =>  $params->{disb_code_sec},
	      bb03 =>  $params->{voucher_type},
              bb08 =>  $params->{ntd_amount},
              bb06 =>  $params->{notes}
	     );
#check the number of vouchers for this vouncher number;
  my  $dw_sql = qq[SELECT COUNT(*) AS EXPR1 FROM fbm02 WHERE bb02 = '$params->{voucher_number}'];
 
  my $dw_sth = $dw_dbh->prepare( $dw_sql);
  my $rv =  $dw_sth->execute;
  my $rec = $dw_sth->fetchrow_hashref;
 
  my $voucher_cnt = $rec->{'EXPR1'};
 
if ( $voucher_cnt) {
 
   $voucher_cnt++;
 }else {

 $voucher_cnt = 1;
}
($params->{voucher_number}) =~ /^(\d{8})/;
my $date = $1;
  $dw_sql = qq[INSERT INTO fbm02 (bb01,bb02,bb04,bb050,bb051,bb03, bb08, bb09,bb06,bb07,bb100,bb13,bb16,bb17)
               VALUES ( '$params->{comp_code}','$params->{voucher_number}', $voucher_cnt,'$params->{acc_code}',
                        '$params->{disb_code_sec}','$params->{voucher_type}','$params->{ntd_amount}','','$params->{notes}',  'D', 'M','$date', 'NT\$', '1')];
#die Dumper $params;
#die UT::format_sql( $dw_sql);
$dw_sth = $dw_dbh->prepare( $dw_sql);
$rv =  $dw_sth->execute;
unless ($rv) {die "Did not post to Accounting"}
$voucher_cnt++;

$dw_sql = qq[INSERT INTO fbm02 (bb01,bb02,bb04,bb050,bb051,bb03, bb08, bb09,bb06,bb07,bb100,bb13,bb16,bb17)
               VALUES ( '$params->{comp_code}','$params->{voucher_number}', $voucher_cnt,'$params->{acc_code}',
                        '$params->{disb_code_sec}','$params->{voucher_type}','$params->{ntd_amount}','','$params->{notes}',  'C', 'M','$date', 'NT\$', '1')];
#die Dumper $params;
#die UT::format_sql( $dw_sql);
$dw_sth = $dw_dbh->prepare( $dw_sql);
$rv =  $dw_sth->execute;
unless ($rv) {die "Did not post to Accounting"}



 $dw_sql = qq[INSERT INTO fbm01 (ba01,ba02,ba03,ba04,ba05,ba06,ba07,
                                 ba09,ba10, ba11, ba12, ba13       )
               VALUES ( '$params->{comp_code}','$params->{voucher_number}', 
                        '$params->{voucher_type}','','$date', '',
			'$params->{initials}',
			'$params->{ntd_amount}','$params->{ntd_amount}',
                        'NT\$','1','N'
                       )
	      ];

UT::format_sql($dw_sql);
$dw_sth = $dw_dbh->prepare( $dw_sql);
$rv =  $dw_sth->execute;
unless ($rv) {die "Did not post to Accounting: $DBD::errstr"}


}
