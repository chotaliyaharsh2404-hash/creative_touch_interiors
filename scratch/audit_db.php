<?php
require_once __DIR__ . '/../includes/config.php';

echo "=== DATABASE: " . DB_NAME . " ===\n";

$res = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $res->fetch_row()) {
    $tbl = $row[0];
    $countRes = $conn->query("SELECT COUNT(*) FROM `{$tbl}`");
    $count = $countRes ? $countRes->fetch_row()[0] : 'err';
    echo "TABLE: {$tbl} ({$count} rows)\n";
    $tables[] = $tbl;
}

echo "\n=== FOREIGN KEYS ===\n";
$fkRes = $conn->query("
    SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND REFERENCED_TABLE_NAME IS NOT NULL
");
if ($fkRes && $fkRes->num_rows > 0) {
    while ($fk = $fkRes->fetch_assoc()) {
        echo "{$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']} ({$fk['CONSTRAINT_NAME']})\n";
    }
} else {
    echo "No explicit foreign key constraints defined in database!\n";
}

echo "\n=== ALL TABLES AND THEIR COLUMNS ===\n";
foreach ($tables as $tbl) {
    echo "\n[TABLE] {$tbl}\n";
    $colRes = $conn->query("SHOW COLUMNS FROM `{$tbl}`");
    while ($col = $colRes->fetch_assoc()) {
        echo "  - {$col['Field']} ({$col['Type']}, Null: {$col['Null']}, Key: {$col['Key']}, Default: " . json_encode($col['Default']) . ")\n";
    }
}
