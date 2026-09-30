<?php
require_once 'includes/config.php';

// Check if viewing single post by slug or id
$slug = isset($_GET['slug']) ? sanitize($_GET['slug']) : '';
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$single_post = null;

if (!empty($slug) || $post_id > 0) {
    if (!empty($slug)) {
        $stmt = $conn->prepare("SELECT * FROM blogs WHERE slug = ? AND (status = 'published' OR ? = 1)");
        $isAdm = isAdminLoggedIn() ? 1 : 0;
        $stmt->bind_param("si", $slug, $isAdm);
    } else {
        $stmt = $conn->prepare("SELECT * FROM blogs WHERE id = ? AND (status = 'published' OR ? = 1)");
        $isAdm = isAdminLoggedIn() ? 1 : 0;
        $stmt->bind_param("ii", $post_id, $isAdm);
    }
    $stmt->execute();
    $single_post = $stmt->get_result()->fetch_assoc();
}

// ----------------------------------------------------
// 1. SINGLE POST VIEW
// ----------------------------------------------------
if ($single_post):
    $page_title = htmlspecialchars($single_post['title']) . ' — Editorial Journal | Creative Touch Interiors';
    $meta_description = htmlspecialchars($single_post['excerpt']);
    
    // Fetch related articles (same category or latest, excluding current)
    $rel_stmt = $conn->prepare("SELECT * FROM blogs WHERE id != ? AND status = 'published' ORDER BY (category = ?) DESC, created_at DESC LIMIT 3");
    $rel_stmt->bind_param("is", $single_post['id'], $single_post['category']);
    $rel_stmt->execute();
    $related_posts = $rel_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    include 'includes/header.php';
?>
<!-- Top Reading Progress Indicator -->
<div id="readingProgress" style="position: fixed; top: 0; left: 0; height: 3px; background: linear-gradient(90deg, #1d4ed8, #3b82f6); width: 0%; z-index: 99999; transition: width 0.1s ease;"></div>

<main class="page-content" style="padding-top: 100px; background: #ffffff; min-height: 100vh;">
    <!-- Article Header Hero -->
    <section class="section" style="padding: 3rem 0 2rem; position: relative;">
        <div class="container" style="max-width: 960px; position: relative; z-index: 2;">
            
            <!-- Breadcrumbs -->
            <nav style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: #64748b; margin-bottom: 2rem;">
                <a href="index.php" style="color: #64748b; text-decoration: none; transition: var(--transition-fast);">Home</a>
                <span>/</span>
                <a href="blog.php" style="color: #64748b; text-decoration: none; transition: var(--transition-fast);">Journal</a>
                <span>/</span>
                <span style="color: #2563eb; font-weight: 600;"><?php echo htmlspecialchars($single_post['category']); ?></span>
            </nav>

            <div style="display: flex; gap: 0.75rem; align-items: center; margin-bottom: 1.25rem;">
                <span class="badge" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.75rem; letter-spacing: 0.08em; text-transform: uppercase; font-weight: 700; padding: 0.35rem 0.8rem; border-radius: 9999px;">
                    <?php echo htmlspecialchars($single_post['category']); ?>
                </span>
                <span style="color: #64748b; font-size: 0.8rem; display: flex; align-items: center; gap: 0.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?php echo htmlspecialchars($single_post['reading_time']); ?>
                </span>
            </div>

            <h1 style="font-family: var(--font-heading); font-size: clamp(2rem, 4vw, 3.25rem); font-weight: 800; color: #0f172a; line-height: 1.2; letter-spacing: -0.02em; margin-bottom: 1.5rem;">
                <?php echo htmlspecialchars($single_post['title']); ?>
            </h1>

            <p style="font-size: 1.15rem; color: #475569; line-height: 1.7; margin-bottom: 2rem; font-style: italic; border-left: 3px solid #2563eb; padding-left: 1.25rem;">
                <?php echo htmlspecialchars($single_post['excerpt']); ?>
            </p>

            <!-- Author & Date Bar -->
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding-bottom: 2rem; border-bottom: 1px solid #e2e8f0;">
                <div style="display: flex; align-items: center; gap: 0.85rem;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #eff6ff; border: 1.5px solid #2563eb; display: flex; align-items: center; justify-content: center; color: #1d4ed8; font-weight: 700; font-size: 0.95rem;">
                        <?php echo strtoupper(substr($single_post['author'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="color: #0f172a; font-weight: 600; font-size: 0.95rem;"><?php echo htmlspecialchars($single_post['author']); ?></div>
                        <div style="color: #64748b; font-size: 0.8rem;"><?php echo date('F j, Y', strtotime($single_post['created_at'])); ?> • CTI Architectural Practice</div>
                    </div>
                </div>

                <!-- Share Actions -->
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <button type="button" onclick="navigator.clipboard.writeText(window.location.href); alert('Article URL copied to clipboard.');" class="btn btn-outline" style="padding: 0.4rem 0.85rem; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem; background: #ffffff; border: 1px solid #cbd5e1; color: #1e293b;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                        Copy Link
                    </button>
                    <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($single_post['title'] . ' ' . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline" style="padding: 0.4rem 0.85rem; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem; color: #16a34a; border-color: rgba(22, 163, 74, 0.4); background: #f0fdf4;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                        Share
                    </a>
                </div>
            </div>

        </div>
    </section>

    <!-- Featured Image -->
    <?php if (!empty($single_post['featured_image'])): ?>
    <section class="section" style="padding: 0 0 3rem;">
        <div class="container" style="max-width: 960px;">
            <div style="border-radius: var(--radius-xl); overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); position: relative; max-height: 520px; background: #f8fafc;">
                <img src="<?php echo htmlspecialchars($single_post['featured_image']); ?>" alt="<?php echo htmlspecialchars($single_post['title']); ?>" style="width: 100%; height: 100%; max-height: 520px; object-fit: cover; display: block;">
                <div style="position: absolute; bottom: 0; left: 0; right: 0; padding: 1.5rem 2rem; background: linear-gradient(to top, rgba(15, 23, 42, 0.85), transparent); color: #cbd5e1; font-size: 0.8rem; display: flex; justify-content: space-between;">
                    <span>Spatial Study by Creative Touch Interiors</span>
                    <span>Curated Reference Photography</span>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Main Editorial Article Body -->
    <article class="section" style="padding: 0 0 4rem;">
        <div class="container" style="max-width: 820px;">
            <div class="editorial-body" style="font-size: 1.075rem; line-height: 1.85; color: #334155;">
                <style>
                    .editorial-body p { margin-bottom: 1.65rem; }
                    .editorial-body p:first-of-type::first-letter {
                        font-family: var(--font-heading);
                        font-size: 3.8rem;
                        line-height: 0.85;
                        float: left;
                        margin: 0.15rem 0.65rem 0 0;
                        color: #1d4ed8;
                        font-weight: 700;
                    }
                    .editorial-body h2, .editorial-body h3 {
                        font-family: var(--font-heading);
                        color: #0f172a;
                        font-weight: 700;
                        margin: 2.75rem 0 1rem;
                        letter-spacing: -0.01em;
                    }
                    .editorial-body h2 { font-size: 1.75rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem; }
                    .editorial-body h3 { font-size: 1.35rem; }
                    .editorial-body blockquote {
                        font-family: var(--font-heading);
                        font-size: 1.25rem;
                        font-style: italic;
                        color: #1e3a8a;
                        border-left: 3px solid #2563eb;
                        padding: 1.25rem 1.75rem;
                        margin: 2.25rem 0;
                        background: #eff6ff;
                        border-radius: 0 var(--radius-md) var(--radius-md) 0;
                        line-height: 1.6;
                    }
                    .editorial-body ul, .editorial-body ol {
                        margin: 1.5rem 0 2rem 1.5rem;
                        padding-left: 0.5rem;
                    }
                    .editorial-body li {
                        margin-bottom: 0.75rem;
                    }
                    .editorial-body strong {
                        color: #0f172a;
                    }
                </style>

                <?php 
                // Display sanitized and structured rich content
                echo $single_post['content']; 
                ?>
            </div>

            <!-- Author Bio Capsule -->
            <div class="glass-card" style="margin-top: 4rem; padding: 2.25rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: var(--radius-xl); box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05); display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap;">
                <div style="width: 72px; height: 72px; border-radius: 50%; background: #eff6ff; border: 2px solid #2563eb; display: flex; align-items: center; justify-content: center; color: #1d4ed8; font-weight: 700; font-size: 1.75rem; flex-shrink: 0;">
                    <?php echo strtoupper(substr($single_post['author'], 0, 1)); ?>
                </div>
                <div style="flex: 1; min-width: 240px;">
                    <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #2563eb; font-weight: 700; margin-bottom: 0.25rem;">
                        Author &amp; Practice Lead
                    </div>
                    <h3 style="font-family: var(--font-heading); font-size: 1.25rem; color: #0f172a; margin: 0 0 0.5rem;">
                        <?php echo htmlspecialchars($single_post['author']); ?>
                    </h3>
                    <p style="color: #64748b; font-size: 0.9rem; line-height: 1.6; margin: 0;">
                        Principal designer at Creative Touch Interiors. Guiding high-end residential, penthouse, and corporate environments with meticulous spatial harmony and tactile materiality.
                    </p>
                </div>
                <a href="about.php" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.6rem 1.2rem; background: #ffffff; border: 1px solid #cbd5e1; color: #1e293b;">
                    Studio Profile →
                </a>
            </div>

            <!-- Consultation Banner -->
            <div class="glass-card" style="margin-top: 2rem; padding: 3rem 2.5rem; text-align: center; border: 1px solid rgba(255, 255, 255, 0.2); background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 60%, #2563eb 100%); border-radius: var(--radius-2xl); box-shadow: 0 16px 36px rgba(29, 78, 216, 0.2);">
                <span class="badge" style="background: rgba(255,255,255,0.15); color: #ffffff; border: 1px solid rgba(255,255,255,0.3); padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.08em; display: inline-block; margin-bottom: 1rem;">Transform Your Sanctuary</span>
                <h3 style="font-family: var(--font-heading); font-size: 1.75rem; color: #ffffff; margin-bottom: 0.75rem;">
                    Envisioning a Bespoke Interior Project?
                </h3>
                <p style="color: #eff6ff; font-size: 0.975rem; max-width: 540px; margin: 0 auto 2rem; line-height: 1.6;">
                    Experience our mathematical quotation wizard or schedule an exclusive consultation session with our principal design directors.
                </p>
                <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                    <a href="consultation.php" style="background: #ffffff; color: #1d4ed8; font-weight: 700; padding: 0.75rem 1.75rem; border-radius: var(--radius-full); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(0,0,0,0.12);">
                        Calculate 3D Instant Quote &rarr;
                    </a>
                    <a href="contact.php" style="border: 1px solid rgba(255,255,255,0.5); color: #ffffff; background: rgba(255,255,255,0.1); padding: 0.75rem 1.75rem; border-radius: var(--radius-full); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; backdrop-filter: blur(8px);">
                        Connect with Studio
                    </a>
                </div>
            </div>

        </div>
    </article>

    <!-- Related Perspectives Grid -->
    <?php if (!empty($related_posts)): ?>
    <section class="section" style="padding: 3rem 0 6rem; border-top: 1px solid #e2e8f0; background: #f8fafc;">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <span class="section-tag" style="color: #2563eb; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; font-size: 0.75rem; display: block; margin-bottom: 0.35rem;">Further Reading</span>
                    <h2 class="section-title" style="margin-bottom: 0; color: #0f172a; font-size: 1.85rem;">Related Spatial Perspectives</h2>
                </div>
                <a href="blog.php" class="btn btn-outline" style="font-size: 0.85rem; background: #ffffff; border: 1px solid #cbd5e1; color: #1e293b;">
                    View All Articles &rarr;
                </a>
            </div>

            <div class="grid grid-3" style="gap: 1.5rem;">
                <?php foreach ($related_posts as $rel): ?>
                <article class="glass-card card-3d" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: var(--radius-xl); box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05); overflow: hidden; display: flex; flex-direction: column;">
                    <?php if (!empty($rel['featured_image'])): ?>
                    <a href="blog.php?slug=<?php echo urlencode($rel['slug']); ?>" style="display: block; height: 180px; overflow: hidden; position: relative;">
                        <img src="<?php echo htmlspecialchars($rel['featured_image']); ?>" alt="<?php echo htmlspecialchars($rel['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        <span class="badge" style="position: absolute; top: 0.75rem; right: 0.75rem; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(8px); color: #ffffff; font-size: 0.7rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 9999px; padding: 0.25rem 0.65rem;">
                            <?php echo htmlspecialchars($rel['category']); ?>
                        </span>
                    </a>
                    <?php endif; ?>
                    <div style="padding: 1.5rem; flex: 1; display: flex; flex-direction: column;">
                        <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.5rem;">
                            <?php echo date('M d, Y', strtotime($rel['created_at'])); ?> • <?php echo htmlspecialchars($rel['reading_time']); ?>
                        </div>
                        <h3 style="font-family: var(--font-heading); font-size: 1.15rem; color: #0f172a; line-height: 1.4; margin: 0 0 0.75rem;">
                            <a href="blog.php?slug=<?php echo urlencode($rel['slug']); ?>" style="color: #0f172a; text-decoration: none; transition: var(--transition-fast);" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#0f172a'">
                                <?php echo htmlspecialchars($rel['title']); ?>
                            </a>
                        </h3>
                        <p style="color: #64748b; font-size: 0.85rem; line-height: 1.6; margin: 0 0 1.25rem; flex: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo htmlspecialchars($rel['excerpt']); ?>
                        </p>
                        <a href="blog.php?slug=<?php echo urlencode($rel['slug']); ?>" style="color: #2563eb; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                            Read Perspective &rarr;
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

</main>

<script>
// Reading progress bar computation
window.addEventListener('scroll', function() {
    const totalHeight = document.documentElement.scrollHeight - window.innerHeight;
    const progress = totalHeight > 0 ? (window.pageYOffset / totalHeight) * 100 : 0;
    const bar = document.getElementById('readingProgress');
    if (bar) {
        bar.style.width = Math.min(100, Math.max(0, progress)) + '%';
    }
});
</script>

<?php 
    include 'includes/footer.php';
    exit;
endif;

// ----------------------------------------------------
// 2. ARCHIVE MAGAZINE VIEW
// ----------------------------------------------------
$page_title = 'Spatial Perspectives & Editorial Journal — Creative Touch Interiors';
$meta_description = 'Curated architectural insights, sensory lighting theories, materiality treatises, and contemporary Indian spatial luxury by Creative Touch Interiors.';

// Filters
$category_filter = sanitize($_GET['category'] ?? '');
$search_query = sanitize($_GET['q'] ?? '');

// Fetch distinct categories for filter buttons
$cat_res = $conn->query("SELECT DISTINCT category FROM blogs WHERE status = 'published' ORDER BY category ASC");
$all_categories = [];
while ($c = $cat_res->fetch_assoc()) {
    $all_categories[] = $c['category'];
}

// Fetch featured spotlight post
$spotlight_sql = "SELECT * FROM blogs WHERE status = 'published' AND featured = 1 ORDER BY created_at DESC LIMIT 1";
$spotlight = $conn->query($spotlight_sql)->fetch_assoc();

// If no explicitly featured post, fallback to the latest post
if (!$spotlight) {
    $spotlight = $conn->query("SELECT * FROM blogs WHERE status = 'published' ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
}

// Fetch all other posts matching filter
$query = "SELECT * FROM blogs WHERE status = 'published'";
$params = [];
$types = "";

if (!empty($category_filter)) {
    $query .= " AND category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if (!empty($search_query)) {
    $query .= " AND (title LIKE ? OR excerpt LIKE ? OR content LIKE ?)";
    $like = "%" . $search_query . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$articles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<main class="page-content" style="padding-top: 100px; background: #ffffff; min-height: 100vh;">
    
    <!-- Editorial Hero -->
    <section class="section" style="padding: 4rem 0 3rem; position: relative;">
        <div class="container" style="position: relative; z-index: 2;">
            <div style="max-width: 760px;">
                <span class="section-tag" style="color: #2563eb; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.8rem; display: block; margin-bottom: 0.5rem;">CTI Editorial Journal</span>
                <h1 class="section-title" style="font-size: clamp(2.5rem, 5vw, 4rem); color: #0f172a; margin-bottom: 1.25rem;">
                    Spatial Perspectives &amp; Architecture
                </h1>
                <p class="section-desc" style="font-size: 1.15rem; color: #475569; margin-bottom: 2rem; line-height: 1.7;">
                    Critical treatises on tactile materiality, circadian lighting phenomenology, spatial acoustics, and the craft of bespoke Indian luxury living.
                </p>

                <!-- Search and Filter Bar -->
                <form method="GET" action="blog.php" style="display: flex; gap: 0.75rem; max-width: 520px;">
                    <div style="position: relative; flex: 1;">
                        <input type="text" name="q" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search essays, materiality, lighting..." class="form-control" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; padding-left: 2.75rem; border-radius: var(--radius-md); box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </div>
                    <?php if (!empty($category_filter)): ?>
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary" style="padding: 0 1.5rem; background: linear-gradient(135deg, #1d4ed8, #2563eb); border: none; border-radius: var(--radius-md);">
                        Filter
                    </button>
                    <?php if (!empty($search_query) || !empty($category_filter)): ?>
                        <a href="blog.php" class="btn btn-outline" style="padding: 0 1rem; display: flex; align-items: center; background: #ffffff; border: 1px solid #cbd5e1; color: #64748b;" title="Reset filters">
                            &times;
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </section>

    <!-- Category Filter Pills -->
    <section style="padding: 0 0 2.5rem;">
        <div class="container">
            <div style="display: flex; gap: 0.6rem; overflow-x: auto; padding-bottom: 0.5rem; scrollbar-width: none;">
                <a href="blog.php<?php echo !empty($search_query) ? '?q=' . urlencode($search_query) : ''; ?>" class="btn <?php echo empty($category_filter) ? 'btn-primary' : 'btn-outline'; ?>" style="padding: 0.45rem 1.15rem; font-size: 0.8rem; border-radius: 9999px; white-space: nowrap; <?php echo empty($category_filter) ? 'background: linear-gradient(135deg, #1d4ed8, #2563eb); color: #ffffff;' : 'background: #ffffff; border: 1px solid #cbd5e1; color: #334155;'; ?>">
                    All Disciplines
                </a>
                <?php foreach ($all_categories as $cat): ?>
                <a href="blog.php?category=<?php echo urlencode($cat); ?><?php echo !empty($search_query) ? '&q=' . urlencode($search_query) : ''; ?>" class="btn <?php echo $category_filter === $cat ? 'btn-primary' : 'btn-outline'; ?>" style="padding: 0.45rem 1.15rem; font-size: 0.8rem; border-radius: 9999px; white-space: nowrap; <?php echo $category_filter === $cat ? 'background: linear-gradient(135deg, #1d4ed8, #2563eb); color: #ffffff;' : 'background: #ffffff; border: 1px solid #cbd5e1; color: #334155;'; ?>">
                    <?php echo htmlspecialchars($cat); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Spotlight Article (Only if on 'All' view with no search) -->
    <?php if (empty($category_filter) && empty($search_query) && $spotlight): ?>
    <section class="section" style="padding: 0 0 4rem;">
        <div class="container">
            <div class="glass-card card-3d" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2.5rem; padding: 2.5rem; align-items: center; border: 1px solid #e2e8f0; background: #ffffff; border-radius: var(--radius-2xl); box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);">
                <?php if (!empty($spotlight['featured_image'])): ?>
                <div style="border-radius: var(--radius-xl); overflow: hidden; height: 360px; position: relative;">
                    <img src="<?php echo htmlspecialchars($spotlight['featured_image']); ?>" alt="<?php echo htmlspecialchars($spotlight['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <span class="badge" style="position: absolute; top: 1rem; left: 1rem; background: linear-gradient(135deg, #1d4ed8, #2563eb); color: #ffffff; border-radius: 9999px; padding: 0.35rem 0.85rem; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;">Spotlight Essay</span>
                </div>
                <?php endif; ?>
                <div>
                    <div style="display: flex; gap: 0.75rem; align-items: center; margin-bottom: 1rem;">
                        <span class="badge" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 0.75rem; border-radius: 9999px; padding: 0.3rem 0.75rem; font-weight: 600;">
                            <?php echo htmlspecialchars($spotlight['category']); ?>
                        </span>
                        <span style="color: #64748b; font-size: 0.8rem;">
                            <?php echo htmlspecialchars($spotlight['reading_time']); ?> • <?php echo date('M d, Y', strtotime($spotlight['created_at'])); ?>
                        </span>
                    </div>
                    <h2 style="font-family: var(--font-heading); font-size: clamp(1.75rem, 3vw, 2.35rem); color: #0f172a; line-height: 1.25; margin-bottom: 1rem;">
                        <a href="blog.php?slug=<?php echo urlencode($spotlight['slug']); ?>" style="color: #0f172a; text-decoration: none;" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#0f172a'">
                            <?php echo htmlspecialchars($spotlight['title']); ?>
                        </a>
                    </h2>
                    <p style="color: #64748b; font-size: 1rem; line-height: 1.7; margin-bottom: 2rem;">
                        <?php echo htmlspecialchars($spotlight['excerpt']); ?>
                    </p>
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 0.65rem;">
                            <div style="width: 34px; height: 34px; border-radius: 50%; background: #eff6ff; border: 1px solid #2563eb; display: flex; align-items: center; justify-content: center; color: #1d4ed8; font-weight: 700; font-size: 0.8rem;">
                                <?php echo strtoupper(substr($spotlight['author'], 0, 1)); ?>
                            </div>
                            <span style="color: #64748b; font-size: 0.85rem; font-weight: 500;"><?php echo htmlspecialchars($spotlight['author']); ?></span>
                        </div>
                        <a href="blog.php?slug=<?php echo urlencode($spotlight['slug']); ?>" class="btn btn-primary" style="padding: 0.65rem 1.4rem; background: linear-gradient(135deg, #1d4ed8, #2563eb); border: none; border-radius: var(--radius-full);">
                            Read Full Article &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Article Catalog Grid -->
    <section class="section" style="padding: 0 0 6rem;">
        <div class="container">
            <?php if (!empty($articles)): ?>
                <div class="grid grid-3" style="gap: 2rem;">
                    <?php foreach ($articles as $art): ?>
                    <article class="glass-card card-3d" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: var(--radius-xl); box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05); overflow: hidden; display: flex; flex-direction: column; transition: all 0.4s ease;">
                        <?php if (!empty($art['featured_image'])): ?>
                        <a href="blog.php?slug=<?php echo urlencode($art['slug']); ?>" style="display: block; height: 220px; overflow: hidden; position: relative;">
                            <img src="<?php echo htmlspecialchars($art['featured_image']); ?>" alt="<?php echo htmlspecialchars($art['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease;" onmouseover="this.style.transform='scale(1.06)'" onmouseout="this.style.transform='scale(1)'">
                            <span class="badge" style="position: absolute; top: 0.85rem; right: 0.85rem; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(8px); color: #ffffff; font-size: 0.72rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 9999px; padding: 0.25rem 0.65rem;">
                                <?php echo htmlspecialchars($art['category']); ?>
                            </span>
                        </a>
                        <?php endif; ?>
                        
                        <div style="padding: 1.75rem; flex: 1; display: flex; flex-direction: column;">
                            <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.65rem; display: flex; justify-content: space-between;">
                                <span><?php echo date('M d, Y', strtotime($art['created_at'])); ?></span>
                                <span><?php echo htmlspecialchars($art['reading_time']); ?></span>
                            </div>

                            <h3 style="font-family: var(--font-heading); font-size: 1.25rem; color: #0f172a; line-height: 1.4; margin: 0 0 0.85rem;">
                                <a href="blog.php?slug=<?php echo urlencode($art['slug']); ?>" style="color: #0f172a; text-decoration: none; transition: var(--transition-fast);" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#0f172a'">
                                    <?php echo htmlspecialchars($art['title']); ?>
                                </a>
                            </h3>

                            <p style="color: #64748b; font-size: 0.885rem; line-height: 1.65; margin: 0 0 1.5rem; flex: 1; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                <?php echo htmlspecialchars($art['excerpt']); ?>
                            </p>

                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 1rem; margin-top: auto;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="width: 26px; height: 26px; border-radius: 50%; background: #eff6ff; border: 1px solid #2563eb; display: flex; align-items: center; justify-content: center; color: #1d4ed8; font-weight: 700; font-size: 0.7rem;">
                                        <?php echo strtoupper(substr($art['author'], 0, 1)); ?>
                                    </div>
                                    <span style="color: #64748b; font-size: 0.775rem;"><?php echo htmlspecialchars($art['author']); ?></span>
                                </div>
                                <a href="blog.php?slug=<?php echo urlencode($art['slug']); ?>" style="color: #2563eb; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    Read &rarr;
                                </a>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="glass-card" style="padding: 4rem 2rem; text-align: center; max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: var(--radius-xl); box-shadow: 0 4px 18px rgba(15, 23, 42, 0.05);">
                    <div style="width: 56px; height: 56px; border-radius: 50%; background: #eff6ff; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; color: #2563eb;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </div>
                    <h3 style="font-family: var(--font-heading); font-size: 1.35rem; color: #0f172a; margin-bottom: 0.5rem;">No Articles Located</h3>
                    <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;">
                        No spatial perspectives matched your search query or selected discipline filter.
                    </p>
                    <a href="blog.php" class="btn btn-outline" style="background: #ffffff; border: 1px solid #cbd5e1; color: #1e293b;">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Consultation / Quote Banner -->
    <section class="section" style="padding: 0 0 6rem;">
        <div class="container">
            <div class="glass-card" style="padding: 3.5rem 2rem; text-align: center; border: 1px solid rgba(255,255,255,0.2); background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 60%, #2563eb 100%); border-radius: var(--radius-2xl); box-shadow: 0 16px 36px rgba(29, 78, 216, 0.2);">
                <span class="badge" style="background: rgba(255,255,255,0.15); color: #ffffff; border: 1px solid rgba(255,255,255,0.3); padding: 0.35rem 0.85rem; border-radius: 9999px; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.08em; display: inline-block; margin-bottom: 1rem;">Experience CTI Precision</span>
                <h2 style="font-family: var(--font-heading); font-size: clamp(2rem, 3.5vw, 2.75rem); color: #ffffff; margin-bottom: 1rem;">
                    From Theoretical Design to Living Reality
                </h2>
                <p style="color: #eff6ff; font-size: 1.05rem; max-width: 600px; margin: 0 auto 2rem; line-height: 1.6;">
                    Let us craft an architectural sanctuary tailored to your aesthetic identity, acoustics, and daily lifestyle.
                </p>
                <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                    <a href="consultation.php" style="background: #ffffff; color: #1d4ed8; font-weight: 700; padding: 0.85rem 2rem; border-radius: var(--radius-full); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 4px 14px rgba(0,0,0,0.12);">
                        Calculate 3D Quotation &rarr;
                    </a>
                    <a href="contact.php" style="border: 1px solid rgba(255,255,255,0.5); color: #ffffff; background: rgba(255,255,255,0.1); padding: 0.85rem 2rem; border-radius: var(--radius-full); text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; backdrop-filter: blur(8px);">
                        Book Private Studio Consultation
                    </a>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include 'includes/footer.php'; ?>
