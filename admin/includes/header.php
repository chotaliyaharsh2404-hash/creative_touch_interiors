<?php
/**
 * Creative Touch Interiors — Executive Top Bar & Admin Avatar Header
 * Theme: Primary Blue #0057FF | Light Surface #F8F7F4 / #FFFFFF
 */

if (!isset($conn)) {
    require_once dirname(__DIR__, 2) . '/includes/config.php';
}

$header_admin_id = (int)($_SESSION['admin_id'] ?? 0);
$header_admin_name = $_SESSION['admin_name'] ?? 'Administrator';
$header_admin_role = getAdminRole();
$header_admin_email = $_SESSION['admin_email'] ?? '';

// If email not in session, fetch once
if (empty($header_admin_email) && isset($conn) && $header_admin_id > 0) {
    $hStmt = $conn->prepare("SELECT email FROM admin_users WHERE id = ?");
    if ($hStmt) {
        $hStmt->bind_param("i", $header_admin_id);
        $hStmt->execute();
        $hRes = $hStmt->get_result()->fetch_assoc();
        $header_admin_email = $hRes['email'] ?? '';
    }
}

// Avatar resolution
$header_avatar_url = getAdminAvatarUrl();
$header_initial = getAdminInitials($header_admin_name);

// Unread notifications telemetry
$h_new_quotes = $sidebar_new_quotes ?? 0;
$h_new_leads = $sidebar_unread_leads ?? 0;
$h_new_contacts = $sidebar_unread_contacts ?? 0;
$h_new_consultations = $sidebar_pending_consultations ?? 0;
$h_total_notifications = $h_new_quotes + $h_new_leads + $h_new_contacts + $h_new_consultations;
?>
<!-- Top Executive Header Bar -->
<header class="cti-admin-header" id="ctiAdminHeader" role="banner">
    <!-- Left: Brand / Section Telemetry -->
    <div class="cti-header-left">
        <!-- Mobile Sidebar Toggle -->
        <button type="button" class="cti-mobile-toggle" id="ctiSidebarMobileToggle" aria-label="Toggle Navigation Sidebar">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <a href="dashboard.php" class="cti-header-brand" title="Creative Touch Interiors Executive Command">
            <span class="cti-brand-emblem">CTI</span>
            <div class="cti-brand-meta">
                <span class="cti-brand-name">Creative Touch Interiors</span>
                <span class="cti-brand-sub">Executive Administration</span>
            </div>
        </a>
    </div>

    <!-- Right: Telemetry Bell & Personal Admin Avatar -->
    <div class="cti-header-right">
        
        <!-- Live Notifications Bell -->
        <div class="cti-notif-wrapper" id="ctiNotifWrapper">
            <button type="button" class="cti-notif-btn" id="ctiNotifBtn" aria-expanded="false" aria-label="View notifications">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <?php if ($h_total_notifications > 0): ?>
                    <span class="cti-notif-badge"><?php echo $h_total_notifications > 99 ? '99+' : $h_total_notifications; ?></span>
                <?php endif; ?>
            </button>

            <!-- Notifications Flyout -->
            <div class="cti-notif-flyout" id="ctiNotifFlyout" role="dialog" aria-label="Notifications Overview">
                <div class="cti-notif-header">
                    <span class="cti-notif-title">Executive Alerts</span>
                    <span class="cti-notif-count"><?php echo $h_total_notifications; ?> Unread</span>
                </div>
                <div class="cti-notif-list">
                    <a href="quotes.php" class="cti-notif-item">
                        <span class="cti-notif-icon blue">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                        </span>
                        <div class="cti-notif-info">
                            <strong>Quotations Engine</strong>
                            <p><?php echo $h_new_quotes; ?> new quote requests awaiting pricing review.</p>
                        </div>
                    </a>
                    <a href="leads.php" class="cti-notif-item">
                        <span class="cti-notif-icon emerald">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                        </span>
                        <div class="cti-notif-info">
                            <strong>Leads Pipeline</strong>
                            <p><?php echo $h_new_leads; ?> inquiries in CRM pipeline.</p>
                        </div>
                    </a>
                    <a href="contact_inquiries.php" class="cti-notif-item">
                        <span class="cti-notif-icon amber">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </span>
                        <div class="cti-notif-info">
                            <strong>Contact Desk</strong>
                            <p><?php echo $h_new_contacts; ?> public messages pending response.</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Admin Avatar Profile Capsule -->
        <div class="cti-profile-capsule-wrapper" id="ctiProfileCapsuleWrapper">
            <button type="button" class="cti-profile-btn" id="ctiProfileDropdownBtn" aria-expanded="false" aria-haspopup="true" aria-label="Open administrative profile menu">
                
                <!-- Avatar Graphic: Image or Initials Fallback -->
                <div class="cti-avatar-frame">
                    <?php if (!empty($header_avatar_url)): ?>
                        <img src="<?php echo htmlspecialchars($header_avatar_url); ?>" 
                             alt="<?php echo htmlspecialchars($header_admin_name); ?>" 
                             class="cti-avatar-img"
                             onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                        <div class="cti-avatar-fallback" style="display: none;">
                            <?php echo htmlspecialchars($header_initial); ?>
                        </div>
                    <?php else: ?>
                        <div class="cti-avatar-fallback">
                            <?php echo htmlspecialchars($header_initial); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Admin Name & Role Meta -->
                <div class="cti-profile-meta">
                    <span class="cti-profile-name" title="<?php echo htmlspecialchars($header_admin_name); ?>">
                        <?php echo htmlspecialchars($header_admin_name); ?>
                    </span>
                    <span class="cti-profile-role cti-role-<?php echo htmlspecialchars($header_admin_role); ?>">
                        <?php echo htmlspecialchars(getAdminRoleLabel($header_admin_role)); ?>
                    </span>
                </div>

                <!-- Dropdown Caret Icon -->
                <span class="cti-caret" aria-hidden="true">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </span>
            </button>

            <!-- Profile Dropdown Menu Surface -->
            <div class="cti-profile-dropdown" id="ctiProfileDropdown" role="menu" aria-label="Staff Account Menu">
                <!-- Dropdown Profile Header Summary -->
                <div class="cti-dropdown-header">
                    <div class="cti-dropdown-avatar">
                        <?php if (!empty($header_avatar_url)): ?>
                            <img src="<?php echo htmlspecialchars($header_avatar_url); ?>" 
                                 alt="<?php echo htmlspecialchars($header_admin_name); ?>" 
                                 class="cti-avatar-img lg"
                                 onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                            <div class="cti-avatar-fallback lg" style="display: none;">
                                <?php echo htmlspecialchars($header_initial); ?>
                            </div>
                        <?php else: ?>
                            <div class="cti-avatar-fallback lg">
                                <?php echo htmlspecialchars($header_initial); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="cti-dropdown-user-details">
                        <div class="cti-dropdown-user-name" title="<?php echo htmlspecialchars($header_admin_name); ?>">
                            <?php echo htmlspecialchars($header_admin_name); ?>
                        </div>
                        <?php if (!empty($header_admin_email)): ?>
                            <div class="cti-dropdown-user-email" title="<?php echo htmlspecialchars($header_admin_email); ?>">
                                <?php echo htmlspecialchars($header_admin_email); ?>
                            </div>
                        <?php endif; ?>
                        <div class="cti-dropdown-user-badge cti-badge-<?php echo htmlspecialchars($header_admin_role); ?>">
                            <span class="cti-badge-dot"></span>
                            <span><?php echo htmlspecialchars(getAdminRoleLabel($header_admin_role)); ?></span>
                        </div>
                    </div>
                </div>

                <div class="cti-dropdown-divider"></div>

                <!-- Menu Links -->
                <nav class="cti-dropdown-links" role="none">
                    <!-- 1. My Profile -->
                    <a href="profile.php" class="cti-dropdown-link" role="menuitem">
                        <span class="cti-link-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <div class="cti-link-text">
                            <strong>My Profile</strong>
                            <small>Change picture &amp; account info</small>
                        </div>
                    </a>

                    <!-- 2. Account Settings (Super Admin -> settings.php, others -> profile.php password) -->
                    <?php if (isSuperAdmin()): ?>
                        <a href="settings.php" class="cti-dropdown-link" role="menuitem">
                            <span class="cti-link-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="3"></circle>
                                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                </svg>
                            </span>
                            <div class="cti-link-text">
                                <strong>Account Settings</strong>
                                <small>Studio &amp; system parameters</small>
                            </div>
                        </a>
                    <?php else: ?>
                        <a href="profile.php#security-card" class="cti-dropdown-link" role="menuitem">
                            <span class="cti-link-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                            </span>
                            <div class="cti-link-text">
                                <strong>Account Security</strong>
                                <small>Passphrase &amp; session guard</small>
                            </div>
                        </a>
                    <?php endif; ?>
                </nav>

                <div class="cti-dropdown-divider"></div>

                <!-- 3. Logout (Using standard logout.php) -->
                <div class="cti-dropdown-footer">
                    <a href="logout.php" class="cti-dropdown-logout-btn" role="menuitem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        <span>Logout Session</span>
                    </a>
                </div>
            </div>
        </div>

    </div>
</header>

<style>
/* ==========================================================================
   Creative Touch Interiors — Admin Header & Avatar Dropdown Styles
   Theme: #0057FF primary | #F8F7F4 background | #0F172A text
   ========================================================================== */
.cti-admin-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 0.75rem 1.25rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 18px rgba(0, 40, 120, 0.04);
    position: relative;
    z-index: 100;
}

.cti-header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.cti-mobile-toggle {
    display: none;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    color: #0f172a;
    cursor: pointer;
    transition: all 0.2s ease;
}
.cti-mobile-toggle:hover {
    background: #e2e8f0;
    color: #0057FF;
}

.cti-header-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    color: inherit;
}
.cti-brand-emblem {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: #0057FF;
    color: #ffffff;
    font-weight: 800;
    font-size: 0.78rem;
    letter-spacing: 0.08em;
    box-shadow: 0 4px 12px rgba(0, 87, 255, 0.32);
}
.cti-brand-meta {
    display: flex;
    flex-direction: column;
}
.cti-brand-name {
    font-family: var(--font-heading, inherit);
    font-size: 0.98rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.01em;
    line-height: 1.2;
}
.cti-brand-sub {
    font-size: 0.72rem;
    color: #0057FF;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.cti-header-right {
    display: flex;
    align-items: center;
    gap: 1rem;
}

/* Notification Bell */
.cti-notif-wrapper {
    position: relative;
}
.cti-notif-btn {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s ease;
}
.cti-notif-btn:hover,
.cti-notif-wrapper.active .cti-notif-btn {
    background: #eff6ff;
    color: #0057FF;
    border-color: #bfdbfe;
    box-shadow: 0 4px 12px rgba(0, 87, 255, 0.12);
}
.cti-notif-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    border-radius: 9999px;
    background: #0057FF;
    color: #ffffff;
    font-size: 0.68rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #ffffff;
    box-shadow: 0 2px 6px rgba(0, 87, 255, 0.4);
}

.cti-notif-flyout {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 320px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 16px 36px rgba(15, 23, 42, 0.12), 0 4px 12px rgba(0, 87, 255, 0.06);
    opacity: 0;
    visibility: hidden;
    transform: translateY(8px);
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 1050;
    overflow: hidden;
}
.cti-notif-wrapper.active .cti-notif-flyout {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
.cti-notif-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.9rem 1.15rem;
    border-bottom: 1px solid #f1f5f9;
    background: #fafafa;
}
.cti-notif-title {
    font-weight: 700;
    font-size: 0.88rem;
    color: #0f172a;
}
.cti-notif-count {
    font-size: 0.72rem;
    font-weight: 700;
    color: #0057FF;
    background: #eff6ff;
    padding: 0.15rem 0.55rem;
    border-radius: 9999px;
    border: 1px solid #bfdbfe;
}
.cti-notif-list {
    padding: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.cti-notif-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.65rem 0.75rem;
    border-radius: 10px;
    text-decoration: none;
    color: inherit;
    transition: background 0.18s ease;
}
.cti-notif-item:hover {
    background: #f8fafc;
}
.cti-notif-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.cti-notif-icon.blue { background: #eff6ff; color: #0057FF; }
.cti-notif-icon.emerald { background: #ecfdf5; color: #059669; }
.cti-notif-icon.amber { background: #fffbeb; color: #d97706; }
.cti-notif-info strong {
    display: block;
    font-size: 0.82rem;
    color: #0f172a;
}
.cti-notif-info p {
    font-size: 0.75rem;
    color: #64748b;
    margin: 0.15rem 0 0;
    line-height: 1.3;
}

/* Admin Avatar Capsule & Trigger */
.cti-profile-capsule-wrapper {
    position: relative;
}
.cti-profile-btn {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.35rem 0.85rem 0.35rem 0.35rem;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 9999px;
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}
.cti-profile-btn:hover,
.cti-profile-capsule-wrapper.active .cti-profile-btn {
    border-color: #0057FF;
    background: #f8fbff;
    box-shadow: 0 4px 16px rgba(0, 87, 255, 0.16);
    transform: translateY(-1px);
}

/* Avatar Frame & Graphics */
.cti-avatar-frame {
    width: 42px;
    height: 42px;
    position: relative;
    flex-shrink: 0;
    border-radius: 50%;
}
.cti-avatar-img {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #0057FF;
    box-shadow: 0 3px 10px rgba(0, 87, 255, 0.22);
    display: block;
    transition: transform 0.2s ease;
}
.cti-profile-btn:hover .cti-avatar-img {
    transform: scale(1.03);
}

.cti-avatar-fallback {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #0057FF;
    border: 2px solid #0057FF;
    box-shadow: 0 3px 10px rgba(0, 87, 255, 0.22);
    color: #ffffff;
    font-weight: 800;
    font-size: 1.05rem;
    display: flex;
    align-items: center;
    justify-content: center;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    user-select: none;
    transition: transform 0.2s ease;
}
.cti-profile-btn:hover .cti-avatar-fallback {
    transform: scale(1.03);
}

.cti-avatar-img.lg,
.cti-avatar-fallback.lg {
    width: 50px;
    height: 50px;
    font-size: 1.35rem;
    border-width: 2.5px;
}

/* Meta Labels */
.cti-profile-meta {
    display: flex;
    flex-direction: column;
    text-align: left;
    max-width: 140px;
}
.cti-profile-name {
    font-size: 0.88rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cti-profile-role {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    color: #0057FF;
    line-height: 1.2;
}

.cti-caret {
    color: #94a3b8;
    display: inline-flex;
    align-items: center;
    transition: transform 0.25s ease, color 0.2s ease;
}
.cti-profile-btn:hover .cti-caret,
.cti-profile-capsule-wrapper.active .cti-caret {
    color: #0057FF;
}
.cti-profile-capsule-wrapper.active .cti-caret {
    transform: rotate(180deg);
}

/* Profile Dropdown Card */
.cti-profile-dropdown {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    width: 280px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 18px 45px -10px rgba(0, 40, 120, 0.16), 0 8px 18px -6px rgba(15, 23, 42, 0.08);
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px) scale(0.98);
    transform-origin: top right;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 1050;
    overflow: hidden;
}
.cti-profile-capsule-wrapper.active .cti-profile-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

/* Dropdown Header User Summary */
.cti-dropdown-header {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1.15rem 1.15rem 0.95rem;
    background: #fafafa;
}
.cti-dropdown-avatar {
    flex-shrink: 0;
}
.cti-dropdown-user-details {
    overflow: hidden;
    flex: 1;
}
.cti-dropdown-user-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cti-dropdown-user-email {
    font-size: 0.76rem;
    color: #64748b;
    margin-top: 0.15rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cti-dropdown-user-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.4rem;
    padding: 0.15rem 0.6rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    background: rgba(0, 87, 255, 0.1);
    color: #0057FF;
    border: 1px solid rgba(0, 87, 255, 0.2);
}
.cti-badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #0057FF;
}
.cti-badge-super_admin {
    background: rgba(99, 102, 241, 0.1);
    color: #4f46e5;
    border-color: rgba(99, 102, 241, 0.25);
}
.cti-badge-super_admin .cti-badge-dot { background: #4f46e5; }
.cti-badge-receptionist {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
    border-color: rgba(16, 185, 129, 0.25);
}
.cti-badge-receptionist .cti-badge-dot { background: #059669; }

.cti-dropdown-divider {
    height: 1px;
    background: #f1f5f9;
    margin: 0;
}

/* Dropdown Menu Links */
.cti-dropdown-links {
    padding: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.cti-dropdown-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 0.85rem;
    border-radius: 10px;
    text-decoration: none;
    color: #1e293b;
    transition: all 0.18s ease;
}
.cti-dropdown-link:hover,
.cti-dropdown-link:focus-visible {
    background: rgba(0, 87, 255, 0.06);
    color: #0057FF;
    outline: none;
}
.cti-link-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #f8fafc;
    color: #64748b;
    flex-shrink: 0;
    transition: all 0.18s ease;
}
.cti-dropdown-link:hover .cti-link-icon,
.cti-dropdown-link:focus-visible .cti-link-icon {
    background: #0057FF;
    color: #ffffff;
    box-shadow: 0 4px 10px rgba(0, 87, 255, 0.3);
}
.cti-link-text {
    display: flex;
    flex-direction: column;
    text-align: left;
}
.cti-link-text strong {
    font-size: 0.85rem;
    font-weight: 600;
    color: inherit;
    line-height: 1.25;
}
.cti-link-text small {
    font-size: 0.72rem;
    color: #64748b;
    margin-top: 0.1rem;
}

/* Dropdown Footer & Logout */
.cti-dropdown-footer {
    padding: 0.5rem;
    background: #fafafa;
}
.cti-dropdown-logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    width: 100%;
    padding: 0.65rem 1rem;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.84rem;
    color: #dc2626;
    background: rgba(239, 68, 68, 0.06);
    border: 1px solid rgba(239, 68, 68, 0.15);
    transition: all 0.2s ease;
}
.cti-dropdown-logout-btn:hover,
.cti-dropdown-logout-btn:focus-visible {
    background: #dc2626;
    color: #ffffff;
    border-color: #dc2626;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
    outline: none;
}

/* ==========================================================================
   Responsive Adaptations
   ========================================================================== */
@media (max-width: 991px) {
    .cti-mobile-toggle {
        display: flex;
    }
}

@media (max-width: 640px) {
    .cti-admin-header {
        padding: 0.6rem 0.85rem;
        margin-bottom: 1.25rem;
    }
    .cti-brand-meta {
        display: none;
    }
    .cti-profile-meta {
        display: none;
    }
    .cti-profile-btn {
        padding: 0.25rem;
    }
    .cti-caret {
        display: none;
    }
    .cti-profile-dropdown {
        width: 260px;
    }
}
</style>

<script>
/**
 * Interactive Profile Dropdown & Notification Handlers
 * Smooth keyboard navigation, Escape dismiss, and outside click handling.
 */
document.addEventListener('DOMContentLoaded', function () {
    const profileWrapper = document.getElementById('ctiProfileCapsuleWrapper');
    const profileBtn = document.getElementById('ctiProfileDropdownBtn');
    const profileDropdown = document.getElementById('ctiProfileDropdown');

    const notifWrapper = document.getElementById('ctiNotifWrapper');
    const notifBtn = document.getElementById('ctiNotifBtn');

    const mobileToggle = document.getElementById('ctiSidebarMobileToggle');
    const adminSidebar = document.querySelector('.admin-sidebar');

    function closeAllDropdowns() {
        if (profileWrapper) {
            profileWrapper.classList.remove('active');
            if (profileBtn) profileBtn.setAttribute('aria-expanded', 'false');
        }
        if (notifWrapper) {
            notifWrapper.classList.remove('active');
            if (notifBtn) notifBtn.setAttribute('aria-expanded', 'false');
        }
    }

    // Toggle Profile Dropdown
    if (profileBtn && profileWrapper) {
        profileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = profileWrapper.classList.contains('active');
            closeAllDropdowns();
            if (!isOpen) {
                profileWrapper.classList.add('active');
                profileBtn.setAttribute('aria-expanded', 'true');
            }
        });
    }

    // Toggle Notifications Flyout
    if (notifBtn && notifWrapper) {
        notifBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = notifWrapper.classList.contains('active');
            closeAllDropdowns();
            if (!isOpen) {
                notifWrapper.classList.add('active');
                notifBtn.setAttribute('aria-expanded', 'true');
            }
        });
    }

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#ctiProfileCapsuleWrapper') && !e.target.closest('#ctiNotifWrapper')) {
            closeAllDropdowns();
        }
    });

    // Close on Escape key press
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            closeAllDropdowns();
        }
    });

    // Mobile Sidebar Drawer Toggle
    if (mobileToggle && adminSidebar) {
        mobileToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            if (adminSidebar.style.display === 'flex' || adminSidebar.classList.contains('mobile-open')) {
                adminSidebar.classList.remove('mobile-open');
            } else {
                adminSidebar.classList.add('mobile-open');
            }
        });
    }
});
</script>
