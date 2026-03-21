<?php
/**
 * Backend del formulario de contacto
 * Guarda en BD + envía email vía Brevo (SendinBlue) API
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../app/Core/bootstrap.php';

function respuesta($ok, $msg) {
    echo json_encode(['ok' => $ok, 'msg' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuesta(false, 'Método no permitido.');
}

$nombre  = trim($_POST['nombre'] ?? '');
$email   = trim($_POST['email'] ?? '');
$mensaje = trim($_POST['mensaje'] ?? '');

// ─── Validaciones ───
if (!$nombre || !$email || !$mensaje) {
    respuesta(false, 'Todos los campos son obligatorios.');
}

require_once __DIR__ . '/../app/Core/email_validator.php';
$email_check = validar_email_completo($email);
if (!$email_check['ok']) {
    respuesta(false, $email_check['msg']);
}

if (strlen($mensaje) < 10) {
    respuesta(false, 'El mensaje debe tener al menos 10 caracteres.');
}

// ─── Guardar en base de datos ───
try {
    $stmt = $pdo->prepare('INSERT INTO contactos (nombre, email, mensaje) VALUES (?, ?, ?)');
    $stmt->execute([$nombre, $email, $mensaje]);
} catch (Exception $e) {
    log_event('Error BD contacto: ' . $e->getMessage());
    respuesta(false, 'Error al guardar el mensaje. Intenta de nuevo.');
}

// ─── Enviar correo vía Brevo API ───
$brevoApiKey     = $_ENV['BREVO_API_KEY'] ?? '';
$senderEmail     = $_ENV['BREVO_SENDER_EMAIL'] ?? 'soportecomputecnicos@yahoo.com';
$senderName      = $_ENV['BREVO_SENDER_NAME'] ?? 'CompuTécnicos';
$contactEmail    = $_ENV['BREVO_CONTACT_EMAIL'] ?? 'soportecomputecnicos@yahoo.com';

if (empty($brevoApiKey) || $brevoApiKey === 'tu_api_key_de_brevo_aqui') {
    log_event('Brevo API key no configurada. Mensaje guardado en BD pero no enviado por email.');
    respuesta(true, '¡Mensaje recibido correctamente! Nos pondremos en contacto contigo pronto.');
}

// Construir el contenido HTML del email
$fechaHora = date('d/m/Y H:i:s');
$htmlContent = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #0a0a0a; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #111; border: 1px solid #222; border-radius: 12px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #dc2626, #991b1b); padding: 24px 30px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; font-weight: 700; letter-spacing: 1px; }
        .header p { color: rgba(255,255,255,0.8); margin: 6px 0 0; font-size: 13px; }
        .body { padding: 30px; color: #ccc; }
        .field { margin-bottom: 20px; }
        .field-label { color: #ef4444; font-size: 11px; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 600; margin-bottom: 6px; }
        .field-value { color: #e5e5e5; font-size: 15px; line-height: 1.6; background: #1a1a1a; padding: 12px 16px; border-radius: 8px; border-left: 3px solid #dc2626; }
        .footer { background: #0a0a0a; padding: 16px 30px; text-align: center; border-top: 1px solid #222; }
        .footer p { color: #555; font-size: 11px; margin: 0; }
        .badge { display: inline-block; background: #dc2626; color: #fff; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>📩 Nuevo Mensaje de Contacto</h1>
            <p>Recibido el {$fechaHora}</p>
        </div>
        <div class='body'>
            <div class='field'>
                <div class='field-label'>👤 Nombre</div>
                <div class='field-value'>" . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "</div>
            </div>
            <div class='field'>
                <div class='field-label'>✉️ Correo Electrónico</div>
                <div class='field-value'><a href='mailto:" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "' style='color: #ef4444; text-decoration: none;'>" . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "</a></div>
            </div>
            <div class='field'>
                <div class='field-label'>💬 Mensaje</div>
                <div class='field-value'>" . nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8')) . "</div>
            </div>
        </div>
        <div class='footer'>
            <p><span class='badge'>CompuTécnicos</span> — Sistema de Contacto Automatizado</p>
        </div>
    </div>
</body>
</html>";

// Construir el payload para Brevo API v3
$brevoPayload = [
    'sender' => [
        'name'  => $senderName,
        'email' => $senderEmail
    ],
    'to' => [
        [
            'email' => $contactEmail,
            'name'  => 'CompuTécnicos Soporte'
        ]
    ],
    'replyTo' => [
        'email' => $email,
        'name'  => $nombre
    ],
    'subject'     => "📩 Nuevo mensaje de contacto: {$nombre}",
    'htmlContent' => $htmlContent
];

// Llamada a la API de Brevo usando cURL
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://api.brevo.com/v3/smtp/email',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($brevoPayload),
    CURLOPT_HTTPHEADER     => [
        'accept: application/json',
        'api-key: ' . $brevoApiKey,
        'content-type: application/json'
    ],
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$response     = curl_exec($ch);
$httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError    = curl_error($ch);
curl_close($ch);

// Registrar resultado del envío
if ($curlError) {
    log_event("Brevo cURL error: {$curlError}");
    // El mensaje ya se guardó en BD, informar éxito parcial
    respuesta(true, '¡Mensaje guardado! Hubo un problema al enviar la notificación por correo, pero tu mensaje fue recibido.');
}

if ($httpCode >= 200 && $httpCode < 300) {
    log_event("Brevo email enviado exitosamente a {$contactEmail} desde {$email} (HTTP {$httpCode})");
    respuesta(true, '¡Mensaje enviado con éxito! Te responderemos lo antes posible a tu correo.');
} else {
    $responseData = json_decode($response, true);
    $errorMsg = $responseData['message'] ?? 'Error desconocido';
    log_event("Brevo error HTTP {$httpCode}: {$errorMsg} | Response: {$response}");
    // El mensaje ya se guardó en BD, informar éxito parcial
    respuesta(true, '¡Mensaje recibido correctamente! Nos pondremos en contacto contigo pronto.');
}