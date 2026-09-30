<?php
require_once __DIR__ . '/../includes/config.php';
echo "--- ADMIN_USERS ---\n";
$res = $conn->query("DESCRIBE admin_users");
while ($r = $res->fetch_assoc()) {
    echo $r['Field'] . ' | ' . $r['Type'] . "\n";
}
echo "\n--- USERS ---\n";
$res = $conn->query("DESCRIBE users");
while ($r = $res->fetch_assoc()) {
    echo $r['Field'] . ' | ' . $r['Type'] . "\n";
}
echo "\n--- ADMIN ROWS ---\n";
$res = $conn->query("SELECT * FROM admin_users");
while ($r = $res->fetch_assoc()) {
    // hide full password hash but show username / email / role
    echo "ID: {$r['id']} | User: " . ($r['username'] ?? 'N/A') . " | Email: " . ($r['email'] ?? 'N/A') . "\n";
}
