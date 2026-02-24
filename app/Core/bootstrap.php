<?php
// Bootstrap central del proyecto (no rompe rutas existentes)

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Zona horaria por defecto
date_default_timezone_set('America/Bogota');

// Constantes de rutas
define('BASE_PATH', realpath(dirname(__DIR__, 2))); // c:\xampp\htdocs\computecnicosproject

// Cargar variables de entorno desde .env manualmente
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}

// Detección sencilla de entorno
if (!defined('APP_ENV')) {
    $envServer = $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: '';
    if ($envServer !== '') {
        define('APP_ENV', strtolower($envServer));
    } else {
        $host = strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');
        $isLocal = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;
        define('APP_ENV', $isLocal ? 'dev' : 'prod');
    }
}

// Helper simple para construir URLs públicas basadas en el path del script
function base_url(): string {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isLocal = strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false;
    $scheme = $isLocal ? 'http' : 'https';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Normalizar rutas de servidor
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/')) : '';
    $basePath = str_replace('\\', '/', rtrim(BASE_PATH, '/'));
    // Calcular subcarpeta del proyecto respecto al DOCUMENT_ROOT
    $projectSubdir = '';
    if ($docRoot && strpos($basePath, $docRoot) === 0) {
        $sub = trim(substr($basePath, strlen($docRoot)), '/');
        if ($sub !== '') { $projectSubdir = '/' . $sub; }
    }
    // Si es entorno de producción, forzar el dominio oficial para que los Redirect URIs de Google Oauth siempre coincidan
    // sin importar si Vercel cargó la página desde un subdominio generado aleatoriamente.
    if ($scheme === 'https' && strpos($host, 'vercel.app') !== false) {
        return "https://computecnicos-kappa.vercel.app";
    }

    // Retornar siempre la raíz del proyecto, independiente del directorio del script en local
    return "$scheme://$host$projectSubdir";
}

// Helpers de rutas públicas (ajustables cuando migremos assets/storage)
function asset(string $path): string {
    $relative = 'assets/' . ltrim($path, '/');
    $file = BASE_PATH . '/' . $relative;
    $url = base_url() . '/' . $relative;
    // Cache-busting por mtime en desarrollo y producción (seguro y transparente)
    if (is_file($file)) {
        $v = (string) @filemtime($file);
        if ($v !== '' && $v !== '0') {
            $url .= '?v=' . rawurlencode($v);
        }
    }
    return $url;
}
function storage_url(string $path): string { return base_url() . '/storage/' . ltrim($path, '/'); }

// Cabeceras de no-caché en desarrollo para facilitar ver cambios inmediatamente
if (APP_ENV === 'dev') {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Conexión a base de datos (mantiene el archivo actual para no romper)
// Archivo de configuración de base de datos movido a config/
require_once BASE_PATH . '/config/database.php';

// Ajuste de esquema: asegurar estado 'preparacion' en ENUM de pedidos y pedido_estados
try {
    // Chequear columna pedidos.estado
    $col = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'estado'")->fetch();
    if ($col && isset($col['Type']) && strpos($col['Type'], "'preparacion'") === false) {
        $pdo->exec("ALTER TABLE pedidos MODIFY estado ENUM('pendiente','pagado','preparacion','enviado','entregado','cancelado') DEFAULT 'pendiente'");
        log_event('Esquema actualizado: agregado estado "preparacion" a pedidos.estado');
    }
    // Chequear columna pedido_estados.estado
    $col2 = $pdo->query("SHOW COLUMNS FROM pedido_estados LIKE 'estado'")->fetch();
    if ($col2 && isset($col2['Type']) && strpos($col2['Type'], "'preparacion'") === false) {
        $pdo->exec("ALTER TABLE pedido_estados MODIFY estado ENUM('pendiente','pagado','preparacion','enviado','entregado','cancelado') NOT NULL");
        log_event('Esquema actualizado: agregado estado "preparacion" a pedido_estados.estado');
    }
} catch (Throwable $e) {
    // No bloquear la app por errores de ALTER; registrar y continuar
    log_event('Error ajustando esquema ENUM estados: ' . $e->getMessage());
}

// Logging sencillo a archivo local (opcional)
function log_event(string $message): void {
    $logDir = BASE_PATH . '/logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
    $line = sprintf("[%s] (%s) %s\n", date('Y-m-d H:i:s'), APP_ENV, $message);
    @file_put_contents($logDir . '/app.log', $line, FILE_APPEND);
}

// Utilidad: mapa de versiones de assets clave
function asset_versions(array $paths): array {
    $out = [];
    foreach ($paths as $p) {
        $file = BASE_PATH . '/assets/' . ltrim($p, '/');
        $out[$p] = [
            'exists' => is_file($file),
            'mtime' => is_file($file) ? @filemtime($file) : null,
            'md5' => is_file($file) ? @md5_file($file) : null,
            'url' => asset($p)
        ];
    }
    return $out;
}

// Helper para verificar sesión y rol de admin
function require_admin(): void {
    if (!isset($_SESSION['usuario']) || ($_SESSION['usuario']['rol'] ?? '') !== 'admin') {
        header('Location: ../index.php?login=1');
        exit;
    }
}

// Helper pequeño para limpiar texto
function e(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }

// Nota: Este bootstrap no cambia rutas ni includes en páginas existentes.
// Podemos ir incorporándolo gradualmente con: require_once __DIR__ . '/bootstrap.php';
?>