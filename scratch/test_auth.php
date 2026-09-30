<?php
require_once __DIR__ . '/../includes/config.php';
$stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = 'admin'");
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
echo "Admin found: " . ($admin ? 'YES' : 'NO') . "\n";
if ($admin) {
    echo "Verify Admin@12345: " . (password_verify('Admin@12345', $admin['password']) ? 'MATCH' : 'NO MATCH') . "\n";
    echo "Verify admin123: " . (password_verify('admin123', $admin['password']) ? 'MATCH' : 'NO MATCH') . "\n";
}
