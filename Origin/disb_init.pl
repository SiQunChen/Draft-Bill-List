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
use Time::HiRes qw(gettimeofday);
use Storable;
use HR;
use Data::Dumper;
my $tm = localtime;
my $query = new CGI;
$query->charset('utf8');

print $query->header(-charset=>'utf8');
my $params = $query->Vars;
my $case_num = $query->param('case_num');
$case_num = uc($case_num);
my $mode = $query->param('mode');
my $deb_num = $query->param('deb_num');
my $bill_status = -1;
if ($mode =~  /update/) {$bill_status = 0};

my $dbh = DBI->connect("dbi:Pg:dbname=qicom", 'postgres');
unless ($dbh){die " no connection"}

my $sql = '';
if ($case_num =~ /%$/) {
  $sql = qq{    SELECT * FROM disbursements
                 WHERE case_num LIKE '$case_num'
		 AND billed_flag = -1
                 ORDER BY case_num
 	     };
}else{
##### add billed_flag detect  => start
  if ($mode =~  /update/) 
  {
     $sql = qq{  SELECT * FROM disbursements
                 WHERE case_num = '$case_num'
                 AND ((billed_flag = 0 AND deb_num='$deb_num') OR ( deb_num IS NOT  NULL AND billed_flag =2))
                 ORDER BY date DESC
 	     };
#		 AND billed_flag = $bill_status OR ( deb_num NOT IS NULL AND billed_flag =2)
  } else {
     $sql = qq{  SELECT * FROM disbursements
                 WHERE case_num = '$case_num' AND (deb_num IS NULL OR deb_num='')
		 AND (billed_flag = $bill_status  or billed_flag = 2)
                 ORDER BY date DESC
 	     };
  }
}
##### add billed_flag detect  => end

my $sth = $dbh->prepare($sql);
$sth->execute();
my $rows = $sth->rows;


my @DISBS      = ();
my @DISB_NAMES = ();
while (my $disb = $sth->fetchrow_hashref){
  $disb->{ntd_amount} = HR::commify($disb->{ntd_amount});
  push (@DISBS, $disb);
  
}

$sql = qq{       SELECT SUM(ntd_amount) FROM disbursements
                 WHERE case_num = '$case_num' AND show_flag = 1
		 AND billed_flag = $bill_status
 	     };

$sth = $dbh->prepare($sql);
$sth->execute();
my $ntd_total = ($sth->fetchrow_array)[0];

my $file = 'disb_bill.html';
my $template = HR::get_template;
my $dates =  $query->cookie('dates');

my %vars = (
	    disbs         => \@DISBS,
            disb_names    => \@DISB_NAMES,
	    ntd_total     => $ntd_total,
	    records       => $rows,
            case_num      => $case_num,
            mode          =>$params->{mode}, 
            select_mode   =>$params->{select_mode},
            initials      =>$params->{initials}, 
            all_cases     =>$params->{all_cases}, 
            in_case_num   =>$params->{in_case_num}, 
            ar            =>$params->{ar}, 
            mid           =>$params->{mid}, 
	    );
if ($sql =~/%/)  { $vars{case_num} = $case_num};
$template->process($file, \%vars)
    || die "Template process failed: ", $template->error(), "\n";
