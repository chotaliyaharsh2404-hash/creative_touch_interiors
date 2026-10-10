<?php
require_once '../includes/config.php';

// Enforce admin authentication and prevent caching
requireAdminLogin('login.php');

$success = '';
$error = '';

// Handle quick actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = "Security token expired. Please reload and try again.";
    } elseif (isset($_POST['action'])) {
        
        // 1. Quick Status Update
        if ($_POST['action'] === 'quick_status') {
            $quote_id = (int)$_POST['id'];
            $new_status = sanitize($_POST['status'] ?? 'new');
            $comment = sanitize($_POST['comment'] ?? 'Status updated from admin quotes dashboard.');

            // Fetch previous status
            $prevStmt = $conn->prepare("SELECT status FROM quote_requests WHERE id = ?");
            $prevStmt->bind_param("i", $quote_id);
            $prevStmt->execute();
            $prevRow = $prevStmt->get_result()->fetch_assoc();
            $prev_status = $prevRow['status'] ?? null;

            $updStmt = $conn->prepare("UPDATE quote_requests SET status = ? WHERE id = ?");
            $updStmt->bind_param("si", $new_status, $quote_id);
            if ($updStmt->execute()) {
                recordQuoteStatusHistory($conn, $quote_id, $prev_status, $new_status, 'admin', $_SESSION['admin_name'] ?? 'Admin', $comment);
                $success = "Quote status updated to " . ucfirst(str_replace('_', ' ', $new_status)) . "!";
            } else {
                $error = "Failed to update status: " . $conn->error;
            }
        }

        // 2. Delete Quote
        if ($_POST['action'] === 'delete') {
            if (isReceptionist()) {
                $error = "Access denied: Receptionists cannot delete quote requests.";
            } else {
                $quote_id = (int)$_POST['id'];
                
                // Delete attachment file if exists
                $attStmt = $conn->prepare("SELECT attachment_path FROM quote_requests WHERE id = ?");
                $attStmt->bind_param("i", $quote_id);
                $attStmt->execute();
                $attRow = $attStmt->get_result()->fetch_assoc();
                if (!empty($attRow['attachment_path'])) {
                    $fPath = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $attRow['attachment_path']), '/');
                    if (file_exists($fPath)) {
                        @unlink($fPath);
                    }
                }

                $delStmt = $conn->prepare("DELETE FROM quote_requests WHERE id = ?");
                $delStmt->bind_param("i", $quote_id);
                if ($delStmt->execute()) {
                    $success = "Quote request deleted successfully!";
                } else {
                    $error = "Failed to delete quote.";
                }
            }
        }
    }
}

// Compute Statistics
$stats_res = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as count_new,
    SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as count_review,
    SUM(CASE WHEN status = 'site_visit_scheduled' THEN 1 ELSE 0 END) as count_site_visit,
    SUM(CASE WHEN status = 'estimation_prepared' THEN 1 ELSE 0 END) as count_estimation,
    SUM(CASE WHEN status = 'quote_sent' THEN 1 ELSE 0 END) as count_quote_sent,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as count_approved,
    SUM(CASE WHEN status IN ('in_progress', 'project_started') THEN 1 ELSE 0 END) as count_in_progress,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as count_completed,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as count_rejected
FROM quote_requests");
$stats = $stats_res->fetch_assoc() ?? [];

// Fetch All Quotes with selected services count
$sqlQuotes = "SELECT q.*, 
    (SELECT COUNT(*) FROM quote_services qs WHERE qs.quote_id = q.id) as services_count,
    (SELECT GROUP_CONCAT(qs.service_title SEPARATOR ', ') FROM quote_services qs WHERE qs.quote_id = q.id) as services_list
FROM quote_requests q 
ORDER BY q.created_at DESC";
$quotes_res = $conn->query($sqlQuotes);
$all_quotes = [];
if ($quotes_res) {
    while ($r = $quotes_res->fetch_assoc()) {
        $all_quotes[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote Requests Management - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/spatial-3d.css">
    <style>
        .metric-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.35rem 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1.15rem;
        }
        .metric-icon-box {
            width: 46px;
            height: 46px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .filter-chip {
            padding: 0.45rem 0.95rem;
            border-radius: 9999px;
            font-size: 0.825rem;
            font-weight: 700;
            border: 1px solid var(--border-color);
            background: white;
            color: var(--color-text-muted);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .filter-chip:hover {
            border-color: #2563eb;
            color: #2563eb;
        }
        .filter-chip.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        }
        .quote-ref-tag {
            font-family: monospace;
            font-size: 0.8rem;
            font-weight: 600;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 0.2rem 0.55rem;
            border-radius: var(--radius-sm);
            border: 1px solid #bfdbfe;
            display: inline-block;
        }
        .action-icon-btn {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-sm);
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.85rem;
            transition: var(--transition);
            text-decoration: none;
            color: #475569;
        }
        .action-icon-btn:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #2563eb;
        }
    </style>
</head>
<body style="background: #F8F7F4; color: #0f172a;">
    <div style="display: flex; min-height: 100vh; width: 100%; overflow-x: hidden;">
        
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-content" style="margin-left: 260px; width: calc(100% - 260px); max-width: calc(100% - 260px); min-width: 0; box-sizing: border-box; padding: 2rem 1.75rem;">
            
            <!-- Executive Header with Avatar & Dropdown -->
            <?php include 'includes/header.php'; ?>

            <!-- Header Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-size: 2.15rem; color: #0f172a; font-weight: 800; margin: 0 0 0.35rem; font-family: var(--font-heading); letter-spacing: -0.01em;">
                        Quotations & Estimations
                    </h1>
                    <p style="color: #64748b; font-size: 0.95rem; margin: 0;">
                        Manage client quote requests, prepare itemized cost estimates, schedule site visits, and track workflow.
                    </p>
                </div>
                
                <div style="display: flex; gap: 0.75rem;">
                    <a href="../consultation.php" target="_blank" class="btn btn-outline" style="font-size: 0.875rem; padding: 0.6rem 1.2rem; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; display: inline-flex; align-items: center; gap: 0.5rem;">
                        <span>View Client Quote Form</span>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                    </a>
                </div>
            </div>

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

            <!-- Metrics Hub -->
            <div class="grid grid-4" style="gap: 1.25rem; margin-bottom: 2rem;">
                
                <div class="metric-card" style="border-left: 4px solid #2563eb;">
                    <div class="metric-icon-box" style="background: #eff6ff; color: #2563eb;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a; line-height: 1.1; font-family: var(--font-heading);">
                            <?php echo $stats['total'] ?? 0; ?>
                        </div>
                        <div style="font-size: 0.825rem; color: #64748b; font-weight: 600; margin-top: 0.2rem;">Total Quote Requests</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #ef4444;">
                    <div class="metric-icon-box" style="background: rgba(239, 68, 68, 0.15); color: #f87171;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #f87171; line-height: 1.1; font-family: var(--font-heading);">
                            <?php echo $stats['count_new'] ?? 0; ?>
                        </div>
                        <div style="font-size: 0.825rem; color: #94a3b8; font-weight: 600; margin-top: 0.2rem;">New Inquiries</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #f59e0b;">
                    <div class="metric-icon-box" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #fbbf24; line-height: 1.1; font-family: var(--font-heading);">
                            <?php echo ($stats['count_site_visit'] ?? 0) + ($stats['count_estimation'] ?? 0); ?>
                        </div>
                        <div style="font-size: 0.825rem; color: #94a3b8; font-weight: 600; margin-top: 0.2rem;">Visits & Estimations</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #10b981;">
                    <div class="metric-icon-box" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #34d399; line-height: 1.1; font-family: var(--font-heading);">
                            <?php echo ($stats['count_approved'] ?? 0) + ($stats['count_in_progress'] ?? 0); ?>
                        </div>
                        <div style="font-size: 0.825rem; color: #94a3b8; font-weight: 600; margin-top: 0.2rem;">Approved / In Progress</div>
                    </div>
                </div>

            </div>

            <!-- Main Quotes Table Card -->
            <div class="card" style="padding: 1.25rem 1.5rem; border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); width: 100%; box-sizing: border-box; overflow: hidden;">
                
                <!-- Filter & Search Controls -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
                    
                    <!-- Chips -->
                    <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                        <button type="button" class="filter-chip active" onclick="filterQuotes('all', this)">All (<?php echo count($all_quotes); ?>)</button>
                        <button type="button" class="filter-chip" onclick="filterQuotes('new', this)">New (<?php echo $stats['count_new'] ?? 0; ?>)</button>
                        <button type="button" class="filter-chip" onclick="filterQuotes('under_review', this)">Under Review</button>
                        <button type="button" class="filter-chip" onclick="filterQuotes('site_visit_scheduled', this)">Site Visit</button>
                        <button type="button" class="filter-chip" onclick="filterQuotes('estimation_prepared', this)">Estimated</button>
                        <button type="button" class="filter-chip" onclick="filterQuotes('quote_sent', this)">Quote Sent</button>
                        <button type="button" class="filter-chip" onclick="filterQuotes('approved', this)">Approved</button>
                        <button type="button" class="filter-chip" onclick="filterQuotes('in_progress', this)">In Execution (<?php echo $stats['count_in_progress'] ?? 0; ?>)</button>
                        <button type="button" class="filter-chip" onclick="filterQuotes('completed', this)">Completed (<?php echo $stats['count_completed'] ?? 0; ?>)</button>
                    </div>

                    <!-- Search Input -->
                    <div style="position: relative; min-width: 240px;">
                        <input type="text" id="quoteSearch" oninput="searchQuotes(this.value)" placeholder="Search client, quote #, city..." class="form-control" style="padding-left: 2.35rem; font-size: 0.85rem; border-radius: 9999px;">
                        <span style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #94a3b8; display: flex; align-items: center;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </span>
                    </div>

                </div>

                <!-- Quotes Table Container with Smooth Horizontal Scrolling -->
                <div style="overflow-x: auto; width: 100%; border: 1px solid #e2e8f0; border-radius: var(--radius-md); background: #ffffff;">
                    <table class="table" style="width: 100%; border-collapse: collapse; min-width: 820px; font-size: 0.875rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; background: #f8fafc;">
                                <th style="padding: 0.75rem 0.85rem; color: #475569; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; white-space: nowrap;">Quote ID</th>
                                <th style="padding: 0.75rem 0.85rem; color: #475569; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Customer Information</th>
                                <th style="padding: 0.75rem 0.85rem; color: #475569; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Project Scope & Area</th>
                                <th style="padding: 0.75rem 0.85rem; color: #475569; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Budget</th>
                                <th style="padding: 0.75rem 0.85rem; color: #475569; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; white-space: nowrap;">Estimated Total</th>
                                <th style="padding: 0.75rem 0.85rem; color: #475569; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; text-align: center; white-space: nowrap;">Workflow Status</th>
                                <th style="padding: 0.75rem 0.85rem; color: #475569; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; text-align: right; white-space: nowrap;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="quotesTableBody">
                            <?php if (!empty($all_quotes)): ?>
                                <?php foreach ($all_quotes as $q): 
                                    $statusInfo = getQuoteStatusInfo($q['status']);
                                    $qSearch = strtolower($q['quote_number'] . ' ' . $q['customer_name'] . ' ' . $q['email'] . ' ' . $q['phone'] . ' ' . $q['city'] . ' ' . $q['project_type'] . ' ' . $q['status']);
                                ?>
                                    <tr class="quote-row" 
                                        data-status="<?php echo htmlspecialchars($q['status']); ?>"
                                        data-search="<?php echo htmlspecialchars($qSearch); ?>"
                                        style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                                                                           <!-- Quote ID -->
                                        <td style="padding: 0.75rem 0.85rem; white-space: nowrap; vertical-align: middle;">
                                            <span class="quote-ref-tag" style="font-size: 0.78rem;"><?php echo htmlspecialchars($q['quote_number']); ?></span>
                                            <div style="font-size: 0.725rem; color: #94a3b8; margin-top: 0.2rem;">
                                                <?php echo date('M d, Y', strtotime($q['created_at'])); ?>
                                            </div>
                                        </td>
                                        <!-- Customer Information -->
                                        <td style="padding: 0.75rem 0.85rem; vertical-align: middle;">
                                            <div style="font-weight: 700; color: #0f172a; font-size: 0.885rem; display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;">
                                                 <span><?php echo htmlspecialchars($q['customer_name']); ?></span>
                                                 <?php if (!empty($q['user_id'])): ?>
                                                     <span style="font-size: 0.65rem; color: #059669; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 0.05rem 0.35rem; border-radius: 4px; font-weight: 700;">Verified</span>
                                                 <?php endif; ?>
                                             </div>
                                             <div style="font-size: 0.78rem; color: #2563eb; margin-top: 0.15rem; display: flex; align-items: center; gap: 0.3rem;">
                                                 <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                                 <span><?php echo htmlspecialchars($q['email']); ?></span>
                                             </div>
                                             <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                                                 📞 <?php echo htmlspecialchars($q['phone']); ?> • 📍 <?php echo htmlspecialchars($q['city']); ?>
                                                                              </td>

                                        <!-- Project Scope -->
                                        <td style="padding: 0.75rem 0.85rem; vertical-align: middle;">
                                            <div style="font-weight: 700; color: #0f172a; font-size: 0.885rem; display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;">
                                                <span><?php echo htmlspecialchars($q['project_type']); ?> (<?php echo htmlspecialchars($q['property_type']); ?>)</span>
                                                <?php if (($q['measurement_status'] ?? 'known') === 'unknown'): ?>
                                                    <span style="background: #fef3c7; color: #d97706; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.68rem; font-weight: 700; border: 1px solid #fde68a;">Survey Req.</span>
                                                <?php endif; ?>
                                                <?php if (!empty($q['package_type'])): ?>
                                                    <span style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.68rem; font-weight: 700;"><?php echo ucfirst($q['package_type']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 0.78rem; color: #64748b; margin-top: 0.2rem; display: flex; align-items: center; gap: 0.3rem;">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.3 15.3l-8.6-8.6a2 2 0 0 0-2.8 0L2.7 13.9a2 2 0 0 0 0 2.8l2.6 2.6a2 2 0 0 0 2.8 0l7.2-7.2"></path></svg>
                                                <span>Area: <strong style="color: #0f172a;"><?php echo ($q['measurement_status'] ?? 'known') === 'unknown' ? 'Unknown' : number_format($q['approx_area']) . ' sq.ft'; ?></strong> • <?php echo $q['rooms_count']; ?> BHK</span>
                                            </div>
                                            <div style="font-size: 0.74rem; color: #64748b; margin-top: 0.1rem;">
                                                Style: <?php echo htmlspecialchars($q['design_style'] ?: 'Modern'); ?>
                                            </div>
                                        </td>

                                        <!-- Services & Budget -->
                                        <td style="padding: 0.75rem 0.85rem; vertical-align: middle;">
                                            <div style="font-weight: 700; color: #2563eb; font-size: 0.825rem;">
                                                <?php echo htmlspecialchars($q['budget_range']); ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.15rem;">
                                                <?php if ($q['services_count'] > 0): ?>
                                                    <span style="background: #f1f5f9; border: 1px solid #e2e8f0; color: #334155; padding: 0.12rem 0.45rem; border-radius: 4px; font-weight: 600;">
                                                        <?php echo $q['services_count']; ?> Packages
                                                    </span>
                                                <?php else: ?>
                                                    <span>Turnkey Scope</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Estimated Total -->
                                        <td style="padding: 0.75rem 0.85rem; vertical-align: middle; white-space: nowrap;">
                                            <?php if ((float)$q['estimated_total'] > 0): ?>
                                                <div style="font-weight: 800; color: #10b981; font-size: 0.925rem;">
                                                    ₹<?php echo number_format($q['estimated_total'], 2); ?>
                                                </div>
                                                <div style="font-size: 0.68rem; color: #64748b;">(Incl. GST)</div>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-size: 0.8rem;">Pending Estimate</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Status Badge -->
                                        <td style="padding: 0.75rem 0.85rem; text-align: center; vertical-align: middle; white-space: nowrap;">
                                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; background: <?php echo $statusInfo['bg']; ?>; color: <?php echo $statusInfo['color']; ?>; border: 1px solid <?php echo $statusInfo['border']; ?>;">
                                                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:currentColor;"></span>
                                                <span><?php echo htmlspecialchars($statusInfo['label']); ?></span>
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td style="padding: 0.75rem 0.85rem; text-align: right; vertical-align: middle; white-space: nowrap;">
                                            <div style="display: inline-flex; gap: 0.35rem; align-items: center; justify-content: flex-end;">
                                                
                                                <!-- Open Full Workspace Detail -->
                                                <a href="quote_details.php?id=<?php echo $q['id']; ?>" class="action-icon-btn" title="Open Quote Workspace" style="color: #2563eb; background: #eff6ff; border-color: #bfdbfe; width: auto; height: 30px; padding: 0 0.65rem; gap: 0.3rem; font-weight: 600; font-size: 0.78rem; border-radius: 6px; display: inline-flex; align-items: center;">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                    <span>Manage</span>
                                                </a>

                                                <!-- View/Print PDF Proposal Docket -->
                                                <a href="../quote_pdf.php?id=<?php echo $q['id']; ?>" target="_blank" class="action-icon-btn" title="View PDF Proposal Docket" style="color: #b45309; width: auto; height: 30px; padding: 0 0.55rem; gap: 0.3rem; font-weight: 600; font-size: 0.78rem; background: #fef3c7; border: 1px solid #fde68a; border-radius: 6px; display: inline-flex; align-items: center;">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                                    <span>PDF</span>
                                                </a>

                                                <!-- Delete Quote -->
                                                <?php if (!isReceptionist()): ?>
                                                <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Are you sure you want to permanently delete quote request <?php echo $q['quote_number']; ?>?');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                                                    <button type="submit" class="action-icon-btn" title="Delete Quote Request" style="color: #ef4444; width: 30px; height: 30px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; background: #fef2f2; border: 1px solid #fee2e2; cursor: pointer;">
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                    </button>
                                                </form>
                                                <?php endif; ?>

                                            </div>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 3.5rem; color: var(--color-text-muted);">
                                        No quotation requests found. Client submissions from the website will automatically appear here.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div id="noQuoteResultsMsg" style="display: none; text-align: center; padding: 3rem; color: var(--color-text-muted);">
                    No quotes match the selected filter or search keyword.
                </div>

            </div>
        </div>
    </div>

    <script>
        let currentQuoteFilter = 'all';
        let searchQuoteQuery = '';

        function filterQuotes(filter, btn) {
            currentQuoteFilter = filter;
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            if (btn) btn.classList.add('active');
            applyQuoteFilters();
        }

        function searchQuotes(query) {
            searchQuoteQuery = query.toLowerCase().trim();
            applyQuoteFilters();
        }

        function applyQuoteFilters() {
            const rows = document.querySelectorAll('.quote-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const status = row.getAttribute('data-status') || '';
                const search = row.getAttribute('data-search') || '';

                let matchFilter = false;
                if (currentQuoteFilter === 'all') {
                    matchFilter = true;
                } else if (currentQuoteFilter === 'in_progress') {
                    matchFilter = (status === 'in_progress' || status === 'project_started');
                } else {
                    matchFilter = (status === currentQuoteFilter);
                }

                const matchSearch = searchQuoteQuery === '' || search.includes(searchQuoteQuery);

                if (matchFilter && matchSearch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('noQuoteResultsMsg').style.display = visibleCount === 0 ? 'block' : 'none';
        }
    </script>
    <script src="../js/vendor/three.min.js"></script>
    <script src="../js/vendor/gsap.min.js"></script>
    <script src="../js/spatial-3d.js"></script>
</body>
</html>
