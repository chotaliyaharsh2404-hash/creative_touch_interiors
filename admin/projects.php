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
        
        // ADD PROJECT
        if ($_POST['action'] == 'add') {
            $title = sanitize($_POST['title'] ?? '');
            $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $description = sanitize($_POST['description'] ?? '');
            $category = sanitize($_POST['category'] ?? '');
            $location = sanitize($_POST['location'] ?? '');
            $area = sanitize($_POST['area'] ?? '');
            $design_style = sanitize($_POST['design_style'] ?? '');
            $completion_date = !empty($_POST['completion_date']) ? sanitize($_POST['completion_date']) : null;
            $budget = !empty($_POST['budget']) ? (float)$_POST['budget'] : null;
            $status = sanitize($_POST['status'] ?? 'completed');
            $featured = isset($_POST['featured']) ? 1 : 0;
            
            // Handle image upload
            $image = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                $targetDir = "../uploads/projects/";
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $realMime = mime_content_type($_FILES['image']['tmp_name']);
                $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                
                if (in_array($realMime, $allowedTypes) && in_array($extension, $allowedExtensions)) {
                    $fileName = time() . '_' . uniqid() . '.' . $extension;
                    $targetFilePath = $targetDir . $fileName;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                        $image = 'uploads/projects/' . $fileName;
                    }
                } else {
                    $error = "Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.";
                }
            }

            if (empty($error)) {
                $sql = "INSERT INTO projects (title, slug, description, category, location, area, design_style, completion_date, budget, status, featured, image) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssdsis", $title, $slug, $description, $category, $location, $area, $design_style, $completion_date, $budget, $status, $featured, $image);
                if ($stmt->execute()) {
                    $success = "Project added successfully!";
                } else {
                    $error = "Failed to add project.";
                }
            }
        }

        // EDIT PROJECT
        if ($_POST['action'] == 'edit') {
            $id = (int)$_POST['id'];
            $title = sanitize($_POST['title'] ?? '');
            $slug = strtolower(str_replace(' ', '-', preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $description = sanitize($_POST['description'] ?? '');
            $category = sanitize($_POST['category'] ?? '');
            $location = sanitize($_POST['location'] ?? '');
            $area = sanitize($_POST['area'] ?? '');
            $design_style = sanitize($_POST['design_style'] ?? '');
            $completion_date = !empty($_POST['completion_date']) ? sanitize($_POST['completion_date']) : null;
            $budget = !empty($_POST['budget']) ? (float)$_POST['budget'] : null;
            $status = sanitize($_POST['status'] ?? 'completed');
            $featured = isset($_POST['featured']) ? 1 : 0;

            // Check if new image uploaded
            $new_image = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                $targetDir = "../uploads/projects/";
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $realMime = mime_content_type($_FILES['image']['tmp_name']);
                $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                
                if (in_array($realMime, $allowedTypes) && in_array($extension, $allowedExtensions)) {
                    $fileName = time() . '_' . uniqid() . '.' . $extension;
                    $targetFilePath = $targetDir . $fileName;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                        $new_image = 'uploads/projects/' . $fileName;
                    }
                }
            }

            if ($new_image) {
                // Delete old image if existed
                $oldImgStmt = $conn->prepare("SELECT image FROM projects WHERE id = ?");
                $oldImgStmt->bind_param("i", $id);
                $oldImgStmt->execute();
                $oldRes = $oldImgStmt->get_result();
                if ($oldRow = $oldRes->fetch_assoc()) {
                    if (!empty($oldRow['image']) && file_exists('../' . $oldRow['image'])) {
                        @unlink('../' . $oldRow['image']);
                    }
                }

                $sql = "UPDATE projects SET title=?, slug=?, description=?, category=?, location=?, area=?, design_style=?, completion_date=?, budget=?, status=?, featured=?, image=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssdsisi", $title, $slug, $description, $category, $location, $area, $design_style, $completion_date, $budget, $status, $featured, $new_image, $id);
            } else {
                $sql = "UPDATE projects SET title=?, slug=?, description=?, category=?, location=?, area=?, design_style=?, completion_date=?, budget=?, status=?, featured=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssdsii", $title, $slug, $description, $category, $location, $area, $design_style, $completion_date, $budget, $status, $featured, $id);
            }

            if ($stmt->execute()) {
                $success = "Project details updated successfully!";
            } else {
                $error = "Failed to update project details.";
            }
        }
        
        // TOGGLE FEATURED
        if ($_POST['action'] == 'toggle_featured') {
            $id = (int)$_POST['id'];
            $featured = (int)$_POST['featured'] == 1 ? 0 : 1;
            $stmt = $conn->prepare("UPDATE projects SET featured = ? WHERE id = ?");
            $stmt->bind_param("ii", $featured, $id);
            if ($stmt->execute()) {
                $success = "Featured status updated!";
            }
        }

        // UPDATE STATUS
        if ($_POST['action'] == 'update_status') {
            $id = (int)$_POST['id'];
            $status = sanitize($_POST['status'] ?? 'completed');
            $stmt = $conn->prepare("UPDATE projects SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
            if ($stmt->execute()) {
                $success = "Project status updated successfully!";
            } else {
                $error = "Failed to update project status.";
            }
        }
        
        // DELETE PROJECT
        if ($_POST['action'] == 'delete') {
            $id = (int)$_POST['id'];
            
            // Delete project image from disk if exists
            $imgStmt = $conn->prepare("SELECT image FROM projects WHERE id = ?");
            $imgStmt->bind_param("i", $id);
            $imgStmt->execute();
            $imgRes = $imgStmt->get_result();
            if ($row = $imgRes->fetch_assoc()) {
                if (!empty($row['image']) && file_exists('../' . $row['image'])) {
                    @unlink('../' . $row['image']);
                }
            }
            
            $delStmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
            $delStmt->bind_param("i", $id);
            if ($delStmt->execute()) {
                $success = "Project deleted successfully!";
            } else {
                $error = "Failed to delete project.";
            }
        }
    }
}

// Fetch stats
$total_projects = $conn->query("SELECT COUNT(*) as c FROM projects")->fetch_assoc()['c'] ?? 0;
$completed_projects = $conn->query("SELECT COUNT(*) as c FROM projects WHERE status='completed'")->fetch_assoc()['c'] ?? 0;
$inprogress_projects = $conn->query("SELECT COUNT(*) as c FROM projects WHERE status='in_progress'")->fetch_assoc()['c'] ?? 0;
$featured_projects = $conn->query("SELECT COUNT(*) as c FROM projects WHERE featured=1")->fetch_assoc()['c'] ?? 0;

// Fetch all projects
$projects_res = $conn->query("SELECT * FROM projects ORDER BY created_at DESC");
$all_projects = [];
if ($projects_res) {
    while ($p = $projects_res->fetch_assoc()) {
        $all_projects[] = $p;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects Management - Admin</title>
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
        .table-thumb {
            width: 54px;
            height: 54px;
            border-radius: var(--radius-md);
            object-fit: cover;
            border: 1px solid var(--border-color);
            background: #f1f5f9;
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
            max-width: 680px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2.25rem;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
            border: 1px solid #bfdbfe;
            position: relative;
            color: #0f172a;
        }

        .btn-add-project {
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
        .btn-add-project:hover {
            background: #0046d6 !important;
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(0, 87, 255, 0.38);
        }
        .btn-add-project:active {
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
                        Projects Management
                    </h1>
                    <p style="color: var(--color-text-muted); font-size: 0.95rem; margin: 0;">
                        Curate, edit, and organize client portfolio case studies & architectural transformations.
                    </p>
                </div>
                
                <div>
                    <!-- Add New Project Button -->
                    <button type="button" onclick="openAddModal()" class="btn-add-project" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>Add New Project</span>
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
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: var(--color-text-main); line-height: 1.1; font-family: var(--font-heading);"><?php echo $total_projects; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">Total Projects</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #16a34a;">
                    <div class="metric-icon-box" style="background: #f0fdf4; color: #16a34a;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #059669; line-height: 1.1; font-family: var(--font-heading);"><?php echo $completed_projects; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">Completed Handover</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #d97706;">
                    <div class="metric-icon-box" style="background: #fffbeb; color: #d97706;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #d97706; line-height: 1.1; font-family: var(--font-heading);"><?php echo $inprogress_projects; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">In Active Progress</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #9333ea;">
                    <div class="metric-icon-box" style="background: #faf5ff; color: #9333ea;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #9333ea; line-height: 1.1; font-family: var(--font-heading);"><?php echo $featured_projects; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">Featured on Home</div>
                    </div>
                </div>

            </div>

            <!-- Main Table Card -->
            <div class="card" style="padding: 1.75rem 2rem; border-radius: var(--radius-2xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                
                <!-- Filter & Search Controls -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
                    
                    <!-- Chips -->
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="button" class="filter-chip active" onclick="filterTable('all', this)">All (<?php echo $total_projects; ?>)</button>
                        <button type="button" class="filter-chip" onclick="filterTable('residential', this)">Residential</button>
                        <button type="button" class="filter-chip" onclick="filterTable('commercial', this)">Commercial</button>
                        <button type="button" class="filter-chip" onclick="filterTable('office', this)">Office</button>
                        <button type="button" class="filter-chip" onclick="filterTable('retail', this)">Retail</button>
                        <button type="button" class="filter-chip" onclick="filterTable('featured', this)">Featured</button>
                    </div>

                    <!-- Search Input -->
                    <div style="position: relative; min-width: 240px;">
                        <input type="text" id="projectSearch" oninput="searchTable(this.value)" placeholder="Search projects..." class="form-control" style="padding-left: 2.35rem; font-size: 0.875rem; border-radius: 9999px;">
                        <span style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #94a3b8; display: flex; align-items: center;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </span>
                    </div>

                </div>

                <!-- Projects Table -->
                <div style="overflow-x: auto;">
                    <table class="table" style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">Cover</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">Project Details</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">Category</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">Status</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase; text-align: center;">Featured</th>
                                <th style="padding: 0.85rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="projectsTableBody">
                            <?php if (!empty($all_projects)): ?>
                                <?php foreach ($all_projects as $project): 
                                    $category_clean = strtolower($project['category'] ?? '');
                                    $is_feat = (int)($project['featured'] ?? 0);
                                    $status = $project['status'] ?? 'completed';
                                ?>
                                    <tr class="project-row" 
                                        data-category="<?php echo htmlspecialchars($category_clean); ?>"
                                        data-featured="<?php echo $is_feat; ?>"
                                        data-search="<?php echo htmlspecialchars(strtolower($project['title'] . ' ' . ($project['location'] ?? '') . ' ' . ($project['design_style'] ?? '') . ' ' . $category_clean)); ?>"
                                        style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                                        
                                        <!-- Cover Thumbnail -->
                                        <td style="padding: 1rem;">
                                            <?php if (!empty($project['image'])): ?>
                                                <img src="../<?php echo htmlspecialchars($project['image']); ?>" alt="Cover" class="table-thumb">
                                            <?php else: ?>
                                                <div class="table-thumb" style="display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="9" y1="22" x2="9" y2="22.01"></line><line x1="15" y1="22" x2="15" y2="22.01"></line></svg>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Title & Specs -->
                                        <td style="padding: 1rem;">
                                            <div style="font-weight: 700; color: var(--color-text-main); font-size: 0.975rem; margin-bottom: 0.25rem;">
                                                <?php echo htmlspecialchars($project['title']); ?>
                                            </div>
                                            <div style="font-size: 0.8rem; color: var(--color-text-muted); display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                                                <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-accent);"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                                    <?php echo htmlspecialchars($project['location'] ?? 'Not specified'); ?>
                                                </span>
                                                <?php if (!empty($project['area'])): ?>
                                                    <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.3 15.3l-8.6-8.6a2 2 0 0 0-2.8 0L2.7 13.9a2 2 0 0 0 0 2.8l2.6 2.6a2 2 0 0 0 2.8 0l7.2-7.2"></path></svg>
                                                        <?php echo htmlspecialchars($project['area']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($project['design_style'])): ?>
                                                    <span style="display: inline-flex; align-items: center; gap: 0.25rem;">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"></path><path d="M2 12h20"></path></svg>
                                                        <?php echo htmlspecialchars($project['design_style']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Category -->
                                        <td style="padding: 1rem;">
                                            <span style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 0.3rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                                <?php echo htmlspecialchars(ucfirst($project['category'])); ?>
                                            </span>
                                        </td>

                                        <!-- Status with Inline Switcher -->
                                        <td style="padding: 1rem;">
                                            <form method="POST" style="display: inline-block;">
                                                 <?php echo csrf_field(); ?>
                                                 <input type="hidden" name="action" value="update_status">
                                                 <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                                                 
                                                 <select name="status" onchange="this.form.submit()" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; font-weight: 600; border-radius: var(--radius-md); border: 1px solid #cbd5e1; background: <?php echo $status == 'completed' ? '#ecfdf5' : ($status == 'in_progress' ? '#fffbeb' : '#f8fafc'); ?>; color: <?php echo $status == 'completed' ? '#059669' : ($status == 'in_progress' ? '#d97706' : '#475569'); ?>; cursor: pointer;">
                                                     <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                     <option value="in_progress" <?php echo $status == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                     <option value="planned" <?php echo $status == 'planned' ? 'selected' : ''; ?>>Planned</option>
                                                 </select>
                                             </form>
                                         </td>

                                         <!-- Featured Switch -->
                                         <td style="padding: 1rem; text-align: center;">
                                             <form method="POST" style="display: inline-block;">
                                                 <?php echo csrf_field(); ?>
                                                 <input type="hidden" name="action" value="toggle_featured">
                                                 <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                                                 <input type="hidden" name="featured" value="<?php echo $is_feat; ?>">
                                                 <button type="submit" title="Click to toggle featured" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; transition: transform 0.2s; color: <?php echo $is_feat ? '#2563eb' : '#cbd5e1'; ?>;" onmouseover="this.style.transform='scale(1.2)'" onmouseout="this.style.transform='scale(1)'">
                                                     ★
                                                 </button>
                                             </form>
                                         </td>

                                         <!-- Action Buttons -->
                                         <td style="padding: 1rem; text-align: right;">
                                             <div style="display: inline-flex; gap: 0.4rem;">
                                                 <!-- Edit Project Modal Trigger -->
                                                 <button type="button" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($project)); ?>)" class="action-icon-btn" title="Edit Project Details">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                </button>

                                                <!-- Delete Project -->
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this project?');">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                                                    <button type="submit" class="action-icon-btn" title="Delete Project" style="color: #ef4444;">
                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 3rem; color: var(--color-text-muted);">
                                        No projects found in database. Click "Add New Project" to get started.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div id="noResultsMsg" style="display: none; text-align: center; padding: 3rem; color: var(--color-text-muted);">
                    No projects match your current filter query.
                </div>

            </div>
        </div>
    </div>

    <!-- ADD PROJECT MODAL -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--color-text-main); margin: 0; font-family: var(--font-heading); display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Add New Project
                </h2>
                <button type="button" onclick="closeAddModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="grid grid-2" style="gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Project Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Modern Minimalist Villa">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="residential">Residential</option>
                            <option value="commercial">Commercial</option>
                            <option value="office">Office</option>
                            <option value="retail">Retail</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">City / Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. Surat, Gujarat">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Area (Sq. Ft)</label>
                        <input type="text" name="area" class="form-control" placeholder="e.g. 3,500 sq ft">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Design Style</label>
                        <input type="text" name="design_style" class="form-control" placeholder="e.g. Scandinavian, Industrial">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Completion Date</label>
                        <input type="date" name="completion_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Budget (₹)</label>
                        <input type="number" name="budget" class="form-control" step="0.01" min="0" placeholder="e.g. 1500000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Initial Status</label>
                        <select name="status" class="form-control">
                            <option value="completed" selected>Completed</option>
                            <option value="in_progress">In Progress</option>
                            <option value="planned">Planned</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Project Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Overview of project brief, spatial highlights, and materials used..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Project Cover Image</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                    <small style="color: #64748b; font-size: 0.8rem;">Supported formats: JPG, PNG, WebP (Max 5MB)</small>
                </div>

                <div class="form-group" style="margin: 1rem 0 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" name="featured" value="1"> 
                        <span>Feature this project on the homepage showcase</span>
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
                        <span>Save & Publish Project</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT PROJECT MODAL -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--color-text-main); margin: 0; font-family: var(--font-heading); display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    Edit Project Details
                </h2>
                <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="grid grid-2" style="gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Project Title *</label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" id="edit_category" class="form-control" required>
                            <option value="residential">Residential</option>
                            <option value="commercial">Commercial</option>
                            <option value="office">Office</option>
                            <option value="retail">Retail</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">City / Location</label>
                        <input type="text" name="location" id="edit_location" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Area</label>
                        <input type="text" name="area" id="edit_area" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Design Style</label>
                        <input type="text" name="design_style" id="edit_design_style" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Completion Date</label>
                        <input type="date" name="completion_date" id="edit_completion_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Budget (₹)</label>
                        <input type="number" name="budget" id="edit_budget" class="form-control" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="completed">Completed</option>
                            <option value="in_progress">In Progress</option>
                            <option value="planned">Planned</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Project Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Replace Cover Image (Optional)</label>
                    <div id="edit_image_preview" style="margin-bottom: 0.5rem;"></div>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>

                <div class="form-group" style="margin: 1rem 0 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" name="featured" id="edit_featured" value="1"> 
                        <span>Feature this project on the homepage showcase</span>
                    </label>
                </div>

                <div style="display: flex; gap: 0.85rem; justify-content: flex-end; align-items: center; padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                    <!-- Cancel Button -->
                    <button type="button" onclick="closeEditModal()" class="btn-modal-cancel">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                        <span>Cancel</span>
                    </button>
                    
                    <!-- Update Project Button -->
                    <button type="submit" class="btn-modal-submit">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        <span>Update Project</span>
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
            const rows = document.querySelectorAll('.project-row');
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

        function openEditModal(project) {
            document.getElementById('edit_id').value = project.id;
            document.getElementById('edit_title').value = project.title || '';
            document.getElementById('edit_category').value = project.category || 'residential';
            document.getElementById('edit_location').value = project.location || '';
            document.getElementById('edit_area').value = project.area || '';
            document.getElementById('edit_design_style').value = project.design_style || '';
            document.getElementById('edit_completion_date').value = project.completion_date || '';
            document.getElementById('edit_budget').value = project.budget || '';
            document.getElementById('edit_status').value = project.status || 'completed';
            document.getElementById('edit_description').value = project.description || '';
            document.getElementById('edit_featured').checked = project.featured == 1;

            const previewBox = document.getElementById('edit_image_preview');
            if (project.image) {
                previewBox.innerHTML = '<div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #64748b;"><span>Current image:</span><img src="../' + project.image + '" style="height: 38px; border-radius: 4px;"></div>';
            } else {
                previewBox.innerHTML = '<span style="font-size: 0.8rem; color: #94a3b8;">No current image uploaded.</span>';
            }

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
