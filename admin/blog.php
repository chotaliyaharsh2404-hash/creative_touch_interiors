<?php
require_once '../includes/config.php';

// Enforce admin login and disable caching
requireAdminLogin('login.php');

$current_page = 'blog';
$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Security token expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // 1. ADD NEW ARTICLE
        if ($action === 'add') {
            $title = sanitize($_POST['title'] ?? '');
            $slug = sanitize($_POST['slug'] ?? '');
            if (empty($slug) && !empty($title)) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
            }
            $category = sanitize($_POST['category'] ?? 'Interior Architecture');
            $excerpt = sanitize($_POST['excerpt'] ?? '');
            $content = $_POST['content'] ?? ''; // rich content
            $author = sanitize($_POST['author'] ?? 'Harsh Chotaliya');
            $reading_time = sanitize($_POST['reading_time'] ?? '5 min read');
            $featured = isset($_POST['featured']) ? 1 : 0;
            $status = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'published';

            if (empty($title) || empty($excerpt) || empty($content)) {
                $error = 'Title, excerpt, and article content are required.';
            } else {
                // Check slug uniqueness
                $chk = $conn->prepare("SELECT id FROM blogs WHERE slug = ?");
                $chk->bind_param("s", $slug);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $slug .= '-' . time();
                }

                // Handle Image Upload
                $featured_image = '';
                if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['featured_image'];
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if (in_array($ext, $allowed_exts)) {
                        $target_dir = '../uploads/blogs/';
                        if (!is_dir($target_dir)) {
                            mkdir($target_dir, 0755, true);
                        }
                        $filename = 'blog_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        if (move_uploaded_file($file['tmp_name'], $target_dir . $filename)) {
                            $featured_image = 'uploads/blogs/' . $filename;
                        }
                    }
                }

                $stmt = $conn->prepare("INSERT INTO blogs (title, slug, category, excerpt, content, featured_image, author, reading_time, featured, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssssis", $title, $slug, $category, $excerpt, $content, $featured_image, $author, $reading_time, $featured, $status);
                if ($stmt->execute()) {
                    $success = 'Article created and saved successfully!';
                } else {
                    $error = 'Failed to create article: ' . $conn->error;
                }
            }
        }

        // 2. EDIT ARTICLE
        elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $title = sanitize($_POST['title'] ?? '');
            $slug = sanitize($_POST['slug'] ?? '');
            $category = sanitize($_POST['category'] ?? 'Interior Architecture');
            $excerpt = sanitize($_POST['excerpt'] ?? '');
            $content = $_POST['content'] ?? '';
            $author = sanitize($_POST['author'] ?? 'Harsh Chotaliya');
            $reading_time = sanitize($_POST['reading_time'] ?? '5 min read');
            $featured = isset($_POST['featured']) ? 1 : 0;
            $status = in_array($_POST['status'] ?? '', ['published', 'draft']) ? $_POST['status'] : 'published';

            if ($id <= 0 || empty($title) || empty($excerpt) || empty($content)) {
                $error = 'Invalid article parameters.';
            } else {
                // Fetch current image
                $cur = $conn->query("SELECT featured_image FROM blogs WHERE id = {$id}")->fetch_assoc();
                $featured_image = $cur['featured_image'] ?? '';

                // Handle replacement image upload
                if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['featured_image'];
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                    if (in_array($ext, $allowed_exts)) {
                        $target_dir = '../uploads/blogs/';
                        if (!is_dir($target_dir)) {
                            mkdir($target_dir, 0755, true);
                        }
                        $filename = 'blog_' . time() . '_' . rand(100, 999) . '.' . $ext;
                        if (move_uploaded_file($file['tmp_name'], $target_dir . $filename)) {
                            $featured_image = 'uploads/blogs/' . $filename;
                        }
                    }
                }

                $stmt = $conn->prepare("UPDATE blogs SET title = ?, slug = ?, category = ?, excerpt = ?, content = ?, featured_image = ?, author = ?, reading_time = ?, featured = ?, status = ? WHERE id = ?");
                $stmt->bind_param("ssssssssisi", $title, $slug, $category, $excerpt, $content, $featured_image, $author, $reading_time, $featured, $status, $id);
                if ($stmt->execute()) {
                    $success = 'Article updated successfully!';
                } else {
                    $error = 'Failed to update article: ' . $conn->error;
                }
            }
        }

        // 3. DELETE ARTICLE
        elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $del = $conn->prepare("DELETE FROM blogs WHERE id = ?");
                $del->bind_param("i", $id);
                if ($del->execute()) {
                    $success = 'Article deleted successfully.';
                } else {
                    $error = 'Failed to delete article.';
                }
            }
        }

        // 4. TOGGLE STATUS
        elseif ($action === 'toggle_status') {
            $id = (int)($_POST['id'] ?? 0);
            $curr = $conn->query("SELECT status FROM blogs WHERE id = {$id}")->fetch_assoc()['status'] ?? '';
            $new_st = ($curr === 'published') ? 'draft' : 'published';
            $upd = $conn->prepare("UPDATE blogs SET status = ? WHERE id = ?");
            $upd->bind_param("si", $new_st, $id);
            $upd->execute();
            $success = "Status updated to " . ucfirst($new_st) . ".";
        }

        // 5. TOGGLE FEATURED
        elseif ($action === 'toggle_featured') {
            $id = (int)($_POST['id'] ?? 0);
            $curr = (int)($conn->query("SELECT featured FROM blogs WHERE id = {$id}")->fetch_assoc()['featured'] ?? 0);
            $new_feat = $curr ? 0 : 1;
            $conn->query("UPDATE blogs SET featured = {$new_feat} WHERE id = {$id}");
            $success = $new_feat ? "Article marked as Spotlight Featured." : "Article removed from Spotlight.";
        }
    }
}

// Fetch metrics
$total_posts = $conn->query("SELECT COUNT(*) as c FROM blogs")->fetch_assoc()['c'] ?? 0;
$pub_posts = $conn->query("SELECT COUNT(*) as c FROM blogs WHERE status = 'published'")->fetch_assoc()['c'] ?? 0;
$draft_posts = $conn->query("SELECT COUNT(*) as c FROM blogs WHERE status = 'draft'")->fetch_assoc()['c'] ?? 0;
$cat_count = $conn->query("SELECT COUNT(DISTINCT category) as c FROM blogs")->fetch_assoc()['c'] ?? 0;

// Fetch all articles
$search = sanitize($_GET['s'] ?? '');
$sql = "SELECT * FROM blogs";
if (!empty($search)) {
    $sql .= " WHERE title LIKE '%{$search}%' OR category LIKE '%{$search}%' OR author LIKE '%{$search}%'";
}
$sql .= " ORDER BY created_at DESC";
$articles = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editorial Journal Management — CTI Executive Suite</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/spatial-3d.css">
    <style>
        .admin-layout {
            display: flex;
            min-height: 100vh;
            background: #f8fafc;
            color: #0f172a;
        }
        .admin-main {
            flex: 1;
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 2.25rem 2.5rem;
            overflow-y: auto;
        }
        .admin-stat-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }
        .admin-stat-card:hover {
            border-color: #93c5fd;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.1);
        }
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: #2563eb;
        }
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: #ffffff;
            border: 1px solid #bfdbfe;
            border-radius: var(--radius-2xl);
            width: 100%;
            max-width: 780px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2.5rem;
            color: #0f172a;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
        }
    </style>
</head>
<body style="background: #F8F7F4; color: #0f172a;">

<div class="admin-layout">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="admin-main">
        
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <span class="section-tag" style="margin-bottom: 0.35rem; display: inline-block;">Editorial Press &amp; Publishing</span>
                <h1 style="font-family: var(--font-heading); font-size: 2.15rem; color: var(--color-text-main); margin: 0; font-weight: 700;">
                    Spatial Perspectives &amp; Journal
                </h1>
            </div>
            <div style="display: flex; gap: 0.85rem;">
                <a href="../blog.php" target="_blank" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.6rem 1.15rem; display: inline-flex; align-items: center; gap: 0.4rem; background: #ffffff; border-color: #cbd5e1; color: #334155;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                    Live Journal View
                </a>
                <button type="button" onclick="openAddModal()" class="btn btn-primary" style="font-size: 0.85rem; padding: 0.6rem 1.25rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Compose New Essay
                </button>
            </div>
        </div>

        <!-- Notifications -->
        <?php if (!empty($success)): ?>
            <div class="glass-card" style="margin-bottom: 1.5rem; padding: 0.85rem 1.25rem; background: rgba(16, 185, 129, 0.12); border-color: rgba(16, 185, 129, 0.3); color: #6ee7b7; display: flex; align-items: center; gap: 0.75rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="glass-card" style="margin-bottom: 1.5rem; padding: 0.85rem 1.25rem; background: rgba(239, 68, 68, 0.12); border-color: rgba(239, 68, 68, 0.3); color: #fca5a5; display: flex; align-items: center; gap: 0.75rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="grid grid-4" style="gap: 1.25rem; margin-bottom: 2rem;">
            <div class="admin-stat-card" style="border-left: 4px solid var(--color-primary);">
                <div>
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-text-muted); font-weight: 700; letter-spacing: 0.05em;">Total Essays</div>
                    <div style="font-size: 1.85rem; font-weight: 800; font-family: var(--font-heading); color: #1d4ed8;"><?php echo $total_posts; ?></div>
                </div>
                <div class="stat-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </div>
            </div>
            <div class="admin-stat-card" style="border-left: 4px solid #10b981;">
                <div>
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-text-muted); font-weight: 700; letter-spacing: 0.05em;">Published</div>
                    <div style="font-size: 1.85rem; font-weight: 800; font-family: var(--font-heading); color: #059669;"><?php echo $pub_posts; ?></div>
                </div>
                <div class="stat-icon" style="background: #ecfdf5; color: #059669;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
            </div>
            <div class="admin-stat-card" style="border-left: 4px solid #f59e0b;">
                <div>
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-text-muted); font-weight: 700; letter-spacing: 0.05em;">Drafts</div>
                    <div style="font-size: 1.85rem; font-weight: 800; font-family: var(--font-heading); color: #d97706;"><?php echo $draft_posts; ?></div>
                </div>
                <div class="stat-icon" style="background: #fffbeb; color: #d97706;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                </div>
            </div>
            <div class="admin-stat-card" style="border-left: 4px solid #2563eb;">
                <div>
                    <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-text-muted); font-weight: 700; letter-spacing: 0.05em;">Disciplines</div>
                    <div style="font-size: 1.85rem; font-weight: 800; font-family: var(--font-heading); color: #2563eb;"><?php echo $cat_count; ?></div>
                </div>
                <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                </div>
            </div>
        </div>

        <!-- Articles Catalog Table -->
        <div class="card" style="padding: 1.75rem 2rem; border-radius: var(--radius-2xl); border: 1px solid var(--border-color); background: #ffffff; box-shadow: var(--shadow-sm);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <h3 style="font-family: var(--font-heading); font-size: 1.25rem; color: var(--color-text-main); margin: 0; font-weight: 700;">
                    All Journal Publications
                </h3>
                <form method="GET" action="blog.php" style="display: flex; gap: 0.5rem;">
                    <input type="text" name="s" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search titles, authors..." class="form-control" style="background: #ffffff; border: 1px solid var(--border-color); color: var(--color-text-main); padding: 0.45rem 0.85rem; font-size: 0.85rem; width: 220px; border-radius: 9999px;">
                    <button type="submit" class="btn btn-outline" style="padding: 0.45rem 0.85rem; font-size: 0.85rem; border-radius: 9999px; background: #ffffff; border-color: #cbd5e1; color: #334155;">Filter</button>
                    <?php if (!empty($search)): ?>
                        <a href="blog.php" class="btn btn-outline" style="padding: 0.45rem 0.75rem; border-radius: 9999px; background: #ffffff; border-color: #cbd5e1; color: #334155;">&times;</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (!empty($articles)): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color); text-align: left; color: var(--color-text-muted); font-size: 0.775rem; text-transform: uppercase; letter-spacing: 0.05em;">
                            <th style="padding: 0.75rem 1rem;">Article</th>
                            <th style="padding: 0.75rem 1rem;">Category</th>
                            <th style="padding: 0.75rem 1rem;">Author</th>
                            <th style="padding: 0.75rem 1rem;">Spotlight</th>
                            <th style="padding: 0.75rem 1rem;">Status</th>
                            <th style="padding: 0.75rem 1rem;">Date</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articles as $a): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <td style="padding: 1rem;">
                                <div style="display: flex; align-items: center; gap: 0.85rem;">
                                    <?php if (!empty($a['featured_image'])): ?>
                                        <img src="../<?php echo htmlspecialchars($a['featured_image']); ?>" alt="" style="width: 48px; height: 38px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color);">
                                    <?php else: ?>
                                        <div style="width: 48px; height: 38px; background: #f1f5f9; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: var(--color-text-muted); font-size: 0.7rem; border: 1px solid var(--border-color);">None</div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 700; color: var(--color-text-main); max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars($a['title']); ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--color-text-muted); font-family: monospace;">
                                            /<?php echo htmlspecialchars($a['slug']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1rem;">
                                <span class="badge" style="background: #eff6ff; color: #2563eb; font-size: 0.72rem; border: 1px solid #bfdbfe; font-weight: 700;">
                                    <?php echo htmlspecialchars($a['category']); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; color: #475569; font-size: 0.825rem;">
                                <?php echo htmlspecialchars($a['author']); ?>
                            </td>
                            <td style="padding: 1rem;">
                                <form method="POST" action="blog.php" style="display: inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_featured">
                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" style="background: none; border: none; cursor: pointer; color: <?php echo $a['featured'] ? '#2563eb' : '#cbd5e1'; ?>;" title="Toggle Spotlight">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="<?php echo $a['featured'] ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                                    </button>
                                </form>
                            </td>
                            <td style="padding: 1rem;">
                                <form method="POST" action="blog.php" style="display: inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                    <button type="submit" class="badge" style="cursor: pointer; border: 1px solid; background: <?php echo $a['status'] === 'published' ? '#ecfdf5' : '#fffbeb'; ?>; color: <?php echo $a['status'] === 'published' ? '#059669' : '#d97706'; ?>; border-color: <?php echo $a['status'] === 'published' ? '#a7f3d0' : '#fde68a'; ?>; font-weight: 700;">
                                        <?php echo ucfirst($a['status']); ?>
                                    </button>
                                </form>
                            </td>
                            <td style="padding: 1rem; color: var(--color-text-muted); font-size: 0.775rem;">
                                <?php echo date('M d, Y', strtotime($a['created_at'])); ?>
                            </td>
                            <td style="padding: 1rem; text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 0.4rem;">
                                    <a href="../blog.php?slug=<?php echo urlencode($a['slug']); ?>" target="_blank" class="btn btn-outline" style="padding: 0.35rem 0.6rem; font-size: 0.75rem; background: #ffffff; border-color: #cbd5e1; color: #475569;" title="View Live">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <button type="button" onclick='openEditModal(<?php echo json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' class="btn btn-outline" style="padding: 0.35rem 0.6rem; font-size: 0.75rem; background: #eff6ff; border-color: #bfdbfe; color: #2563eb;" title="Edit Essay">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    <form method="POST" action="blog.php" style="display: inline;" onsubmit="return confirm('Permanently remove this article from the journal?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                        <button type="submit" class="btn btn-outline" style="padding: 0.35rem 0.6rem; font-size: 0.75rem; color: #ef4444; border-color: #fca5a5; background: #fef2f2;" title="Delete">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
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
                <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                    No articles located. Click "Compose New Essay" to publish the first perspective.
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<!-- Modal: Compose New Article -->
<div id="addModal" class="modal-overlay">
    <div class="modal-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
            <h3 style="font-family: var(--font-heading); font-size: 1.35rem; color: var(--color-text-main); margin: 0; font-weight: 700;">Compose New Perspective</h3>
            <button type="button" onclick="closeAddModal()" style="background: none; border: none; color: var(--color-text-muted); font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="blog.php" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add">

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Article Title *</label>
                <input type="text" name="title" id="add_title" class="form-control" required placeholder="e.g. Tactile Travertine: The Architecture of Pure Geometry" oninput="autoSlug('add_title', 'add_slug')">
            </div>

            <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">URL Slug</label>
                    <input type="text" name="slug" id="add_slug" class="form-control" placeholder="auto-generated-slug">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Discipline / Category</label>
                    <select name="category" class="form-control">
                        <option value="Interior Architecture">Interior Architecture</option>
                        <option value="Lighting Design">Lighting Design</option>
                        <option value="Materials & Craft">Materials &amp; Craft</option>
                        <option value="Modular Kitchens">Modular Kitchens</option>
                        <option value="Residential">Residential</option>
                        <option value="Commercial">Commercial</option>
                        <option value="Acoustics & Sensory">Acoustics &amp; Sensory</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Author</label>
                    <select name="author" class="form-control">
                        <option value="Harsh Chotaliya">Harsh Chotaliya (Principal Architect)</option>
                        <option value="Het Rana">Het Rana (Lead Interior Designer)</option>
                        <option value="CTI Editorial Desk">CTI Editorial Desk</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Reading Time Estimate</label>
                    <input type="text" name="reading_time" class="form-control" value="5 min read">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Executive Excerpt (Summary) *</label>
                <textarea name="excerpt" rows="2" class="form-control" required placeholder="A brief introductory paragraph summarizing the core spatial thesis..."></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Full Article Content (HTML / Text) *</label>
                <textarea name="content" rows="8" class="form-control" required placeholder="<p>Write your detailed architectural treatise here...</p>" style="font-family: monospace; font-size: 0.85rem;"></textarea>
            </div>

            <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Featured Image (Cover)</label>
                    <input type="file" name="featured_image" accept="image/*" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Publication Status</label>
                    <select name="status" class="form-control">
                        <option value="published">Published Immediately</option>
                        <option value="draft">Save as Draft</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.75rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--color-text-main); font-weight: 600;">
                    <input type="checkbox" name="featured" value="1" style="accent-color: var(--color-primary);">
                    <span>Pin as Spotlight Featured Article on Journal Homepage</span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="closeAddModal()" class="btn btn-outline" style="background: #f1f5f9; border-color: #cbd5e1; color: #475569;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.75rem;">Publish Article &rarr;</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Article -->
<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
            <h3 style="font-family: var(--font-heading); font-size: 1.35rem; color: var(--color-text-main); margin: 0; font-weight: 700;">Edit Journal Article</h3>
            <button type="button" onclick="closeEditModal()" style="background: none; border: none; color: var(--color-text-muted); font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="blog.php" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Article Title *</label>
                <input type="text" name="title" id="edit_title" class="form-control" required>
            </div>

            <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">URL Slug</label>
                    <input type="text" name="slug" id="edit_slug" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Category</label>
                    <select name="category" id="edit_category" class="form-control">
                        <option value="Interior Architecture">Interior Architecture</option>
                        <option value="Lighting Design">Lighting Design</option>
                        <option value="Materials & Craft">Materials &amp; Craft</option>
                        <option value="Modular Kitchens">Modular Kitchens</option>
                        <option value="Residential">Residential</option>
                        <option value="Commercial">Commercial</option>
                        <option value="Acoustics & Sensory">Acoustics &amp; Sensory</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Author</label>
                    <select name="author" id="edit_author" class="form-control">
                        <option value="Harsh Chotaliya">Harsh Chotaliya (Principal Architect)</option>
                        <option value="Het Rana">Het Rana (Lead Interior Designer)</option>
                        <option value="CTI Editorial Desk">CTI Editorial Desk</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Reading Time Estimate</label>
                    <input type="text" name="reading_time" id="edit_reading_time" class="form-control">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Executive Excerpt *</label>
                <textarea name="excerpt" id="edit_excerpt" rows="2" class="form-control" required></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Full Article Content *</label>
                <textarea name="content" id="edit_content" rows="8" class="form-control" required style="font-family: monospace; font-size: 0.85rem;"></textarea>
            </div>

            <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Replace Cover Image (Optional)</label>
                    <input type="file" name="featured_image" accept="image/*" class="form-control">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 0.8rem; text-transform: uppercase;">Publication Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.75rem;">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; color: var(--color-text-main); font-weight: 600;">
                    <input type="checkbox" name="featured" id="edit_featured" value="1" style="accent-color: var(--color-primary);">
                    <span>Pin as Spotlight Featured Article on Journal Homepage</span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="closeEditModal()" class="btn btn-outline" style="background: #f1f5f9; border-color: #cbd5e1; color: #475569;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.75rem;">Save Changes &rarr;</button>
            </div>
        </form>
    </div>
</div>

<script>
function autoSlug(srcId, targetId) {
    const src = document.getElementById(srcId).value;
    const slug = src.toLowerCase().trim().replace(/[^a-z0-9 -]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
    document.getElementById(targetId).value = slug;
}

function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}
function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
}

function openEditModal(article) {
    document.getElementById('edit_id').value = article.id;
    document.getElementById('edit_title').value = article.title;
    document.getElementById('edit_slug').value = article.slug;
    document.getElementById('edit_category').value = article.category;
    document.getElementById('edit_author').value = article.author;
    document.getElementById('edit_reading_time').value = article.reading_time;
    document.getElementById('edit_excerpt').value = article.excerpt;
    document.getElementById('edit_content').value = article.content;
    document.getElementById('edit_status').value = article.status;
    document.getElementById('edit_featured').checked = (parseInt(article.featured) === 1);
    document.getElementById('editModal').classList.add('active');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

// Close modals when clicking on backdrop
window.onclick = function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
}
</script>

</body>
</html>
