<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/app/Core/bootstrap.php';

// Utilidad: detectar si la petición espera JSON (fetch/AJAX)
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

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (expects_json()) {
        respond_json(['ok' => false, 'msg' => 'Método inválido', 'total' => null]);
    }
    header('Location: productos.php');
    exit;
}

$id_producto = intval($_POST['id_producto'] ?? 0);
$cantidad = max(1, intval($_POST['cantidad'] ?? 1));

if (!$id_producto) {
    $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => 'ID de producto inválido.'];
    if (expects_json()) {
        respond_json(['ok' => false, 'msg' => 'ID de producto inválido.', 'total' => null]);
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'productos.php'));
    exit;
}

// Verificar que el producto existe
$stmt = $pdo->prepare('SELECT id, nombre, precio, stock FROM productos WHERE id = ?');
$stmt->execute([$id_producto]);
$producto = $stmt->fetch();

if (!$producto) {
    $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => 'Producto no encontrado.'];
    if (expects_json()) {
        respond_json(['ok' => false, 'msg' => 'Producto no encontrado.', 'total' => null]);
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'productos.php'));
    exit;
}

// Solo bloquear si el producto está completamente agotado (stock 0)
// El stock se reduce únicamente al completar la compra, no al agregar al carrito
if ((int) $producto['stock'] <= 0) {
    $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => 'Este producto está agotado.'];
    if (expects_json()) {
        respond_json(['ok' => false, 'msg' => 'Este producto está agotado.', 'total' => null]);
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'productos.php'));
    exit;
}

// Inicializar carrito si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Buscar si ya está en el carrito
$encontrado = false;
$stock_limitado = false;
foreach ($_SESSION['carrito'] as &$item) {
    if ($item['id_producto'] == $id_producto) {
        $nueva_cantidad = $item['cantidad'] + $cantidad;
        // Limitar la cantidad en carrito al stock disponible real
        if ($nueva_cantidad > (int) $producto['stock']) {
            $nueva_cantidad = (int) $producto['stock'];
            $stock_limitado = true;
        }
        $item['cantidad'] = $nueva_cantidad;
        $encontrado = true;
        break;
    }
}
unset($item);

if (!$encontrado) {
    // Limitar cantidad inicial al stock disponible
    if ($cantidad > (int) $producto['stock']) {
        $cantidad = (int) $producto['stock'];
        $stock_limitado = true;
    }
    $_SESSION['carrito'][] = [
        'id_producto' => $id_producto,
        'cantidad' => $cantidad
    ];
}

// Calcular total de items en carrito
$total_items = 0;
foreach ($_SESSION['carrito'] as $i) {
    $total_items += (int) $i['cantidad'];
}

if ($stock_limitado) {
    $msg_stock = 'Has alcanzado el stock máximo disponible (' . (int) $producto['stock'] . ' unidades) de este producto.';
    $_SESSION['mensaje'] = ['tipo' => 'warning', 'texto' => $msg_stock];
    if (expects_json()) {
        respond_json(['ok' => true, 'msg' => $msg_stock, 'total' => $total_items, 'stock_limit' => true, 'max_stock' => (int) $producto['stock']]);
    }
} else {
    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Producto agregado al carrito correctamente.'];
    if (expects_json()) {
        respond_json(['ok' => true, 'msg' => 'Producto agregado al carrito correctamente.', 'total' => $total_items, 'stock_limit' => false]);
    }
}

// Determinar URL de redirección
$redirect_url = $_SERVER['HTTP_REFERER'] ?? 'productos.php';
// Si se especifica redirect (ej: "Comprar Ahora" envía redirect=checkout.php)
if (!empty($_POST['redirect'])) {
    $custom_redirect = $_POST['redirect'];
    // Solo permitir redirecciones relativas (seguridad: no URLs externas)
    if (strpos($custom_redirect, '://') === false && strpos($custom_redirect, '//') !== 0) {
        $redirect_url = $custom_redirect;
    }
}

header('Location: ' . $redirect_url);
exit;