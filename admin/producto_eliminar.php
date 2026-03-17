<?php
// Bootstrap PRIMERO: inicia sesión desde la BD antes de verificar permisos
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // Obtener imagen para eliminar archivo
    $stmt = $pdo->prepare('SELECT imagen FROM productos WHERE id = ?');
    $stmt->execute([$id]);
    $producto = $stmt->fetch();
    
    if ($producto && $producto['imagen'] && file_exists('../' . $producto['imagen'])) {
        @unlink('../' . $producto['imagen']);
    }
    
    // Eliminar producto (la base de datos maneja las restricciones automáticamente)
    $stmt = $pdo->prepare('DELETE FROM productos WHERE id = ?');
    $stmt->execute([$id]);
}

header('Location: productos.php?eliminado=1');
exit;