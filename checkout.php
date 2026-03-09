<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/app/Core/bootstrap.php';
// Configuración de PayPal
$paypal_config = require __DIR__ . '/config/paypal_config.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php');
    exit;
}

// ── Compra directa: recibir producto desde "Comprar Ahora" ──
$es_compra_directa = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['compra_directa'])) {
    $cd_id = intval($_POST['id_producto'] ?? 0);
    $cd_qty = max(1, intval($_POST['cantidad'] ?? 1));
    if ($cd_id > 0) {
        // Verificar que el producto existe y tiene stock
        $stmt = $pdo->prepare('SELECT id, stock FROM productos WHERE id = ?');
        $stmt->execute([$cd_id]);
        $cd_prod = $stmt->fetch();
        if ($cd_prod && (int) $cd_prod['stock'] > 0) {
            if ($cd_qty > (int) $cd_prod['stock']) {
                $cd_qty = (int) $cd_prod['stock'];
            }
            $_SESSION['compra_directa'] = [
                ['id_producto' => $cd_id, 'cantidad' => $cd_qty]
            ];
        }
    }
}

// Determinar si estamos en modo compra directa
if (isset($_SESSION['compra_directa']) && !empty($_SESSION['compra_directa'])) {
    $es_compra_directa = true;
}

// Fuente de items: compra directa o carrito normal
if ($es_compra_directa) {
    $items_source = $_SESSION['compra_directa'];
} else {
    $items_source = $_SESSION['carrito'] ?? [];
}

$carrito_vacio = empty($items_source);

$productos = [];
$total = 0;
$items_detalle = [];
$combo_discount = 0;

if (!$carrito_vacio) {
    // Obtener productos
    $ids = array_column($items_source, 'id_producto');
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT p.*, c.nombre AS categoria, m.nombre AS marca FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id LEFT JOIN marcas m ON p.id_marca = m.id WHERE p.id IN ($in)");
        $stmt->execute($ids);
        $productos = $stmt->fetchAll(PDO::FETCH_UNIQUE);
    }

    // Calcular total
    foreach ($items_source as $item) {
        if (isset($productos[$item['id_producto']])) {
            $p = $productos[$item['id_producto']];
            $total += $p['precio'] * $item['cantidad'];
            $items_detalle[] = [
                'nombre' => $p['nombre'],
                'cantidad' => $item['cantidad'],
                'precio' => $p['precio']
            ];
        }
    }

    // Descuento por combos (solo para carrito normal, no compra directa)
    if (!$es_compra_directa) {
        try {
            $byCat = [];
            foreach ($items_source as $item) {
                $pid = $item['id_producto'];
                if (!isset($productos[$pid]))
                    continue;
                $cat = $productos[$pid]['categoria'];
                if (!isset($byCat[$cat]))
                    $byCat[$cat] = [];
                $byCat[$cat][] = [
                    'precio' => (float) $productos[$pid]['precio'],
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
    }

    // Aplicar descuento al total
    $total = max(0, $total - $combo_discount);
}

// Datos del usuario si está logeado
$nombre = $email = $telefono = $direccion = '';
$id_usuario = null;
if (isset($_SESSION['usuario'])) {
    $id_usuario = $_SESSION['usuario']['id'];
    $nombre = $_SESSION['usuario']['nombre'];
    $email = $_SESSION['usuario']['email'];
    // Obtener teléfono y dirección actualizados
    $stmt = $pdo->prepare('SELECT telefono, direccion FROM usuarios WHERE id = ?');
    $stmt->execute([$id_usuario]);
    $u = $stmt->fetch();
    if ($u) {
        $telefono = $u['telefono'] ?? '';
        $direccion = $u['direccion'] ?? '';
    }
}

$mensaje = '';
$id_pedido = null;
if (!$carrito_vacio && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['compra_directa'])) {
    $nombre = $_POST['nombre'] ?? '';
    $email = $_POST['email'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $direccion = $_POST['direccion'] ?? '';

    if ($nombre && $email && $direccion) {
        try {
            // Validar stock disponible antes de crear el pedido
            $sin_stock = [];
            foreach ($items_source as $item) {
                if (isset($productos[$item['id_producto']])) {
                    $p = $productos[$item['id_producto']];
                    if ((int) $p['stock'] < (int) $item['cantidad']) {
                        $sin_stock[] = htmlspecialchars($p['nombre']) . ' (disponible: ' . $p['stock'] . ', solicitado: ' . $item['cantidad'] . ')';
                    }
                }
            }
            if (!empty($sin_stock)) {
                throw new Exception('Stock insuficiente para: ' . implode(', ', $sin_stock));
            }

            // Registrar pedido
            $pdo->beginTransaction();

            // Insertar pedido
            $stmt = $pdo->prepare('INSERT INTO pedidos (id_usuario, fecha, estado, total, direccion_envio) VALUES (?, NOW(), ?, ?, ?)');
            $stmt->execute([$id_usuario, 'pendiente', $total, $direccion]);
            $id_pedido = $pdo->lastInsertId();

            // Insertar detalles
            $stmt = $pdo->prepare('INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)');
            foreach ($items_source as $item) {
                if (isset($productos[$item['id_producto']])) {
                    $p = $productos[$item['id_producto']];
                    $stmt->execute([$id_pedido, $item['id_producto'], $item['cantidad'], $p['precio']]);
                }
            }

            // Registrar estado inicial en historial
            $stmt_hist = $pdo->prepare('INSERT INTO pedido_estados (id_pedido, estado, comentario) VALUES (?, ?, ?)');
            $stmt_hist->execute([$id_pedido, 'pendiente', 'Pedido creado desde checkout']);
            $pdo->commit();
            $mensaje = 'Pedido creado. Completa el pago con PayPal para finalizar.';

            // Si era compra directa, limpiar la sesión temporal
            if ($es_compra_directa) {
                unset($_SESSION['compra_directa']);
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje = 'Error procesando el pedido. Por favor intenta de nuevo.';
            error_log("Error en checkout: " . $e->getMessage());
        }
    } else {
        $mensaje = 'Por favor completa todos los campos obligatorios.';
    }
}

$page_title = 'Finalizar compra';

$extra_css = '<link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet" />' . "\n" .
    '<link rel="stylesheet" href="' . asset('css/index.css') . '">' . "\n" .
    '<link rel="stylesheet" href="' . asset('css/checkout.css') . '">';

include 'includes/header.php';

$mensaje_tipo = '';
if ($mensaje !== '') {
    $mensaje_tipo = $id_pedido ? 'success' : 'error';
}

$step_carrito = $carrito_vacio ? 'pending' : 'completed';
$step_datos = $carrito_vacio ? 'pending' : ($id_pedido ? 'completed' : 'active');
$step_pago = $carrito_vacio ? 'pending' : ($id_pedido ? 'active' : 'pending');
$step_confirm = 'pending';
?>

<main class="flex-1 bg-[#050505] text-white relative z-10">
    <section class="checkout-header">
        <div class="container mx-auto px-4">
            <h1 class="checkout-title animate-slide-up">Finalizar compra</h1>
            <p class="checkout-subtitle animate-slide-up delay-100">Confirma tus datos y finaliza el pago con seguridad.
            </p>

        </div>
    </section>

    <section class="checkout-container">
        <div class="checkout-progress animate-slide-up delay-100">
            <div class="checkout-progress-title">Progreso de compra</div>
            <div class="checkout-progress-steps">
                <div class="checkout-progress-step <?php echo $step_carrito; ?>">
                    <div class="checkout-progress-number <?php echo $step_carrito; ?>">1</div>
                    <div class="checkout-progress-label">Carrito</div>
                </div>
                <div class="checkout-progress-step <?php echo $step_datos; ?>">
                    <div class="checkout-progress-number <?php echo $step_datos; ?>">2</div>
                    <div class="checkout-progress-label">Datos</div>
                </div>
                <div class="checkout-progress-step <?php echo $step_pago; ?>">
                    <div class="checkout-progress-number <?php echo $step_pago; ?>">3</div>
                    <div class="checkout-progress-label">Pago</div>
                </div>
                <div class="checkout-progress-step <?php echo $step_confirm; ?>">
                    <div class="checkout-progress-number <?php echo $step_confirm; ?>">4</div>
                    <div class="checkout-progress-label">Confirmación</div>
                </div>
            </div>
        </div>
        <?php if ($mensaje): ?>
            <div class="checkout-message <?php echo $mensaje_tipo; ?> animate-slide-up">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <?php if ($carrito_vacio): ?>
            <div class="checkout-form animate-slide-up">
                <div class="text-center text-gray-300">Tu carrito está vacío.</div>
                <div class="checkout-actions mt-6">
                    <a href="productos.php" class="checkout-btn primary">Explorar productos</a>
                </div>
            </div>
        <?php elseif ($id_pedido): ?>
            <div class="checkout-form animate-slide-up">
                <div class="checkout-section">
                    <h3 class="checkout-form-title">Resumen del pedido</h3>

                    <div class="space-y-4">
                        <?php foreach ($items_detalle as $item): ?>
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="font-semibold"><?php echo htmlspecialchars($item['nombre']); ?></span>
                                    <span class="text-sm text-gray-400 ml-2">x<?php echo $item['cantidad']; ?></span>
                                </div>
                                <span
                                    class="text-red-500 font-bold">$<?php echo number_format($item['precio'] * $item['cantidad'], 0, ',', '.'); ?>
                                    COP</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="checkout-section">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400">Total a pagar:</span>
                            <span class="text-2xl font-bold text-red-500">$<?php echo number_format($total, 0, ',', '.'); ?>
                                COP</span>
                        </div>
                        <?php if ($combo_discount > 0): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400">Descuento combo</span>
                                <span class="text-green-500 font-bold">-
                                    $<?php echo number_format($combo_discount, 0, ',', '.'); ?> COP</span>
                            </div>
                        <?php endif; ?>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400">Método de pago:</span>
                            <span class="text-white">PayPal</span>
                        </div>
                    </div>

                    <div class="checkout-sla">
                        <div class="checkout-sla-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path
                                    d="M3 3a1 1 0 011-1h9a1 1 0 011 1v3h1a1 1 0 01.894.553l2 4A1 1 0 0116 11h-1v3a1 1 0 01-1 1H3a1 1 0 01-1-1V3z" />
                            </svg>
                            <span>Entrega estimada</span>
                        </div>
                        <?php
                        $hoy = new DateTime('now', new DateTimeZone('America/Bogota'));
                        $min = clone $hoy;
                        $min->modify('+2 days');
                        $max = clone $hoy;
                        $max->modify('+5 days');
                        ?>
                        <p>Entre <?php echo $min->format('d/m'); ?> y <?php echo $max->format('d/m'); ?>.</p>
                        <p class="note">Garantía de entrega: si no llega antes de <?php echo $max->format('d/m'); ?>, te
                            damos un cupón del 10%.</p>
                    </div>
                </div>

                <div class="checkout-section">
                    <div id="paypal-button-container"></div>
                    <?php if (($paypal_config['environment'] ?? 'sandbox') === 'sandbox'): ?>
                        <p class="mt-3 text-sm text-gray-400">
                            Nota sandbox: inicia sesión con una <span class="font-semibold">cuenta de comprador
                                (Personal)</span> distinta a la del vendedor.
                            Si ves el mensaje "Estás iniciando sesión en la cuenta del vendedor…", cierra sesión y usa las
                            credenciales del comprador de <span class="font-semibold">PayPal Sandbox</span>.
                        </p>
                    <?php endif; ?>
                    <div class="checkout-security">
                        <div class="checkout-security-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                <path d="M9 12l2 2 4-4" />
                            </svg>
                        </div>
                        <div class="checkout-security-text">Transacción PayPal verificada y protegida.</div>
                    </div>
                </div>
            </div>

            <script
                src="https://www.paypal.com/sdk/js?client-id=<?php echo urlencode($paypal_config['client_id']); ?>&currency=<?php echo htmlspecialchars($paypal_config['currency'] ?? 'USD'); ?>&components=buttons"></script>
            <script>
                paypal.Buttons({
                    createOrder: function (data, actions) {
                        return fetch('paypal_create_order.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ pedido_id: <?php echo (int) $id_pedido; ?> })
                        })
                            .then(function (res) {
                                return res.json().then(function (data) {
                                    if (!res.ok || !data.id) {
                                        var msg = (data && data.error) ? data.error : 'Error creando la orden.';
                                        throw new Error(msg);
                                    }
                                    return data.id;
                                });
                            });
                    },
                    onApprove: function (data, actions) {
                        return fetch('paypal_capture_order.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ orderID: data.orderID, pedido_id: <?php echo (int) $id_pedido; ?> })
                        })
                            .then(function (res) {
                                return res.json().then(function (result) {
                                    if (!res.ok) {
                                        var msg2 = (result && result.error) ? result.error : 'Error capturando el pago.';
                                        throw new Error(msg2);
                                    }
                                    if (result.status === 'COMPLETED') {
                                        window.location.href = 'paypal_response.php?status=' + encodeURIComponent(result.status) + '&pedido_id=<?php echo (int) $id_pedido; ?>';
                                    } else {
                                        alert('Pago no completado. Estado: ' + (result.status || 'desconocido'));
                                    }
                                });
                            });
                    },
                    onError: function (err) {
                        console.error('Error con PayPal:', err);
                        alert('Ocurrió un error procesando el pago con PayPal: ' + (err && err.message ? err.message : 'ver consola'));
                    }
                }).render('#paypal-button-container');
            </script>
        <?php else: ?>
            <div class="checkout-layout">
                <div class="checkout-form animate-slide-up">
                    <form method="post" class="space-y-6">
                        <div class="checkout-form-section">
                            <h3 class="checkout-form-subtitle">Datos de contacto</h3>
                            <div class="checkout-form-grid">
                                <div class="checkout-form-group">
                                    <label class="checkout-form-label">Nombre completo *</label>
                                    <input type="text" name="nombre" class="checkout-form-input"
                                        value="<?php echo e($nombre); ?>" required>
                                </div>
                                <div class="checkout-form-group">
                                    <label class="checkout-form-label">Correo electrónico *</label>
                                    <input type="email" name="email" class="checkout-form-input"
                                        value="<?php echo e($email); ?>" required>
                                </div>
                                <div class="checkout-form-group">
                                    <label class="checkout-form-label">Teléfono</label>
                                    <input type="text" name="telefono" class="checkout-form-input"
                                        value="<?php echo e($telefono); ?>">
                                </div>
                                <div class="checkout-form-group full-width">
                                    <label class="checkout-form-label">Dirección de envío *</label>
                                    <input type="text" name="direccion" class="checkout-form-input"
                                        value="<?php echo e($direccion); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="checkout-actions">
                            <button type="submit" class="checkout-btn primary">
                                Registrar pedido y pagar con PayPal
                            </button>
                        </div>
                        <div class="checkout-security">
                            <div class="checkout-security-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                    <path d="M9 12l2 2 4-4" />
                                </svg>
                            </div>
                            <div class="checkout-security-text">Pago protegido con cifrado SSL. Tus datos viajan seguros.
                            </div>
                        </div>
                    </form>
                </div>

                <aside class="checkout-summary animate-slide-up delay-100">
                    <h3 class="checkout-summary-title">Resumen del pedido</h3>

                    <div class="checkout-summary-items">
                        <?php foreach ($items_detalle as $item): ?>
                            <div class="checkout-summary-item">
                                <span>
                                    <?php echo htmlspecialchars($item['nombre']); ?>
                                    <span class="text-xs text-gray-500 ml-1">x<?php echo $item['cantidad']; ?></span>
                                </span>
                                <span
                                    class="text-red-500 font-semibold">$<?php echo number_format($item['precio'] * $item['cantidad'], 0, ',', '.'); ?>
                                    COP</span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($combo_discount > 0): ?>
                            <div class="checkout-summary-item">
                                <span>Descuento combo</span>
                                <span class="text-green-500 font-semibold">-
                                    $<?php echo number_format($combo_discount, 0, ',', '.'); ?> COP</span>
                            </div>
                        <?php endif; ?>
                        <div class="checkout-summary-item total">
                            <span>Total</span>
                            <span>$<?php echo number_format($total, 0, ',', '.'); ?> COP</span>
                        </div>
                    </div>

                    <div class="checkout-sla">
                        <div class="checkout-sla-title">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500" viewBox="0 0 20 20"
                                fill="currentColor">
                                <path
                                    d="M3 3a1 1 0 011-1h9a1 1 0 011 1v3h1a1 1 0 01.894.553l2 4A1 1 0 0116 11h-1v3a1 1 0 01-1 1H3a1 1 0 01-1-1V3z" />
                            </svg>
                            <span>Entrega estimada</span>
                        </div>
                        <?php
                        $hoy2 = new DateTime('now', new DateTimeZone('America/Bogota'));
                        $min2 = clone $hoy2;
                        $min2->modify('+2 days');
                        $max2 = clone $hoy2;
                        $max2->modify('+5 days');
                        ?>
                        <p>Entre <span id="sla-min"><?php echo $min2->format('d/m'); ?></span> y <span
                                id="sla-max"><?php echo $max2->format('d/m'); ?></span>.</p>
                        <p class="note">Garantía de entrega: si no llega antes de <span
                                id="sla-deadline"><?php echo $max2->format('d/m'); ?></span>, te damos un cupón del 10%.</p>
                        <p class="note" id="sla-city-note">Si estás en Bogotá: 1-2 días; otras ciudades: 3-5 días.</p>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php if ($es_compra_directa && !$id_pedido): ?>
<script>
// Limpiar compra directa si el usuario sale sin completar
window.addEventListener('beforeunload', function () {
    navigator.sendBeacon('limpiar_compra_directa.php');
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
</body>

</html>