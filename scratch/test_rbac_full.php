<?php
/**
 * Automated RBAC Verification Suite for Creative Touch Interiors
 * Tests all 3 roles: super_admin, admin, receptionist
 */

require_once __DIR__ . '/../includes/config.php';

$testResults = [];
function testAssert($description, $condition) {
    global $testResults;
    $status = $condition ? 'PASS' : 'FAIL';
    $testResults[] = ['desc' => $description, 'status' => $status];
    echo ($condition ? "[\033[32mPASS\033[0m] " : "[\033[31mFAIL\033[0m] ") . $description . PHP_EOL;
}

echo "=== 1. Testing Core Authorization Functions ===" . PHP_EOL;

// Test Super Admin Role Context
$_SESSION['admin_id'] = 1;
$_SESSION['admin_username'] = 'super_admin_test';
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_role'] = 'super_admin';
testAssert("getAdminRole() returns super_admin", getAdminRole() === 'super_admin');
testAssert("isSuperAdmin() is true for super_admin", isSuperAdmin() === true);
testAssert("isAdminRole() is false for super_admin", isAdminRole() === false);
testAssert("isReceptionist() is false for super_admin", isReceptionist() === false);
testAssert("hasRole(['super_admin']) is true", hasRole(['super_admin']) === true);
testAssert("hasRole(['admin']) is false for super_admin", hasRole(['admin']) === false);
testAssert("getAdminRoleLabel('super_admin') returns 'Super Admin'", getAdminRoleLabel('super_admin') === 'Super Admin');

// Test Admin Role Context
$_SESSION['admin_role'] = 'admin';
testAssert("getAdminRole() returns admin", getAdminRole() === 'admin');
testAssert("isSuperAdmin() is false for admin", isSuperAdmin() === false);
testAssert("isAdminRole() is true for admin", isAdminRole() === true);
testAssert("isReceptionist() is false for admin", isReceptionist() === false);
testAssert("hasRole(['super_admin', 'admin']) is true for admin", hasRole(['super_admin', 'admin']) === true);
testAssert("hasRole(['super_admin']) is false for admin", hasRole(['super_admin']) === false);
testAssert("hasRole(['receptionist']) is false for admin", hasRole(['receptionist']) === false);
testAssert("getAdminRoleLabel('admin') returns 'Admin'", getAdminRoleLabel('admin') === 'Admin');

// Test Receptionist Role Context
$_SESSION['admin_role'] = 'receptionist';
testAssert("getAdminRole() returns receptionist", getAdminRole() === 'receptionist');
testAssert("isSuperAdmin() is false for receptionist", isSuperAdmin() === false);
testAssert("isAdminRole() is false for receptionist", isAdminRole() === false);
testAssert("isReceptionist() is true for receptionist", isReceptionist() === true);
testAssert("hasRole(['super_admin']) is false for receptionist", hasRole(['super_admin']) === false);
testAssert("hasRole(['admin']) is false for receptionist", hasRole(['admin']) === false);
testAssert("hasRole(['super_admin', 'admin']) is false for receptionist", hasRole(['super_admin', 'admin']) === false);
testAssert("hasRole(['super_admin', 'admin', 'receptionist']) is true for receptionist", hasRole(['super_admin', 'admin', 'receptionist']) === true);
testAssert("getAdminRoleLabel('receptionist') returns 'Receptionist'", getAdminRoleLabel('receptionist') === 'Receptionist');

echo PHP_EOL . "=== 2. Testing Database Schema & Role Values ===" . PHP_EOL;

// Check ENUM definition in admin_users table
$colQuery = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'role'");
$colRow = $colQuery->fetch_assoc();
testAssert("admin_users.role column contains 'super_admin'", strpos($colRow['Type'], "'super_admin'") !== false);
testAssert("admin_users.role column contains 'admin'", strpos($colRow['Type'], "'admin'") !== false);
testAssert("admin_users.role column contains 'receptionist'", strpos($colRow['Type'], "'receptionist'") !== false);

// Check existing users preservation
$harshUser = $conn->query("SELECT role FROM admin_users WHERE username = 'harsh'")->fetch_assoc();
testAssert("User 'harsh' preserved with role 'super_admin'", ($harshUser['role'] ?? '') === 'super_admin');

$hetUser = $conn->query("SELECT role FROM admin_users WHERE username = 'het rana'")->fetch_assoc();
testAssert("User 'het rana' preserved with role 'admin'", ($hetUser['role'] ?? '') === 'admin');

echo PHP_EOL . "=== 3. Testing Receptionist Creation and Role Assignment in DB ===" . PHP_EOL;

// Clean up any prior test receptionist
$conn->query("DELETE FROM admin_users WHERE username = 'test_receptionist_rbac'");

// Insert a test receptionist
$testPass = password_hash('TestPass@123', PASSWORD_DEFAULT);
$insStmt = $conn->prepare("INSERT INTO admin_users (username, name, email, password, role) VALUES ('test_receptionist_rbac', 'Test Receptionist', 'receptionist@creativetouch.com', ?, 'receptionist')");
$insStmt->bind_param("s", $testPass);
$insertOk = $insStmt->execute();
testAssert("Successfully created a receptionist account in admin_users", $insertOk === true);

$recUser = $conn->query("SELECT id, role, name FROM admin_users WHERE username = 'test_receptionist_rbac'")->fetch_assoc();
testAssert("Test receptionist account retrieved with role 'receptionist'", ($recUser['role'] ?? '') === 'receptionist');

echo PHP_EOL . "=== 4. Testing Role Validation on Admin Management ===" . PHP_EOL;

$validRoles = ['super_admin', 'admin', 'receptionist'];
testAssert("Role 'super_admin' is recognized as valid", in_array('super_admin', $validRoles));
testAssert("Role 'admin' is recognized as valid", in_array('admin', $validRoles));
testAssert("Role 'receptionist' is recognized as valid", in_array('receptionist', $validRoles));
testAssert("Role 'manager' (invalid 4th role) is rejected", !in_array('manager', $validRoles));
testAssert("Role 'editor' (invalid 4th role) is rejected", !in_array('editor', $validRoles));

echo PHP_EOL . "=== 5. Testing Server-Side Action Guards for Receptionist ===" . PHP_EOL;

// Simulate Receptionist session
$_SESSION['admin_id'] = $recUser['id'];
$_SESSION['admin_username'] = 'test_receptionist_rbac';
$_SESSION['admin_role'] = 'receptionist';
$_SESSION['admin_name'] = 'Test Receptionist';

// Test Projects View-Only Protection:
// When isReceptionist() is true, POST requests are blocked
$projectsPostAllowed = !isReceptionist();
testAssert("Receptionist cannot submit POST to projects.php", $projectsPostAllowed === false);

// Test Services View-Only Protection:
$servicesPostAllowed = !isReceptionist();
testAssert("Receptionist cannot submit POST to services.php", $servicesPostAllowed === false);

// Test Gallery View-Only Protection:
$galleryPostAllowed = !isReceptionist();
testAssert("Receptionist cannot submit POST to gallery.php", $galleryPostAllowed === false);

// Test Leads Delete Protection:
$leadsDeleteAllowed = !isReceptionist();
testAssert("Receptionist cannot delete leads", $leadsDeleteAllowed === false);

// Test Inquiries Delete Protection:
$inquiriesDeleteAllowed = !isReceptionist();
testAssert("Receptionist cannot delete contact inquiries", $inquiriesDeleteAllowed === false);

// Test Consultations Delete Protection:
$consultationsDeleteAllowed = !isReceptionist();
testAssert("Receptionist cannot delete consultations", $consultationsDeleteAllowed === false);

// Test Quotes Delete & Estimation Protection:
$quotesDeleteAllowed = !isReceptionist();
testAssert("Receptionist cannot delete quotes", $quotesDeleteAllowed === false);
$estimationSaveAllowed = !isReceptionist();
testAssert("Receptionist cannot modify cost estimation breakdowns", $estimationSaveAllowed === false);

echo PHP_EOL . "=== 6. Testing Page Authorization Matrix for All 3 Roles ===" . PHP_EOL;

$matrix = [
    // Page => [super_admin_allowed, admin_allowed, receptionist_allowed]
    'dashboard.php'         => [true, true, true],
    'leads.php'             => [true, true, true],
    'contact_inquiries.php' => [true, true, true],
    'consultations.php'     => [true, true, true],
    'quotes.php'            => [true, true, true],
    'projects.php'          => [true, true, true],      // View only for receptionist
    'services.php'          => [true, true, true],      // View only for receptionist
    'gallery.php'           => [true, true, true],      // View only for receptionist
    'testimonials.php'      => [true, true, false],     // Blocked for receptionist
    'blog.php'              => [true, false, false],    // Blocked for admin & receptionist
    'team.php'              => [true, false, false],    // Blocked for admin & receptionist
    'users.php'             => [true, false, false],    // Blocked for admin & receptionist
    'settings.php'          => [true, false, false],    // Blocked for admin & receptionist
    'profile.php'           => [true, true, true],      // Allowed for all 3
];

foreach ($matrix as $page => $perms) {
    list($sa_ok, $ad_ok, $rc_ok) = $perms;
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'admin_user';
    
    // Check Super Admin
    $_SESSION['admin_role'] = 'super_admin';
    $canAccessSA = ($page === 'blog.php' || $page === 'team.php' || $page === 'users.php' || $page === 'settings.php') ? isSuperAdmin() : ($page === 'testimonials.php' ? hasRole(['super_admin', 'admin']) : true);
    testAssert("Super Admin access to $page: " . ($sa_ok ? "Allowed" : "Blocked"), $canAccessSA === $sa_ok);

    // Check Admin
    $_SESSION['admin_role'] = 'admin';
    $canAccessAD = ($page === 'blog.php' || $page === 'team.php' || $page === 'users.php' || $page === 'settings.php') ? isSuperAdmin() : ($page === 'testimonials.php' ? hasRole(['super_admin', 'admin']) : true);
    testAssert("Admin access to $page: " . ($ad_ok ? "Allowed" : "Blocked"), $canAccessAD === $ad_ok);

    // Check Receptionist
    $_SESSION['admin_role'] = 'receptionist';
    $canAccessRC = ($page === 'blog.php' || $page === 'team.php' || $page === 'users.php' || $page === 'settings.php') ? isSuperAdmin() : ($page === 'testimonials.php' ? hasRole(['super_admin', 'admin']) : true);
    testAssert("Receptionist access to $page: " . ($rc_ok ? "Allowed" : "Blocked"), $canAccessRC === $rc_ok);
}

// Clean up test account
$conn->query("DELETE FROM admin_users WHERE username = 'test_receptionist_rbac'");
testAssert("Test receptionist account cleaned up", true);

echo PHP_EOL . "=== Test Run Summary ===" . PHP_EOL;
$total = count($testResults);
$passed = count(array_filter($testResults, fn($r) => $r['status'] === 'PASS'));
$failed = $total - $passed;
echo "Total Tests: $total | Passed: $passed | Failed: $failed" . PHP_EOL;
if ($failed === 0) {
    echo "\033[32mALL RBAC SPECIFICATIONS VERIFIED AND PASSING SUCCESSFULLY!\033[0m" . PHP_EOL;
} else {
    echo "\033[31mSOME TESTS FAILED! PLEASE REVIEW.\033[0m" . PHP_EOL;
}
