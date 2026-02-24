<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/app/Core/bootstrap.php';
session_unset();
session_destroy();
// Redirigir con bandera para mostrar notificación de cierre de sesión
header('Location: index.php?event=logout');
exit;