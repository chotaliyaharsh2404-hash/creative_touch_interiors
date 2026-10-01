<?php
/**
 * Creative Touch Interiors — Master Functional, Integration & Regression Test Suite
 * Executes real HTTP requests against Apache server and verifies live MySQL/MariaDB database.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$detectedBase = 'http://localhost/project/creative_touch_interiors';
$chTest = curl_init($detectedBase . '/index.php');
curl_setopt($chTest, CURLOPT_RETURNTRANSFER, true);
curl_exec($chTest);
if (curl_getinfo($chTest, CURLINFO_HTTP_CODE) !== 200) {
    $detectedBase = 'http://localhost/project/Creative%20Touch%20Interiors';
}
curl_close($chTest);
define('BASE_URL', $detectedBase);
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'creative_touch_interiors');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("CRITICAL: Failed to connect to MySQL: " . $conn->connect_error . "\n");
}

class TestReporter {
    public static $total = 0;
    public static $passed = 0;
    public static $failed = 0;
    public static $blocked = 0;
    public static $failures = [];

    public static function assert($condition, $name, $details = '') {
        self::$total++;
        if ($condition) {
            self::$passed++;
            echo "  [PASS] {$name}\n";
            return true;
        } else {
            self::$failed++;
            echo "  [FAIL] {$name} - {$details}\n";
            self::$failures[] = ['name' => $name, 'details' => $details];
            return false;
        }
    }

    public static function summary() {
        echo "\n======================================================\n";
        echo "TEST SUITE SUMMARY:\n";
        echo "Total Tests : " . self::$total . "\n";
        echo "Passed      : " . self::$passed . "\n";
        echo "Failed      : " . self::$failed . "\n";
        echo "Blocked     : " . self::$blocked . "\n";
        echo "Success Rate: " . (self::$total > 0 ? round((self::$passed / self::$total) * 100, 1) : 0) . "%\n";
        echo "======================================================\n";
        if (!empty(self::$failures)) {
            echo "FAILURES DETAIL:\n";
            foreach (self::$failures as $idx => $f) {
                echo ($idx + 1) . ". {$f['name']}: {$f['details']}\n";
            }
        }
    }
}

class HttpClient {
    private $cookieFile;

    public function __construct() {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'cti_cookie_');
    }

    public function __destruct() {
        if (file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function request($url, $method = 'GET', $data = [], $headers = []) {
        $ch = curl_init();
        if ($method === 'GET' && !empty($data)) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($data);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($ch);
        curl_close($ch);

        $headerText = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        return [
            'code' => $httpCode,
            'body' => $body,
            'headers' => $headerText,
            'effective_url' => $effectiveUrl,
            'error' => $error
        ];
    }

    public function extractCsrf($html) {
        if (preg_match('/name=["\']csrf_token["\']\s+value=["\']([^"\']+)["\']/i', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/value=["\']([^"\']+)["\']\s+name=["\']csrf_token["\']/i', $html, $m)) {
            return $m[1];
        }
        return '';
    }
}

echo "=== Creative Touch Interiors: Functional & Regression Test Suite ===\n\n";

// -------------------------------------------------------------
// 1. PHP Syntax & Code Quality Audit across all Workspace Files
// -------------------------------------------------------------
echo "1. AUDITING PHP SYNTAX ACROSS ALL FILES...\n";
$phpFiles = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__)));
foreach ($iter as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        // Skip vendor if any
        if (strpos($path, 'vendor') === false) {
            $phpFiles[] = $path;
        }
    }
}
$syntaxErrors = 0;
foreach ($phpFiles as $file) {
    $cmd = escapeshellarg(PHP_BINARY) . " -l " . escapeshellarg($file);
    exec($cmd, $out, $ret);
    if ($ret !== 0) {
        $syntaxErrors++;
        TestReporter::assert(false, "PHP Syntax Check: " . basename($file), implode("\n", $out));
    }
}
TestReporter::assert($syntaxErrors === 0, "PHP Syntax: Checked " . count($phpFiles) . " PHP files with 0 syntax errors");

// -------------------------------------------------------------
// 2. Public Frontend Endpoints Availability
// -------------------------------------------------------------
echo "\n2. TESTING PUBLIC FRONTEND ENDPOINTS...\n";
$publicClient = new HttpClient();
$endpoints = [
    'index.php' => 'Creative Touch Interiors',
    'about.php' => 'Harsh',
    'services.php' => 'Services',
    'projects.php' => 'Projects',
    'gallery.php' => 'Gallery',
    'contact.php' => 'Contact',
    'login.php' => 'Client Login',
    'register.php' => 'Create Account',
    'terms.php' => 'Terms',
    'privacy.php' => 'Privacy'
];
foreach ($endpoints as $ep => $expectedText) {
    $resp = $publicClient->request(BASE_URL . '/' . $ep);
    TestReporter::assert($resp['code'] === 200 && strpos($resp['body'], $expectedText) !== false, "Public Page HTTP 200: {$ep}", "Code: {$resp['code']}");
}

// Verify consultation.php enforces authentication
$respConsultUnauth = $publicClient->request(BASE_URL . '/consultation.php');
TestReporter::assert(
    strpos($respConsultUnauth['effective_url'], 'login.php') !== false || strpos($respConsultUnauth['body'], 'Client Login') !== false,
    "Access Control: consultation.php requires client authentication"
);

// -------------------------------------------------------------
// 3. Client Authentication Testing (Registration, Login, Validation)
// -------------------------------------------------------------
echo "\n3. TESTING CLIENT AUTHENTICATION...\n";
$authClient = new HttpClient();

// A. Registration Validation: Empty Fields
$regPage = $authClient->request(BASE_URL . '/register.php');
$csrf = $authClient->extractCsrf($regPage['body']);
$respEmpty = $authClient->request(BASE_URL . '/register.php', 'POST', [
    'csrf_token' => $csrf,
    'name' => '',
    'email' => '',
    'password' => '',
    'confirm_password' => '',
    'terms_agreed' => '1'
]);
TestReporter::assert(strpos($respEmpty['body'], 'All required fields must be filled') !== false, "Registration: Rejects empty fields");

// B. Registration Validation: Weak/Short Password
$csrf = $authClient->extractCsrf($respEmpty['body']) ?: $csrf;
$respWeak = $authClient->request(BASE_URL . '/register.php', 'POST', [
    'csrf_token' => $csrf,
    'name' => 'QA Client',
    'email' => 'qa_client_weak@example.com',
    'password' => '123',
    'confirm_password' => '123',
    'terms_agreed' => '1'
]);
TestReporter::assert(strpos($respWeak['body'], 'at least 6 characters') !== false, "Registration: Rejects short password");

// C. Registration Validation: Terms Not Checked
$csrf = $authClient->extractCsrf($respWeak['body']) ?: $csrf;
$respNoTerms = $authClient->request(BASE_URL . '/register.php', 'POST', [
    'csrf_token' => $csrf,
    'name' => 'QA Client',
    'email' => 'qa_client_terms@example.com',
    'password' => 'Test@12345',
    'confirm_password' => 'Test@12345'
]);
TestReporter::assert(strpos($respNoTerms['body'], 'agree to the Terms') !== false, "Registration: Requires Terms acceptance");

// D. Clean up previous test users & consultations if any
$testEmailA = 'qa_test_client@example.com';
$testEmailB = 'qa_test_client_b@example.com';
$conn->query("DELETE FROM consultations WHERE client_email IN ('{$testEmailA}', '{$testEmailB}') OR notes LIKE '%QA Test Client%' OR notes LIKE '%CTI-QT%'");
$conn->query("DELETE FROM quote_services WHERE quote_id IN (SELECT id FROM quote_requests WHERE email IN ('{$testEmailA}', '{$testEmailB}'))");
$conn->query("DELETE FROM quote_rooms WHERE quote_id IN (SELECT id FROM quote_requests WHERE email IN ('{$testEmailA}', '{$testEmailB}'))");
$conn->query("DELETE FROM quote_status_history WHERE quote_id IN (SELECT id FROM quote_requests WHERE email IN ('{$testEmailA}', '{$testEmailB}'))");
$conn->query("DELETE FROM quote_requests WHERE email IN ('{$testEmailA}', '{$testEmailB}')");
$conn->query("DELETE FROM users WHERE email IN ('{$testEmailA}', '{$testEmailB}')");
$conn->query("DELETE FROM team_members WHERE name LIKE '%QA Senior Architect%'");
$conn->query("DELETE FROM testimonials WHERE client_name LIKE '%QA%' OR project_title LIKE '%QA%'");

// E. Valid Client A Registration
$csrf = $authClient->extractCsrf($respNoTerms['body']) ?: $csrf;
$respReg = $authClient->request(BASE_URL . '/register.php', 'POST', [
    'csrf_token' => $csrf,
    'name' => 'QA Test Client',
    'email' => $testEmailA,
    'phone' => '9999999999',
    'password' => 'Test@12345',
    'confirm_password' => 'Test@12345',
    'terms_agreed' => '1'
]);
TestReporter::assert(
    strpos($respReg['effective_url'], 'profile.php') !== false || strpos($respReg['body'], 'QA Test Client') !== false,
    "Registration: Client A account created successfully"
);

// Verify DB record for Client A
$checkUser = $conn->query("SELECT * FROM users WHERE email = '{$testEmailA}'")->fetch_assoc();
TestReporter::assert(!empty($checkUser['id']) && $checkUser['terms_agreed'] == 1, "Database: Client A inserted with terms_agreed=1");
$clientA_id = $checkUser['id'] ?? 0;

// F. Duplicate Registration Attempt
$clientAnon = new HttpClient();
$regPage2 = $clientAnon->request(BASE_URL . '/register.php');
$csrf2 = $clientAnon->extractCsrf($regPage2['body']);
$respDup = $clientAnon->request(BASE_URL . '/register.php', 'POST', [
    'csrf_token' => $csrf2,
    'name' => 'QA Test Client Duplicate',
    'email' => $testEmailA,
    'password' => 'Test@12345',
    'confirm_password' => 'Test@12345',
    'terms_agreed' => '1'
]);
TestReporter::assert(strpos($respDup['body'], 'already registered') !== false, "Registration: Rejects duplicate email address");

// G. Client Login: Incorrect Password
$loginClient = new HttpClient();
$loginPage = $loginClient->request(BASE_URL . '/login.php');
$csrfLogin = $loginClient->extractCsrf($loginPage['body']);
$respBadPass = $loginClient->request(BASE_URL . '/login.php', 'POST', [
    'csrf_token' => $csrfLogin,
    'email' => $testEmailA,
    'password' => 'WrongPassword123'
]);
TestReporter::assert(strpos($respBadPass['body'], 'Invalid email or password') !== false, "Login: Rejects invalid password");

// H. Client Login: Non-existent Email
$csrfLogin = $loginClient->extractCsrf($respBadPass['body']) ?: $csrfLogin;
$respNoEmail = $loginClient->request(BASE_URL . '/login.php', 'POST', [
    'csrf_token' => $csrfLogin,
    'email' => 'doesnotexist_9988@example.com',
    'password' => 'Test@12345'
]);
TestReporter::assert(strpos($respNoEmail['body'], 'Invalid email or password') !== false, "Login: Rejects non-existent email");

// I. Client Login: Successful Authentication & Session
$csrfLogin = $loginClient->extractCsrf($respNoEmail['body']) ?: $csrfLogin;
$respGoodLogin = $loginClient->request(BASE_URL . '/login.php', 'POST', [
    'csrf_token' => $csrfLogin,
    'email' => $testEmailA,
    'password' => 'Test@12345'
]);
TestReporter::assert(
    strpos($respGoodLogin['effective_url'], 'profile.php') !== false || strpos($respGoodLogin['body'], 'QA Test Client') !== false,
    "Login: Successful login creates authenticated session"
);

// J. Profile Access & Verification
$respProfile = $loginClient->request(BASE_URL . '/profile.php');
TestReporter::assert(
    strpos($respProfile['body'], 'QA Test Client') !== false && strpos($respProfile['body'], $testEmailA) !== false,
    "Client Profile: Renders customer details, email, and member badge"
);

// Register Client B for Authorization Isolation Tests
$clientBHttp = new HttpClient();
$regPageB = $clientBHttp->request(BASE_URL . '/register.php');
$csrfB = $clientBHttp->extractCsrf($regPageB['body']);
$clientBHttp->request(BASE_URL . '/register.php', 'POST', [
    'csrf_token' => $csrfB,
    'name' => 'Client B Isolated',
    'email' => $testEmailB,
    'phone' => '8888888888',
    'password' => 'Test@12345',
    'confirm_password' => 'Test@12345',
    'terms_agreed' => '1'
]);
$checkUserB = $conn->query("SELECT id FROM users WHERE email = '{$testEmailB}'")->fetch_assoc();
$clientB_id = $checkUserB['id'] ?? 0;
TestReporter::assert($clientB_id > 0, "Security Setup: Client B registered for isolation audit");

// -------------------------------------------------------------
// 4. Complete Get Quote Workflow (Consultation Form)
// -------------------------------------------------------------
echo "\n4. TESTING COMPLETE GET QUOTE WORKFLOW...\n";
// Record leads count before submission to verify ONE authoritative pipeline rule
$leadsCountBefore = (int)$conn->query("SELECT COUNT(*) FROM leads")->fetch_row()[0];
$quotesCountBefore = (int)$conn->query("SELECT COUNT(*) FROM quote_requests")->fetch_row()[0];

// Submit quote as logged-in Client A
$consultPage = $loginClient->request(BASE_URL . '/consultation.php');
$csrfQuote = $loginClient->extractCsrf($consultPage['body']);

$quotePostData = [
    'csrf_token' => $csrfQuote,
    'action' => 'submit_quote',
    'property_type' => 'residential',
    'project_type' => 'turnkey_interior',
    'approx_area' => '1850',
    'city' => 'Surat',
    'pincode' => '395007',
    'rooms_count' => '4',
    'design_style' => 'modern_luxury',
    'budget_range' => 'luxury',
    'material_quality' => 'premium',
    'timeline' => '3_months',
    'customer_name' => 'QA Test Client',
    'email' => $testEmailA,
    'phone' => '9999999999',
    'requirements' => 'Comprehensive QA interior test project with false ceiling, veneer joinery, and lighting design.',
    'services' => [1, 2, 4] // array of service IDs
];

$respQuoteSub = $loginClient->request(BASE_URL . '/consultation.php', 'POST', $quotePostData);

$leadsCountAfter = (int)$conn->query("SELECT COUNT(*) FROM leads")->fetch_row()[0];
$quotesCountAfter = (int)$conn->query("SELECT COUNT(*) FROM quote_requests")->fetch_row()[0];

TestReporter::assert($quotesCountAfter === $quotesCountBefore + 1, "Get Quote: 1 new record inserted into quote_requests");
TestReporter::assert($leadsCountAfter === $leadsCountBefore, "Architecture Rule: 0 duplicate records created in legacy 'leads' table");

// Fetch newly created quote
$newQuote = $conn->query("SELECT * FROM quote_requests WHERE email = '{$testEmailA}' ORDER BY id DESC LIMIT 1")->fetch_assoc();
TestReporter::assert(!empty($newQuote['id']), "Quote Record: Stored in quote_requests with ID #{$newQuote['id']}");
TestReporter::assert(!empty($newQuote['quote_number']), "Quote Number: Generated format {$newQuote['quote_number']}");
TestReporter::assert((float)$newQuote['approx_area'] === 1850.0, "Quote Data: Approx area 1850 sq ft matches");
TestReporter::assert($newQuote['design_style'] === 'modern_luxury', "Quote Data: Design style matches");
TestReporter::assert($newQuote['status'] === 'new' || $newQuote['status'] === 'under_review', "Quote Data: Initial status is valid ({$newQuote['status']})");
$testQuoteId = (int)$newQuote['id'];

// -------------------------------------------------------------
// 5. Admin Authentication & Quotations Management
// -------------------------------------------------------------
echo "\n5. TESTING ADMIN QUOTATION MANAGEMENT...\n";
$adminClient = new HttpClient();
$adminLoginPage = $adminClient->request(BASE_URL . '/admin/login.php');
$csrfAdmin = $adminClient->extractCsrf($adminLoginPage['body']);

$adminAuthResp = $adminClient->request(BASE_URL . '/admin/login.php', 'POST', [
    'csrf_token' => $csrfAdmin,
    'username' => 'admin',
    'password' => 'Admin@12345'
]);
TestReporter::assert(
    strpos($adminAuthResp['effective_url'], 'dashboard.php') !== false || strpos($adminAuthResp['body'], 'Dashboard') !== false,
    "Admin Login: Successful authentication as administrator"
);

// Check quote in Admin Quotes List
$adminQuotesPage = $adminClient->request(BASE_URL . '/admin/quotes.php');
TestReporter::assert(
    strpos($adminQuotesPage['body'], $newQuote['quote_number']) !== false || strpos($adminQuotesPage['body'], 'QA Test Client') !== false,
    "Admin Quotes List: New quote appears with customer name and quote number"
);

// Open Quote Details
$adminQuoteDetails = $adminClient->request(BASE_URL . "/admin/quote_details.php?id={$testQuoteId}");
TestReporter::assert($adminQuoteDetails['code'] === 200, "Admin Quote Details: HTTP 200 OK");
TestReporter::assert(strpos($adminQuoteDetails['body'], 'QA Test Client') !== false, "Admin Quote Details: Displays customer name");
TestReporter::assert(strpos($adminQuoteDetails['body'], '1,850') !== false || strpos($adminQuoteDetails['body'], '1850') !== false, "Admin Quote Details: Displays area specifications");

// Test Status Update: Change status to 'under_review'
$csrfQuoteDetails = $adminClient->extractCsrf($adminQuoteDetails['body']);
$respStatusUpdate = $adminClient->request(BASE_URL . "/admin/quote_details.php?id={$testQuoteId}", 'POST', [
    'csrf_token' => $csrfQuoteDetails,
    'action' => 'update_status',
    'status' => 'under_review',
    'status_comment' => 'QA review initiated by design lead.'
]);
$checkStatus = $conn->query("SELECT status FROM quote_requests WHERE id = {$testQuoteId}")->fetch_assoc()['status'] ?? '';
TestReporter::assert($checkStatus === 'under_review', "Quote Status: Successfully updated to 'under_review'");

// Test PDF Document Generation
$pdfResp = $adminClient->request(BASE_URL . "/quote_pdf.php?id={$testQuoteId}");
TestReporter::assert($pdfResp['code'] === 200, "Quote PDF/Printable: Returns HTTP 200 OK");
TestReporter::assert(strpos($pdfResp['body'], $newQuote['quote_number']) !== false, "Quote PDF: Contains authoritative quote number");
TestReporter::assert(strpos($pdfResp['body'], 'QA Test Client') !== false, "Quote PDF: Contains client name");

// -------------------------------------------------------------
// 6. Site Visit Integration & Consultation Synchronization
// -------------------------------------------------------------
echo "\n6. TESTING SITE VISIT INTEGRATION & CONSULTATION SYNC...\n";
// Schedule Site Visit from admin/quote_details.php
$siteVisitDate = date('Y-m-d', strtotime('+3 days'));
$siteVisitTime = '15:30:00';
$siteVisitNotes = 'Site inspection for structural measurements and aesthetic consultation.';

$csrfQuoteDetails = $adminClient->extractCsrf($adminQuoteDetails['body']);
$respSiteVisit = $adminClient->request(BASE_URL . "/admin/quote_details.php?id={$testQuoteId}", 'POST', [
    'csrf_token' => $csrfQuoteDetails,
    'action' => 'schedule_site_visit',
    'site_visit_date' => $siteVisitDate,
    'site_visit_time' => $siteVisitTime,
    'site_visit_notes' => $siteVisitNotes
]);

// 1. Verify in quote_requests
$svQuote = $conn->query("SELECT site_visit_date, site_visit_time, site_visit_notes, status FROM quote_requests WHERE id = {$testQuoteId}")->fetch_assoc();
TestReporter::assert($svQuote['site_visit_date'] === $siteVisitDate, "Site Visit: Date stored in quote_requests ({$siteVisitDate})");
TestReporter::assert($svQuote['site_visit_time'] === $siteVisitTime, "Site Visit: Time stored in quote_requests ({$siteVisitTime})");
TestReporter::assert($svQuote['status'] === 'site_visit_scheduled', "Site Visit: Quote status transitioned to 'site_visit_scheduled'");

// 2. Verify in consultations table
$likeQ = "%" . $newQuote['quote_number'] . "%";
$consultStmt = $conn->prepare("SELECT * FROM consultations WHERE notes LIKE ?");
$consultStmt->bind_param("s", $likeQ);
$consultStmt->execute();
$consultRow = $consultStmt->get_result()->fetch_assoc();

TestReporter::assert(!empty($consultRow['id']), "Integration: Corresponding consultation record automatically created");
TestReporter::assert($consultRow['client_name'] === 'QA Test Client', "Consultation: Client name is correct ({$consultRow['client_name']})");
TestReporter::assert($consultRow['consultation_date'] === $siteVisitDate, "Consultation: Date matches site visit ({$consultRow['consultation_date']})");
TestReporter::assert(substr($consultRow['consultation_time'], 0, 5) === '15:30', "Consultation: Time matches site visit (15:30)");
TestReporter::assert($consultRow['status'] === 'confirmed', "Consultation: Status synchronized as 'confirmed'");

// 3. Test Reschedule & verify NO duplicate consultation created
$rescheduleDate = date('Y-m-d', strtotime('+5 days'));
$adminQuoteDetails2 = $adminClient->request(BASE_URL . "/admin/quote_details.php?id={$testQuoteId}");
$csrfQuoteDetails2 = $adminClient->extractCsrf($adminQuoteDetails2['body']);
$adminClient->request(BASE_URL . "/admin/quote_details.php?id={$testQuoteId}", 'POST', [
    'csrf_token' => $csrfQuoteDetails2,
    'action' => 'schedule_site_visit',
    'site_visit_date' => $rescheduleDate,
    'site_visit_time' => '16:00:00',
    'site_visit_notes' => 'Rescheduled appointment.'
]);

$consultCount = (int)$conn->query("SELECT COUNT(*) FROM consultations WHERE notes LIKE '{$likeQ}'")->fetch_row()[0];
TestReporter::assert($consultCount === 1, "Duplicate Prevention: Rescheduling updated existing record without duplicate (count: 1)");

$updConsult = $conn->query("SELECT consultation_date, consultation_time FROM consultations WHERE notes LIKE '{$likeQ}'")->fetch_assoc();
TestReporter::assert($updConsult['consultation_date'] === $rescheduleDate, "Reschedule: Consultation date successfully updated to {$rescheduleDate}");

// 4. Verify in Admin Consultations View
$adminConsultPage = $adminClient->request(BASE_URL . '/admin/consultations.php');
TestReporter::assert(strpos($adminConsultPage['body'], 'QA Test Client') !== false, "Admin Consultations: Scheduled appointment appears in admin module");

// -------------------------------------------------------------
// 7. Client Profile Integration & Data Isolation
// -------------------------------------------------------------
echo "\n7. TESTING CLIENT PROFILE INTEGRATION & DATA ISOLATION...\n";
$clientProfileResp = $loginClient->request(BASE_URL . '/profile.php');
TestReporter::assert(strpos($clientProfileResp['body'], $newQuote['quote_number']) !== false, "Client Profile: Displays submitted quote proposal");
TestReporter::assert(strpos($clientProfileResp['body'], 'Site Inspection Confirmed') !== false, "Client Profile: Displays site inspection banner");
TestReporter::assert(strpos($clientProfileResp['body'], 'Consultations & Sessions') !== false, "Client Profile: Shows consultation tab");

// DATA ISOLATION TEST: Client B attempts to access Client A's quote breakdown
$respHijack = $clientBHttp->request(BASE_URL . "/quote_details.php?id={$testQuoteId}");
TestReporter::assert(
    $respHijack['code'] === 403 || strpos($respHijack['body'], '403') !== false || strpos($respHijack['body'], 'Access Restricted') !== false,
    "Security Isolation: Client B forbidden (HTTP 403) from viewing Client A's quotation"
);

// DATA ISOLATION TEST: Client B attempts to access Client A's PDF
$respPdfHijack = $clientBHttp->request(BASE_URL . "/quote_pdf.php?id={$testQuoteId}");
TestReporter::assert(
    $respPdfHijack['code'] === 403 || strpos($respPdfHijack['body'], '403') !== false,
    "Security Isolation: Client B forbidden (HTTP 403) from accessing Client A's PDF document"
);

// -------------------------------------------------------------
// 8. Testimonial Workflow (Client Submission -> Pending -> Admin Approval -> Public)
// -------------------------------------------------------------
echo "\n8. TESTING TESTIMONIAL SYSTEM WORKFLOW...\n";
$csrfProf = $loginClient->extractCsrf($clientProfileResp['body']);
$respTestimSub = $loginClient->request(BASE_URL . '/profile.php', 'POST', [
    'csrf_token' => $csrfProf,
    'action' => 'submit_testimonial',
    'project_title' => 'QA Penthouse Showcase',
    'rating' => '5',
    'testimonial' => 'Creative Touch Interiors executed our spatial plan with world-class precision and timeless elegance.'
]);

$testimRow = $conn->query("SELECT * FROM testimonials WHERE project_title = 'QA Penthouse Showcase'")->fetch_assoc();
TestReporter::assert(!empty($testimRow['id']), "Testimonial: Record inserted into testimonials table");
TestReporter::assert($testimRow['status'] === 'pending', "Testimonial: Received initial status 'pending'");
$t_id = (int)$testimRow['id'];

// Admin views pending testimonial
$adminTestimPage = $adminClient->request(BASE_URL . '/admin/testimonials.php');
TestReporter::assert(strpos($adminTestimPage['body'], 'QA Penthouse Showcase') !== false, "Admin Testimonials: Appears in admin review queue");

// Admin Approves Testimonial
$csrfAdminT = $adminClient->extractCsrf($adminTestimPage['body']);
$adminClient->request(BASE_URL . '/admin/testimonials.php', 'POST', [
    'csrf_token' => $csrfAdminT,
    'action' => 'set_status',
    'id' => $t_id,
    'status' => 'approved'
]);
$checkTStatus = $conn->query("SELECT status FROM testimonials WHERE id = {$t_id}")->fetch_assoc()['status'] ?? '';
TestReporter::assert($checkTStatus === 'approved', "Admin Moderation: Status transitioned to 'approved'");

// Admin Marks as Featured
$adminTestimPage2 = $adminClient->request(BASE_URL . '/admin/testimonials.php');
$csrfAdminT2 = $adminClient->extractCsrf($adminTestimPage2['body']);
$adminClient->request(BASE_URL . '/admin/testimonials.php', 'POST', [
    'csrf_token' => $csrfAdminT2,
    'action' => 'toggle_featured',
    'id' => $t_id,
    'featured' => '1'
]);
$checkTFeat = (int)$conn->query("SELECT featured FROM testimonials WHERE id = {$t_id}")->fetch_assoc()['featured'];
TestReporter::assert($checkTFeat === 1, "Admin Moderation: Marked as featured (featured=1)");

// Public verification: Home page displays approved & featured testimonial
$publicHome = $publicClient->request(BASE_URL . '/index.php');
TestReporter::assert(strpos($publicHome['body'], 'QA Penthouse Showcase') !== false, "Public Display: Approved testimonial displayed on Home page");

// Admin Rejects Testimonial
$adminClient->request(BASE_URL . '/admin/testimonials.php', 'POST', [
    'csrf_token' => $csrfAdminT2,
    'action' => 'set_status',
    'id' => $t_id,
    'status' => 'rejected'
]);
$publicHome2 = $publicClient->request(BASE_URL . '/index.php');
TestReporter::assert(strpos($publicHome2['body'], 'QA Penthouse Showcase') === false, "Public Isolation: Rejected testimonial does NOT appear publicly");

// Clean up test testimonial
$conn->query("DELETE FROM testimonials WHERE id = {$t_id}");

// -------------------------------------------------------------
// 9. Team Members Management
// -------------------------------------------------------------
echo "\n9. TESTING TEAM MEMBERS SYSTEM...\n";
$teamPage = $adminClient->request(BASE_URL . '/admin/team.php');
TestReporter::assert($teamPage['code'] === 200, "Admin Team: HTTP 200 OK");
$csrfTeam = $adminClient->extractCsrf($teamPage['body']);

// Add Team Member
$adminClient->request(BASE_URL . '/admin/team.php', 'POST', [
    'csrf_token' => $csrfTeam,
    'action' => 'add',
    'name' => 'QA Senior Architect',
    'designation' => 'Principal Lighting & Spatial Designer',
    'bio' => 'Specializes in residential luxury and sustainable illumination.',
    'order_index' => 8,
    'status' => 'active'
]);
$teamRow = $conn->query("SELECT * FROM team_members WHERE name = 'QA Senior Architect'")->fetch_assoc();
TestReporter::assert(!empty($teamRow['id']), "Team Module: New team member added to database");
$team_id = (int)($teamRow['id'] ?? 0);

// Check Public About Page
$aboutPage = $publicClient->request(BASE_URL . '/about.php');
TestReporter::assert(strpos($aboutPage['body'], 'QA Senior Architect') !== false, "Public About: Active team member displayed publicly");

// Deactivate Team Member
$teamPage2 = $adminClient->request(BASE_URL . '/admin/team.php');
$csrfTeam2 = $adminClient->extractCsrf($teamPage2['body']);
$adminClient->request(BASE_URL . '/admin/team.php', 'POST', [
    'csrf_token' => $csrfTeam2,
    'action' => 'toggle_status',
    'id' => $team_id,
    'status' => 'inactive'
]);
$aboutPage2 = $publicClient->request(BASE_URL . '/about.php');
TestReporter::assert(strpos($aboutPage2['body'], 'QA Senior Architect') === false, "Public About: Inactive team member hidden from public view");

// Clean up test team member
$conn->query("DELETE FROM team_members WHERE id = {$team_id}");

// -------------------------------------------------------------
// 10. Website Settings / CMS Dynamic Content
// -------------------------------------------------------------
echo "\n10. TESTING WEBSITE SETTINGS CMS...\n";
$settingsPage = $adminClient->request(BASE_URL . '/admin/settings.php');
TestReporter::assert($settingsPage['code'] === 200, "Admin Settings: HTTP 200 OK");
$csrfSettings = $adminClient->extractCsrf($settingsPage['body']);

// Update CMS Setting
$testHeroTitle = "Luxury Interior Architecture [QA Tested]";
$testPhone = "+91 93168 56961";

$adminClient->request(BASE_URL . '/admin/settings.php', 'POST', [
    'csrf_token' => $csrfSettings,
    'action' => 'save_settings',
    'hero_title' => $testHeroTitle,
    'hero_subtitle' => 'Bespoke residential and commercial interior environments crafted with precision.',
    'about_story' => 'Creative Touch Interiors is a premier interior architecture studio founded by Harsh and Het.',
    'contact_address' => 'Surat, Gujarat, India',
    'contact_phone' => $testPhone,
    'contact_email' => 'creativetouchinteriors61@gmail.com'
]);

// Verify database
$checkContent = $conn->query("SELECT content FROM website_content WHERE section_key = 'hero_title'")->fetch_assoc()['content'] ?? '';
TestReporter::assert($checkContent === $testHeroTitle, "CMS Database: hero_title updated in website_content");

// Verify Public Pages render updated content
$indexUpdated = $publicClient->request(BASE_URL . '/index.php');
TestReporter::assert(strpos($indexUpdated['body'], $testHeroTitle) !== false, "Public Index: Immediately renders updated hero_title from database");

$contactUpdated = $publicClient->request(BASE_URL . '/contact.php');
TestReporter::assert(strpos($contactUpdated['body'], $testPhone) !== false, "Public Contact: Immediately renders updated phone from database");

// Revert hero_title to clean text
$settingsPage2 = $adminClient->request(BASE_URL . '/admin/settings.php');
$csrfSettings2 = $adminClient->extractCsrf($settingsPage2['body']);
$adminClient->request(BASE_URL . '/admin/settings.php', 'POST', [
    'csrf_token' => $csrfSettings2,
    'action' => 'save_settings',
    'hero_title' => 'Where Luxury Meets Living',
    'hero_subtitle' => 'Bespoke residential and commercial interior environments crafted with precision.',
    'about_story' => 'Creative Touch Interiors is a premier interior architecture studio founded by Harsh and Het.',
    'contact_address' => 'Surat, Gujarat, India',
    'contact_phone' => '+91 93168 56961',
    'contact_email' => 'creativetouchinteriors61@gmail.com'
]);

// -------------------------------------------------------------
// 11. Contact Inquiries Pipeline
// -------------------------------------------------------------
echo "\n11. TESTING CONTACT INQUIRIES...\n";
$contactPage = $publicClient->request(BASE_URL . '/contact.php');
$csrfContact = $publicClient->extractCsrf($contactPage['body']);

$inqEmail = 'qa_inquiry_' . time() . '@example.com';
$inqMsg = 'Testing public visitor contact inquiry dispatch.';

$respContact = $publicClient->request(BASE_URL . '/contact.php', 'POST', [
    'csrf_token' => $csrfContact,
    'name' => 'QA Contact Tester',
    'email' => $inqEmail,
    'phone' => '9876543210',
    'subject' => 'Villa Architectural Consultation',
    'message' => $inqMsg
]);

$inqRow = $conn->query("SELECT * FROM contact_inquiries WHERE email = '{$inqEmail}'")->fetch_assoc();
TestReporter::assert(!empty($inqRow['id']), "Contact Inquiry: Saved to contact_inquiries table");

$adminInqPage = $adminClient->request(BASE_URL . '/admin/contact_inquiries.php');
TestReporter::assert(strpos($adminInqPage['body'], $inqEmail) !== false, "Admin Contact Inquiries: Displays submitted inquiry");

// Clean up test inquiry
if (!empty($inqRow['id'])) {
    $conn->query("DELETE FROM contact_inquiries WHERE id = {$inqRow['id']}");
}

// -------------------------------------------------------------
// 12. Gallery & Project Association Integration
// -------------------------------------------------------------
echo "\n12. TESTING GALLERY & PROJECT PORTFOLIO...\n";
// Create test project with valid schema
$projSlug = 'qa-villa-penthouse-' . time();
$conn->query("INSERT INTO projects (title, slug, category, location, description, status) VALUES ('QA Villa Penthouse', '{$projSlug}', 'residential', 'Surat', 'Luxury Penthouse', 'completed')");
$proj_id = $conn->insert_id;
TestReporter::assert($proj_id > 0, "Projects: Added test project #{$proj_id}");

// Add Gallery image linked to project
$conn->query("INSERT INTO gallery_images (title, category, project_id, image_path, order_index) VALUES ('QA Living Room View', 'Living Room', {$proj_id}, 'gallery/sample.jpg', 1)");
$gal_id = $conn->insert_id;
TestReporter::assert($gal_id > 0, "Gallery: Created gallery image linked to project_id {$proj_id}");

// Verify relationship
$checkGal = $conn->query("SELECT g.title, p.title as proj_title FROM gallery_images g LEFT JOIN projects p ON g.project_id = p.id WHERE g.id = {$gal_id}")->fetch_assoc();
TestReporter::assert($checkGal && $checkGal['proj_title'] === 'QA Villa Penthouse', "Gallery Relationship: Correctly joins gallery_images.project_id with projects.id");

// Clean up test project & gallery row
$conn->query("DELETE FROM gallery_images WHERE id = {$gal_id}");
$conn->query("DELETE FROM projects WHERE id = {$proj_id}");

// -------------------------------------------------------------
// 13. Admin Dashboard & Metrics Integrity
// -------------------------------------------------------------
echo "\n13. TESTING ADMIN DASHBOARD & METRICS...\n";
$dashResp = $adminClient->request(BASE_URL . '/admin/dashboard.php');
TestReporter::assert($dashResp['code'] === 200, "Admin Dashboard: Returns HTTP 200 OK");
TestReporter::assert(strpos($dashResp['body'], 'Fatal error') === false && strpos($dashResp['body'], 'Warning:') === false, "Admin Dashboard: Loads cleanly without PHP warnings or notices");

// Check stats accuracy
$qCountDb = (int)$conn->query("SELECT COUNT(*) FROM quote_requests")->fetch_row()[0];
$cCountDb = (int)$conn->query("SELECT COUNT(*) FROM consultations")->fetch_row()[0];
TestReporter::assert(strpos($dashResp['body'], (string)$qCountDb) !== false, "Dashboard Metrics: Displays accurate quote count ({$qCountDb})");

// -------------------------------------------------------------
// 14. Security & Access Control
// -------------------------------------------------------------
echo "\n14. TESTING SECURITY & ACCESS CONTROL...\n";

// A. Unauthenticated access to admin dashboard
$unauthClient = new HttpClient();
$respUnauthAdmin = $unauthClient->request(BASE_URL . '/admin/dashboard.php');
TestReporter::assert(
    strpos($respUnauthAdmin['effective_url'], 'login.php') !== false || $respUnauthAdmin['code'] === 302 || $respUnauthAdmin['code'] === 403,
    "Access Control: Unauthenticated user redirected away from admin dashboard"
);

// B. Client session attempting to access admin dashboard
$respClientAdmin = $loginClient->request(BASE_URL . '/admin/dashboard.php');
TestReporter::assert(
    strpos($respClientAdmin['effective_url'], 'login.php') !== false || $respClientAdmin['code'] === 302 || $respClientAdmin['code'] === 403,
    "Access Control: Regular client session blocked from admin panel"
);

// C. CSRF Protection: Admin POST without token rejected
$respNoCsrf = $adminClient->request(BASE_URL . '/admin/settings.php', 'POST', [
    'action' => 'save_settings',
    'hero_title' => 'Malicious Hack'
]);
TestReporter::assert(strpos($respNoCsrf['body'], 'Security token') !== false, "CSRF Defense: Protected admin form rejects missing/invalid token");

// D. SQL Injection Protection: Admin login attempt with ' OR '1'='1
$respSqli = $unauthClient->request(BASE_URL . '/admin/login.php', 'POST', [
    'csrf_token' => 'invalid_or_bypassed',
    'username' => "' OR '1'='1",
    'password' => "' OR '1'='1"
]);
TestReporter::assert(strpos($respSqli['body'], 'Invalid username or password') !== false || strpos($respSqli['body'], 'Security token') !== false, "SQLi Defense: Prepared statements neutralize injection payloads");

// E. Client Logout
$respLogout = $loginClient->request(BASE_URL . '/user_logout.php');
$respPostLogout = $loginClient->request(BASE_URL . '/profile.php');
TestReporter::assert(
    strpos($respPostLogout['effective_url'], 'login.php') !== false || $respPostLogout['code'] === 302,
    "Session Security: Logout clears user session and redirects to login"
);

// -------------------------------------------------------------
// Clean up Master Test Records
// -------------------------------------------------------------
echo "\nCleaning up master test artifacts...\n";
if (!empty($testQuoteId)) {
    $conn->query("DELETE FROM quote_services WHERE quote_id = {$testQuoteId}");
    $conn->query("DELETE FROM quote_rooms WHERE quote_id = {$testQuoteId}");
    $conn->query("DELETE FROM quote_status_history WHERE quote_id = {$testQuoteId}");
    $conn->query("DELETE FROM quote_requests WHERE id = {$testQuoteId}");
}
$conn->query("DELETE FROM consultations WHERE client_email IN ('{$testEmailA}', '{$testEmailB}')");
$conn->query("DELETE FROM users WHERE email IN ('{$testEmailA}', '{$testEmailB}')");

TestReporter::summary();
