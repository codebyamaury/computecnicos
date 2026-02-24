<?php
session_start();
session_unset();
session_destroy();
// Redirigir con bandera para mostrar notificación de cierre de sesión
header('Location: index.php?event=logout');
exit;