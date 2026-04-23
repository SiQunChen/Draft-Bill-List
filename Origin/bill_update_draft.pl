#!/usr/bin/perl -w

use strict;
use File::Copy;
use Date::Calc qw(:all);
use Time::localtime;
use CGI;
use CGI::Carp qw(fatalsToBrowser);
use DBI;
use DBD::Pg;
use Time::Local;
use File::stat;
use Template;
use HR;
use UT;
use Storable;
use Data::Dumper;
use Math::Round;

my $query = new CGI;
$query->charset('utf8');
print $query->header(-charset=>'utf8');
my $params = $query->Vars;
my %seen;
my @tr_ids = ();
for (keys %$params) {

  if (/^id/){
    my @id = split (/ /,$_);
    unless($seen{$id[1]}) {
      $seen{$id[1]} = 1;
      push (@tr_ids,$id[1]);  
    }
  }else{
    next;
  }
}
 my @trs = ();
for (@tr_ids) {
  my %record = ();
  my $rec_ref = \%record;
  my $id = $_;
  my $seen_nocharge_flag = 0; 
  for (keys %$params){
    if (/$id/){ #this is one of our values
    if (/nocharge_flag/) { $seen_nocharge_flag =1} 
      my @field = split / /, $_;
      $rec_ref->{$field[0]} = $params->{$_};
    }
  }
  unless  ($seen_nocharge_flag) {$rec_ref->{nocharge_flag} = -1};
 push (@trs,$rec_ref);
 
}
#die Dumper @trs;
my $dbh = DBI->connect("dbi:Pg:dbname=qicom", 'postgres');
unless ($dbh){die " no connection"}


for  (@trs) {
  my $tr = $_;
  unless ($tr->{show_flag}){$tr->{show_flag} = -1}
  #unless ($tr->{nocharge_flag}){$tr->{nocharge_flag} = 1}
  
  my $sql_sth = HR::update('tr', $tr, $dbh);
  #print $sql_sth, '<p>';
  my $rv = $sql_sth->execute();
  unless ($rv) {die "Failed to update time record $tr"}
}

update_bill ($dbh,$query->url_param('deb_num'),$query->url_param('in_case_num_dir'),$query->url_param('in_manager'));

sub update_bill {
  my $dbh = shift;
  my $deb_num = shift;
  my $in_case_num_dir = shift;
  my $in_manager = shift;
  #retrieve the bills
  # First, get the case
  my $sql = qq {SELECT * FROM bills
            WHERE deb_num = '$deb_num'
           };

  my $sth = $dbh->prepare($sql);
 
  my $rv = $sth->execute;
  unless ($rv) { die "Could not retrieve the bill $deb_num. Please contact
                     System Administrator immediately."}
  my $bill = $sth->fetchrow_hashref;
  #calculate legal services

 my ($legal_services, $flat_fee,$legal_services_foreign, $flat_fee_foreign) = UT::update_legal_services($dbh, $deb_num, 0, -1, 1, $bill);
 unless ($legal_services) {$legal_services = 0};
 unless ($flat_fee) {$flat_fee = 0};
 unless ($legal_services_foreign) {$legal_services_foreign = 0};
 unless ($flat_fee_foreign) {$flat_fee_foreign = 0};

 #recalculate the disbursements
 $sql =    qq{SELECT coalesce(SUM(ntd_amount),0) AS ntd_disbs,coalesce(SUM(foreign_amount2),0) AS foreign_disbs FROM 
 	    disbursements 
 	    WHERE billed_flag = 0 
            AND nocharge_flag = -1
 	    AND  deb_num = '$deb_num'};

  $sth = $dbh->prepare($sql);
  $rv = $sth->execute;
  my $disb = $sth->fetchrow_hashref;
  # First, get the case
  $sql = qq {SELECT * FROM cases
             WHERE case_num = '$bill->{case_num}'
           };

  $sth = $dbh->prepare($sql);
 
  $rv = $sth->execute;
  unless ($rv) { die "Could not retrieve the case $bill->{case_num}. Please contact
                     System Administrator immediately."}
  my $case = $sth->fetchrow_hashref;
    my $disb_total = 0;
    my $disb_total_foreign = 0;
    unless ($case->{includes_disbursements}) { 
    $disb_total  =  $disb->{ntd_disbs} ;
    $disb_total_foreign  =  $disb->{foreign_disbs};

   };
 
   my $total = 0;
   my $usd_total =0 ;
   my $foreign_total =0 ;
   if ( $bill->{billing_currency} eq 'English (USD)' || $bill->{billing_currency} eq 'English (EUR)' ) {
     $legal_services = round($bill->{x_rate2} * $legal_services_foreign) ;

   }
  
   if($case->{flat_fee}) {
     $legal_services = $flat_fee;
     $legal_services_foreign = $flat_fee_foreign;
     $total = $disb_total + $flat_fee+$bill->{trans_services};
   }else {
    $total = $disb_total + $legal_services + $bill->{trans_services};
    $foreign_total = $disb_total_foreign + $legal_services_foreign ;
   }

   my $month = UT::get_current_month();


### PH modify all cases recount USD 
#   if ($case->{bill_country} == 0) {
   if (1) {
     my $rate =  $bill->{x_rate};
     $usd_total = $total/$rate if ($rate);
     unless ($disb_total) {$disb_total = 0} 
     $sql = qq[UPDATE bills SET  undiscounted_legal_services = $legal_services,
                                 disbs         =  $disb_total,
                                 total         =  $total,
                                 usd_total     =   $usd_total,
                                 foreign_undiscount_legal2 = $legal_services_foreign,
                                 foreign_disbs2 = $disb_total_foreign
               WHERE id = '$bill->{id}'
               ];                                
#die Dumper $legal_services, $flat_fee,$total,$disb_total,$bill->{trans_services},$sql; 
    }else { # omit USD total 
  $legal_services = $case->{flat_fee_nt_amount} 
                    if ($case->{flat_fee_nt_amount} > 0); 
  $sql = qq[UPDATE bills SET  legal_services = $legal_services,
                                 disbs         =  $disb_total,
                                 total         =  $total
                         WHERE id = '$bill->{id}'
               ];  


    
   }
   
   $sth = $dbh->prepare($sql);
  # $sql =~ s/\n/ /g;
#   $sql =~ s/\s\s/ /;
#   die $sql;  
  
  
$rv = $sth->execute;
  
   unless($rv) {
    
     die "Failed to generate and insert new bill. 
        Contact database administrator immediately";
   }
  
  
#print "Bill $deb_num has been updated.<p> The old values were:<p>";
#print Dumper $bill;
  $sql = qq {SELECT * FROM bills
            WHERE deb_num = '$deb_num'
           };

  $sth = $dbh->prepare($sql);
 
  $rv = $sth->execute;
  $bill = $sth->fetchrow_hashref;
#  print "<p>The new values are :<p>";
#  print Dumper $bill;
  print "<p>";
  print "Return to <a href='http://$ENV{SERVER_NAME}/cgi-bin/bill_draft_list.pl?case_manager=$in_manager&case_num=$in_case_num_dir'>Draft bill list</a>";
#  #now update the time records.
#   # set tr.billed_flag to 0. This shows that these are draft bills
  
  
#   $sql = qq{UPDATE tr SET billed_flag=0, 
# 	    deb_num = '$deb_num'
#             WHERE billed_flag = -1
#             AND case_num= '$case_num'
#             };
#   $sql =~ s/\n/ /g;
#   $sql =~ s/\s\s/ /g;
#   $sth = $dbh->prepare($sql);
#   $rv = $dbh->do($sql);
  
#   unless($rv) {
  
#     die "Failed to update time records with debit note number and billing flag. 
#        Contact database administrator immediately";
# }

#   #now update the disbursements.
#   # set disbursements.billed_flag to 0. This shows that these are draft bills


#   $sql = qq{UPDATE disbursements SET billed_flag=0, 
#             deb_num = '$deb_num'
#             WHERE billed_flag = -1
#             AND case_num= '$case_num'
#             AND nocharge_flag =  -1
#             AND show_flag = 1};$sql =~ s/\n/ /g;

#   # $sql =~ s/\n/ /g;
#   # $sql =~ s/\s\s/ /g;
#   # die $sql;
#   $sth = $dbh->prepare($sql);
#   $rv = $dbh->do($sql);

#   unless($rv) {
  
#     die "Failed to update disbursements with debit note number and billing flag. 
#        Contact database administrator immediately";
#   }
  
  
#   my $template = Template->new({
# 				# where to find template files
# 				# pre-process lib/config to define any extra values
# 				INCLUDE_PATH => Template::Config->instdir('templates'),
# 				PRE_PROCESS  => 'splash/config',
# 				ABSOLUTE => 1,
# 			       });
#   my $initials = $query->param('initials');
#   my $url = "http://translation.wpoffice.com/cgi-bin/cases_managed.pl?initials=$initials";
  
#   my %vars = (url => $url
# 	     );
  
#   my $vars = \%vars;
#   my $file = '/var/www/html/back_to_slash.html';
#   $template->process($file, $vars)
#     || die "Template process failed: ", $template->error(), "\n";
# my  $sender = new Mail::Sender;
#   $sender-> MailMsg({to => 'mf,bi',
# 		   subject => "Draft Bill $deb_num For $case_num Created",
# 		   msg => "Case Manager $initials has just created a draft bill for $case_num with debit number $deb_num. Please finalize the bill by clicking http://translation.wpoffice.com/cgi-bin/bill_list.pl?case_num=$case_num.",
# 		   }

# 		 );
# print "$Mail::Sender::Error\n";

}

sub create_tm_bill {

 my $query = shift;
 my $params = shift;
 my $deb_num = insert_deb_num ($query); 
 my $case_num = $query->param('case_num');
  my $sql = qq {SELECT * FROM cases
            WHERE case_num = '$case_num'
           };

  my $sth = $dbh->prepare($sql);
 
  my $rv = $sth->execute;
  unless ($rv) { die "Could not retrieve the case $case_num. Please contact
                     System Administrator immediately."}
  my $case = $sth->fetchrow_hashref;
  my $legal_services = $params->{legal_services};
  $sql = qq{SELECT SUM (ntd_amount) FROM 
	    disbursements 
	    WHERE billed_flag = -1 
	    AND case_num = '$case_num'};
  
  $sth = $dbh->prepare($sql);
  $rv = $sth->execute;
  my $disb = $sth->fetchrow_hashref;
  
  unless ($disb->{sum}) {$disb->{sum} = 0}; 

  if($case->{includes_disbursements}) {  
    $disb->{sum}  =  0; 

  };
  my $total = $disb->{sum} + $legal_services;
  # Convert to USD if necessary
  my $usd_total =0 ;
  my $month = UT::get_current_month();
 
  $params->{tm_narrative} =~ s/'/''/g;
  if ($case->{bill_country} == 0) {
    
    my $rate =  UT::get_x_rate;
    $usd_total = $total/$rate if ($rate);
    $usd_total = $case->{flat_fee_usd_amount} if ( $case->{flat_fee_usd_amount});
    $sql = qq{INSERT INTO bills (case_num, deb_num, 
				 legal_services, disbs, total , usd_total, month, bill_narrative)
	                         VALUES ('$case_num', '$deb_num', $legal_services, 
		                 $disb->{sum}, $total, $usd_total, 
                                 $month, '$params->{tm_narrative}')
	     };
  
  }else { # omit USD total 
    $legal_services = $case->{flat_fee_nt_amount} if $case->{flat_fee_nt_amount}; 
    $sql = qq{INSERT INTO bills (case_num, deb_num, 
				 legal_services, disbs, total, month, bill_narrative)
	                          VALUES ('$case_num', '$deb_num', $legal_services, 
		                  $disb->{sum}, $total, $month, '$params->{tm_narrative}')
              };

    
  }
 


 $sql =~ s/\n/ /g;
 $sql =~ s/\s\s+/ /g;
  $sth = $dbh->prepare($sql);
  $rv = $dbh->do($sql);
  

  unless($rv) {
    
    die "Failed to generate and insert new bill. 
       Contact database administrator immediately: $rv  $DBI::errstr\n";
  }
  
  
  


 #now update the time records.
  # set tr.billed_flag to 0. This shows that these are draft bills
  
  
  $sql = qq{UPDATE tr SET billed_flag=0, 
	    deb_num = '$deb_num'
            WHERE billed_flag = -1
            AND case_num= '$case_num'
            AND nocharge_flag =  -1
            AND show_flag = 1};
  $sql =~ s/\n/ /g;
  $sql =~ s/\s\s/ /g;
  $sth = $dbh->prepare($sql);
  $rv = $dbh->do($sql);
  
  unless($rv) {
  
    die "Failed to update time records with debit note number and billing flag. 
       Contact database administrator immediately";
}

  #now update the disbursements.
  # set disbursements.billed_flag to 0. This shows that these are draft bills


  $sql = qq{UPDATE disbursements SET billed_flag=0, 
            deb_num = '$deb_num'
            WHERE billed_flag = -1
            AND case_num= '$case_num'
            AND nocharge_flag =  -1
            AND show_flag = 1};$sql =~ s/\n/ /g;

  # $sql =~ s/\n/ /g;
  # $sql =~ s/\s\s/ /g;
  # die $sql;
  $sth = $dbh->prepare($sql);
  $rv = $dbh->do($sql);

  unless($rv) {
  
    die "Failed to update disbursements with debit note number and billing flag. 
       Contact database administrator immediately";
  }
  
  
  my $template = HR::get_template;
  my $initials = $query->param('initials');
  my $url = "../cgi-bin/cases_managed.pl?initials=$initials";
  
  my %vars = (url => $url
	     );
  
  my $vars = \%vars;
  my $file = 'back_to_slash.html';
  $template->process($file, $vars)
    || die "Template process failed: ", $template->error(), "\n";

}
 sub insert_deb_num {
  my $query = shift; 
  my $date  = UT::get_todays_date();
  my $month = UT::get_current_month();
  my $year = UT::get_current_year();
  my $days  = Days_in_Month($year, $month);
  my $sql =  qq {SELECT MAX(month_num) FROM deb_nums
               WHERE date BETWEEN '$year-$month-01'
               AND '$year-$month-$days';
              };


  my $sth = $dbh->prepare($sql);
  my $rv = $sth->execute;
  my $deb_note_num = $sth->fetchrow_hashref;
  my $month_num = 0;
  if ($deb_note_num->{max} && $deb_note_num->{max} != 202108999 ) {
    $month_num = ++$deb_note_num->{max}; 
  }else {
    # first deb number this month
    my $month = $month; 
    if ( $deb_note_num->{max} == 202108999 ) {
      $month =9;
    }

    $month =~ s/^(\d)$/0$1/;
#    $month_num = $year . $month . '001';
    $month_num = $year . $month . '0001';
  } 

  my $deb_num = 'A' . $month_num;
  my $case_num = $query->param('case_num');
  $case_num = uc($case_num);

  $sql = qq{INSERT INTO deb_nums (case_num, deb_note_num, deb_note_type, month_num )
	    VALUES ('$case_num','$deb_num', 'A', $month_num)
	   };
  $rv = $dbh->do($sql);
  
  unless($rv) {
    
    die "Failed to generate and insert new reference number. 
       Contact database administrator immediately";
  }
return $deb_num;
}
