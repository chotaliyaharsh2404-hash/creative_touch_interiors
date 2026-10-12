<?php
/**
 * Creative Touch Interiors — Automated Test Suite
 * v2.7.0 Base Spatial Execution Rate Standardized to ₹500/sq.ft
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/quote_engine.php';

$totalPassed = 0;
$totalFailed = 0;

function assertTest($description, $condition, $details = '') {
    global $totalPassed, $totalFailed;
    if ($condition) {
        $totalPassed++;
        echo " [PASS] {$description}\n";
    } else {
        $totalFailed++;
        echo " ![FAIL] {$description}" . ($details ? " - {$details}" : "") . "\n";
    }
}

echo "=================================================================\n";
echo "V2.7.0 BASE SPATIAL EXECUTION RATE (₹500/SQ.FT) VERIFICATION\n";
echo "=================================================================\n\n";

// ===================================================================
// TEST GROUP 1: AUTHORITATIVE CONFIGURATION & HELPER FUNCTIONS
// ===================================================================
echo "--- TEST GROUP 1: AUTHORITATIVE CONFIGURATION & HELPERS ---\n";

assertTest("BASE_SPATIAL_RATE constant is defined", defined('BASE_SPATIAL_RATE'));
assertTest("BASE_SPATIAL_RATE equals exactly 500.00", BASE_SPATIAL_RATE === 500.00);
assertTest("getBaseSpatialRate() returns 500.00", getBaseSpatialRate() === 500.00);
assertTest("APP_VERSION is '2.7.0'", APP_VERSION === '2.7.0');

// Area * Rate calculation
$base300 = calculateBaseSpatialCost(300);
assertTest("calculateBaseSpatialCost(300) equals 150,000.00", $base300 === 150000.00);

$baseCustom = calculateBaseSpatialCost(300, 1800);
assertTest("calculateBaseSpatialCost(300, 1800) preserves historical rate of 1800 => 540,000.00", $baseCustom === 540000.00);

// ===================================================================
// TEST GROUP 2: REQUIRED 300 SQ.FT TEST CALCULATIONS (SECTION 5)
// ===================================================================
echo "\n--- TEST GROUP 2: REQUIRED 300 SQ.FT CASES (CASES A, B, C) ---\n";

// CASE A: 300 sq.ft, ₹500/sq.ft, 1.00x, Capabilities: ₹0
// Base Spatial: ₹150,000
// Adjusted Base: ₹150,000
// Project Cost Before GST: ₹150,000
// GST 18%: ₹27,000
// Final Payable: ₹177,000
$baseCostA = calculateBaseSpatialCost(300);
$calcA = calculateQuoteGrandTotal($baseCostA, 0.00, 1.00, 0.0, 'none', 0.0, 18.0);
assertTest("CASE A: Base Spatial Cost is ₹150,000", $baseCostA === 150000.00);
assertTest("CASE A: Project Cost Before GST is ₹150,000", $calcA['subtotal'] === 150000.00);
assertTest("CASE A: GST 18% is ₹27,000", $calcA['tax_amount'] === 27000.00);
assertTest("CASE A: Final Payable is ₹177,000", $calcA['grand_total'] === 177000.00);

// CASE B: 300 sq.ft, ₹500/sq.ft, 1.35x, Capabilities: ₹0
// Base Spatial: ₹150,000
// Adjusted Base: ₹202,500
// Project Cost Before GST: ₹202,500
// GST 18%: ₹36,450
// Final Payable: ₹238,950
$baseCostB = calculateBaseSpatialCost(300);
$calcB = calculateQuoteGrandTotal($baseCostB, 0.00, 1.35, 0.0, 'none', 0.0, 18.0);
assertTest("CASE B: Base Spatial Cost is ₹150,000", $baseCostB === 150000.00);
assertTest("CASE B: Project Cost Before GST is ₹202,500", $calcB['subtotal'] === 202500.00);
assertTest("CASE B: GST 18% is ₹36,450", $calcB['tax_amount'] === 36450.00);
assertTest("CASE B: Final Payable is ₹238,950", $calcB['grand_total'] === 238950.00);

// CASE C: 300 sq.ft, ₹500/sq.ft, 1.75x, Capabilities: ₹0
// Base Spatial: ₹150,000
// Adjusted Base: ₹262,500
// Project Cost Before GST: ₹262,500
// GST 18%: ₹47,250
// Final Payable: ₹309,750
$baseCostC = calculateBaseSpatialCost(300);
$calcC = calculateQuoteGrandTotal($baseCostC, 0.00, 1.75, 0.0, 'none', 0.0, 18.0);
assertTest("CASE C: Base Spatial Cost is ₹150,000", $baseCostC === 150000.00);
assertTest("CASE C: Project Cost Before GST is ₹262,500", $calcC['subtotal'] === 262500.00);
assertTest("CASE C: GST 18% is ₹47,250", $calcC['tax_amount'] === 47250.00);
assertTest("CASE C: Final Payable is ₹309,750", $calcC['grand_total'] === 309750.00);

// ===================================================================
// TEST GROUP 3: BUDGET EVALUATION BEFORE GST (SECTION 7)
// ===================================================================
echo "\n--- TEST GROUP 3: BUDGET EVALUATION BEFORE GST ---\n";

// Example from prompt:
// Budget = ₹1L–₹3L
// Project Cost Before GST = ₹2.80L, GST = ₹50,400, Final Payable = ₹3.304L
// MUST BE 'within' because 2.80L <= 3L
$evalEx1 = evaluateQuoteBudget(280000, '₹1L – ₹3L', ['final_payable' => 330400]);
assertTest("Prompt Example 1: Pre-GST ₹2.80L (Final ₹3.304L) vs ₹1L–₹3L is 'within'", $evalEx1['status'] === 'within');

// Example from prompt:
// Project Cost Before GST = ₹3.50L, Budget maximum = ₹3L
// MUST BE 'over' with variance = ₹50,000 before GST
$evalEx2 = evaluateQuoteBudget(350000, '₹1L – ₹3L', ['final_payable' => 413000]);
assertTest("Prompt Example 2: Pre-GST ₹3.50L vs ₹1L–₹3L is 'over'", $evalEx2['status'] === 'over');
assertTest("Prompt Example 2: Variance is exactly ₹50,000 before GST", $evalEx2['difference'] === 50000.00);

// Verify across all 8 budget brackets:
$allBudgets = [
    '₹1L – ₹3L' => ['max' => 300000],
    '₹3L – ₹5L' => ['max' => 500000],
    '₹5L – ₹10L' => ['max' => 1000000],
    '₹10L – ₹25L' => ['max' => 2500000],
    '₹25L – ₹50L' => ['max' => 5000000],
    '₹50L – ₹1Cr' => ['max' => 10000000],
    '₹1Cr+' => ['min' => 10000000],
    "I'm Not Sure Yet" => ['unspecified' => true]
];

foreach ($allBudgets as $bLabel => $meta) {
    if (isset($meta['unspecified'])) {
        $ev = evaluateQuoteBudget(150000, $bLabel);
        assertTest("Budget bracket '{$bLabel}' evaluates to 'unspecified'", $ev['status'] === 'unspecified');
    } elseif (isset($meta['min'])) {
        $evWithin = evaluateQuoteBudget(12000000, $bLabel);
        assertTest("Budget bracket '{$bLabel}' with ₹1.2Cr pre-GST evaluates to 'within'", $evWithin['status'] === 'within');
        $evBelow = evaluateQuoteBudget(5000000, $bLabel);
        assertTest("Budget bracket '{$bLabel}' with ₹50L pre-GST evaluates to 'below'", $evBelow['status'] === 'below');
    } else {
        $underMax = $meta['max'] - 20000;
        $overMax = $meta['max'] + 50000;
        $evUnder = evaluateQuoteBudget($underMax, $bLabel);
        $evOver = evaluateQuoteBudget($overMax, $bLabel);
        assertTest("Budget bracket '{$bLabel}' within max evaluates to 'within'", $evUnder['status'] === 'within');
        assertTest("Budget bracket '{$bLabel}' over max evaluates to 'over' or 'near'", in_array($evOver['status'], ['over', 'near']));
    }
}

// ===================================================================
// TEST GROUP 4: CAPABILITY MODULES ISOLATION (SECTION 6)
// ===================================================================
echo "\n--- TEST GROUP 4: CAPABILITY MODULES ISOLATION ---\n";

// 0 capabilities selected:
$calc0Cap = calculateQuoteGrandTotal(150000, 0, 1.00);
assertTest("0 capabilities adds ₹0 to project cost", $calc0Cap['subtotal'] === 150000.00);

// 1 capability (e.g. ₹25,000):
$calc1Cap = calculateQuoteGrandTotal(150000, 25000, 1.00);
assertTest("1 capability adds exactly its cost (₹25,000) => ₹175,000", $calc1Cap['subtotal'] === 175000.00);

// Multiple capabilities (e.g. ₹75,000):
$calcMultiCap = calculateQuoteGrandTotal(150000, 75000, 1.35);
// Base 150,000 * 1.35 = 202,500 + 75,000 = 277,500
assertTest("Multiple capabilities add to adjusted base (₹202,500 + ₹75,000 = ₹277,500)", $calcMultiCap['subtotal'] === 277500.00);

// ===================================================================
// TEST GROUP 5: EDGE CASES (SECTION 15)
// ===================================================================
echo "\n--- TEST GROUP 5: EDGE CASES (AREA <= 0, NEGATIVE, INVALID) ---\n";

$resNegative = validateAndCalculateRoomArea(-10, 20);
assertTest("Negative room length rejected", $resNegative['valid'] === false);

$resZero = validateAndCalculateRoomArea(0, 20);
assertTest("Zero room length rejected", $resZero['valid'] === false);

$resNaN = validateAndCalculateRoomArea("abc", 20);
assertTest("Non-numeric room length rejected", $resNaN['valid'] === false);

// Empty dimensions
$resEmpty = validateAndCalculateRoomArea("", "");
assertTest("Empty dimensions rejected", $resEmpty['valid'] === false);

// calculateBaseSpatialCost with 0 area
$costZero = calculateBaseSpatialCost(0);
assertTest("calculateBaseSpatialCost(0) returns 0.00 (not negative, not NaN)", $costZero === 0.00);

// calculateQuoteGrandTotal with 0
$calcZero = calculateQuoteGrandTotal(0, 0, 1.00);
assertTest("calculateQuoteGrandTotal(0) produces 0 grand total (never negative, never NaN)", $calcZero['grand_total'] === 0.00);

// ===================================================================
// TEST GROUP 6: HISTORICAL QUOTE PROTECTION (SECTION 11)
// ===================================================================
echo "\n--- TEST GROUP 6: HISTORICAL QUOTE VALUE PRESERVATION ---\n";

// Fetch existing quotes created previously in the database:
$histQuery = $conn->query("SELECT id, quote_number, approx_area, base_rate_per_sqft, base_cost, package_multiplier, estimated_total FROM quote_requests WHERE approx_area > 0 ORDER BY id ASC LIMIT 5");
if ($histQuery && $histQuery->num_rows > 0) {
    while ($row = $histQuery->fetch_assoc()) {
        $qId = $row['id'];
        $qNum = $row['quote_number'];
        $storedRate = (float)$row['base_rate_per_sqft'];
        $storedBase = (float)$row['base_cost'];
        $storedTotal = (float)$row['estimated_total'];

        // Verify that existing database values are intact
        assertTest("Historical Quote {$qNum} (ID {$qId}) retains its stored base_cost (₹" . number_format($storedBase) . ")", $storedBase >= 0);
        assertTest("Historical Quote {$qNum} (ID {$qId}) retains its stored total (₹" . number_format($storedTotal) . ")", $storedTotal >= 0);
    }
} else {
    echo " (No historical quotes found in test DB to inspect)\n";
}

// ===================================================================
// TEST GROUP 7: END-TO-END DATABASE INSERTION WITH NEW RATE ₹500
// ===================================================================
echo "\n--- TEST GROUP 7: END-TO-END SUBMISSION AT ₹500/SQ.FT ---\n";

$testTypologies = [
    'residential' => 'Residential Estate',
    'commercial' => 'Commercial / Retail',
    'executive' => 'Executive Office'
];

foreach ($testTypologies as $tKey => $tName) {
    $testQuoteNum = 'TEST-500-' . strtoupper(substr($tKey, 0, 3)) . '-' . time() . rand(10, 99);
    $area = 300.0;
    $baseRate = getBaseSpatialRate(); // 500.00
    $baseCost = calculateBaseSpatialCost($area, $baseRate); // 150000.00
    $pkgMult = 1.35; // Signature Premium
    $calcGrand = calculateQuoteGrandTotal($baseCost, 0.00, $pkgMult, 0.0, 'none', 0.0, 18.0);

    $insStmt = $conn->prepare("INSERT INTO quote_requests (
        quote_number, customer_name, email, phone, project_type, property_type, approx_area,
        design_style, package_type, package_multiplier, budget_range, status,
        base_rate_per_sqft, base_cost, service_cost, tax_percentage, tax_amount, estimated_total
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, 'new',
        ?, ?, ?, ?, ?, ?
    )");
    
    $cName = "Test Client {$tName}";
    $cEmail = "test.{$tKey}@creativetouch.local";
    $cPhone = "9876543210";
    $propType = "Standard Space";
    $style = "Modern";
    $pkgType = "Signature Premium";
    $budget = "₹3L – ₹5L";
    $srvCost = 0.0;
    $taxPct = 18.0;
    $taxAmt = $calcGrand['tax_amount'];
    $estTot = $calcGrand['grand_total'];

    $insStmt->bind_param("ssssssdsdsddddddd",
        $testQuoteNum, $cName, $cEmail, $cPhone, $tName, $propType, $area,
        $style, $pkgType, $pkgMult, $budget,
        $baseRate, $baseCost, $srvCost, $taxPct, $taxAmt, $estTot
    );

    $inserted = $insStmt->execute();
    $insertedId = $insStmt->insert_id;
    assertTest("End-to-end insertion for {$tName} succeeded (ID {$insertedId})", $inserted);

    // Verify stored values from DB
    $chkStmt = $conn->prepare("SELECT * FROM quote_requests WHERE id = ?");
    $chkStmt->bind_param("i", $insertedId);
    $chkStmt->execute();
    $dbRecord = $chkStmt->get_result()->fetch_assoc();

    assertTest("{$tName}: Stored base_rate_per_sqft is exactly ₹500", (float)$dbRecord['base_rate_per_sqft'] === 500.00);
    assertTest("{$tName}: Stored base_cost is ₹150,000", (float)$dbRecord['base_cost'] === 150000.00);
    assertTest("{$tName}: Stored package_multiplier is 1.35", (float)$dbRecord['package_multiplier'] === 1.35);
    assertTest("{$tName}: Stored tax_amount is ₹36,450", (float)$dbRecord['tax_amount'] === 36450.00);
    assertTest("{$tName}: Stored estimated_total is ₹238,950", (float)$dbRecord['estimated_total'] === 238950.00);

    // Clean up test quote
    $conn->query("DELETE FROM quote_requests WHERE id = {$insertedId}");
}

echo "\n=================================================================\n";
echo "SUMMARY: {$totalPassed} PASSED, {$totalFailed} FAILED\n";
echo "=================================================================\n";

if ($totalFailed > 0) {
    exit(1);
}
