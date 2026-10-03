<?php
require_once '../includes/config.php';

// Enforce admin authentication and prevent caching
requireAdminLogin('login.php');

$quote_id = (int)($_GET['id'] ?? 0);
if ($quote_id <= 0) {
    redirect('quotes.php');
}

$success = '';
$error = '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = "Security token expired. Please reload and try again.";
    } elseif (isset($_POST['action'])) {

        // 1. UPDATE STATUS
        if ($_POST['action'] === 'update_status') {
            $new_status = sanitize($_POST['status'] ?? 'new');
            $comment = sanitize($_POST['status_comment'] ?? 'Status updated by administrator.');

            $prevStmt = $conn->prepare("SELECT status FROM quote_requests WHERE id = ?");
            $prevStmt->bind_param("i", $quote_id);
            $prevStmt->execute();
            $prevRow = $prevStmt->get_result()->fetch_assoc();
            $prev_status = $prevRow['status'] ?? null;

            if ($prev_status !== $new_status) {
                $updStmt = $conn->prepare("UPDATE quote_requests SET status = ? WHERE id = ?");
                $updStmt->bind_param("si", $new_status, $quote_id);
                if ($updStmt->execute()) {
                    recordQuoteStatusHistory($conn, $quote_id, $prev_status, $new_status, 'admin', $_SESSION['admin_name'] ?? 'Admin', $comment);
                    $success = "Workflow status updated to " . ucfirst(str_replace('_', ' ', $new_status)) . "!";
                } else {
                    $error = "Failed to update status.";
                }
            }
        }

        // 2. SCHEDULE SITE VISIT
        if ($_POST['action'] === 'schedule_site_visit') {
            $site_date = !empty($_POST['site_visit_date']) ? sanitize($_POST['site_visit_date']) : null;
            $site_time = !empty($_POST['site_visit_time']) ? sanitize($_POST['site_visit_time']) : null;
            $site_notes = sanitize($_POST['site_visit_notes'] ?? '');

            $svStmt = $conn->prepare("UPDATE quote_requests SET site_visit_date = ?, site_visit_time = ?, site_visit_notes = ?, status = 'site_visit_scheduled' WHERE id = ?");
            $svStmt->bind_param("sssi", $site_date, $site_time, $site_notes, $quote_id);
            if ($svStmt->execute()) {
                recordQuoteStatusHistory($conn, $quote_id, null, 'site_visit_scheduled', 'admin', $_SESSION['admin_name'] ?? 'Admin', "Site visit scheduled for {$site_date} at {$site_time}. Notes: {$site_notes}");

                // Automatically synchronize into consultations table so it appears on the calendar and client profile
                $qInfoStmt = $conn->prepare("SELECT quote_number, customer_name, email, phone, property_type, city FROM quote_requests WHERE id = ?");
                $qInfoStmt->bind_param("i", $quote_id);
                $qInfoStmt->execute();
                $qInfo = $qInfoStmt->get_result()->fetch_assoc();

                if ($qInfo && !empty($site_date) && !empty($site_time)) {
                    $consult_subj = "Site Visit: " . $qInfo['quote_number'] . " (" . ucfirst($qInfo['property_type'] ?? 'Project') . ")";
                    $consult_notes = "Appointment scheduled via Quote #" . $qInfo['quote_number'] . " in " . ($qInfo['city'] ?? 'Location') . ".\nNotes: " . $site_notes;

                    // Check if already created for this quote
                    $chkConsult = $conn->prepare("SELECT id FROM consultations WHERE subject = ? OR notes LIKE ?");
                    $likeQuote = "%" . $qInfo['quote_number'] . "%";
                    $chkConsult->bind_param("ss", $consult_subj, $likeQuote);
                    $chkConsult->execute();
                    $existConsult = $chkConsult->get_result()->fetch_assoc();

                    if ($existConsult) {
                        $updConsult = $conn->prepare("UPDATE consultations SET consultation_date = ?, consultation_time = ?, status = 'confirmed', notes = ? WHERE id = ?");
                        $updConsult->bind_param("sssi", $site_date, $site_time, $consult_notes, $existConsult['id']);
                        $updConsult->execute();
                    } else {
                        $insConsult = $conn->prepare("INSERT INTO consultations (client_name, client_email, client_phone, subject, consultation_date, consultation_time, status, notes) VALUES (?, ?, ?, ?, ?, ?, 'confirmed', ?)");
                        $insConsult->bind_param("sssssss", $qInfo['customer_name'], $qInfo['email'], $qInfo['phone'], $consult_subj, $site_date, $site_time, $consult_notes);
                        $insConsult->execute();
                    }
                }

                $success = "Site visit appointment scheduled and synchronized with Consultations calendar successfully!";
            } else {
                $error = "Failed to schedule site visit.";
            }
        }

        // 3. SAVE COST ESTIMATION BREAKDOWN
        if ($_POST['action'] === 'save_estimation') {
            if (isReceptionist()) {
                $error = "Access denied: Receptionists cannot modify estimation and pricing breakdowns.";
            } else {
                $base_rate = max(0, (float)($_POST['base_rate_per_sqft'] ?? 0));
                $material_cost = max(0, (float)($_POST['material_cost'] ?? 0));
                $service_cost = max(0, (float)($_POST['service_cost'] ?? 0));
                $additional_cost = max(0, (float)($_POST['additional_cost'] ?? 0));
                $package_type = sanitize($_POST['package_type'] ?? 'standard');
                $pkgMultiplier = getPackageMultiplier($package_type);
                $discount_type = sanitize($_POST['discount_type'] ?? 'flat');
                $discount_val = max(0, (float)($_POST['discount_amount'] ?? 0));
                $tax_percent = max(0, (float)($_POST['tax_percentage'] ?? 18.0));
                $estimation_notes = sanitize($_POST['estimation_notes'] ?? '');
                $set_status_prepared = isset($_POST['mark_estimation_prepared']) ? true : false;

                // Fetch approx area for base cost calc
                $areaStmt = $conn->prepare("SELECT approx_area, measurement_status FROM quote_requests WHERE id = ?");
                $areaStmt->bind_param("i", $quote_id);
                $areaStmt->execute();
                $areaRow = $areaStmt->get_result()->fetch_assoc();
                $approx_area = (float)($areaRow['approx_area'] ?? 0);

                // Compute authoritative calculation via quote_engine
                $base_cost = round($approx_area * $base_rate, 2);
                $gross_subtotal = round(($base_cost + $service_cost + $material_cost) * $pkgMultiplier, 2);
                $calc = calculateQuoteGrandTotal($gross_subtotal, $additional_cost, $discount_type, $discount_val, $tax_percent);

                $discount_amount = $calc['discount_amount'];
                $tax_amount = $calc['tax_amount'];
                $estimated_total = $calc['grand_total'];

                $new_status_clause = $set_status_prepared ? ", status = 'estimation_prepared'" : "";

                $estStmt = $conn->prepare("UPDATE quote_requests SET 
                    base_rate_per_sqft = ?, base_cost = ?, service_cost = ?, material_cost = ?, additional_cost = ?,
                    package_type = ?, package_multiplier = ?, discount_type = ?, discount_value = ?, discount_amount = ?,
                    tax_percentage = ?, tax_amount = ?, estimated_total = ?, estimation_notes = ? {$new_status_clause}
                    WHERE id = ?");
                $estStmt->bind_param("dddddsdsdddddsi", 
                    $base_rate, $base_cost, $service_cost, $material_cost, $additional_cost,
                    $package_type, $pkgMultiplier, $discount_type, $discount_val, $discount_amount,
                    $tax_percent, $tax_amount, $estimated_total, $estimation_notes, $quote_id);

                if ($estStmt->execute()) {
                    $histComment = "Estimation calculated via engine ({$package_type} tier, discount: ₹" . number_format($discount_amount, 2) . "). Total: ₹" . number_format($estimated_total, 2) . ($set_status_prepared ? " (Status updated to Estimation Prepared)" : "");
                    recordQuoteStatusHistory($conn, $quote_id, null, $set_status_prepared ? 'estimation_prepared' : 'under_review', 'admin', $_SESSION['admin_name'] ?? 'Admin', $histComment);
                    $success = "Quotation & Estimation calculation saved successfully! Grand Total: " . formatIndianCurrency($estimated_total);
                } else {
                    $error = "Failed to save estimation: " . $conn->error;
                }
            }
        }

        // 4. SAVE INTERNAL ADMIN NOTES
        if ($_POST['action'] === 'save_admin_notes') {
            $admin_notes = sanitize($_POST['admin_notes'] ?? '');
            $nStmt = $conn->prepare("UPDATE quote_requests SET admin_notes = ? WHERE id = ?");
            $nStmt->bind_param("si", $admin_notes, $quote_id);
            if ($nStmt->execute()) {
                $success = "Internal admin notes updated!";
            } else {
                $error = "Failed to update notes.";
            }
        }
    }
}

// Fetch Complete Quote Data
$qStmt = $conn->prepare("SELECT * FROM quote_requests WHERE id = ?");
$qStmt->bind_param("i", $quote_id);
$qStmt->execute();
$quote = $qStmt->get_result()->fetch_assoc();

if (!$quote) {
    redirect('quotes.php');
}

// Fetch Itemized Quote Rooms
$roomsStmt = $conn->prepare("SELECT * FROM quote_rooms WHERE quote_id = ? ORDER BY id ASC");
$roomsStmt->bind_param("i", $quote_id);
$roomsStmt->execute();
$quote_rooms = $roomsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Selected Services
$qsStmt = $conn->prepare("SELECT qs.*, s.category, s.icon, s.price_range 
    FROM quote_services qs 
    LEFT JOIN services s ON qs.service_id = s.id 
    WHERE qs.quote_id = ?");
$qsStmt->bind_param("i", $quote_id);
$qsStmt->execute();
$quote_services = $qsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch Status History Audit Trail
$histStmt = $conn->prepare("SELECT * FROM quote_status_history WHERE quote_id = ? ORDER BY created_at DESC");
$histStmt->bind_param("i", $quote_id);
$histStmt->execute();
$quote_history = $histStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$statusInfo = getQuoteStatusInfo($quote['status']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quote <?php echo htmlspecialchars($quote['quote_number']); ?> - Admin Workspace</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/spatial-3d.css">
    <style>
        .workspace-card {
            background: rgba(14, 18, 26, 0.78) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            background: #ffffff !important;
            border-radius: var(--radius-md) !important;
            border: 1px solid #e2e8f0 !important;
            padding: 1.75rem 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
            margin-bottom: 1.75rem;
            color: #0f172a;
        }
        .workspace-card-title {
            color: #0f172a !important;
        }
        .info-grid-value {
            color: #0f172a !important;
        }
        .workspace-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 1rem;
            margin-bottom: 1.25rem;
        }
        .workspace-card-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
            font-family: var(--font-heading);
        }
        .info-grid-label {
            font-size: 0.725rem;
            color: var(--color-text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 0.2rem;
        }
        .info-grid-value {
            font-size: 0.925rem;
            color: #0f172a;
            font-weight: 600;
        }
        .status-timeline-step {
            display: flex;
            gap: 1rem;
            position: relative;
            padding-bottom: 1.25rem;
        }
        .status-timeline-step::before {
            content: '';
            position: absolute;
            left: 14px;
            top: 28px;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }
        .status-timeline-step:last-child::before {
            display: none;
        }
        .status-dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 600;
            border: 2px solid #3b82f6;
            flex-shrink: 0;
            z-index: 1;
        }
        @media print {
            .admin-sidebar, .no-print {
                display: none !important;
            }
            .admin-content {
                padding: 0 !important;
            }
        }
    </style>
</head>
<body style="background: #F8F7F4; color: #0f172a;">
    <div style="display: flex; min-height: 100vh;">
        
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-content" style="flex: 1; padding: 2.25rem 2.5rem;">
            
            <!-- Breadcrumbs & Top Actions -->
            <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <a href="quotes.php" style="color: #94a3b8; text-decoration: none; font-size: 0.875rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.45rem; transition: color 0.15s ease;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        <span>Back to Quotes List</span>
                    </a>
                    <div style="display: flex; align-items: center; gap: 0.85rem; margin-top: 0.45rem;">
                        <h1 style="font-family: var(--font-heading); font-size: 1.95rem; color: #0f172a; font-weight: 700; margin: 0;">
                            Quote <?php echo htmlspecialchars($quote['quote_number']); ?>
                        </h1>
                        <span style="display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.35rem 0.95rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 700; background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; border: 1px solid <?php echo $statusInfo['border']; ?>;">
                            <span><?php echo $statusInfo['icon']; ?></span>
                            <span><?php echo htmlspecialchars($statusInfo['label']); ?></span>
                        </span>
                    </div>
                </div>

                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <a href="../quote_pdf.php?id=<?php echo $quote_id; ?>" target="_blank" class="btn btn-outline" style="font-size: 0.875rem; padding: 0.6rem 1.15rem; border-color: #bfdbfe; color: #1d4ed8; background: #eff6ff; display: inline-flex; align-items: center; gap: 0.45rem; border-radius: var(--radius-lg); font-weight: 600;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                        </svg>
                        <span>PDF Quotation Docket</span>
                    </a>
                    <button type="button" onclick="window.print()" class="btn btn-outline" style="font-size: 0.875rem; padding: 0.6rem 1.15rem; background: white; border-color: #cbd5e1; color: #334155; display: inline-flex; align-items: center; gap: 0.45rem; border-radius: var(--radius-lg); font-weight: 600;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                            <rect x="6" y="14" width="12" height="8"></rect>
                        </svg>
                        <span>Print Sheet</span>
                    </button>
                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $quote['phone']); ?>?text=<?php echo urlencode("Hello " . $quote['customer_name'] . ", regarding your Creative Touch Interiors quote " . $quote['quote_number'] . "..."); ?>" target="_blank" class="btn btn-outline" style="font-size: 0.875rem; padding: 0.6rem 1.15rem; border-color: #86efac; color: #16a34a; background: #f0fdf4; display: inline-flex; align-items: center; gap: 0.45rem; border-radius: var(--radius-lg); font-weight: 600;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                        </svg>
                        <span>WhatsApp Client</span>
                    </a>
                </div>
            </div>

            <!-- Unknown Measurements Notice -->
            <?php if (($quote['measurement_status'] ?? 'known') === 'unknown'): ?>
                <div style="background: #fffbeb; border: 1.5px solid #fde68a; border-radius: var(--radius-lg); padding: 1.15rem 1.35rem; margin-bottom: 1.5rem; display: flex; align-items: flex-start; gap: 1rem;">
                    <div style="font-size: 1.6rem; line-height: 1;">📐</div>
                    <div>
                        <div style="font-weight: 700; color: #92400e; font-size: 0.95rem;">Measurement Required (Survey Needed)</div>
                        <div style="font-size: 0.85rem; color: #78350f; margin-top: 0.25rem; line-height: 1.45;">
                            The customer marked <strong>"I don't know measurements"</strong> during quotation request submission. No synthetic/fake room areas were assigned. Please schedule an on-site dimension audit with our field architects before finalizing formal contracts.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Alerts -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem; border-radius: var(--radius-lg); font-weight: 500;">
                    ✓ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom: 1.5rem; border-radius: var(--radius-lg); font-weight: 500;">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 380px; gap: 1.75rem; align-items: start;">
                
                <!-- Left Main Workspace Column -->
                <div>
                    
                    <!-- CLIENT & PROPERTY SPECIFICATIONS -->
                    <div class="workspace-card">
                        <div class="workspace-card-header">
                            <h3 class="workspace-card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: #2563eb;">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                <span>Client Profile & Project Dimensions</span>
                            </h3>
                            <span style="font-size: 0.8rem; color: #64748b;">
                                Submitted on <?php echo date('M d, Y h:i A', strtotime($quote['created_at'])); ?>
                            </span>
                        </div>

                        <div class="grid grid-3" style="gap: 1.25rem; margin-bottom: 1.25rem;">
                            <div>
                                <div class="info-grid-label">Full Name</div>
                                <div class="info-grid-value" style="color: #0f172a;"><?php echo htmlspecialchars($quote['customer_name']); ?></div>
                            </div>
                            <div>
                                <div class="info-grid-label">Email Address</div>
                                <div class="info-grid-value" style="color: #2563eb;">
                                    <a href="mailto:<?php echo htmlspecialchars($quote['email']); ?>" style="color: inherit; text-decoration: none;">
                                        <?php echo htmlspecialchars($quote['email']); ?>
                                    </a>
                                </div>
                            </div>
                            <div>
                                <div class="info-grid-label">Mobile Number</div>
                                <div class="info-grid-value" style="color: #0f172a;">
                                    <a href="tel:<?php echo htmlspecialchars($quote['phone']); ?>" style="color: inherit; text-decoration: none;">
                                        <?php echo htmlspecialchars($quote['phone']); ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-3" style="gap: 1.25rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; margin-bottom: 1.25rem;">
                            <div>
                                <div class="info-grid-label">Project Scope</div>
                                <div class="info-grid-value" style="color: #0f172a;"><?php echo htmlspecialchars($quote['project_type']); ?></div>
                            </div>
                            <div>
                                <div class="info-grid-label">Property Type</div>
                                <div class="info-grid-value" style="color: #0f172a;"><?php echo htmlspecialchars($quote['property_type']); ?></div>
                            </div>
                            <div>
                                <div class="info-grid-label">Approx Area</div>
                                <div class="info-grid-value" style="color: #2563eb; font-size: 1.1rem; font-weight: 700;">
                                    <?php echo number_format($quote['approx_area']); ?> sq.ft
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-3" style="gap: 1.25rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                            <div>
                                <div class="info-grid-label">Location / City</div>
                                <div class="info-grid-value" style="color: #0f172a;"><?php echo htmlspecialchars($quote['city']); ?>, <?php echo htmlspecialchars($quote['state']); ?></div>
                            </div>
                            <div>
                                <div class="info-grid-label">Rooms / Floors</div>
                                <div class="info-grid-value" style="color: #0f172a;"><?php echo $quote['rooms_count']; ?> BHK • <?php echo $quote['floors_count']; ?> Floor(s)</div>
                            </div>
                            <div>
                                <div class="info-grid-label">Budget Range</div>
                                <div class="info-grid-value" style="color: #2563eb; font-weight: 700;">
                                    <?php echo htmlspecialchars($quote['budget_range'] ?: 'Flexible / Custom'); ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($quote['special_requirements']) || !empty($quote['additional_notes'])): ?>
                            <div style="margin-top: 1.25rem; padding: 1rem; background: #eff6ff; border-radius: var(--radius-md); border-left: 3px solid #2563eb; border: 1px solid #bfdbfe;">
                                <div class="info-grid-label" style="color: #1e40af;">Client Requirements & Scope Notes:</div>
                                <div style="font-size: 0.9rem; color: #334155; line-height: 1.5; white-space: pre-line;">
                                    <?php echo htmlspecialchars($quote['special_requirements'] ?: $quote['additional_notes']); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($quote['attachment_path'])): ?>
                            <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #2563eb;">
                                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                                    </svg>
                                    <div>
                                        <div style="font-weight: 700; font-size: 0.875rem; color: #0f172a;">
                                            <?php echo htmlspecialchars($quote['attachment_name'] ?: 'Project Reference Attachment'); ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #64748b;">Uploaded with quote request</div>
                                    </div>
                                </div>
                                <a href="../<?php echo htmlspecialchars($quote['attachment_path']); ?>" target="_blank" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.9rem;">
                                    View Attachment ↗
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ITEMIZED ROOM DIMENSIONS & AREA SCHEDULE -->
                    <div class="workspace-card">
                        <div class="workspace-card-header">
                            <h3 class="workspace-card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: #2563eb;">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="3" y1="9" x2="21" y2="9"></line>
                                    <line x1="9" y1="21" x2="9" y2="9"></line>
                                </svg>
                                <span>Room Schedule & Dimensions</span>
                            </h3>
                            <span style="font-size: 0.8rem; background: #eff6ff; color: #1d4ed8; padding: 0.25rem 0.65rem; border-radius: 9999px; font-weight: 700;">
                                <?php echo count($quote_rooms); ?> Room(s) Recorded
                            </span>
                        </div>

                        <?php if (!empty($quote_rooms)): ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                                    <thead>
                                        <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                                            <th style="padding: 0.65rem 0.75rem; color: #64748b; font-weight: 700;">#</th>
                                            <th style="padding: 0.65rem 0.75rem; color: #64748b; font-weight: 700;">Room / Zone</th>
                                            <th style="padding: 0.65rem 0.75rem; color: #64748b; font-weight: 700; text-align: right;">Length (ft)</th>
                                            <th style="padding: 0.65rem 0.75rem; color: #64748b; font-weight: 700; text-align: right;">Width (ft)</th>
                                            <th style="padding: 0.65rem 0.75rem; color: #64748b; font-weight: 700; text-align: right;">Area (sq.ft)</th>
                                            <th style="padding: 0.65rem 0.75rem; color: #64748b; font-weight: 700;">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $totRoomArea = 0;
                                        foreach ($quote_rooms as $rIdx => $rm): 
                                             $totRoomArea += (float)$rm['area_sqft'];
                                        ?>
                                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                                <td style="padding: 0.65rem 0.75rem; color: #64748b;"><?php echo $rIdx + 1; ?></td>
                                                <td style="padding: 0.65rem 0.75rem; font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($rm['room_name']); ?></td>
                                                <td style="padding: 0.65rem 0.75rem; text-align: right; color: #334155;"><?php echo number_format($rm['length_ft'], 1); ?> ft</td>
                                                <td style="padding: 0.65rem 0.75rem; text-align: right; color: #334155;"><?php echo number_format($rm['width_ft'], 1); ?> ft</td>
                                                <td style="padding: 0.65rem 0.75rem; text-align: right; font-weight: 700; color: #2563eb;"><?php echo number_format($rm['area_sqft'], 2); ?> sq.ft</td>
                                                <td style="padding: 0.65rem 0.75rem; color: #64748b; font-size: 0.8rem;"><?php echo htmlspecialchars($rm['notes'] ?? '—'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr style="background: #f8fafc; font-weight: 700; border-top: 1px solid #e2e8f0;">
                                            <td colspan="4" style="padding: 0.75rem; text-align: right; color: #0f172a;">Total Measured Floor Area:</td>
                                            <td style="padding: 0.75rem; text-align: right; color: #2563eb; font-size: 1rem;"><?php echo number_format($totRoomArea, 2); ?> sq.ft</td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div style="padding: 1.5rem; text-align: center; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-md);">
                                <?php if (($quote['measurement_status'] ?? 'known') === 'unknown'): ?>
                                    ⚠️ Client specified "I don't know measurements". Detailed room schedule requires on-site measurement audit.
                                <?php else: ?>
                                    Single total approximate area reported: <strong style="color: #0f172a;"><?php echo number_format($quote['approx_area']); ?> sq.ft</strong>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- SELECTED SERVICES SPECIFICATION -->
                    <div class="workspace-card">
                        <div class="workspace-card-header">
                            <h3 class="workspace-card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);">
                                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                                    <polyline points="2 17 12 22 22 17"></polyline>
                                    <polyline points="2 12 12 17 22 12"></polyline>
                                </svg>
                                <span>Selected Services & Line Items (<?php echo count($quote_services); ?>)</span>
                            </h3>
                        </div>

                        <?php if (!empty($quote_services)): ?>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <?php foreach ($quote_services as $srv): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-lg);">
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <span style="font-size: 1.35rem;"><?php echo htmlspecialchars($srv['icon'] ?? '🏠'); ?></span>
                                            <div>
                                                <div style="font-weight: 700; font-size: 0.925rem; color: #0f172a;">
                                                    <?php echo htmlspecialchars($srv['service_title']); ?>
                                                    <?php if (!empty($srv['room_name'])): ?>
                                                        <span style="background: #e2e8f0; color: #475569; font-size: 0.7rem; padding: 0.1rem 0.4rem; border-radius: 4px; margin-left: 0.4rem;">
                                                            Room: <?php echo htmlspecialchars($srv['room_name']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                                                    Category: <?php echo htmlspecialchars($srv['category'] ?? 'Design Module'); ?>
                                                    <?php if (!empty($srv['service_type'])): ?>
                                                        • Type: <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $srv['service_type']))); ?></strong>
                                                    <?php endif; ?>
                                                    <?php if ((float)($srv['wastage_percent'] ?? 0) > 0): ?>
                                                        • Wastage: <?php echo $srv['wastage_percent']; ?>% (+₹<?php echo number_format($srv['wastage_amount'], 2); ?>)
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-weight: 700; color: #059669; font-size: 0.95rem;">
                                                <?php 
                                                if ((float)($srv['final_service_amount'] ?? 0) > 0) {
                                                    echo '₹' . number_format($srv['final_service_amount'], 2);
                                                } elseif (!empty($srv['price_range'])) {
                                                    echo htmlspecialchars($srv['price_range']);
                                                } else {
                                                    echo '₹' . number_format($srv['service_rate'], 2);
                                                }
                                                ?>
                                            </div>
                                            <div style="font-size: 0.7rem; color: #94a3b8;">
                                                <?php if ((float)($srv['unit_rate'] ?? 0) > 0): ?>
                                                    Rate: ₹<?php echo number_format($srv['unit_rate'], 2); ?>
                                                <?php else: ?>
                                                    Base: ₹<?php echo number_format($srv['service_rate'], 2); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="padding: 1.5rem; text-align: center; color: #64748b; background: #f8fafc; border-radius: var(--radius-md);">
                                Client requested full turnkey quotation without selecting individual module chips.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- DYNAMIC COST ESTIMATION BUILDER -->
                    <div class="workspace-card" style="border: 1.5px solid #bfdbfe; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                        <div class="workspace-card-header" style="border-bottom: 1px solid #e2e8f0;">
                            <h3 class="workspace-card-title" style="color: #1e3a8a;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21.3 15.3l-7.6-7.6a1 1 0 0 0-1.4 0l-7.6 7.6a1 1 0 0 0 0 1.4l7.6 7.6a1 1 0 0 0 1.4 0l7.6-7.6a1 1 0 0 0 0-1.4z"></path>
                                    <path d="m14.5 9.5-5 5"></path>
                                </svg>
                                <span>Authoritative Cost Estimation & Quotation Builder</span>
                            </h3>
                            <span style="font-size: 0.8rem; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 0.25rem 0.75rem; border-radius: 9999px; font-weight: 700;">
                                Auto-Calculated Engine
                            </span>
                        </div>

                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="save_estimation">

                            <!-- Package Tier & Discount Configuration -->
                            <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-weight: 600; color: #334155;">Package Tier (Multiplier)</label>
                                    <select name="package_type" id="est_package_type" class="form-control" onchange="calcAdminEstimate()" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                                        <option value="basic" <?php echo ($quote['package_type'] ?? '') === 'basic' ? 'selected' : ''; ?>>Basic Package (1.00x Base Scope)</option>
                                        <option value="standard" <?php echo ($quote['package_type'] ?? 'standard') === 'standard' ? 'selected' : ''; ?>>Standard Package (1.00x Base Scope)</option>
                                        <option value="premium" <?php echo ($quote['package_type'] ?? '') === 'premium' ? 'selected' : ''; ?>>Premium Package (1.25x Curated Finishes)</option>
                                        <option value="luxury" <?php echo ($quote['package_type'] ?? '') === 'luxury' ? 'selected' : ''; ?>>Luxury Package (1.50x Bespoke Finishes)</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-weight: 600; color: #334155;">Discount Type & Value</label>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <select name="discount_type" id="est_discount_type" class="form-control" style="width: 120px; background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;" onchange="calcAdminEstimate()">
                                            <option value="flat" <?php echo ($quote['discount_type'] ?? 'flat') === 'flat' ? 'selected' : ''; ?>>Flat (₹)</option>
                                            <option value="percentage" <?php echo ($quote['discount_type'] ?? '') === 'percentage' ? 'selected' : ''; ?>>Percent (%)</option>
                                        </select>
                                        <input type="number" step="0.01" name="discount_amount" id="est_discount" class="form-control" value="<?php echo htmlspecialchars($quote['discount_value'] ?? $quote['discount_amount'] ?? '0'); ?>" oninput="calcAdminEstimate()" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-3" style="gap: 1rem; margin-bottom: 1rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-weight: 600; color: #334155;">Base Rate / sq.ft (₹)</label>
                                    <input type="number" step="10" name="base_rate_per_sqft" id="est_rate" class="form-control" value="<?php echo htmlspecialchars($quote['base_rate_per_sqft'] ?: '1200'); ?>" oninput="calcAdminEstimate()" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-weight: 600; color: #334155;">Base Architecture Cost (₹)</label>
                                    <input type="text" id="est_base_cost_display" class="form-control" disabled value="₹<?php echo number_format($quote['base_cost'], 2); ?>" style="background: #f8fafc; border-color: #cbd5e1; color: #0f172a; font-weight: 700;">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-weight: 600; color: #334155;">Services Cost (₹)</label>
                                    <input type="number" step="100" name="service_cost" id="est_service_cost" class="form-control" value="<?php echo htmlspecialchars($quote['service_cost']); ?>" oninput="calcAdminEstimate()" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                                </div>
                            </div>

                            <div class="grid grid-3" style="gap: 1rem; margin-bottom: 1rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-weight: 600; color: #334155;">Material Cost (₹)</label>
                                    <input type="number" step="100" name="material_cost" id="est_material_cost" class="form-control" value="<?php echo htmlspecialchars($quote['material_cost']); ?>" oninput="calcAdminEstimate()" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-weight: 600; color: #334155;">Additional Charges (₹)</label>
                                    <input type="number" step="100" name="additional_cost" id="est_additional_cost" class="form-control" value="<?php echo htmlspecialchars($quote['additional_cost']); ?>" oninput="calcAdminEstimate()" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-weight: 600; color: #334155;">GST / Tax Rate (%)</label>
                                    <input type="number" step="0.5" name="tax_percentage" id="est_tax_percent" class="form-control" value="<?php echo htmlspecialchars(isset($quote['tax_percentage']) && $quote['tax_percentage'] !== '' ? $quote['tax_percentage'] : '18'); ?>" oninput="calcAdminEstimate()" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                                </div>
                            </div>

                            <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1.25rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-weight: 600; color: #334155;">Calculated Discount Amount (₹)</label>
                                    <input type="text" id="est_discount_amt_display" class="form-control" disabled value="₹<?php echo number_format($quote['discount_amount'], 2); ?>" style="background: #f8fafc; border-color: #cbd5e1; color: #475569;">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-weight: 600; color: #334155;">Estimated Tax Amount (₹)</label>
                                    <input type="text" id="est_tax_amount_display" class="form-control" disabled value="₹<?php echo number_format($quote['tax_amount'], 2); ?>" style="background: #f8fafc; border-color: #cbd5e1; color: #475569;">
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 1.25rem;">
                                <label class="form-label" style="font-weight: 600; color: #334155;">Estimation Notes & Proposal Summary</label>
                                <textarea name="estimation_notes" class="form-control" rows="3" placeholder="Specify what materials, timeline milestones, or deliverables are included in this estimate..." style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;"><?php echo htmlspecialchars($quote['estimation_notes'] ?? ''); ?></textarea>
                            </div>

                            <!-- Live Grand Total Banner -->
                            <div style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 1px solid #bfdbfe; border-radius: var(--radius-lg); padding: 1.25rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
                                <div>
                                    <div style="font-size: 0.8rem; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: 0.05em;">
                                        Final Estimated Total (Incl. GST)
                                    </div>
                                    <div style="font-size: 1.85rem; font-weight: 900; color: #1d4ed8; font-family: var(--font-heading);" id="est_grand_total_display">
                                        ₹<?php echo number_format($quote['estimated_total'], 2); ?>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.875rem; font-weight: 700; color: #1e293b; cursor: pointer;">
                                        <input type="checkbox" name="mark_estimation_prepared" value="1" <?php echo $quote['status'] === 'estimation_prepared' ? 'checked' : ''; ?> style="accent-color: #2563eb;">
                                        <span>Mark Workflow as 'Estimation Prepared'</span>
                                    </label>
                                </div>
                            </div>

                            <?php if (!isReceptionist()): ?>
                            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                <span>Save & Calculate Quotation Estimate</span>
                            </button>
                            <?php else: ?>
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.85rem 1.25rem; border-radius: var(--radius-md); font-size: 0.85rem; color: #64748b; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #94a3b8;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                <span>View-Only Mode: Receptionists cannot modify estimation and pricing breakdowns.</span>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>

                </div>

                <!-- Right Action & Workflow Column -->
                <div>
                    
                    <!-- WORKFLOW STATUS CONTROLLER -->
                    <div class="workspace-card no-print">
                        <div class="workspace-card-header">
                            <h3 class="workspace-card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);">
                                    <circle cx="12" cy="12" r="4"></circle>
                                    <line x1="1.05" y1="12" x2="7" y2="12"></line>
                                    <line x1="17.01" y1="12" x2="22.96" y2="12"></line>
                                </svg>
                                <span>Workflow Status Manager</span>
                            </h3>
                        </div>

                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="update_status">
                            
                            <div class="form-group" style="margin-bottom: 1rem;">
                                <label class="form-label" style="font-weight: 600;">Select New Status *</label>
                                <select name="status" class="form-control" required style="font-weight: 700; color: #0f172a;">
                                    <option value="new" <?php echo $quote['status'] === 'new' ? 'selected' : ''; ?>>1. New Request</option>
                                    <option value="under_review" <?php echo $quote['status'] === 'under_review' ? 'selected' : ''; ?>>2. Under Review</option>
                                    <option value="contacted" <?php echo $quote['status'] === 'contacted' ? 'selected' : ''; ?>>3. In Discussion / Contacted</option>
                                    <option value="site_visit_scheduled" <?php echo $quote['status'] === 'site_visit_scheduled' ? 'selected' : ''; ?>>4. Site Visit Scheduled</option>
                                    <option value="estimation_prepared" <?php echo $quote['status'] === 'estimation_prepared' ? 'selected' : ''; ?>>5. Estimation Prepared</option>
                                    <option value="quote_sent" <?php echo $quote['status'] === 'quote_sent' ? 'selected' : ''; ?>>6. Quote Sent</option>
                                    <option value="approved" <?php echo $quote['status'] === 'approved' ? 'selected' : ''; ?>>7. Approved by Client</option>
                                    <option value="in_progress" <?php echo ($quote['status'] === 'in_progress' || $quote['status'] === 'project_started') ? 'selected' : ''; ?>>8. In Execution</option>
                                    <option value="completed" <?php echo $quote['status'] === 'completed' ? 'selected' : ''; ?>>9. Completed</option>
                                    <option value="rejected" <?php echo $quote['status'] === 'rejected' ? 'selected' : ''; ?>>10. Closed / Rejected</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 1.25rem;">
                                <label class="form-label" style="font-weight: 600;">Status Transition Note</label>
                                <textarea name="status_comment" class="form-control" rows="2" placeholder="e.g. Sent PDF estimation to client via email/WhatsApp..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="23 4 23 10 17 10"></polyline>
                                    <polyline points="1 20 1 14 7 14"></polyline>
                                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                                </svg>
                                <span>Update Workflow Status</span>
                            </button>
                        </form>
                    </div>

                    <!-- SITE VISIT SCHEDULER -->
                    <div class="workspace-card no-print">
                        <div class="workspace-card-header">
                            <h3 class="workspace-card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <span>Site Visit Scheduler</span>
                            </h3>
                        </div>

                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="schedule_site_visit">

                            <div class="form-group" style="margin-bottom: 0.85rem;">
                                <label class="form-label" style="font-weight: 600;">Appointment Date *</label>
                                <input type="date" name="site_visit_date" class="form-control" required value="<?php echo htmlspecialchars($quote['site_visit_date'] ?? ''); ?>" min="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <div class="form-group" style="margin-bottom: 0.85rem;">
                                <label class="form-label" style="font-weight: 600;">Time Slot</label>
                                <input type="time" name="site_visit_time" class="form-control" value="<?php echo htmlspecialchars($quote['site_visit_time'] ?? '11:00'); ?>">
                            </div>

                            <div class="form-group" style="margin-bottom: 1.25rem;">
                                <label class="form-label" style="font-weight: 600;">Location / Visit Instructions</label>
                                <input type="text" name="site_visit_notes" class="form-control" value="<?php echo htmlspecialchars($quote['site_visit_notes'] ?? ''); ?>" placeholder="Architects attending, gate code...">
                            </div>

                            <button type="submit" class="btn btn-outline" style="width: 100%; border-color: #d97706; color: #d97706; font-weight: 700; padding: 0.65rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <span>Confirm Site Visit</span>
                            </button>
                        </form>
                    </div>

                    <!-- INTERNAL ADMIN NOTES -->
                    <div class="workspace-card no-print">
                        <div class="workspace-card-header">
                            <h3 class="workspace-card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                <span>Private Admin Notes</span>
                            </h3>
                        </div>

                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="save_admin_notes">
                            
                            <div class="form-group" style="margin-bottom: 1rem;">
                                <textarea name="admin_notes" class="form-control" rows="4" placeholder="Confidential staff notes (e.g. client budget flexibility, key decision makers)..."><?php echo htmlspecialchars($quote['admin_notes'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-outline" style="width: 100%; font-size: 0.85rem; font-weight: 600;">
                                Save Notes
                            </button>
                        </form>
                    </div>

                    <!-- STATUS AUDIT TRAIL / HISTORY -->
                    <div class="workspace-card">
                        <div class="workspace-card-header">
                            <h3 class="workspace-card-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <span>Audit Trail & History</span>
                            </h3>
                        </div>

                        <?php if (!empty($quote_history)): ?>
                            <div style="margin-top: 0.5rem;">
                                <?php foreach ($quote_history as $idx => $hist): 
                                    $hInfo = getQuoteStatusInfo($hist['new_status']);
                                ?>
                                    <div class="status-timeline-step">
                                        <div class="status-dot">
                                            <?php echo $hInfo['icon']; ?>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="font-weight: 700; font-size: 0.875rem; color: #0f172a;">
                                                <?php echo htmlspecialchars($hInfo['label']); ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                                                By <?php echo htmlspecialchars($hist['changed_by_name'] ?: 'Admin'); ?> • <?php echo date('M d, Y h:i A', strtotime($hist['created_at'])); ?>
                                            </div>
                                            <?php if (!empty($hist['comment'])): ?>
                                                <div style="font-size: 0.8rem; color: #334155; background: #f8fafc; padding: 0.4rem 0.65rem; border-radius: 4px; margin-top: 0.35rem; border: 1px solid #e2e8f0;">
                                                    <?php echo htmlspecialchars($hist['comment']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="color: #94a3b8; font-size: 0.85rem; text-align: center; padding: 1rem 0;">
                                No status changes recorded yet.
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

            </div>

        </div>
    </div>

    <script>
    const quoteArea = <?php echo (float)$quote['approx_area']; ?>;

    function calcAdminEstimate() {
        const rate = parseFloat(document.getElementById('est_rate').value) || 0;
        const srvCost = parseFloat(document.getElementById('est_service_cost').value) || 0;
        const matCost = parseFloat(document.getElementById('est_material_cost').value) || 0;
        const addCost = parseFloat(document.getElementById('est_additional_cost').value) || 0;
        const discountVal = parseFloat(document.getElementById('est_discount').value) || 0;
        const discType = document.getElementById('est_discount_type') ? document.getElementById('est_discount_type').value : 'flat';
        const pkgType = document.getElementById('est_package_type') ? document.getElementById('est_package_type').value : 'standard';
        const taxPercent = parseFloat(document.getElementById('est_tax_percent').value) || 0;

        let pkgMult = 1.0;
        if (pkgType === 'premium') pkgMult = 1.25;
        else if (pkgType === 'luxury') pkgMult = 1.50;

        const baseCost = quoteArea * rate;
        const grossSubtotal = (baseCost + srvCost + matCost) * pkgMult;

        let discountAmt = 0;
        if (discType === 'percentage') {
            discountAmt = (grossSubtotal * discountVal) / 100;
        } else {
            discountAmt = discountVal;
        }
        discountAmt = Math.min(discountAmt, grossSubtotal); // Never exceed gross subtotal

        const taxableAmount = Math.max(0, grossSubtotal + addCost - discountAmt);
        const taxAmount = (taxableAmount * taxPercent) / 100;
        const grandTotal = taxableAmount + taxAmount;

        document.getElementById('est_base_cost_display').value = '₹' + baseCost.toLocaleString('en-IN', { maximumFractionDigits: 2 });
        if (document.getElementById('est_discount_amt_display')) {
            document.getElementById('est_discount_amt_display').value = '₹' + discountAmt.toLocaleString('en-IN', { maximumFractionDigits: 2 });
        }
        document.getElementById('est_tax_amount_display').value = '₹' + taxAmount.toLocaleString('en-IN', { maximumFractionDigits: 2 });
        document.getElementById('est_grand_total_display').textContent = '₹' + grandTotal.toLocaleString('en-IN', { maximumFractionDigits: 2 });
    }

    // Trigger initial calculation
    document.addEventListener('DOMContentLoaded', calcAdminEstimate);
    </script>
    <script src="../js/vendor/three.min.js"></script>
    <script src="../js/vendor/gsap.min.js"></script>
    <script src="../js/spatial-3d.js"></script>
</body>
</html>
