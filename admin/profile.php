<?php
require_once '../includes/config.php';

// Enforce admin authentication across all 3 roles
requireAdminLogin('login.php');

$current_page = 'profile';
$admin_id = (int)($_SESSION['admin_id'] ?? 0);
$success = '';
$error = '';

// Fetch fresh details from admin_users
$stmt = $conn->prepare("SELECT id, username, name, email, role, created_at, updated_at FROM admin_users WHERE id = ?");
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

        // 1. UPDATE PROFILE INFORMATION
        if ($_POST['action'] === 'update_profile') {
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');

            if (empty($name)) {
                $error = "Name cannot be blank.";
            } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
            } else {
                $upd = $conn->prepare("UPDATE admin_users SET name = ?, email = ? WHERE id = ?");
                $upd->bind_param("ssi", $name, $email, $admin_id);
                if ($upd->execute()) {
                    $_SESSION['admin_name'] = $name;
                    $admin_data['name'] = $name;
                    $admin_data['email'] = $email;
                    $success = "Profile details updated successfully!";
                } else {
                    $error = "Failed to update profile: " . $conn->error;
                }
            }
        }

        // 2. CHANGE PASSWORD
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
                    $uStmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Creative Touch Interiors Admin</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/spatial-3d.css">
    <style>
        .role-pill-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .role-super_admin {
            background: rgba(99, 102, 241, 0.15);
            color: #4f46e5;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        .role-admin {
            background: rgba(37, 99, 235, 0.15);
            color: #2563eb;
            border: 1px solid rgba(37, 99, 235, 0.3);
        }
        .role-receptionist {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
    </style>
</head>
<body style="background: #f8fafc; color: #0f172a; margin: 0; font-family: var(--font-body);">
    <div style="display: flex; min-height: 100vh;">
        
        <!-- Sidebar Navigation -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content Viewport -->
        <main class="admin-content-viewport" style="flex: 1; margin-left: 260px; width: calc(100% - 260px); padding: 2.25rem 2.5rem; overflow-y: auto;">
            
            <!-- Page Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                        <span class="section-tag" style="margin-bottom: 0;">Account Management</span>
                        <span class="role-pill-badge role-<?php echo htmlspecialchars($admin_data['role']); ?>">
                            <?php echo getAdminRoleLabel($admin_data['role']); ?>
                        </span>
                    </div>
                    <h1 style="font-family: var(--font-heading); font-size: 2rem; font-weight: 700; margin: 0; color: #0f172a;">
                        Staff Profile &amp; Credentials
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.875rem; margin: 0.25rem 0 0;">
                        Manage your administrative account information, communication preferences, and login credentials.
                    </p>
                </div>
            </div>

            <!-- Notifications -->
            <?php if (!empty($success)): ?>
                <div style="background: #ecfdf5; border-left: 4px solid #10b981; color: #065f46; padding: 1rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div style="background: #fef2f2; border-left: 4px solid #ef4444; color: #991b1b; padding: 1rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                
                <!-- Profile Information Card -->
                <div class="glass-card" style="padding: 2rem; background: #ffffff; border-radius: var(--radius-lg); border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.75rem; padding-bottom: 1.25rem; border-bottom: 1px solid #e2e8f0;">
                        <div style="width: 54px; height: 54px; border-radius: 50%; background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); color: #ffffff; font-weight: 800; font-size: 1.35rem; display: flex; align-items: center; justify-content: center; border: 2px solid #93c5fd;">
                            <?php echo strtoupper(substr($admin_data['name'] ?? $admin_data['username'], 0, 1)); ?>
                        </div>
                        <div>
                            <h2 style="font-size: 1.2rem; font-weight: 700; margin: 0; color: #0f172a;">
                                <?php echo htmlspecialchars($admin_data['name'] ?? 'Staff Member'); ?>
                            </h2>
                            <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.15rem;">
                                @<?php echo htmlspecialchars($admin_data['username']); ?> &bull; 
                                <span class="role-pill-badge role-<?php echo htmlspecialchars($admin_data['role']); ?>" style="padding: 0.1rem 0.5rem; font-size: 0.7rem;">
                                    <?php echo getAdminRoleLabel($admin_data['role']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: #334155;">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin_data['username']); ?>" disabled style="background: #f1f5f9; cursor: not-allowed;">
                            <small style="color: #64748b; font-size: 0.75rem;">Usernames are permanently bound and can only be altered by a Super Administrator.</small>
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: #334155;">Assigned Role</label>
                            <input type="text" class="form-control" value="<?php echo getAdminRoleLabel($admin_data['role']); ?>" disabled style="background: #f1f5f9; cursor: not-allowed; font-weight: 600;">
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: #334155;">Full Name *</label>
                            <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($admin_data['name'] ?? ''); ?>" placeholder="Enter your full name">
                        </div>

                        <div class="form-group" style="margin-bottom: 1.75rem;">
                            <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: #334155;">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin_data['email'] ?? ''); ?>" placeholder="Enter your email address">
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.75rem; font-weight: 600;">
                            Save Profile Changes
                        </button>
                    </form>
                </div>

                <!-- Security & Password Card -->
                <div class="glass-card" style="padding: 2rem; background: #ffffff; border-radius: var(--radius-lg); border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    <div style="margin-bottom: 1.75rem; padding-bottom: 1.25rem; border-bottom: 1px solid #e2e8f0;">
                        <h2 style="font-size: 1.2rem; font-weight: 700; margin: 0; color: #0f172a; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #2563eb;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            Change Password
                        </h2>
                        <p style="color: var(--text-muted); font-size: 0.825rem; margin: 0.25rem 0 0;">
                            Ensure your account is protected with a robust, minimum 6-character passphrase.
                        </p>
                    </div>

                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: #334155;">Current Password *</label>
                            <input type="password" name="current_password" class="form-control" required placeholder="Enter existing password">
                        </div>

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: #334155;">New Password *</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Minimum 6 characters">
                        </div>

                        <div class="form-group" style="margin-bottom: 1.75rem;">
                            <label class="form-label" style="font-weight: 600; font-size: 0.85rem; color: #334155;">Confirm New Password *</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6" placeholder="Re-enter new password">
                        </div>

                        <button type="submit" class="btn btn-outline" style="width: 100%; justify-content: center; padding: 0.75rem; font-weight: 600; border-color: #2563eb; color: #2563eb;">
                            Update Password
                        </button>
                    </form>
                </div>

            </div>

        </main>
    </div>
</body>
</html>
