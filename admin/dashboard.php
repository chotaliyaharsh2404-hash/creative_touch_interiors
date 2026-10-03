<?php
require_once '../includes/config.php';

// Enforce admin authentication and prevent caching
requireAdminLogin('login.php');

// Run automatic system self-healing check (seeds empty tables, syncs site visits) — Super Admin only
if (isSuperAdmin()) {
    require_once '../includes/system_repair.php';
    runSystemRepairs($conn);
}

// Check for Welcome Popup flash flag
$show_welcome = false;
if (!empty($_SESSION['show_welcome_modal'])) {
    $show_welcome = true;
    unset($_SESSION['show_welcome_modal']);
}

// Fetch comprehensive metrics
$projects_count = $conn->query("SELECT COUNT(*) as count FROM projects")->fetch_assoc()['count'] ?? 0;
$completed_projects = $conn->query("SELECT COUNT(*) as count FROM projects WHERE status = 'completed'")->fetch_assoc()['count'] ?? 0;
$services_count = $conn->query("SELECT COUNT(*) as count FROM services")->fetch_assoc()['count'] ?? 0;
$quotes_count = $conn->query("SELECT COUNT(*) as count FROM quote_requests")->fetch_assoc()['count'] ?? 0;
$new_quotes_count = $conn->query("SELECT COUNT(*) as count FROM quote_requests WHERE status = 'new'")->fetch_assoc()['count'] ?? 0;
$leads_count = $conn->query("SELECT COUNT(*) as count FROM leads WHERE project_type != 'general_inquiry' OR project_type IS NULL")->fetch_assoc()['count'] ?? 0;
$new_leads_count = $conn->query("SELECT COUNT(*) as count FROM leads WHERE status = 'new' AND (project_type != 'general_inquiry' OR project_type IS NULL)")->fetch_assoc()['count'] ?? 0;
$contacts_count = $conn->query("SELECT COUNT(*) as count FROM contact_inquiries")->fetch_assoc()['count'] ?? 0;
$new_contacts_count = $conn->query("SELECT COUNT(*) as count FROM contact_inquiries WHERE status = 'new'")->fetch_assoc()['count'] ?? 0;
$consultations_count = $conn->query("SELECT COUNT(*) as count FROM consultations")->fetch_assoc()['count'] ?? 0;
$pending_consultations = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0;
$blogs_count = $conn->query("SELECT COUNT(*) as count FROM blogs")->fetch_assoc()['count'] ?? 0;
$users_count = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;

// Fetch recent quote requests
$recent_quotes_sql = "SELECT * FROM quote_requests ORDER BY created_at DESC LIMIT 5";
$recent_quotes_result = $conn->query($recent_quotes_sql);

// Fetch recent project leads
$recent_leads_sql = "SELECT * FROM leads WHERE project_type != 'general_inquiry' OR project_type IS NULL ORDER BY created_at DESC LIMIT 5";
$recent_leads_result = $conn->query($recent_leads_sql);

// Fetch recent contact inquiries
$recent_contacts_sql = "SELECT * FROM contact_inquiries ORDER BY created_at DESC LIMIT 5";
$recent_contacts_result = $conn->query($recent_contacts_sql);

// Fetch recent projects
$recent_projects_sql = "SELECT * FROM projects ORDER BY created_at DESC LIMIT 6";
$recent_projects_result = $conn->query($recent_projects_sql);

// Aggregations for Chart.js Analytics (Past 6 Months Activity)
$months = [];
$quote_counts = [];
$inquiry_counts = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $mLabel = date('M Y', strtotime("-$i months"));
    $months[] = $mLabel;
    
    $q_cnt = $conn->query("SELECT COUNT(*) as c FROM quote_requests WHERE DATE_FORMAT(created_at, '%Y-%m') = '{$m}'")->fetch_assoc()['c'] ?? 0;
    $quote_counts[] = (int)$q_cnt;
    
    $inq_cnt = $conn->query("SELECT COUNT(*) as c FROM contact_inquiries WHERE DATE_FORMAT(created_at, '%Y-%m') = '{$m}'")->fetch_assoc()['c'] ?? 0;
    $inquiry_counts[] = (int)$inq_cnt;
}

// Portfolio Project Category Distribution for Doughnut Chart
$cat_distribution = [];
$cat_res = $conn->query("SELECT category, COUNT(*) as c FROM projects GROUP BY category");
if ($cat_res) {
    while ($cr = $cat_res->fetch_assoc()) {
        $cName = !empty($cr['category']) ? ucfirst($cr['category']) : 'Bespoke';
        $cat_distribution[$cName] = (int)$cr['c'];
    }
}
if (empty($cat_distribution)) {
    $cat_distribution = ['Residential' => 3, 'Commercial' => 2, 'Luxury Villa' => 1];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Command Suite — Creative Touch Interiors</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/spatial-3d.css">

    <!-- Chart.js Vendor -->
    <script src="../js/vendor/chart.umd.min.js"></script>

    <style>
        .admin-layout {
            display: flex;
            min-height: 100vh;
            background: #F8F7F4;
            color: #0f172a;
        }
        .admin-content-viewport {
            flex: 1;
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 2.25rem 2.5rem;
            overflow-y: auto;
            position: relative;
        }
        .executive-kpi-card {
            background: rgba(255, 255, 255, 0.78);
            backdrop-filter: blur(24px) saturate(190%);
            -webkit-backdrop-filter: blur(24px) saturate(190%);
            border: 1px solid rgba(255, 255, 255, 0.90);
            box-shadow: 0 14px 35px rgba(0, 40, 120, 0.06), inset 0 1.5px 2px rgba(255, 255, 255, 0.95);
            border-radius: var(--radius-xl);
            padding: 1.5rem 1.65rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s cubic-bezier(0.25, 1, 0.35, 1);
            position: relative;
            overflow: hidden;
        }
        .executive-kpi-card:hover {
            transform: translateY(-4px) scale(1.01);
            border-color: rgba(0, 87, 255, 0.35);
            box-shadow: 0 20px 48px rgba(0, 87, 255, 0.16), inset 0 2px 2px #ffffff;
        }
        .kpi-icon-box {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }
        .table-spatial-row:hover {
            background: #f1f5f9;
        }
        @keyframes slideInToast {
            from { opacity: 0; transform: translateX(30px) translateY(-10px); }
            to { opacity: 1; transform: translateX(0) translateY(0); }
        }
    </style>
</head>
<body style="background: #F8F7F4; color: #0f172a;">

    <div class="admin-layout">
        
        <!-- Sidebar Navigation -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Dashboard Viewport -->
        <main class="admin-content-viewport">
            
            <?php if (!empty($_GET['error'])): ?>
                <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.35); color: #fca5a5; padding: 1rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <span style="font-size: 0.9rem; font-weight: 500;"><?php echo htmlspecialchars($_GET['error']); ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; color: #fca5a5; cursor: pointer; padding: 0.25rem; font-size: 1.25rem; line-height: 1;">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Dashboard Welcome Banner -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.35rem;">
                        <span class="section-tag" style="margin-bottom: 0;">CTI Executive Operations</span>
                        <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; color: #10b981; background: rgba(16, 185, 129, 0.12); padding: 0.15rem 0.65rem; border-radius: 9999px; border: 1px solid rgba(16, 185, 129, 0.25);">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981; box-shadow: 0 0 8px #10b981;"></span>
                            Systems Active
                        </span>
                    </div>
                    <h1 style="font-family: var(--font-heading); font-size: 2.25rem; color: #0f172a; font-weight: 700; margin: 0; letter-spacing: -0.01em;">
                        Command Center
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0.35rem 0 0;">
                        Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator'); ?></strong>. Here is your practice's live architectural and quotation telemetry.
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem; align-items: center;">
                    <div class="glass-card" style="padding: 0.5rem 1rem; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 600; color: #1e40af; display: flex; align-items: center; gap: 0.5rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #2563eb;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?php echo date('l, F j, Y'); ?>
                    </div>
                </div>
            </div>

            <!-- Primary Metrics 3D KPI Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                
                <!-- 1. Total Leads -->
                <div class="executive-kpi-card" style="border-left: 3px solid #0057FF;">
                    <div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">Total Leads</div>
                        <div style="font-size: 2rem; font-weight: 700; color: #0f172a; line-height: 1.2; margin: 0.25rem 0; font-family: var(--font-heading);"><?php echo $leads_count; ?></div>
                        <div style="font-size: 0.8rem; font-weight: 600;">
                            <?php if ($new_leads_count > 0): ?>
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: #0057FF;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                    <?php echo $new_leads_count; ?> New Leads
                                </span>
                            <?php else: ?>
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: #10b981;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    Pipeline Current
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="kpi-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                </div>

                <!-- 2. Total Clients -->
                <div class="executive-kpi-card" style="border-left: 3px solid #0057FF;">
                    <div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">Total Clients</div>
                        <div style="font-size: 2rem; font-weight: 700; color: #0f172a; line-height: 1.2; margin: 0.25rem 0; font-family: var(--font-heading);"><?php echo $users_count; ?></div>
                        <div style="font-size: 0.8rem; color: #0057FF; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Registered Accounts
                        </div>
                    </div>
                    <div class="kpi-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    </div>
                </div>

                <!-- 3. Quotation Requests -->
                <div class="executive-kpi-card" style="border-left: 3px solid #0057FF;">
                    <div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">Quotations</div>
                        <div style="font-size: 2rem; font-weight: 700; color: #0f172a; line-height: 1.2; margin: 0.25rem 0; font-family: var(--font-heading);"><?php echo $quotes_count; ?></div>
                        <div style="font-size: 0.8rem; font-weight: 600;">
                            <?php if ($new_quotes_count > 0): ?>
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: #0057FF;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                    <?php echo $new_quotes_count; ?> New Requests
                                </span>
                            <?php else: ?>
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: #10b981;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    All Reviewed
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="kpi-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </div>
                </div>

                <!-- 4. Total Projects -->
                <div class="executive-kpi-card" style="border-left: 3px solid #10b981;">
                    <div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">Projects</div>
                        <div style="font-size: 2rem; font-weight: 700; color: #0f172a; line-height: 1.2; margin: 0.25rem 0; font-family: var(--font-heading);"><?php echo $projects_count; ?></div>
                        <div style="font-size: 0.8rem; color: #10b981; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php echo $completed_projects; ?> Completed
                        </div>
                    </div>
                    <div class="kpi-icon-box" style="background: #f0fdf4; color: #10b981; border-color: #bbf7d0;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    </div>
                </div>

                <!-- 5. Consultations -->
                <div class="executive-kpi-card" style="border-left: 3px solid #0057FF;">
                    <div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">Consultations</div>
                        <div style="font-size: 2rem; font-weight: 700; color: #0f172a; line-height: 1.2; margin: 0.25rem 0; font-family: var(--font-heading);"><?php echo $consultations_count; ?></div>
                        <div style="font-size: 0.8rem; font-weight: 600;">
                            <?php if ($pending_consultations > 0): ?>
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: #0057FF;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <?php echo $pending_consultations; ?> Pending
                                </span>
                            <?php else: ?>
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: #10b981;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    Calendar Current
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="kpi-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                </div>

                <!-- 6. Recent Inquiries -->
                <div class="executive-kpi-card" style="border-left: 3px solid #f97316;">
                    <div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">Inquiries</div>
                        <div style="font-size: 2rem; font-weight: 700; color: #0f172a; line-height: 1.2; margin: 0.25rem 0; font-family: var(--font-heading);"><?php echo $contacts_count; ?></div>
                        <div style="font-size: 0.8rem; font-weight: 600;">
                            <?php if ($new_contacts_count > 0): ?>
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: #f97316;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    <?php echo $new_contacts_count; ?> Unread
                                </span>
                            <?php else: ?>
                                <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: var(--text-muted);">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    All Replied
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="kpi-icon-box" style="background: #fff7ed; color: #f97316; border-color: #fed7aa;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                </div>

            </div>

            <!-- Interactive Chart.js Telemetry Grid -->
            <div class="grid grid-2" style="gap: 1.5rem; margin-bottom: 2rem;">
                
                <!-- Chart 1: Quotation & Inquiries Activity Stream -->
                <div class="glass-card" style="padding: 1.75rem; border: 1px solid var(--glass-border);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <div>
                            <h3 style="font-family: var(--font-heading); font-size: 1.15rem; color: #0f172a; margin: 0;">
                                Inbound Practice Velocity
                            </h3>
                            <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0.25rem 0 0;">6-Month quotation requests &amp; client inquiries trend</p>
                        </div>
                        <span class="badge" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.72rem;">Live Aggregation</span>
                    </div>
                    <div style="height: 240px; position: relative;">
                        <canvas id="velocityChart"></canvas>
                    </div>
                </div>

                <!-- Chart 2: Project Portfolio Distribution -->
                <div class="glass-card" style="padding: 1.75rem; border: 1px solid var(--glass-border);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <div>
                            <h3 style="font-family: var(--font-heading); font-size: 1.15rem; color: #0f172a; margin: 0;">
                                Architectural Portfolio Mix
                            </h3>
                            <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0.25rem 0 0;">Distribution of curated projects by sector discipline</p>
                        </div>
                        <span class="badge" style="background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; font-size: 0.72rem;"><?php echo $projects_count; ?> Total Works</span>
                    </div>
                    <div style="height: 240px; position: relative; display: flex; align-items: center; justify-content: center;">
                        <canvas id="portfolioChart"></canvas>
                    </div>
                </div>

            </div>

            <!-- Recent Quotation Requests (Prominent Table) -->
            <div class="glass-card" style="padding: 1.75rem; margin-bottom: 2rem; border: 1px solid var(--glass-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <h3 style="font-family: var(--font-heading); font-size: 1.25rem; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #2563eb;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            Recent Quotation Calculations
                            <?php if ($new_quotes_count > 0): ?>
                                <span class="badge" style="background: #eff6ff; color: #1d4ed8; font-size: 0.72rem; border: 1px solid #bfdbfe;">
                                    <?php echo $new_quotes_count; ?> New
                                </span>
                            <?php endif; ?>
                        </h3>
                        <p style="color: var(--text-muted); font-size: 0.825rem; margin: 0.2rem 0 0;">Latest mathematical project estimates and site-visit inquiries</p>
                    </div>
                    <a href="quotes.php" class="btn btn-primary" style="font-size: 0.85rem; padding: 0.5rem 1.15rem;">
                        View All Quotations &rarr;
                    </a>
                </div>

                <?php if ($recent_quotes_result && $recent_quotes_result->num_rows > 0): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--glass-border); text-align: left; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                    <th style="padding: 0.75rem 1rem;">Quote Reference</th>
                                    <th style="padding: 0.75rem 1rem;">Customer</th>
                                    <th style="padding: 0.75rem 1rem;">Typology &amp; Scale</th>
                                    <th style="padding: 0.75rem 1rem;">Estimated Valuation</th>
                                    <th style="padding: 0.75rem 1rem;">Status</th>
                                    <th style="padding: 0.75rem 1rem;">Date</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($q = $recent_quotes_result->fetch_assoc()): 
                                    $st = getQuoteStatusInfo($q['status']);
                                ?>
                                <tr class="table-spatial-row" style="border-bottom: 1px solid #e2e8f0; transition: background 0.2s;">
                                    <td style="padding: 0.85rem 1rem; font-weight: 700; color: #2563eb; font-family: monospace;">
                                        <?php echo htmlspecialchars($q['quote_number']); ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <div style="font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($q['customer_name'] ?? ($q['name'] ?? 'Client')); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($q['phone'] ?? ''); ?></div>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <div style="font-weight: 500; text-transform: capitalize; color: #0f172a;"><?php echo htmlspecialchars(str_replace('_', ' ', $q['project_type'] ?? '')); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo number_format((float)($q['approx_area'] ?? ($q['carpet_area'] ?? 0))); ?> sq.ft • <?php echo htmlspecialchars($q['city'] ?? ''); ?></div>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-weight: 700; color: #10b981;">
                                        ₹<?php 
                                             $admAmount = !empty($q['final_estimate']) && $q['final_estimate'] > 0 ? (float)$q['final_estimate'] : (float)($q['estimated_total'] ?? 0);
                                             echo number_format($admAmount, 2); 
                                        ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem;">
                                        <span class="badge" style="background: <?php echo $st['bg']; ?>; color: <?php echo $st['color']; ?>; border: 1px solid <?php echo $st['border']; ?>; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;">
                                            <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:currentColor;"></span>
                                            <?php echo $st['label']; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; font-size: 0.8rem; color: var(--text-muted);">
                                        <?php echo date('M d, Y', strtotime($q['created_at'])); ?>
                                    </td>
                                    <td style="padding: 0.85rem 1rem; text-align: right;">
                                        <a href="quote_details.php?id=<?php echo $q['id']; ?>" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">
                                            Manage Proposal &rarr;
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted); padding: 1.5rem; text-align: center; margin: 0;">No quotation requests recorded yet.</p>
                <?php endif; ?>
            </div>

            <!-- Operational Activity Grids -->
            <div class="grid grid-2" style="gap: 1.5rem; margin-bottom: 2rem;">
                
                <!-- Recent Project Leads Card -->
                <div class="glass-card" style="padding: 1.75rem; border: 1px solid var(--glass-border);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <div>
                            <h3 style="font-family: var(--font-heading); font-size: 1.15rem; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #2563eb;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                Recent Leads CRM
                            </h3>
                            <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0.2rem 0 0;">Direct inquiries from prospective project sponsors</p>
                        </div>
                        <a href="leads.php" style="color: #2563eb; font-size: 0.85rem; font-weight: 600; text-decoration: none;">View CRM &rarr;</a>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php if ($recent_leads_result && $recent_leads_result->num_rows > 0): ?>
                            <?php while ($lead = $recent_leads_result->fetch_assoc()): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1rem; background: #f8fafc; border-radius: var(--radius-md); border: 1px solid #e2e8f0;" class="table-spatial-row">
                                    <div>
                                        <div style="font-weight: 600; color: #0f172a; font-size: 0.9rem;"><?php echo htmlspecialchars($lead['name']); ?></div>
                                        <div style="color: var(--text-muted); font-size: 0.775rem;">
                                            <?php echo htmlspecialchars($lead['email']); ?> • <span style="text-transform: capitalize; color: #2563eb;"><?php echo htmlspecialchars(str_replace('_', ' ', $lead['project_type'] ?? 'Interior')); ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="badge" style="background: <?php echo $lead['status'] == 'new' ? '#fee2e2' : ($lead['status'] == 'converted' ? '#dcfce7' : '#dbeafe'); ?>; color: <?php echo $lead['status'] == 'new' ? '#dc2626' : ($lead['status'] == 'converted' ? '#16a34a' : '#2563eb'); ?>; border: 1px solid currentColor;">
                                            <?php echo htmlspecialchars(ucfirst($lead['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color: var(--text-muted); padding: 1.5rem; text-align: center;">No project leads received yet.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Contact Inquiries Card -->
                <div class="glass-card" style="padding: 1.75rem; border: 1px solid var(--glass-border);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                        <div>
                            <h3 style="font-family: var(--font-heading); font-size: 1.15rem; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #2563eb;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                Inbound Messages
                            </h3>
                            <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0.2rem 0 0;">Direct inquiries from the Studio Contact interface</p>
                        </div>
                        <a href="contact_inquiries.php" style="color: #2563eb; font-size: 0.85rem; font-weight: 600; text-decoration: none;">View All &rarr;</a>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php if ($recent_contacts_result && $recent_contacts_result->num_rows > 0): ?>
                            <?php while ($msg = $recent_contacts_result->fetch_assoc()): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1rem; background: #f8fafc; border-radius: var(--radius-md); border: 1px solid #e2e8f0;" class="table-spatial-row">
                                    <div>
                                        <div style="font-weight: 600; color: #0f172a; font-size: 0.9rem;"><?php echo htmlspecialchars($msg['name']); ?></div>
                                        <div style="color: var(--text-muted); font-size: 0.775rem;">
                                            <?php echo htmlspecialchars($msg['email']); ?> • <span style="color: var(--text-secondary);"><?php echo htmlspecialchars($msg['subject']); ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="badge" style="background: <?php echo $msg['status'] == 'new' ? '#ffedd5' : ($msg['status'] == 'replied' ? '#dbeafe' : '#dcfce7'); ?>; color: <?php echo $msg['status'] == 'new' ? '#c2410c' : ($msg['status'] == 'replied' ? '#1d4ed8' : '#15803d'); ?>; border: 1px solid currentColor;">
                                            <?php echo htmlspecialchars(ucfirst($msg['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem 1rem; color: var(--text-muted);">
                                No contact messages received yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Recent Portfolio Showcase -->
            <div class="glass-card" style="padding: 1.75rem; border: 1px solid var(--glass-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <h3 style="font-family: var(--font-heading); font-size: 1.15rem; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.6rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #2563eb;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                            Live Portfolio Works
                        </h3>
                        <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0.2rem 0 0;">Recent projects displayed across the public studio showcase</p>
                    </div>
                    <a href="projects.php" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.4rem 0.85rem;">Manage Portfolio &rarr;</a>
                </div>

                <div class="grid grid-3" style="gap: 1.25rem;">
                    <?php if ($recent_projects_result && $recent_projects_result->num_rows > 0): ?>
                        <?php while ($project = $recent_projects_result->fetch_assoc()): ?>
                            <div class="card-3d" style="border: 1px solid #e2e8f0; border-radius: var(--radius-md); overflow: hidden; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: all 0.3s ease;">
                                <div style="height: 140px; overflow: hidden; position: relative; background: #f1f5f9;">
                                    <?php if (!empty($project['image'])): ?>
                                        <img src="../<?php echo htmlspecialchars($project['image']); ?>" alt="<?php echo htmlspecialchars($project['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-muted);">
                                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="9" y1="22" x2="9" y2="22.01"/><line x1="15" y1="22" x2="15" y2="22.01"/><line x1="9" y1="6" x2="9" y2="6.01"/><line x1="15" y1="6" x2="15" y2="6.01"/></svg>
                                        </div>
                                    <?php endif; ?>
                                    <span class="badge" style="position: absolute; top: 0.5rem; right: 0.5rem; font-size: 0.65rem; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(8px); color: #1e3a8a; border: 1px solid #bfdbfe;">
                                        <?php echo htmlspecialchars(ucfirst($project['category'] ?? 'Residential')); ?>
                                    </span>
                                </div>
                                <div style="padding: 1rem;">
                                    <h4 style="font-size: 0.95rem; font-weight: 700; margin: 0 0 0.25rem; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($project['title']); ?>
                                    </h4>
                                    <p style="color: var(--text-muted); font-size: 0.775rem; margin: 0; display: flex; align-items: center; gap: 0.35rem;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #2563eb;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                        <?php echo htmlspecialchars($project['location'] ?: 'Surat, Gujarat'); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="color: var(--text-muted); padding: 1.5rem; grid-column: 1 / -1; text-align: center;">No projects found.</p>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>

    <!-- Chart.js Scripts Initialization -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Chart 1: Practice Velocity Line/Area Chart
        const ctxVel = document.getElementById('velocityChart').getContext('2d');
        const gradientBlue1 = ctxVel.createLinearGradient(0, 0, 0, 240);
        gradientBlue1.addColorStop(0, 'rgba(37, 99, 235, 0.35)');
        gradientBlue1.addColorStop(1, 'rgba(37, 99, 235, 0.0)');

        const gradientBlue2 = ctxVel.createLinearGradient(0, 0, 0, 240);
        gradientBlue2.addColorStop(0, 'rgba(96, 165, 250, 0.3)');
        gradientBlue2.addColorStop(1, 'rgba(96, 165, 250, 0.0)');

        new Chart(ctxVel, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($months); ?>,
                datasets: [
                    {
                        label: 'Quotations',
                        data: <?php echo json_encode($quote_counts); ?>,
                        borderColor: '#2563eb',
                        backgroundColor: gradientBlue1,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#1d4ed8',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    },
                    {
                        label: 'Inquiries',
                        data: <?php echo json_encode($inquiry_counts); ?>,
                        borderColor: '#60a5fa',
                        backgroundColor: gradientBlue2,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#93c5fd',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#475569',
                            font: { family: 'Plus Jakarta Sans', size: 11 }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(0, 0, 0, 0.06)' },
                        ticks: { color: '#64748b', font: { family: 'Plus Jakarta Sans', size: 10 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.06)' },
                        ticks: { color: '#64748b', font: { family: 'Plus Jakarta Sans', size: 10 }, stepSize: 1 }
                    }
                }
            }
        });

        // Chart 2: Portfolio Distribution Doughnut Chart
        const ctxPort = document.getElementById('portfolioChart').getContext('2d');
        const portLabels = <?php echo json_encode(array_keys($cat_distribution)); ?>;
        const portData = <?php echo json_encode(array_values($cat_distribution)); ?>;
        const colorPalette = ['#2563eb', '#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#06b6d4'];

        new Chart(ctxPort, {
            type: 'doughnut',
            data: {
                labels: portLabels,
                datasets: [{
                    data: portData,
                    backgroundColor: colorPalette.slice(0, portLabels.length),
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: '#475569',
                            font: { family: 'Plus Jakarta Sans', size: 11 },
                            boxWidth: 12
                        }
                    }
                },
                cutout: '70%'
            }
        });
    });
    </script>

    <?php if ($show_welcome): ?>
    <!-- WELCOME POPUP TOAST -->
    <div id="welcomeToast" style="position: fixed; top: 1.5rem; right: 1.5rem; z-index: 99999; background: #ffffff; border: 1px solid #bfdbfe; border-left: 4px solid #2563eb; border-radius: var(--radius-md); padding: 0.9rem 1.15rem; box-shadow: 0 10px 25px rgba(37, 99, 235, 0.15); display: flex; align-items: center; gap: 0.85rem; max-width: 420px; animation: slideInToast 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
        <div style="width: 38px; height: 38px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
        </div>
        <div style="flex: 1;">
            <div style="font-weight: 700; color: #0f172a; font-size: 0.925rem; line-height: 1.2;">
                Welcome, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>!
            </div>
            <div style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.2rem;">
                Authenticated as <strong><?php echo getAdminRoleLabel(); ?></strong>
            </div>
        </div>
        <button type="button" onclick="closeWelcomeToast()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer; padding: 0.2rem; line-height: 1;" title="Dismiss">&times;</button>
    </div>

    <script>
        function closeWelcomeToast() {
            const t = document.getElementById('welcomeToast');
            if (t) {
                t.style.opacity = '0';
                t.style.transform = 'translateX(20px)';
                t.style.transition = 'all 0.3s ease';
                setTimeout(() => t.remove(), 300);
            }
        }
        setTimeout(closeWelcomeToast, 4000);
    </script>
    <?php endif; ?>

</body>
</html>
