<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/../app/Core/bootstrap.php';
require_once __DIR__ . '/../includes/PaypalHelper.php';

header('Content-Type: application/json');
try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['orderID'], $input['pedido_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'orderID y pedido_id requeridos']);
        exit;
    }
    $paypal = new PaypalHelper($pdo);
    $result = $paypal->captureOrder($input['orderID'], (int)$input['pedido_id']);
    // Si el pago fue completado, vaciar el carrito
    if (isset($result['status']) && $result['status'] === 'COMPLETED') {
        $_SESSION['carrito'] = [];
        // Mensaje de éxito para mostrar en el header/toast si aplica
        $_SESSION['checkout_success'] = 'Pago completado. Carrito vaciado.';
    }
    echo json_encode($result);
} catch (Exception $e) {
    error_log('paypal_capture_order: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo capturar la orden']);
}