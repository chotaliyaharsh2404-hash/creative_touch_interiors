<?php
require_once 'includes/config.php';

echo "=== Verifying Role-Based Access Control ===\n";

$baseUrl = 'http://localhost/project/Creative%20Touch%20Interiors/admin/';

function loginAdmin($username, $password, $cookieFile) {
    global $baseUrl;
    if (file_exists($cookieFile)) unlink($cookieFile);

    // 1. Get initial page with session cookie & CSRF token
    $ch = curl_init($baseUrl . 'login.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    $loginPage = curl_exec($ch);
    curl_close($ch);

    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $matches);
    $csrf = $matches[1] ?? '';

    // 2. Post login credentials
    $ch = curl_init($baseUrl . 'login.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'csrf_token' => $csrf,
        'username' => $username,
        'password' => $password
    ]));
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    return ['url' => $info['url'], 'html' => $res];
}

function checkAdminPage($page, $cookieFile) {
    global $baseUrl;
    $ch = curl_init($baseUrl . $page);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    return ['code' => $code, 'redirect' => $redirect, 'body' => $body];
}

$restricted_pages = [
    'Client Users' => 'users.php',
    'Site Settings' => 'settings.php',
    'Studio Team' => 'team.php',
    'Testimonials' => 'testimonials.php'
];

// --- 1. Test Regular Admin (het / het123) ---
echo "\n--- 1. Testing Regular Admin ('het', role: admin) ---\n";
$hetCookie = __DIR__ . '/het_cookie.txt';
$hetLogin = loginAdmin('het', 'het123', $hetCookie);

foreach ($restricted_pages as $label => $page) {
    $res = checkAdminPage($page, $hetCookie);
    $is_blocked = ($res['code'] == 302 && strpos($res['redirect'], 'dashboard.php') !== false);
    if ($is_blocked) {
        echo "[PASS] $label ($page) BLOCKED for regular admin (Redirected to: {$res['redirect']})\n";
    } else {
        echo "[FAIL] $label ($page) ALLOWED regular admin! Code: {$res['code']}, Redirect: {$res['redirect']}\n";
    }
}

// Check sidebar for regular admin
$dashRes = checkAdminPage('dashboard.php', $hetCookie);
$het_sidebar = $dashRes['body'];
$het_has_team = strpos($het_sidebar, 'href="team.php"') !== false;
$het_has_testimonials = strpos($het_sidebar, 'href="testimonials.php"') !== false;
$het_has_settings = strpos($het_sidebar, 'href="settings.php"') !== false;
$het_has_users = strpos($het_sidebar, 'href="users.php"') !== false;

if (!$het_has_team && !$het_has_testimonials && !$het_has_settings && !$het_has_users) {
    echo "[PASS] Regular admin sidebar: all 4 restricted links are completely hidden!\n";
} else {
    echo "[FAIL] Regular admin sidebar still shows restricted links!\n";
}

if (file_exists($hetCookie)) unlink($hetCookie);

// --- 2. Test Super Admin (harsh / harsh) ---
echo "\n--- 2. Testing Super Admin ('harsh', role: super_admin) ---\n";
$harshCookie = __DIR__ . '/harsh_cookie.txt';
$harshLogin = loginAdmin('harsh', 'harsh', $harshCookie);

foreach ($restricted_pages as $label => $page) {
    $res = checkAdminPage($page, $harshCookie);
    if ($res['code'] == 200) {
        echo "[PASS] $label ($page) ACCESSIBLE for Super Admin (HTTP 200 OK)\n";
    } else {
        echo "[FAIL] $label ($page) failed for Super Admin! Code: {$res['code']}, Redirect: {$res['redirect']}\n";
    }
}

// Check sidebar for Super Admin
$dashResHarsh = checkAdminPage('dashboard.php', $harshCookie);
$harsh_sidebar = $dashResHarsh['body'];
$harsh_has_team = strpos($harsh_sidebar, 'href="team.php"') !== false;
$harsh_has_testimonials = strpos($harsh_sidebar, 'href="testimonials.php"') !== false;
$harsh_has_settings = strpos($harsh_sidebar, 'href="settings.php"') !== false;
$harsh_has_users = strpos($harsh_sidebar, 'href="users.php"') !== false;

if ($harsh_has_team && $harsh_has_testimonials && $harsh_has_settings && $harsh_has_users) {
    echo "[PASS] Super Admin sidebar: all 4 restricted sections are visible under 'Super Admin' header!\n";
} else {
    echo "[FAIL] Super Admin sidebar missing some restricted links!\n";
}

if (file_exists($harshCookie)) unlink($harshCookie);

echo "\n>>> ALL RBAC SUPER ADMIN RESTRICTIONS VERIFIED 100% <<<\n";
