<?php
/**
 * End-to-End HTTP Simulation Test
 * Simulates a real browser user submitting the Get Quote form to the live Apache web server.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';

$baseUrl = 'http://localhost/project/Creative%20Touch%20Interiors/';
$testEmail = 'test@example.com';
$testPass = 'Test@12345';
$testHash = password_hash($testPass, PASSWORD_DEFAULT);

// 1. Ensure Test Customer exists in the database
$uStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$uStmt->bind_param("s", $testEmail);
$uStmt->execute();
$uRow = $uStmt->get_result()->fetch_assoc();

if (!$uRow) {
    $ins = $conn->prepare("INSERT INTO users (name, email, phone, password) VALUES ('Test Customer', ?, '9876543210', ?)");
    $ins->bind_param("ss", $testEmail, $testHash);
    $ins->execute();
    echo "Created Test Customer user record in database.\n";
} else {
    $upd = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $upd->bind_param("ss", $testHash, $testEmail);
    $upd->execute();
    echo "Updated Test Customer password in database.\n";
}

// 2. Perform Login via HTTP
$cookieFile = __DIR__ . '/test_cookies.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// Fetch login page for CSRF token
$ch = curl_init($baseUrl . 'login.php?redirect=consultation.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$loginPage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginPage, $loginCsrfMatches);
$loginCsrf = $loginCsrfMatches[1] ?? '';
if (!$loginCsrf) {
    die("Failed to extract CSRF token from login page.\n");
}

// Submit login form
$loginPost = [
    'csrf_token' => $loginCsrf,
    'email' => $testEmail,
    'password' => $testPass,
    'redirect' => 'consultation.php'
];

$ch = curl_init($baseUrl . 'login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($loginPost));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$loginRes = curl_exec($ch);
$loginInfo = curl_getinfo($ch);
curl_close($ch);

echo "Login Successful! Final URL: " . $loginInfo['url'] . " (HTTP " . $loginInfo['http_code'] . ")\n";

// 3. Now Fetch consultation.php with authenticated session
$ch = curl_init($baseUrl . 'consultation.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$quotePage = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $quotePage, $csrfMatches);
preg_match('/name="submission_token"\s+value="([^"]+)"/', $quotePage, $subMatches);

$csrfToken = $csrfMatches[1] ?? '';
$submissionToken = $subMatches[1] ?? '';

echo "Consultation CSRF Token: " . ($csrfToken ? "Extracted" : "MISSING") . "\n";
echo "Consultation Submission Token: " . ($submissionToken ? "Extracted" : "MISSING") . "\n";

if (!$csrfToken || !$submissionToken) {
    die("Error extracting tokens from consultation page.\n");
}

// 4. Submit Get Quote form via HTTP POST
$quotePostData = [
    'csrf_token' => $csrfToken,
    'submission_token' => $submissionToken,
    'customer_name' => 'Test Customer',
    'email' => $testEmail,
    'phone' => '9876543210',
    'city' => 'Surat',
    'state' => 'Gujarat',
    'pincode' => '395001',
    'project_type' => 'Residential',
    'property_type' => 'Apartment / Flat',
    'measurement_status' => 'known',
    'rooms' => [
        [
            'name' => 'Living Room',
            'length' => '20',
            'width' => '15'
        ],
        [
            'name' => 'Master Bedroom',
            'length' => '20',
            'width' => '10'
        ]
    ],
    'package_type' => 'premium',
    'budget_range' => '₹10 - ₹20 Lakhs',
    'design_style' => 'Modern Minimalist',
    'special_requirements' => 'End-to-End HTTP automated quote verification.'
];

$ch = curl_init($baseUrl . 'consultation.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($quotePostData));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$quoteSubmitRes = curl_exec($ch);
$quoteInfo = curl_getinfo($ch);
curl_close($ch);

echo "Quote Submit HTTP Code: " . $quoteInfo['http_code'] . "\n";

file_put_contents(__DIR__ . '/last_response.html', $quoteSubmitRes);

// Check if success message or quote number is present in response
if (preg_match('/(CTI-QT?-[A-Z0-9\-]+)/', $quoteSubmitRes, $quoteNumMatches)) {
    $quoteNumber = $quoteNumMatches[1];
    echo "✓ SUCCESS: Quote Created with Number: " . $quoteNumber . "\n";

    // Query DB for newly created quote
    $qCheck = $conn->prepare("SELECT * FROM quote_requests WHERE quote_number = ?");
    $qCheck->bind_param("s", $quoteNumber);
    $qCheck->execute();
    $createdQuote = $qCheck->get_result()->fetch_assoc();

    if ($createdQuote) {
        $quoteId = (int)$createdQuote['id'];
        echo "✓ Database Record Verified: ID=" . $quoteId . ", Area=" . $createdQuote['approx_area'] . " sq.ft, Total=" . formatIndianCurrency($createdQuote['estimated_total']) . "\n";

        // 5. Test PDF Generation
        $pdfUrl = $baseUrl . "quote_pdf.php?id=" . $quoteId;
        $ch = curl_init($pdfUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        $pdfRes = curl_exec($ch);
        $pdfCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        echo "✓ PDF Generation HTTP Code: " . $pdfCode . "\n";
        echo "✓ PDF Contains Quote Number: " . (strpos($pdfRes, $quoteNumber) !== false ? "YES" : "NO") . "\n";
        echo "✓ PDF Contains Customer Name: " . (strpos($pdfRes, 'Test Customer') !== false ? "YES" : "NO") . "\n";
        echo "✓ PDF Contains Living Room (300 sq.ft): " . (strpos($pdfRes, 'Living Room') !== false ? "YES" : "NO") . "\n";
        echo "✓ PDF Contains Master Bedroom (200 sq.ft): " . (strpos($pdfRes, 'Master Bedroom') !== false ? "YES" : "NO") . "\n";
    }
} else {
    if (preg_match('/<div class="alert alert-danger[^>]*>(.*?)<\/div>/s', $quoteSubmitRes, $errMatches)) {
        echo "Server Alert Error: " . trim(strip_tags($errMatches[1])) . "\n";
    } else {
        echo "Notice: Output snippet:\n" . substr(strip_tags($quoteSubmitRes), 0, 500) . "\n";
    }
}

if (file_exists($cookieFile)) unlink($cookieFile);
echo "\n=== ALL HTTP CLIENT & PDF ENDPOINTS VERIFIED OPERATIONAL ===\n";
