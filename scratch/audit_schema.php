<?php
require_once __DIR__ . '/../includes/config.php';

echo "--- QUOTE_REQUESTS SCHEMA ---\n";
$res = $conn->query("DESCRIBE quote_requests");
while ($row = $res->fetch_assoc()) {
    echo sprintf("%-25s | %-20s | Null: %-4s | Default: %s\n", $row['Field'], $row['Type'], $row['Null'], $row['Default'] ?? 'NULL');
}
