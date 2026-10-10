<?php
require_once '../includes/config.php';

// Enforce super admin authentication and prevent caching
requireSuperAdmin('dashboard.php');

$error = '';
$success = '';

// Auto-seed team members if table is completely empty
$checkCount = $conn->query("SELECT COUNT(*) as c FROM team_members");
$rowCount = $checkCount ? ($checkCount->fetch_assoc()['c'] ?? 0) : 0;
if ($rowCount == 0) {
    $founders = [
        [
            'name' => 'Harsh Chotaliya',
            'designation' => 'Principal Architect & Founder',
            'bio' => 'Dedicated to structural harmony, spatial equilibrium, and contemporary Indian architectural luxury with over 15 years of industry mastery.',
            'photo' => 'uploads/team/harsh.jpeg',
            'email' => 'harshchotaliya@gmail.com',
            'order' => 1
        ],
        [
            'name' => 'Het Rana',
            'designation' => 'Lead Interior Designer & Co-Founder',
            'bio' => 'Specializes in high-end bespoke residential estates, sensory lighting integration, and turnkey execution with timeless materials.',
            'photo' => 'uploads/team/het.jpg',
            'email' => 'hetrana@gmail.com',
            'order' => 2
        ]
    ];
    foreach ($founders as $f) {
        $stmt_seed = $conn->prepare("INSERT INTO team_members (name, designation, bio, photo, email, order_index, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $stmt_seed->bind_param("sssssi", $f['name'], $f['designation'], $f['bio'], $f['photo'], $f['email'], $f['order']);
        $stmt_seed->execute();
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = "Security token mismatch. Please reload and try again.";
    } elseif (isset($_POST['action'])) {
        $action = $_POST['action'];

        // 1. ADD TEAM MEMBER
        if ($action === 'add') {
            $name = sanitize($_POST['name'] ?? '');
            $designation = sanitize($_POST['designation'] ?? '');
            $bio = sanitize($_POST['bio'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $linkedin = sanitize($_POST['linkedin'] ?? '');
            $order_index = (int)($_POST['order_index'] ?? 0);
            $status = sanitize($_POST['status'] ?? 'active');

            $photo_path = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadRes = secure_upload_image($_FILES['photo'], 'team', 'team_');
                if (!$uploadRes['success']) {
                    $error = $uploadRes['error'];
                } else {
                    $photo_path = $uploadRes['filepath'];
                }
            }

            if (empty($name) || empty($designation)) {
                $error = "Name and Designation are required fields.";
                if (!empty($photo_path)) {
                    safe_delete_uploaded_image($photo_path);
                }
            } elseif (empty($error)) {
                $stmt = $conn->prepare("INSERT INTO team_members (name, designation, bio, photo, email, linkedin, order_index, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssis", $name, $designation, $bio, $photo_path, $email, $linkedin, $order_index, $status);
                if ($stmt->execute()) {
                    $success = "Team member '$name' added successfully!";
                } else {
                    if (!empty($photo_path)) {
                        safe_delete_uploaded_image($photo_path);
                    }
                    $error = "Failed to add team member. Please try again.";
                }
            }
        }

        // 2. EDIT TEAM MEMBER
        if ($action === 'edit') {
            $id = (int)$_POST['id'];
            $name = sanitize($_POST['name'] ?? '');
            $designation = sanitize($_POST['designation'] ?? '');
            $bio = sanitize($_POST['bio'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $linkedin = sanitize($_POST['linkedin'] ?? '');
            $order_index = (int)($_POST['order_index'] ?? 0);
            $status = sanitize($_POST['status'] ?? 'active');

            if (empty($name) || empty($designation)) {
                $error = "Name and Designation are required.";
            } else {
                // Fetch current member photo using prepared statement
                $chkStmt = $conn->prepare("SELECT photo FROM team_members WHERE id = ?");
                $chkStmt->bind_param("i", $id);
                $chkStmt->execute();
                $currRow = $chkStmt->get_result()->fetch_assoc();
                $old_photo = $currRow['photo'] ?? null;
                $new_photo = $old_photo;
                $new_upload = false;

                // Check if new photo was uploaded
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadRes = secure_upload_image($_FILES['photo'], 'team', 'team_');
                    if (!$uploadRes['success']) {
                        $error = $uploadRes['error'];
                    } else {
                        $new_photo = $uploadRes['filepath'];
                        $new_upload = true;
                    }
                }

                if (empty($error)) {
                    $upd = $conn->prepare("UPDATE team_members SET name=?, designation=?, bio=?, photo=?, email=?, linkedin=?, order_index=?, status=? WHERE id=?");
                    $upd->bind_param("ssssssisi", $name, $designation, $bio, $new_photo, $email, $linkedin, $order_index, $status, $id);
                    if ($upd->execute()) {
                        // Safe Image Replacement: Delete old image only after successful DB update
                        if ($new_upload && !empty($old_photo) && $old_photo !== $new_photo) {
                            safe_delete_uploaded_image($old_photo);
                        }
                        $success = "Team member details updated successfully!";
                    } else {
                        // If DB update failed, delete the newly uploaded photo and preserve existing
                        if ($new_upload && !empty($new_photo)) {
                            safe_delete_uploaded_image($new_photo);
                        }
                        $error = "Failed to update team member. Please try again.";
                    }
                }
            }
        }

        // 3. TOGGLE STATUS
        if ($action === 'toggle_status') {
            $id = (int)$_POST['id'];
            $newStatus = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
            $stmt = $conn->prepare("UPDATE team_members SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $id);
            if ($stmt->execute()) {
                $success = "Status updated to " . ucfirst($newStatus) . "!";
            } else {
                $error = "Failed to update status. Please try again.";
            }
        }

        // 4. DELETE
        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            // Fetch photo path before deletion
            $chkStmt = $conn->prepare("SELECT photo FROM team_members WHERE id = ?");
            $chkStmt->bind_param("i", $id);
            $chkStmt->execute();
            $row = $chkStmt->get_result()->fetch_assoc();
            $photo_to_delete = $row['photo'] ?? null;

            $stmt = $conn->prepare("DELETE FROM team_members WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                if (!empty($photo_to_delete)) {
                    safe_delete_uploaded_image($photo_to_delete);
                }
                $success = "Team member removed successfully.";
            } else {
                $error = "Failed to remove team member. Please try again.";
            }
        }
    }
}

// Fetch all team members
$members = [];
$res = $conn->query("SELECT * FROM team_members ORDER BY order_index ASC, id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $members[] = $row;
    }
}

$page_title = 'Studio Team Management — Executive Suite';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/spatial-3d.css">
    <style>
        .admin-page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        .team-card {
            background: #ffffff;
            border-radius: var(--radius-xl);
            border: 1px solid #e2e8f0;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }
        .team-card:hover {
            box-shadow: 0 12px 24px -4px rgba(37, 99, 235, 0.12);
            border-color: #93c5fd;
            transform: translateY(-3px);
        }
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: #ffffff;
            border-radius: var(--radius-2xl);
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2.25rem;
            position: relative;
            border: 1px solid #bfdbfe;
            box-shadow: 0 25px 60px rgba(15, 23, 42, 0.2);
            color: #0f172a;
        }
    </style>
</head>
<body style="background: #F8F7F4; color: #0f172a;">
    <div style="display: flex; min-height: 100vh;">
        
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-content" style="flex: 1; padding: 2.25rem 2.5rem;">
            
            <!-- Executive Header with Avatar & Dropdown -->
            <?php include 'includes/header.php'; ?>

            <div class="admin-page-header">
                <div>
                    <h1 style="font-family: var(--font-heading); font-size: 1.85rem; color: #0f172a; margin: 0 0 0.25rem;">
                        Studio Leadership & Team
                    </h1>
                    <p style="color: #64748b; font-size: 0.885rem; margin: 0;">
                        Manage principal architects, designers, and project leaders displayed on the public About & Home pages.
                    </p>
                </div>
                <div>
                    <button onclick="openAddModal()" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; background: #0057FF; color: #ffffff; font-weight: 700; border: none; border-radius: 9999px; padding: 0.75rem 1.4rem; box-shadow: 0 10px 24px rgba(0, 87, 255, 0.28), 0 2px 6px rgba(15, 23, 42, 0.05); transform: translateY(-1px); transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); cursor: pointer;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        <span>Add Team Member</span>
                    </button>
                </div>
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

            <div class="team-grid">
                <?php foreach ($members as $m): 
                    $photo = getTeamMemberPhoto($m['name'], $m['photo'] ?? '', '../');
                ?>
                    <div class="team-card">
                        <div style="display: flex; gap: 1.25rem; align-items: center; margin-bottom: 1.25rem;">
                            <div style="width: 72px; height: 72px; border-radius: 50%; overflow: hidden; border: 2px solid #bfdbfe; flex-shrink: 0; background: #eff6ff; display: flex; align-items: center; justify-content: center;">
                                <?php if (!empty($photo)): ?>
                                    <img src="<?php echo htmlspecialchars($photo); ?>" alt="<?php echo htmlspecialchars($m['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <span style="font-size: 1.5rem; font-weight: 700; color: #2563eb;">
                                        <?php echo strtoupper(substr($m['name'], 0, 1)); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="overflow: hidden; flex: 1;">
                                <h3 style="font-size: 1.15rem; color: #0f172a; margin: 0 0 0.2rem; font-family: var(--font-heading); white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
                                    <?php echo htmlspecialchars($m['name']); ?>
                                </h3>
                                <p style="font-size: 0.8rem; color: #2563eb; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; margin: 0 0 0.35rem;">
                                    <?php echo htmlspecialchars($m['designation']); ?>
                                </p>
                                <div>
                                    <span style="font-size: 0.7rem; padding: 0.2rem 0.6rem; border-radius: 9999px; font-weight: 600; display: inline-block; background: <?php echo $m['status'] === 'active' ? '#ecfdf5' : '#f1f5f9'; ?>; color: <?php echo $m['status'] === 'active' ? '#047857' : '#64748b'; ?>; border: 1px solid <?php echo $m['status'] === 'active' ? '#a7f3d0' : '#cbd5e1'; ?>;">
                                        <?php echo ucfirst($m['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p style="font-size: 0.85rem; color: #475569; line-height: 1.6; margin: 0 0 1.25rem; flex: 1;">
                            <?php echo htmlspecialchars($m['bio']); ?>
                        </p>

                        <div style="font-size: 0.775rem; color: #64748b; margin-bottom: 1.25rem; display: flex; flex-direction: column; gap: 0.3rem;">
                            <div><strong style="color: #334155;">Email:</strong> <?php echo htmlspecialchars($m['email'] ?: '—'); ?></div>
                            <div><strong style="color: #334155;">Order Index:</strong> <?php echo (int)$m['order_index']; ?></div>
                        </div>

                        <div style="display: flex; gap: 0.5rem; border-top: 1px solid #e2e8f0; padding-top: 1rem; margin-top: auto;">
                            <button onclick='openEditModal(<?php echo json_encode($m); ?>)' class="btn btn-outline" style="flex: 1; font-size: 0.8rem; padding: 0.45rem 0.75rem; background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; border-radius: 8px; cursor: pointer;">
                                Edit Details
                            </button>
                            <form method="POST" style="margin: 0;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                <input type="hidden" name="status" value="<?php echo $m['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                <button type="submit" class="btn btn-outline" title="Toggle active status" style="font-size: 0.8rem; padding: 0.45rem 0.65rem; background: #eff6ff; border: 1px solid #bfdbfe; color: #2563eb; border-radius: 8px; cursor: pointer;">
                                    <?php echo $m['status'] === 'active' ? 'Disable' : 'Enable'; ?>
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to remove <?php echo htmlspecialchars($m['name']); ?>?');" style="margin: 0;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                <button type="submit" class="btn btn-outline" style="color: #ef4444; background: #fef2f2; border: 1px solid #fecaca; font-size: 0.8rem; padding: 0.45rem 0.65rem; border-radius: 8px; cursor: pointer;">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
    </div>

    <!-- Add Member Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content" style="max-width: 580px;">
            <h2 style="font-family: var(--font-heading); font-size: 1.35rem; margin: 0 0 1.5rem; color: #1e3a8a;">Add New Team Member</h2>
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Full Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Alok Verma" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Designation / Role *</label>
                    <input type="text" name="designation" class="form-control" required placeholder="e.g. Senior Lighting Consultant" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Professional Biography</label>
                    <textarea name="bio" class="form-control" rows="3" placeholder="Brief background, credentials, and architectural expertise..." style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;"></textarea>
                </div>

                <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="name@domain.com" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                    </div>
                    <div>
                        <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Display Order</label>
                        <input type="number" name="order_index" class="form-control" value="0" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Profile Photo (JPG, PNG, WEBP — Max 5 MB)</label>
                    <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.5rem 0.85rem; width: 100%;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" onclick="closeModals()" class="btn btn-outline" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; border-radius: 8px; padding: 0.65rem 1.25rem; font-weight: 600; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #0057FF; color: #ffffff; font-weight: 700; border: none; border-radius: 8px; padding: 0.65rem 1.4rem; box-shadow: 0 10px 24px rgba(0, 87, 255, 0.28), 0 2px 6px rgba(15, 23, 42, 0.05); cursor: pointer;">Save Team Member</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Member Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 580px;">
            <h2 style="font-family: var(--font-heading); font-size: 1.35rem; margin: 0 0 1.5rem; color: #1e3a8a;">Edit Team Member Details</h2>
            <form method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Full Name *</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Designation / Role *</label>
                    <input type="text" name="designation" id="edit_designation" class="form-control" required style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Professional Biography</label>
                    <textarea name="bio" id="edit_bio" class="form-control" rows="3" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;"></textarea>
                </div>

                <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Email Address</label>
                        <input type="email" name="email" id="edit_email" class="form-control" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                    </div>
                    <div>
                        <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Display Order</label>
                        <input type="number" name="order_index" id="edit_order" class="form-control" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Status</label>
                    <select name="status" id="edit_status" class="form-control" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                        <option value="active">Active (Visible)</option>
                        <option value="inactive">Inactive (Hidden)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Update Photo (Optional — JPG, PNG, WEBP — Max 5 MB)</label>
                    <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.5rem 0.85rem; width: 100%;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" onclick="closeModals()" class="btn btn-outline" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; border-radius: 8px; padding: 0.65rem 1.25rem; font-weight: 600; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #0057FF; color: #ffffff; font-weight: 700; border: none; border-radius: 8px; padding: 0.65rem 1.4rem; box-shadow: 0 10px 24px rgba(0, 87, 255, 0.28), 0 2px 6px rgba(15, 23, 42, 0.05); cursor: pointer;">Update Details</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }
        function openEditModal(m) {
            document.getElementById('edit_id').value = m.id;
            document.getElementById('edit_name').value = m.name;
            document.getElementById('edit_designation').value = m.designation;
            document.getElementById('edit_bio').value = m.bio || '';
            document.getElementById('edit_email').value = m.email || '';
            document.getElementById('edit_order').value = m.order_index || 0;
            document.getElementById('edit_status').value = m.status || 'active';
            document.getElementById('editModal').classList.add('active');
        }
        function closeModals() {
            document.getElementById('addModal').classList.remove('active');
            document.getElementById('editModal').classList.remove('active');
        }
        window.onclick = function(e) {
            if (e.target.classList.contains('modal')) {
                closeModals();
            }
        };
    </script>
    <script src="../js/vendor/three.min.js"></script>
    <script src="../js/vendor/gsap.min.js"></script>
    <script src="../js/spatial-3d.js"></script>
</body>
</html>
