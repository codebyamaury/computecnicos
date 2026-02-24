<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    http_response_code(403);
    exit('Acceso denegado');
}

require_once __DIR__ . '/../app/Core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id_movimiento = intval($_POST['id']);
    
    try {
        $pdo->beginTransaction();
        
        // Obtener información del movimiento antes de eliminarlo
        $stmt = $pdo->prepare('SELECT id_producto, tipo, cantidad, soporte_documental FROM movimientos_inventario WHERE id = ?');
        $stmt->execute([$id_movimiento]);
        $movimiento = $stmt->fetch();
        
        if ($movimiento) {
            // Eliminar el archivo de soporte si existe
            if ($movimiento['soporte_documental'] && file_exists('../' . $movimiento['soporte_documental'])) {
                unlink('../' . $movimiento['soporte_documental']);
            }
            
            // Revertir el stock si es necesario
            if ($movimiento['tipo'] === 'entrada') {
                $pdo->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?')->execute([$movimiento['cantidad'], $movimiento['id_producto']]);
            } elseif ($movimiento['tipo'] === 'salida') {
                $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?')->execute([$movimiento['cantidad'], $movimiento['id_producto']]);
            }
            
            // Eliminar el movimiento
            $pdo->prepare('DELETE FROM movimientos_inventario WHERE id = ?')->execute([$id_movimiento]);
            
            $pdo->commit();
            echo 'success';
        } else {
            throw new Exception('Movimiento no encontrado.');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo 'Error: ' . $e->getMessage();
    }
} else {
    http_response_code(400);
    echo 'Solicitud inválida';
}
?>