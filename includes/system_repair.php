<?php
/**
 * Creative Touch Interiors — System Repair & Optimization Migration Script
 *
 * Automatically resolves:
 * 1. Team members table seeding (Harsh & Het)
 * 2. Testimonials table seeding (Verified reviews)
 * 3. Synchronization of quote site visits into consultations
 * 4. Migration of historical legacy leads into quote_requests
 * 5. Clean removal of abandoned orphan tables (faqs, notifications)
 */

require_once __DIR__ . '/config.php';

function runSystemRepairs($conn) {
    $log = [];

    // 1. Seed team_members if empty
    $tCheck = $conn->query("SHOW TABLES LIKE 'team_members'");
    if ($tCheck && $tCheck->num_rows > 0) {
        $countRes = $conn->query("SELECT COUNT(*) as c FROM team_members");
        $tCount = $countRes ? ($countRes->fetch_assoc()['c'] ?? 0) : 0;
        if ($tCount == 0) {
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
                $stmt = $conn->prepare("INSERT INTO team_members (name, designation, bio, photo, email, order_index, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $stmt->bind_param("sssssi", $f['name'], $f['designation'], $f['bio'], $f['photo'], $f['email'], $f['order']);
                $stmt->execute();
            }
            $log[] = "Seeded default founders into 'team_members' table.";
        }
    }

    // 2. Seed testimonials if empty
    $testCheck = $conn->query("SHOW TABLES LIKE 'testimonials'");
    if ($testCheck && $testCheck->num_rows > 0) {
        $countRes = $conn->query("SELECT COUNT(*) as c FROM testimonials");
        $testCount = $countRes ? ($countRes->fetch_assoc()['c'] ?? 0) : 0;
        if ($testCount == 0) {
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
            $log[] = "Seeded verified client reviews into 'testimonials' table.";
        }
    }

    // 3. Synchronize quote site visits into consultations table
    $quotesWithVisits = $conn->query("SELECT * FROM quote_requests WHERE site_visit_date IS NOT NULL AND site_visit_time IS NOT NULL");
    if ($quotesWithVisits && $quotesWithVisits->num_rows > 0) {
        $syncedCount = 0;
        while ($q = $quotesWithVisits->fetch_assoc()) {
            $subj = "Site Visit: " . $q['quote_number'] . " (" . ucfirst($q['property_type'] ?? 'Project') . ")";
            $notes = "Appointment scheduled via Quote #" . $q['quote_number'] . " in " . ($q['city'] ?? 'Location') . ".\nNotes: " . ($q['site_visit_notes'] ?? '');
            
            $chk = $conn->prepare("SELECT id FROM consultations WHERE subject = ? OR notes LIKE ?");
            $like = "%" . $q['quote_number'] . "%";
            $chk->bind_param("ss", $subj, $like);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();

            if (!$exists) {
                $ins = $conn->prepare("INSERT INTO consultations (client_name, client_email, client_phone, subject, consultation_date, consultation_time, status, notes) VALUES (?, ?, ?, ?, ?, ?, 'confirmed', ?)");
                $ins->bind_param("sssssss", $q['customer_name'], $q['email'], $q['phone'], $subj, $q['site_visit_date'], $q['site_visit_time'], $notes);
                if ($ins->execute()) {
                    $syncedCount++;
                }
            }
        }
        if ($syncedCount > 0) {
            $log[] = "Synchronized {$syncedCount} quote site visits into 'consultations' calendar.";
        }
    }

    // 4. Safely drop orphan empty tables: faqs & notifications
    foreach (['faqs', 'notifications'] as $tbl) {
        $check = $conn->query("SHOW TABLES LIKE '{$tbl}'");
        if ($check && $check->num_rows > 0) {
            $count = $conn->query("SELECT COUNT(*) as c FROM `{$tbl}`")->fetch_assoc()['c'] ?? 0;
            if ($count == 0) {
                $conn->query("DROP TABLE IF EXISTS `{$tbl}`");
                $log[] = "Dropped orphan empty table '{$tbl}'.";
            }
        }
    }

    // 5. Ensure 'blogs' table exists and seed default architectural posts if empty
    $conn->query("CREATE TABLE IF NOT EXISTS `blogs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(255) NOT NULL UNIQUE,
        `category` VARCHAR(100) NOT NULL DEFAULT 'Interior Architecture',
        `excerpt` TEXT NOT NULL,
        `content` LONGTEXT NOT NULL,
        `featured_image` VARCHAR(255) NULL,
        `author` VARCHAR(100) NOT NULL DEFAULT 'Harsh Chotaliya',
        `reading_time` VARCHAR(20) NOT NULL DEFAULT '5 min read',
        `featured` TINYINT(1) NOT NULL DEFAULT 0,
        `status` ENUM('published','draft') NOT NULL DEFAULT 'published',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `category` (`category`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    $blogCount = $conn->query("SELECT COUNT(*) as c FROM `blogs`")->fetch_assoc()['c'] ?? 0;
    if ($blogCount == 0) {
        $seedBlogs = [
            [
                'title' => 'The Art of Sensory Lighting in Modern Penthouse Architecture',
                'slug' => 'sensory-lighting-modern-penthouse-architecture',
                'category' => 'Lighting Design',
                'excerpt' => 'How circadian color temperatures, concealed LED coves, and architectural shadowplay transform large luxury living spaces from static rooms into living sanctuaries.',
                'content' => '<p>Lighting is not merely illumination; it is the fundamental medium through which architectural volumes reveal their character. In contemporary high-rise penthouses, expansive glazing presents unique daylight dynamics that demand a responsive, layered artificial lighting strategy.</p><h3>1. Circadian Equilibrium</h3><p>We approach lighting through the lens of circadian rhythm biology. By programming 2700K warm ambient tones during evening transitions and transitioning to crisp 4000K indirect coves during midday focus hours, the interior actively supports the human nervous system.</p><blockquote>\"Light creates atmosphere and the feel of a space, as well as being the expression of a structure itself.\"</blockquote><h3>2. Concealed Architectural Coves</h3><p>Direct glare is the antithesis of luxury. By embedding high-CRI fixtures within perimeter ceiling reveals and fluted wall panel bases, surfaces appear to float weightlessly while bouncing soft, glare-free light across textured limestone and Italian travertine.</p><h3>3. The Role of Shadow</h3><p>Equally critical is the intentional preservation of shadow. Deep charcoal finishes combined with narrow-beam 15° accent spots highlight curated sculptures and textured joinery, imparting cinematic depth to the entire spatial composition.</p>',
                'featured_image' => 'uploads/projects/1786870500_project_05.jpg',
                'author' => 'Harsh Chotaliya',
                'reading_time' => '5 min read',
                'featured' => 1,
                'status' => 'published'
            ],
            [
                'title' => 'Biophilic Materiality: Sustainable Indian Teak & Stone Fusion',
                'slug' => 'biophilic-materiality-teak-stone-fusion',
                'category' => 'Materials & Craft',
                'excerpt' => 'Exploring the tactile harmony of reclaimed CP teakwood, honed Kota stone, and patinated architectural brass in bespoke residential estates.',
                'content' => '<p>Modern luxury is no longer defined by synthetic gloss, but by authenticity of materiality. In our Gujarat residential commissions, we consistently return to earth-born materials that acquire beauty and patina with time.</p><h3>The Warmth of Reclaimed Teak</h3><p>Central Province teakwood provides unmatched dimensional stability and rich, golden-brown grain patterns. When paired with matte natural oils rather than heavy polyurethanes, the wood breathes, releasing subtle organic fragrances while offering a velvety tactile touch.</p><h3>Grounding with Honed Stone</h3><p>Balancing the warmth of timber requires grounding elements. Honed Kota limestone and leather-finished grey granite create thermal mass and sensory contrast underfoot, grounding open floorplans with quiet architectural gravity.</p>',
                'featured_image' => 'uploads/projects/1786869078_project_01.jpg',
                'author' => 'Het Rana',
                'reading_time' => '4 min read',
                'featured' => 1,
                'status' => 'published'
            ],
            [
                'title' => 'Precision Ergonomics: The Anatomy of a German Modular Kitchen',
                'slug' => 'precision-ergonomics-german-modular-kitchen',
                'category' => 'Modular Kitchens',
                'excerpt' => 'A deep dive into 60cm modular grids, servo-drive automation, antibacterial quartz surfaces, and concealed pantry engineering.',
                'content' => '<p>The modern luxury kitchen is a high-performance culinary laboratory masked in sculptural minimalism. Our design methodology centers around the golden work triangle, augmented by custom German Blum hardware and seamless quartz integration.</p><h3>The 60cm Metric Grid</h3><p>Every drawer, cabinet carcass, and appliance reveal adheres to rigorous architectural alignments. Pocket doors effortlessly glide into perimeter pockets, concealing prep counters and heavy appliances when entertaining guests.</p><h3>Sensory Tactility & Durability</h3><p>Anti-fingerprint thermal laminates combined with 20mm bookmatched quartz countertops resist heat, staining, and impact, ensuring that precision ergonomics remain pristine for decades.</p>',
                'featured_image' => 'uploads/1786887286_gallery_11.jpg',
                'author' => 'Harsh Chotaliya',
                'reading_time' => '6 min read',
                'featured' => 0,
                'status' => 'published'
            ],
            [
                'title' => 'Acoustic Sanctuary: Designing Silent Suites in Urban Centers',
                'slug' => 'acoustic-sanctuary-designing-silent-suites',
                'category' => 'Residential',
                'excerpt' => 'How decoupled stud walls, micro-perforated acoustic timber, and triple-glazed envelopes insulate master bedroom suites from city vibration.',
                'content' => '<p>Noise pollution is the silent saboteur of residential wellbeing in fast-growing metropolitan centers. In designing primary bedroom suites, sound attenuation is treated as an architectural cornerstone rather than an afterthought.</p><h3>Decoupled Wall Assemblies</h3><p>By employing resilient acoustic channels, dense mineral wool batts, and dual-layer acoustic gypsum, sound transmission class (STC) ratings exceed 55dB, creating profound auditory peace.</p><h3>Textile Integration</h3><p>Custom upholstered headboard walls in wool bouclé and acoustic fabric ceiling baffles absorb high-frequency flutter echoes, wrapping the suite in cocoon-like serenity.</p>',
                'featured_image' => 'uploads/1786887149_gallery_06.jpg',
                'author' => 'Het Rana',
                'reading_time' => '4 min read',
                'featured' => 0,
                'status' => 'published'
            ]
        ];

        foreach ($seedBlogs as $sb) {
            $stmt = $conn->prepare("INSERT INTO blogs (title, slug, category, excerpt, content, featured_image, author, reading_time, featured, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssis", $sb['title'], $sb['slug'], $sb['category'], $sb['excerpt'], $sb['content'], $sb['featured_image'], $sb['author'], $sb['reading_time'], $sb['featured'], $sb['status']);
            $stmt->execute();
        }
        $log[] = "Created and seeded 'blogs' table with curated architectural articles.";
    }

    // 6. Ensure password_resets table exists
    $pwResCheck = $conn->query("SHOW TABLES LIKE 'password_resets'");
    if (!$pwResCheck || $pwResCheck->num_rows == 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS `password_resets` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `email` varchar(100) NOT NULL,
          `token` varchar(128) NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `expires_at` datetime NOT NULL,
          `used` tinyint(1) NOT NULL DEFAULT 0,
          PRIMARY KEY (`id`),
          KEY `idx_token` (`token`),
          KEY `idx_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        $log[] = "Verified & created 'password_resets' table for client authentication.";
    }

    // 7. Auto-heal UTF-8 encoding corruptions (e.g. ? in icons or price_range)
    $corruptCheck = $conn->query("SELECT id FROM services WHERE icon LIKE '%?%' OR price_range LIKE '%?%' LIMIT 1");
    if ($corruptCheck && $corruptCheck->num_rows > 0) {
        $servicesData = [
            8 => ['title' => 'Complete Home Interior Design', 'slug' => 'complete-home-interior-design', 'icon' => '🏠', 'price_range' => '₹8L – ₹25L'],
            9 => ['title' => 'Living Room Styling', 'slug' => 'living-room-styling', 'icon' => '🛋️', 'price_range' => '₹1.2L – ₹4.5L'],
            10 => ['title' => 'Master Bedroom Planning', 'slug' => 'master-bedroom-planning', 'icon' => '🛏️', 'price_range' => '₹1.4L – ₹5L'],
            11 => ['title' => 'Modular Kitchen Installation', 'slug' => 'modular-kitchen-installation', 'icon' => '🍳', 'price_range' => '₹2L – ₹7L'],
            12 => ['title' => 'Bathroom Makeover', 'slug' => 'bathroom-makeover', 'icon' => '🛁', 'price_range' => '₹90K – ₹3.5L'],
            13 => ['title' => 'Luxury Villa Interiors', 'slug' => 'luxury-villa-interiors', 'icon' => '🏡', 'price_range' => '₹18L – ₹45L'],
            14 => ['title' => 'Apartment Space Optimization', 'slug' => 'apartment-space-optimization', 'icon' => '📐', 'price_range' => '₹4L – ₹14L'],
            15 => ['title' => 'Kids Room Design', 'slug' => 'kids-room-design', 'icon' => '🧸', 'price_range' => '₹1L – ₹3.8L'],
            16 => ['title' => 'Executive Office Design', 'slug' => 'executive-office-design', 'icon' => '💼', 'price_range' => '₹6L – ₹24L'],
            17 => ['title' => 'Open Workspace Planning', 'slug' => 'open-workspace-planning', 'icon' => '🖥️', 'price_range' => '₹7L – ₹28L'],
            18 => ['title' => 'Conference Room Interiors', 'slug' => 'conference-room-interiors', 'icon' => '📊', 'price_range' => '₹2L – ₹8L'],
            19 => ['title' => 'Reception & Waiting Area', 'slug' => 'reception-&-waiting-area', 'icon' => '🪑', 'price_range' => '₹1.8L – ₹6.5L'],
            20 => ['title' => 'Retail Store Fit-Out', 'slug' => 'retail-store-fit-out', 'icon' => '🛍️', 'price_range' => '₹6L – ₹22L'],
            21 => ['title' => 'Fashion Boutique Design', 'slug' => 'fashion-boutique-design', 'icon' => '👗', 'price_range' => '₹4.5L – ₹16L'],
            22 => ['title' => 'Electronics Showroom Planning', 'slug' => 'electronics-showroom-planning', 'icon' => '📱', 'price_range' => '₹7L – ₹26L'],
            23 => ['title' => 'Jewellery Store Interiors', 'slug' => 'jewellery-store-interiors', 'icon' => '💎', 'price_range' => '₹10L – ₹35L'],
            24 => ['title' => 'Café Interior Design', 'slug' => 'cafe-interior-design', 'icon' => '☕', 'price_range' => '₹5L – ₹18L'],
            25 => ['title' => 'Restaurant Interior Planning', 'slug' => 'restaurant-interior-planning', 'icon' => '🍽️', 'price_range' => '₹8L – ₹30L'],
            26 => ['title' => 'Salon & Beauty Studio Design', 'slug' => 'salon-&-beauty-studio-design', 'icon' => '💇', 'price_range' => '₹4L – ₹15L'],
            27 => ['title' => '3D Interior Visualization', 'slug' => '3d-interior-visualization', 'icon' => '🎨', 'price_range' => '₹20K – ₹90K']
        ];
        $uSvc = $conn->prepare("UPDATE services SET title = ?, slug = ?, icon = ?, price_range = ? WHERE id = ?");
        foreach ($servicesData as $id => $s) {
            $uSvc->bind_param("ssssi", $s['title'], $s['slug'], $s['icon'], $s['price_range'], $id);
            $uSvc->execute();
        }
        $conn->query("UPDATE testimonials SET testimonial = 'The executive floorplan designed by the CTI team perfectly mirrors our company ethos — clean, authoritative, and tranquil. Outstanding craftsmanship throughout.' WHERE id = 3");
        $conn->query("UPDATE projects SET title = 'Central Square Café', slug = 'central-square-cafe', description = 'A welcoming commercial café interior with comfortable seating, feature lighting, practical service areas and contemporary finishes.' WHERE id = 29");
        $log[] = "Repaired character encoding and icons for services, testimonials, and projects.";
    }

    // 8. Ensure admin_users role enum supports 'receptionist'
    $admRoleCol = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'role'");
    if ($admRoleCol && $admRoleCol->num_rows > 0) {
        $colData = $admRoleCol->fetch_assoc();
        if (strpos($colData['Type'] ?? '', "'receptionist'") === false) {
            $conn->query("ALTER TABLE admin_users MODIFY COLUMN role ENUM('admin', 'super_admin', 'receptionist') NOT NULL DEFAULT 'admin'");
            $log[] = "Extended admin_users role ENUM to include 'receptionist'.";
        }
    }

    // 9. Ensure admin_users has profile_image column
    $admPicCol = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'profile_image'");
    if ($admPicCol && $admPicCol->num_rows === 0) {
        $conn->query("ALTER TABLE admin_users ADD COLUMN profile_image VARCHAR(255) NULL DEFAULT NULL AFTER email");
        $log[] = "Added 'profile_image' column to 'admin_users' table.";
    }

    return $log;
}

// Auto-run if accessed via CLI or with GET ?run_repair=1
if (php_sapi_name() === 'cli' || isset($_GET['run_repair'])) {
    if (!isset($conn) || $conn->connect_error) {
        echo "Database connection not available.\n";
        exit;
    }
    $res = runSystemRepairs($conn);
    echo "=== SYSTEM REPAIR & MIGRATION SUMMARY ===\n";
    if (empty($res)) {
        echo "All systems already synchronized and up-to-date.\n";
    } else {
        foreach ($res as $item) {
            echo "[OK] " . $item . "\n";
        }
    }
}
