<?php
require_once '../includes/config.php';

// Enforce super admin authentication and prevent caching
requireSuperAdmin('dashboard.php');

$error = '';
$success = '';
$active_tab = 'clients';
$is_super = isSuperAdmin();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if (!validate_csrf()) {
        $error = 'Security token expired. Please reload and try again.';
    } else {
        $action = $_POST['action'];

        // 1. ADD CLIENT
        if ($action == 'add_user') {
            $active_tab = 'clients';
            $name = sanitize($_POST['name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($name) || empty($email) || empty($password)) {
                $error = 'Name, email, and password are required.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } else {
                $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $check->bind_param("s", $email);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $error = 'A client with this email already exists.';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name, $email, $hashed, $phone);
                    if ($stmt->execute()) {
                        $success = 'Client account created successfully!';
                    } else {
                        $error = 'Failed to create client account.';
                    }
                }
            }
        }

        // 2. EDIT CLIENT
        if ($action == 'edit_user') {
            $active_tab = 'clients';
            $user_id = (int)$_POST['user_id'];
            $name = sanitize($_POST['name'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');

            if (empty($name)) {
                $error = 'Name cannot be empty.';
            } else {
                $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $phone, $user_id);
                if ($stmt->execute()) {
                    $success = 'Client details updated successfully!';
                } else {
                    $error = 'Failed to update client details.';
                }
            }
        }

        // 3. RESET CLIENT PASSWORD
        if ($action == 'reset_user_password') {
            $active_tab = 'clients';
            $user_id = (int)$_POST['user_id'];
            $new_password = $_POST['new_password'] ?? '';

            if (strlen($new_password) < 6) {
                $error = 'New password must be at least 6 characters.';
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashed, $user_id);
                if ($stmt->execute()) {
                    $success = 'Client password reset successfully!';
                } else {
                    $error = 'Failed to reset password.';
                }
            }
        }

        // 4. DELETE CLIENT
        if ($action == 'delete_user') {
            $active_tab = 'clients';
            $user_id = (int)$_POST['user_id'];

            $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user_data = $stmt->get_result()->fetch_assoc();

            $del = $conn->prepare("DELETE FROM users WHERE id = ?");
            $del->bind_param("i", $user_id);
            if ($del->execute()) {
                if (!empty($user_data['profile_picture'])) {
                    if (file_exists('../uploads/profiles/' . $user_data['profile_picture'])) {
                        @unlink('../uploads/profiles/' . $user_data['profile_picture']);
                    } elseif (file_exists('../' . $user_data['profile_picture'])) {
                        @unlink('../' . $user_data['profile_picture']);
                    }
                }
                $success = 'Client account deleted.';
            } else {
                $error = 'Failed to delete client account.';
            }
        }

        $allowed_admin_roles = ['super_admin', 'admin', 'receptionist'];

        // 5. CREATE NEW ADMIN / RECEPTIONIST (Super Admin Only)
        if ($action == 'add_admin') {
            $active_tab = 'admins';
            if (!$is_super) {
                $error = 'Only Super Administrators can create administrative accounts.';
            } else {
                $username = sanitize($_POST['username'] ?? '');
                $email = sanitize($_POST['email'] ?? '');
                $full_name = sanitize($_POST['full_name'] ?? '');
                $role = sanitize($_POST['role'] ?? 'admin');
                $password = $_POST['password'] ?? '';

                if (!in_array($role, $allowed_admin_roles, true)) {
                    $error = 'Invalid role specified. Allowed roles are: Super Admin, Admin, Receptionist.';
                } elseif (empty($username) || empty($password)) {
                    $error = 'Username and password are required.';
                } elseif (strlen($password) < 6) {
                    $error = 'Password must be at least 6 characters.';
                } else {
                    $check = $conn->prepare("SELECT id FROM admin_users WHERE username = ?");
                    $check->bind_param("s", $username);
                    $check->execute();
                    if ($check->get_result()->num_rows > 0) {
                        $error = 'An administrator with this username already exists.';
                    } else {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("INSERT INTO admin_users (username, password, email, name, role) VALUES (?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssss", $username, $hashed, $email, $full_name, $role);
                        if ($stmt->execute()) {
                            $success = "Staff account '$username' (" . getAdminRoleLabel($role) . ") created successfully!";
                        } else {
                            $error = 'Failed to create administrator account: ' . $conn->error;
                        }
                    }
                }
            }
        }

        // 5b. EDIT ADMIN / RECEPTIONIST (Super Admin Only)
        if ($action == 'edit_admin') {
            $active_tab = 'admins';
            if (!$is_super) {
                $error = 'Only Super Administrators can edit administrative accounts.';
            } else {
                $target_id = (int)($_POST['admin_id'] ?? 0);
                $full_name = sanitize($_POST['full_name'] ?? '');
                $email = sanitize($_POST['email'] ?? '');
                $new_role = sanitize($_POST['role'] ?? 'admin');

                if (!in_array($new_role, $allowed_admin_roles, true)) {
                    $error = 'Invalid role selected. Allowed roles are: Super Admin, Admin, Receptionist.';
                } elseif (empty($full_name)) {
                    $error = 'Full name is required.';
                } else {
                    // Prevent logged-in super admin from changing own role
                    if ($target_id === (int)$_SESSION['admin_id']) {
                        $new_role = 'super_admin';
                    }
                    $stmt = $conn->prepare("UPDATE admin_users SET name = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $full_name, $email, $new_role, $target_id);
                    if ($stmt->execute()) {
                        $success = 'Staff account details updated successfully.';
                    } else {
                        $error = 'Failed to update staff account: ' . $conn->error;
                    }
                }
            }
        }

        // 6. CHANGE ADMIN ROLE (Super Admin Only)
        if ($action == 'change_admin_role') {
            $active_tab = 'admins';
            if (!$is_super) {
                $error = 'Only Super Administrators can change admin roles.';
            } else {
                $target_id = (int)$_POST['admin_id'];
                $new_role = sanitize($_POST['role'] ?? 'admin');

                if (!in_array($new_role, $allowed_admin_roles, true)) {
                    $error = 'Invalid role selected. Allowed roles are: Super Admin, Admin, Receptionist.';
                } elseif ($target_id === (int)$_SESSION['admin_id']) {
                    $error = 'You cannot change your own role.';
                } else {
                    $stmt = $conn->prepare("UPDATE admin_users SET role = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_role, $target_id);
                    if ($stmt->execute()) {
                        $success = 'Staff role updated to ' . getAdminRoleLabel($new_role) . '.';
                    } else {
                        $error = 'Failed to update role: ' . $conn->error;
                    }
                }
            }
        }

        // 7. RESET ADMIN PASSWORD (Super Admin Only)
        if ($action == 'reset_admin_password') {
            $active_tab = 'admins';
            if (!$is_super) {
                $error = 'Only Super Administrators can reset admin passwords.';
            } else {
                $target_id = (int)$_POST['admin_id'];
                $new_pass = $_POST['new_password'] ?? '';

                if (strlen($new_pass) < 6) {
                    $error = 'Password must be at least 6 characters.';
                } else {
                    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed, $target_id);
                    if ($stmt->execute()) {
                        $success = 'Administrator password reset successfully.';
                    } else {
                        $error = 'Failed to reset password: ' . $conn->error;
                    }
                }
            }
        }

        // 8. DELETE ADMIN (Super Admin Only)
        if ($action == 'delete_admin') {
            $active_tab = 'admins';
            if (!$is_super) {
                $error = 'Only Super Administrators can delete admin accounts.';
            } else {
                $target_id = (int)$_POST['admin_id'];

                if ($target_id === (int)$_SESSION['admin_id']) {
                    $error = 'You cannot delete your own active administrator account.';
                } else {
                    // Fetch existing profile picture for safe cleanup
                    $picStmt = $conn->prepare("SELECT profile_image FROM admin_users WHERE id = ?");
                    $picStmt->bind_param("i", $target_id);
                    $picStmt->execute();
                    $picRow = $picStmt->get_result()->fetch_assoc();
                    $target_pic = $picRow['profile_image'] ?? '';

                    $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $target_id);
                        if ($stmt->execute()) {
                            if (!empty($target_pic)) {
                                safe_delete_uploaded_image($target_pic);
                            }
                            $success = 'Administrator account deleted.';
                        } else {
                            $error = 'Failed to delete admin account: ' . $conn->error;
                        }
                    } else {
                        $error = 'Database error preparing statement: ' . $conn->error;
                    }
                }
            }
        }
    }
}

// Fetch Data
$users_res = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
$users = [];
if ($users_res) {
    while ($r = $users_res->fetch_assoc()) {
        $users[] = $r;
    }
}

$admins_res = $conn->query("SELECT * FROM admin_users ORDER BY id ASC");
$admins = [];
if ($admins_res) {
    while ($r = $admins_res->fetch_assoc()) {
        $admins[] = $r;
    }
}

$super_count = 0;
foreach ($admins as $a) {
    if (($a['role'] ?? '') === 'super_admin') $super_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User & Admin Management - Creative Touch Interiors</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/spatial-3d.css">
    <style>
        .role-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .role-pill.super_admin {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        .role-pill.admin {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }
        .role-pill.receptionist {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        .role-pill.client {
            background: #f8fafc;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        .tab-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            background: none;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--color-text-muted);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: var(--transition);
        }
        .tab-btn.active {
            color: var(--color-accent);
            border-bottom-color: var(--color-accent);
        }
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #475569;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: var(--radius-2xl);
            max-width: 520px;
            width: 100%;
            box-shadow: var(--shadow-2xl);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        .btn-modal-cancel {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.65rem 1.3rem;
            border-radius: var(--radius-lg);
            font-weight: 700;
            font-size: 0.9rem;
            color: #64748b;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-modal-cancel:hover {
            background: #f1f5f9;
            color: #334155;
            border-color: #cbd5e1;
        }
        .btn-modal-submit {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.65rem 1.5rem;
            border-radius: var(--radius-lg);
            font-weight: 700;
            font-size: 0.9rem;
            color: #ffffff !important;
            background: #0057FF;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 24px rgba(0, 87, 255, 0.28), 0 2px 6px rgba(15, 23, 42, 0.05);
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .btn-modal-submit:hover {
            background: #0046d6 !important;
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(0, 87, 255, 0.38);
        }
    </style>
</head>
<body style="background: #F8F7F4; color: #0f172a;">
    <div style="display: flex; min-height: 100vh;">
        
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-content" style="flex: 1; padding: 2.25rem 2.5rem;">
            
            <!-- Executive Header with Avatar & Dropdown -->
            <?php include 'includes/header.php'; ?>

            <!-- Header Bar -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <h1 style="font-size: 1.85rem; color: #ffffff; margin: 0; font-weight: 800; font-family: var(--font-heading); letter-spacing: -0.01em;">User & Role Management</h1>
                        <?php if ($is_super): ?>
                            <span class="role-pill super_admin" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                Super Admin Access
                            </span>
                        <?php else: ?>
                            <span class="role-pill admin" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                Standard Admin
                            </span>
                        <?php endif; ?>
                    </div>
                    <p style="color: #94a3b8; font-size: 0.95rem; margin: 0.25rem 0 0;">Manage client portal accounts, staff permissions, and role access controls</p>
                </div>
                <div>
                    <?php if ($is_super): ?>
                        <button type="button" onclick="openAddAdminModal()" class="btn btn-primary" style="font-size: 0.875rem; padding: 0.65rem 1.3rem; background: #0057FF; color: #ffffff; font-weight: 700; border-radius: 9999px; display: inline-flex; align-items: center; gap: 0.5rem; border: none; box-shadow: 0 10px 24px rgba(0, 87, 255, 0.28), 0 2px 6px rgba(15, 23, 42, 0.05); transform: translateY(-1px); transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); cursor: pointer;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                            <span>Create Admin Account</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom: 1.5rem; border-radius: var(--radius-lg);">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="margin-bottom: 1.5rem; border-radius: var(--radius-lg);">
                    ✓ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Metrics Summary Bar -->
            <div class="grid grid-3" style="margin-bottom: 2rem; gap: 1.25rem;">
                <div class="card" style="padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; border-radius: var(--radius-xl); border-left: 4px solid #2563eb;">
                    <div>
                        <div style="color: #64748b; font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Registered Clients</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #1e3a8a; font-family: var(--font-heading);"><?php echo count($users); ?></div>
                    </div>
                    <div style="background: #eff6ff; color: #2563eb; width: 48px; height: 48px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                </div>

                <div class="card" style="padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; border-radius: var(--radius-xl); border-left: 4px solid #3b82f6;">
                    <div>
                        <div style="color: #64748b; font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Admin Staff</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #1d4ed8; font-family: var(--font-heading);"><?php echo count($admins); ?></div>
                    </div>
                    <div style="background: #eff6ff; color: #2563eb; width: 48px; height: 48px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    </div>
                </div>

                <div class="card" style="padding: 1.25rem 1.5rem; display: flex; align-items: center; justify-content: space-between; border-radius: var(--radius-xl); border-left: 4px solid #be185d;">
                    <div>
                        <div style="color: #94a3b8; font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Super Admins</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #be185d; font-family: var(--font-heading);"><?php echo $super_count; ?></div>
                    </div>
                    <div style="background: #fdf2f8; color: #be185d; width: 48px; height: 48px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    </div>
                </div>
            </div>

            <!-- Tabbed Card -->
            <div class="card" style="padding: 0; border-radius: var(--radius-2xl); overflow: hidden; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
                
                <div style="display: flex; border-bottom: 1px solid var(--border-color); padding: 0 1.5rem; background: #f8fafc;">
                    <button class="tab-btn <?php echo $active_tab == 'clients' ? 'active' : ''; ?>" onclick="switchTab('clients', this)" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        <span>Client Accounts (<?php echo count($users); ?>)</span>
                    </button>
                    <button class="tab-btn <?php echo $active_tab == 'admins' ? 'active' : ''; ?>" onclick="switchTab('admins', this)" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        <span>Administrators & Staff (<?php echo count($admins); ?>)</span>
                    </button>
                </div>

                <!-- TAB 1: CLIENTS TABLE -->
                <div id="tab-clients" style="display: <?php echo $active_tab == 'clients' ? 'block' : 'none'; ?>; padding: 1.5rem 1.75rem;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
                        <input type="text" id="clientSearch" oninput="filterClients(this.value)" placeholder="Search client name, email, or phone..." class="form-control" style="max-width: 320px; font-size: 0.875rem; border-radius: 9999px; padding-left: 1rem;">
                        <span style="font-size: 0.85rem; color: var(--color-text-muted);">Clients can log in, request quotes, and manage their portfolio</span>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color); text-align: left;">
                                    <th style="padding: 0.75rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">User Profile</th>
                                    <th style="padding: 0.75rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">Contact Info</th>
                                    <th style="padding: 0.75rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase;">Registered</th>
                                    <th style="padding: 0.75rem 1rem; color: var(--color-text-muted); font-size: 0.8rem; text-transform: uppercase; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="clientsTableBody">
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $u): ?>
                                        <tr class="client-row" data-search="<?php echo htmlspecialchars(strtolower($u['name'] . ' ' . $u['email'] . ' ' . ($u['phone'] ?? ''))); ?>" style="border-bottom: 1px solid var(--border-subtle);">
                                            <td style="padding: 1rem;">
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <?php 
                                                        $avatar_url = '';
                                                        if (!empty($u['profile_picture'])) {
                                                            if (file_exists('../uploads/profiles/' . $u['profile_picture'])) {
                                                                $avatar_url = '../uploads/profiles/' . $u['profile_picture'];
                                                            } elseif (file_exists('../' . $u['profile_picture'])) {
                                                                $avatar_url = '../' . $u['profile_picture'];
                                                            }
                                                        }
                                                    ?>
                                                    <?php if (!empty($avatar_url)): ?>
                                                        <img src="<?php echo htmlspecialchars($avatar_url); ?>" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255, 255, 255, 0.1);" alt="Avatar">
                                                    <?php else: ?>
                                                        <div style="width: 38px; height: 38px; border-radius: 50%; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; border: 1px solid #bfdbfe;">
                                                            <?php echo strtoupper(substr($u['name'] ?? 'C', 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div style="font-weight: 700; color: #0f172a; font-size: 0.925rem;"><?php echo htmlspecialchars($u['name']); ?></div>
                                                        <span class="role-pill client">Client</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="padding: 1rem; font-size: 0.875rem;">
                                                <div style="color: #2563eb; font-weight: 500;"><?php echo htmlspecialchars($u['email']); ?></div>
                                                <div style="color: #64748b; font-size: 0.8rem;"><?php echo htmlspecialchars($u['phone'] ?? 'No phone'); ?></div>
                                            </td>
                                            <td style="padding: 1rem; font-size: 0.85rem; color: #64748b;">
                                                <?php echo date('M d, Y', strtotime($u['created_at'])); ?>
                                            </td>
                                            <td style="padding: 1rem; text-align: right;">
                                                <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                                                    <button type="button" onclick="openEditClientModal(<?php echo $u['id']; ?>, '<?php echo addslashes(htmlspecialchars($u['name'])); ?>', '<?php echo addslashes(htmlspecialchars($u['phone'] ?? '')); ?>')" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; color: #2563eb; border-color: #bfdbfe; background: #eff6ff; display: inline-flex; align-items: center; gap: 0.25rem;">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                        <span>Edit</span>
                                                    </button>
                                                    <button type="button" onclick="openResetClientPassModal(<?php echo $u['id']; ?>, '<?php echo addslashes(htmlspecialchars($u['name'])); ?>')" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; color: #fbbf24; border-color: rgba(245, 158, 11, 0.3); background: rgba(245, 158, 11, 0.08); display: inline-flex; align-items: center; gap: 0.25rem;">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="7.5" cy="15.5" r="5.5"></circle><path d="m21 2-9.6 9.6"></path><path d="m15.5 7.5 3 3L22 7l-3-3"></path></svg>
                                                        <span>Reset Pass</span>
                                                    </button>
                                                    <form method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this client account?');" style="display: inline;">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                        <button type="submit" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; color: #f87171; border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.08); display: inline-flex; align-items: center; gap: 0.25rem;">
                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                            <span>Delete</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 2.5rem; color: #94a3b8;">No client accounts registered yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: ADMINS TABLE -->
                <div id="tab-admins" style="display: <?php echo $active_tab == 'admins' ? 'block' : 'none'; ?>; padding: 1.5rem 1.75rem;">
                    
                    <div style="margin-bottom: 1.25rem; font-size: 0.85rem; color: #94a3b8;">
                        Staff members with full administrative privileges to manage portfolio, leads, services, and consultations.
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 2px solid rgba(255, 255, 255, 0.08); text-align: left;">
                                    <th style="padding: 0.85rem 1rem; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase;">Administrator</th>
                                    <th style="padding: 0.85rem 1rem; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase;">Role</th>
                                    <th style="padding: 0.85rem 1rem; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase;">Email</th>
                                    <th style="padding: 0.85rem 1rem; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase;">Created On</th>
                                    <th style="padding: 0.85rem 1rem; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; text-align: right;">Access Controls</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $adm): ?>
                                    <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.04);">
                                        <td style="padding: 1rem;">
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <?php 
                                                    $adm_pic_url = getAdminAvatarUrl($adm['profile_image'] ?? null);
                                                    $adm_init = getAdminInitials($adm['name'] ?? $adm['username']);
                                                ?>
                                                <?php if (!empty($adm_pic_url)): ?>
                                                    <div style="position: relative; width: 38px; height: 38px; flex-shrink: 0;">
                                                        <img src="<?php echo htmlspecialchars($adm_pic_url); ?>" alt="<?php echo htmlspecialchars($adm['name']); ?>" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #0057FF; box-shadow: 0 2px 8px rgba(0, 87, 255, 0.25); display: block;" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                                        <div style="display: none; width: 38px; height: 38px; border-radius: 50%; background: #0057FF; color: #ffffff; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem;">
                                                            <?php echo htmlspecialchars($adm_init); ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div style="width: 38px; height: 38px; border-radius: 50%; background: #0057FF; color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; box-shadow: 0 2px 8px rgba(0, 87, 255, 0.25); flex-shrink: 0;">
                                                        <?php echo htmlspecialchars($adm_init); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div style="font-weight: 700; color: #0f172a; font-size: 0.95rem;">
                                                        <?php echo htmlspecialchars($adm['username']); ?>
                                                        <?php if ((int)$adm['id'] === (int)($_SESSION['admin_id'] ?? 0)): ?>
                                                            <span style="font-size: 0.75rem; background: #e0e7ff; color: #4338ca; padding: 0.15rem 0.5rem; border-radius: 9999px; margin-left: 0.35rem; font-weight: 600;">You</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div style="font-size: 0.8rem; color: var(--color-text-muted);"><?php echo htmlspecialchars($adm['name'] ?? ''); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 1rem;">
                                            <span class="role-pill <?php echo $adm['role'] ?? 'admin'; ?>" style="display: inline-flex; align-items: center; gap: 0.35rem;">
                                                <?php if (($adm['role'] ?? '') == 'super_admin'): ?>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M2 4l3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"></path>
                                                    </svg>
                                                    <span>Super Admin</span>
                                                <?php elseif (($adm['role'] ?? '') == 'receptionist'): ?>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                                    </svg>
                                                    <span>Receptionist</span>
                                                <?php else: ?>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                                    </svg>
                                                    <span>Admin</span>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; font-size: 0.875rem; color: var(--color-text-muted);">
                                            <?php echo htmlspecialchars($adm['email'] ?? 'N/A'); ?>
                                        </td>
                                        <td style="padding: 1rem; font-size: 0.85rem; color: var(--color-text-muted);">
                                            <?php echo !empty($adm['created_at']) ? date('M d, Y', strtotime($adm['created_at'])) : 'N/A'; ?>
                                        </td>
                                        <td style="padding: 1rem; text-align: right;">
                                            <?php if ($is_super): ?>
                                                <div style="display: inline-flex; gap: 0.35rem; align-items: center;">
                                                    
                                                    <!-- Edit Staff Button -->
                                                    <button type="button" onclick="openEditAdminModal(<?php echo $adm['id']; ?>, '<?php echo addslashes(htmlspecialchars($adm['username'])); ?>', '<?php echo addslashes(htmlspecialchars($adm['name'] ?? '')); ?>', '<?php echo addslashes(htmlspecialchars($adm['email'] ?? '')); ?>', '<?php echo $adm['role'] ?? 'admin'; ?>', <?php echo (int)$adm['id'] === (int)($_SESSION['admin_id'] ?? 0) ? 'true' : 'false'; ?>)" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; color: #4f46e5; border-color: #c7d2fe; display: inline-flex; align-items: center; gap: 0.25rem;">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                                        <span>Edit</span>
                                                    </button>

                                                    <!-- Reset Admin Pass -->
                                                    <button type="button" onclick="openResetAdminPassModal(<?php echo $adm['id']; ?>, '<?php echo addslashes(htmlspecialchars($adm['username'])); ?>')" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; color: #d97706; border-color: #fde68a; display: inline-flex; align-items: center; gap: 0.25rem;">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="7.5" cy="15.5" r="5.5"></circle><path d="m21 2-9.6 9.6"></path><path d="m15.5 7.5 3 3L22 7l-3-3"></path></svg>
                                                        <span>Reset Pass</span>
                                                    </button>

                                                    <!-- Delete Admin Form (Cannot delete self) -->
                                                    <?php if ((int)$adm['id'] !== (int)($_SESSION['admin_id'] ?? 0)): ?>
                                                        <form method="POST" onsubmit="return confirm('Revoke all access for <?php echo addslashes(htmlspecialchars($adm['username'])); ?>?');" style="display: inline;">
                                                            <?php echo csrf_field(); ?>
                                                            <input type="hidden" name="action" value="delete_admin">
                                                            <input type="hidden" name="admin_id" value="<?php echo $adm['id']; ?>">
                                                            <button type="submit" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; color: #dc2626; border-color: #fecaca; display: inline-flex; align-items: center; gap: 0.25rem;">
                                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                                                <span>Delete</span>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                </div>
                                            <?php else: ?>
                                                <span style="font-size: 0.8rem; color: var(--color-text-light);">Super Admin Only</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- MODAL 1: EDIT CLIENT -->
    <div id="modalEditClient" class="modal">
        <div class="modal-content">
            <div style="padding: 1.5rem 1.75rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 1.2rem; font-weight: 700; margin: 0; font-family: var(--font-heading); display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    Edit Client Details
                </h3>
                <button type="button" onclick="closeModal('modalEditClient')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-muted);">&times;</button>
            </div>
            <form method="POST" style="padding: 1.5rem 1.75rem;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group" style="margin-bottom: 1.15rem;">
                    <label class="form-label">Client Name *</label>
                    <input type="text" name="name" id="edit_user_name" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" id="edit_user_phone" class="form-control">
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" onclick="closeModal('modalEditClient')" class="btn-modal-cancel">Cancel</button>
                    <button type="submit" class="btn-modal-submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: RESET CLIENT PASSWORD -->
    <div id="modalResetClientPass" class="modal">
        <div class="modal-content">
            <div style="padding: 1.5rem 1.75rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 1.2rem; font-weight: 700; margin: 0; font-family: var(--font-heading); display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);"><circle cx="7.5" cy="15.5" r="5.5"></circle><path d="m21 2-9.6 9.6"></path><path d="m15.5 7.5 3 3L22 7l-3-3"></path></svg>
                    <span>Reset Password for <span id="reset_client_name" style="color: #2563eb;"></span></span>
                </h3>
                <button type="button" onclick="closeModal('modalResetClientPass')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-muted);">&times;</button>
            </div>
            <form method="POST" style="padding: 1.5rem 1.75rem;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reset_user_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">New Password *</label>
                    <input type="password" name="new_password" class="form-control" required placeholder="Minimum 6 characters">
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" onclick="closeModal('modalResetClientPass')" class="btn-modal-cancel">Cancel</button>
                    <button type="submit" class="btn-modal-submit">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: CREATE ADMIN (Super Admin Only) -->
    <?php if ($is_super): ?>
    <div id="modalAddAdmin" class="modal">
        <div class="modal-content">
            <div style="padding: 1.5rem 1.75rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 1.2rem; font-weight: 700; margin: 0; font-family: var(--font-heading); display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    Create Administrator
                </h3>
                <button type="button" onclick="closeModal('modalAddAdmin')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-muted);">&times;</button>
            </div>
            <form method="POST" style="padding: 1.5rem 1.75rem;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add_admin">
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" required placeholder="e.g. arch_harsh">
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" placeholder="e.g. Harsh Chotaliya">
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="admin@creativetouch.com">
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Administrator / Staff Role *</label>
                    <select name="role" class="form-control" required>
                        <option value="receptionist">Receptionist (Front-Desk: Inquiries, Leads, Consultations & Quotes)</option>
                        <option value="admin">Admin (Business Manager: Projects, Services, Quotes, Testimonials)</option>
                        <option value="super_admin">Super Admin (Full Access: System, Users, Settings, Blog & Team)</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required placeholder="Minimum 6 characters">
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" onclick="closeModal('modalAddAdmin')" class="btn-modal-cancel">Cancel</button>
                    <button type="submit" class="btn-modal-submit">Create Staff Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3B: EDIT ADMIN / STAFF (Super Admin Only) -->
    <div id="modalEditAdmin" class="modal">
        <div class="modal-content">
            <div style="padding: 1.5rem 1.75rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 1.2rem; font-weight: 700; margin: 0; font-family: var(--font-heading); display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    Edit Staff Account: <span id="edit_admin_username_label" style="color: #2563eb;"></span>
                </h3>
                <button type="button" onclick="closeModal('modalEditAdmin')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-muted);">&times;</button>
            </div>
            <form method="POST" style="padding: 1.5rem 1.75rem;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit_admin">
                <input type="hidden" name="admin_id" id="edit_admin_id">

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Username</label>
                    <input type="text" id="edit_admin_username" class="form-control" disabled style="background: #f1f5f9; cursor: not-allowed;">
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" id="edit_admin_name" class="form-control" required placeholder="Staff Full Name">
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" id="edit_admin_email" class="form-control" placeholder="staff@creativetouch.com">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">Assigned Role *</label>
                    <select name="role" id="edit_admin_role" class="form-control" required>
                        <option value="super_admin">Super Admin (Full Access: System, Users, Settings, Blog & Team)</option>
                        <option value="admin">Admin (Business Manager: Projects, Services, Quotes, Testimonials)</option>
                        <option value="receptionist">Receptionist (Front-Desk: Inquiries, Leads, Consultations & Quotes)</option>
                    </select>
                    <small id="edit_admin_self_notice" style="color: #64748b; font-size: 0.75rem; display: none; margin-top: 0.35rem;">
                        Note: You cannot change your own Super Administrator role.
                    </small>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" onclick="closeModal('modalEditAdmin')" class="btn-modal-cancel">Cancel</button>
                    <button type="submit" class="btn-modal-submit">Save Staff Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 4: RESET ADMIN PASSWORD -->
    <div id="modalResetAdminPass" class="modal">
        <div class="modal-content">
            <div style="padding: 1.5rem 1.75rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 1.2rem; font-weight: 700; margin: 0; font-family: var(--font-heading); display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-accent);"><circle cx="7.5" cy="15.5" r="5.5"></circle><path d="m21 2-9.6 9.6"></path><path d="m15.5 7.5 3 3L22 7l-3-3"></path></svg>
                    <span>Reset Password for <span id="reset_admin_username" style="color: #2563eb;"></span></span>
                </h3>
                <button type="button" onclick="closeModal('modalResetAdminPass')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-muted);">&times;</button>
            </div>
            <form method="POST" style="padding: 1.5rem 1.75rem;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="reset_admin_password">
                <input type="hidden" name="admin_id" id="reset_admin_id">
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label">New Administrator Password *</label>
                    <input type="password" name="new_password" class="form-control" required placeholder="Minimum 6 characters">
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" onclick="closeModal('modalResetAdminPass')" class="btn-modal-cancel">Cancel</button>
                    <button type="submit" class="btn-modal-submit">Update Password</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function switchTab(tab, btn) {
            document.getElementById('tab-clients').style.display = tab === 'clients' ? 'block' : 'none';
            document.getElementById('tab-admins').style.display = tab === 'admins' ? 'block' : 'none';
            
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
        }

        function filterClients(query) {
            const q = query.toLowerCase().trim();
            const rows = document.querySelectorAll('.client-row');
            rows.forEach(r => {
                const search = r.getAttribute('data-search') || '';
                r.style.display = (q === '' || search.includes(q)) ? '' : 'none';
            });
        }

        function openEditClientModal(id, name, phone) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_user_name').value = name;
            document.getElementById('edit_user_phone').value = phone;
            openModal('modalEditClient');
        }

        function openResetClientPassModal(id, name) {
            document.getElementById('reset_user_id').value = id;
            document.getElementById('reset_client_name').textContent = name;
            openModal('modalResetClientPass');
        }

        function openAddAdminModal() {
            openModal('modalAddAdmin');
        }

        function openEditAdminModal(id, username, name, email, role, isSelf) {
            document.getElementById('edit_admin_id').value = id;
            document.getElementById('edit_admin_username_label').textContent = username;
            document.getElementById('edit_admin_username').value = username;
            document.getElementById('edit_admin_name').value = name;
            document.getElementById('edit_admin_email').value = email;
            const roleSelect = document.getElementById('edit_admin_role');
            roleSelect.value = role;
            const selfNotice = document.getElementById('edit_admin_self_notice');
            if (isSelf) {
                roleSelect.disabled = true;
                if (selfNotice) selfNotice.style.display = 'block';
            } else {
                roleSelect.disabled = false;
                if (selfNotice) selfNotice.style.display = 'none';
            }
            openModal('modalEditAdmin');
        }

        function openResetAdminPassModal(id, username) {
            document.getElementById('reset_admin_id').value = id;
            document.getElementById('reset_admin_username').textContent = username;
            openModal('modalResetAdminPass');
        }

        function openModal(id) {
            const m = document.getElementById(id);
            if (m) m.classList.add('active');
        }

        function closeModal(id) {
            const m = document.getElementById(id);
            if (m) m.classList.remove('active');
        }

        // Close on outside click
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
            }
        });
    </script>
    <script src="../js/vendor/three.min.js"></script>
    <script src="../js/vendor/gsap.min.js"></script>
    <script src="../js/spatial-3d.js"></script>
</body>
</html>
