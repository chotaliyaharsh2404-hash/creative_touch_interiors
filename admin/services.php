<?php
require_once '../includes/config.php';

// Enforce admin authentication and prevent caching
requireAdminLogin('login.php');

// Handle form submissions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validate_csrf()) {
        $error = "Security token expired. Please try again.";
    } else if (isset($_POST['action'])) {
        
        // ADD SERVICE
        if ($_POST['action'] == 'add') {
            $title = sanitize($_POST['title'] ?? '');
            $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $description = sanitize($_POST['description'] ?? '');
            $category = sanitize($_POST['category'] ?? 'Residential');
            $icon = sanitize($_POST['icon'] ?? '🏠');
            $price_range = sanitize($_POST['price_range'] ?? '');
            $starting_price = (float)($_POST['starting_price'] ?? 0);
            $status = sanitize($_POST['status'] ?? 'active');
            $featured = isset($_POST['featured']) ? 1 : 0;

            if (empty($title)) {
                $error = "Service title is required.";
            } else {
                $sql = "INSERT INTO services (title, slug, description, category, icon, price_range, starting_price, featured, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssdis", $title, $slug, $description, $category, $icon, $price_range, $starting_price, $featured, $status);
                if ($stmt->execute()) {
                    $success = "New service added successfully!";
                } else {
                    $error = "Failed to add service: " . $conn->error;
                }
            }
        }

        // EDIT SERVICE
        if ($_POST['action'] == 'edit') {
            $id = (int)$_POST['id'];
            $title = sanitize($_POST['title'] ?? '');
            $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $description = sanitize($_POST['description'] ?? '');
            $category = sanitize($_POST['category'] ?? 'Residential');
            $icon = sanitize($_POST['icon'] ?? '🏠');
            $price_range = sanitize($_POST['price_range'] ?? '');
            $starting_price = (float)($_POST['starting_price'] ?? 0);
            $status = sanitize($_POST['status'] ?? 'active');
            $featured = isset($_POST['featured']) ? 1 : 0;

            if (empty($title)) {
                $error = "Service title is required.";
            } else {
                $sql = "UPDATE services SET title=?, slug=?, description=?, category=?, icon=?, price_range=?, starting_price=?, featured=?, status=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssdisi", $title, $slug, $description, $category, $icon, $price_range, $starting_price, $featured, $status, $id);
                if ($stmt->execute()) {
                    $success = "Service details updated successfully!";
                } else {
                    $error = "Failed to update service details.";
                }
            }
        }
        
        // TOGGLE FEATURED
        if ($_POST['action'] == 'toggle_featured') {
            $id = (int)$_POST['id'];
            $featured = (int)$_POST['featured'] == 1 ? 0 : 1;
            $stmt = $conn->prepare("UPDATE services SET featured = ? WHERE id = ?");
            $stmt->bind_param("ii", $featured, $id);
            if ($stmt->execute()) {
                $success = "Featured status updated!";
            }
        }

        // TOGGLE ACTIVE STATUS
        if ($_POST['action'] == 'toggle_status') {
            $id = (int)$_POST['id'];
            $newStatus = sanitize($_POST['status'] ?? 'active') === 'active' ? 'inactive' : 'active';
            $stmt = $conn->prepare("UPDATE services SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $id);
            if ($stmt->execute()) {
                $success = "Service is now " . ucfirst($newStatus) . "!";
            }
        }

        // DELETE SERVICE
        if ($_POST['action'] == 'delete') {
            $id = (int)$_POST['id'];
            $delStmt = $conn->prepare("DELETE FROM services WHERE id = ?");
            $delStmt->bind_param("i", $id);
            if ($delStmt->execute()) {
                $success = "Service removed successfully!";
            } else {
                $error = "Failed to delete service.";
            }
        }
    }
}

// Fetch stats
$total_services = $conn->query("SELECT COUNT(*) as c FROM services")->fetch_assoc()['c'] ?? 0;
$active_services = $conn->query("SELECT COUNT(*) as c FROM services WHERE status='active'")->fetch_assoc()['c'] ?? 0;
$featured_services = $conn->query("SELECT COUNT(*) as c FROM services WHERE featured=1")->fetch_assoc()['c'] ?? 0;
$residential_services = $conn->query("SELECT COUNT(*) as c FROM services WHERE LOWER(category) LIKE '%residential%'")->fetch_assoc()['c'] ?? 0;
$commercial_services = $conn->query("SELECT COUNT(*) as c FROM services WHERE LOWER(category) LIKE '%commercial%' OR LOWER(category) LIKE '%office%' OR LOWER(category) LIKE '%retail%'")->fetch_assoc()['c'] ?? 0;

// Fetch all services
$services_res = $conn->query("SELECT * FROM services ORDER BY id ASC");
$all_services = [];
if ($services_res) {
    while ($s = $services_res->fetch_assoc()) {
        $all_services[] = $s;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Management - Admin</title>
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
        .icon-badge {
            width: 46px;
            height: 46px;
            border-radius: var(--radius-md);
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            color: #2563eb;
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
            max-width: 620px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2.25rem;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
            border: 1px solid #bfdbfe;
            position: relative;
            color: #0f172a;
        }

        .btn-add-service {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.6rem;
            border-radius: 9999px;
            font-size: 0.9rem;
            font-weight: 700;
            color: #ffffff !important;
            background: #0057FF;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(0, 87, 255, 0.28), 0 2px 6px rgba(15, 23, 42, 0.05);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .btn-add-service:hover {
            background: #0046d6 !important;
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(0, 87, 255, 0.38);
        }
        .btn-add-service:active {
            transform: translateY(0);
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
        .btn-modal-cancel:active {
            transform: translateY(0);
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
        .btn-modal-submit:hover svg {
            transform: scale(1.08);
        }
        .btn-modal-submit:active {
            transform: translateY(0);
        }
        .btn-modal-submit svg {
            transition: transform 0.2s ease;
        }
    </style>
</head>
<body style="background: #F8F7F4; color: #0f172a;">
    <div style="display: flex; min-height: 100vh;">
        
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-content" style="flex: 1; padding: 2.25rem 2.5rem;">
            
            <!-- Header Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-size: 2.15rem; color: var(--color-text-main); font-weight: 800; margin: 0 0 0.35rem; font-family: var(--font-heading); letter-spacing: -0.01em;">
                        Services Management
                    </h1>
                    <p style="color: var(--color-text-muted); font-size: 0.95rem; margin: 0;">
                        Configure architectural services, investment estimates, and client package offerings.
                    </p>
                </div>
                
                <div>
                    <!-- Add New Service Button -->
                    <button type="button" onclick="openAddModal()" class="btn-add-service" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>Add New Service</span>
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
                
                <div class="metric-card" style="border-left: 4px solid var(--color-primary);">
                    <div class="metric-icon-box" style="background: #eff6ff; color: #2563eb;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-text-main); line-height: 1.1; font-family: var(--font-heading);"><?php echo $total_services; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">Active Services</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #9333ea;">
                    <div class="metric-icon-box" style="background: #faf5ff; color: #9333ea;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #9333ea; line-height: 1.1; font-family: var(--font-heading);"><?php echo $featured_services; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">Featured Offerings</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #059669;">
                    <div class="metric-icon-box" style="background: #ecfdf5; color: #059669;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #059669; line-height: 1.1; font-family: var(--font-heading);"><?php echo $residential_services; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">Residential Packages</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #d97706;">
                    <div class="metric-icon-box" style="background: #fffbeb; color: #d97706;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="9" y1="22" x2="9" y2="22.01"></line><line x1="15" y1="22" x2="15" y2="22.01"></line><line x1="9" y1="6" x2="9" y2="6.01"></line><line x1="15" y1="6" x2="15" y2="6.01"></line></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #d97706; line-height: 1.1; font-family: var(--font-heading);"><?php echo $commercial_services; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">Commercial & Offices</div>
                    </div>
                </div>

            </div>

            <!-- Main Table Card -->
            <div class="card" style="padding: 1.75rem 2rem; border-radius: var(--radius-2xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                
                <!-- Filter & Search Controls -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
                    
                    <!-- Chips -->
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="button" class="filter-chip active" onclick="filterTable('all', this)">All Services (<?php echo $total_services; ?>)</button>
                        <button type="button" class="filter-chip" onclick="filterTable('active', this)">Active (<?php echo $active_services; ?>)</button>
                        <button type="button" class="filter-chip" onclick="filterTable('residential', this)">Residential</button>
                        <button type="button" class="filter-chip" onclick="filterTable('commercial', this)">Commercial</button>
                        <button type="button" class="filter-chip" onclick="filterTable('retail', this)">Retail</button>
                        <button type="button" class="filter-chip" onclick="filterTable('featured', this)">Featured</button>
                    </div>

                    <!-- Search Input -->
                    <div style="position: relative; min-width: 240px;">
                        <input type="text" id="serviceSearch" oninput="searchTable(this.value)" placeholder="Search services..." class="form-control" style="padding-left: 2.35rem; font-size: 0.875rem; border-radius: 9999px;">
                        <span style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #94a3b8; display: flex; align-items: center;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </span>
                    </div>

                </div>

                <!-- Services Table -->
                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase; width: 60px;">Icon</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">Service Title & Scope</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">Category</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">Price Range / Start</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase; text-align: center;">Status</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase; text-align: center;">Featured</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="servicesTableBody">
                            <?php if (!empty($all_services)): ?>
                                <?php foreach ($all_services as $service): 
                                    $category_clean = strtolower($service['category'] ?? '');
                                    $is_feat = (int)($service['featured'] ?? 0);
                                    $status_clean = strtolower($service['status'] ?? 'active');
                                ?>
                                    <tr class="service-row" 
                                        data-category="<?php echo htmlspecialchars($category_clean); ?>"
                                        data-status="<?php echo htmlspecialchars($status_clean); ?>"
                                        data-featured="<?php echo $is_feat; ?>"
                                        data-search="<?php echo htmlspecialchars(strtolower($service['title'] . ' ' . ($service['description'] ?? '') . ' ' . ($service['price_range'] ?? '') . ' ' . $category_clean . ' ' . $status_clean)); ?>"
                                        style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                                        
                                        <!-- Icon Badge -->
                                        <td style="padding: 1rem;">
                                            <div class="icon-badge">
                                                <?php echo htmlspecialchars($service['icon'] ?? '🏠'); ?>
                                            </div>
                                        </td>

                                        <!-- Title & Scope -->
                                        <td style="padding: 1rem;">
                                            <div style="font-weight: 700; color: var(--color-text-main); font-size: 0.975rem; margin-bottom: 0.25rem;">
                                                <?php echo htmlspecialchars($service['title']); ?>
                                            </div>
                                            <div style="font-size: 0.825rem; color: var(--color-text-muted); line-height: 1.4; max-width: 450px;">
                                                <?php echo htmlspecialchars(substr($service['description'] ?? '', 0, 110)) . (strlen($service['description'] ?? '') > 110 ? '...' : ''); ?>
                                            </div>
                                        </td>

                                        <!-- Category -->
                                        <td style="padding: 1rem;">
                                            <span style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 0.3rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                                <?php echo htmlspecialchars(ucfirst($service['category'] ?? 'General')); ?>
                                            </span>
                                        </td>

                                        <!-- Price Range & Starting Rate -->
                                        <td style="padding: 1rem;">
                                            <?php if (!empty($service['price_range'])): ?>
                                                <div style="font-weight: 700; color: #10b981; font-size: 0.875rem;">
                                                    <?php echo htmlspecialchars($service['price_range']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($service['starting_price']) && (float)$service['starting_price'] > 0): ?>
                                                <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.15rem;">
                                                    Base: ₹<?php echo number_format($service['starting_price'], 2); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Status Toggle Button -->
                                        <td style="padding: 1rem; text-align: center;">
                                            <form method="POST" style="display: inline-block;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                                                <input type="hidden" name="status" value="<?php echo $status_clean; ?>">
                                                <button type="submit" title="Click to toggle Active/Inactive" style="background: none; border: none; cursor: pointer; padding: 0;">
                                                    <?php if ($status_clean === 'active'): ?>
                                                        <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;">
                                                            ✓ Active
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="background: rgba(255, 255, 255, 0.05); color: #94a3b8; border: 1px solid rgba(255, 255, 255, 0.1); padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">
                                                            Inactive
                                                        </span>
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                        </td>

                                        <!-- Featured Switch -->
                                        <td style="padding: 1rem; text-align: center;">
                                            <form method="POST" style="display: inline-block;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="toggle_featured">
                                                <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                                                <input type="hidden" name="featured" value="<?php echo $is_feat; ?>">
                                                <button type="submit" title="Click to toggle featured" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                                    <?php echo $is_feat ? '⭐' : '☆'; ?>
                                                </button>
                                            </form>
                                        </td>

                                        <!-- Actions -->
                                        <td style="padding: 1rem; text-align: right;">
                                            <div style="display: inline-flex; gap: 0.4rem;">
                                                <!-- Edit Service Modal Trigger -->
                                                <button type="button" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($service)); ?>)" class="action-icon-btn" title="Edit Service Details">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                </button>

                                                <!-- Delete Service -->
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this service offering?');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                                                    <button type="submit" class="action-icon-btn" title="Delete Service" style="color: #ef4444;">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 3rem; color: var(--color-text-muted);">
                                        No services found in catalog. Click "Add New Service" to configure offerings.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div id="noResultsMsg" style="display: none; text-align: center; padding: 3rem; color: var(--color-text-muted);">
                    No services match your search query.
                </div>

            </div>
        </div>
    </div>

    <!-- ADD SERVICE MODAL -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--color-text-main); margin: 0; font-family: var(--font-heading); display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Add New Service
                </h2>
                <button type="button" onclick="closeAddModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
            </div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="grid grid-2" style="gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Service Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Turnkey Villa Interiors">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-control" required>
                            <option value="Residential">Residential</option>
                            <option value="Commercial">Commercial</option>
                            <option value="Office">Office</option>
                            <option value="Retail">Retail</option>
                            <option value="Design Service">Design Service</option>
                            <option value="Consultation">Consultation</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Icon / Emoji</label>
                        <input type="text" name="icon" class="form-control" placeholder="🏠 or 🏢, ✨, 🛍️" value="🏠">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Starting Price (₹ for Estimation)</label>
                        <input type="number" step="0.01" name="starting_price" class="form-control" placeholder="e.g. 150000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Price Range</label>
                        <input type="text" name="price_range" class="form-control" placeholder="e.g. ₹8L – ₹25L">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quote System Status *</label>
                        <select name="status" class="form-control" required>
                            <option value="active">Active (Available on Get Quote Form)</option>
                            <option value="inactive">Inactive (Hidden from Get Quote Form)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Service Scope & Description</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Detail what is included (3D plans, material selection, execution, supervision)..."></textarea>
                </div>

                <div class="form-group" style="margin: 1rem 0 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" name="featured" value="1"> 
                        <span>Feature this package on homepage & services overview</span>
                    </label>
                </div>

                <div style="display: flex; gap: 0.85rem; justify-content: flex-end; align-items: center; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                    <button type="button" onclick="closeAddModal()" class="btn-modal-cancel">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        <span>Cancel</span>
                    </button>
                    <button type="submit" class="btn-modal-submit">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>Save & Publish Service</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT SERVICE MODAL -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--color-text-main); margin: 0; font-family: var(--font-heading); display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    Edit Service Offering
                </h2>
                <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
            </div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="grid grid-2" style="gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Service Title *</label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" id="edit_category" class="form-control" required>
                            <option value="Residential">Residential</option>
                            <option value="Commercial">Commercial</option>
                            <option value="Office">Office</option>
                            <option value="Retail">Retail</option>
                            <option value="Design Service">Design Service</option>
                            <option value="Consultation">Consultation</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Icon / Emoji</label>
                        <input type="text" name="icon" id="edit_icon" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Starting Price (₹ for Estimation)</label>
                        <input type="number" step="0.01" name="starting_price" id="edit_starting_price" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Price Range</label>
                        <input type="text" name="price_range" id="edit_price_range" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Quote System Status *</label>
                        <select name="status" id="edit_status" class="form-control" required>
                            <option value="active">Active (Available on Get Quote Form)</option>
                            <option value="inactive">Inactive (Hidden from Get Quote Form)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Service Scope & Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="4"></textarea>
                </div>

                <div class="form-group" style="margin: 1rem 0 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" name="featured" id="edit_featured" value="1"> 
                        <span>Feature this package on homepage & services overview</span>
                    </label>
                </div>

                <div style="display: flex; gap: 0.85rem; justify-content: flex-end; align-items: center; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                    <button type="button" onclick="closeEditModal()" class="btn-modal-cancel">
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
                        <span>Update Service</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentFilter = 'all';
        let searchQuery = '';

        function filterTable(filter, btn) {
            currentFilter = filter;
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            if (btn) btn.classList.add('active');
            applyFilters();
        }

        function searchTable(query) {
            searchQuery = query.toLowerCase().trim();
            applyFilters();
        }

        function applyFilters() {
            const rows = document.querySelectorAll('.service-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const category = row.getAttribute('data-category') || '';
                const featured = row.getAttribute('data-featured') || '0';
                const search = row.getAttribute('data-search') || '';

                let matchFilter = false;
                if (currentFilter === 'all') matchFilter = true;
                else if (currentFilter === 'featured') matchFilter = featured === '1';
                else matchFilter = category.includes(currentFilter);

                const matchSearch = searchQuery === '' || search.includes(searchQuery);

                if (matchFilter && matchSearch) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('noResultsMsg').style.display = visibleCount === 0 ? 'block' : 'none';
        }

        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function openEditModal(service) {
            document.getElementById('edit_id').value = service.id;
            document.getElementById('edit_title').value = service.title || '';
            document.getElementById('edit_category').value = service.category || 'Residential';
            document.getElementById('edit_icon').value = service.icon || '🏠';
            document.getElementById('edit_starting_price').value = service.starting_price || '';
            document.getElementById('edit_price_range').value = service.price_range || '';
            document.getElementById('edit_status').value = service.status || 'active';
            document.getElementById('edit_description').value = service.description || '';
            document.getElementById('edit_featured').checked = service.featured == 1;

            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        // Close modal on outside click
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                closeAddModal();
                closeEditModal();
            }
        });
    </script>
    <script src="../js/vendor/three.min.js"></script>
    <script src="../js/vendor/gsap.min.js"></script>
    <script src="../js/spatial-3d.js"></script>
</body>
</html>
