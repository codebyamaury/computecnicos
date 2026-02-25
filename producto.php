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
$esNuevo = (strtotime($producto['fecha_creacion']) > strtotime('-15 days'));
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
$extra_css = '<link rel="stylesheet" href="' . asset('css/index.css') . '?v=' . time() . '_11">' . "\n" .
    '<link rel="stylesheet" href="' . asset('css/producto.css') . '?v=' . time() . '_2">';
include 'includes/header.php';
?>

<main class="flex-1 bg-[#0a0a0a] text-white">

    <!-- Product Main -->
    <section class="prod-main">

        <!-- Galería -->
        <div class="prod-gallery">
            <div class="prod-img-main">
                <div class="prod-badges">
                    <?php if (!empty($producto['oferta'])): ?>
                        <span class="prod-badge prod-badge-offer">OFERTA</span>
                    <?php endif; ?>
                    <?php if ($esNuevo): ?>
                        <span class="prod-badge prod-badge-new">NUEVO</span>
                    <?php endif; ?>
                </div>
                <img id="main-img"
                    src="<?php echo htmlspecialchars($galeria[0] ?? 'https://via.placeholder.com/500x500?text=Sin+Imagen'); ?>"
                    alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
            </div>

            <?php if (count($galeria) > 1): ?>
                <div class="prod-thumbs">
                    <?php foreach ($galeria as $idx => $img): ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" data-src="<?php echo htmlspecialchars($img); ?>"
                            alt="Miniatura <?php echo $idx + 1; ?>"
                            class="prod-thumb thumb-img <?php echo $idx === 0 ? 'active' : ''; ?>">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
                    <form method="POST" action="agregar_carrito.php" class="prod-action-form">
                        <input type="hidden" name="id_producto" value="<?php echo intval($producto['id']); ?>">
                        <input type="hidden" name="cantidad" id="qty-hidden-buy" value="1">
                        <input type="hidden" name="redirect" value="checkout.php">
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

    <!-- Reseñas -->
    <section class="prod-reviews-section">
        <div class="prod-reviews-header">
            <h2><i data-lucide="message-square" class="w-6 h-6"></i> Reseñas</h2>
            <?php if ($avgRating !== null): ?>
                <div class="prod-reviews-avg">
                    <span class="star">★</span>
                    <span class="score"><?php echo number_format($avgRating, 1); ?></span>
                    <span class="count">(<?php echo count($resenas); ?>)</span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($resenas)): ?>
            <div class="prod-reviews-grid">
                <?php foreach ($resenas as $r): ?>
                    <div class="prod-review-card">
                        <div class="prod-review-top">
                            <div class="prod-review-user">
                                <div class="prod-review-avatar">
                                    <?php echo strtoupper(substr($r['usuario'] ?: 'U', 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="prod-review-name"><?php echo htmlspecialchars($r['usuario'] ?: 'Usuario'); ?>
                                    </div>
                                    <div class="prod-review-date"><?php echo date('d M Y', strtotime($r['fecha'])); ?></div>
                                </div>
                            </div>
                            <div class="prod-review-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="<?php echo $i <= intval($r['calificacion']) ? 'filled' : 'empty'; ?>">★</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <p class="prod-review-text"><?php echo nl2br(htmlspecialchars($r['comentario'])); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            </div>
        <?php else: ?>
            <div class="prod-no-reviews">
                <div class="icon"><i data-lucide="message-circle" class="w-12 h-12" style="color:#333"></i></div>
                <h3>Sin reseñas aún</h3>
                <p>Sé el primero en opinar sobre este producto.</p>
            </div>
        <?php endif; ?>
    </section>
</main>

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
        document.querySelectorAll('form[action="agregar_carrito.php"]').forEach(form => {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                e.stopPropagation();
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

        // Gallery thumbnails
        const mainImg = document.getElementById('main-img');
        const thumbs = document.querySelectorAll('.thumb-img');

        thumbs.forEach(thumb => {
            thumb.addEventListener('click', function () {
                const src = this.dataset.src || this.src;
                if (mainImg) mainImg.src = src;
                thumbs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
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
<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>
</body>

</html>