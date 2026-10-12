/**
 * Creative Touch Interiors — Master 3D Spatial Engine & UX Controller
 * Technologies: Three.js (WebGL), GSAP, Custom Cursor, Spatial Card Tilt,
 * Multi-Step Quote Wizard, Interactive 3D Showcase & Testimonials
 */

(function () {
    'use strict';

    // State & Utilities
    const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ==========================================================================
       1. Custom Subtle Cursor System
       ========================================================================== */
    function initCustomCursor() {
        if (isTouchDevice || prefersReducedMotion) return;

        const dot = document.createElement('div');
        dot.className = 'spatial-cursor-dot';
        const ring = document.createElement('div');
        ring.className = 'spatial-cursor-ring';

        document.body.appendChild(dot);
        document.body.appendChild(ring);

        let mouseX = window.innerWidth / 2;
        let mouseY = window.innerHeight / 2;
        let ringX = mouseX;
        let ringY = mouseY;
        let isVisible = false;

        window.addEventListener('mousemove', (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;
            if (!isVisible) {
                dot.style.opacity = '1';
                ring.style.opacity = '1';
                isVisible = true;
            }
            dot.style.transform = `translate(${mouseX}px, ${mouseY}px)`;
        }, { passive: true });

        window.addEventListener('mouseleave', () => {
            dot.style.opacity = '0';
            ring.style.opacity = '0';
            isVisible = false;
        });

        // Smooth trailing animation for the ring
        function renderCursor() {
            ringX += (mouseX - ringX) * 0.18;
            ringY += (mouseY - ringY) * 0.18;
            ring.style.transform = `translate(${ringX}px, ${ringY}px)`;
            requestAnimationFrame(renderCursor);
        }
        requestAnimationFrame(renderCursor);

        // Hover states
        const hoverTargets = 'a, button, [role="button"], input, select, textarea, .spatial-filter-btn, .spatial-choice-card, .spatial-carousel-arrow';
        document.addEventListener('mouseover', (e) => {
            if (e.target.closest(hoverTargets)) {
                document.body.classList.add('cursor-hover');
            } else if (e.target.closest('.spatial-project-card')) {
                document.body.classList.add('cursor-view');
            }
        });

        document.addEventListener('mouseout', (e) => {
            if (e.target.closest(hoverTargets)) {
                document.body.classList.remove('cursor-hover');
            }
            if (e.target.closest('.spatial-project-card')) {
                document.body.classList.remove('cursor-view');
            }
        });
    }

    /* ==========================================================================
       2. Three.js Immersive 3D Hero Interior Environment
       ========================================================================== */
    function initHero3D() {
        const canvas = document.getElementById('hero-3d-canvas');
        if (!canvas || typeof THREE === 'undefined') return;

        const scene = new THREE.Scene();
        scene.fog = new THREE.FogExp2(0xffffff, 0.08);

        const camera = new THREE.PerspectiveCamera(45, canvas.clientWidth / canvas.clientHeight, 0.1, 100);
        camera.position.set(0, 1.4, 6.2);

        const renderer = new THREE.WebGLRenderer({
            canvas: canvas,
            antialias: true,
            alpha: true,
            powerPreference: 'high-performance'
        });
        renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.15;

        // Lighting Architecture
        const ambientLight = new THREE.AmbientLight(0xffecd1, 0.85);
        scene.add(ambientLight);

        // Sun / Terrace Light
        const sunLight = new THREE.DirectionalLight(0xfff5e6, 1.6);
        sunLight.position.set(6, 8, 4);
        scene.add(sunLight);

        // Warm Designer Lamp Point Light
        const lampLight = new THREE.PointLight(0xc59b27, 2.2, 12);
        lampLight.position.set(-2.8, 2.2, -0.5);
        scene.add(lampLight);

        // Secondary Bronze Accent Light
        const accentPoint = new THREE.PointLight(0xdfb84c, 1.4, 10);
        accentPoint.position.set(3.2, 1.5, 0);
        scene.add(accentPoint);

        // 3D Architectural Room Volume
        const roomGroup = new THREE.Group();
        scene.add(roomGroup);

        // Floor - Dark Polished Italian Stone
        const floorGeo = new THREE.PlaneGeometry(24, 24);
        const floorMat = new THREE.MeshStandardMaterial({
            color: 0x0f121a,
            roughness: 0.25,
            metalness: 0.2
        });
        const floorMesh = new THREE.Mesh(floorGeo, floorMat);
        floorMesh.rotation.x = -Math.PI / 2;
        floorMesh.position.y = 0;
        roomGroup.add(floorMesh);

        // Architectural Floor Inlay Grid Lines (Subtle Bronze Inlay)
        const gridHelper = new THREE.GridHelper(24, 24, 0xc59b27, 0x1b202c);
        gridHelper.position.y = 0.002;
        gridHelper.material.opacity = 0.35;
        gridHelper.material.transparent = true;
        roomGroup.add(gridHelper);

        // Back Wall with Vertical Architectural Slats (Fluted Feature Wall)
        const wallGroup = new THREE.Group();
        wallGroup.position.set(0, 4, -5);
        roomGroup.add(wallGroup);

        const backWallGeo = new THREE.PlaneGeometry(24, 8);
        const backWallMat = new THREE.MeshStandardMaterial({
            color: 0x090b10,
            roughness: 0.8
        });
        const backWall = new THREE.Mesh(backWallGeo, backWallMat);
        wallGroup.add(backWall);

        // Procedural Fluted Acoustic Wood Slats
        const slatMat = new THREE.MeshStandardMaterial({
            color: 0x1b1814,
            roughness: 0.45,
            metalness: 0.1
        });
        const bronzeAccentMat = new THREE.MeshStandardMaterial({
            color: 0xc59b27,
            roughness: 0.2,
            metalness: 0.85
        });

        for (let x = -8; x <= 8; x += 0.45) {
            const isBronze = Math.abs(x) < 0.2 || Math.abs(x - 3.6) < 0.2 || Math.abs(x + 3.6) < 0.2;
            const slatGeo = new THREE.BoxGeometry(0.12, 7.8, 0.08);
            const slat = new THREE.Mesh(slatGeo, isBronze ? bronzeAccentMat : slatMat);
            slat.position.set(x, 0, 0.05);
            wallGroup.add(slat);
        }

        // Modern Luxury Low-Profile Sofa
        const sofaGroup = new THREE.Group();
        sofaGroup.position.set(0, 0, -1.2);
        roomGroup.add(sofaGroup);

        const sofaFabric = new THREE.MeshStandardMaterial({
            color: 0x1e2430,
            roughness: 0.65,
            metalness: 0.05
        });

        // Sofa Base
        const sofaBase = new THREE.Mesh(new THREE.BoxGeometry(4.2, 0.45, 1.6), sofaFabric);
        sofaBase.position.y = 0.225;
        sofaGroup.add(sofaBase);

        // Sofa Seat Cushions
        const cushionGeo = new THREE.BoxGeometry(1.95, 0.35, 1.45);
        const cushion1 = new THREE.Mesh(cushionGeo, sofaFabric);
        cushion1.position.set(-1.02, 0.55, 0);
        const cushion2 = new THREE.Mesh(cushionGeo, sofaFabric);
        cushion2.position.set(1.02, 0.55, 0);
        sofaGroup.add(cushion1, cushion2);

        // Sofa Backrest
        const sofaBack = new THREE.Mesh(new THREE.BoxGeometry(4.2, 0.75, 0.4), sofaFabric);
        sofaBack.position.set(0, 0.85, -0.65);
        sofaGroup.add(sofaBack);

        // Bronze Sofa Frame Plinth
        const sofaPlinth = new THREE.Mesh(new THREE.BoxGeometry(4.3, 0.06, 1.7), bronzeAccentMat);
        sofaPlinth.position.y = 0.03;
        sofaGroup.add(sofaPlinth);

        // Architectural Monolithic Coffee Table (Black Marble & Bronze)
        const tableGroup = new THREE.Group();
        tableGroup.position.set(0, 0, 1.2);
        roomGroup.add(tableGroup);

        const marbleTop = new THREE.Mesh(
            new THREE.CylinderGeometry(1.2, 1.2, 0.12, 32),
            new THREE.MeshStandardMaterial({ color: 0x11141c, roughness: 0.15, metalness: 0.3 })
        );
        marbleTop.position.y = 0.45;
        tableGroup.add(marbleTop);

        const tableStem = new THREE.Mesh(
            new THREE.CylinderGeometry(0.35, 0.6, 0.4, 24),
            bronzeAccentMat
        );
        tableStem.position.y = 0.2;
        tableGroup.add(tableStem);

        // Floor Architectural Luminaire (Stem Lamp)
        const lampGroup = new THREE.Group();
        lampGroup.position.set(-2.8, 0, -0.5);
        roomGroup.add(lampGroup);

        const lampBase = new THREE.Mesh(new THREE.CylinderGeometry(0.4, 0.4, 0.06, 24), bronzeAccentMat);
        lampBase.position.y = 0.03;
        const lampStem = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, 2.4, 16), bronzeAccentMat);
        lampStem.position.y = 1.2;
        const lampShade = new THREE.Mesh(
            new THREE.SphereGeometry(0.28, 24, 24),
            new THREE.MeshStandardMaterial({
                color: 0xfff0d4,
                emissive: 0xc59b27,
                emissiveIntensity: 1.5,
                roughness: 0.1
            })
        );
        lampShade.position.y = 2.4;
        lampGroup.add(lampBase, lampStem, lampShade);

        // Floating Kinetic Architectural Rings (Abstract Spatial Art)
        const sculptureGroup = new THREE.Group();
        sculptureGroup.position.set(2.6, 1.8, -0.5);
        roomGroup.add(sculptureGroup);

        const ringGeo1 = new THREE.TorusGeometry(0.65, 0.025, 16, 64);
        const ringMesh1 = new THREE.Mesh(ringGeo1, bronzeAccentMat);
        const ringGeo2 = new THREE.TorusGeometry(0.45, 0.02, 16, 64);
        const ringMesh2 = new THREE.Mesh(ringGeo2, new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.1, metalness: 0.9 }));
        const ringGeo3 = new THREE.OctahedronGeometry(0.25, 0);
        const ringMesh3 = new THREE.Mesh(ringGeo3, bronzeAccentMat);

        sculptureGroup.add(ringMesh1, ringMesh2, ringMesh3);

        // Ambient Floating Light Particles (Atmospheric Depth)
        const particleCount = 75;
        const particleGeo = new THREE.BufferGeometry();
        const positions = new Float32Array(particleCount * 3);

        for (let i = 0; i < particleCount * 3; i += 3) {
            positions[i] = (Math.random() - 0.5) * 12;
            positions[i + 1] = Math.random() * 4 + 0.2;
            positions[i + 2] = (Math.random() - 0.5) * 8;
        }
        particleGeo.setAttribute('position', new THREE.BufferAttribute(positions, 3));

        const particleMat = new THREE.PointsMaterial({
            color: 0xdfb84c,
            size: 0.04,
            transparent: true,
            opacity: 0.6
        });
        const particles = new THREE.Points(particleGeo, particleMat);
        scene.add(particles);

        // Mouse Parallax Controls
        let targetX = 0;
        let targetY = 0;
        let currentX = 0;
        let currentY = 0;

        window.addEventListener('mousemove', (e) => {
            targetX = (e.clientX / window.innerWidth - 0.5) * 2;
            targetY = (e.clientY / window.innerHeight - 0.5) * 2;
        }, { passive: true });

        // Animation Loop & Viewport Caching
        let isHeroVisible = true;
        const observer = new IntersectionObserver((entries) => {
            isHeroVisible = entries[0].isIntersecting;
        }, { threshold: 0.05 });
        observer.observe(canvas);

        let clock = new THREE.Clock();

        function animate() {
            requestAnimationFrame(animate);
            if (!isHeroVisible) return;

            const elapsedTime = clock.getElapsedTime();

            // Smooth camera lag
            currentX += (targetX - currentX) * 0.04;
            currentY += (targetY - currentY) * 0.04;

            camera.position.x = currentX * 0.75;
            camera.position.y = 1.4 - currentY * 0.35;
            camera.lookAt(0, 0.9, -0.5);

            // Subtle rotation of kinetic sculpture
            ringMesh1.rotation.x = elapsedTime * 0.35;
            ringMesh1.rotation.y = elapsedTime * 0.2;
            ringMesh2.rotation.y = -elapsedTime * 0.45;
            ringMesh2.rotation.z = elapsedTime * 0.3;
            ringMesh3.rotation.x = elapsedTime * 0.6;
            ringMesh3.rotation.y = elapsedTime * 0.5;

            // Gentle floating hovering of table & sculpture
            sculptureGroup.position.y = 1.8 + Math.sin(elapsedTime * 1.2) * 0.08;

            // Floating dust particles gentle oscillation
            const posAttr = particleGeo.attributes.position;
            for (let i = 1; i < particleCount * 3; i += 3) {
                posAttr.array[i] += Math.sin(elapsedTime + i) * 0.0015;
            }
            posAttr.needsUpdate = true;

            // Lamp subtle breathing pulse
            lampLight.intensity = 2.2 + Math.sin(elapsedTime * 2) * 0.15;

            renderer.render(scene, camera);
        }
        animate();

        // Responsive Resizing
        window.addEventListener('resize', () => {
            const width = canvas.parentElement.clientWidth;
            const height = canvas.parentElement.clientHeight;
            camera.aspect = width / height;
            camera.updateProjectionMatrix();
            renderer.setSize(width, height, false);
        });
    }

    /* ==========================================================================
       3. Three.js About Section Spatial Sculpture
       ========================================================================== */
    function initAbout3D() {
        const canvas = document.getElementById('about-3d-canvas');
        if (!canvas || typeof THREE === 'undefined') return;

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(40, canvas.clientWidth / canvas.clientHeight, 0.1, 50);
        camera.position.set(0, 0, 4.5);

        const renderer = new THREE.WebGLRenderer({
            canvas: canvas,
            antialias: true,
            alpha: true
        });
        renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

        // Lighting
        const amb = new THREE.AmbientLight(0xffffff, 1.4);
        const dir1 = new THREE.DirectionalLight(0x2563eb, 2.2);
        dir1.position.set(3, 4, 3);
        const dir2 = new THREE.DirectionalLight(0x60a5fa, 1.6);
        dir2.position.set(-3, -2, 2);
        scene.add(amb, dir1, dir2);

        // Architectural Spatial Pavilion Sculpture
        const group = new THREE.Group();
        scene.add(group);

        const wireMat = new THREE.MeshStandardMaterial({
            color: 0x1d4ed8,
            metalness: 0.8,
            roughness: 0.2,
            wireframe: true
        });
        const glassMat = new THREE.MeshPhysicalMaterial({
            color: 0x3b82f6,
            metalness: 0.1,
            roughness: 0.1,
            transmission: 0.85,
            thickness: 0.5,
            transparent: true,
            opacity: 0.65
        });

        const icosahedron = new THREE.Mesh(new THREE.IcosahedronGeometry(1.35, 1), wireMat);
        const innerCube = new THREE.Mesh(new THREE.BoxGeometry(1.2, 1.2, 1.2), glassMat);
        const coreOct = new THREE.Mesh(new THREE.OctahedronGeometry(0.55, 0), new THREE.MeshStandardMaterial({ color: 0x2563eb, metalness: 0.9, roughness: 0.15 }));

        group.add(icosahedron, innerCube, coreOct);

        let mouseX = 0;
        let mouseY = 0;
        canvas.addEventListener('mousemove', (e) => {
            const rect = canvas.getBoundingClientRect();
            mouseX = ((e.clientX - rect.left) / rect.width - 0.5) * 2;
            mouseY = ((e.clientY - rect.top) / rect.height - 0.5) * 2;
        }, { passive: true });

        let isAboutVisible = true;
        const obs = new IntersectionObserver((entries) => {
            isAboutVisible = entries[0].isIntersecting;
        }, { threshold: 0.05 });
        obs.observe(canvas);

        function renderAbout() {
            requestAnimationFrame(renderAbout);
            if (!isAboutVisible) return;

            group.rotation.x += 0.005 + mouseY * 0.02;
            group.rotation.y += 0.008 + mouseX * 0.02;
            innerCube.rotation.x -= 0.004;
            innerCube.rotation.y -= 0.006;
            coreOct.rotation.z += 0.01;

            renderer.render(scene, camera);
        }
        renderAbout();

        window.addEventListener('resize', () => {
            const w = canvas.clientWidth;
            const h = canvas.clientHeight;
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h, false);
        });
    }

    /* ==========================================================================
       4. 3D Card Tilt Interaction (Perspective Hover)
       ========================================================================== */
    function initCardTilt() {
        if (isTouchDevice || prefersReducedMotion) return;

        const tiltCards = document.querySelectorAll('.spatial-project-card, .spatial-service-card, .spatial-choice-card, .spatial-stat-card');

        tiltCards.forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;

                const rotateX = ((y - centerY) / centerY) * -8;
                const rotateY = ((x - centerX) / centerX) * 8;

                card.style.transform = `perspective(1000px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg) translateY(-6px)`;
            });

            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0)';
            });
        });
    }

    /* ==========================================================================
       5. Interactive Category Filtering (Project Showcase & Gallery)
       ========================================================================== */
    function initCategoryFiltering() {
        const filterBtns = document.querySelectorAll('.spatial-filter-btn');
        const projectCards = document.querySelectorAll('.spatial-project-card, .gallery-item');

        if (!filterBtns.length || !projectCards.length) return;

        filterBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const category = this.getAttribute('data-filter') || 'all';

                projectCards.forEach(card => {
                    const cardCat = card.getAttribute('data-category') || '';
                    if (category === 'all' || cardCat.toLowerCase().includes(category.toLowerCase())) {
                        card.style.display = '';
                        setTimeout(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'scale(1)';
                        }, 10);
                    } else {
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.94)';
                        setTimeout(() => {
                            card.style.display = 'none';
                        }, 300);
                    }
                });
            });
        });
    }

    /* ==========================================================================
       6. 3D Testimonial Carousel Controller
       ========================================================================== */
    function initTestimonialCarousel() {
        const slides = document.querySelectorAll('.spatial-testimonial-card');
        const prevBtn = document.getElementById('prev-testimonial-btn');
        const nextBtn = document.getElementById('next-testimonial-btn');

        if (!slides.length) return;

        let currentIndex = 0;

        function showSlide(index) {
            if (index < 0) index = slides.length - 1;
            if (index >= slides.length) index = 0;
            currentIndex = index;

            slides.forEach((slide, i) => {
                if (i === currentIndex) {
                    slide.style.display = 'block';
                    setTimeout(() => {
                        slide.style.opacity = '1';
                        slide.style.transform = 'perspective(1000px) rotateY(0deg) scale(1)';
                    }, 20);
                } else {
                    slide.style.opacity = '0';
                    slide.style.transform = 'perspective(1000px) rotateY(12deg) scale(0.92)';
                    setTimeout(() => {
                        slide.style.display = 'none';
                    }, 400);
                }
            });
        }

        showSlide(0);

        if (prevBtn) {
            prevBtn.addEventListener('click', () => showSlide(currentIndex - 1));
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => showSlide(currentIndex + 1));
        }

        // Auto advance every 8 seconds
        let autoPlay = setInterval(() => {
            showSlide(currentIndex + 1);
        }, 8000);

        const container = document.querySelector('.spatial-carousel-container');
        if (container) {
            container.addEventListener('mouseenter', () => clearInterval(autoPlay));
            container.addEventListener('mouseleave', () => {
                autoPlay = setInterval(() => showSlide(currentIndex + 1), 8000);
            });
        }
    }

    /* ==========================================================================
       7. Multi-Step Get Quote Wizard Controller
       ========================================================================== */
    function initQuoteWizard() {
        const wizard = document.querySelector('.spatial-wizard-wrapper');
        if (!wizard) return;

        const panes = wizard.querySelectorAll('.spatial-wizard-pane');
        const stepNodes = wizard.querySelectorAll('.spatial-wizard-step-node');
        const nextBtns = wizard.querySelectorAll('.wizard-next-btn');
        const prevBtns = wizard.querySelectorAll('.wizard-prev-btn');

        let currentStep = 1;
        const totalSteps = panes.length;

        function updateStepUI(step) {
            currentStep = step;

            // Panes
            panes.forEach((p, idx) => {
                if (idx + 1 === step) {
                    p.classList.add('active');
                } else {
                    p.classList.remove('active');
                }
            });

            // Nodes
            stepNodes.forEach((node, idx) => {
                const nodeStep = idx + 1;
                node.classList.remove('active', 'completed');
                if (nodeStep === step) {
                    node.classList.add('active');
                } else if (nodeStep < step) {
                    node.classList.add('completed');
                }
            });

            // Trigger budget calculation review on step 6
            if (step === 6 && typeof window.updateStage6BudgetReview === 'function') {
                window.updateStage6BudgetReview();
            }

            // Scroll gently into view of wizard, clearing the floating/sticky navigation
            const navEl = document.querySelector('.spatial-nav');
            let headerOffset = 135;
            if (navEl) {
                const navComputedTop = parseInt(window.getComputedStyle(navEl).top || '24', 10) || 24;
                headerOffset = Math.max(headerOffset, (navEl.offsetHeight || 70) + navComputedTop + 20);
            }
            const wizardPos = wizard.getBoundingClientRect().top + window.pageYOffset;
            const targetY = Math.max(0, wizardPos - headerOffset);
            window.scrollTo({
                top: targetY,
                behavior: 'smooth'
            });
        }

        nextBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                // Validate required fields in current pane
                const activePane = wizard.querySelector('.spatial-wizard-pane.active');
                const reqInputs = activePane ? activePane.querySelectorAll('input[required]:not([type="radio"]), select[required]') : [];
                let valid = true;

                reqInputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.style.borderColor = '#ef4444';
                        valid = false;
                    } else {
                        input.style.borderColor = '';
                    }
                });

                // Radio groups with required attribute (e.g. package_multiplier, budget_range)
                if (activePane) {
                    const radioGroups = {};
                    activePane.querySelectorAll('input[type="radio"][required]').forEach(r => {
                        radioGroups[r.name] = true;
                    });
                    for (const groupName in radioGroups) {
                        const checked = activePane.querySelector(`input[name="${groupName}"]:checked`);
                        if (!checked) {
                            valid = false;
                            if (groupName === 'package_multiplier') {
                                const ind = document.getElementById('multiplier_req_indicator');
                                if (ind) ind.style.display = 'inline-block';
                                const grid = document.getElementById('design_multiplier_grid');
                                if (grid) {
                                    grid.style.outline = '2px solid #ef4444';
                                    grid.style.borderRadius = '12px';
                                    setTimeout(() => { grid.style.outline = ''; }, 3500);
                                }
                                alert('Please select a Design Level (Essential Economy 1.00×, Signature Premium 1.35×, or Ultra Palatial 1.75×) before proceeding.');
                                return;
                            } else if (groupName === 'budget_range') {
                                alert('Please select an Estimated Budget Range before proceeding.');
                                return;
                            }
                        }
                    }
                }

                if (!valid) {
                    alert('Please complete the highlighted required fields before proceeding.');
                    return;
                }

                if (currentStep < totalSteps) {
                    updateStepUI(currentStep + 1);
                }
            });
        });

        prevBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                if (currentStep > 1) {
                    updateStepUI(currentStep - 1);
                }
            });
        });

        stepNodes.forEach((node, idx) => {
            node.addEventListener('click', () => {
                const targetStep = idx + 1;
                if (targetStep <= currentStep || node.classList.contains('completed')) {
                    updateStepUI(targetStep);
                }
            });
        });

        // Choice & Budget Card Selection with Full Accessibility (v2.3.0)
        wizard.querySelectorAll('.spatial-choice-card, .spatial-budget-card').forEach(card => {
            function activateCard() {
                const group = card.getAttribute('data-choice-group');
                if (group) {
                    wizard.querySelectorAll(`[data-choice-group="${group}"]`).forEach(c => {
                        c.classList.remove('selected');
                        c.setAttribute('aria-checked', 'false');
                    });
                }
                card.classList.add('selected');
                card.setAttribute('aria-checked', 'true');
                const radioOrCheckbox = card.querySelector('input[type="radio"], input[type="checkbox"]');
                if (radioOrCheckbox) {
                    radioOrCheckbox.checked = true;
                    if (group === 'package_multiplier') {
                        const pkgName = card.getAttribute('data-package-name') || '';
                        const hiddenPkg = document.getElementById('hidden_package_type');
                        if (hiddenPkg) hiddenPkg.value = pkgName;
                        const ind = document.getElementById('multiplier_req_indicator');
                        if (ind) ind.style.display = 'none';
                    }
                    // Trigger change event for dynamic price calculators and preference summary
                    radioOrCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
                }

                if (typeof window.updateStage6BudgetReview === 'function') {
                    window.updateStage6BudgetReview();
                }
            }

            card.addEventListener('click', activateCard);
            card.addEventListener('keydown', function (e) {
                if (e.key === ' ' || e.key === 'Enter' || e.keyCode === 32 || e.keyCode === 13) {
                    e.preventDefault();
                    activateCard();
                }
            });
        });

        // =====================================================================
        // Stage 04 Smart Budget & Design Preferences Controller (v2.3.0)
        // =====================================================================
        const stage4Pane = wizard.querySelector('#wizard-pane-4');
        if (stage4Pane) {
            const recommendationBox = stage4Pane.querySelector('#budget_recommendation_box');
            const prefSummaryTier = stage4Pane.querySelector('#pref_summary_tier');
            const prefSummaryStyle = stage4Pane.querySelector('#pref_summary_style');
            const prefSummaryBudget = stage4Pane.querySelector('#pref_summary_budget');
            const styleSelect = stage4Pane.querySelector('#design_style');

            const tierNames = {
                '1.00': 'Essential Economy (1.00×)',
                '1.0': 'Essential Economy (1.00×)',
                '1': 'Essential Economy (1.00×)',
                'Essential Economy': 'Essential Economy (1.00×)',
                'Standard': 'Essential Economy (1.00×)',
                '1.35': 'Signature Premium (1.35×)',
                'Signature Premium': 'Signature Premium (1.35×)',
                'Premium': 'Signature Premium (1.35×)',
                '1.75': 'Ultra Palatial (1.75×)',
                'Ultra Palatial': 'Ultra Palatial (1.75×)',
                'Luxury': 'Ultra Palatial (1.75×)'
            };

            const budgetRecommendations = {
                '₹1L – ₹3L': { text: 'Essential Economy (1.00×)', isNotice: false, label: '₹1 Lakh – ₹3 Lakhs' },
                '₹1 Lakh – ₹3 Lakhs': { text: 'Essential Economy (1.00×)', isNotice: false, label: '₹1 Lakh – ₹3 Lakhs' },
                'budget_1_3': { text: 'Essential Economy (1.00×)', isNotice: false, label: '₹1 Lakh – ₹3 Lakhs' },

                '₹3L – ₹5L': { text: 'Essential Economy (1.00×)', isNotice: false, label: '₹3 Lakhs – ₹5 Lakhs' },
                '₹3 Lakhs – ₹5 Lakhs': { text: 'Essential Economy (1.00×)', isNotice: false, label: '₹3 Lakhs – ₹5 Lakhs' },
                'budget_3_5': { text: 'Essential Economy (1.00×)', isNotice: false, label: '₹3 Lakhs – ₹5 Lakhs' },

                '₹5L – ₹10L': { text: 'Essential Economy / Signature Premium', isNotice: false, label: '₹5 Lakhs – ₹10 Lakhs' },
                '₹5 Lakhs – ₹10 Lakhs': { text: 'Essential Economy / Signature Premium', isNotice: false, label: '₹5 Lakhs – ₹10 Lakhs' },
                'budget_5_10': { text: 'Essential Economy / Signature Premium', isNotice: false, label: '₹5 Lakhs – ₹10 Lakhs' },

                '₹10L – ₹25L': { text: 'Signature Premium (1.35×)', isNotice: false, label: '₹10 Lakhs – ₹25 Lakhs' },
                '₹10 Lakhs – ₹25 Lakhs': { text: 'Signature Premium (1.35×)', isNotice: false, label: '₹10 Lakhs – ₹25 Lakhs' },
                'budget_10_25': { text: 'Signature Premium (1.35×)', isNotice: false, label: '₹10 Lakhs – ₹25 Lakhs' },

                '₹25L – ₹50L': { text: 'Signature Premium (1.35×)', isNotice: false, label: '₹25 Lakhs – ₹50 Lakhs' },
                '₹25 Lakhs – ₹50 Lakhs': { text: 'Signature Premium (1.35×)', isNotice: false, label: '₹25 Lakhs – ₹50 Lakhs' },
                'budget_25_50': { text: 'Signature Premium (1.35×)', isNotice: false, label: '₹25 Lakhs – ₹50 Lakhs' },

                '₹50L – ₹1Cr': { text: 'Ultra Palatial (1.75×)', isNotice: false, label: '₹50 Lakhs – ₹1 Crore' },
                '₹50 Lakhs – ₹1 Crore': { text: 'Ultra Palatial (1.75×)', isNotice: false, label: '₹50 Lakhs – ₹1 Crore' },
                'budget_50_100': { text: 'Ultra Palatial (1.75×)', isNotice: false, label: '₹50 Lakhs – ₹1 Crore' },

                '₹1Cr+': { text: 'Ultra Palatial (1.75×)', isNotice: false, label: '₹1 Crore+' },
                '₹1 Crore+': { text: 'Ultra Palatial (1.75×)', isNotice: false, label: '₹1 Crore+' },
                'budget_100_plus': { text: 'Ultra Palatial (1.75×)', isNotice: false, label: '₹1 Crore+' },

                "I'm Not Sure Yet": {
                    text: "That's okay — we'll help you determine a suitable budget during your consultation.",
                    isNotice: true,
                    label: 'Not decided yet'
                },
                'budget_not_sure': {
                    text: "That's okay — we'll help you determine a suitable budget during your consultation.",
                    isNotice: true,
                    label: 'Not decided yet'
                }
            };

            function getBudgetDisplayLabel(val) {
                if (!val) return 'Not decided yet';
                const rec = budgetRecommendations[val];
                return rec ? rec.label : val;
            }

            function updateStage4Preferences() {
                // 1. Get currently selected design level multiplier
                const selectedTierRadio = stage4Pane.querySelector('input[name="package_multiplier"]:checked, input[name="package_type"]:checked');
                const tierVal = selectedTierRadio ? selectedTierRadio.value : '';
                const tierDisplayName = tierVal ? (tierNames[tierVal] || tierVal) : 'Selection Required';

                // 2. Get currently selected architectural style
                const styleVal = styleSelect ? styleSelect.value : 'Modern Luxury';

                // 3. Get currently selected budget range
                const selectedBudgetRadio = stage4Pane.querySelector('input[name="budget_range"]:checked');
                const budgetVal = selectedBudgetRadio ? selectedBudgetRadio.value : '₹10L – ₹25L';
                const budgetDisplayLabel = getBudgetDisplayLabel(budgetVal);

                // Update Live Preference Summary
                if (prefSummaryTier) prefSummaryTier.textContent = tierDisplayName;
                if (prefSummaryStyle) prefSummaryStyle.textContent = styleVal;
                if (prefSummaryBudget) {
                    prefSummaryBudget.textContent = budgetDisplayLabel;
                }

                // Update Recommendation UI
                if (recommendationBox) {
                    const rec = budgetRecommendations[budgetVal];
                    if (rec) {
                        if (rec.isNotice) {
                            recommendationBox.className = 'spatial-budget-recommendation notice-mode';
                            recommendationBox.innerHTML = `<span>${rec.text}</span>`;
                        } else {
                            recommendationBox.className = 'spatial-budget-recommendation';
                            recommendationBox.innerHTML = `<span class="recommendation-badge">⭐ Recommended for your budget:</span> <strong id="budget_recommendation_text" class="recommendation-highlight">${rec.text}</strong>`;
                        }
                    }
                }

                // Synchronize Review Stage (Stage 06) fields
                const revPkg = wizard.querySelector('#rev_pkg');
                const revStyle = wizard.querySelector('#rev_style');
                const revBudget = wizard.querySelector('#rev_budget');
                if (revPkg) revPkg.textContent = tierDisplayName;
                if (revStyle) revStyle.textContent = styleVal;
                if (revBudget) {
                    revBudget.textContent = budgetDisplayLabel;
                }

                if (typeof window.updateStage6BudgetReview === 'function') {
                    window.updateStage6BudgetReview();
                }
            }

            // Listeners
            stage4Pane.querySelectorAll('input[name="package_multiplier"], input[name="package_type"]').forEach(r => {
                r.addEventListener('change', updateStage4Preferences);
            });
            stage4Pane.querySelectorAll('input[name="budget_range"]').forEach(r => {
                r.addEventListener('change', updateStage4Preferences);
            });
            if (styleSelect) {
                styleSelect.addEventListener('change', updateStage4Preferences);
            }

            // Initial execution
            updateStage4Preferences();
        }

        // =====================================================================
        // Multi-Room Dimension Calculator Live Engine (v2.2.3 Polish)
        // =====================================================================
        const roomsContainer = wizard.querySelector('#rooms_container');
        const applyRoomAreaBtn = wizard.querySelector('#apply_room_area_btn');
        const applyBtnLabel = wizard.querySelector('#apply_btn_label');
        const addRoomBtnFooter = wizard.querySelector('#add_room_btn_footer');
        const clearAllRoomsBtn = wizard.querySelector('#clear_all_rooms_btn');
        const totalAreaInput = wizard.querySelector('#approx_area');
        const approxAreaStatus = wizard.querySelector('#approx_area_status');
        const summaryEmptyState = wizard.querySelector('#summary_empty_state');
        const summaryBreakdownList = wizard.querySelector('#summary_breakdown_list');
        const summaryTotalSection = wizard.querySelector('#summary_total_section');
        const summaryTotalDisplay = wizard.querySelector('#summary_total_display');
        const summaryRoomCount = wizard.querySelector('#summary_room_count');

        let lastAppliedArea = null;
        let isAutoFilled = false;

        // Suggested sequential room identifiers
        const defaultRoomNames = ['Living Room', 'Bedroom', 'Kitchen', 'Dining Room', 'Master Bedroom', 'Home Office', 'Guest Bedroom', 'Balcony / Terrace'];

        // Validate and parse dimension inputs (allows decimals like 12.5, 10.5)
        function validateDimension(val, fieldName) {
            if (val === null || val === undefined) return { valid: false, empty: true, error: '' };
            const trimmed = String(val).trim();
            if (trimmed === '') return { valid: false, empty: true, error: '' };
            const num = parseFloat(trimmed);
            if (isNaN(num)) {
                return { valid: false, empty: false, error: `Please enter a valid room ${fieldName}.` };
            }
            if (num <= 0) {
                return { valid: false, empty: false, error: `Please enter a valid room ${fieldName}.` };
            }
            if (num > 500) {
                return { valid: false, empty: false, error: `Room ${fieldName} exceeds limit of 500 ft.` };
            }
            return { valid: true, empty: false, value: num };
        }

        // Format sq.ft display strings
        function formatAreaDisplay(num) {
            if (num <= 0 || isNaN(num)) return '0 sq.ft';
            const rounded = Math.round(num * 100) / 100;
            return (Number.isInteger(rounded) ? rounded.toString() : rounded.toFixed(2)) + ' sq.ft';
        }

        // Format clean numeric value for inputs and button labels
        function formatAreaNumber(num) {
            const rounded = Math.round(num * 100) / 100;
            return Number.isInteger(rounded) ? rounded.toString() : rounded.toFixed(2);
        }

        // Contextual status pill below Total Carpet Area field
        function updateCarpetAreaStatus(type) {
            if (!approxAreaStatus) return;
            if (type === 'autofill') {
                approxAreaStatus.className = 'carpet-area-status status-autofill';
                approxAreaStatus.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg> <span>&#10003; Auto-filled from Room Dimension Calculator</span>';
                approxAreaStatus.style.display = 'inline-flex';
            } else if (type === 'manual') {
                approxAreaStatus.className = 'carpet-area-status status-manual';
                approxAreaStatus.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> <span>Manually adjusted</span>';
                approxAreaStatus.style.display = 'inline-flex';
            } else {
                approxAreaStatus.className = 'carpet-area-status';
                approxAreaStatus.innerHTML = '';
                approxAreaStatus.style.display = 'none';
            }
        }

        // Recalculates each room independently, updates summary and apply button
        function recalculateAllRooms() {
            if (!roomsContainer) return 0;

            const roomRows = roomsContainer.querySelectorAll('.calculator-room-row');
            let grandTotalArea = 0;
            let hasAnyEnteredDimensions = false;
            const validRoomsList = [];

            roomRows.forEach((row, idx) => {
                const displayNum = idx + 1;
                const numberTag = row.querySelector('.room-number-tag');
                const titleDisplay = row.querySelector('.room-title-display');
                const nameInput = row.querySelector('.room-name');
                const lenInput = row.querySelector('.room-length');
                const widInput = row.querySelector('.room-width');
                const lenError = row.querySelector('.room-length-error');
                const widError = row.querySelector('.room-width-error');
                const areaBadge = row.querySelector('.room-individual-area');
                const removeBtn = row.querySelector('.remove-room-btn');

                if (numberTag) numberTag.textContent = 'Room ' + displayNum;
                if (removeBtn) removeBtn.setAttribute('aria-label', 'Remove Room ' + displayNum);

                const defaultFallback = defaultRoomNames[idx % defaultRoomNames.length] || ('Room ' + displayNum);
                const roomName = (nameInput && nameInput.value.trim()) ? nameInput.value.trim() : defaultFallback;
                if (titleDisplay) titleDisplay.textContent = roomName;

                // Sync form input names and IDs for accessibility and PHP POST
                if (nameInput) {
                    nameInput.name = `rooms[${idx}][name]`;
                    nameInput.id = `room_name_${idx}`;
                    const label = row.querySelector('label[for^="room_name_"]');
                    if (label) label.setAttribute('for', `room_name_${idx}`);
                }
                if (lenInput) {
                    lenInput.name = `rooms[${idx}][length]`;
                    lenInput.id = `room_length_${idx}`;
                    const label = row.querySelector('label[for^="room_length_"]');
                    if (label) label.setAttribute('for', `room_length_${idx}`);
                }
                if (widInput) {
                    widInput.name = `rooms[${idx}][width]`;
                    widInput.id = `room_width_${idx}`;
                    const label = row.querySelector('label[for^="room_width_"]');
                    if (label) label.setAttribute('for', `room_width_${idx}`);
                }

                const rawLen = lenInput ? lenInput.value.trim() : '';
                const rawWid = widInput ? widInput.value.trim() : '';
                if (rawLen !== '' || rawWid !== '') {
                    hasAnyEnteredDimensions = true;
                }

                // Validate length
                const lenRes = validateDimension(rawLen, 'length');
                if (!lenRes.valid && !lenRes.empty) {
                    if (lenInput) lenInput.classList.add('has-error');
                    if (lenError) {
                        lenError.textContent = lenRes.error;
                        lenError.style.display = 'block';
                    }
                } else {
                    if (lenInput) lenInput.classList.remove('has-error');
                    if (lenError) {
                        lenError.textContent = '';
                        lenError.style.display = 'none';
                    }
                }

                // Validate width
                const widRes = validateDimension(rawWid, 'width');
                if (!widRes.valid && !widRes.empty) {
                    if (widInput) widInput.classList.add('has-error');
                    if (widError) {
                        widError.textContent = widRes.error;
                        widError.style.display = 'block';
                    }
                } else {
                    if (widInput) widInput.classList.remove('has-error');
                    if (widError) {
                        widError.textContent = '';
                        widError.style.display = 'none';
                    }
                }

                // Calculate room area if both dimensions are valid positive numbers
                let roomArea = 0;
                if (lenRes.valid && widRes.valid) {
                    roomArea = lenRes.value * widRes.value;
                    grandTotalArea += roomArea;
                    validRoomsList.push({
                        name: roomName,
                        area: roomArea
                    });
                    if (areaBadge) {
                        areaBadge.textContent = formatAreaDisplay(roomArea);
                    }
                } else {
                    if (areaBadge) {
                        areaBadge.textContent = '—';
                    }
                }
            });

            // Update Clear All button: show only if multiple rooms or dimensions entered
            if (clearAllRoomsBtn) {
                if (roomRows.length > 1 || hasAnyEnteredDimensions) {
                    clearAllRoomsBtn.style.display = 'inline-flex';
                } else {
                    clearAllRoomsBtn.style.display = 'none';
                }
            }

            // Update summary room count badge
            if (summaryRoomCount) {
                const totalCount = roomRows.length;
                summaryRoomCount.textContent = `${totalCount} Room${totalCount > 1 ? 's' : ''}`;
            }

            // Update Area Summary & Apply button
            if (validRoomsList.length > 0 && grandTotalArea > 0) {
                if (summaryEmptyState) summaryEmptyState.style.display = 'none';
                if (summaryBreakdownList) {
                    summaryBreakdownList.style.display = 'flex';
                    summaryBreakdownList.innerHTML = validRoomsList.map(r => `
                        <div class="summary-breakdown-row" style="display: flex; justify-content: space-between; align-items: center; font-size: 0.86rem; color: #334155;">
                            <span class="summary-room-label" style="font-weight: 600;">${r.name}</span>
                            <span class="summary-room-leader" style="flex: 1; border-bottom: 1px dotted #cbd5e1; margin: 0 0.75rem; height: 1px;"></span>
                            <span class="summary-room-val" style="font-weight: 700; color: #0f172a; font-family: monospace; font-size: 0.9rem;">${formatAreaDisplay(r.area)}</span>
                        </div>
                    `).join('');
                }
                if (summaryTotalSection) summaryTotalSection.style.display = 'flex';
                if (summaryTotalDisplay) summaryTotalDisplay.textContent = formatAreaDisplay(grandTotalArea);

                if (applyRoomAreaBtn) {
                    applyRoomAreaBtn.disabled = false;
                    applyRoomAreaBtn.classList.remove('disabled');
                    const applyValText = formatAreaNumber(grandTotalArea);
                    if (applyBtnLabel) {
                        applyBtnLabel.innerHTML = `APPLY ${applyValText} SQ.FT &rarr;`;
                    } else {
                        applyRoomAreaBtn.innerHTML = `<span>APPLY ${applyValText} SQ.FT &rarr;</span>`;
                    }
                }
            } else {
                // Empty state
                if (summaryEmptyState) summaryEmptyState.style.display = 'block';
                if (summaryBreakdownList) {
                    summaryBreakdownList.style.display = 'none';
                    summaryBreakdownList.innerHTML = '';
                }
                if (summaryTotalSection) summaryTotalSection.style.display = 'none';
                if (summaryTotalDisplay) summaryTotalDisplay.textContent = '—';

                if (applyRoomAreaBtn) {
                    applyRoomAreaBtn.disabled = true;
                    applyRoomAreaBtn.classList.add('disabled');
                    if (applyBtnLabel) {
                        applyBtnLabel.innerHTML = 'APPLY AREA &rarr;';
                    } else {
                        applyRoomAreaBtn.innerHTML = '<span>APPLY AREA &rarr;</span>';
                    }
                }
            }

            return grandTotalArea;
        }

        // Attach listeners to room row
        function attachRoomListeners(row) {
            const nameInput = row.querySelector('.room-name');
            const lenInput = row.querySelector('.room-length');
            const widInput = row.querySelector('.room-width');
            const removeBtn = row.querySelector('.remove-room-btn');

            if (nameInput) {
                nameInput.addEventListener('input', () => recalculateAllRooms());
            }
            if (lenInput) {
                lenInput.addEventListener('input', () => recalculateAllRooms());
            }
            if (widInput) {
                widInput.addEventListener('input', () => recalculateAllRooms());
            }
            if (removeBtn) {
                removeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    row.remove();
                    recalculateAllRooms();
                });
            }
        }

        // Add new room dynamically
        function addNewRoom() {
            if (!roomsContainer) return;

            const existingRows = roomsContainer.querySelectorAll('.calculator-room-row');
            const newIndex = existingRows.length;
            const displayNum = newIndex + 1;
            const suggestedName = defaultRoomNames[newIndex % defaultRoomNames.length] || ('Room ' + displayNum);

            const newRow = document.createElement('div');
            newRow.className = 'calculator-room-row';
            newRow.setAttribute('data-room-index', newIndex);
            newRow.style.cssText = 'background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.15rem 1.25rem; transition: all 0.2s;';

            newRow.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="room-number-tag" style="font-size: 0.75rem; font-weight: 700; color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; padding: 0.2rem 0.55rem; border-radius: 6px; text-transform: uppercase;">Room ${displayNum}</span>
                        <span class="room-title-display" style="font-size: 0.95rem; font-weight: 700; color: #0f172a;">${suggestedName}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.65rem;">
                        <div style="font-size: 0.82rem; font-weight: 700; color: #0f172a; background: #f8fafc; border: 1px solid #cbd5e1; padding: 0.25rem 0.65rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <span style="font-size: 0.72rem; color: #64748b; font-weight: 600;">Area:</span>
                            <span class="room-individual-area">—</span>
                        </div>
                        <button type="button" class="remove-room-btn" aria-label="Remove Room ${displayNum}" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; padding: 0.3rem 0.65rem; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem;" title="Remove Room">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            <span>Remove</span>
                        </button>
                    </div>
                </div>

                <div class="room-fields-grid" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1.25rem;">
                    <div class="spatial-form-group" style="margin-bottom:0;">
                        <label class="spatial-form-label" for="room_name_${newIndex}" style="color: #334155; font-weight: 600;">Room Identifier</label>
                        <input type="text" id="room_name_${newIndex}" name="rooms[${newIndex}][name]" class="spatial-form-control room-name" value="${suggestedName}" placeholder="e.g. Living Room" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                    </div>
                    <div class="spatial-form-group" style="margin-bottom:0;">
                        <label class="spatial-form-label" for="room_length_${newIndex}" style="color: #334155; font-weight: 600;">Length (ft)</label>
                        <input type="number" step="any" min="0.1" id="room_length_${newIndex}" name="rooms[${newIndex}][length]" class="spatial-form-control room-length" value="" placeholder="e.g. 12" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                        <span class="room-dimension-error room-length-error" role="alert" style="display: none; font-size: 0.74rem; color: #dc2626; margin-top: 0.3rem; font-weight: 600;"></span>
                    </div>
                    <div class="spatial-form-group" style="margin-bottom:0;">
                        <label class="spatial-form-label" for="room_width_${newIndex}" style="color: #334155; font-weight: 600;">Width (ft)</label>
                        <input type="number" step="any" min="0.1" id="room_width_${newIndex}" name="rooms[${newIndex}][width]" class="spatial-form-control room-width" value="" placeholder="e.g. 10" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                        <span class="room-dimension-error room-width-error" role="alert" style="display: none; font-size: 0.74rem; color: #dc2626; margin-top: 0.3rem; font-weight: 600;"></span>
                    </div>
                </div>
            `;

            roomsContainer.appendChild(newRow);
            attachRoomListeners(newRow);

            const newLen = newRow.querySelector('.room-length');
            if (newLen) newLen.focus();

            recalculateAllRooms();
        }

        // Initialize listeners on pre-rendered room(s)
        if (roomsContainer) {
            const initialRows = roomsContainer.querySelectorAll('.calculator-room-row');
            initialRows.forEach(row => attachRoomListeners(row));
            recalculateAllRooms();
        }

        // Clear All action
        if (clearAllRoomsBtn) {
            clearAllRoomsBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (!roomsContainer) return;

                const allRows = roomsContainer.querySelectorAll('.calculator-room-row');
                allRows.forEach((row, idx) => {
                    if (idx > 0) row.remove();
                });

                const firstRow = roomsContainer.querySelector('.calculator-room-row');
                if (firstRow) {
                    const nameInput = firstRow.querySelector('.room-name');
                    const lenInput = firstRow.querySelector('.room-length');
                    const widInput = firstRow.querySelector('.room-width');
                    const titleDisplay = firstRow.querySelector('.room-title-display');
                    const lenError = firstRow.querySelector('.room-length-error');
                    const widError = firstRow.querySelector('.room-width-error');

                    if (nameInput) nameInput.value = 'Living Room';
                    if (titleDisplay) titleDisplay.textContent = 'Living Room';
                    if (lenInput) {
                        lenInput.value = '';
                        lenInput.classList.remove('has-error');
                    }
                    if (widInput) {
                        widInput.value = '';
                        widInput.classList.remove('has-error');
                    }
                    if (lenError) {
                        lenError.textContent = '';
                        lenError.style.display = 'none';
                    }
                    if (widError) {
                        widError.textContent = '';
                        widError.style.display = 'none';
                    }
                }

                recalculateAllRooms();
            });
        }

        // Single primary Add Room trigger (Bottom)
        if (addRoomBtnFooter) {
            addRoomBtnFooter.addEventListener('click', function(e) {
                e.preventDefault();
                addNewRoom();
            });
        }

        // Apply Room Area Button: copies calculated total to approx_area without reloading
        if (applyRoomAreaBtn) {
            applyRoomAreaBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const grandTotal = recalculateAllRooms();
                if (totalAreaInput && grandTotal > 0) {
                    const appliedVal = formatAreaNumber(grandTotal);
                    totalAreaInput.value = appliedVal;
                    lastAppliedArea = appliedVal;
                    isAutoFilled = true;
                    updateCarpetAreaStatus('autofill');

                    totalAreaInput.dispatchEvent(new Event('input', { bubbles: true }));
                    totalAreaInput.dispatchEvent(new Event('change', { bubbles: true }));

                    const origLabelHTML = applyBtnLabel ? applyBtnLabel.innerHTML : applyRoomAreaBtn.innerHTML;
                    if (applyBtnLabel) {
                        applyBtnLabel.innerHTML = `&#10003; APPLIED ${appliedVal} SQ.FT!`;
                    } else {
                        applyRoomAreaBtn.innerHTML = `<span>&#10003; APPLIED ${appliedVal} SQ.FT!</span>`;
                    }
                    applyRoomAreaBtn.style.background = '#16a34a';

                    setTimeout(() => {
                        applyRoomAreaBtn.style.background = '';
                        if (applyBtnLabel) {
                            applyBtnLabel.innerHTML = `APPLY ${appliedVal} SQ.FT &rarr;`;
                        } else {
                            applyRoomAreaBtn.innerHTML = origLabelHTML;
                        }
                    }, 1800);
                }
            });
        }

        // Manual override detector on main carpet area input
        if (totalAreaInput) {
            totalAreaInput.addEventListener('input', function() {
                const currentVal = totalAreaInput.value.trim();
                if (isAutoFilled && lastAppliedArea !== null) {
                    if (currentVal !== String(lastAppliedArea)) {
                        isAutoFilled = false;
                        updateCarpetAreaStatus('manual');
                    }
                } else if (currentVal !== '') {
                    updateCarpetAreaStatus('manual');
                } else {
                    updateCarpetAreaStatus('none');
                }
            });
        }

        // =====================================================================
        // Stage 05 Smart Typology-Based Capability Scope Controller (v2.4.0)
        // =====================================================================
        const stage5Pane = wizard.querySelector('#wizard-pane-5');
        if (stage5Pane) {
            const stage5Heading = stage5Pane.querySelector('#stage5_heading');
            const stage5Subheading = stage5Pane.querySelector('#stage5_subheading');
            const typologyChangeAlert = stage5Pane.querySelector('#capability_typology_change_alert');
            const typologyChangeText = stage5Pane.querySelector('#capability_typology_change_text');
            const dismissTypologyAlertBtn = stage5Pane.querySelector('#dismiss_typology_alert_btn');
            const selectedCountBadge = stage5Pane.querySelector('#capability_selected_count_badge');
            const selectAllBtn = stage5Pane.querySelector('#capability_select_all_btn');
            const clearAllBtn = stage5Pane.querySelector('#capability_clear_all_btn');
            const searchInput = stage5Pane.querySelector('#capability_search_input');
            const searchEmptyState = stage5Pane.querySelector('#capability_search_empty');
            const summaryBox = stage5Pane.querySelector('#capability_summary_box');
            const summaryCount = stage5Pane.querySelector('#capability_summary_count');
            const summaryList = stage5Pane.querySelector('#capability_summary_list');
            const allCapabilityCards = stage5Pane.querySelectorAll('.capability-item-card');
            const revCapabilities = wizard.querySelector('#rev_capabilities');
            const revCapabilitiesCount = wizard.querySelector('#rev_capabilities_count');

            const typologyConfig = {
                residential: {
                    title: 'CAPABILITY SCOPE — RESIDENTIAL',
                    subtitle: 'Select the design and execution capabilities required for your project.',
                    label: 'Residential Estate',
                    groups: {
                        'RESIDENTIAL DESIGN': ['Complete Home Interior Design', 'Luxury Villa Interiors', 'Apartment Space Optimization'],
                        'ROOM-SPECIFIC': ['Living Room Styling', 'Master Bedroom Planning', 'Kids Room Design'],
                        'KITCHEN & BATH': ['Modular Kitchen Installation', 'Bathroom Makeover'],
                        'VISUALIZATION': ['3D Interior Visualization']
                    }
                },
                commercial: {
                    title: 'CAPABILITY SCOPE — COMMERCIAL & RETAIL',
                    subtitle: 'Select the design and execution capabilities required for your project.',
                    label: 'Commercial / Retail',
                    groups: {
                        'COMMERCIAL & HOSPITALITY': ['Café Interior Design', 'Restaurant Interior Planning', 'Salon & Beauty Studio Design'],
                        'RETAIL': ['Retail Store Fit-Out', 'Fashion Boutique Design', 'Jewellery Store Interiors', 'Electronics Showroom Planning'],
                        'VISUALIZATION': ['3D Interior Visualization']
                    }
                },
                office: {
                    title: 'CAPABILITY SCOPE — EXECUTIVE OFFICE',
                    subtitle: 'Select the design and execution capabilities required for your project.',
                    label: 'Executive Office',
                    groups: {
                        'OFFICE PLANNING': ['Corporate Office Design', 'Executive Office Design'],
                        'WORKSPACE': ['Open Workspace Planning'],
                        'MEETING & CLIENT AREAS': ['Meeting / Boardroom Planning'],
                        'VISUALIZATION': ['3D Interior Visualization']
                    }
                }
            };

            function getActiveTypology() {
                const checkedRadio = wizard.querySelector('input[name="project_type"]:checked');
                if (checkedRadio) {
                    const rawVal = (checkedRadio.getAttribute('data-typology') || checkedRadio.value || '').toLowerCase();
                    if (rawVal.includes('residen')) return 'residential';
                    if (rawVal.includes('commerc')) return 'commercial';
                    if (rawVal.includes('office') || rawVal.includes('execut')) return 'office';
                }
                const selectedCard = wizard.querySelector('.spatial-choice-card.selected[data-choice-group="proj_type"]');
                if (selectedCard) {
                    const rawCard = (selectedCard.getAttribute('data-typology') || selectedCard.textContent || '').toLowerCase();
                    if (rawCard.includes('residen')) return 'residential';
                    if (rawCard.includes('commerc')) return 'commercial';
                    if (rawCard.includes('office') || rawCard.includes('execut')) return 'office';
                }
                return 'residential';
            }

            function resolveGroupBadge(title, category, typoKey) {
                const cfg = typologyConfig[typoKey];
                if (cfg && cfg.groups) {
                    for (const [groupName, titles] of Object.entries(cfg.groups)) {
                        if (titles.includes(title)) {
                            return groupName;
                        }
                    }
                }
                if (title.includes('3D') || category === 'Design Service') return 'VISUALIZATION';
                return category.toUpperCase();
            }

            function updateRecommendations(typoKey) {
                const propTypeSelect = wizard.querySelector('#property_type');
                const propVal = (propTypeSelect ? propTypeSelect.value : '').toLowerCase();

                allCapabilityCards.forEach(card => {
                    const id = card.dataset.serviceId;
                    const title = card.dataset.title || '';
                    const badge = card.querySelector('#rec_badge_' + id);
                    if (!badge) return;

                    let isRec = false;
                    if (typoKey === 'residential') {
                        if (propVal.includes('villa') && title === 'Luxury Villa Interiors') isRec = true;
                        else if ((propVal.includes('apartment') || propVal.includes('bhk') || propVal.includes('penthouse')) && title === 'Apartment Space Optimization') isRec = true;
                        else if (!propVal && title === 'Complete Home Interior Design') isRec = true;
                        else if (title === '3D Interior Visualization') isRec = true;
                    } else if (typoKey === 'commercial') {
                        if ((propVal.includes('cafe') || propVal.includes('restaurant')) && (title === 'Café Interior Design' || title === 'Restaurant Interior Planning')) isRec = true;
                        else if ((propVal.includes('retail') || propVal.includes('showroom') || propVal.includes('boutique')) && title === 'Retail Store Fit-Out') isRec = true;
                        else if (title === '3D Interior Visualization') isRec = true;
                    } else if (typoKey === 'office') {
                        if (propVal.includes('co-working') && title === 'Open Workspace Planning') isRec = true;
                        else if ((propVal.includes('corporate') || propVal.includes('suite') || propVal.includes('floor')) && (title === 'Corporate Office Design' || title === 'Executive Office Design')) isRec = true;
                        else if (title === '3D Interior Visualization') isRec = true;
                    }

                    badge.style.display = isRec ? 'inline-flex' : 'none';
                });
            }

            let previousTypology = null;

            function syncCapabilitySelections() {
                const selectedCards = [];
                allCapabilityCards.forEach(card => {
                    if (!card.classList.contains('typology-visible')) return;
                    const cb = card.querySelector('input[type="checkbox"]');
                    if (cb && cb.checked) {
                        card.classList.add('selected');
                        card.setAttribute('aria-checked', 'true');
                        selectedCards.push({
                            id: card.dataset.serviceId,
                            title: card.dataset.title || 'Capability',
                            price: card.dataset.price || ''
                        });
                    } else {
                        card.classList.remove('selected');
                        card.setAttribute('aria-checked', 'false');
                    }
                });

                const count = selectedCards.length;

                // Live selection counter
                if (selectedCountBadge) {
                    selectedCountBadge.textContent = count === 1 ? '1 CAPABILITY SELECTED' : `${count} CAPABILITIES SELECTED`;
                }

                // Stage 05 summary box
                if (summaryCount) {
                    summaryCount.textContent = count === 1 ? '1 capability selected' : `${count} capabilities selected`;
                }
                if (summaryList) {
                    if (count === 0) {
                        summaryList.innerHTML = '<span style="font-size: 0.85rem; color: #64748b; font-style: italic;">No capabilities selected yet. Click any card above to include it in your project scope.</span>';
                    } else {
                        summaryList.innerHTML = selectedCards.map(item => `
                            <span class="capability-summary-chip">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="color: #0057FF;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span>${item.title}</span>
                            </span>
                        `).join('');
                    }
                }

                // Stage 06 Review
                if (revCapabilitiesCount) {
                    revCapabilitiesCount.textContent = count;
                }
                if (revCapabilities) {
                    if (count === 0) {
                        revCapabilities.innerHTML = '<span style="color: #64748b; font-style: italic; font-size: 0.88rem;">None selected (Base space turnkey execution only)</span>';
                    } else {
                        revCapabilities.innerHTML = selectedCards.map((item, idx) => `
                            <span style="display: inline-flex; align-items: center; gap: 0.35rem; background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; padding: 0.3rem 0.65rem; border-radius: 6px; font-size: 0.82rem; font-weight: 600;">
                                <span style="color: #2563eb; font-weight: 700;">${idx + 1}.</span>
                                <span>${item.title}</span>
                            </span>
                        `).join('');
                    }
                }
            }

            function refreshCapabilities(isTypologySwitch = false) {
                const activeTypo = getActiveTypology();
                const meta = typologyConfig[activeTypo] || typologyConfig['residential'];

                if (stage5Heading) stage5Heading.textContent = meta.title;
                if (stage5Subheading) stage5Subheading.textContent = meta.subtitle;

                let clearedIncompatibleCount = 0;
                const searchQuery = (searchInput ? searchInput.value : '').trim().toLowerCase();
                let matchingSearchCount = 0;

                allCapabilityCards.forEach(card => {
                    const typosAttr = (card.dataset.typologies || '').toLowerCase();
                    const typosList = typosAttr.split(',').map(s => s.trim());
                    const isCompatible = typosList.includes(activeTypo) || 
                        ((activeTypo === 'office' || activeTypo === 'executive') && (typosList.includes('office') || typosList.includes('executive'))) || 
                        typosList.includes('all');

                    const checkbox = card.querySelector('input[type="checkbox"]');
                    const id = card.dataset.serviceId;
                    const title = card.dataset.title || '';
                    const category = card.dataset.category || '';

                    const groupBadge = card.querySelector('#group_badge_' + id);
                    if (groupBadge) {
                        groupBadge.textContent = resolveGroupBadge(title, category, activeTypo);
                    }

                    if (!isCompatible) {
                        if (checkbox && checkbox.checked) {
                            checkbox.checked = false;
                            card.classList.remove('selected');
                            card.setAttribute('aria-checked', 'false');
                            clearedIncompatibleCount++;
                        }
                        card.style.display = 'none';
                        card.classList.remove('typology-visible');
                    } else {
                        card.classList.add('typology-visible');
                        const matchesSearch = !searchQuery || 
                            title.toLowerCase().includes(searchQuery) || 
                            category.toLowerCase().includes(searchQuery) ||
                            (groupBadge && groupBadge.textContent.toLowerCase().includes(searchQuery));

                        if (matchesSearch) {
                            card.style.display = 'flex';
                            matchingSearchCount++;
                        } else {
                            card.style.display = 'none';
                        }
                    }
                });

                if (isTypologySwitch && clearedIncompatibleCount > 0 && previousTypology && previousTypology !== activeTypo) {
                    if (typologyChangeAlert && typologyChangeText) {
                        typologyChangeText.textContent = `Your project type was changed to ${meta.label}. ${clearedIncompatibleCount} previously selected capability${clearedIncompatibleCount > 1 ? 'ies were' : ' was'} incompatible and removed from scope.`;
                        typologyChangeAlert.style.display = 'flex';
                    }
                }
                previousTypology = activeTypo;

                if (searchEmptyState) {
                    searchEmptyState.style.display = (searchQuery && matchingSearchCount === 0) ? 'block' : 'none';
                }

                updateRecommendations(activeTypo);
                syncCapabilitySelections();
            }

            // Interactive card selection
            allCapabilityCards.forEach(card => {
                const cb = card.querySelector('input[type="checkbox"]');

                function toggleCard(e) {
                    if (e && e.target === cb) {
                        syncCapabilitySelections();
                        return;
                    }
                    if (cb) {
                        cb.checked = !cb.checked;
                        syncCapabilitySelections();
                    }
                }

                card.addEventListener('click', toggleCard);

                card.addEventListener('keydown', function(e) {
                    if (e.key === ' ' || e.key === 'Enter' || e.keyCode === 32 || e.keyCode === 13) {
                        e.preventDefault();
                        toggleCard();
                    }
                });

                if (cb) {
                    cb.addEventListener('change', () => syncCapabilitySelections());
                }
            });

            // Select All (visible & allowed only)
            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    allCapabilityCards.forEach(card => {
                        if (card.classList.contains('typology-visible') && card.style.display !== 'none') {
                            const cb = card.querySelector('input[type="checkbox"]');
                            if (cb) cb.checked = true;
                        }
                    });
                    syncCapabilitySelections();
                });
            }

            // Clear All (Stage 05 only)
            if (clearAllBtn) {
                clearAllBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    allCapabilityCards.forEach(card => {
                        if (card.classList.contains('typology-visible')) {
                            const cb = card.querySelector('input[type="checkbox"]');
                            if (cb) cb.checked = false;
                        }
                    });
                    syncCapabilitySelections();
                });
            }

            // Search filter
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    refreshCapabilities(false);
                });
            }

            // Typology listeners
            wizard.querySelectorAll('input[name="project_type"]').forEach(radio => {
                radio.addEventListener('change', () => refreshCapabilities(true));
            });
            wizard.querySelectorAll('.spatial-choice-card[data-choice-group="proj_type"]').forEach(card => {
                card.addEventListener('click', () => {
                    setTimeout(() => refreshCapabilities(true), 60);
                });
            });

            const propSelect = wizard.querySelector('#property_type');
            if (propSelect) {
                propSelect.addEventListener('change', () => {
                    updateRecommendations(getActiveTypology());
                });
            }

            if (dismissTypologyAlertBtn && typologyChangeAlert) {
                dismissTypologyAlertBtn.addEventListener('click', () => {
                    typologyChangeAlert.style.display = 'none';
                });
            }

            // Initial load
            refreshCapabilities(false);
            window.__refreshCapabilityScope = refreshCapabilities;
            window.__syncCapabilitySelections = syncCapabilitySelections;
        }
    }

    /* ==========================================================================
       8. Reading Progress Bar (Blog)
       ========================================================================== */
    function initReadingProgressBar() {
        const bar = document.querySelector('.spatial-reading-progress-bar');
        if (!bar) return;

        window.addEventListener('scroll', () => {
            const scrollTop = window.scrollY;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            if (docHeight > 0) {
                const progress = (scrollTop / docHeight) * 100;
                bar.style.width = Math.min(progress, 100) + '%';
            }
        }, { passive: true });
    }

    /* ==========================================================================
       9. Navigation Sticky & Fullscreen Mobile Menu
       ========================================================================== */
    function initNavigation() {
        const nav = document.querySelector('.spatial-nav');
        if (nav) {
            window.addEventListener('scroll', () => {
                if (window.scrollY > 40) {
                    nav.classList.add('scrolled');
                } else {
                    nav.classList.remove('scrolled');
                }
            }, { passive: true });
        }

        const mobileToggle = document.querySelector('.spatial-mobile-toggle');
        const mobileDrawer = document.querySelector('.spatial-mobile-drawer');
        const drawerClose = document.querySelector('.spatial-drawer-close');

        if (mobileToggle && mobileDrawer) {
            mobileToggle.addEventListener('click', () => {
                mobileDrawer.classList.add('open');
            });
            if (drawerClose) {
                drawerClose.addEventListener('click', () => {
                    mobileDrawer.classList.remove('open');
                });
            }
            // Close on link click
            mobileDrawer.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    mobileDrawer.classList.remove('open');
                });
            });
        }
    }

    /* ==========================================================================
       10. Animated Statistics Counter
       ========================================================================== */
    function initCounters() {
        const counters = document.querySelectorAll('.spatial-stat-number[data-target]');
        if (!counters.length) return;

        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const target = parseInt(el.getAttribute('data-target'), 10) || 0;
                    let current = 0;
                    const duration = 1600;
                    const step = Math.ceil(target / (duration / 25));

                    const timer = setInterval(() => {
                        current += step;
                        if (current >= target) {
                            current = target;
                            clearInterval(timer);
                        }
                        el.innerHTML = '<span style="color: #0f172a !important; display: inline-block;">' + current + '</span><span class="plus" style="color: #2563eb !important; display: inline-block;">+</span>';
                    }, 25);

                    obs.unobserve(el);
                }
            });
        }, { threshold: 0.3 });

        counters.forEach(c => observer.observe(c));
    }

    // Initialize all modules when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        initCustomCursor();
        initNavigation();
        initHero3D();
        initAbout3D();
        initCardTilt();
        initCategoryFiltering();
        initTestimonialCarousel();
        initQuoteWizard();
        initReadingProgressBar();
        initCounters();
    });

})();
