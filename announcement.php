<?php
require_once 'includes/config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect('announcements.php');
}

$now = date('Y-m-d H:i:s');
$is_admin_preview = isAdminLoggedIn() && hasRole(['super_admin', 'admin']);

// Fetch announcement details
if ($is_admin_preview) {
    // Admins can view any announcement including drafts
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ? AND deleted_at IS NULL");
    $stmt->bind_param("i", $id);
} else {
    // Public visitors can only view active published announcements
    $stmt = $conn->prepare("SELECT * FROM announcements 
                            WHERE id = ? 
                              AND status = 'published' 
                              AND deleted_at IS NULL 
                              AND (start_at IS NULL OR start_at <= ?) 
                              AND (expires_at IS NULL OR expires_at >= ?)");
    $stmt->bind_param("iss", $id, $now, $now);
}

$stmt->execute();
$announcement = $stmt->get_result()->fetch_assoc();

if (!$announcement) {
    redirect('announcements.php');
}

// Record anonymous analytics view for public visitors (No PII)
if (!$is_admin_preview) {
    recordAnonymousAnnouncementView($conn, $id);
}

$page_title = htmlspecialchars($announcement['title']) . ' — Creative Touch Interiors';

// Resolve CTA URL and label
$cta_url = sanitizeCtaUrl($announcement['cta_url'] ?? '');
if (empty($cta_url)) {
    $cta_url = 'consultation.php';
}
$cta_text = !empty($announcement['cta_text']) ? trim($announcement['cta_text']) : 'Get a Quote';

// Fetch 2 other active announcements for related discovery
$relStmt = $conn->prepare("SELECT id, title, type, expires_at FROM announcements 
                           WHERE status = 'published' 
                             AND deleted_at IS NULL 
                             AND id != ? 
                             AND (start_at IS NULL OR start_at <= ?) 
                             AND (expires_at IS NULL OR expires_at >= ?) 
                           ORDER BY FIELD(priority, 'urgent', 'important', 'normal') ASC, id DESC LIMIT 2");
$relStmt->bind_param("iss", $id, $now, $now);
$relStmt->execute();
$related_res = $relStmt->get_result();
$related_announcements = [];
while ($r = $related_res->fetch_assoc()) {
    $related_announcements[] = $r;
}

include 'includes/header.php';
?>

    <!-- Breadcrumb & Back Strip -->
    <section style="padding-top: 8rem; padding-bottom: 1.5rem; background: #F8F7F4;">
        <div class="container" style="max-width: 920px;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <a href="announcements.php" style="display: inline-flex; align-items: center; gap: 0.5rem; color: #0057FF; text-decoration: none; font-weight: 700; font-size: 0.88rem; transition: transform 0.2s;" onmouseover="this.style.transform='translateX(-3px)'" onmouseout="this.style.transform='translateX(0)'">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    <span>Back to Announcements</span>
                </a>
            </div>
        </div>
    </section>

    <!-- Main Announcement Glass Container -->
    <main class="spatial-section" style="padding-top: 0.5rem; padding-bottom: 6rem; background: #F8F7F4;">
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
                                Urgent
                            </span>
                        <?php elseif ($announcement['priority'] === 'important'): ?>
                            <span style="font-size: 0.78rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; padding: 0.3rem 0.85rem; border-radius: 9999px; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;">
                                Important
                            </span>
                        <?php endif; ?>
                    </div>

                    <div style="font-size: 0.82rem; color: #64748b; font-weight: 500;">
                        <span>Broadcasted on <?php echo date('d F Y', strtotime($announcement['created_at'])); ?></span>
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
                <?php if (!empty($announcement['cover_image']) && file_exists(__DIR__ . '/' . ltrim(str_replace('\\', '/', $announcement['cover_image']), '/'))): ?>
                    <div style="margin-bottom: 2.5rem; border-radius: 16px; overflow: hidden; box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08); max-height: 480px; width: 100%;">
                        <img src="<?php echo htmlspecialchars($announcement['cover_image']); ?>" alt="<?php echo htmlspecialchars($announcement['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;">
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
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                    </a>
                </div>

                <!-- Anonymous Telemetry Disclaimer (Privacy Compliant) -->
                <div style="margin-top: 2rem; border-top: 1px solid #e2e8f0; padding-top: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; font-size: 0.8rem; color: #94a3b8;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <span>Public Notice &bull; Anonymous view counters protect your privacy</span>
                    </div>

                    <button type="button" onclick="navigator.clipboard.writeText(window.location.href); alert('Announcement link copied to clipboard!');" style="background: none; border: none; color: #0057FF; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        <span>Share Announcement</span>
                    </button>
                </div>

            </article>

            <!-- Other Active Announcements -->
            <?php if (!empty($related_announcements)): ?>
                <div style="margin-top: 3.5rem;">
                    <h3 style="font-family: var(--font-heading); font-size: 1.3rem; color: #0f172a; margin-bottom: 1.25rem;">
                        Other Active Announcements
                    </h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <?php foreach ($related_announcements as $rel): ?>
                            <a href="announcement.php?id=<?php echo (int)$rel['id']; ?>" style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 1.25rem 1.5rem; text-decoration: none; display: flex; flex-direction: column; gap: 0.5rem; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 10px 24px rgba(0,87,255,0.08)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                <span style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #0057FF;">
                                    <?php echo htmlspecialchars($rel['type']); ?>
                                </span>
                                <h4 style="margin: 0; font-size: 1.05rem; color: #0f172a; font-weight: 700;">
                                    <?php echo htmlspecialchars($rel['title']); ?>
                                </h4>
                                <?php if (!empty($rel['expires_at'])): ?>
                                    <span style="font-size: 0.75rem; color: #94a3b8;">
                                        Valid until <?php echo date('d M Y', strtotime($rel['expires_at'])); ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

<?php include 'includes/footer.php'; ?>
