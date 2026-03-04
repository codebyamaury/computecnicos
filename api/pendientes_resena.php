<?php
/**
 * API — Productos pendientes de reseña
 * GET /api/pendientes_resena.php
 * 
 * Devuelve productos entregados que el usuario aún no ha reseñado.
 */

require_once __DIR__ . '/../app/Core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario'])) {
    echo json_encode(['ok' => false, 'pendientes' => []]);
    exit;
}

$userId = intval($_SESSION['usuario']['id']);

try {
    // Productos entregados que NO tienen reseña del usuario
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id, p.nombre, p.imagen, p.precio,
               ped.fecha AS fecha_entrega
        FROM pedidos ped
        INNER JOIN detalle_pedido dp ON dp.id_pedido = ped.id
        INNER JOIN productos p ON dp.id_producto = p.id
        LEFT JOIN resenas r ON r.id_producto = p.id AND r.id_usuario = ?
        WHERE ped.id_usuario = ?
          AND ped.estado = 'entregado'
          AND r.id IS NULL
        ORDER BY ped.fecha DESC
        LIMIT 5
    ");
    $stmt->execute([$userId, $userId]);
    $pendientes = $stmt->fetchAll();

    echo json_encode([
        'ok' => true,
        'pendientes' => $pendientes
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'pendientes' => [], 'error' => $e->getMessage()]);
}
