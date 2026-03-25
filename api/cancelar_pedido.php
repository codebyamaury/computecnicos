<?php
// Cancelar un pedido pendiente (no pagado) del usuario actual
require_once __DIR__ . '/../app/Core/bootstrap.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autenticado.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pedido_id = intval($input['pedido_id'] ?? 0);

if ($pedido_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'ID de pedido inválido.']);
    exit;
}

try {
    $id_usuario = $_SESSION['usuario']['id'];

    // Verificar que el pedido pertenece al usuario y está pendiente
    $stmt = $pdo->prepare("SELECT id, estado FROM pedidos WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$pedido_id, $id_usuario]);
    $pedido = $stmt->fetch();

    if (!$pedido) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'msg' => 'Pedido no encontrado.']);
        exit;
    }

    if ($pedido['estado'] !== 'pendiente') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Solo se pueden cancelar pedidos pendientes de pago.']);
        exit;
    }

    $pdo->beginTransaction();

    // Cambiar estado a cancelado
    $stmt = $pdo->prepare("UPDATE pedidos SET estado = 'cancelado' WHERE id = ?");
    $stmt->execute([$pedido_id]);

    // Registrar en historial
    $stmt = $pdo->prepare("INSERT INTO pedido_estados (id_pedido, estado, comentario) VALUES (?, 'cancelado', 'Cancelado por el usuario antes de pagar')");
    $stmt->execute([$pedido_id]);

    $pdo->commit();

    echo json_encode(['ok' => true, 'msg' => 'Pedido cancelado correctamente.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error cancelando pedido: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al cancelar el pedido.']);
}
