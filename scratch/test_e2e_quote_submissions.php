<?php
/**
 * End-to-End Quotation Submission Test across Budget Ranges & Typologies
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/quote_engine.php';

echo "=================================================================\n";
echo "E2E SUBMISSION TEST: BUDGET-AWARE QUOTATION PIPELINE (v2.5.0)\n";
echo "=================================================================\n\n";

$pass = 0;
$fail = 0;

function runAssertion($name, $cond, $details = '') {
    global $pass, $fail;
    if ($cond) {
        echo " [PASS] $name\n";
        $pass++;
    } else {
        echo "![FAIL] $name: $details\n";
        $fail++;
    }
}

// 1. Ensure test user exists
$userRes = $conn->query("SELECT id, name, email, phone FROM users WHERE email = 'testuser@example.com' LIMIT 1");
if ($userRes && $userRes->num_rows > 0) {
    $testUser = $userRes->fetch_assoc();
} else {
    $hashed = password_hash('TestPass123!', PASSWORD_BCRYPT);
    $conn->query("INSERT INTO users (name, email, password, phone) VALUES ('Harsh Chotaliya', 'testuser@example.com', '$hashed', '9876543210')");
    $testUser = ['id' => $conn->insert_id, 'name' => 'Harsh Chotaliya', 'email' => 'testuser@example.com', 'phone' => '9876543210'];
}

$scenarios = [
    [
        'name' => 'Residential with ₹1L–₹3L Budget (Calculated scope ₹4.8L+ -> Over Budget)',
        'project_type' => 'Residential',
        'property_type' => 'Villa / Independent House',
        'approx_area' => 1200,
        'package_type' => 'Standard',
        'design_style' => 'Modern Luxury',
        'budget_range' => '₹1L – ₹3L',
        'selected_services' => [1, 2], // 3D Walkthrough + False Ceiling
        'expected_status' => 'over'
    ],
    [
        'name' => 'Commercial with ₹10L–₹25L Budget (Within Budget)',
        'project_type' => 'Commercial',
        'property_type' => 'Retail Showroom',
        'approx_area' => 800,
        'package_type' => 'Standard',
        'design_style' => 'Minimalist Contemporary',
        'budget_range' => '₹10L – ₹25L',
        'selected_services' => [12], // Retail Display & Showroom Racks
        'expected_status' => 'within'
    ],
    [
        'name' => 'Executive Office with ₹1Cr+ Budget (Within Target)',
        'project_type' => 'Office',
        'property_type' => 'Corporate Office Floor',
        'approx_area' => 6000,
        'package_type' => 'Luxury',
        'design_style' => 'Modern Luxury',
        'budget_range' => '₹1Cr+',
        'selected_services' => [23], // Executive Acoustic & Conference
        'expected_status' => 'within'
    ],
    [
        'name' => 'Residential with "I\'m Not Sure Yet" (Unspecified Budget)',
        'project_type' => 'Residential',
        'property_type' => 'Villa / Independent House',
        'approx_area' => 1500,
        'package_type' => 'Premium',
        'design_style' => 'Scandinavian Warmth',
        'budget_range' => "I'm Not Sure Yet",
        'selected_services' => [],
        'expected_status' => 'unspecified'
    ]
];

foreach ($scenarios as $sc) {
    echo "\nTesting: {$sc['name']}...\n";

    // Simulate submission calculation logic
    $base_rate_sqft = 1200.00;
    if (stripos($sc['design_style'], 'luxury') !== false) {
        $base_rate_sqft = 1800.00;
    } elseif (stripos($sc['design_style'], 'minimalist') !== false) {
        $base_rate_sqft = 1100.00;
    }
    $base_cost = round($sc['approx_area'] * $base_rate_sqft, 2);
    $package_multiplier = getPackageMultiplier($sc['package_type']);

    $service_cost = 0.00;
    $calculated_services = [];
    if (!empty($sc['selected_services'])) {
        $inClause = implode(',', $sc['selected_services']);
        $srvQuery = $conn->query("SELECT * FROM services WHERE id IN ($inClause)");
        while ($sRow = $srvQuery->fetch_assoc()) {
            $sType = $sRow['service_type'] ?? 'area_based';
            $uRate = (float)($sRow['unit_rate'] > 0 ? $sRow['unit_rate'] : ($sRow['starting_price'] ?? 0));
            $sMin = (float)($sRow['min_charge'] ?? 0);
            $sWastage = (float)($sRow['default_wastage_percent'] ?? 0);
            $itemCalc = calculateServiceItem($sType, $uRate, 1.0, $sc['approx_area'], 1.0, $sWastage, $sMin);
            $service_cost += $itemCalc['final_amount'];
            $calculated_services[] = $itemCalc;
        }
    }

    $grandCalc = calculateQuoteGrandTotal($base_cost, $service_cost, $package_multiplier, 0.0, 'none', 0.0, 18.0);
    $estimated_total = $grandCalc['grand_total'];
    $quote_number = generateQuoteNumber($conn);
    $budget_range = formatBudgetDisplay($sc['budget_range']);

    $stmt = $conn->prepare("INSERT INTO quote_requests (
        quote_number, user_id, customer_name, email, phone, address, city, state, pincode,
        project_type, property_type, approx_area, measurement_status, rooms_count, floors_count,
        design_style, budget_range, package_type, package_multiplier, status,
        base_rate_per_sqft, base_cost, service_cost, tax_percentage, tax_amount, estimated_total
    ) VALUES (
        ?, ?, ?, ?, ?, 'Ring Road', 'Surat', 'Gujarat', '395004',
        ?, ?, ?, 'known', 3, 1,
        ?, ?, ?, ?, 'new',
        ?, ?, ?, 18.0, ?, ?
    )");

    $tax_amt = $grandCalc['tax_amount'];
    $stmt->bind_param(
        "sisssssdsssdddddd",
        $quote_number, $testUser['id'], $testUser['name'], $testUser['email'], $testUser['phone'],
        $sc['project_type'], $sc['property_type'], $sc['approx_area'],
        $sc['design_style'], $budget_range, $sc['package_type'], $package_multiplier,
        $base_rate_sqft, $base_cost, $service_cost, $tax_amt, $estimated_total
    );

    $ok = $stmt->execute();
    $newId = $stmt->insert_id;
    runAssertion("Insert quote {$quote_number}", $ok && $newId > 0);

    // Verify budget evaluation
    $bEval = evaluateQuoteBudget($estimated_total, $budget_range);
    runAssertion("Expected budget status '{$sc['expected_status']}'", $bEval['status'] === $sc['expected_status'], "Got: " . $bEval['status']);
    runAssertion("Mathematical estimate is truthful (> 0)", $estimated_total > 0);

    // Verify Admin details view
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'admin';
    $_SESSION['admin_role'] = 'admin';
    $_GET['id'] = $newId;
    chdir(__DIR__ . '/../admin');
    ob_start();
    include 'quote_details.php';
    $admContent = ob_get_clean();
    runAssertion("Admin details rendered for {$quote_number}", strpos($admContent, $quote_number) !== false);

    // Verify PDF view
    chdir(__DIR__ . '/..');
    ob_start();
    include 'quote_pdf.php';
    $pdfContent = ob_get_clean();
    runAssertion("PDF rendered for {$quote_number}", strpos($pdfContent, $quote_number) !== false);
}

echo "\n=================================================================\n";
echo "E2E SUBMISSION RESULTS: Passed: $pass | Failed: $fail\n";
echo "=================================================================\n";

if ($fail === 0) {
    echo ">>> ALL E2E SUBMISSIONS PASSED! <<<\n";
    exit(0);
} else {
    exit(1);
}
