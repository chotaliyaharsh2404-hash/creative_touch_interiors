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

        </div>
    </div>
    <script src="../js/vendor/three.min.js"></script>
    <script src="../js/vendor/gsap.min.js"></script>
    <script src="../js/spatial-3d.js"></script>
</body>
</html>
