#!/usr/bin/perl -w

use DBI;
use CGI;
use CGI::Carp qw(fatalsToBrowser);
use HR;
use UT;
use Template;
use strict;
use Data::Dumper;

my $query = new CGI;
my $id = $query->param('id');
my $deb_num = $query->param('deb_num');
my $in_case_num_dir = $query->param('in_case_num_dir');
my $in_manager = $query->param('in_manager');

my $refer_url = "../cgi-bin/bill_mod.pl?id=$id&deb_num=$deb_num";

my     $cookie = $query->cookie(-name=>'refer_url',
			     -value=>$refer_url,
			     -expires=>'+6h',
			     );

print $query->header(
-type => 'text/html',
-charset => 'utf8',
		     );

print $query->start_html(
     -title => 'Modify Bill',
     -head => CGI::meta ({-http_equiv => 'Content-Type',
     -content => 'text/html; charset=utf8' ,
					  })
			 );


#print $query->start_html(-style=>{'src'=>'stylesheet/wp.css'},
			# -title=>"Modify Bill"
                        #  );

#$query->charset('UTF-8');




my $dbh = HR::DBConnect();
my $template = HR::get_template();


#my $sql = "SELECT * FROM bills where id= '$id'";
my $sql = " SELECT cases.billing_currency,bills.* FROM bills LEFT JOIN cases ON ( bills.case_num=cases.case_num ) where id= '$id'";

my $sth = $dbh->prepare($sql);
my $rv = $sth->execute;
unless ($rv) { die "Read bills error "}
my $bill = $sth->fetchrow_hashref;


### get dis_ledes_code table data
 my $case_num3=substr($bill->{case_num},0,3);
 my $sql = "SELECT * FROM dis_ledes_code WHERE case_num='$case_num3' ORDER BY dis_ledes_code ";

 my $sth = $dbh->prepare($sql);
 my $rv = $sth->execute;
 my @dis_ledes_code;
 while (my $disb_ledes = $sth->fetchrow_hashref) {
   push @dis_ledes_code,$disb_ledes;
 }


my $deb_num = $query->param('deb_num');

$sql = qq{SELECT * FROM disbursements 
	  WHERE deb_num ='$deb_num' ORDER BY id
	 };

$sth = $dbh->prepare($sql);
$sth->execute;
my @DISBS = ();
while (my $disb = $sth->fetchrow_hashref) {
push @DISBS, $disb;
}	     


if ($bill->{case_num} =~ /^MID/ || $bill->{case_num} =~ /^NUP/ || $bill->{case_num} =~ /^MFN/ || $bill->{case_num} =~ /^SNL/ || $bill->{case_num} =~ /^MFT/) {
    $bill->{mid_flag} = 1;
    }
my $vars = {bill => $bill,
            result => \@DISBS,
            in_case_num_dir => $in_case_num_dir, 
            in_manager => $in_manager, 
            dis_ledes_code => \@dis_ledes_code, 
	   };
#die Dumper $vars;
my $file = 'bill_mod.html';
  $template->process($file, $vars)
    || die "Template process failed: ", $template->error(), "\n";
