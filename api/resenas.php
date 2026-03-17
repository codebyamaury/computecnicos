<?php
/**
 * API de Reseñas — Computécnicos
 * 
 * POST /api/resenas.php  → Crear reseña (con imágenes opcionales)
 * GET  /api/resenas.php?id_producto=X  → Obtener reseñas de un producto
 * 
 * Solo pueden crear reseñas usuarios que hayan comprado el producto
 * (pedido con estado pagado, preparacion, enviado o entregado).
 */

require_once __DIR__ . '/../app/Core/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// ── Auto-crear tablas si no existen ──
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

    $pdo->exec("CREATE TABLE IF NOT EXISTS resenas_imagenes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_resena INT NOT NULL,
        url_imagen VARCHAR(500) NOT NULL,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_resena) REFERENCES resenas(id) ON DELETE CASCADE,
        INDEX idx_resena (id_resena)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    // Tablas probablemente ya existen
}

$method = $_SERVER['REQUEST_METHOD'];

// ══════════════════════════════════════════════════
// GET — Obtener reseñas de un producto
// ══════════════════════════════════════════════════
if ($method === 'GET') {
    $idProducto = intval($_GET['id_producto'] ?? 0);
    if ($idProducto <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'id_producto requerido']);
        exit;
    }

    // Obtener reseñas con datos del usuario
    $stmt = $pdo->prepare("
        SELECT r.id, r.id_producto, r.id_usuario, r.calificacion, r.titulo, r.comentario, 
               r.fecha, r.verificado, u.nombre AS usuario, u.foto AS usuario_foto
        FROM resenas r
        LEFT JOIN usuarios u ON r.id_usuario = u.id
        WHERE r.id_producto = ?
        ORDER BY r.fecha DESC
    ");
    $stmt->execute([$idProducto]);
    $resenas = $stmt->fetchAll();

    // Obtener imágenes de cada reseña
    foreach ($resenas as &$r) {
        $stmtImg = $pdo->prepare("SELECT id, url_imagen FROM resenas_imagenes WHERE id_resena = ?");
        $stmtImg->execute([$r['id']]);
        $r['imagenes'] = $stmtImg->fetchAll();
    }
    unset($r);

    // Calcular estadísticas
    $totalResenas = count($resenas);
    $avgRating = 0;
    $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    if ($totalResenas > 0) {
        $sum = 0;
        foreach ($resenas as $r) {
            $sum += intval($r['calificacion']);
            $cal = intval($r['calificacion']);
            if ($cal >= 1 && $cal <= 5) $distribution[$cal]++;
        }
        $avgRating = round($sum / $totalResenas, 1);
    }

    // Verificar si el usuario actual puede dejar reseña
    $puedeResenar = false;
    $yaReseno = false;
    if (isset($_SESSION['usuario'])) {
        $userId = intval($_SESSION['usuario']['id']);
        
        // ¿Ya dejó reseña?
        $chk = $pdo->prepare("SELECT id FROM resenas WHERE id_producto = ? AND id_usuario = ?");
        $chk->execute([$idProducto, $userId]);
        $yaReseno = (bool) $chk->fetch();

        if (!$yaReseno) {
            // ¿Compró el producto? (pedido pagado, preparacion, enviado o entregado)
            $chkCompra = $pdo->prepare("
                SELECT p.id FROM pedidos p
                INNER JOIN detalle_pedido dp ON dp.id_pedido = p.id
                WHERE p.id_usuario = ? AND dp.id_producto = ?
                  AND p.estado IN ('pagado','preparacion','enviado','entregado')
                LIMIT 1
            ");
            $chkCompra->execute([$userId, $idProducto]);
            $puedeResenar = (bool) $chkCompra->fetch();
        }
    }

    echo json_encode([
        'ok' => true,
        'resenas' => $resenas,
        'stats' => [
            'total' => $totalResenas,
            'promedio' => $avgRating,
            'distribucion' => $distribution
        ],
        'puede_resenar' => $puedeResenar,
        'ya_reseno' => $yaReseno
    ]);
    exit;
}

// ══════════════════════════════════════════════════
// POST — Crear reseña
// ══════════════════════════════════════════════════
if ($method === 'POST') {
    // Verificar sesión
    if (!isset($_SESSION['usuario'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'Debes iniciar sesión para dejar una reseña.']);
        exit;
    }

    $userId = intval($_SESSION['usuario']['id']);
    $idProducto = intval($_POST['id_producto'] ?? 0);
    $calificacion = intval($_POST['calificacion'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $comentario = trim($_POST['comentario'] ?? '');

    // Validaciones
    if ($idProducto <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Producto inválido.']);
        exit;
    }
    if ($calificacion < 1 || $calificacion > 5) {
        echo json_encode(['ok' => false, 'msg' => 'La calificación debe ser entre 1 y 5 estrellas.']);
        exit;
    }
    if (empty($comentario) || mb_strlen($comentario) < 10) {
        echo json_encode(['ok' => false, 'msg' => 'El comentario debe tener al menos 10 caracteres.']);
        exit;
    }
    if (mb_strlen($comentario) > 2000) {
        echo json_encode(['ok' => false, 'msg' => 'El comentario no puede exceder 2000 caracteres.']);
        exit;
    }
    if (mb_strlen($titulo) > 150) {
        echo json_encode(['ok' => false, 'msg' => 'El título no puede exceder 150 caracteres.']);
        exit;
    }

    // Verificar que NO haya dejado reseña ya
    $chk = $pdo->prepare("SELECT id FROM resenas WHERE id_producto = ? AND id_usuario = ?");
    $chk->execute([$idProducto, $userId]);
    if ($chk->fetch()) {
        echo json_encode(['ok' => false, 'msg' => 'Ya dejaste una reseña para este producto.']);
        exit;
    }

    // Verificar que haya COMPRADO el producto
    $chkCompra = $pdo->prepare("
        SELECT p.id FROM pedidos p
        INNER JOIN detalle_pedido dp ON dp.id_pedido = p.id
        WHERE p.id_usuario = ? AND dp.id_producto = ?
          AND p.estado IN ('pagado','preparacion','enviado','entregado')
        LIMIT 1
    ");
    $chkCompra->execute([$userId, $idProducto]);
    if (!$chkCompra->fetch()) {
        echo json_encode(['ok' => false, 'msg' => 'Solo puedes dejar reseña de productos que hayas comprado.']);
        exit;
    }

    // Insertar reseña
    try {
        $pdo->beginTransaction();

        $stmtIns = $pdo->prepare("
            INSERT INTO resenas (id_producto, id_usuario, calificacion, titulo, comentario, verificado)
            VALUES (?, ?, ?, ?, ?, 1)
        ");
        $stmtIns->execute([$idProducto, $userId, $calificacion, $titulo ?: null, $comentario]);
        $resenaId = $pdo->lastInsertId();

        // Procesar imágenes (hasta 3)
        $imagenesGuardadas = [];
        $uploadDir = BASE_PATH . '/uploads/resenas/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        if (!empty($_FILES['imagenes'])) {
            $files = $_FILES['imagenes'];
            $maxFiles = min(count($files['name']), 3);
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            for ($i = 0; $i < $maxFiles; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                if ($files['size'][$i] > $maxSize) continue;
                
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($files['tmp_name'][$i]);
                if (!in_array($mime, $allowedTypes)) continue;

                $ext = match($mime) {
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                    'image/gif'  => 'gif',
                    default => 'jpg'
                };

                $filename = 'resena_' . $resenaId . '_' . ($i + 1) . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $filepath = $uploadDir . $filename;

                if (move_uploaded_file($files['tmp_name'][$i], $filepath)) {
                    $urlRelativa = 'uploads/resenas/' . $filename;
                    $stmtImgIns = $pdo->prepare("INSERT INTO resenas_imagenes (id_resena, url_imagen) VALUES (?, ?)");
                    $stmtImgIns->execute([$resenaId, $urlRelativa]);
                    $imagenesGuardadas[] = $urlRelativa;
                }
            }
        }

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'msg' => '¡Gracias por tu reseña!',
            'resena_id' => $resenaId,
            'imagenes' => $imagenesGuardadas
        ]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        log_event('Error creando reseña: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'msg' => 'Error al guardar la reseña. Intenta de nuevo.']);
    }
    exit;
}

// Otro método
http_response_code(405);
echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
