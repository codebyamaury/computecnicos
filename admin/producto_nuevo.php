<?php
// Bootstrap PRIMERO: inicia sesión desde la BD antes de verificar permisos
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
// Obtener categorías y marcas
$categorias = $pdo->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll();
$marcas = $pdo->query('SELECT id, nombre FROM marcas ORDER BY nombre')->fetchAll();

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $precio = floatval($_POST['precio'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $id_categoria = intval($_POST['id_categoria'] ?? 0);
    $id_marca = intval($_POST['id_marca'] ?? 0);
    $oferta = isset($_POST['oferta']) ? 1 : 0;
    $destacado = isset($_POST['destacado']) ? 1 : 0;
    $nuevo_hasta = !empty($_POST['nuevo_hasta']) ? $_POST['nuevo_hasta'] : null;
    $oferta_hasta = !empty($_POST['oferta_hasta']) ? $_POST['oferta_hasta'] : null;

    // Si se marca oferta pero no se pone fecha, oferta_hasta queda null (oferta permanente)
    // Si se pone fecha de oferta, se activa oferta automáticamente
    if ($oferta_hasta && !$oferta) {
        $oferta = 1;
    }

    $imagenes_urls = [];
    $error_imagen = '';
    // Subida de imágenes múltiples
    if (isset($_FILES['imagenes']) && count($_FILES['imagenes']['name']) > 0) {
        foreach ($_FILES['imagenes']['tmp_name'] as $idx => $tmp_name) {
            if ($_FILES['imagenes']['error'][$idx] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['imagenes']['name'][$idx], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                    $error_imagen = 'Formato de imagen no permitido: ' . htmlspecialchars($ext);
                    break;
                }
                // Subir a Imgur API de forma anónima para evitar fallos de Vercel (Read-Only)
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.imgur.com/3/image');
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Client-ID 546c25a59c58ad7']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, [
                    'image' => base64_encode(file_get_contents($tmp_name)),
                    'type'  => 'base64'
                ]);
                $reply = json_decode(curl_exec($ch));
                curl_close($ch);
                
                if (isset($reply->data->link)) {
                    $imagenes_urls[] = $reply->data->link;
                } else {
                    $error_imagen = 'Imgur Error: ' . ($reply->data->error->message ?? json_encode($reply->data->error ?? 'Desconocido'));
                    break;
                }
            } else if ($_FILES['imagenes']['error'][$idx] !== UPLOAD_ERR_NO_FILE) {
                $error_imagen = 'Error al subir la imagen: ' . $_FILES['imagenes']['name'][$idx];
                break;
            }
        }
    }
    $imagen_principal = $imagenes_urls[0] ?? '';
    if ($error_imagen) {
        $mensaje = $error_imagen;
    } else if ($nombre && $precio > 0 && $id_categoria && $id_marca && $imagen_principal) {
        try {
            $stmt = $pdo->prepare('INSERT INTO productos (nombre, descripcion, precio, stock, imagen, id_categoria, id_marca, oferta, destacado, nuevo_hasta, oferta_hasta, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$nombre, $descripcion, $precio, $stock, $imagen_principal, $id_categoria, $id_marca, $oferta, $destacado, $nuevo_hasta, $oferta_hasta]);
            $id_producto = $pdo->lastInsertId();
            // Guardar todas las imágenes en imagenes_producto
            if ($id_producto && count($imagenes_urls) > 0) {
                $stmtImg = $pdo->prepare('INSERT INTO imagenes_producto (id_producto, url_imagen) VALUES (?, ?)');
                foreach ($imagenes_urls as $url) {
                    $stmtImg->execute([$id_producto, $url]);
                }
            }
            $mensaje = 'Producto agregado correctamente.';
        } catch (Exception $e) {
            $mensaje = 'Error de base de datos: ' . $e->getMessage();
        }
    } else {
        if (!$imagen_principal) {
            $mensaje = 'Debes subir al menos una imagen válida.';
        } else {
            $mensaje = 'Por favor completa todos los campos obligatorios.';
        }
    }
}

// Variables para el layout del admin
$page_title = 'Agregar Producto | Computécnicos';
$admin_page = 'productos';
$admin_title = 'Nuevo Producto';
$admin_breadcrumb = [['label' => 'Productos', 'href' => 'productos.php'], ['label' => 'Nuevo']];
$admin_header_extra = '<a href="productos.php" class="adm-btn adm-btn-secondary">← Volver a productos</a>';

include '_layout.php';
?>

<main class="admin-content">
    <div class="admin-content-inner">
        <?php if ($mensaje): ?>
            <div class="adm-alert <?= strpos($mensaje, 'correctamente') !== false ? 'adm-alert-success' : 'adm-alert-error' ?>">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <div class="adm-form">
            <form method="post" enctype="multipart/form-data">
                <!-- Nombre -->
                <div class="adm-form-group">
                    <label class="adm-label">Nombre del producto *</label>
                    <input type="text" name="nombre" class="adm-input" placeholder="Ej: ASUS ROG Strix G16" required>
                </div>

                <!-- Descripción -->
                <div class="adm-form-group">
                    <label class="adm-label">Descripción</label>
                    <textarea name="descripcion" class="adm-textarea" rows="3" placeholder="Descripción detallada del producto..."></textarea>
                </div>

                <!-- Precio y Stock -->
                <div class="adm-form-row">
                    <div>
                        <label class="adm-label">Precio (COP) *</label>
                        <input type="number" name="precio" min="0" step="0.01" class="adm-input" required>
                    </div>
                    <div>
                        <label class="adm-label">Stock *</label>
                        <input type="number" name="stock" min="0" class="adm-input" required>
                    </div>
                </div>

                <!-- Categoría y Marca -->
                <div class="adm-form-row">
                    <div>
                        <label class="adm-label">Categoría *</label>
                        <select name="id_categoria" class="adm-select" required>
                            <option value="">Selecciona</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="adm-label">Marca *</label>
                        <select name="id_marca" class="adm-select" required>
                            <option value="">Selecciona</option>
                            <?php foreach ($marcas as $marca): ?>
                                <option value="<?= $marca['id'] ?>"><?= htmlspecialchars($marca['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Imágenes -->
                <div class="adm-form-group">
                    <label class="adm-label">Imágenes (puedes seleccionar varias) *</label>
                    <input type="file" name="imagenes[]" accept="image/*" class="adm-input" multiple required style="padding:0.5rem">
                </div>

                <!-- Separador visual -->
                <div style="border-top:1px solid var(--adm-border);margin:1.5rem 0;padding-top:1.5rem">
                    <h3 style="color:#fff;font-size:0.95rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:8px">
                        <span style="width:3px;height:16px;background:var(--adm-red);border-radius:2px;display:inline-block"></span>
                        Visibilidad y Promociones
                    </h3>

                    <!-- Destacado -->
                    <div class="adm-form-group" style="display:flex;align-items:center;gap:0.75rem;padding:1rem;background:rgba(255,255,255,0.03);border-radius:0.75rem;border:1px solid var(--adm-border)">
                        <label style="position:relative;display:inline-block;width:48px;height:26px;flex-shrink:0">
                            <input type="checkbox" name="destacado" value="1" style="opacity:0;width:0;height:0" id="toggle-destacado">
                            <span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.08);border-radius:13px;transition:0.3s" id="slider-destacado"></span>
                        </label>
                        <div>
                            <div style="font-size:0.88rem;font-weight:600;color:#e7e7ea">Producto Destacado</div>
                            <div style="font-size:0.72rem;color:#666">Se mostrará en la sección "Productos Destacados" de la página principal</div>
                        </div>
                    </div>

                    <!-- Tiempo como Nuevo -->
                    <div class="adm-form-group" style="padding:1rem;background:rgba(255,255,255,0.03);border-radius:0.75rem;border:1px solid var(--adm-border)">
                        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                            <span style="width:8px;height:8px;background:#3b82f6;border-radius:50%;display:inline-block"></span>
                            <span style="font-size:0.88rem;font-weight:600;color:#e7e7ea">Badge "NUEVO"</span>
                        </div>
                        <label class="adm-label">Mostrar como nuevo hasta</label>
                        <input type="date" name="nuevo_hasta" class="adm-input" min="<?= date('Y-m-d') ?>">
                        <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Déjalo vacío para que no muestre la badge "NUEVO". El producto mostrará la badge hasta la fecha indicada.</div>
                    </div>

                    <!-- Oferta -->
                    <div class="adm-form-group" style="padding:1rem;background:rgba(255,255,255,0.03);border-radius:0.75rem;border:1px solid var(--adm-border)">
                        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                            <span style="width:8px;height:8px;background:#ef4444;border-radius:50%;display:inline-block"></span>
                            <span style="font-size:0.88rem;font-weight:600;color:#e7e7ea">Badge "OFERTA"</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem">
                            <label style="position:relative;display:inline-block;width:48px;height:26px;flex-shrink:0">
                                <input type="checkbox" name="oferta" value="1" style="opacity:0;width:0;height:0" id="toggle-oferta">
                                <span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.08);border-radius:13px;transition:0.3s" id="slider-oferta"></span>
                            </label>
                            <span style="font-size:0.85rem;color:#aaa">Activar oferta</span>
                        </div>
                        <label class="adm-label">Oferta válida hasta</label>
                        <input type="date" name="oferta_hasta" class="adm-input" min="<?= date('Y-m-d') ?>">
                        <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Si pones una fecha, la oferta se desactivará automáticamente al vencer. Déjalo vacío para oferta permanente (mientras esté activada).</div>
                    </div>
                </div>

                <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;padding:0.85rem;font-size:0.95rem;margin-top:0.5rem">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Guardar Producto
                </button>
            </form>
        </div>
    </div>
</main>

<style>
    /* Toggle switch styling */
    #toggle-destacado:checked + #slider-destacado,
    #toggle-oferta:checked + #slider-oferta {
        background: var(--adm-red) !important;
    }
    #toggle-destacado:checked + #slider-destacado::before,
    #toggle-oferta:checked + #slider-oferta::before {
        transform: translateX(22px);
    }
    #slider-destacado::before,
    #slider-oferta::before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        border-radius: 50%;
        transition: 0.3s;
    }
</style>

<?php include '_layout_end.php'; ?>