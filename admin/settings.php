<?php
require_once '../includes/config.php';

// Enforce super admin authentication and prevent caching
requireSuperAdmin('dashboard.php');

$error = '';
$success = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = "Security token mismatch. Please reload and try again.";
    } else {
        $fields = [
            'hero_title' => sanitize($_POST['hero_title'] ?? ''),
            'hero_subtitle' => sanitize($_POST['hero_subtitle'] ?? ''),
            'about_story' => sanitize($_POST['about_story'] ?? ''),
            'contact_address' => sanitize($_POST['contact_address'] ?? ''),
            'contact_phone' => sanitize($_POST['contact_phone'] ?? ''),
            'contact_email' => sanitize($_POST['contact_email'] ?? '')
        ];

        foreach ($fields as $key => $val) {
            $check = $conn->prepare("SELECT id FROM website_content WHERE section_key = ?");
            $check->bind_param("s", $key);
            $check->execute();
            $res = $check->get_result();

            if ($res && $res->num_rows > 0) {
                $upd = $conn->prepare("UPDATE website_content SET content = ? WHERE section_key = ?");
                $upd->bind_param("ss", $val, $key);
                $upd->execute();
            } else {
                $ins = $conn->prepare("INSERT INTO website_content (section_key, content) VALUES (?, ?)");
                $ins->bind_param("ss", $key, $val);
                $ins->execute();
            }
        }
        $success = "Website content and contact configuration updated successfully!";
    }
}

// Fetch Current Settings
$content = getAllSiteContent();
$page_title = 'Website Content & Configuration — Executive Suite';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/spatial-3d.css">
</head>
<body style="background: #F8F7F4; color: #0f172a;">
    <div style="display: flex; min-height: 100vh;">
        
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-content" style="flex: 1; padding: 2.25rem 2.5rem;">
            
            <!-- Executive Header with Avatar & Dropdown -->
            <?php include 'includes/header.php'; ?>

            <div style="margin-bottom: 2rem;">
                <h1 style="font-family: var(--font-heading); font-size: 1.85rem; color: #0f172a; margin: 0 0 0.25rem;">
                    Website Content & Studio Configuration
                </h1>
                <p style="color: #64748b; font-size: 0.885rem; margin: 0;">
                    Manage public editorial headlines, company mission narrative, studio address, and contact coordinates.
                </p>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem; padding: 0.85rem 1.25rem;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom: 1.5rem; padding: 0.85rem 1.25rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="card" style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 2.5rem; max-width: 820px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                <form method="POST">
                    <?php echo csrf_field(); ?>

                    <h2 style="font-family: var(--font-heading); font-size: 1.25rem; color: #1e3a8a; margin: 0 0 1.25rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">
                        Homepage Hero Section
                    </h2>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label" style="font-weight: 600; color: #334155; font-size: 0.85rem; margin-bottom: 0.4rem; display: block;">Hero Headline</label>
                        <input type="text" name="hero_title" class="form-control" value="<?php echo htmlspecialchars($content['hero_title'] ?? 'Transform Your Space Into Something Extraordinary'); ?>" required style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.7rem 0.9rem; width: 100%;">
                    </div>

                    <div class="form-group" style="margin-bottom: 2rem;">
                        <label class="form-label" style="font-weight: 600; color: #334155; font-size: 0.85rem; margin-bottom: 0.4rem; display: block;">Hero Subtitle</label>
                        <input type="text" name="hero_subtitle" class="form-control" value="<?php echo htmlspecialchars($content['hero_subtitle'] ?? 'Award-winning interior design services for homes and businesses'); ?>" required style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.7rem 0.9rem; width: 100%;">
                    </div>

                    <h2 style="font-family: var(--font-heading); font-size: 1.25rem; color: #1e3a8a; margin: 0 0 1.25rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">
                        About Page Story & Philosophy
                    </h2>

                    <div class="form-group" style="margin-bottom: 2rem;">
                        <label class="form-label" style="font-weight: 600; color: #334155; font-size: 0.85rem; margin-bottom: 0.4rem; display: block;">Studio Founding Story Narrative</label>
                        <textarea name="about_story" class="form-control" rows="4" required style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.7rem 0.9rem; width: 100%;"><?php echo htmlspecialchars($content['about_story'] ?? 'Founded in 2009, Creative Touch Interiors began with a simple vision: to transform ordinary spaces into extraordinary experiences.'); ?></textarea>
                    </div>

                    <h2 style="font-family: var(--font-heading); font-size: 1.25rem; color: #1e3a8a; margin: 0 0 1.25rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">
                        Studio Coordinates & Contact Info
                    </h2>

                    <div class="form-group" style="margin-bottom: 1.25rem;">
                        <label class="form-label" style="font-weight: 600; color: #334155; font-size: 0.85rem; margin-bottom: 0.4rem; display: block;">Physical Studio Address</label>
                        <input type="text" name="contact_address" class="form-control" value="<?php echo htmlspecialchars($content['contact_address'] ?? '123 Design Street, Andheri West, Mumbai, Maharashtra 400058'); ?>" required style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.7rem 0.9rem; width: 100%;">
                    </div>

                    <div class="grid grid-2" style="gap: 1.25rem; margin-bottom: 2.25rem;">
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; color: #334155; font-size: 0.85rem; margin-bottom: 0.4rem; display: block;">Primary Phone Number</label>
                            <input type="text" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($content['contact_phone'] ?? '+91 9316856961'); ?>" required style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.7rem 0.9rem; width: 100%;">
                        </div>
                        <div class="form-group">
                            <label class="form-label" style="font-weight: 600; color: #334155; font-size: 0.85rem; margin-bottom: 0.4rem; display: block;">Official Email Address</label>
                            <input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($content['contact_email'] ?? 'harshchotaliya@gmail.com'); ?>" required style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.7rem 0.9rem; width: 100%;">
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                        <button type="submit" class="btn btn-primary btn-lg" style="background: #0057FF; color: #ffffff; font-weight: 700; border: none; border-radius: 9999px; padding: 0.85rem 2.25rem; box-shadow: 0 10px 24px rgba(0, 87, 255, 0.28), 0 2px 6px rgba(15, 23, 42, 0.05); transform: translateY(-1px); transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); cursor: pointer;">
                            Save Configuration Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- System & Application Information Section -->
            <div class="card" style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 2rem 2.5rem; max-width: 820px; margin-top: 2rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                <h2 style="font-family: var(--font-heading); font-size: 1.2rem; color: #0f172a; margin: 0 0 1.25rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.65rem; display: flex; align-items: center; gap: 0.6rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0057FF" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    <span>System &amp; Application Information</span>
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                    <div style="background: #F8F7F4; padding: 1.25rem 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; font-weight: 700;">Application</div>
                        <div style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin-top: 0.35rem;">Creative Touch Interiors</div>
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.2rem;">Executive Administration Suite</div>
                    </div>

                    <div style="background: #F8F7F4; padding: 1.25rem 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; font-weight: 700;">Version</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.35rem;">
                            <span style="font-size: 1.15rem; font-weight: 800; color: #0057FF; font-family: monospace;">v<?php echo htmlspecialchars(APP_VERSION); ?></span>
                            <span style="background: rgba(0, 87, 255, 0.1); color: #0057FF; font-size: 0.7rem; font-weight: 700; padding: 0.15rem 0.55rem; border-radius: 9999px; border: 1px solid rgba(0, 87, 255, 0.2);">Stable</span>
                        </div>
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.2rem;">Production Build Channel</div>
                    </div>

                    <div style="background: #F8F7F4; padding: 1.25rem 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; font-weight: 700;">Release</div>
                        <div style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin-top: 0.35rem;">Project Category Badge UI Polish</div>
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.2rem;">Light Glassmorphic Badges on Project Cards</div>
                    </div>
                </div>

                <!-- Professional What's New Section (v2.2.2 Current Release) -->
                <div style="margin-top: 2rem; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
                        <h3 style="font-family: var(--font-heading); font-size: 1.15rem; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #0057FF;"></span>
                            <span>What's New in v2.2.2 &bull; Project Category Badge UI Polish</span>
                        </h3>
                        <span style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; background: rgba(0, 87, 255, 0.1); color: #0057FF; padding: 0.25rem 0.75rem; border-radius: 9999px;">
                            Current Release
                        </span>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem;">
                            <div style="font-weight: 800; font-size: 0.8rem; text-transform: uppercase; color: #0057FF; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.4rem;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span>UI POLISH</span>
                            </div>
                            <ul style="margin: 0; padding-left: 1.1rem; font-size: 0.825rem; color: #334155; line-height: 1.65;">
                                <li>Replaced dark/black project badge styling with light glassmorphism pills</li>
                                <li>Applied translucent white/blue surface with subtle 1px border (rgba(0, 87, 255, 0.20))</li>
                                <li>Enhanced typography with brand #0057FF color and 700 font weight</li>
                                <li>Integrated 10px backdrop blur with refined micro-shadow</li>
                                <li>Interactive hover state with subtle illumination transition</li>
                            </ul>
                        </div>

                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem;">
                            <div style="font-weight: 800; font-size: 0.8rem; text-transform: uppercase; color: #059669; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.4rem;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20V10"></path><path d="M18 20V4"></path><path d="M6 20v-4"></path></svg>
                                <span>COMPATIBILITY</span>
                            </div>
                            <ul style="margin: 0; padding-left: 1.1rem; font-size: 0.825rem; color: #334155; line-height: 1.65;">
                                <li>High contrast and readability over both bright and dark imagery</li>
                                <li>Consistent across all categories: Residential, Commercial, Office, Retail</li>
                                <li>Zero layout shift; dimensions, grid, and card spacing strictly preserved</li>
                                <li>Fully responsive on desktop, tablet, and mobile displays</li>
                                <li>No text clipping or horizontal overflow</li>
                            </ul>
                        </div>

                        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem;">
                            <div style="font-weight: 800; font-size: 0.8rem; text-transform: uppercase; color: #7c3aed; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.4rem;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                                <span>STABILITY &amp; INTEGRITY</span>
                            </div>
                            <ul style="margin: 0; padding-left: 1.1rem; font-size: 0.825rem; color: #334155; line-height: 1.65;">
                                <li>No changes to project database records or schema</li>
                                <li>No modifications to unrelated components, header, or footer</li>
                                <li>Preserved v2.2.0 announcement system &amp; v2.2.1 bell fixes</li>
                                <li>Zero regression across admin management suite and RBAC</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Preserved Version 2.2.0 Milestone Overview -->
                <div style="margin-top: 1.5rem; background: #F8F7F4; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem 1.5rem;">
                    <div style="font-size: 0.85rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">
                        v2.2.0 Feature Milestone: Public Announcement &amp; Promotion System
                    </div>
                    <div style="font-size: 0.8rem; color: #475569; line-height: 1.6;">
                        Introduced public broadcasts, glassmorphic top announcement banner with dismiss memory, header announcement bell dropdown, public catalog (announcements.php), announcement details (announcement.php), anonymous aggregate telemetry, and admin management suite.
                    </div>
                </div>

                <!-- Preserved Version History -->
                <div style="margin-top: 2rem; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
                    <h3 style="font-family: var(--font-heading); font-size: 1.05rem; color: #0f172a; margin: 0 0 1rem;">
                        Version History &amp; Release Lineage
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div style="padding: 0.85rem 1.25rem; background: #ffffff; border: 1px solid #0057FF; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                            <div>
                                <span style="font-family: monospace; font-weight: 800; color: #0057FF; font-size: 0.95rem;">v2.2.2</span>
                                <strong style="margin-left: 0.75rem; color: #0f172a; font-size: 0.9rem;">Project Category Badge UI Polish</strong>
                            </div>
                            <span style="background: #0057FF; color: #ffffff; font-size: 0.7rem; font-weight: 800; padding: 0.2rem 0.6rem; border-radius: 9999px;">CURRENT RELEASE</span>
                        </div>

                        <div style="padding: 0.85rem 1.25rem; background: #F8F7F4; border: 1px solid #e2e8f0; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                            <div>
                                <span style="font-family: monospace; font-weight: 700; color: #475569; font-size: 0.95rem;">v2.2.1</span>
                                <strong style="margin-left: 0.75rem; color: #334155; font-size: 0.9rem;">Announcement Bell Interaction Fix</strong>
                            </div>
                            <span style="color: #64748b; font-size: 0.75rem; font-weight: 600;">Previous Patch Release</span>
                        </div>

                        <div style="padding: 0.85rem 1.25rem; background: #F8F7F4; border: 1px solid #e2e8f0; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                            <div>
                                <span style="font-family: monospace; font-weight: 700; color: #475569; font-size: 0.95rem;">v2.2.0</span>
                                <strong style="margin-left: 0.75rem; color: #334155; font-size: 0.9rem;">Public Announcement &amp; Promotion System</strong>
                            </div>
                            <span style="color: #64748b; font-size: 0.75rem; font-weight: 600;">Previous Minor Release</span>
                        </div>

                        <div style="padding: 0.85rem 1.25rem; background: #F8F7F4; border: 1px solid #e2e8f0; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                            <div>
                                <span style="font-family: monospace; font-weight: 700; color: #475569; font-size: 0.95rem;">v2.1.0</span>
                                <strong style="margin-left: 0.75rem; color: #334155; font-size: 0.9rem;">Admin Profile &amp; Avatar System</strong>
                            </div>
                            <span style="color: #64748b; font-size: 0.75rem; font-weight: 600;">Previous Release</span>
                        </div>

                        <div style="padding: 0.85rem 1.25rem; background: #F8F7F4; border: 1px solid #e2e8f0; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                            <div>
                                <span style="font-family: monospace; font-weight: 700; color: #475569; font-size: 0.95rem;">v2.0.0</span>
                                <strong style="margin-left: 0.75rem; color: #334155; font-size: 0.9rem;">Role-Based Access Control</strong>
                            </div>
                            <span style="color: #64748b; font-size: 0.75rem; font-weight: 600;">Major Architecture</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subtle Admin Footer Indicator -->
            <?php include 'includes/footer.php'; ?>

        </div>
    </div>
    <script src="../js/vendor/three.min.js"></script>
    <script src="../js/vendor/gsap.min.js"></script>
    <script src="../js/spatial-3d.js"></script>
</body>
</html>
