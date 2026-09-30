<?php
require_once 'includes/config.php';

// Enforce authentication and disable caching for protected page
requireUserLogin('login.php');

$page_title = 'Client Portal — Creative Touch Interiors';
$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';
$active_tab = 'profile';

// Handle profile actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validate_csrf()) {
        $error = 'Security token expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // 1. Update Profile Info
        if ($action == 'update_profile') {
            $active_tab = 'profile';
            $name = sanitize($_POST['name'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');

            if (empty($name)) {
                $error = 'Name cannot be empty.';
            } else {
                $sql = "UPDATE users SET name = ?, phone = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $name, $phone, $user_id);

                if ($stmt->execute()) {
                    $_SESSION['user_name'] = $name;
                    $success = 'Profile details updated successfully!';
                } else {
                    $error = 'Failed to update profile details.';
                }
            }
        }

        // 2. Upload Profile Avatar
        if ($action == 'upload_profile_picture') {
            $active_tab = 'profile';
            if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Please select a valid image file to upload.';
            } else {
                $file = $_FILES['profile_picture'];
                $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                $max_size = 5 * 1024 * 1024; // 5MB

                $real_mime = mime_content_type($file['tmp_name']);
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($real_mime, $allowed_mimes) || !in_array($extension, $allowed_extensions)) {
                    $error = 'Invalid image type. Only JPG, PNG, and WEBP files are allowed.';
                } elseif ($file['size'] > $max_size) {
                    $error = 'File size exceeds the 5MB limit.';
                } else {
                    $upload_dir = 'uploads/profiles/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
                    $upload_path = $upload_dir . $filename;

                    // Delete old profile picture if exists
                    $sql = "SELECT profile_picture FROM users WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user_data = $result->fetch_assoc();

                    if (!empty($user_data['profile_picture'])) {
                        if (file_exists('uploads/profiles/' . $user_data['profile_picture'])) {
                            unlink('uploads/profiles/' . $user_data['profile_picture']);
                        } elseif (file_exists('uploads/' . $user_data['profile_picture'])) {
                            unlink('uploads/' . $user_data['profile_picture']);
                        }
                    }

                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("si", $filename, $user_id);

                        if ($stmt->execute()) {
                            $success = 'Profile avatar updated successfully!';
                        } else {
                            $error = 'Failed to update avatar in database.';
                            unlink($upload_path);
                        }
                    } else {
                        $error = 'Failed to save avatar image file.';
                    }
                }
            }
        }

        // 3. Remove Profile Avatar
        if ($action == 'remove_profile_picture') {
            $active_tab = 'profile';
            $sql = "SELECT profile_picture FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();

            if (!empty($user_data['profile_picture'])) {
                if (file_exists('uploads/profiles/' . $user_data['profile_picture'])) {
                    unlink('uploads/profiles/' . $user_data['profile_picture']);
                } elseif (file_exists('uploads/' . $user_data['profile_picture'])) {
                    unlink('uploads/' . $user_data['profile_picture']);
                }
            }

            $sql = "UPDATE users SET profile_picture = NULL WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                $success = 'Profile avatar removed successfully!';
            } else {
                $error = 'Failed to remove profile picture.';
            }
        }
        
        // 4. Change Password
        if ($action == 'change_password') {
            $active_tab = 'security';
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            $sql = "SELECT password FROM users WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_auth = $result->fetch_assoc();

            if (!password_verify($current_password, $user_auth['password'])) {
                $error = 'Your current password is incorrect.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'The new passwords do not match.';
            } elseif (strlen($new_password) < 6) {
                $error = 'New password must be at least 6 characters long.';
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $hashed_password, $user_id);

                if ($stmt->execute()) {
                    $success = 'Password changed successfully!';
                } else {
                    $error = 'Failed to update password.';
                }
            }
        }

        // 5. Submit Testimonial / Experience Review
        if ($action == 'submit_testimonial') {
            $active_tab = 'reviews';
            $project_title = sanitize($_POST['project_title'] ?? '');
            $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
            $testimonial = sanitize($_POST['testimonial'] ?? '');
            
            // Get user's current name
            $uNameStmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $uNameStmt->bind_param("i", $user_id);
            $uNameStmt->execute();
            $client_name = $uNameStmt->get_result()->fetch_assoc()['name'] ?? 'Valued Client';

            if (empty($testimonial)) {
                $error = 'Please write a review before submitting.';
            } else {
                $status = 'pending';
                $featured = 0;
                $stmt = $conn->prepare("INSERT INTO testimonials (client_name, project_title, rating, testimonial, featured, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssisis", $client_name, $project_title, $rating, $testimonial, $featured, $status);

                if ($stmt->execute()) {
                    $success = 'Thank you! Your testimonial has been submitted and is pending verification by our design team.';
                } else {
                    $error = 'Failed to submit review. Please try again.';
                }
            }
        }
    }
}

// Fetch current user data
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Fetch user quote requests (from quote_requests) and consultations
$user_email = $user['email'] ?? '';
$user_phone = $user['phone'] ?? '';
$user_quotes = [];
$user_consultations = [];

// 1. Fetch Quote Requests from quote_requests table
$stmt_quotes = $conn->prepare("
    SELECT * FROM quote_requests 
    WHERE user_id = ? OR (email = ? AND email != '') OR (phone = ? AND phone != '') 
    ORDER BY created_at DESC
");
$stmt_quotes->bind_param("iss", $user_id, $user_email, $user_phone);
$stmt_quotes->execute();
$res_quotes = $stmt_quotes->get_result();
while ($row = $res_quotes->fetch_assoc()) {
    // Normalize aliases for seamless display
    $row['carpet_area'] = (float)($row['approx_area'] ?? ($row['carpet_area'] ?? 0));
    $row['final_estimate'] = (float)($row['final_estimate'] ?? 0);
    $row['estimated_total'] = (float)($row['estimated_total'] ?? 0);

    // Fetch attached services for each quote
    $q_id = (int)$row['id'];
    $srv_stmt = $conn->prepare("
        SELECT qs.*, s.title as service_name, s.category 
        FROM quote_services qs 
        LEFT JOIN services s ON qs.service_id = s.id 
        WHERE qs.quote_id = ?
    ");
    $srv_stmt->bind_param("i", $q_id);
    $srv_stmt->execute();
    $row['services'] = $srv_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $user_quotes[] = $row;
}

// 2. Fetch Consultations & Meetings
if (!empty($user_email) || !empty($user_phone)) {
    $stmt_consult = $conn->prepare("SELECT * FROM consultations WHERE (client_email = ? AND client_email != '') OR (client_phone = ? AND client_phone != '') ORDER BY consultation_date DESC, consultation_time DESC");
    $stmt_consult->bind_param("ss", $user_email, $user_phone);
    $stmt_consult->execute();
    $res_consult = $stmt_consult->get_result();
    $existing_sessions = [];
    while ($row = $res_consult->fetch_assoc()) {
        $user_consultations[] = $row;
        $existing_sessions[] = $row['consultation_date'] . '_' . substr($row['consultation_time'], 0, 5);
    }

    // Also include any scheduled site visits directly from quotes if not already in consultations table
    foreach ($user_quotes as $uq) {
        if (!empty($uq['site_visit_date']) && !empty($uq['site_visit_time'])) {
            $sess_key = $uq['site_visit_date'] . '_' . substr($uq['site_visit_time'], 0, 5);
            if (!in_array($sess_key, $existing_sessions)) {
                $user_consultations[] = [
                    'id' => 'q_' . $uq['id'],
                    'client_name' => $uq['customer_name'] ?? ($user['name'] ?? 'Client'),
                    'client_email' => $uq['email'] ?? $user_email,
                    'client_phone' => $uq['phone'] ?? $user_phone,
                    'subject' => "Site Visit: " . $uq['quote_number'] . " (" . ucfirst($uq['property_type'] ?? 'Project') . ")",
                    'consultation_date' => $uq['site_visit_date'],
                    'consultation_time' => $uq['site_visit_time'],
                    'status' => 'confirmed',
                    'notes' => $uq['site_visit_notes'] ?? 'Scheduled on-site measurement & architectural consultation.',
                    'created_at' => $uq['updated_at'] ?? $uq['created_at']
                ];
                $existing_sessions[] = $sess_key;
            }
        }
    }
}

// 3. Fetch User Testimonials
$user_testimonials = [];
$userName = $user['name'] ?? '';
if (!empty($userName)) {
    $stmt_testim = $conn->prepare("SELECT * FROM testimonials WHERE client_name = ? ORDER BY created_at DESC");
    $stmt_testim->bind_param("s", $userName);
    $stmt_testim->execute();
    $user_testimonials = $stmt_testim->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Resolve user avatar path with dual-directory fallback
$user_avatar_path = '';
if (!empty($user['profile_picture'])) {
    if (file_exists('uploads/profiles/' . $user['profile_picture'])) {
        $user_avatar_path = 'uploads/profiles/' . $user['profile_picture'];
    } elseif (file_exists('uploads/' . $user['profile_picture'])) {
        $user_avatar_path = 'uploads/' . $user['profile_picture'];
    }
}
?>

<?php include 'includes/header.php'; ?>

<style>
.profile-wrapper {
    background: #f8fafc;
    min-height: calc(100vh - 200px);
    padding: 3rem 0 5rem;
    color: #0f172a;
}
.profile-hero {
    background: radial-gradient(circle at 50% 20%, #eff6ff 0%, #ffffff 85%);
    color: #0f172a;
    padding: 4.5rem 0 3.5rem;
    position: relative;
    border-bottom: 1px solid #e2e8f0;
}
.profile-card {
    background: #ffffff;
    border-radius: var(--radius-lg);
    box-shadow: 0 10px 30px rgba(37, 99, 235, 0.06);
    border: 1px solid #e2e8f0;
    color: #0f172a;
    overflow: hidden;
}
.profile-avatar-circle {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    border: 2px solid #2563eb;
    background: #eff6ff;
    box-shadow: 0 0 20px rgba(37, 99, 235, 0.15);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #2563eb;
    flex-shrink: 0;
}
.nav-tab-btn {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 1.1rem 1.35rem;
    width: 100%;
    border: none;
    background: transparent;
    text-align: left;
    font-size: 0.875rem;
    font-weight: 500;
    color: #64748b;
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: var(--transition-fast);
    border-radius: 0;
}
.nav-tab-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.nav-tab-btn.active {
    background: #eff6ff;
    color: #1d4ed8;
    border-left-color: #2563eb;
    font-weight: 700;
}
.stat-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: var(--radius-md);
    padding: 1.25rem;
    text-align: center;
    transition: var(--transition-fast);
}
.stat-box:hover {
    border-color: #2563eb;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.08);
    transform: translateY(-2px);
}
.stat-box .num {
    font-family: var(--font-heading);
    font-size: 2rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1;
}
.stat-box .label {
    font-size: 0.725rem;
    color: #64748b;
    margin-top: 0.4rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.tab-pane {
    display: none;
}
.tab-pane.active {
    display: block;
}
.quote-card {
    border: 1px solid #e2e8f0;
    border-radius: var(--radius-md);
    background: #ffffff;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.04);
    transition: var(--transition-fast);
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.quote-card:hover {
    box-shadow: 0 12px 30px rgba(37, 99, 235, 0.08);
    border-color: #bfdbfe;
    transform: translateY(-2px);
}
.quote-header {
    padding: 1.25rem 1.5rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.quote-body {
    padding: 1.5rem;
}
.quote-ref-badge {
    font-size: 0.8rem;
    font-weight: 600;
    background: #eff6ff;
    color: #1d4ed8;
    padding: 0.25rem 0.65rem;
    border-radius: 4px;
    border: 1px solid #bfdbfe;
    font-family: monospace;
    letter-spacing: 0.05em;
}
.quote-specs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    background: #f8fafc;
    padding: 1.25rem;
    border-radius: var(--radius-md);
    border: 1px solid #e2e8f0;
}
.spec-item .spec-label {
    font-size: 0.7rem;
    color: #64748b;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.08em;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.spec-item .spec-value {
    font-size: 0.925rem;
    color: #0f172a;
    font-weight: 600;
    margin-top: 0.35rem;
}
.quote-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 1rem;
    border-radius: var(--radius-sm);
    font-size: 0.825rem;
    font-weight: 600;
    text-decoration: none;
    transition: var(--transition);
}
</style>

<!-- Profile Header Banner -->
<div class="profile-hero">
    <div class="container">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 1.75rem;">
                <div class="profile-avatar-circle">
                    <?php if (!empty($user_avatar_path)): ?>
                        <img src="<?php echo htmlspecialchars($user_avatar_path); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <?php endif; ?>
                </div>
                <div>
                    <span class="section-eyebrow" style="color: #2563eb; margin-bottom: 0.25rem;">Client Space</span>
                    <h1 style="font-family: var(--font-heading); font-size: 2.15rem; margin-bottom: 0.35rem; color: #0f172a; font-weight: 700; letter-spacing: -0.01em;">
                        <?php echo htmlspecialchars($user['name']); ?>
                    </h1>
                    <p style="color: #64748b; font-size: 0.875rem; display: flex; align-items: center; gap: 0.65rem; margin: 0; flex-wrap: wrap;">
                        <span style="display: inline-flex; align-items: center; gap: 0.35rem;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                            <?php echo htmlspecialchars($user['email']); ?>
                        </span>
                        <span>•</span>
                        <span>Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                    </p>
                </div>
            </div>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <a href="consultation.php" class="btn btn-primary" style="background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); border: none; color: #ffffff; font-weight: 600; box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);">
                    + New Project Quote
                </a>
                <a href="user_logout.php" class="btn btn-outline" style="border-color: #cbd5e1; color: #475569; background: #ffffff;">Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- Main Profile Body -->
<div class="profile-wrapper">
    <div class="container">
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success" style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-3" style="grid-template-columns: 1fr 2.6fr; gap: 2rem; align-items: flex-start;">
            
            <!-- Left Sidebar Navigation -->
            <div>
                <div class="profile-card" style="margin-bottom: 1.5rem;">
                    <div style="padding: 1.35rem 1.5rem; border-bottom: 1px solid var(--color-light-gray); background: var(--color-ivory);">
                        <h3 style="font-family: var(--font-heading); font-size: 1.05rem; color: var(--color-black); margin-bottom: 0.25rem; font-weight: 600;">
                            <?php echo htmlspecialchars($user['name']); ?>
                        </h3>
                        <span style="font-size: 0.725rem; color: var(--color-bronze); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: var(--color-bronze);"></span>
                            Verified Client
                        </span>
                    </div>
                    <div style="display: flex; flex-direction: column;">
                        <button class="nav-tab-btn <?php echo $active_tab == 'profile' ? 'active' : ''; ?>" onclick="openTab('profile', this)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            Personal Information
                        </button>
                        <button class="nav-tab-btn <?php echo $active_tab == 'quotes' ? 'active' : ''; ?>" id="tab-btn-quotes" onclick="openTab('quotes', this)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                            My Quote Requests (<?php echo count($user_quotes); ?>)
                        </button>
                        <button class="nav-tab-btn <?php echo $active_tab == 'consultations' ? 'active' : ''; ?>" id="tab-btn-consultations" onclick="openTab('consultations', this)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            Consultations & Sessions (<?php echo count($user_consultations); ?>)
                        </button>
                        <button class="nav-tab-btn <?php echo $active_tab == 'reviews' ? 'active' : ''; ?>" id="tab-btn-reviews" onclick="openTab('reviews', this)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            Share Review (<?php echo count($user_testimonials); ?>)
                        </button>
                        <button class="nav-tab-btn <?php echo $active_tab == 'security' ? 'active' : ''; ?>" onclick="openTab('security', this)">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            Security & Credentials
                        </button>
                    </div>
                </div>

                <!-- Quick Stats Box -->
                <div class="profile-card" style="padding: 1.5rem;">
                    <div class="grid grid-3" style="gap: 0.5rem;">
                        <div class="stat-box">
                            <div class="num"><?php echo count($user_quotes); ?></div>
                            <div class="label">Quotes</div>
                        </div>
                        <div class="stat-box">
                            <div class="num"><?php echo count($user_consultations); ?></div>
                            <div class="label">Meetings</div>
                        </div>
                        <div class="stat-box">
                            <div class="num"><?php echo count($user_testimonials); ?></div>
                            <div class="label">Reviews</div>
                        </div>
                    </div>
                    <div style="margin-top: 1.25rem; text-align: center;">
                        <a href="consultation.php" style="color: var(--color-bronze); font-size: 0.85rem; font-weight: 600; text-decoration: none;">
                            + Request New Estimate &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Content Area -->
            <div>
                
                <!-- TAB 1: Personal Information & Avatar -->
                <div id="tab-profile" class="tab-pane <?php echo $active_tab == 'profile' ? 'active' : ''; ?>">
                    
                    <!-- Avatar Management Card -->
                    <div class="profile-card" style="padding: 2.25rem; margin-bottom: 1.5rem;">
                        <h2 style="font-family: var(--font-heading); font-size: 1.25rem; color: var(--color-black); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.65rem; font-weight: 600;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-bronze)" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                            Profile Photo
                        </h2>
                        
                        <div style="display: flex; align-items: center; gap: 1.75rem; flex-wrap: wrap;">
                            <div style="width: 88px; height: 88px; border-radius: 50%; overflow: hidden; border: 2px solid var(--color-bronze); background: var(--color-charcoal); display: flex; align-items: center; justify-content: center; font-size: 2rem; flex-shrink: 0; color: var(--color-bronze-light);" id="avatarPreviewContainer">
                                <?php if (!empty($user_avatar_path)): ?>
                                    <img src="<?php echo htmlspecialchars($user_avatar_path); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;" id="avatarPreviewImg">
                                <?php else: ?>
                                    <span id="avatarPlaceholder">
                                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div style="flex: 1; min-width: 240px;">
                                <div style="display: flex; gap: 0.65rem; flex-wrap: wrap; align-items: center;">
                                    <form method="POST" enctype="multipart/form-data" id="avatarForm" style="display: inline-flex; gap: 0.65rem;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="upload_profile_picture">
                                        <input type="file" name="profile_picture" id="profile_picture_input" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="previewAvatar(this)">
                                        
                                        <button type="button" onclick="document.getElementById('profile_picture_input').click()" class="btn btn-outline" style="font-size: 0.825rem; padding: 0.55rem 1.15rem;">
                                            Select Photo
                                        </button>
                                        <button type="submit" id="saveAvatarBtn" class="btn btn-primary" style="font-size: 0.825rem; padding: 0.55rem 1.15rem; display: none;">
                                            Save Avatar
                                        </button>
                                    </form>
                                    <?php if (!empty($user['profile_picture'])): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove your profile photo?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="remove_profile_picture">
                                            <button type="submit" class="btn btn-outline" style="color: #dc2626; border-color: #fca5a5; font-size: 0.825rem; padding: 0.55rem 1.15rem;">
                                                Remove
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <p style="color: var(--color-text-muted); font-size: 0.775rem; margin-top: 0.65rem;">Supported formats: JPG, PNG, WEBP. Maximum 5MB.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Details Form -->
                    <div class="profile-card" style="padding: 2.25rem;">
                        <h2 style="font-family: var(--font-heading); font-size: 1.25rem; color: var(--color-black); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.65rem; font-weight: 600;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-bronze)" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                            Personal Details
                        </h2>
                        
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="grid grid-2" style="gap: 1.5rem; margin-bottom: 1.25rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" for="profile_name">Full Name <span class="required">*</span></label>
                                    <input type="text" id="profile_name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required placeholder="Enter full name">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" for="profile_phone">Phone Number</label>
                                    <input type="tel" id="profile_phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="+91 98765 43210">
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 1.75rem;">
                                <label class="form-label">Email Address (Registered Account)</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled style="background: var(--color-ivory); cursor: not-allowed; color: var(--color-text-muted);">
                                <small style="color: var(--color-text-muted); font-size: 0.775rem; margin-top: 0.35rem; display: block;">Your email is tied to all your quotation records and consultations.</small>
                            </div>

                            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                                Save Profile Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- TAB 2: MY QUOTE REQUESTS -->
                <div id="tab-quotes" class="tab-pane <?php echo $active_tab == 'quotes' ? 'active' : ''; ?>">
                    <div class="profile-card" style="padding: 2.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <h2 style="font-family: var(--font-heading); font-size: 1.35rem; color: var(--color-black); font-weight: 600; display: flex; align-items: center; gap: 0.65rem; margin: 0 0 0.35rem;">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-bronze)" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                                    Quotation Proposals
                                </h2>
                                <p style="color: var(--color-text-muted); font-size: 0.85rem; margin: 0;">
                                    Review custom estimates, status updates, and site inspection schedules
                                </p>
                            </div>
                            <a href="consultation.php" class="btn btn-primary" style="font-size: 0.825rem; padding: 0.6rem 1.25rem;">
                                + New Request
                            </a>
                        </div>

                        <?php if (empty($user_quotes)): ?>
                            <div style="text-align: center; padding: 4rem 1.5rem; color: var(--color-text-muted); background: var(--color-ivory); border-radius: var(--radius-sm); border: 1px dashed var(--color-light-gray);">
                                <div style="width: 52px; height: 52px; border-radius: 50%; background: #FFFFFF; color: var(--color-bronze); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; border: 1px solid var(--color-light-gray);">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                                </div>
                                <h3 style="font-family: var(--font-heading); font-size: 1.25rem; color: var(--color-black); font-weight: 600; margin-bottom: 0.5rem;">No Quotes on Record</h3>
                                <p style="font-size: 0.9rem; max-width: 440px; margin: 0 auto 1.5rem; line-height: 1.6;">
                                    Ready to discuss your interior vision? Use our multi-step quote wizard to calculate an instant architectural estimate.
                                </p>
                                <a href="consultation.php" class="btn btn-primary">
                                    Request Your Custom Quote &rarr;
                                </a>
                            </div>
                        <?php else: ?>
                            <div>
                                <?php foreach ($user_quotes as $quote): 
                                    $st = getQuoteStatusInfo($quote['status']);
                                    $qNum = $quote['quote_number'] ?: ('CTI-Q-' . str_pad($quote['id'], 4, '0', STR_PAD_LEFT));
                                    $scopeTitle = ucwords(str_replace('_', ' ', $quote['project_type'] ?? 'Interior Consultation'));
                                    $propType = ucwords(str_replace('_', ' ', $quote['property_type'] ?? 'Residential'));
                                    $waText = urlencode("Hello Creative Touch Interiors, I am checking the status of my design quote " . $qNum . " for " . $scopeTitle . ".");
                                    $finalEstimate = (float)($quote['final_estimate'] ?? 0);
                                    $displayAmount = $finalEstimate > 0 ? $finalEstimate : (float)($quote['estimated_total'] ?? 0);
                                ?>
                                    <div class="quote-card">
                                        <!-- Card Header -->
                                        <div class="quote-header">
                                            <div style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
                                                <span class="quote-ref-badge"><?php echo htmlspecialchars($qNum); ?></span>
                                                <h3 style="font-family: var(--font-heading); font-size: 1.15rem; color: var(--color-black); font-weight: 600; margin: 0;">
                                                    <?php echo htmlspecialchars($scopeTitle); ?> <span style="font-size: 0.85rem; font-weight: 400; color: var(--color-text-muted);">(<?php echo htmlspecialchars($propType); ?>)</span>
                                                </h3>
                                                <span style="font-size: 0.8rem; color: var(--color-light-gray);">•</span>
                                                <span style="font-size: 0.8rem; color: var(--color-text-muted); display: inline-flex; align-items: center; gap: 0.35rem;">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                                    <?php echo date('M d, Y', strtotime($quote['created_at'])); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <span class="badge" style="background: <?php echo $st['bg']; ?>; color: <?php echo $st['color']; ?>; border: 1px solid <?php echo $st['border']; ?>; font-weight: 600; font-size: 0.75rem;">
                                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: currentColor; display: inline-block; margin-right: 4px;"></span>
                                                    <?php echo $st['label']; ?>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Card Body -->
                                        <div class="quote-body">
                                            
                                            <!-- Site Visit Schedule Banner (if set) -->
                                            <?php if (!empty($quote['site_visit_date'])): ?>
                                                <div style="background: var(--color-ivory); border-left: 3px solid var(--color-bronze); border-radius: 4px; padding: 0.85rem 1.15rem; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                                                    <div style="display: flex; align-items: center; gap: 0.65rem; color: var(--color-black); font-size: 0.85rem;">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--color-bronze)" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                                        <span><strong>Site Inspection Confirmed:</strong> <?php echo date('l, F j, Y', strtotime($quote['site_visit_date'])); ?> <?php echo !empty($quote['site_visit_time']) ? 'at ' . date('h:i A', strtotime($quote['site_visit_time'])) : ''; ?></span>
                                                    </div>
                                                    <span style="font-size: 0.725rem; background: var(--color-black); color: #FFFFFF; padding: 0.2rem 0.55rem; border-radius: 3px; font-weight: 600;">Scheduled</span>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Specifications Grid -->
                                            <div class="quote-specs-grid">
                                                <div class="spec-item">
                                                    <div class="spec-label">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                                                        Property
                                                    </div>
                                                    <div class="spec-value"><?php echo htmlspecialchars($propType); ?></div>
                                                </div>
                                                <div class="spec-item">
                                                    <div class="spec-label">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path></svg>
                                                        Location
                                                    </div>
                                                    <div class="spec-value"><?php echo htmlspecialchars($quote['city'] ?: 'Surat, Gujarat'); ?></div>
                                                </div>
                                                <div class="spec-item">
                                                    <div class="spec-label">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12h20"></path></svg>
                                                        Carpet Area
                                                    </div>
                                                    <div class="spec-value"><?php 
                                                        $sqft = (float)($quote['carpet_area'] ?? ($quote['approx_area'] ?? 0));
                                                        echo $sqft > 0 ? number_format($sqft) . ' sq ft' : 'Not specified'; 
                                                    ?></div>
                                                </div>
                                                <div class="spec-item">
                                                    <div class="spec-label">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon></svg>
                                                        Layout
                                                    </div>
                                                    <div class="spec-value"><?php echo htmlspecialchars($quote['rooms_count'] ?: 'N/A'); ?> Rooms</div>
                                                </div>
                                                <div class="spec-item">
                                                    <div class="spec-label">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>
                                                        Style
                                                    </div>
                                                    <div class="spec-value"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $quote['design_style'] ?? 'Modern Luxury'))); ?></div>
                                                </div>
                                                <div class="spec-item">
                                                    <div class="spec-label">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                                                        Estimated Total
                                                    </div>
                                                    <div class="spec-value" style="color: var(--color-black); font-size: 1.1rem; font-family: monospace;">
                                                        ₹<?php echo number_format($displayAmount, 2); ?>
                                                        <?php if (!empty($quote['final_estimate']) && $quote['final_estimate'] > 0): ?>
                                                            <small style="font-size: 0.65rem; color: var(--color-bronze); display: block; font-weight: 600; font-family: var(--font-body);">(Approved Estimate)</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Selected Services Chips -->
                                            <?php if (!empty($quote['services'])): ?>
                                                <div style="margin-top: 1.15rem;">
                                                    <div style="font-size: 0.725rem; font-weight: 600; color: var(--color-text-muted); margin-bottom: 0.45rem; text-transform: uppercase; letter-spacing: 0.06em;">
                                                        Selected Modules:
                                                    </div>
                                                    <div style="display: flex; flex-wrap: wrap; gap: 0.45rem;">
                                                        <?php foreach ($quote['services'] as $srv): ?>
                                                            <span style="background: var(--color-ivory); border: 1px solid var(--color-light-gray); color: var(--color-text); padding: 0.25rem 0.65rem; border-radius: 4px; font-size: 0.75rem; font-weight: 500; display: inline-flex; align-items: center; gap: 0.35rem;">
                                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                                <?php echo htmlspecialchars($srv['service_title'] ?: $srv['service_name']); ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Action Shortcuts -->
                                            <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--color-light-gray); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                                    <a href="quote_details.php?id=<?php echo $quote['id']; ?>" class="quote-action-btn" style="background: var(--color-black); color: #FFFFFF;">
                                                        View Full Breakdown &rarr;
                                                    </a>
                                                    <a href="https://wa.me/919316856961?text=<?php echo $waText; ?>" target="_blank" class="quote-action-btn" style="background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0;">
                                                        Chat on WhatsApp
                                                    </a>
                                                    <a href="tel:+919316856961" class="quote-action-btn" style="background: var(--color-ivory); color: var(--color-black); border: 1px solid var(--color-light-gray);">
                                                        Call Studio
                                                    </a>
                                                </div>
                                                <div style="font-size: 0.75rem; color: var(--color-text-muted);">
                                                    Creative Touch Interiors
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TAB 3: SCHEDULED CONSULTATIONS -->
                <div id="tab-consultations" class="tab-pane <?php echo $active_tab == 'consultations' ? 'active' : ''; ?>">
                    <div class="profile-card" style="padding: 2.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <h2 style="font-family: var(--font-heading); font-size: 1.35rem; color: var(--color-black); font-weight: 600; display: flex; align-items: center; gap: 0.65rem; margin: 0 0 0.35rem;">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-bronze)" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                    Consultations & Studio Sessions
                                </h2>
                                <p style="color: var(--color-text-muted); font-size: 0.85rem; margin: 0;">
                                    Appointments and design sessions scheduled with our lead architects
                                </p>
                            </div>
                            <a href="contact.php" class="btn btn-outline" style="font-size: 0.825rem; padding: 0.55rem 1.15rem;">
                                + Schedule Session
                            </a>
                        </div>

                        <?php if (empty($user_consultations)): ?>
                            <div style="text-align: center; padding: 3.5rem 1.5rem; color: var(--color-text-muted); background: var(--color-ivory); border-radius: var(--radius-sm); border: 1px dashed var(--color-light-gray);">
                                <div style="width: 52px; height: 52px; border-radius: 50%; background: #FFFFFF; color: var(--color-bronze); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; border: 1px solid var(--color-light-gray);">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                </div>
                                <h3 style="font-family: var(--font-heading); font-size: 1.25rem; color: var(--color-black); font-weight: 600; margin-bottom: 0.5rem;">No Appointments Scheduled</h3>
                                <p style="font-size: 0.9rem; max-width: 440px; margin: 0 auto 1.5rem; line-height: 1.6;">
                                    Schedule a 1-on-1 private consultation at our design studio or request a site visit.
                                </p>
                                <a href="contact.php" class="btn btn-primary">Book Consultation Session</a>
                            </div>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <?php foreach ($user_consultations as $c): 
                                    $c_status = strtolower($c['status'] ?? 'pending');
                                ?>
                                    <div style="border: 1px solid var(--color-light-gray); border-radius: var(--radius-sm); padding: 1.25rem 1.5rem; background: #ffffff;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; flex-wrap: wrap; gap: 0.5rem;">
                                            <div>
                                                <h4 style="font-family: var(--font-heading); font-size: 1.15rem; color: var(--color-black); font-weight: 600; margin-bottom: 0.25rem;">
                                                    <?php echo htmlspecialchars($c['subject'] ?: 'Interior Design Consultation'); ?>
                                                </h4>
                                                <div style="font-size: 0.825rem; color: var(--color-bronze); font-weight: 500; display: flex; align-items: center; gap: 0.5rem;">
                                                    <span style="display: inline-flex; align-items: center; gap: 0.3rem;">
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                                        <?php echo date('l, M d, Y', strtotime($c['consultation_date'])); ?>
                                                    </span>
                                                    <?php if (!empty($c['consultation_time']) && $c['consultation_time'] != '00:00:00'): ?>
                                                        <span>•</span>
                                                        <span><?php echo date('h:i A', strtotime($c['consultation_time'])); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="badge badge-primary" style="text-transform: capitalize;">
                                                    <?php echo htmlspecialchars($c_status); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <?php if (!empty($c['notes'])): ?>
                                            <div style="background: var(--color-ivory); padding: 0.75rem 1rem; border-radius: 4px; font-size: 0.825rem; color: var(--color-text); margin-top: 0.75rem; border-left: 2px solid var(--color-bronze);">
                                                <strong>Discussion Scope:</strong> <?php echo nl2br(htmlspecialchars($c['notes'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TAB 4: Security & Password -->
                <div id="tab-security" class="tab-pane <?php echo $active_tab == 'security' ? 'active' : ''; ?>">
                    <div class="profile-card" style="padding: 2.25rem;">
                        <h2 style="font-family: var(--font-heading); font-size: 1.25rem; color: var(--color-black); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.65rem; font-weight: 600;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-bronze)" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            Security & Account Credentials
                        </h2>
                        
                        <form method="POST" onsubmit="return validatePasswordForm();">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group" style="margin-bottom: 1.25rem;">
                                <label class="form-label" for="curr_pass">Current Password <span class="required">*</span></label>
                                <div style="position: relative;">
                                    <input type="password" name="current_password" id="curr_pass" class="form-control" required placeholder="Enter current password">
                                    <button type="button" onclick="togglePassView('curr_pass')" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--color-text-muted); padding: 0.25rem;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 1.25rem;">
                                <label class="form-label" for="new_pass">New Password <span class="required">*</span></label>
                                <div style="position: relative;">
                                    <input type="password" name="new_password" id="new_pass" class="form-control" required placeholder="At least 6 characters" oninput="checkStrength(this.value)">
                                    <button type="button" onclick="togglePassView('new_pass')" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--color-text-muted); padding: 0.25rem;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </div>
                                <div id="strengthMeter" style="height: 3px; background: var(--color-light-gray); border-radius: 2px; margin-top: 0.5rem; overflow: hidden;">
                                    <div id="strengthBar" style="width: 0%; height: 100%; transition: all 0.3s ease;"></div>
                                </div>
                                <small id="strengthText" style="color: var(--color-text-muted); font-size: 0.725rem; margin-top: 0.25rem; display: block;"></small>
                            </div>

                            <div class="form-group" style="margin-bottom: 1.75rem;">
                                <label class="form-label" for="conf_pass">Confirm New Password <span class="required">*</span></label>
                                <div style="position: relative;">
                                    <input type="password" name="confirm_password" id="conf_pass" class="form-control" required placeholder="Repeat new password">
                                    <button type="button" onclick="togglePassView('conf_pass')" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--color-text-muted); padding: 0.25rem;">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                                Update Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- TAB 5: Client Experience & Review -->
                <div id="tab-reviews" class="tab-pane <?php echo $active_tab == 'reviews' ? 'active' : ''; ?>">
                    <div class="profile-card" style="padding: 2.25rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <h2 style="font-family: var(--font-heading); font-size: 1.35rem; color: var(--color-black); font-weight: 600; display: flex; align-items: center; gap: 0.65rem; margin: 0 0 0.35rem;">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--color-bronze)" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                    Share Your Design Experience
                                </h2>
                                <p style="color: var(--color-text-muted); font-size: 0.85rem; margin: 0;">
                                    Submit your testimonial for moderation and publication on our verified client showcase
                                </p>
                            </div>
                        </div>

                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="submit_testimonial">

                            <div class="grid grid-2" style="gap: 1.5rem; margin-bottom: 1.25rem;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" for="review_project">Project / Space Scope <span class="required">*</span></label>
                                    <input type="text" id="review_project" name="project_title" class="form-control" required placeholder="e.g. 4BHK Penthouse, Surat or Luxury Villa">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" for="review_rating">Rating <span class="required">*</span></label>
                                    <select id="review_rating" name="rating" class="form-control" required>
                                        <option value="5" selected>5 Stars — Exceptional Masterpiece</option>
                                        <option value="4">4 Stars — Very Good Experience</option>
                                        <option value="3">3 Stars — Met Expectations</option>
                                        <option value="2">2 Stars — Needs Improvement</option>
                                        <option value="1">1 Star — Unsatisfactory</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                <label class="form-label" for="review_text">Your Testimonial / Feedback <span class="required">*</span></label>
                                <textarea id="review_text" name="testimonial" class="form-control" rows="4" required placeholder="Tell us about the craftsmanship, turnaround, spatial planning, and aesthetic satisfaction..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                                Submit Testimonial for Review
                            </button>
                        </form>
                    </div>

                    <!-- Client Testimonials History -->
                    <div class="profile-card" style="padding: 2.25rem;">
                        <h3 style="font-family: var(--font-heading); font-size: 1.2rem; color: var(--color-black); margin-bottom: 1.25rem; font-weight: 600;">
                            Your Submitted Reviews
                        </h3>

                        <?php if (empty($user_testimonials)): ?>
                            <p style="color: var(--color-text-muted); font-size: 0.9rem; margin: 0;">You haven't submitted any reviews yet. Share your experience using the form above.</p>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <?php foreach ($user_testimonials as $t): 
                                    $t_status = $t['status'] ?? 'pending';
                                    $badge_style = 'background: #fef3c7; color: #b45309; border: 1px solid #fde68a;';
                                    $badge_label = 'Pending Moderation';
                                    if ($t_status === 'approved') {
                                        $badge_style = 'background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;';
                                        $badge_label = 'Approved & Published';
                                    } elseif ($t_status === 'rejected') {
                                        $badge_style = 'background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;';
                                        $badge_label = 'Declined';
                                    }
                                ?>
                                    <div style="border: 1px solid var(--color-light-gray); border-radius: var(--radius-sm); padding: 1.25rem 1.5rem; background: #ffffff;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.65rem; flex-wrap: wrap; gap: 0.5rem;">
                                            <div>
                                                <h4 style="font-family: var(--font-heading); font-size: 1.1rem; color: var(--color-black); font-weight: 600; margin: 0 0 0.25rem;">
                                                    <?php echo htmlspecialchars($t['project_title'] ?: 'Interior Design Project'); ?>
                                                </h4>
                                                <div style="color: #f59e0b; font-size: 0.9rem;">
                                                    <?php echo str_repeat('★', (int)$t['rating']) . str_repeat('☆', 5 - (int)$t['rating']); ?>
                                                    <span style="color: var(--color-text-muted); font-size: 0.775rem; margin-left: 0.35rem;">
                                                        • <?php echo date('M d, Y', strtotime($t['created_at'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <span style="display: inline-block; padding: 0.25rem 0.65rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; <?php echo $badge_style; ?>">
                                                <?php echo $badge_label; ?>
                                            </span>
                                        </div>
                                        <p style="color: var(--color-text); font-size: 0.875rem; line-height: 1.5; margin: 0;">
                                            "<?php echo nl2br(htmlspecialchars($t['testimonial'])); ?>"
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function openTab(tabName, el) {
    document.querySelectorAll('.tab-pane').forEach(function(pane) {
        pane.classList.remove('active');
    });
    document.querySelectorAll('.nav-tab-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });
    
    const target = document.getElementById('tab-' + tabName);
    if (target) target.classList.add('active');
    if (el) el.classList.add('active');
}

// Auto-switch tab if hash present in URL
window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace('#', '');
    if (hash === 'quotes') {
        const btn = document.getElementById('tab-btn-quotes');
        if (btn) openTab('quotes', btn);
    } else if (hash === 'consultations') {
        const btn = document.getElementById('tab-btn-consultations');
        if (btn) openTab('consultations', btn);
    } else if (hash === 'reviews') {
        const btn = document.getElementById('tab-btn-reviews');
        if (btn) openTab('reviews', btn);
    }
});

function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const container = document.getElementById('avatarPreviewContainer');
            container.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">';
            document.getElementById('saveAvatarBtn').style.display = 'inline-block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function togglePassView(id) {
    const input = document.getElementById(id);
    if (input) {
        input.type = input.type === 'password' ? 'text' : 'password';
    }
}

function checkStrength(val) {
    const bar = document.getElementById('strengthBar');
    const text = document.getElementById('strengthText');
    if (!val) {
        bar.style.width = '0%';
        text.textContent = '';
        return;
    }
    let score = 0;
    if (val.length >= 6) score += 33;
    if (val.length >= 10) score += 33;
    if (/[0-9]/.test(val) && /[A-Z]/.test(val)) score += 34;

    bar.style.width = score + '%';
    if (score < 40) {
        bar.style.background = '#ef4444';
        text.textContent = 'Weak password';
        text.style.color = '#ef4444';
    } else if (score < 80) {
        bar.style.background = '#f59e0b';
        text.textContent = 'Moderate password';
        text.style.color = '#f59e0b';
    } else {
        bar.style.background = '#10b981';
        text.textContent = 'Strong password';
        text.style.color = '#10b981';
    }
}

function validatePasswordForm() {
    const p1 = document.getElementById('new_pass').value;
    const p2 = document.getElementById('conf_pass').value;
    if (p1 !== p2) {
        alert('New passwords do not match!');
        return false;
    }
    return true;
}

// Force reload on Back/Forward navigation from bfcache to validate session with server
window.addEventListener('pageshow', function(event) {
    if (event.persisted || (window.performance && window.performance.getEntriesByType && window.performance.getEntriesByType("navigation")[0]?.type === 'back_forward')) {
        window.location.reload();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
