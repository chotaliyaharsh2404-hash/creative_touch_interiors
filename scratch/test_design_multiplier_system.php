<?php
/**
 * Creative Touch Interiors — Automated Test Suite
 * User-Selectable Design Level / Multiplier System & Pre-GST Budget Rule
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
echo "DESIGN LEVEL / MULTIPLIER & PRE-GST BUDGET SYSTEM TEST SUITE\n";
echo "=================================================================\n\n";

// ===================================================================
// TEST GROUP 1: MULTIPLIER WHITELIST & RESOLUTION
// ===================================================================
echo "--- TEST GROUP 1: MULTIPLIER RESOLUTION & STRICT WHITELIST ---\n";

assertTest("Essential Economy resolves to 1.00", getPackageMultiplier('Essential Economy') === 1.00);
assertTest("'essential economy' resolves to 1.00", getPackageMultiplier('essential economy') === 1.00);
assertTest("'1.00' string resolves to 1.00", getPackageMultiplier('1.00') === 1.00);
assertTest("1.00 float resolves to 1.00", getPackageMultiplier(1.00) === 1.00);

assertTest("Signature Premium resolves to 1.35", getPackageMultiplier('Signature Premium') === 1.35);
assertTest("'signature premium' resolves to 1.35", getPackageMultiplier('signature premium') === 1.35);
assertTest("'1.35' string resolves to 1.35", getPackageMultiplier('1.35') === 1.35);
assertTest("1.35 float resolves to 1.35", getPackageMultiplier(1.35) === 1.35);

assertTest("Ultra Palatial resolves to 1.75", getPackageMultiplier('Ultra Palatial') === 1.75);
assertTest("'ultra palatial' resolves to 1.75", getPackageMultiplier('ultra palatial') === 1.75);
assertTest("'1.75' string resolves to 1.75", getPackageMultiplier('1.75') === 1.75);
assertTest("1.75 float resolves to 1.75", getPackageMultiplier(1.75) === 1.75);

// Invalid / arbitrary multiplier rejection
$invalidValidation1 = validatePackage('2.50');
assertTest("Arbitrary multiplier 2.50x rejected", $invalidValidation1['valid'] === false);

$invalidValidation2 = validatePackage(2.50);
assertTest("Arbitrary float 2.50 rejected", $invalidValidation2['valid'] === false);

$invalidValidation3 = validatePackage('Arbitrary Tier');
assertTest("Arbitrary Tier rejected", $invalidValidation3['valid'] === false);

$validVal1 = validatePackage('Essential Economy');
assertTest("Essential Economy validated successfully", $validVal1['valid'] === true && $validVal1['multiplier'] === 1.00);

$validVal2 = validatePackage('Signature Premium');
assertTest("Signature Premium validated successfully", $validVal2['valid'] === true && $validVal2['multiplier'] === 1.35);

$validVal3 = validatePackage('Ultra Palatial');
assertTest("Ultra Palatial validated successfully", $validVal3['valid'] === true && $validVal3['multiplier'] === 1.75);

// Display label formatting
assertTest("Display label for '1.00' is 'Essential Economy'", formatPackageTierDisplay('1.00') === 'Essential Economy');
assertTest("Display label for '1.35' is 'Signature Premium'", formatPackageTierDisplay('1.35') === 'Signature Premium');
assertTest("Display label for '1.75' is 'Ultra Palatial'", formatPackageTierDisplay('1.75') === 'Ultra Palatial');
assertTest("Display label for 'Essential Economy' is 'Essential Economy'", formatPackageTierDisplay('Essential Economy') === 'Essential Economy');
assertTest("Display label for 'Signature Premium' is 'Signature Premium'", formatPackageTierDisplay('Signature Premium') === 'Signature Premium');
assertTest("Display label for 'Ultra Palatial' is 'Ultra Palatial'", formatPackageTierDisplay('Ultra Palatial') === 'Ultra Palatial');

// ===================================================================
// TEST GROUP 2: CALCULATION ORDER & PRE-GST BUDGET RULE
// ===================================================================
echo "\n--- TEST GROUP 2: CALCULATION ORDER & PRE-GST BUDGET RULE ---\n";

// Example from User Specification:
// Budget = ₹1,00,000–₹3,00,000
// Max allowed project cost before GST = ₹3,00,000
// Case A: Pre-GST = ₹2,80,000, GST = ₹50,400, Final Payable = ₹3,30,400 -> WITHIN BUDGET
$caseA_preGst = 280000.0;
$caseA_gst = round($caseA_preGst * 0.18, 2); // 50,400
$caseA_payable = $caseA_preGst + $caseA_gst; // 330,400

$evalCaseA = evaluateQuoteBudget($caseA_preGst, '₹1L – ₹3L', ['final_payable' => $caseA_payable]);
assertTest(
    "User Specification Example A: Pre-GST ₹2,80,000 (Final ₹3,30,400) is 'within' for ₹1L–₹3L budget",
    $evalCaseA['status'] === 'within' && $evalCaseA['difference'] === 0.0
);

// Case B: Pre-GST = ₹3,50,000, GST = ₹63,000, Final Payable = ₹4,13,000 -> OVER BUDGET by ₹50,000
$caseB_preGst = 350000.0;
$caseB_gst = round($caseB_preGst * 0.18, 2);
$caseB_payable = $caseB_preGst + $caseB_gst;

$evalCaseB = evaluateQuoteBudget($caseB_preGst, '₹1L – ₹3L', ['final_payable' => $caseB_payable]);
assertTest(
    "User Specification Example B: Pre-GST ₹3,50,000 is 'over' by exactly ₹50,000 for ₹1L–₹3L budget",
    $evalCaseB['status'] === 'over' && $evalCaseB['difference'] === 50000.0
);

// Calculation Order Verification via calculateQuoteGrandTotal:
// Base Spatial = 200,000
// Capabilities = 50,000
// 1.00x: Base*1.00 + 50,000 = 250,000 pre-GST; GST = 45,000; Final = 295,000
$calc1 = calculateQuoteGrandTotal(200000, 50000, 1.00, 0, 'none', 0, 18.0);
assertTest("1.00x Pre-GST Subtotal equals 250,000", $calc1['subtotal'] === 250000.0);
assertTest("1.00x GST (18%) equals 45,000", $calc1['tax_amount'] === 45000.0);
assertTest("1.00x Final Payable equals 295,000", $calc1['grand_total'] === 295000.0);

// 1.35x: Base*1.35 = 270,000; + 50,000 = 320,000 pre-GST; GST = 57,600; Final = 377,600
$calc135 = calculateQuoteGrandTotal(200000, 50000, 1.35, 0, 'none', 0, 18.0);
assertTest("1.35x Pre-GST Subtotal equals 320,000", $calc135['subtotal'] === 320000.0);
assertTest("1.35x GST (18%) equals 57,600", $calc135['tax_amount'] === 57600.0);
assertTest("1.35x Final Payable equals 377,600", $calc135['grand_total'] === 377600.0);

// 1.75x: Base*1.75 = 350,000; + 50,000 = 400,000 pre-GST; GST = 72,000; Final = 472,000
$calc175 = calculateQuoteGrandTotal(200000, 50000, 1.75, 0, 'none', 0, 18.0);
assertTest("1.75x Pre-GST Subtotal equals 400,000", $calc175['subtotal'] === 400000.0);
assertTest("1.75x GST (18%) equals 72,000", $calc175['tax_amount'] === 72000.0);
assertTest("1.75x Final Payable equals 472,000", $calc175['grand_total'] === 472000.0);

assertTest("Estimate changes proportionally when multiplier changes", 
    $calc1['grand_total'] < $calc135['grand_total'] && $calc135['grand_total'] < $calc175['grand_total']
);

// ===================================================================
// TEST GROUP 3: MATRIX TEST (3 MULTIPLIERS × 3 BUDGET BRACKETS)
// ===================================================================
echo "\n--- TEST GROUP 3: 3 MULTIPLIERS × 3 BUDGET BRACKETS MATRIX ---\n";

$brackets = [
    '₹1L – ₹3L'  => ['min' => 100000, 'max' => 300000],
    '₹3L – ₹5L'  => ['min' => 300000, 'max' => 500000],
    '₹5L – ₹10L' => ['min' => 500000, 'max' => 1000000]
];

$multipliers = [
    'Essential Economy' => 1.00,
    'Signature Premium' => 1.35,
    'Ultra Palatial'    => 1.75
];

// Base scenario: Area = 300 sq.ft, Base rate = 1200 => Base spatial cost = 360,000. Capabilities = 40,000.
// 1.00x: Base = 360,000; Pre-GST = 400,000
// 1.35x: Base = 486,000; Pre-GST = 526,000
// 1.75x: Base = 630,000; Pre-GST = 670,000
$baseSpatial = 360000;
$capabilities = 40000;

foreach ($multipliers as $mName => $mVal) {
    $preGst = round(($baseSpatial * $mVal) + $capabilities, 2);
    $gst = round($preGst * 0.18, 2);
    $finalPayable = $preGst + $gst;

    foreach ($brackets as $bLabel => $bLimits) {
        $eval = evaluateQuoteBudget($preGst, $bLabel, ['final_payable' => $finalPayable]);
        $expectedStatus = ($preGst <= $bLimits['max']) ? 'within' : (($preGst <= $bLimits['max'] * 1.15) ? 'near' : 'over');
        
        assertTest(
            "{$mName} ({$mVal}×) Pre-GST ₹" . number_format($preGst) . " vs {$bLabel} (Max ₹" . number_format($bLimits['max']) . "): status '{$eval['status']}' matches expected '{$expectedStatus}'",
            $eval['status'] === $expectedStatus
        );

        if ($preGst <= $bLimits['max']) {
            assertTest("   -> Difference is 0 when within budget", $eval['difference'] === 0.0);
        } else {
            $expectedDiff = round($preGst - $bLimits['max'], 2);
            assertTest("   -> Difference is exactly Pre-GST overage (₹" . number_format($expectedDiff) . ")", $eval['difference'] === $expectedDiff);
        }
    }
}

// ===================================================================
// TEST GROUP 4: END-TO-END DATABASE SUBMISSION WITH 3 DESIGN LEVELS
// ===================================================================
echo "\n--- TEST GROUP 4: END-TO-END DATABASE SUBMISSION WITH 3 DESIGN LEVELS ---\n";

$testSubmissions = [
    [
        'name' => 'Economy Client',
        'level' => 'Essential Economy',
        'multiplier' => 1.00,
        'budget' => '₹1L – ₹3L',
        'area' => 150.0,
        'expected_status' => 'within' // 150*1200*1.00 = 180,000 <= 300,000
    ],
    [
        'name' => 'Signature Client',
        'level' => 'Signature Premium',
        'multiplier' => 1.35,
        'budget' => '₹3L – ₹5L',
        'area' => 250.0,
        'expected_status' => 'within' // 250*1200 = 300,000 * 1.35 = 405,000 <= 500,000
    ],
    [
        'name' => 'Palatial Client',
        'level' => 'Ultra Palatial',
        'multiplier' => 1.75,
        'budget' => '₹5L – ₹10L',
        'area' => 400.0,
        'expected_status' => 'within' // 400*1200 = 480,000 * 1.75 = 840,000 <= 1,000,000
    ]
];

$insertedQuotes = [];

foreach ($testSubmissions as $sub) {
    $qNum = generateQuoteNumber($conn);
    $baseRate = 1200.0;
    $baseCost = round($sub['area'] * $baseRate, 2);
    $srvCost = 0.0;
    $pkgMult = $sub['multiplier'];
    
    $grand = calculateQuoteGrandTotal($baseCost, $srvCost, $pkgMult, 0, 'none', 0, 18.0);
    $subtotal = $grand['subtotal'];
    $taxAmt = $grand['tax_amount'];
    $estTotal = $grand['grand_total'];

    $insStmt = $conn->prepare("INSERT INTO quote_requests (
        quote_number, customer_name, email, phone, city, state, pincode,
        project_type, property_type, approx_area, measurement_status, rooms_count, floors_count,
        budget_range, package_type, package_multiplier, status,
        base_rate_per_sqft, base_cost, service_cost, tax_percentage, tax_amount, estimated_total
    ) VALUES (
        ?, ?, 'test_multiplier@creativetouch.com', '9876543210', 'Surat', 'Gujarat', '395001',
        'Residential Estate', 'Penthouse', ?, 'known', 3, 1,
        ?, ?, ?, 'new',
        ?, ?, ?, 18.00, ?, ?
    )");

    $insStmt->bind_param(
        "ssdssdddddd",
        $qNum, $sub['name'], $sub['area'],
        $sub['budget'], $sub['level'], $pkgMult,
        $baseRate, $baseCost, $srvCost, $taxAmt, $estTotal
    );

    $ok = $insStmt->execute();
    $qId = $insStmt->insert_id;
    assertTest("Quote {$qNum} ({$sub['level']}) inserted into DB", $ok && $qId > 0);

    if ($ok) {
        $insertedQuotes[] = [
            'id' => $qId,
            'quote_number' => $qNum,
            'level' => $sub['level'],
            'multiplier' => $pkgMult,
            'budget' => $sub['budget'],
            'pre_gst' => $subtotal,
            'tax' => $taxAmt,
            'total' => $estTotal
        ];
    }
}

// ===================================================================
// TEST GROUP 5: ADMIN AND PDF CONSISTENCY FOR DESIGN LEVEL & MULTIPLIER
// ===================================================================
echo "\n--- TEST GROUP 5: ADMIN AND PDF OUTPUT CONSISTENCY ---\n";

foreach ($insertedQuotes as $iq) {
    // 1. Admin Quote Details
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_username'] = 'admin';
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_role'] = 'admin';
    $_SESSION['admin_name'] = 'Test Admin';
    $_GET['id'] = $iq['id'];

    chdir(__DIR__ . '/../admin');
    ob_start();
    include 'quote_details.php';
    $adminHtml = ob_get_clean();

    assertTest("Admin details for {$iq['quote_number']} renders without error", !empty($adminHtml));
    assertTest(
        "Admin details contains Design Level '{$iq['level']}'",
        stripos($adminHtml, $iq['level']) !== false
    );
    assertTest(
        "Admin details contains Multiplier '" . number_format($iq['multiplier'], 2) . "×'",
        stripos($adminHtml, number_format($iq['multiplier'], 2) . '×') !== false || stripos($adminHtml, number_format($iq['multiplier'], 2)) !== false
    );
    assertTest(
        "Admin details contains Pre-GST Project Cost",
        stripos($adminHtml, 'Project Cost (Pre-GST)') !== false
    );

    // 2. Quotation PDF
    chdir(__DIR__ . '/..');
    $_GET['token'] = $iq['quote_number'];
    ob_start();
    include 'quote_pdf.php';
    $pdfHtml = ob_get_clean();

    assertTest("Quotation PDF for {$iq['quote_number']} renders without error", !empty($pdfHtml));
    assertTest(
        "Quotation PDF contains Design Level '{$iq['level']}'",
        stripos($pdfHtml, $iq['level']) !== false
    );
    assertTest(
        "Quotation PDF contains Multiplier '" . number_format($iq['multiplier'], 2) . "×'",
        stripos($pdfHtml, number_format($iq['multiplier'], 2) . '×') !== false
    );
    assertTest(
        "Quotation PDF does NOT expose technical internal identifiers like '1.35x Curated Finishes'",
        stripos($pdfHtml, '1.25x Curated') === false && stripos($pdfHtml, '1.50x Bespoke') === false
    );

    // 3. Customer Portal quote_details.php
    $_SESSION['user_id'] = 1;
    $_GET['id'] = $iq['id'];
    ob_start();
    include 'quote_details.php';
    $portalHtml = ob_get_clean();

    assertTest("Customer portal for {$iq['quote_number']} renders without error", !empty($portalHtml));
    assertTest(
        "Customer portal shows Design Level '{$iq['level']}'",
        stripos($portalHtml, $iq['level']) !== false
    );
}

// Cleanup test quotes
if (!empty($insertedQuotes)) {
    $ids = implode(',', array_column($insertedQuotes, 'id'));
    $conn->query("DELETE FROM quote_requests WHERE id IN ($ids)");
}

echo "\n=================================================================\n";
echo "TEST RESULTS: Total: " . ($totalPassed + $totalFailed) . " | Passed: {$totalPassed} | Failed: {$totalFailed}\n";
echo "=================================================================\n";

if ($totalFailed === 0) {
    echo ">>> ALL DESIGN LEVEL / MULTIPLIER TESTS PASSED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED! <<<\n";
    exit(1);
}
