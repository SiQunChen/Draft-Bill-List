<html lang="">
 <head>
  <meta charset="utf-8">
 </head>
 <body>

<?php
#receipt_email.php

  require("/var/www/receipt_sec/db.ini");
#  require("/var/www/receipt_sec/db23.ini");
  require("/var/www/receipt_sec/utility.php");

  $submit=$_POST["action"];
  $initials=$_POST["initials"];
  $notes=$_POST["notes"];
### 判斷是否是 Split
  if ( $submit !='Split' ) {
    $deb_nums=$_POST["deb_nums"];
  } else {
    $deb_num=$_POST["deb_num"];
    $case_num=$_POST["case_num"];
    $split_entity=$_POST["split_entity"];
    $split_deb_num=$_POST["split_deb_num"];
    $split_legal_services=$_POST["split_legal_services"];
    $split_disbs=$_POST["split_disbs"];
  }
  $sec_id=1; 

//執行POSTGRESQL的連結
  $dblink = @pg_pconnect(DB_CONNECT);
  if (!$dblink) {
    DB_error_message("資料庫連線失敗");
    exit(0); //exit program normally exit; exit();
  }

#echo "initials=$initials <BR>";
#echo "notes=$notes <BR>";
#print_r ($deb_nums);
#exit;

### 當不是 Split 要執行
  if ( $submit !='Split' ) {
    if ( preg_match("/apply_ckBx/",$deb_nums[0]) ) {
      $ids=$deb_nums;
      $deb_nums = array();
      $case_nums = array();
#print_r($ids);
      foreach ( $ids as $ida ) {
        $id = preg_replace("/apply_ckBx /",'',$ida) ;
        $sql = "SELECT case_num,deb_num FROM bills WHERE id='$id'";
#echo "$sql<BR>";
//執行postgresql query
        $result=@pg_query($dblink,$sql);
        if (!$result) {
          DB_error_message("SQL 執行失敗",pg_last_error($dblink));
          exit(); //exit program normally exit; exit(); exit(0);
        }

        if ($row = pg_fetch_array($result)) {
          array_push($case_nums , $row[0]);
          array_push($deb_nums , $row[1]);
        }
#      echo "r=>$result <BR>";
#      array_push($deb_nums , preg_replace("/apply_ckBx /",'',$id) );
      }
#echo "<BR>";
#print_r($case_nums);

    }
  }
#echo "<BR>";
#exit;

### Insert receipt table SQL
  $sql = " INSERT INTO receipt_sec(initials,note) values('$initials','$notes')";
#echo "$sql<BR>";

//執行postgresql query
  $result=@pg_query($dblink,$sql);
  if (!$result) {
    DB_error_message("SQL 執行失敗",pg_last_error($dblink));
    exit(); //exit program normally exit; exit(); exit(0);
  }

### get SQL
  $sql = "SELECT currval('receipt_sec_id_seq')";
#echo "$sql<BR>";

//執行postgresql query
  $result=@pg_query($dblink,$sql);
  if (!$result) {
    DB_error_message("SQL 執行失敗",pg_last_error($dblink));
    exit(); //exit program normally exit; exit(); exit(0);
  }

  if ($row = pg_fetch_array($result)) {
    $sec_id = $row[0];
  } else {
    echo " receipt_sec Table can't obtain id data<BR>" ;
  }

#print "sec_id = $sec_id <BR>";
  
### 判斷是否是 Split
  if ( $submit !='Split' ) {
    $display_deb_num=''; 
    foreach ( $deb_nums as $deb_num ) {
      $sql = "SELECT case_num FROM bills WHERE deb_num='$deb_num'";
//執行postgresql query
      $result=@pg_query($dblink,$sql);
      if (!$result) {
        DB_error_message("SQL 執行失敗",pg_last_error($dblink));
        exit(); //exit program normally exit; exit(); exit(0);
      }

       if ($row = pg_fetch_array($result)) {
         $case_num = $row[0];
       }
      $display_deb_num = $display_deb_num.$case_num.' , '.$deb_num.'<BR>';
      $sql = " INSERT INTO receipt_sec_deb(sec_id,deb_num) values('$sec_id','$deb_num')";
#echo "$sql<BR>";

//執行postgresql query
      $result=@pg_query($dblink,$sql);
      if (!$result) {
        DB_error_message("SQL 執行失敗 $sql",pg_last_error($dblink));
        exit(); //exit program normally exit; exit(); exit(0);
      }
    } 
    $body="<html lang=\"\">
<head>
<meta charset=\"utf-8\"></head>
<body>Hi Frances,<BR><BR> 
$initials 申請開立收據，申請單號為  $sec_id ，案號及帳單號碼如下：<BR>$display_deb_num <BR>
備註：$notes
</body></html>";

  } else {
    $display_data='';
    for ($i=0;$i<sizeof($split_entity);$i++ ) {
      $entity_a = $split_entity[$i];
      $deb_num_a = $split_deb_num[$i];
      $legal_services_a = $split_legal_services[$i];
      $disbs_a = $split_disbs[$i];
      $sql = " INSERT INTO receipt_sec_deb(sec_id,deb_num,split_entity,split_deb_num,split_legal_services,split_disbs) values('$sec_id','$deb_num','$entity_a','$deb_num_a','$legal_services_a','$disbs_a')";

//執行postgresql query
      $result=@pg_query($dblink,$sql);
      if (!$result) {
        DB_error_message("SQL 執行失敗 $sql",pg_last_error($dblink));
        exit(); //exit program normally exit; exit(); exit(0);
      }
      $display_data = $display_data."Entity: $entity_a ";
      $display_data = $display_data.", $deb_num$deb_num_a ";
      $display_data = $display_data."Legal Services: $legal_services_a ";
      $display_data = $display_data."Disbursements: $disbs_a<BR>";
    }
    $body="<html lang=\"\">
<head>
<meta charset=\"utf-8\"></head>
<body>Hi Frances,<BR><BR> 
$initials 申請開立收據，申請單號為  $sec_id ，案號：$case_num 帳單號碼： $deb_num ，單一帳單分割出收據如下：<BR>$display_data <BR>
備註：$notes
</body></html>";
  }

  $email = $initials.'@winklerpartners.com';
  $notes = preg_replace('/\n/','<BR>',$notes);

  $email_status = receipt_email("申請開立收據開立通知信。申請人：$initials ，申請單號：$sec_id ","$body",$email,$initials);
  if ( $email_status ==1 ) {
    echo "資料寫入完畢，郵件發信成功 <BR>";
  } else if ( $email_status == 0 ) {
    echo "資料寫入完畢，郵件發信失敗<BR>";
  }


  function receipt_email($subject,$body,$toi,$initials) {
    $email_status=1;
    require_once("/var/www/case_list/PHPMailer_5.2.4/class.phpmailer.php");
    require_once("/var/www/case_list/PHPMailer_5.2.4/class.smtp.php");

    $mail = new PHPMailer();
    $mail->IsSMTP();
    $mail->SMTPAuth = true; //設定SMTP需要驗證
    $mail->SMTPSecure = "ssl"; // Gmail的SMTP主機需要使用SSL連線
    $mail->Host = "smtp.gmail.com"; //Gamil的SMTP主機
    $mail->Port = 465; //Gamil的SMTP主機的SMTP埠位為465埠。

    $mail->Username = "phsiao@winklerpartners.com"; //設定驗證帳號
    $mail->Password = "epukwgevjwlmchrk"; //設定驗證密碼
    $mail->From = "phsiao@winklerpartners.com"; //設定寄件者信箱
    $mail->FromName = "Phsiao"; //設定寄件者姓名

//設定收件者
    $mail->AddAddress("$to",$initials);
    $mail->AddAddress("phsiao@winklerpartners.com",'Pon');
    $mail->AddAddress("fchang@winklerpartners.com",'Frances');
    $mail->AddAddress("$toi","$initials");

//設定密件副本
//$mail->AddBCC("55555@abc.com");

//設定信件字元編碼
    $mail->CharSet="utf-8";
//設定信件編碼，大部分郵件工具都支援此編碼方式
    $mail->Encoding = "base64";
//設置郵件格式為HTML
    $mail->IsHTML(true);
//每50自斷行
    $mail->WordWrap = 50;

//傳送附檔
//$mail->AddAttachment("/var/www/case_list/data/$filename");
//傳送附檔的另一種格式，可替附檔重新命名
//$mail->AddAttachment("upload/temp/filename.zip", "newname.zip");

//郵件主題
    $mail->Subject="$subject";
//郵件內容
    $mail->Body = "$body";

//附加內容
// $mail->AltBody = '這是附加的信件內容';

//寄送郵件
    if (!$mail->Send()) {
#return "郵件無法順利寄出! Mailer Error: ".$mail->ErrorInfo;
      $email_status=0;  
    }
    return $email_status;
  } 
?>


 </body>
</html>



