<?php
require_once '../includes/config.php';

// Enforce admin authentication and prevent caching
requireAdminLogin('login.php');

$success = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validate_csrf()) {
        $error = "Security token expired. Please reload and try again.";
    } else if (isset($_POST['action'])) {
        
        // 1. SCHEDULE / ADD CONSULTATION
        if ($_POST['action'] == 'add') {
            $client_name = sanitize($_POST['client_name'] ?? '');
            $client_email = sanitize($_POST['client_email'] ?? '');
            $client_phone = sanitize($_POST['client_phone'] ?? '');
            $subject = sanitize($_POST['subject'] ?? 'Design Consultation');
            $consultation_date = sanitize($_POST['consultation_date'] ?? date('Y-m-d'));
            $consultation_time = sanitize($_POST['consultation_time'] ?? '10:00:00');
            $status = sanitize($_POST['status'] ?? 'confirmed');
            $notes = sanitize($_POST['notes'] ?? '');

            if (empty($client_name) || empty($client_email)) {
                $error = "Client name and email are required.";
            } else {
                $sql = "INSERT INTO consultations (client_name, client_email, client_phone, subject, consultation_date, consultation_time, status, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssss", $client_name, $client_email, $client_phone, $subject, $consultation_date, $consultation_time, $status, $notes);
                if ($stmt->execute()) {
                    $success = "Consultation session scheduled successfully!";
                } else {
                    $error = "Failed to record consultation: " . $conn->error;
                }
            }
        }

        // 2. EDIT CONSULTATION
        if ($_POST['action'] == 'edit') {
            $id = (int)$_POST['id'];
            $client_name = sanitize($_POST['client_name'] ?? '');
            $client_email = sanitize($_POST['client_email'] ?? '');
            $client_phone = sanitize($_POST['client_phone'] ?? '');
            $subject = sanitize($_POST['subject'] ?? 'Design Consultation');
            $consultation_date = sanitize($_POST['consultation_date'] ?? date('Y-m-d'));
            $consultation_time = sanitize($_POST['consultation_time'] ?? '10:00:00');
            $status = sanitize($_POST['status'] ?? 'pending');
            $notes = sanitize($_POST['notes'] ?? '');

            if (empty($client_name) || empty($client_email)) {
                $error = "Client name and email are required.";
            } else {
                $sql = "UPDATE consultations SET client_name=?, client_email=?, client_phone=?, subject=?, consultation_date=?, consultation_time=?, status=?, notes=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssi", $client_name, $client_email, $client_phone, $subject, $consultation_date, $consultation_time, $status, $notes, $id);
                if ($stmt->execute()) {
                    $success = "Consultation details updated successfully!";
                } else {
                    $error = "Failed to update consultation details.";
                }
            }
        }
        
        // 3. UPDATE STATUS ONLY
        if ($_POST['action'] == 'update_status') {
            $id = (int)$_POST['id'];
            $status = sanitize($_POST['status'] ?? 'pending');
            $stmt = $conn->prepare("UPDATE consultations SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
            if ($stmt->execute()) {
                $success = "Consultation status updated to " . ucfirst($status) . "!";
            } else {
                $error = "Failed to update status.";
            }
        }
        
        // 4. DELETE CONSULTATION
        if ($_POST['action'] == 'delete') {
            if (isReceptionist()) {
                $error = "Access denied: Receptionists cannot delete consultations.";
            } else {
                $id = (int)$_POST['id'];
                $stmt = $conn->prepare("DELETE FROM consultations WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $success = "Consultation record deleted successfully!";
                } else {
                    $error = "Failed to delete consultation.";
                }
            }
        }
    }
}

// Fetch stats
$total_consultations = $conn->query("SELECT COUNT(*) as c FROM consultations")->fetch_assoc()['c'] ?? 0;
$pending_consultations = $conn->query("SELECT COUNT(*) as c FROM consultations WHERE status='pending'")->fetch_assoc()['c'] ?? 0;
$confirmed_consultations = $conn->query("SELECT COUNT(*) as c FROM consultations WHERE status='confirmed'")->fetch_assoc()['c'] ?? 0;
$completed_consultations = $conn->query("SELECT COUNT(*) as c FROM consultations WHERE status='completed'")->fetch_assoc()['c'] ?? 0;

// Fetch all consultations
$consultations_res = $conn->query("SELECT * FROM consultations ORDER BY consultation_date DESC, consultation_time DESC");
$all_consultations = [];
if ($consultations_res) {
    while ($c = $consultations_res->fetch_assoc()) {
        $all_consultations[] = $c;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultations & Design Sessions - Admin</title>
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
        .slot-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 0.35rem 0.65rem;
            border-radius: var(--radius-md);
            font-size: 0.8rem;
            font-weight: 600;
            color: #1e293b;
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
        .btn-schedule-main {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.75rem 1.65rem;
            border-radius: 9999px;
            font-size: 0.925rem;
            font-weight: 700;
            color: #ffffff !important;
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .btn-schedule-main:hover {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%) !important;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.45);
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
        .btn-preset {
            padding: 0.3rem 0.65rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-preset:hover {
            background: #dbeafe;
            color: #1e40af;
            border-color: #93c5fd;
        }
    </style>
</head>
<body style="background: #F8F7F4; color: #0f172a;">
    <div style="display: flex; min-height: 100vh;">
        
        <!-- Unified Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content Viewport -->
        <div class="admin-content" style="flex: 1; padding: 2.25rem 2.5rem;">
            
            <!-- Executive Header with Avatar & Dropdown -->
            <?php include 'includes/header.php'; ?>

            <!-- Header Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-family: var(--font-heading); font-size: 2.15rem; color: var(--color-text-main); font-weight: 700; margin: 0 0 0.35rem; display: flex; align-items: center; gap: 0.75rem;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: var(--radius-lg); background: #eff6ff; color: #2563eb;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </span>
                        <span>Consultations & Appointments</span>
                    </h1>
                    <p style="color: var(--color-text-muted); font-size: 0.95rem; margin: 0;">
                        Manage scheduled design sessions, in-person studio meetings, and client consultation slots.
                    </p>
                </div>
                
                <div>
                    <button type="button" onclick="openScheduleModal()" class="btn-schedule-main">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                            <line x1="12" y1="14" x2="12" y2="18"></line>
                            <line x1="10" y1="16" x2="14" y2="16"></line>
                        </svg>
                        <span>Book New Consultation</span>
                    </button>
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
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #1e3a8a; line-height: 1.1; font-family: var(--font-heading);"><?php echo $total_consultations; ?></div>
                        <div style="font-size: 0.825rem; color: #64748b; font-weight: 600; margin-top: 0.2rem;">Total Appointments</div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon-box" style="background: #fff7ed; color: #ea580c;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #ea580c; line-height: 1.1; font-family: var(--font-heading);"><?php echo $pending_consultations; ?></div>
                        <div style="font-size: 0.825rem; color: #64748b; font-weight: 600; margin-top: 0.2rem;">Pending Requests</div>
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
                        <div style="font-size: 1.75rem; font-weight: 800; color: #10b981; line-height: 1.1; font-family: var(--font-heading);"><?php echo $confirmed_consultations; ?></div>
                        <div style="font-size: 0.825rem; color: #64748b; font-weight: 600; margin-top: 0.2rem;">Confirmed Sessions</div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon-box" style="background: #faf5ff; color: #9333ea;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #9333ea; line-height: 1.1; font-family: var(--font-heading);"><?php echo $completed_consultations; ?></div>
                        <div style="font-size: 0.825rem; color: #64748b; font-weight: 600; margin-top: 0.2rem;">Completed Sessions</div>
                    </div>
                </div>

            </div>

            <!-- Main CRM Table Card -->
            <div class="card" style="padding: 1.75rem 2rem; border-radius: var(--radius-2xl); border: 1px solid #bfdbfe; background: #ffffff; box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);">
                
                <!-- Filter & Search Controls -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
                    
                    <!-- Filter Chips -->
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="button" class="filter-chip active" onclick="filterConsultations('all', this)">
                            <span>All Sessions</span>
                            <span class="chip-badge"><?php echo $total_consultations; ?></span>
                        </button>
                        <button type="button" class="filter-chip" onclick="filterConsultations('pending', this)">
                            <span>Pending</span>
                            <span class="chip-badge"><?php echo $pending_consultations; ?></span>
                        </button>
                        <button type="button" class="filter-chip" onclick="filterConsultations('confirmed', this)">
                            <span>Confirmed</span>
                            <span class="chip-badge"><?php echo $confirmed_consultations; ?></span>
                        </button>
                        <button type="button" class="filter-chip" onclick="filterConsultations('completed', this)">
                            <span>Completed</span>
                            <span class="chip-badge"><?php echo $completed_consultations; ?></span>
                        </button>
                        <button type="button" class="filter-chip" onclick="filterConsultations('cancelled', this)">
                            <span>Cancelled</span>
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div style="position: relative; min-width: 260px;">
                        <input type="text" id="consultationSearch" oninput="searchConsultations(this.value)" placeholder="Search client, topic, date, notes..." class="form-control" style="padding-left: 2.4rem; font-size: 0.875rem; border-radius: 9999px; background: #f8fafc; border: 1px solid #cbd5e1; color: #0f172a;">
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
                                <th style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Client Name</th>
                                <th style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Direct Contact</th>
                                <th style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Consultation Topic</th>
                                <th style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Slot Date & Time</th>
                                <th style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Status</th>
                                <th style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Notes</th>
                                <th style="padding: 0.85rem 1rem; color: #64748b; font-size: 0.8rem; text-transform: uppercase; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="consultationsTableBody">
                            <?php if (!empty($all_consultations)): ?>
                                <?php foreach ($all_consultations as $item): 
                                    $status = strtolower($item['status'] ?? 'pending');
                                    $initials = strtoupper(substr($item['client_name'] ?? 'C', 0, 2));
                                    $cleanPhone = preg_replace('/[^0-9]/', '', $item['client_phone'] ?? '');
                                ?>
                                    <tr class="consultation-row" 
                                        data-status="<?php echo htmlspecialchars($status); ?>"
                                        data-search="<?php echo htmlspecialchars(strtolower($item['client_name'] . ' ' . ($item['client_email'] ?? '') . ' ' . ($item['client_phone'] ?? '') . ' ' . ($item['subject'] ?? '') . ' ' . ($item['notes'] ?? ''))); ?>"
                                        style="border-bottom: 1px solid #f1f5f9; background: #ffffff; transition: background 0.15s ease;">
                                        
                                        <!-- Client Name & Avatar -->
                                        <td style="padding: 1rem;">
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <div class="client-avatar-badge">
                                                    <?php echo htmlspecialchars($initials); ?>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.95rem;">
                                                        <?php echo htmlspecialchars($item['client_name']); ?>
                                                    </div>
                                                    <div style="font-size: 0.775rem; color: #64748b; margin-top: 0.1rem;">
                                                        Booked: <?php echo !empty($item['created_at']) ? date('M d, Y', strtotime($item['created_at'])) : 'N/A'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Contact Info -->
                                        <td style="padding: 1rem;">
                                            <div style="display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.85rem;">
                                                <a href="mailto:<?php echo htmlspecialchars($item['client_email']); ?>" style="color: #2563eb; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 600;">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                        <polyline points="22,6 12,13 2,6"></polyline>
                                                    </svg>
                                                    <span><?php echo htmlspecialchars($item['client_email']); ?></span>
                                                </a>
                                                <?php if (!empty($item['client_phone'])): ?>
                                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                        <a href="tel:<?php echo htmlspecialchars($item['client_phone']); ?>" style="color: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                                            </svg>
                                                            <span><?php echo htmlspecialchars($item['client_phone']); ?></span>
                                                        </a>
                                                        <?php if (!empty($cleanPhone)): ?>
                                                            <a href="https://wa.me/<?php echo $cleanPhone; ?>?text=<?php echo urlencode("Hello " . $item['client_name'] . ", this is Creative Touch Interiors regarding your consultation appointment on " . $item['consultation_date']); ?>" target="_blank" title="WhatsApp" style="color: #10b981; text-decoration: none; display: inline-flex; align-items: center;">
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
                                            <div style="font-weight: 700; font-size: 0.875rem; color: #1e293b; max-width: 200px;">
                                                <?php echo htmlspecialchars($item['subject'] ?? 'Design Consultation'); ?>
                                            </div>
                                        </td>

                                        <!-- Date & Time Slot -->
                                        <td style="padding: 1rem;">
                                            <div style="display: flex; flex-direction: column; gap: 0.3rem;">
                                                <div class="slot-pill" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                                        <line x1="3" y1="10" x2="21" y2="10"></line>
                                                    </svg>
                                                    <span><?php echo !empty($item['consultation_date']) ? date('M d, Y', strtotime($item['consultation_date'])) : 'TBD'; ?></span>
                                                </div>
                                                <?php if (!empty($item['consultation_time']) && $item['consultation_time'] != '00:00:00'): ?>
                                                    <div style="font-size: 0.75rem; font-weight: 700; color: #2563eb; display: inline-flex; align-items: center; gap: 0.35rem; padding-left: 0.3rem;">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                            <circle cx="12" cy="12" r="10"></circle>
                                                            <polyline points="12 6 12 12 16 14"></polyline>
                                                        </svg>
                                                        <span><?php echo date('h:i A', strtotime($item['consultation_time'])); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Status with Inline Switcher -->
                                        <td style="padding: 1rem;">
                                            <form method="POST" style="display: inline-block;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                
                                                <select name="status" onchange="this.form.submit()" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; font-weight: 700; border-radius: var(--radius-md); border: 1px solid <?php 
                                                    echo $status == 'confirmed' ? '#a7f3d0' : ($status == 'pending' ? '#fed7aa' : ($status == 'completed' ? '#bfdbfe' : '#fecaca')); 
                                                ?>; background: <?php 
                                                    echo $status == 'confirmed' ? '#ecfdf5' : ($status == 'pending' ? '#fff7ed' : ($status == 'completed' ? '#eff6ff' : '#fef2f2')); 
                                                ?>; color: <?php 
                                                    echo $status == 'confirmed' ? '#047857' : ($status == 'pending' ? '#c2410c' : ($status == 'completed' ? '#1d4ed8' : '#b91c1c')); 
                                                ?>; cursor: pointer;">
                                                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="confirmed" <?php echo $status == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                    <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                            </form>
                                        </td>

                                        <!-- Notes Excerpt -->
                                        <td style="padding: 1rem;">
                                            <div style="font-size: 0.825rem; color: #64748b; max-width: 220px; line-height: 1.4;">
                                                <?php 
                                                    $cleanNote = strip_tags($item['notes'] ?? '');
                                                    echo !empty($cleanNote) ? htmlspecialchars(substr($cleanNote, 0, 75)) . (strlen($cleanNote) > 75 ? '...' : '') : '<span style="color: #94a3b8;">None provided</span>'; 
                                                ?>
                                            </div>
                                        </td>

                                        <!-- Actions -->
                                        <td style="padding: 1rem; text-align: right;">
                                            <div style="display: inline-flex; gap: 0.4rem;">
                                                <button type="button" onclick="openConsultModal(<?php echo htmlspecialchars(json_encode($item)); ?>)" class="action-icon-btn" title="View & Edit" style="color: #2563eb;">
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                        <circle cx="12" cy="12" r="3"></circle>
                                                    </svg>
                                                </button>
                                                <?php if (!isReceptionist()): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this consultation record?');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" class="action-icon-btn" title="Delete Consultation" style="color: #ef4444;">
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                            <polyline points="3 6 5 6 21 6"></polyline>
                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                            <line x1="10" y1="11" x2="10" y2="17"></line>
                                                            <line x1="14" y1="11" x2="14" y2="17"></line>
                                                        </svg>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 3rem; color: var(--color-text-muted);">
                                        No consultation sessions scheduled yet. Bookings will appear here.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div id="noResultsMsg" style="display: none; text-align: center; padding: 3rem; color: var(--color-text-muted);">
                    No consultations match your current search or filter query.
                </div>

            </div>
        </div>
    </div>

    <!-- SCHEDULE CONSULTATION MODAL -->
    <div id="scheduleModal" class="modal-overlay">
        <div class="modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-family: var(--font-heading); font-size: 1.5rem; font-weight: 700; color: var(--color-text-main); margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: #2563eb;">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span>Schedule New Consultation</span>
                </h2>
                <button type="button" onclick="closeScheduleModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
            </div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="grid grid-2" style="gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Client Full Name *</label>
                        <input type="text" name="client_name" class="form-control" required placeholder="e.g. Rahul Sharma">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Client Email *</label>
                        <input type="email" name="client_email" class="form-control" required placeholder="rahul@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="client_phone" class="form-control" placeholder="+91 98765 43210">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Topic / Scope</label>
                        <input type="text" name="subject" class="form-control" placeholder="e.g. 3BHK Apartment Interior Design">
                    </div>
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                            <label class="form-label" style="margin: 0;">Appointment Date</label>
                            <div style="display: flex; gap: 0.25rem;">
                                <button type="button" class="btn-preset" onclick="setScheduleDate('today')">Today</button>
                                <button type="button" class="btn-preset" onclick="setScheduleDate('tomorrow')">Tomorrow</button>
                            </div>
                        </div>
                        <input type="date" name="consultation_date" id="schedule_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                            <label class="form-label" style="margin: 0;">Appointment Time</label>
                            <div style="display: flex; gap: 0.25rem;">
                                <button type="button" class="btn-preset" onclick="setScheduleTime('10:00')">10 AM</button>
                                <button type="button" class="btn-preset" onclick="setScheduleTime('14:00')">2 PM</button>
                                <button type="button" class="btn-preset" onclick="setScheduleTime('17:00')">5 PM</button>
                            </div>
                        </div>
                        <input type="time" name="consultation_time" id="schedule_time" class="form-control" value="10:00">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">Initial Status</label>
                        <select name="status" class="form-control">
                            <option value="pending">Pending</option>
                            <option value="confirmed" selected>Confirmed</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Meeting Notes & Discussion Agenda</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Enter meeting details, client specifications, or material notes..."></textarea>
                </div>

                <div style="display: flex; gap: 0.85rem; justify-content: flex-end; align-items: center; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                    <button type="button" onclick="closeScheduleModal()" class="btn-modal-cancel">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        <span>Cancel</span>
                    </button>
                    <button type="submit" class="btn-modal-submit">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span>Save Consultation</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- VIEW & EDIT CONSULTATION MODAL -->
    <div id="consultModal" class="modal-overlay">
        <div class="modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <div>
                    <h2 style="font-family: var(--font-heading); font-size: 1.45rem; font-weight: 700; color: var(--color-text-main); margin: 0;" id="edit_modal_title">
                        Consultation Details
                    </h2>
                    <span style="font-size: 0.8rem; color: var(--color-text-muted);" id="edit_modal_subtitle"></span>
                </div>
                <button type="button" onclick="closeConsultModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
            </div>

            <!-- Direct Client Contact Actions -->
            <div style="display: flex; gap: 0.6rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                <a id="btn_consult_whatsapp" href="#" target="_blank" class="btn btn-outline" style="font-size: 0.825rem; padding: 0.45rem 0.9rem; color: #10b981; border-color: rgba(16, 185, 129, 0.3); background: #ecfdf5; display: inline-flex; align-items: center; gap: 0.45rem; border-radius: 9999px; font-weight: 600;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                    <span>WhatsApp Client</span>
                </a>
                <a id="btn_consult_email" href="#" class="btn btn-outline" style="font-size: 0.825rem; padding: 0.45rem 0.9rem; color: #2563eb; border-color: #bfdbfe; background: #eff6ff; display: inline-flex; align-items: center; gap: 0.45rem; border-radius: 9999px; font-weight: 600;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    <span>Send Email</span>
                </a>
                <a id="btn_consult_call" href="#" class="btn btn-outline" style="font-size: 0.825rem; padding: 0.45rem 0.9rem; color: #475569; border-color: #cbd5e1; background: #f8fafc; display: inline-flex; align-items: center; gap: 0.45rem; border-radius: 9999px; font-weight: 600;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                    <span>Phone Call</span>
                </a>
            </div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="grid grid-2" style="gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Client Name *</label>
                        <input type="text" name="client_name" id="edit_client_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="client_email" id="edit_client_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="client_phone" id="edit_client_phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Topic / Subject</label>
                        <input type="text" name="subject" id="edit_subject" class="form-control">
                    </div>
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                            <label class="form-label" style="margin: 0;">Appointment Date</label>
                            <div style="display: flex; gap: 0.25rem;">
                                <button type="button" class="btn-preset" onclick="setEditDate('today')">Today</button>
                                <button type="button" class="btn-preset" onclick="setEditDate('tomorrow')">Tomorrow</button>
                            </div>
                        </div>
                        <input type="date" name="consultation_date" id="edit_consultation_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                            <label class="form-label" style="margin: 0;">Appointment Time</label>
                            <div style="display: flex; gap: 0.25rem;">
                                <button type="button" class="btn-preset" onclick="setEditTime('10:00')">10 AM</button>
                                <button type="button" class="btn-preset" onclick="setEditTime('14:00')">2 PM</button>
                                <button type="button" class="btn-preset" onclick="setEditTime('17:00')">5 PM</button>
                            </div>
                        </div>
                        <input type="time" name="consultation_time" id="edit_consultation_time" class="form-control">
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Client Requirements / Discussion Notes</label>
                    <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                </div>

                <div style="display: flex; gap: 0.85rem; justify-content: flex-end; align-items: center; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                    <button type="button" onclick="closeConsultModal()" class="btn-modal-cancel">
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
                        <span>Update Consultation</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentStatusFilter = 'all';
        let searchQuery = '';

        function filterConsultations(status, btn) {
            currentStatusFilter = status;
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            if (btn) btn.classList.add('active');
            applyFilters();
        }

        function searchConsultations(query) {
            searchQuery = query.toLowerCase().trim();
            applyFilters();
        }

        function applyFilters() {
            const rows = document.querySelectorAll('.consultation-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const status = row.getAttribute('data-status') || '';
                const search = row.getAttribute('data-search') || '';

                let matchStatus = (currentStatusFilter === 'all') ? true : (status === currentStatusFilter);
                let matchSearch = (searchQuery === '' || search.includes(searchQuery));

                if (matchStatus && matchSearch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('noResultsMsg').style.display = visibleCount === 0 ? 'block' : 'none';
        }

        function openScheduleModal() {
            document.getElementById('scheduleModal').classList.add('active');
        }

        function closeScheduleModal() {
            document.getElementById('scheduleModal').classList.remove('active');
        }

        function setScheduleDate(type) {
            const d = new Date();
            if (type === 'tomorrow') d.setDate(d.getDate() + 1);
            document.getElementById('schedule_date').value = d.toISOString().split('T')[0];
        }

        function setScheduleTime(timeStr) {
            document.getElementById('schedule_time').value = timeStr;
        }

        function setEditDate(type) {
            const d = new Date();
            if (type === 'tomorrow') d.setDate(d.getDate() + 1);
            document.getElementById('edit_consultation_date').value = d.toISOString().split('T')[0];
        }

        function setEditTime(timeStr) {
            document.getElementById('edit_consultation_time').value = timeStr;
        }

        function openConsultModal(item) {
            document.getElementById('edit_id').value = item.id;
            document.getElementById('edit_client_name').value = item.client_name || '';
            document.getElementById('edit_client_email').value = item.client_email || '';
            document.getElementById('edit_client_phone').value = item.client_phone || '';
            document.getElementById('edit_subject').value = item.subject || '';
            document.getElementById('edit_consultation_date').value = item.consultation_date || '';
            document.getElementById('edit_consultation_time').value = item.consultation_time || '';
            document.getElementById('edit_status').value = item.status || 'pending';
            document.getElementById('edit_notes').value = item.notes || '';

            document.getElementById('edit_modal_title').textContent = item.client_name ? 'Appointment: ' + item.client_name : 'Consultation Details';
            document.getElementById('edit_modal_subtitle').textContent = item.created_at ? 'Submitted on ' + item.created_at : '';

            // Update Direct Outreach Shortcuts
            const cleanPhone = (item.client_phone || '').replace(/[^0-9]/g, '');
            const encodedMsg = encodeURIComponent("Hello " + (item.client_name || 'there') + ", this is Creative Touch Interiors regarding your consultation appointment for " + (item.subject || 'Interior Design') + ".");
            document.getElementById('btn_consult_whatsapp').href = cleanPhone ? 'https://wa.me/' + cleanPhone + '?text=' + encodedMsg : '#';
            document.getElementById('btn_consult_whatsapp').style.display = cleanPhone ? 'inline-flex' : 'none';

            const emailSubject = encodeURIComponent("Regarding your consultation: " + (item.subject || 'Creative Touch Interiors'));
            document.getElementById('btn_consult_email').href = item.client_email ? 'mailto:' + item.client_email + '?subject=' + emailSubject : '#';

            document.getElementById('btn_consult_call').href = cleanPhone ? 'tel:' + cleanPhone : '#';
            document.getElementById('btn_consult_call').style.display = cleanPhone ? 'inline-flex' : 'none';

            document.getElementById('consultModal').classList.add('active');
        }

        function closeConsultModal() {
            document.getElementById('consultModal').classList.remove('active');
        }

        // Close on outside click
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                closeScheduleModal();
                closeConsultModal();
            }
        });
    </script>
    <script src="../js/vendor/three.min.js"></script>
    <script src="../js/vendor/gsap.min.js"></script>
    <script src="../js/spatial-3d.js"></script>
</body>
</html>
