<?php
// Sesión manejada por bootstrap (DB handler)
header('Content-Type: application/json');
require_once __DIR__ . '/app/Core/bootstrap.php';

function respuesta($ok, $msg)
{
    echo json_encode(['ok' => $ok, 'msg' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuesta(false, 'Método no permitido.');
}

$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$direccion = trim($_POST['direccion'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');

// Validación básica
if (!$nombre || !$email || !$password) {
    respuesta(false, 'Nombre, correo y contraseña son obligatorios.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respuesta(false, 'Correo electrónico inválido.');
}

if (strlen($password) < 6) {
    respuesta(false, 'La contraseña debe tener al menos 6 caracteres.');
}

try {
    // Verificar si el correo ya existe
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        respuesta(false, 'El correo electrónico ya está registrado.');
    }

    // Hash de contraseña
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Insertar usuario
    // Rol por defecto: 'cliente' (o lo que se use en la DB, asumiré 'cliente' o 'usuario')
    // Foto por defecto: null
    $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, email, password, direccion, telefono, rol, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$nombre, $email, $passwordHash, $direccion, $telefono, 'cliente']);

    $idUsuario = $pdo->lastInsertId();

    // Iniciar sesión automáticamente
    $_SESSION['usuario'] = [
        'id' => $idUsuario,
        'nombre' => $nombre,
        'email' => $email,
        'rol' => 'cliente',
        'foto' => null
    ];

    $_SESSION['login_success'] = '¡Registro exitoso! Bienvenido a Computécnicos.';

    // Crear token persistente para mantener sesion activa (30 dias)
    $rememberMe->createToken($idUsuario);

    respuesta(true, 'Registro exitoso.');

} catch (Exception $e) {
    respuesta(false, 'Error al registrar usuario: ' . $e->getMessage());
}
