#!/usr/bin/perl -w
use DBI;
use DBD::Pg;
use CGI;
use CGI::Carp qw(fatalsToBrowser);
use HR;
use Template;
use Time::localtime;
use Data::Dumper;
use strict;
my $dbh = HR::DBConnect();
my $query = new CGI;
print $query->header;
my $params = $query->Vars;

#unless ($params->{show_flag}) {$params->{show_flag} = -1};
if ($params->{show_flag} == -1 ){
  $params->{nocharge_flag} = 1;
##### add billed_flag
  $params->{billed_flag} = 2;
} else {
  $params->{show_flag} = 1;
  $params->{nocharge_flag} = -1;
##### add billed_flag
  if ($params->{deb_num} ne '')
  { 
     $params->{billed_flag} = 0;
  } else {
     $params->{billed_flag} = -1;
  }
}

unless ($params->{show_as_legal_service_flag}) {$params->{show_as_legal_service_flag} = -1};

#unless ($params->{disb_code} =~/^2/) {die "Please begin all codes with 2."}
$params->{summary} =~ s/'/''/g;
my $sql = qq[SELECT * FROM cases  WHERE case_num = '$params->{case_num}'];
my $sth = $dbh->prepare($sql);
$sth->execute();
my $rv = $sth->rows;
#unless ($rv == 1) { warn "$params->{case_num} is not a valid case number."};
my $case =  $sth->fetchrow_hashref;
$params->{ntd_amount} =~ s/,//g;

### 20220809 PH add check_bills
if ( $params->{check_bills} != 1) {
  $params->{check_bills} =0;
}

##### search disb_name 
my $sql = qq[SELECT * FROM disb  WHERE disb_code = '$params->{disb_code}'];
my $sth = $dbh->prepare($sql);
$sth->execute();
my $disb_names =  $sth->fetchrow_hashref;
######  get disbursements value => start
 my $sql = qq[SELECT * FROM disbursements WHERE id = '$params->{id}'];
 my $sth = $dbh->prepare($sql);
 my $result = $sth->execute;
 my $disbs = $sth->fetchrow_hashref;
######  get disbursements value => end


# add $params->{nocharge_flag}
my $sql = qq[UPDATE disbursements
       SET case_num = '$params->{case_num}',
           date   =  '$params->{date}',
          disb_code   =  $params->{disb_code},
          disb_name   =  '$disb_names->{disb_name}',
         ntd_amount   =  $params->{ntd_amount},
        initials  =     '$params->{initials}',
        narrative =   '$params->{narrative}',
        show_flag     = $params->{show_flag},
        nocharge_flag  = $params->{nocharge_flag},
        billed_flag  = $params->{billed_flag},
        currency2  = '$params->{currency2}',
        foreign_amount2  = '$params->{foreign_amount2}',
        show_as_legal_service_flag = $params->{show_as_legal_service_flag},
        check_bills = $params->{check_bills}
        WHERE id = $params->{id};

];
#die Dumper $params;
#$sth = HR::update('disbursements', $params, $dbh);
$sth = $dbh->prepare ($sql);
$rv = $sth->execute();

##### send mail for deleted disbursements content
    $params->{notes}=$disbs->{notes};
    $params->{disb_name}=$disb_names->{disb_name};
    $params->{paydate}=$disbs->{paydate};
    $params->{invoice_date}=$disbs->{invoice_date};
    $params->{counsel_invoice}=$disbs->{counsel_invoice};

### add get IP address
        my $addr = $ENV{'REMOTE_ADDR'};
        my $ip_addr=HR::ip_addr();
        $disbs->{ip_name} = $ip_addr->{$addr};
        $disbs->{ip_addr} = $addr;


#    HR::mail_to ($disbs,$params, "","disbs_update");



##### count bills table disbs field => start

if ($params->{deb_num} ne '')
{
   $sql = qq{SELECT SUM (ntd_amount) AS ntd_total,SUM(foreign_amount2) AS foreign_total FROM
             disbursements
             WHERE billed_flag = 0
             AND show_flag = 1
             AND nocharge_flag='-1'
             AND case_num = '$params->{case_num}'
             AND deb_num = '$params->{deb_num}'};
   $sth = $dbh->prepare ($sql);
   $rv = $sth->execute();
   my $disb = $sth->fetchrow_hashref;
#   unless ($disb->{sum}) {$disb->{sum} = 0};
   unless ($disb->{ntd_total}) {$disb->{ntd_total} = 0};
   unless ($disb->{foreign_total}) {$disb->{foreign_total} = 0};

   $sql = qq{UPDATE bills SET disbs= $disb->{ntd_total},foreign_disbs2=$disb->{foreign_total}
             WHERE deb_num = '$params->{deb_num}'};
   $sth = $dbh->prepare ($sql);
   $rv = $sth->execute();
}

##### count bills table disbs field => end

#die Dumper $params;

print qq[You updated the disbursement. <p>Back to <a href="../cgi-bin/bill_draft_list.pl">draft bill list</a>
<p> Back to <a href="../cgi-bin/disb_init.pl?case_num=$params->{case_num}&select_mode=$params->{select_mode}&initials=$params->{in_initials}&in_case_num=$params->{in_case_num}&all_cases=$params->{all_cases}&ar=$params->{ar}&mid=$params->{mid}">Edit Disbursements</a>
];
