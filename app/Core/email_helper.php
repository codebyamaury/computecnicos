<?php
/**
 * Email Helper — Computécnicos
 * 
 * Envía emails usando Resend API (gratis: 100 emails/día)
 * o fallback a mail() si no hay API key configurada.
 * 
 * Configuración en .env:
 *   RESEND_API_KEY=re_xxxxxxxxxxxx
 *   MAIL_FROM=noreply@tudominio.com
 */

function enviar_email($to, $subject, $htmlBody, $fromName = 'Computécnicos') {
    $apiKey = $_ENV['RESEND_API_KEY'] ?? '';
    $fromEmail = $_ENV['MAIL_FROM'] ?? 'noreply@computecnicos.com';
    
    // Si hay API key de Resend, usar su API
    if (!empty($apiKey)) {
        return enviar_con_resend($apiKey, $fromEmail, $fromName, $to, $subject, $htmlBody);
    }
    
    // Fallback: mail() nativo (funciona en hosting tradicional, NO en Vercel)
    return enviar_con_mail($fromEmail, $fromName, $to, $subject, $htmlBody);
}

/**
 * Enviar email via Resend API (HTTP POST)
 * https://resend.com/docs/api-reference/emails/send-email
 */
function enviar_con_resend($apiKey, $fromEmail, $fromName, $to, $subject, $htmlBody) {
    $data = json_encode([
        'from' => "$fromName <$fromEmail>",
        'to' => [$to],
        'subject' => $subject,
        'html' => $htmlBody
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        log_event("Resend curl error: $error");
        return false;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        log_event("Email enviado via Resend a: $to");
        return true;
    }

    log_event("Resend error ($httpCode): $response");
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
    
    if ($result) {
        log_event("Email enviado via mail() a: $to");
    } else {
        log_event("Error enviando email via mail() a: $to");
    }
    
    return $result;
}
