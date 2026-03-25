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

/**
 * Enviar email de confirmación de compra exitosa
 */
function enviar_correo_compra($email, $nombre, $pedidoId, $total, $items) {
    if (empty($email)) return false;
    
    $subject = "Confirmación de Pedido #$pedidoId — CompuTécnicos";
    $nombreCorto = explode(' ', trim($nombre))[0];
    $site_url = function_exists('base_url') ? base_url() : 'https://computecnicos.store';
    $totalFmt = '$' . number_format($total, 0, ',', '.');
    
    $itemsHtml = '';
    foreach ($items as $item) {
        $precioFmt = '$' . number_format($item['precio'], 0, ',', '.');
        $itemsHtml .= "
        <tr>
            <td style='padding: 12px; border-bottom: 1px solid #333; color: #fff;'>{$item['nombre']}</td>
            <td style='padding: 12px; border-bottom: 1px solid #333; color: #ccc; text-align: center;'>{$item['cantidad']}</td>
            <td style='padding: 12px; border-bottom: 1px solid #333; color: #fff; text-align: right;'>$precioFmt</td>
        </tr>";
    }

    $htmlBody = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #050505; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #0f0f0f; border: 1px solid #222; border-radius: 12px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #16a34a, #14532d); padding: 35px 30px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 26px; font-weight: 800; letter-spacing: 1px; }
        .body { padding: 40px 30px; color: #cccccc; line-height: 1.6; font-size: 15px; }
        .body h2 { color: #ffffff; font-size: 22px; margin-top: 0; margin-bottom: 20px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 25px; margin-bottom: 25px; }
        .table th { background: #1a1a1a; color: #888; padding: 12px; text-align: left; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #333; }
        .total-row { font-size: 18px; font-weight: bold; color: #22c55e; text-align: right; padding-top: 20px; }
        .btn { display: inline-block; background: #dc2626; color: #ffffff !important; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 25px; transition: background 0.2s; }
        .footer { background: #050505; padding: 25px; text-align: center; border-top: 1px solid #1a1a1a; }
        .footer p { color: #555555; font-size: 12px; margin: 5px 0; }
        .highlight { color: #22c55e; font-weight: 600; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>¡PAGO EXITOSO!</h1>
        </div>
        <div class='body'>
            <h2>Hola, $nombreCorto 👋</h2>
            <p>Queremos confirmarte que hemos recibido tu pago correctamente y tu pedido <strong style='color:#fff;'>#$pedidoId</strong> ya está en proceso.</p>
            <p>A continuación, te presentamos el resumen de tu compra:</p>
            
            <table class='table'>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th style='text-align:center;'>Cant.</th>
                        <th style='text-align:right;'>Precio Unit.</th>
                    </tr>
                </thead>
                <tbody>
                    $itemsHtml
                </tbody>
            </table>
            <div class='total-row'>Total Pagado: $totalFmt</div>
            
            <p style='margin-top: 35px;'>Nos estamos preparando para enviar tus productos lo antes posible. Podrás rastrear el estado de tu pedido directamente en tu perfil:</p>
            <div style='text-align: center;'>
                <a href='$site_url/pedidos.php' class='btn'>Ver mi pedido</a>
            </div>
            <p style='margin-top: 35px;'>¡Gracias por confiar en CompuTécnicos!</p>
        </div>
        <div class='footer'>
            <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
            <p>&copy; " . date('Y') . " CompuTécnicos. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>";

    return enviar_email($email, $subject, $htmlBody);
}
