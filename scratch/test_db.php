<?php
require_once __DIR__ . '/../includes/config.php';

echo "Connected successfully to MySQL: " . DB_NAME . "\n";
$tables = ['admin_users', 'consultations', 'contact_inquiries', 'gallery_images', 'leads', 'projects', 'services', 'team_members', 'testimonials', 'users', 'website_content', 'quote_requests', 'blogs'];

foreach ($tables as $t) {
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    if ($res && $res->num_rows > 0) {
        $c = $conn->query("SELECT COUNT(*) as c FROM `$t`")->fetch_assoc()['c'];
        echo "Table $t: $c records\n";
    } else {
        echo "Table $t: DOES NOT EXIST\n";
    }
}

echo "\n--- Admin Users ---\n";
$res = $conn->query("SELECT id, username, password, email, role FROM admin_users");
while ($r = $res->fetch_assoc()) {
    echo "ID: {$r['id']}, User: {$r['username']}, Email: {$r['email']}, Role: {$r['role']}\n";
    foreach (['admin', 'admin123', 'Admin@123', 'Admin@12345', 'harsh', 'harsh123', 'het', 'het123', '123456', 'password'] as $pwd) {
        if (password_verify($pwd, $r['password'])) {
            echo "   MATCH FOUND for {$r['username']}: password is '$pwd'\n";
        }
    }
}


