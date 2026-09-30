<?php
require_once __DIR__ . '/../includes/config.php';
$res = $conn->query("DESCRIBE projects");
while ($r = $res->fetch_assoc()) {
    echo $r['Field'] . ' | ' . $r['Type'] . ' | ' . $r['Null'] . ' | ' . $r['Key'] . ' | ' . $r['Default'] . "\n";
}
