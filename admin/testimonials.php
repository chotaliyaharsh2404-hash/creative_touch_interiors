<?php
require_once '../includes/config.php';

// Enforce super_admin or admin role and prevent caching (Receptionist blocked)
requireRoles(['super_admin', 'admin'], 'dashboard.php');

$error = '';
$success = '';

// Auto-seed testimonials if table is completely empty
$checkCount = $conn->query("SELECT COUNT(*) as c FROM testimonials");
$rowCount = $checkCount ? ($checkCount->fetch_assoc()['c'] ?? 0) : 0;
if ($rowCount == 0) {
    $seedTestimonials = [
        [
            'client_name' => 'Rajesh & Meera Patel',
            'project_title' => 'Palatial Villa, Surat',
            'rating' => 5,
            'testimonial' => 'Creative Touch Interiors transformed our bare shell villa into an extraordinary sanctuary. Their attention to lighting layers, joinery detailing, and acoustic comfort exceeded every expectation.',
            'featured' => 1,
            'status' => 'approved'
        ],
        [
            'client_name' => 'Ananya Singhania',
            'project_title' => 'Luxury High-Rise Penthouse, Mumbai',
            'rating' => 5,
            'testimonial' => 'From the 3D visualization phase to turnkey execution, Harsh and Het handled our penthouse with impeccable professionalism and unmatched aesthetic sensitivity.',
            'featured' => 1,
            'status' => 'approved'
        ],
        [
            'client_name' => 'Vikramaditya Shah',
            'project_title' => 'Corporate HQ & Executive Suite, Pune',
            'rating' => 5,
            'testimonial' => 'The executive floorplan designed by the CTI team perfectly mirrors our company ethos — clean, authoritative, and tranquil. Outstanding craftsmanship throughout.',
            'featured' => 1,
            'status' => 'approved'
        ]
    ];

    foreach ($seedTestimonials as $st) {
        $ins = $conn->prepare("INSERT INTO testimonials (client_name, project_title, rating, testimonial, featured, status) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->bind_param("ssisis", $st['client_name'], $st['project_title'], $st['rating'], $st['testimonial'], $st['featured'], $st['status']);
        $ins->execute();
    }
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = "Security token mismatch. Please reload and try again.";
    } elseif (isset($_POST['action'])) {
        $action = $_POST['action'];

        // 1. ADD TESTIMONIAL
        if ($action === 'add') {
            $client_name = sanitize($_POST['client_name'] ?? '');
            $project_title = sanitize($_POST['project_title'] ?? '');
            $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
            $testimonial = sanitize($_POST['testimonial'] ?? '');
            $featured = isset($_POST['featured']) ? 1 : 0;
            $status = sanitize($_POST['status'] ?? 'approved');

            if (empty($client_name) || empty($testimonial)) {
                $error = "Client name and review message are required.";
            } else {
                $stmt = $conn->prepare("INSERT INTO testimonials (client_name, project_title, rating, testimonial, featured, status) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssisis", $client_name, $project_title, $rating, $testimonial, $featured, $status);
                if ($stmt->execute()) {
                    $success = "Testimonial review added successfully!";
                } else {
                    $error = "Failed to add testimonial.";
                }
            }
        }

        // 2. EDIT TESTIMONIAL
        if ($action === 'edit') {
            $id = (int)$_POST['id'];
            $client_name = sanitize($_POST['client_name'] ?? '');
            $project_title = sanitize($_POST['project_title'] ?? '');
            $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
            $testimonial = sanitize($_POST['testimonial'] ?? '');
            $featured = isset($_POST['featured']) ? 1 : 0;
            $status = sanitize($_POST['status'] ?? 'approved');

            if (empty($client_name) || empty($testimonial)) {
                $error = "Client name and review message are required.";
            } else {
                $stmt = $conn->prepare("UPDATE testimonials SET client_name=?, project_title=?, rating=?, testimonial=?, featured=?, status=? WHERE id=?");
                $stmt->bind_param("ssisisi", $client_name, $project_title, $rating, $testimonial, $featured, $status, $id);
                if ($stmt->execute()) {
                    $success = "Testimonial updated successfully!";
                } else {
                    $error = "Failed to update testimonial.";
                }
            }
        }

        // 3. UPDATE STATUS (Approve / Reject)
        if ($action === 'set_status') {
            $id = (int)$_POST['id'];
            $newStatus = sanitize($_POST['status'] ?? 'pending');
            $stmt = $conn->prepare("UPDATE testimonials SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $id);
            if ($stmt->execute()) {
                $success = "Testimonial status changed to " . ucfirst($newStatus) . "!";
            }
        }

        // 4. TOGGLE FEATURED
        if ($action === 'toggle_featured') {
            $id = (int)$_POST['id'];
            $featured = (int)($_POST['featured'] ?? 0);
            $stmt = $conn->prepare("UPDATE testimonials SET featured = ? WHERE id = ?");
            $stmt->bind_param("ii", $featured, $id);
            if ($stmt->execute()) {
                $success = $featured ? "Testimonial marked as Featured on Home Page!" : "Testimonial removed from Featured.";
            }
        }

        // 5. DELETE
        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare("DELETE FROM testimonials WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $success = "Testimonial review deleted successfully.";
            }
        }
    }
}

// Filter Handling
$filter = sanitize($_GET['filter'] ?? 'all');
$whereClause = "";
if ($filter === 'pending') {
    $whereClause = "WHERE status = 'pending'";
} elseif ($filter === 'approved') {
    $whereClause = "WHERE status = 'approved'";
} elseif ($filter === 'featured') {
    $whereClause = "WHERE featured = 1";
}

// Fetch Metrics
$total_count = $conn->query("SELECT COUNT(*) as c FROM testimonials")->fetch_assoc()['c'] ?? 0;
$pending_count = $conn->query("SELECT COUNT(*) as c FROM testimonials WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0;
$approved_count = $conn->query("SELECT COUNT(*) as c FROM testimonials WHERE status = 'approved'")->fetch_assoc()['c'] ?? 0;
$featured_count = $conn->query("SELECT COUNT(*) as c FROM testimonials WHERE featured = 1")->fetch_assoc()['c'] ?? 0;

// Fetch Reviews
$testimonials = [];
$res = $conn->query("SELECT * FROM testimonials $whereClause ORDER BY created_at DESC, id DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $testimonials[] = $r;
    }
}

$page_title = 'Client Testimonials & Reviews — Executive Suite';
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
        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.825rem;
            font-weight: 600;
            text-decoration: none;
            color: #94a3b8;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.09);
            transition: all 0.2s ease;
        }
        .filter-btn.active, .filter-btn:hover {
            background: #eff6ff;
            color: #2563eb;
            border-color: #93c5fd;
        }
        .review-card {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            padding: 1.75rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.25s ease;
        }
        .review-card:hover {
            border-color: #93c5fd;
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -4px rgba(37, 99, 235, 0.12);
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
            border: 1px solid #bfdbfe;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.2);
            border-radius: 16px;
            color: #0f172a;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2.25rem;
        }
    </style>
</head>
<body style="background: #F8F7F4; color: #0f172a;">
    <div style="display: flex; min-height: 100vh;">
        
        <?php include 'includes/sidebar.php'; ?>

        <div class="admin-content" style="flex: 1; padding: 2.25rem 2.5rem;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="font-family: var(--font-heading); font-size: 1.85rem; color: #0f172a; margin: 0 0 0.25rem;">
                        Client Reviews & Testimonials
                    </h1>
                    <p style="color: #64748b; font-size: 0.885rem; margin: 0;">
                        Moderate verified client reviews and configure featured testimonials for the public homepage.
                    </p>
                </div>
                <div>
                    <button onclick="openAddModal()" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; background: #0057FF; color: #ffffff; font-weight: 700; border: none; border-radius: 9999px; padding: 0.75rem 1.4rem; box-shadow: 0 10px 24px rgba(0, 87, 255, 0.28), 0 2px 6px rgba(15, 23, 42, 0.05); transform: translateY(-1px); transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1); cursor: pointer;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        <span>Add Testimonial</span>
                    </button>
                </div>
            </div>

            <!-- Metrics Bar -->
            <div class="grid grid-4" style="gap: 1.25rem; margin-bottom: 2rem;">
                <div class="card" style="padding: 1.25rem 1.5rem; background: #ffffff; border: 1px solid #bfdbfe; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <div>
                        <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Total Reviews</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #0f172a;"><?php echo $total_count; ?></div>
                    </div>
                    <span style="font-size: 1.75rem;">💬</span>
                </div>
                <div class="card" style="padding: 1.25rem 1.5rem; background: #ffffff; border: 1px solid #bfdbfe; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <div>
                        <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Approved</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #10b981;"><?php echo $approved_count; ?></div>
                    </div>
                    <span style="font-size: 1.75rem;">✅</span>
                </div>
                <div class="card" style="padding: 1.25rem 1.5rem; background: #ffffff; border: 1px solid #bfdbfe; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <div>
                        <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Pending Moderation</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #ea580c;"><?php echo $pending_count; ?></div>
                    </div>
                    <span style="font-size: 1.75rem;">⏳</span>
                </div>
                <div class="card" style="padding: 1.25rem 1.5rem; background: #ffffff; border: 1px solid #bfdbfe; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                    <div>
                        <div style="font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.04em;">Featured on Home</div>
                        <div style="font-size: 1.75rem; font-weight: 800; color: #1d4ed8;"><?php echo $featured_count; ?></div>
                    </div>
                    <span style="font-size: 1.75rem;">⭐</span>
                </div>
            </div>

            <!-- Filters -->
            <div style="display: flex; gap: 0.5rem; margin-bottom: 1.75rem; flex-wrap: wrap;">
                <a href="testimonials.php?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All (<?php echo $total_count; ?>)</a>
                <a href="testimonials.php?filter=approved" class="filter-btn <?php echo $filter === 'approved' ? 'active' : ''; ?>">Approved (<?php echo $approved_count; ?>)</a>
                <a href="testimonials.php?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending (<?php echo $pending_count; ?>)</a>
                <a href="testimonials.php?filter=featured" class="filter-btn <?php echo $filter === 'featured' ? 'active' : ''; ?>">Featured (<?php echo $featured_count; ?>)</a>
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

            <?php if (empty($testimonials)): ?>
                <div class="card" style="padding: 3.5rem 2rem; text-align: center; background: #ffffff; border: 1px solid #bfdbfe; border-radius: 14px;">
                    <p style="color: #64748b; font-size: 1rem; margin: 0 0 1rem;">No testimonials found for this filter.</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ($testimonials as $t): ?>
                        <div class="review-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                                <div>
                                    <h3 style="font-family: var(--font-heading); font-size: 1.15rem; color: #0f172a; margin: 0 0 0.2rem;">
                                        <?php echo htmlspecialchars($t['client_name']); ?>
                                    </h3>
                                    <div style="font-size: 0.825rem; color: #2563eb; font-weight: 600;">
                                        <?php echo htmlspecialchars($t['project_title'] ?: 'Bespoke Residential Commission'); ?>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <?php if ($t['featured']): ?>
                                        <span style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.725rem; padding: 0.2rem 0.6rem; border-radius: 9999px; font-weight: 600;">
                                            ★ Featured
                                        </span>
                                    <?php endif; ?>
                                    <?php
                                        $statusBg = '#f1f5f9';
                                        $statusColor = '#64748b';
                                        $statusBorder = '#cbd5e1';
                                        if ($t['status'] === 'approved') {
                                            $statusBg = '#ecfdf5';
                                            $statusColor = '#047857';
                                            $statusBorder = '#a7f3d0';
                                        } elseif ($t['status'] === 'pending') {
                                            $statusBg = '#fff7ed';
                                            $statusColor = '#ea580c';
                                            $statusBorder = '#fed7aa';
                                        } elseif ($t['status'] === 'rejected') {
                                            $statusBg = '#fef2f2';
                                            $statusColor = '#b91c1c';
                                            $statusBorder = '#fecaca';
                                        }
                                    ?>
                                    <span style="font-size: 0.725rem; text-transform: capitalize; padding: 0.2rem 0.6rem; border-radius: 9999px; font-weight: 600; background: <?php echo $statusBg; ?>; color: <?php echo $statusColor; ?>; border: 1px solid <?php echo $statusBorder; ?>;">
                                        <?php echo htmlspecialchars($t['status']); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Star Rating -->
                            <div style="color: #2563eb; margin-bottom: 0.75rem; font-size: 1.1rem; letter-spacing: 0.1em;">
                                <?php echo str_repeat('★', (int)$t['rating']) . str_repeat('☆', 5 - (int)$t['rating']); ?>
                            </div>

                            <p style="font-size: 0.925rem; color: #475569; line-height: 1.7; margin: 0 0 1.25rem; font-style: italic;">
                                "<?php echo nl2br(htmlspecialchars($t['testimonial'])); ?>"
                            </p>

                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 0.85rem; font-size: 0.8rem; color: #64748b; flex-wrap: wrap; gap: 0.5rem;">
                                <div>Submitted on: <?php echo date('M d, Y', strtotime($t['created_at'])); ?></div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <?php if ($t['status'] !== 'approved'): ?>
                                        <form method="POST" style="margin: 0;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="set_status">
                                            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <button type="submit" class="btn btn-outline" style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; font-size: 0.785rem; padding: 0.35rem 0.65rem; border-radius: 6px; cursor: pointer;">
                                                ✓ Approve
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="margin: 0;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="set_status">
                                            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                            <input type="hidden" name="status" value="pending">
                                            <button type="submit" class="btn btn-outline" style="background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; font-size: 0.785rem; padding: 0.35rem 0.65rem; border-radius: 6px; cursor: pointer;">
                                                Set to Pending
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="POST" style="margin: 0;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle_featured">
                                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                        <input type="hidden" name="featured" value="<?php echo $t['featured'] ? 0 : 1; ?>">
                                        <button type="submit" class="btn btn-outline" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.785rem; padding: 0.35rem 0.65rem; border-radius: 6px; cursor: pointer;">
                                            <?php echo $t['featured'] ? 'Unfeature' : '★ Feature'; ?>
                                        </button>
                                    </form>

                                    <button onclick='openEditModal(<?php echo json_encode($t); ?>)' class="btn btn-outline" style="background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; font-size: 0.785rem; padding: 0.35rem 0.65rem; border-radius: 6px; cursor: pointer;">
                                        Edit
                                    </button>

                                    <form method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this testimonial?');" style="margin: 0;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                        <button type="submit" class="btn btn-outline" style="color: #f87171; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239,68,68,0.28); font-size: 0.785rem; padding: 0.35rem 0.65rem; border-radius: 6px; cursor: pointer;">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Add Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h2 style="font-family: var(--font-heading); font-size: 1.35rem; margin: 0 0 1.5rem; color: #1e3a8a;">Add Client Testimonial</h2>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="add">

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Client Name *</label>
                    <input type="text" name="client_name" class="form-control" required placeholder="e.g. Sanya & Anirudh Rao" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Project / Residence Title</label>
                    <input type="text" name="project_title" class="form-control" placeholder="e.g. 4BHK Penthouse, Surat" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                </div>

                <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Rating (Stars 1 - 5)</label>
                        <select name="rating" class="form-control" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                            <option value="5">★★★★★ (5 Stars)</option>
                            <option value="4">★★★★☆ (4 Stars)</option>
                            <option value="3">★★★☆☆ (3 Stars)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Initial Status</label>
                        <select name="status" class="form-control" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Review / Testimonial Text *</label>
                    <textarea name="testimonial" class="form-control" rows="4" required placeholder="Client feedback about design quality, project management, and execution..." style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;"></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem; color: #334155;">
                        <input type="checkbox" name="featured" value="1" checked style="accent-color: #2563eb;">
                        <span>Feature on Homepage carousel</span>
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" onclick="closeModals()" class="btn btn-outline" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; border-radius: 8px; padding: 0.65rem 1.25rem; font-weight: 600; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #0057FF; color: #ffffff; font-weight: 700; border: none; border-radius: 8px; padding: 0.65rem 1.4rem; box-shadow: 0 10px 24px rgba(0, 87, 255, 0.28), 0 2px 6px rgba(15, 23, 42, 0.05); cursor: pointer;">Save Testimonial</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2 style="font-family: var(--font-heading); font-size: 1.35rem; margin: 0 0 1.5rem; color: #1e3a8a;">Edit Testimonial</h2>
            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Client Name *</label>
                    <input type="text" name="client_name" id="edit_client_name" class="form-control" required style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Project / Residence Title</label>
                    <input type="text" name="project_title" id="edit_project_title" class="form-control" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                </div>

                <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Rating</label>
                        <select name="rating" id="edit_rating" class="form-control" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                            <option value="5">★★★★★ (5 Stars)</option>
                            <option value="4">★★★★☆ (4 Stars)</option>
                            <option value="3">★★★☆☆ (3 Stars)</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Status</label>
                        <select name="status" id="edit_status" class="form-control" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;">
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label" style="color: #334155; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.35rem; display: block;">Testimonial *</label>
                    <textarea name="testimonial" id="edit_testimonial" class="form-control" rows="4" required style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; border-radius: 8px; padding: 0.65rem 0.85rem; width: 100%;"></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem; color: #334155;">
                        <input type="checkbox" name="featured" id="edit_featured" value="1" style="accent-color: #2563eb;">
                        <span>Feature on Homepage</span>
                    </label>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" onclick="closeModals()" class="btn btn-outline" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; border-radius: 8px; padding: 0.65rem 1.25rem; font-weight: 600; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #0057FF; color: #ffffff; font-weight: 700; border: none; border-radius: 8px; padding: 0.65rem 1.4rem; box-shadow: 0 10px 24px rgba(0, 87, 255, 0.28), 0 2px 6px rgba(15, 23, 42, 0.05); cursor: pointer;">Update Testimonial</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }
        function openEditModal(t) {
            document.getElementById('edit_id').value = t.id;
            document.getElementById('edit_client_name').value = t.client_name;
            document.getElementById('edit_project_title').value = t.project_title || '';
            document.getElementById('edit_rating').value = t.rating;
            document.getElementById('edit_status').value = t.status;
            document.getElementById('edit_testimonial').value = t.testimonial;
            document.getElementById('edit_featured').checked = (t.featured == 1);
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
