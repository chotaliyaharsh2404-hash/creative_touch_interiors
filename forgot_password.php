<?php
require_once 'includes/config.php';

// Prevent caching on forgot password page
preventPageCaching();

$page_title = 'Reset Your Password — Creative Touch Interiors';

// Check if already logged in
if (isUserLoggedIn()) {
    header("Location: profile.php");
    exit();
}

// Ensure password_resets table exists
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

$error = '';
$success = false;
$reset_link = '';
$sent_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        $error = 'Security token mismatch. Please reload and try again.';
    } else {
        $email = sanitize(trim($_POST['email'] ?? ''));

        if (empty($email)) {
            $error = 'Please enter your registered email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check if user exists in database
            $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();

            if (!$user) {
                $error = 'No client account is registered with this email address. Please check your spelling or create an account.';
            } else {
                // Generate secure random token
                $token = bin2hex(random_bytes(32));

                // Invalidate any existing unused tokens for this email
                $invStmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0");
                $invStmt->bind_param("s", $email);
                $invStmt->execute();

                // Save new reset token using MySQL server time + 1 hour
                $insStmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at, used) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), 0)");
                $insStmt->bind_param("ss", $email, $token);

                if ($insStmt->execute()) {
                    $reset_link = BASE_URL . 'reset_password.php?token=' . urlencode($token);
                    $sent_email = $email;
                    $success = true;

                    // Send email (HTML format with luxury styling)
                    $subject = "Reset Your Password — Creative Touch Interiors";
                    $headers  = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: " . SITE_NAME . " <" . SITE_EMAIL . ">\r\n";
                    $headers .= "Reply-To: " . SITE_EMAIL . "\r\n";
                    $headers .= "X-Mailer: PHP/" . phpversion();

                    $client_name = !empty($user['name']) ? htmlspecialchars($user['name']) : 'Valued Client';

                    $mail_body = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Reset Request</title>
</head>
<body style="margin: 0; padding: 30px 15px; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #1e293b;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
        <div style="background: linear-gradient(135deg, #0057FF 0%, #0036a7 100%); padding: 35px 30px; text-align: center; color: #ffffff;">
            <div style="display: inline-block; width: 44px; height: 44px; line-height: 44px; border: 2px solid rgba(255,255,255,0.4); border-radius: 10px; font-weight: 800; font-size: 16px; letter-spacing: 1px; margin-bottom: 12px;">CTI</div>
            <h1 style="margin: 0; font-size: 20px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;">Creative Touch Interiors</h1>
            <p style="margin: 5px 0 0; font-size: 12px; letter-spacing: 0.15em; text-transform: uppercase; opacity: 0.85;">Client Portal Security</p>
        </div>
        <div style="padding: 35px 30px;">
            <h2 style="font-size: 18px; margin-top: 0; color: #0f172a;">Password Reset Request</h2>
            <p style="font-size: 15px; line-height: 1.6; color: #475569;">Hello ' . $client_name . ',</p>
            <p style="font-size: 15px; line-height: 1.6; color: #475569;">We received a request to reset the password for your Creative Touch Interiors client account. Click the button below to choose a new password:</p>
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . htmlspecialchars($reset_link) . '" style="display: inline-block; background: #0057FF; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 700; font-size: 14px; letter-spacing: 0.04em;">Reset My Password &rarr;</a>
            </div>
            <p style="font-size: 13px; line-height: 1.5; color: #64748b; background: #f8fafc; padding: 12px 16px; border-radius: 8px; border-left: 3px solid #0057FF;">
                <strong>Security Notice:</strong> This reset link is valid for <strong>60 minutes</strong>. If you did not make this request, you can safely ignore this email; your existing password will remain unchanged.
            </p>
            <p style="font-size: 12px; color: #94a3b8; margin-top: 25px; word-break: break-all;">
                If the button above does not work, copy and paste this link into your browser:<br>
                <a href="' . htmlspecialchars($reset_link) . '" style="color: #0057FF;">' . htmlspecialchars($reset_link) . '</a>
            </p>
        </div>
        <div style="background: #f8fafc; padding: 18px 30px; text-align: center; border-top: 1px solid #e2e8f0; font-size: 12px; color: #94a3b8;">
            &copy; ' . date('Y') . ' Creative Touch Interiors. All rights reserved.
        </div>
    </div>
</body>
</html>';

                    @mail($email, $subject, $mail_body, $headers);
                } else {
                    $error = 'Failed to generate password reset request. Please try again.';
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

        /* Center Card - Liquid Glass Architecture */
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

        /* Form Inputs */
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

        /* Banner alerts */
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

        /* Success Card Styling */
        .reset-success-box {
            text-align: center;
            margin-top: 1rem;
        }
        .reset-success-badge {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #dcfce7;
            border: 2px solid #86efac;
            color: #16a34a;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            box-shadow: 0 0 25px rgba(34, 197, 94, 0.25);
            animation: pulseSuccess 2s infinite ease-in-out;
        }
        @keyframes pulseSuccess {
            0%, 100% { transform: scale(1); box-shadow: 0 0 25px rgba(34, 197, 94, 0.25); }
            50% { transform: scale(1.05); box-shadow: 0 0 35px rgba(34, 197, 94, 0.4); }
        }

        .dev-preview-card {
            background: rgba(241, 245, 249, 0.9);
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            padding: 1.25rem;
            margin-top: 1.5rem;
            text-align: left;
        }
        .dev-preview-card h4 {
            font-size: 0.825rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #0057FF;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .dev-link-field {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            margin-top: 0.5rem;
        }
        .dev-link-field input {
            border: none;
            outline: none;
            background: transparent;
            font-size: 0.8rem;
            color: #475569;
            width: 100%;
            font-family: monospace;
        }
        .dev-copy-btn {
            background: #0057FF;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35rem 0.65rem;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .dev-copy-btn:hover {
            background: #0046d6;
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
                <span class="auth-subtitle-tag">Client Security &bull; Account Recovery</span>
                <h1 style="font-family: var(--font-heading); font-size: 1.65rem; color: #0f172a; font-weight: 800; margin-top: 0.5rem; margin-bottom: 0.25rem;">
                    Forgot Password
                </h1>
                <p style="color: #64748b; font-size: 0.875rem; margin: 0;">
                    <?php if ($success): ?>
                        Password reset instructions have been generated
                    <?php else: ?>
                        Enter your registered email to receive a secure recovery link
                    <?php endif; ?>
                </p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="auth-error-banner">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <!-- SUCCESS STATE -->
                <div class="reset-success-box">
                    <div class="reset-success-badge">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>

                    <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">
                        Reset Link Dispatched
                    </h3>
                    <p style="font-size: 0.875rem; color: #475569; line-height: 1.5; margin-bottom: 1rem;">
                        A secure password reset link has been dispatched to:<br>
                        <strong style="color: #0057FF;"><?php echo htmlspecialchars($sent_email); ?></strong>
                    </p>
                    <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 1.25rem;">
                        The recovery link will remain active for <strong>60 minutes</strong>.
                    </p>

                    <!-- DIRECT QUICK-ACTION & DEMO LINK (Guarantees seamless testing on localhost / XAMPP) -->
                    <div class="dev-preview-card">
                        <h4>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            Instant Reset Link (Localhost / Testing)
                        </h4>
                        <p style="font-size: 0.775rem; color: #64748b; margin: 0 0 0.5rem 0;">
                            In this environment, you can proceed directly or copy your token URL:
                        </p>
                        <div class="dev-link-field">
                            <input type="text" id="devResetLinkInput" value="<?php echo htmlspecialchars($reset_link); ?>" readonly>
                            <button type="button" class="dev-copy-btn" onclick="copyResetLink(this)">Copy</button>
                        </div>
                    </div>

                    <a href="<?php echo htmlspecialchars($reset_link); ?>" class="auth-submit-btn" style="text-decoration: none; margin-top: 1.25rem;">
                        Proceed to Set New Password &rarr;
                    </a>

                    <div style="margin-top: 1.25rem;">
                        <a href="forgot_password.php" style="font-size: 0.825rem; color: #64748b; text-decoration: none;">
                            &larr; Try another email address
                        </a>
                    </div>
                </div>

            <?php else: ?>

                <!-- PASSWORD RESET REQUEST FORM -->
                <form method="POST" action="forgot_password.php" class="auth-form-wrap">
                    <?php echo csrf_field(); ?>

                    <div class="auth-input-group">
                        <label class="auth-input-label" for="recovery_email">
                            Account Email Address <span style="color: #0057FF;">*</span>
                        </label>
                        <input type="email" id="recovery_email" name="email" class="auth-input-field" required placeholder="client@luxuryestate.com" autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autofocus>
                    </div>

                    <button type="submit" class="auth-submit-btn">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Send Reset Link &rarr;
                    </button>
                </form>

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
    function copyResetLink(btn) {
        const input = document.getElementById('devResetLinkInput');
        if (!input) return;
        input.select();
        input.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(input.value).then(() => {
            const originalText = btn.textContent;
            btn.textContent = 'Copied!';
            btn.style.background = '#16a34a';
            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.background = '#0057FF';
            }, 2000);
        }).catch(() => {
            document.execCommand('copy');
            btn.textContent = 'Copied!';
        });
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
