<?php
/**
 * Sistema de "Remember Me" con tokens persistentes.
 * Mantiene la sesión del usuario activa mediante una cookie segura
 * que se valida contra la base de datos.
 *
 * - Los tokens expiran a los 30 dias por defecto.
 * - Al cambiar contraseña se invalidan TODOS los tokens del usuario.
 * - Cada token se usa una sola vez (rotacion automatica para mayor seguridad).
 */
class RememberMe
{
    private PDO $pdo;

    // Duracion de la cookie en segundos (30 dias)
    const TOKEN_LIFETIME = 30 * 24 * 60 * 60;

    // Nombre de la cookie
    const COOKIE_NAME = 'remember_token';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Crear la tabla de tokens si no existe.
     */
    public function ensureTable(): void
    {
        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS remember_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_usuario INT NOT NULL,
                token VARCHAR(128) NOT NULL UNIQUE,
                expira DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token),
                INDEX idx_usuario (id_usuario),
                INDEX idx_expira (expira),
                FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } catch (Throwable $e) {
            // No bloquear la app si hay error menor
            if (function_exists('log_event')) {
                log_event('Aviso tabla remember_tokens: ' . $e->getMessage());
            }
        }
    }

    /**
     * Generar un token seguro, guardarlo en la BD y setear la cookie.
     * Se llama despues de un login exitoso.
     */
    public function createToken(int $userId): void
    {
        // Generar token criptograficamente seguro
        $token = bin2hex(random_bytes(48)); // 96 caracteres hex

        // Calcular fecha de expiracion
        $expira = date('Y-m-d H:i:s', time() + self::TOKEN_LIFETIME);

        // Guardar en la base de datos (hash del token para mayor seguridad)
        $tokenHash = hash('sha256', $token);
        $stmt = $this->pdo->prepare(
            'INSERT INTO remember_tokens (id_usuario, token, expira) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $tokenHash, $expira]);

        // Setear cookie con el token en texto plano (el hash esta en la BD)
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => time() + self::TOKEN_LIFETIME,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly'  => true,
            'samesite'  => 'Lax',
        ]);

        // Limpiar tokens expirados de este usuario (limpieza incremental)
        $this->cleanExpiredTokens($userId);
    }

    /**
     * Intentar restaurar la sesion desde la cookie remember_token.
     * Retorna true si se restauro la sesion exitosamente.
     */
    public function tryRestore(): bool
    {
        // Si ya hay sesion activa, no hacer nada
        if (isset($_SESSION['usuario'])) {
            return true;
        }

        // Verificar si existe la cookie
        $token = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (empty($token)) {
            return false;
        }

        // Buscar el token en la BD (comparar con hash)
        $tokenHash = hash('sha256', $token);
        $stmt = $this->pdo->prepare(
            'SELECT rt.id AS token_id, rt.id_usuario, u.id, u.nombre, u.email, u.rol, u.foto
             FROM remember_tokens rt
             INNER JOIN usuarios u ON u.id = rt.id_usuario
             WHERE rt.token = ? AND rt.expira > NOW()
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            // Token invalido o expirado: limpiar cookie
            $this->clearCookie();
            return false;
        }

        // Restaurar sesion con los datos del usuario
        $_SESSION['usuario'] = [
            'id'     => $result['id'],
            'nombre' => $result['nombre'],
            'email'  => $result['email'],
            'rol'    => $result['rol'],
            'foto'   => $result['foto'],
        ];

        // Rotacion de token: eliminar el actual y crear uno nuevo
        // Esto previene ataques de robo de cookie (cada token se usa una sola vez)
        $this->deleteToken($result['token_id']);
        $this->createToken($result['id']);

        if (function_exists('log_event')) {
            log_event('Sesion restaurada via remember_token para usuario ID: ' . $result['id']);
        }

        return true;
    }

    /**
     * Eliminar un token especifico por su ID.
     */
    private function deleteToken(int $tokenId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM remember_tokens WHERE id = ?');
        $stmt->execute([$tokenId]);
    }

    /**
     * Invalidar TODOS los tokens de un usuario.
     * Se llama cuando cambia de contraseña o restablece contraseña.
     */
    public function invalidateAllTokens(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM remember_tokens WHERE id_usuario = ?');
        $stmt->execute([$userId]);

        // Limpiar la cookie del navegador actual
        $this->clearCookie();
    }

    /**
     * Invalidar el token actual (logout).
     */
    public function invalidateCurrentToken(): void
    {
        $token = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (!empty($token)) {
            $tokenHash = hash('sha256', $token);
            $stmt = $this->pdo->prepare('DELETE FROM remember_tokens WHERE token = ?');
            $stmt->execute([$tokenHash]);
        }

        $this->clearCookie();
    }

    /**
     * Limpiar la cookie del navegador.
     */
    private function clearCookie(): void
    {
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly'  => true,
            'samesite'  => 'Lax',
        ]);

        // Remover de $_COOKIE para esta request
        unset($_COOKIE[self::COOKIE_NAME]);
    }

    /**
     * Limpiar tokens expirados de un usuario (o de todos si no se pasa ID).
     */
    public function cleanExpiredTokens(?int $userId = null): void
    {
        try {
            if ($userId !== null) {
                $stmt = $this->pdo->prepare(
                    'DELETE FROM remember_tokens WHERE id_usuario = ? AND expira < NOW()'
                );
                $stmt->execute([$userId]);
            } else {
                $this->pdo->exec('DELETE FROM remember_tokens WHERE expira < NOW()');
            }
        } catch (Throwable $e) {
            // No bloquear por error de limpieza
        }
    }
}
