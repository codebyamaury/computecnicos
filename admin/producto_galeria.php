<?php
// Sesión manejada por bootstrap (DB handler)
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}
require_once __DIR__ . '/../app/Core/bootstrap.php';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode([]);
    exit;
}
$stmt = $pdo->prepare('SELECT id, url_imagen FROM imagenes_producto WHERE id_producto = ?');
$stmt->execute([$id]);
$imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($imagenes);