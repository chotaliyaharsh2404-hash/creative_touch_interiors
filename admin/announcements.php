<?php
require_once '../includes/config.php';

// Enforce RBAC: Only Super Admin and Admin can manage announcements (Receptionist blocked)
requireRoles(['super_admin', 'admin'], 'dashboard.php');

$current_page = 'announcements';
$error = '';
$success = '';

$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$now = date('Y-m-d H:i:s');

// Handle Form & Mutation Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Security token expired or invalid. Please reload and try again.';
    } else {
        $action = sanitize($_POST['action'] ?? '');

        // 1. CREATE ANNOUNCEMENT
        if ($action === 'create') {
            $title = sanitize($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $type = sanitize($_POST['type'] ?? 'announcement');
            $priority = sanitize($_POST['priority'] ?? 'normal');
            $status = sanitize($_POST['status'] ?? 'draft');
            $cta_text = sanitize($_POST['cta_text'] ?? '');
            $raw_cta_url = trim($_POST['cta_url'] ?? '');
            $cta_url = sanitizeCtaUrl($raw_cta_url);

            $start_date = !empty($_POST['start_at']) ? date('Y-m-d H:i:s', strtotime($_POST['start_at'])) : date('Y-m-d H:i:s');
            $expires_date = !empty($_POST['expires_at']) ? date('Y-m-d H:i:s', strtotime($_POST['expires_at'])) : null;

            if (!in_array($type, ['promotion', 'announcement', 'update', 'notice', 'system'], true)) {
                $type = 'announcement';
            }
            if (!in_array($priority, ['normal', 'important', 'urgent'], true)) {
                $priority = 'normal';
            }
            if (!in_array($status, ['draft', 'published', 'expired', 'archived'], true)) {
                $status = 'draft';
            }

            if (empty($title) || empty($message)) {
                $error = 'Announcement title and message are required.';
            } else {
                $cover_image = null;
                // Handle optional image upload
                if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadRes = secure_upload_image($_FILES['cover_image'], 'announcements', 'announcement_');
                    if (!$uploadRes['success']) {
                        $error = $uploadRes['error'];
                    } else {
                        $cover_image = $uploadRes['filepath'];
                    }
                }

                if (empty($error)) {
                    $stmt = $conn->prepare("INSERT INTO announcements (title, message, type, cover_image, priority, cta_text, cta_url, start_at, expires_at, status, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssssssssii", $title, $message, $type, $cover_image, $priority, $cta_text, $cta_url, $start_date, $expires_date, $status, $admin_id, $admin_id);
                    if ($stmt->execute()) {
                        $new_id = $stmt->insert_id;
                        $stmt->close();
                        if (isset($_POST['save_and_preview'])) {
                            redirect("announcement_preview.php?id={$new_id}");
                        } else {
                            $success = 'Announcement created successfully!';
                        }
                    } else {
                        if ($cover_image) {
                            safe_delete_uploaded_image($cover_image);
                        }
                        $error = 'Failed to save announcement to database.';
                    }
                }
            }
        }

        // 2. EDIT ANNOUNCEMENT
        elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $title = sanitize($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $type = sanitize($_POST['type'] ?? 'announcement');
            $priority = sanitize($_POST['priority'] ?? 'normal');
            $status = sanitize($_POST['status'] ?? 'draft');
            $cta_text = sanitize($_POST['cta_text'] ?? '');
            $raw_cta_url = trim($_POST['cta_url'] ?? '');
            $cta_url = sanitizeCtaUrl($raw_cta_url);

            $start_date = !empty($_POST['start_at']) ? date('Y-m-d H:i:s', strtotime($_POST['start_at'])) : date('Y-m-d H:i:s');
            $expires_date = !empty($_POST['expires_at']) ? date('Y-m-d H:i:s', strtotime($_POST['expires_at'])) : null;

            if ($id <= 0 || empty($title) || empty($message)) {
                $error = 'Invalid parameters for announcement update.';
            } else {
                // Fetch current record
                $curStmt = $conn->prepare("SELECT cover_image FROM announcements WHERE id = ? AND deleted_at IS NULL");
                $curStmt->bind_param("i", $id);
                $curStmt->execute();
                $cur = $curStmt->get_result()->fetch_assoc();
                $curStmt->close();

                if (!$cur) {
                    $error = 'Announcement record not found.';
                } else {
                    $cover_image = $cur['cover_image'];
                    $old_image = $cur['cover_image'];
                    $new_uploaded = false;

                    // Handle replacement image upload if provided
                    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $uploadRes = secure_upload_image($_FILES['cover_image'], 'announcements', 'announcement_');
                        if (!$uploadRes['success']) {
                            $error = $uploadRes['error'];
                        } else {
                            $cover_image = $uploadRes['filepath'];
                            $new_uploaded = true;
                        }
                    }

                    // Optional image removal
                    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
                        $cover_image = null;
                        if (!empty($old_image)) {
                            safe_delete_uploaded_image($old_image);
                        }
                    }

                    if (empty($error)) {
                        $upStmt = $conn->prepare("UPDATE announcements SET title = ?, message = ?, type = ?, cover_image = ?, priority = ?, cta_text = ?, cta_url = ?, start_at = ?, expires_at = ?, status = ?, updated_by = ? WHERE id = ?");
                        $upStmt->bind_param("ssssssssssii", $title, $message, $type, $cover_image, $priority, $cta_text, $cta_url, $start_date, $expires_date, $status, $admin_id, $id);
                        if ($upStmt->execute()) {
                            $upStmt->close();
                            if ($new_uploaded && !empty($old_image) && $old_image !== $cover_image) {
                                safe_delete_uploaded_image($old_image);
                            }
                            if (isset($_POST['save_and_preview'])) {
                                redirect("announcement_preview.php?id={$id}");
                            } else {
                                $success = 'Announcement updated successfully!';
                            }
                        } else {
                            if ($new_uploaded && !empty($cover_image)) {
                                safe_delete_uploaded_image($cover_image);
                            }
                            $error = 'Failed to update announcement.';
                        }
                    }
                }
            }
        }

        // 3. PUBLISH QUICK ACTION
        elseif ($action === 'publish') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE announcements SET status = 'published', updated_by = ? WHERE id = ? AND deleted_at IS NULL");
                $stmt->bind_param("ii", $admin_id, $id);
                if ($stmt->execute()) {
                    $success = 'Announcement is now published and live on the public website!';
                }
                $stmt->close();
            }
        }

        // 4. ARCHIVE ACTION
        elseif ($action === 'archive') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("UPDATE announcements SET status = 'archived', updated_by = ? WHERE id = ? AND deleted_at IS NULL");
                $stmt->bind_param("ii", $admin_id, $id);
                if ($stmt->execute()) {
                    $success = 'Announcement archived successfully.';
                }
                $stmt->close();
            }
        }

        // 5. DELETE ACTION (Soft Delete & Safe Image Cleanup)
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $dStmt = $conn->prepare("UPDATE announcements SET deleted_at = NOW(), updated_by = ? WHERE id = ?");
                $dStmt->bind_param("ii", $admin_id, $id);
                if ($dStmt->execute()) {
                    $success = 'Announcement deleted successfully.';
                }
                $dStmt->close();
            }
        }
    }
}

// Filter handling
$filter = sanitize($_GET['filter'] ?? 'all');
$where = "WHERE deleted_at IS NULL";
if ($filter === 'published') {
    $where .= " AND status = 'published' AND (expires_at IS NULL OR expires_at >= '{$now}')";
} elseif ($filter === 'draft') {
    $where .= " AND status = 'draft'";
} elseif ($filter === 'expired') {
    $where .= " AND (status = 'expired' OR (status = 'published' AND expires_at < '{$now}'))";
} elseif ($filter === 'archived') {
    $where .= " AND status = 'archived'";
}

// Fetch telemetry summary metrics
$stat_total = $conn->query("SELECT COUNT(*) as c FROM announcements WHERE deleted_at IS NULL")->fetch_assoc()['c'] ?? 0;
$stat_published = $conn->query("SELECT COUNT(*) as c FROM announcements WHERE deleted_at IS NULL AND status = 'published' AND (expires_at IS NULL OR expires_at >= '{$now}')")->fetch_assoc()['c'] ?? 0;
$stat_views = $conn->query("SELECT SUM(views_count) as s FROM announcements WHERE deleted_at IS NULL")->fetch_assoc()['s'] ?? 0;
$stat_unique = $conn->query("SELECT SUM(unique_views_count) as s FROM announcements WHERE deleted_at IS NULL")->fetch_assoc()['s'] ?? 0;

// Fetch list of announcements
$announcements = [];
$res = $conn->query("SELECT * FROM announcements $where ORDER BY created_at DESC, id DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $announcements[] = $r;
    }
}

$page_title = 'Announcement & Promotion Management — Executive Suite';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/spatial-3d.css">
    <style>
        .filter-pill {
            padding: 0.5rem 1.1rem;
            border-radius: 9999px;
            font-size: 0.825rem;
            font-weight: 700;
            text-decoration: none;
            color: #64748b;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .filter-pill:hover {
            color: #0057FF;
            border-color: #0057FF;
            background: rgba(0, 87, 255, 0.04);
        }
        .filter-pill.active {
            color: #ffffff;
            background: #0057FF;
            border-color: #0057FF;
            box-shadow: 0 4px 12px rgba(0, 87, 255, 0.25);
        }
        .ann-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .ann-modal.active {
            display: flex;
        }
        .ann-modal-content {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.25);
            border-radius: 20px;
            color: #0f172a;
            width: 100%;
            max-width: 680px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2.25rem 2.5rem;
        }
        .ann-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }
        .ann-table th {
            text-align: left;
            padding: 1rem 1.1rem;
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .ann-table td {
            padding: 1.1rem;
            border-bottom: 1px solid #e2e8f0;
            color: #1e293b;
            vertical-align: middle;
        }
        .ann-table tr:hover td {
            background: rgba(248, 250, 252, 0.7);
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-action:hover {
            border-color: #0057FF;
            color: #0057FF;
            background: rgba(0, 87, 255, 0.06);
            transform: translateY(-1px);
        }
    </style>
</head>
<body style="background: #F8F7F4; color: #0f172a;">
    <div style="display: flex; min-height: 100vh;">
        
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-content" style="flex: 1; padding: 2.25rem 2.5rem; max-width: 1600px;">
            
            <!-- Executive Header with Avatar & Dropdown -->
            <?php include 'includes/header.php'; ?>

            <!-- Page Title Bar & Primary Create Action -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1.25rem;">
                <div>
                    <h1 style="font-family: var(--font-heading); font-size: 1.85rem; color: #0f172a; margin: 0 0 0.4rem; font-weight: 700;">
                        Public Announcements &amp; Promotions
                    </h1>
                    <p style="color: #64748b; margin: 0; font-size: 0.92rem;">
                        Broadcast promotional offers, new architectural services, and studio advisories directly to all website visitors.
                    </p>
                </div>

                <div style="display: flex; gap: 0.85rem; align-items: center;">
                    <a href="../announcements.php" target="_blank" class="btn-action" style="width: auto; padding: 0.65rem 1.15rem; gap: 0.45rem; font-size: 0.825rem; font-weight: 700;" title="View public page in new tab">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                        <span>View Public Page</span>
                    </a>
                    <button type="button" onclick="openCreateModal()" class="btn btn-primary" style="background: #0057FF; color: #ffffff; border: none; border-radius: 9999px; padding: 0.75rem 1.6rem; font-weight: 700; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 8px 20px rgba(0, 87, 255, 0.28); cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        <span>Create Announcement</span>
                    </button>
                </div>
            </div>

            <!-- Feedback Notifications -->
            <?php if (!empty($error)): ?>
                <div style="background: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b; padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.75rem; font-size: 0.9rem; display: flex; align-items: center; gap: 0.75rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div style="background: #f0fdf4; border-left: 4px solid #22c55e; color: #166534; padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.75rem; font-size: 0.9rem; display: flex; align-items: center; gap: 0.75rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Telemetry Metrics Summary Bar -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                <div style="background: #ffffff; padding: 1.35rem 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.05em;">Total Broadcasts</div>
                    <div style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin-top: 0.35rem;"><?php echo $stat_total; ?></div>
                    <div style="font-size: 0.78rem; color: #94a3b8; margin-top: 0.2rem;">All promotional records</div>
                </div>

                <div style="background: #ffffff; padding: 1.35rem 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #059669; font-weight: 700; letter-spacing: 0.05em;">Active &amp; Published</div>
                    <div style="font-size: 1.85rem; font-weight: 800; color: #059669; margin-top: 0.35rem;"><?php echo $stat_published; ?></div>
                    <div style="font-size: 0.78rem; color: #94a3b8; margin-top: 0.2rem;">Currently live to visitors</div>
                </div>

                <div style="background: #ffffff; padding: 1.35rem 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #0057FF; font-weight: 700; letter-spacing: 0.05em;">Total Anonymous Views</div>
                    <div style="font-size: 1.85rem; font-weight: 800; color: #0057FF; margin-top: 0.35rem;"><?php echo number_format($stat_views); ?></div>
                    <div style="font-size: 0.78rem; color: #94a3b8; margin-top: 0.2rem;">Aggregated impressions</div>
                </div>

                <div style="background: #ffffff; padding: 1.35rem 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: #7c3aed; font-weight: 700; letter-spacing: 0.05em;">Approx. Unique Visitors</div>
                    <div style="font-size: 1.85rem; font-weight: 800; color: #7c3aed; margin-top: 0.35rem;"><?php echo number_format($stat_unique); ?></div>
                    <div style="font-size: 0.78rem; color: #94a3b8; margin-top: 0.2rem;">Zero-PII privacy telemetry</div>
                </div>
            </div>

            <!-- Filter Navigation Tabs -->
            <div style="display: flex; gap: 0.65rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                <a href="announcements.php?filter=all" class="filter-pill <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    All Broadcasts (<?php echo $stat_total; ?>)
                </a>
                <a href="announcements.php?filter=published" class="filter-pill <?php echo $filter === 'published' ? 'active' : ''; ?>">
                    Published &amp; Active (<?php echo $stat_published; ?>)
                </a>
                <a href="announcements.php?filter=draft" class="filter-pill <?php echo $filter === 'draft' ? 'active' : ''; ?>">
                    Drafts
                </a>
                <a href="announcements.php?filter=expired" class="filter-pill <?php echo $filter === 'expired' ? 'active' : ''; ?>">
                    Expired
                </a>
                <a href="announcements.php?filter=archived" class="filter-pill <?php echo $filter === 'archived' ? 'active' : ''; ?>">
                    Archived
                </a>
            </div>

            <!-- Main Announcements Table Card -->
            <div style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 16px rgba(0,0,0,0.04); overflow: hidden;">
                <?php if (!empty($announcements)): ?>
                    <div style="overflow-x: auto;">
                        <table class="ann-table">
                            <thead>
                                <tr>
                                    <th style="width: 32%;">Title</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Start Date</th>
                                    <th>Expiry Date</th>
                                    <th>Views (Total / Uniq)</th>
                                    <th style="text-align: right; padding-right: 1.5rem;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($announcements as $row): ?>
                                    <?php 
                                        // Dynamic status detection
                                        $display_status = $row['status'];
                                        $is_expired = (!empty($row['expires_at']) && $row['expires_at'] < $now && $row['status'] === 'published');
                                        if ($is_expired) {
                                            $display_status = 'expired';
                                        }

                                        // Status badge styling
                                        $status_color = '#64748b';
                                        $status_bg = '#f1f5f9';
                                        if ($display_status === 'published') {
                                            $status_color = '#059669';
                                            $status_bg = '#ecfdf5';
                                        } elseif ($display_status === 'draft') {
                                            $status_color = '#2563eb';
                                            $status_bg = '#eff6ff';
                                        } elseif ($display_status === 'expired') {
                                            $status_color = '#dc2626';
                                            $status_bg = '#fef2f2';
                                        } elseif ($display_status === 'archived') {
                                            $status_color = '#94a3b8';
                                            $status_bg = '#f8fafc';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.85rem;">
                                                <?php if (!empty($row['cover_image'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($row['cover_image']); ?>" alt="Cover" style="width: 44px; height: 44px; border-radius: 8px; object-fit: cover; border: 1px solid #e2e8f0; flex-shrink: 0;">
                                                <?php else: ?>
                                                    <div style="width: 44px; height: 44px; border-radius: 8px; background: rgba(0,87,255,0.08); color: #0057FF; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0; border: 1px solid rgba(0,87,255,0.15);">
                                                        <?php echo $row['type'] === 'promotion' ? '🎉' : ($row['type'] === 'update' ? '📢' : '✨'); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div style="font-weight: 700; color: #0f172a; line-height: 1.35; margin-bottom: 0.2rem;">
                                                        <?php echo htmlspecialchars($row['title']); ?>
                                                    </div>
                                                    <div style="font-size: 0.78rem; color: #64748b;">
                                                        Priority: <strong style="text-transform: capitalize; color: <?php echo $row['priority'] === 'urgent' ? '#dc2626' : ($row['priority'] === 'important' ? '#0057FF' : '#475569'); ?>;"><?php echo htmlspecialchars($row['priority']); ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-size: 0.74rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.25rem 0.65rem; border-radius: 9999px; background: <?php echo $row['type'] === 'promotion' ? 'rgba(234, 88, 12, 0.1)' : ($row['type'] === 'update' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(0, 87, 255, 0.1)'); ?>; color: <?php echo $row['type'] === 'promotion' ? '#ea580c' : ($row['type'] === 'update' ? '#059669' : '#0057FF'); ?>;">
                                                <?php echo htmlspecialchars($row['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-size: 0.74rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 0.25rem 0.65rem; border-radius: 9999px; background: <?php echo $status_bg; ?>; color: <?php echo $status_color; ?>; border: 1px solid <?php echo $status_color; ?>33;">
                                                <?php echo ucfirst($display_status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-size: 0.82rem; color: #475569;">
                                                <?php echo !empty($row['start_at']) ? date('d M Y', strtotime($row['start_at'])) : date('d M Y', strtotime($row['created_at'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-size: 0.82rem; color: <?php echo $is_expired ? '#dc2626' : '#475569'; ?>; font-weight: <?php echo $is_expired ? '700' : '500'; ?>;">
                                                <?php echo !empty($row['expires_at']) ? date('d M Y', strtotime($row['expires_at'])) : 'No Expiry'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.82rem;">
                                                <strong style="color: #0057FF;"><?php echo number_format($row['views_count']); ?></strong>
                                                <span style="color: #94a3b8;">/</span>
                                                <span style="color: #7c3aed; font-weight: 600;"><?php echo number_format($row['unique_views_count']); ?></span>
                                            </div>
                                        </td>
                                        <td style="text-align: right; padding-right: 1.5rem;">
                                            <div style="display: inline-flex; gap: 0.4rem; align-items: center;">
                                                
                                                <!-- 1. View / Analytics Modal Button -->
                                                <button type="button" class="btn-action" title="View Analytics &amp; Details" onclick='openAnalyticsModal(<?php echo json_encode($row, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                </button>

                                                <!-- 2. Preview Button -->
                                                <a href="announcement_preview.php?id=<?php echo $row['id']; ?>" class="btn-action" title="Preview Public Appearance">
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
                                                </a>

                                                <!-- 3. Edit Button -->
                                                <button type="button" class="btn-action" title="Edit Announcement" onclick='openEditModal(<?php echo json_encode($row, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'>
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                </button>

                                                <!-- 4. Quick Publish or Archive Toggle -->
                                                <?php if ($row['status'] === 'draft'): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Publish this announcement now? It will immediately appear on the public website.');">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="publish">
                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                        <button type="submit" class="btn-action" style="color: #059669; border-color: #a7f3d0;" title="Publish to Live Website">
                                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                        </button>
                                                    </form>
                                                <?php elseif ($row['status'] === 'published'): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Archive this active announcement?');">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="archive">
                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                        <button type="submit" class="btn-action" style="color: #64748b;" title="Archive Broadcast">
                                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line></svg>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- 5. Delete Action -->
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" class="btn-action" style="color: #ef4444;" title="Delete Announcement">
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                    </button>
                                                </form>

                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="padding: 4rem 2rem; text-align: center; color: #64748b;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: #eff6ff; color: #0057FF; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        </div>
                        <h3 style="font-family: var(--font-heading); color: #0f172a; margin-bottom: 0.5rem; font-size: 1.2rem;">No Announcements Found</h3>
                        <p style="margin-bottom: 1.5rem; font-size: 0.92rem;">No announcements match the selected filter criteria.</p>
                        <button type="button" onclick="openCreateModal()" class="btn btn-primary" style="background: #0057FF; border: none; padding: 0.65rem 1.4rem; border-radius: 9999px; font-weight: 700; color: #fff; cursor: pointer;">
                            Create First Announcement
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer indicator -->
            <?php include 'includes/footer.php'; ?>

        </div>
    </div>

    <!-- =========================================================================
         CREATE ANNOUNCEMENT MODAL
         ========================================================================= -->
    <div class="ann-modal" id="createAnnouncementModal">
        <div class="ann-modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem;">
                <h2 style="font-family: var(--font-heading); font-size: 1.35rem; font-weight: 700; margin: 0; color: #0f172a;">
                    Create Public Announcement
                </h2>
                <button type="button" onclick="closeModals()" style="background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer;">&times;</button>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create">

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 0.4rem;">
                        Announcement Title <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="title" required placeholder="e.g. 🎉 10% OFF Selected Interior Services" style="width: 100%; padding: 0.75rem 0.9rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; color: #0f172a;">
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 0.4rem;">
                        Message / Description <span style="color: #ef4444;">*</span>
                    </label>
                    <textarea name="message" required rows="4" placeholder="Full announcement copy seen by public visitors..." style="width: 100%; padding: 0.75rem 0.9rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; color: #0f172a; resize: vertical;"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">Type</label>
                        <select name="type" style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; color: #0f172a;">
                            <option value="promotion">Promotion (Offer / Discount)</option>
                            <option value="announcement" selected>Announcement</option>
                            <option value="update">Update (New Service / Feature)</option>
                            <option value="notice">Notice (Holiday / Schedule)</option>
                            <option value="system">System</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">Priority</label>
                        <select name="priority" style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; color: #0f172a;">
                            <option value="normal" selected>Normal</option>
                            <option value="important">Important</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">Status</label>
                        <select name="status" style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; color: #0f172a;">
                            <option value="draft" selected>Draft (Save &amp; Preview)</option>
                            <option value="published">Published (Live Immediately)</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">Start Date &amp; Time</label>
                        <input type="datetime-local" name="start_at" value="<?php echo date('Y-m-d\TH:i'); ?>" style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; color: #0f172a;">
                    </div>

                    <div>
                        <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">Expiry Date &amp; Time (Optional)</label>
                        <input type="datetime-local" name="expires_at" style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; color: #0f172a;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">Call-To-Action (CTA) Label</label>
                        <input type="text" name="cta_text" placeholder="e.g. Get a Quote, Explore Project" value="Get a Quote" style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; color: #0f172a;">
                    </div>

                    <div>
                        <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">CTA Target URL</label>
                        <input type="text" name="cta_url" placeholder="e.g. consultation.php, contact.php" value="consultation.php" style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; color: #0f172a;">
                    </div>
                </div>

                <div style="margin-bottom: 1.75rem;">
                    <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">
                        Cover Image (Optional &bull; JPG, PNG, WebP up to 5 MB)
                    </label>
                    <input type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.85rem; color: #0f172a;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.85rem; border-top: 1px solid #e2e8f0; padding-top: 1.25rem;">
                    <button type="button" onclick="closeModals()" class="btn" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.75rem 1.4rem; font-weight: 600; cursor: pointer;">
                        Cancel
                    </button>
                    <button type="submit" name="save_and_preview" value="1" class="btn" style="background: rgba(0, 87, 255, 0.1); color: #0057FF; border: 1px solid rgba(0, 87, 255, 0.25); border-radius: 8px; padding: 0.75rem 1.4rem; font-weight: 700; cursor: pointer;">
                        Save &amp; Preview &rarr;
                    </button>
                    <button type="submit" class="btn btn-primary" style="background: #0057FF; color: #ffffff; border: none; border-radius: 8px; padding: 0.75rem 1.6rem; font-weight: 700; cursor: pointer;">
                        Save Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================================
         EDIT ANNOUNCEMENT MODAL
         ========================================================================= -->
    <div class="ann-modal" id="editAnnouncementModal">
        <div class="ann-modal-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem;">
                <h2 style="font-family: var(--font-heading); font-size: 1.35rem; font-weight: 700; margin: 0; color: #0f172a;">
                    Edit Announcement &amp; Promotion
                </h2>
                <button type="button" onclick="closeModals()" style="background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer;">&times;</button>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id" value="">

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 0.4rem;">
                        Announcement Title <span style="color: #ef4444;">*</span>
                    </label>
                    <input type="text" name="title" id="edit_title" required style="width: 100%; padding: 0.75rem 0.9rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; color: #0f172a;">
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label style="display: block; font-weight: 700; font-size: 0.85rem; color: #334155; margin-bottom: 0.4rem;">
                        Message / Description <span style="color: #ef4444;">*</span>
                    </label>
                    <textarea name="message" id="edit_message" required rows="4" style="width: 100%; padding: 0.75rem 0.9rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; color: #0f172a; resize: vertical;"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">Type</label>
                        <select name="type" id="edit_type" style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; color: #0f172a;">
                            <option value="promotion">Promotion (Offer / Discount)</option>
                            <option value="announcement">Announcement</option>
                            <option value="update">Update (New Service / Feature)</option>
                            <option value="notice">Notice (Holiday / Schedule)</option>
                            <option value="system">System</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">Priority</label>
                        <select name="priority" id="edit_priority" style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; color: #0f172a;">
                            <option value="normal">Normal</option>
                            <option value="important">Important</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">Status</label>
                        <select name="status" id="edit_status" style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; color: #0f172a;">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="expired">Expired</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">Start Date &amp; Time</label>
                        <input type="datetime-local" name="start_at" id="edit_start_at" style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; color: #0f172a;">
                    </div>

                    <div>
                        <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">Expiry Date &amp; Time</label>
                        <input type="datetime-local" name="expires_at" id="edit_expires_at" style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; color: #0f172a;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">CTA Label</label>
                        <input type="text" name="cta_text" id="edit_cta_text" style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; color: #0f172a;">
                    </div>

                    <div>
                        <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">CTA Target URL</label>
                        <input type="text" name="cta_url" id="edit_cta_url" style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; color: #0f172a;">
                    </div>
                </div>

                <div style="margin-bottom: 1.75rem;">
                    <label style="display: block; font-weight: 700; font-size: 0.82rem; color: #334155; margin-bottom: 0.4rem;">
                        Replace Cover Image (Optional)
                    </label>
                    <input type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.85rem; color: #0f172a;">
                    <div id="edit_current_img_wrap" style="margin-top: 0.6rem; font-size: 0.8rem; color: #64748b; display: none;">
                        <span>Current Image: <a href="#" id="edit_current_img_link" target="_blank" style="color: #0057FF; font-weight: 600;">View Image</a></span>
                        <label style="margin-left: 1rem; color: #ef4444; font-weight: 600; cursor: pointer;">
                            <input type="checkbox" name="remove_image" value="1"> Remove Image
                        </label>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.85rem; border-top: 1px solid #e2e8f0; padding-top: 1.25rem;">
                    <button type="button" onclick="closeModals()" class="btn" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.75rem 1.4rem; font-weight: 600; cursor: pointer;">
                        Cancel
                    </button>
                    <button type="submit" name="save_and_preview" value="1" class="btn" style="background: rgba(0, 87, 255, 0.1); color: #0057FF; border: 1px solid rgba(0, 87, 255, 0.25); border-radius: 8px; padding: 0.75rem 1.4rem; font-weight: 700; cursor: pointer;">
                        Update &amp; Preview &rarr;
                    </button>
                    <button type="submit" class="btn btn-primary" style="background: #0057FF; color: #ffffff; border: none; border-radius: 8px; padding: 0.75rem 1.6rem; font-weight: 700; cursor: pointer;">
                        Update Announcement
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================================
         VIEW DETAILS & ANALYTICS MODAL
         ========================================================================= -->
    <div class="ann-modal" id="analyticsModal">
        <div class="ann-modal-content" style="max-width: 620px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem;">
                <h2 style="font-family: var(--font-heading); font-size: 1.35rem; font-weight: 700; margin: 0; color: #0f172a;">
                    Announcement Analytics &amp; Details
                </h2>
                <button type="button" onclick="closeModals()" style="background: none; border: none; font-size: 1.5rem; color: #94a3b8; cursor: pointer;">&times;</button>
            </div>

            <div id="analyticsContent">
                <!-- Populated via Javascript -->
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.85rem; border-top: 1px solid #e2e8f0; padding-top: 1.25rem; margin-top: 1.5rem;">
                <a href="#" id="analyticsPreviewLink" class="btn" style="background: #0057FF; color: #ffffff; text-decoration: none; border-radius: 8px; padding: 0.65rem 1.25rem; font-weight: 700; font-size: 0.85rem;">
                    Launch Public Preview &rarr;
                </a>
                <button type="button" onclick="closeModals()" class="btn" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.65rem 1.2rem; font-weight: 600; cursor: pointer;">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Modals & Interaction Logic Script -->
    <script>
    function openCreateModal() {
        document.getElementById('createAnnouncementModal').classList.add('active');
    }

    function openEditModal(data) {
        document.getElementById('edit_id').value = data.id || '';
        document.getElementById('edit_title').value = data.title || '';
        document.getElementById('edit_message').value = data.message || '';
        document.getElementById('edit_type').value = data.type || 'announcement';
        document.getElementById('edit_priority').value = data.priority || 'normal';
        document.getElementById('edit_status').value = data.status || 'draft';
        document.getElementById('edit_cta_text').value = data.cta_text || '';
        document.getElementById('edit_cta_url').value = data.cta_url || '';

        if (data.start_at) {
            document.getElementById('edit_start_at').value = data.start_at.replace(' ', 'T').substring(0, 16);
        } else {
            document.getElementById('edit_start_at').value = '';
        }

        if (data.expires_at) {
            document.getElementById('edit_expires_at').value = data.expires_at.replace(' ', 'T').substring(0, 16);
        } else {
            document.getElementById('edit_expires_at').value = '';
        }

        const imgWrap = document.getElementById('edit_current_img_wrap');
        const imgLink = document.getElementById('edit_current_img_link');
        if (data.cover_image) {
            imgLink.href = '../' + data.cover_image;
            imgWrap.style.display = 'block';
        } else {
            imgWrap.style.display = 'none';
        }

        document.getElementById('editAnnouncementModal').classList.add('active');
    }

    function openAnalyticsModal(data) {
        const previewUrl = 'announcement_preview.php?id=' + encodeURIComponent(data.id);
        document.getElementById('analyticsPreviewLink').href = previewUrl;

        const pubDate = data.created_at ? new Date(data.created_at).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'}) : '—';
        const expDate = data.expires_at ? new Date(data.expires_at).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'}) : 'No Expiration';

        const html = `
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem;">
                <div style="font-size: 0.75rem; text-transform: uppercase; color: #0057FF; font-weight: 800; letter-spacing: 0.05em; margin-bottom: 0.25rem;">
                    ${escapeHtml(data.type.toUpperCase())} &bull; ${escapeHtml(data.priority.toUpperCase())} PRIORITY
                </div>
                <h3 style="font-family: var(--font-heading); font-size: 1.2rem; margin: 0; color: #0f172a;">
                    ${escapeHtml(data.title)}
                </h3>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                    <div style="font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700;">Total Views</div>
                    <div style="font-size: 2rem; font-weight: 800; color: #0057FF; margin-top: 0.25rem;">${Number(data.views_count).toLocaleString()}</div>
                    <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem;">Total aggregated impressions</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                    <div style="font-size: 0.72rem; text-transform: uppercase; color: #7c3aed; font-weight: 700;">Approx. Unique Visitors</div>
                    <div style="font-size: 2rem; font-weight: 800; color: #7c3aed; margin-top: 0.25rem;">${Number(data.unique_views_count).toLocaleString()}</div>
                    <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.2rem;">Anonymous hash telemetry</div>
                </div>
            </div>

            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; font-size: 0.88rem; color: #334155;">
                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #64748b;">Current Status:</span>
                    <strong style="text-transform: capitalize; color: #0f172a;">${escapeHtml(data.status)}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #64748b;">Published Date:</span>
                    <strong style="color: #0f172a;">${pubDate}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #64748b;">Expiry Date:</span>
                    <strong style="color: #0f172a;">${expDate}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #64748b;">Call-to-Action (CTA):</span>
                    <strong style="color: #0057FF;">${escapeHtml(data.cta_text || 'None')} (${escapeHtml(data.cta_url || '')})</strong>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                    <span style="color: #64748b;">Audience Scope:</span>
                    <strong style="color: #059669;">All Public Visitors (No Login Required)</strong>
                </div>
            </div>
        `;
        document.getElementById('analyticsContent').innerHTML = html;
        document.getElementById('analyticsModal').classList.add('active');
    }

    function closeModals() {
        document.querySelectorAll('.ann-modal').forEach(m => m.classList.remove('active'));
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Close modal on click outside content or Escape key
    window.addEventListener('click', function(e) {
        document.querySelectorAll('.ann-modal').forEach(m => {
            if (e.target === m) {
                m.classList.remove('active');
            }
        });
    });

    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModals();
        }
    });

    // If query string has ?edit=ID, automatically open edit modal
    <?php if (isset($_GET['edit']) && (int)$_GET['edit'] > 0): ?>
        <?php 
            $eId = (int)$_GET['edit'];
            $eRes = $conn->query("SELECT * FROM announcements WHERE id = {$eId} AND deleted_at IS NULL LIMIT 1");
            if ($eRes && $eRow = $eRes->fetch_assoc()):
        ?>
            document.addEventListener('DOMContentLoaded', function() {
                openEditModal(<?php echo json_encode($eRow, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>);
            });
        <?php endif; ?>
    <?php endif; ?>
    </script>
</body>
</html>
