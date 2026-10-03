<?php
require_once __DIR__ . '/../includes/config.php';

echo "Checking admin_users role column...\n";
$res = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'role'");
$col = $res->fetch_assoc();
echo "Current Type: " . $col['Type'] . "\n";

if (strpos($col['Type'], "'receptionist'") === false) {
    echo "Altering admin_users role ENUM...\n";
    $sql = "ALTER TABLE admin_users MODIFY COLUMN role ENUM('admin', 'super_admin', 'receptionist') NOT NULL DEFAULT 'admin'";
    if ($conn->query($sql)) {
        echo "Successfully updated role ENUM to include 'receptionist'.\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
} else {
    echo "admin_users role already contains 'receptionist'.\n";
}

$res = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'role'");
$col = $res->fetch_assoc();
echo "Updated Type: " . $col['Type'] . "\n";

echo "\nChecking existing users remain intact:\n";
$users = $conn->query("SELECT id, username, email, role FROM admin_users");
while ($u = $users->fetch_assoc()) {
    echo "ID {$u['id']} | User: {$u['username']} | Role: {$u['role']}\n";
}
