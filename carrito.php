<?php
require_once __DIR__ . '/app/Core/bootstrap.php';
// Sesión manejada por bootstrap (DB handler)

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Utilidades para respuestas AJAX
function expects_json()
{
    $xrw = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $accept = isset($_SERVER['HTTP_ACCEPT']) ? strtolower($_SERVER['HTTP_ACCEPT']) : '';
    return $xrw || strpos($accept, 'application/json') !== false || strpos($accept, 'json') !== false;
}
function respond_json($payload)
{
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

// Eliminar producto del carrito
if (isset($_GET['eliminar'])) {
    $id_eliminar = intval($_GET['eliminar']);
    $_SESSION['carrito'] = array_filter($_SESSION['carrito'], function ($item) use ($id_eliminar) {
        return $item['id_producto'] != $id_eliminar;
    });
    header('Location: carrito.php');
    exit;
}

// Actualizar cantidades
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])) {
    $stock_limitado_update = false;
    $stock_max_msg = '';
    foreach ($_POST['cantidades'] as $id_producto => $cantidad) {
        foreach ($_SESSION['carrito'] as &$item) {
            if ($item['id_producto'] == $id_producto) {
                $cantidad = max(1, intval($cantidad));
                // Verificar stock disponible
                $stmtStock = $pdo->prepare('SELECT stock FROM productos WHERE id = ?');
                $stmtStock->execute([intval($id_producto)]);
                $prodStock = $stmtStock->fetch();
                $maxStock = $prodStock ? (int) $prodStock['stock'] : $cantidad;
                if ($cantidad > $maxStock) {
                    $cantidad = $maxStock;
                    $stock_limitado_update = true;
                    $stock_max_msg = 'Has alcanzado el stock máximo disponible (' . $maxStock . ' unidades).';
                }
                $item['cantidad'] = $cantidad;
            }
        }
    }
    unset($item);
    if (expects_json()) {
        $ids = array_column($_SESSION['carrito'], 'id_producto');
        $productos = [];
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id IN ($in)");
            $stmt->execute($ids);
            $productos = $stmt->fetchAll(PDO::FETCH_UNIQUE);
        }
        $subtotal = 0;
        $total_items = 0;
        foreach ($_SESSION['carrito'] as $it) {
            $total_items += (int) $it['cantidad'];
            if (isset($productos[$it['id_producto']])) {
                $priceData = get_product_price_data($productos[$it['id_producto']]);
                $subtotal += $priceData['precio'] * $it['cantidad'];
            }
        }
        $envio = $subtotal > 50000 ? 0 : 10000;
        $msg = $stock_limitado_update ? $stock_max_msg : 'Cantidad actualizada';
        respond_json(['ok' => true, 'msg' => $msg, 'subtotal' => $subtotal, 'envio' => $envio, 'total' => $subtotal + $envio, 'items' => $total_items, 'stock_limit' => $stock_limitado_update]);
    }
    header('Location: carrito.php');
    exit;
}

// Vaciar carrito
if (isset($_GET['vaciar'])) {
    $_SESSION['carrito'] = [];
    header('Location: carrito.php');
    exit;
}

// Configuración del header
$page_title = 'Carrito de Compras';
$extra_css = '<link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet" />' . "\n" .
    '<link rel="stylesheet" href="' . asset('css/index.css') . '">' . "\n" .
    '<link rel="stylesheet" href="' . asset('css/carrito.css') . '">';

// Obtener productos del carrito
$ids = array_column($_SESSION['carrito'], 'id_producto');
$productos = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT p.*, c.nombre AS categoria, m.nombre AS marca FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id LEFT JOIN marcas m ON p.id_marca = m.id WHERE p.id IN ($in)");
    $stmt->execute($ids);
    $productos = $stmt->fetchAll(PDO::FETCH_UNIQUE);
}




// Calcular totales
$subtotal = 0;
$total_items = 0;
foreach ($_SESSION['carrito'] as $item) {
    $total_items += (int) $item['cantidad'];
    if (isset($productos[$item['id_producto']])) {
        $priceData = get_product_price_data($productos[$item['id_producto']]);
        $subtotal += $priceData['precio'] * $item['cantidad'];
    }
}

// Descuento por combos (10% en pares compatibles)
$combo_discount = 0;
try {
    $byCat = [];
    foreach ($_SESSION['carrito'] as $item) {
        $pid = $item['id_producto'];
        if (!isset($productos[$pid]))
            continue;
        $cat = $productos[$pid]['categoria'];
        if (!isset($byCat[$cat]))
            $byCat[$cat] = [];
        $priceData = get_product_price_data($productos[$pid]);
        $byCat[$cat][] = [
            'precio' => (float) $priceData['precio'],
            'cantidad' => (int) $item['cantidad']
        ];
    }
    $flatten = function (array $list) {
        $arr = [];
        foreach ($list as $it) {
            for ($i = 0; $i < $it['cantidad']; $i++) {
                $arr[] = $it['precio'];
            }
        }
        sort($arr);
        return $arr;
    };
    $catCPU = $flatten($byCat['Procesadores'] ?? []);
    $catRAM = $flatten($byCat['Memorias RAM'] ?? []);
    $catGPU = $flatten($byCat['Tarjetas Gráficas'] ?? []);
    $catSTO = $flatten($byCat['Almacenamiento'] ?? []);
    $catPER = $flatten($byCat['Periféricos'] ?? []);

    $pairDiscount = function (&$a, &$b) {
        $d = 0.0;
        $pairs = min(count($a), count($b));
        for ($i = 0; $i < $pairs; $i++) {
            $pa = array_shift($a);
            $pb = array_shift($b);
            $d += 0.10 * ($pa + $pb);
        }
        return $d;
    };

    $combo_discount += $pairDiscount($catCPU, $catRAM);
    $combo_discount += $pairDiscount($catCPU, $catSTO);
    $combo_discount += $pairDiscount($catGPU, $catPER);
    $combo_discount += $pairDiscount($catGPU, $catSTO);
    $combo_discount += $pairDiscount($catSTO, $catRAM);
    $combo_discount += $pairDiscount($catSTO, $catPER);
    $combo_discount = round($combo_discount, 2);
} catch (Exception $e) {
    $combo_discount = 0;
}

// Calcular envío
$envioGratisMin = 50000;
$envio = $subtotal > $envioGratisMin ? 0 : 10000;
$total = max(0, $subtotal - $combo_discount) + $envio;
$faltanteEnvioGratis = max(0, $envioGratisMin - $subtotal);
$avanceEnvioGratis = min(100, max(0, floor(($subtotal / $envioGratisMin) * 100)));

// Cupón por entrega tardía
$lateDeliveryCouponCop = 4000;



include __DIR__ . '/includes/header.php';
?>

<main class="flex-1 bg-[#050505] text-white relative z-10">

    <!-- Hero Section -->

    <section class="cart-hero">
        <!--
        <div class="container mx-auto px-4">
            <div class="cart-hero-content animate-slide-up">
                <div class="cart-hero-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <h1 class="cart-hero-title">Tu Carrito</h1>
                <p class="cart-hero-subtitle">Revisa tus productos y procede al checkout cuando estés listo</p>
                -->
        <?php if (!empty($_SESSION['carrito'])): ?>
            <div class="cart-count-badge animate-slide-up delay-200">
                <i data-lucide="package" class="w-5 h-5"></i>
                <span class="cart-count-number"><?php echo $total_items; ?></span>
                <span>producto<?php echo $total_items !== 1 ? 's' : ''; ?> en tu carrito</span>
            </div>
        <?php endif; ?>
        </div>
        </div>
    </section>

    <!-- Contenido del Carrito -->
    <section class="container mx-auto px-4 pb-20">
        <?php if (!isset($_SESSION['usuario'])): ?>
            <!-- Requiere iniciar sesión -->
            <div class="cart-empty animate-slide-up delay-100">
                <div class="cart-empty-icon" style="color:#ff4444;">
                    <i data-lucide="lock" style="width:48px;height:48px"></i>
                </div>
                <h2 class="cart-empty-title">Inicia sesión para ver tu carrito</h2>
                <p class="cart-empty-text">Necesitas una cuenta para agregar productos y gestionar tu carrito de compras.</p>
                <button type="button" onclick="if(typeof abrirModalLogin==='function'){abrirModalLogin();}else{window.location.href='index.php';}" class="cart-empty-btn">
                    <i data-lucide="user" style="width:20px;height:20px"></i>
                    Iniciar Sesión
                </button>
                <a href="productos.php" class="cart-empty-btn" style="background:transparent;border:1px solid rgba(255,255,255,0.15);color:#ccc;margin-top:0.75rem;">
                    <i data-lucide="shopping-bag" style="width:20px;height:20px"></i>
                    Explorar Productos
                </a>
            </div>
        <?php elseif (empty($_SESSION['carrito'])): ?>
            <!-- Carrito Vacío -->
            <div class="cart-empty animate-slide-up delay-100">
                <div class="cart-empty-icon">
                    <i data-lucide="shopping-cart" style="width:48px;height:48px"></i>
                </div>
                <h2 class="cart-empty-title">Tu carrito está vacío</h2>
                <p class="cart-empty-text">¡Explora nuestra tienda y agrega productos increíbles a tu carrito!</p>
                <a href="productos.php" class="cart-empty-btn">
                    <i data-lucide="shopping-bag" style="width:20px;height:20px"></i>
                    Explorar Productos
                </a>
            </div>



        <?php else: ?>
            <!-- Carrito con productos -->
            <div class="cart-layout">
                <!-- Lista de Items -->
                <div class="cart-items-container">
                    <div class="cart-items-header animate-slide-up">
                        <h2 class="cart-items-title">
                            <i data-lucide="shopping-bag" style="width:20px;height:20px"></i>
                            Productos
                            <span class="cart-items-count">(<?php echo count($_SESSION['carrito']); ?> items)</span>
                        </h2>
                    </div>

                    <div class="cart-items-list">
                        <?php
                        $delay = 0;
                        foreach ($_SESSION['carrito'] as $item):
                            if (!isset($productos[$item['id_producto']]))
                                continue;
                            $p = $productos[$item['id_producto']];
                            $priceData = get_product_price_data($p);
                            $itemSubtotal = $priceData['precio'] * $item['cantidad'];
                            $delay_class = 'delay-' . min($delay * 100, 500);
                            ?>
                            <div class="cart-item-card animate-slide-right <?php echo $delay_class; ?>">
                                <div class="cart-item-inner">
                                    <!-- Imagen -->
                                    <div class="cart-item-image">
                                        <img src="<?php echo htmlspecialchars($p['imagen'] ?: 'https://via.placeholder.com/200x200?text=Sin+Imagen'); ?>"
                                            alt="<?php echo htmlspecialchars($p['nombre']); ?>">
                                        <?php if (!empty($p['oferta'])): ?>
                                            <span class="cart-item-badge">Oferta</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Info -->
                                    <div class="cart-item-info">
                                        <span class="cart-item-category"><?php echo htmlspecialchars($p['categoria']); ?></span>
                                        <h3 class="cart-item-name"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                                        <div class="cart-item-meta">
                                            <span class="cart-item-brand"><?php echo htmlspecialchars($p['marca']); ?></span>
                                            <?php if ((int) $p['stock'] > 0): ?>
                                                <span class="cart-item-stock in-stock">En stock (<?php echo $p['stock']; ?>)</span>
                                            <?php else: ?>
                                                <span class="cart-item-stock out-stock">Sin stock</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Controles móvil -->
                                        <div class="cart-item-controls-mobile hidden md:hidden">
                                            <div class="cart-quantity-wrapper">
                                                <button type="button" class="cart-quantity-btn"
                                                    onclick="decrementQuantity(<?php echo intval($item['id_producto']); ?>)">−</button>
                                                <input type="number" id="qty-<?php echo intval($item['id_producto']); ?>"
                                                    value="<?php echo $item['cantidad']; ?>" min="1"
                                                    max="<?php echo $p['stock']; ?>" class="cart-quantity-input"
                                                    onchange="actualizarCantidad(<?php echo intval($item['id_producto']); ?>, this.value)">
                                                <button type="button" class="cart-quantity-btn"
                                                    onclick="incrementQuantity(<?php echo intval($item['id_producto']); ?>, <?php echo $p['stock']; ?>)">+</button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Controles desktop -->
                                    <div class="cart-item-controls">
                                        <div class="cart-item-subtotal">
                                            <span class="cart-item-subtotal-label">Subtotal</span>
                                            <span
                                                class="cart-item-subtotal-value">$<?php echo number_format($itemSubtotal, 0, ',', '.'); ?></span>
                                        </div>

                                        <div class="cart-quantity-wrapper">
                                            <button type="button" class="cart-quantity-btn"
                                                onclick="decrementQuantity(<?php echo intval($item['id_producto']); ?>)">−</button>
                                            <input type="number" id="qty-desktop-<?php echo intval($item['id_producto']); ?>"
                                                value="<?php echo $item['cantidad']; ?>" min="1"
                                                max="<?php echo $p['stock']; ?>" class="cart-quantity-input"
                                                onchange="actualizarCantidad(<?php echo intval($item['id_producto']); ?>, this.value)">
                                            <button type="button" class="cart-quantity-btn"
                                                onclick="incrementQuantity(<?php echo intval($item['id_producto']); ?>, <?php echo $p['stock']; ?>)">+</button>
                                        </div>

                                        <div class="cart-item-actions">
                                            <button type="button" class="cart-remove-btn"
                                                onclick="eliminarDelCarrito(<?php echo intval($item['id_producto']); ?>)"
                                                title="Eliminar">
                                                <i data-lucide="trash-2" style="width:18px;height:18px"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $delay++;
                        endforeach;
                        ?>
                    </div>
                </div>

                <!-- Sidebar Resumen -->
                <div class="cart-summary-sidebar animate-slide-up delay-200">
                    <div class="cart-summary-card">
                        <div class="cart-summary-header">
                            <i data-lucide="calculator" style="width:20px;height:20px"></i>
                            <h2 class="cart-summary-title">Resumen de Compra</h2>
                        </div>

                        <div class="cart-summary-body">
                            <!-- Filas del resumen -->
                            <div class="cart-summary-row">
                                <span class="cart-summary-label">Subtotal (<?php echo $total_items; ?> productos)</span>
                                <span
                                    class="cart-summary-value">$<?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                            </div>

                            <div class="cart-summary-row">
                                <span class="cart-summary-label">Envío</span>
                                <span class="cart-summary-value <?php echo $envio === 0 ? 'free' : ''; ?>">
                                    <?php echo $envio > 0 ? '$' . number_format($envio, 0, ',', '.') : '¡Gratis!'; ?>
                                </span>
                            </div>

                            <!-- Barra de progreso envío gratis -->
                            <div class="cart-shipping-progress">
                                <div class="cart-shipping-header">
                                    <span class="cart-shipping-label">Envío gratis</span>
                                    <span class="cart-shipping-percent"><?php echo $avanceEnvioGratis; ?>%</span>
                                </div>
                                <div class="cart-shipping-bar">
                                    <div class="cart-shipping-fill" style="width: <?php echo $avanceEnvioGratis; ?>%;">
                                    </div>
                                </div>
                                <?php if ($faltanteEnvioGratis > 0): ?>
                                    <p class="cart-shipping-text remaining">Te faltan
                                        $<?php echo number_format($faltanteEnvioGratis, 0, ',', '.'); ?> para envío gratis</p>
                                <?php else: ?>
                                    <p class="cart-shipping-text complete">🎉 ¡Ya alcanzaste el envío gratis!</p>
                                <?php endif; ?>
                            </div>

                            <?php if ($combo_discount > 0): ?>
                                <!-- Descuento combo -->
                                <div class="cart-combo-discount">
                                    <div class="cart-combo-header">
                                        <span class="cart-combo-label">
                                            <i data-lucide="tag" style="width:16px;height:16px"></i>
                                            Descuento Combo
                                        </span>
                                        <span class="cart-combo-value">-
                                            $<?php echo number_format($combo_discount, 0, ',', '.'); ?></span>
                                    </div>
                                    <p class="cart-combo-text">Ahorro por combinar categorías compatibles (ej. CPU + RAM)</p>
                                </div>
                            <?php endif; ?>

                            <!-- Garantía de entrega -->
                            <div class="cart-guarantee">
                                <i data-lucide="shield-check" style="width:20px;height:20px"></i>
                                <p class="cart-guarantee-text">
                                    Entrega garantizada: si se retrasa, recibes un cupón de
                                    $<?php echo number_format($lateDeliveryCouponCop, 0, ',', '.'); ?>.
                                </p>
                            </div>

                            <!-- Total -->
                            <div class="cart-total-section">
                                <div class="cart-total-row">
                                    <span class="cart-total-label">Total</span>
                                    <span class="cart-total-value">$<?php echo number_format($total, 0, ',', '.'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="cart-actions">
                            <?php if (isset($_SESSION['usuario'])): ?>
                                <a href="checkout.php" class="cart-checkout-btn">
                                    <i data-lucide="arrow-right" style="width:20px;height:20px"></i>
                                    Proceder al Pago
                                </a>
                            <?php else: ?>
                                <a href="index.php?login=1" class="cart-checkout-btn">
                                    <i data-lucide="user" style="width:20px;height:20px"></i>
                                    Iniciar Sesión para Pagar
                                </a>
                            <?php endif; ?>
                            <button type="button" class="cart-clear-btn" onclick="vaciarCarrito()">
                                <i data-lucide="trash-2" style="width:18px;height:18px"></i>
                                Vaciar Carrito
                            </button>
                        </div>
                    </div>


                </div>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- Modal de Confirmación -->
<div id="cart-modal" class="cart-modal-overlay">
    <div class="cart-modal">
        <div class="cart-modal-header">
            <i data-lucide="alert-triangle" style="width:24px;height:24px"></i>
            <h3 class="cart-modal-title">Confirmar acción</h3>
        </div>
        <div class="cart-modal-body">
            <p id="cart-modal-text" class="cart-modal-text">¿Estás seguro de que deseas continuar?</p>
        </div>
        <div class="cart-modal-actions">
            <button type="button" id="cart-modal-cancel" class="cart-modal-btn cancel">Cancelar</button>
            <button type="button" id="cart-modal-confirm" class="cart-modal-btn confirm">Confirmar</button>
        </div>
    </div>
</div>

<!-- Toast notification container -->
<div id="toast-container" style="position:fixed;top:80px;right:16px;z-index:900;display:flex;flex-direction:column;gap:8px;"></div>

<script>
    (function () {
        // Toast notification function
        function showToast(message, type = 'error', duration = 4000) {
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                toastContainer.style.cssText = 'position:fixed;top:80px;right:16px;z-index:900;display:flex;flex-direction:column;gap:8px;';
                document.body.appendChild(toastContainer);
            }

            const toast = document.createElement('div');
            toast.style.cssText = 'transform:translateX(120%);opacity:0;transition:all 0.3s ease-in-out;';

            let icon, title, borderColor, dotColor, barColor;
            if (type === 'success') {
                icon = '✓';
                title = '¡Listo!';
                borderColor = '#22c55e';
                dotColor = '#22c55e';
                barColor = 'linear-gradient(to right, #22c55e, #16a34a)';
            } else if (type === 'warning') {
                icon = '⚠';
                title = 'Atención';
                borderColor = '#f59e0b';
                dotColor = '#f59e0b';
                barColor = 'linear-gradient(to right, #f59e0b, #d97706)';
            } else {
                icon = '✕';
                title = 'Error';
                borderColor = '#ef4444';
                dotColor = '#ef4444';
                barColor = 'linear-gradient(to right, #ef4444, #dc2626)';
            }

            toast.innerHTML = `
                <div style="position:relative;max-width:360px;padding:14px 16px;border-radius:16px;background:rgba(20,20,20,0.95);color:#f3f4f6;border:1px solid #333;backdrop-filter:blur(12px);box-shadow:0 20px 60px rgba(0,0,0,0.5);overflow:hidden;">
                    <div style="display:flex;align-items:flex-start;gap:10px;">
                        <div style="flex-shrink:0;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:bold;color:${dotColor};border:2px solid ${dotColor};">${icon}</div>
                        <div style="flex:1;">
                            <div style="font-size:13px;font-weight:600;letter-spacing:0.5px;color:${dotColor};">${title}</div>
                            <div style="font-size:12px;margin-top:4px;opacity:0.9;line-height:1.4;">${message}</div>
                        </div>
                        <button onclick="this.closest('div[style]').parentElement.remove()" style="flex-shrink:0;margin-left:4px;color:#9ca3af;background:none;border:none;cursor:pointer;font-size:18px;line-height:1;">&times;</button>
                    </div>
                    <div style="position:absolute;left:0;right:0;bottom:0;height:3px;overflow:hidden;border-radius:0 0 16px 16px;">
                        <div class="toast-progress-bar" style="height:3px;width:100%;background:${barColor};transition:width ${duration}ms linear;"></div>
                    </div>
                </div>
            `;

            toastContainer.appendChild(toast);

            setTimeout(() => {
                toast.style.transform = 'translateX(0)';
                toast.style.opacity = '1';
            }, 50);

            const bar = toast.querySelector('.toast-progress-bar');
            if (bar) {
                requestAnimationFrame(() => { bar.style.width = '0%'; });
            }

            setTimeout(() => {
                toast.style.transform = 'translateX(120%)';
                toast.style.opacity = '0';
                setTimeout(() => { if (toast.parentNode) toast.remove(); }, 300);
            }, duration);
        }

        // Modal
        const modal = document.getElementById('cart-modal');
        const modalText = document.getElementById('cart-modal-text');
        const btnConfirm = document.getElementById('cart-modal-confirm');
        const btnCancel = document.getElementById('cart-modal-cancel');
        let onConfirm = null;

        function openModal(message, callback) {
            modalText.textContent = message;
            onConfirm = callback;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
            onConfirm = null;
        }

        btnConfirm.addEventListener('click', function () {
            if (typeof onConfirm === 'function') onConfirm();
            closeModal();
        });

        btnCancel.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
        });

        // Funciones del carrito
        async function actualizarCantidad(id, cantidad) {
            if (cantidad < 1) cantidad = 1;
            try {
                const fd = new FormData();
                fd.append('actualizar', '1');
                fd.append(`cantidades[${id}]`, String(cantidad));
                const res = await fetch('carrito.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: fd
                });
                const data = await res.json();
                if (data && data.ok) {
                    if (data.stock_limit) {
                        showToast(data.msg || 'Has alcanzado el stock máximo disponible.', 'warning', 4000);
                    }
                    window.location.reload();
                }
            } catch (e) {
                console.error('Error al actualizar cantidad:', e);
            }
        }

        function incrementQuantity(id, maxStock) {
            const input = document.getElementById('qty-desktop-' + id) || document.getElementById('qty-' + id);
            if (input) {
                let val = parseInt(input.value) || 1;
                if (val < maxStock) {
                    input.value = val + 1;
                    actualizarCantidad(id, val + 1);
                } else {
                    showToast('Has alcanzado el stock máximo disponible (' + maxStock + ' unidades) de este producto.', 'warning', 4000);
                }
            }
        }

        function decrementQuantity(id) {
            const input = document.getElementById('qty-desktop-' + id) || document.getElementById('qty-' + id);
            if (input) {
                let val = parseInt(input.value) || 1;
                if (val > 1) {
                    input.value = val - 1;
                    actualizarCantidad(id, val - 1);
                }
            }
        }

        function eliminarDelCarrito(id) {
            openModal('¿Seguro que deseas eliminar este producto del carrito?', function () {
                window.location.href = 'carrito.php?eliminar=' + id;
            });
        }

        function vaciarCarrito() {
            openModal('¿Seguro que deseas vaciar todo el carrito?', function () {
                window.location.href = 'carrito.php?vaciar=1';
            });
        }

        // Exponer globalmente
        window.actualizarCantidad = actualizarCantidad;
        window.incrementQuantity = incrementQuantity;
        window.decrementQuantity = decrementQuantity;
        window.eliminarDelCarrito = eliminarDelCarrito;
        window.vaciarCarrito = vaciarCarrito;
    })();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>

</html>