<?php
/**
 * Email Helper — Computécnicos
 * 
 * Envía emails usando Brevo API (gratis: 300 emails/día, sin verificar dominio)
 * 
 * Configuración en .env:
 *   BREVO_API_KEY=xkeysib-xxxxxxxxxxxx
 *   MAIL_FROM=tu@email.com
 *   MAIL_FROM_NAME=Computécnicos
 */

function enviar_email($to, $subject, $htmlBody, $fromName = 'Computécnicos') {
    $apiKey = $_ENV['BREVO_API_KEY'] ?? '';
    $fromEmail = $_ENV['MAIL_FROM'] ?? 'noreply@computecnicos.com';
    $fromName = $_ENV['MAIL_FROM_NAME'] ?? $fromName;
    
    if (!empty($apiKey)) {
        return enviar_con_brevo($apiKey, $fromEmail, $fromName, $to, $subject, $htmlBody);
    }
    
    // Fallback: mail() nativo
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
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        if (function_exists('log_event')) log_event("Brevo curl error: $error");
        return false;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        if (function_exists('log_event')) log_event("Email enviado via Brevo a: $to");
        return true;
    }

    if (function_exists('log_event')) log_event("Brevo error ($httpCode): $response");
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
