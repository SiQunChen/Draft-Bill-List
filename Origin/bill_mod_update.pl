#!/usr/bin/perl -w

use DBI;
use DBD::Pg;
use CGI;
use CGI::Carp qw(fatalsToBrowser);
use HR;
use UT;
use Template;
use strict;
use Data::Dumper;

my $query = new CGI;
print $query->header(-charset=>'UTF-8');
print $query->start_html(-style=>{'src'=>'stylesheet/wp.css'},
			 -title=>"Modify Bill");


my $dbh = HR::DBConnect();
unless ($dbh) {die "No db connection."};
my $template = HR::get_template();
my $params = $query->Vars;
$params->{trans_services} =~ s/\.00//g;

### add detect ''
### 202211119 disable
# if ($params->{sent} eq '')
# {
#    $params->{sent}="'NULL'";
# }
# if ($params->{trans_services} eq '')
# {
#    $params->{trans_services}="'NULL'";
# }

my $in_manager=$params->{in_manager};
my $in_case_num_dir=$params->{in_case_num_dir};
my $sth = HR::update('bills',$params,$dbh); 
my $rv = $sth->execute;

if ($rv ==1 ) {
 my $url = "../cgi-bin/bill_mod.pl?id=$params->{id}&deb_num=$params->{deb_num}&in_manager=$in_manager&in_case_num_dir=$in_case_num_dir";

 my %vars = (url => $url,
	     );
my $vars = \%vars;
 my $file = 'back_to_slash.html';
  $template->process($file, $vars)
    || die "Template process failed: ", $template->error(), "\n";
}else {
    die $dbh->errstr;

}

