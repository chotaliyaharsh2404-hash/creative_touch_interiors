<?php
require_once 'includes/config.php';

$page_title = 'Signature Works & 3D Spatial Commissions — Creative Touch Interiors';

// Fetch projects with category filtering from MySQL
$category = isset($_GET['category']) ? sanitize($_GET['category']) : 'all';

if ($category == 'all') {
    $projects_sql = "SELECT * FROM projects ORDER BY featured DESC, created_at DESC";
    $projects_result = $conn->query($projects_sql);
} else {
    $projects_sql = "SELECT * FROM projects WHERE category = ? ORDER BY featured DESC, created_at DESC";
    $stmt = $conn->prepare($projects_sql);
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $projects_result = $stmt->get_result();
}

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
                <span>Architectural Portfolio</span>
            </div>
            <h1 class="spatial-hero-title" style="font-size: clamp(2.4rem, 5vw, 4rem); margin-bottom: 1rem; color: #0f172a;">
                Signature Works &amp; Spatial Commissions
            </h1>
            <p class="spatial-hero-subtitle" style="margin-bottom: 0; color: #475569;">
                A curated survey of luxury private estates, penthouse sanctuaries, and corporate headquarters tailored to precision living.
            </p>
        </div>
    </section>

    <!-- Filter & Search Bar -->
    <section style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 1.25rem 0; position: sticky; top: 80px; z-index: 90; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);">
        <div class="container">
            <div style="display: flex; flex-wrap: wrap; gap: 1.25rem; align-items: center; justify-content: space-between;">
                
                <!-- Category Pills -->
                <div class="spatial-filter-pills" style="margin-bottom: 0;">
                    <a href="projects.php?category=all" class="spatial-filter-btn <?php echo $category == 'all' ? 'active' : ''; ?>">All Portfolios</a>
                    <a href="projects.php?category=residential" class="spatial-filter-btn <?php echo $category == 'residential' ? 'active' : ''; ?>">Residential</a>
                    <a href="projects.php?category=commercial" class="spatial-filter-btn <?php echo $category == 'commercial' ? 'active' : ''; ?>">Commercial</a>
                    <a href="projects.php?category=office" class="spatial-filter-btn <?php echo $category == 'office' ? 'active' : ''; ?>">Workspaces</a>
                    <a href="projects.php?category=retail" class="spatial-filter-btn <?php echo $category == 'retail' ? 'active' : ''; ?>">Retail Flagships</a>
                </div>

                <!-- Live Search Input -->
                <div style="position: relative; min-width: 290px;">
                    <input type="text" id="projectSearchInput" placeholder="Search by name, city, style..." class="spatial-form-control" style="padding-left: 2.75rem; padding-right: 1rem; border-radius: var(--radius-full); font-size: 0.875rem;" aria-label="Search projects">
                    <span aria-hidden="true" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--color-bronze); pointer-events: none;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- Projects 3D Grid -->
    <section class="spatial-section" style="padding: 5rem 0 7rem;">
        <div class="container">
            <div class="spatial-projects-grid" id="projectsGrid">
                <?php if ($projects_result && $projects_result->num_rows > 0): ?>
                    <?php while ($proj = $projects_result->fetch_assoc()): ?>
                        <article class="spatial-project-card" data-category="<?php echo htmlspecialchars($proj['category']); ?>">
                            <div class="spatial-project-thumb-wrap">
                                <span class="spatial-project-badge-tag">
                                    <?php echo htmlspecialchars(ucfirst($proj['category'] ?? 'Residential')); ?>
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
                                        <span>View Details</span> &rarr;
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 5rem 1rem;">
                        <p style="color: var(--color-stone-light); font-size: 1.15rem; margin-bottom: 1.5rem;">
                            No architectural projects found matching this selection.
                        </p>
                        <a href="projects.php" class="btn-spatial-bronze">Explore All Commissions</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Consultation CTA -->
    <section class="spatial-section spatial-section-alt" style="padding: 6rem 0; text-align: center;">
        <div class="container" style="max-width: 800px;">
            <span class="spatial-section-eyebrow">Commissioning</span>
            <h2 class="spatial-section-title" style="margin-bottom: 1.25rem; color: #0f172a !important;">Envisioning a Similar Space?</h2>
            <p style="color: var(--color-stone-light); font-size: 1.1rem; line-height: 1.7; margin-bottom: 2.5rem;">
                Our principal architects are available for private consultations to evaluate floorplans, structural opportunities, and bespoke budgets.
            </p>
            <div style="display: flex; gap: 1.25rem; justify-content: center; flex-wrap: wrap;">
                <a href="consultation.php" class="btn-spatial-bronze">
                    <span>Calculate Custom Quote</span> &rarr;
                </a>
                <a href="contact.php" class="btn-spatial-outline">
                    <span>Visit Surat Studio</span>
                </a>
            </div>
        </div>
    </section>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('projectSearchInput');
        const cards = document.querySelectorAll('.spatial-project-card');
        if (searchInput && cards.length) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                cards.forEach(card => {
                    const text = card.textContent.toLowerCase();
                    if (!query || text.includes(query)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }
    });
    </script>

<?php include 'includes/footer.php'; ?>
