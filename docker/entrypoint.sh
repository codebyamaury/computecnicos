#!/bin/bash
# ============================================
# Entrypoint - CompuTécnicos Docker Container
# Configura la conexión a BD usando variables de entorno
# antes de iniciar Apache.
# ============================================
set -e

echo "╔══════════════════════════════════════════╗"
echo "║   CompuTécnicos - Iniciando aplicación   ║"
echo "╚══════════════════════════════════════════╝"

# ─── Generar config/database.php dinámicamente ───
cat > /var/www/html/config/database.php <<DBCONF
<?php
// Configuración de base de datos (generada por Docker entrypoint)
\$host = getenv('DB_HOST') ?: 'db';
\$db   = getenv('DB_NAME') ?: 'computecnicos';
\$user = getenv('DB_USER') ?: 'root';
\$pass = getenv('DB_PASS') ?: '';
\$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try {
    \$pdo = new PDO("mysql:host=\$host;dbname=\$db;charset=utf8mb4", \$user, \$pass, \$options);
} catch (PDOException \$e) {
    die('Error de conexión a la base de datos: ' . \$e->getMessage());
}
DBCONF

echo "✓ Configuración de base de datos generada"
echo "  → Host: ${DB_HOST:-db}"
echo "  → Base de datos: ${DB_NAME:-computecnicos}"
echo "  → Usuario: ${DB_USER:-root}"

# ─── Esperar a que MySQL esté listo ───
echo "⏳ Esperando a que MySQL esté disponible..."
MAX_RETRIES=30
RETRY_COUNT=0
until php -r "
    try {
        new PDO(
            'mysql:host=' . (getenv('DB_HOST') ?: 'db') . ';dbname=' . (getenv('DB_NAME') ?: 'computecnicos') . ';charset=utf8mb4',
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASS') ?: ''
        );
        echo 'OK';
        exit(0);
    } catch (Exception \$e) {
        exit(1);
    }
" 2>/dev/null; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        echo "✗ No se pudo conectar a MySQL después de ${MAX_RETRIES} intentos"
        exit 1
    fi
    echo "  Intento ${RETRY_COUNT}/${MAX_RETRIES}..."
    sleep 2
done

echo "✓ Conexión a MySQL exitosa"

# ─── Asegurar permisos correctos ───
chown -R www-data:www-data /var/www/html/uploads /var/www/html/logs 2>/dev/null || true

echo "✓ Permisos de directorios configurados"
echo "══════════════════════════════════════════"
echo "  Aplicación lista en el puerto 80"
echo "══════════════════════════════════════════"

# ─── Ejecutar el comando principal (Apache) ───
exec "$@"
