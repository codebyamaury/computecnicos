<?php
/**
 * Email Validator — Computécnicos
 * 
 * Validación robusta de emails usando Abstract Email Reputation API
 * con fallback a validación local (formato + DNS + detección de typos).
 * 
 * La API verifica: formato, SMTP, MX, emails desechables, catch-all, riesgo.
 * Free plan: limitado, por eso tenemos fallback local.
 * 
 * Configuración en .env:
 *   ABSTRACT_EMAIL_API_KEY=tu_api_key
 */

/**
 * Valida un email completamente usando Abstract API + fallback local.
 * Retorna ['ok' => true] o ['ok' => false, 'msg' => 'razón']
 */
function validar_email_completo($email) {
    $email = trim($email);
    
    // 1. Validación de formato básica (rápida, sin API)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'msg' => 'El correo electrónico no tiene un formato válido.'];
    }
    
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    
    // 2. Detección local de typos (rápida, sin API)
    $typo_check = detectar_dominio_sospechoso($domain);
    if ($typo_check) {
        return ['ok' => false, 'msg' => $typo_check];
    }
    
    // 3. Intentar validación con Abstract API
    $api_key = $_ENV['ABSTRACT_EMAIL_API_KEY'] ?? '';
    if (!empty($api_key) && $api_key !== 'tu_api_key_aqui') {
        $api_result = validar_con_abstract_api($email, $api_key);
        if ($api_result !== null) {
            return $api_result;
        }
        // Si la API falla (timeout, error, etc.), continuar con validación local
    }
    
    // 4. Fallback: validación local con DNS MX
    if (!checkdnsrr($domain, 'MX')) {
        return ['ok' => false, 'msg' => 'El dominio "' . htmlspecialchars($domain) . '" no parece aceptar correos electrónicos.'];
    }
    
    return ['ok' => true];
}

/**
 * Valida email usando Abstract Email Reputation API.
 * Retorna ['ok' => bool, 'msg' => string] o null si la API falla.
 */
function validar_con_abstract_api($email, $api_key) {
    $url = 'https://emailreputation.abstractapi.com/v1/?'
         . http_build_query(['api_key' => $api_key, 'email' => $email]);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Si hay error de conexión o la API no responde, retornar null (usar fallback)
    if ($error || $httpCode !== 200 || empty($response)) {
        if (function_exists('log_event')) {
            log_event("Abstract API error: HTTP $httpCode, curl: $error");
        }
        return null;
    }
    
    $data = json_decode($response, true);
    if (!$data || !isset($data['email_deliverability'])) {
        return null;
    }
    
    $deliverability = $data['email_deliverability'] ?? [];
    $quality = $data['email_quality'] ?? [];
    $risk = $data['email_risk'] ?? [];
    
    // Email no entregable
    if (($deliverability['status'] ?? '') === 'undeliverable') {
        return ['ok' => false, 'msg' => 'Este correo electrónico no existe o no puede recibir mensajes.'];
    }
    
    // Formato inválido según la API
    if (!($deliverability['is_format_valid'] ?? true)) {
        return ['ok' => false, 'msg' => 'El formato del correo electrónico no es válido.'];
    }
    
    // Sin registros MX (dominio no acepta correo)
    if (!($deliverability['is_mx_valid'] ?? true)) {
        return ['ok' => false, 'msg' => 'El dominio de este correo no acepta correos electrónicos.'];
    }
    
    // SMTP inválido (el buzón no existe)
    if (isset($deliverability['is_smtp_valid']) && $deliverability['is_smtp_valid'] === false) {
        return ['ok' => false, 'msg' => 'Este correo electrónico no existe. Verifica que esté bien escrito.'];
    }
    
    // Email desechable/temporal (tempmail, guerrillamail, etc.)
    if (!empty($quality['is_disposable'])) {
        return ['ok' => false, 'msg' => 'No se permiten correos electrónicos temporales o desechables.'];
    }
    
    // Riesgo alto
    if (($risk['address_risk_status'] ?? '') === 'high') {
        return ['ok' => false, 'msg' => 'Este correo electrónico ha sido marcado como riesgoso. Usa otro correo.'];
    }
    
    // Score de calidad muy bajo
    $score = $quality['score'] ?? 1;
    if ($score < 0.3) {
        return ['ok' => false, 'msg' => 'Este correo electrónico no parece ser válido. Verifica que esté bien escrito.'];
    }
    
    // Todo OK
    return ['ok' => true];
}

/**
 * Detecta si un dominio es un typo o variación sospechosa de un proveedor conocido.
 * Retorna un mensaje de error o null si es válido.
 */
function detectar_dominio_sospechoso($domain) {
    // Dominios legítimos por proveedor
    $proveedores_validos = [
        'gmail' => ['gmail.com'],
        'yahoo' => ['yahoo.com', 'yahoo.es', 'yahoo.com.mx', 'yahoo.com.co', 'yahoo.com.ar', 'yahoo.com.br', 'yahoo.co.uk', 'yahoo.fr', 'yahoo.de', 'yahoo.it', 'yahoo.co.jp', 'yahoo.ca', 'ymail.com', 'rocketmail.com'],
        'outlook' => ['outlook.com', 'outlook.es', 'outlook.com.mx', 'outlook.co', 'outlook.com.co'],
        'hotmail' => ['hotmail.com', 'hotmail.es', 'hotmail.com.mx', 'hotmail.co', 'hotmail.com.co', 'hotmail.co.uk', 'hotmail.fr', 'hotmail.de', 'hotmail.it'],
        'live' => ['live.com', 'live.com.mx', 'live.com.co', 'live.co.uk'],
        'icloud' => ['icloud.com'],
        'me' => ['me.com'],
        'mac' => ['mac.com'],
        'aol' => ['aol.com'],
        'protonmail' => ['protonmail.com', 'proton.me', 'pm.me'],
        'zoho' => ['zoho.com', 'zohomail.com'],
    ];
    
    $parts = explode('.', $domain);
    $base = $parts[0];
    
    foreach ($proveedores_validos as $proveedor => $dominios_ok) {
        if ($base === $proveedor && !in_array($domain, $dominios_ok)) {
            $sugerencia = $dominios_ok[0];
            return 'El dominio "' . htmlspecialchars($domain) . '" no es válido. ¿Quisiste escribir @' . $sugerencia . '?';
        }
    }
    
    // Typos comunes
    $typos_comunes = [
        'gmal' => 'gmail.com', 'gmial' => 'gmail.com', 'gmaill' => 'gmail.com',
        'gamil' => 'gmail.com', 'gnail' => 'gmail.com', 'gmai' => 'gmail.com',
        'gimail' => 'gmail.com', 'gmil' => 'gmail.com',
        'yaho' => 'yahoo.com', 'yahooo' => 'yahoo.com', 'yhaoo' => 'yahoo.com',
        'outllok' => 'outlook.com', 'outlok' => 'outlook.com', 'outloock' => 'outlook.com',
        'hotmal' => 'hotmail.com', 'hotmial' => 'hotmail.com', 'hotmaill' => 'hotmail.com',
        'htmail' => 'hotmail.com', 'htomail' => 'hotmail.com',
        'iclould' => 'icloud.com',
    ];
    
    foreach ($typos_comunes as $typo => $correcto) {
        if ($base === $typo) {
            return 'El dominio "' . htmlspecialchars($domain) . '" parece un error. ¿Quisiste escribir @' . $correcto . '?';
        }
    }
    
    return null;
}
