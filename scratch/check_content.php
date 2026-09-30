<?php
require_once __DIR__ . '/../includes/config.php';
$res = $conn->query("DESCRIBE website_content");
while ($r = $res->fetch_assoc()) {
    echo $r['Field'] . ' | ' . $r['Type'] . "\n";
}
$rows = $conn->query("SELECT * FROM website_content");
while ($r = $rows->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
