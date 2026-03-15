<?php
// Bootstrap PRIMERO: inicia sesión desde la BD antes de verificar permisos
require_once __DIR__ . '/../app/Core/bootstrap.php';
require_once __DIR__ . '/../app/Core/image_helper.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
// Obtener categorías y marcas
$categorias = $pdo->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll();
$marcas = $pdo->query('SELECT id, nombre FROM marcas ORDER BY nombre')->fetchAll();

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $mensaje = 'Error: Las imágenes seleccionadas superan el límite de tamaño del servidor. Por favor, sube menos o de menor peso.';
    }

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
    $imagen_principal = '';

    // Subida de imagen principal (Requerida) — solo formato PNG
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/productos/';
        $result = upload_product_image($_FILES['imagen']['tmp_name'], $upload_dir, base_url(), 'main_');
        if ($result['ok']) {
            $imagen_principal = $result['url'];
        } else {
            $error_imagen = $result['error'];
        }
    } else if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error_imagen = 'Error al subir la imagen principal: ' . $_FILES['imagen']['error'];
    }

    // Subida de imágenes secundarias (Opcional) — solo formato PNG
    if (!$error_imagen && isset($_FILES['imagenes']) && count($_FILES['imagenes']['name']) > 0) {
        foreach ($_FILES['imagenes']['tmp_name'] as $idx => $tmp_name) {
            if ($_FILES['imagenes']['error'][$idx] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/productos/';
                $result = upload_product_image($tmp_name, $upload_dir, base_url(), 'prod_');
                if ($result['ok']) {
                    $imagenes_urls[] = $result['url'];
                } else {
                    $error_imagen = $result['error'];
                    break;
                }
            } else if ($_FILES['imagenes']['error'][$idx] !== UPLOAD_ERR_NO_FILE) {
                $error_imagen = 'Error al subir imagen secundaria: ' . $_FILES['imagenes']['name'][$idx];
                break;
            }
        }
    }

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
            <script>document.addEventListener('DOMContentLoaded', function(){ admToast('<?= addslashes($mensaje) ?>', '<?= strpos($mensaje, "correctamente") !== false ? "success" : "error" ?>'); });</script>
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
                        <input type="number" name="precio" min="0" step="1" class="adm-input" required>
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

                <!-- Imagene Principal y Galería -->
                <div class="adm-form-group">
                    <label class="adm-label">Imagen principal (Solo PNG) *</label>
                    <input type="file" name="imagen" accept="image/png" class="adm-input !p-2" required>
                    <div class="adm-form-help">⚠️ Solo se permiten imágenes en formato <strong>PNG</strong>. Ésta será la portada del producto.</div>
                </div>

                <div class="adm-form-group">
                    <label class="adm-label">Agregar nuevas imágenes a la galería (Solo PNG, Opcional)</label>
                    <input type="file" name="imagenes[]" accept="image/png" class="adm-input !p-2" multiple>
                    <div class="adm-form-help">Puedes seleccionar varias imágenes para la galería del producto.</div>
                </div>

                <div class="adm-form-section">
                    <h3 class="adm-form-section-title">Visibilidad y Promociones</h3>

                    <!-- Destacado -->
                    <div class="adm-form-group-box">
                        <div class="adm-toggle-wrap">
                            <label class="relative inline-block w-12 h-[26px] shrink-0">
                                <input type="checkbox" name="destacado" value="1" class="opacity-0 w-0 h-0" id="toggle-destacado">
                                <span class="adm-toggle-slider"></span>
                            </label>
                            <div class="adm-toggle-info">
                                <div class="adm-toggle-title">Producto Destacado</div>
                                <div class="adm-toggle-desc">Se mostrará en la sección "Productos Destacados" de la página principal</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tiempo como Nuevo -->
                    <div class="adm-form-group-box">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-2 h-2 bg-[#3b82f6] rounded-full inline-block"></span>
                            <span class="text-[0.88rem] font-semibold text-[#e7e7ea]">Badge "NUEVO"</span>
                        </div>
                        <label class="adm-label">Mostrar como nuevo hasta</label>
                        <input type="date" name="nuevo_hasta" class="adm-input" min="<?= date('Y-m-d') ?>">
                        <div class="adm-form-help">Déjalo vacío para que no muestre la badge "NUEVO". El producto mostrará la badge hasta la fecha indicada.</div>
                    </div>

                    <!-- Oferta -->
                    <div class="adm-form-group-box">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-2 h-2 bg-[#ef4444] rounded-full inline-block"></span>
                            <span class="text-[0.88rem] font-semibold text-[#e7e7ea]">Badge "OFERTA"</span>
                        </div>
                        <div class="flex items-center gap-3 mb-3">
                            <label class="relative inline-block w-12 h-[26px] shrink-0">
                                <input type="checkbox" name="oferta" value="1" class="opacity-0 w-0 h-0" id="toggle-oferta">
                                <span class="adm-toggle-slider"></span>
                            </label>
                            <span class="text-[0.85rem] text-[#aaa]">Activar oferta</span>
                        </div>
                        <label class="adm-label">Oferta válida hasta</label>
                        <input type="date" name="oferta_hasta" class="adm-input" min="<?= date('Y-m-d') ?>">
                        <div class="adm-form-help">Si pones una fecha, la oferta se desactivará automáticamente al vencer. Déjalo vacío para oferta permanente (mientras esté activada).</div>
                    </div>
                </div>

                <button type="submit" class="adm-btn adm-btn-primary w-full justify-center !p-[0.85rem] !text-[0.95rem] mt-2">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="adm-btn-icon"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Guardar Producto
                </button>
            </form>
        </div>
    </div>
</main>



<?php include '_layout_end.php'; ?>