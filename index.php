<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/app/Core/bootstrap.php';
// Obtener productos destacados: primero los marcados como destacado, luego los que tienen oferta activa
$stmt = $pdo->prepare('SELECT p.*, c.nombre AS categoria, m.nombre AS marca FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id LEFT JOIN marcas m ON p.id_marca = m.id WHERE p.destacado = 1 OR (p.oferta = 1 AND (p.oferta_hasta IS NULL OR p.oferta_hasta >= CURDATE())) ORDER BY p.destacado DESC, p.fecha_creacion DESC LIMIT 20');
$stmt->execute();
$productos_destacados = $stmt->fetchAll();

// Título y CSS extra para esta página (Flowbite + index.css)
$page_title = 'Inicio';
    $extra_css = '<link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet" />' . "\n" . '<link rel="stylesheet" href="' . base_url() . '/assets/css/index.css?v=' . filemtime(__DIR__ . '/assets/css/index.css') . '">' . "\n" . '<link rel="stylesheet" href="' . base_url() . '/assets/css/productos.css?v=' . filemtime(__DIR__ . '/assets/css/productos.css') . '">';

// Incluir header común
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section w-full px-2 sm:px-4 relative">
    <!-- Contenedor para Three.js (Eliminado a petición del usuario) -->

    <div class="hero-content relative z-10">
        <div class="animate-slide-up">
            <h1 class="hero-title">
                COMPU<span data-text="TECNICOS">TECNICOS</span>
            </h1>
        </div>
        <br>


        <p
            class="max-w-xl sm:max-w-2xl text-base sm:text-lg md:text-xl text-gray-400 mb-8 px-2 mx-auto animate-slide-up delay-200">
            Accede a la mejor tecnología del futuro. Componentes de alto rendimiento y sistemas avanzados.
        </p>
        <div class="hero-buttons animate-slide-up delay-300">
            <a href="productos.php" class="hero-btn primary">Ver Productos</a>
            <a href="contacto.php" class="hero-btn secondary">Contactar</a>
        </div>
    </div>
</section>

<!-- Script 3D Background (Eliminado a petición del usuario) -->
<!-- Ventajas eliminadas -->
<!-- Categorías -->
<section class="container mx-auto px-2 sm:px-4 py-8 md:py-12 w-full">
    <h2 class="section-title">Categorías</h2>

    <div class="categories-pro-grid">
        <!-- Laptops -->
        <div class="category-card-pro group" onclick="window.location.href='productos.php?categoria=laptops'">
            <div class="cat-pro-bg"></div>
            <div class="cat-pro-content">
                <div class="cat-pro-icon">
                    <i data-lucide="laptop" style="width:40px;height:40px"></i>
                </div>
                <div class="cat-pro-info">
                    <h3>Laptops</h3>
                    <p>Portabilidad y Potencia</p>
                    <span class="cat-pro-link">Ver Modelos &rarr;</span>
                </div>
            </div>
            <div class="cat-pro-border"></div>
        </div>

        <!-- Computadoras -->
        <div class="category-card-pro group" onclick="window.location.href='productos.php?categoria=computadoras'">
            <div class="cat-pro-bg"></div>
            <div class="cat-pro-content">
                <div class="cat-pro-icon">
                    <i data-lucide="monitor" style="width:40px;height:40px"></i>
                </div>
                <div class="cat-pro-info">
                    <h3>Computadoras</h3>
                    <p>Máximo Rendimiento</p>
                    <span class="cat-pro-link">Ver Equipos &rarr;</span>
                </div>
            </div>
            <div class="cat-pro-border"></div>
        </div>

        <!-- Componentes -->
        <div class="category-card-pro group" onclick="window.location.href='productos.php?categoria=componentes'">
            <div class="cat-pro-bg"></div>
            <div class="cat-pro-content">
                <div class="cat-pro-icon">
                    <i data-lucide="cpu" style="width:40px;height:40px"></i>
                </div>
                <div class="cat-pro-info">
                    <h3>Componentes</h3>
                    <p>Hardware Avanzado</p>
                    <span class="cat-pro-link">Ver Catálogo &rarr;</span>
                </div>
            </div>
            <div class="cat-pro-border"></div>
        </div>

        <!-- Accesorios -->
        <div class="category-card-pro group" onclick="window.location.href='productos.php?categoria=accesorios'">
            <div class="cat-pro-bg"></div>
            <div class="cat-pro-content">
                <div class="cat-pro-icon">
                    <i data-lucide="headphones" style="width:40px;height:40px"></i>
                </div>
                <div class="cat-pro-info">
                    <h3>Accesorios</h3>
                    <p>Periféricos Pro</p>
                    <span class="cat-pro-link">Ver Accesorios &rarr;</span>
                </div>
            </div>
            <div class="cat-pro-border"></div>
        </div>
    </div>
</section>
<!-- Productos Destacados - Carrusel -->
<section class="container mx-auto px-2 sm:px-4 py-8 md:py-12 w-full">
    <h2 class="section-title">Productos Destacados</h2>

    <?php if (empty($productos_destacados)): ?>
        <div class="text-center text-gray-400" style="margin-top:3rem">No hay productos destacados disponibles.</div>
    <?php else: ?>
    <div class="carousel-destacados-wrap" style="position:relative;margin-top:3rem">
        <div class="carousel-destacados" id="carousel-destacados">
            <?php foreach ($productos_destacados as $p):
                $priceData = get_product_price_data($p);
                $esNuevo = !empty($p['nuevo_hasta']) && strtotime($p['nuevo_hasta']) >= strtotime('today');
                $enOferta = $priceData['tiene_descuento'];
            ?>
                <article class="product-card carousel-dest-card" onclick="window.location.href='producto.php?id=<?php echo intval($p['id']); ?>'">
                    <div class="product-image-container">
                        <img src="<?php echo htmlspecialchars($p['imagen'] ?: 'https://via.placeholder.com/600x450?text=Sin+Imagen'); ?>"
                            alt="<?php echo htmlspecialchars($p['nombre']); ?>" loading="lazy">
                        <div class="product-badges">
                            <?php if ($esNuevo): ?>
                                <span class="tech-badge tech-badge-new">NUEVO</span>
                            <?php endif; ?>


                        </div>
                        <div class="product-overlay">
                            <span class="view-product-btn">Ver detalles</span>
                        </div>
                    </div>
                    <div class="product-info">
                        <div class="product-category"><?php echo htmlspecialchars($p['categoria']); ?></div>
                        <h3 class="product-name"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                        <div class="product-meta">
                            <span class="product-brand"><?php echo htmlspecialchars($p['marca']); ?></span>
                            <?php if ((int)$p['stock'] > 0): ?>
                                <span class="product-stock in-stock">En stock</span>
                            <?php else: ?>
                                <span class="product-stock out-stock">Agotado</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-footer">
                            <?php if ($priceData['tiene_descuento']): ?>
                                <div class="product-price-container">
                                    <span class="product-price-old">$<?php echo number_format($priceData['precio_original'], 0, ',', '.'); ?></span>
                                    <div class="product-price-row">
                                        <div class="product-price">$<?php echo number_format($priceData['precio'], 0, ',', '.'); ?></div>
                                        <span class="product-discount-badge-small">-<?php echo number_format($priceData['porcentaje'], 0); ?>%</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="product-price">$<?php echo number_format($priceData['precio'], 0, ',', '.'); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</section>


<script>
(function(){
    var track = document.getElementById('carousel-destacados');
    if (!track) return;
    var paused = false;
    var speed = 0.8; // pixeles por frame
    var currentScroll = 0; // Usar variable interna para evitar problemas de redondeo en Safari/iOS
    
    // Duplicar las tarjetas para loop infinito
    var cards = track.innerHTML;
    track.innerHTML = cards + cards;

    function animate() {
        if (!paused) {
            var halfWidth = track.scrollWidth / 2;
            currentScroll += speed;
            if (currentScroll >= halfWidth) {
                currentScroll -= halfWidth;
            }
            track.scrollLeft = currentScroll;
        }
        requestAnimationFrame(animate);
    }

    // Sincronizar el scroll manual (swipe del dedo) con nuestra variable
    track.addEventListener('scroll', function(){
        if (paused) {
            currentScroll = track.scrollLeft;
        }
    }, {passive:true});

    // Mouse encima = pausa, mouse fuera = reanuda
    track.addEventListener('mouseenter', function(){ paused = true; currentScroll = track.scrollLeft; });
    track.addEventListener('mouseleave', function(){ paused = false; currentScroll = track.scrollLeft; });

    // Touch: pausa al tocar y permite swipe
    track.addEventListener('touchstart', function(){ paused = true; currentScroll = track.scrollLeft; }, {passive:true});
    track.addEventListener('touchend', function(){ paused = false; currentScroll = track.scrollLeft; }, {passive:true});

    // Iniciar animación
    animate();
})();
</script>

<!-- Creadores -->
<section class="container mx-auto px-2 sm:px-4 py-8 md:py-16 w-full relative z-10 section-divider-top">
    <h2 class="section-title">Creadores</h2>
    <div class="team-grid mt-8">
        <!-- Amaury -->
        <div class="team-card" data-aos="flip-right" data-aos-delay="0">
            <div class="team-img-wrapper">
                <img src="assets/images/amaury.jpg" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=Amaury+Mendoza&background=0a0a0a&color=dc2626&size=200&bold=true';" alt="Amaury Enrique Mendoza Acosta">
            </div>
            <h3 class="team-name">Amaury Enrique<br>Mendoza Acosta</h3>
            <p class="team-role">Desarrollador Full Stack<br>& Director</p>
        </div>
        <!-- Carlos -->
        <div class="team-card" data-aos="flip-right" data-aos-delay="100">
            <div class="team-img-wrapper">
                <img src="assets/images/Carmona.jpg" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=Carlos+Carmona&background=0a0a0a&color=dc2626&size=200&bold=true';" alt="Carlos Alberto Carmona Miranda">
            </div>
            <h3 class="team-name">Carlos Alberto<br>Carmona Miranda</h3>
            <p class="team-role">Desarrollador Frontend</p>
        </div>
        <!-- Jose -->
        <div class="team-card" data-aos="flip-right" data-aos-delay="200">
            <div class="team-img-wrapper">
                <img src="assets/images/Jose.jpg" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=Jose+Olivo&background=0a0a0a&color=dc2626&size=200&bold=true';" alt="Jose Miguel Olivo Zabaleta">
            </div>
            <h3 class="team-name">Jose Miguel<br>Olivo Zabaleta</h3>
            <p class="team-role">Desarrollador Frontend</p>
        </div>
        <!-- Samuel -->
        <div class="team-card" data-aos="flip-right" data-aos-delay="300">
            <div class="team-img-wrapper">
                <img src="assets/images/Samuel.jpg?v=2" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=Samuel+Ramos&background=0a0a0a&color=dc2626&size=200&bold=true';" alt="Samuel David Ramos Teran">
            </div>
            <h3 class="team-name">Samuel David<br>Ramos Teran</h3>
            <p class="team-role">Frontend &<br>Documentación</p>
        </div>
        <!-- Luis -->
        <div class="team-card" data-aos="flip-right" data-aos-delay="400">
            <div class="team-img-wrapper">
                <img src="assets/images/Luis.jpg" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=Luis+Perez&background=0a0a0a&color=dc2626&size=200&bold=true';" alt="Luis David Perez Coa">
            </div>
            <h3 class="team-name">Luis Daniel<br>Perez Coa</h3>
            <p class="team-role">Tester</p>
        </div>
    </div>
</section>
<!-- Flowbite JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
<script>
    async function agregarAlCarrito(idProducto) {
        try {
            const formData = new FormData();
            formData.append('id_producto', idProducto);
            formData.append('cantidad', 1);

            const res = await fetch('api/agregar_carrito.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            });
            const data = await res.json();

            if (data.ok) {
                showToast(data.msg || 'Producto agregado al carrito.', 'success', 4000);
                // Actualizar contador del carrito en el header si existe
                const carritoCounter = document.querySelector('.cart-counter') || document.querySelector('.bg-red-600.text-xs');
                if (carritoCounter) {
                    const total = data.msg.match(/Total: (\d+) items/);
                    if (total) {
                        carritoCounter.textContent = total[1];
                    }
                }
            } else {
                showToast(data.msg || 'No se pudo agregar al carrito.', 'error', 5000);
            }
        } catch (err) {
            showToast('Error de conexión. Intenta de nuevo.', 'error', 5000);
        }
    }
</script>
<script src="<?= asset('js/main.js') ?>"></script>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
    // Animación de aparición al hacer scroll
    (function () {
        const observer = new window.IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fadeIn');
                } else {
                    entry.target.classList.remove('animate-fadeIn');
                }
            });
        }, { threshold: 0.15 });
        document.querySelectorAll('.scroll-animate').forEach(el => {
            observer.observe(el);
        });
    })();
</script>

</body>

</html>