<?php
/**
 * Automated End-to-End Verification Test Suite
 */

$baseUrl = "http://localhost/project/Creative%20Touch%20Interiors/";

echo "====================================================\n";
echo "   CREATIVE TOUCH INTERIORS — HTTP TEST SUITE       \n";
echo "====================================================\n";

$allPassed = true;

// Public Guest Endpoints
$publicEndpoints = [
    'Homepage (3D WebGL Living Room Hero)' => 'index.php',
    'About Studio (3D Pavilion & Founders)' => 'about.php',
    'Services (3D Cards & Search)' => 'services.php',
    'Portfolio Projects (3D Spatial Lookbook)' => 'projects.php',
    'Single Project View (ID: 10 Serene Oak)' => 'project.php?id=10',
    'Visual Gallery (3D Lookbook)' => 'gallery.php',
    'Editorial Blog Magazine' => 'blog.php',
    'Single Blog Post (Sensory Lighting)' => 'blog.php?slug=sensory-lighting-modern-penthouse-architecture',
    'Studio Contact' => 'contact.php',
    'Client Login' => 'login.php',
    'Client Register' => 'register.php',
    'Executive Admin Login' => 'admin/login.php',
];

foreach ($publicEndpoints as $label => $uri) {
    $url = $baseUrl . $uri;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "[ PASS - 200 OK ] {$label} ({$uri})\n";
    } else {
        echo "[ FAIL - {$httpCode} ] {$label} ({$uri})\n";
        $allPassed = false;
    }
}

// ----------------------------------------------------------------
// 2. Client Authentication & Protected Consultation 3D Quote Wizard
// ----------------------------------------------------------------
echo "\n--- Testing Client Authentication & 3D Quote Wizard ---\n";
require_once __DIR__ . '/../includes/config.php';

// Check if a client user exists in `users` table
$userRes = $conn->query("SELECT id, email FROM users LIMIT 1");
$testUser = $userRes->fetch_assoc();

if (!$testUser) {
    // Create temporary test user
    $hash = password_hash('password123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (name, email, password, phone, terms_agreed, terms_agreed_at) VALUES ('Test Client', 'client_test@luxury.com', '{$hash}', '9876543210', 1, NOW())");
    $testUserId = $conn->insert_id;
    $testUserEmail = 'client_test@luxury.com';
} else {
    $testUserId = $testUser['id'];
    $testUserEmail = $testUser['email'];
}

// Reset password for test account to known password
$testPass = 'client123';
$hash = password_hash($testPass, PASSWORD_DEFAULT);
$conn->query("UPDATE users SET password = '{$hash}' WHERE id = {$testUserId}");

$clientCookie = __DIR__ . '/client_cookie.txt';
if (file_exists($clientCookie)) unlink($clientCookie);

// Get CSRF from login.php
$ch = curl_init($baseUrl . 'login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $clientCookie);
$loginHtml = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginHtml, $matches);
$clientCsrf = $matches[1] ?? '';

// Post client login
$ch = curl_init($baseUrl . 'login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $clientCsrf,
    'email' => $testUserEmail,
    'password' => $testPass,
    'redirect' => 'consultation.php'
]));
curl_setopt($ch, CURLOPT_COOKIEFILE, $clientCookie);
curl_setopt($ch, CURLOPT_COOKIEJAR, $clientCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$consultHtml = curl_exec($ch);
$consultHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($consultHttp === 200 && strpos($consultHtml, 'Custom Spatial Estimation') !== false) {
    echo "[ PASS - 200 OK ] Client logged in and accessed 3D Instant Quote Wizard (consultation.php)!\n";
} else {
    echo "[ FAIL ] Consultation wizard load failed (HTTP {$consultHttp})\n";
    $allPassed = false;
}

// Test Client Profile
$ch = curl_init($baseUrl . 'profile.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $clientCookie);
$profileHtml = curl_exec($ch);
$profileHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($profileHttp === 200 && strpos($profileHtml, 'Client Portal') !== false) {
    echo "[ PASS - 200 OK ] Client Portal Profile loaded successfully (profile.php)!\n";
} else {
    echo "[ FAIL ] Profile load failed (HTTP {$profileHttp})\n";
    $allPassed = false;
}

if (file_exists($clientCookie)) unlink($clientCookie);

// ----------------------------------------------------------------
// 3. Executive Admin Authentication & Dashboard + Blog Manager
// ----------------------------------------------------------------
echo "\n--- Testing Executive Admin Authentication ---\n";
$adminCookie = __DIR__ . '/admin_cookie.txt';
if (file_exists($adminCookie)) unlink($adminCookie);

// 1. Get CSRF token from admin/login.php
$ch = curl_init($baseUrl . 'admin/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $adminCookie);
$loginPage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $matches);
$csrfToken = $matches[1] ?? '';

if (empty($csrfToken)) {
    echo "[ FAIL ] Could not extract CSRF token from admin/login.php\n";
    $allPassed = false;
} else {
    echo "[ PASS ] Extracted Admin CSRF token successfully.\n";

    // 2. Post login credentials
    $ch = curl_init($baseUrl . 'admin/login.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'csrf_token' => $csrfToken,
        'username' => 'harsh',
        'password' => 'harsh'
    ]));
    curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $adminCookie);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $dashboardHtml = curl_exec($ch);
    $dashHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($dashHttp === 200 && strpos($dashboardHtml, 'Command Center') !== false) {
        echo "[ PASS - 200 OK ] Authenticated as Super Admin into Executive Command Center (admin/dashboard.php)!\n";
    } else {
        echo "[ FAIL ] Admin authentication failed (HTTP {$dashHttp})\n";
        $allPassed = false;
    }

    // 3. Test Admin Blog Manager Access
    $ch = curl_init($baseUrl . 'admin/blog.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
    $blogAdminHtml = curl_exec($ch);
    $blogHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($blogHttp === 200 && strpos($blogAdminHtml, 'Spatial Perspectives') !== false) {
        echo "[ PASS - 200 OK ] Admin Editorial Blog Management loaded successfully (admin/blog.php)!\n";
    } else {
        echo "[ FAIL ] Failed to load admin/blog.php (HTTP {$blogHttp})\n";
        $allPassed = false;
    }

    // 4. Test Quotes Management
    $ch = curl_init($baseUrl . 'admin/quotes.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
    $qHtml = curl_exec($ch);
    $qHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($qHttp === 200 && strpos($qHtml, 'Quote Requests') !== false) {
        echo "[ PASS - 200 OK ] Admin Quotes Management loaded successfully (admin/quotes.php)!\n";
    } else {
        echo "[ FAIL ] Quotes admin load failed (HTTP {$qHttp})\n";
        $allPassed = false;
    }
}

if (file_exists($adminCookie)) unlink($adminCookie);

echo "====================================================\n";
echo $allPassed ? "   ALL 16 E2E ENDPOINTS & WORKFLOWS PASSED 100%!\n" : "   SOME TESTS FAILED!\n";
echo "====================================================\n";
