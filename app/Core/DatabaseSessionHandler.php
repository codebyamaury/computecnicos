<?php
/**
 * SessionHandler personalizado para guardar sesiones en MySQL/MariaDB.
 * Necesario para entornos serverless (Vercel) donde el sistema de archivos
 * no persiste entre requests.
 */
class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    /**
     * Leer datos de sesión desde la base de datos.
     */
    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT data FROM sessions WHERE id = ? AND expires_at > NOW()'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['data'] : '';
    }

    /**
     * Escribir datos de sesión a la base de datos.
     */
    public function write(string $id, string $data): bool
    {
        $lifetime = (int) ini_get('session.gc_maxlifetime');
        if ($lifetime < 1) {
            $lifetime = 1440; // 24 minutos por defecto
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (id, data, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)'
        );
        return $stmt->execute([$id, $data, $lifetime]);
    }

    /**
     * Destruir una sesión específica.
     */
    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Garbage collection: eliminar sesiones expiradas.
     */
    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE expires_at < NOW()');
        $stmt->execute();
        return $stmt->rowCount();
    }
}
