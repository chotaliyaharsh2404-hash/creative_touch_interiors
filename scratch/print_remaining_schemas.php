<?php
require_once __DIR__ . '/../includes/config.php';

$tables = ['admin_users', 'consultations', 'contact_inquiries', 'faqs', 'gallery_images', 'leads', 'notifications', 'projects'];
foreach ($tables as $tbl) {
    echo "\n[TABLE] {$tbl}\n";
    $colRes = $conn->query("SHOW COLUMNS FROM `{$tbl}`");
    while ($col = $colRes->fetch_assoc()) {
        echo "  - {$col['Field']} ({$col['Type']}, Null: {$col['Null']}, Key: {$col['Key']}, Default: " . json_encode($col['Default']) . ")\n";
    }
}
