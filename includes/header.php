<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Public Announcements Telemetry (v2.2.0)
$announcement_bar_item = isset($conn) ? getAnnouncementBarItem($conn) : null;
$header_latest_announcements = isset($conn) ? getActiveAnnouncements($conn, 4) : [];
$header_active_ann_count = count($header_latest_announcements);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Creative Touch Interiors — Bespoke luxury 3D interior architecture and turnkey design studio in Surat, Gujarat. Spaces designed around you.">
    <meta name="theme-color" content="#1d4ed8">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Creative Touch Interiors — Bespoke Luxury 3D Interior Architecture'; ?></title>
    
    <!-- Google Fonts Preconnect & Optimized Font Loading -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Core & 3D Spatial Stylesheets -->
    <link rel="stylesheet" href="css/style.css?v=<?php echo file_exists(__DIR__ . '/../css/style.css') ? filemtime(__DIR__ . '/../css/style.css') : time(); ?>">
    <link rel="stylesheet" href="css/spatial-3d.css?v=<?php echo file_exists(__DIR__ . '/../css/spatial-3d.css') ? filemtime(__DIR__ . '/../css/spatial-3d.css') : time(); ?>">

    <!-- Three.js & GSAP Vendor Scripts (Local with fallback) -->
    <script src="js/vendor/three.min.js"></script>
    <script src="js/vendor/gsap.min.js"></script>
    <script src="js/vendor/ScrollTrigger.min.js"></script>
</head>
<body class="<?php echo $announcement_bar_item ? 'has-announcement-bar' : ''; ?>">
    <!-- Liquid Glass Ambient Atmospheric Mesh -->
    <div class="liquid-bg-mesh" aria-hidden="true">
        <div class="liquid-orb liquid-orb-1"></div>
        <div class="liquid-orb liquid-orb-2"></div>
        <div class="liquid-orb liquid-orb-3"></div>
    </div>

    <?php if ($announcement_bar_item): ?>
    <!-- Subtle Glassmorphic Public Announcement Bar (v2.2.0) -->
    <aside class="cti-announcement-bar" id="ctiAnnouncementBar" role="region" aria-label="Public Announcement">
        <div class="cti-announcement-bar-inner">
            <div class="cti-announcement-bar-content">
                <span class="cti-ann-badge <?php echo htmlspecialchars($announcement_bar_item['type']); ?>">
                    <?php echo strtoupper(htmlspecialchars($announcement_bar_item['type'])); ?>
                </span>
                <span class="cti-ann-title">
                    <?php echo htmlspecialchars($announcement_bar_item['title']); ?>
                </span>
                <?php if (!empty($announcement_bar_item['expires_at'])): ?>
                    <span class="cti-ann-validity">
                        Valid until <?php echo date('d F Y', strtotime($announcement_bar_item['expires_at'])); ?>
                    </span>
                <?php endif; ?>
                <a href="announcement.php?id=<?php echo (int)$announcement_bar_item['id']; ?>" class="cti-ann-cta">
                    View Details &rarr;
                </a>
            </div>
            <button type="button" class="cti-ann-close" id="ctiDismissAnnBar" aria-label="Dismiss Announcement">&times;</button>
        </div>
    </aside>
    <?php endif; ?>

    <!-- Floating Glass Spatial Navigation -->
    <nav class="spatial-nav" role="navigation" aria-label="Main Navigation">
        <div class="spatial-nav-container">
            <a href="index.php" class="spatial-brand" title="Creative Touch Interiors — Luxury Architecture">
                <div class="spatial-brand-emblem" aria-hidden="true">
                    <div class="spatial-brand-emblem-diamond"></div>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18"/>
                        <path d="M5 21V7l8-4 6 3v15"/>
                        <path d="M9 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"/>
                    </svg>
                </div>
                <span class="spatial-brand-title">Creative Touch<span>.</span></span>
            </a>

            <!-- Navigation Links -->
            <ul class="spatial-nav-links">
                <li><a href="index.php" class="spatial-nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">Home</a></li>
                <li><a href="about.php" class="spatial-nav-link <?php echo ($current_page == 'about.php') ? 'active' : ''; ?>">About</a></li>
                <li><a href="services.php" class="spatial-nav-link <?php echo ($current_page == 'services.php') ? 'active' : ''; ?>">Services</a></li>
                <li><a href="projects.php" class="spatial-nav-link <?php echo ($current_page == 'projects.php' || $current_page == 'project.php') ? 'active' : ''; ?>">Projects</a></li>
                <li><a href="gallery.php" class="spatial-nav-link <?php echo ($current_page == 'gallery.php') ? 'active' : ''; ?>">Gallery</a></li>
                <li><a href="blog.php" class="spatial-nav-link <?php echo ($current_page == 'blog.php') ? 'active' : ''; ?>">Blog</a></li>
                <li><a href="contact.php" class="spatial-nav-link <?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>">Contact</a></li>
            </ul>

            <!-- Navigation CTA & Auth -->
            <div class="spatial-nav-cta">
                <!-- Header Announcement Bell Icon Dropdown -->
                <div class="spatial-announcement-wrapper" id="spatialAnnouncementWrapper">
                    <button type="button" class="spatial-announcement-btn" id="spatialAnnouncementBtn" aria-label="Latest Announcements and Special Offers" aria-expanded="false" aria-controls="spatialAnnouncementDropdown" title="Announcements &amp; Offers">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <?php if ($header_active_ann_count > 0): ?>
                            <span class="spatial-announcement-dot" title="<?php echo $header_active_ann_count; ?> Active Announcements"></span>
                        <?php endif; ?>
                    </button>
                    <!-- Glassmorphism Dropdown -->
                    <div class="spatial-announcement-dropdown" id="spatialAnnouncementDropdown" role="menu">
                        <div class="spatial-ann-dd-header">
                            <span class="spatial-ann-dd-title">Latest Announcements</span>
                            <span class="spatial-ann-dd-count"><?php echo $header_active_ann_count; ?> Active</span>
                        </div>
                        <div class="spatial-ann-dd-list">
                            <?php if (!empty($header_latest_announcements)): ?>
                                <?php foreach ($header_latest_announcements as $ann): ?>
                                    <a href="announcement.php?id=<?php echo (int)$ann['id']; ?>" class="spatial-ann-dd-item">
                                        <div class="spatial-ann-dd-item-top">
                                            <span class="spatial-ann-tag <?php echo htmlspecialchars($ann['type']); ?>"><?php echo ucfirst(htmlspecialchars($ann['type'])); ?></span>
                                            <?php if (!empty($ann['expires_at'])): ?>
                                                <span class="spatial-ann-date">Valid until <?php echo date('d M', strtotime($ann['expires_at'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="spatial-ann-dd-item-title"><?php echo htmlspecialchars($ann['title']); ?></div>
                                        <div class="spatial-ann-dd-item-excerpt"><?php echo htmlspecialchars(mb_strimwidth(strip_tags($ann['message']), 0, 75, '...')); ?></div>
                                        <span class="spatial-ann-view-link">View &rarr;</span>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="spatial-ann-empty">No new announcements</div>
                            <?php endif; ?>
                        </div>
                        <a href="announcements.php" class="spatial-ann-dd-footer">
                            <span>View All Announcements</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </a>
                    </div>
                </div>

                <a href="consultation.php" class="btn-spatial-bronze">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    <span>Get a Quote</span>
                </a>

                <?php if (isUserLoggedIn()): ?>
                    <a href="profile.php" class="btn-spatial-outline" title="Client Dashboard">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span><?php echo htmlspecialchars(explode(' ', trim($_SESSION['user_name'] ?? 'Account'))[0]); ?></span>
                    </a>
                    <a href="user_logout.php" class="btn-spatial-outline" style="padding: 0.65rem 0.85rem;" title="Sign Out">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn-spatial-outline">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                            <polyline points="10 17 15 12 10 7"></polyline>
                            <line x1="15" y1="12" x2="3" y2="12"></line>
                        </svg>
                        <span>Login</span>
                    </a>
                <?php endif; ?>

                <!-- Mobile Menu Button -->
                <button class="spatial-mobile-toggle" aria-label="Toggle navigation menu">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="4" y1="7" x2="20" y2="7"/>
                        <line x1="4" y1="12" x2="20" y2="12"/>
                        <line x1="4" y1="17" x2="20" y2="17"/>
                    </svg>
                </button>
            </div>
        </div>
    </nav>

    <!-- Fullscreen Mobile Navigation Drawer -->
    <div class="spatial-mobile-drawer" role="dialog" aria-modal="true" aria-label="Mobile Navigation">
        <div class="spatial-mobile-drawer-header">
            <a href="index.php" class="spatial-brand">
                <div class="spatial-brand-emblem">
                    <div class="spatial-brand-emblem-diamond"></div>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path d="M3 21h18"/><path d="M5 21V7l8-4 6 3v15"/>
                    </svg>
                </div>
                <span class="spatial-brand-title">Creative Touch<span>.</span></span>
            </a>
            <button class="spatial-drawer-close spatial-mobile-toggle" style="display:flex;" aria-label="Close menu">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <ul class="spatial-mobile-nav-list">
            <li class="spatial-mobile-nav-item"><a href="index.php"><span>Home</span> <span>&rarr;</span></a></li>
            <li class="spatial-mobile-nav-item"><a href="about.php"><span>About</span> <span>&rarr;</span></a></li>
            <li class="spatial-mobile-nav-item"><a href="services.php"><span>Services</span> <span>&rarr;</span></a></li>
            <li class="spatial-mobile-nav-item"><a href="projects.php"><span>Projects</span> <span>&rarr;</span></a></li>
            <li class="spatial-mobile-nav-item"><a href="gallery.php"><span>Gallery</span> <span>&rarr;</span></a></li>
            <li class="spatial-mobile-nav-item"><a href="blog.php"><span>Blog</span> <span>&rarr;</span></a></li>
            <li class="spatial-mobile-nav-item"><a href="contact.php"><span>Contact</span> <span>&rarr;</span></a></li>
            <li class="spatial-mobile-nav-item"><a href="announcements.php" style="color: #0057FF; font-weight: 700;"><span>Announcements &amp; Offers</span> <span>&rarr;</span></a></li>
        </ul>

        <div style="display: flex; flex-direction: column; gap: 1rem; padding-top: 1.5rem; border-top: 1px solid var(--glass-border);">
            <a href="consultation.php" class="btn-spatial-bronze" style="justify-content: center;">
                <span>Get a Custom Quote</span>
            </a>
            <?php if (isUserLoggedIn()): ?>
                <a href="profile.php" class="btn-spatial-outline" style="justify-content: center;">Account Dashboard</a>
                <a href="user_logout.php" class="btn-spatial-outline" style="justify-content: center; color: #ef4444 !important;">Sign Out</a>
            <?php else: ?>
                <a href="login.php" class="btn-spatial-outline" style="justify-content: center;">Client Login</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Public Announcement Dynamic Interactions Script -->
    <script>
    (function() {
        function initAnnouncements() {
            // Announcement Bar Dismissal Memory
            const annBar = document.getElementById('ctiAnnouncementBar');
            const dismissBtn = document.getElementById('ctiDismissAnnBar');
            if (annBar && dismissBtn) {
                const barId = '<?php echo $announcement_bar_item ? (int)$announcement_bar_item['id'] : 0; ?>';
                if (sessionStorage.getItem('cti_dismissed_ann_' + barId) === '1') {
                    document.body.classList.add('ann-dismissed');
                }
                dismissBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    document.body.classList.add('ann-dismissed');
                    sessionStorage.setItem('cti_dismissed_ann_' + barId, '1');
                });
            }

            // Header Bell Dropdown Toggle
            const annBtn = document.getElementById('spatialAnnouncementBtn');
            const annDropdown = document.getElementById('spatialAnnouncementDropdown');
            if (annBtn && annDropdown) {
                annBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const isOpen = annDropdown.classList.contains('show');
                    if (isOpen) {
                        annDropdown.classList.remove('show');
                        annBtn.setAttribute('aria-expanded', 'false');
                    } else {
                        annDropdown.classList.add('show');
                        annBtn.setAttribute('aria-expanded', 'true');
                    }
                });

                // Prevent click on dropdown interior from closing it unless clicking an actual navigation link
                annDropdown.addEventListener('click', function(e) {
                    const link = e.target.closest('a');
                    if (!link) {
                        e.stopPropagation();
                    }
                });

                // Outside click closes dropdown
                document.addEventListener('click', function(e) {
                    if (!annDropdown.contains(e.target) && !annBtn.contains(e.target)) {
                        if (annDropdown.classList.contains('show')) {
                            annDropdown.classList.remove('show');
                            annBtn.setAttribute('aria-expanded', 'false');
                        }
                    }
                });

                // Escape key closes dropdown and restores focus to the trigger button
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && annDropdown.classList.contains('show')) {
                        annDropdown.classList.remove('show');
                        annBtn.setAttribute('aria-expanded', 'false');
                        annBtn.focus();
                    }
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAnnouncements);
        } else {
            initAnnouncements();
        }
    })();
    </script>
