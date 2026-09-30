<?php
/**
 * Database Migration Script for Dynamic Get Quote System
 * Creative Touch Interiors
 */
require_once __DIR__ . '/config.php';

function runQuoteDbMigration($conn) {
    $log = [];

    // 1. Create upload directory for quote attachments if not exists
    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'quotes';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
        // Create an .htaccess to prevent script execution inside uploads
        @file_put_contents($uploadDir . DIRECTORY_SEPARATOR . '.htaccess', "php_flag engine off\nOptions -ExecCGI\nAddHandler cgi-script .php .phtml .php3 .php4 .php5 .php7 .php8 .phps\n");
    }

    // 2. Enhance services table with starting_price, status, image
    $checkCols = $conn->query("SHOW COLUMNS FROM services LIKE 'starting_price'");
    if ($checkCols && $checkCols->num_rows == 0) {
        $conn->query("ALTER TABLE services ADD COLUMN starting_price DECIMAL(10,2) DEFAULT 0.00 AFTER price_range");
        $log[] = "Added 'starting_price' column to 'services' table.";
    }

    $checkCols = $conn->query("SHOW COLUMNS FROM services LIKE 'status'");
    if ($checkCols && $checkCols->num_rows == 0) {
        $conn->query("ALTER TABLE services ADD COLUMN status ENUM('active','inactive') DEFAULT 'active' AFTER featured");
        $log[] = "Added 'status' column to 'services' table.";
    }

    $checkCols = $conn->query("SHOW COLUMNS FROM services LIKE 'image'");
    if ($checkCols && $checkCols->num_rows == 0) {
        $conn->query("ALTER TABLE services ADD COLUMN image VARCHAR(255) NULL AFTER icon");
        $log[] = "Added 'image' column to 'services' table.";
    }

    $checkCols = $conn->query("SHOW COLUMNS FROM services LIKE 'service_type'");
    if ($checkCols && $checkCols->num_rows == 0) {
        $conn->query("ALTER TABLE services ADD COLUMN service_type ENUM('area_based','quantity_based','fixed_price') DEFAULT 'area_based' AFTER category");
        $log[] = "Added 'service_type' column to 'services' table.";
    }

    $checkCols = $conn->query("SHOW COLUMNS FROM services LIKE 'unit_rate'");
    if ($checkCols && $checkCols->num_rows == 0) {
        $conn->query("ALTER TABLE services ADD COLUMN unit_rate DECIMAL(10,2) DEFAULT 0.00 AFTER starting_price");
        $log[] = "Added 'unit_rate' column to 'services' table.";
    }

    $checkCols = $conn->query("SHOW COLUMNS FROM services LIKE 'min_charge'");
    if ($checkCols && $checkCols->num_rows == 0) {
        $conn->query("ALTER TABLE services ADD COLUMN min_charge DECIMAL(10,2) DEFAULT 0.00 AFTER unit_rate");
        $log[] = "Added 'min_charge' column to 'services' table.";
    }

    $checkCols = $conn->query("SHOW COLUMNS FROM services LIKE 'default_wastage_percent'");
    if ($checkCols && $checkCols->num_rows == 0) {
        $conn->query("ALTER TABLE services ADD COLUMN default_wastage_percent DECIMAL(5,2) DEFAULT 0.00 AFTER min_charge");
        $log[] = "Added 'default_wastage_percent' column to 'services' table.";
    }

    // Update default starting prices on existing services if they are 0
    $conn->query("UPDATE services SET starting_price = 800000.00 WHERE slug LIKE '%complete-home%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 120000.00 WHERE slug LIKE '%living-room%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 140000.00 WHERE slug LIKE '%bedroom%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 200000.00 WHERE slug LIKE '%kitchen%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 90000.00 WHERE slug LIKE '%bathroom%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 1800000.00 WHERE slug LIKE '%luxury-villa%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 400000.00 WHERE slug LIKE '%apartment%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 100000.00 WHERE slug LIKE '%kids-room%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 600000.00 WHERE slug LIKE '%executive-office%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 700000.00 WHERE slug LIKE '%open-workspace%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 200000.00 WHERE slug LIKE '%conference-room%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 180000.00 WHERE slug LIKE '%reception%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 600000.00 WHERE slug LIKE '%retail-store%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 450000.00 WHERE slug LIKE '%fashion-boutique%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 700000.00 WHERE slug LIKE '%electronics-showroom%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 1000000.00 WHERE slug LIKE '%jewellery-store%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 500000.00 WHERE slug LIKE '%caf%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 800000.00 WHERE slug LIKE '%restaurant%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 400000.00 WHERE slug LIKE '%salon%' AND (starting_price IS NULL OR starting_price = 0)");
    $conn->query("UPDATE services SET starting_price = 25000.00 WHERE slug LIKE '%3d%' AND (starting_price IS NULL OR starting_price = 0)");

    // 3. Create quote_requests table
    $sqlQuoteRequests = "CREATE TABLE IF NOT EXISTS `quote_requests` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `quote_number` VARCHAR(30) NOT NULL UNIQUE,
        `user_id` INT(11) NULL,
        `customer_name` VARCHAR(150) NOT NULL,
        `email` VARCHAR(150) NOT NULL,
        `phone` VARCHAR(30) NOT NULL,
        `alternate_phone` VARCHAR(30) NULL,
        `address` TEXT NULL,
        `city` VARCHAR(100) NOT NULL,
        `state` VARCHAR(100) NOT NULL,
        `pincode` VARCHAR(20) NOT NULL,
        `project_type` VARCHAR(100) NOT NULL,
        `property_type` VARCHAR(100) NOT NULL,
        `project_location` VARCHAR(255) NULL,
        `approx_area` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `rooms_count` INT(11) NOT NULL DEFAULT 1,
        `floors_count` INT(11) NOT NULL DEFAULT 1,
        `expected_start_date` DATE NULL,
        `expected_completion_date` DATE NULL,
        `design_style` VARCHAR(100) NULL,
        `preferred_theme` VARCHAR(150) NULL,
        `budget_range` VARCHAR(100) NOT NULL,
        `material_preference` VARCHAR(150) NULL,
        `special_requirements` TEXT NULL,
        `additional_notes` TEXT NULL,
        `attachment_path` VARCHAR(255) NULL,
        `attachment_name` VARCHAR(255) NULL,
        `status` ENUM('new','under_review','contacted','site_visit_scheduled','estimation_prepared','quote_sent','approved','rejected','project_started') NOT NULL DEFAULT 'new',
        `base_rate_per_sqft` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `base_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `service_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `material_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `additional_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `tax_percentage` DECIMAL(5,2) NOT NULL DEFAULT 18.00,
        `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `estimated_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `estimation_notes` TEXT NULL,
        `site_visit_date` DATE NULL,
        `site_visit_time` TIME NULL,
        `site_visit_notes` TEXT NULL,
        `admin_notes` TEXT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `status` (`status`),
        KEY `created_at` (`created_at`),
        CONSTRAINT `fk_quote_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    if ($conn->query($sqlQuoteRequests)) {
        $log[] = "Table 'quote_requests' is verified/created.";
    } else {
        $log[] = "Error with 'quote_requests': " . $conn->error;
    }

    // Enhance quote_requests with measurement_status, package_type, package_multiplier, discount_type, discount_value, submission_token
    $qrCols = [
        "measurement_status" => "ALTER TABLE quote_requests ADD COLUMN measurement_status ENUM('known','unknown') NOT NULL DEFAULT 'known' AFTER approx_area",
        "package_type" => "ALTER TABLE quote_requests ADD COLUMN package_type VARCHAR(50) NOT NULL DEFAULT 'Standard' AFTER budget_range",
        "package_multiplier" => "ALTER TABLE quote_requests ADD COLUMN package_multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00 AFTER package_type",
        "material_multiplier" => "ALTER TABLE quote_requests ADD COLUMN material_multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00 AFTER material_preference",
        "discount_type" => "ALTER TABLE quote_requests ADD COLUMN discount_type ENUM('none','flat','percentage') NOT NULL DEFAULT 'none' AFTER additional_cost",
        "discount_value" => "ALTER TABLE quote_requests ADD COLUMN discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_type",
        "submission_token" => "ALTER TABLE quote_requests ADD COLUMN submission_token VARCHAR(64) NULL AFTER status"
    ];
    foreach ($qrCols as $colName => $alterSql) {
        $chk = $conn->query("SHOW COLUMNS FROM quote_requests LIKE '$colName'");
        if ($chk && $chk->num_rows == 0) {
            $conn->query($alterSql);
            $log[] = "Added '$colName' column to 'quote_requests'.";
        }
    }

    // 4. Create quote_rooms table for individual room calculations
    $sqlQuoteRooms = "CREATE TABLE IF NOT EXISTS `quote_rooms` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `quote_id` INT(11) NOT NULL,
        `room_name` VARCHAR(100) NOT NULL,
        `length_ft` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `width_ft` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `area_sqft` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `notes` VARCHAR(255) NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `quote_id` (`quote_id`),
        CONSTRAINT `fk_qroom_quote` FOREIGN KEY (`quote_id`) REFERENCES `quote_requests` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    if ($conn->query($sqlQuoteRooms)) {
        $log[] = "Table 'quote_rooms' is verified/created.";
    } else {
        $log[] = "Error with 'quote_rooms': " . $conn->error;
    }

    // 5. Create quote_services relational table
    $sqlQuoteServices = "CREATE TABLE IF NOT EXISTS `quote_services` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `quote_id` INT(11) NOT NULL,
        `room_id` INT(11) NULL,
        `room_name` VARCHAR(100) NULL,
        `service_id` INT(11) NOT NULL,
        `service_title` VARCHAR(255) NOT NULL,
        `service_type` ENUM('area_based','quantity_based','fixed_price') NOT NULL DEFAULT 'fixed_price',
        `unit_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
        `area_sqft` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `material_multiplier` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
        `wastage_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        `wastage_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `min_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `base_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `final_service_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `service_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `quote_id` (`quote_id`),
        KEY `service_id` (`service_id`),
        CONSTRAINT `fk_qs_quote` FOREIGN KEY (`quote_id`) REFERENCES `quote_requests` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_qs_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    if ($conn->query($sqlQuoteServices)) {
        $log[] = "Table 'quote_services' is verified/created.";
    } else {
        $log[] = "Error with 'quote_services': " . $conn->error;
    }

    // Enhance existing quote_services if table already existed without new columns
    $qsCols = [
        "room_id" => "ALTER TABLE quote_services ADD COLUMN room_id INT(11) NULL AFTER quote_id",
        "room_name" => "ALTER TABLE quote_services ADD COLUMN room_name VARCHAR(100) NULL AFTER room_id",
        "service_type" => "ALTER TABLE quote_services ADD COLUMN service_type ENUM('area_based','quantity_based','fixed_price') NOT NULL DEFAULT 'fixed_price' AFTER service_title",
        "unit_rate" => "ALTER TABLE quote_services ADD COLUMN unit_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER service_type",
        "quantity" => "ALTER TABLE quote_services ADD COLUMN quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00 AFTER unit_rate",
        "area_sqft" => "ALTER TABLE quote_services ADD COLUMN area_sqft DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER quantity",
        "material_multiplier" => "ALTER TABLE quote_services ADD COLUMN material_multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00 AFTER area_sqft",
        "wastage_percent" => "ALTER TABLE quote_services ADD COLUMN wastage_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER material_multiplier",
        "wastage_amount" => "ALTER TABLE quote_services ADD COLUMN wastage_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER wastage_percent",
        "min_charge" => "ALTER TABLE quote_services ADD COLUMN min_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER wastage_amount",
        "base_amount" => "ALTER TABLE quote_services ADD COLUMN base_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER min_charge",
        "final_service_amount" => "ALTER TABLE quote_services ADD COLUMN final_service_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER base_amount"
    ];
    foreach ($qsCols as $colName => $alterSql) {
        $chk = $conn->query("SHOW COLUMNS FROM quote_services LIKE '$colName'");
        if ($chk && $chk->num_rows == 0) {
            $conn->query($alterSql);
            $log[] = "Added '$colName' column to 'quote_services'.";
        }
    }

    // 5. Create quote_status_history audit table
    $sqlStatusHistory = "CREATE TABLE IF NOT EXISTS `quote_status_history` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `quote_id` INT(11) NOT NULL,
        `previous_status` VARCHAR(50) NULL,
        `new_status` VARCHAR(50) NOT NULL,
        `changed_by_type` ENUM('admin','user','system') NOT NULL DEFAULT 'admin',
        `changed_by_name` VARCHAR(100) NULL,
        `comment` TEXT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `quote_id` (`quote_id`),
        CONSTRAINT `fk_qsh_quote` FOREIGN KEY (`quote_id`) REFERENCES `quote_requests` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    if ($conn->query($sqlStatusHistory)) {
        $log[] = "Table 'quote_status_history' is verified/created.";
    } else {
        $log[] = "Error with 'quote_status_history': " . $conn->error;
    }

    // 6. Migrate existing legacy leads into quote_requests if quote_requests is empty
    $countQuotes = $conn->query("SELECT COUNT(*) as c FROM quote_requests")->fetch_assoc()['c'] ?? 0;
    if ($countQuotes == 0) {
        $leadsRes = $conn->query("SELECT * FROM leads ORDER BY id ASC");
        if ($leadsRes && $leadsRes->num_rows > 0) {
            while ($lead = $leadsRes->fetch_assoc()) {
                $qNum = 'CTI-QT-' . date('Y', strtotime($lead['created_at'])) . '-' . str_pad($lead['id'], 4, '0', STR_PAD_LEFT);
                $userId = null;
                // Find matching user by email
                $findUser = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $findUser->bind_param("s", $lead['email']);
                $findUser->execute();
                $uRow = $findUser->get_result()->fetch_assoc();
                if ($uRow) {
                    $userId = (int)$uRow['id'];
                }

                $areaVal = (float)preg_replace('/[^0-9.]/', '', $lead['area'] ?? '0');
                $stateVal = 'Gujarat';
                $pincodeVal = '395001';
                $statusMap = [
                    'new' => 'new',
                    'contacted' => 'contacted',
                    'qualified' => 'estimation_prepared',
                    'converted' => 'project_started',
                    'lost' => 'rejected'
                ];
                $leadStatus = $statusMap[$lead['status'] ?? 'new'] ?? 'new';

                $insStmt = $conn->prepare("INSERT INTO quote_requests (
                    quote_number, user_id, customer_name, email, phone, address, city, state, pincode,
                    project_type, property_type, approx_area, budget_range, design_style,
                    expected_start_date, special_requirements, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $addr = $lead['city'] ?: 'Surat';
                $city = $lead['city'] ?: 'Surat';
                $projType = $lead['project_type'] ?: 'Residential';
                $propType = $lead['property_type'] ?: 'Villa';
                $budget = !empty($lead['budget']) ? $lead['budget'] . ' Lakhs' : '₹10L - ₹25L';
                $style = $lead['design_preference'] ?: 'Modern';
                $startDate = !empty($lead['expected_start_date']) ? $lead['expected_start_date'] : null;
                $msg = $lead['message'] ?: '';
                $createdAt = $lead['created_at'];

                $insStmt->bind_param(
                    "sisssssssdssssssss",
                    $qNum, $userId, $lead['name'], $lead['email'], $lead['phone'],
                    $addr, $city, $stateVal, $pincodeVal,
                    $projType, $propType, $areaVal, $budget, $style,
                    $startDate, $msg, $leadStatus, $createdAt
                );

                if ($insStmt->execute()) {
                    $newQId = $insStmt->insert_id;
                    // Initial history record
                    $histStmt = $conn->prepare("INSERT INTO quote_status_history (quote_id, previous_status, new_status, changed_by_type, changed_by_name, comment, created_at) VALUES (?, NULL, ?, 'system', 'Legacy Migration', 'Imported from existing lead system', ?)");
                    $histStmt->bind_param("iss", $newQId, $leadStatus, $createdAt);
                    $histStmt->execute();
                }
            }
            $log[] = "Migrated legacy leads into 'quote_requests'.";
        }
    }

    return $log;
}

// Auto-run if executed directly
if (php_sapi_name() === 'cli' || isset($_GET['run_migration'])) {
    $results = runQuoteDbMigration($conn);
    foreach ($results as $msg) {
        echo "[MIGRATION] " . $msg . "\n";
    }
}
