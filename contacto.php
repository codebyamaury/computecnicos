<?php
require_once __DIR__ . '/app/Core/bootstrap.php';
$page_title = 'Contacto';
// Usamos index.css para mantener el mismo estilo futurista y contacto.css para lo específico
$extra_css = '<link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet" />' . "\n" .
    '<link rel="stylesheet" href="' . asset('css/index.css') . '">' . "\n" .
    '<link rel="stylesheet" href="' . asset('css/contacto.css') . '">' . "\n" .
    '<link href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css" rel="stylesheet" />';

include 'includes/header.php';
?>

<main class="flex-1 bg-[#050505] text-white relative z-10">

    <!-- Hero Section -->
    <section class="contact-hero">
        <div class="container mx-auto px-4 relative z-10">
            <h1 class="section-title animate-slide-up">Contáctanos</h1>
            <p class="text-gray-400 max-w-2xl mx-auto mt-4 animate-slide-up delay-100">
                Estamos aquí para ayudarte. Cuéntanos tu problema o consulta y nuestro equipo técnico te responderá a la
                brevedad.
            </p>
        </div>
    </section>

    <!-- Contact Grid -->
    <section class="container mx-auto px-4 pb-20">
        <div class="contact-grid">

            <!-- Formulario -->
            <div class="contact-card animate-slide-up delay-200 h-full flex flex-col">
                <h3 class="text-2xl font-bold mb-6 flex items-center gap-3 shrink-0">
                    <span class="w-1 h-8 bg-red-600 rounded-full"></span>
                    Envíanos un mensaje
                </h3>

                <form id="contactForm" class="mt-6 flex flex-col flex-1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6 shrink-0">
                        <div class="form-group">
                            <input type="text" id="nombre" class="form-input" placeholder=" " required>
                            <label for="nombre"
                                class="form-label absolute left-4 top-4 pointer-events-none transition-all">Nombre
                                Completo</label>
                            <span id="error-nombre" class="text-red-500 text-xs hidden mt-1">Por favor ingresa tu
                                nombre.</span>
                        </div>
                        <div class="form-group">
                            <input type="email" id="email" class="form-input" placeholder=" " required>
                            <label for="email"
                                class="form-label absolute left-4 top-4 pointer-events-none transition-all">Correo
                                Electrónico</label>
                            <span id="error-email" class="text-red-500 text-xs hidden mt-1">Ingresa un correo
                                válido.</span>
                        </div>
                    </div>

                    <div class="form-group mb-8 shrink-0">
                        <textarea id="mensaje" class="form-textarea" placeholder=" " required></textarea>
                        <label for="mensaje"
                            class="form-label absolute left-4 top-4 pointer-events-none transition-all">¿En qué podemos
                            ayudarte?</label>
                        <span id="error-mensaje" class="text-red-500 text-xs hidden mt-1">Por favor escribe tu
                            mensaje.</span>
                    </div>

                    <button type="submit" id="btnEnviar" class="submit-btn group shrink-0">
                        <span>Enviar Mensaje</span>
                        <i data-lucide="arrow-right"
                            class="w-5 h-5 transform group-hover:translate-x-1 transition-transform"></i>
                    </button>

                    <div id="form-success"
                        class="hidden bg-green-900/30 border border-green-500/50 text-green-400 p-4 rounded-lg mt-6 text-center font-semibold shrink-0">
                        ¡Mensaje enviado con éxito! Nos pondremos en contacto pronto.
                    </div>

                    <div id="form-error"
                        class="hidden bg-red-900/30 border border-red-500/50 text-red-400 p-4 rounded-lg mt-6 text-center font-semibold shrink-0">
                        Error al enviar el mensaje. Intenta de nuevo.
                    </div>


                    <!-- 3D Element Container -->
                    <div id="tech-3d-container"
                        class="mt-8 w-full flex-1 min-h-[200px] rounded-lg overflow-hidden relative z-10 border border-gray-800 bg-black/20">
                    </div>
                </form>
            </div>

            <!-- Información y Mapa -->
            <div class="flex flex-col gap-6 animate-slide-up delay-300">

                <div class="contact-card pb-20">
                    <h3 class="text-xl font-bold mb-6 border-b border-gray-800 pb-4">Información Directa</h3>

                    <div class="info-item">
                        <div class="info-icon">
                            <i data-lucide="mail" class="w-6 h-6"></i>
                        </div>
                        <div class="info-content">
                            <h4>Email</h4>
                            <p>info@computecnicos.com</p>
                            <p>soporte@computecnicos.com</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i data-lucide="phone" class="w-6 h-6"></i>
                        </div>
                        <div class="info-content">
                            <h4>Llámanos</h4>
                            <p>+57 316 850 0131</p>
                            <p>Lun - Vie, 9am - 6pm</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i data-lucide="map-pin" class="w-6 h-6"></i>
                        </div>
                        <div class="info-content">
                            <h4>Ubicación</h4>
                            <p>Paseo Bolívar Cra 17 #45-20</p>
                            <p>Cartagena de Indias, Colombia</p>
                        </div>
                    </div>
                </div>

                <div class="contact-card !p-0 overflow-hidden h-full min-h-[250px] relative">
                    <div id="mapcn-container" style="width: 100%; height: 100%; min-height: 300px;"></div>
                    <!-- Branding badge estilo MapCN -->
                    <div style="position: absolute; top: 12px; left: 12px; z-index: 10; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); border: 1px solid rgba(255,0,0,0.3); border-radius: 6px; padding: 4px 10px; display: flex; align-items: center; gap: 6px; pointer-events: none;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ff0000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <span style="color: #ccc; font-size: 11px; font-weight: 600; letter-spacing: 0.5px;">MapCN</span>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // --- 3D Element Logic ---
            const container = document.getElementById('tech-3d-container');
            if (container && typeof THREE !== 'undefined') {
                const scene = new THREE.Scene();
                // scene.background = new THREE.Color(0x000000); // Optional: match container bg or keep transparent

                const camera = new THREE.PerspectiveCamera(75, container.clientWidth / container.clientHeight, 0.1, 1000);
                camera.position.z = 5;

                const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
                renderer.setSize(container.clientWidth, container.clientHeight);
                container.appendChild(renderer.domElement);

                // Create a Tech Sphere (Icosahedron with wireframe)
                const geometry = new THREE.IcosahedronGeometry(2, 1);
                const material = new THREE.MeshBasicMaterial({
                    color: 0xff0000,
                    wireframe: true,
                    transparent: true,
                    opacity: 0.5
                });
                const sphere = new THREE.Mesh(geometry, material);
                scene.add(sphere);


                // Particles
                const particlesGeometry = new THREE.BufferGeometry();
                const particlesCount = 200;
                const posArray = new Float32Array(particlesCount * 3);
                for (let i = 0; i < particlesCount * 3; i++) {
                    posArray[i] = (Math.random() - 0.5) * 10;
                }
                particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
                const particlesMaterial = new THREE.PointsMaterial({
                    size: 0.05,
                    color: 0xff0000,
                    transparent: true,
                    opacity: 0.8
                });
                const particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
                scene.add(particlesMesh);

                // Mouse interaction
                let mouseX = 0;
                let mouseY = 0;
                let targetX = 0;
                let targetY = 0;

                const windowHalfX = container.clientWidth / 2;
                const windowHalfY = container.clientHeight / 2;

                container.addEventListener('mousemove', (event) => {
                    const rect = container.getBoundingClientRect();
                    mouseX = (event.clientX - rect.left - rect.width / 2);
                    mouseY = (event.clientY - rect.top - rect.height / 2);
                });



                // Handle Resize
                window.addEventListener('resize', () => {
                    if (container) {
                        const width = container.clientWidth;
                        const height = container.clientHeight;
                        renderer.setSize(width, height);
                        camera.aspect = width / height;
                        camera.updateProjectionMatrix();
                    }
                });

                const animate = function () {
                    requestAnimationFrame(animate);

                    // Use mouse position to influence rotation SPEED, not absolute position
                    // This prevents "snapping" when the mouse re-enters at a different spot
                    sphere.rotation.y += 0.005 + (mouseX * 0.0001);
                    sphere.rotation.x += 0.002 + (mouseY * 0.0001);


                    // Particles also rotate continuously with mouse influence
                    particlesMesh.rotation.y -= 0.002 + (mouseX * 0.00005);
                    particlesMesh.rotation.x -= 0.001 + (mouseY * 0.00005);

                    renderer.render(scene, camera);
                };

                animate();
            }

            // Input Animation Logic
            const inputs = document.querySelectorAll('.form-input, .form-textarea');
            inputs.forEach(input => {
                // Check initial state
                if (input.value) {
                    input.classList.add('has-content');
                }

                input.addEventListener('blur', function () {
                    if (this.value) {
                        this.classList.add('has-content');
                    } else {
                        this.classList.remove('has-content');
                    }
                });
            });

            // Form Submission Logic
            const form = document.getElementById('contactForm');
            const nombre = document.getElementById('nombre');
            const email = document.getElementById('email');
            const mensaje = document.getElementById('mensaje');
            const errorNombre = document.getElementById('error-nombre');
            const errorEmail = document.getElementById('error-email');
            const errorMensaje = document.getElementById('error-mensaje');
            const formSuccess = document.getElementById('form-success');
            const formError = document.getElementById('form-error');
            const btnEnviar = document.getElementById('btnEnviar');
            const btnText = btnEnviar.querySelector('span');

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                let valido = true;

                // Ocultar mensajes previos
                formSuccess.classList.add('hidden');
                formError.classList.add('hidden');

                // Validar nombre
                if (!nombre.value.trim()) {
                    errorNombre.classList.remove('hidden');
                    valido = false;
                } else {
                    errorNombre.classList.add('hidden');
                }

                // Validar email
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!email.value.trim() || !emailRegex.test(email.value.trim())) {
                    errorEmail.classList.remove('hidden');
                    valido = false;
                } else {
                    errorEmail.classList.add('hidden');
                }

                // Validar mensaje
                if (!mensaje.value.trim()) {
                    errorMensaje.classList.remove('hidden');
                    valido = false;
                } else {
                    errorMensaje.classList.add('hidden');
                }

                if (valido) {
                    btnEnviar.disabled = true;
                    const originalText = btnText.textContent;
                    btnText.textContent = 'Enviando...';

                    const formData = new FormData();
                    formData.append('nombre', nombre.value.trim());
                    formData.append('email', email.value.trim());
                    formData.append('mensaje', mensaje.value.trim());

                    try {
                        const res = await fetch('contacto_backend', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();

                        if (data.ok) {
                            formSuccess.textContent = data.msg;
                            formSuccess.classList.remove('hidden');
                            formSuccess.classList.add('animate-slide-up');

                            nombre.value = '';
                            email.value = '';
                            mensaje.value = '';

                            // Quitar clase has-content de los inputs
                            [nombre, email, mensaje].forEach(inp => inp.classList.remove('has-content'));

                            setTimeout(() => {
                                formSuccess.classList.add('hidden');
                            }, 7000);
                        } else {
                            formError.textContent = data.msg || 'Error al enviar el mensaje.';
                            formError.classList.remove('hidden');
                            formError.classList.add('animate-slide-up');

                            setTimeout(() => {
                                formError.classList.add('hidden');
                            }, 7000);
                        }
                    } catch (err) {
                        console.error(err);
                        formError.textContent = 'Error de conexión. Verifica tu internet e intenta de nuevo.';
                        formError.classList.remove('hidden');
                        formError.classList.add('animate-slide-up');

                        setTimeout(() => {
                            formError.classList.add('hidden');
                        }, 7000);
                    } finally {
                        btnEnviar.disabled = false;
                        btnText.textContent = originalText;
                    }
                }
            });

        });
    </script>

    <!-- MapCN / MapLibre GL Map Initialization -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mapContainer = document.getElementById('mapcn-container');
            if (!mapContainer || typeof maplibregl === 'undefined') return;

            // Coordenadas de la ubicación real de CompuTécnicos (Paseo Bolívar, Cartagena)
            const LNG = -75.51047;
            const LAT = 10.39332;

            // Estilo satelital con Google Satellite + etiquetas de calles
            const map = new maplibregl.Map({
                container: 'mapcn-container',
                style: {
                    version: 8,
                    name: 'MapCN Satellite',
                    sources: {
                        'google-satellite': {
                            type: 'raster',
                            tiles: [
                                'https://mt0.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',
                                'https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',
                                'https://mt2.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',
                                'https://mt3.google.com/vt/lyrs=s&x={x}&y={y}&z={z}'
                            ],
                            tileSize: 256,
                            maxzoom: 20,
                            attribution: '&copy; Google Maps'
                        },
                        'carto-labels': {
                            type: 'raster',
                            tiles: [
                                'https://a.basemaps.cartocdn.com/dark_only_labels/{z}/{x}/{y}@2x.png',
                                'https://b.basemaps.cartocdn.com/dark_only_labels/{z}/{x}/{y}@2x.png'
                            ],
                            tileSize: 256,
                            maxzoom: 20,
                            attribution: '&copy; <a href="https://carto.com/">CARTO</a> &copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
                        }
                    },
                    layers: [
                        {
                            id: 'satellite-layer',
                            type: 'raster',
                            source: 'google-satellite',
                            minzoom: 0,
                            maxzoom: 22
                        },
                        {
                            id: 'labels-layer',
                            type: 'raster',
                            source: 'carto-labels',
                            minzoom: 0,
                            maxzoom: 22
                        }
                    ]
                },
                center: [LNG, LAT],
                zoom: 14,
                maxZoom: 19,
                pitch: 0,
                bearing: 0,
                antialias: true,
                attributionControl: true
            });

            // Controles de navegación (zoom + rotación)
            map.addControl(new maplibregl.NavigationControl({
                showCompass: true,
                showZoom: true,
                visualizePitch: true
            }), 'top-right');

            // Controles de pantalla completa
            map.addControl(new maplibregl.FullscreenControl(), 'top-right');

            // Crear marcador personalizado con animación de pulso
            const markerEl = document.createElement('div');
            markerEl.className = 'mapcn-marker';
            markerEl.innerHTML = `
                <div class="mapcn-marker-pulse"></div>
                <div class="mapcn-marker-pin">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                </div>
            `;

            // Popup con información de la ubicación
            const popup = new maplibregl.Popup({
                offset: 30,
                closeButton: true,
                closeOnClick: false,
                className: 'mapcn-popup'
            }).setHTML(`
                <div style="padding: 8px 4px;">
                    <h4 style="margin: 0 0 6px; font-size: 14px; font-weight: 700; color: #fff;">CompuTécnicos</h4>
                    <p style="margin: 0 0 4px; font-size: 12px; color: #aaa;">📍 Paseo Bolívar Cra 17 #45-20, Cartagena</p>
                    <p style="margin: 0; font-size: 12px; color: #aaa;">🕐 Lun - Vie, 9am - 6pm</p>
                </div>
            `);

            // Agregar marcador al mapa
            const marker = new maplibregl.Marker({ element: markerEl, anchor: 'bottom' })
                .setLngLat([LNG, LAT])
                .setPopup(popup)
                .addTo(map);

            // Al cargar, hacer animación de acercamiento con perspectiva 3D
            map.on('load', () => {
                // Mostrar popup automáticamente
                popup.addTo(map);

                // Animación cinematográfica: acercar + inclinar + rotar
                setTimeout(() => {
                    map.flyTo({
                        center: [LNG, LAT],
                        zoom: 17,
                        pitch: 50,
                        bearing: -20,
                        duration: 3000,
                        essential: true
                    });
                }, 500);
            });

            // Manejar resize
            const resizeObserver = new ResizeObserver(() => {
                map.resize();
            });
            resizeObserver.observe(mapContainer);
        });
    </script>
</main>

<?php include 'includes/footer.php'; ?>