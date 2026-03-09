<?php
// Depuración para ver el error 500 en pantalla:
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Bootstrap PRIMERO: inicia sesión desde la BD antes de verificar permisos
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    try {
        // Obtener estado y detalles antes de eliminar
        $stmt = $pdo->prepare('SELECT estado FROM pedidos WHERE id = ?');
        $stmt->execute([$id]);
        $estado = $stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT * FROM detalle_pedido WHERE id_pedido = ?');
        $stmt->execute([$id]);
        $detalles = $stmt->fetchAll();
        $id_admin = $_SESSION['usuario']['id'];
        
        // Si el pedido estaba pagado/entregado, reponer stock y registrar entrada
        if ($estado && in_array($estado, ['pagado','entregado'])) {
            foreach ($detalles as $d) {
                $pdo->prepare('INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, motivo, id_usuario) VALUES (?, "entrada", ?, ?, ?)')
                    ->execute([$d['id_producto'], $d['cantidad'], 'Eliminación Pedido #' . $id, $id_admin]);
                $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?')
                    ->execute([$d['cantidad'], $d['id_producto']]);
            }
        }
        
        // Eliminar dependencias primero
        $pdo->prepare('DELETE FROM pedido_estados WHERE id_pedido = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM facturas_electronicas WHERE id_pedido = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM detalle_pedido WHERE id_pedido = ?')->execute([$id]);
        
        // Eliminar notas de crédito si hay (prevención extra)
        $pdo->prepare('DELETE FROM notas_credito WHERE id_pedido = ?')->execute([$id]);
        
        // Eliminar pedido
        $pdo->prepare('DELETE FROM pedidos WHERE id = ?')->execute([$id]);
        
    } catch (Throwable $e) {
        die("<h1>Error crítico al eliminar pedido #$id</h1><p><b>Mensaje:</b> " . htmlspecialchars($e->getMessage()) . "</p><p><b>Archivo:</b> " . $e->getFile() . " en linea " . $e->getLine() . "</p><a href='pedidos.php'>Volver</a>");
    }
}
header('Location: pedidos.php?eliminado=1');
exit;
