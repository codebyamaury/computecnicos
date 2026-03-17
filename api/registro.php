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
$password = $_POST['password'] ?? '';
$direccion = trim($_POST['direccion'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$foto = $_FILES['foto'] ?? null;

if (!$nombre || !$email || !$password || !$direccion || !$telefono) {
    respuesta(false, 'Todos los campos son obligatorios.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respuesta(false, 'Correo electrónico inválido.');
}
if (strlen($password) < 6) {
    respuesta(false, 'La contraseña debe tener al menos 6 caracteres.');
}

// Verificar que el email fue verificado con código
if (!isset($_SESSION['reg_email_verified']) 
    || strtolower($_SESSION['reg_email_verified']['email']) !== strtolower($email)
    || $_SESSION['reg_email_verified']['verificado'] !== true) {
    respuesta(false, 'Debes verificar tu correo electrónico con el código enviado antes de registrarte.');
}
// Verificar que la verificación no haya expirado (30 minutos de gracia)
if ((time() - ($_SESSION['reg_email_verified']['timestamp'] ?? 0)) > 1800) {
    unset($_SESSION['reg_email_verified']);
    respuesta(false, 'La verificación ha expirado. Solicita un nuevo código.');
}

// Verificar si el correo ya existe
$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    respuesta(false, 'El correo ya está registrado.');
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$foto_url = null;
if ($foto && $foto['tmp_name']) {
    $ext = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));
    $permitidas = ['jpg','jpeg','png','webp'];
    if (!in_array($ext, $permitidas)) {
        respuesta(false, 'Formato de imagen no permitido.');
    }
    if ($foto['size'] > 2*1024*1024) {
        respuesta(false, 'La imagen no debe superar 2MB.');
    }
    $nombre_archivo = uniqid('profile_') . '.' . $ext;
    $destino = 'uploads/profiles/' . $nombre_archivo;
    if (!move_uploaded_file($foto['tmp_name'], $destino)) {
        respuesta(false, 'Error al subir la foto de perfil.');
    }
    $foto_url = $destino;
}

try {
    $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, email, telefono, direccion, password, foto) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$nombre, $email, $telefono, $direccion, $hash, $foto_url]);
    // Iniciar sesión automáticamente
// Sesión manejada por bootstrap (DB handler)
    $stmt2 = $pdo->prepare('SELECT id, nombre, email, rol, foto FROM usuarios WHERE email = ?');
    $stmt2->execute([$email]);
    $usuario = $stmt2->fetch();
    $_SESSION['usuario'] = [
        'id' => $usuario['id'],
        'nombre' => $usuario['nombre'],
        'email' => $usuario['email'],
        'rol' => $usuario['rol'],
        'foto' => $usuario['foto']
    ];
    
    // Crear token persistente para mantener sesión activa (30 días)
    $rememberMe->createToken($usuario['id']);
    
    respuesta(true, 'Registro exitoso. Redirigiendo...');
} catch (Exception $e) {
    respuesta(false, 'Error al registrar usuario: ' . $e->getMessage());
}