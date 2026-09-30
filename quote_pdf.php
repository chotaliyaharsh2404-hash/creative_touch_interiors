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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation — <?php echo htmlspecialchars($quote['quote_number']); ?> — Creative Touch Interiors</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-black: #111111;
            --color-bronze: #8B7355;
            --color-bronze-light: #C4A982;
            --color-ivory: #F5F2EC;
            --color-border: #E8E5DF;
            --color-text-muted: #64748B;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #E8E5DF;
            color: #111111;
            margin: 0;
            padding: 2.5rem 1rem;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        .pdf-page {
            max-width: 860px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 3.5rem 4rem;
            border-radius: 4px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
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
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.2s;
        }
        .btn-print {
            background: #111111;
            color: #FFFFFF;
        }
        .btn-print:hover {
            background: #8B7355;
        }
        .btn-close {
            background: #FFFFFF;
            color: #111111;
            border-color: #CBD5E1;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111111;
            padding-bottom: 2rem;
            margin-bottom: 2rem;
        }
        .brand-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.85rem;
            font-weight: 700;
            margin: 0 0 0.35rem;
            letter-spacing: -0.01em;
            color: #111111;
        }
        .brand-tagline {
            color: #8B7355;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .doc-meta {
            text-align: right;
            font-size: 0.85rem;
        }
        .doc-badge {
            display: inline-block;
            background: #111111;
            color: #FFFFFF;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
            border-radius: 2px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2.5rem;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            padding: 1.5rem;
            border-radius: 4px;
        }
        .info-block h4 {
            margin: 0 0 0.65rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #8B7355;
            font-weight: 700;
        }
        .info-block p {
            margin: 0.2rem 0;
            font-size: 0.9rem;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
            font-size: 0.885rem;
        }
        .table-custom th {
            background: #111111;
            color: #FFFFFF;
            text-align: left;
            padding: 0.65rem 0.85rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
        }
        .table-custom td {
            padding: 0.75rem 0.85rem;
            border-bottom: 1px solid #E2E8F0;
        }
        .table-custom tr:nth-child(even) td {
            background: #FAFAFA;
        }
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 2.5rem;
        }
        .totals-table {
            width: 380px;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .totals-table td {
            padding: 0.45rem 0.5rem;
        }
        .totals-table tr.grand-total-row td {
            border-top: 2px solid #111111;
            border-bottom: 2px solid #111111;
            padding: 0.75rem 0.5rem;
            font-size: 1.15rem;
            font-weight: 700;
            color: #111111;
        }
        .signatures-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            margin-top: 4rem;
            padding-top: 2rem;
            border-top: 1px solid #E2E8F0;
        }
        .sig-box {
            text-align: center;
        }
        .sig-line {
            height: 1px;
            background: #111111;
            margin-bottom: 0.5rem;
        }
        .sig-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #111111;
        }
        .sig-sub {
            font-size: 0.75rem;
            color: #64748B;
        }
        @media print {
            body {
                background: #FFFFFF;
                padding: 0;
            }
            .no-print-bar {
                display: none !important;
            }
            .pdf-page {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
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
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Print / Save PDF
            </button>
        </div>
    </div>

    <div class="pdf-page">
        
        <!-- Header -->
        <div class="header-top">
            <div>
                <div class="brand-title">Creative Touch Interiors</div>
                <div class="brand-tagline">Architectural Interior Practice &bull; Turnkey Execution</div>
                <p style="margin: 0.4rem 0 0; font-size: 0.8rem; color: var(--color-text-muted); max-width: 360px;">
                    Madhavanand Society, Dhanmora, Chikuvadi, Katargam, Surat, Gujarat 395004<br>
                    Phone: +91 9316856961 &bull; Email: harshchotaliya@gmail.com
                </p>
            </div>
            <div class="doc-meta">
                <span class="doc-badge">Official Quotation Proposal</span>
                <div style="font-family: monospace; font-size: 1.15rem; font-weight: 700; color: #8B7355;"><?php echo htmlspecialchars($quote['quote_number']); ?></div>
                <div style="color: #64748B; margin-top: 0.25rem;">Date: <?php echo date('d F Y', strtotime($quote['created_at'])); ?></div>
                <div style="color: #64748B;">Valid Until: <?php echo date('d F Y', strtotime($quote['created_at'] . ' + 30 days')); ?></div>
            </div>
        </div>

        <!-- Client & Project Information -->
        <div class="info-grid">
            <div class="info-block">
                <h4>Client Details</h4>
                <p><strong><?php echo htmlspecialchars($quote['customer_name']); ?></strong></p>
                <p>Email: <?php echo htmlspecialchars($quote['email']); ?></p>
                <p>Mobile: <?php echo htmlspecialchars($quote['phone']); ?></p>
                <?php if (!empty($quote['address'])): ?>
                    <p>Address: <?php echo htmlspecialchars($quote['address'] . ', ' . $quote['city']); ?></p>
                <?php else: ?>
                    <p>City: <?php echo htmlspecialchars($quote['city']); ?></p>
                <?php endif; ?>
            </div>
            <div class="info-block">
                <h4>Project Parameters</h4>
                <p>Scope: <strong><?php echo htmlspecialchars($quote['project_type'] . ' &mdash; ' . $quote['property_type']); ?></strong></p>
                <p>Design Style: <?php echo htmlspecialchars($quote['design_style'] ?: 'Modern Luxury'); ?></p>
                <p>Package Tier: <strong><?php echo htmlspecialchars($quote['package_type'] ?? 'Standard'); ?></strong> (<?php echo number_format($quote['package_multiplier'] ?? 1.0, 2); ?>x)</p>
                <p>Status: <strong style="color: <?php echo $statusInfo['color']; ?>;"><?php echo htmlspecialchars($statusInfo['label']); ?></strong></p>
            </div>
        </div>

        <!-- Room Schedule Breakdown -->
        <div style="margin-bottom: 2rem;">
            <h3 style="font-family: 'Playfair Display', serif; font-size: 1.15rem; margin: 0 0 0.75rem; color: #111111;">
                1. Spatial Dimensions &amp; Room Schedule
            </h3>
            
            <?php if (($quote['measurement_status'] ?? 'known') === 'unknown'): ?>
                <div style="background: #FFFBEB; border: 1px solid #FDE68A; padding: 1rem 1.25rem; border-radius: 4px; font-size: 0.875rem; color: #92400E;">
                    <strong>Measurement Status: Measurement Required</strong><br>
                    Client requested professional on-site laser survey and dimension verification by Creative Touch Interiors architects.
                </div>
            <?php elseif (!empty($rooms)): ?>
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
                                <td><strong><?php echo htmlspecialchars($r['room_name']); ?></strong><?php echo !empty($r['notes']) ? ' <span style="color:#64748B; font-size:0.8rem;">(' . htmlspecialchars($r['notes']) . ')</span>' : ''; ?></td>
                                <td style="text-align: center;"><?php echo number_format((float)$r['length_ft'], 2); ?></td>
                                <td style="text-align: center;"><?php echo number_format((float)$r['width_ft'], 2); ?></td>
                                <td style="text-align: right; font-weight: 600;"><?php echo number_format((float)$r['area_sqft'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background: #F8FAFC; font-weight: 700;">
                            <td colspan="4" style="text-align: right;">Total Verified Carpet Area:</td>
                            <td style="text-align: right; color: #8B7355;"><?php echo number_format($totalRoomArea, 2); ?> sq.ft</td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
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
                            <td style="text-align: right; font-weight: 600;"><?php echo number_format((float)$quote['approx_area'], 2); ?> sq.ft</td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Itemized Services Breakdown -->
        <div style="margin-bottom: 2rem;">
            <h3 style="font-family: 'Playfair Display', serif; font-size: 1.15rem; margin: 0 0 0.75rem; color: #111111;">
                2. Itemized Architectural Services &amp; Finishes
            </h3>
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
                                    <div style="font-size: 0.75rem; color: #64748B; text-transform: uppercase;">
                                        <?php echo htmlspecialchars(str_replace('_', ' ', $s['service_type'] ?? 'Standard')); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($s['room_name'] ?: 'Whole Space'); ?></td>
                                <td style="text-align: center; font-size: 0.8rem;">
                                    <?php if (($s['service_type'] ?? '') === 'area_based'): ?>
                                        ₹<?php echo number_format((float)$s['unit_rate'], 2); ?>/sq.ft
                                    <?php elseif (($s['service_type'] ?? '') === 'quantity_based'): ?>
                                        ₹<?php echo number_format((float)$s['unit_rate'], 2); ?> &times; <?php echo (float)$s['quantity']; ?>
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
                            <td colspan="6" style="text-align: center; color: #64748B;">Comprehensive turnkey architecture package based on floor area.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals & Financial Summary -->
        <div class="totals-section">
            <table class="totals-table">
                <?php if ((float)$quote['base_cost'] > 0): ?>
                    <tr>
                        <td style="color: #64748B;">Base Space Planning:</td>
                        <td style="text-align: right; font-weight: 500;">₹<?php echo number_format((float)$quote['base_cost'], 2); ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ((float)$quote['service_cost'] > 0): ?>
                    <tr>
                        <td style="color: #64748B;">Craftsmanship &amp; Services Total:</td>
                        <td style="text-align: right; font-weight: 500;">₹<?php echo number_format((float)$quote['service_cost'], 2); ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ((float)($quote['package_multiplier'] ?? 1.0) != 1.0): ?>
                    <tr>
                        <td style="color: #64748B;"><?php echo htmlspecialchars($quote['package_type'] ?? 'Tier'); ?> Multiplier:</td>
                        <td style="text-align: right; font-weight: 500;"><?php echo number_format((float)$quote['package_multiplier'], 2); ?>x</td>
                    </tr>
                <?php endif; ?>

                <?php if ((float)$quote['additional_cost'] > 0): ?>
                    <tr>
                        <td style="color: #64748B;">Additional Structural / Site Fees:</td>
                        <td style="text-align: right; font-weight: 500;">₹<?php echo number_format((float)$quote['additional_cost'], 2); ?></td>
                    </tr>
                <?php endif; ?>

                <?php if ((float)$quote['discount_amount'] > 0): ?>
                    <tr>
                        <td style="color: #16A34A;">Promotional Studio Discount:</td>
                        <td style="text-align: right; color: #16A34A; font-weight: 600;">- ₹<?php echo number_format((float)$quote['discount_amount'], 2); ?></td>
                    </tr>
                <?php endif; ?>

                <tr>
                    <td style="color: #64748B;">Taxable Net Amount:</td>
                    <td style="text-align: right; font-weight: 600;">
                        <?php 
                        $netTaxable = max(0, ((float)$quote['base_cost'] + (float)$quote['service_cost'] + (float)$quote['additional_cost']) - (float)$quote['discount_amount']);
                        echo '₹' . number_format($netTaxable, 2);
                        ?>
                    </td>
                </tr>

                <tr>
                    <td style="color: #64748B;">GST / Tax:</td>
                    <td style="text-align: right; font-weight: 500;">₹<?php echo number_format((float)$quote['tax_amount'], 2); ?></td>
                </tr>

                <tr class="grand-total-row">
                    <td>Grand Investment Total:</td>
                    <td style="text-align: right; color: #8B7355;">
                        <?php echo formatIndianCurrency($quote['estimated_total'], true, 2); ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Payment Milestones & Terms -->
        <div style="border-top: 1px solid #E2E8F0; padding-top: 1.5rem; margin-bottom: 2rem;">
            <h4 style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.08em; color: #8B7355; margin: 0 0 0.5rem;">
                Standard Turnkey Milestone Schedule
            </h4>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; font-size: 0.8rem; text-align: center;">
                <div style="background: #F8FAFC; padding: 0.75rem; border-radius: 4px; border: 1px solid #E2E8F0;">
                    <div style="font-weight: 700; color: #111111;">10%</div>
                    <div style="color: #64748B; margin-top: 0.2rem;">Booking &amp; 3D Design Approval</div>
                </div>
                <div style="background: #F8FAFC; padding: 0.75rem; border-radius: 4px; border: 1px solid #E2E8F0;">
                    <div style="font-weight: 700; color: #111111;">40%</div>
                    <div style="color: #64748B; margin-top: 0.2rem;">Civil Work &amp; Structural Joinery</div>
                </div>
                <div style="background: #F8FAFC; padding: 0.75rem; border-radius: 4px; border: 1px solid #E2E8F0;">
                    <div style="font-weight: 700; color: #111111;">40%</div>
                    <div style="color: #64748B; margin-top: 0.2rem;">Veneer, Paint &amp; Electrical Fit-outs</div>
                </div>
                <div style="background: #F8FAFC; padding: 0.75rem; border-radius: 4px; border: 1px solid #E2E8F0;">
                    <div style="font-weight: 700; color: #111111;">10%</div>
                    <div style="color: #64748B; margin-top: 0.2rem;">Final Inspection &amp; Key Handover</div>
                </div>
            </div>
        </div>

        <!-- Signatures -->
        <div class="signatures-grid">
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-title">Harsh Chotaliya &bull; Principal Architect</div>
                <div class="sig-sub">Creative Touch Interiors</div>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-title">Client Acceptance Signature</div>
                <div class="sig-sub"><?php echo htmlspecialchars($quote['customer_name']); ?></div>
            </div>
        </div>

    </div>

</body>
</html>
