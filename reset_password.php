<?php
require_once 'includes/config.php';

// Prevent caching
preventPageCaching();

$page_title = 'Set New Password — Creative Touch Interiors';

// Check if already logged in
if (isUserLoggedIn()) {
    header("Location: profile.php");
    exit();
}

$token = sanitize(trim($_GET['token'] ?? $_POST['token'] ?? ''));
$error = '';
$valid_token = false;
$token_row = null;
$user = null;
$token_state = 'invalid'; // 'valid', 'used', 'expired', 'invalid'

if (!empty($token)) {
    $stmt = $conn->prepare("SELECT * FROM password_resets WHERE token = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $token_row = $res->fetch_assoc();
        
        if ($token_row['used'] == 1) {
            $token_state = 'used';
        } elseif (strtotime($token_row['expires_at']) < time()) {
            $token_state = 'expired';
        } else {
            // Verify user exists
            $uStmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ? LIMIT 1");
            $uStmt->bind_param("s", $token_row['email']);
            $uStmt->execute();
            $uRes = $uStmt->get_result();
            $user = $uRes->fetch_assoc();

            if ($user) {
                $valid_token = true;
                $token_state = 'valid';
            } else {
                $token_state = 'invalid';
                $error = 'The user account linked to this reset request no longer exists.';
            }
        }
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Security token mismatch. Please reload and try again.';
    } elseif (!$valid_token) {
        $error = 'This reset token is no longer valid. Please request a new link.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($password)) {
            $error = 'Please enter a new password.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters in length.';
        } elseif ($password !== $confirm_password) {
            $error = 'Password confirmation does not match.';
        } else {
            // Update password
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->bind_param("si", $hashed, $user['id']);

            if ($upd->execute()) {
                // Invalidate this token and all other tokens for this email
                $inv = $conn->prepare("UPDATE password_resets SET used = 1 WHERE email = ?");
                $inv->bind_param("s", $user['email']);
                $inv->execute();

                // Redirect to login with success state
                header("Location: login.php?reset=success&email=" . urlencode($user['email']));
                exit();
            } else {
                $error = 'An error occurred while updating your password. Please try again.';
            }
        }
    }
}

// Mask email for privacy (e.g. j***n@domain.com)
function maskEmailAddress($email) {
    if (empty($email)) return '';
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1] ?? '';
    $len = strlen($name);
    if ($len <= 2) {
        $maskedName = substr($name, 0, 1) . '*';
    } else {
        $maskedName = substr($name, 0, 1) . str_repeat('*', max(1, $len - 2)) . substr($name, -1);
    }
    return $maskedName . '@' . $domain;
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
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800;900&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

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
                        url('uploads/projects/1786884033_project_07.jpg') center/cover no-repeat fixed;
            color: #0f172a;
            font-family: var(--font-body, 'Plus Jakarta Sans', sans-serif);
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }

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

        .auth-center-card {
            width: 100%;
            max-width: 480px;
            margin: auto;
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(32px) saturate(200%) contrast(92%);
            -webkit-backdrop-filter: blur(32px) saturate(200%) contrast(92%);
            border: 1px solid rgba(255, 255, 255, 0.92);
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

        /* Form Controls */
        .auth-form-wrap {
            width: 100%;
            text-align: left;
            margin-top: 1.5rem;
        }

        .auth-input-group {
            margin-bottom: 1.25rem;
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
            margin-bottom: 0.45rem;
            text-align: left;
        }

        .auth-input-field {
            display: block !important;
            width: 100% !important;
            box-sizing: border-box !important;
            padding: 0.9rem 1.15rem !important;
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
            gap: 0.5rem;
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
            margin-top: 1rem;
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

        .user-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: rgba(0, 87, 255, 0.06);
            border: 1px solid rgba(0, 87, 255, 0.18);
            border-radius: 9999px;
            padding: 0.35rem 0.85rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #0057FF;
            margin-bottom: 1rem;
        }

        /* Password Strength Meter */
        .pass-meter-wrap {
            margin-top: 0.5rem;
        }
        .pass-meter-bars {
            display: flex;
            gap: 4px;
            height: 4px;
            margin-bottom: 0.35rem;
        }
        .pass-meter-bar {
            flex: 1;
            background: #e2e8f0;
            border-radius: 2px;
            transition: background 0.3s;
        }
        .pass-meter-text {
            font-size: 0.725rem;
            color: #64748b;
            display: flex;
            justify-content: space-between;
        }

        /* Invalid / Expired Screen Badge */
        .token-alert-badge {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #fef2f2;
            border: 2px solid #fecaca;
            color: #dc2626;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            box-shadow: 0 0 25px rgba(239, 68, 68, 0.2);
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
                <span class="auth-subtitle-tag">Account Security &bull; Credentials Update</span>
                <h1 style="font-family: var(--font-heading); font-size: 1.65rem; color: #0f172a; font-weight: 800; margin-top: 0.5rem; margin-bottom: 0.25rem;">
                    Set New Password
                </h1>
                <p style="color: #64748b; font-size: 0.875rem; margin: 0;">
                    <?php if ($valid_token): ?>
                        Create a strong, secure password for your client portal
                    <?php else: ?>
                        Password recovery token validation
                    <?php endif; ?>
                </p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="auth-error-banner">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($valid_token): ?>

                <!-- User account identity banner -->
                <div class="user-pill">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Resetting account: <?php echo htmlspecialchars(maskEmailAddress($user['email'])); ?></span>
                </div>

                <!-- Set New Password Form -->
                <form method="POST" action="reset_password.php" class="auth-form-wrap">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div class="auth-input-group">
                        <label class="auth-input-label" for="new_password">
                            New Password <span style="color: #0057FF;">*</span>
                        </label>
                        <div class="auth-password-wrapper">
                            <input type="password" id="new_password" name="password" class="auth-input-field" required placeholder="Minimum 6 characters" autocomplete="new-password" autofocus oninput="checkStrength(this.value)">
                            <button type="button" class="auth-pass-toggle" aria-label="Toggle password visibility" onclick="togglePass('new_password', this)">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                        
                        <!-- Interactive Password Strength Meter -->
                        <div class="pass-meter-wrap">
                            <div class="pass-meter-bars">
                                <div class="pass-meter-bar" id="bar1"></div>
                                <div class="pass-meter-bar" id="bar2"></div>
                                <div class="pass-meter-bar" id="bar3"></div>
                                <div class="pass-meter-bar" id="bar4"></div>
                            </div>
                            <div class="pass-meter-text">
                                <span id="passStrengthLabel">Password strength</span>
                                <span>Min. 6 characters</span>
                            </div>
                        </div>
                    </div>

                    <div class="auth-input-group">
                        <label class="auth-input-label" for="confirm_password">
                            Confirm New Password <span style="color: #0057FF;">*</span>
                        </label>
                        <div class="auth-password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" class="auth-input-field" required placeholder="Re-type your new password" autocomplete="new-password">
                            <button type="button" class="auth-pass-toggle" aria-label="Toggle password visibility" onclick="togglePass('confirm_password', this)">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="auth-submit-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Save New Password &amp; Log In &rarr;
                    </button>
                </form>

            <?php else: ?>

                <!-- INVALID / EXPIRED TOKEN STATE -->
                <div style="margin-top: 1rem;">
                    <div class="token-alert-badge">
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    </div>

                    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">
                        <?php 
                        if ($token_state === 'used') {
                            echo 'Link Already Used';
                        } elseif ($token_state === 'expired') {
                            echo 'Reset Link Expired';
                        } else {
                            echo 'Invalid Reset Link';
                        }
                        ?>
                    </h3>

                    <p style="font-size: 0.875rem; color: #64748b; line-height: 1.5; margin-bottom: 1.5rem;">
                        <?php 
                        if ($token_state === 'used') {
                            echo 'This password reset link has already been used. If you still need to change your password, please submit a new request.';
                        } elseif ($token_state === 'expired') {
                            echo 'This recovery link has expired (links are valid for 60 minutes). Please request a fresh reset link below.';
                        } else {
                            echo 'The link you followed appears to be invalid or incomplete. Please ensure you clicked the full URL or generate a new request.';
                        }
                        ?>
                    </p>

                    <a href="forgot_password.php" class="auth-submit-btn" style="text-decoration: none;">
                        Request New Reset Link &rarr;
                    </a>
                </div>

            <?php endif; ?>

            <div class="auth-alt-switch">
                Remember your password? <a href="login.php">Return to Sign In</a>
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

    function checkStrength(pass) {
        const bar1 = document.getElementById('bar1');
        const bar2 = document.getElementById('bar2');
        const bar3 = document.getElementById('bar3');
        const bar4 = document.getElementById('bar4');
        const label = document.getElementById('passStrengthLabel');
        if (!bar1) return;

        // Reset
        [bar1, bar2, bar3, bar4].forEach(b => {
            b.style.background = '#e2e8f0';
        });

        if (!pass) {
            label.textContent = 'Password strength';
            label.style.color = '#64748b';
            return;
        }

        let score = 0;
        if (pass.length >= 6) score++;
        if (pass.length >= 8) score++;
        if (/[A-Z]/.test(pass) && /[a-z]/.test(pass)) score++;
        if (/\d/.test(pass) || /[^A-Za-z0-9]/.test(pass)) score++;

        if (score === 1) {
            bar1.style.background = '#ef4444';
            label.textContent = 'Weak';
            label.style.color = '#ef4444';
        } else if (score === 2) {
            bar1.style.background = '#f59e0b';
            bar2.style.background = '#f59e0b';
            label.textContent = 'Fair';
            label.style.color = '#f59e0b';
        } else if (score === 3) {
            bar1.style.background = '#0057FF';
            bar2.style.background = '#0057FF';
            bar3.style.background = '#0057FF';
            label.textContent = 'Good';
            label.style.color = '#0057FF';
        } else if (score >= 4) {
            bar1.style.background = '#10b981';
            bar2.style.background = '#10b981';
            bar3.style.background = '#10b981';
            bar4.style.background = '#10b981';
            label.textContent = 'Strong';
            label.style.color = '#10b981';
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
