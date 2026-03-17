<?php
// Limpiar la sesión de compra directa cuando el usuario sale del checkout sin completar
require_once __DIR__ . '/../app/Core/bootstrap.php';

if (isset($_SESSION['compra_directa'])) {
    unset($_SESSION['compra_directa']);
}

// Responder vacío (es un beacon)
http_response_code(204);
exit;
