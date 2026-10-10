<?php
require_once '../includes/config.php';

// Enforce admin authentication and prevent caching
requireAdminLogin('login.php');

// Handle form submissions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isReceptionist()) {
        $error = "Access denied: Receptionists have view-only access to the gallery.";
    } else if (!validate_csrf()) {
        $error = "Security token expired. Please try again.";
    } else if (isset($_POST['action'])) {
        
        // ADD IMAGE
        if ($_POST['action'] == 'add') {
            $title = sanitize($_POST['title'] ?? '');
            $category = sanitize($_POST['category'] ?? 'living_room');
            $featured = isset($_POST['featured']) ? 1 : 0;

            // Handle file upload
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $real_mime = mime_content_type($_FILES['image_file']['tmp_name']);
                $extension = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));

                if (!in_array($real_mime, $allowed_mimes) || !in_array($extension, $allowed_extensions)) {
                    $error = "Invalid file type. Only JPG, PNG, WEBP, and GIF images are allowed.";
                } else {
                    $file_name = time() . '_' . uniqid() . '.' . $extension;
                    $file_path = $upload_dir . $file_name;

                    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $file_path)) {
                        $image_path = 'uploads/' . $file_name;

                        $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
                        $sql = "INSERT INTO gallery_images (title, image_path, category, featured, project_id) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sssii", $title, $image_path, $category, $featured, $project_id);

                        if ($stmt->execute()) {
                            $success = "New visual added to gallery successfully!";
                        } else {
                            $error = "Database error adding visual.";
                            @unlink($file_path);
                        }
                    } else {
                        $error = "Error saving uploaded file.";
                    }
                }
            } else {
                $error = "Please select a valid image file to upload.";
            }
        }

        // EDIT IMAGE
        if ($_POST['action'] == 'edit') {
            $id = (int)$_POST['id'];
            $title = sanitize($_POST['title'] ?? '');
            $category = sanitize($_POST['category'] ?? 'living_room');
            $featured = isset($_POST['featured']) ? 1 : 0;

            $new_image = null;
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $real_mime = mime_content_type($_FILES['image_file']['tmp_name']);
                $extension = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));

                if (in_array($real_mime, $allowed_mimes) && in_array($extension, $allowed_extensions)) {
                    $file_name = time() . '_' . uniqid() . '.' . $extension;
                    $file_path = $upload_dir . $file_name;

                    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $file_path)) {
                        $new_image = 'uploads/' . $file_name;
                    }
                }
            }

            if ($new_image) {
                // Delete old image file
                $imgStmt = $conn->prepare("SELECT image_path FROM gallery_images WHERE id = ?");
                $imgStmt->bind_param("i", $id);
                $imgStmt->execute();
                $res = $imgStmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    if (!empty($row['image_path']) && file_exists('../' . $row['image_path'])) {
                        @unlink('../' . $row['image_path']);
                    }
                }

                $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
                $sql = "UPDATE gallery_images SET title=?, category=?, featured=?, image_path=?, project_id=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssisii", $title, $category, $featured, $new_image, $project_id, $id);
            } else {
                $project_id = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
                $sql = "UPDATE gallery_images SET title=?, category=?, featured=?, project_id=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssiii", $title, $category, $featured, $project_id, $id);
            }

            if ($stmt->execute()) {
                $success = "Gallery visual details updated!";
            } else {
                $error = "Failed to update visual details.";
            }
        }
        
        // TOGGLE FEATURED
        if ($_POST['action'] == 'toggle_featured') {
            $id = (int)$_POST['id'];
            $featured = (int)$_POST['featured'] == 1 ? 0 : 1;
            $stmt = $conn->prepare("UPDATE gallery_images SET featured = ? WHERE id = ?");
            $stmt->bind_param("ii", $featured, $id);
            if ($stmt->execute()) {
                $success = "Featured status updated!";
            }
        }

        // DELETE IMAGE
        if ($_POST['action'] == 'delete') {
            $id = (int)$_POST['id'];
            
            // Delete file from disk
            $imgStmt = $conn->prepare("SELECT image_path FROM gallery_images WHERE id = ?");
            $imgStmt->bind_param("i", $id);
            $imgStmt->execute();
            $res = $imgStmt->get_result();
            if ($row = $res->fetch_assoc()) {
                if (!empty($row['image_path']) && file_exists('../' . $row['image_path'])) {
                    @unlink('../' . $row['image_path']);
                }
            }
            
            $stmt = $conn->prepare("DELETE FROM gallery_images WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Visual removed from gallery!";
            } else {
                $error = "Failed to delete visual.";
            }
        }
    }
}

// Fetch stats
$total_visuals = $conn->query("SELECT COUNT(*) as c FROM gallery_images")->fetch_assoc()['c'] ?? 0;
$featured_visuals = $conn->query("SELECT COUNT(*) as c FROM gallery_images WHERE featured=1")->fetch_assoc()['c'] ?? 0;
$living_bedroom = $conn->query("SELECT COUNT(*) as c FROM gallery_images WHERE category IN ('living_room', 'bedroom')")->fetch_assoc()['c'] ?? 0;
$kitchen_bath_office = $conn->query("SELECT COUNT(*) as c FROM gallery_images WHERE category IN ('kitchen', 'bathroom', 'office')")->fetch_assoc()['c'] ?? 0;

// Fetch all gallery images
$gallery_res = $conn->query("SELECT * FROM gallery_images ORDER BY order_index ASC, id DESC");
$all_visuals = [];
if ($gallery_res) {
    while ($g = $gallery_res->fetch_assoc()) {
        $all_visuals[] = $g;
    }
}

// Fetch projects list for association dropdown
$projects_list = [];
$pRes = $conn->query("SELECT id, title FROM projects ORDER BY title ASC");
if ($pRes) {
    while ($pr = $pRes->fetch_assoc()) {
        $projects_list[] = $pr;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery Management - Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/spatial-3d.css">
    <style>
        .metric-card {
            background: rgba(14, 18, 26, 0.88);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
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
        .gallery-admin-card {
            background: #ffffff;
            border-radius: var(--radius-xl);
            border: 1px solid var(--border-color);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease, border-color 0.25s ease;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .gallery-admin-card:hover {
            transform: translateY(-4px);
            border-color: #93c5fd;
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.12);
        }
        .gallery-thumb-wrap {
            height: 185px;
            width: 100%;
            position: relative;
            overflow: hidden;
            background: #f1f5f9;
        }
        .gallery-thumb-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.35s ease;
        }
        .gallery-admin-card:hover .gallery-thumb-img {
            transform: scale(1.05);
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
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
            color: #ffffff !important;
            border-color: #1d4ed8;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
            font-weight: 700;
        }
        .action-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: var(--radius-md);
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            font-size: 0.9rem;
        }
        .action-icon-btn:hover {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #2563eb;
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
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2.25rem;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
            border: 1px solid #bfdbfe;
            position: relative;
            color: #0f172a;
        }

        .btn-add-visual {
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
        .btn-add-visual:hover {
            background: #0046d6 !important;
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(0, 87, 255, 0.38);
        }
        .btn-add-visual:active {
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
            
            <!-- Executive Header with Avatar & Dropdown -->
            <?php include 'includes/header.php'; ?>

            <!-- Header Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-family: var(--font-heading); font-size: 2.15rem; color: var(--color-text-main); font-weight: 700; margin: 0 0 0.35rem; display: flex; align-items: center; gap: 0.75rem;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: var(--radius-lg); background: #eff6ff; color: #2563eb;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                <polyline points="21 15 16 10 5 21"></polyline>
                            </svg>
                        </span>
                        <span>Gallery Management</span>
                    </h1>
                    <p style="color: var(--color-text-muted); font-size: 0.95rem; margin: 0;">
                        Manage spatial aesthetics, room concepts, and public lookbook visual collections.
                    </p>
                </div>
                
                <div>
                    <?php if (!isReceptionist()): ?>
                    <!-- Add New Visual Button -->
                    <button type="button" onclick="openAddModal()" class="btn-add-visual" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>Add New Visual</span>
                    </button>
                    <?php endif; ?>
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
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #1d4ed8; line-height: 1.1; font-family: var(--font-heading);"><?php echo $total_visuals; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">Total Visuals</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #d97706;">
                    <div class="metric-icon-box" style="background: #fffbeb; color: #d97706;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #d97706; line-height: 1.1; font-family: var(--font-heading);"><?php echo $featured_visuals; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">Featured Lookbook</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #059669;">
                    <div class="metric-icon-box" style="background: #ecfdf5; color: #059669;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #059669; line-height: 1.1; font-family: var(--font-heading);"><?php echo $living_bedroom; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">Living & Bedrooms</div>
                    </div>
                </div>

                <div class="metric-card" style="border-left: 4px solid #9333ea;">
                    <div class="metric-icon-box" style="background: #faf5ff; color: #9333ea;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>
                    </div>
                    <div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #9333ea; line-height: 1.1; font-family: var(--font-heading);"><?php echo $kitchen_bath_office; ?></div>
                        <div style="font-size: 0.825rem; color: var(--color-text-muted); font-weight: 600; margin-top: 0.2rem;">Kitchen, Bath & Office</div>
                    </div>
                </div>

            </div>

            <!-- Gallery Controls & Showcase Card -->
            <div class="card" style="padding: 1.75rem 2rem; border-radius: var(--radius-2xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); background: #ffffff;">
                
                <!-- Filter & Search Controls -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
                    
                    <!-- Chips -->
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="button" class="filter-chip active" onclick="filterGallery('all', this)">All (<?php echo $total_visuals; ?>)</button>
                        <button type="button" class="filter-chip" onclick="filterGallery('living_room', this)">Living Room</button>
                        <button type="button" class="filter-chip" onclick="filterGallery('bedroom', this)">Bedroom</button>
                        <button type="button" class="filter-chip" onclick="filterGallery('kitchen', this)">Kitchen</button>
                        <button type="button" class="filter-chip" onclick="filterGallery('bathroom', this)">Bathroom</button>
                        <button type="button" class="filter-chip" onclick="filterGallery('office', this)">Office</button>
                        <button type="button" class="filter-chip" onclick="filterGallery('featured', this)">Featured</button>
                    </div>

                    <!-- Search Input -->
                    <div style="position: relative; min-width: 240px;">
                        <input type="text" id="gallerySearch" oninput="searchGallery(this.value)" placeholder="Search visual titles..." class="form-control" style="padding-left: 2.4rem; font-size: 0.875rem; border-radius: 9999px;">
                        <span style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: #94a3b8; display: flex; align-items: center; pointer-events: none;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </span>
                    </div>

                </div>

                <!-- Visual Grid -->
                <div id="galleryGrid" class="grid grid-4" style="gap: 1.25rem;">
                    <?php if (!empty($all_visuals)): ?>
                        <?php foreach ($all_visuals as $item): 
                            $cat_clean = strtolower($item['category'] ?? '');
                            $is_feat = (int)($item['featured'] ?? 0);
                            $cat_label = ucfirst(str_replace('_', ' ', $item['category'] ?? 'General'));
                        ?>
                            <div class="gallery-admin-card visual-card-item"
                                 data-category="<?php echo htmlspecialchars($cat_clean); ?>"
                                 data-featured="<?php echo $is_feat; ?>"
                                 data-search="<?php echo htmlspecialchars(strtolower($item['title'] . ' ' . $cat_clean)); ?>">
                                 
                                <div class="gallery-thumb-wrap">
                                    <img src="../<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="gallery-thumb-img">
                                    
                                    <!-- Category Badge Floating Top Left -->
                                    <div style="position: absolute; top: 0.75rem; left: 0.75rem; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(8px); border: 1px solid #bfdbfe; color: #2563eb; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.725rem; font-weight: 700; box-shadow: 0 2px 6px rgba(0,0,0,0.06);">
                                        <?php echo htmlspecialchars($cat_label); ?>
                                    </div>

                                    <!-- Featured Star Floating Top Right -->
                                    <?php if (!isReceptionist()): ?>
                                    <form method="POST" style="position: absolute; top: 0.65rem; right: 0.65rem;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle_featured">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                        <input type="hidden" name="featured" value="<?php echo $is_feat; ?>">
                                        <button type="submit" title="<?php echo $is_feat ? 'Remove from featured' : 'Mark as featured'; ?>" style="background: rgba(255, 255, 255, 0.95); border: 1px solid #cbd5e1; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: var(--shadow-sm); color: <?php echo $is_feat ? '#2563eb' : '#94a3b8'; ?>; transition: transform 0.15s ease;">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="<?php echo $is_feat ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                            </svg>
                                        </button>
                                    </form>
                                    <?php elseif ($is_feat): ?>
                                    <div style="position: absolute; top: 0.65rem; right: 0.65rem; background: rgba(255, 255, 255, 0.95); border: 1px solid #cbd5e1; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; color: #2563eb; box-shadow: var(--shadow-sm);" title="Featured">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                        </svg>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div style="padding: 1rem 1.15rem; display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; flex: 1;">
                                    <div style="font-weight: 700; font-size: 0.9rem; color: var(--color-text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($item['title']); ?>
                                    </div>
                                    
                                    <?php if (!isReceptionist()): ?>
                                    <div style="display: flex; gap: 0.35rem; flex-shrink: 0;">
                                        <!-- Edit Modal Trigger -->
                                        <button type="button" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>)" class="action-icon-btn" title="Edit Visual Title & Category" style="color: #2563eb;">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>

                                        <!-- Delete Visual -->
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently remove this visual from the gallery?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="action-icon-btn" title="Delete Visual" style="color: #f87171;">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                    <line x1="10" y1="11" x2="10" y2="17"></line>
                                                    <line x1="14" y1="11" x2="14" y2="17"></line>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                    <?php else: ?>
                                        <span style="font-size: 0.75rem; color: var(--color-text-muted); font-weight: 500;">View Only</span>
                                    <?php endif; ?>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--color-text-muted);">
                            No visuals uploaded in gallery. Click "Add New Visual" to upload photographs.
                        </div>
                    <?php endif; ?>
                </div>

                <div id="noResultsMsg" style="display: none; text-align: center; padding: 3rem; color: var(--color-text-muted);">
                    No visuals match your active filter or search query.
                </div>

            </div>
        </div>
    </div>

    <?php if (!isReceptionist()): ?>
    <!-- ADD VISUAL MODAL -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-family: var(--font-heading); font-size: 1.5rem; font-weight: 700; color: var(--color-text-main); margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Add New Visual</span>
                </h2>
                <button type="button" onclick="closeAddModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">
                
                <div class="form-group" style="margin-bottom: 1.15rem;">
                    <label class="form-label">Visual Title *</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Minimalist Italian Kitchen Concept">
                </div>

                <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1.15rem;">
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-control" required>
                            <option value="living_room">Living Room</option>
                            <option value="bedroom">Bedroom</option>
                            <option value="kitchen">Kitchen</option>
                            <option value="bathroom">Bathroom</option>
                            <option value="office">Office</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Associated Project (Optional)</label>
                        <select name="project_id" class="form-control">
                            <option value="">— None (Independent Visual) —</option>
                            <?php foreach ($projects_list as $pItem): ?>
                                <option value="<?php echo $pItem['id']; ?>"><?php echo htmlspecialchars($pItem['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Upload Image File *</label>
                        <input type="file" name="image_file" class="form-control" accept="image/*" required>
                    </div>
                </div>

                <div class="form-group" style="margin: 1rem 0 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" name="featured" value="1"> 
                        <span>Feature this visual on homepage lookbook carousel</span>
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
                        <span>Upload & Save Visual</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT VISUAL MODAL -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2 style="font-family: var(--font-heading); font-size: 1.5rem; font-weight: 700; color: var(--color-text-main); margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                    <span>Edit Visual Details</span>
                </h2>
                <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group" style="margin-bottom: 1.15rem;">
                    <label class="form-label">Visual Title *</label>
                    <input type="text" name="title" id="edit_title" class="form-control" required>
                </div>

                <div class="form-group" style="margin-bottom: 1.15rem;">
                    <label class="form-label">Category *</label>
                    <select name="category" id="edit_category" class="form-control" required>
                        <option value="living_room">Living Room</option>
                        <option value="bedroom">Bedroom</option>
                        <option value="kitchen">Kitchen</option>
                        <option value="bathroom">Bathroom</option>
                        <option value="office">Office</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.15rem;">
                    <label class="form-label">Associated Project (Optional)</label>
                    <select name="project_id" id="edit_project_id" class="form-control">
                        <option value="">— None (Independent Visual) —</option>
                        <?php foreach ($projects_list as $pItem): ?>
                            <option value="<?php echo $pItem['id']; ?>"><?php echo htmlspecialchars($pItem['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.15rem;">
                    <label class="form-label">Replace Image (Optional)</label>
                    <div id="edit_img_preview" style="margin-bottom: 0.5rem;"></div>
                    <input type="file" name="image_file" class="form-control" accept="image/*">
                </div>

                <div class="form-group" style="margin: 1rem 0 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" name="featured" id="edit_featured" value="1"> 
                        <span>Feature this visual on homepage lookbook carousel</span>
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
                        <span>Update Visual</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        let currentFilter = 'all';
        let searchQuery = '';

        function filterGallery(filter, btn) {
            currentFilter = filter;
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            if (btn) btn.classList.add('active');
            applyFilters();
        }

        function searchGallery(query) {
            searchQuery = query.toLowerCase().trim();
            applyFilters();
        }

        function applyFilters() {
            const cards = document.querySelectorAll('.visual-card-item');
            let visibleCount = 0;

            cards.forEach(card => {
                const category = card.getAttribute('data-category') || '';
                const featured = card.getAttribute('data-featured') || '0';
                const search = card.getAttribute('data-search') || '';

                let matchFilter = false;
                if (currentFilter === 'all') matchFilter = true;
                else if (currentFilter === 'featured') matchFilter = featured === '1';
                else matchFilter = category === currentFilter;

                const matchSearch = searchQuery === '' || search.includes(searchQuery);

                if (matchFilter && matchSearch) {
                    card.style.display = 'flex';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
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

        function openEditModal(item) {
            document.getElementById('edit_id').value = item.id;
            document.getElementById('edit_title').value = item.title || '';
            document.getElementById('edit_category').value = item.category || 'living_room';
            document.getElementById('edit_project_id').value = item.project_id || '';
            document.getElementById('edit_featured').checked = item.featured == 1;

            const previewBox = document.getElementById('edit_img_preview');
            if (item.image_path) {
                previewBox.innerHTML = '<div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #64748b;"><span>Current photo:</span><img src="../' + item.image_path + '" style="height: 40px; border-radius: 4px; object-fit: cover;"></div>';
            } else {
                previewBox.innerHTML = '<span style="font-size: 0.8rem; color: #94a3b8;">No photo uploaded.</span>';
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
