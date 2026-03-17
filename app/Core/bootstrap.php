<?php
// Bootstrap central del proyecto — optimizado para VPS
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

// Helper para construir URLs públicas (resultado cacheado por request)
function base_url(): string {
    static $cached = null;
    if ($cached !== null) return $cached;

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Detectar protocolo REAL de la petición actual
    $isSecure = false;
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        $isSecure = true;
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $isSecure = true;
    } elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        $isSecure = true;
    }
    $scheme = $isSecure ? 'https' : 'http';

    // Calcular subcarpeta (solo aplica en XAMPP local)
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/')) : '';
    $basePath = str_replace('\\', '/', rtrim(BASE_PATH, '/'));
    $projectSubdir = '';
    if ($docRoot && strpos($basePath, $docRoot) === 0) {
        $sub = trim(substr($basePath, strlen($docRoot)), '/');
        if ($sub !== '') { $projectSubdir = '/' . $sub; }
    }

    $cached = "$scheme://$host$projectSubdir";
    return $cached;
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

// Conexión a base de datos
require_once BASE_PATH . '/config/database.php';

// ─── Sesiones ───
// En VPS: usar sesiones nativas de PHP (rápido, filesystem persistente)

if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isSecure,
        'httponly'  => true,
        'samesite'  => 'Lax',
    ]);

    session_start();
}

// ─── CSRF Protection ───
require_once BASE_PATH . '/app/Core/csrf_helper.php';
// Interceptar peticiones POST de la ruta /admin/ automáticamente
if (session_status() === PHP_SESSION_ACTIVE) {
    auto_protect_admin_csrf();
}

// ─── Remember Me ───
require_once BASE_PATH . '/app/Core/RememberMe.php';
$rememberMe = new RememberMe($pdo);
$rememberMe->tryRestore();

// ─── Migraciones de esquema (se ejecutan UNA SOLA VEZ) ───
    // Usa un archivo bandera para evitar consultas de esquema en cada request
    $migrationFlag = BASE_PATH . '/logs/.schema_migrated_v8';
    if (!file_exists($migrationFlag)) {
        try {
            // Crear tabla de sesiones si no existe
            $pdo->exec('CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(128) NOT NULL PRIMARY KEY,
                data TEXT NOT NULL,
                expires_at DATETIME NOT NULL,
                INDEX idx_expires (expires_at)
            )');

            // Crear tabla de remember_tokens si no existe
            $rememberMe->ensureTable();

            // Ajustar ENUM de estados en pedidos
            $col = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'estado'")->fetch();
            if ($col && isset($col['Type']) && strpos($col['Type'], "'preparacion'") === false) {
                $pdo->exec("ALTER TABLE pedidos MODIFY estado ENUM('pendiente','pagado','preparacion','enviado','entregado','cancelado') DEFAULT 'pendiente'");
            }
            $col2 = $pdo->query("SHOW COLUMNS FROM pedido_estados LIKE 'estado'")->fetch();
            if ($col2 && isset($col2['Type']) && strpos($col2['Type'], "'preparacion'") === false) {
                $pdo->exec("ALTER TABLE pedido_estados MODIFY estado ENUM('pendiente','pagado','preparacion','enviado','entregado','cancelado') NOT NULL");
            }

            // Agregar columna notificado_admin a pedidos
            $colsPed = $pdo->query("SHOW COLUMNS FROM pedidos")->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('notificado_admin', $colsPed)) {
                $pdo->exec("ALTER TABLE pedidos ADD COLUMN notificado_admin TINYINT(1) NOT NULL DEFAULT 0");
            }

            // Agregar columnas a productos si faltan
            $cols = $pdo->query("SHOW COLUMNS FROM productos")->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('destacado', $cols))
                $pdo->exec("ALTER TABLE productos ADD COLUMN destacado TINYINT(1) NOT NULL DEFAULT 0");
            if (!in_array('nuevo_hasta', $cols))
                $pdo->exec("ALTER TABLE productos ADD COLUMN nuevo_hasta DATE NULL DEFAULT NULL");
            if (!in_array('oferta_hasta', $cols))
                $pdo->exec("ALTER TABLE productos ADD COLUMN oferta_hasta DATE NULL DEFAULT NULL");
            if (!in_array('precio_original', $cols))
                $pdo->exec("ALTER TABLE productos ADD COLUMN precio_original DECIMAL(12,2) NULL DEFAULT NULL");
            if (!in_array('descuento', $cols))
                $pdo->exec("ALTER TABLE productos ADD COLUMN descuento DECIMAL(5,2) NULL DEFAULT NULL");
            if (!in_array('video_url', $cols))
                $pdo->exec("ALTER TABLE productos ADD COLUMN video_url VARCHAR(500) NULL DEFAULT NULL");

            // v7: Auto-populate precio_original para productos con oferta activa que no lo tienen
            // Esto hace que los productos existentes muestren el precio original tachado
            $pdo->exec("UPDATE productos SET 
                precio_original = ROUND(precio * 1.15, 2), 
                descuento = 13.04 
                WHERE oferta = 1 
                AND (precio_original IS NULL OR precio_original = 0)
                AND precio > 0");

            // Migrar usuarios es_principal
            $colsUsers = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('es_principal', $colsUsers)) {
                $pdo->exec("ALTER TABLE usuarios ADD COLUMN es_principal TINYINT(1) NOT NULL DEFAULT 0");
                $pdo->exec("UPDATE usuarios SET es_principal = 1 ORDER BY id ASC LIMIT 1");
            }

        // Marcar migraciones como completadas
        @mkdir(BASE_PATH . '/logs', 0775, true);
        @file_put_contents($migrationFlag, date('Y-m-d H:i:s') . ' - Schema migrations v8 completed');
        log_event('Migraciones de esquema v8 completadas exitosamente');
    } catch (Throwable $e) {
        log_event('Error en migraciones: ' . $e->getMessage());
    }
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

/**
 * Calcula el precio efectivo y el estado del descuento de un producto.
 * @param array $p Datos del producto de la BD
 * @return array [precio, tiene_descuento, precio_original, porcentaje, ahorro]
 */
function get_product_price_data(array $p): array {
    $now = strtotime('today');
    $enOferta = !empty($p['oferta']) && (empty($p['oferta_hasta']) || strtotime($p['oferta_hasta']) >= $now);
    
    // Si tiene precio_original configurado
    if (!empty($p['precio_original']) && $p['precio_original'] > 0) {
        if ($enOferta) {
            // El precio de venta (p['precio']) es el descuento
            $porcentaje = !empty($p['descuento']) && $p['descuento'] > 0 
                ? $p['descuento'] 
                : round((($p['precio_original'] - $p['precio']) / $p['precio_original']) * 100);
            
            return [
                'precio' => (float)$p['precio'],
                'tiene_descuento' => true,
                'precio_original' => (float)$p['precio_original'],
                'porcentaje' => (float)$porcentaje,
                'ahorro' => (float)($p['precio_original'] - $p['precio'])
            ];
        } else {
            // La oferta venció o no está activa: el precio vuelve al original
            return [
                'precio' => (float)$p['precio_original'],
                'tiene_descuento' => false,
                'precio_original' => (float)$p['precio_original'],
                'porcentaje' => 0,
                'ahorro' => 0
            ];
        }
    }

    // Sin descuento configurado o precio_original vacío
    return [
        'precio' => (float)$p['precio'],
        'tiene_descuento' => false,
        'precio_original' => (float)$p['precio'],
        'porcentaje' => 0,
        'ahorro' => 0
    ];
}

?>