<?php
require_once 'includes/config.php';

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$project = null;

if ($project_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $project = $result->fetch_assoc();
}

if (!$project) {
    header('Location: projects.php');
    exit();
}

$page_title = htmlspecialchars($project['title']) . ' — Architectural Case Study — Creative Touch Interiors';

// Fetch Related Projects from database
$cat = $project['category'] ?? 'residential';
$stmt_rel = $conn->prepare("SELECT * FROM projects WHERE category = ? AND id != ? ORDER BY created_at DESC LIMIT 3");
$stmt_rel->bind_param("si", $cat, $project_id);
$stmt_rel->execute();
$related_res = $stmt_rel->get_result();
$related_projects = [];
if ($related_res && $related_res->num_rows > 0) {
    while ($r = $related_res->fetch_assoc()) $related_projects[] = $r;
}
if (count($related_projects) < 3) {
    $stmt_fallback = $conn->prepare("SELECT * FROM projects WHERE id != ? ORDER BY created_at DESC LIMIT 3");
    $stmt_fallback->bind_param("i", $project_id);
    $stmt_fallback->execute();
    $f_res = $stmt_fallback->get_result();
    if ($f_res) {
        $related_projects = [];
        while ($r = $f_res->fetch_assoc()) $related_projects[] = $r;
    }
}

include 'includes/header.php';
?>

    <!-- Breadcrumb -->
    <div style="background: #F8F7F4; border-bottom: 1px solid rgba(0, 0, 0, 0.06); padding: 1rem 0; margin-top: 5rem; font-size: 0.85rem;">
        <div class="container">
            <div style="display: flex; align-items: center; gap: 0.5rem; color: #64748b;">
                <a href="index.php" style="color: #64748b; text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='#0057FF';" onmouseout="this.style.color='#64748b';">Home</a>
                <span>/</span>
                <a href="projects.php" style="color: #64748b; text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='#0057FF';" onmouseout="this.style.color='#64748b';">Projects</a>
                <span>/</span>
                <span style="color: #0057FF; font-weight: 600;"><?php echo htmlspecialchars($project['title']); ?></span>
            </div>
        </div>
    </div>

    <!-- Project Hero -->
    <section class="spatial-hero-section" style="min-height: 55vh; padding: 6rem 0 4.5rem; background: radial-gradient(circle at 50% 30%, #eff6ff 0%, #ffffff 85%);">
        <div class="spatial-hero-scrim"></div>
        <div class="container" style="position: relative; z-index: 3; text-align: center;">
            <div class="spatial-hero-pill" style="margin-bottom: 1.25rem;">
                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $project['status'] ?? 'Completed'))); ?> &bull; <?php echo htmlspecialchars(ucfirst($project['category'])); ?>
            </div>

            <h1 class="spatial-hero-title" style="font-size: clamp(2.4rem, 5vw, 4rem); max-width: 900px; margin-left: auto; margin-right: auto; color: #0f172a;">
                <?php echo htmlspecialchars($project['title']); ?>
            </h1>

            <p style="font-size: 1.15rem; color: #475569; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2.2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span><?php echo htmlspecialchars($project['location'] ?: 'Surat, Gujarat'); ?></span>
            </p>
        </div>
    </section>

    <!-- Main Project Visual Showcase -->
    <section class="spatial-section" style="padding: 4rem 0; background: #F8F7F4;">
        <div class="container">
            <!-- Large Hero Project Visual -->
            <div style="border-radius: var(--radius-2xl); overflow: hidden; border: 1px solid rgba(0, 0, 0, 0.08); box-shadow: var(--shadow-spatial-3d); margin-bottom: 4.5rem; aspect-ratio: 16/9; max-height: 640px; background: #0f172a;">
                <img src="<?php echo htmlspecialchars($project['image'] ?: 'uploads/projects/1786870500_project_05.jpg'); ?>" 
                     alt="<?php echo htmlspecialchars($project['title']); ?>" 
                     style="width: 100%; height: 100%; object-fit: cover;">
            </div>

            <!-- Content Split Grid -->
            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 4rem; align-items: start;">
                
                <!-- Left: Narrative & Execution -->
                <div>
                    <span class="spatial-section-eyebrow">Concept &amp; Execution</span>
                    <h2 style="font-size: 2.25rem; color: #0f172a; margin-bottom: 1.5rem; font-family: var(--font-heading); font-weight: 700;">Spatial Narrative</h2>
                    <div style="color: #334155; font-size: 1.05rem; line-height: 1.8; margin-bottom: 2.5rem;">
                        <?php echo nl2br(htmlspecialchars($project['description'] ?: 'This bespoke interior project showcases our signature blend of architectural proportion, tactile materiality, and tailored lighting design. Crafted to provide a tranquil and functional sanctuary, every joinery detail and custom furnishing was specifically fabricated to harmonize with the structural layout.')); ?>
                    </div>

                    <h3 style="font-size: 1.5rem; color: #0f172a; margin-bottom: 1.25rem; font-family: var(--font-heading); font-weight: 700;">Architectural Highlights</h3>
                    <div style="background: rgba(255, 255, 255, 0.92); backdrop-filter: blur(28px); -webkit-backdrop-filter: blur(28px); border: 1px solid rgba(0, 87, 255, 0.12); border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow-spatial-sm);">
                        <ul style="list-style: none; display: flex; flex-direction: column; gap: 1rem; color: #334155; font-size: 0.95rem;">
                            <li style="display: flex; gap: 0.85rem; align-items: flex-start;">
                                <span style="color: #0057FF; font-weight: bold; font-size: 1.1rem; line-height: 1;">✓</span>
                                <span>Complete turnkey electrical, plumbing, civil, acoustic, and joinery coordination.</span>
                            </li>
                            <li style="display: flex; gap: 0.85rem; align-items: flex-start;">
                                <span style="color: #0057FF; font-weight: bold; font-size: 1.1rem; line-height: 1;">✓</span>
                                <span>Bespoke custom furniture designed specifically for optimal sightlines and circulation.</span>
                            </li>
                            <li style="display: flex; gap: 0.85rem; align-items: flex-start;">
                                <span style="color: #0057FF; font-weight: bold; font-size: 1.1rem; line-height: 1;">✓</span>
                                <span>Natural organic stone, Central Province teakwood veneers, and warm circadian lighting.</span>
                            </li>
                            <li style="display: flex; gap: 0.85rem; align-items: flex-start;">
                                <span style="color: #0057FF; font-weight: bold; font-size: 1.1rem; line-height: 1;">✓</span>
                                <span>Full 4K 3D visualization and photometric lighting simulations delivered prior to civil execution.</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Right: Specifications Card -->
                <div style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(28px); -webkit-backdrop-filter: blur(28px); border: 1px solid rgba(0, 87, 255, 0.16); border-radius: var(--radius-2xl); padding: 2.5rem; box-shadow: var(--shadow-spatial-lg); position: sticky; top: 120px;">
                    <span class="spatial-section-eyebrow" style="margin-bottom: 0.5rem;">Technical Profile</span>
                    <h3 style="font-size: 1.5rem; color: #0f172a; margin-bottom: 1.5rem; font-family: var(--font-heading); font-weight: 700;">Commission Details</h3>

                    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.85rem; border-bottom: 1px solid rgba(0, 0, 0, 0.06);">
                            <span style="color: #64748b; font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em;">Location</span>
                            <strong style="color: #0f172a; font-weight: 700; font-size: 0.95rem; text-align: right;"><?php echo htmlspecialchars($project['location'] ?: 'Surat, Gujarat'); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.85rem; border-bottom: 1px solid rgba(0, 0, 0, 0.06);">
                            <span style="color: #64748b; font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em;">Carpet Area</span>
                            <strong style="color: #0f172a; font-weight: 700; font-size: 0.95rem; text-align: right;"><?php echo htmlspecialchars($project['area'] ?: '2,500 sq.ft'); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.85rem; border-bottom: 1px solid rgba(0, 0, 0, 0.06);">
                            <span style="color: #64748b; font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em;">Design Aesthetic</span>
                            <strong style="color: #0f172a; font-weight: 700; font-size: 0.95rem; text-align: right;"><?php echo htmlspecialchars($project['design_style'] ?: 'Contemporary Luxury'); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.85rem; border-bottom: 1px solid rgba(0, 0, 0, 0.06);">
                            <span style="color: #64748b; font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em;">Project Typology</span>
                            <strong style="color: #0f172a; font-weight: 700; font-size: 0.95rem; text-align: right;"><?php echo htmlspecialchars(ucfirst($project['category'])); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.85rem; border-bottom: 1px solid rgba(0, 0, 0, 0.06);">
                            <span style="color: #64748b; font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em;">Status</span>
                            <span class="status-badge <?php echo htmlspecialchars($project['status'] ?: 'completed'); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $project['status'] ?? 'Completed'))); ?>
                            </span>
                        </div>
                        <?php if (!empty($project['completion_date'])): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.85rem; border-bottom: 1px solid rgba(0, 0, 0, 0.06);">
                                <span style="color: #64748b; font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em;">Handover</span>
                                <strong style="color: #0f172a; font-weight: 700; font-size: 0.95rem; text-align: right;"><?php echo date('F Y', strtotime($project['completion_date'])); ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 2rem; display: flex; flex-direction: column; gap: 0.85rem;">
                        <a href="consultation.php" class="btn-spatial-bronze" style="justify-content: center; width: 100%;">
                            <span>Request Similar Quote &rarr;</span>
                        </a>
                        <a href="contact.php" class="btn-spatial-outline" style="justify-content: center; width: 100%;">
                            <span>Inquire With Studio</span>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Related Works -->
    <?php if (!empty($related_projects)): ?>
        <section class="spatial-section spatial-section-alt">
            <div class="container">
                <div class="spatial-section-header">
                    <span class="spatial-section-eyebrow">Related Works</span>
                    <h2 class="spatial-section-title" style="color: #0f172a !important;">Similar Spatial Commissions</h2>
                </div>

                <div class="spatial-projects-grid">
                    <?php foreach ($related_projects as $rel): ?>
                        <article class="spatial-project-card">
                            <div class="spatial-project-thumb-wrap">
                                <span class="spatial-project-badge-tag"><?php echo htmlspecialchars(ucfirst($rel['category'])); ?></span>
                                <img src="<?php echo htmlspecialchars($rel['image'] ?: 'uploads/projects/1786870500_project_05.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($rel['title']); ?>" 
                                     class="spatial-project-thumb" 
                                     loading="lazy">
                            </div>
                            <div class="spatial-project-body">
                                <h3 class="spatial-project-title">
                                    <a href="project.php?id=<?php echo $rel['id']; ?>"><?php echo htmlspecialchars($rel['title']); ?></a>
                                </h3>
                                <p class="spatial-project-desc"><?php echo htmlspecialchars($rel['description']); ?></p>
                                <div class="spatial-project-footer">
                                    <span class="spatial-project-spec"><?php echo htmlspecialchars($rel['location'] ?: 'Surat, Gujarat'); ?></span>
                                    <a href="project.php?id=<?php echo $rel['id']; ?>" class="spatial-project-link-btn">
                                        <span>View</span> &rarr;
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

<?php include 'includes/footer.php'; ?>
