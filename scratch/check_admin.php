<?php
require_once __DIR__ . '/../includes/config.php';
$res = $conn->query("SELECT id, username, email, role, status FROM admin_users");
while ($r = $res->fetch_assoc()) {
    echo json_encode($r) . "\n";
}
