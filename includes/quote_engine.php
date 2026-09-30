<?php
/**
 * Creative Touch Interiors — Authoritative Quote Engine & Calculation Library
 *
 * Core algorithmic engine implementing:
 * - Room dimension validation and area calculation (Length x Width)
 * - Multi-room aggregation
 * - Service pricing: Area-based, Quantity-based, Fixed-price
 * - Material multipliers with boundary validation
 * - Wastage percentage and rounding
 * - Minimum charge thresholds
 * - Package tier multipliers
 * - Discount validation (flat and percentage with non-negative constraints)
 * - Tax (GST) calculations
 * - Grand total synthesis
 * - Indian currency formatting
 * - Duplicate submission token validation
 */

if (!defined('QUOTE_ENGINE_LOADED')) {
    define('QUOTE_ENGINE_LOADED', true);

    /**
     * 1. ROOM CALCULATION & VALIDATION
     * Formula: Area = Length × Width
     */
    function validateAndCalculateRoomArea($length, $width) {
        // Validate blank or null
        if ($length === '' || $length === null || $width === '' || $width === null) {
            return ['valid' => false, 'error' => 'Length and width cannot be blank.', 'area' => 0.00];
        }

        // Validate numeric
        if (!is_numeric($length) || !is_numeric($width)) {
            return ['valid' => false, 'error' => 'Length and width must be numeric values.', 'area' => 0.00];
        }

        $l = (float)$length;
        $w = (float)$width;

        // Reject zero or negative
        if ($l <= 0 || $w <= 0) {
            return ['valid' => false, 'error' => 'Length and width must be strictly greater than zero.', 'area' => 0.00];
        }

        // Reject unrealistic/malicious inputs (e.g. > 500 ft for interior spaces)
        if ($l > 500 || $w > 500) {
            return ['valid' => false, 'error' => 'Room dimensions exceed the maximum allowable limit of 500 ft.', 'area' => 0.00];
        }

        $area = round($l * $w, 2);
        return [
            'valid' => true,
            'length' => $l,
            'width' => $w,
            'area' => $area,
            'error' => null
        ];
    }

    /**
     * 2. MATERIAL MULTIPLIER VALIDATION
     * Validates and normalizes material multipliers.
     * Rejects 0, negative, NULL, invalid text, or out-of-range multipliers.
     */
    function validateMaterialMultiplier($multiplier) {
        if ($multiplier === '' || $multiplier === null || !is_numeric($multiplier)) {
            return ['valid' => false, 'error' => 'Material multiplier must be a valid number.', 'multiplier' => 1.00];
        }

        $m = (float)$multiplier;
        if ($m <= 0) {
            return ['valid' => false, 'error' => 'Material multiplier must be strictly positive.', 'multiplier' => 1.00];
        }

        if ($m < 0.5 || $m > 5.0) {
            return ['valid' => false, 'error' => 'Material multiplier must be between 0.50 and 5.00.', 'multiplier' => 1.00];
        }

        return ['valid' => true, 'multiplier' => round($m, 2), 'error' => null];
    }

    /**
     * 3. SERVICE LINE ITEM CALCULATION
     * Handles:
     * - area_based (Rate × Area)
     * - quantity_based (Rate × Quantity)
     * - fixed_price (Flat Rate — quantity/area must NOT multiply)
     * - Material multiplier
     * - Wastage percentage (wastage = base × wastage% / 100)
     * - Minimum charge enforcement
     */
    function calculateServiceItem(
        $serviceType,
        $unitRate,
        $quantity = 1.0,
        $areaSqft = 0.0,
        $materialMultiplier = 1.0,
        $wastagePercent = 0.0,
        $minCharge = 0.0
    ) {
        $unitRate = max(0.0, (float)$unitRate);
        $quantity = (float)$quantity;
        $areaSqft = max(0.0, (float)$areaSqft);
        $wastagePercent = max(0.0, (float)$wastagePercent);
        $minCharge = max(0.0, (float)$minCharge);

        // Validate Material Multiplier
        $matVal = validateMaterialMultiplier($materialMultiplier);
        $matMult = $matVal['valid'] ? $matVal['multiplier'] : 1.00;

        // Base Calculation based on service type
        $baseAmount = 0.00;
        if ($serviceType === 'area_based') {
            $baseAmount = round($unitRate * $areaSqft, 2);
        } elseif ($serviceType === 'quantity_based') {
            if ($quantity < 0) {
                return [
                    'valid' => false,
                    'error' => 'Quantity cannot be negative.',
                    'service_type' => $serviceType,
                    'unit_rate' => $unitRate,
                    'quantity' => $quantity,
                    'base_amount' => 0.00,
                    'final_amount' => 0.00,
                    'amount' => 0.00
                ];
            }
            $baseAmount = round($unitRate * $quantity, 2);
        } else {
            // fixed_price: Flat price. Quantity and Area do NOT multiply fixed price.
            $baseAmount = round($unitRate, 2);
        }

        // Apply Material Multiplier to base amount
        $multipliedBase = round($baseAmount * $matMult, 2);

        // Apply Wastage
        $wastageAmount = 0.00;
        if ($wastagePercent > 0) {
            $wastageAmount = round($multipliedBase * ($wastagePercent / 100), 2);
        }

        $serviceSubtotal = round($multipliedBase + $wastageAmount, 2);

        // Apply Minimum Charge
        $finalAmount = $serviceSubtotal;
        $minChargeApplied = false;
        if ($minCharge > 0 && $serviceSubtotal < $minCharge) {
            $finalAmount = round($minCharge, 2);
            $minChargeApplied = true;
        }

        return [
            'valid' => true,
            'service_type' => $serviceType,
            'unit_rate' => $unitRate,
            'quantity' => $quantity,
            'area_sqft' => $areaSqft,
            'base_amount' => $baseAmount,
            'material_multiplier' => $matMult,
            'multiplied_base' => $multipliedBase,
            'wastage_percent' => $wastagePercent,
            'wastage_amount' => $wastageAmount,
            'service_subtotal' => $serviceSubtotal,
            'min_charge' => $minCharge,
            'min_charge_applied' => $minChargeApplied,
            'final_amount' => $finalAmount,
            'amount' => $finalAmount,
            'error' => null
        ];
    }

    /**
     * 4. PACKAGE MULTIPLIER LOOKUP & VALIDATION
     * Returns float multiplier (1.00, 1.25, 1.50)
     */
    function getPackageMultiplier($packageType) {
        $p = strtolower(trim((string)$packageType));
        $packages = [
            'basic' => 1.00,
            'standard' => 1.00,
            'premium' => 1.25,
            'luxury' => 1.50
        ];

        if (!isset($packages[$p])) {
            return 1.00;
        }

        return (float)$packages[$p];
    }

    function validatePackage($packageType) {
        $p = strtolower(trim((string)$packageType));
        $packages = [
            'basic' => 1.00,
            'standard' => 1.00,
            'premium' => 1.25,
            'luxury' => 1.50
        ];

        if (!isset($packages[$p])) {
            return ['valid' => false, 'error' => 'Invalid or inactive package selected.', 'multiplier' => 1.00];
        }

        return ['valid' => true, 'multiplier' => (float)$packages[$p], 'package' => ucfirst($p), 'error' => null];
    }

    /**
     * 5. DISCOUNT VALIDATION & CALCULATION
     * Supports:
     * - none
     * - flat (₹ value)
     * - percentage (% of subtotal)
     *
     * Rule: Never allow the discount to exceed the subtotal (total cannot be negative).
     */
    function calculateDiscount($subtotal, $discountType, $discountValue) {
        $subtotal = max(0.0, (float)$subtotal);
        $discountType = strtolower(trim((string)$discountType));
        
        if ($discountType === 'none' || empty($discountType)) {
            return ['valid' => true, 'discount_type' => 'none', 'discount_value' => 0.00, 'discount_amount' => 0.00, 'error' => null];
        }

        if (!is_numeric($discountValue)) {
            return ['valid' => false, 'error' => 'Discount value must be numeric.', 'discount_amount' => 0.00];
        }

        $val = (float)$discountValue;
        if ($val < 0) {
            return ['valid' => false, 'error' => 'Discount cannot be negative.', 'discount_amount' => 0.00];
        }

        $discountAmount = 0.00;
        if ($discountType === 'percentage') {
            if ($val > 100) {
                return ['valid' => false, 'error' => 'Percentage discount cannot exceed 100%.', 'discount_amount' => 0.00];
            }
            $discountAmount = round($subtotal * ($val / 100), 2);
        } elseif ($discountType === 'flat') {
            $discountAmount = round($val, 2);
        } else {
            return ['valid' => false, 'error' => 'Invalid discount type specified.', 'discount_amount' => 0.00];
        }

        // Rule: Discount can never exceed subtotal (never allow negative total)
        if ($discountAmount > $subtotal) {
            $discountAmount = $subtotal;
        }

        return [
            'valid' => true,
            'discount_type' => $discountType,
            'discount_value' => $val,
            'discount_amount' => $discountAmount,
            'error' => null
        ];
    }

    /**
     * 6. TAX (GST) CALCULATION & VALIDATION
     */
    function calculateTax($taxableAmount, $taxPercentage = 18.0) {
        $taxableAmount = max(0.0, (float)$taxableAmount);
        
        if (!is_numeric($taxPercentage)) {
            return ['valid' => false, 'error' => 'Tax percentage must be numeric.', 'tax_amount' => 0.00];
        }

        $pct = (float)$taxPercentage;
        if ($pct < 0) {
            return ['valid' => false, 'error' => 'Tax percentage cannot be negative.', 'tax_amount' => 0.00];
        }

        if ($pct > 100) {
            return ['valid' => false, 'error' => 'Tax percentage cannot exceed 100%.', 'tax_amount' => 0.00];
        }

        $taxAmount = round($taxableAmount * ($pct / 100), 2);
        return [
            'valid' => true,
            'tax_percentage' => $pct,
            'tax_amount' => $taxAmount,
            'error' => null
        ];
    }

    /**
     * 7. GRAND TOTAL SYNTHESIS
     * Grand Total = (Subtotal + Additional Charges - Discount) + Tax
     * Supports both 7-parameter full breakdown and 5-parameter subtotal invocation.
     */
    function calculateQuoteGrandTotal(
        $baseCost,
        $servicesTotal = 0.00,
        $packageMultiplier = 1.00,
        $additionalCharges = 0.00,
        $discountType = 'none',
        $discountValue = 0.00,
        $taxPercentage = 18.00
    ) {
        // If invoked with 5 arguments: calculateQuoteGrandTotal($subtotal, $additional, $discountType, $discountValue, $taxPercent)
        if (is_string($packageMultiplier) && in_array(strtolower($packageMultiplier), ['none', 'flat', 'percentage'])) {
            $taxPercentage = (float)$discountType;
            $discountValue = (float)$additionalCharges;
            $discountType = $packageMultiplier;
            $additionalCharges = (float)$servicesTotal;
            $subtotalInput = (float)$baseCost;
            $baseCost = $subtotalInput;
            $servicesTotal = 0.00;
            $packageMultiplier = 1.00;
        }

        $baseCost = max(0.0, (float)$baseCost);
        $servicesTotal = max(0.0, (float)$servicesTotal);
        $additionalCharges = max(0.0, (float)$additionalCharges);

        // Package Multiplier applied cleanly to base estimate
        $pkgMult = getPackageMultiplier($packageMultiplier);
        $adjustedBaseCost = round($baseCost * $pkgMult, 2);

        // Subtotal = Adjusted Base Cost + Services Total
        $subtotal = round($adjustedBaseCost + $servicesTotal, 2);

        // Discount
        $discRes = calculateDiscount($subtotal, $discountType, $discountValue);
        $discountAmount = $discRes['valid'] ? $discRes['discount_amount'] : 0.00;

        // Net Taxable Amount
        $netTaxable = max(0.0, round(($subtotal + $additionalCharges) - $discountAmount, 2));

        // Tax (GST)
        $taxRes = calculateTax($netTaxable, $taxPercentage);
        $taxAmount = $taxRes['valid'] ? $taxRes['tax_amount'] : 0.00;

        // Grand Total
        $grandTotal = round($netTaxable + $taxAmount, 2);

        return [
            'base_cost' => $baseCost,
            'package_multiplier' => $pkgMult,
            'adjusted_base_cost' => $adjustedBaseCost,
            'services_total' => $servicesTotal,
            'subtotal' => $subtotal,
            'additional_charges' => $additionalCharges,
            'discount_type' => $discRes['discount_type'] ?? 'none',
            'discount_value' => $discRes['discount_value'] ?? 0.00,
            'discount_amount' => $discountAmount,
            'net_taxable' => $netTaxable,
            'taxable_amount' => $netTaxable,
            'tax_percentage' => $taxRes['tax_percentage'] ?? 18.00,
            'tax_amount' => $taxAmount,
            'grand_total' => $grandTotal
        ];
    }

    /**
     * 8. INDIAN CURRENCY FORMATTER
     * Formats numbers to Indian numbering format: e.g. 10,50,000.00
     */
    function formatIndianCurrency($number, $includeSymbol = true, $decimals = 2) {
        $number = (float)$number;
        $isNegative = $number < 0;
        $number = abs($number);

        $formatted = number_format($number, $decimals, '.', '');
        $parts = explode('.', $formatted);
        $integerPart = $parts[0];
        $decimalPart = isset($parts[1]) ? '.' . $parts[1] : '';

        if (strlen($integerPart) > 3) {
            $lastThree = substr($integerPart, -3);
            $rest = substr($integerPart, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $indianStr = $rest . ',' . $lastThree;
        } else {
            $indianStr = $integerPart;
        }

        $res = ($isNegative ? '-' : '') . ($includeSymbol ? '₹' : '') . $indianStr . $decimalPart;
        return $res;
    }

    /**
     * 9. IDEMPOTENCY / SUBMISSION TOKEN MANAGEMENT
     */
    function generateQuoteSubmissionToken() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $token = bin2hex(random_bytes(32));
        $_SESSION['quote_submission_token'] = $token;
        return $token;
    }

    function verifyQuoteSubmissionToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($token) || empty($_SESSION['quote_submission_token'])) {
            return false;
        }
        $isValid = hash_equals($_SESSION['quote_submission_token'], $token);
        if ($isValid) {
            // Invalidate token after single use to prevent duplicate submission
            unset($_SESSION['quote_submission_token']);
        }
        return $isValid;
    }

    /**
     * 10. STATUS AUDIT LOGGING HELPER
     */
    if (!function_exists('recordQuoteStatusHistory')) {
        function recordQuoteStatusHistory($conn, $quoteId, $prevStatus, $newStatus, $byType = 'admin', $byName = 'Admin', $comment = '') {
            $quoteId = (int)$quoteId;
            $prevStatus = $prevStatus !== null ? (string)$prevStatus : null;
            $newStatus = (string)$newStatus;
            $byType = in_array($byType, ['customer', 'admin', 'system']) ? $byType : 'admin';
            $byName = (string)$byName;
            $comment = (string)$comment;

            $stmt = $conn->prepare("INSERT INTO quote_status_history (quote_id, previous_status, new_status, changed_by_type, changed_by_name, comment) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("isssss", $quoteId, $prevStatus, $newStatus, $byType, $byName, $comment);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    /**
     * 11. STATUS INFO HELPER
     */
    if (!function_exists('getQuoteStatusInfo')) {
        function getQuoteStatusInfo($status) {
            $badges = [
                'new' => ['label' => 'New Request', 'color' => '#2563eb', 'bg' => '#eff6ff', 'border' => '#bfdbfe', 'icon' => '📩'],
                'under_review' => ['label' => 'Under Review', 'color' => '#d97706', 'bg' => '#fffbeb', 'border' => '#fde68a', 'icon' => '⏳'],
                'contacted' => ['label' => 'Contacted / In Discussion', 'color' => '#4f46e5', 'bg' => '#eef2ff', 'border' => '#c7d2fe', 'icon' => '📞'],
                'site_visit_scheduled' => ['label' => 'Site Visit Scheduled', 'color' => '#7c3aed', 'bg' => '#f5f3ff', 'border' => '#ddd6fe', 'icon' => '📅'],
                'estimation_prepared' => ['label' => 'Estimation Prepared', 'color' => '#0d9488', 'bg' => '#f0fdfa', 'border' => '#99f6e4', 'icon' => '📐'],
                'quote_sent' => ['label' => 'Quotation Sent', 'color' => '#0284c7', 'bg' => '#f0f9ff', 'border' => '#bae6fd', 'icon' => '📄'],
                'approved' => ['label' => 'Approved by Client', 'color' => '#16a34a', 'bg' => '#f0fdf4', 'border' => '#bbf7d0', 'icon' => '✅'],
                'in_progress' => ['label' => 'In Execution / Site Work', 'color' => '#059669', 'bg' => '#ecfdf5', 'border' => '#a7f3d0', 'icon' => '🔨'],
                'project_started' => ['label' => 'Project Started', 'color' => '#059669', 'bg' => '#ecfdf5', 'border' => '#a7f3d0', 'icon' => '🔨'],
                'completed' => ['label' => 'Project Completed', 'color' => '#15803d', 'bg' => '#dcfce7', 'border' => '#86efac', 'icon' => '🏆'],
                'rejected' => ['label' => 'Closed / Rejected', 'color' => '#dc2626', 'bg' => '#fef2f2', 'border' => '#fecaca', 'icon' => '❌']
            ];
            return $badges[$status] ?? ['label' => ucfirst(str_replace('_', ' ', (string)$status)), 'color' => '#64748b', 'bg' => '#f8fafc', 'border' => '#e2e8f0', 'icon' => '📌'];
        }
    }
}
