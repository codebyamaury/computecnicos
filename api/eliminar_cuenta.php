<?php
/**
 * API — Eliminar cuenta de usuario
 * POST /api/eliminar_cuenta.php
 * 
 * Verifica la contraseña y elimina la cuenta del usuario.
 * Responde en JSON para compatibilidad con el formulario AJAX del perfil.
 */
require_once __DIR__ . '/../app/Core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function respuesta($ok, $msg) {
    echo json_encode(['ok' => $ok, 'msg' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    respuesta(false, 'Método no permitido.');
}

if (!isset($_SESSION['usuario'])) {
    respuesta(false, 'Debes iniciar sesión para realizar esta acción.');
}

$usuario_id = $_SESSION['usuario']['id'];
$password = $_POST['password'] ?? '';

if (!$password) {
    respuesta(false, 'Debes ingresar tu contraseña para confirmar.');
}

// Obtener el hash de la contraseña
$stmt = $pdo->prepare('SELECT password FROM usuarios WHERE id = ?');
$stmt->execute([$usuario_id]);
$hash = $stmt->fetchColumn();

// Caso: usuario registrado con Google (no tiene contraseña)
if (!$hash || $hash === '' || $hash === null) {
    respuesta(false, 'Tu cuenta fue creada con Google. Para eliminarla, contacta a soporte: info@computecnicos.com');
}

if (!password_verify($password, $hash)) {
    respuesta(false, 'La contraseña es incorrecta. Intenta de nuevo.');
}

try {
    // Eliminar todos los tokens Remember Me del usuario
    $rememberMe->invalidateAllTokens($usuario_id);

    // Eliminar pedidos del usuario (opcional, depende de la política)
    // $pdo->prepare('DELETE FROM pedidos WHERE usuario_id = ?')->execute([$usuario_id]);

    // Eliminar usuario
    $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = ?');
    $stmt->execute([$usuario_id]);

    log_event("Cuenta eliminada: usuario_id=$usuario_id");

    // Cerrar sesión
    session_unset();
    session_destroy();

    respuesta(true, 'Tu cuenta ha sido eliminada correctamente. Serás redirigido...');
} catch (Exception $e) {
    log_event("Error eliminando cuenta usuario_id=$usuario_id: " . $e->getMessage());
    respuesta(false, 'Ocurrió un error al eliminar la cuenta. Intenta de nuevo.');
}
