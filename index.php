<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/app/Core/bootstrap.php';
// Obtener productos destacados (con oferta o más recientes)
$stmt = $pdo->prepare('SELECT p.*, c.nombre AS categoria, m.nombre AS marca FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id LEFT JOIN marcas m ON p.id_marca = m.id WHERE p.oferta = 1 OR p.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY p.oferta DESC, p.fecha_creacion DESC LIMIT 4');
$stmt->execute();
$productos_destacados = $stmt->fetchAll();

// Título y CSS extra para esta página (Flowbite + index.css)
$page_title = 'Inicio';
    $extra_css = '<link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet" />' . "\n" . '<link rel="stylesheet" href="' . asset('css/index.css') . '?v=' . time() . '_14">' . "\n" . '<link rel="stylesheet" href="' . asset('css/productos.css') . '?v=' . time() . '_7">';

// Incluir header común
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section w-full px-2 sm:px-4 relative">
    <!-- Contenedor para Three.js -->
    <div id="hero-canvas-container" class="absolute inset-0 z-0 pointer-events-none"></div>

    <div class="hero-content relative z-10">
        <h1 class="hero-title animate-slide-up">
            COMPU<span>TECNICOS</span>
        </h1>
        <br>


        <h2 class="hero-subtitle animate-slide-up delay-100">SYSTEM STATUS: ONLINE | Palabras claves: <span
                id="animacion-palabras" class="text-red-500"></span></h2>
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

<!-- Script 3D Background -->
<script type="module" src="assets/js/hero-3d.js?v=<?php echo time(); ?>_6"></script>
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
<!-- Productos Destacados -->
<section class="container mx-auto px-2 sm:px-4 py-8 md:py-12 w-full">
    <h2 class="section-title">Productos Destacados</h2>

    <div class="products-grid" style="margin-top: 3rem;">
        <?php if (empty($productos_destacados)): ?>
            <div class="col-span-full text-center text-gray-400">No hay productos destacados disponibles.</div>
        <?php else: ?>
            <?php foreach ($productos_destacados as $p):
                $esNuevo = (strtotime($p['fecha_creacion']) > strtotime('-15 days'));
                ?>
                <article class="product-card" onclick="window.location.href='producto.php?id=<?php echo intval($p['id']); ?>'">
                    <div class="product-image-container">
                        <img src="<?php echo htmlspecialchars($p['imagen'] ?: 'https://via.placeholder.com/600x450?text=Sin+Imagen'); ?>"
                            alt="<?php echo htmlspecialchars($p['nombre']); ?>" loading="lazy">
                        
                        <div class="product-badges">
                            <?php if ($esNuevo): ?>
                                <span class="tech-badge tech-badge-new">NUEVO</span>
                            <?php endif; ?>
                            <?php if (!empty($p['oferta'])): ?>
                                <span class="tech-badge tech-badge-offer">OFERTA</span>
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
                            <div class="product-price">$<?php echo number_format($p['precio'], 0, ',', '.'); ?></div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Creadores -->
<section class="container mx-auto px-2 sm:px-4 py-8 md:py-16 w-full relative z-10 border-t border-gray-800 mt-12">
    <h2 class="section-title">Creadores</h2>
    <div class="team-grid mt-8">
        <!-- Amaury -->
        <div class="team-card">
            <div class="team-img-wrapper">
                <img src="assets/images/amaury.jpg" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=Amaury+Mendoza&background=0a0a0a&color=dc2626&size=200&bold=true';" alt="Amaury Enrique Mendoza Acosta">
            </div>
            <h3 class="team-name">Amaury Enrique<br>Mendoza Acosta</h3>
            <p class="team-role">Desarrollador Full Stack<br>& Director</p>
        </div>
        <!-- Carlos -->
        <div class="team-card">
            <div class="team-img-wrapper">
                <img src="assets/images/Carmona.jpg" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=Carlos+Carmona&background=0a0a0a&color=dc2626&size=200&bold=true';" alt="Carlos Alberto Carmona Miranda">
            </div>
            <h3 class="team-name">Carlos Alberto<br>Carmona Miranda</h3>
            <p class="team-role">Desarrollador Frontend</p>
        </div>
        <!-- Jose -->
        <div class="team-card">
            <div class="team-img-wrapper">
                <img src="assets/images/Jose.jpg" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=Jose+Olivo&background=0a0a0a&color=dc2626&size=200&bold=true';" alt="Jose Miguel Olivo Zabaleta">
            </div>
            <h3 class="team-name">Jose Miguel<br>Olivo Zabaleta</h3>
            <p class="team-role">Desarrollador Frontend</p>
        </div>
        <!-- Samuel -->
        <div class="team-card">
            <div class="team-img-wrapper">
                <img src="assets/images/Samuel.jpg" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=Samuel+Ramos&background=0a0a0a&color=dc2626&size=200&bold=true';" alt="Samuel David Ramos Teran" style="transform: scale(1.4) translateY(8%); clip-path: circle(50%);">
            </div>
            <h3 class="team-name">Samuel David<br>Ramos Teran</h3>
            <p class="team-role">Frontend &<br>Documentación</p>
        </div>
        <!-- Luis -->
        <div class="team-card">
            <div class="team-img-wrapper">
                <img src="assets/images/Luis.jpg" onerror="this.onerror=null;this.src='https://ui-avatars.com/api/?name=Luis+Perez&background=0a0a0a&color=dc2626&size=200&bold=true';" alt="Luis David Perez Coa">
            </div>
            <h3 class="team-name">Luis David<br>Perez Coa</h3>
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

            const res = await fetch('agregar_carrito.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            });
            const data = await res.json();

            if (data.ok) {
                alert(data.msg);
                // Actualizar contador del carrito en el header si existe
                const carritoCounter = document.querySelector('.cart-counter') || document.querySelector('.bg-red-600.text-xs');
                if (carritoCounter) {
                    const total = data.msg.match(/Total: (\d+) items/);
                    if (total) {
                        carritoCounter.textContent = total[1];
                    }
                }
            } else {
                alert('Error: ' + data.msg);
            }
        } catch (err) {
            alert('Error de conexión. Intenta de nuevo.');
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