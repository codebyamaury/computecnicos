<?php
// Sesión manejada por bootstrap (DB handler)
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
require_once __DIR__ . '/../app/Core/bootstrap.php';

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare('DELETE FROM proveedores WHERE id = ?');
    $stmt->execute([$id]);
}
header('Location: proveedores.php?eliminado=1');
exit;