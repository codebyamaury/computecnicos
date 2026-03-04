<?php
require_once __DIR__ . '/../app/Core/bootstrap.php';

echo "Conectado a: " . ($_ENV['DB_HOST'] ?? 'localhost') . "\n";

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS resenas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_producto INT NOT NULL,
        id_usuario INT NOT NULL,
        calificacion TINYINT NOT NULL DEFAULT 5,
        titulo VARCHAR(150) NULL,
        comentario TEXT NULL,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        verificado TINYINT(1) DEFAULT 1,
        FOREIGN KEY (id_producto) REFERENCES productos(id) ON DELETE CASCADE,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_resena_usuario_producto (id_usuario, id_producto),
        INDEX idx_producto (id_producto),
        INDEX idx_fecha (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Tabla 'resenas' creada OK\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS resenas_imagenes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_resena INT NOT NULL,
        url_imagen VARCHAR(500) NOT NULL,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_resena) REFERENCES resenas(id) ON DELETE CASCADE,
        INDEX idx_resena (id_resena)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Tabla 'resenas_imagenes' creada OK\n";

    // Verificar
    $tables = $pdo->query("SHOW TABLES LIKE 'resenas%'")->fetchAll();
    echo "\nTablas verificadas:\n";
    foreach ($tables as $t) {
        echo "  - " . array_values($t)[0] . "\n";
    }
    echo "\nListo!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
