<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/app/Core/bootstrap.php';

// Utilidad: detectar si la petición espera JSON (fetch/AJAX)
function expects_json() {
    $xrw = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $accept = isset($_SERVER['HTTP_ACCEPT']) ? strtolower($_SERVER['HTTP_ACCEPT']) : '';
    return $xrw || strpos($accept, 'application/json') !== false || strpos($accept, 'json') !== false;
}
function respond_json($payload) {
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (expects_json()) return respond_json(['ok' => false, 'msg' => 'Método inválido']);
    header('Location: productos.php');
    exit;
}

$id_producto = intval($_POST['id_producto'] ?? 0);
$email = trim($_POST['email'] ?? '');
$id_usuario = isset($_SESSION['usuario']['id']) ? intval($_SESSION['usuario']['id']) : null;

if ($id_producto <= 0) {
    if (expects_json()) return respond_json(['ok' => false, 'msg' => 'Producto inválido']);
    header('Location: productos.php');
    exit;
}

// Validar producto
$stmtP = $pdo->prepare('SELECT id, nombre FROM productos WHERE id = ?');
$stmtP->execute([$id_producto]);
$prod = $stmtP->fetch();
if (!$prod) {
    if (expects_json()) return respond_json(['ok' => false, 'msg' => 'Producto no encontrado']);
    header('Location: productos.php');
    exit;
}

// Crear tabla si no existe
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS alertas_stock (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_producto INT NOT NULL,
        id_usuario INT NULL,
        email VARCHAR(255) NULL,
        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_alerta (id_producto, COALESCE(id_usuario, 0), COALESCE(email, ""))
    )');
} catch (Exception $e) { /* continuar */ }

if (!$id_usuario && empty($email)) {
    if (expects_json()) return respond_json(['ok' => false, 'msg' => 'Proporciona un correo o inicia sesión']);
    $_SESSION['mensaje'] = ['tipo' => 'error', 'texto' => 'Proporciona un correo o inicia sesión'];
    header('Location: producto.php?id=' . $id_producto);
    exit;
}

// Evitar duplicados
$stmtChk = $pdo->prepare('SELECT id FROM alertas_stock WHERE id_producto = ? AND (
    (id_usuario IS NOT NULL AND id_usuario = ?) OR
    (? IS NOT NULL AND email = ?)
) LIMIT 1');
$stmtChk->execute([$id_producto, $id_usuario, $email ?: null, $email ?: null]);
$existe = $stmtChk->fetch();

if ($existe) {
    if (expects_json()) return respond_json(['ok' => true, 'msg' => 'Ya estabas suscrito']);
    $_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Ya estabas suscrito'];
    header('Location: producto.php?id=' . $id_producto);
    exit;
}

$stmtIns = $pdo->prepare('INSERT INTO alertas_stock (id_producto, id_usuario, email) VALUES (?, ?, ?)');
$stmtIns->execute([$id_producto, $id_usuario, $email ?: null]);

if (expects_json()) return respond_json(['ok' => true, 'msg' => 'Te avisaremos cuando haya stock']);
$_SESSION['mensaje'] = ['tipo' => 'success', 'texto' => 'Te avisaremos cuando haya stock'];
header('Location: producto.php?id=' . $id_producto);
exit;
?>