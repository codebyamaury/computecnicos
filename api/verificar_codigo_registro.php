<?php
/**
 * API — Verificar código de registro
 * POST /api/verificar_codigo_registro.php
 * 
 * Valida el código de 6 dígitos enviado al correo del usuario.
 * Si es correcto, marca la sesión como verificada para permitir el registro.
 */

require_once __DIR__ . '/../app/Core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

$codigo = trim($_POST['codigo'] ?? '');
$email  = trim($_POST['email'] ?? '');

if (empty($codigo) || empty($email)) {
    echo json_encode(['ok' => false, 'msg' => 'Código y correo son requeridos.']);
    exit;
}

// Verificar que existe data de verificación en sesión
if (!isset($_SESSION['reg_verification'])) {
    echo json_encode(['ok' => false, 'msg' => 'No se ha solicitado un código. Vuelve al paso anterior y solicita uno nuevo.']);
    exit;
}

$ver = $_SESSION['reg_verification'];

// Verificar que el email coincide
if (strtolower($ver['email']) !== strtolower($email)) {
    echo json_encode(['ok' => false, 'msg' => 'El correo no coincide con el código solicitado. Solicita un nuevo código.']);
    exit;
}

// Verificar expiración
if (time() > $ver['expira']) {
    unset($_SESSION['reg_verification']);
    echo json_encode(['ok' => false, 'msg' => 'El código ha expirado. Solicita uno nuevo.']);
    exit;
}

// Verificar intentos (máximo 5)
if ($ver['intentos'] >= 5) {
    unset($_SESSION['reg_verification']);
    echo json_encode(['ok' => false, 'msg' => 'Demasiados intentos fallidos. Solicita un nuevo código.']);
    exit;
}

// Incrementar intentos
$_SESSION['reg_verification']['intentos']++;

// Verificar código
if ($ver['codigo'] !== $codigo) {
    $restantes = 5 - $_SESSION['reg_verification']['intentos'];
    echo json_encode([
        'ok' => false, 
        'msg' => 'Código incorrecto. Te quedan ' . $restantes . ' intento(s).'
    ]);
    exit;
}

// ¡Código correcto! Marcar como verificado
$_SESSION['reg_email_verified'] = [
    'email'     => $email,
    'verificado' => true,
    'timestamp'  => time()
];

// Limpiar el código usado
unset($_SESSION['reg_verification']);

log_event("Email verificado exitosamente para registro: $email");

echo json_encode(['ok' => true, 'msg' => '¡Correo verificado correctamente!']);
