<?php
// Shared Admin Sidebar Component
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
}

// Fetch new quotes count for sidebar badge
if (!isset($sidebar_new_quotes) && isset($conn)) {
    $sidebar_new_quotes = $conn->query("SELECT COUNT(*) as c FROM quote_requests WHERE status = 'new'")->fetch_assoc()['c'] ?? 0;
} else if (!isset($sidebar_new_quotes)) {
    $sidebar_new_quotes = 0;
}

// Fetch unread leads count for sidebar badge
if (!isset($sidebar_unread_leads) && isset($conn)) {
    $sidebar_unread_leads = $conn->query("SELECT COUNT(*) as c FROM leads WHERE status = 'new'")->fetch_assoc()['c'] ?? 0;
} else if (!isset($sidebar_unread_leads)) {
    $sidebar_unread_leads = 0;
}

// Fetch unread contact inquiries count for sidebar badge
if (!isset($sidebar_unread_contacts) && isset($conn)) {
    $sidebar_unread_contacts = $conn->query("SELECT COUNT(*) as c FROM contact_inquiries WHERE status = 'new'")->fetch_assoc()['c'] ?? 0;
} else if (!isset($sidebar_unread_contacts)) {
    $sidebar_unread_contacts = 0;
}

// Fetch pending consultations count for sidebar badge
if (!isset($sidebar_pending_consultations) && isset($conn)) {
    $sidebar_pending_consultations = $conn->query("SELECT COUNT(*) as c FROM consultations WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0;
} else if (!isset($sidebar_pending_consultations)) {
    $sidebar_pending_consultations = 0;
}

$current_role = getAdminRole();
?>
<!-- Unified Admin Sidebar Navigation -->
<div class="admin-sidebar" style="background: #0b1736; border-right: 1px solid #1e293b; display: flex; flex-direction: column;">
    <div style="display: flex; align-items: center; gap: 0.85rem; padding-bottom: 1.5rem; border-bottom: 1px solid #1e293b;">
        <!-- CTI Blue Diamond Emblem -->
        <div style="width: 38px; height: 38px; position: relative; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <div style="position: absolute; inset: 0; transform: rotate(45deg); border: 2px solid #3b82f6; border-radius: 6px; background: rgba(37, 99, 235, 0.25);"></div>
            <span style="position: relative; font-family: var(--font-heading); font-size: 0.95rem; font-weight: 800; color: #60a5fa; letter-spacing: 0.05em;">CTI</span>
        </div>
        <div>
            <h2 style="font-family: var(--font-heading); font-size: 1.15rem; color: #ffffff; margin: 0; font-weight: 700; letter-spacing: -0.01em;">Creative Touch</h2>
            <span style="font-size: 0.72rem; color: #60a5fa; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600;">Executive Suite</span>
        </div>
    </div>

    <!-- Admin User Profile Capsule with Quick-Logout Button -->
    <div style="margin: 1.25rem 0; padding: 0.75rem 0.85rem; background: rgba(255,255,255,0.04); border-radius: var(--radius-md); display: flex; align-items: center; gap: 0.75rem; border: 1px solid #1e293b;">
        <a href="profile.php" title="View Profile" style="display: flex; align-items: center; gap: 0.75rem; text-decoration: none; overflow: hidden; flex: 1;">
            <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); border: 1.5px solid #60a5fa; display: flex; align-items: center; justify-content: center; color: #ffffff; font-weight: 700; font-size: 0.85rem; flex-shrink: 0;">
                <?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?>
            </div>
            <div style="overflow: hidden; flex: 1;">
                <div style="color: white; font-size: 0.85rem; font-weight: 600; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;" title="<?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Staff Member'); ?>">
                    <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Staff Member'); ?>
                </div>
                <div style="color: #94a3b8; font-size: 0.7rem; font-weight: 500; display: flex; align-items: center; gap: 0.3rem;">
                    <span style="width: 6px; height: 6px; border-radius: 50%; background: <?php echo $current_role === 'super_admin' ? '#818cf8' : ($current_role === 'receptionist' ? '#10b981' : '#38bdf8'); ?>;"></span>
                    <?php echo getAdminRoleLabel($current_role); ?>
                </div>
            </div>
        </a>
        <a href="logout.php" title="Logout Session" style="display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 6px; background: rgba(239, 68, 68, 0.15); color: #f87171; text-decoration: none; border: 1px solid rgba(239, 68, 68, 0.3); transition: all 0.2s; flex-shrink: 0;" onmouseover="this.style.background='#ef4444'; this.style.color='#fff';" onmouseout="this.style.background='rgba(239, 68, 68, 0.15)'; this.style.color='#f87171';">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
        </a>
    </div>

    <!-- Navigation Links with Bespoke SVGs -->
    <ul class="admin-sidebar-nav" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.3rem;">
        
        <!-- 1. Dashboard (All 3 Roles) -->
        <li>
            <a href="dashboard.php" class="<?php echo $current_page == 'dashboard' ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- 2. Leads CRM (All 3 Roles) -->
        <li>
            <a href="leads.php" class="<?php echo $current_page == 'leads' ? 'active' : ''; ?>" style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span>Leads CRM</span>
                </div>
                <?php if ($sidebar_unread_leads > 0): ?>
                    <span style="background: #ef4444; color: white; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; min-width: 18px;">
                        <?php echo $sidebar_unread_leads; ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>

        <!-- 3. Inquiries (All 3 Roles) -->
        <li>
            <a href="contact_inquiries.php" class="<?php echo $current_page == 'contact_inquiries' ? 'active' : ''; ?>" style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    <span>Inquiries</span>
                </div>
                <?php if ($sidebar_unread_contacts > 0): ?>
                    <span style="background: #ea580c; color: white; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; min-width: 18px;">
                        <?php echo $sidebar_unread_contacts; ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>

        <!-- 4. Consultations (All 3 Roles) -->
        <li>
            <a href="consultations.php" class="<?php echo $current_page == 'consultations' ? 'active' : ''; ?>" style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    <span>Consultations</span>
                </div>
                <?php if ($sidebar_pending_consultations > 0): ?>
                    <span style="background: #d97706; color: white; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; min-width: 18px;">
                        <?php echo $sidebar_pending_consultations; ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>

        <!-- 5. Quotations (All 3 Roles) -->
        <li>
            <a href="quotes.php" class="<?php echo ($current_page == 'quotes' || $current_page == 'quote_details') ? 'active' : ''; ?>" style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                    <span>Quotations</span>
                </div>
                <?php if ($sidebar_new_quotes > 0): ?>
                    <span style="background: #2563eb; color: #ffffff; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; min-width: 18px;">
                        <?php echo $sidebar_new_quotes; ?>
                    </span>
                <?php endif; ?>
            </a>
        </li>

        <!-- 6. Projects / Portfolio (All 3 Roles - View-only for Receptionist) -->
        <li>
            <a href="projects.php" class="<?php echo $current_page == 'projects' ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                <span>Portfolio</span>
            </a>
        </li>

        <!-- 7. Services (All 3 Roles - View-only for Receptionist) -->
        <li>
            <a href="services.php" class="<?php echo $current_page == 'services' ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                <span>Services</span>
            </a>
        </li>

        <!-- 8. Gallery (All 3 Roles - View-only for Receptionist) -->
        <li>
            <a href="gallery.php" class="<?php echo $current_page == 'gallery' ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                <span>Visual Gallery</span>
            </a>
        </li>

        <!-- 9. Testimonials (Super Admin & Admin ONLY — Hidden from Receptionist) -->
        <?php if (!isReceptionist()): ?>
        <li>
            <a href="testimonials.php" class="<?php echo $current_page == 'testimonials' ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <span>Testimonials</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- 10. Profile (All 3 Roles) -->
        <li>
            <a href="profile.php" class="<?php echo $current_page == 'profile' ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span>Staff Profile</span>
            </a>
        </li>

        <!-- SUPER ADMIN EXCLUSIVE SECTION (Hidden from Admin & Receptionist) -->
        <?php if (isSuperAdmin()): ?>
            <li style="margin-top: 0.65rem; padding-top: 0.65rem; border-top: 1px solid #1e293b;">
                <div style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.12em; color: #818cf8; padding: 0.2rem 0.6rem; font-weight: 700; opacity: 0.9; display: flex; align-items: center; gap: 0.4rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    <span>Super Admin</span>
                </div>
            </li>
            <li>
                <a href="blog.php" class="<?php echo $current_page == 'blog' ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                    <span>Editorial Blog</span>
                </a>
            </li>
            <li>
                <a href="team.php" class="<?php echo $current_page == 'team' ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span>Studio Team</span>
                </a>
            </li>
            <li>
                <a href="users.php" class="<?php echo $current_page == 'users' ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                    <span>Users &amp; Admin</span>
                </a>
            </li>
            <li>
                <a href="settings.php" class="<?php echo $current_page == 'settings' ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    <span>Site Settings</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <!-- Prominent Bottom Logout Button -->
    <div style="margin-top: auto; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.08); padding-bottom: 0.5rem;">
        <a href="logout.php" style="display: flex; align-items: center; justify-content: center; gap: 0.6rem; width: 100%; padding: 0.75rem 1rem; background: rgba(239, 68, 68, 0.12); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.25); border-radius: var(--radius-md); text-decoration: none; font-weight: 700; font-size: 0.85rem; transition: all 0.2s;" onmouseover="this.style.background='#ef4444'; this.style.color='#ffffff'; this.style.borderColor='#dc2626';" onmouseout="this.style.background='rgba(239, 68, 68, 0.12)'; this.style.color='#fca5a5'; this.style.borderColor='rgba(239, 68, 68, 0.25)';">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>End Session</span>
        </a>
    </div>
</div>
