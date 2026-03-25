<?php
/**
 * Email Helper — Computécnicos
 * 
 * Envía emails usando Brevo API (gratis: 300 emails/día)
 * 
 * Configuración en .env:
 *   BREVO_API_KEY=xkeysib-xxxxxxxxxxxx
 *   BREVO_SENDER_EMAIL=tu@email.com
 *   BREVO_SENDER_NAME=Computécnicos
 */

function enviar_email($to, $subject, $htmlBody, $fromName = 'CompuTécnicos') {
    $apiKey = $_ENV['BREVO_API_KEY'] ?? '';
    // Unificar: usar BREVO_SENDER_EMAIL o MAIL_FROM como fallback
    $fromEmail = $_ENV['BREVO_SENDER_EMAIL'] ?? $_ENV['MAIL_FROM'] ?? 'soportecomputecnicos@yahoo.com';
    $fromName = $_ENV['BREVO_SENDER_NAME'] ?? $_ENV['MAIL_FROM_NAME'] ?? $fromName;
    
    if (!empty($apiKey) && $apiKey !== 'tu_api_key_de_brevo_aqui') {
        return enviar_con_brevo($apiKey, $fromEmail, $fromName, $to, $subject, $htmlBody);
    }
    
    // Fallback: mail() nativo
    if (function_exists('log_event')) {
        log_event("BREVO_API_KEY no configurada o es placeholder. Intentando mail() nativo para: $to");
    }
    return enviar_con_mail($fromEmail, $fromName, $to, $subject, $htmlBody);
}

/**
 * Enviar email via Brevo (Sendinblue) API
 * https://developers.brevo.com/reference/sendtransacemail
 */
function enviar_con_brevo($apiKey, $fromEmail, $fromName, $to, $subject, $htmlBody) {
    $data = json_encode([
        'sender' => ['name' => $fromName, 'email' => $fromEmail],
        'to' => [['email' => $to]],
        'subject' => $subject,
        'htmlContent' => $htmlBody
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        if (function_exists('log_event')) log_event("Brevo cURL error: $error");
        return false;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        if (function_exists('log_event')) log_event("Email enviado via Brevo a: $to (subject: $subject)");
        return true;
    }

    $responseData = json_decode($response, true);
    $errorMsg = $responseData['message'] ?? 'Error desconocido';
    if (function_exists('log_event')) log_event("Brevo error HTTP $httpCode: $errorMsg | to: $to | response: $response");
    return false;
}

/**
 * Fallback: mail() nativo de PHP
 */
function enviar_con_mail($fromEmail, $fromName, $to, $subject, $htmlBody) {
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        "From: $fromName <$fromEmail>",
        "Reply-To: $fromEmail",
        'X-Mailer: PHP/' . phpversion()
    ]);

    $result = @mail($to, $subject, $htmlBody, $headers);
    if (function_exists('log_event')) {
        log_event($result ? "Email enviado via mail() a: $to" : "Error mail() a: $to");
    }
    return $result;
}

/**
 * Enviar email de bienvenida a nuevos usuarios
 */
function enviar_correo_bienvenida($email, $nombre) {
    if (empty($email)) return false;
    
    $subject = "¡Bienvenido a CompuTécnicos, $nombre!";
    $nombreCorto = explode(' ', trim($nombre))[0];
    
    // Asumimos que base_url() existe globalmente via bootstrap.php
    $site_url = function_exists('base_url') ? base_url() : 'https://computecnicos.store';
    
    $htmlBody = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #050505; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #0f0f0f; border: 1px solid #222; border-radius: 12px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #b91c1c, #7f1d1d); padding: 35px 30px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 28px; font-weight: 800; letter-spacing: 2px; }
        .body { padding: 40px 30px; color: #cccccc; line-height: 1.6; font-size: 15px; }
        .body h2 { color: #ffffff; font-size: 22px; margin-top: 0; margin-bottom: 20px; }
        .btn { display: inline-block; background: #dc2626; color: #ffffff !important; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 25px; transition: background 0.2s; }
        .footer { background: #050505; padding: 25px; text-align: center; border-top: 1px solid #1a1a1a; }
        .footer p { color: #555555; font-size: 12px; margin: 5px 0; }
        .highlight { color: #ef4444; font-weight: 600; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>COMPUTÉCNICOS</h1>
        </div>
        <div class='body'>
            <h2>¡Hola, $nombreCorto! 👋</h2>
            <p>Queremos darte una cálida bienvenida a la familia de <span class='highlight'>CompuTécnicos</span>. Nos emociona mucho tenerte con nosotros.</p>
            <p>A partir de ahora, tienes acceso a todo nuestro catálogo de productos tecnológicos: desde los mejores componentes para armar tu PC, hasta accesorios premium con envíos rápidos y seguros.</p>
            <p>Tu cuenta ya está activa y lista para usar. Entra ahora para descubrir nuestras últimas ofertas y novedades:</p>
            <div style='text-align: center;'>
                <a href='$site_url/productos.php' class='btn'>Explorar Productos</a>
            </div>
            <p style='margin-top: 35px;'>Si en algún momento necesitas ayuda técnica o tienes alguna duda con un producto, no dudes en contactarnos. ¡Nuestro equipo de soporte siempre está dispuesto a asesorarte!</p>
            <p>Saludos cordiales,<br><strong style='color:#fff;'>El Equipo de CompuTécnicos</strong></p>
        </div>
        <div class='footer'>
            <p>Estás recibiendo este correo porque te has registrado recientemente en CompuTécnicos Store.</p>
            <p>&copy; " . date('Y') . " CompuTécnicos. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>";

    return enviar_email($email, $subject, $htmlBody);
}
