<?php
$html = @file_get_contents('http://localhost/project/Creative%20Touch%20Interiors/login.php');

$checks = [
    'CREATIVE TOUCH',
    'INTERIORS',
    'ENTER THE STUDIO',
    'CLIENT LOGIN',
    'CREATE CLIENT ACCOUNT',
    'ADMIN LOGIN',
    'Access your client account',
    'Start your design journey',
    'Creative Touch administration',
    'auth3dCanvas',
    'switchAuthView'
];

echo "=== Verifying 3D Authentication UI ===\n";
$all_passed = true;
foreach ($checks as $item) {
    if (strpos($html, $item) !== false) {
        echo "[PASS] Found: $item\n";
    } else {
        echo "[FAIL] Missing: $item\n";
        $all_passed = false;
    }
}

// Also test ?view=client
$htmlClient = @file_get_contents('http://localhost/project/Creative%20Touch%20Interiors/login.php?view=client');
if (strpos($htmlClient, 'viewClient" class="auth-form-view active"') !== false) {
    echo "[PASS] Direct client view query works\n";
} else {
    echo "[FAIL] Direct client view query failed\n";
    $all_passed = false;
}

if ($all_passed) {
    echo "\n>>> ALL 3D AUTHENTICATION UI CHECKS PASSED SUCCESSFULLY! <<<\n";
}
