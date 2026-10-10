<?php
require_once 'includes/config.php';

$page_title = 'Public Announcements & Promotions — Creative Touch Interiors';

// Filter by announcement type
$type_filter = isset($_GET['type']) ? sanitize($_GET['type']) : 'all';
$valid_types = ['promotion', 'announcement', 'update', 'notice', 'system'];

$now = date('Y-m-d H:i:s');

if ($type_filter !== 'all' && in_array($type_filter, $valid_types, true)) {
    $stmt = $conn->prepare("SELECT * FROM announcements 
                            WHERE status = 'published' 
                              AND deleted_at IS NULL 
                              AND (start_at IS NULL OR start_at <= ?) 
                              AND (expires_at IS NULL OR expires_at >= ?) 
                              AND type = ? 
                            ORDER BY FIELD(priority, 'urgent', 'important', 'normal') ASC, created_at DESC");
    $stmt->bind_param("sss", $now, $now, $type_filter);
    $stmt->execute();
    $announcements_res = $stmt->get_result();
} else {
    $type_filter = 'all';
    $stmt = $conn->prepare("SELECT * FROM announcements 
                            WHERE status = 'published' 
                              AND deleted_at IS NULL 
                              AND (start_at IS NULL OR start_at <= ?) 
                              AND (expires_at IS NULL OR expires_at >= ?) 
                            ORDER BY FIELD(priority, 'urgent', 'important', 'normal') ASC, created_at DESC");
    $stmt->bind_param("ss", $now, $now);
    $stmt->execute();
    $announcements_res = $stmt->get_result();
}

$announcements = [];
if ($announcements_res) {
    while ($row = $announcements_res->fetch_assoc()) {
        $announcements[] = $row;
    }
}

// Counts for filter pills
$counts = ['all' => 0, 'promotion' => 0, 'update' => 0, 'notice' => 0];
$cRes = $conn->query("SELECT type, COUNT(*) as c FROM announcements 
                      WHERE status = 'published' 
                        AND deleted_at IS NULL 
                        AND (start_at IS NULL OR start_at <= '{$now}') 
                        AND (expires_at IS NULL OR expires_at >= '{$now}') 
                      GROUP BY type");
if ($cRes) {
    while ($cRow = $cRes->fetch_assoc()) {
        $t = $cRow['type'];
        if (isset($counts[$t])) {
            $counts[$t] = (int)$cRow['c'];
        }
        $counts['all'] += (int)$cRow['c'];
    }
}

include 'includes/header.php';
?>

    <!-- Spatial Editorial Hero Header -->
    <section class="spatial-hero-section" style="min-height: 48vh; padding-top: 8.5rem; padding-bottom: 3.5rem; background: radial-gradient(circle at 50% 25%, #eff6ff 0%, #F8F7F4 85%);">
        <div class="spatial-hero-scrim"></div>
        <div class="spatial-hero-content" style="padding: 1rem; max-width: 900px;">
            <div class="spatial-hero-pill" style="margin-bottom: 1.25rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span>Studio Broadcasts &amp; Special Offers</span>
            </div>
            <h1 class="spatial-hero-title" style="font-size: clamp(2.3rem, 4.5vw, 3.8rem); margin-bottom: 1rem; color: #0f172a; line-height: 1.2;">
                Public Announcements &amp; Promotions
            </h1>
            <p class="spatial-hero-subtitle" style="margin-bottom: 0; color: #475569; font-size: clamp(1rem, 1.8vw, 1.15rem); line-height: 1.6;">
                Stay current with seasonal design privileges, limited-time promotions, new architectural services, and important studio notices.
            </p>
        </div>
    </section>

    <!-- Glassmorphic Filter & Live Search Bar -->
    <section style="background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border-top: 1px solid rgba(0, 87, 255, 0.1); border-bottom: 1px solid rgba(0, 87, 255, 0.1); padding: 1.25rem 0; position: sticky; top: 76px; z-index: 90; box-shadow: 0 8px 30px rgba(15, 23, 42, 0.04);">
        <div class="container">
            <div style="display: flex; flex-wrap: wrap; gap: 1.25rem; align-items: center; justify-content: space-between;">
                
                <!-- Category Filter Pills -->
                <div class="spatial-filter-pills" style="margin-bottom: 0; display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    <a href="announcements.php?type=all" class="spatial-filter-btn <?php echo $type_filter === 'all' ? 'active' : ''; ?>">
                        All (<?php echo $counts['all']; ?>)
                    </a>
                    <a href="announcements.php?type=promotion" class="spatial-filter-btn <?php echo $type_filter === 'promotion' ? 'active' : ''; ?>">
                        Promotions (<?php echo $counts['promotion']; ?>)
                    </a>
                    <a href="announcements.php?type=update" class="spatial-filter-btn <?php echo $type_filter === 'update' ? 'active' : ''; ?>">
                        Updates (<?php echo $counts['update']; ?>)
                    </a>
                    <a href="announcements.php?type=notice" class="spatial-filter-btn <?php echo $type_filter === 'notice' ? 'active' : ''; ?>">
                        Notices (<?php echo $counts['notice']; ?>)
                    </a>
                </div>

                <!-- Client-side Search Field -->
                <div style="position: relative; min-width: 280px;">
                    <input type="text" id="announcementSearchInput" placeholder="Filter announcements..." class="spatial-form-control" style="padding-left: 2.75rem; padding-right: 1rem; border-radius: var(--radius-full); font-size: 0.875rem; background: #ffffff; border: 1px solid #cbd5e1; height: 42px; width: 100%;" aria-label="Search announcements">
                    <span aria-hidden="true" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #0057FF; pointer-events: none;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- Announcements Cards Grid -->
    <section class="spatial-section" style="padding: 4.5rem 0 6.5rem; background: #F8F7F4;">
        <div class="container">
            <?php if (!empty($announcements)): ?>
                <div class="spatial-announcements-grid" id="announcementsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 2rem;">
                    <?php foreach ($announcements as $ann): ?>
                        <?php 
                            $type_badge_color = '#0057FF';
                            $type_bg_color = 'rgba(0, 87, 255, 0.1)';
                            if ($ann['type'] === 'promotion') {
                                $type_badge_color = '#ea580c';
                                $type_bg_color = 'rgba(234, 88, 12, 0.12)';
                            } elseif ($ann['type'] === 'update') {
                                $type_badge_color = '#059669';
                                $type_bg_color = 'rgba(16, 185, 129, 0.12)';
                            } elseif ($ann['type'] === 'notice') {
                                $type_badge_color = '#b45309';
                                $type_bg_color = 'rgba(217, 119, 6, 0.12)';
                            }
                        ?>
                        <article class="spatial-announcement-card" data-title="<?php echo htmlspecialchars(strtolower($ann['title'])); ?>" data-msg="<?php echo htmlspecialchars(strtolower($ann['message'])); ?>" style="background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.95); border-radius: 20px; box-shadow: 0 16px 36px rgba(0, 40, 120, 0.06); overflow: hidden; display: flex; flex-direction: column; transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.3s ease;">
                            
                            <!-- Cover Image or Decorative Graphical Header -->
                            <div style="position: relative; height: 210px; width: 100%; overflow: hidden; background: linear-gradient(135deg, #0b1736 0%, #1e3a8a 100%);">
                                <?php if (!empty($ann['cover_image']) && file_exists(__DIR__ . '/' . ltrim(str_replace('\\', '/', $ann['cover_image']), '/'))): ?>
                                    <img src="<?php echo htmlspecialchars($ann['cover_image']); ?>" alt="<?php echo htmlspecialchars($ann['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                                <?php else: ?>
                                    <!-- Elegant Architectural Graphic Pattern -->
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; background: radial-gradient(circle at 30% 30%, rgba(0, 87, 255, 0.45) 0%, rgba(11, 23, 54, 0.95) 80%);">
                                        <div style="position: absolute; inset: 0; opacity: 0.15; background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 20px 20px;"></div>
                                        <div style="display: flex; flex-direction: column; align-items: center; gap: 0.75rem; z-index: 1;">
                                            <div style="width: 52px; height: 52px; border-radius: 50%; background: rgba(255, 255, 255, 0.12); border: 1px solid rgba(255, 255, 255, 0.25); display: flex; align-items: center; justify-content: center; color: #ffffff;">
                                                <?php if ($ann['type'] === 'promotion'): ?>
                                                    <span style="font-size: 1.5rem;">🎉</span>
                                                <?php elseif ($ann['type'] === 'update'): ?>
                                                    <span style="font-size: 1.5rem;">📢</span>
                                                <?php else: ?>
                                                    <span style="font-size: 1.5rem;">✨</span>
                                                <?php endif; ?>
                                            </div>
                                            <span style="color: rgba(255, 255, 255, 0.85); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em;">Creative Touch Studio</span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Priority Ribbon if Urgent/Important -->
                                <?php if ($ann['priority'] === 'urgent'): ?>
                                    <span style="position: absolute; top: 1rem; right: 1rem; background: #ef4444; color: #ffffff; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; padding: 0.25rem 0.65rem; border-radius: 9999px; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);">
                                        Urgent Notice
                                    </span>
                                <?php elseif ($ann['priority'] === 'important'): ?>
                                    <span style="position: absolute; top: 1rem; right: 1rem; background: #0057FF; color: #ffffff; font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; padding: 0.25rem 0.65rem; border-radius: 9999px; box-shadow: 0 4px 12px rgba(0, 87, 255, 0.4);">
                                        Featured
                                    </span>
                                <?php endif; ?>

                                <!-- Type Tag -->
                                <span style="position: absolute; bottom: 1rem; left: 1rem; background: <?php echo $type_bg_color; ?>; color: <?php echo $type_badge_color; ?>; backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.4); font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; padding: 0.25rem 0.7rem; border-radius: 9999px;">
                                    <?php echo htmlspecialchars($ann['type']); ?>
                                </span>
                            </div>

                            <!-- Card Body Content -->
                            <div style="padding: 1.75rem; display: flex; flex-direction: column; flex: 1;">
                                <h3 style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0 0 0.85rem; line-height: 1.35;">
                                    <a href="announcement.php?id=<?php echo (int)$ann['id']; ?>" style="color: inherit; text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='#0057FF';" onmouseout="this.style.color='#0f172a';">
                                        <?php echo htmlspecialchars($ann['title']); ?>
                                    </a>
                                </h3>

                                <p style="color: #64748b; font-size: 0.9rem; line-height: 1.6; margin: 0 0 1.25rem; flex: 1;">
                                    <?php echo htmlspecialchars(mb_strimwidth(strip_tags($ann['message']), 0, 140, '...')); ?>
                                </p>

                                <!-- Dates Meta Information -->
                                <div style="display: flex; flex-wrap: wrap; gap: 0.85rem; font-size: 0.78rem; color: #94a3b8; border-top: 1px solid rgba(0, 0, 0, 0.06); padding-top: 1rem; margin-bottom: 1.25rem;">
                                    <div>
                                        <strong style="color: #475569;">Published:</strong> 
                                        <?php echo date('d M Y', strtotime($ann['created_at'])); ?>
                                    </div>
                                    <?php if (!empty($ann['expires_at'])): ?>
                                        <div style="color: #ea580c; font-weight: 600;">
                                            <strong>Valid Until:</strong> 
                                            <?php echo date('d M Y', strtotime($ann['expires_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Card Footer Action -->
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                                    <a href="announcement.php?id=<?php echo (int)$ann['id']; ?>" class="btn-spatial-bronze" style="width: 100%; justify-content: center; padding: 0.65rem 1.25rem; font-size: 0.82rem;">
                                        <span>View Details</span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Clean Empty State -->
                <div style="text-align: center; padding: 4.5rem 1.5rem; background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; max-width: 600px; margin: 0 auto; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: #eff6ff; color: #0057FF; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    </div>
                    <h3 style="font-family: var(--font-heading); font-size: 1.35rem; color: #0f172a; margin-bottom: 0.5rem;">No Active Announcements</h3>
                    <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 1.75rem;">
                        There are currently no active announcements in this category. Please check back soon or explore our full interior services.
                    </p>
                    <a href="announcements.php?type=all" class="btn-spatial-bronze" style="padding: 0.65rem 1.5rem;">
                        <span>View All Categories</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Consultation CTA Ribbon -->
    <section style="background: linear-gradient(135deg, #0b1736 0%, #172554 100%); padding: 5rem 0; color: #ffffff; text-align: center;">
        <div class="container" style="max-width: 820px;">
            <span style="color: #60a5fa; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.15em; display: block; margin-bottom: 0.75rem;">Turnkey Excellence</span>
            <h2 style="font-family: var(--font-heading); font-size: clamp(2rem, 3.5vw, 2.75rem); color: #ffffff; margin-bottom: 1.25rem;">
                Ready to Experience Bespoke Luxury?
            </h2>
            <p style="color: #94a3b8; font-size: 1.05rem; line-height: 1.6; margin-bottom: 2.25rem;">
                Whether designing a palatial residence, modular kitchen, or commercial headquarters, our architects bring your vision to life.
            </p>
            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="consultation.php" class="btn-spatial-bronze" style="padding: 0.85rem 2.25rem;">
                    <span>Schedule Free Consultation</span>
                </a>
                <a href="contact.php" class="btn-spatial-outline" style="border-color: rgba(255, 255, 255, 0.3); color: #ffffff; padding: 0.85rem 2rem;">
                    <span>Contact Studio</span>
                </a>
            </div>
        </div>
    </section>

    <!-- Search Filtering Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('announcementSearchInput');
        const grid = document.getElementById('announcementsGrid');
        if (searchInput && grid) {
            const cards = grid.querySelectorAll('.spatial-announcement-card');
            searchInput.addEventListener('input', function(e) {
                const term = e.target.value.toLowerCase().trim();
                cards.forEach(card => {
                    const title = card.getAttribute('data-title') || '';
                    const msg = card.getAttribute('data-msg') || '';
                    if (term === '' || title.includes(term) || msg.includes(term)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }
    });
    </script>

<?php include 'includes/footer.php'; ?>
