<?php
// 1. 設定錯誤顯示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. 引入資料庫設定
require_once("db23.ini");

// 3. 建立資料庫連接
$dblink = @pg_connect(DB_CONNECT23);
if (!$dblink) {
    die("資料庫連接失敗: " . pg_last_error());
}

// 4. 接收參數
if (!isset($_GET['deb_num']) || empty($_GET['deb_num'])) {
    echo "<script>alert('錯誤：未提供帳單編號 (deb_num)'); history.back();</script>";
    exit;
}

$deb_num = trim($_GET['deb_num']);

try {
    // 5. 開啟交易
    pg_query($dblink, "BEGIN");

    // -----------------------------------------------------------
    // 步驟一：刪除帳單主檔 (Bills)
    // -----------------------------------------------------------
    $sql_del_bill = "DELETE FROM bills WHERE deb_num = $1";
    $res_del = pg_query_params($dblink, $sql_del_bill, [$deb_num]);

    if (!$res_del) {
        throw new Exception("刪除帳單主檔失敗 (SQL Error): " . pg_last_error($dblink));
    }

    // 檢查是否有刪除到資料 (若影響行數為 0，代表帳單不存在)
    if (pg_affected_rows($res_del) == 0) {
        throw new Exception("找不到該帳單編號 ($deb_num)，無法刪除。");
    }

    // -----------------------------------------------------------
    // 步驟二：重置工時紀錄 (Time Records)
    // -----------------------------------------------------------
    // 將 deb_num 設為 NULL，並將狀態設為 -1 (未結帳)
    $sql_reset_tr = "UPDATE tr SET deb_num = NULL, billed_flag = -1 WHERE deb_num = $1";
    $res_tr = pg_query_params($dblink, $sql_reset_tr, [$deb_num]);

    if (!$res_tr) {
        throw new Exception("重置工時紀錄失敗 (SQL Error): " . pg_last_error($dblink));
    }

    // -----------------------------------------------------------
    // 步驟三：重置代墊款 (特殊規則 - 無償項目)
    // -----------------------------------------------------------
    // 必須先執行此步驟 (Specific Rule)
    $sql_reset_disb_special = "UPDATE disbursements 
                               SET deb_num = NULL, billed_flag = 2 
                               WHERE nocharge_flag = 1 
                               AND show_flag = -1 
                               AND deb_num = $1";
    $res_disb_sp = pg_query_params($dblink, $sql_reset_disb_special, [$deb_num]);

    if (!$res_disb_sp) {
        throw new Exception("重置特殊代墊款失敗 (SQL Error): " . pg_last_error($dblink));
    }

    // -----------------------------------------------------------
    // 步驟四：重置代墊款 (一般規則)
    // -----------------------------------------------------------
    // 將其餘項目的 deb_num 設為 NULL
    $sql_reset_disb_general = "UPDATE disbursements 
                               SET deb_num = NULL, billed_flag = -1 
                               WHERE deb_num = $1";
    $res_disb_gen = pg_query_params($dblink, $sql_reset_disb_general, [$deb_num]);

    if (!$res_disb_gen) {
        throw new Exception("重置一般代墊款失敗 (SQL Error): " . pg_last_error($dblink));
    }

    // -----------------------------------------------------------
    // 6. 提交交易 & 成功回饋
    // -----------------------------------------------------------
    pg_query($dblink, "COMMIT");

    echo "<script>
            alert('帳單 $deb_num 已成功刪除並重置 (Reset Successfully).');
            window.location.href = document.referrer;
          </script>";
} catch (Exception $e) {
    // 7. 發生錯誤時回滾 (Rollback)
    pg_query($dblink, "ROLLBACK");

    $msg = addslashes($e->getMessage()); // 處理引號以免破壞 JS
    echo "<script>
            alert('執行失敗，系統已還原變更。\\n錯誤原因：$msg');
            window.history.back();
          </script>";
} finally {
    // 8. 關閉連線
    if ($dblink) {
        pg_close($dblink);
    }
}
