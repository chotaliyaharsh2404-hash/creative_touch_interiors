<?php
require_once 'includes/config.php';

// Prevent browser caching of quotation data
preventPageCaching();

// Support lookup by ID or quote_number
$quote_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$quote_num = isset($_GET['quote']) ? sanitize($_GET['quote']) : '';

if ($quote_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM quote_requests WHERE id = ?");
    $stmt->bind_param("i", $quote_id);
} elseif (!empty($quote_num)) {
    $stmt = $conn->prepare("SELECT * FROM quote_requests WHERE quote_number = ?");
    $stmt->bind_param("s", $quote_num);
} else {
    header("Location: profile.php#quotes");
    exit;
}

$stmt->execute();
$quote_row = $stmt->get_result()->fetch_assoc();

if (!$quote_row) {
    $_SESSION['flash_error'] = "Quotation record not found.";
    header("Location: index.php");
    exit;
}

// Normalize column aliases for seamless compatibility
$quote = $quote_row;
$quote['name'] = $quote_row['customer_name'] ?? ($quote_row['name'] ?? 'Valued Client');
$quote['carpet_area'] = (float)($quote_row['approx_area'] ?? ($quote_row['carpet_area'] ?? 0));
$quote['attachments'] = $quote_row['attachment_path'] ?? ($quote_row['attachments'] ?? '');
$quote['material_preference'] = $quote_row['material_preference'] ?? ($quote_row['material_quality'] ?? 'Premium');
$quote['material_quality'] = $quote['material_preference'];
$quote['final_estimate'] = (float)($quote_row['final_estimate'] ?? 0);
$quote['estimated_total'] = (float)($quote_row['estimated_total'] ?? 0);
$quote['designer_notes'] = $quote_row['designer_notes'] ?? ($quote_row['estimation_notes'] ?? ($quote_row['admin_notes'] ?? ''));

// SECURITY & AUTHORIZATION CHECK:
// Verify if the current visitor is authorized to view this quote
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
    // If not logged in, redirect to login with return url
    if (!$is_user) {
        $_SESSION['flash_error'] = "Please log in to your account to view this quotation breakdown.";
        header("Location: login.php?redirect=" . urlencode("quote_details.php?id=" . $quote['id']));
        exit;
    } else {
        // Forbidden: Logged in user attempting to access someone else's quote
        http_response_code(403);
        die("
            <div style='font-family: Inter, sans-serif; text-align: center; padding: 5rem 1rem; background: #F5F2EC; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center;'>
                <h1 style='color: #111111; font-family: Playfair Display, serif; font-size: 2.25rem; margin-bottom: 0.5rem;'>403 — Access Restricted</h1>
                <p style='color: #666666; font-size: 1rem; max-width: 480px; margin: 0 auto 2rem;'>
                    You do not have permission to access this quotation proposal.
                </p>
                <a href='profile.php#quotes' style='display: inline-block; background: #111111; color: #FFFFFF; padding: 0.75rem 1.75rem; text-decoration: none; border-radius: 4px; font-weight: 600;'>Return to My Quotes</a>
            </div>
        ");
    }
}

// Fetch selected services
$stmt_srv = $conn->prepare("
    SELECT qs.*, s.title as service_name, s.category, s.description as srv_desc 
    FROM quote_services qs 
    LEFT JOIN services s ON qs.service_id = s.id 
    WHERE qs.quote_id = ?
");
$stmt_srv->bind_param("i", $quote['id']);
$stmt_srv->execute();
$selected_services = $stmt_srv->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch room schedule
$stmt_rooms = $conn->prepare("SELECT * FROM quote_rooms WHERE quote_id = ? ORDER BY id ASC");
$stmt_rooms->bind_param("i", $quote['id']);
$stmt_rooms->execute();
$quote_rooms = $stmt_rooms->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch audit trail
$stmt_hist = $conn->prepare("
    SELECT * FROM quote_status_history 
    WHERE quote_id = ? 
    ORDER BY created_at ASC
");
$stmt_hist->bind_param("i", $quote['id']);
$stmt_hist->execute();
$status_history = $stmt_hist->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate estimate formula breakdown for display from quote record
$base_cost = (float)($quote['base_cost'] ?? 0);
if ($base_cost <= 0 && $quote['carpet_area'] > 0) {
    $rate = (float)($quote['base_rate_per_sqft'] ?? 0);
    if ($rate <= 0) {
        $pt = strtolower($quote['project_type'] ?? '');
        $rate = (strpos($pt, 'commercial') !== false || strpos($pt, 'office') !== false) ? 2200.0 : ((strpos($pt, 'hospitality') !== false) ? 2500.0 : 1800.0);
    }
    $base_cost = $quote['carpet_area'] * $rate;
}

$services_total = (float)($quote['service_cost'] ?? 0);
if ($services_total <= 0 && !empty($selected_services)) {
    foreach ($selected_services as $s) {
        $services_total += (float)($s['service_rate'] ?? ($s['service_price'] ?? 0));
    }
}

$material_multiplier = 1.0;
$matPref = strtolower($quote['material_preference'] ?? '');
if (strpos($matPref, 'ultra') !== false || strpos($matPref, 'italian') !== false || strpos($matPref, 'luxury') !== false) {
    $material_multiplier = 1.25;
} elseif (strpos($matPref, 'premium') !== false || strpos($matPref, 'teak') !== false) {
    $material_multiplier = 1.15;
}

$subtotal = $base_cost + $services_total + (float)($quote['material_cost'] ?? 0) + (float)($quote['additional_cost'] ?? 0) - (float)($quote['discount_amount'] ?? 0);
$tax_percent = isset($quote['tax_percentage']) ? (float)$quote['tax_percentage'] : 18.00;

$tax_amount = isset($quote['tax_amount']) ? (float)$quote['tax_amount'] : 0.0;
if (!isset($quote['tax_amount']) && $subtotal > 0 && $tax_percent > 0) {
    $tax_amount = ($subtotal * $tax_percent) / 100;
}

$est_total = (float)($quote['estimated_total'] ?? 0);
if ($est_total <= 0) {
    $est_total = $subtotal + $tax_amount;
}

$pricing = [
    'base_area_cost'      => round($base_cost, 2),
    'services_total'      => round($services_total, 2),
    'material_multiplier' => $material_multiplier,
    'subtotal'            => round($subtotal, 2),
    'tax_amount'          => round($tax_amount, 2),
    'estimated_total'     => round($est_total, 2)
];

$page_title = 'Quotation ' . htmlspecialchars($quote['quote_number']) . ' — Creative Touch Interiors';
$st = getQuoteStatusInfo($quote['status']);
$waText = urlencode("Hello Creative Touch Interiors, I am viewing quotation " . $quote['quote_number'] . " for " . ucwords(str_replace('_', ' ', $quote['project_type'])) . ".");
?>
<?php include 'includes/header.php'; ?>

<style>
.quote-view-hero {
    background: radial-gradient(circle at 50% 20%, #eff6ff 0%, #F8F7F4 85%);
    color: #0f172a;
    padding: 3.5rem 0 2.5rem;
    position: relative;
    border-bottom: 1px solid rgba(0, 87, 255, 0.1);
}
.quote-card-box {
    background: rgba(255, 255, 255, 0.76);
    backdrop-filter: blur(28px) saturate(190%) contrast(92%);
    -webkit-backdrop-filter: blur(28px) saturate(190%) contrast(92%);
    border-radius: var(--radius-xl, 22px);
    border: 1px solid rgba(255, 255, 255, 0.88);
    box-shadow: 0 16px 42px rgba(0, 40, 120, 0.07), 
                0 3px 10px rgba(0, 87, 255, 0.03),
                inset 0 1.5px 2px rgba(255, 255, 255, 0.95),
                inset 0 -1.5px 3px rgba(0, 87, 255, 0.04);
    padding: 2.25rem;
    margin-bottom: 1.75rem;
    position: relative;
    color: #0f172a;
    transition: transform 0.4s var(--ease-liquid, ease), box-shadow 0.4s ease;
    overflow: hidden;
}
.quote-card-box:hover {
    transform: translateY(-4px);
    box-shadow: 0 24px 55px rgba(0, 87, 255, 0.16), inset 0 2px 2px #ffffff;
}
.glass-summary-card {
    background: rgba(255, 255, 255, 0.80);
    backdrop-filter: blur(32px) saturate(200%) contrast(90%);
    -webkit-backdrop-filter: blur(32px) saturate(200%) contrast(90%);
    border-radius: var(--radius-xl, 24px);
    border: 1px solid rgba(255, 255, 255, 0.92);
    box-shadow: 0 22px 55px rgba(0, 40, 120, 0.10), 
                0 6px 18px rgba(0, 87, 255, 0.05),
                inset 0 2px 2px rgba(255, 255, 255, 0.98),
                inset 0 -2px 4px rgba(0, 87, 255, 0.04);
    padding: 2.25rem;
    position: sticky;
    top: 6.5rem;
    overflow: hidden;
}
.quote-progress-bar {
    display: flex;
    justify-content: space-between;
    position: relative;
    margin: 2rem 0 1rem;
}
.quote-progress-bar::before {
    content: '';
    position: absolute;
    top: 18px;
    left: 20px;
    right: 20px;
    height: 2px;
    background: #e2e8f0;
    z-index: 1;
}
.quote-step {
    position: relative;
    z-index: 2;
    text-align: center;
    flex: 1;
}
.quote-step-dot {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #ffffff;
    border: 2px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: #64748b;
    transition: var(--transition-fast);
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}
.quote-step.active .quote-step-dot {
    background: #0057FF;
    border-color: #0057FF;
    color: #ffffff;
    box-shadow: 0 0 18px rgba(0, 87, 255, 0.45);
}
.quote-step.completed .quote-step-dot {
    background: #0057FF;
    border-color: #0057FF;
    color: #ffffff;
}
.quote-step-label {
    font-size: 0.725rem;
    font-weight: 500;
    color: #64748b;
    letter-spacing: 0.02em;
}
.quote-step.active .quote-step-label {
    color: #0057FF;
    font-weight: 700;
}
.quote-step.completed .quote-step-label {
    color: #0057FF;
    font-weight: 600;
}
.spec-tile {
    background: rgba(255, 255, 255, 0.75);
    padding: 1.25rem;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.9);
    box-shadow: 0 4px 14px rgba(0, 40, 120, 0.03);
    transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
    color: #0f172a;
}
.spec-tile:hover {
    border-color: rgba(0, 87, 255, 0.3);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 87, 255, 0.08);
}
.spec-tile-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(0, 87, 255, 0.08);
    color: #0057FF;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.65rem;
    border: 1px solid rgba(0, 87, 255, 0.15);
}

@media print {
    .header, .footer, .btn, .no-print {
        display: none !important;
    }
    .quote-card-box, .glass-summary-card {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin-bottom: 1.5rem !important;
        background: transparent !important;
    }
    body {
        background: white !important;
        color: #111111 !important;
        font-size: 11pt !important;
    }
    .print-only-header {
        display: block !important;
        border-bottom: 2px solid #111111;
        padding-bottom: 1rem;
        margin-bottom: 1.5rem;
    }
}
@media screen {
    .print-only-header {
        display: none;
    }
}
</style>

<!-- Print Only Formal Header -->
<div class="print-only-header container">
    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-family: var(--font-heading); font-size: 1.8rem; margin: 0; color: #111111; letter-spacing: 0.02em;">CREATIVE TOUCH INTERIORS</h1>
            <p style="margin: 0.25rem 0 0; color: #666666; font-size: 0.85rem;">Architectural &amp; Bespoke Interior Design Studio</p>
            <p style="margin: 0; color: #666666; font-size: 0.8rem;">City Centre, VIP Road, Surat, Gujarat • +91 93168 56961</p>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 1.25rem; font-weight: 700; font-family: monospace; color: #111111;">
                <?php echo htmlspecialchars($quote['quote_number']); ?>
            </div>
            <div style="font-size: 0.85rem; color: #666666;">
                Date: <?php echo date('d M Y', strtotime($quote['created_at'])); ?>
            </div>
        </div>
    </div>
</div>

<!-- Hero Banner -->
<div class="quote-view-hero no-print">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; flex-wrap: wrap;">
                    <span style="background: rgba(0, 87, 255, 0.08); color: #0057FF; padding: 0.35rem 0.85rem; border-radius: 6px; font-family: monospace; font-size: 0.9rem; font-weight: 700; border: 1px solid rgba(0, 87, 255, 0.2); letter-spacing: 0.05em;">
                        <?php echo htmlspecialchars($quote['quote_number']); ?>
                    </span>
                    <span class="badge" style="background: <?php echo $st['bg']; ?>; color: <?php echo $st['color']; ?>; border: 1px solid <?php echo $st['border']; ?>; font-weight: 600; font-size: 0.775rem; padding: 0.35rem 0.85rem; display: inline-flex; align-items: center; gap: 0.4rem; border-radius: 20px;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor;"></span>
                        <?php echo $st['label']; ?>
                    </span>
                </div>
                <h1 style="font-family: var(--font-heading); font-size: 2.25rem; color: #0f172a; margin: 0 0 0.4rem; font-weight: 700; letter-spacing: -0.01em;">
                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $quote['project_type']))); ?> Proposal
                </h1>
                <p style="color: #64748b; font-size: 0.875rem; margin: 0;">
                    Submitted on <?php echo date('l, F j, Y \a\t h:i A', strtotime($quote['created_at'])); ?> • Prepared for <strong style="color: #0f172a;"><?php echo htmlspecialchars($quote['name']); ?></strong>
                </p>
            </div>

            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <?php if ($quote['status'] === 'rejected'): ?>
                    <a href="consultation.php" class="btn btn-primary" style="background: #0057FF; border: none; color: #ffffff; font-weight: 600; box-shadow: 0 4px 15px rgba(0, 87, 255, 0.3);">
                        Submit New Request
                    </a>
                <?php endif; ?>
                <a href="quote_pdf.php?id=<?php echo $quote['id']; ?>" target="_blank" class="btn btn-primary" style="background: #0057FF; border: none; color: #ffffff; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 600; box-shadow: 0 4px 15px rgba(0, 87, 255, 0.3);">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    Download PDF
                </a>
                <button type="button" onclick="window.print()" class="btn btn-outline" style="border-color: #cbd5e1; color: #475569; background: #ffffff;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.35rem;"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                    Print
                </button>
                <a href="profile.php#quotes" class="btn btn-outline" style="border-color: #cbd5e1; color: #64748b; background: #ffffff;">
                    &larr; Client Portal
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Main Body -->
<div style="background: #F8F7F4; padding: 3rem 0 5rem; min-height: 80vh;">
    <div class="container">

        <!-- Stage Progress Indicator (No Print) -->
        <?php if ($quote['status'] === 'rejected'): ?>
            <div class="quote-card-box no-print" style="padding: 1.75rem 2rem; border-left: 4px solid #ef4444; background: rgba(254, 242, 242, 0.9);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.25rem;">
                    <div style="display: flex; align-items: flex-start; gap: 1.15rem;">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                        </div>
                        <div>
                            <h3 style="font-family: var(--font-heading); font-size: 1.2rem; font-weight: 600; color: #991b1b; margin: 0 0 0.35rem;">
                                Quotation Request Concluded
                            </h3>
                            <p style="color: #b91c1c; font-size: 0.875rem; margin: 0 0 0.6rem; max-width: 680px; line-height: 1.5;">
                                This request has concluded. If your project timelines or space requirements have evolved, our architectural team is ready to prepare a new consultation for you.
                            </p>
                            <div style="font-size: 0.825rem; color: #7f1d1d; display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;">
                                <span>Direct Studio Line: <strong>+91 93168 56961</strong></span>
                                <span><a href="https://wa.me/919316856961" target="_blank" style="color: #15803d; font-weight: 600; text-decoration: underline;">Chat on WhatsApp</a></span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <a href="consultation.php" class="btn btn-primary" style="background: #0057FF; font-weight: 600;">
                            Submit New Request &rarr;
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="quote-card-box no-print" style="padding: 1.75rem 2rem;">
                <div style="font-weight: 600; color: #0f172a; font-size: 1rem; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-family: var(--font-heading); font-size: 1.15rem; color: #0f172a;">Project Progression Stage</span>
                    <span style="font-size: 0.775rem; color: #0057FF; font-weight: 600; background: rgba(0, 87, 255, 0.08); padding: 0.3rem 0.85rem; border-radius: 9999px; border: 1px solid rgba(0, 87, 255, 0.2);">
                        Status: <?php echo $st['label']; ?>
                    </span>
                </div>

                <?php
                $stages = [
                    'new' => '1. Received',
                    'under_review' => '2. Under Review',
                    'site_visit_scheduled' => '3. Site Visit',
                    'estimating' => '4. Estimating',
                    'quote_sent' => '5. Proposal Ready',
                    'approved' => '6. Approved',
                    'in_progress' => '7. In Execution',
                    'completed' => '8. Completed'
                ];
                $stage_aliases = [
                    'contacted' => 'under_review',
                    'estimation_prepared' => 'estimating',
                    'project_started' => 'in_progress'
                ];
                $current_stage = $stage_aliases[$quote['status']] ?? $quote['status'];
                $stage_keys = array_keys($stages);
                $current_idx = array_search($current_stage, $stage_keys);
                if ($current_idx === false) $current_idx = 0;
                ?>

                <div class="quote-progress-bar">
                    <?php foreach ($stages as $key => $label): 
                        $idx = array_search($key, $stage_keys);
                        $step_class = '';
                        if ($idx < $current_idx) $step_class = 'completed';
                        elseif ($idx === $current_idx) $step_class = 'active';
                    ?>
                        <div class="quote-step <?php echo $step_class; ?>">
                            <div class="quote-step-dot">
                                <?php if ($idx < $current_idx): ?>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <?php else: ?>
                                    <?php echo ($idx + 1); ?>
                                <?php endif; ?>
                            </div>
                            <div class="quote-step-label"><?php echo $label; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-3" style="grid-template-columns: 2fr 1fr; gap: 2rem; align-items: flex-start;">
            
            <!-- LEFT / MAIN AREA: Project Specifications & Schedules -->
            <div>

                <!-- Site Visit Alert if set -->
                <?php if (!empty($quote['site_visit_date'])): ?>
                    <div class="quote-card-box" style="border-left: 4px solid #0057FF; background: rgba(239, 246, 255, 0.9);">
                        <div style="display: flex; align-items: flex-start; gap: 1.25rem;">
                            <div style="width: 42px; height: 42px; border-radius: 10px; background: #FFFFFF; color: #0057FF; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0, 87, 255, 0.15);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            </div>
                            <div style="flex: 1;">
                                <h3 style="font-family: var(--font-heading); color: #0f172a; font-size: 1.15rem; margin: 0 0 0.35rem; font-weight: 600;">
                                    Site Consultation &amp; Inspection Scheduled
                                </h3>
                                <p style="color: #475569; font-size: 0.885rem; margin: 0 0 0.5rem;">
                                    Our lead interior architect will inspect your property on 
                                    <strong><?php echo date('l, F j, Y', strtotime($quote['site_visit_date'])); ?></strong>
                                    <?php if (!empty($quote['site_visit_time']) && $quote['site_visit_time'] != '00:00:00'): ?>
                                        at <strong><?php echo date('h:i A', strtotime($quote['site_visit_time'])); ?></strong>
                                    <?php endif; ?>.
                                </p>
                                <?php if (!empty($quote['site_visit_notes'])): ?>
                                    <div style="background: #FFFFFF; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.825rem; color: #334155; border: 1px solid rgba(0, 87, 255, 0.12);">
                                        <strong>Designer Visit Notes:</strong> <?php echo nl2br(htmlspecialchars($quote['site_visit_notes'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Project Specifications -->
                <div class="quote-card-box">
                    <h2 style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 600; color: #0f172a; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.65rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0057FF" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                        Project Specifications
                    </h2>

                    <div class="grid grid-3" style="gap: 1rem; margin-bottom: 1.5rem;">
                        <div class="spec-tile">
                            <div class="spec-tile-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                            </div>
                            <div style="font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;">Property Type</div>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #0f172a; margin-top: 0.2rem; text-transform: capitalize;">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $quote['property_type']))); ?>
                            </div>
                        </div>

                        <div class="spec-tile">
                            <div class="spec-tile-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12h20M2 12l5-5M2 12l5 5M22 12l-5-5M22 12l5 5"></path></svg>
                            </div>
                            <div style="font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;">Carpet Area</div>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #0f172a; margin-top: 0.2rem;">
                                <?php echo number_format($quote['carpet_area']); ?> sq. ft.
                            </div>
                        </div>

                        <div class="spec-tile">
                            <div class="spec-tile-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon></svg>
                            </div>
                            <div style="font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;">Layout</div>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #0f172a; margin-top: 0.2rem;">
                                <?php echo htmlspecialchars($quote['rooms_count'] ?: 'N/A'); ?> Rooms
                            </div>
                        </div>

                        <div class="spec-tile">
                            <div class="spec-tile-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>
                            </div>
                            <div style="font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;">Style</div>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #0f172a; margin-top: 0.2rem; text-transform: capitalize;">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $quote['design_style'] ?? 'Modern Luxury'))); ?>
                            </div>
                        </div>

                        <div class="spec-tile">
                            <div class="spec-tile-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="6 2 18 2 22 7 12 22 2 7 6 2"></polygon></svg>
                            </div>
                            <div style="font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;">Material Grade</div>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #0f172a; margin-top: 0.2rem; text-transform: capitalize;">
                                <?php echo htmlspecialchars(ucwords($quote['material_quality'] ?? 'Premium')); ?>
                            </div>
                        </div>

                        <div class="spec-tile">
                            <div class="spec-tile-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path></svg>
                            </div>
                            <div style="font-size: 0.7rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;">City</div>
                            <div style="font-size: 0.95rem; font-weight: 600; color: #0f172a; margin-top: 0.2rem;">
                                <?php echo htmlspecialchars($quote['city'] ?: 'Surat, Gujarat'); ?>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($quote['special_requirements'])): ?>
                        <div style="background: rgba(239, 246, 255, 0.7); border-left: 3px solid #0057FF; border-radius: 8px; padding: 1rem 1.25rem;">
                            <div style="font-size: 0.725rem; font-weight: 600; color: #0057FF; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.25rem;">Project Scope Notes</div>
                            <div style="font-size: 0.885rem; color: #334155; line-height: 1.6;">
                                <?php echo nl2br(htmlspecialchars($quote['special_requirements'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Itemized Room Dimensions Schedule -->
                <?php if (($quote['measurement_status'] ?? 'known') === 'unknown'): ?>
                    <div class="quote-card-box" style="border-left: 4px solid #0057FF; background: #FFFBEB;">
                        <h2 style="font-family: var(--font-heading); font-size: 1.15rem; font-weight: 600; color: #92400E; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                            Measurement Status: On-Site Survey Required
                        </h2>
                        <p style="color: #78350F; font-size: 0.885rem; margin: 0; line-height: 1.6;">
                            You indicated that property measurements are not known. A Creative Touch Interiors architectural surveyor will perform an on-site laser survey to document verified dimensions.
                        </p>
                    </div>
                <?php elseif (!empty($quote_rooms)): ?>
                    <div class="quote-card-box">
                        <h2 style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 600; color: #0f172a; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.65rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0057FF" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                            Itemized Room Dimensions Schedule
                        </h2>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                                        <th style="padding: 0.75rem 0.5rem; color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">Room</th>
                                        <th style="padding: 0.75rem 0.5rem; color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; text-align: center;">Length (ft)</th>
                                        <th style="padding: 0.75rem 0.5rem; color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; text-align: center;">Width (ft)</th>
                                        <th style="padding: 0.75rem 0.5rem; color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; text-align: right;">Area (sq.ft)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rTotal = 0;
                                    foreach ($quote_rooms as $rm): 
                                        $rTotal += (float)$rm['area_sqft'];
                                    ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 0.75rem 0.5rem; font-weight: 600; color: #0f172a; font-size: 0.875rem;">
                                                <?php echo htmlspecialchars($rm['room_name']); ?>
                                            </td>
                                            <td style="padding: 0.75rem 0.5rem; text-align: center; color: #475569; font-size: 0.85rem;">
                                                <?php echo number_format((float)$rm['length_ft'], 2); ?>
                                            </td>
                                            <td style="padding: 0.75rem 0.5rem; text-align: center; color: #475569; font-size: 0.85rem;">
                                                <?php echo number_format((float)$rm['width_ft'], 2); ?>
                                            </td>
                                            <td style="padding: 0.75rem 0.5rem; text-align: right; font-weight: 600; color: #0057FF; font-size: 0.875rem;">
                                                <?php echo number_format((float)$rm['area_sqft'], 2); ?> sq.ft
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr style="background: rgba(0, 87, 255, 0.04); font-weight: 700;">
                                        <td colspan="3" style="padding: 0.75rem 0.5rem; text-align: right; color: #0f172a;">Total Measured Carpet Area:</td>
                                        <td style="padding: 0.75rem 0.5rem; text-align: right; color: #0057FF;"><?php echo number_format($rTotal, 2); ?> sq.ft</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Selected Interior Services -->
                <div class="quote-card-box">
                    <h2 style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 600; color: #0f172a; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.65rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0057FF" stroke-width="2"><path d="M20 7h-9"></path><path d="M14 17H5"></path><circle cx="17" cy="17" r="3"></circle><circle cx="7" cy="7" r="3"></circle></svg>
                        Selected Design Modules &amp; Services
                    </h2>

                    <?php if (!empty($selected_services)): ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                                        <th style="padding: 0.75rem 0.5rem; color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;">Module</th>
                                        <th style="padding: 0.75rem 0.5rem; color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;">Category</th>
                                        <th style="padding: 0.75rem 0.5rem; color: #64748b; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; text-align: right;">Base Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($selected_services as $srv): ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 0.85rem 0.5rem;">
                                                <div style="font-weight: 600; color: #0f172a; font-size: 0.875rem;">
                                                    <?php echo htmlspecialchars($srv['service_title'] ?: $srv['service_name']); ?>
                                                </div>
                                                <?php if (!empty($srv['srv_desc'])): ?>
                                                    <div style="font-size: 0.775rem; color: #64748b; margin-top: 0.15rem;">
                                                        <?php echo htmlspecialchars(substr($srv['srv_desc'], 0, 75)) . '...'; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 0.85rem 0.5rem; font-size: 0.8rem; text-transform: capitalize; color: #64748b;">
                                                <?php echo htmlspecialchars($srv['category'] ?: 'Interior'); ?>
                                            </td>
                                            <td style="padding: 0.85rem 0.5rem; text-align: right; font-weight: 600; color: #0f172a; font-family: monospace; font-size: 0.9rem;">
                                                ₹<?php echo number_format($srv['service_rate'] ?? ($srv['service_price'] ?? 0), 2); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: #64748b; margin: 0; font-size: 0.885rem;">Comprehensive turnkey architectural design and interior execution consultation.</p>
                    <?php endif; ?>
                </div>

                <!-- Attached Project Documents / Floor Plans -->
                <?php if (!empty($quote['attachments'])): ?>
                    <div class="quote-card-box">
                        <h2 style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 600; color: #0f172a; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.65rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0057FF" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                            Project Documents &amp; Floor Plans
                        </h2>
                        <div style="display: flex; align-items: center; gap: 1.25rem; background: rgba(255, 255, 255, 0.8); padding: 1.15rem 1.35rem; border-radius: 10px; border: 1px solid rgba(0, 87, 255, 0.12);">
                            <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(0, 87, 255, 0.08); color: #0057FF; display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(0, 87, 255, 0.15);">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                            </div>
                            <div style="flex: 1; overflow: hidden;">
                                <div style="font-weight: 600; color: #0f172a; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; font-size: 0.875rem;"><?php echo htmlspecialchars($quote['attachments']); ?></div>
                                <div style="font-size: 0.775rem; color: #64748b;">Floor Plan / Reference Blueprint</div>
                            </div>
                            <a href="uploads/quotes/<?php echo htmlspecialchars($quote['attachments']); ?>" target="_blank" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.45rem 0.95rem; border-color: rgba(0, 87, 255, 0.25); color: #0057FF;">
                                Open Blueprint &nearr;
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Status Timeline / Audit History -->
                <div class="quote-card-box">
                    <h2 style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 600; color: #0f172a; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.65rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0057FF" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        Timeline &amp; Activity Audit
                    </h2>

                    <div style="display: flex; flex-direction: column; gap: 1.25rem; position: relative; padding-left: 1.5rem; border-left: 2px solid rgba(0, 87, 255, 0.15);">
                        <?php if (!empty($status_history)): ?>
                            <?php foreach ($status_history as $hist): 
                                $hst = getQuoteStatusInfo($hist['new_status']);
                            ?>
                                <div style="position: relative;">
                                    <div style="position: absolute; left: -1.9rem; top: 0.2rem; width: 12px; height: 12px; border-radius: 50%; background: #0057FF; border: 2px solid #FFFFFF; box-shadow: 0 0 8px rgba(0, 87, 255, 0.5);"></div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                                        <div style="font-weight: 600; color: #0f172a; font-size: 0.875rem;">
                                            Status updated to <span style="color: <?php echo $hst['color']; ?>;"><?php echo $hst['label']; ?></span>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #64748b; font-family: monospace;">
                                            <?php echo date('M d, Y h:i A', strtotime($hist['created_at'])); ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($hist['notes'])): ?>
                                        <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">
                                            <?php echo htmlspecialchars($hist['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="position: relative;">
                                <div style="position: absolute; left: -1.9rem; top: 0.2rem; width: 12px; height: 12px; border-radius: 50%; background: #0057FF; border: 2px solid #FFFFFF;"></div>
                                <div style="font-weight: 600; color: #0f172a; font-size: 0.875rem;">Quotation Request Registered</div>
                                <div style="font-size: 0.75rem; color: #64748b; font-family: monospace;"><?php echo date('M d, Y h:i A', strtotime($quote['created_at'])); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- RIGHT AREA: Glass Quotation Summary -->
            <div>
                
                <div class="glass-summary-card">
                    <h3 style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 600; color: #0f172a; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0057FF" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                        Quotation Summary
                    </h3>

                    <?php if ($quote['final_estimate'] > 0): ?>
                        <div style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border-radius: 12px; padding: 1.35rem; margin-bottom: 1.5rem; text-align: center; color: white; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.15);">
                            <div style="font-size: 0.7rem; font-weight: 600; color: #93C5FD; text-transform: uppercase; letter-spacing: 0.08em;">
                                Official Approved Proposal
                            </div>
                            <div style="font-family: var(--font-heading); font-size: 2.15rem; font-weight: 700; color: #FFFFFF; line-height: 1.2; margin: 0.4rem 0;">
                                ₹<?php echo number_format($quote['final_estimate'], 2); ?>
                            </div>
                            <div style="font-size: 0.75rem; color: #94A3B8;">
                                Comprehensive turnkey proposal with architecture &amp; site supervision
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Itemized Formula Breakdown -->
                    <div style="display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.875rem; border-bottom: 1px solid rgba(0, 87, 255, 0.1); padding-bottom: 1.25rem; margin-bottom: 1.25rem;">
                        
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #64748b;">Base Floor Work (<?php echo number_format($quote['carpet_area']); ?> sq.ft):</span>
                            <span style="font-weight: 600; color: #0f172a; font-family: monospace;">₹<?php echo number_format($pricing['base_area_cost'], 2); ?></span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #64748b;">Selected Modules:</span>
                            <span style="font-weight: 600; color: #0f172a; font-family: monospace;">₹<?php echo number_format($pricing['services_total'], 2); ?></span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #64748b;">Material Multiplier:</span>
                            <span style="font-weight: 600; color: #0f172a; font-family: monospace;"><?php echo $pricing['material_multiplier']; ?>x</span>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #64748b;">Subtotal:</span>
                            <span style="font-weight: 600; color: #0f172a; font-family: monospace;">₹<?php echo number_format($pricing['subtotal'], 2); ?></span>
                        </div>

                        <!-- GST (18%) strictly clean, standard typography with NO red circle, NO marker, NO annotation, NO scribble, NO unwanted highlight -->
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #64748b;">GST (18%):</span>
                            <span style="font-weight: 600; color: #0f172a; font-family: monospace;">₹<?php echo number_format($pricing['tax_amount'], 2); ?></span>
                        </div>

                    </div>

                    <!-- Total Estimated -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding: 0.75rem 1rem; background: rgba(0, 87, 255, 0.05); border-radius: 10px; border: 1px solid rgba(0, 87, 255, 0.1);">
                        <span style="font-size: 0.95rem; font-weight: 600; color: #0f172a;">Estimated Total:</span>
                        <span style="font-family: var(--font-heading); font-size: 1.6rem; font-weight: 700; color: #0057FF;">
                            ₹<?php echo number_format($quote['estimated_total'], 2); ?>
                        </span>
                    </div>

                    <!-- Designer Notes if provided -->
                    <?php if (!empty($quote['designer_notes'])): ?>
                        <div style="background: rgba(239, 246, 255, 0.7); border-left: 3px solid #0057FF; padding: 0.75rem 0.85rem; border-radius: 8px; font-size: 0.8rem; margin-bottom: 1.25rem;">
                            <strong style="color: #0f172a; display: block; margin-bottom: 0.2rem;">Architect Notes:</strong>
                            <span style="color: #334155;"><?php echo nl2br(htmlspecialchars($quote['designer_notes'])); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;" class="no-print">
                        <a href="https://wa.me/919316856961?text=<?php echo $waText; ?>" target="_blank" class="btn" style="background: #16a34a; border-color: #16a34a; color: white; justify-content: center; font-weight: 600; font-size: 0.875rem; border-radius: 10px; box-shadow: 0 4px 15px rgba(22, 163, 74, 0.25);">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 0.4rem;"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.669-.699c.983.538 1.838.82 2.791.82 3.181 0 5.767-2.586 5.768-5.766 0-3.18-2.587-5.766-5.768-5.766zm9.969 5.766c0 5.514-4.486 10-10 10-1.745 0-3.385-.45-4.819-1.237l-5.181 1.356 1.381-5.05c-.886-1.503-1.381-3.252-1.381-5.069 0-5.514 4.486-10 10-10s10 4.486 10 10z"/></svg>
                            Discuss on WhatsApp
                        </a>

                        <a href="tel:+919316856961" class="btn btn-outline" style="justify-content: center; font-weight: 600; font-size: 0.875rem; border-radius: 10px; border-color: rgba(0, 87, 255, 0.25); color: #0057FF; background: #ffffff;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.4rem;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                            Call Studio: +91 93168 56961
                        </a>

                        <button type="button" onclick="window.print()" class="btn btn-outline" style="justify-content: center; font-size: 0.875rem; border-radius: 10px; border-color: #cbd5e1; color: #475569; background: #ffffff;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.4rem;"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                            Print Quotation PDF
                        </button>
                    </div>

                    <!-- Client Information -->
                    <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid rgba(0, 87, 255, 0.1); font-size: 0.825rem; color: #64748b; line-height: 1.7;">
                        <div><strong style="color: #0f172a;">Client:</strong> <?php echo htmlspecialchars($quote['name']); ?></div>
                        <div><strong style="color: #0f172a;">Email:</strong> <?php echo htmlspecialchars($quote['email']); ?></div>
                        <div><strong style="color: #0f172a;">Phone:</strong> <?php echo htmlspecialchars($quote['phone']); ?></div>
                        <div><strong style="color: #0f172a;">Location:</strong> <?php echo htmlspecialchars($quote['address'] ? $quote['address'] . ', ' . $quote['city'] : $quote['city']); ?></div>
                    </div>

                </div>

            </div>

        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
