<?php
// Router para Vercel Serverless
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/' || $uri === '') {
    $uri = '/index.php';
}

$requested_file = __DIR__ . '/..' . $uri;

if (file_exists($requested_file) && is_file($requested_file) && pathinfo($requested_file, PATHINFO_EXTENSION) === 'php') {
    // Sobrescribir variables de entorno de PHP para que los menús activos y rutas funcionen
    $_SERVER['PHP_SELF'] = $uri;
    $_SERVER['SCRIPT_NAME'] = $uri;
    $_SERVER['SCRIPT_FILENAME'] = $requested_file;
    
    // Cambiar el directorio de trabajo actual para que las sentencias require relativas en los subdirectorios funcionen
    chdir(dirname($requested_file));
    
    require $requested_file;
} else {
    http_response_code(404);
    echo "404 Not Found";
}
