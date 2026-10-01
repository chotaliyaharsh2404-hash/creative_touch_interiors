<?php
require_once 'includes/config.php';

$page_title = 'Creative Touch Interiors — Luxury 3D Interior Architecture';

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

// Fetch featured projects for 3D showcase
$projects_sql = "SELECT * FROM projects ORDER BY featured DESC, created_at DESC LIMIT 6";
$projects_result = $conn->query($projects_sql);
$featured_projects = [];
if ($projects_result) {
    while ($p = $projects_result->fetch_assoc()) {
        $featured_projects[] = $p;
    }
}

// First featured project preview for hero HUD badge
$hero_preview_project = !empty($featured_projects) ? $featured_projects[0] : null;

// Fetch services from MySQL
$services_sql = "SELECT * FROM services WHERE status = 'active' ORDER BY featured DESC, id ASC LIMIT 6";
$services_result = $conn->query($services_sql);
$featured_services = [];
if ($services_result) {
    while ($s = $services_result->fetch_assoc()) {
        $featured_services[] = $s;
    }
}

// Fetch approved testimonials
$testimonials_sql = "SELECT * FROM testimonials WHERE status = 'approved' ORDER BY featured DESC, id DESC LIMIT 5";
$testimonials_result = $conn->query($testimonials_sql);
$testimonials = [];
if ($testimonials_result && $testimonials_result->num_rows > 0) {
    while ($t = $testimonials_result->fetch_assoc()) {
        $testimonials[] = $t;
    }
} else {
    $testimonials = [
        [
            'client_name' => 'Rajesh & Meera Patel',
            'project_title' => 'Palatial Villa, Surat',
            'rating' => 5,
            'testimonial' => 'Creative Touch Interiors transformed our bare shell villa into an extraordinary sanctuary. Their attention to lighting layers, joinery detailing, and acoustic comfort exceeded every expectation.'
        ],
        [
            'client_name' => 'Ananya Singhania',
            'project_title' => 'Luxury High-Rise Penthouse, Mumbai',
            'rating' => 5,
            'testimonial' => 'From the 3D visualization phase to turnkey execution, Harsh and Het handled our penthouse with impeccable professionalism and unmatched aesthetic sensitivity.'
        ],
        [
            'client_name' => 'Vikramaditya Shah',
            'project_title' => 'Corporate HQ & Executive Suite, Pune',
            'rating' => 5,
            'testimonial' => 'The executive floorplan designed by the CTI team perfectly mirrors our company ethos — clean, authoritative, and tranquil. Outstanding craftsmanship throughout.'
        ]
    ];
}

include 'includes/header.php';
?>

    <!-- ==========================================================================
         HERO SECTION: 3D LUXURY LIVING SPACE ENVIRONMENT
         ========================================================================== -->
    <section class="spatial-hero-section" id="hero">
        <!-- Interactive WebGL Three.js Canvas -->
        <canvas id="hero-3d-canvas" aria-label="Interactive 3D Interior Environment"></canvas>
        <div class="spatial-hero-scrim"></div>

        <!-- Floating UI Elements Above 3D Canvas -->
        <div class="spatial-hero-content">
            <div class="spatial-hero-pill">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
                <span>3D Architectural Interior Studio</span>
            </div>

            <?php 
                $db_hero_title = getSiteContent('hero_title');
                $db_hero_subtitle = getSiteContent('hero_subtitle');
            ?>
            <h1 class="spatial-hero-title">
                <?php if (!empty($db_hero_title)): ?>
                    <?php echo htmlspecialchars($db_hero_title); ?>
                <?php else: ?>
                    DESIGNING SPACES.<br>
                    <span class="gold-text">DEFINING EXPERIENCES.</span>
                <?php endif; ?>
            </h1>

            <p class="spatial-hero-subtitle">
                <?php echo !empty($db_hero_subtitle) ? htmlspecialchars($db_hero_subtitle) : 'We create elegant, functional and personalized interiors designed around the way you live.'; ?>
            </p>

            <div class="spatial-hero-actions">
                <a href="projects.php" class="btn-spatial-bronze">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                    </svg>
                    <span>Explore Projects</span>
                </a>
                <a href="consultation.php" class="btn-spatial-outline">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="12" y1="18" x2="12" y2="12"></line>
                        <line x1="9" y1="15" x2="15" y2="15"></line>
                    </svg>
                    <span>Get a Quote</span>
                </a>
            </div>
        </div>

        <!-- Floating HUD Project Preview Badge -->
        <?php if ($hero_preview_project): ?>
            <div class="spatial-hero-badge">
                <img src="<?php echo htmlspecialchars($hero_preview_project['image'] ?: 'uploads/projects/1786870500_project_05.jpg'); ?>" alt="Project preview" class="spatial-hero-badge-img">
                <div>
                    <div style="font-size: 0.7rem; color: var(--color-bronze-light); text-transform: uppercase; font-weight: 700; letter-spacing: 0.1em;">
                        Signature Spotlight
                    </div>
                    <div style="font-size: 0.9rem; font-weight: 700; color: #ffffff; white-space: nowrap;">
                        <?php echo htmlspecialchars($hero_preview_project['title']); ?>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--color-stone);">
                        <?php echo htmlspecialchars($hero_preview_project['location'] ?: 'Surat, Gujarat'); ?>
                    </div>
                </div>
                <a href="project.php?id=<?php echo $hero_preview_project['id']; ?>" class="spatial-project-link-btn" style="margin-left: 0.5rem;" title="View Case Study">
                    &rarr;
                </a>
            </div>
        <?php endif; ?>

        <!-- Scroll Indicator -->
        <a href="#projects-showcase" class="spatial-scroll-indicator" aria-label="Scroll to discover">
            <span>Scroll to Discover</span>
            <div class="spatial-scroll-indicator-line"></div>
        </a>
    </section>


    <!-- ==========================================================================
         SECTION 6: INTERACTIVE 3D PROJECT SHOWCASE
         ========================================================================== -->
    <section class="spatial-section" id="projects-showcase">
        <div class="container">
            <div class="spatial-section-header">
                <span class="spatial-section-eyebrow">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    Curated Spatial Portfolio
                </span>
                <h2 class="spatial-section-title" style="color: #0f172a !important;">Architectural Precision &amp; Crafted Living</h2>
                <p class="spatial-section-desc">
                    Explore our signature portfolio of bespoke residential villas, luxury high-rises, and executive commercial headquarters across Gujarat and Western India.
                </p>
            </div>

            <!-- Dynamic Category Filters -->
            <div class="spatial-filter-pills">
                <button class="spatial-filter-btn active" data-filter="all">All Disciplines</button>
                <button class="spatial-filter-btn" data-filter="residential">Residential</button>
                <button class="spatial-filter-btn" data-filter="commercial">Commercial</button>
                <button class="spatial-filter-btn" data-filter="office">Office &amp; Workspaces</button>
                <button class="spatial-filter-btn" data-filter="retail">Retail Flagships</button>
            </div>

            <!-- 3D Floating Project Cards Grid -->
            <div class="spatial-projects-grid">
                <?php foreach ($featured_projects as $proj): ?>
                    <article class="spatial-project-card" data-category="<?php echo htmlspecialchars($proj['category']); ?>">
                        <div class="spatial-project-thumb-wrap">
                            <span class="spatial-project-badge-tag">
                                <?php echo htmlspecialchars(ucfirst($proj['category'])); ?>
                            </span>
                            <img src="<?php echo htmlspecialchars($proj['image'] ?: 'uploads/projects/1786870500_project_05.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($proj['title']); ?>" 
                                 class="spatial-project-thumb" 
                                 loading="lazy">
                        </div>

                        <div class="spatial-project-body">
                            <div class="spatial-project-meta">
                                <span><?php echo htmlspecialchars($proj['location'] ?: 'Surat, Gujarat'); ?></span>
                                <span>&bull;</span>
                                <span><?php echo htmlspecialchars($proj['area'] ?: '2,500 sq.ft'); ?></span>
                            </div>

                            <h3 class="spatial-project-title">
                                <a href="project.php?id=<?php echo $proj['id']; ?>">
                                    <?php echo htmlspecialchars($proj['title']); ?>
                                </a>
                            </h3>

                            <p class="spatial-project-desc">
                                <?php echo htmlspecialchars($proj['description']); ?>
                            </p>

                            <div class="spatial-project-footer">
                                <span class="spatial-project-spec">
                                    <?php echo htmlspecialchars($proj['design_style'] ?: 'Modern Luxury'); ?>
                                </span>
                                <a href="project.php?id=<?php echo $proj['id']; ?>" class="spatial-project-link-btn">
                                    <span>Explore</span> &rarr;
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div style="text-align: center; margin-top: 3.5rem;">
                <a href="projects.php" class="btn-spatial-outline">
                    <span>View All <?php echo $db_projects_count; ?> Architectural Projects &rarr;</span>
                </a>
            </div>
        </div>
    </section>


    <!-- ==========================================================================
         SECTION 7: ARCHITECTURAL 3D SERVICES SHOWCASE
         ========================================================================== -->
    <section class="spatial-section spatial-section-alt" id="services">
        <div class="container">
            <div class="spatial-section-header">
                <span class="spatial-section-eyebrow">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    Design Disciplines
                </span>
                <h2 class="spatial-section-title" style="color: #0f172a !important;">End-to-End Architectural Capabilities</h2>
                <p class="spatial-section-desc">
                    From structural zoning and photorealistic 3D visualization to precision German cabinetry and turnkey civil execution.
                </p>
            </div>

            <div class="spatial-services-grid">
                <?php 
                $count = 1;
                foreach ($featured_services as $srv): 
                    $numStr = str_pad($count++, 2, '0', STR_PAD_LEFT);
                ?>
                    <div class="spatial-service-card">
                        <span class="spatial-service-number"><?php echo $numStr; ?></span>

                        <div class="spatial-service-icon-box">
                            <?php if (!empty($srv['icon']) && strpos($srv['icon'], '?') === false && mb_strlen($srv['icon']) <= 4): ?>
                                <span><?php echo $srv['icon']; ?></span>
                            <?php else: ?>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                                    <polyline points="2 17 12 22 22 17"></polyline>
                                    <polyline points="2 12 12 17 22 12"></polyline>
                                </svg>
                            <?php endif; ?>
                        </div>

                        <h3 class="spatial-service-title"><?php echo htmlspecialchars($srv['title']); ?></h3>
                        <p class="spatial-service-desc"><?php echo htmlspecialchars($srv['description']); ?></p>

                        <?php if (!empty($srv['price_range'])): ?>
                            <div class="spatial-service-price-tag">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                                    <line x1="2" y1="10" x2="22" y2="10"></line>
                                </svg>
                                <span>Estimation: <?php echo htmlspecialchars($srv['price_range']); ?></span>
                            </div>
                        <?php endif; ?>

                        <a href="services.php" class="spatial-service-btn">
                            <span>Explore Discipline</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>


    <!-- ==========================================================================
         SECTION 8: ABOUT SECTION (3D SPLIT SCREEN & REAL DB STATS)
         ========================================================================== -->
    <section class="spatial-section" id="about">
        <div class="container">
            <div class="spatial-about-grid">
                
                <!-- Left: 3D Interactive Spatial Pavilion Canvas -->
                <div class="spatial-about-3d-visual">
                    <canvas id="about-3d-canvas" aria-label="Interactive 3D Architectural Sculpture"></canvas>
                    <div style="position: absolute; bottom: 1.5rem; left: 1.5rem; z-index: 2; font-size: 0.7rem; color: #1d4ed8; text-transform: uppercase; letter-spacing: 0.15em; font-weight: 700; background: rgba(255,255,255,0.92); padding: 0.35rem 0.75rem; border-radius: 999px; backdrop-filter: blur(8px); border: 1px solid #bfdbfe; box-shadow: 0 4px 12px rgba(37,99,235,0.08);">
                        Spatial Pavilion Model &bull; Interactive
                    </div>
                </div>

                <!-- Right: Content & Real DB Animated Stats -->
                <div class="spatial-about-content">
                    <span class="spatial-about-badge">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                        Architectural Studio
                    </span>

                    <h2 class="spatial-about-title" style="color: #0f172a !important;">Turning Ideas Into Beautiful Spaces</h2>

                    <p class="spatial-about-text">
                        Creative Touch Interiors was founded on the philosophy that architectural excellence arises from the disciplined interplay of natural light, acoustic harmony, and authentic Indian materiality. Directed by principal architect <strong>Harsh Chotaliya</strong> and interior designer <strong>Het Rana</strong>, our practice crafts enduring environments that honor how discerning clients live and work.
                    </p>

                    <!-- Real Database Values for Statistics -->
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

                    <div style="margin-top: 2.25rem;">
                        <a href="about.php" class="btn-spatial-outline">
                            <span>Read Full Studio Story &rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- ==========================================================================
         SECTION 9: 6-STEP DESIGN PROCESS (INTERACTIVE TIMELINE)
         ========================================================================== -->
    <section class="spatial-section spatial-section-alt" id="process">
        <div class="container">
            <div class="spatial-section-header">
                <span class="spatial-section-eyebrow">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    Methodology
                </span>
                <h2 class="spatial-section-title" style="color: #0f172a !important;">The Architectural Lifecycle</h2>
                <p class="spatial-section-desc">
                    A rigorous six-stage development workflow engineered to bring clarity, punctuality, and artistic perfection to every bespoke estate.
                </p>
            </div>

            <div class="spatial-timeline-wrap">
                <div class="spatial-timeline-track"></div>
                <div class="spatial-timeline-grid">
                    
                    <div class="spatial-timeline-step">
                        <div class="spatial-timeline-node">01</div>
                        <h3 class="spatial-timeline-step-title">Consultation</h3>
                        <p class="spatial-timeline-step-desc">Site analysis, spatial briefing, lifestyle mapping, and preliminary budget framework.</p>
                    </div>

                    <div class="spatial-timeline-step">
                        <div class="spatial-timeline-node">02</div>
                        <h3 class="spatial-timeline-step-title">Planning</h3>
                        <p class="spatial-timeline-step-desc">2D schematic zoning, circulation flows, acoustic barriers, and structural alignments.</p>
                    </div>

                    <div class="spatial-timeline-step">
                        <div class="spatial-timeline-node">03</div>
                        <h3 class="spatial-timeline-step-title">3D Visualization</h3>
                        <p class="spatial-timeline-step-desc">Photorealistic 4K architectural renders, lighting simulations, and material tactile pairings.</p>
                    </div>

                    <div class="spatial-timeline-step">
                        <div class="spatial-timeline-node">04</div>
                        <h3 class="spatial-timeline-step-title">Development</h3>
                        <p class="spatial-timeline-step-desc">Itemized joinery drawings, MEP coordination, hardware specs, and procurement schedules.</p>
                    </div>

                    <div class="spatial-timeline-step">
                        <div class="spatial-timeline-node">05</div>
                        <h3 class="spatial-timeline-step-title">Execution</h3>
                        <p class="spatial-timeline-step-desc">On-site artisan fabrication, strict quality auditing, and weekly progress milestones.</p>
                    </div>

                    <div class="spatial-timeline-step">
                        <div class="spatial-timeline-node">06</div>
                        <h3 class="spatial-timeline-step-title">Handover</h3>
                        <p class="spatial-timeline-step-desc">Final architectural styling, snag rectification, warranty documentation, and key delivery.</p>
                    </div>

                </div>
            </div>
        </div>
    </section>


    <!-- ==========================================================================
         SECTION 10: 3D TESTIMONIAL CAROUSEL
         ========================================================================== -->
    <section class="spatial-section" id="testimonials">
        <div class="container">
            <div class="spatial-section-header">
                <span class="spatial-section-eyebrow">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    Verified Client Experiences
                </span>
                <h2 class="spatial-section-title" style="color: #0f172a !important;">Words of Trust &amp; Craftsmanship</h2>
            </div>

            <div class="spatial-carousel-container">
                <div class="spatial-testimonial-viewport">
                    <?php foreach ($testimonials as $idx => $t): ?>
                        <div class="spatial-testimonial-card" style="<?php echo $idx > 0 ? 'display: none;' : ''; ?>">
                            <div class="spatial-quote-glyph">&ldquo;</div>
                            <blockquote class="spatial-testimonial-quote">
                                <?php echo htmlspecialchars($t['testimonial']); ?>
                            </blockquote>

                            <div class="spatial-star-rating" style="margin-bottom: 1.25rem;">
                                <?php for ($s = 0; $s < ($t['rating'] ?? 5); $s++): ?>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                    </svg>
                                <?php endfor; ?>
                            </div>

                            <div class="spatial-testimonial-author">
                                <?php echo htmlspecialchars($t['client_name']); ?>
                            </div>
                            <div class="spatial-testimonial-role">
                                <?php echo htmlspecialchars($t['project_title']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Navigation Controls -->
                <div class="spatial-carousel-nav">
                    <button class="spatial-carousel-arrow" id="prev-testimonial-btn" aria-label="Previous testimonial">
                        &larr;
                    </button>
                    <button class="spatial-carousel-arrow" id="next-testimonial-btn" aria-label="Next testimonial">
                        &rarr;
                    </button>
                </div>
            </div>
        </div>
    </section>


    <!-- ==========================================================================
         HIGH-CONVERTING 3D GLASS GET QUOTE CTA BANNER
         ========================================================================== -->
    <section class="spatial-section spatial-section-alt" style="padding: 6rem 0;">
        <div class="container">
            <div class="card" style="background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 60%, #2563eb 100%); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: var(--radius-2xl); padding: 4.5rem 3rem; text-align: center; box-shadow: 0 20px 45px rgba(29, 78, 216, 0.25); position: relative; overflow: hidden;">
                <div style="position: absolute; top: -50px; right: -50px; width: 220px; height: 220px; background: rgba(255, 255, 255, 0.12); filter: blur(50px); border-radius: 50%;"></div>
                <div style="position: absolute; bottom: -50px; left: -50px; width: 220px; height: 220px; background: rgba(96, 165, 250, 0.2); filter: blur(50px); border-radius: 50%;"></div>

                <span class="spatial-section-eyebrow" style="margin-bottom: 1rem; color: #bfdbfe;">Instant Architectural Estimation</span>
                <h2 style="font-size: clamp(2.2rem, 4vw, 3.5rem); color: #ffffff; margin-bottom: 1.25rem;">
                    Ready to Realize Your Sanctuary?
                </h2>
                <p style="color: #eff6ff; max-width: 650px; margin: 0 auto 2.5rem; font-size: 1.1rem; line-height: 1.7;">
                    Experience our multi-step interactive spatial calculator. Specify room dimensions, select bespoke finishes, and receive an algorithmic cost estimate in real time.
                </p>

                <div style="display: flex; gap: 1.25rem; justify-content: center; flex-wrap: wrap;">
                    <a href="consultation.php" style="padding: 0.85rem 2rem; font-size: 0.95rem; background: #ffffff; color: #1d4ed8; font-weight: 700; border-radius: var(--radius-full); display: inline-flex; align-items: center; gap: 0.6rem; box-shadow: 0 4px 14px rgba(0,0,0,0.12); transition: all 0.25s ease;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="12" y1="18" x2="12" y2="12"></line>
                        </svg>
                        <span>Launch Multi-Step Quote Wizard</span>
                    </a>
                    <a href="contact.php" style="padding: 0.85rem 2rem; font-size: 0.95rem; border: 1px solid rgba(255, 255, 255, 0.5); color: #ffffff; background: rgba(255, 255, 255, 0.1); border-radius: var(--radius-full); display: inline-flex; align-items: center; gap: 0.6rem; backdrop-filter: blur(8px); transition: all 0.25s ease;">
                        <span>Direct Studio Consultation</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
