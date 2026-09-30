<?php
require_once 'includes/config.php';

// Prevent caching on register page
preventPageCaching();

$page_title = 'Create Client Account — Creative Touch Interiors';
$redirect = sanitize($_GET['redirect'] ?? $_POST['redirect'] ?? 'profile.php');

// Prevent open redirect vulnerabilities: only allow relative .php paths
if (!preg_match('/^[a-zA-Z0-9_\-]+\.php(\?[a-zA-Z0-9_=&-]*)?$/', $redirect)) {
    $redirect = 'profile.php';
}

// Check if already logged in
if (isUserLoggedIn()) {
    header("Location: $redirect");
    exit();
}

// Handle registration
$error = '';
$name = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validate_csrf()) {
        $error = 'Security token mismatch. Please reload and try again.';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $phone = sanitize($_POST['phone'] ?? '');
        $terms_agreed = (isset($_POST['terms_agreed']) && $_POST['terms_agreed'] === '1') ? 1 : 0;

        // Validation
        if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = 'All required fields must be filled.';
        } elseif (!$terms_agreed) {
            $error = 'Please agree to the Terms & Conditions and Privacy Policy.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Check if email already exists
            $sql = "SELECT id FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                $error = 'Database error: ' . $conn->error;
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $error = 'This email address is already registered. Please login instead.';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $insert_sql = "INSERT INTO users (name, email, password, phone, terms_agreed, terms_agreed_at, created_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
                    $insert_stmt = $conn->prepare($insert_sql);
                    
                    if ($insert_stmt === false) {
                        $error = 'Database error: ' . $conn->error;
                    } else {
                        $insert_stmt->bind_param("ssssi", $name, $email, $hashed_password, $phone, $terms_agreed);
                        
                        if ($insert_stmt->execute()) {
                            $new_user_id = $conn->insert_id;
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $new_user_id;
                            $_SESSION['user_name'] = $name;
                            $_SESSION['user_email'] = $email;
                            
                            header("Location: $redirect");
                            exit();
                        } else {
                            $error = "Failed to create account. Please try again.";
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800;900&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="css/spatial-3d.css?v=<?php echo file_exists('css/spatial-3d.css') ? filemtime('css/spatial-3d.css') : time(); ?>">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            width: 100%;
            height: 100%;
            min-height: 100vh;
            background-color: #F8F7F4;
            background: radial-gradient(circle at 12% 18%, rgba(0, 87, 255, 0.08) 0%, transparent 45%),
                        radial-gradient(circle at 88% 82%, rgba(0, 87, 255, 0.06) 0%, transparent 40%),
                        linear-gradient(180deg, rgba(248, 247, 244, 0.93) 0%, rgba(248, 247, 244, 0.98) 100%),
                        url('uploads/projects/1786870500_project_05.jpg') center/cover no-repeat fixed;
            color: #0f172a;
            font-family: var(--font-body, 'Plus Jakarta Sans', sans-serif);
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }

        /* 3D WebGL Canvas Layer */
        #auth3dCanvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1;
            pointer-events: none;
            opacity: 0.65;
        }

        /* Atmospheric Glow */
        .auth-ambient-glow {
            position: fixed;
            pointer-events: none;
            border-radius: 50%;
            filter: blur(120px);
            z-index: 2;
            width: 580px;
            height: 580px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: radial-gradient(circle, rgba(0, 87, 255, 0.12) 0%, rgba(248, 247, 244, 0) 70%);
        }

        /* Viewport Center Wrapper */
        .auth-viewport-wrap {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1.25rem;
            box-sizing: border-box;
        }

        /* EXACT CENTER COMPOSITION CARD - LIQUID GLASS ARCHITECTURE */
        .auth-center-card {
            width: 100%;
            max-width: 480px;
            margin: auto;
            background: rgba(255, 255, 255, 0.76);
            backdrop-filter: blur(32px) saturate(200%) contrast(92%);
            -webkit-backdrop-filter: blur(32px) saturate(200%) contrast(92%);
            border: 1px solid rgba(255, 255, 255, 0.90);
            border-radius: 28px;
            padding: 3rem 2.5rem;
            box-shadow: 0 28px 65px rgba(0, 40, 120, 0.12),
                        0 8px 24px rgba(0, 87, 255, 0.05),
                        inset 0 2px 2px rgba(255, 255, 255, 0.98),
                        inset 0 -2px 4px rgba(0, 87, 255, 0.04);
            text-align: center;
            position: relative;
            box-sizing: border-box;
            transition: all 0.4s cubic-bezier(0.25, 1, 0.35, 1);
            overflow: hidden;
        }

        .auth-center-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -130%;
            width: 75%;
            height: 200%;
            background: linear-gradient(
                105deg,
                transparent 25%,
                rgba(255, 255, 255, 0.5) 48%,
                rgba(0, 87, 255, 0.12) 53%,
                transparent 65%
            );
            transform: rotate(25deg);
            pointer-events: none;
            z-index: 2;
            transition: left 0.85s cubic-bezier(0.2, 0.8, 0.2, 1);
            opacity: 0.9;
        }

        .auth-center-card:hover::before {
            left: 150%;
        }

        .auth-center-card:hover {
            transform: translateY(-4px) scale(1.008);
            border-color: rgba(0, 87, 255, 0.35);
            box-shadow: 0 34px 75px rgba(0, 87, 255, 0.22),
                        0 10px 30px rgba(15, 23, 42, 0.08),
                        inset 0 2px 2px #ffffff,
                        inset 0 -2px 4px rgba(0, 87, 255, 0.06);
        }

        /* Brand 3D Emblem */
        .auth-emblem-wrap {
            width: 56px;
            height: 56px;
            position: relative;
            margin: 0 auto 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        .auth-emblem-diamond {
            position: absolute;
            inset: 0;
            transform: rotate(45deg);
            border: 2px solid #0057FF;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(0, 87, 255, 0.2) 0%, rgba(255, 255, 255, 0.6) 100%);
            box-shadow: 0 0 25px rgba(0, 87, 255, 0.35);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.4s ease;
        }
        .auth-center-card:hover .auth-emblem-diamond {
            transform: rotate(135deg) scale(1.08);
            box-shadow: 0 0 35px rgba(0, 87, 255, 0.5);
        }
        .auth-emblem-text {
            position: relative;
            z-index: 2;
            font-family: var(--font-heading, 'Plus Jakarta Sans', sans-serif);
            font-size: 1.15rem;
            font-weight: 800;
            color: #0057FF;
            letter-spacing: 0.05em;
        }

        /* Brand Title */
        .auth-brand-heading {
            margin-bottom: 0.35rem;
            line-height: 1.2;
        }
        .auth-brand-name {
            display: block;
            font-family: var(--font-heading, 'Plus Jakarta Sans', sans-serif);
            font-size: 1.45rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            color: #0f172a;
            text-transform: uppercase;
        }
        .auth-brand-sub {
            display: block;
            font-family: var(--font-heading, 'Plus Jakarta Sans', sans-serif);
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.22em;
            color: #0057FF;
            text-transform: uppercase;
            margin-top: 0.2rem;
        }
        .auth-subtitle-tag {
            display: inline-block;
            font-family: var(--font-body, 'Plus Jakarta Sans', sans-serif);
            font-size: 0.725rem;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #0057FF;
            padding: 0.3rem 0.95rem;
            border-radius: 9999px;
            background: rgba(0, 87, 255, 0.08);
            border: 1px solid rgba(0, 87, 255, 0.25);
            backdrop-filter: blur(8px);
        }

        /* Form Layout */
        .auth-form-wrap {
            width: 100%;
            text-align: left;
            margin-top: 1.5rem;
        }

        .auth-input-group {
            margin-bottom: 1.15rem;
            width: 100%;
        }

        .auth-input-label {
            display: block;
            width: 100%;
            font-size: 0.775rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.4rem;
            text-align: left;
        }

        .auth-input-field {
            display: block !important;
            width: 100% !important;
            box-sizing: border-box !important;
            padding: 0.85rem 1.15rem !important;
            font-family: var(--font-body, 'Plus Jakarta Sans', sans-serif);
            font-size: 0.95rem !important;
            line-height: 1.5;
            background: rgba(255, 255, 255, 0.92) !important;
            border: 1.5px solid rgba(203, 213, 225, 0.85) !important;
            border-radius: 12px !important;
            color: #0f172a !important;
            transition: all 0.25s ease;
            outline: none;
        }

        .auth-input-field:focus {
            border-color: #0057FF !important;
            box-shadow: 0 0 0 4px rgba(0, 87, 255, 0.18) !important;
            background: #ffffff !important;
        }

        .auth-password-wrapper {
            position: relative;
            width: 100%;
        }

        .auth-password-wrapper .auth-input-field {
            padding-right: 2.85rem !important;
        }

        .auth-pass-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 0.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s;
        }
        .auth-pass-toggle:hover {
            color: #0057FF;
        }

        .auth-submit-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 1rem;
            font-family: var(--font-body, 'Plus Jakarta Sans', sans-serif);
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            border-radius: 12px;
            border: none;
            background: #0057FF;
            color: #ffffff;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 87, 255, 0.38), inset 0 1px 0 rgba(255, 255, 255, 0.25);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            margin-top: 0.75rem;
        }

        .auth-submit-btn:hover {
            background: #0046d6;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 87, 255, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.35);
        }

        .auth-alt-switch {
            text-align: center;
            margin-top: 1.75rem;
            padding-top: 1.35rem;
            border-top: 1px solid rgba(0, 40, 120, 0.08);
            font-size: 0.875rem;
            color: #475569;
        }
        .auth-alt-switch a {
            color: #0057FF;
            font-weight: 700;
            text-decoration: none;
        }
        .auth-alt-switch a:hover {
            text-decoration: underline;
        }

        .auth-return-home {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin-top: 1.25rem;
            font-size: 0.825rem;
            color: #64748b;
            text-decoration: none;
            transition: color 0.2s ease;
            font-weight: 500;
        }
        .auth-return-home:hover {
            color: #0057FF;
        }

        /* Error Banner */
        .auth-error-banner {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 1.35rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            text-align: left;
        }
    </style>
</head>
<body>

    <!-- 3D Cinematic Background Canvas -->
    <canvas id="auth3dCanvas"></canvas>

    <!-- Ambient Lighting Glow -->
    <div class="auth-ambient-glow"></div>

    <!-- MAIN CENTERED VIEWPORT -->
    <div class="auth-viewport-wrap">
        <div class="auth-center-card">

            <!-- Center Brand 3D Emblem -->
            <a href="index.php" class="auth-emblem-wrap" title="Creative Touch Interiors">
                <div class="auth-emblem-diamond"></div>
                <span class="auth-emblem-text">CTI</span>
            </a>

            <!-- Brand Title -->
            <div class="auth-brand-heading">
                <span class="auth-brand-name">CREATIVE TOUCH</span>
                <span class="auth-brand-sub">INTERIORS</span>
            </div>

            <!-- Supporting Heading -->
            <div style="margin-top: 0.75rem; margin-bottom: 1.5rem;">
                <span class="auth-subtitle-tag">Create Account &bull; Client Onboarding</span>
                <h1 style="font-family: var(--font-heading); font-size: 1.65rem; color: #0f172a; font-weight: 800; margin-top: 0.5rem; margin-bottom: 0.25rem;">
                    Create Account
                </h1>
                <p style="color: #64748b; font-size: 0.875rem; margin: 0;">
                    Track quotations, architectural layouts, and schedule studio sessions
                </p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="auth-error-banner">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST" action="register.php<?php echo !empty($redirect) ? '?redirect=' . urlencode($redirect) : ''; ?>" class="auth-form-wrap">
                <?php echo csrf_field(); ?>

                <div class="auth-input-group">
                    <label class="auth-input-label" for="reg_name">
                        Full Legal Name <span style="color: #0057FF;">*</span>
                    </label>
                    <input type="text" id="reg_name" name="name" class="auth-input-field" required placeholder="Alistair Sterling" value="<?php echo htmlspecialchars($name); ?>" autocomplete="name" autofocus>
                </div>

                <div class="auth-input-group">
                    <label class="auth-input-label" for="reg_email">
                        Email Address <span style="color: #0057FF;">*</span>
                    </label>
                    <input type="email" id="reg_email" name="email" class="auth-input-field" required placeholder="client@luxuryestate.com" value="<?php echo htmlspecialchars($email); ?>" autocomplete="email">
                </div>

                <div class="auth-input-group">
                    <label class="auth-input-label" for="reg_phone">
                        Phone Number
                    </label>
                    <input type="tel" id="reg_phone" name="phone" class="auth-input-field" placeholder="+91 98765 43210" value="<?php echo htmlspecialchars($phone); ?>" autocomplete="tel">
                </div>

                <div class="auth-input-group">
                    <label class="auth-input-label" for="reg_password">
                        Password (min. 6 characters) <span style="color: #0057FF;">*</span>
                    </label>
                    <div class="auth-password-wrapper">
                        <input type="password" id="reg_password" name="password" class="auth-input-field" required placeholder="Choose a secure password" autocomplete="new-password">
                        <button type="button" class="auth-pass-toggle" aria-label="Toggle password visibility" onclick="togglePass('reg_password', this)">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="auth-input-group">
                    <label class="auth-input-label" for="reg_confirm_password">
                        Confirm Password <span style="color: #0057FF;">*</span>
                    </label>
                    <input type="password" id="reg_confirm_password" name="confirm_password" class="auth-input-field" required placeholder="Repeat password" autocomplete="new-password">
                </div>

                <div style="margin-bottom: 1.35rem; display: flex; align-items: flex-start; gap: 0.65rem; font-size: 0.85rem; color: #475569;">
                    <input type="checkbox" id="reg_terms" name="terms_agreed" value="1" required style="accent-color: #0057FF; margin-top: 0.2rem; cursor: pointer; width: 16px; height: 16px;">
                    <label for="reg_terms" style="cursor: pointer; line-height: 1.4; text-align: left;">
                        I agree to the <a href="terms.php" target="_blank" style="color: #0057FF; font-weight: 600;">Terms</a> and <a href="privacy.php" target="_blank" style="color: #0057FF; font-weight: 600;">Privacy Policy</a>.
                    </label>
                </div>

                <button type="submit" class="auth-submit-btn">
                    Create Client Account &rarr;
                </button>
            </form>

            <div class="auth-alt-switch">
                Already have an account? <a href="login.php<?php echo !empty($redirect) ? '?redirect=' . urlencode($redirect) : ''; ?>">Sign In</a>
            </div>

            <a href="index.php" class="auth-return-home">
                &larr; Return to Studio Website
            </a>

        </div>
    </div>

    <!-- Three.js for 3D Cinematic Background -->
    <script src="js/vendor/three.min.js"></script>

    <script>
    function togglePass(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            btn.innerHTML = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
        } else {
            input.type = 'password';
            btn.innerHTML = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
        }
    }

    // 3D Cinematic Background
    (function init3D() {
        const canvas = document.getElementById('auth3dCanvas');
        if (!canvas || typeof THREE === 'undefined') return;

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 1000);
        camera.position.z = 24;

        const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

        const geoGroup = new THREE.Group();
        scene.add(geoGroup);

        const icoGeo = new THREE.IcosahedronGeometry(7, 1);
        const icoMat = new THREE.MeshBasicMaterial({
            color: 0x2563eb,
            wireframe: true,
            transparent: true,
            opacity: 0.18
        });
        const icoMesh = new THREE.Mesh(icoGeo, icoMat);
        geoGroup.add(icoMesh);

        const ringGeo1 = new THREE.TorusGeometry(10.5, 0.04, 16, 100);
        const ringMat = new THREE.MeshBasicMaterial({ color: 0x3b82f6, transparent: true, opacity: 0.25 });
        const ring1 = new THREE.Mesh(ringGeo1, ringMat);
        ring1.rotation.x = Math.PI / 3;
        geoGroup.add(ring1);

        const ringGeo2 = new THREE.TorusGeometry(13, 0.03, 16, 100);
        const ring2 = new THREE.Mesh(ringGeo2, ringMat);
        ring2.rotation.y = Math.PI / 4;
        geoGroup.add(ring2);

        const particleCount = 140;
        const particleGeo = new THREE.BufferGeometry();
        const particlePos = new Float32Array(particleCount * 3);
        for (let i = 0; i < particleCount * 3; i += 3) {
            particlePos[i] = (Math.random() - 0.5) * 45;
            particlePos[i + 1] = (Math.random() - 0.5) * 45;
            particlePos[i + 2] = (Math.random() - 0.5) * 35;
        }
        particleGeo.setAttribute('position', new THREE.BufferAttribute(particlePos, 3));
        const particleMat = new THREE.PointsMaterial({
            color: 0x2563eb,
            size: 0.12,
            transparent: true,
            opacity: 0.55
        });
        const particles = new THREE.Points(particleGeo, particleMat);
        scene.add(particles);

        let targetRotX = 0;
        let targetRotY = 0;
        window.addEventListener('mousemove', (e) => {
            const mx = (e.clientX / window.innerWidth) - 0.5;
            const my = (e.clientY / window.innerHeight) - 0.5;
            targetRotX = my * 0.25;
            targetRotY = mx * 0.35;
        });

        let clock = new THREE.Clock();
        function animate() {
            requestAnimationFrame(animate);
            const delta = clock.getDelta();

            geoGroup.rotation.y += 0.08 * delta;
            geoGroup.rotation.x += 0.04 * delta;
            particles.rotation.y -= 0.02 * delta;

            geoGroup.rotation.x += (targetRotX - geoGroup.rotation.x) * 0.05;
            geoGroup.rotation.y += (targetRotY - geoGroup.rotation.y) * 0.05;

            renderer.render(scene, camera);
        }
        animate();

        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });
    })();
    </script>
</body>
</html>
