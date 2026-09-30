<?php
require_once __DIR__ . '/../includes/config.php';

$res = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $res->fetch_row()) {
    $tbl = $row[0];
    $count = $conn->query("SELECT COUNT(*) FROM `{$tbl}`")->fetch_row()[0];
    echo sprintf("%-25s : %d rows\n", $tbl, $count);
}
