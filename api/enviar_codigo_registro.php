<?php
/**
 * API — Enviar código de verificación para registro
 * POST /api/enviar_codigo_registro.php
 * 
 * Genera un código de 6 dígitos, lo guarda en sesión y lo envía
 * al correo del usuario usando Brevo (mismas credenciales que forgot_password).
 */

require_once __DIR__ . '/../app/Core/bootstrap.php';
require_once __DIR__ . '/../app/Core/email_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$nombre = trim($_POST['nombre'] ?? 'Usuario');

// Validar email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'msg' => 'Ingresa un correo electrónico válido.']);
    exit;
}

// Verificar que el email NO exista ya en la BD
$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['ok' => false, 'msg' => 'Este correo ya está registrado. Intenta iniciar sesión.']);
    exit;
}

// Rate limiting: máximo 5 códigos cada 15 minutos por email (usando sesión)
$now = time();
$key = 'reg_code_attempts_' . md5($email);
if (!isset($_SESSION[$key])) {
    $_SESSION[$key] = [];
}
// Limpiar intentos viejos (más de 15 min)
$_SESSION[$key] = array_filter($_SESSION[$key], function($t) use ($now) {
    return ($now - $t) < 900;
});
if (count($_SESSION[$key]) >= 5) {
    echo json_encode(['ok' => false, 'msg' => 'Demasiados intentos. Espera 15 minutos antes de solicitar otro código.']);
    exit;
}

// Generar código de 6 dígitos
$codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Guardar en sesión con expiración de 10 minutos
$_SESSION['reg_verification'] = [
    'email'   => $email,
    'codigo'  => $codigo,
    'expira'  => $now + 600, // 10 minutos
    'intentos' => 0
];

// Registrar intento
$_SESSION[$key][] = $now;

// Construir email HTML con el código
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
            Para completar tu registro, escribe el siguiente código de verificación en nuestra página:
        </p>
        <div style="text-align:center;margin:32px 0">
            <div style="display:inline-block;background:#222;border:1px solid #444;border-radius:12px;padding:20px 40px;box-shadow: 0 4px 20px rgba(0,0,0,0.5)">
                <span style="color:#ff0000;font-size:32px;font-weight:900;letter-spacing:10px;font-family:monospace;display:block">' . $codigo . '</span>
            </div>
            <p style="color:#666;font-size:11px;margin-top:12px">Este código expira en 10 minutos</p>
        </div>
        <p style="color:#666;font-size:12px;line-height:1.5;margin:0 0 16px;text-align:center">
            Si no solicitaste este registro, ignora este mensaje y nada cambiará en tu cuenta.
        </p>
        <div style="border-top:1px solid #333;padding-top:16px;margin-top:16px">
            <p style="color:#555;font-size:10px;margin:0;text-align:center;line-height:1.4">
                Por seguridad, nunca compartas este código con nadie. El equipo de CompuTécnicos nunca te pedirá este código por teléfono o chat.
            </p>
        </div>
    </div>
    <div style="background:#141414;padding:16px 32px;text-align:center;border-top:1px solid #222">
        <p style="color:#444;font-size:11px;margin:0">© ' . date('Y') . ' Computécnicos — Todos los derechos reservados</p>
    </div>
</div>
</body>
</html>';

$asunto = 'Código de verificación — Computécnicos';
$enviado = enviar_email($email, $asunto, $htmlBody);

if ($enviado) {
    log_event("Código de verificación de registro enviado a: $email");
    echo json_encode(['ok' => true, 'msg' => 'Código de verificación enviado a tu correo electrónico.']);
} else {
    log_event("Error enviando código de verificación a: $email");
    echo json_encode(['ok' => false, 'msg' => 'No se pudo enviar el código. Intenta de nuevo en unos segundos.']);
}
