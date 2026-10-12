<?php
/**
 * Automated Test Suite: Budget-Aware Quotation System (v2.5.0)
 * Tests all 8 budget ranges, boundary conditions, recommendations,
 * quote calculations, regression against existing quotes, admin details, and PDF.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/quote_engine.php';

echo "=================================================================\n";
echo "CREATIVE TOUCH INTERIORS — BUDGET-AWARE QUOTATION SYSTEM TEST SUITE\n";
echo "=================================================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertCondition($name, $condition, $details = '') {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo " [PASS] $name\n";
        $testsPassed++;
    } else {
        echo "![FAIL] $name: $details\n";
        $testsFailed++;
    }
}

// ===================================================================
// TEST GROUP 1: ALL 8 BUDGET RANGES LIMIT PARSING
// ===================================================================
echo "--- TEST GROUP 1: BUDGET RANGE LIMIT PARSING ---\n";

$rangesToTest = [
    '₹1L – ₹3L' => ['min' => 100000.0, 'max' => 300000.0, 'unbounded' => false, 'unspecified' => false],
    '₹1 Lakh – ₹3 Lakhs' => ['min' => 100000.0, 'max' => 300000.0, 'unbounded' => false, 'unspecified' => false],
    '₹3L – ₹5L' => ['min' => 300000.0, 'max' => 500000.0, 'unbounded' => false, 'unspecified' => false],
    '₹3 Lakhs – ₹5 Lakhs' => ['min' => 300000.0, 'max' => 500000.0, 'unbounded' => false, 'unspecified' => false],
    '₹5L – ₹10L' => ['min' => 500000.0, 'max' => 1000000.0, 'unbounded' => false, 'unspecified' => false],
    '₹5 Lakhs – ₹10 Lakhs' => ['min' => 500000.0, 'max' => 1000000.0, 'unbounded' => false, 'unspecified' => false],
    '₹10L – ₹25L' => ['min' => 1000000.0, 'max' => 2500000.0, 'unbounded' => false, 'unspecified' => false],
    '₹10 Lakhs – ₹25 Lakhs' => ['min' => 1000000.0, 'max' => 2500000.0, 'unbounded' => false, 'unspecified' => false],
    '₹25L – ₹50L' => ['min' => 2500000.0, 'max' => 5000000.0, 'unbounded' => false, 'unspecified' => false],
    '₹25 Lakhs – ₹50 Lakhs' => ['min' => 2500000.0, 'max' => 5000000.0, 'unbounded' => false, 'unspecified' => false],
    '₹50L – ₹1Cr' => ['min' => 5000000.0, 'max' => 10000000.0, 'unbounded' => false, 'unspecified' => false],
    '₹50 Lakhs – ₹1 Crore' => ['min' => 5000000.0, 'max' => 10000000.0, 'unbounded' => false, 'unspecified' => false],
    '₹1Cr+' => ['min' => 10000000.0, 'max' => null, 'unbounded' => true, 'unspecified' => false],
    '₹1 Crore+' => ['min' => 10000000.0, 'max' => null, 'unbounded' => true, 'unspecified' => false],
    "I'm Not Sure Yet" => ['min' => null, 'max' => null, 'unbounded' => false, 'unspecified' => true],
    "Not decided yet" => ['min' => null, 'max' => null, 'unbounded' => false, 'unspecified' => true],
];

foreach ($rangesToTest as $raw => $expected) {
    $parsed = parseBudgetRangeLimits($raw);
    assertCondition(
        "parseBudgetRangeLimits('$raw')",
        $parsed['min'] === $expected['min'] &&
        $parsed['max'] === $expected['max'] &&
        $parsed['unbounded'] === $expected['unbounded'] &&
        $parsed['unspecified'] === $expected['unspecified'],
        "Got " . json_encode($parsed) . " expected " . json_encode($expected)
    );
}

// ===================================================================
// TEST GROUP 2: STATUS EVALUATION & THRESHOLDS
// ===================================================================
echo "\n--- TEST GROUP 2: STATUS EVALUATION & THRESHOLDS ---\n";

// Bounded range: ₹1L – ₹3L (max = 300,000, 15% threshold = 345,000)
// Case 1: Within budget (e.g. 250,000)
$evalWithin = evaluateQuoteBudget(250000, '₹1L – ₹3L');
assertCondition("₹1L–₹3L: 2.5L is 'within'", $evalWithin['status'] === 'within' && $evalWithin['difference'] === 0.0);

// Case 2: Exactly at upper boundary (300,000)
$evalBoundary = evaluateQuoteBudget(300000, '₹1L – ₹3L');
assertCondition("₹1L–₹3L: exactly 3.0L is 'within'", $evalBoundary['status'] === 'within' && $evalBoundary['difference'] === 0.0);

// Case 3: Slightly above budget (e.g. 320,000, which is <= 345,000)
$evalNear = evaluateQuoteBudget(320000, '₹1L – ₹3L');
assertCondition("₹1L–₹3L: 3.2L is 'near'", $evalNear['status'] === 'near' && $evalNear['difference'] === 20000.0);
assertCondition("₹1L–₹3L: 3.2L status_label is 'Slightly Above Budget'", $evalNear['status_label'] === 'Slightly Above Budget');

// Case 4: Significantly above budget (e.g. 660,000, which is > 345,000)
$evalOver = evaluateQuoteBudget(660000, '₹1L – ₹3L');
assertCondition("₹1L–₹3L: 6.6L is 'over'", $evalOver['status'] === 'over' && $evalOver['difference'] === 360000.0);
assertCondition("₹1L–₹3L: 6.6L difference text contains 3.60 Lakhs", strpos($evalOver['difference_text'], '3.60 Lakhs') !== false);

// Case 5: Unbounded luxury range ₹1Cr+ (min = 10,000,000)
$evalLuxury1 = evaluateQuoteBudget(12000000, '₹1Cr+');
assertCondition("₹1Cr+: 1.2 Cr is 'within'", $evalLuxury1['status'] === 'within');

$evalLuxury2 = evaluateQuoteBudget(8500000, '₹1Cr+');
assertCondition("₹1Cr+: 85 Lakhs is 'below'", $evalLuxury2['status'] === 'below');

// Case 6: I'm Not Sure Yet
$evalNotSure = evaluateQuoteBudget(4500000, "I'm Not Sure Yet");
assertCondition("I'm Not Sure Yet is 'unspecified'", $evalNotSure['status'] === 'unspecified' && $evalNotSure['difference'] === 0.0);
assertCondition("I'm Not Sure Yet has empty recommendations", empty($evalNotSure['recommendations']));

// ===================================================================
// TEST GROUP 3: CONTEXTUAL SCOPE GUIDANCE RECOMMENDATIONS
// ===================================================================
echo "\n--- TEST GROUP 3: CONTEXTUAL RECOMMENDATIONS ---\n";

$context = [
    'approx_area' => 1850,
    'package_type' => 'Luxury',
    'base_rate_sqft' => 1200.0,
    'base_cost' => 1850 * 1200.0,
    'package_multiplier' => 1.50,
    'material_multiplier' => 1.0,
    'services_count' => 4,
    'services_total' => 350000.0
];

$evalGuidance = evaluateQuoteBudget(4500000, '₹10L – ₹25L', $context);
assertCondition("Over-budget evaluation generates recommendations", count($evalGuidance['recommendations']) > 0);

$hasPackageRec = false;
$hasCapabilityRec = false;
foreach ($evalGuidance['recommendations'] as $rec) {
    if (stripos($rec, 'finishes') !== false || stripos($rec, 'package') !== false || stripos($rec, 'palatial') !== false || stripos($rec, 'premium') !== false) $hasPackageRec = true;
    if (stripos($rec, 'capability') !== false || stripos($rec, 'module') !== false) $hasCapabilityRec = true;
}
assertCondition("Recommendations include package tier savings", $hasPackageRec);
assertCondition("Recommendations include capability phasing", $hasCapabilityRec);

// ===================================================================
// TEST GROUP 4: FULL AUTHORITATIVE CALCULATION PIPELINE
// ===================================================================
echo "\n--- TEST GROUP 4: CALCULATION INTEGRITY ---\n";

// Area = 1850 sq.ft, Style = Modern Luxury (1800), Package = Standard (1.0x)
// Base cost = 1850 * 1800 = 3,330,000
// Package multiplier = 1.0x -> adjusted = 3,330,000
// Services: 1 service, unit rate 150, area based, 10% wastage = 150 * 1850 * 1.10 = 305,250
// Subtotal = 3,330,000 + 305,250 = 3,635,250
// GST 18% = 654,345
// Grand total = 4,289,595
$srvItem = calculateServiceItem('area_based', 150.0, 1.0, 1850.0, 1.0, 10.0, 0.0);
$grandCalc = calculateQuoteGrandTotal(3330000.0, $srvItem['final_amount'], 1.0, 0.0, 'none', 0.0, 18.0);

assertCondition("calculateServiceItem produces 305,250", $srvItem['final_amount'] === 305250.0);
assertCondition("calculateQuoteGrandTotal subtotal is 3,635,250", $grandCalc['subtotal'] === 3635250.0);
assertCondition("calculateQuoteGrandTotal tax_amount is 654,345", $grandCalc['tax_amount'] === 654345.0);
assertCondition("calculateQuoteGrandTotal grand_total is 4,289,595", $grandCalc['grand_total'] === 4289595.0);

// Verify that budget evaluation does NOT alter this mathematical total!
$evalAgainstBudget = evaluateQuoteBudget($grandCalc['grand_total'], '₹10L – ₹25L');
assertCondition("Grand total remains unmanipulated (4,289,595)", $grandCalc['grand_total'] === 4289595.0);
assertCondition("Evaluated status correctly flags >25L as 'over'", $evalAgainstBudget['status'] === 'over');
assertCondition("Difference equals 1,789,595", $evalAgainstBudget['difference'] === (4289595.0 - 2500000.0));

// ===================================================================
// TEST GROUP 5: DATABASE & HISTORICAL QUOTES INTEGRITY
// ===================================================================
echo "\n--- TEST GROUP 5: HISTORICAL QUOTES INTEGRITY ---\n";

$histQuery = $conn->query("SELECT id, quote_number, budget_range, estimated_total FROM quote_requests ORDER BY id DESC LIMIT 10");
assertCondition("Historical quotes query succeeded", $histQuery !== false && $histQuery->num_rows > 0);

if ($histQuery && $histQuery->num_rows > 0) {
    while ($hq = $histQuery->fetch_assoc()) {
        $hEval = evaluateQuoteBudget((float)$hq['estimated_total'], $hq['budget_range']);
        assertCondition(
            "Historical Quote #{$hq['quote_number']} (Budget: '{$hq['budget_range']}', Total: {$hq['estimated_total']}) evaluates valid status",
            in_array($hEval['status'], ['within', 'near', 'over', 'below', 'unspecified'], true)
        );
    }
}

// ===================================================================
// TEST GROUP 6: ADMIN QUOTE DETAILS & PDF RENDERING VERIFICATION
// ===================================================================
echo "\n--- TEST GROUP 6: ADMIN QUOTE DETAILS & PDF RENDERING ---\n";

// Fetch a bounded quote and an unspecified quote for comprehensive PDF & Admin testing
$boundedQ = $conn->query("SELECT * FROM quote_requests WHERE budget_range LIKE '%Lakh%' ORDER BY id DESC LIMIT 1")->fetch_assoc();
$unspecifiedQ = $conn->query("SELECT * FROM quote_requests WHERE budget_range LIKE '%sure%' OR budget_range LIKE '%decided%' ORDER BY id DESC LIMIT 1")->fetch_assoc();

$_SERVER['REQUEST_METHOD'] = 'GET';

if ($boundedQ) {
    $qId = $boundedQ['id'];

    // 1. Test Admin Quote Details page buffer with bounded quote
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'admin';
    $_SESSION['admin_role'] = 'admin';
    $_SESSION['admin_name'] = 'Test Admin';
    $_GET['id'] = $qId;

    chdir(__DIR__ . '/../admin');
    ob_start();
    include 'quote_details.php';
    $adminHtml = ob_get_clean();

    assertCondition("Admin Quote Details renders without fatal error", !empty($adminHtml));
    assertCondition("Admin Quote Details contains Estimated Budget", strpos($adminHtml, 'Estimated Budget') !== false);
    assertCondition("Admin Quote Details contains Budget Alignment status", strpos($adminHtml, 'budget-status-pill') !== false);
    assertCondition("Admin Quote Details contains correct quote number", strpos($adminHtml, $boundedQ['quote_number']) !== false);

    // 2. Test Customer Quote Details page buffer
    $_SESSION['user_id'] = $boundedQ['user_id'] ?: 1;
    $_GET['id'] = $qId;

    chdir(__DIR__ . '/..');
    ob_start();
    include 'quote_details.php';
    $custHtml = ob_get_clean();

    assertCondition("Customer Quote Details renders without fatal error", !empty($custHtml));
    assertCondition("Customer Quote Details contains Project Specifications", strpos($custHtml, 'Project Specifications') !== false);
    assertCondition("Customer Quote Details contains Budget Alignment badge", strpos($custHtml, 'budget-status-pill') !== false);

    // 3. Test Quotation PDF page buffer with bounded quote
    ob_start();
    include 'quote_pdf.php';
    $pdfHtml = ob_get_clean();

    assertCondition("Quotation PDF renders without fatal error", !empty($pdfHtml));
    assertCondition("Quotation PDF contains Project Parameters", strpos($pdfHtml, 'Project Parameters') !== false);
    assertCondition("Quotation PDF contains Estimated Budget", strpos($pdfHtml, 'Estimated Budget:') !== false);
    assertCondition("Quotation PDF contains Budget Alignment for bounded quote", strpos($pdfHtml, 'Budget Alignment:') !== false);
}

if ($unspecifiedQ) {
    $_GET['id'] = $unspecifiedQ['id'];
    chdir(__DIR__ . '/..');
    ob_start();
    include 'quote_pdf.php';
    $pdfUnspecifiedHtml = ob_get_clean();

    assertCondition("Quotation PDF renders for unspecified budget quote", !empty($pdfUnspecifiedHtml));
    assertCondition("Quotation PDF contains Estimated Budget for unspecified quote", strpos($pdfUnspecifiedHtml, 'Estimated Budget:') !== false);
    assertCondition("Quotation PDF omits Budget Alignment warning for unspecified quote", strpos($pdfUnspecifiedHtml, 'Budget Alignment:') === false);
}


// ===================================================================
// FINAL TEST SUMMARY
// ===================================================================
echo "\n=================================================================\n";
echo "TEST RESULTS: Total: " . ($testsPassed + $testsFailed) . " | Passed: $testsPassed | Failed: $testsFailed\n";
echo "=================================================================\n";

if ($testsFailed === 0) {
    echo ">>> ALL TESTS PASSED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
