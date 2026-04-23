<?php
try {
    $db = new PDO(
        "pgsql:
                    host=192.168.0.23;
                    port=5432;
                    dbname=qicom",
        "postgres",
        "blah"
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "資料庫連線成功！<br><br>\n\n";
} catch (PDOException $e) {
    echo "資料庫連線失敗: " . $e->getMessage() . "<br>\n";
    exit();
}

try {
    $query = "SELECT *
              FROM payments
              WHERE case_num = 'ADB007-11'";

    // $query = "DELETE FROM client_pay_history
    //             WHERE id > 64";

    // $query = "ALTER SEQUENCE client_pay_history_id_seq RESTART WITH 65";

    // $query = "UPDATE client_pay_total
    //         SET temp_twd_total_amount = twd_total_amount
    //         WHERE case_num='ADB007'";

    $result = $db->query($query);

    if ($result) {
        $rows = $result->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 0) {
            echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;">';
            // 表頭
            echo '<tr style="background:#f0f0f0;">';
            foreach (array_keys($rows[0]) as $col) {
                echo '<th>' . htmlspecialchars($col) . '</th>';
            }
            echo '</tr>';
            // 資料列
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>' . htmlspecialchars($cell) . '</td>';
                }
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '查無資料。';
        }
    } else {
        echo '查詢失敗。';
    }
} catch (PDOException $e) {
    echo '查詢錯誤: ' . $e->getMessage();
}
