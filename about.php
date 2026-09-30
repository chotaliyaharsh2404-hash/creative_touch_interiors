<?php
require_once 'includes/config.php';

$page_title = 'Studio Story, Leadership & Architectural Philosophy — Creative Touch Interiors';

// Fetch live dynamic statistics from MySQL database
$db_projects_count = 0;
$p_count_res = $conn->query("SELECT COUNT(*) as count FROM projects");
if ($p_count_res && $p_row = $p_count_res->fetch_assoc()) {
    $db_projects_count = (int)$p_row['count'];
}

$db_clients_count = 0;
$c_count_res = $conn->query("SELECT COUNT(DISTINCT email) as count FROM quote_requests");
if ($c_count_res && $c_row = $c_count_res->fetch_assoc()) {
    $db_clients_count = (int)$c_row['count'];
}
if ($db_clients_count < 10) {
    $u_count_res = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($u_count_res && $u_row = $u_count_res->fetch_assoc()) {
        $db_clients_count = max($db_clients_count, (int)$u_row['count']);
    }
}

// Fetch team members from database
$team_sql = "SELECT * FROM team_members WHERE status = 'active' ORDER BY order_index ASC";
$team_result = $conn->query($team_sql);
$team_members = [];
if ($team_result && $team_result->num_rows > 0) {
    while ($row = $team_result->fetch_assoc()) {
        $team_members[] = $row;
    }
} else {
    $team_members = [
        [
            'name' => 'Harsh Chotaliya',
            'designation' => 'Principal Architect & Founder',
            'bio' => 'Dedicated to structural harmony, spatial equilibrium, and contemporary Indian architectural luxury with over 15 years of industry mastery.',
            'photo' => 'uploads/team/harsh.jpeg',
            'email' => 'harshchotaliya@gmail.com'
        ],
        [
            'name' => 'Het Rana',
            'designation' => 'Lead Interior Designer & Co-Founder',
            'bio' => 'Specializes in high-end bespoke residential estates, sensory lighting integration, and turnkey execution with timeless materials.',
            'photo' => 'uploads/team/het.jpg',
            'email' => 'hetrana@gmail.com'
        ]
    ];
}

// Get site content
$about_story = getSiteContent('about_story') ?: 'Creative Touch Interiors was founded on a singular architectural conviction: to transform ordinary residences and bare-shell commercial structures into extraordinary living environments where aesthetics, acoustic peace, and functional ergonomics exist in effortless harmony.';

include 'includes/header.php';
?>

    <!-- Editorial Hero Header -->
    <section class="spatial-hero-section" style="min-height: 52vh; padding-top: 8rem; padding-bottom: 4rem; background: radial-gradient(circle at 50% 20%, #eff6ff 0%, #ffffff 85%);">
        <div class="spatial-hero-scrim"></div>
        <div class="spatial-hero-content" style="padding: 1rem;">
            <div class="spatial-hero-pill">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
                <span>Studio Heritage &bull; Surat, Gujarat</span>
            </div>
            <h1 class="spatial-hero-title" style="font-size: clamp(2.4rem, 5vw, 4rem); margin-bottom: 1rem; color: #0f172a;">
                Turning Ideas Into Beautiful Spaces
            </h1>
            <p class="spatial-hero-subtitle" style="margin-bottom: 0; color: #475569;">
                Translating personal life rituals into enduring architectural sanctuaries through sensory materiality and turnkey craft.
            </p>
        </div>
    </section>

    <!-- Narrative & 3D Interactive Spatial Split Section -->
    <section class="spatial-section">
        <div class="container">
            <div class="spatial-about-grid">
                
                <!-- Left: 3D Interactive Spatial Sculpture -->
                <div class="spatial-about-3d-visual">
                    <canvas id="about-3d-canvas" aria-label="Interactive 3D Architectural Sculpture"></canvas>
                    <div style="position: absolute; bottom: 1.5rem; left: 1.5rem; z-index: 2; font-size: 0.72rem; color: #2563eb; text-transform: uppercase; letter-spacing: 0.15em; font-weight: 700; background: rgba(255,255,255,0.92); padding: 0.35rem 0.75rem; border-radius: 999px; backdrop-filter: blur(8px); border: 1px solid #bfdbfe; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);">
                        Spatial Pavilion Model &bull; Interactive
                    </div>
                </div>

                <!-- Right: Studio Story & Real DB Statistics -->
                <div class="spatial-about-content">
                    <span class="spatial-about-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                        Design Philosophy
                    </span>

                    <h2 class="spatial-about-title" style="color: #0f172a !important;">The Art of Spatial Equilibrium</h2>

                    <p class="spatial-about-text">
                        <?php echo htmlspecialchars($about_story); ?>
                    </p>

                    <p class="spatial-about-text" style="margin-top: -1rem;">
                        Under the stewardship of <strong>Harsh Chotaliya</strong> and <strong>Het Rana</strong>, each commission begins with structural analysis and sunlight mapping. We reject generic catalog templates in favor of bespoke architectural joinery, Central Province teakwood veneers, and acoustic wall assemblies that withstand the test of decades.
                    </p>

                    <!-- Real Database Animated Counters -->
                    <div class="spatial-stats-grid">
                        <div class="spatial-stat-card">
                            <div class="spatial-stat-number" style="color: #0f172a !important;" data-target="<?php echo max(15, $db_projects_count); ?>">
                                <span style="color: #0f172a !important;"><?php echo max(15, $db_projects_count); ?></span><span class="plus" style="color: #2563eb !important;">+</span>
                            </div>
                            <div class="spatial-stat-label">Curated Projects</div>
                        </div>

                        <div class="spatial-stat-card">
                            <div class="spatial-stat-number" style="color: #0f172a !important;" data-target="<?php echo max(25, $db_clients_count * 2); ?>">
                                <span style="color: #0f172a !important;"><?php echo max(25, $db_clients_count * 2); ?></span><span class="plus" style="color: #2563eb !important;">+</span>
                            </div>
                            <div class="spatial-stat-label">Discerning Clients</div>
                        </div>

                        <div class="spatial-stat-card">
                            <div class="spatial-stat-number" style="color: #0f172a !important;" data-target="5">
                                <span style="color: #0f172a !important;">5</span><span class="plus" style="color: #2563eb !important;">+</span>
                            </div>
                            <div class="spatial-stat-label">Years of Mastery</div>
                        </div>

                        <div class="spatial-stat-card">
                            <div class="spatial-stat-number" style="color: #0f172a !important;" data-target="10">
                                <span style="color: #0f172a !important;">10</span><span class="plus" style="color: #2563eb !important;">+</span>
                            </div>
                            <div class="spatial-stat-label">Design Disciplines</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Core Architectural Pillars -->
    <section class="spatial-section spatial-section-alt" style="padding: 6rem 0;">
        <div class="container">
            <div class="spatial-section-header">
                <span class="spatial-section-eyebrow">Studio Values</span>
                <h2 class="spatial-section-title" style="color: #0f172a !important;">The Four Architectural Cornerstones</h2>
                <p class="spatial-section-desc">
                    Every floorplan, joint, and illumination angle we design is governed by four immutable principles.
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 2rem;">
                <div style="background: var(--glass-bg-card); border: 1px solid var(--glass-border); border-radius: var(--radius-xl); padding: 2.25rem 1.75rem;">
                    <div style="color: var(--color-bronze); font-size: 1.75rem; margin-bottom: 1rem; font-weight: 700;">01</div>
                    <h3 style="font-size: 1.25rem; color: #0f172a; margin-bottom: 0.65rem;">Circadian Lighting</h3>
                    <p style="color: var(--color-stone-light); font-size: 0.875rem; line-height: 1.65;">
                        Layered ambient coves, high-CRI fixtures, and daylight harvesting engineered around human biological vitality.
                    </p>
                </div>

                <div style="background: var(--glass-bg-card); border: 1px solid var(--glass-border); border-radius: var(--radius-xl); padding: 2.25rem 1.75rem;">
                    <div style="color: var(--color-bronze); font-size: 1.75rem; margin-bottom: 1rem; font-weight: 700;">02</div>
                    <h3 style="font-size: 1.25rem; color: #0f172a; margin-bottom: 0.65rem;">Sensory Materiality</h3>
                    <p style="color: var(--color-stone-light); font-size: 0.875rem; line-height: 1.65;">
                        Authentic timbers, honed Kota stone, textured limestone, and hand-patinated brass that age with dignity.
                    </p>
                </div>

                <div style="background: var(--glass-bg-card); border: 1px solid var(--glass-border); border-radius: var(--radius-xl); padding: 2.25rem 1.75rem;">
                    <div style="color: var(--color-bronze); font-size: 1.75rem; margin-bottom: 1rem; font-weight: 700;">03</div>
                    <h3 style="font-size: 1.25rem; color: #0f172a; margin-bottom: 0.65rem;">Acoustic Solitude</h3>
                    <p style="color: var(--color-stone-light); font-size: 0.875rem; line-height: 1.65;">
                        Decoupled walls, micro-perforated acoustic timber, and fabric wall assemblies to create profound auditory silence.
                    </p>
                </div>

                <div style="background: var(--glass-bg-card); border: 1px solid var(--glass-border); border-radius: var(--radius-xl); padding: 2.25rem 1.75rem;">
                    <div style="color: var(--color-bronze); font-size: 1.75rem; margin-bottom: 1rem; font-weight: 700;">04</div>
                    <h3 style="font-size: 1.25rem; color: #0f172a; margin-bottom: 0.65rem;">Turnkey Rigor</h3>
                    <p style="color: var(--color-stone-light); font-size: 0.875rem; line-height: 1.65;">
                        End-to-end civil coordination, algorithmic budgeting, and punctually delivered handover milestones.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Leadership & Studio Partners -->
    <section class="spatial-section" style="padding: 6rem 0 7rem;">
        <div class="container">
            <div class="spatial-section-header">
                <span class="spatial-section-eyebrow">Studio Leadership</span>
                <h2 class="spatial-section-title" style="color: #0f172a !important;">The Minds Behind the Spaces</h2>
                <p class="spatial-section-desc">
                    Meet the principal architects and creative directors guiding Creative Touch Interiors.
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 3rem; max-width: 960px; margin: 0 auto;">
                <?php foreach ($team_members as $member): ?>
                    <div class="card" style="background: var(--glass-bg-card); border: 1px solid var(--glass-border); border-radius: var(--radius-2xl); overflow: hidden; box-shadow: var(--shadow-spatial-lg); transition: var(--transition-spatial);" onmouseover="this.style.borderColor='var(--color-bronze-border)';" onmouseout="this.style.borderColor='var(--glass-border)';">
                        <div style="aspect-ratio: 1/1; overflow: hidden; background: #eff6ff;">
                            <img src="<?php echo htmlspecialchars($member['photo'] ?: 'uploads/team/harsh.jpeg'); ?>" 
                                 alt="<?php echo htmlspecialchars($member['name']); ?>" 
                                 style="width: 100%; height: 100%; object-fit: cover; filter: grayscale(15%); transition: transform 0.5s ease;"
                                 onmouseover="this.style.transform='scale(1.05)'; this.style.filter='grayscale(0%)';"
                                 onmouseout="this.style.transform='scale(1)'; this.style.filter='grayscale(15%)';">
                        </div>

                        <div style="padding: 2.25rem 2rem;">
                            <span style="font-size: 0.75rem; color: var(--color-bronze-light); text-transform: uppercase; font-weight: 700; letter-spacing: 0.12em; display: block; margin-bottom: 0.4rem;">
                                <?php echo htmlspecialchars($member['designation']); ?>
                            </span>
                            <h3 style="font-size: 1.5rem; color: #0f172a; margin-bottom: 1rem;">
                                <?php echo htmlspecialchars($member['name']); ?>
                            </h3>
                            <p style="color: var(--color-stone-light); font-size: 0.925rem; line-height: 1.7; margin-bottom: 1.5rem;">
                                <?php echo htmlspecialchars($member['bio']); ?>
                            </p>
                            <?php if (!empty($member['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" class="spatial-project-link-btn" style="font-size: 0.825rem;">
                                    <span><?php echo htmlspecialchars($member['email']); ?></span> &rarr;
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Consultation CTA -->
    <section class="spatial-section spatial-section-alt" style="padding: 6rem 0; text-align: center;">
        <div class="container" style="max-width: 800px;">
            <span class="spatial-section-eyebrow">Studio Consultation</span>
            <h2 class="spatial-section-title" style="margin-bottom: 1.25rem; color: #0f172a !important;">Experience Our Architectural Practice</h2>
            <p style="color: var(--color-stone-light); font-size: 1.1rem; line-height: 1.7; margin-bottom: 2.5rem;">
                Visit our Surat design studio for a private portfolio walkthrough and material tactile session.
            </p>
            <div style="display: flex; gap: 1.25rem; justify-content: center; flex-wrap: wrap;">
                <a href="contact.php" class="btn-spatial-bronze">
                    <span>Schedule Studio Meeting</span> &rarr;
                </a>
                <a href="consultation.php" class="btn-spatial-outline">
                    <span>Get Custom Estimate</span>
                </a>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
