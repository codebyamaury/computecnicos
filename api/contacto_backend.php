<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../app/Core/bootstrap.php';

function respuesta($ok, $msg) {
    echo json_encode(['ok' => $ok, 'msg' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuesta(false, 'Método no permitido.');
}

$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$mensaje = trim($_POST['mensaje'] ?? '');

if (!$nombre || !$email || !$mensaje) {
    respuesta(false, 'Todos los campos son obligatorios.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respuesta(false, 'Correo electrónico inválido.');
}

if (strlen($mensaje) < 10) {
    respuesta(false, 'El mensaje debe tener al menos 10 caracteres.');
}

try {
    $stmt = $pdo->prepare('INSERT INTO contactos (nombre, email, mensaje) VALUES (?, ?, ?)');
    $stmt->execute([$nombre, $email, $mensaje]);
    respuesta(true, 'Mensaje enviado exitosamente. Te responderemos pronto.');
} catch (Exception $e) {
    respuesta(false, 'Error al enviar el mensaje: ' . $e->getMessage());
}