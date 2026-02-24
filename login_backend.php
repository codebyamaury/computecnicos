<?php
// No session_start here, as bootstrap.php handles it safely
require_once __DIR__ . '/app/Core/bootstrap.php';
header('Content-Type: application/json');

// Regenerate session ID for security
session_regenerate_id(true);

function respuesta($ok, $msg) {
    echo json_encode(['ok' => $ok, 'msg' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuesta(false, 'Método no permitido.');
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    respuesta(false, 'Correo y contraseña son obligatorios.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respuesta(false, 'Correo electrónico inválido.');
}

try {
    $stmt = $pdo->prepare('SELECT id, nombre, email, password, rol, foto FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if (!$usuario || !password_verify($password, $usuario['password'])) {
        respuesta(false, 'Correo o contraseña incorrectos.');
    }
    
    $_SESSION['usuario'] = [
        'id' => $usuario['id'],
        'nombre' => $usuario['nombre'],
        'email' => $usuario['email'],
        'rol' => $usuario['rol'],
        'foto' => $usuario['foto']
    ];
    // Mensaje de éxito para mostrar toast en la página principal
    $_SESSION['login_success'] = '¡Bienvenido, ' . ($usuario['nombre'] ?? 'usuario') . '! Has iniciado sesión correctamente.';
    
    respuesta(true, 'Inicio de sesión exitoso.');
    
} catch (Exception $e) {
    respuesta(false, 'Error al iniciar sesión: ' . $e->getMessage());
}