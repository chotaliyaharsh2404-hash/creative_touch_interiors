<?php
require_once 'includes/config.php';

// Prevent caching on quote form
preventPageCaching();

// Lock quote system: Enforce user authentication
requireUserLogin('login.php?redirect=consultation.php&msg=quote_required');

$page_title = 'Get Custom 3D Design Quote — Creative Touch Interiors';
$is_logged_in = isUserLoggedIn();

// Fetch authenticated user details to pre-populate
$user_id = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();

if (!$current_user) {
    destroyUserSession();
    redirect('login.php?redirect=consultation.php&msg=quote_required');
}

// Fetch all active services dynamically from MySQL database
$services_res = $conn->query("SELECT * FROM services WHERE status = 'active' ORDER BY category ASC, title ASC");
$active_services = [];
if ($services_res) {
    while ($s = $services_res->fetch_assoc()) {
        $active_services[] = $s;
    }
}

// Handle form submission
$error = '';
$success_quote = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = "Security token mismatch. Please reload the page and try again.";
    } else {
        // Idempotency check: verify submission token if provided
        $subToken = $_POST['submission_token'] ?? '';
        if (!empty($subToken) && !verifyQuoteSubmissionToken($subToken)) {
            $error = "Duplicate submission detected. Your quotation proposal has already been queued.";
        }

        // 1. Customer Information
        $customer_name = sanitize($_POST['customer_name'] ?? ($current_user['name'] ?? ''));
        $email = trim(sanitize($_POST['email'] ?? ($current_user['email'] ?? '')));
        $phone = sanitize($_POST['phone'] ?? ($current_user['phone'] ?? ''));
        $alternate_phone = sanitize($_POST['alternate_phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $state = sanitize($_POST['state'] ?? 'Gujarat');
        $pincode = sanitize($_POST['pincode'] ?? '');

        // 2. Project Information
        $project_type = sanitize($_POST['project_type'] ?? 'Residential');
        $property_type = sanitize($_POST['property_type'] ?? 'Apartment');
        $project_location = sanitize($_POST['project_location'] ?? $city);
        $measurement_status = (isset($_POST['unknown_measurements']) && $_POST['unknown_measurements'] == '1') ? 'unknown' : 'known';
        $floors_count = max(1, (int)($_POST['floors_count'] ?? 1));
        $expected_start_date = !empty($_POST['expected_start_date']) ? sanitize($_POST['expected_start_date']) : null;
        $expected_completion_date = !empty($_POST['expected_completion_date']) ? sanitize($_POST['expected_completion_date']) : null;

        // Process Room Inputs
        $rooms_data = [];
        $approx_area = 0.00;
        $rooms_count = 1;

        if ($measurement_status === 'unknown') {
            $approx_area = 0.00;
            $rooms_count = 1;
        } else {
            if (isset($_POST['rooms']) && is_array($_POST['rooms']) && count($_POST['rooms']) > 0) {
                $rooms_count = 0;
                foreach ($_POST['rooms'] as $r) {
                    $rName = sanitize($r['name'] ?? 'Room');
                    $rLen = $r['length'] ?? '';
                    $rWid = $r['width'] ?? '';
                    $rNotes = sanitize($r['notes'] ?? '');

                    if ($rLen === '' && $rWid === '') continue;

                    $calcRes = validateAndCalculateRoomArea($rLen, $rWid);
                    if (!$calcRes['valid']) {
                        $error = "Room '" . htmlspecialchars($rName) . "' calculation error: " . $calcRes['error'];
                        break;
                    }

                    $rArea = $calcRes['area'];
                    $approx_area += $rArea;
                    $rooms_count++;
                    $rooms_data[] = [
                        'name' => $rName,
                        'length' => (float)$rLen,
                        'width' => (float)$rWid,
                        'area' => $rArea,
                        'notes' => $rNotes
                    ];
                }
            }

            if (empty($rooms_data) && empty($error)) {
                $directArea = (float)($_POST['approx_area'] ?? 0);
                if ($directArea <= 0) {
                    $error = "Please specify valid room dimensions or floor carpet area (in sq.ft).";
                } elseif ($directArea > 50000) {
                    $error = "Floor carpet area exceeds maximum allowable limit of 50,000 sq.ft.";
                } else {
                    $approx_area = $directArea;
                    $rooms_count = max(1, (int)($_POST['rooms_count'] ?? 1));
                    $rooms_data[] = [
                        'name' => 'Main Space',
                        'length' => round(sqrt($approx_area), 2),
                        'width' => round(sqrt($approx_area), 2),
                        'area' => $approx_area,
                        'notes' => 'Total specified floor area'
                    ];
                }
            }
        }

        // 3. Interior Requirements & Preferences
        $design_style = sanitize($_POST['design_style'] ?? 'Modern Luxury');
        $preferred_theme = sanitize($_POST['preferred_theme'] ?? 'Warm Neutral & Oak');
        $budget_range = sanitize($_POST['budget_range'] ?? '₹10L – ₹25L');
        $package_type = sanitize($_POST['package_type'] ?? 'Standard');
        $package_multiplier = getPackageMultiplier($package_type);

        $material_preference = sanitize($_POST['material_preference'] ?? 'Premium Solid Teakwood');
        $matMultiplier = 1.00;
        if (stripos($material_preference, 'luxury') !== false || stripos($material_preference, 'marble') !== false) {
            $matMultiplier = 1.25;
        } elseif (stripos($material_preference, 'veneer') !== false || stripos($material_preference, 'teak') !== false) {
            $matMultiplier = 1.15;
        }

        $special_requirements = sanitize($_POST['special_requirements'] ?? '');
        $additional_notes = sanitize($_POST['additional_notes'] ?? '');

        // Selected Services array
        $selected_service_ids = isset($_POST['selected_services']) && is_array($_POST['selected_services']) 
            ? array_map('intval', $_POST['selected_services']) 
            : [];

        // Server-Side Validation
        if (empty($error)) {
            if (empty($customer_name)) {
                $error = "Please enter your full name.";
            } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
            } elseif (empty($phone) || strlen(preg_replace('/[^0-9]/', '', $phone)) < 10) {
                $error = "Please enter a valid 10-digit mobile number.";
            } elseif (empty($city)) {
                $error = "Please specify your project city.";
            } elseif (empty($budget_range)) {
                $error = "Please select an estimated budget range.";
            }
        }

        // Handle Optional Attachment Upload
        $attachment_path = null;
        $attachment_name = null;

        if (empty($error) && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['attachment'];
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            $max_size = 10 * 1024 * 1024; // 10MB

            $real_mime = mime_content_type($file['tmp_name']);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!in_array($real_mime, $allowed_mimes) || !in_array($ext, $allowed_exts)) {
                $error = "Invalid attachment format. Only JPG, PNG, WEBP images and PDF files are allowed.";
            } elseif ($file['size'] > $max_size) {
                $error = "Attachment size exceeds the maximum 10MB limit.";
            } else {
                $upload_dir = 'uploads/quotes/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }
                $safe_filename = 'quote_doc_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $target_path = $upload_dir . $safe_filename;

                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $attachment_path = $target_path;
                    $attachment_name = sanitize($file['name']);
                } else {
                    $error = "Failed to save uploaded attachment file.";
                }
            }
        }

        // Authoritative Calculation & DB Insertion
        if (empty($error)) {
            $base_rate_sqft = 1200.00;
            if (stripos($design_style, 'luxury') !== false) {
                $base_rate_sqft = 1800.00;
            } elseif (stripos($design_style, 'minimalist') !== false) {
                $base_rate_sqft = 1100.00;
            }

            $base_cost = ($measurement_status === 'unknown') ? 0.00 : round($approx_area * $base_rate_sqft, 2);

            $service_cost = 0.00;
            $calculated_services = [];

            if (!empty($selected_service_ids)) {
                $inClause = implode(',', $selected_service_ids);
                $srvQuery = $conn->query("SELECT * FROM services WHERE id IN ($inClause)");
                if ($srvQuery) {
                    while ($sRow = $srvQuery->fetch_assoc()) {
                        $sType = $sRow['service_type'] ?? 'area_based';
                        $uRate = (float)($sRow['unit_rate'] > 0 ? $sRow['unit_rate'] : ($sRow['starting_price'] ?? 0));
                        $sMin = (float)($sRow['min_charge'] ?? 0);
                        $sWastage = (float)($sRow['default_wastage_percent'] ?? 0);

                        $itemCalc = calculateServiceItem($sType, $uRate, 1.0, $approx_area, $matMultiplier, $sWastage, $sMin);
                        $finalSrvCost = $itemCalc['final_amount'];
                        $service_cost += $finalSrvCost;

                        $calculated_services[] = [
                            'service_id' => (int)$sRow['id'],
                            'service_title' => $sRow['title'],
                            'service_type' => $sType,
                            'unit_rate' => $uRate,
                            'quantity' => 1.0,
                            'area_sqft' => $approx_area,
                            'material_multiplier' => $matMultiplier,
                            'wastage_percent' => $sWastage,
                            'wastage_amount' => $itemCalc['wastage_amount'],
                            'min_charge' => $sMin,
                            'base_amount' => $itemCalc['base_amount'],
                            'final_service_amount' => $finalSrvCost
                        ];
                    }
                }
            }

            $grandCalc = calculateQuoteGrandTotal($base_cost, $service_cost, $package_multiplier, 0.0, 'none', 0.0, 18.0);
            $subtotal = $grandCalc['subtotal'];
            $tax_amount = $grandCalc['tax_amount'];
            $estimated_total = $grandCalc['grand_total'];
            $tax_percent = 18.00;

            $quote_number = generateQuoteNumber($conn);
            $status = 'new';

            $sqlInsert = "INSERT INTO quote_requests (
                quote_number, user_id, customer_name, email, phone, alternate_phone, address, city, state, pincode,
                project_type, property_type, project_location, approx_area, measurement_status, rooms_count, floors_count,
                expected_start_date, expected_completion_date, design_style, preferred_theme, budget_range, package_type, package_multiplier,
                material_preference, material_multiplier, special_requirements, additional_notes, attachment_path, attachment_name,
                status, base_rate_per_sqft, base_cost, service_cost, tax_percentage, tax_amount, estimated_total
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?
            )";

            $stmt = $conn->prepare($sqlInsert);
            $stmt->bind_param(
                "sisssssssssssdsiissssssdsdsssssdddddd",
                $quote_number, $user_id, $customer_name, $email, $phone, $alternate_phone, $address, $city, $state, $pincode,
                $project_type, $property_type, $project_location, $approx_area, $measurement_status, $rooms_count, $floors_count,
                $expected_start_date, $expected_completion_date, $design_style, $preferred_theme, $budget_range, $package_type, $package_multiplier,
                $material_preference, $matMultiplier, $special_requirements, $additional_notes, $attachment_path, $attachment_name,
                $status, $base_rate_sqft, $base_cost, $service_cost, $tax_percent, $tax_amount, $estimated_total
            );

            if ($stmt->execute()) {
                $quote_id = $stmt->insert_id;

                if (!empty($rooms_data)) {
                    $rStmt = $conn->prepare("INSERT INTO quote_rooms (quote_id, room_name, length_ft, width_ft, area_sqft, notes) VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($rooms_data as $rm) {
                        $rStmt->bind_param("isddds", $quote_id, $rm['name'], $rm['length'], $rm['width'], $rm['area'], $rm['notes']);
                        $rStmt->execute();
                    }
                }

                if (!empty($calculated_services)) {
                    $sStmt = $conn->prepare("INSERT INTO quote_services (
                        quote_id, service_id, service_title, service_type, unit_rate, quantity, area_sqft,
                        material_multiplier, wastage_percent, wastage_amount, min_charge, base_amount, final_service_amount, service_rate
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    foreach ($calculated_services as $cs) {
                        $sStmt->bind_param(
                            "iissdddddddddd",
                            $quote_id, $cs['service_id'], $cs['service_title'], $cs['service_type'],
                            $cs['unit_rate'], $cs['quantity'], $cs['area_sqft'], $cs['material_multiplier'],
                            $cs['wastage_percent'], $cs['wastage_amount'], $cs['min_charge'], $cs['base_amount'],
                            $cs['final_service_amount'], $cs['final_service_amount']
                        );
                        $sStmt->execute();
                    }
                }

                recordQuoteStatusHistory($conn, $quote_id, null, 'new', 'user', $customer_name, 'Quote request submitted via online portal.');

                $success_quote = [
                    'id' => $quote_id,
                    'quote_number' => $quote_number,
                    'customer_name' => $customer_name,
                    'email' => $email,
                    'project_type' => $project_type,
                    'measurement_status' => $measurement_status,
                    'approx_area' => $approx_area,
                    'package_type' => $package_type,
                    'budget_range' => $budget_range,
                    'estimated_total' => $estimated_total,
                    'rooms_count' => count($rooms_data),
                    'services_count' => count($calculated_services)
                ];
            } else {
                $error = "Failed to submit quote request. Database error: " . $conn->error;
            }
        }
    }
}

$submission_token = generateQuoteSubmissionToken();

include 'includes/header.php';
?>

    <!-- Editorial Hero Header -->
    <section class="spatial-hero-section" style="min-height: 48vh; padding-top: 8rem; padding-bottom: 3.5rem; background: radial-gradient(circle at 50% 20%, #eff6ff 0%, #ffffff 85%);">
        <div class="spatial-hero-scrim"></div>
        <div class="spatial-hero-content" style="padding: 1rem;">
            <div class="spatial-hero-pill">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
                <span>6-Stage Architectural Estimator</span>
            </div>
            <h1 class="spatial-hero-title" style="font-size: clamp(2.4rem, 5vw, 4rem); margin-bottom: 1rem; color: #0f172a;">
                Custom Spatial Estimation
            </h1>
            <p class="spatial-hero-subtitle" style="margin-bottom: 0; color: #475569;">
                Configure your project type, floor dimensions, bespoke finishes, and receive an algorithmic itemized quote.
            </p>
        </div>
    </section>

    <!-- Main Quotation Section -->
    <section class="spatial-section" style="padding: 4rem 0 7rem;">
        <div class="container">

            <?php if (!empty($success_quote)): ?>
                <!-- Success State Card with 3D Celebration Visual -->
                <div class="spatial-wizard-wrapper" style="text-align: center; max-width: 780px;">
                    <div style="width: 72px; height: 72px; margin: 0 auto 1.5rem; border-radius: 50%; background: rgba(16, 185, 129, 0.15); border: 2px solid #10b981; display: flex; align-items: center; justify-content: center; color: #10b981; box-shadow: 0 0 25px rgba(16, 185, 129, 0.3);">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>

                    <span class="spatial-section-eyebrow" style="color: #10b981;">Quotation Docket Generated</span>
                    <h2 style="font-size: 2.25rem; color: #0f172a; margin-bottom: 1rem; font-weight: 800;">
                        Quotation Proposal Confirmed
                    </h2>
                    <p style="color: #64748b; font-size: 1.05rem; max-width: 600px; margin: 0 auto 2rem; line-height: 1.7;">
                        Thank you, <strong style="color: #0f172a;"><?php echo htmlspecialchars($success_quote['customer_name']); ?></strong>. Your quotation docket 
                        <strong style="color: #1d4ed8; font-family: monospace; font-size: 1.2rem; letter-spacing: 0.05em;"><?php echo htmlspecialchars($success_quote['quote_number']); ?></strong> 
                        has been calculated authoritatively by our architectural engine.
                    </p>

                    <!-- Summary Capsule -->
                    <div style="background: #f8fafc; border: 1px solid #bfdbfe; border-radius: var(--radius-xl); padding: 2rem; max-width: 580px; margin: 0 auto 2.5rem; text-align: left; box-shadow: 0 4px 16px rgba(37, 99, 235, 0.05);">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; font-size: 0.9rem; color: #64748b;">
                            <div><strong style="color:#334155;">Project Scope:</strong> <span style="color:#0f172a; font-weight:600;"><?php echo htmlspecialchars($success_quote['project_type']); ?></span></div>
                            <div><strong style="color:#334155;">Property Typology:</strong> <span style="color:#0f172a; font-weight:600;"><?php echo htmlspecialchars($property_type ?? 'Villa'); ?></span></div>
                            <div><strong style="color:#334155;">Carpet Area:</strong> <span style="color:#0f172a; font-weight:600;"><?php echo number_format($success_quote['approx_area']); ?> sq.ft</span></div>
                            <div><strong style="color:#334155;">Package Tier:</strong> <span style="color:#0f172a; font-weight:600;"><?php echo htmlspecialchars($success_quote['package_type']); ?></span></div>
                            <div><strong style="color:#334155;">Itemized Services:</strong> <span style="color:#0f172a; font-weight:600;"><?php echo $success_quote['services_count']; ?> services</span></div>
                            <div><strong style="color:#334155;">Budget Window:</strong> <span style="color:#0f172a; font-weight:600;"><?php echo htmlspecialchars($success_quote['budget_range']); ?></span></div>
                        </div>

                        <?php if ($success_quote['estimated_total'] > 0): ?>
                            <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: baseline;">
                                <span style="font-size: 0.85rem; text-transform: uppercase; color: #64748b; font-weight: 600;">Estimated Turnkey Total:</span>
                                <span style="font-family: var(--font-heading); font-size: 1.85rem; font-weight: 800; color: #1d4ed8;">
                                    <?php echo formatIndianCurrency($success_quote['estimated_total']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="quote_details.php?quote_no=<?php echo urlencode($success_quote['quote_number']); ?>" class="btn-spatial-bronze">
                            <span>View Full Proposal Docket &rarr;</span>
                        </a>
                        <a href="profile.php" class="btn-spatial-outline">
                            <span>Client Account Dashboard</span>
                        </a>
                    </div>
                </div>

            <?php else: ?>

                <!-- Error Toast if any -->
                <?php if (!empty($error)): ?>
                    <div style="max-width: 960px; margin: 0 auto 2rem; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; color: #fca5a5; display: flex; align-items: center; gap: 0.75rem;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Multi-Step Wizard Form -->
                <div class="spatial-wizard-wrapper">
                    
                    <!-- Stepper Bar (6 Steps) -->
                    <div class="spatial-wizard-stepper">
                        <div class="spatial-wizard-step-node active" data-step="1">
                            <div class="spatial-wizard-step-circle">01</div>
                            <span class="spatial-wizard-step-label">Details</span>
                        </div>
                        <div class="spatial-wizard-step-node" data-step="2">
                            <div class="spatial-wizard-step-circle">02</div>
                            <span class="spatial-wizard-step-label">Typology</span>
                        </div>
                        <div class="spatial-wizard-step-node" data-step="3">
                            <div class="spatial-wizard-step-circle">03</div>
                            <span class="spatial-wizard-step-label">Space &amp; Area</span>
                        </div>
                        <div class="spatial-wizard-step-node" data-step="4">
                            <div class="spatial-wizard-step-circle">04</div>
                            <span class="spatial-wizard-step-label">Aesthetic</span>
                        </div>
                        <div class="spatial-wizard-step-node" data-step="5">
                            <div class="spatial-wizard-step-circle">05</div>
                            <span class="spatial-wizard-step-label">Services</span>
                        </div>
                        <div class="spatial-wizard-step-node" data-step="6">
                            <div class="spatial-wizard-step-circle">06</div>
                            <span class="spatial-wizard-step-label">Review</span>
                        </div>
                    </div>

                    <form id="spatialQuoteForm" method="POST" enctype="multipart/form-data">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="submission_token" value="<?php echo htmlspecialchars($submission_token); ?>">

                        <!-- STEP 1: Your Details -->
                        <div class="spatial-wizard-pane active" id="wizard-pane-1">
                            <span class="spatial-section-eyebrow" style="color: #2563eb; font-weight: 700; text-transform: uppercase; font-size: 0.78rem; letter-spacing: 0.12em; display: inline-block; margin-bottom: 0.35rem;">Stage 01</span>
                            <h3 style="font-size: 1.75rem; color: #0f172a; margin-bottom: 0.5rem; font-weight: 800;">Client &amp; Location Profile</h3>
                            <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 2rem;">
                                Please confirm your primary contact coordinates for the architectural dossier.
                            </p>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                <div class="spatial-form-group">
                                    <label class="spatial-form-label" for="customer_name">Full Name <span class="req">*</span></label>
                                    <input type="text" id="customer_name" name="customer_name" class="spatial-form-control" required value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ($current_user['name'] ?? '')); ?>" placeholder="e.g. Rajesh Patel">
                                </div>
                                <div class="spatial-form-group">
                                    <label class="spatial-form-label" for="email">Email Address <span class="req">*</span></label>
                                    <input type="email" id="email" name="email" class="spatial-form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ($current_user['email'] ?? '')); ?>" placeholder="rajesh@domain.com">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                <div class="spatial-form-group">
                                    <label class="spatial-form-label" for="phone">Primary Mobile Number <span class="req">*</span></label>
                                    <input type="tel" id="phone" name="phone" class="spatial-form-control" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ($current_user['phone'] ?? '')); ?>" placeholder="98250 12345">
                                </div>
                                <div class="spatial-form-group">
                                    <label class="spatial-form-label" for="alternate_phone">Alternate Contact</label>
                                    <input type="tel" id="alternate_phone" name="alternate_phone" class="spatial-form-control" value="<?php echo htmlspecialchars($_POST['alternate_phone'] ?? ''); ?>" placeholder="Optional alternate">
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1.25rem;">
                                <div class="spatial-form-group">
                                    <label class="spatial-form-label" for="city">Project City / District <span class="req">*</span></label>
                                    <input type="text" id="city" name="city" class="spatial-form-control" required value="<?php echo htmlspecialchars($_POST['city'] ?? 'Surat'); ?>" placeholder="Surat">
                                </div>
                                <div class="spatial-form-group">
                                    <label class="spatial-form-label" for="state">State</label>
                                    <input type="text" id="state" name="state" class="spatial-form-control" value="<?php echo htmlspecialchars($_POST['state'] ?? 'Gujarat'); ?>">
                                </div>
                                <div class="spatial-form-group">
                                    <label class="spatial-form-label" for="pincode">Pincode</label>
                                    <input type="text" id="pincode" name="pincode" class="spatial-form-control" value="<?php echo htmlspecialchars($_POST['pincode'] ?? '395004'); ?>" placeholder="395004">
                                </div>
                            </div>

                            <div class="spatial-wizard-nav">
                                <div></div>
                                <button type="button" class="btn-spatial-bronze wizard-next-btn">
                                    <span>Continue to Project Typology &rarr;</span>
                                </button>
                            </div>
                        </div>

                        <!-- STEP 2: Project Type -->
                        <div class="spatial-wizard-pane" id="wizard-pane-2">
                            <span class="spatial-section-eyebrow" style="color: #2563eb; font-weight: 700; text-transform: uppercase; font-size: 0.78rem; letter-spacing: 0.12em; display: inline-block; margin-bottom: 0.35rem;">Stage 02</span>
                            <h3 style="font-size: 1.75rem; color: #0f172a; margin-bottom: 0.5rem; font-weight: 800;">Project Typology</h3>
                            <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 2rem;">
                                Select the architectural category and structural property layout.
                            </p>

                            <div class="spatial-choice-grid" style="margin-bottom: 2rem;">
                                <div class="spatial-choice-card selected" data-choice-group="proj_type">
                                    <input type="radio" name="project_type" value="Residential" checked style="display:none;">
                                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🏠</div>
                                    <h4 style="color:#0f172a; margin-bottom:0.25rem; font-weight: 700;">Residential Estate</h4>
                                    <p style="font-size:0.8rem; color:#64748b;">Private villas, bungalows &amp; apartments</p>
                                </div>
                                <div class="spatial-choice-card" data-choice-group="proj_type">
                                    <input type="radio" name="project_type" value="Commercial" style="display:none;">
                                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">🏢</div>
                                    <h4 style="color:#0f172a; margin-bottom:0.25rem; font-weight: 700;">Commercial / Retail</h4>
                                    <p style="font-size:0.8rem; color:#64748b;">Boutiques, cafes &amp; hospitality</p>
                                </div>
                                <div class="spatial-choice-card" data-choice-group="proj_type">
                                    <input type="radio" name="project_type" value="Office" style="display:none;">
                                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">💼</div>
                                    <h4 style="color:#0f172a; margin-bottom:0.25rem; font-weight: 700;">Executive Office</h4>
                                    <p style="font-size:0.8rem; color:#64748b;">Corporate suites &amp; co-working hubs</p>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                <div class="spatial-form-group">
                                    <label class="spatial-form-label" for="property_type">Specific Property Layout</label>
                                    <select id="property_type" name="property_type" class="spatial-form-control">
                                        <option value="Villa / Independent House">Villa / Independent House</option>
                                        <option value="3BHK / 4BHK Apartment">3BHK / 4BHK Luxury Apartment</option>
                                        <option value="Penthouse Sanctuary">Penthouse Sanctuary</option>
                                        <option value="Corporate Office Floor">Corporate Office Floor</option>
                                        <option value="Retail Showroom">Retail Showroom</option>
                                    </select>
                                </div>
                                <div class="spatial-form-group">
                                    <label class="spatial-form-label" for="floors_count">Number of Floors</label>
                                    <select id="floors_count" name="floors_count" class="spatial-form-control">
                                        <option value="1">1 Floor (Single Level)</option>
                                        <option value="2">2 Floors (Duplex)</option>
                                        <option value="3">3+ Floors (Multi-Level Estate)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="spatial-wizard-nav">
                                <button type="button" class="btn-spatial-outline wizard-prev-btn">&larr; Back</button>
                                <button type="button" class="btn-spatial-bronze wizard-next-btn">
                                    <span>Continue to Space &amp; Area &rarr;</span>
                                </button>
                            </div>
                        </div>

                        <!-- STEP 3: Space Information & Room Calculator -->
                        <div class="spatial-wizard-pane" id="wizard-pane-3">
                            <span class="spatial-section-eyebrow" style="color: #2563eb; font-weight: 700; text-transform: uppercase; font-size: 0.78rem; letter-spacing: 0.12em; display: inline-block; margin-bottom: 0.35rem;">Stage 03</span>
                            <h3 style="font-size: 1.75rem; color: #0f172a; margin-bottom: 0.5rem; font-weight: 800;">Space Information &amp; Room Dimensions</h3>
                            <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 2rem;">
                                Specify total carpet area or use our dimension calculator (Length &times; Width).
                            </p>

                            <!-- Modern Executive Blue & White Room Dimension Calculator Box -->
                            <div style="background: #f8fafc; border: 1px solid #bfdbfe; border-radius: var(--radius-xl); padding: 1.75rem; margin-bottom: 2rem; box-shadow: 0 4px 16px rgba(37, 99, 235, 0.05);">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
                                    <div style="display: flex; align-items: center; gap: 0.65rem;">
                                        <div style="width: 34px; height: 34px; border-radius: 8px; background: #eff6ff; border: 1px solid #bfdbfe; display: flex; align-items: center; justify-content: center; color: #1d4ed8;">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                                        </div>
                                        <div>
                                            <h4 style="color: #0f172a; margin: 0; font-size: 1.15rem; font-weight: 700;">Room Dimension Calculator</h4>
                                            <span style="font-size: 0.75rem; color: #64748b;">Live area estimation tool</span>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                        <div style="font-size: 0.9rem; color: #1d4ed8; font-weight: 700; background: #dbeafe; padding: 0.4rem 1rem; border-radius: 9999px; border: 1px solid #bfdbfe; display: inline-flex; align-items: center; gap: 0.35rem;" id="calc_area_display">
                                            300.00 sq.ft
                                        </div>
                                        <button type="button" id="apply_room_area_btn" style="background: #2563eb; color: #ffffff; border: none; border-radius: 9999px; padding: 0.4rem 0.85rem; font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.25rem;" title="Copy to Total Carpet Area">
                                            <span>Apply to Area &darr;</span>
                                        </button>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1.25rem;">
                                    <div class="spatial-form-group" style="margin-bottom:0;">
                                        <label class="spatial-form-label" style="color: #334155; font-weight: 600;">Room Identifier</label>
                                        <input type="text" name="rooms[0][name]" class="spatial-form-control" value="Living Room" placeholder="e.g. Master Suite" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                                    </div>
                                    <div class="spatial-form-group" style="margin-bottom:0;">
                                        <label class="spatial-form-label" style="color: #334155; font-weight: 600;">Length (ft)</label>
                                        <input type="number" step="0.1" id="room_length" name="rooms[0][length]" class="spatial-form-control" value="20" placeholder="20" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                                    </div>
                                    <div class="spatial-form-group" style="margin-bottom:0;">
                                        <label class="spatial-form-label" style="color: #334155; font-weight: 600;">Width (ft)</label>
                                        <input type="number" step="0.1" id="room_width" name="rooms[0][width]" class="spatial-form-control" value="15" placeholder="15" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                                    </div>
                                </div>
                            </div>

                            <div class="spatial-form-group">
                                <label class="spatial-form-label" for="approx_area">Total Approximate Carpet Area (sq.ft) <span class="req">*</span></label>
                                <input type="number" id="approx_area" name="approx_area" class="spatial-form-control" required value="<?php echo htmlspecialchars($_POST['approx_area'] ?? '1850'); ?>" placeholder="1850">
                                <span style="font-size: 0.75rem; color: var(--color-stone);">If exact dimensions are unknown, enter estimated total floor area.</span>
                            </div>

                            <div class="spatial-wizard-nav">
                                <button type="button" class="btn-spatial-outline wizard-prev-btn">&larr; Back</button>
                                <button type="button" class="btn-spatial-bronze wizard-next-btn">
                                    <span>Continue to Aesthetic &rarr;</span>
                                </button>
                            </div>
                        </div>

                        <!-- STEP 4: Budget & Requirements -->
                        <div class="spatial-wizard-pane" id="wizard-pane-4">
                            <span class="spatial-section-eyebrow" style="color: #2563eb; font-weight: 700; text-transform: uppercase; font-size: 0.78rem; letter-spacing: 0.12em; display: inline-block; margin-bottom: 0.35rem;">Stage 04</span>
                            <h3 style="font-size: 1.75rem; color: #0f172a; margin-bottom: 0.5rem; font-weight: 800;">Material Tier &amp; Aesthetic Preferences</h3>
                            <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 2rem;">
                                Select design tier and budget range to calibrate material multipliers.
                            </p>

                            <div class="spatial-choice-grid" style="margin-bottom: 2rem;">
                                <div class="spatial-choice-card selected" data-choice-group="pkg_type">
                                    <input type="radio" name="package_type" value="Standard" checked style="display:none;">
                                    <h4 style="color:#0f172a; margin-bottom:0.25rem; font-weight: 700;">Standard Luxury</h4>
                                    <p style="font-size:0.8rem; color:#64748b;">High-grade laminates, premium hardware &amp; ambient LED layers</p>
                                </div>
                                <div class="spatial-choice-card" data-choice-group="pkg_type">
                                    <input type="radio" name="package_type" value="Premium" style="display:none;">
                                    <h4 style="color:#0f172a; margin-bottom:0.25rem; font-weight: 700;">Signature Premium</h4>
                                    <p style="font-size:0.8rem; color:#64748b;">Natural veneers, German Blum fittings &amp; fluted acoustic panels</p>
                                </div>
                                <div class="spatial-choice-card" data-choice-group="pkg_type">
                                    <input type="radio" name="package_type" value="Luxury" style="display:none;">
                                    <h4 style="color:#0f172a; margin-bottom:0.25rem; font-weight: 700;">Ultra Palatial</h4>
                                    <p style="font-size:0.8rem; color:#64748b;">Italian marble, solid CP teakwood &amp; home automation integration</p>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                                <div class="spatial-form-group">
                                    <label class="spatial-form-label" for="design_style">Architectural Style</label>
                                    <select id="design_style" name="design_style" class="spatial-form-control">
                                        <option value="Modern Luxury">Modern Luxury</option>
                                        <option value="Minimalist Contemporary">Minimalist Contemporary</option>
                                        <option value="Scandinavian Warmth">Scandinavian Warmth</option>
                                        <option value="Industrial Architectural">Industrial Architectural</option>
                                        <option value="Classic Heritage Fusion">Classic Heritage Fusion</option>
                                    </select>
                                </div>
                                <div class="spatial-form-group">
                                    <label class="spatial-form-label" for="budget_range">Budget Window <span class="req">*</span></label>
                                    <select id="budget_range" name="budget_range" class="spatial-form-control" required>
                                        <option value="₹10L – ₹25L">₹10 Lakhs – ₹25 Lakhs</option>
                                        <option value="₹25L – ₹50L" selected>₹25 Lakhs – ₹50 Lakhs</option>
                                        <option value="₹50L – ₹1Cr">₹50 Lakhs – ₹1 Crore</option>
                                        <option value="₹1Cr+">₹1 Crore+ (Bespoke Unlimited)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="spatial-wizard-nav">
                                <button type="button" class="btn-spatial-outline wizard-prev-btn">&larr; Back</button>
                                <button type="button" class="btn-spatial-bronze wizard-next-btn">
                                    <span>Continue to Capabilities &rarr;</span>
                                </button>
                            </div>
                        </div>

                        <!-- STEP 5: Service Selection -->
                        <div class="spatial-wizard-pane" id="wizard-pane-5">
                            <span class="spatial-section-eyebrow" style="color: #2563eb; font-weight: 700; text-transform: uppercase; font-size: 0.78rem; letter-spacing: 0.12em; display: inline-block; margin-bottom: 0.35rem;">Stage 05</span>
                            <h3 style="font-size: 1.75rem; color: #0f172a; margin-bottom: 0.5rem; font-weight: 800;">Capability Scope Selection</h3>
                            <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 2rem;">
                                Check the design and execution modules required for your commission.
                            </p>

                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 2rem;">
                                <?php foreach ($active_services as $srvItem): ?>
                                    <label style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: var(--radius-md); padding: 1.15rem; display: flex; align-items: flex-start; gap: 0.85rem; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#2563eb'; this.style.background='#eff6ff';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#f8fafc';">
                                        <input type="checkbox" name="selected_services[]" value="<?php echo $srvItem['id']; ?>" style="margin-top: 0.25rem; accent-color: #2563eb;" checked>
                                        <div>
                                            <div style="font-weight: 700; color: #0f172a; font-size: 0.95rem;"><?php echo htmlspecialchars($srvItem['title']); ?></div>
                                            <div style="font-size: 0.78rem; color: #64748b;"><?php echo htmlspecialchars($srvItem['category']); ?> &bull; <?php echo htmlspecialchars($srvItem['price_range'] ?? 'Standard'); ?></div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="spatial-form-group">
                                <label class="spatial-form-label" for="special_requirements">Special Brief / Custom Notes</label>
                                <textarea id="special_requirements" name="special_requirements" rows="3" class="spatial-form-control" placeholder="Specify any unique joinery requirements, soundproofing, smart home requests, or architectural priorities..."></textarea>
                            </div>

                            <div class="spatial-wizard-nav">
                                <button type="button" class="btn-spatial-outline wizard-prev-btn">&larr; Back</button>
                                <button type="button" class="btn-spatial-bronze wizard-next-btn">
                                    <span>Review Proposal &rarr;</span>
                                </button>
                            </div>
                        </div>

                        <!-- STEP 6: Review & Submit -->
                        <div class="spatial-wizard-pane" id="wizard-pane-6">
                            <span class="spatial-section-eyebrow" style="color: #2563eb; font-weight: 700; text-transform: uppercase; font-size: 0.78rem; letter-spacing: 0.12em; display: inline-block; margin-bottom: 0.35rem;">Stage 06</span>
                            <h3 style="font-size: 1.75rem; color: #0f172a; margin-bottom: 0.5rem; font-weight: 800;">Review &amp; Authoritative Synthesis</h3>
                            <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 2rem;">
                                Confirm your project specifications and submit for executive scheduling.
                            </p>

                            <div style="background: #f8fafc; border: 1px solid #bfdbfe; border-radius: var(--radius-xl); padding: 2.25rem; margin-bottom: 2rem; box-shadow: 0 4px 16px rgba(37,99,235,0.05);">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; font-size: 0.95rem;">
                                    <div><span style="color:#64748b;">Client Name:</span> <strong style="color:#0f172a;" id="rev_name">--</strong></div>
                                    <div><span style="color:#64748b;">Email:</span> <strong style="color:#0f172a;" id="rev_email">--</strong></div>
                                    <div><span style="color:#64748b;">Project Scope:</span> <strong style="color:#0f172a;" id="rev_type">--</strong></div>
                                    <div><span style="color:#64748b;">Specified Area:</span> <strong style="color:#0f172a;" id="rev_area">--</strong></div>
                                </div>
                            </div>

                            <div class="spatial-form-group">
                                <label class="spatial-form-label" for="attachment">Attach Architectural Drawings / Floorplans (Optional, PDF or Images up to 10MB)</label>
                                <input type="file" id="attachment" name="attachment" class="spatial-form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            </div>

                            <div class="spatial-wizard-nav">
                                <button type="button" class="btn-spatial-outline wizard-prev-btn">&larr; Back</button>
                                <button type="submit" class="btn-spatial-bronze" style="padding: 0.85rem 2.25rem; font-size: 0.95rem;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    <span>Submit Architectural Proposal</span>
                                </button>
                            </div>
                        </div>

                    </form>
                </div>

            <?php endif; ?>

        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const wizard = document.querySelector('.spatial-wizard-wrapper');
        if (!wizard) return;

        // Hook review updates
        const nextToReviewBtn = document.querySelectorAll('.wizard-next-btn');
        nextToReviewBtn.forEach(btn => {
            btn.addEventListener('click', function() {
                const nameInp = document.getElementById('customer_name');
                const emailInp = document.getElementById('email');
                const areaInp = document.getElementById('approx_area');
                const projTypeInp = document.querySelector('input[name="project_type"]:checked');

                if (nameInp && document.getElementById('rev_name')) {
                    document.getElementById('rev_name').textContent = nameInp.value || 'Client';
                }
                if (emailInp && document.getElementById('rev_email')) {
                    document.getElementById('rev_email').textContent = emailInp.value || 'Email';
                }
                if (areaInp && document.getElementById('rev_area')) {
                    document.getElementById('rev_area').textContent = (areaInp.value || '0') + ' sq.ft';
                }
                if (projTypeInp && document.getElementById('rev_type')) {
                    document.getElementById('rev_type').textContent = projTypeInp.value;
                }
            });
        });
    });
    </script>

<?php include 'includes/footer.php'; ?>
