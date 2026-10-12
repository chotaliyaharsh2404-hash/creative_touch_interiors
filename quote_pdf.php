<?php
require_once __DIR__ . '/includes/config.php';

// Prevent browser caching
preventPageCaching();

// Support lookup by id or quote_number
$quote_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quote_num = isset($_GET['quote']) ? sanitize($_GET['quote']) : '';

if ($quote_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM quote_requests WHERE id = ?");
    $stmt->bind_param("i", $quote_id);
} elseif (!empty($quote_num)) {
    $stmt = $conn->prepare("SELECT * FROM quote_requests WHERE quote_number = ?");
    $stmt->bind_param("s", $quote_num);
} else {
    redirect('profile.php#quotes');
}

$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();

if (!$quote) {
    die("Quotation document not found.");
}

// SECURITY & AUTHORIZATION CHECK
$is_admin = isAdminLoggedIn();
$is_user = isUserLoggedIn();
$current_user_id = $is_user ? (int)$_SESSION['user_id'] : 0;
$current_user_email = $is_user ? strtolower(trim($_SESSION['user_email'] ?? '')) : '';

$authorized = false;
if ($is_admin) {
    $authorized = true;
} elseif ($is_user) {
    if ((int)$quote['user_id'] === $current_user_id) {
        $authorized = true;
    } elseif (!empty($current_user_email) && strtolower(trim($quote['email'])) === $current_user_email) {
        $authorized = true;
    }
}

if (!$authorized) {
    http_response_code(403);
    die("<div style='font-family:sans-serif; text-align:center; padding:4rem;'><h2>403 Forbidden</h2><p>You do not have permission to view this quotation.</p></div>");
}

// Fetch rooms
$rooms_stmt = $conn->prepare("SELECT * FROM quote_rooms WHERE quote_id = ? ORDER BY id ASC");
$rooms_stmt->bind_param("i", $quote['id']);
$rooms_stmt->execute();
$rooms = $rooms_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch itemized services
$srv_stmt = $conn->prepare("SELECT * FROM quote_services WHERE quote_id = ? ORDER BY id ASC");
$srv_stmt->bind_param("i", $quote['id']);
$srv_stmt->execute();
$services = $srv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$statusInfo = getQuoteStatusInfo($quote['status']);

// Resolve customer-facing values
$customer_name = !empty($quote['customer_name']) ? $quote['customer_name'] : (!empty($quote['name']) ? $quote['name'] : 'Valued Client');
$pType = trim($quote['project_type'] ?? '');
$propType = trim($quote['property_type'] ?? '');
if (!empty($pType) && !empty($propType)) {
    $scope_display = htmlspecialchars($pType) . ' — ' . htmlspecialchars($propType);
} else {
    $scope_display = htmlspecialchars($pType ?: ($propType ?: 'Turnkey Architectural Scope'));
}
$package_tier_label = formatPackageTierDisplay($quote['package_type'] ?? '');
$package_multiplier = (float)($quote['package_multiplier'] ?? 1.0);
$budget_display = formatBudgetDisplay($quote['budget_range'] ?? '');

// Financial breakdown calculation
$approx_area = (float)($quote['approx_area'] ?? 0);
$base_cost = (float)($quote['base_cost'] ?? 0);
$service_cost = (float)($quote['service_cost'] ?? 0);
$material_cost = (float)($quote['material_cost'] ?? 0);
$additional_cost = (float)($quote['additional_cost'] ?? 0);
$discount_amount = (float)($quote['discount_amount'] ?? 0);
$tax_amount = (float)($quote['tax_amount'] ?? 0);
$estimated_total = (float)($quote['estimated_total'] ?? 0);

$base_rate_sqft = (float)($quote['base_rate_per_sqft'] ?? 0);
if ($base_rate_sqft <= 0) {
    $base_rate_sqft = ($approx_area > 0 && $base_cost > 0) ? round($base_cost / $approx_area, 2) : getBaseSpatialRate();
}
if ($base_cost <= 0 && $approx_area > 0) {
    $base_cost = calculateBaseSpatialCost($approx_area, $base_rate_sqft);
}

$gross_subtotal = round(($base_cost * $package_multiplier) + $service_cost + $material_cost + $additional_cost, 2);
$netTaxable = max(0, round($gross_subtotal - $discount_amount, 2));
$tax_percent = ($netTaxable > 0 && $tax_amount > 0) ? round(($tax_amount / $netTaxable) * 100) : 18;

$preGstCost = ($tax_amount > 0 && $estimated_total > 0) ? round($estimated_total - $tax_amount, 2) : $netTaxable;
if ($estimated_total <= 0) {
    $tax_amount = round($preGstCost * 0.18, 2);
    $estimated_total = round($preGstCost + $tax_amount, 2);
}
$budgetEval = evaluateQuoteBudget($preGstCost, $quote['budget_range'] ?? '', [
    'package_type' => $quote['package_type'] ?? '',
    'package_multiplier' => $package_multiplier,
    'final_payable' => $estimated_total
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation — <?php echo htmlspecialchars($quote['quote_number']); ?> — Creative Touch Interiors</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0057FF;
            --primary-dark: #0046D6;
            --primary-light: #EFF6FF;
            --primary-border: rgba(0, 87, 255, 0.16);
            --bg-page: #F8F7F4;
            --surface-white: #FFFFFF;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --border-subtle: #E2E8F0;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-page);
            color: var(--text-main);
            margin: 0;
            padding: 2.5rem 1rem;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }
        .pdf-page {
            max-width: 860px;
            margin: 0 auto;
            background: var(--surface-white);
            padding: 3.5rem 3.75rem;
            border-radius: 12px;
            border: 1px solid var(--primary-border);
            box-shadow: 0 16px 40px rgba(0, 40, 120, 0.06);
            position: relative;
        }
        .no-print-bar {
            max-width: 860px;
            margin: 0 auto 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1.4rem;
            border-radius: 8px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-print {
            background: var(--primary);
            color: #FFFFFF;
            border: 1px solid var(--primary);
            box-shadow: 0 4px 14px rgba(0, 87, 255, 0.22);
        }
        .btn-print:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        .btn-close {
            background: var(--surface-white);
            color: var(--text-main);
            border: 1px solid #CBD5E1;
        }
        .btn-close:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* Modern Branded Header */
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 1.75rem;
            margin-bottom: 2.25rem;
            gap: 1.5rem;
        }
        .brand-block {
            display: flex;
            align-items: flex-start;
            gap: 0.9rem;
        }
        .brand-emblem {
            width: 42px;
            height: 42px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .brand-emblem-diamond {
            position: absolute;
            inset: 0;
            transform: rotate(45deg);
            border: 1.5px solid var(--primary);
            border-radius: 5px;
            background: linear-gradient(135deg, rgba(0, 87, 255, 0.16) 0%, rgba(0, 87, 255, 0.04) 100%);
        }
        .brand-emblem svg {
            position: relative;
            z-index: 1;
            color: var(--primary);
        }
        .brand-title {
            font-size: 1.45rem;
            font-weight: 800;
            margin: 0 0 0.2rem;
            letter-spacing: -0.01em;
            color: var(--text-main);
        }
        .brand-title span {
            color: var(--primary);
        }
        .brand-tagline {
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .brand-address {
            margin: 0.45rem 0 0;
            font-size: 0.78rem;
            color: var(--text-muted);
            line-height: 1.45;
        }
        .doc-meta {
            text-align: right;
            flex-shrink: 0;
        }
        .doc-badge {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            border: 1px solid #BFDBFE;
            padding: 0.28rem 0.75rem;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            border-radius: 999px;
            margin-bottom: 0.4rem;
        }
        .doc-number {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: 0.02em;
            line-height: 1.2;
        }
        .doc-date {
            color: var(--text-muted);
            font-size: 0.82rem;
            margin-top: 0.35rem;
        }

        /* Modern Rounded Info Cards */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2.25rem;
        }
        .info-card {
            background: var(--surface-white);
            border: 1px solid var(--primary-border);
            border-radius: 10px;
            padding: 1.35rem 1.5rem;
            box-shadow: 0 4px 16px rgba(0, 40, 120, 0.03);
        }
        .info-card-header {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--primary);
            font-weight: 800;
            margin-bottom: 0.75rem;
            border-bottom: 1px solid #F1F5F9;
            padding-bottom: 0.45rem;
        }
        .info-card-content {
            font-size: 0.865rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin: 0.35rem 0;
            gap: 0.75rem;
        }
        .info-label {
            color: var(--text-muted);
            font-size: 0.82rem;
            flex-shrink: 0;
        }
        .info-value {
            font-weight: 600;
            color: var(--text-main);
            text-align: right;
            word-break: break-word;
        }
        .status-pill {
            display: inline-block;
            padding: 0.15rem 0.6rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* Section Headings */
        .section-header {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            margin: 0 0 0.85rem;
        }
        .section-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            background: rgba(0, 87, 255, 0.10);
            color: var(--primary);
            font-size: 0.72rem;
            font-weight: 800;
            flex-shrink: 0;
        }
        .section-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }

        /* Modern Tables */
        .table-wrapper {
            border: 1px solid var(--primary-border);
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 2.25rem;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.865rem;
        }
        .table-custom th {
            background: var(--primary);
            color: #FFFFFF;
            text-align: left;
            padding: 0.7rem 0.85rem;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
        }
        .table-custom td {
            padding: 0.72rem 0.85rem;
            border-bottom: 1px solid #E2E8F0;
            color: var(--text-main);
        }
        .table-custom tr:nth-child(even) td {
            background: #F8FAFC;
        }
        .table-custom tr:last-child td {
            border-bottom: none;
        }

        /* Financial Quotation Summary */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 2.5rem;
        }
        .totals-card {
            width: 420px;
            background: var(--surface-white);
            border: 1px solid var(--primary-border);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0, 40, 120, 0.04);
        }
        .totals-card-header {
            background: #F8FAFC;
            padding: 0.65rem 1.15rem;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--primary);
            border-bottom: 1px solid var(--primary-border);
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        .totals-table td {
            padding: 0.5rem 1.15rem;
            color: var(--text-main);
        }
        .totals-table tr.total-divider td {
            border-top: 1px solid #E2E8F0;
            padding-top: 0.65rem;
        }
        .grand-total-row {
            background: var(--primary) !important;
            color: #FFFFFF !important;
        }
        .grand-total-row td {
            color: #FFFFFF !important;
            padding: 0.85rem 1.15rem !important;
            font-weight: 800;
            font-size: 1.05rem;
            letter-spacing: 0.01em;
        }

        /* Milestones & Schedule */
        .milestone-section {
            border-top: 1px solid var(--primary-border);
            padding-top: 1.75rem;
            margin-bottom: 2.5rem;
        }
        .milestone-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        .milestone-card {
            background: var(--surface-white);
            border: 1px solid var(--primary-border);
            border-radius: 8px;
            padding: 0.85rem 0.75rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 40, 120, 0.03);
        }
        .milestone-percent {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.25rem;
            line-height: 1.2;
        }
        .milestone-title {
            font-size: 0.74rem;
            color: var(--text-muted);
            font-weight: 500;
            line-height: 1.35;
        }

        /* Signatures Grid */
        .signatures-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            margin-top: 3.5rem;
            padding-top: 1.75rem;
            border-top: 1px solid #E2E8F0;
        }
        .sig-box {
            text-align: center;
        }
        .sig-line {
            height: 1.5px;
            background: var(--primary);
            margin-bottom: 0.65rem;
        }
        .sig-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-main);
        }
        .sig-sub {
            font-size: 0.74rem;
            color: var(--text-muted);
        }

        /* Branded Footer */
        .doc-footer {
            margin-top: 2.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(0, 87, 255, 0.12);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.74rem;
            color: var(--text-muted);
        }

        /* Print Media Styles */
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm 12mm;
            }
            body {
                background: #FFFFFF;
                padding: 0;
            }
            .no-print-bar {
                display: none !important;
            }
            .pdf-page {
                box-shadow: none;
                border: none;
                padding: 0;
                max-width: 100%;
                border-radius: 0;
            }
            .info-card,
            .table-wrapper,
            .totals-card,
            .milestone-section,
            .signatures-grid {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <div>
            <a href="<?php echo $is_admin ? 'admin/quote_details.php?id=' . $quote['id'] : 'quote_details.php?id=' . $quote['id']; ?>" class="btn-action btn-close">&larr; Return to Portal</a>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <button onclick="window.print()" class="btn-action btn-print">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Print / Save PDF
            </button>
        </div>
    </div>

    <div class="pdf-page">
        
        <!-- Modern Branded Header -->
        <div class="header-top">
            <div class="brand-block">
                <div class="brand-emblem" aria-hidden="true">
                    <div class="brand-emblem-diamond"></div>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18"/>
                        <path d="M5 21V7l8-4 6 3v15"/>
                        <path d="M9 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>
                    </svg>
                </div>
                <div>
                    <div class="brand-title">Creative Touch<span>.</span></div>
                    <div class="brand-tagline">Architectural Interior Practice &bull; Turnkey Execution</div>
                    <div class="brand-address">
                        Madhavanand Society, Dhanmora, Chikuvadi, Katargam, Surat, Gujarat 395004<br>
                        Phone: +91 9316856961 &bull; Email: harshchotaliya@gmail.com
                    </div>
                </div>
            </div>
            <div class="doc-meta">
                <span class="doc-badge">Official Quotation Proposal</span>
                <div class="doc-number"><?php echo htmlspecialchars($quote['quote_number']); ?></div>
                <div class="doc-date">Date: <?php echo date('d F Y', strtotime($quote['created_at'])); ?></div>
                <div class="doc-date">Valid Until: <?php echo date('d F Y', strtotime($quote['created_at'] . ' + 30 days')); ?></div>
            </div>
        </div>

        <!-- Client & Project Information Cards -->
        <div class="info-grid">
            <div class="info-card">
                <div class="info-card-header">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    Client Details
                </div>
                <div class="info-card-content">
                    <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">
                        <?php echo htmlspecialchars($customer_name); ?>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($quote['email'] ?? '—'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Mobile:</span>
                        <span class="info-value"><?php echo htmlspecialchars($quote['phone'] ?? '—'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">City:</span>
                        <span class="info-value">
                            <?php 
                            if (!empty($quote['address'])) {
                                echo htmlspecialchars($quote['address'] . ', ' . $quote['city']);
                            } else {
                                echo htmlspecialchars($quote['city'] ?: 'Surat');
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="info-card-header">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                    Project Parameters
                </div>
                <div class="info-card-content">
                    <div class="info-row">
                        <span class="info-label">Scope:</span>
                        <span class="info-value"><strong><?php echo $scope_display; ?></strong></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Design Style:</span>
                        <span class="info-value"><?php echo htmlspecialchars($quote['design_style'] ?: 'Modern Luxury'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Design Level:</span>
                        <span class="info-value">
                            <strong><?php echo htmlspecialchars($package_tier_label); ?></strong> (<?php echo number_format($package_multiplier, 2); ?>×)
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Estimated Budget:</span>
                        <span class="info-value" style="color: var(--primary);">
                            <span style="display:none">Estimated Budget: <strong><?php echo htmlspecialchars($budget_display); ?></strong></span>
                            <strong><?php echo htmlspecialchars($budget_display); ?></strong>
                        </span>
                    </div>
                    <?php if ($budgetEval['status'] !== 'unspecified'): ?>
                        <div class="info-row">
                            <span class="info-label">Budget Alignment:</span>
                            <span class="info-value">
                                <span class="status-pill" style="background: <?php echo htmlspecialchars($budgetEval['badge_bg']); ?>; color: <?php echo htmlspecialchars($budgetEval['badge_color']); ?>; border: 1px solid <?php echo htmlspecialchars($budgetEval['badge_border']); ?>;">
                                    <?php echo htmlspecialchars($budgetEval['status_label']); ?>
                                </span>
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="info-row" style="margin-top: 0.5rem;">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <span class="status-pill" style="background: <?php echo htmlspecialchars($statusInfo['bg']); ?>; color: <?php echo htmlspecialchars($statusInfo['color']); ?>; border: 1px solid <?php echo htmlspecialchars($statusInfo['border']); ?>;">
                                <?php echo htmlspecialchars($statusInfo['label']); ?>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Room Schedule Breakdown -->
        <div style="margin-bottom: 2rem;">
            <div class="section-header">
                <span class="section-num">01</span>
                <h3 class="section-title">Spatial Dimensions &amp; Room Schedule</h3>
            </div>
            
            <?php if (($quote['measurement_status'] ?? 'known') === 'unknown'): ?>
                <div style="background: #EFF6FF; border: 1px solid #BFDBFE; padding: 1rem 1.25rem; border-radius: 8px; font-size: 0.875rem; color: #1E40AF; margin-bottom: 2.25rem;">
                    <strong>Measurement Status: Professional On-Site Laser Survey Required</strong><br>
                    Client requested on-site laser survey and spatial dimension verification by Creative Touch Interiors architects.
                </div>
            <?php elseif (!empty($rooms)): ?>
                <div class="table-wrapper">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 45%;">Room Specification</th>
                                <th style="width: 15%; text-align: center;">Length (ft)</th>
                                <th style="width: 15%; text-align: center;">Width (ft)</th>
                                <th style="width: 20%; text-align: right;">Area (sq.ft)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalRoomArea = 0;
                            foreach ($rooms as $idx => $r): 
                                $totalRoomArea += (float)$r['area_sqft'];
                            ?>
                                <tr>
                                    <td><?php echo $idx + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($r['room_name']); ?></strong>
                                        <?php echo !empty($r['notes']) ? ' <span style="color:var(--text-muted); font-size:0.78rem;">(' . htmlspecialchars($r['notes']) . ')</span>' : ''; ?>
                                    </td>
                                    <td style="text-align: center;"><?php echo number_format((float)$r['length_ft'], 2); ?></td>
                                    <td style="text-align: center;"><?php echo number_format((float)$r['width_ft'], 2); ?></td>
                                    <td style="text-align: right; font-weight: 600;"><?php echo number_format((float)$r['area_sqft'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr style="background: #EFF6FF; font-weight: 700;">
                                <td colspan="4" style="text-align: right; color: var(--primary);">Total Verified Carpet Area:</td>
                                <td style="text-align: right; color: var(--primary); font-weight: 800;"><?php echo number_format($totalRoomArea, 2); ?> sq.ft</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Parameter</th>
                                <th style="text-align: right;">Measurement</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Total Floor Approximate Area</td>
                                <td style="text-align: right; font-weight: 700; color: var(--primary);">
                                    <?php echo number_format((float)($quote['approx_area'] ?? 0), 2); ?> sq.ft
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Itemized Services Breakdown -->
        <div style="margin-bottom: 2rem;">
            <div class="section-header">
                <span class="section-num">02</span>
                <h3 class="section-title">Itemized Architectural Services &amp; Finishes</h3>
            </div>
            <div class="table-wrapper">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 35%;">Service Scope</th>
                            <th style="width: 20%;">Room / Zone</th>
                            <th style="width: 15%; text-align: center;">Rate Spec</th>
                            <th style="width: 10%; text-align: center;">Wastage</th>
                            <th style="width: 15%; text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($services)): ?>
                            <?php foreach ($services as $idx => $s): 
                                $amt = (float)($s['final_service_amount'] > 0 ? $s['final_service_amount'] : ($s['service_rate'] ?: 0));
                            ?>
                                <tr>
                                    <td><?php echo $idx + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($s['service_title']); ?></strong>
                                        <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;">
                                            <?php echo htmlspecialchars(str_replace('_', ' ', $s['service_type'] ?? 'Standard')); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($s['room_name'] ?: 'Whole Space'); ?></td>
                                    <td style="text-align: center; font-size: 0.8rem;">
                                        <?php if (($s['service_type'] ?? '') === 'area_based'): ?>
                                            ₹<?php echo number_format((float)$s['unit_rate'], 2); ?>/sq.ft
                                        <?php elseif (($s['service_type'] ?? '') === 'quantity_based'): ?>
                                            ₹<?php echo number_format((float)$s['unit_rate'], 2); ?> × <?php echo (float)$s['quantity']; ?>
                                        <?php else: ?>
                                            ₹<?php echo number_format((float)($s['unit_rate'] ?: $s['service_rate']), 2); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center; font-size: 0.8rem;">
                                        <?php echo (float)($s['wastage_percent'] ?? 0) > 0 ? (float)$s['wastage_percent'] . '%' : '0%'; ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 600;">
                                        ₹<?php echo number_format($amt, 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 1.25rem;">
                                    Comprehensive turnkey interior architecture package based on floor area.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Totals & Financial Summary Card -->
        <div class="totals-section">
            <div class="totals-card">
                <div class="totals-card-header">
                    Quotation Financial Summary
                </div>
                <table class="totals-table">
                    <?php if ($base_cost > 0): ?>
                        <tr>
                            <td style="color: var(--text-muted);">
                                Base Spatial Execution<br>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">
                                    <?php echo number_format($approx_area); ?> sq.ft × ₹<?php echo number_format($base_rate_sqft); ?>/sq.ft × <?php echo number_format($package_multiplier, 2); ?>×
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 600; vertical-align: top;">
                                ₹<?php echo number_format(round($base_cost * $package_multiplier, 2), 2); ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($service_cost > 0): ?>
                        <tr>
                            <td style="color: var(--text-muted);">Selected Modules / Services:</td>
                            <td style="text-align: right; font-weight: 600;">₹<?php echo number_format($service_cost, 2); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($additional_cost > 0): ?>
                        <tr>
                            <td style="color: var(--text-muted);">Additional Structural / Site Fees:</td>
                            <td style="text-align: right; font-weight: 600;">₹<?php echo number_format($additional_cost, 2); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($discount_amount > 0): ?>
                        <tr>
                            <td style="color: #16A34A; font-weight: 600;">Promotional Studio Discount:</td>
                            <td style="text-align: right; color: #16A34A; font-weight: 700;">- ₹<?php echo number_format($discount_amount, 2); ?></td>
                        </tr>
                    <?php endif; ?>

                    <tr class="total-divider">
                        <td style="color: var(--text-muted); font-weight: 600;">Project Cost Before GST:</td>
                        <td style="text-align: right; font-weight: 700; color: var(--text-main);">
                            ₹<?php echo number_format($preGstCost, 2); ?>
                        </td>
                    </tr>

                    <tr>
                        <td style="color: var(--text-muted);">GST (<?php echo $tax_percent; ?>%):</td>
                        <td style="text-align: right; font-weight: 600;">₹<?php echo number_format($tax_amount, 2); ?></td>
                    </tr>

                    <tr class="grand-total-row">
                        <td>Final Payable:</td>
                        <td style="text-align: right;">
                            <?php echo formatIndianCurrency($estimated_total, true, 2); ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Payment Milestones & Turnkey Schedule -->
        <div class="milestone-section">
            <div style="font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--primary); font-weight: 800; margin-bottom: 0.85rem;">
                Turnkey Project Milestones &amp; Payment Schedule
            </div>
            <div class="milestone-grid">
                <div class="milestone-card">
                    <div class="milestone-percent">10%</div>
                    <div class="milestone-title">Booking &amp; 3D Design Approval</div>
                </div>
                <div class="milestone-card">
                    <div class="milestone-percent">40%</div>
                    <div class="milestone-title">Civil Work &amp; Structural Joinery</div>
                </div>
                <div class="milestone-card">
                    <div class="milestone-percent">40%</div>
                    <div class="milestone-title">Veneer, Paint &amp; Electrical Fit-outs</div>
                </div>
                <div class="milestone-card">
                    <div class="milestone-percent">10%</div>
                    <div class="milestone-title">Final Inspection &amp; Key Handover</div>
                </div>
            </div>
        </div>

        <!-- Signatures Grid -->
        <div class="signatures-grid">
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-title">Harsh Chotaliya &bull; Principal Architect</div>
                <div class="sig-sub">Creative Touch Interiors</div>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-title">Client Acceptance Signature</div>
                <div class="sig-sub"><?php echo htmlspecialchars($customer_name); ?></div>
            </div>
        </div>

        <!-- Branded Document Footer -->
        <div class="doc-footer">
            <div>Creative Touch Interiors &bull; Architectural Interior Practice &bull; Turnkey Execution</div>
            <div>Confidential Client Proposal &bull; Valid for 30 Days</div>
        </div>

    </div>

</body>
</html>
