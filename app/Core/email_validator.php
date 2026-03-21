<?php
/**
 * Email Validator — Computécnicos
 * 
 * Validación robusta de emails:
 * 1. Formato con filter_var
 * 2. Dominio real con DNS (MX records)  
 * 3. Detección de dominios sospechosos/typos de proveedores conocidos
 */

/**
 * Valida un email completamente.
 * Retorna ['ok' => true] o ['ok' => false, 'msg' => 'razón']
 */
function validar_email_completo($email) {
    $email = trim($email);
    
    // 1. Formato básico
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'msg' => 'El correo electrónico no tiene un formato válido.'];
    }
    
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    
    // 2. Detectar dominios sospechosos (typos de proveedores conocidos)
    $dominios_sospechosos = detectar_dominio_sospechoso($domain);
    if ($dominios_sospechosos) {
        return ['ok' => false, 'msg' => $dominios_sospechosos];
    }
    
    // 3. Verificar que el dominio tenga registros MX (acepta correos)
    if (!checkdnsrr($domain, 'MX')) {
        // Algunos dominios legítimos solo tienen A records, pero casi ninguno real
        // Para email, MX es obligatorio en la práctica
        return ['ok' => false, 'msg' => 'El dominio "' . htmlspecialchars($domain) . '" no parece aceptar correos electrónicos.'];
    }
    
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
    
    // Extraer la parte antes del primer punto del dominio
    $parts = explode('.', $domain);
    $base = $parts[0]; // ej: "gmail" de "gmail.yt"
    
    // Verificar si el nombre base coincide con un proveedor conocido
    foreach ($proveedores_validos as $proveedor => $dominios_ok) {
        if ($base === $proveedor && !in_array($domain, $dominios_ok)) {
            // El dominio parece un typo de un proveedor conocido
            $sugerencia = $dominios_ok[0]; // sugerir el dominio principal
            return 'El dominio "' . htmlspecialchars($domain) . '" no es válido. ¿Quisiste escribir @' . $sugerencia . '?';
        }
    }
    
    // Verificar variaciones comunes con typos (ej: "gmal", "gmial", "yaho", "outllok")
    $typos_comunes = [
        'gmal' => 'gmail.com',
        'gmial' => 'gmail.com',
        'gmaill' => 'gmail.com',
        'gamil' => 'gmail.com',
        'gnail' => 'gmail.com',
        'gmai' => 'gmail.com',
        'gimail' => 'gmail.com',
        'gmil' => 'gmail.com',
        'yaho' => 'yahoo.com',
        'yahooo' => 'yahoo.com',
        'yhaoo' => 'yahoo.com',
        'outllok' => 'outlook.com',
        'outlok' => 'outlook.com',
        'outloock' => 'outlook.com',
        'hotmal' => 'hotmail.com',
        'hotmial' => 'hotmail.com',
        'hotmaill' => 'hotmail.com',
        'htmail' => 'hotmail.com',
        'htomail' => 'hotmail.com',
        'icloud' => 'icloud.com',
        'iclould' => 'icloud.com',
    ];
    
    foreach ($typos_comunes as $typo => $correcto) {
        if ($base === $typo) {
            return 'El dominio "' . htmlspecialchars($domain) . '" parece un error. ¿Quisiste escribir @' . $correcto . '?';
        }
    }
    
    return null; // dominio OK
}
