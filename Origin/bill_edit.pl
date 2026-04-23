#!/usr/bin/perl -w

use DBI;
use CGI;
#use CGI::Carp qw(fatalsToBrowser);
use HR;
use UT;
use Template;
use strict;
use Data::Dumper;

my $query = new CGI;
print $query->header(-charset=>'UTF-8');
print $query->start_html(-style=>{'src'=>'stylesheet/wp.css'},
			 -title=>"Edit Bill");
my $deb_num = $query->param('deb_num');
my $in_case_num_dir = $query->param('in_case_num_dir');
my $in_manager = $query->param('in_manager');

 my $dbh = HR::DBConnect();

 my $sql = "SELECT * FROM bills 
         where deb_num= '$deb_num'";

 my $sth = $dbh->prepare($sql);
 my $rv = $sth->execute;

 my $bill = $sth->fetchrow_hashref;
 my $case_num = $bill->{case_num};

### get tr_ledes_code table data
 my $case_num3=substr($case_num,0,3);
 my $sql = "SELECT * FROM tr_ledes_code WHERE case_num='$case_num3' ORDER BY ledes_code ";

 my $sth = $dbh->prepare($sql);
 my $rv = $sth->execute;
 my %ledes_code;
 while (my $tr = $sth->fetchrow_hashref) {
   my $code = $tr->{ledes_code};
   $ledes_code{$code} =  $tr->{ledes_content};
 }
### tr_ledes_activity_code table data

 my $sql = "SELECT * FROM tr_ledes_activity_code WHERE ledes_a_case_num='$case_num3' ORDER BY ledes_activity_code ";

 my $sth = $dbh->prepare($sql);
 my $rv = $sth->execute;
 my %ledes_activity_code;
 while (my $tr = $sth->fetchrow_hashref) {
   my $code = $tr->{ledes_activity_code};
   $ledes_activity_code{$code} =  $tr->{ledes_activity_content};
 }
### Get tr table data
 $sql = "SELECT * FROM tr 
         where deb_num= '$deb_num' 
         ORDER BY date,id";

 $sth = $dbh->prepare($sql);
 $rv = $sth->execute;
 my $show="";
 my $color;
my $counts=0;
my %fee_earners;

while (my $tr = $sth->fetchrow_hashref) {
   $fee_earners{$tr->{initials}}=1; 
   my $showflag = $tr->{show_flag};
   if ($tr->{show_flag} == 1) {
     $showflag = 'CHECKED';
   }else{
     $showflag ='';
   }
   
    my $nocharge_flag = $tr->{nocharge_flag};
    if ($nocharge_flag == -1) {
      $nocharge_flag = '';
    }else {
      $nocharge_flag = 'CHECKED';
    }

 
     my $color = ($color eq '#c9c9c9' ? '#a9a9a9' : '#c9c9c9');
    if ($showflag eq 'CHECKED')
    {
    $show .= << ".";
<tr bgcolor="$color">
.
    } else {
    $counts++;
    my $id_name='A'.$counts ;
    $show .= << ".";
<tr bgcolor="$color" class="postshown" id="$id_name">
.
    }
    $show .= << ".";
 <td width=15 rowspan=2 valign="top">
  <input type='text' name='id $tr->{id}' value='$tr->{id}' readonly size=5 class="readonly"><input type='hidden' name='initials $tr->{id}' value='$tr->{initials}'>
<!--  <input type='text' name='case_num $tr->{id}' value='$tr->{case_num}'> -->

  $tr->{case_num}
  <input type='hidden' name='show_rate $tr->{id}' value='$tr->{show_rate}'>
 </td>
 <td>
  <input type='text' name='show_initials $tr->{id}' value='$tr->{show_initials}' size=6>
  $tr->{rate}
 </td>
 <td>
  <input type='text' name='date $tr->{id}' value='$tr->{date}' readonly size=12 class="readonly">
 </td>
 <td>
  <input type='text' value='$tr->{bill_time}' readonly size=3 class="readonly">
 </td>
 <td>

 </td>
 <td>
  <input type='text' name='internal_time $tr->{id}' value='$tr->{internal_time}' size=4>
 </td>
 <td>
  <input type='text' name='charge $tr->{id}' value='$tr->{charge}' size=4>
 </td>
 <td>
  <input type='checkbox' id='nocharge_flag $tr->{id}' name='nocharge_flag $tr->{id}' $nocharge_flag value='1' onClick="return check_show_nocharge($tr->{id});">
 </td>
 <td>
  <input type='checkbox' id='show_flag $tr->{id}'  name='show_flag $tr->{id}' $showflag value='1' onClick="return check_show_nocharge($tr->{id});">
 </td>
 </tr>
.

    if ($showflag eq 'CHECKED')
    {
    $show .= << ".";
<tr bgcolor="$color" valign="middle">
.
    } else {
    $counts++;
    my $id_name='A'.$counts ;
    $show .= << ".";
<tr bgcolor="$color" valign="middle" class="postshown" id="$id_name">
.
    }
    $show .= << ".";
  <td colspan=3 align="left">
   <textarea name='nar_2 $tr->{id}' rows="11" cols="40" style='width: 100%'>$tr->{nar_2}</textarea>
  </td>
  <td  bgcolor="#ffffEE" align="CENTER">
  Task code<BR>
  <input type="hidden" name="id_num_a[]" value="$tr->{id}">
  <select name="ledes_code $tr->{id}" size="1" value="">
.
foreach (sort keys %ledes_code) {
  if ($_ eq $tr->{ledes_code} ) {
$show .= << ".";
   <option selected value="$_">$_ $ledes_code{$_}</option>
.
  } else {
$show .= << ".";
   <option value="$_">$_ $ledes_code{$_}</option>
.
    
  }
}
$show .= << ".";
   </select>
   <BR>Activity code<BR>
  <select name="ledes_activity_code $tr->{id}" size="1" value="">
.
foreach (sort keys %ledes_activity_code) {
  if ($_ eq $tr->{ledes_activity_code} ) {
$show .= << ".";
   <option selected value="$_">$_ $ledes_activity_code{$_}</option>
.
  } else {
$show .= << ".";
   <option value="$_">$_ $ledes_activity_code{$_}</option>
.
    
  }
}
$show .= << ".";
  </td>
  <td colspan=4 bgcolor="#393949" align="center">
<!--   <input type='submit' value='submit'>-->
  </td>
 </tr>
.
 }


 my $show2 .= << ".";
  Set all LEDES code
  <select name="ledes_code_all" size="1" value=""  onChange="change_ledes_code(this);" >
.
foreach (sort keys %ledes_code) {
  $show2 .= << ".";
  <option value="$_">$_ $ledes_code{$_}</option>
.
  }

  $show2 .= << ".";
  </select>
.

### Get initials 
my $legal_services = $bill->{legal_services};


### add show Fee Earner Summary
my $fee_earner_table  .= << ".";
<table  cellpadding=3 cellspacing=0 border=1 width='50%'>
<caption>Fee Earner Summary</caption>
<tr>
<th>Fee Earner</th>
<th>Rate</th>
<th>Recorded Amount</th>
<th>Recorded Hours</th>
<th>Internal Hours</th>
<th width="120">Share</th>
<th>Bonus 4%</th>
</tr>
.

#foreach my $initials(sort keys %fee_earners){
### get Fee  farnner
#  $sql = qq{SELECT * FROM  hr WHERE hr.initials='$initials'};
#  my $sth = $dbh->prepare ($sql);
#  $sth->execute();
#  my $result =  $sth->fetchrow_hashref();
#  my $name   = $result->{'en_name_first'} . ' ' . $result->{'en_name_mid'} . ' ' . $result->{'en_name_last'};$name =~  s/  / /g;

### add work bonus total 4%
my $work_bonus_total = $legal_services *0.04;

### Get $total
$sql = qq{SELECT SUM(tr.internal_time * tr.rate * 1000) AS total FROM tr WHERE tr.deb_num =   '$deb_num' };
my $sth = $dbh->prepare ($sql);
$sth->execute();
my $result =  $sth->fetchrow_hashref();
my $total = $result->{'total'};

### Get initials legal service
  $sql = qq{SELECT  SUM(tr.internal_time * tr.rate * 1000) AS in_total,SUM(internal_time) AS internal,SUM(bill_time) AS bill,initials,rate  FROM tr WHERE tr.deb_num =  '$deb_num' GROUP BY initials,rate ORDER BY initials};
  my $sth = $dbh->prepare ($sql);
  $sth->execute();
  while (my $tr = $sth->fetchrow_hashref) {
   my $in_total = $tr->{in_total};
   my $internal = $tr->{internal};
   my $bill = $tr->{bill};
   my $initials = $tr->{initials};
   my $rate = $tr->{rate};
   my $share=0;
   if ($total >0) {
      $share = $in_total / $total * 100;
      $share = HR::commify($share,1,2);
   } 
   my $share_total = $work_bonus_total * $share /100 ;
   $share_total = HR::commify($share_total,1,2);
   $fee_earner_table  .= << ".";
<tr>
  <td> $initials </td>
  <td> $rate </td>
  <td> $in_total </td>
  <td> $bill </td>
  <td> $internal </td>
  <td> $share% </td>
  <td> $share_total</td>
</tr>


.

     
  }
   $fee_earner_table  .= << ".";
</table>
.
  
#}

my $vars = {
     fee_earner_table=>$fee_earner_table,
     list=>$show,
     list2=>$show2,
     case_num=>$case_num,
     in_case_num_dir => $in_case_num_dir,
     in_manager => $in_manager,
     count_1000 => $counts,
     bill => $bill,
 };


 $sql = qq{SELECT * FROM cases WHERE case_num = '$case_num'};  
 $sth = $dbh->prepare($sql);
 $rv = $sth->execute;
 my $case = $sth->fetchrow_hashref;

 $vars->{legal_services} = $bill->{legal_services};
 $vars->{disbs}          = $bill->{disbs};  
 $vars->{total}          = $bill->{total};

 $vars->{total}          = HR::commify ($bill->{total});
 $vars->{deb_num}        = $deb_num;
### 20221121 add cases table 
 $vars->{foreign_legal2} = $bill->{foreign_legal2};
 $vars->{currency2} = $bill->{currency2};
 $vars->{foreign_disbs2} = $bill->{foreign_disbs2};
 $vars->{foreign_total2} = $bill->{foreign_total2};
# ($vars->{legal_services2},$vars->{currency2},$vars->{flat_fee_foreign},$vars->{foreign_status}) = UT::get_legal_services_foreign($dbh, $case_num, 0, -1, 1);  
 $vars->{case}        = $case;
#die Dumper $vars;

# #check if this is likely to be a multiple fee trademark case
# if (($case->{case_num} =~ /\d\d\d\d\d/) and ($case->{case_type_name} eq 'TM')){
#    $vars->{create_action}  = 'bill_tm_fee_select_dev.pl';
#    $vars->{action_button} = 'Process TM Bill';
# }else{
#   $vars->{create_action} = 'bill_create.pl';
#   $vars->{action_button} = 'Create Bill';
# }

 $sql = qq[SELECT * FROM case_summaries  WHERE case_num = '$case_num'];
 $sth = $dbh->prepare($sql);
 $sth->execute();
 my $case_sum =  $sth->fetchrow_hashref;
 $vars->{narrative} = $case_sum->{narrative};
my $template = HR::get_template;
# $vars->{fee_earners}= build_fee_earner_table($dbh, $case_num);
 my $file = "bill_draft_update.html";
 $template->process($file, $vars)
     || die "Template process failed: ", $template->error(), "\n";


# sub build_fee_earner_table {
#   my $dbh = shift;
#   my $case_num = shift;
#   my $sql = qq{SELECT DISTINCT (show_initials) FROM tr WHERE case_num = '$case_num' AND show_flag = 1 AND billed_flag = -1 AND nocharge_flag = -1 ORDER by show_initials};
#   my $sth = $dbh->prepare($sql);
  
#   my $rv = $sth->execute;
#   my @fee_earners;
#   while (my $fee_earner_ref = $sth->fetchrow_hashref){
#       push (@fee_earners,$fee_earner_ref->{show_initials});
#   }

# my $fee_earner_table = ''; 
  
# for (@fee_earners){
#     $sql = qq{SELECT * FROM  hr WHERE hr.initials='$_'};
#     my $sth = $dbh->prepare ($sql);
#     $sth->execute();
#     my $result =  $sth->fetchrow_hashref();
#     my $name   = $result->{'en_name_first'} . ' ' . $result->{'en_name_mid'} . ' ' . $result->{'en_name_last'};$name =~  s/  / /g;
#     my $rate = (($result->{rank} *1000));
    
#     $sql = qq{SELECT DISTINCT(show_rate) FROM  tr WHERE initials='$_' 
#                AND case_num = '$case_num' };
#     $sth = $dbh->prepare ($sql);
#     my $rv = $sth->execute();
#     if ( $rv == 1) {
#      my $result =  $sth->fetchrow_hashref();
#     if (($result->{show_rate} * 1000) !=  $rate) {
#        $rate = ($result->{show_rate} * 1000);
#          }
#    }
    
#      $rate = HR::commify($rate);
#      $name =~  s/Peterpan/Peter/g;
#     $sql = qq{SELECT sum(charge) FROM tr WHERE case_num = '$case_num' AND show_initials = '$_' AND show_flag = 1 AND nocharge_flag = -1 AND billed_flag=-1};
#     $sth = $dbh->prepare($sql);
#     $sth->execute;
#     my $tr_hrs = $sth->fetchrow_hashref;
#     my $total_hrs = $tr_hrs->{sum};
#     $sql = qq{SELECT sum(charge * show_rate * 1000) FROM tr WHERE case_num = '$case_num' AND show_initials = '$_' AND show_flag = 1 AND nocharge_flag = -1 AND billed_flag= -1};
#      $sth = $dbh->prepare($sql);
#      $sth->execute;
#      my $tr_sum = $sth->fetchrow_hashref;
#      $tr_sum->{sum} =~ s/\.00$//;
#      $tr_sum->{sum}  = HR::commify($tr_sum->{sum});

#     $fee_earner_table  .= << ".";
# <tr>
# <td> $name </td>
# <td align="right"> $rate </td>
# <td align="right"> $total_hrs </td>
# <td align="right"> $tr_sum->{sum}</td>
# </tr>
# .
# }

# return $fee_earner_table;
# }
