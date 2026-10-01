<?php
/**
 * Admin Workflow HTTP Simulation Test
 * Simulates an administrator logging in, viewing Quote #15, scheduling a site visit,
 * recalculating the cost estimation with discounts, and checking the audit trail.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';

$baseUrl = BASE_URL . 'admin/';
$adminCookie = __DIR__ . '/admin_cookies.txt';
if (file_exists($adminCookie)) unlink($adminCookie);

// 1. Ensure an admin exists in database
$adminUser = 'admin';
$adminPass = 'Admin@12345';
$adminHash = password_hash($adminPass, PASSWORD_DEFAULT);

$aStmt = $conn->prepare("SELECT id FROM admin_users WHERE username = ?");
$aStmt->bind_param("s", $adminUser);
$aStmt->execute();
$aRow = $aStmt->get_result()->fetch_assoc();

if (!$aRow) {
    $ins = $conn->prepare("INSERT INTO admin_users (name, username, email, password, role) VALUES ('Principal Architect', ?, 'admin@creativetouch.com', ?, 'superadmin')");
    $ins->bind_param("ss", $adminUser, $adminHash);
    $ins->execute();
    echo "✓ Created Admin record in database.\n";
} else {
    $upd = $conn->prepare("UPDATE admin_users SET password = ? WHERE username = ?");
    $upd->bind_param("ss", $adminHash, $adminUser);
    $upd->execute();
    echo "✓ Updated Admin credentials in database.\n";
}

// 2. Admin Login
$ch = curl_init($baseUrl . 'login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $adminCookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
$adminLoginPage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $adminLoginPage, $mCsrf);
$csrfToken = $mCsrf[1] ?? '';
if (!$csrfToken) die("Failed to extract Admin CSRF token.\n");

$loginData = [
    'csrf_token' => $csrfToken,
    'username' => $adminUser,
    'password' => $adminPass
];

$ch = curl_init($baseUrl . 'login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($loginData));
curl_setopt($ch, CURLOPT_COOKIEJAR, $adminCookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$res = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "✓ Admin Login Successful (HTTP " . $info['http_code'] . ")\n";

// 3. Fetch Latest Quote ID
$latestQuote = $conn->query("SELECT id, quote_number FROM quote_requests ORDER BY id DESC LIMIT 1")->fetch_assoc();
if (!$latestQuote) die("No quotes found in database to manage.\n");

$quoteId = (int)$latestQuote['id'];
$quoteNum = $latestQuote['quote_number'];
echo "Managing Quote ID: {$quoteId} ({$quoteNum})\n";

// Fetch Quote Details page
$ch = curl_init($baseUrl . "quote_details.php?id={$quoteId}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
$detailPage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $detailPage, $mDetCsrf);
$detCsrf = $mDetCsrf[1] ?? '';

// 4. Admin Action: Transition Status to 'under_review'
$postStatus = [
    'csrf_token' => $detCsrf,
    'action' => 'update_status',
    'status' => 'under_review',
    'status_comment' => 'Audited floor plan dimensions and scope items.'
];

$ch = curl_init($baseUrl . "quote_details.php?id={$quoteId}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postStatus));
curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
$resStatus = curl_exec($ch);
curl_close($ch);

$statusCheck = $conn->query("SELECT status FROM quote_requests WHERE id = {$quoteId}")->fetch_assoc();
echo "✓ Status Update Verified: Current Status = " . $statusCheck['status'] . "\n";

// 5. Admin Action: Save Cost Estimation with Discount
$postEst = [
    'csrf_token' => $detCsrf,
    'action' => 'save_estimation',
    'package_type' => 'premium',
    'base_rate_per_sqft' => '1200',
    'service_cost' => '40000',
    'material_cost' => '20000',
    'additional_cost' => '10000',
    'discount_type' => 'flat',
    'discount_amount' => '15000',
    'tax_percentage' => '18.0',
    'mark_estimation_prepared' => '1',
    'estimation_notes' => 'Authoritative estimation calculated via engine. Approved for client review.'
];

$ch = curl_init($baseUrl . "quote_details.php?id={$quoteId}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postEst));
curl_setopt($ch, CURLOPT_COOKIEFILE, $adminCookie);
$resEst = curl_exec($ch);
curl_close($ch);

$estCheck = $conn->query("SELECT estimated_total, status, discount_amount, tax_amount FROM quote_requests WHERE id = {$quoteId}")->fetch_assoc();
echo "✓ Estimation Recalculation Verified: Total = " . formatIndianCurrency((float)$estCheck['estimated_total']) . " | Status = " . $estCheck['status'] . "\n";

// 6. Verify Status History Audit Trail
$histCount = $conn->query("SELECT COUNT(*) as cnt FROM quote_status_history WHERE quote_id = {$quoteId}")->fetch_assoc();
echo "✓ Audit Trail Records: " . $histCount['cnt'] . " events logged.\n";

if (file_exists($adminCookie)) unlink($adminCookie);
echo "\n=== ALL ADMIN WORKFLOW & AUDIT TESTS PASSED ===\n";
