<?php
// Bootstrap central del proyecto (no rompe rutas existentes)

// NO iniciar sesión aún — se hace después de conectar a la BD
// para poder usar el handler de sesiones en base de datos.

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

    // ── 1. Detectar protocolo REAL de la petición actual ──
    // Soporta: proxy inverso (X-Forwarded-Proto), HTTPS nativo, puerto 443
    $isSecure = false;
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        $isSecure = true;
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $isSecure = true;
    } elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        $isSecure = true;
    }
    $scheme = $isSecure ? 'https' : 'http';

    // ── 2. Calcular subcarpeta (solo aplica en XAMPP local) ──
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/')) : '';
    $basePath = str_replace('\\', '/', rtrim(BASE_PATH, '/'));
    $projectSubdir = '';
    if ($docRoot && strpos($basePath, $docRoot) === 0) {
        $sub = trim(substr($basePath, strlen($docRoot)), '/');
        if ($sub !== '') { $projectSubdir = '/' . $sub; }
    }

    // ── 3. Vercel: forzar dominio canónico para OAuth callbacks ──
    if (strpos($host, 'vercel.app') !== false) {
        return "https://computecnicos-kappa.vercel.app";
    }

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

// ─── Sesiones persistentes en base de datos ───
// En Vercel (serverless) el sistema de archivos no persiste entre requests,
// por lo tanto las sesiones PHP normales se pierden inmediatamente.
// Solución: guardar las sesiones en MySQL usando un handler personalizado.
if (session_status() === PHP_SESSION_NONE) {
    // Crear la tabla de sesiones automáticamente si no existe
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            data TEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            INDEX idx_expires (expires_at)
        )');
    } catch (Throwable $e) {
        // No bloquear si ya existe o hay error menor
        log_event('Aviso tabla sessions: ' . $e->getMessage());
    }

    // Registrar el handler de sesiones en base de datos
    require_once BASE_PATH . '/app/Core/DatabaseSessionHandler.php';
    $dbSessionHandler = new DatabaseSessionHandler($pdo);
    session_set_save_handler($dbSessionHandler, true);

    // Configurar cookie de sesión para que funcione correctamente
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 86400, // 24 horas
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly'  => true,
        'samesite'  => 'Lax',
    ]);

    session_start();
}

// ─── Sistema Remember Me (sesion persistente con cookie) ───
// Mantiene al usuario logueado entre sesiones del navegador.
// Los tokens se invalidan al cambiar contraseña o por expiracion (30 dias).
require_once BASE_PATH . '/app/Core/RememberMe.php';
$rememberMe = new RememberMe($pdo);
$rememberMe->ensureTable();
$rememberMe->tryRestore();

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

// Migración: columnas destacado, nuevo_hasta, oferta_hasta en productos
try {
    $cols = $pdo->query("SHOW COLUMNS FROM productos")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('destacado', $cols)) {
        $pdo->exec("ALTER TABLE productos ADD COLUMN destacado TINYINT(1) NOT NULL DEFAULT 0");
        log_event('Esquema actualizado: agregada columna destacado a productos');
    }
    if (!in_array('nuevo_hasta', $cols)) {
        $pdo->exec("ALTER TABLE productos ADD COLUMN nuevo_hasta DATE NULL DEFAULT NULL");
        log_event('Esquema actualizado: agregada columna nuevo_hasta a productos');
    }
    if (!in_array('oferta_hasta', $cols)) {
        $pdo->exec("ALTER TABLE productos ADD COLUMN oferta_hasta DATE NULL DEFAULT NULL");
        log_event('Esquema actualizado: agregada columna oferta_hasta a productos');
    }
} catch (Throwable $e) {
    log_event('Error migrando columnas productos: ' . $e->getMessage());
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
function e(?string $text): string { return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8'); }

// Nota: Este bootstrap no cambia rutas ni includes en páginas existentes.
// Podemos ir incorporándolo gradualmente con: require_once __DIR__ . '/bootstrap.php';
?>