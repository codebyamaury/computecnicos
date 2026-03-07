<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/app/Core/bootstrap.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('Location: productos.php');
    exit;
}
// Obtener producto
$stmt = $pdo->prepare('SELECT p.*, c.nombre AS categoria, m.nombre AS marca FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id LEFT JOIN marcas m ON p.id_marca = m.id WHERE p.id = ?');
$stmt->execute([$id]);
$producto = $stmt->fetch();
if (!$producto) {
    header('Location: productos.php');
    exit;
}
// Obtener imágenes adicionales
$stmtImg = $pdo->prepare('SELECT url_imagen FROM imagenes_producto WHERE id_producto = ?');
$stmtImg->execute([$id]);
$imagenes = $stmtImg->fetchAll(PDO::FETCH_COLUMN);
// Construir galería incluyendo la imagen principal como primer elemento
$galeria = [];
if (!empty($producto['imagen'])) {
    $galeria[] = $producto['imagen'];
}
if (!empty($imagenes)) {
    $galeria = array_merge($galeria, array_filter($imagenes));
}
// Evitar duplicados y reindexar
$galeria = array_values(array_unique($galeria));
$esNuevo = !empty($producto['nuevo_hasta']) && strtotime($producto['nuevo_hasta']) >= strtotime('today');
$enOferta = !empty($producto['oferta']) && (empty($producto['oferta_hasta']) || strtotime($producto['oferta_hasta']) >= strtotime('today'));
// Stock real de la BD — solo se reduce al completar una compra, no al agregar al carrito
$stockEfectivo = intval($producto['stock']);
// Reseñas/comentarios
try {
    $stmtRev = $pdo->prepare('SELECT r.id, r.id_producto, r.id_usuario, r.calificacion, r.comentario, r.fecha, u.nombre AS usuario FROM comentarios_producto r LEFT JOIN usuarios u ON r.id_usuario = u.id WHERE r.id_producto = ? ORDER BY r.fecha DESC');
    $stmtRev->execute([$id]);
    $resenas = $stmtRev->fetchAll();
    $avgRating = null;
    if ($resenas) {
        $sum = 0;
        $count = 0;
        foreach ($resenas as $r) {
            $sum += (int) $r['calificacion'];
            $count++;
        }
        $avgRating = $count ? round($sum / $count, 1) : null;
    }
} catch (Exception $e) {
    $resenas = [];
    $avgRating = null;
}
// Compatibilidades: categorías complementarias simples según la categoría del producto
$compatibles = [];
try {
    $cat = strtolower(trim($producto['categoria']));
    $map = [
        'procesadores' => ['Memorias RAM', 'Almacenamiento'],
        'tarjetas gráficas' => ['Periféricos', 'Almacenamiento'],
        'memorias ram' => ['Procesadores', 'Almacenamiento'],
        'almacenamiento' => ['Memorias RAM', 'Periféricos'],
        // por defecto sugerimos oferta reciente en componentes
    ];
    $targets = $map[$cat] ?? ['Procesadores', 'Tarjetas Gráficas'];
    // Consulta de productos de categorías objetivo, excluyendo el actual
    $place = implode(',', array_fill(0, count($targets), '?'));
    $sqlCompat = "SELECT p.*, c.nombre AS categoria, m.nombre AS marca FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id LEFT JOIN marcas m ON p.id_marca = m.id WHERE p.activo = 1 AND c.nombre IN ($place) AND p.id <> ? ORDER BY p.oferta DESC, p.fecha_creacion DESC LIMIT 6";
    $stmtCompat = $pdo->prepare($sqlCompat);
    $params = $targets;
    $params[] = $producto['id'];
    $stmtCompat->execute($params);
    $compatibles = $stmtCompat->fetchAll();
} catch (Exception $e) {
    $compatibles = [];
}
?>
<?php
$page_title = htmlspecialchars($producto['nombre']) . ' | Computécnicos';
$extra_css = '<link rel="stylesheet" href="' . asset('css/index.css') . '?v=' . time() . '_13">' . "\n" .
    '<link rel="stylesheet" href="' . asset('css/producto.css') . '?v=' . time() . '_5">';
include 'includes/header.php';
?>

<main class="flex-1 bg-[#0a0a0a] text-white">

    <!-- Product Main -->
    <section class="prod-main">

        <!-- Galería -->
        <div class="prod-gallery">
            <?php if (count($galeria) > 1): ?>
                <div class="prod-thumbs">
                    <?php foreach ($galeria as $idx => $img): ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" data-src="<?php echo htmlspecialchars($img); ?>"
                            alt="Miniatura <?php echo $idx + 1; ?>"
                            class="prod-thumb thumb-img <?php echo $idx === 0 ? 'active' : ''; ?>">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="prod-img-main">
                <div class="prod-badges">
                    <?php if ($enOferta): ?>
                        <span class="prod-badge prod-badge-offer">OFERTA</span>
                    <?php endif; ?>
                    <?php if ($esNuevo): ?>
                        <span class="prod-badge prod-badge-new">NUEVO</span>
                    <?php endif; ?>
                </div>
                <div class="prod-img-wrapper">
                    <img id="main-img"
                        src="<?php echo htmlspecialchars($galeria[0] ?? 'https://via.placeholder.com/500x500?text=Sin+Imagen'); ?>"
                        alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                </div>
                <button class="prod-zoom-btn" id="open-lightbox" title="Ver imagen completa">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                </button>
            </div>
        </div>

        <!-- Info del Producto -->
        <div class="prod-info">

            <!-- Tags -->
            <div class="prod-tags">
                <span class="prod-tag prod-tag-cat">
                    <i data-lucide="tag" class="w-3 h-3"></i>
                    <?php echo htmlspecialchars($producto['categoria']); ?>
                </span>
                <span class="prod-tag prod-tag-brand">
                    <i data-lucide="badge-check" class="w-3 h-3"></i>
                    <?php echo htmlspecialchars($producto['marca']); ?>
                </span>
            </div>

            <!-- Título -->
            <h1 class="prod-title"><?php echo htmlspecialchars($producto['nombre']); ?></h1>

            <!-- Rating -->
            <?php if ($avgRating !== null): ?>
                <div class="prod-rating">
                    <div class="prod-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="prod-star <?php echo $i <= round($avgRating) ? 'filled' : 'empty'; ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <span class="prod-rating-text"><?php echo number_format($avgRating, 1); ?>
                        (<?php echo count($resenas); ?> reseñas)</span>
                </div>
            <?php endif; ?>

            <!-- Precio -->
            <div class="prod-price-block">
                <div class="prod-price">$<?php echo number_format($producto['precio'], 0, ',', '.'); ?></div>
                <span class="prod-price-iva">IVA incluido</span>
            </div>

            <!-- Divider -->
            <div class="prod-divider"></div>

            <!-- Descripción -->
            <div class="prod-desc-card">
                <h3><i data-lucide="file-text" class="w-4 h-4"></i> Descripción</h3>
                <p><?php echo nl2br(htmlspecialchars($producto['descripcion'])); ?></p>
            </div>

            <!-- SKU & Meta -->
            <?php if (!empty($producto['sku'])): ?>
                <div class="prod-meta-row">
                    <span class="prod-meta-item">
                        <i data-lucide="barcode" class="w-3.5 h-3.5"></i>
                        SKU: <strong><?php echo htmlspecialchars($producto['sku']); ?></strong>
                    </span>
                </div>
            <?php endif; ?>

            <!-- Stock -->
            <div id="stock-display"
                class="prod-stock <?php echo $stockEfectivo > 0 ? 'prod-stock-in' : 'prod-stock-out'; ?>"
                data-stock="<?php echo $stockEfectivo; ?>">
                <span class="prod-stock-dot"></span>
                <span
                    id="stock-text"><?php echo $stockEfectivo > 0 ? 'En stock (' . $stockEfectivo . ' disponibles)' : 'Agotado'; ?></span>
                <?php if ($stockEfectivo > 0 && $stockEfectivo <= 5): ?>
                    <span class="prod-stock-low"><i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i> ¡Últimas
                        unidades!</span>
                <?php endif; ?>
            </div>

            <!-- Stock Alert -->
            <?php if (intval($producto['stock']) <= 2): ?>
                <div class="prod-stock-alert">
                    <p><i data-lucide="bell" class="w-4 h-4"></i> Notifícame cuando haya stock</p>
                    <form id="stock-alert-form">
                        <input type="hidden" name="id_producto" value="<?php echo intval($producto['id']); ?>">
                        <?php if (!isset($_SESSION['usuario'])): ?>
                            <input type="email" name="email" placeholder="Tu correo electrónico" required>
                        <?php else: ?>
                            <span style="flex:1;font-size:0.85rem;color:#888;padding:0.5rem">Usando:
                                <?php echo htmlspecialchars($_SESSION['usuario']['email']); ?></span>
                        <?php endif; ?>
                        <button type="submit">Notificarme</button>
                    </form>
                    <div id="stock-alert-msg" style="font-size:0.82rem;margin-top:0.5rem"></div>
                </div>
            <?php endif; ?>

            <!-- Carrito -->
            <div id="cart-form-wrapper" style="<?php echo $stockEfectivo <= 0 ? 'display:none' : ''; ?>">
                <!-- Quantity selector -->
                <div class="prod-cart-form">
                    <span class="prod-qty-label">Cantidad:</span>
                    <div class="prod-qty-wrap">
                        <button type="button" id="btn-minus" class="prod-qty-btn">−</button>
                        <input type="number" name="cantidad" id="cantidad-input" value="1" min="1"
                            max="<?php echo $stockEfectivo; ?>" class="prod-qty-input">
                        <button type="button" id="btn-plus" class="prod-qty-btn">+</button>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="prod-action-buttons">
                    <form method="POST" action="agregar_carrito.php" class="prod-action-form">
                        <input type="hidden" name="id_producto" value="<?php echo intval($producto['id']); ?>">
                        <input type="hidden" name="cantidad" id="qty-hidden-cart" value="1">
                        <button type="submit" class="prod-add-btn">
                            <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                            Agregar al Carrito
                        </button>
                    </form>
                    <form method="POST" action="checkout.php" class="prod-action-form">
                        <input type="hidden" name="id_producto" value="<?php echo intval($producto['id']); ?>">
                        <input type="hidden" name="cantidad" id="qty-hidden-buy" value="1">
                        <input type="hidden" name="compra_directa" value="1">
                        <button type="submit" class="prod-buy-btn">
                            <i data-lucide="zap" class="w-5 h-5"></i>
                            Comprar Ahora
                        </button>
                    </form>
                </div>
            </div>

            <!-- Trust Badges -->
            <div class="prod-trust-badges">
                <div class="prod-trust-item">
                    <i data-lucide="shield-check" class="w-5 h-5"></i>
                    <div>
                        <strong>Compra Segura</strong>
                        <span>Pago protegido 100%</span>
                    </div>
                </div>
                <div class="prod-trust-item">
                    <i data-lucide="truck" class="w-5 h-5"></i>
                    <div>
                        <strong>Envío Rápido</strong>
                        <span>Entrega en 24-48h</span>
                    </div>
                </div>
                <div class="prod-trust-item">
                    <i data-lucide="rotate-ccw" class="w-5 h-5"></i>
                    <div>
                        <strong>Garantía</strong>
                        <span>30 días de garantía</span>
                    </div>
                </div>
            </div>

        </div>
    </section>


    <?php if (!empty($compatibles)): ?>
        <!-- Combo Section -->
        <section class="prod-combo-section">
            <div class="prod-combo-inner">
                <div class="prod-combo-header">
                    <h2>🎮 Arma tu Combo</h2>
                    <p>Productos complementarios con 10% de descuento</p>
                </div>

                <div class="prod-combo-controls">
                    <select id="combo-select" class="prod-combo-select">
                        <option value="">Selecciona un complemento...</option>
                        <?php foreach ($compatibles as $c): ?>
                            <option value="<?php echo intval($c['id']); ?>">
                                <?php echo htmlspecialchars($c['nombre']); ?> —
                                $<?php echo number_format($c['precio'], 0, ',', '.'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button id="btn-agregar-combo" data-product="<?php echo intval($producto['id']); ?>"
                        class="prod-combo-btn">
                        Agregar Combo (-10%)
                    </button>
                </div>
                <div id="combo-msg" style="text-align:center;font-size:0.85rem;margin-bottom:1.5rem"></div>

                <div class="prod-combo-grid">
                    <?php foreach ($compatibles as $c): ?>
                        <div class="prod-combo-card">
                            <a href="producto.php?id=<?php echo intval($c['id']); ?>" class="prod-combo-card-img">
                                <img src="<?php echo htmlspecialchars($c['imagen'] ?: 'https://via.placeholder.com/100?text=Img'); ?>"
                                    alt="<?php echo htmlspecialchars($c['nombre']); ?>">
                            </a>
                            <div class="prod-combo-card-info">
                                <a href="producto.php?id=<?php echo intval($c['id']); ?>">
                                    <?php echo htmlspecialchars($c['nombre']); ?>
                                </a>
                                <span class="prod-combo-card-brand"><?php echo htmlspecialchars($c['marca']); ?></span>
                                <span
                                    class="prod-combo-card-price">$<?php echo number_format($c['precio'], 0, ',', '.'); ?></span>
                            </div>
                            <form method="POST" action="agregar_carrito.php" style="display:flex;align-items:center">
                                <input type="hidden" name="id_producto" value="<?php echo intval($c['id']); ?>">
                                <input type="hidden" name="cantidad" value="1">
                                <button type="submit" class="prod-combo-card-btn">+</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Reseñas — Sistema Robusto -->
    <section class="prod-reviews-section" id="reviews-section">
        <div class="prod-reviews-header">
            <h2><i data-lucide="message-square" class="w-6 h-6"></i> Reseñas</h2>
        </div>

        <!-- Stats Panel -->
        <div class="reviews-stats-panel" id="reviews-stats">
            <!-- Se carga dinámicamente via JS -->
        </div>

        <!-- Formulario de Reseña (solo si puede reseñar) -->
        <div id="review-form-container" style="display:none;">
            <div class="review-form-card">
                <h3><i data-lucide="edit-3" class="w-5 h-5"></i> Escribe tu reseña</h3>
                <p class="review-form-subtitle">Comparte tu experiencia con este producto</p>
                
                <form id="review-form" enctype="multipart/form-data">
                    <input type="hidden" name="id_producto" value="<?php echo intval($producto['id']); ?>">
                    
                    <!-- Estrellas -->
                    <div class="review-stars-selector">
                        <label>Tu calificación</label>
                        <div class="stars-input" id="stars-input">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star-select" data-value="<?php echo $i; ?>">★</span>
                            <?php endfor; ?>
                        </div>
                        <span class="stars-label" id="stars-label">Selecciona una calificación</span>
                        <input type="hidden" name="calificacion" id="calificacion-input" value="0">
                    </div>


                    <!-- Comentario -->
                    <div class="review-field">
                        <label for="review-comentario">Tu opinión *</label>
                        <textarea id="review-comentario" name="comentario" rows="4" 
                                  placeholder="¿Qué te pareció el producto? ¿Cumplió tus expectativas? Cuéntanos tu experiencia..."
                                  minlength="10" maxlength="2000" required></textarea>
                        <span class="char-count"><span id="comentario-count">0</span>/2000</span>
                    </div>

                    <!-- Upload de imágenes -->
                    <div class="review-field">
                        <label>Imágenes (opcional, máx. 3)</label>
                        <div class="review-upload-area" id="upload-area">
                            <input type="file" name="imagenes[]" id="review-images" 
                                   accept="image/jpeg,image/png,image/webp" multiple 
                                   style="display:none" max="3">
                            <div class="upload-placeholder" id="upload-placeholder">
                                <i data-lucide="camera" class="w-8 h-8"></i>
                                <span>Haz clic o arrastra imágenes aquí</span>
                                <small>JPG, PNG o WebP — Máximo 5MB cada una</small>
                            </div>
                            <div class="upload-previews" id="upload-previews"></div>
                        </div>
                    </div>

                    <!-- Botón enviar -->
                    <button type="submit" class="review-submit-btn" id="review-submit-btn">
                        <i data-lucide="send" class="w-5 h-5"></i>
                        Publicar Reseña
                    </button>
                    <div id="review-form-msg" style="margin-top:0.75rem;text-align:center;font-size:0.85rem"></div>
                </form>
            </div>
        </div>

        <!-- Mensaje si ya reseñó -->
        <div id="review-already" style="display:none;">
            <div class="review-already-card">
                <i data-lucide="check-circle" class="w-6 h-6" style="color:#4ade80"></i>
                <span>Ya dejaste tu reseña para este producto. ¡Gracias!</span>
            </div>
        </div>

        <!-- Placeholder para no compradores (sin mensaje visible) -->
        <div id="review-not-buyer" style="display:none;"></div>

        <!-- Lista de reseñas -->
        <div class="prod-reviews-grid" id="reviews-list">
            <!-- Se carga dinámicamente via JS -->
        </div>

        <!-- Sin reseñas -->
        <div class="prod-no-reviews" id="no-reviews" style="display:none">
            <div class="icon"><i data-lucide="message-circle" class="w-12 h-12" style="color:#333"></i></div>
            <h3>Sin reseñas aún</h3>
            <p>Sé el primero en opinar sobre este producto.</p>
        </div>
    </section>
</main>

<!-- Lightbox / Visor de imágenes -->
<div class="prod-lightbox" id="prod-lightbox">
    <div class="prod-lightbox-overlay" id="lightbox-overlay"></div>
    <button class="prod-lightbox-close" id="lightbox-close">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <button class="prod-lightbox-nav prod-lightbox-prev" id="lightbox-prev">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
    <div class="prod-lightbox-content">
        <img id="lightbox-img" src="" alt="Imagen ampliada">
    </div>
    <button class="prod-lightbox-nav prod-lightbox-next" id="lightbox-next">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
    <div class="prod-lightbox-counter" id="lightbox-counter"></div>
    <div class="prod-lightbox-thumbs" id="lightbox-thumbs"></div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>


<!-- Toast notification container -->
<div id="toast-container" class="fixed top-20 right-4 z-[900] space-y-2"></div>

<script>
    // Toast notification function
    function showToast(message, type = 'error', duration = 4000) {
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            toastContainer.className = 'fixed top-20 right-4 z-[900] space-y-2';
            document.body.appendChild(toastContainer);
        }

        const toast = document.createElement('div');
        toast.className = 'toast-notification transform translate-x-full opacity-0 transition-all duration-300 ease-in-out';

        let icon, title;
        if (type === 'success') {
            icon = '<svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
            title = '¡Listo!';
        } else if (type === 'warning') {
            icon = '<svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-2.5L13.73 4c-.77-.83-1.96-.83-2.73 0L3.2 16.5c-.77.83.19 2.5 1.73 2.5z"/></svg>';
            title = 'Atención';
        } else {
            icon = '<svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-2.5L13.73 4c-.77-.83-1.96-.83-2.73 0L3.2 16.5c-.77.83.19 2.5 1.73 2.5z"/></svg>';
            title = 'Error';
        }

        const barColor = type === 'success' ? 'from-green-500 via-green-600 to-green-700' : type === 'warning' ? 'from-amber-500 via-amber-600 to-amber-700' : 'from-red-500 via-red-600 to-red-700';
        const ringColor = type === 'success' ? 'ring-green-500/40' : type === 'warning' ? 'ring-amber-500/40' : 'ring-red-500/40';

        toast.innerHTML = `
        <div class="relative max-w-sm p-4 rounded-2xl bg-[#141414]/90 text-gray-100 border border-[#333] backdrop-blur-md shadow-2xl ring-1 ${ringColor}">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">${icon}</div>
                <div class="flex-1">
                    <div class="text-sm font-semibold tracking-wide">${title}</div>
                    <div class="text-xs mt-1 opacity-90">${message}</div>
                </div>
                <button onclick="this.closest('.toast-notification').remove()" class="flex-shrink-0 ml-1 text-gray-400 hover:text-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="absolute left-0 right-0 bottom-0 h-1 overflow-hidden rounded-b-2xl">
                <div class="progress-bar h-1 bg-gradient-to-r ${barColor}"></div>
            </div>
        </div>
    `;

        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.classList.remove('translate-x-full', 'opacity-0');
            toast.classList.add('translate-x-0', 'opacity-100');
        }, 100);

        const bar = toast.querySelector('.progress-bar');
        if (bar) {
            bar.style.width = '100%';
            bar.style.transition = `width ${duration}ms linear`;
            requestAnimationFrame(() => { bar.style.width = '0%'; });
        }

        setTimeout(() => {
            toast.classList.add('translate-x-full', 'opacity-0');
            setTimeout(() => { if (toast.parentNode) toast.remove(); }, 300);
        }, duration);
    }

    // Intercept all add-to-cart forms and use AJAX instead
    document.addEventListener('DOMContentLoaded', function () {
        const isLoggedIn = <?php echo isset($_SESSION['usuario']) ? 'true' : 'false'; ?>;
        
        document.querySelectorAll('form[action="agregar_carrito.php"]').forEach(form => {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (!isLoggedIn) {
                    if (typeof abrirModalLogin === 'function') {
                        abrirModalLogin();
                    } else {
                        window.location.href = 'index.php'; // Fallback
                    }
                    return;
                }
                
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;

                // Check if this form has a redirect field (Comprar Ahora)
                const redirectField = form.querySelector('input[name="redirect"]');
                const redirectUrl = redirectField ? redirectField.value : null;

                try {
                    const fd = new FormData(form);
                    fd.delete('redirect'); // Remove redirect from POST data
                    const res = await fetch('agregar_carrito.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        body: fd
                    });
                    const data = await res.json();
                    if (data && data.ok) {
                        // If redirect requested (Comprar Ahora), go to checkout
                        if (redirectUrl) {
                            window.location.href = redirectUrl;
                            return;
                        }
                        if (data.stock_limit) {
                            showToast(data.msg || 'Has alcanzado el stock máximo disponible.', 'warning', 5000);
                        } else {
                            showToast(data.msg || 'Producto agregado al carrito correctamente.', 'success', 4000);
                        }
                        // Update cart counter in header
                        if (data.total !== null && data.total !== undefined) {
                            const counters = document.querySelectorAll('.cart-counter, .bg-red-600.text-xs');
                            counters.forEach(c => { c.textContent = data.total; });
                        }
                        // Stock no se reduce al agregar al carrito — solo al completar la compra
                    } else {
                        showToast((data && data.msg) || 'No se pudo agregar al carrito.', 'error', 5000);
                    }
                } catch (err) {
                    showToast('Error de conexión. Intenta de nuevo.', 'error', 5000);
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        });
    });
</script>

<script>
    // Quantity buttons + sync hidden fields
    document.addEventListener('DOMContentLoaded', function () {
        const minusBtn = document.getElementById('btn-minus');
        const plusBtn = document.getElementById('btn-plus');
        const cantidadInput = document.getElementById('cantidad-input');
        const qtyCart = document.getElementById('qty-hidden-cart');
        const qtyBuy = document.getElementById('qty-hidden-buy');

        function syncQty() {
            const val = cantidadInput ? cantidadInput.value : '1';
            if (qtyCart) qtyCart.value = val;
            if (qtyBuy) qtyBuy.value = val;
        }

        if (minusBtn && plusBtn && cantidadInput) {
            minusBtn.addEventListener('click', function () {
                let val = parseInt(cantidadInput.value) || 1;
                if (val > 1) {
                    cantidadInput.value = val - 1;
                    syncQty();
                }
            });

            plusBtn.addEventListener('click', function () {
                let val = parseInt(cantidadInput.value) || 1;
                let max = parseInt(cantidadInput.max) || 99;
                if (val < max) {
                    cantidadInput.value = val + 1;
                    syncQty();
                } else {
                    showToast('Has alcanzado el stock máximo disponible (' + max + ' unidades) de este producto.', 'warning', 4000);
                }
            });

            cantidadInput.addEventListener('change', syncQty);
            cantidadInput.addEventListener('input', syncQty);
        }

        // ══════ GALERÍA COMPLETA: thumbnails, zoom, lightbox ══════
        const mainImg = document.getElementById('main-img');
        const thumbs = document.querySelectorAll('.thumb-img');
        const galeria = <?php echo json_encode($galeria); ?>;
        let currentIndex = 0;

        // Cambiar imagen al clic en miniatura
        thumbs.forEach((thumb, idx) => {
            thumb.addEventListener('click', function () {
                currentIndex = idx;
                const src = this.dataset.src || this.src;
                if (mainImg) mainImg.src = src;
                thumbs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // ── Lightbox (visor fullscreen) ──
        const lightbox = document.getElementById('prod-lightbox');
        const lightboxImg = document.getElementById('lightbox-img');
        const lightboxCounter = document.getElementById('lightbox-counter');
        const lightboxThumbs = document.getElementById('lightbox-thumbs');
        const openBtn = document.getElementById('open-lightbox');

        function openLightbox(index) {
            if (!lightbox || !galeria.length) return;
            currentIndex = index;
            lightboxImg.src = galeria[currentIndex];
            lightboxCounter.textContent = `${currentIndex + 1} / ${galeria.length}`;
            document.body.style.overflow = 'hidden';
            lightbox.classList.add('open');
            updateLightboxThumbs();
        }

        function closeLightbox() {
            if (!lightbox) return;
            lightbox.classList.remove('open');
            document.body.style.overflow = '';
        }

        function navigateLightbox(dir) {
            currentIndex = (currentIndex + dir + galeria.length) % galeria.length;
            lightboxImg.src = galeria[currentIndex];
            lightboxCounter.textContent = `${currentIndex + 1} / ${galeria.length}`;
            updateLightboxThumbs();
            // También actualizar miniatura activa en la galería principal
            if (mainImg) mainImg.src = galeria[currentIndex];
            thumbs.forEach((t, i) => t.classList.toggle('active', i === currentIndex));
        }

        function updateLightboxThumbs() {
            if (!lightboxThumbs) return;
            lightboxThumbs.innerHTML = '';
            galeria.forEach((src, i) => {
                const t = document.createElement('img');
                t.src = src;
                t.alt = `Imagen ${i + 1}`;
                t.className = 'prod-lightbox-thumb' + (i === currentIndex ? ' active' : '');
                t.addEventListener('click', () => {
                    currentIndex = i;
                    lightboxImg.src = galeria[currentIndex];
                    lightboxCounter.textContent = `${currentIndex + 1} / ${galeria.length}`;
                    updateLightboxThumbs();
                    if (mainImg) mainImg.src = galeria[currentIndex];
                    thumbs.forEach((th, idx) => th.classList.toggle('active', idx === currentIndex));
                });
                lightboxThumbs.appendChild(t);
            });
        }

        // Abrir lightbox al clic en imagen o botón lupa
        if (openBtn) openBtn.addEventListener('click', () => openLightbox(currentIndex));
        if (mainImg) mainImg.addEventListener('click', () => openLightbox(currentIndex));

        // Cerrar lightbox
        document.getElementById('lightbox-close')?.addEventListener('click', closeLightbox);
        document.getElementById('lightbox-overlay')?.addEventListener('click', closeLightbox);

        // Navegación lightbox
        document.getElementById('lightbox-prev')?.addEventListener('click', () => navigateLightbox(-1));
        document.getElementById('lightbox-next')?.addEventListener('click', () => navigateLightbox(1));

        // Teclado: Esc cerrar, flechas navegar
        document.addEventListener('keydown', function (e) {
            if (!lightbox || !lightbox.classList.contains('open')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') navigateLightbox(-1);
            if (e.key === 'ArrowRight') navigateLightbox(1);
        });
    });

    // Stock alert subscription
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('stock-alert-form');
        const msg = document.getElementById('stock-alert-msg');
        if (!form) return;
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            msg.textContent = '';
            const fd = new FormData(form);
            try {
                const res = await fetch('alerta_stock.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd
                });
                const data = await res.json();
                if (data && data.ok) {
                    msg.textContent = data.msg || 'Suscripción registrada';
                    msg.className = 'text-sm mt-2 text-green-500';
                } else {
                    msg.textContent = (data && data.msg) ? data.msg : 'No se pudo suscribir';
                    msg.className = 'text-sm mt-2 text-red-500';
                }
            } catch (err) {
                msg.textContent = 'Error de red. Intenta nuevamente';
                msg.className = 'text-sm mt-2 text-red-500';
            }
        });
    });

    // Combo functionality
    document.addEventListener('DOMContentLoaded', function () {
        const btnCombo = document.getElementById('btn-agregar-combo');
        const selCombo = document.getElementById('combo-select');
        const msgCombo = document.getElementById('combo-msg');
        if (!btnCombo || !selCombo) return;

        btnCombo.addEventListener('click', async function (e) {
            e.preventDefault();
            const isLoggedIn = <?php echo isset($_SESSION['usuario']) ? 'true' : 'false'; ?>;
            if (!isLoggedIn) {
                if (typeof abrirModalLogin === 'function') {
                    abrirModalLogin();
                } else {
                    window.location.href = 'index.php'; // Fallback
                }
                return;
            }
            if (msgCombo) { msgCombo.textContent = ''; msgCombo.className = 'text-sm'; }
            const compatibleId = parseInt(selCombo.value || '0', 10);
            const currentId = parseInt(btnCombo.dataset.product || '0', 10);
            if (!compatibleId) {
                if (msgCombo) { msgCombo.textContent = 'Selecciona un complemento para el combo'; msgCombo.className = 'text-sm text-center text-yellow-500'; }
                return;
            }
            const agregar = async (id) => {
                const fd = new FormData();
                fd.append('id_producto', id);
                fd.append('cantidad', 1);
                const res = await fetch('agregar_carrito.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd
                });
                const data = await res.json();
                if (!data || !data.ok) {
                    throw new Error((data && data.msg) ? data.msg : 'No se pudo agregar al carrito');
                }
                return data;
            };
            try {
                await agregar(currentId);
                const data2 = await agregar(compatibleId);
                if (msgCombo) {
                    msgCombo.innerHTML = 'Combo agregado. Verás -10% aplicado en el carrito. <a href="carrito.php" class="inline-block ml-2 px-2 py-1 rounded bg-red-600 text-white text-xs hover:bg-red-700">Ver carrito</a>';
                    msgCombo.className = 'text-sm text-center text-green-500';
                }
                if (data2 && typeof data2.total !== 'undefined' && data2.total !== null) {
                    const counters = document.querySelectorAll('.cart-counter, .bg-red-600.text-xs');
                    counters.forEach(c => { c.textContent = data2.total; });
                }
            } catch (err) {
                if (msgCombo) { msgCombo.textContent = err.message || 'Error al agregar el combo'; msgCombo.className = 'text-sm text-center text-red-500'; }
            }
        });
    });
</script>
<script>
// ══════════════════════════════════════════════════
// SISTEMA DE RESEÑAS — COMPUTÉCNICOS
// ══════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {
    const PRODUCT_ID = <?php echo intval($producto['id']); ?>;
    const IS_LOGGED_IN = <?php echo isset($_SESSION['usuario']) ? 'true' : 'false'; ?>;
    const BASE = '<?php echo rtrim(base_url(), '/'); ?>';
    
    // ── Cargar reseñas ──
    async function loadReviews() {
        try {
            const res = await fetch(`${BASE}/api/resenas.php?id_producto=${PRODUCT_ID}`);
            const data = await res.json();
            if (!data.ok) return;
            
            renderStats(data.stats);
            renderReviews(data.resenas);
            handleFormVisibility(data);
            
            // Actualizar rating en la sección de info del producto
            updateProductRating(data.stats);
        } catch(e) {
            console.error('Error cargando reseñas:', e);
        }
    }
    
    // ── Render Stats ──
    function renderStats(stats) {
        const el = document.getElementById('reviews-stats');
        if (!el || stats.total === 0) {
            if (el) el.style.display = 'none';
            return;
        }
        el.style.display = '';
        
        const labels = ['Excelente', 'Muy bueno', 'Bueno', 'Regular', 'Malo'];
        let barsHtml = '';
        for (let i = 5; i >= 1; i--) {
            const count = stats.distribucion[i] || 0;
            const pct = stats.total > 0 ? Math.round((count / stats.total) * 100) : 0;
            barsHtml += `
                <div class="rating-bar-row">
                    <span class="rating-bar-label">${i} ★</span>
                    <div class="rating-bar-track">
                        <div class="rating-bar-fill" style="width:${pct}%"></div>
                    </div>
                    <span class="rating-bar-count">${count}</span>
                </div>`;
        }
        
        let starsHtml = '';
        for (let i = 1; i <= 5; i++) {
            starsHtml += `<span class="${i <= Math.round(stats.promedio) ? 'filled' : 'empty'}">★</span>`;
        }
        
        el.innerHTML = `
            <div class="reviews-stats-left">
                <div class="reviews-big-score">${stats.promedio.toFixed(1)}</div>
                <div class="reviews-big-stars">${starsHtml}</div>
                <div class="reviews-total-count">${stats.total} reseña${stats.total !== 1 ? 's' : ''}</div>
            </div>
            <div class="reviews-stats-right">
                ${barsHtml}
            </div>`;
    }
    
    // ── Render Reviews ──
    function renderReviews(resenas) {
        const listEl = document.getElementById('reviews-list');
        const noReviews = document.getElementById('no-reviews');
        
        if (!resenas || resenas.length === 0) {
            listEl.innerHTML = '';
            noReviews.style.display = '';
            return;
        }
        noReviews.style.display = 'none';
        
        let html = '';
        resenas.forEach(r => {
            const initial = (r.usuario || 'U').charAt(0).toUpperCase();
            const stars = Array.from({length: 5}, (_, i) => 
                `<span class="${i < r.calificacion ? 'filled' : 'empty'}">★</span>`
            ).join('');
            
            const fecha = new Date(r.fecha).toLocaleDateString('es-CO', { 
                day: 'numeric', month: 'short', year: 'numeric' 
            });
            
            let imgsHtml = '';
            if (r.imagenes && r.imagenes.length > 0) {
                imgsHtml = '<div class="review-images-grid">';
                r.imagenes.forEach(img => {
                    imgsHtml += `<img src="${BASE}/${img.url_imagen}" alt="Imagen de reseña" class="review-img-thumb" onclick="openReviewImage(this.src)">`;
                });
                imgsHtml += '</div>';
            }
            
            const tituloHtml = r.titulo ? `<div class="prod-review-title">${escapeHtml(r.titulo)}</div>` : '';
            const verificadoHtml = r.verificado ? `<span class="review-verified-badge"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Compra verificada</span>` : '';
            
            html += `
                <div class="prod-review-card">
                    <div class="prod-review-top">
                        <div class="prod-review-user">
                            <div class="prod-review-avatar">${initial}</div>
                            <div>
                                <div class="prod-review-name">${escapeHtml(r.usuario || 'Usuario')}</div>
                                <div class="prod-review-date">${fecha}</div>
                            </div>
                        </div>
                        <div class="prod-review-top-right">
                            <div class="prod-review-stars">${stars}</div>
                            ${verificadoHtml}
                        </div>
                    </div>
                    ${tituloHtml}
                    <p class="prod-review-text">${escapeHtml(r.comentario || '').replace(/\n/g, '<br>')}</p>
                    ${imgsHtml}
                </div>`;
        });
        
        listEl.innerHTML = html;
    }
    
    // ── Form Visibility ──
    function handleFormVisibility(data) {
        const formContainer = document.getElementById('review-form-container');
        const alreadyEl = document.getElementById('review-already');
        const notBuyerEl = document.getElementById('review-not-buyer');
        
        if (!IS_LOGGED_IN) {
            notBuyerEl.style.display = '';
            return;
        }
        
        if (data.ya_reseno) {
            alreadyEl.style.display = '';
        } else if (data.puede_resenar) {
            formContainer.style.display = '';
        } else {
            notBuyerEl.style.display = '';
        }
    }
    
    // ── Update product rating display ──
    function updateProductRating(stats) {
        if (stats.total === 0) return;
        const ratingEl = document.querySelector('.prod-rating');
        if (ratingEl) {
            let starsHtml = '';
            for (let i = 1; i <= 5; i++) {
                starsHtml += `<span class="prod-star ${i <= Math.round(stats.promedio) ? 'filled' : 'empty'}">★</span>`;
            }
            ratingEl.innerHTML = `
                <div class="prod-stars">${starsHtml}</div>
                <span class="prod-rating-text">${stats.promedio.toFixed(1)} (${stats.total} reseña${stats.total !== 1 ? 's' : ''})</span>`;
        }
    }
    
    // ── Star Selector ──
    const starsInput = document.getElementById('stars-input');
    const calInput = document.getElementById('calificacion-input');
    const starsLabel = document.getElementById('stars-label');
    const starLabels = ['', 'Malo', 'Regular', 'Bueno', 'Muy bueno', 'Excelente'];
    
    if (starsInput) {
        const starEls = starsInput.querySelectorAll('.star-select');
        
        starEls.forEach(star => {
            star.addEventListener('mouseenter', function() {
                const val = parseInt(this.dataset.value);
                starEls.forEach((s, i) => {
                    s.classList.toggle('hover', i < val);
                });
            });
            
            star.addEventListener('click', function() {
                const val = parseInt(this.dataset.value);
                calInput.value = val;
                starEls.forEach((s, i) => {
                    s.classList.toggle('selected', i < val);
                });
                starsLabel.textContent = starLabels[val];
                starsLabel.style.color = val >= 4 ? '#4ade80' : val >= 3 ? '#facc15' : '#f87171';
            });
        });
        
        starsInput.addEventListener('mouseleave', function() {
            const current = parseInt(calInput.value) || 0;
            starEls.forEach((s, i) => {
                s.classList.remove('hover');
                s.classList.toggle('selected', i < current);
            });
        });
    }
    
    // ── Character Counters ──
    const tituloInput = document.getElementById('review-titulo');
    const comentarioInput = document.getElementById('review-comentario');
    
    if (tituloInput) {
        tituloInput.addEventListener('input', () => {
            document.getElementById('titulo-count').textContent = tituloInput.value.length;
        });
    }
    if (comentarioInput) {
        comentarioInput.addEventListener('input', () => {
            document.getElementById('comentario-count').textContent = comentarioInput.value.length;
        });
    }
    
    // ── Image Upload with Drag & Drop ──
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('review-images');
    const previewsEl = document.getElementById('upload-previews');
    const placeholder = document.getElementById('upload-placeholder');
    let selectedFiles = [];
    
    if (uploadArea && fileInput) {
        uploadArea.addEventListener('click', (e) => {
            if (e.target.closest('.upload-remove-btn')) return;
            fileInput.click();
        });
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('drag-over');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            handleFiles(e.dataTransfer.files);
        });
        
        fileInput.addEventListener('change', () => {
            handleFiles(fileInput.files);
        });
    }
    
    function handleFiles(fileList) {
        const newFiles = Array.from(fileList).filter(f => f.type.startsWith('image/'));
        selectedFiles = [...selectedFiles, ...newFiles].slice(0, 3);
        renderPreviews();
    }
    
    function renderPreviews() {
        if (!previewsEl || !placeholder) return;
        previewsEl.innerHTML = '';
        
        if (selectedFiles.length === 0) {
            placeholder.style.display = '';
            return;
        }
        placeholder.style.display = 'none';
        
        selectedFiles.forEach((file, idx) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'upload-preview-item';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="upload-remove-btn" data-idx="${idx}">×</button>`;
                div.querySelector('.upload-remove-btn').addEventListener('click', (ev) => {
                    ev.stopPropagation();
                    selectedFiles.splice(idx, 1);
                    renderPreviews();
                    updateFileInput();
                });
                previewsEl.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }
    
    function updateFileInput() {
        const dt = new DataTransfer();
        selectedFiles.forEach(f => dt.items.add(f));
        if (fileInput) fileInput.files = dt.files;
    }
    
    // ── Form Submit ──
    const reviewForm = document.getElementById('review-form');
    if (reviewForm) {
        reviewForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const msgEl = document.getElementById('review-form-msg');
            const submitBtn = document.getElementById('review-submit-btn');
            
            // Validar calificación
            if (parseInt(calInput.value) < 1) {
                msgEl.textContent = 'Por favor selecciona una calificación (1-5 estrellas)';
                msgEl.style.color = '#f87171';
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="spinner" style="width:18px;height:18px;border:2px solid transparent;border-top-color:currentColor;border-radius:50%;animation:spin 0.8s linear infinite"></div> Enviando...';
            msgEl.textContent = '';
            
            const fd = new FormData(reviewForm);
            // Agregar archivos manualmente (por si se usó drag & drop)
            fd.delete('imagenes[]');
            selectedFiles.forEach(f => fd.append('imagenes[]', f));
            
            try {
                const res = await fetch(`${BASE}/api/resenas.php`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                });
                const data = await res.json();
                
                if (data.ok) {
                    msgEl.textContent = data.msg || '¡Reseña publicada!';
                    msgEl.style.color = '#4ade80';
                    // Recargar reseñas
                    setTimeout(() => {
                        document.getElementById('review-form-container').style.display = 'none';
                        document.getElementById('review-already').style.display = '';
                        loadReviews();
                    }, 1000);
                } else {
                    msgEl.textContent = data.msg || 'Error al publicar la reseña.';
                    msgEl.style.color = '#f87171';
                }
            } catch(err) {
                msgEl.textContent = 'Error de conexión. Intenta de nuevo.';
                msgEl.style.color = '#f87171';
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg> Publicar Reseña';
            }
        });
    }
    
    // ── Helpers ──
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }
    
    // Cargar reseñas al inicio
    loadReviews();
});

// ── Lightbox para imágenes de reseñas ──
function openReviewImage(src) {
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.92);display:flex;align-items:center;justify-content:center;cursor:pointer;backdrop-filter:blur(8px)';
    overlay.innerHTML = `<img src="${src}" style="max-width:90vw;max-height:85vh;object-fit:contain;border-radius:0.5rem;box-shadow:0 0 40px rgba(0,0,0,0.5)">
        <button style="position:absolute;top:20px;right:20px;width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.15);color:#fff;font-size:1.5rem;cursor:pointer;display:flex;align-items:center;justify-content:center">×</button>`;
    overlay.addEventListener('click', () => overlay.remove());
    document.body.appendChild(overlay);
}
</script>
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>

</html>