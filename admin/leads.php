<?php
require_once '../includes/config.php';

// Enforce admin authentication and prevent caching
requireAdminLogin('login.php');

$success = '';
$error = '';

// Handle Form Actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validate_csrf()) {
        $error = "Security token expired. Please reload and try again.";
    } else if (isset($_POST['action'])) {

        // 1. UPDATE LEAD STATUS
        if ($_POST['action'] == 'update_status') {
            $id = (int)$_POST['id'];
            $status = sanitize($_POST['status'] ?? 'new');
            $stmt = $conn->prepare("UPDATE leads SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
            if ($stmt->execute()) {
                $success = "Lead status updated to " . ucfirst($status) . "!";
            } else {
                $error = "Failed to update lead status.";
            }
        }

        // 2. EDIT LEAD DETAILS
        if ($_POST['action'] == 'edit_lead') {
            $id = (int)$_POST['id'];
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $city = sanitize($_POST['city'] ?? '');
            $project_type = sanitize($_POST['project_type'] ?? 'residential');
            $property_type = sanitize($_POST['property_type'] ?? '');
            $area = sanitize($_POST['area'] ?? '');
            $budget = sanitize($_POST['budget'] ?? '');
            $status = sanitize($_POST['status'] ?? 'new');
            $message = sanitize($_POST['message'] ?? '');

            if (empty($name) || empty($email)) {
                $error = "Client name and email address are required.";
            } else {
                $sql = "UPDATE leads SET name=?, email=?, phone=?, city=?, project_type=?, property_type=?, area=?, budget=?, status=?, message=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssssi", $name, $email, $phone, $city, $project_type, $property_type, $area, $budget, $status, $message, $id);
                if ($stmt->execute()) {
                    $success = "Lead details updated successfully!";
                } else {
                    $error = "Failed to update lead details.";
                }
            }
        }
        
        // 3. DELETE LEAD
        if ($_POST['action'] == 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("DELETE FROM leads WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Lead deleted successfully!";
            } else {
                $error = "Failed to delete lead.";
            }
        }
    }
}

// Fetch stats (strictly project design quote leads)
$total_leads = $conn->query("SELECT COUNT(*) as c FROM leads WHERE project_type != 'general_inquiry' OR project_type IS NULL")->fetch_assoc()['c'] ?? 0;
$new_leads = $conn->query("SELECT COUNT(*) as c FROM leads WHERE status='new' AND (project_type != 'general_inquiry' OR project_type IS NULL)")->fetch_assoc()['c'] ?? 0;
$in_discussion_leads = $conn->query("SELECT COUNT(*) as c FROM leads WHERE status IN ('contacted', 'qualified') AND (project_type != 'general_inquiry' OR project_type IS NULL)")->fetch_assoc()['c'] ?? 0;
$converted_leads = $conn->query("SELECT COUNT(*) as c FROM leads WHERE status='converted' AND (project_type != 'general_inquiry' OR project_type IS NULL)")->fetch_assoc()['c'] ?? 0;

// Fetch all project leads
$leads_res = $conn->query("SELECT * FROM leads WHERE project_type != 'general_inquiry' OR project_type IS NULL ORDER BY created_at DESC");
$all_leads = [];
if ($leads_res) {
    while ($r = $leads_res->fetch_assoc()) {
        $all_leads[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leads Management - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/spatial-3d.css">
    <style>
        .metric-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .metric-icon-box {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .lead-avatar-badge {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            font-weight: 800;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #bfdbfe;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.1);
            flex-shrink: 0;
        }
        .filter-chip {
            padding: 0.45rem 1rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            cursor: pointer;
            transition: var(--transition);
        }
        .filter-chip:hover {
            border-color: #2563eb;
            color: #2563eb;
            background: #eff6ff;
        }
        .filter-chip.active {
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            color: #ffffff;
            border-color: #1d4ed8;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
        }
        .action-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: var(--radius-md);
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            font-size: 0.95rem;
        }
        .action-icon-btn:hover {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #2563eb;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.15);
        }
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: #ffffff;
            border-radius: var(--radius-2xl);
            max-width: 650px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2.25rem;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
            border: 1px solid #bfdbfe;
            position: relative;
            color: #0f172a;
        }

        /* Modal Footer Action Buttons */
        .btn-modal-cancel {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.7rem 1.4rem;
            border-radius: var(--radius-lg);
            font-weight: 700;
            font-size: 0.9rem;
            color: #475569;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-modal-cancel:hover {
            background: #e2e8f0;
            color: #0f172a;
            border-color: #94a3b8;
            transform: translateY(-1px);
        }
        .btn-modal-submit {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.7rem 1.65rem;
            border-radius: var(--radius-lg);
            font-weight: 700;
            font-size: 0.925rem;
            color: #ffffff !important;
            background: #0057FF;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(0, 87, 255, 0.28), 0 2px 6px rgba(15, 23, 42, 0.05);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .btn-modal-submit:hover {
            background: #0046d6 !important;
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(0, 87, 255, 0.38) !important;
        }
    </style>
</head>
<body style="background: #F8F7F4; color: #0f172a;">
    <div style="display: flex; min-height: 100vh;">
        
        <!-- Unified Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content Viewport -->
        <div class="admin-content" style="flex: 1; padding: 2.25rem 2.5rem;">
            
            <!-- Header Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-size: 2.15rem; color: var(--color-text-main); font-weight: 800; margin: 0 0 0.35rem; font-family: var(--font-heading); letter-spacing: -0.01em;">
                        Leads & Inquiries CRM
                    </h1>
                    <p style="color: var(--color-text-muted); font-size: 0.95rem; margin: 0;">
                        Legacy client quotation inquiries and project design requests.
                    </p>
                </div>
                <div>
                    <a href="quotes.php" class="btn btn-primary" style="background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); color: #ffffff; font-weight: 800; border: none; padding: 0.65rem 1.35rem; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                        <span>Switch to Quotations Engine &rarr;</span>
                    </a>
                </div>
            </div>

            <!-- Modern Pipeline Notice Banner -->
            <div style="background: #eff6ff; border: 1.5px solid #bfdbfe; border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 42px; height: 42px; border-radius: 50%; background: #dbeafe; border: 1px solid #93c5fd; color: #1d4ed8; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                        💡
                    </div>
                    <div>
                        <h4 style="font-size: 0.95rem; color: #1e3a8a; margin: 0 0 0.2rem; font-weight: 700;">Unified Quotations & Estimation Workflow Active</h4>
                        <p style="font-size: 0.85rem; color: #334155; margin: 0;">New customer quotation requests are now processed through the primary Quotations CRM with full multi-room mathematics, material multipliers, and PDF generation.</p>
                    </div>
                </div>
                <div>
                    <a href="quotes.php" class="btn btn-outline" style="background: #ffffff; border-color: #93c5fd; color: #2563eb; font-size: 0.825rem; font-weight: 700;">
                        View All Active Quotations
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
                
                <div class="metric-card" style="border-left: 4px solid var(--color-primary);">
                    <div class="metric-icon-box" style="background: #eff6ff; color: #2563eb;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-text-main); line-height: 1.1; font-family: var(--font-heading);"><?php echo $total_leads; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">Total Inquiries</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #ea580c;">
                    <div class="metric-icon-box" style="background: #fff7ed; color: #ea580c;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #ea580c; line-height: 1.1; font-family: var(--font-heading);"><?php echo $new_leads; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">New / Unread Leads</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #9333ea;">
                    <div class="metric-icon-box" style="background: #faf5ff; color: #9333ea;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #9333ea; line-height: 1.1; font-family: var(--font-heading);"><?php echo $in_discussion_leads; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">Active Discussion</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #059669;">
                    <div class="metric-icon-box" style="background: #ecfdf5; color: #059669;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #059669; line-height: 1.1; font-family: var(--font-heading);"><?php echo $converted_leads; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">Signed & Converted</div>
                    </div>
                </div>

            </div>

            <!-- Main CRM Table Card -->
            <div class="card" style="padding: 1.75rem 2rem; border-radius: var(--radius-2xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                
                <!-- Filter & Search Controls -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
                    
                    <!-- Filter Chips -->
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="button" class="filter-chip active" onclick="filterLeads('all', this)">All Leads (<?php echo $total_leads; ?>)</button>
                        <button type="button" class="filter-chip" onclick="filterLeads('new', this)">New (<?php echo $new_leads; ?>)</button>
                        <button type="button" class="filter-chip" onclick="filterLeads('contacted', this)">Contacted</button>
                        <button type="button" class="filter-chip" onclick="filterLeads('qualified', this)">Qualified</button>
                        <button type="button" class="filter-chip" onclick="filterLeads('converted', this)">Converted</button>
                        <button type="button" class="filter-chip" onclick="filterLeads('lost', this)">Lost</button>
                    </div>

                    <!-- Search Input -->
                    <div style="position: relative; min-width: 240px;">
                        <input type="text" id="leadSearch" oninput="searchLeads(this.value)" placeholder="Search client, city, notes..." class="form-control" style="padding-left: 2.35rem; font-size: 0.875rem; border-radius: 9999px;">
                        <span style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #94a3b8; display: flex; align-items: center;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </span>
                    </div>

                </div>

                <!-- Leads Table -->
                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">Lead / Client</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">Contact Info</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">Project Scope</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">Status</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">Requirements</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="leadsTableBody">
                            <?php if (!empty($all_leads)): ?>
                                <?php foreach ($all_leads as $lead): 
                                    $status = strtolower($lead['status'] ?? 'new');
                                    $initials = strtoupper(substr($lead['name'] ?: 'C', 0, 2));
                                ?>
                                    <tr class="lead-row" 
                                        data-status="<?php echo htmlspecialchars($status); ?>"
                                        data-search="<?php echo htmlspecialchars(strtolower($lead['name'] . ' ' . $lead['email'] . ' ' . ($lead['phone'] ?? '') . ' ' . ($lead['city'] ?? '') . ' ' . ($lead['project_type'] ?? '') . ' ' . ($lead['message'] ?? ''))); ?>"
                                        style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                                        
                                        <!-- Client Name & Time -->
                                        <td style="padding: 1rem;">
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div class="lead-avatar-badge">
                                                    <?php echo htmlspecialchars($initials); ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 700; color: var(--color-text-main); font-size: 0.95rem;">
                                                        <?php echo htmlspecialchars($lead['name'] ?: 'Anonymous Client'); ?>
                                                    </div>
                                                    <div style="font-size: 0.775rem; color: var(--color-text-muted);">
                                                        <?php echo !empty($lead['created_at']) ? date('M d, Y h:i A', strtotime($lead['created_at'])) : 'N/A'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Contact Info -->
                                        <td style="padding: 1rem;">
                                            <div style="display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.85rem;">
                                                <a href="mailto:<?php echo htmlspecialchars($lead['email']); ?>" style="color: #2563eb; text-decoration: none; display: flex; align-items: center; gap: 0.35rem; font-weight: 600;">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                                    <?php echo htmlspecialchars($lead['email']); ?>
                                                </a>
                                                <?php if (!empty($lead['phone'])): ?>
                                                    <a href="tel:<?php echo htmlspecialchars($lead['phone']); ?>" style="color: #475569; text-decoration: none; display: flex; align-items: center; gap: 0.35rem;">
                                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                                        <?php echo htmlspecialchars($lead['phone']); ?>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Project Scope & Location -->
                                        <td style="padding: 1rem;">
                                            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                                <span style="font-weight: 700; font-size: 0.85rem; color: var(--color-text-main); text-transform: capitalize; display: flex; align-items: center; gap: 0.35rem;">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                                                    <?php echo htmlspecialchars(str_replace('_', ' ', $lead['project_type'] ?? 'General')); ?>
                                                </span>
                                                <div style="font-size: 0.775rem; color: var(--color-text-muted); display: flex; align-items: center; gap: 0.35rem;">
                                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-accent);"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                                    <span><?php echo htmlspecialchars($lead['city'] ?: 'Not Specified'); ?><?php if (!empty($lead['area'])): ?> • <?php echo htmlspecialchars($lead['area']); ?> sq ft<?php endif; ?></span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Status Inline Switcher -->
                                        <td style="padding: 1rem;">
                                            <form method="POST" style="display: inline-block;">
                                                 <?php echo csrf_field(); ?>
                                                 <input type="hidden" name="action" value="update_status">
                                                 <input type="hidden" name="id" value="<?php echo $lead['id']; ?>">
                                                 
                                                 <select name="status" onchange="this.form.submit()" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; font-weight: 700; border-radius: var(--radius-md); border: 1px solid #cbd5e1; background: <?php 
                                                     echo $status == 'new' ? '#fff7ed' : ($status == 'contacted' ? '#eff6ff' : ($status == 'qualified' ? '#faf5ff' : ($status == 'converted' ? '#ecfdf5' : '#f8fafc'))); 
                                                 ?>; color: <?php 
                                                     echo $status == 'new' ? '#ea580c' : ($status == 'contacted' ? '#2563eb' : ($status == 'qualified' ? '#9333ea' : ($status == 'converted' ? '#059669' : '#64748b'))); 
                                                 ?>; cursor: pointer;">
                                                     <option value="new" <?php echo $status == 'new' ? 'selected' : ''; ?>>New Lead</option>
                                                     <option value="contacted" <?php echo $status == 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                                                     <option value="qualified" <?php echo $status == 'qualified' ? 'selected' : ''; ?>>Qualified</option>
                                                     <option value="converted" <?php echo $status == 'converted' ? 'selected' : ''; ?>>Converted</option>
                                                     <option value="lost" <?php echo $status == 'lost' ? 'selected' : ''; ?>>Lost</option>
                                                 </select>
                                             </form>
                                         </td>

                                        <!-- Message Excerpt -->
                                        <td style="padding: 1rem;">
                                            <div style="font-size: 0.825rem; color: #94a3b8; max-width: 240px; line-height: 1.4;">
                                                <?php echo !empty($lead['message']) ? htmlspecialchars(substr($lead['message'], 0, 75)) . (strlen($lead['message']) > 75 ? '...' : '') : '<span style="color: #64748b;">None provided</span>'; ?>
                                            </div>
                                        </td>

                                        <!-- Actions -->
                                        <td style="padding: 1rem; text-align: right;">
                                            <div style="display: inline-flex; gap: 0.4rem;">
                                                <!-- View / Edit Modal Trigger -->
                                                <button type="button" onclick="openLeadModal(<?php echo htmlspecialchars(json_encode($lead)); ?>)" class="action-icon-btn" title="View / Inspect Lead">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                </button>

                                                <!-- Delete Lead -->
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this lead?');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $lead['id']; ?>">
                                                    <button type="submit" class="action-icon-btn" title="Delete Lead" style="color: #ef4444;">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 3rem; color: var(--color-text-muted);">
                                        No leads recorded yet. Inbound client inquiries will appear here automatically.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div id="noResultsMsg" style="display: none; text-align: center; padding: 3rem; color: var(--color-text-muted);">
                    No leads match your search or filter query.
                </div>

            </div>
        </div>
    </div>

    <!-- INSPECT / EDIT LEAD MODAL -->
    <div id="leadModal" class="modal-overlay">
        <div class="modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h2 style="font-size: 1.45rem; font-weight: 800; color: var(--color-text-main); margin: 0;" id="modal_lead_name_title">
                        Lead Details
                    </h2>
                    <span style="font-size: 0.8rem; color: var(--color-text-muted);" id="modal_lead_timestamp"></span>
                </div>
                <button type="button" onclick="closeLeadModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
            </div>

            <!-- Quick Direct Contact Action Buttons -->
            <div style="display: flex; gap: 0.6rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                <a id="btn_whatsapp" href="#" target="_blank" class="btn btn-outline" style="font-size: 0.825rem; padding: 0.45rem 0.9rem; color: #34d399; border-color: rgba(52, 211, 153, 0.3); background: rgba(52, 211, 153, 0.08); display: inline-flex; align-items: center; gap: 0.45rem; border-radius: 9999px; font-weight: 600;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                    <span>WhatsApp Client</span>
                </a>
                <a id="btn_email" href="#" class="btn btn-outline" style="font-size: 0.825rem; padding: 0.45rem 0.9rem; color: #2563eb; border-color: #bfdbfe; background: #eff6ff; display: inline-flex; align-items: center; gap: 0.45rem; border-radius: 9999px; font-weight: 600;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <span>Send Email</span>
                </a>
                <a id="btn_call" href="#" class="btn btn-outline" style="font-size: 0.825rem; padding: 0.45rem 0.9rem; color: #334155; border-color: #cbd5e1; background: #f8fafc; display: inline-flex; align-items: center; gap: 0.45rem; border-radius: 9999px; font-weight: 600;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <span>Phone Call</span>
                </a>
            </div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit_lead">
                <input type="hidden" name="id" id="modal_lead_id">
                
                <div class="grid grid-2" style="gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Client Name *</label>
                        <input type="text" name="name" id="modal_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" id="modal_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" id="modal_phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">City / Location</label>
                        <input type="text" name="city" id="modal_city" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Project Scope</label>
                        <select name="project_type" id="modal_project_type" class="form-control">
                            <option value="full_home">Complete Full Home Interior</option>
                            <option value="kitchen">Modular Kitchen</option>
                            <option value="bedroom">Luxury Bedroom & Wardrobes</option>
                            <option value="living_room">Living Room & False Ceiling</option>
                            <option value="office">Corporate / Office Workspace</option>
                            <option value="renovation">Renovation & Remodeling</option>
                            <option value="general_inquiry">General Contact Inquiry</option>
                            <option value="residential">Residential Interior</option>
                            <option value="commercial">Commercial Space</option>
                            <option value="retail">Retail / Showroom</option>
                            <option value="villa">Luxury Villa / Bungalow</option>
                            <option value="other">Other Space</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">CRM Pipeline Status</label>
                        <select name="status" id="modal_status" class="form-control">
                            <option value="new">New Lead</option>
                            <option value="contacted">Contacted</option>
                            <option value="qualified">Qualified</option>
                            <option value="converted">Converted</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Carpet Area</label>
                        <input type="text" name="area" id="modal_area" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Budget Estimate</label>
                        <input type="text" name="budget" id="modal_budget" class="form-control">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Client Message / Requirement Scope</label>
                    <textarea name="message" id="modal_message" class="form-control" rows="3"></textarea>
                </div>

                <div style="display: flex; gap: 0.85rem; justify-content: flex-end; align-items: center; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                    <button type="button" onclick="closeLeadModal()" class="btn-modal-cancel">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        <span>Cancel</span>
                    </button>
                    <button type="submit" class="btn-modal-submit">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        <span>Update Lead</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentLeadFilter = 'all';
        let leadSearchQuery = '';

        function filterLeads(status, btn) {
            currentLeadFilter = status;
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            if (btn) btn.classList.add('active');
            applyLeadFilters();
        }

        function searchLeads(query) {
            leadSearchQuery = query.toLowerCase().trim();
            applyLeadFilters();
        }

        function applyLeadFilters() {
            const rows = document.querySelectorAll('.lead-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const status = row.getAttribute('data-status') || '';
                const search = row.getAttribute('data-search') || '';

                let matchStatus = false;
                if (currentLeadFilter === 'all') matchStatus = true;
                else matchStatus = status === currentLeadFilter;

                const matchSearch = leadSearchQuery === '' || search.includes(leadSearchQuery);

                if (matchStatus && matchSearch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('noResultsMsg').style.display = visibleCount === 0 ? 'block' : 'none';
        }

        function openLeadModal(item) {
            document.getElementById('modal_lead_id').value = item.id;
            document.getElementById('modal_name').value = item.name || '';
            document.getElementById('modal_email').value = item.email || '';
            document.getElementById('modal_phone').value = item.phone || '';
            document.getElementById('modal_city').value = item.city || '';
            document.getElementById('modal_project_type').value = item.project_type || 'residential';
            document.getElementById('modal_status').value = item.status || 'new';
            document.getElementById('modal_area').value = item.area || '';
            document.getElementById('modal_budget').value = item.budget || '';
            document.getElementById('modal_message').value = item.message || '';

            document.getElementById('modal_lead_name_title').textContent = item.name ? 'Lead: ' + item.name : 'Lead Details';
            document.getElementById('modal_lead_timestamp').textContent = item.created_at ? 'Submitted on ' + item.created_at : '';

            // Update Quick Action Link URLs
            const cleanPhone = (item.phone || '').replace(/[^0-9]/g, '');
            document.getElementById('btn_whatsapp').href = cleanPhone ? 'https://wa.me/' + cleanPhone : '#';
            document.getElementById('btn_whatsapp').style.display = cleanPhone ? 'inline-flex' : 'none';
            document.getElementById('btn_email').href = item.email ? 'mailto:' + item.email : '#';
            document.getElementById('btn_call').href = cleanPhone ? 'tel:' + cleanPhone : '#';
            document.getElementById('btn_call').style.display = cleanPhone ? 'inline-flex' : 'none';

            document.getElementById('leadModal').classList.add('active');
        }

        function closeLeadModal() {
            document.getElementById('leadModal').classList.remove('active');
        }

        // Close on outside click
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                closeLeadModal();
            }
        });
    </script>
    <script src="../js/vendor/three.min.js"></script>
    <script src="../js/vendor/gsap.min.js"></script>
    <script src="../js/spatial-3d.js"></script>
</body>
</html>
