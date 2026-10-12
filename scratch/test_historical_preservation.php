<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/quote_engine.php';

// Insert a simulated historical quote with rate 1800
$histQuoteNum = 'HIST-1800-' . time();
$histArea = 300.0;
$histRate = 1800.0;
$histBase = 540000.0; // 300 * 1800
$histMult = 1.35;
$histSubtotal = 729000.0; // 540000 * 1.35
$histTax = 131220.0; // 18%
$histTotal = 860220.0;

$stmt = $conn->prepare("INSERT INTO quote_requests (
    quote_number, customer_name, email, phone, project_type, property_type, approx_area,
    design_style, package_type, package_multiplier, budget_range, status,
    base_rate_per_sqft, base_cost, service_cost, tax_percentage, tax_amount, estimated_total
) VALUES (
    ?, 'Historical Client', 'hist@example.com', '9876543210', 'Residential Estate', 'Villa', ?,
    'Modern Luxury', 'Signature Premium', ?, '₹5L – ₹10L', 'approved',
    ?, ?, 0, 18.0, ?, ?
)");
$stmt->bind_param("sdddddd", $histQuoteNum, $histArea, $histMult, $histRate, $histBase, $histTax, $histTotal);
$stmt->execute();
$histId = $stmt->insert_id;

// Now fetch it as quote_details.php would
$chk = $conn->query("SELECT * FROM quote_requests WHERE id = $histId")->fetch_assoc();

$approx_area = (float)($chk['approx_area'] ?? 0);
$base_cost = (float)($chk['base_cost'] ?? 0);
$base_rate = (float)($chk['base_rate_per_sqft'] ?? 0);
if ($base_rate <= 0) {
    $base_rate = ($approx_area > 0 && $base_cost > 0) ? round($base_cost / $approx_area, 2) : getBaseSpatialRate();
}
if ($base_cost <= 0 && $approx_area > 0) {
    $base_cost = calculateBaseSpatialCost($approx_area, $base_rate);
}

echo "HISTORICAL QUOTE VERIFICATION:\n";
echo "Stored Base Rate: " . $base_rate . " (Expected: 1800)\n";
echo "Stored Base Cost: " . $base_cost . " (Expected: 540000)\n";
echo "Stored Total: " . $chk['estimated_total'] . " (Expected: 860220)\n";

$pass = ($base_rate === 1800.0 && $base_cost === 540000.0 && (float)$chk['estimated_total'] === 860220.0);
echo "Result: " . ($pass ? "PASS: Historical Quote Values Perfectly Preserved!" : "FAIL") . "\n";

// Clean up
$conn->query("DELETE FROM quote_requests WHERE id = $histId");
