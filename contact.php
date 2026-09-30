<?php
require_once 'includes/config.php';

$page_title = 'Contact Our Architectural Practice — Creative Touch Interiors';

// Handle form submission
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!validate_csrf()) {
        $error = "Security token mismatch. Please reload and try again.";
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $subject = sanitize($_POST['subject'] ?? '');
        $message_text = sanitize($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($subject) || empty($message_text)) {
            $error = "Please fill in all required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Clean insert into contact_inquiries table
            $sql = "INSERT INTO contact_inquiries (name, email, phone, subject, message, status) VALUES (?, ?, ?, ?, ?, 'new')";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $name, $email, $phone, $subject, $message_text);
            if ($stmt->execute()) {
                $success = "Thank you. Your message has been received by our architectural practice. Our principal architects will contact you within 24 hours.";
            } else {
                $error = "An error occurred while transmitting your message. Please try again.";
            }
        }
    }
}

// Studio coordinates
$contact_address = getSiteContent('contact_address') ?: 'Madhavanand Society, Dhanmora, Chikuvadi, Katargam, Surat, Gujarat 395004';
$contact_phone = getSiteContent('contact_phone') ?: '+91 9316856961';
$contact_email = getSiteContent('contact_email') ?: 'harshchotaliya@gmail.com';

include 'includes/header.php';
?>

    <!-- Editorial Hero Header -->
    <section class="spatial-hero-section" style="min-height: 50vh; padding-top: 8rem; padding-bottom: 3.5rem; background: radial-gradient(circle at 50% 20%, #eff6ff 0%, #ffffff 85%);">
        <div class="spatial-hero-scrim"></div>
        <div class="spatial-hero-content" style="padding: 1rem;">
            <div class="spatial-hero-pill">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
                <span>Direct Studio Inquiries</span>
            </div>
            <h1 class="spatial-hero-title" style="font-size: clamp(2.4rem, 5vw, 4rem); margin-bottom: 1rem; color: #0f172a;">
                Connect With Our Practice
            </h1>
            <p class="spatial-hero-subtitle" style="margin-bottom: 0; color: #475569;">
                Whether you are commissioning a private estate or an executive workplace, our partners are at your service.
            </p>
        </div>
    </section>

    <!-- Main Contact Section -->
    <section class="spatial-section" style="padding: 5rem 0 7rem;">
        <div class="container">
            <div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 4rem; align-items: start;">
                
                <!-- Left: Form Card -->
                <div style="background: var(--glass-bg-card); border: 1px solid var(--glass-border); border-radius: var(--radius-2xl); padding: 3rem 2.5rem; box-shadow: var(--shadow-spatial-lg);">
                    <span class="spatial-section-eyebrow">Direct Studio Message</span>
                    <h2 style="font-size: 2rem; color: #0f172a; margin-bottom: 0.5rem;">
                        Schedule a Dialogue
                    </h2>
                    <p style="color: var(--color-stone-light); font-size: 0.95rem; margin-bottom: 2rem;">
                        Complete the inquiry parameters below and our lead architects will contact you with preliminary spatial insights.
                    </p>

                    <?php if (!empty($success)): ?>
                        <div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: var(--radius-md); padding: 1.25rem 1.5rem; color: #166534; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <div><?php echo htmlspecialchars($success); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius-md); padding: 1.25rem 1.5rem; color: #b91c1c; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <form id="contactForm" method="POST">
                        <?php echo csrf_field(); ?>

                        <div class="spatial-form-group">
                            <label class="spatial-form-label" for="contact_name">Full Name <span class="req">*</span></label>
                            <input type="text" id="contact_name" name="name" class="spatial-form-control" required placeholder="e.g. Rajesh Patel">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                            <div class="spatial-form-group">
                                <label class="spatial-form-label" for="contact_email">Email Address <span class="req">*</span></label>
                                <input type="email" id="contact_email" name="email" class="spatial-form-control" required placeholder="rajesh@domain.com">
                            </div>
                            <div class="spatial-form-group">
                                <label class="spatial-form-label" for="contact_phone">Mobile Number</label>
                                <input type="tel" id="contact_phone" name="phone" class="spatial-form-control" placeholder="+91 98765 43210">
                            </div>
                        </div>

                        <div class="spatial-form-group">
                            <label class="spatial-form-label" for="contact_subject">Project Scope / Subject <span class="req">*</span></label>
                            <input type="text" id="contact_subject" name="subject" class="spatial-form-control" required placeholder="e.g. 4BHK Penthouse Turnkey Interior Consultation">
                        </div>

                        <div class="spatial-form-group">
                            <label class="spatial-form-label" for="contact_message">Spatial Narrative / Brief <span class="req">*</span></label>
                            <textarea id="contact_message" name="message" rows="5" class="spatial-form-control" required placeholder="Tell us about your property location, carpet area, aesthetic goals, and target handover timeline..."></textarea>
                        </div>

                        <button type="submit" class="btn-spatial-bronze" style="width: 100%; justify-content: center; padding: 0.85rem; font-size: 0.95rem;">
                            <span>Transmit Inquiry to Lead Architects</span> &rarr;
                        </button>
                    </form>
                </div>

                <!-- Right: Studio Coordinates & Architectural Map Card -->
                <div>
                    <div style="background: var(--glass-bg-card); border: 1px solid var(--glass-border); border-radius: var(--radius-2xl); padding: 2.5rem; box-shadow: var(--shadow-spatial-lg); margin-bottom: 2rem;">
                        <span class="spatial-section-eyebrow">Physical Coordinates</span>
                        <h3 style="font-size: 1.5rem; color: #0f172a; margin-bottom: 1.5rem;">The Surat Design Studio</h3>

                        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                            <div style="display: flex; gap: 1rem; align-items: flex-start;">
                                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(37, 99, 235, 0.1); border: 1px solid #bfdbfe; display: flex; align-items: center; justify-content: center; color: #2563eb; flex-shrink: 0;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                </div>
                                <div>
                                    <div style="font-size: 0.78rem; text-transform: uppercase; color: var(--color-stone); font-weight: 700;">Studio Address</div>
                                    <div style="color: #0f172a; font-size: 0.95rem; margin-top: 0.2rem; line-height: 1.6;">
                                        <?php echo htmlspecialchars($contact_address); ?>
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; gap: 1rem; align-items: flex-start;">
                                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(37, 99, 235, 0.1); border: 1px solid #bfdbfe; display: flex; align-items: center; justify-content: center; color: #2563eb; flex-shrink: 0;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                </div>
                                <div>
                                    <div style="font-size: 0.78rem; text-transform: uppercase; color: var(--color-stone); font-weight: 700;">Direct Telephone</div>
                                    <div style="color: #0f172a; font-size: 0.95rem; margin-top: 0.2rem;">
                                        <a href="tel:<?php echo htmlspecialchars($contact_phone); ?>" style="color: #2563eb; font-weight: 600; text-decoration: none;">
                                            <?php echo htmlspecialchars($contact_phone); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; gap: 1rem; align-items: flex-start;">
                                <div style="width: 40px; height: 40px; border-radius: var(--radius-md); background: rgba(37, 99, 235, 0.1); border: 1px solid #bfdbfe; display: flex; align-items: center; justify-content: center; color: #2563eb; flex-shrink: 0;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                                </div>
                                <div>
                                    <div style="font-size: 0.78rem; text-transform: uppercase; color: var(--color-stone); font-weight: 700;">Executive Inquiries</div>
                                    <div style="color: #0f172a; font-size: 0.95rem; margin-top: 0.2rem;">
                                        <a href="mailto:<?php echo htmlspecialchars($contact_email); ?>" style="color: #2563eb; font-weight: 600; text-decoration: none;">
                                            <?php echo htmlspecialchars($contact_email); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0;">
                            <div style="font-size: 0.78rem; text-transform: uppercase; color: var(--color-stone); font-weight: 700; margin-bottom: 0.5rem;">Operating Hours</div>
                            <p style="color: var(--color-stone-light); font-size: 0.9rem; line-height: 1.6; margin: 0;">
                                Monday – Saturday: 10:00 AM – 7:30 PM<br>
                                <span style="color: #2563eb; font-weight: 600;">Sunday: By Prior Architectural Appointment</span>
                            </p>
                        </div>
                    </div>

                    <!-- Location Map Showcase Card -->
                    <div style="background: var(--glass-bg-card); border: 1px solid var(--glass-border); border-radius: var(--radius-2xl); overflow: hidden; box-shadow: var(--shadow-spatial-lg);">
                        <div style="padding: 1.5rem 1.75rem; border-bottom: 1px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.85rem; font-weight: 700; color: #0f172a; text-transform: uppercase; letter-spacing: 0.08em;">Studio Location Map</span>
                            <span style="font-size: 0.72rem; color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 999px;">Surat, Gujarat</span>
                        </div>
                        <div style="height: 240px; background: #f8fafc; position: relative; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                            <iframe 
                                title="Studio Location Map"
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d119066.4170942416!2d72.75704175317765!3d21.170240100000007!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3be04e59411d1563%3A0xfe4558290938b042!2sSurat%2C%20Gujarat!5e0!3m2!1sen!2sin!4v1711234567890!5m2!1sen!2sin" 
                                width="100%" 
                                height="100%" 
                                style="border:0;" 
                                allowfullscreen="" 
                                loading="lazy" 
                                referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
