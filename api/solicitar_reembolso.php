<?php
/**
 * API: Solicitar reembolso (Cliente)
 * POST: id_pedido, motivo
 */
require_once __DIR__ . '/../app/Core/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['ok' => false, 'msg' => 'Debes iniciar sesión']);
    exit;
}

$id_usuario = $_SESSION['usuario']['id'];
$id_pedido = intval($_POST['id_pedido'] ?? 0);
$motivo = trim($_POST['motivo'] ?? '');

if ($id_pedido <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Pedido inválido']);
    exit;
}

if (strlen($motivo) < 10) {
    echo json_encode(['ok' => false, 'msg' => 'El motivo debe tener al menos 10 caracteres']);
    exit;
}

if (strlen($motivo) > 500) {
    echo json_encode(['ok' => false, 'msg' => 'El motivo no puede superar 500 caracteres']);
    exit;
}

// Verify the order belongs to this user and is eligible for refund
$stmt = $pdo->prepare('SELECT id, id_usuario, estado, total, fecha FROM pedidos WHERE id = ? AND id_usuario = ?');
$stmt->execute([$id_pedido, $id_usuario]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    echo json_encode(['ok' => false, 'msg' => 'Pedido no encontrado']);
    exit;
}

// Only allow refunds for paid/shipped/delivered orders (not pending or already cancelled)
$estados_elegibles = ['pagado', 'preparacion', 'enviado', 'entregado'];
if (!in_array($pedido['estado'], $estados_elegibles)) {
    echo json_encode(['ok' => false, 'msg' => 'Este pedido no es elegible para reembolso. Solo se pueden reembolsar pedidos pagados, en preparación, enviados o entregados.']);
    exit;
}

// Check if delivery was more than 30 days ago (for entregado)
if ($pedido['estado'] === 'entregado') {
    $stmtEntrega = $pdo->prepare("SELECT fecha FROM pedido_estados WHERE id_pedido = ? AND estado = 'entregado' ORDER BY fecha DESC LIMIT 1");
    $stmtEntrega->execute([$id_pedido]);
    $fechaEntrega = $stmtEntrega->fetchColumn();
    if ($fechaEntrega) {
        $diasDesdeEntrega = (time() - strtotime($fechaEntrega)) / 86400;
        if ($diasDesdeEntrega > 30) {
            echo json_encode(['ok' => false, 'msg' => 'Han pasado más de 30 días desde la entrega. No es posible solicitar reembolso.']);
            exit;
        }
    }
}

// Check if there's already a pending refund for this order
$stmtCheck = $pdo->prepare("SELECT id, estado FROM reembolsos WHERE id_pedido = ? AND estado IN ('solicitado', 'aprobado')");
$stmtCheck->execute([$id_pedido]);
$existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if ($existente) {
    echo json_encode(['ok' => false, 'msg' => 'Ya existe una solicitud de reembolso pendiente para este pedido.']);
    exit;
}

// Check if already refunded
$stmtRefunded = $pdo->prepare("SELECT id FROM reembolsos WHERE id_pedido = ? AND estado = 'procesado'");
$stmtRefunded->execute([$id_pedido]);
if ($stmtRefunded->fetch()) {
    echo json_encode(['ok' => false, 'msg' => 'Este pedido ya fue reembolsado anteriormente.']);
    exit;
}

// Create refund request
try {
    $stmt = $pdo->prepare('INSERT INTO reembolsos (id_pedido, id_usuario, motivo, monto, estado) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$id_pedido, $id_usuario, $motivo, $pedido['total'], 'solicitado']);

    // Add to order history
    $pdo->prepare('INSERT INTO pedido_estados (id_pedido, estado, comentario) VALUES (?, ?, ?)')
        ->execute([$id_pedido, $pedido['estado'], 'Solicitud de reembolso enviada por el cliente']);

    echo json_encode([
        'ok' => true,
        'msg' => 'Tu solicitud de reembolso ha sido enviada correctamente. Nuestro equipo la revisará en las próximas 24-48 horas.'
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error al procesar la solicitud. Intenta de nuevo.']);
}
