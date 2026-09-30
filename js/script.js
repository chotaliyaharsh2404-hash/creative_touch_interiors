/**
 * Creative Touch Interiors - Master UI/UX Script
 * Interactive enhancements: Sticky Nav, Lightbox, Animated Counters, Mobile Menu,
 * Multi-Step Quote Wizard, Live Real-Time Filtering, and Upload Previews
 */

document.addEventListener('DOMContentLoaded', function() {

    // 1. Sticky Navbar Transition on Scroll
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 40) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    }

    // 2. Mobile Navigation Toggle & Accessible Drawer
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navbarNav = document.querySelector('.navbar-nav');

    if (mobileMenuBtn && navbarNav) {
        mobileMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = navbarNav.classList.toggle('active');
            mobileMenuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        // Close on clicking outside
        document.addEventListener('click', function(e) {
            if (!navbarNav.contains(e.target) && !mobileMenuBtn.contains(e.target) && navbarNav.classList.contains('active')) {
                navbarNav.classList.remove('active');
                mobileMenuBtn.setAttribute('aria-expanded', 'false');
            }
        });

        // Close when clicking a link
        navbarNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 991) {
                    navbarNav.classList.remove('active');
                    mobileMenuBtn.setAttribute('aria-expanded', 'false');
                }
            });
        });
    }

    // 3. Smooth Scroll for Anchor Links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href && href !== '#' && href.length > 1) {
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // 4. Milestone Statistics Animated Counters
    const counterElements = document.querySelectorAll('.stat-counter');
    if (counterElements.length > 0) {
        const counterObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const targetNum = parseInt(el.getAttribute('data-target') || el.textContent.replace(/[^0-9]/g, ''), 10);
                    const suffix = el.getAttribute('data-suffix') || (el.textContent.includes('+') ? '+' : (el.textContent.includes('%') ? '%' : ''));
                    if (!isNaN(targetNum) && targetNum > 0) {
                        let currentNum = 0;
                        const duration = 1400; // ms
                        const stepTime = Math.max(16, Math.floor(duration / targetNum));
                        const increment = Math.ceil(targetNum / (duration / stepTime));

                        const timer = setInterval(() => {
                            currentNum += increment;
                            if (currentNum >= targetNum) {
                                currentNum = targetNum;
                                clearInterval(timer);
                            }
                            el.textContent = currentNum + suffix;
                        }, stepTime);
                    }
                    observer.unobserve(el);
                }
            });
        }, { threshold: 0.3 });

        counterElements.forEach(el => counterObserver.observe(el));
    }

    // 5. Accordion (FAQs)
    const accordionHeaders = document.querySelectorAll('.accordion-header');
    accordionHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const item = this.parentElement;
            const isActive = item.classList.contains('active');
            
            document.querySelectorAll('.accordion-item').forEach(i => {
                i.classList.remove('active');
            });

            if (!isActive) {
                item.classList.add('active');
            }
        });
    });

    // 6. Image Upload Previews
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(ev) {
                    let preview = input.parentElement.querySelector('.image-upload-preview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.className = 'image-upload-preview';
                        preview.style.cssText = 'max-width: 140px; height: 140px; object-fit: cover; margin-top: 12px; border-radius: 6px; border: 1.5px solid #8B7355; box-shadow: 0 4px 12px rgba(17,17,17,0.08); display: block;';
                        input.parentElement.appendChild(preview);
                    }
                    preview.src = ev.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // 7. Interactive Lightbox for Gallery & Projects
    const galleryItems = Array.from(document.querySelectorAll('.gallery-item, .project-img-lightbox'));
    if (galleryItems.length > 0) {
        let currentIndex = 0;
        let lightbox = document.querySelector('.lightbox');

        if (!lightbox) {
            lightbox = document.createElement('div');
            lightbox.className = 'lightbox';
            lightbox.innerHTML = `
                <div class="lightbox-backdrop"></div>
                <div class="lightbox-container">
                    <button class="lightbox-close" aria-label="Close dialog">&times;</button>
                    <button class="lightbox-nav lightbox-prev" aria-label="Previous image">&#10094;</button>
                    <div class="lightbox-content">
                        <img src="" alt="" class="lightbox-image">
                        <div class="lightbox-caption"></div>
                    </div>
                    <button class="lightbox-nav lightbox-next" aria-label="Next image">&#10095;</button>
                </div>
            `;
            document.body.appendChild(lightbox);
        }

        const lightboxImg = lightbox.querySelector('.lightbox-image');
        const lightboxCaption = lightbox.querySelector('.lightbox-caption');
        const closeBtn = lightbox.querySelector('.lightbox-close');
        const prevBtn = lightbox.querySelector('.lightbox-prev');
        const nextBtn = lightbox.querySelector('.lightbox-next');
        const backdrop = lightbox.querySelector('.lightbox-backdrop');

        function showLightboxImage(idx) {
            if (idx < 0) idx = galleryItems.length - 1;
            if (idx >= galleryItems.length) idx = 0;
            currentIndex = idx;

            const item = galleryItems[currentIndex];
            const img = item.querySelector('img');
            if (img) {
                lightboxImg.style.opacity = '0';
                lightboxImg.src = img.src;
                lightboxImg.alt = img.alt || 'Architectural View';

                const title = item.querySelector('.gallery-overlay div, h3, .project-card-content h3')?.textContent || img.alt || 'Creative Touch Interiors';
                lightboxCaption.textContent = title;

                lightboxImg.onload = function() {
                    lightboxImg.style.opacity = '1';
                };
            }
        }

        galleryItems.forEach((item, index) => {
            item.addEventListener('click', function(e) {
                // If it's a link to a detail page, only trigger lightbox if specifically clicking zoom trigger
                if (item.tagName === 'A' && !e.target.closest('.gallery-overlay, .zoom-trigger')) {
                    return;
                }
                e.preventDefault();
                currentIndex = index;
                showLightboxImage(currentIndex);
                lightbox.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        });

        function closeLightbox() {
            lightbox.classList.remove('active');
            document.body.style.overflow = '';
        }

        if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
        if (backdrop) backdrop.addEventListener('click', closeLightbox);
        if (prevBtn) prevBtn.addEventListener('click', () => showLightboxImage(currentIndex - 1));
        if (nextBtn) nextBtn.addEventListener('click', () => showLightboxImage(currentIndex + 1));

        document.addEventListener('keydown', function(e) {
            if (!lightbox.classList.contains('active')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') showLightboxImage(currentIndex - 1);
            if (e.key === 'ArrowRight') showLightboxImage(currentIndex + 1);
        });
    }

    // 8. Live Real-Time Search for Projects & Gallery
    const searchInputs = document.querySelectorAll('.search-input');
    searchInputs.forEach(input => {
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const searchTarget = this.dataset.search;

            if (searchTarget === 'projects') {
                const projectCards = document.querySelectorAll('.project-card');
                projectCards.forEach(card => {
                    const title = card.querySelector('h3')?.textContent.toLowerCase() || '';
                    const loc = card.querySelector('.location')?.textContent.toLowerCase() || '';
                    const cat = (card.dataset.category || '').toLowerCase();

                    if (searchTerm === '' || title.includes(searchTerm) || loc.includes(searchTerm) || cat.includes(searchTerm)) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            } else if (searchTarget === 'gallery') {
                const galleryCards = document.querySelectorAll('.gallery-item');
                let visibleCount = 0;
                galleryCards.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    const alt = item.querySelector('img')?.alt?.toLowerCase() || '';
                    if (searchTerm === '' || text.includes(searchTerm) || alt.includes(searchTerm)) {
                        item.style.display = '';
                        visibleCount++;
                    } else {
                        item.style.display = 'none';
                    }
                });
                const countBadge = document.getElementById('visibleImagesCount');
                if (countBadge) countBadge.textContent = visibleCount;
            }
        });
    });

    // 9. Floating Scroll-To-Top Button
    const scrollTopBtn = document.createElement('button');
    scrollTopBtn.className = 'scroll-top-btn';
    scrollTopBtn.setAttribute('aria-label', 'Back to top');
    scrollTopBtn.innerHTML = `
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="18 15 12 9 6 15"></polyline>
        </svg>
    `;
    scrollTopBtn.style.cssText = `
        position: fixed;
        bottom: 28px;
        right: 28px;
        width: 44px;
        height: 44px;
        background: #111111;
        color: #F5F2EC;
        border: 1px solid #8B7355;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        z-index: 999;
        box-shadow: 0 4px 16px rgba(17, 17, 17, 0.25);
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    `;
    document.body.appendChild(scrollTopBtn);

    scrollTopBtn.addEventListener('mouseenter', () => {
        scrollTopBtn.style.background = '#8B7355';
        scrollTopBtn.style.color = '#FFFFFF';
        scrollTopBtn.style.transform = 'translateY(-2px)';
    });

    scrollTopBtn.addEventListener('mouseleave', () => {
        scrollTopBtn.style.background = '#111111';
        scrollTopBtn.style.color = '#F5F2EC';
        scrollTopBtn.style.transform = 'translateY(0)';
    });

    window.addEventListener('scroll', function() {
        if (window.scrollY > 350) {
            scrollTopBtn.style.opacity = '1';
            scrollTopBtn.style.visibility = 'visible';
        } else {
            scrollTopBtn.style.opacity = '0';
            scrollTopBtn.style.visibility = 'hidden';
        }
    });

    scrollTopBtn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // 10. Multi-Step Quote Engine Form UX Controller (consultation.php)
    const quoteForm = document.getElementById('multiStepQuoteForm');
    if (quoteForm) {
        let currentStep = 1;
        const totalSteps = 6;
        const stepPanes = quoteForm.querySelectorAll('.quote-step-pane');
        const stepperItems = document.querySelectorAll('.stepper-step');
        const progressBar = document.querySelector('.quote-stepper-progress');
        const prevBtn = document.getElementById('quotePrevBtn');
        const nextBtn = document.getElementById('quoteNextBtn');
        const submitBtn = document.getElementById('quoteSubmitBtn');

        function updateStep(step) {
            currentStep = step;
            stepPanes.forEach((pane, idx) => {
                pane.style.display = (idx + 1 === step) ? 'block' : 'none';
            });

            stepperItems.forEach((item, idx) => {
                const s = idx + 1;
                item.classList.remove('active', 'completed');
                if (s === step) {
                    item.classList.add('active');
                } else if (s < step) {
                    item.classList.add('completed');
                }
            });

            if (progressBar) {
                const pct = ((step - 1) / (totalSteps - 1)) * 100;
                progressBar.style.width = pct + '%';
            }

            if (prevBtn) prevBtn.style.display = (step > 1) ? 'inline-flex' : 'none';
            if (nextBtn) nextBtn.style.display = (step < totalSteps) ? 'inline-flex' : 'none';
            if (submitBtn) submitBtn.style.display = (step === totalSteps) ? 'inline-flex' : 'none';

            // If entering step 6 (Review), populate summary dynamically
            if (step === 6) {
                populateReviewSummary();
            }

            // Scroll gently to top of quote card
            const container = document.querySelector('.quote-card-box');
            if (container) {
                container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function validateCurrentStep() {
            const currentPane = quoteForm.querySelector(`.quote-step-pane[data-step="${currentStep}"]`);
            if (!currentPane) return true;

            let isValid = true;
            const requiredInputs = currentPane.querySelectorAll('input[required], select[required], textarea[required]');
            
            requiredInputs.forEach(input => {
                if (input.type === 'radio') {
                    const name = input.name;
                    const checked = currentPane.querySelector(`input[name="${name}"]:checked`);
                    if (!checked) {
                        isValid = false;
                        input.closest('.selector-grid, .form-group')?.classList.add('has-error');
                    } else {
                        input.closest('.selector-grid, .form-group')?.classList.remove('has-error');
                    }
                } else if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            });

            // Specific validations
            if (currentStep === 2) {
                const area = currentPane.querySelector('input[name="approx_area"]');
                if (area && (parseFloat(area.value) <= 0 || isNaN(parseFloat(area.value)))) {
                    isValid = false;
                    area.classList.add('is-invalid');
                }
            } else if (currentStep === 5) {
                const email = currentPane.querySelector('input[name="email"]');
                const phone = currentPane.querySelector('input[name="phone"]');
                if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                    isValid = false;
                    email.classList.add('is-invalid');
                }
                if (phone && phone.value.replace(/[^0-9]/g, '').length < 10) {
                    isValid = false;
                    phone.classList.add('is-invalid');
                }
            }

            return isValid;
        }

        function populateReviewSummary() {
            const summaryBox = document.getElementById('quoteReviewSummary');
            if (!summaryBox) return;

            const projectType = quoteForm.querySelector('input[name="project_type"]:checked')?.value || 'Not specified';
            const propertyType = quoteForm.querySelector('select[name="property_type"]')?.value || 'Not specified';
            const city = quoteForm.querySelector('input[name="city"]')?.value || 'Not specified';
            const area = quoteForm.querySelector('input[name="approx_area"]')?.value || '0';
            const rooms = quoteForm.querySelector('input[name="rooms_count"]')?.value || '1';
            const designStyle = quoteForm.querySelector('select[name="design_style"]')?.value || 'Modern Luxury';
            const theme = quoteForm.querySelector('input[name="preferred_theme"]')?.value || 'Standard';
            const budget = quoteForm.querySelector('select[name="budget_range"]')?.value || 'Not specified';
            const name = quoteForm.querySelector('input[name="customer_name"]')?.value || '';
            const email = quoteForm.querySelector('input[name="email"]')?.value || '';
            const phone = quoteForm.querySelector('input[name="phone"]')?.value || '';

            // Selected services count
            const checkedServices = quoteForm.querySelectorAll('input[name="selected_services[]"]:checked');
            const servicesCount = checkedServices.length;

            summaryBox.innerHTML = `
                <div style="background: var(--color-ivory); border: 1px solid var(--color-light-gray); border-radius: var(--radius-sm); padding: 1.5rem; margin-bottom: 1.5rem;">
                    <h4 style="font-family: var(--font-heading); color: var(--color-black); margin-bottom: 1rem; font-size: 1.15rem; border-bottom: 1px solid var(--color-light-gray); padding-bottom: 0.5rem;">
                        Project &amp; Property Overview
                    </h4>
                    <div class="grid grid-2" style="gap: 1rem; font-size: 0.9rem;">
                        <div><strong>Project Scope:</strong> ${escapeHtml(projectType)}</div>
                        <div><strong>Property Type:</strong> ${escapeHtml(propertyType)}</div>
                        <div><strong>City / Location:</strong> ${escapeHtml(city)}</div>
                        <div><strong>Approximate Area:</strong> ${escapeHtml(area)} sq.ft (${escapeHtml(rooms)} Rooms)</div>
                        <div><strong>Preferred Style:</strong> ${escapeHtml(designStyle)}</div>
                        <div><strong>Aesthetic Theme:</strong> ${escapeHtml(theme)}</div>
                        <div><strong>Target Budget:</strong> <span style="color: var(--color-bronze); font-weight: 700;">${escapeHtml(budget)}</span></div>
                        <div><strong>Selected Services:</strong> ${servicesCount} specific architectural packages</div>
                    </div>
                </div>

                <div style="background: #FFFFFF; border: 1px solid var(--color-light-gray); border-radius: var(--radius-sm); padding: 1.5rem; margin-bottom: 1.5rem;">
                    <h4 style="font-family: var(--font-heading); color: var(--color-black); margin-bottom: 1rem; font-size: 1.15rem; border-bottom: 1px solid var(--color-light-gray); padding-bottom: 0.5rem;">
                        Client Contact Coordinates
                    </h4>
                    <div class="grid grid-2" style="gap: 1rem; font-size: 0.9rem;">
                        <div><strong>Name:</strong> ${escapeHtml(name)}</div>
                        <div><strong>Email:</strong> ${escapeHtml(email)}</div>
                        <div><strong>Mobile:</strong> ${escapeHtml(phone)}</div>
                    </div>
                </div>
            `;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                if (validateCurrentStep()) {
                    if (currentStep < totalSteps) {
                        updateStep(currentStep + 1);
                    }
                } else {
                    const firstInvalid = quoteForm.querySelector('.is-invalid, .has-error');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                if (currentStep > 1) {
                    updateStep(currentStep - 1);
                }
            });
        }

        // Stepper click allows jumping back to completed steps
        stepperItems.forEach(item => {
            item.addEventListener('click', function() {
                const targetStep = parseInt(this.dataset.step, 10);
                if (targetStep < currentStep) {
                    updateStep(targetStep);
                }
            });
        });

        // Initialize Step 1
        updateStep(1);
    }

    // 11. Custom Radio & Checkbox Card Selection Highlights
    const selectorCards = document.querySelectorAll('.selector-card');
    selectorCards.forEach(card => {
        const input = card.querySelector('input[type="radio"], input[type="checkbox"]');
        if (input) {
            if (input.checked) card.classList.add('selected');

            card.addEventListener('click', function(e) {
                if (input.type === 'radio') {
                    const group = document.querySelectorAll(`input[name="${input.name}"]`);
                    group.forEach(r => r.closest('.selector-card')?.classList.remove('selected'));
                    input.checked = true;
                    card.classList.add('selected');
                } else if (input.type === 'checkbox') {
                    if (e.target !== input) {
                        input.checked = !input.checked;
                    }
                    card.classList.toggle('selected', input.checked);
                }
            });
        }
    });

    // 12. Password Visibility Toggle
    document.querySelectorAll('.password-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('input');
            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    this.setAttribute('aria-label', 'Hide password');
                } else {
                    input.type = 'password';
                    this.setAttribute('aria-label', 'Show password');
                }
            }
        });
    });

    // 13. Interactive Homepage Project Filter
    const homeTabs = document.querySelectorAll('#homeProjectsTabs .category-filter-btn');
    const homeGrid = document.getElementById('homeProjectsGrid');
    if (homeTabs.length > 0 && homeGrid) {
        homeTabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                homeTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                const selectedCat = (this.getAttribute('data-filter') || 'all').toLowerCase();
                const cards = homeGrid.querySelectorAll('.project-card');

                cards.forEach(card => {
                    const cardCat = (card.getAttribute('data-category') || '').toLowerCase();
                    if (selectedCat === 'all' || cardCat === selectedCat) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    }

});

// Lightbox Styles injected
const lightboxStyles = document.createElement('style');
lightboxStyles.textContent = `
    .lightbox {
        position: fixed;
        inset: 0;
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
    }
    .lightbox.active {
        display: flex;
    }
    .lightbox-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(17, 17, 17, 0.94);
        backdrop-filter: blur(8px);
    }
    .lightbox-container {
        position: relative;
        z-index: 10001;
        max-width: 90vw;
        max-height: 90vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .lightbox-content {
        text-align: center;
        max-width: 100%;
        max-height: 85vh;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .lightbox-image {
        max-width: 82vw;
        max-height: 75vh;
        object-fit: contain;
        border-radius: 4px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        border: 1px solid rgba(255,255,255,0.15);
        transition: opacity 0.25s ease;
    }
    .lightbox-caption {
        color: #F5F2EC;
        margin-top: 14px;
        font-family: 'Playfair Display', serif;
        font-size: 1.15rem;
        font-weight: 600;
        letter-spacing: 0.02em;
    }
    .lightbox-close {
        position: absolute;
        top: -46px;
        right: 0;
        background: transparent;
        border: none;
        color: #FFFFFF;
        font-size: 36px;
        cursor: pointer;
        line-height: 1;
        transition: color 0.2s;
    }
    .lightbox-close:hover { color: #8B7355; }
    .lightbox-nav {
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        color: #FFFFFF;
        font-size: 20px;
        width: 44px;
        height: 44px;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        margin: 0 16px;
    }
    .lightbox-nav:hover {
        background: #8B7355;
        border-color: #8B7355;
        transform: scale(1.05);
    }
`;
document.head.appendChild(lightboxStyles);
