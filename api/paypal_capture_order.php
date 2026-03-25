<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/../app/Core/bootstrap.php';
require_once __DIR__ . '/../app/Core/PaypalHelper.php';

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
    // Si el pago fue completado, vaciar el carrito y guardar capture_id
    if (isset($result['status']) && $result['status'] === 'COMPLETED') {
        $_SESSION['carrito'] = [];
        $_SESSION['checkout_success'] = 'Pago completado. Carrito vaciado.';
        // Guardar el capture_id de PayPal para futuros reembolsos
        $captureId = $result['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
        $orderId = $result['id'] ?? $input['orderID'];
        if ($captureId) {
            try {
                // Agregar columnas si no existen (compatible con MySQL < 8.0.29)
                try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN paypal_order_id VARCHAR(128) NULL"); } catch (Exception $e) {}
                try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN paypal_capture_id VARCHAR(128) NULL"); } catch (Exception $e) {}
                $pdo->prepare('UPDATE pedidos SET paypal_order_id = ?, paypal_capture_id = ? WHERE id = ?')
                    ->execute([$orderId, $captureId, (int)$input['pedido_id']]);
            } catch (Exception $e) {
                error_log('paypal_capture: Error guardando capture_id: ' . $e->getMessage());
            }
        }
    }
    echo json_encode($result);
} catch (Throwable $e) {
    error_log('paypal_capture_order: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo capturar la orden: ' . $e->getMessage()]);
}