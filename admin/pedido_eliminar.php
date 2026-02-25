<?php
// Bootstrap PRIMERO: inicia sesión desde la BD antes de verificar permisos
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    // Obtener estado y detalles antes de eliminar
    $stmt = $pdo->prepare('SELECT estado FROM pedidos WHERE id = ?');
    $stmt->execute([$id]);
    $estado = $stmt->fetchColumn();
    $stmt = $pdo->prepare('SELECT * FROM detalle_pedido WHERE id_pedido = ?');
    $stmt->execute([$id]);
    $detalles = $stmt->fetchAll();
    $id_admin = $_SESSION['usuario']['id'];
    // Si el pedido estaba pagado/entregado, reponer stock y registrar entrada
    if (in_array($estado, ['pagado','entregado'])) {
        foreach ($detalles as $d) {
            $pdo->prepare('INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, motivo, id_usuario) VALUES (?, "entrada", ?, ?, ?)')
                ->execute([$d['id_producto'], $d['cantidad'], 'Eliminación Pedido #' . $id, $id_admin]);
            $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?')
                ->execute([$d['cantidad'], $d['id_producto']]);
        }
    }
    // Eliminar detalles primero
    $stmt = $pdo->prepare('DELETE FROM detalle_pedido WHERE id_pedido = ?');
    $stmt->execute([$id]);
    // Eliminar pedido
    $stmt = $pdo->prepare('DELETE FROM pedidos WHERE id = ?');
    $stmt->execute([$id]);
}
header('Location: pedidos.php?eliminado=1');
exit;