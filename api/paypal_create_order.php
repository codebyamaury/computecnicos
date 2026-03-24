<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/../app/Core/bootstrap.php';
require_once __DIR__ . '/../app/Core/PaypalHelper.php';

header('Content-Type: application/json');
try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['pedido_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'pedido_id requerido']);
        exit;
    }
    $paypal = new PaypalHelper($pdo);
    $order = $paypal->createOrder((int)$input['pedido_id']);
    echo json_encode($order);
} catch (Throwable $e) {
    error_log('paypal_create_order: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo crear la orden: ' . $e->getMessage()]);
}