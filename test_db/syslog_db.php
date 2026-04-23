<?php
/*
error_reporting(E_ERROR);
if (session_status() == PHP_SESSION_NONE){
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 0);
    session_set_cookie_params(86400);
    session_start();
  }
*/
#  require("db.ini");
require_once("db23.ini");
require_once __DIR__ . "/../db.php";

function writeSyslog($initial, $ip_address, $action, $item = null, $table_name = null, $table_id = null) {
  $dblink = @pg_connect(DB_CONNECT);
  if ($table_name == null) {
    $sql = "INSERT INTO syslog (initials,ip_address,\"action\",create_time,item) VALUES ('$initial','$ip_address','$action',CURRENT_TIMESTAMP,'$item')";
  } else {
    if ($action == 3) {
      $sql = "INSERT INTO syslog (initials,ip_address,\"action\",item,create_time,table_name,table_id) VALUES ('$initial','$ip_address','$action','$item',CURRENT_TIMESTAMP,'$table_name','$table_id')";
    } else {
      $table_id = findLastIdOfTable($table_name);
      $sql = "INSERT INTO syslog (initials,ip_address,\"action\",item,create_time,table_name,table_id) VALUES ('$initial','$ip_address','$action','$item',CURRENT_TIMESTAMP,'$table_name','$table_id')";
    }
  }
  $result = @pg_query($dblink, $sql);
  if (!$result) {
    $error = pg_last_error($dblink);
    echo "SQL 執行失敗: $error";
    exit;
  }
  pg_close($dblink);
}

function findLastIdOfTable($table) {
  $dblink = @pg_connect(DB_CONNECT);
  $table = $table . "_id_seq";
  $sql = "SELECT last_value FROM $table";
  $result = @pg_query($dblink, $sql);
  if (!$result) {
    $error = pg_last_error($dblink);
    echo "SQL 執行失敗: $error";
    exit;
  }
  while ($row = pg_fetch_assoc($result)) {
    $id = $row['last_value'];
  }
  return $id;
  pg_close($dblink);
}

function writePrivate() {
  $dblink = @pg_connect(DB_CONNECT);
  $sql = "SELECT * FROM private_item";
  $result = @pg_query($dblink, $sql);
  if (!$result) {
    $error = pg_last_error($dblink);
    echo "SQL 執行失敗: $error";
    exit;
  }
  $count = 0;
  while ($row = pg_fetch_assoc($result)) {
    $net = "private" . $count;
    $_SESSION[$net] = $row['item'];
    $count++;
  }
  $_SESSION['private_count'] = $count;
  pg_close();
}

function checkPrivacy($init, $name) {

  // 建立（或重用）連線
  $conn = connect_pg();
  $sql = ' SELECT private_initials.initials FROM private_item
            JOIN private_initials
            ON private_item.id = private_initials.private_item_id
            WHERE private_item.item ILIKE $1 AND private_initials.initials=$2 ';

  $params = [$name, $init];
  $result = @pg_query_params($conn, $sql, $params);
  if ($result === false) {
    // 把具體錯誤寫入 log
    $preview = render_sql_preview($sql, $params, $conn);
    $pg_error = sanitize_log(pg_last_error($conn) ?: '');
    $content = json_encode([
      'preview' => $preview,  // $sql
      'params'  => $params,
      'message' => $pg_error,
    ], JSON_UNESCAPED_UNICODE);


    error_log(sprintf('[%s] [syslog_db.php] wp_error_log  SQL QUERY Failed: %s SQL:%s', $GLOBALS['REQ_ID'] ?? '-', $pg_error ?: '', $content));

    wp_error_log($GLOBALS['REQ_ID'], $who, 'syslog_db.php', 'private_item', 'SQL 查詢失敗', "$content");

    throw new RuntimeException('SQL QUERY Fail', E_DB_QUERY);
  }



  /*
    $dblink = @pg_connect(DB_CONNECT);
    $sql = "SELECT private_initials.initials 
    FROM private_item 
    JOIN private_initials 
    ON private_item.id = private_initials.private_item_id 
    WHERE private_item.item = '$name' AND private_initials.initials='$init'";
    $result = @pg_query($dblink,$sql);
    if (!$result) {
      $error = pg_last_error($dblink);
      echo "SQL 執行失敗: $error";
      exit;
    }
*/
  $rows = pg_num_rows($result);
  $approve = false;
  if ($rows == 1) {
    return true;
  } else {
    return false;
  }
}



function backUpData($table, $column_name, $id, $option) {
  $dblink23 = @pg_pconnect(DB_CONNECT23);
  $sql = "SELECT * FROM $table WHERE $column_name='$id'";
  $result = @pg_query($dblink23, $sql);

  if (!$result) {
    $error = pg_last_error($dblink23);
    echo "SQL 執行失敗: $error";
    exit;
  }

  $data = pg_fetch_all($result);
  $initial = $_SESSION['initial'];
  $backup_table = $table . "_keep";
  foreach ($data as &$each_data) {
    $each_data_ready = array_filter($each_data, function ($value) {
      return $value !== "" && $value !== null;
    });

    $columns = implode(", ", array_keys($each_data_ready));
    $values = "'" . implode("', '", $each_data_ready) . "'";


    $sql_insert = "INSERT INTO $backup_table ($columns, keep_initials, keep_option) VALUES ($values, '$initial', '$option')";

    $insert_result = @pg_query($dblink23, $sql_insert);
    if (!$insert_result) {
      $error = pg_last_error($dblink23);
      echo "插入資料失敗: $error";
      exit;
    }
  }
  pg_close($dblink23);
}
