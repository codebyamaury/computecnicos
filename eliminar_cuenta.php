<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/app/Core/bootstrap.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: index.php?login=1');
    exit;
}

$usuario_id = $_SESSION['usuario']['id'];
$password = $_POST['password'] ?? '';

if (!$password) {
    header('Location: perfil.php?edit=personal&error=1');
    exit;
}

// Obtener el hash de la contraseña
$stmt = $pdo->prepare('SELECT password FROM usuarios WHERE id = ?');
$stmt->execute([$usuario_id]);
$hash = $stmt->fetchColumn();

if (!$hash || !password_verify($password, $hash)) {
    // Contraseña incorrecta
    header('Location: perfil.php?edit=personal&error=1');
    exit;
}

// Eliminar todos los tokens Remember Me del usuario
$rememberMe->invalidateAllTokens($usuario_id);

// Eliminar usuario
$stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
$stmt->execute([$usuario_id]);

// Cerrar sesión
session_unset();
session_destroy();

header('Location: index.php?cuenta_eliminada=1');
exit;