<?php
require_once '../includes/config.php';

// Enforce admin authentication and prevent caching
requireAdminLogin('login.php');

$success = '';
$error = '';

// Handle Actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validate_csrf()) {
        $error = "Security token expired. Please reload and try again.";
    } else if (isset($_POST['action'])) {
        
        // 1. UPDATE STATUS (New, Read, Replied, Closed)
        if ($_POST['action'] == 'update_status') {
            $id = (int)$_POST['id'];
            $status = sanitize($_POST['status'] ?? 'new');
            $stmt = $conn->prepare("UPDATE contact_inquiries SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
            if ($stmt->execute()) {
                $success = "Inquiry status updated to " . ucfirst($status) . "!";
            } else {
                $error = "Failed to update status.";
            }
        }

        // 2. MARK AS READ / UNREAD
        if ($_POST['action'] == 'toggle_read') {
            $id = (int)$_POST['id'];
            $status = sanitize($_POST['status'] ?? 'read');
            $stmt = $conn->prepare("UPDATE contact_inquiries SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
            if ($stmt->execute()) {
                $success = "Marked as " . ($status == 'read' ? 'Read' : 'Unread') . "!";
            } else {
                $error = "Failed to update read state.";
            }
        }

        // 3. EDIT INQUIRY DETAILS / NOTES
        if ($_POST['action'] == 'edit_inquiry') {
            $id = (int)$_POST['id'];
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $subject = sanitize($_POST['subject'] ?? '');
            $status = sanitize($_POST['status'] ?? 'new');

            if (empty($name) || empty($email) || empty($subject)) {
                $error = "Customer name, email, and subject are required.";
            } else {
                $stmt = $conn->prepare("UPDATE contact_inquiries SET name = ?, email = ?, phone = ?, subject = ?, status = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $name, $email, $phone, $subject, $status, $id);
                if ($stmt->execute()) {
                    $success = "Contact inquiry details updated successfully!";
                } else {
                    $error = "Failed to update inquiry details.";
                }
            }
        }

        // 4. DELETE INQUIRY
        if ($_POST['action'] == 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("DELETE FROM contact_inquiries WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Inquiry message deleted successfully!";
            } else {
                $error = "Failed to delete message.";
            }
        }
    }
}

// Fetch Metrics
$total_inquiries = $conn->query("SELECT COUNT(*) as c FROM contact_inquiries")->fetch_assoc()['c'] ?? 0;
$new_inquiries = $conn->query("SELECT COUNT(*) as c FROM contact_inquiries WHERE status = 'new'")->fetch_assoc()['c'] ?? 0;
$read_inquiries = $conn->query("SELECT COUNT(*) as c FROM contact_inquiries WHERE status = 'read'")->fetch_assoc()['c'] ?? 0;
$replied_inquiries = $conn->query("SELECT COUNT(*) as c FROM contact_inquiries WHERE status = 'replied'")->fetch_assoc()['c'] ?? 0;
$closed_inquiries = $conn->query("SELECT COUNT(*) as c FROM contact_inquiries WHERE status = 'closed'")->fetch_assoc()['c'] ?? 0;

// Fetch All Inquiries
$inquiries_res = $conn->query("SELECT * FROM contact_inquiries ORDER BY created_at DESC");
$all_inquiries = [];
if ($inquiries_res) {
    while ($r = $inquiries_res->fetch_assoc()) {
        $all_inquiries[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Inquiries - Admin Panel</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/spatial-3d.css">
    <style>
        .metric-card {
            background: #ffffff;
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            border: 1px solid #bfdbfe;
            box-shadow: 0 4px 16px -2px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 1.25rem;
            transition: all 0.2s ease;
        }
        .metric-card:hover {
            box-shadow: 0 10px 25px -3px rgba(37, 99, 235, 0.12);
            transform: translateY(-2px);
            border-color: #3b82f6;
        }
        .metric-icon-box {
            width: 52px;
            height: 52px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            flex-shrink: 0;
        }
        .client-avatar-badge {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #eff6ff;
            color: #2563eb;
            font-weight: 800;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1.5px solid #bfdbfe;
            flex-shrink: 0;
            position: relative;
        }
        .unread-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 12px;
            height: 12px;
            background: #2563eb;
            border: 2px solid #ffffff;
            border-radius: 50%;
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
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            user-select: none;
        }
        .filter-chip:hover {
            border-color: #2563eb;
            color: #2563eb;
            background: #eff6ff;
        }
        .filter-chip.active {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            color: #ffffff !important;
            border-color: #1d4ed8;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
            font-weight: 700;
        }
        .filter-chip.active .chip-badge {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }
        .chip-badge {
            background: #f1f5f9;
            color: #475569;
            font-size: 0.725rem;
            font-weight: 700;
            padding: 0.1rem 0.45rem;
            border-radius: 9999px;
        }
        .action-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            font-size: 1rem;
        }
        .action-icon-btn:hover {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #2563eb;
            transform: translateY(-1px);
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
            max-width: 680px;
            width: 100%;
            max-height: 92vh;
            overflow-y: auto;
            padding: 2.25rem;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
            border: 1px solid #bfdbfe;
            position: relative;
            color: #0f172a;
        }
        .inquiry-message-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #2563eb;
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            padding: 1.15rem 1.35rem;
            font-size: 0.925rem;
            color: #1e293b;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
        }
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-family: var(--font-heading); font-size: 2.15rem; color: var(--color-text-main); font-weight: 700; margin: 0 0 0.35rem; display: flex; align-items: center; gap: 0.75rem;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: var(--radius-lg); background: #eff6ff; color: #2563eb;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                <polyline points="22,6 12,13 2,6"></polyline>
                            </svg>
                        </span>
                        <span>Contact Inquiries</span>
                    </h1>
                    <p style="color: var(--color-text-muted); font-size: 0.95rem; margin: 0;">
                        Inbound client messages and general studio inquiries from the Contact Us channel.
                    </p>
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
                
                <div class="metric-card">
                    <div class="metric-icon-box" style="background: #eff6ff; color: #2563eb;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline>
                            <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #1e3a8a; line-height: 1.1; font-family: var(--font-heading);"><?php echo $total_inquiries; ?></div>
                        <div style="font-size: 0.825rem; color: #64748b; font-weight: 600; margin-top: 0.2rem;">Total Inquiries</div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon-box" style="background: #fff7ed; color: #ea580c;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #ea580c; line-height: 1.1; font-family: var(--font-heading);"><?php echo $new_inquiries; ?></div>
                        <div style="font-size: 0.825rem; color: #64748b; font-weight: 600; margin-top: 0.2rem;">New / Unread</div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon-box" style="background: #faf5ff; color: #9333ea;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #9333ea; line-height: 1.1; font-family: var(--font-heading);"><?php echo $replied_inquiries; ?></div>
                        <div style="font-size: 0.825rem; color: #64748b; font-weight: 600; margin-top: 0.2rem;">Replied / Follow-up</div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon-box" style="background: #ecfdf5; color: #10b981;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #10b981; line-height: 1.1; font-family: var(--font-heading);"><?php echo $closed_inquiries; ?></div>
                        <div style="font-size: 0.825rem; color: #64748b; font-weight: 600; margin-top: 0.2rem;">Resolved & Closed</div>
                    </div>
                </div>

            </div>

            <!-- Main Inquiries Table Card -->
            <div class="card" style="padding: 1.75rem 2rem; border-radius: var(--radius-2xl); border: 1px solid #bfdbfe; background: #ffffff; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);">
                
                <!-- Filter & Search Controls -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
                    
                    <!-- Filter Chips -->
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="button" class="filter-chip active" onclick="filterInquiries('all', this)">
                            <span>All Messages</span>
                            <span class="chip-badge"><?php echo $total_inquiries; ?></span>
                        </button>
                        <button type="button" class="filter-chip" onclick="filterInquiries('new', this)">
                            <span>New</span>
                            <span class="chip-badge"><?php echo $new_inquiries; ?></span>
                        </button>
                        <button type="button" class="filter-chip" onclick="filterInquiries('read', this)">
                            <span>Read</span>
                            <span class="chip-badge"><?php echo $read_inquiries; ?></span>
                        </button>
                        <button type="button" class="filter-chip" onclick="filterInquiries('replied', this)">
                            <span>Replied</span>
                            <span class="chip-badge"><?php echo $replied_inquiries; ?></span>
                        </button>
                        <button type="button" class="filter-chip" onclick="filterInquiries('closed', this)">
                            <span>Closed</span>
                            <span class="chip-badge"><?php echo $closed_inquiries; ?></span>
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div style="position: relative; min-width: 260px;">
                        <input type="text" id="inquirySearch" oninput="searchInquiries(this.value)" placeholder="Search name, email, subject, text..." class="form-control" style="padding-left: 2.4rem; font-size: 0.875rem; border-radius: 9999px; background: #f8fafc; border: 1px solid #cbd5e1; color: #0f172a;">
                        <span style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #64748b; display: flex; align-items: center; pointer-events: none;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </span>
                    </div>

                </div>

                <!-- Table -->
                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                                <th style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Customer</th>
                                <th style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Direct Contact</th>
                                <th style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Subject</th>
                                <th style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Status</th>
                                <th style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Message Excerpt</th>
                                <th style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8rem; text-transform: uppercase; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="inquiriesTableBody">
                            <?php if (!empty($all_inquiries)): ?>
                                <?php foreach ($all_inquiries as $item): 
                                    $status = strtolower($item['status'] ?? 'new');
                                    $initials = strtoupper(substr($item['name'] ?: 'C', 0, 2));
                                    $cleanPhone = preg_replace('/[^0-9]/', '', $item['phone'] ?? '');
                                    $is_new = ($status === 'new');
                                ?>
                                    <tr class="inquiry-row" 
                                        data-status="<?php echo htmlspecialchars($status); ?>"
                                        data-search="<?php echo htmlspecialchars(strtolower($item['name'] . ' ' . $item['email'] . ' ' . ($item['phone'] ?? '') . ' ' . $item['subject'] . ' ' . $item['message'])); ?>"
                                        style="border-bottom: 1px solid #f1f5f9; background: <?php echo $is_new ? '#fffbeb' : '#ffffff'; ?>; transition: background 0.15s ease;">
                                        
                                        <!-- Customer Name & Avatar -->
                                        <td style="padding: 1rem;">
                                             <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div class="client-avatar-badge">
                                                    <?php echo htmlspecialchars($initials); ?>
                                                    <?php if ($is_new): ?>
                                                        <span class="unread-dot" title="New Unread Message"></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.95rem;">
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                    </div>
                                                    <div style="font-size: 0.775rem; color: #64748b; margin-top: 0.1rem;">
                                                        <?php echo !empty($item['created_at']) ? date('M d, Y h:i A', strtotime($item['created_at'])) : 'N/A'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Contact Info & Quick Shortcuts -->
                                        <td style="padding: 1rem;">
                                            <div style="display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.85rem;">
                                                <a href="mailto:<?php echo htmlspecialchars($item['email']); ?>?subject=<?php echo urlencode('Re: ' . $item['subject']); ?>" style="color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 600;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                        <polyline points="22,6 12,13 2,6"></polyline>
                                                    </svg>
                                                    <span><?php echo htmlspecialchars($item['email']); ?></span>
                                                </a>
                                                <?php if (!empty($item['phone'])): ?>
                                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                        <a href="tel:<?php echo htmlspecialchars($item['phone']); ?>" style="color: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                                            </svg>
                                                            <span><?php echo htmlspecialchars($item['phone']); ?></span>
                                                        </a>
                                                        <?php if (!empty($cleanPhone)): ?>
                                                            <a href="https://wa.me/<?php echo $cleanPhone; ?>?text=<?php echo urlencode("Hello " . $item['name'] . ", thank you for reaching out to Creative Touch Interiors regarding your inquiry: " . $item['subject']); ?>" target="_blank" title="Chat on WhatsApp" style="color: #10b981; text-decoration: none; display: inline-flex; align-items: center;">
                                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                                                                </svg>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Subject -->
                                        <td style="padding: 1rem;">
                                            <div style="font-weight: 700; font-size: 0.875rem; color: #1e293b; max-width: 220px;">
                                                <?php echo htmlspecialchars($item['subject']); ?>
                                            </div>
                                        </td>

                                        <!-- Status Inline Switcher -->
                                        <td style="padding: 1rem;">
                                            <form method="POST" style="display: inline-block;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                
                                                <select name="status" onchange="this.form.submit()" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; font-weight: 700; border-radius: var(--radius-md); border: 1px solid <?php 
                                                    echo $status == 'new' ? '#fed7aa' : ($status == 'read' ? '#bfdbfe' : ($status == 'replied' ? '#e9d5ff' : '#a7f3d0')); 
                                                ?>; background: <?php 
                                                    echo $status == 'new' ? '#fff7ed' : ($status == 'read' ? '#eff6ff' : ($status == 'replied' ? '#faf5ff' : '#ecfdf5')); 
                                                ?>; color: <?php 
                                                    echo $status == 'new' ? '#c2410c' : ($status == 'read' ? '#1d4ed8' : ($status == 'replied' ? '#7e22ce' : '#047857')); 
                                                ?>; cursor: pointer;">
                                                    <option value="new" <?php echo $status == 'new' ? 'selected' : ''; ?>>New</option>
                                                    <option value="read" <?php echo $status == 'read' ? 'selected' : ''; ?>>Read</option>
                                                    <option value="replied" <?php echo $status == 'replied' ? 'selected' : ''; ?>>Replied</option>
                                                    <option value="closed" <?php echo $status == 'closed' ? 'selected' : ''; ?>>Closed</option>
                                                </select>
                                            </form>
                                        </td>

                                        <!-- Message Excerpt -->
                                        <td style="padding: 1rem;">
                                            <div style="font-size: 0.825rem; color: #64748b; max-width: 240px; line-height: 1.4;">
                                                <?php echo htmlspecialchars(substr($item['message'], 0, 75)) . (strlen($item['message']) > 75 ? '...' : ''); ?>
                                            </div>
                                        </td>

                                        <!-- Actions -->
                                        <td style="padding: 1rem; text-align: right;">
                                            <div style="display: inline-flex; gap: 0.4rem;">
                                                <!-- View / Inspect Modal Trigger -->
                                                <button type="button" onclick="openInquiryModal(<?php echo htmlspecialchars(json_encode($item)); ?>)" class="action-icon-btn" title="View Full Message" style="color: #2563eb;">
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                        <circle cx="12" cy="12" r="3"></circle>
                                                    </svg>
                                                </button>

                                                <!-- Delete -->
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this contact message?');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="action-icon-btn" title="Delete Message" style="color: #ef4444;">
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                            <polyline points="3 6 5 6 21 6"></polyline>
                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                            <line x1="10" y1="11" x2="10" y2="17"></line>
                                                            <line x1="14" y1="11" x2="14" y2="17"></line>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 3rem; color: var(--color-text-muted);">
                                        No contact inquiries received yet. Inbound messages from the Contact Us form will appear here.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div id="noResultsMsg" style="display: none; text-align: center; padding: 3rem; color: var(--color-text-muted);">
                    No contact messages match your search or filter query.
                </div>

            </div>
        </div>
    </div>

    <!-- INSPECT / VIEW INQUIRY MODAL -->
    <div id="inquiryModal" class="modal-overlay">
        <div class="modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h2 style="font-family: var(--font-heading); font-size: 1.45rem; font-weight: 700; color: var(--color-text-main); margin: 0;" id="modal_client_name">
                        Inquiry Message
                    </h2>
                    <span style="font-size: 0.8rem; color: var(--color-text-muted);" id="modal_timestamp"></span>
                </div>
                <button type="button" onclick="closeInquiryModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
            </div>

            <!-- Direct Client Communication Action Bar -->
            <div style="display: flex; gap: 0.6rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                <a id="btn_modal_whatsapp" href="#" target="_blank" class="btn btn-outline" style="font-size: 0.825rem; padding: 0.45rem 0.9rem; color: #10b981; border-color: rgba(16, 185, 129, 0.3); background: #ecfdf5; display: inline-flex; align-items: center; gap: 0.45rem; border-radius: 9999px; font-weight: 600;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                    <span>WhatsApp Customer</span>
                </a>
                <a id="btn_modal_email" href="#" class="btn btn-outline" style="font-size: 0.825rem; padding: 0.45rem 0.9rem; color: #2563eb; border-color: #bfdbfe; background: #eff6ff; display: inline-flex; align-items: center; gap: 0.45rem; border-radius: 9999px; font-weight: 600;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <span>Reply via Email</span>
                </a>
                <a id="btn_modal_call" href="#" class="btn btn-outline" style="font-size: 0.825rem; padding: 0.45rem 0.9rem; color: #475569; border-color: #cbd5e1; background: #f8fafc; display: inline-flex; align-items: center; gap: 0.45rem; border-radius: 9999px; font-weight: 600;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <span>Phone Call</span>
                </a>
            </div>

            <!-- Subject Badge Box -->
            <div style="margin-bottom: 1.25rem; background: #eff6ff; padding: 0.75rem 1rem; border-radius: var(--radius-md); border: 1px solid #bfdbfe;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #1d4ed8; text-transform: uppercase;">Subject:</span>
                <div style="font-size: 1rem; font-weight: 700; color: #1e3a8a; margin-top: 0.15rem;" id="modal_subject_text"></div>
            </div>

            <!-- Full Message Body -->
            <div style="margin-bottom: 1.5rem;">
                <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 0.35rem;">Message Content:</span>
                <div class="inquiry-message-card" id="modal_message_body"></div>
            </div>

            <!-- Edit / Update Form -->
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit_inquiry">
                <input type="hidden" name="id" id="modal_inquiry_id">
                
                <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group">
                        <label class="form-label">Customer Name</label>
                        <input type="text" name="name" id="modal_input_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" id="modal_input_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" id="modal_input_phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Inquiry Status</label>
                        <select name="status" id="modal_input_status" class="form-control">
                            <option value="new">New / Unread</option>
                            <option value="read">Read / Reviewed</option>
                            <option value="replied">Replied / Contacted</option>
                            <option value="closed">Resolved & Closed</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">Subject Line</label>
                        <input type="text" name="subject" id="modal_input_subject" class="form-control" required>
                    </div>
                </div>

                <div style="display: flex; gap: 0.85rem; justify-content: flex-end; align-items: center; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                    <button type="button" onclick="closeInquiryModal()" class="btn-modal-cancel">
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
                        <span>Save Status</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentStatusFilter = 'all';
        let searchQuery = '';

        function filterInquiries(status, btn) {
            currentStatusFilter = status;
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            if (btn) btn.classList.add('active');
            applyFilters();
        }

        function searchInquiries(query) {
            searchQuery = query.toLowerCase().trim();
            applyFilters();
        }

        function applyFilters() {
            const rows = document.querySelectorAll('.inquiry-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const status = row.getAttribute('data-status') || '';
                const search = row.getAttribute('data-search') || '';

                let matchFilter = (currentStatusFilter === 'all') ? true : (status === currentStatusFilter);
                let matchSearch = (searchQuery === '' || search.includes(searchQuery));

                if (matchFilter && matchSearch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('noResultsMsg').style.display = visibleCount === 0 ? 'block' : 'none';
        }

        function openInquiryModal(item) {
            document.getElementById('modal_inquiry_id').value = item.id;
            document.getElementById('modal_client_name').textContent = item.name ? 'Message from ' + item.name : 'Inquiry Message';
            document.getElementById('modal_timestamp').textContent = item.created_at ? 'Received on ' + item.created_at : '';

            document.getElementById('modal_subject_text').textContent = item.subject || 'No Subject';
            document.getElementById('modal_message_body').textContent = item.message || 'No message content provided.';

            document.getElementById('modal_input_name').value = item.name || '';
            document.getElementById('modal_input_email').value = item.email || '';
            document.getElementById('modal_input_phone').value = item.phone || '';
            document.getElementById('modal_input_subject').value = item.subject || '';
            document.getElementById('modal_input_status').value = item.status || 'new';

            // Direct Contact Links
            const cleanPhone = (item.phone || '').replace(/[^0-9]/g, '');
            const encodedWa = encodeURIComponent("Hello " + (item.name || 'there') + ", thank you for reaching out to Creative Touch Interiors regarding: " + (item.subject || 'your message'));
            document.getElementById('btn_modal_whatsapp').href = cleanPhone ? 'https://wa.me/' + cleanPhone + '?text=' + encodedWa : '#';
            document.getElementById('btn_modal_whatsapp').style.display = cleanPhone ? 'inline-flex' : 'none';

            const emailSubject = encodeURIComponent("Re: " + (item.subject || 'Inquiry'));
            document.getElementById('btn_modal_email').href = item.email ? 'mailto:' + item.email + '?subject=' + emailSubject : '#';

            document.getElementById('btn_modal_call').href = cleanPhone ? 'tel:' + cleanPhone : '#';
            document.getElementById('btn_modal_call').style.display = cleanPhone ? 'inline-flex' : 'none';

            document.getElementById('inquiryModal').classList.add('active');
        }

        function closeInquiryModal() {
            document.getElementById('inquiryModal').classList.remove('active');
        }

        // Close on outside click
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                closeInquiryModal();
            }
        });
    </script>
    <script src="../js/vendor/three.min.js"></script>
    <script src="../js/vendor/gsap.min.js"></script>
    <script src="../js/spatial-3d.js"></script>
</body>
</html>
