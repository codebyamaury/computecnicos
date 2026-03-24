<?php
/**
 * API — Enviar código de verificación para cambio de email
 * POST /api/enviar_codigo_cambio_email.php
 * 
 * Genera un código de 6 dígitos y lo envía al NUEVO correo.
 * El usuario debe estar logueado.
 */

require_once __DIR__ . '/../app/Core/bootstrap.php';
require_once __DIR__ . '/../app/Core/email_helper.php';
require_once __DIR__ . '/../app/Core/email_validator.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

// Verificar sesión
if (!isset($_SESSION['usuario']['id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Debes iniciar sesión.']);
    exit;
}

$nuevo_email = trim($_POST['email'] ?? '');
$usuario_id = $_SESSION['usuario']['id'];
$email_actual = $_SESSION['usuario']['email'];

// Si el email no cambió, no hacer nada
if (strtolower($nuevo_email) === strtolower($email_actual)) {
    echo json_encode(['ok' => false, 'msg' => 'Este ya es tu correo actual.']);
    exit;
}

// Validar email completo
$email_check = validar_email_completo($nuevo_email);
if (!$email_check['ok']) {
    echo json_encode(['ok' => false, 'msg' => $email_check['msg']]);
    exit;
}

// Verificar que no esté en uso por otro usuario
$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id != ?');
$stmt->execute([$nuevo_email, $usuario_id]);
if ($stmt->fetch()) {
    echo json_encode(['ok' => false, 'msg' => 'Este correo ya está registrado por otra cuenta.']);
    exit;
}

// Rate limiting: máximo 5 códigos cada 15 minutos
$now = time();
$key = 'change_email_attempts_' . $usuario_id;
if (!isset($_SESSION[$key])) {
    $_SESSION[$key] = [];
}
$_SESSION[$key] = array_filter($_SESSION[$key], function($t) use ($now) {
    return ($now - $t) < 900;
});
if (count($_SESSION[$key]) >= 5) {
    echo json_encode(['ok' => false, 'msg' => 'Demasiados intentos. Espera 15 minutos.']);
    exit;
}

// Generar código de 6 dígitos
$codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Guardar en sesión
$_SESSION['cambio_email_verificacion'] = [
    'email'    => $nuevo_email,
    'codigo'   => $codigo,
    'expira'   => $now + 600, // 10 minutos
    'intentos' => 0
];

$_SESSION[$key][] = $now;

// Nombre del usuario
$nombre = $_SESSION['usuario']['nombre'] ?? 'Usuario';

// Construir email HTML
$htmlBody = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#111;font-family:Arial,Helvetica,sans-serif">
<div style="max-width:520px;margin:40px auto;background:#1a1a1a;border:1px solid #333;border-radius:12px;overflow:hidden">
    <div style="background:linear-gradient(135deg,#cc0000,#ff0000);padding:28px 32px;text-align:center">
        <h1 style="margin:0;color:#fff;font-size:22px;font-weight:800;letter-spacing:1px">COMPU<span style="font-weight:400">TÉCNICOS</span></h1>
    </div>
    <div style="padding:32px">
        <h2 style="color:#fff;margin:0 0 8px;font-size:18px">Hola, ' . htmlspecialchars($nombre) . '</h2>
        <p style="color:#999;font-size:14px;line-height:1.6;margin:0 0 24px">
            Recibimos una solicitud para cambiar tu correo electrónico. 
            Ingresa el siguiente código de verificación para confirmar el cambio:
        </p>
        <div style="text-align:center;margin:32px 0">
            <div style="display:inline-block;background:#222;border:1px solid #444;border-radius:12px;padding:20px 40px;box-shadow: 0 4px 20px rgba(0,0,0,0.5)">
                <span style="color:#ff0000;font-size:32px;font-weight:900;letter-spacing:10px;font-family:monospace;display:block">' . $codigo . '</span>
            </div>
            <p style="color:#666;font-size:11px;margin-top:12px">Este código expira en 10 minutos</p>
        </div>
        <p style="color:#666;font-size:12px;line-height:1.5;margin:0 0 16px;text-align:center">
            Si no solicitaste este cambio, ignora este mensaje.
        </p>
        <div style="border-top:1px solid #333;padding-top:16px;margin-top:16px">
            <p style="color:#555;font-size:10px;margin:0;text-align:center;line-height:1.4">
                Por seguridad, nunca compartas este código con nadie.
            </p>
        </div>
    </div>
    <div style="background:#141414;padding:16px 32px;text-align:center;border-top:1px solid #222">
        <p style="color:#444;font-size:11px;margin:0">© ' . date('Y') . ' Computécnicos — Todos los derechos reservados</p>
    </div>
</div>
</body>
</html>';

$asunto = 'Código de verificación — Cambio de correo';
$enviado = enviar_email($nuevo_email, $asunto, $htmlBody);

if ($enviado) {
    log_event("Código de cambio de email enviado a: $nuevo_email (usuario: $usuario_id)");
    // Ofuscar el email para mostrar al usuario
    $parts = explode('@', $nuevo_email);
    $user = $parts[0];
    $domain = $parts[1];
    $masked = substr($user, 0, 2) . str_repeat('•', max(1, strlen($user) - 2)) . '@' . $domain;
    echo json_encode(['ok' => true, 'msg' => 'Código enviado a ' . $masked]);
} else {
    log_event("Error enviando código de cambio de email a: $nuevo_email");
    echo json_encode(['ok' => false, 'msg' => 'No se pudo enviar el código. Intenta de nuevo.']);
}
