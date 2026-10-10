<?php
require_once '../includes/config.php';

// Enforce RBAC: Super Admin and Admin only
requireRoles(['super_admin', 'admin'], 'dashboard.php');

$current_page = 'announcements';
$admin_id = (int)($_SESSION['admin_id'] ?? 0);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('announcements.php');
}

// Handle Direct Publish action from Preview Bar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_now') {
    if (validate_csrf()) {
        $up = $conn->prepare("UPDATE announcements SET status = 'published', updated_by = ? WHERE id = ? AND deleted_at IS NULL");
        $up->bind_param("ii", $admin_id, $id);
        $up->execute();
        $up->close();
        redirect("announcements.php?published=1");
    }
}

// Fetch Announcement
$stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $id);
$stmt->execute();
$announcement = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$announcement) {
    redirect('announcements.php');
}

$cta_url = sanitizeCtaUrl($announcement['cta_url'] ?? '');
if (empty($cta_url)) {
    $cta_url = '../consultation.php';
} elseif (!preg_match('/^https?:\/\//i', $cta_url)) {
    $cta_url = '../' . ltrim($cta_url, '/');
}
$cta_text = !empty($announcement['cta_text']) ? trim($announcement['cta_text']) : 'Get a Quote';

$page_title = 'Preview: ' . htmlspecialchars($announcement['title']) . ' — Creative Touch Interiors';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Core & 3D Spatial Stylesheets -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/spatial-3d.css">
</head>
<body style="background: #F8F7F4; color: #0f172a; padding-top: 54px;">

    <!-- =========================================================================
         EXECUTIVE FLOATING PREVIEW HUD TOOLBAR
         ========================================================================= -->
    <header style="position: fixed; top: 0; left: 0; right: 0; z-index: 99999; background: #0b1736; border-bottom: 2px solid #0057FF; box-shadow: 0 4px 20px rgba(0,0,0,0.3); padding: 0.65rem 1.75rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.85rem; color: #ffffff;">
        <div style="display: flex; align-items: center; gap: 0.85rem;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; padding: 0.2rem 0.65rem; border-radius: 9999px; background: <?php echo $announcement['status'] === 'published' ? '#10b981' : '#f59e0b'; ?>; color: #ffffff;">
                    <?php echo $announcement['status'] === 'published' ? 'Live on Website' : 'Draft Preview'; ?>
                </span>
                <span style="font-size: 0.88rem; font-weight: 700; color: #f8fafc;">
                    Announcement Preview
                </span>
            </div>
            <span style="color: #64748b; font-size: 0.8rem; display: none; @media(min-width: 768px){display:inline;}">
                Audience: All Website Visitors &bull; Theme: Creative Touch Public Site
            </span>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <!-- [ Back to Edit ] Button -->
            <a href="announcements.php?edit=<?php echo $announcement['id']; ?>" class="btn" style="background: rgba(255, 255, 255, 0.1); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.25); border-radius: 8px; padding: 0.45rem 1.15rem; font-size: 0.825rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                <span>Back to Edit</span>
            </a>

            <!-- [ Publish Announcement ] Button -->
            <?php if ($announcement['status'] !== 'published'): ?>
                <form method="POST" style="margin: 0;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="publish_now">
                    <button type="submit" class="btn" style="background: #0057FF; color: #ffffff; border: none; border-radius: 8px; padding: 0.45rem 1.35rem; font-size: 0.825rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 0.45rem; box-shadow: 0 4px 12px rgba(0, 87, 255, 0.35); transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span>Publish Announcement</span>
                    </button>
                </form>
            <?php else: ?>
                <a href="../announcement.php?id=<?php echo $announcement['id']; ?>" target="_blank" class="btn" style="background: #10b981; color: #ffffff; border: none; border-radius: 8px; padding: 0.45rem 1.25rem; font-size: 0.825rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <span>View Public Page &rarr;</span>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Liquid Glass Ambient Atmospheric Mesh -->
    <div class="liquid-bg-mesh" aria-hidden="true">
        <div class="liquid-orb liquid-orb-1"></div>
        <div class="liquid-orb liquid-orb-2"></div>
        <div class="liquid-orb liquid-orb-3"></div>
    </div>

    <!-- Floating Glass Spatial Navigation -->
    <nav class="spatial-nav" role="navigation" aria-label="Main Navigation" style="top: 4.8rem;">
        <div class="spatial-nav-container">
            <a href="../index.php" class="spatial-brand">
                <div class="spatial-brand-emblem" aria-hidden="true">
                    <div class="spatial-brand-emblem-diamond"></div>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path d="M3 21h18"/><path d="M5 21V7l8-4 6 3v15"/><path d="M9 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>
                    </svg>
                </div>
                <span class="spatial-brand-title">Creative Touch<span>.</span></span>
            </a>

            <ul class="spatial-nav-links">
                <li><a href="../index.php" class="spatial-nav-link">Home</a></li>
                <li><a href="../about.php" class="spatial-nav-link">About</a></li>
                <li><a href="../services.php" class="spatial-nav-link">Services</a></li>
                <li><a href="../projects.php" class="spatial-nav-link">Projects</a></li>
                <li><a href="../gallery.php" class="spatial-nav-link">Gallery</a></li>
                <li><a href="../blog.php" class="spatial-nav-link">Blog</a></li>
                <li><a href="../contact.php" class="spatial-nav-link">Contact</a></li>
            </ul>

            <div class="spatial-nav-cta">
                <a href="../consultation.php" class="btn-spatial-bronze">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    <span>Get a Quote</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Public Design Preview Container -->
    <main class="spatial-section" style="padding-top: 10.5rem; padding-bottom: 6rem; background: #F8F7F4;">
        <div class="container" style="max-width: 920px;">
            <article style="background: rgba(255, 255, 255, 0.94); backdrop-filter: blur(28px); -webkit-backdrop-filter: blur(28px); border: 1px solid rgba(255, 255, 255, 0.95); border-radius: 24px; box-shadow: 0 20px 50px rgba(0, 40, 120, 0.08); overflow: hidden; padding: clamp(1.75rem, 4vw, 3.5rem);">
                
                <!-- Category Badges & Priority Ribbon -->
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.85rem; margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                        <span style="font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; padding: 0.3rem 0.85rem; border-radius: 9999px; background: rgba(0, 87, 255, 0.1); color: #0057FF; border: 1px solid rgba(0, 87, 255, 0.2);">
                            <?php echo strtoupper(htmlspecialchars($announcement['type'])); ?>
                        </span>

                        <?php if ($announcement['priority'] === 'urgent'): ?>
                            <span style="font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; padding: 0.3rem 0.85rem; border-radius: 9999px; background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;">
                                Urgent Notice
                            </span>
                        <?php elseif ($announcement['priority'] === 'important'): ?>
                            <span style="font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; padding: 0.3rem 0.85rem; border-radius: 9999px; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;">
                                Important
                            </span>
                        <?php endif; ?>
                    </div>

                    <div style="font-size: 0.82rem; color: #64748b; font-weight: 500;">
                        <span>Broadcast Date: <?php echo date('d F Y', strtotime($announcement['start_at'] ?? $announcement['created_at'])); ?></span>
                    </div>
                </div>

                <!-- Main Announcement Title -->
                <h1 style="font-family: var(--font-heading); font-size: clamp(2rem, 3.8vw, 3rem); font-weight: 700; color: #0f172a; line-height: 1.25; margin: 0 0 1.5rem; letter-spacing: -0.01em;">
                    <?php echo htmlspecialchars($announcement['title']); ?>
                </h1>

                <!-- Validity Banner if active/scheduled -->
                <?php if (!empty($announcement['expires_at'])): ?>
                    <div style="background: rgba(234, 88, 12, 0.08); border-left: 4px solid #ea580c; border-radius: 8px; padding: 0.85rem 1.25rem; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <div style="font-size: 0.9rem; color: #9a3412;">
                            <strong>Offer Validity:</strong> This promotion is active until 
                            <span style="font-weight: 700; color: #c2410c;"><?php echo date('d F Y', strtotime($announcement['expires_at'])); ?></span>.
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Optional Cover Image -->
                <?php if (!empty($announcement['cover_image']) && file_exists(dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $announcement['cover_image']), '/'))): ?>
                    <div style="margin-bottom: 2.5rem; border-radius: 16px; overflow: hidden; box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08); max-height: 480px; width: 100%;">
                        <img src="../<?php echo htmlspecialchars($announcement['cover_image']); ?>" alt="<?php echo htmlspecialchars($announcement['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;">
                    </div>
                <?php endif; ?>

                <!-- Full Formatted Message Body -->
                <div style="font-size: 1.1rem; line-height: 1.85; color: #334155; margin-bottom: 3rem; white-space: pre-line;">
                    <?php echo nl2br(htmlspecialchars($announcement['message'])); ?>
                </div>

                <!-- Interactive CTA Action Box -->
                <div style="background: radial-gradient(circle at 10% 20%, #eff6ff 0%, #ffffff 90%); border: 1px solid rgba(0, 87, 255, 0.18); border-radius: 16px; padding: 2rem 2.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 8px 24px rgba(0, 87, 255, 0.06);">
                    <div>
                        <h3 style="font-family: var(--font-heading); font-size: 1.35rem; font-weight: 700; color: #0f172a; margin: 0 0 0.4rem;">
                            Take Advantage of this Offer
                        </h3>
                        <p style="margin: 0; color: #64748b; font-size: 0.95rem;">
                            Connect with our principal architects to claim this promotion or discuss your bespoke space.
                        </p>
                    </div>

                    <a href="<?php echo htmlspecialchars($cta_url); ?>" class="btn-spatial-bronze" style="padding: 0.9rem 2.4rem; font-size: 0.92rem; text-decoration: none;">
                        <span><?php echo htmlspecialchars($cta_text); ?></span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                </div>

            </article>
        </div>
    </main>

    <!-- Luxury Architectural Footer -->
    <footer class="footer" style="background: #091124; border-top: 1px solid #1e293b; padding: 3.5rem 0 2rem; color: #94a3b8; text-align: center; font-size: 0.88rem;">
        <div class="container">
            <p style="margin: 0 0 0.5rem; color: #ffffff; font-weight: 700;">Creative Touch Interiors &bull; Studio Preview</p>
            <p style="margin: 0; font-size: 0.8rem; color: #64748b;">This preview accurately reflects how public website visitors view this announcement.</p>
        </div>
    </footer>

</body>
</html>
