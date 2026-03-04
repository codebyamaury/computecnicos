<?php
/**
 * API — Solicitar recuperación de contraseña
 * POST /api/forgot_password.php
 * 
 * Genera un token único, lo guarda en la BD y envía un email
 * con el link de recuperación.
 */

require_once __DIR__ . '/../app/Core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

// Auto-crear tabla si no existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expira DATETIME NOT NULL,
        usado TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {}

$email = trim($_POST['email'] ?? '');

// Validar email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'msg' => 'Ingresa un correo electrónico válido.']);
    exit;
}

// Verificar que el email existe en la BD
$stmt = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE email = ?");
$stmt->execute([$email]);
$usuario = $stmt->fetch();

if (!$usuario) {
    // Por seguridad, no revelamos si el email existe o no
    echo json_encode([
        'ok' => true, 
        'msg' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.'
    ]);
    exit;
}

// Limitar intentos: no más de 3 solicitudes en 15 minutos
$stmtLimit = $pdo->prepare("
    SELECT COUNT(*) as cnt FROM password_resets 
    WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
");
$stmtLimit->execute([$email]);
if ($stmtLimit->fetch()['cnt'] >= 3) {
    echo json_encode(['ok' => false, 'msg' => 'Demasiadas solicitudes. Espera 15 minutos antes de intentar de nuevo.']);
    exit;
}

// Invalidar tokens anteriores
$pdo->prepare("UPDATE password_resets SET usado = 1 WHERE email = ? AND usado = 0")->execute([$email]);

// Generar token seguro
$token = bin2hex(random_bytes(32));
$expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

$stmtIns = $pdo->prepare("INSERT INTO password_resets (email, token, expira) VALUES (?, ?, ?)");
$stmtIns->execute([$email, $token, $expira]);

// Construir URL de recuperación (siempre apuntar a producción)
$appUrl = $_ENV['APP_URL'] ?? 'https://computecnicos-kappa.vercel.app';
$resetUrl = rtrim($appUrl, '/') . '/reset_password.php?token=' . $token;

// Enviar email
$nombreUsuario = $usuario['nombre'];
$asunto = 'Restablecer contraseña — Computécnicos';

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
        <h2 style="color:#fff;margin:0 0 8px;font-size:18px">Hola, ' . htmlspecialchars($nombreUsuario) . '</h2>
        <p style="color:#999;font-size:14px;line-height:1.6;margin:0 0 24px">
            Recibimos una solicitud para restablecer la contraseña de tu cuenta. 
            Haz clic en el botón de abajo para crear una nueva contraseña.
        </p>
        <div style="text-align:center;margin:24px 0">
            <a href="' . $resetUrl . '" style="display:inline-block;background:linear-gradient(135deg,#cc0000,#ff0000);color:#fff;font-weight:800;font-size:14px;padding:14px 36px;border-radius:8px;text-decoration:none;text-transform:uppercase;letter-spacing:1px">
                Restablecer Contraseña
            </a>
        </div>
        <p style="color:#666;font-size:12px;line-height:1.5;margin:0 0 16px">
            Este enlace expira en <strong style="color:#999">1 hora</strong>. Si no solicitaste este cambio, ignora este mensaje.
        </p>
        <div style="border-top:1px solid #333;padding-top:16px;margin-top:16px">
            <p style="color:#555;font-size:11px;margin:0">
                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                <a href="' . $resetUrl . '" style="color:#ff4444;word-break:break-all;font-size:11px">' . $resetUrl . '</a>
            </p>
        </div>
    </div>
    <div style="background:#141414;padding:16px 32px;text-align:center;border-top:1px solid #222">
        <p style="color:#444;font-size:11px;margin:0">© ' . date('Y') . ' Computécnicos — Todos los derechos reservados</p>
    </div>
</div>
</body>
</html>';

// Enviar con el helper (Brevo API o mail() fallback)
require_once __DIR__ . '/../app/Core/email_helper.php';
$enviado = enviar_email($email, $asunto, $htmlBody);

if ($enviado) {
    log_event("Password reset solicitado para: $email");
} else {
    log_event("Error enviando email de password reset a: $email");
}

// Siempre responder éxito (por seguridad)
echo json_encode([
    'ok' => true,
    'msg' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
    // En desarrollo, incluir el token para testing
    '_dev_token' => (($_ENV['APP_ENV'] ?? 'production') === 'development') ? $token : null
]);
