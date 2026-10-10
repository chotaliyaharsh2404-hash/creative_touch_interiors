<?php
require_once 'includes/config.php';

$page_title = 'Design Lookbook & 3D Spatial Visuals — Creative Touch Interiors';

// Fetch gallery images
$gallery_sql = "SELECT * FROM gallery_images ORDER BY featured DESC, order_index ASC, id DESC";
$gallery_result = $conn->query($gallery_sql);

$images = [];
$cat_counts = [
    'all' => 0,
    'living_room' => 0,
    'bedroom' => 0,
    'kitchen' => 0,
    'bathroom' => 0,
    'office' => 0
];

if ($gallery_result && $gallery_result->num_rows > 0) {
    while ($row = $gallery_result->fetch_assoc()) {
        $images[] = $row;
        $cat_counts['all']++;
        $c = strtolower($row['category'] ?? '');
        if (isset($cat_counts[$c])) {
            $cat_counts[$c]++;
        }
    }
}

$initial_category = isset($_GET['category']) ? strtolower(sanitize($_GET['category'])) : 'all';

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
                <span>Visual Inspiration Lookbook</span>
            </div>
            <h1 class="spatial-hero-title" style="font-size: clamp(2.4rem, 5vw, 4rem); margin-bottom: 1rem; color: #0f172a;">
                Architectural Design Lookbook
            </h1>
            <p class="spatial-hero-subtitle" style="margin-bottom: 0; color: #475569;">
                High-resolution architectural perspectives of handcrafted living rooms, master bedroom suites, modular kitchens, and luxury executive suites.
            </p>
        </div>
    </section>

    <!-- Filters & Search Toolbar -->
    <section style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 1.25rem 0; position: sticky; top: 80px; z-index: 90; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);">
        <div class="container">
            <div style="display: flex; flex-wrap: wrap; gap: 1.25rem; align-items: center; justify-content: space-between;">
                
                <!-- Category Pills -->
                <div class="spatial-filter-pills" style="margin-bottom: 0;">
                    <button type="button" class="spatial-filter-btn <?php echo $initial_category == 'all' ? 'active' : ''; ?>" onclick="filterGallery('all', this)">
                        All Curation (<?php echo $cat_counts['all']; ?>)
                    </button>
                    <button type="button" class="spatial-filter-btn <?php echo $initial_category == 'living_room' ? 'active' : ''; ?>" onclick="filterGallery('living_room', this)">
                        Living Rooms (<?php echo $cat_counts['living_room']; ?>)
                    </button>
                    <button type="button" class="spatial-filter-btn <?php echo $initial_category == 'bedroom' ? 'active' : ''; ?>" onclick="filterGallery('bedroom', this)">
                        Master Suites (<?php echo $cat_counts['bedroom']; ?>)
                    </button>
                    <button type="button" class="spatial-filter-btn <?php echo $initial_category == 'kitchen' ? 'active' : ''; ?>" onclick="filterGallery('kitchen', this)">
                        Modular Kitchens (<?php echo $cat_counts['kitchen']; ?>)
                    </button>
                    <button type="button" class="spatial-filter-btn <?php echo $initial_category == 'office' ? 'active' : ''; ?>" onclick="filterGallery('office', this)">
                        Workspaces (<?php echo $cat_counts['office']; ?>)
                    </button>
                </div>

                <!-- Live Search Box -->
                <div style="position: relative; min-width: 290px;">
                    <input type="text" id="gallerySearchInput" oninput="searchGallery(this.value)" placeholder="Search lookbook by room, style..." class="spatial-form-control" style="padding-left: 2.75rem; padding-right: 1rem; border-radius: var(--radius-full); font-size: 0.875rem;" aria-label="Search lookbook">
                    <span aria-hidden="true" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--color-bronze); pointer-events: none;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery 3D Grid -->
    <section class="spatial-section" style="padding: 5rem 0 7rem;">
        <div class="container">
            <div style="margin-bottom: 2rem; font-size: 0.9rem; color: var(--color-stone);">
                Presenting <strong id="visibleImagesCount" style="color: #0f172a;"><?php echo count($images); ?></strong> curated perspectives
            </div>

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.75rem;" id="galleryGrid">
                <?php if (!empty($images)): ?>
                    <?php foreach ($images as $index => $img): 
                        $cat_clean = strtolower($img['category'] ?? 'all');
                        $image_src = htmlspecialchars($img['image_path']);
                        $title = htmlspecialchars($img['title']);
                    ?>
                        <div class="gallery-item-spatial" data-category="<?php echo htmlspecialchars($cat_clean); ?>" onclick="openLightbox('<?php echo $image_src; ?>', '<?php echo addslashes($title); ?>', '<?php echo ucfirst(str_replace('_', ' ', $cat_clean)); ?>')" style="aspect-ratio: 1; border-radius: var(--radius-xl); overflow: hidden; background: var(--color-charcoal); position: relative; cursor: pointer; border: 1px solid var(--glass-border); box-shadow: var(--shadow-spatial-sm); transition: var(--transition-spatial);" onmouseover="this.style.transform='translateY(-6px)'; this.style.borderColor='var(--color-bronze-border)';" onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='var(--glass-border)';">
                            <img src="<?php echo $image_src; ?>" 
                                 alt="<?php echo $title; ?>" 
                                 loading="lazy" 
                                 style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s var(--ease-out-expo);"
                                 onmouseover="this.style.transform='scale(1.08)';"
                                 onmouseout="this.style.transform='scale(1)';">
                            
                            <div style="position: absolute; inset: 0; background: linear-gradient(180deg, transparent 40%, rgba(7, 8, 10, 0.85) 100%); display: flex; flex-direction: column; justify-content: flex-end; padding: 1.25rem; opacity: 0; transition: opacity 0.3s ease;" onmouseover="this.style.opacity='1';" onmouseout="this.style.opacity='0';">
                                <span style="font-size: 0.7rem; color: var(--color-bronze-light); text-transform: uppercase; font-weight: 700; letter-spacing: 0.1em; margin-bottom: 0.2rem;">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $cat_clean))); ?>
                                </span>
                                <h4 style="font-size: 1rem; color: #ffffff; margin: 0;"><?php echo $title; ?></h4>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Luxury Spatial Lightbox Modal -->
    <div id="spatialLightbox" style="position: fixed; inset: 0; background: rgba(5, 6, 8, 0.95); backdrop-filter: blur(25px); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 2rem;">
        <button onclick="closeLightbox()" style="position: absolute; top: 2rem; right: 2rem; width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,0.08); border: 1px solid var(--glass-border); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
            &times;
        </button>

        <div style="max-width: 1000px; width: 100%; text-align: center;">
            <img id="lightboxImg" src="" alt="Lookbook Full View" style="max-height: 75vh; max-width: 100%; margin: 0 auto; border-radius: var(--radius-xl); border: 1px solid var(--glass-border); box-shadow: var(--shadow-spatial-3d); object-fit: contain;">
            <div style="margin-top: 1.5rem;">
                <span id="lightboxCategory" style="font-size: 0.75rem; color: var(--color-bronze-light); text-transform: uppercase; font-weight: 700; letter-spacing: 0.15em;"></span>
                <h3 id="lightboxTitle" style="font-size: 1.5rem; color: #ffffff; margin-top: 0.35rem;"></h3>
            </div>
        </div>
    </div>

    <script>
    function filterGallery(category, btn) {
        document.querySelectorAll('.spatial-filter-pills .spatial-filter-btn').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');

        let visibleCount = 0;
        const items = document.querySelectorAll('.gallery-item-spatial');
        items.forEach(item => {
            const itemCat = item.getAttribute('data-category') || '';
            if (category === 'all' || itemCat.includes(category)) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        document.getElementById('visibleImagesCount').textContent = visibleCount;
    }

    function searchGallery(query) {
        const q = query.toLowerCase().trim();
        let visibleCount = 0;
        const items = document.querySelectorAll('.gallery-item-spatial');
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (!q || text.includes(q)) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        document.getElementById('visibleImagesCount').textContent = visibleCount;
    }

    function openLightbox(src, title, category) {
        const modal = document.getElementById('spatialLightbox');
        document.getElementById('lightboxImg').src = src;
        document.getElementById('lightboxTitle').textContent = title;
        document.getElementById('lightboxCategory').textContent = category;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        const modal = document.getElementById('spatialLightbox');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }

    document.getElementById('spatialLightbox').addEventListener('click', function(e) {
        if (e.target === this) closeLightbox();
    });
    </script>

<?php include 'includes/footer.php'; ?>
