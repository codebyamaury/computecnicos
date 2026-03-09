<?php
require_once __DIR__ . '/../app/Core/bootstrap.php';
header('Content-Type: application/json');

// Verificar permisos
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

try {
    // Buscar pedidos que ya están pagados pero no han sido notificados
    $stmt = $pdo->query("SELECT p.id, u.nombre, p.total FROM pedidos p JOIN usuarios u ON p.id_usuario = u.id WHERE p.estado = 'pagado' AND p.notificado_admin = 0 ORDER BY p.id ASC LIMIT 5");
    $nuevos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($nuevos)) {
        // Marcarlos como notificados
        $ids = array_column($nuevos, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        $update = $pdo->prepare("UPDATE pedidos SET notificado_admin = 1 WHERE id IN ($in)");
        $update->execute($ids);
    }

    echo json_encode(['nuevos' => $nuevos]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'nuevos' => []]);
}
