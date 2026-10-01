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

            // Scroll gently into view of wizard
            wizard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        nextBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                // Validate required fields in current pane
                const activePane = wizard.querySelector('.spatial-wizard-pane.active');
                const reqInputs = activePane ? activePane.querySelectorAll('input[required], select[required]') : [];
                let valid = true;

                reqInputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.style.borderColor = '#ef4444';
                        valid = false;
                    } else {
                        input.style.borderColor = '';
                    }
                });

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

        // Choice Card Selection
        wizard.querySelectorAll('.spatial-choice-card').forEach(card => {
            card.addEventListener('click', function () {
                const group = this.getAttribute('data-choice-group');
                if (group) {
                    wizard.querySelectorAll(`.spatial-choice-card[data-choice-group="${group}"]`).forEach(c => c.classList.remove('selected'));
                }
                this.classList.add('selected');
                const radioOrCheckbox = this.querySelector('input[type="radio"], input[type="checkbox"]');
                if (radioOrCheckbox) {
                    radioOrCheckbox.checked = true;
                    // Trigger change event for dynamic price calculators
                    radioOrCheckbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });

        // =====================================================================
        // Multi-Room Dimension Calculator Live Engine
        // =====================================================================
        const roomsContainer = wizard.querySelector('#rooms_container');
        const calculatedAreaDisplay = wizard.querySelector('#calc_area_display');
        const applyRoomAreaBtn = wizard.querySelector('#apply_room_area_btn');
        const addRoomBtn = wizard.querySelector('#add_room_btn');
        const addRoomBtnFooter = wizard.querySelector('#add_room_btn_footer');
        const totalAreaInput = wizard.querySelector('#approx_area');

        // Safe dimension parser: ensures positive numbers only and avoids NaN
        function parseDimension(val) {
            if (val === null || val === undefined) return 0;
            const trimmed = String(val).trim();
            if (trimmed === '') return 0;
            const num = parseFloat(trimmed);
            if (isNaN(num) || !isFinite(num) || num <= 0) return 0;
            return num;
        }

        // Suggested sequential room identifiers
        const defaultRoomNames = ['Bedroom', 'Kitchen', 'Dining Room', 'Master Suite', 'Guest Bedroom', 'Home Office', 'Kids Room', 'Pooja Room', 'Balcony / Terrace'];

        // Recalculates each room independently and updates total sum
        function recalculateAllRooms(autoUpdateTotalInput = true) {
            if (!roomsContainer) return 0;

            const roomRows = roomsContainer.querySelectorAll('.calculator-room-row');
            let grandTotalArea = 0;

            roomRows.forEach((row, idx) => {
                // Keep room number label updated
                const numberTag = row.querySelector('.room-number-tag');
                if (numberTag) {
                    numberTag.textContent = 'Room ' + (idx + 1);
                }

                // Update input name attributes for PHP post array
                const nameInput = row.querySelector('.room-name');
                const lenInput = row.querySelector('.room-length');
                const widInput = row.querySelector('.room-width');

                if (nameInput) nameInput.name = `rooms[${idx}][name]`;
                if (lenInput) lenInput.name = `rooms[${idx}][length]`;
                if (widInput) widInput.name = `rooms[${idx}][width]`;

                // Calculate independent room area (Length x Width)
                const l = parseDimension(lenInput ? lenInput.value : 0);
                const w = parseDimension(widInput ? widInput.value : 0);
                let roomArea = 0;

                if (l > 0 && w > 0) {
                    roomArea = l * w;
                }

                // Update individual room area badge (prevents NaN, shows 0.00 sq.ft when empty)
                const areaBadge = row.querySelector('.room-individual-area');
                if (areaBadge) {
                    areaBadge.textContent = roomArea > 0 ? roomArea.toFixed(2) + ' sq.ft' : '0.00 sq.ft';
                }

                grandTotalArea += roomArea;
            });

            // Update top total area display badge
            if (calculatedAreaDisplay) {
                calculatedAreaDisplay.textContent = grandTotalArea > 0 ? grandTotalArea.toFixed(2) + ' sq.ft' : '0.00 sq.ft';
            }

            // Automatically update existing "Total Approximate Carpet Area (SQ.FT)" field
            if (totalAreaInput && autoUpdateTotalInput) {
                totalAreaInput.value = grandTotalArea > 0 ? Math.round(grandTotalArea) : (grandTotalArea === 0 ? 0 : totalAreaInput.value);
                totalAreaInput.dispatchEvent(new Event('input', { bubbles: true }));
            }

            return grandTotalArea;
        }

        // Attach input listeners and remove handler to a room row
        function attachRoomListeners(row) {
            const lenInput = row.querySelector('.room-length');
            const widInput = row.querySelector('.room-width');
            const removeBtn = row.querySelector('.remove-room-btn');

            if (lenInput) {
                lenInput.addEventListener('input', () => recalculateAllRooms(true));
            }
            if (widInput) {
                widInput.addEventListener('input', () => recalculateAllRooms(true));
            }
            if (removeBtn) {
                removeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    row.remove();
                    recalculateAllRooms(true);
                });
            }
        }

        // Dynamic Add Room Creator
        function addNewRoom() {
            if (!roomsContainer) return;

            const existingRows = roomsContainer.querySelectorAll('.calculator-room-row');
            const newIndex = existingRows.length;
            const displayNum = newIndex + 1;
            const suggestedName = defaultRoomNames[(newIndex - 1) % defaultRoomNames.length] || ('Room ' + displayNum);

            const newRow = document.createElement('div');
            newRow.className = 'calculator-room-row';
            newRow.setAttribute('data-room-index', newIndex);
            newRow.style.cssText = 'background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.15rem 1.25rem; transition: all 0.2s;';

            newRow.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="room-number-tag" style="font-size: 0.75rem; font-weight: 700; color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; padding: 0.2rem 0.55rem; border-radius: 6px; text-transform: uppercase;">Room ${displayNum}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.65rem;">
                        <div style="font-size: 0.82rem; font-weight: 700; color: #0f172a; background: #f8fafc; border: 1px solid #cbd5e1; padding: 0.25rem 0.65rem; border-radius: 6px; display: inline-flex; align-items: center; gap: 0.35rem;">
                            <span style="font-size: 0.72rem; color: #64748b; font-weight: 600;">Area:</span>
                            <span class="room-individual-area">0.00 sq.ft</span>
                        </div>
                        <button type="button" class="remove-room-btn" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; padding: 0.3rem 0.65rem; font-size: 0.75rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 0.3rem;" title="Remove Room">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            <span>Remove</span>
                        </button>
                    </div>
                </div>

                <div class="room-fields-grid" style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1.25rem;">
                    <div class="spatial-form-group" style="margin-bottom:0;">
                        <label class="spatial-form-label" style="color: #334155; font-weight: 600;">Room Identifier</label>
                        <input type="text" name="rooms[${newIndex}][name]" class="spatial-form-control room-name" value="${suggestedName}" placeholder="e.g. Master Bedroom" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                    </div>
                    <div class="spatial-form-group" style="margin-bottom:0;">
                        <label class="spatial-form-label" style="color: #334155; font-weight: 600;">Length (ft)</label>
                        <input type="number" step="0.1" min="0.1" name="rooms[${newIndex}][length]" class="spatial-form-control room-length" value="" placeholder="e.g. 12" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                    </div>
                    <div class="spatial-form-group" style="margin-bottom:0;">
                        <label class="spatial-form-label" style="color: #334155; font-weight: 600;">Width (ft)</label>
                        <input type="number" step="0.1" min="0.1" name="rooms[${newIndex}][width]" class="spatial-form-control room-width" value="" placeholder="e.g. 10" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a;">
                    </div>
                </div>
            `;

            roomsContainer.appendChild(newRow);
            attachRoomListeners(newRow);

            const newLenInput = newRow.querySelector('.room-length');
            if (newLenInput) {
                newLenInput.focus();
            }

            recalculateAllRooms(true);
        }

        // Initialize listeners on pre-rendered room(s)
        if (roomsContainer) {
            const initialRows = roomsContainer.querySelectorAll('.calculator-room-row');
            initialRows.forEach(row => attachRoomListeners(row));
            // Calculate initial state without forcibly overwriting pre-populated approx_area unless needed
            const initTotal = recalculateAllRooms(false);
            if (totalAreaInput && (!totalAreaInput.value || totalAreaInput.value === '0') && initTotal > 0) {
                totalAreaInput.value = Math.round(initTotal);
                totalAreaInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        // Add Room Button triggers
        if (addRoomBtn) {
            addRoomBtn.addEventListener('click', function(e) {
                e.preventDefault();
                addNewRoom();
            });
        }
        if (addRoomBtnFooter) {
            addRoomBtnFooter.addEventListener('click', function(e) {
                e.preventDefault();
                addNewRoom();
            });
        }

        // Apply Room Area Button (uses total calculated area across all rooms)
        if (applyRoomAreaBtn) {
            applyRoomAreaBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const grandTotal = recalculateAllRooms(false);
                if (totalAreaInput && grandTotal > 0) {
                    totalAreaInput.value = Math.round(grandTotal);
                    totalAreaInput.dispatchEvent(new Event('input', { bubbles: true }));
                    applyRoomAreaBtn.innerHTML = '<span>&#10003; Applied!</span>';
                    applyRoomAreaBtn.style.background = '#16a34a';
                    setTimeout(() => {
                        applyRoomAreaBtn.innerHTML = '<span>Apply to Area &darr;</span>';
                        applyRoomAreaBtn.style.background = '#2563eb';
                    }, 2000);
                }
            });
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
