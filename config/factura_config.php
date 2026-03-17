<?php
// Configuración de facturación electrónica
// Ajusta estos valores según el proveedor seleccionado y credenciales reales.
return [
    // Proveedor: 'alegra' o 'siigo'
    'provider' => getenv('FE_PROVIDER') ?: 'alegra',
    // Modo simulado: si true, no se llama API externa y se registra factura simulada
    'simulate' => (getenv('FE_SIMULATE') ?: 'true') === 'true',
    // Alegra
    'alegra' => [
        // Token de API de Alegra
        'token' => getenv('ALEGRA_TOKEN') ?: '',
        // Email de la cuenta (algunos flujos usan Basic con email:token)
        'email' => getenv('ALEGRA_EMAIL') ?: ''
    ],
    // Siigo
    'siigo' => [
        'client_id' => getenv('SIIGO_CLIENT_ID') ?: '',
        'client_secret' => getenv('SIIGO_CLIENT_SECRET') ?: '',
        'username' => getenv('SIIGO_USERNAME') ?: '',
        'password' => getenv('SIIGO_PASSWORD') ?: ''
    ]
];
?>