<?php
/**
 * API: Procesar reembolso (Admin)
 * POST: id_reembolso, accion (aprobar|rechazar|procesar_paypal), nota_admin
 */
require_once __DIR__ . '/../app/Core/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    echo json_encode(['ok' => false, 'msg' => 'Acceso denegado']);
    exit;
}

$admin_id = $_SESSION['usuario']['id'];
$id_reembolso = intval($_POST['id_reembolso'] ?? 0);
$accion = trim($_POST['accion'] ?? '');
$nota_admin = trim($_POST['nota_admin'] ?? '');

if ($id_reembolso <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Reembolso inválido']);
    exit;
}

if (!in_array($accion, ['aprobar', 'rechazar', 'procesar_paypal'])) {
    echo json_encode(['ok' => false, 'msg' => 'Acción no válida']);
    exit;
}

// Get refund details
$stmt = $pdo->prepare('SELECT r.*, p.total as pedido_total, p.estado as pedido_estado FROM reembolsos r JOIN pedidos p ON r.id_pedido = p.id WHERE r.id = ?');
$stmt->execute([$id_reembolso]);
$reembolso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reembolso) {
    echo json_encode(['ok' => false, 'msg' => 'Reembolso no encontrado']);
    exit;
}

try {
    switch ($accion) {
        case 'aprobar':
            if ($reembolso['estado'] !== 'solicitado') {
                echo json_encode(['ok' => false, 'msg' => 'Solo se pueden aprobar reembolsos en estado "solicitado"']);
                exit;
            }

            $pdo->prepare('UPDATE reembolsos SET estado = ?, nota_admin = ?, fecha_resolucion = NOW(), id_admin_resolucion = ? WHERE id = ?')
                ->execute(['aprobado', $nota_admin ?: 'Aprobado por administrador', $admin_id, $id_reembolso]);

            // Add to order history
            $pdo->prepare('INSERT INTO pedido_estados (id_pedido, estado, comentario) VALUES (?, ?, ?)')
                ->execute([$reembolso['id_pedido'], $reembolso['pedido_estado'], 'Reembolso aprobado por administrador']);

            echo json_encode(['ok' => true, 'msg' => 'Reembolso aprobado correctamente. Proceda a ejecutar el desembolso.']);
            break;

        case 'rechazar':
            if (!in_array($reembolso['estado'], ['solicitado', 'aprobado'])) {
                echo json_encode(['ok' => false, 'msg' => 'Este reembolso no puede ser rechazado en su estado actual']);
                exit;
            }

            if (strlen($nota_admin) < 5) {
                echo json_encode(['ok' => false, 'msg' => 'Debe proporcionar un motivo para el rechazo (mínimo 5 caracteres)']);
                exit;
            }

            $pdo->prepare('UPDATE reembolsos SET estado = ?, nota_admin = ?, fecha_resolucion = NOW(), id_admin_resolucion = ? WHERE id = ?')
                ->execute(['rechazado', $nota_admin, $admin_id, $id_reembolso]);

            $pdo->prepare('INSERT INTO pedido_estados (id_pedido, estado, comentario) VALUES (?, ?, ?)')
                ->execute([$reembolso['id_pedido'], $reembolso['pedido_estado'], 'Reembolso rechazado: ' . $nota_admin]);

            echo json_encode(['ok' => true, 'msg' => 'Reembolso rechazado.']);
            break;

        case 'procesar_paypal':
            if ($reembolso['estado'] !== 'aprobado') {
                echo json_encode(['ok' => false, 'msg' => 'Solo se pueden procesar reembolsos aprobados']);
                exit;
            }

            // Try PayPal refund
            if ($reembolso['paypal_capture_id']) {
                require_once __DIR__ . '/../app/Core/PaypalHelper.php';
                $paypal = new PaypalHelper($pdo);
                $result = $paypal->processFullRefund($reembolso['id_pedido'], $id_reembolso, $admin_id);
                echo json_encode([
                    'ok' => true,
                    'msg' => 'Reembolso procesado exitosamente vía PayPal. ID: ' . ($result['refund_id'] ?? 'N/A'),
                    'refund_id' => $result['refund_id'] ?? null
                ]);
            } else {
                // Manual refund (no PayPal capture ID available — manual payment)
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE reembolsos SET estado = ?, nota_admin = ?, fecha_resolucion = NOW(), id_admin_resolucion = ? WHERE id = ?')
                        ->execute(['procesado', $nota_admin ?: 'Reembolso procesado manualmente', $admin_id, $id_reembolso]);

                    $pdo->prepare('UPDATE pedidos SET estado = ? WHERE id = ?')
                        ->execute(['cancelado', $reembolso['id_pedido']]);

                    $pdo->prepare('INSERT INTO pedido_estados (id_pedido, estado, comentario) VALUES (?, ?, ?)')
                        ->execute([$reembolso['id_pedido'], 'cancelado', 'Reembolso procesado manualmente por administrador']);

                    // Restore stock
                    $stmtDet = $pdo->prepare('SELECT id_producto, cantidad FROM detalle_pedido WHERE id_pedido = ?');
                    $stmtDet->execute([$reembolso['id_pedido']]);
                    $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($detalles as $d) {
                        $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?')
                            ->execute([$d['cantidad'], $d['id_producto']]);
                        $pdo->prepare("INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, motivo, id_usuario) VALUES (?, 'entrada', ?, ?, ?)")
                            ->execute([$d['id_producto'], $d['cantidad'], 'Reembolso manual Pedido #' . $reembolso['id_pedido'], $admin_id]);
                    }

                    $pdo->prepare('UPDATE reembolsos SET stock_devuelto = 1 WHERE id = ?')
                        ->execute([$id_reembolso]);

                    $pdo->commit();
                    echo json_encode(['ok' => true, 'msg' => 'Reembolso procesado manualmente. Stock restaurado.']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
            break;
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . $e->getMessage()]);
}
