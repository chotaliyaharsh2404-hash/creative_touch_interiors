<?php
require_once 'includes/config.php';

$page_title = 'Interior Architecture Capabilities & Turnkey Services — Creative Touch Interiors';

// Fetch all active services ordered by featured first, then id
$services_sql = "SELECT * FROM services WHERE status = 'active' ORDER BY featured DESC, id ASC";
$services_result = $conn->query($services_sql);

$services = [];
$cat_counts = [
    'all' => 0,
    'residential' => 0,
    'office' => 0,
    'retail' => 0,
    'commercial' => 0,
    'design' => 0
];

if ($services_result && $services_result->num_rows > 0) {
    while ($row = $services_result->fetch_assoc()) {
        $services[] = $row;
        $cat_counts['all']++;
        $c = strtolower($row['category'] ?? '');
        if (strpos($c, 'resident') !== false) $cat_counts['residential']++;
        elseif (strpos($c, 'office') !== false) $cat_counts['office']++;
        elseif (strpos($c, 'retail') !== false) $cat_counts['retail']++;
        elseif (strpos($c, 'commercial') !== false) $cat_counts['commercial']++;
        else $cat_counts['design']++;
    }
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
                <span>Bespoke Design Spectrum</span>
            </div>
            <h1 class="spatial-hero-title" style="font-size: clamp(2.4rem, 5vw, 4rem); margin-bottom: 1rem; color: #0f172a;">
                Architectural Disciplines &amp; Turnkey Execution
            </h1>
            <p class="spatial-hero-subtitle" style="margin-bottom: 0; color: #475569;">
                From bare-shell villa structural masterplanning to precision German modular kitchens and high-performance corporate suites.
            </p>
        </div>
    </section>

    <!-- Filter & Live Search Toolbar -->
    <section style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 1.25rem 0; position: sticky; top: 80px; z-index: 90; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);">
        <div class="container">
            <div style="display: flex; flex-wrap: wrap; gap: 1.25rem; align-items: center; justify-content: space-between;">
                
                <!-- Category Pills -->
                <div class="spatial-filter-pills" style="margin-bottom: 0;">
                    <button type="button" class="spatial-filter-btn active" onclick="filterServices('all', this)">
                        All Capabilities (<?php echo $cat_counts['all']; ?>)
                    </button>
                    <button type="button" class="spatial-filter-btn" onclick="filterServices('residential', this)">
                        Residential (<?php echo $cat_counts['residential']; ?>)
                    </button>
                    <button type="button" class="spatial-filter-btn" onclick="filterServices('office', this)">
                        Workspaces (<?php echo $cat_counts['office']; ?>)
                    </button>
                    <button type="button" class="spatial-filter-btn" onclick="filterServices('retail', this)">
                        Retail Flagships (<?php echo $cat_counts['retail']; ?>)
                    </button>
                    <button type="button" class="spatial-filter-btn" onclick="filterServices('commercial', this)">
                        Commercial (<?php echo $cat_counts['commercial']; ?>)
                    </button>
                </div>

                <!-- Live Search Box -->
                <div style="position: relative; min-width: 290px;">
                    <input type="text" id="serviceSearchInput" oninput="searchServices(this.value)" placeholder="Search capabilities..." class="spatial-form-control" style="padding-left: 2.75rem; padding-right: 1rem; border-radius: var(--radius-full); font-size: 0.875rem;" aria-label="Search design services">
                    <span aria-hidden="true" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--color-bronze); pointer-events: none;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- Services 3D Grid -->
    <section class="spatial-section" style="padding: 5rem 0 7rem;">
        <div class="container">
            <div class="spatial-services-grid" id="servicesListing">
                <?php 
                $count = 1;
                foreach ($services as $srv): 
                    $numStr = str_pad($count++, 2, '0', STR_PAD_LEFT);
                    $catClass = strtolower($srv['category'] ?? 'other');
                ?>
                    <div class="spatial-service-card" data-category="<?php echo htmlspecialchars($catClass); ?>">
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

                        <div style="font-size: 0.75rem; color: var(--color-bronze-light); text-transform: uppercase; font-weight: 700; letter-spacing: 0.12em; margin-bottom: 0.4rem;">
                            <?php echo htmlspecialchars($srv['category'] ?: 'Turnkey Architectural'); ?>
                        </div>

                        <h3 class="spatial-service-title"><?php echo htmlspecialchars($srv['title']); ?></h3>
                        <p class="spatial-service-desc"><?php echo htmlspecialchars($srv['description']); ?></p>

                        <?php if (!empty($srv['price_range'])): ?>
                            <div class="spatial-service-price-tag">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                                    <line x1="2" y1="10" x2="22" y2="10"></line>
                                </svg>
                                <span>Budget Range: <?php echo htmlspecialchars($srv['price_range']); ?></span>
                            </div>
                        <?php endif; ?>

                        <a href="consultation.php" class="spatial-service-btn">
                            <span>Configure In Quote Wizard</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Architectural Commitment Section -->
    <section class="spatial-section spatial-section-alt" style="padding: 6rem 0;">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2.5rem;">
                <div style="background: var(--glass-bg-card); border: 1px solid var(--glass-border); border-radius: var(--radius-xl); padding: 2.5rem 2rem;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(37, 99, 235, 0.1); border: 1px solid var(--color-bronze); display: flex; align-items: center; justify-content: center; color: var(--color-bronze); font-weight: 800; margin-bottom: 1.25rem;">
                        01
                    </div>
                    <h3 style="font-size: 1.35rem; color: #0f172a; margin-bottom: 0.75rem;">Algorithmic Transparency</h3>
                    <p style="color: var(--color-stone-light); font-size: 0.925rem; line-height: 1.7;">
                        No hidden contingencies. Every quotation is calculated item-by-item based on verified room carpet areas and clear material grades.
                    </p>
                </div>

                <div style="background: var(--glass-bg-card); border: 1px solid var(--glass-border); border-radius: var(--radius-xl); padding: 2.5rem 2rem;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(37, 99, 235, 0.1); border: 1px solid var(--color-bronze); display: flex; align-items: center; justify-content: center; color: var(--color-bronze); font-weight: 800; margin-bottom: 1.25rem;">
                        02
                    </div>
                    <h3 style="font-size: 1.35rem; color: #0f172a; margin-bottom: 0.75rem;">Turnkey Civil Oversight</h3>
                    <p style="color: var(--color-stone-light); font-size: 0.925rem; line-height: 1.7;">
                        Our senior architects personally supervise on-site execution, maintaining strict laser tolerances for carpentry, joinery, and MEP installations.
                    </p>
                </div>

                <div style="background: var(--glass-bg-card); border: 1px solid var(--glass-border); border-radius: var(--radius-xl); padding: 2.5rem 2rem;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: rgba(37, 99, 235, 0.1); border: 1px solid var(--color-bronze); display: flex; align-items: center; justify-content: center; color: var(--color-bronze); font-weight: 800; margin-bottom: 1.25rem;">
                        03
                    </div>
                    <h3 style="font-size: 1.35rem; color: #0f172a; margin-bottom: 0.75rem;">10-Year Craft Guarantee</h3>
                    <p style="color: var(--color-stone-light); font-size: 0.925rem; line-height: 1.7;">
                        We stand behind our bespoke hardware, German fittings, and timber joinery with comprehensive post-handover warranty support.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Quote Banner -->
    <section class="spatial-section" style="padding: 6rem 0; text-align: center;">
        <div class="container" style="max-width: 800px;">
            <span class="spatial-section-eyebrow">Ready to Calculate?</span>
            <h2 class="spatial-section-title" style="margin-bottom: 1.25rem; color: #0f172a !important;">Configure Your Space in 3D</h2>
            <p style="color: var(--color-stone-light); font-size: 1.1rem; line-height: 1.7; margin-bottom: 2.5rem;">
                Select your property type, enter room measurements, and receive an algorithmic itemized quote instantly.
            </p>
            <a href="consultation.php" class="btn-spatial-bronze">
                <span>Start Multi-Step Quote Wizard &rarr;</span>
            </a>
        </div>
    </section>

    <script>
    function filterServices(category, btn) {
        document.querySelectorAll('.spatial-filter-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');

        const cards = document.querySelectorAll('.spatial-service-card');
        cards.forEach(card => {
            const cardCat = card.getAttribute('data-category') || '';
            if (category === 'all' || cardCat.includes(category)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function searchServices(query) {
        const q = query.toLowerCase().trim();
        const cards = document.querySelectorAll('.spatial-service-card');
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            if (!q || text.includes(q)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }
    </script>

<?php include 'includes/footer.php'; ?>
