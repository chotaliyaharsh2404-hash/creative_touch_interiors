<?php
require_once '../includes/config.php';

// Enforce admin authentication across all 3 roles
requireAdminLogin('login.php');

$current_page = 'profile';
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$success = '';
$error = '';

// Fetch fresh details from admin_users
$stmt = $conn->prepare("SELECT id, username, name, email, role, profile_image, created_at, updated_at FROM admin_users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_data = $stmt->get_result()->fetch_assoc();

if (!$admin_data) {
    redirect('logout.php');
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = "Security token expired. Please reload and try again.";
    } elseif (isset($_POST['action'])) {

        // 1. UPLOAD OR REPLACE PROFILE AVATAR
        if ($_POST['action'] === 'upload_avatar') {
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
                $error = "Please choose a photo to upload (JPG, PNG, or WebP up to 5 MB).";
            } else {
                $uploadResult = secure_upload_image($_FILES['avatar'], 'profiles', 'admin_' . $admin_id . '_');
                
                if (!$uploadResult['success']) {
                    $error = $uploadResult['error'] ?? "Failed to upload avatar.";
                } else {
                    $new_filepath = $uploadResult['filepath'];
                    $old_filepath = $admin_data['profile_image'] ?? '';

                    // Update database first with prepared statement
                    $updStmt = $conn->prepare("UPDATE admin_users SET profile_image = ?, updated_at = NOW() WHERE id = ?");
                    $updStmt->bind_param("si", $new_filepath, $admin_id);

                    if ($updStmt->execute()) {
                        // Update active session and local data
                        $_SESSION['admin_profile_image'] = $new_filepath;
                        $admin_data['profile_image'] = $new_filepath;

                        // Delete old uploaded image only AFTER new image is validated and saved
                        if (!empty($old_filepath) && $old_filepath !== $new_filepath) {
                            safe_delete_uploaded_image($old_filepath);
                        }

                        $success = "Profile picture updated successfully!";
                    } else {
                        // Rollback newly saved file on database failure
                        safe_delete_uploaded_image($new_filepath);
                        $error = "Database failed to save profile picture: " . $conn->error;
                    }
                }
            }
        }

        // 2. REMOVE PROFILE AVATAR (REVERT TO INITIALS)
        if ($_POST['action'] === 'remove_avatar') {
            $old_filepath = $admin_data['profile_image'] ?? '';

            $remStmt = $conn->prepare("UPDATE admin_users SET profile_image = NULL, updated_at = NOW() WHERE id = ?");
            $remStmt->bind_param("i", $admin_id);

            if ($remStmt->execute()) {
                $_SESSION['admin_profile_image'] = null;
                $admin_data['profile_image'] = null;

                if (!empty($old_filepath)) {
                    safe_delete_uploaded_image($old_filepath);
                }

                $success = "Profile picture removed. Reverted to name initial fallback.";
            } else {
                $error = "Failed to remove profile picture: " . $conn->error;
            }
        }

        // 3. UPDATE PROFILE INFORMATION
        if ($_POST['action'] === 'update_profile') {
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');

            if (empty($name)) {
                $error = "Name cannot be blank.";
            } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
            } else {
                $upd = $conn->prepare("UPDATE admin_users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
                $upd->bind_param("ssi", $name, $email, $admin_id);
                if ($upd->execute()) {
                    $_SESSION['admin_name'] = $name;
                    $_SESSION['admin_email'] = $email;
                    $admin_data['name'] = $name;
                    $admin_data['email'] = $email;
                    $success = "Profile details updated successfully!";
                } else {
                    $error = "Failed to update profile: " . $conn->error;
                }
            }
        }

        // 4. CHANGE PASSWORD
        if ($_POST['action'] === 'change_password') {
            $current_pass = $_POST['current_password'] ?? '';
            $new_pass = $_POST['new_password'] ?? '';
            $confirm_pass = $_POST['confirm_password'] ?? '';

            if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
                $error = "All password fields are required.";
            } elseif ($new_pass !== $confirm_pass) {
                $error = "New password and confirmation do not match.";
            } elseif (strlen($new_pass) < 6) {
                $error = "New password must be at least 6 characters in length.";
            } else {
                // Verify current password
                $pStmt = $conn->prepare("SELECT password FROM admin_users WHERE id = ?");
                $pStmt->bind_param("i", $admin_id);
                $pStmt->execute();
                $pRow = $pStmt->get_result()->fetch_assoc();

                if (!$pRow || !password_verify($current_pass, $pRow['password'])) {
                    $error = "Current password is incorrect.";
                } else {
                    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                    $uStmt = $conn->prepare("UPDATE admin_users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $uStmt->bind_param("si", $hashed, $admin_id);
                    if ($uStmt->execute()) {
                        $success = "Your password has been changed successfully!";
                    } else {
                        $error = "Failed to update password: " . $conn->error;
                    }
                }
            }
        }
    }
}

// Current Avatar details
$profile_avatar_url = getAdminAvatarUrl($admin_data['profile_image'] ?? null);
$profile_initial = getAdminInitials($admin_data['name'] ?? $admin_data['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Profile & Credentials — Creative Touch Interiors</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/spatial-3d.css">
    
    <style>
        :root {
            --cti-blue: #0057FF;
            --cti-blue-dark: #0046d6;
            --cti-bg: #F8F7F4;
            --cti-card-bg: #ffffff;
            --cti-text-dark: #0f172a;
            --cti-text-muted: #64748b;
            --cti-border: #e2e8f0;
        }

        body {
            background-color: var(--cti-bg) !important;
            color: var(--cti-text-dark);
            font-family: var(--font-body, 'Plus Jakarta Sans', sans-serif);
            margin: 0;
            padding: 0;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
            background: var(--cti-bg);
        }

        .admin-content-viewport {
            flex: 1;
            margin-left: 260px;
            width: calc(100% - 260px);
            padding: 2rem 2.5rem 3rem;
            overflow-y: auto;
            position: relative;
        }

        /* Hero Profile Card */
        .cti-hero-profile-card {
            background: #ffffff;
            border: 1px solid var(--cti-border);
            border-radius: 20px;
            padding: 2rem 2.25rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 40, 120, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }
        .cti-hero-profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--cti-blue) 0%, #60a5fa 100%);
        }

        .cti-hero-left {
            display: flex;
            align-items: center;
            gap: 1.75rem;
            flex-wrap: wrap;
        }

        /* Large 108px Avatar Container */
        .cti-large-avatar-wrap {
            position: relative;
            width: 108px;
            height: 108px;
            flex-shrink: 0;
            border-radius: 50%;
        }
        .cti-large-avatar-img {
            width: 108px;
            height: 108px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--cti-blue);
            box-shadow: 0 8px 24px rgba(0, 87, 255, 0.22);
            display: block;
        }
        .cti-large-avatar-fallback {
            width: 108px;
            height: 108px;
            border-radius: 50%;
            background: var(--cti-blue);
            border: 3px solid var(--cti-blue);
            box-shadow: 0 8px 24px rgba(0, 87, 255, 0.22);
            color: #ffffff;
            font-size: 2.75rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            text-transform: uppercase;
            user-select: none;
            font-family: var(--font-heading, inherit);
        }

        .cti-hero-details h1 {
            font-family: var(--font-heading, inherit);
            font-size: 1.85rem;
            font-weight: 700;
            color: var(--cti-text-dark);
            margin: 0 0 0.35rem;
            letter-spacing: -0.01em;
        }
        .cti-hero-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 0.5rem;
            color: var(--cti-text-muted);
            font-size: 0.88rem;
        }
        .cti-hero-email {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            color: var(--cti-text-muted);
            font-size: 0.85rem;
        }

        .cti-role-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.2rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .cti-role-badge.super_admin {
            background: rgba(99, 102, 241, 0.12);
            color: #4f46e5;
            border: 1px solid rgba(99, 102, 241, 0.25);
        }
        .cti-role-badge.admin {
            background: rgba(0, 87, 255, 0.12);
            color: var(--cti-blue);
            border: 1px solid rgba(0, 87, 255, 0.25);
        }
        .cti-role-badge.receptionist {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        /* Avatar Action Controls */
        .cti-hero-actions {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-cti-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--cti-blue);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 0.88rem;
            padding: 0.7rem 1.35rem;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(0, 87, 255, 0.3);
            text-decoration: none;
        }
        .btn-cti-primary:hover {
            background: var(--cti-blue-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 87, 255, 0.4);
        }

        .btn-cti-danger-outline {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(239, 68, 68, 0.06);
            color: #dc2626 !important;
            font-weight: 700;
            font-size: 0.88rem;
            padding: 0.7rem 1.15rem;
            border-radius: 10px;
            border: 1px solid rgba(239, 68, 68, 0.25);
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-cti-danger-outline:hover {
            background: #dc2626;
            color: #ffffff !important;
            border-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(220, 38, 38, 0.25);
        }

        /* Upload Modal Surface */
        .cti-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1100;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .cti-modal-backdrop.open {
            display: flex;
        }
        .cti-modal-box {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid var(--cti-border);
            max-width: 520px;
            width: 100%;
            padding: 2rem;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.2);
            animation: modalPop 0.24s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .cti-drop-area {
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            padding: 2rem 1.5rem;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            margin: 1.25rem 0;
        }
        .cti-drop-area:hover,
        .cti-drop-area.dragover {
            border-color: var(--cti-blue);
            background: #eff6ff;
        }
        .cti-preview-container {
            display: none;
            margin-top: 1rem;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 0.5rem;
        }
        .cti-preview-img {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--cti-blue);
            box-shadow: 0 4px 14px rgba(0, 87, 255, 0.25);
        }

        /* Dual Column Content */
        .cti-profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .cti-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid var(--cti-border);
            padding: 2rem;
            box-shadow: 0 6px 20px rgba(0, 40, 120, 0.04);
        }
        .cti-card-header {
            margin-bottom: 1.75rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--cti-border);
        }
        .cti-card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--cti-text-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .cti-card-subtitle {
            font-size: 0.85rem;
            color: var(--cti-text-muted);
            margin: 0.3rem 0 0;
        }

        .form-group {
            margin-bottom: 1.35rem;
        }
        .form-label {
            display: block;
            font-weight: 600;
            font-size: 0.85rem;
            color: #334155;
            margin-bottom: 0.4rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem 0.95rem;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.9rem;
            color: var(--cti-text-dark);
            background: #ffffff;
            box-sizing: border-box;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--cti-blue);
            box-shadow: 0 0 0 3px rgba(0, 87, 255, 0.15);
        }
        .form-control:disabled {
            background: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
            border-color: #e2e8f0;
        }
        .form-hint {
            font-size: 0.76rem;
            color: var(--cti-text-muted);
            margin-top: 0.35rem;
            display: block;
        }

        @media (max-width: 991px) {
            .admin-content-viewport {
                margin-left: 0;
                width: 100%;
                padding: 1.25rem 1rem 2.5rem;
            }
            .cti-profile-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        
        <!-- Sidebar Navigation -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content Viewport -->
        <main class="admin-content-viewport">
            
            <!-- Global SaaS Header with Avatar & Dropdown -->
            <?php include 'includes/header.php'; ?>

            <!-- Feedback Alerts -->
            <?php if (!empty($success)): ?>
                <div style="background: #ecfdf5; border-left: 4px solid #10b981; color: #065f46; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.75rem; font-size: 0.92rem; font-weight: 600; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.08);">
                    <div style="display: flex; align-items: center; gap: 0.65rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 1.35rem; color: #065f46; cursor: pointer;">&times;</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div style="background: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b; padding: 1rem 1.25rem; border-radius: 12px; margin-bottom: 1.75rem; font-size: 0.92rem; font-weight: 600; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.08);">
                    <div style="display: flex; align-items: center; gap: 0.65rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 1.35rem; color: #991b1b; cursor: pointer;">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Professional Profile Section / Profile Card -->
            <section class="cti-hero-profile-card">
                <div class="cti-hero-left">
                    <!-- Large Profile Picture / Fallback -->
                    <div class="cti-large-avatar-wrap">
                        <?php if (!empty($profile_avatar_url)): ?>
                            <img src="<?php echo htmlspecialchars($profile_avatar_url); ?>" 
                                 alt="<?php echo htmlspecialchars($admin_data['name']); ?>" 
                                 class="cti-large-avatar-img"
                                 id="profileMainAvatarImg"
                                 onerror="this.style.display='none'; document.getElementById('profileMainFallback').style.display='flex';">
                            <div class="cti-large-avatar-fallback" id="profileMainFallback" style="display: none;">
                                <?php echo htmlspecialchars($profile_initial); ?>
                            </div>
                        <?php else: ?>
                            <div class="cti-large-avatar-fallback" id="profileMainFallback">
                                <?php echo htmlspecialchars($profile_initial); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Details & Badges -->
                    <div class="cti-hero-details">
                        <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 0.25rem;">
                            <span class="cti-role-badge <?php echo htmlspecialchars($admin_data['role']); ?>">
                                <?php echo htmlspecialchars(getAdminRoleLabel($admin_data['role'])); ?>
                            </span>
                            <span style="font-size: 0.78rem; color: #94a3b8;">#<?php echo $admin_data['id']; ?></span>
                        </div>
                        <h1><?php echo htmlspecialchars($admin_data['name'] ?? 'Staff Member'); ?></h1>
                        <div class="cti-hero-meta">
                            <span>@<?php echo htmlspecialchars($admin_data['username']); ?></span>
                            <span>&bull;</span>
                            <span class="cti-hero-email">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                <?php echo htmlspecialchars($admin_data['email'] ?? 'No email on record'); ?>
                            </span>
                            <span>&bull;</span>
                            <span>Active since <?php echo date('M Y', strtotime($admin_data['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Avatar Upload Controls -->
                <div class="cti-hero-actions">
                    <button type="button" class="btn-cti-primary" onclick="openAvatarModal()" id="btnChangeAvatar">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                        </svg>
                        <span><?php echo !empty($admin_data['profile_image']) ? 'Replace Photo' : 'Upload Profile Picture'; ?></span>
                    </button>

                    <?php if (!empty($admin_data['profile_image'])): ?>
                        <form method="POST" onsubmit="return confirm('Remove your profile picture and restore your name initial fallback?');" style="margin: 0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="remove_avatar">
                            <button type="submit" class="btn-cti-danger-outline" title="Remove picture and return to initials fallback">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                </svg>
                                <span>Remove Picture</span>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Dual Cards: Details & Password -->
            <div class="cti-profile-grid">
                
                <!-- 1. Profile Information Card -->
                <div class="cti-card" id="profile-info-card">
                    <div class="cti-card-header">
                        <h2 class="cti-card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0057FF" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            <span>Administrative Account Details</span>
                        </h2>
                        <p class="cti-card-subtitle">
                            Update your public administrative display name and notification email.
                        </p>
                    </div>

                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin_data['username']); ?>" disabled>
                            <small class="form-hint">Administrative usernames are permanent and cannot be modified.</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Assigned Executive Role</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars(getAdminRoleLabel($admin_data['role'])); ?>" disabled style="font-weight: 700; color: #0057FF;">
                            <small class="form-hint">Security permissions are controlled strictly via Super Admin RBAC policies.</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Full Display Name *</label>
                            <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($admin_data['name'] ?? ''); ?>" placeholder="Enter your full name">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($admin_data['email'] ?? ''); ?>" placeholder="Enter corporate email">
                        </div>

                        <button type="submit" class="btn-cti-primary" style="width: 100%; justify-content: center; padding: 0.85rem; margin-top: 0.5rem;">
                            Save Profile Details
                        </button>
                    </form>
                </div>

                <!-- 2. Security & Password Card -->
                <div class="cti-card" id="security-card">
                    <div class="cti-card-header">
                        <h2 class="cti-card-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0057FF" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            <span>Security &amp; Credentials</span>
                        </h2>
                        <p class="cti-card-subtitle">
                            Ensure your executive account is protected with a strong passphrase.
                        </p>
                    </div>

                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label class="form-label">Current Password *</label>
                            <input type="password" name="current_password" class="form-control" required placeholder="Enter your existing password">
                        </div>

                        <div class="form-group">
                            <label class="form-label">New Password *</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Minimum 6 characters">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Confirm New Password *</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6" placeholder="Re-enter new password">
                        </div>

                        <button type="submit" class="btn-cti-primary" style="width: 100%; justify-content: center; padding: 0.85rem; margin-top: 0.5rem; background: #0f172a;">
                            Update Password
                        </button>
                    </form>
                </div>

            </div>

            <!-- Subtle Admin Footer Indicator -->
            <?php include 'includes/footer.php'; ?>

        </main>
    </div>

    <!-- Upload Profile Picture Modal Dialog -->
    <div class="cti-modal-backdrop" id="avatarModalBackdrop" role="dialog" aria-modal="true" aria-labelledby="avatarModalTitle">
        <div class="cti-modal-box">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 id="avatarModalTitle" style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0057FF" stroke-width="2.2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                    <span>Change Profile Picture</span>
                </h3>
                <button type="button" onclick="closeAvatarModal()" style="background: none; border: none; font-size: 1.5rem; color: #64748b; cursor: pointer; line-height: 1;">&times;</button>
            </div>

            <p style="font-size: 0.86rem; color: #64748b; margin: 0 0 1rem; line-height: 1.45;">
                Select a portrait image for your administrative profile. Accepted formats: <strong>JPG, JPEG, PNG, WebP</strong>. Maximum file size: <strong>5 MB</strong>.
            </p>

            <form method="POST" enctype="multipart/form-data" id="avatarUploadForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="upload_avatar">

                <!-- Hidden file input -->
                <input type="file" name="avatar" id="avatarFileInput" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" style="display: none;" onchange="handleAvatarFileSelect(this)">

                <!-- Drag & drop surface -->
                <div class="cti-drop-area" id="avatarDropArea" onclick="document.getElementById('avatarFileInput').click()">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: #eff6ff; color: #0057FF; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                    </div>
                    <div style="font-weight: 700; font-size: 0.95rem; color: #0f172a; margin-bottom: 0.25rem;">
                        Click to choose photo or drag &amp; drop here
                    </div>
                    <div style="font-size: 0.78rem; color: #64748b;">
                        JPG, PNG, or WebP &bull; Max 5 MB
                    </div>
                </div>

                <!-- Preview thumbnail -->
                <div class="cti-preview-container" id="avatarPreviewContainer">
                    <img src="" alt="Selected Avatar Preview" class="cti-preview-img" id="avatarPreviewImg">
                    <span id="avatarFileName" style="font-size: 0.82rem; font-weight: 600; color: #0f172a;"></span>
                    <button type="button" onclick="clearAvatarSelection()" style="background: none; border: none; color: #dc2626; font-size: 0.78rem; font-weight: 600; cursor: pointer;">Remove selected file</button>
                </div>

                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeAvatarModal()" style="padding: 0.7rem 1.25rem; border-radius: 10px; background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; font-weight: 600; cursor: pointer;">
                        Cancel
                    </button>
                    <button type="submit" class="btn-cti-primary" id="btnSubmitAvatar" disabled style="opacity: 0.6; cursor: not-allowed;">
                        Upload &amp; Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openAvatarModal() {
        const modal = document.getElementById('avatarModalBackdrop');
        if (modal) {
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeAvatarModal() {
        const modal = document.getElementById('avatarModalBackdrop');
        if (modal) {
            modal.classList.remove('open');
            document.body.style.overflow = '';
            clearAvatarSelection();
        }
    }

    function clearAvatarSelection() {
        const fileInput = document.getElementById('avatarFileInput');
        const previewContainer = document.getElementById('avatarPreviewContainer');
        const dropArea = document.getElementById('avatarDropArea');
        const submitBtn = document.getElementById('btnSubmitAvatar');

        if (fileInput) fileInput.value = '';
        if (previewContainer) previewContainer.style.display = 'none';
        if (dropArea) dropArea.style.display = 'block';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.6';
            submitBtn.style.cursor = 'not-allowed';
        }
    }

    function handleAvatarFileSelect(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];

        // Client-side quick check
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file format. Please select a JPG, PNG, or WebP image.');
            clearAvatarSelection();
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            alert('File exceeds the 5 MB maximum limit.');
            clearAvatarSelection();
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            const previewImg = document.getElementById('avatarPreviewImg');
            const previewContainer = document.getElementById('avatarPreviewContainer');
            const fileNameSpan = document.getElementById('avatarFileName');
            const dropArea = document.getElementById('avatarDropArea');
            const submitBtn = document.getElementById('btnSubmitAvatar');

            if (previewImg) previewImg.src = e.target.result;
            if (fileNameSpan) fileNameSpan.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
            if (dropArea) dropArea.style.display = 'none';
            if (previewContainer) previewContainer.style.display = 'flex';
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
        };
        reader.readAsDataURL(file);
    }

    // Drag and Drop listeners
    const dropArea = document.getElementById('avatarDropArea');
    if (dropArea) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropArea.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropArea.classList.remove('dragover');
            });
        });

        dropArea.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                const fileInput = document.getElementById('avatarFileInput');
                fileInput.files = files;
                handleAvatarFileSelect(fileInput);
            }
        });
    }

    // Close modal on backdrop click or ESC
    document.getElementById('avatarModalBackdrop')?.addEventListener('click', function(e) {
        if (e.target === this) {
            closeAvatarModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if ((e.key === 'Escape' || e.key === 'Esc') && document.getElementById('avatarModalBackdrop')?.classList.contains('open')) {
            closeAvatarModal();
        }
    });
    </script>
</body>
</html>
