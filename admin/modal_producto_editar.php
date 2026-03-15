<?php
// Bootstrap PRIMERO: inicia sesión desde la BD antes de verificar permisos
require_once __DIR__ . '/../app/Core/bootstrap.php';
require_once __DIR__ . '/../app/Core/image_helper.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die('<div class="adm-alert adm-alert-danger">ID inválido</div>');
}
// Obtener producto actual
$stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
$stmt->execute([$id]);
$producto = $stmt->fetch();
if (!$producto) {
    die('<div class="adm-alert adm-alert-danger">Producto no encontrado</div>');
}
// Obtener imágenes actuales del producto
$imagenes = $pdo->prepare('SELECT * FROM imagenes_producto WHERE id_producto = ?');
$imagenes->execute([$id]);
$imagenes = $imagenes->fetchAll();
// Obtener categorías y marcas
$categorias = $pdo->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll();
$marcas = $pdo->query('SELECT id, nombre FROM marcas ORDER BY nombre')->fetchAll();

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
        echo 'Error: Las imágenes seleccionadas superan el límite de tamaño del servidor. Por favor, sube menos imágenes o redúcelas.';
        exit;
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

    // Si se pone fecha de oferta, activar oferta automáticamente
    if ($oferta_hasta && !$oferta) {
        $oferta = 1;
    }

    // Subida de nuevas imágenes — solo PNG permitido
    $nuevas_imagenes = [];
    if (isset($_FILES['imagenes']) && count($_FILES['imagenes']['name']) > 0) {
        foreach ($_FILES['imagenes']['tmp_name'] as $idx => $tmp_name) {
            if ($_FILES['imagenes']['error'][$idx] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/productos/';
                $result = upload_product_image($tmp_name, $upload_dir, base_url(), 'prod_');
                if ($result['ok']) {
                    $nuevas_imagenes[] = $result['url'];
                }
            }
        }
    }
    // Insertar nuevas imágenes en la tabla imagenes_producto
    if (count($nuevas_imagenes) > 0) {
        $stmtImg = $pdo->prepare('INSERT INTO imagenes_producto (id_producto, url_imagen) VALUES (?, ?)');
        foreach ($nuevas_imagenes as $url) {
            $stmtImg->execute([$id, $url]);
        }
        // Si el producto no tiene imagen principal, asignar la primera nueva
        if (empty($producto['imagen'])) {
            $stmt = $pdo->prepare('UPDATE productos SET imagen=? WHERE id=?');
            $stmt->execute([$nuevas_imagenes[0], $id]);
            $producto['imagen'] = $nuevas_imagenes[0];
        }
    }
    // Reemplazar imagen principal si se sube una nueva — solo PNG permitido
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK && !empty($_FILES['imagen']['tmp_name'])) {
        $upload_dir = __DIR__ . '/../uploads/productos/';
        $result = upload_product_image($_FILES['imagen']['tmp_name'], $upload_dir, base_url(), 'main_');
        if ($result['ok']) {
            if (!empty($producto['imagen']) && strpos($producto['imagen'], 'http') !== 0) {
                $ruta_anterior = '../' . $producto['imagen'];
                if (is_file($ruta_anterior)) { @unlink($ruta_anterior); }
            }
            $producto['imagen'] = $result['url'];
        }
    }
    // Eliminar imágenes seleccionadas
    if (!empty($_POST['eliminar_imagen'])) {
        foreach ($_POST['eliminar_imagen'] as $id_img => $val) {
            $stmt = $pdo->prepare('SELECT url_imagen FROM imagenes_producto WHERE id=? AND id_producto=?');
            $stmt->execute([$id_img, $id]);
            $img = $stmt->fetch();
            if ($img) {
                $ruta_fisica = '../' . $img['url_imagen'];
                if (file_exists($ruta_fisica)) unlink($ruta_fisica);
                $pdo->prepare('DELETE FROM imagenes_producto WHERE id=?')->execute([$id_img]);
                if ($producto['imagen'] == $img['url_imagen']) {
                    $stmt2 = $pdo->prepare('SELECT url_imagen FROM imagenes_producto WHERE id_producto=? LIMIT 1');
                    $stmt2->execute([$id]);
                    $nueva_principal = $stmt2->fetchColumn();
                    $pdo->prepare('UPDATE productos SET imagen=? WHERE id=?')->execute([$nueva_principal ?: '', $id]);
                    $producto['imagen'] = $nueva_principal ?: '';
                }
            }
        }
    }
    // Actualizar datos del producto
    if ($nombre && $precio > 0 && $id_categoria && $id_marca) {
        $stmt = $pdo->prepare('UPDATE productos SET nombre=?, descripcion=?, precio=?, stock=?, imagen=?, id_categoria=?, id_marca=?, oferta=?, destacado=?, nuevo_hasta=?, oferta_hasta=? WHERE id=?');
        $stmt->execute([$nombre, $descripcion, $precio, $stock, $producto['imagen'], $id_categoria, $id_marca, $oferta, $destacado, $nuevo_hasta, $oferta_hasta, $id]);
        echo 'success';
        exit;
    } else {
        echo 'Por favor completa todos los campos obligatorios.';
        exit;
    }
}
?>

<div id="modal-edit-bg" class="adm-modal-overlay" onclick="cerrarModalEditarProducto()"></div>
<div id="modal-edit-producto" class="adm-modal hidden">
    <div class="adm-modal-box !w-full !max-w-[700px] !max-h-[90vh] overflow-y-auto !p-6 text-left">
        <button type="button" class="adm-modal-close" onclick="cerrarModalEditarProducto()">&times;</button>
        <div class="adm-modal-title mb-6">Editar Producto: <?= htmlspecialchars($producto['nombre']) ?></div>
        
        <form id="form-editar-producto" onsubmit="guardarEdicionProducto(event, <?= $id ?>)" enctype="multipart/form-data" class="flex flex-col gap-[1.2rem]">
                <!-- Nombre -->
                <div class="adm-form-group">
                    <label class="adm-label">Nombre del producto *</label>
                    <input type="text" name="nombre" class="adm-input" value="<?= htmlspecialchars($producto['nombre']) ?>" required>
                </div>

                <!-- Descripción -->
                <div class="adm-form-group">
                    <label class="adm-label">Descripción</label>
                    <textarea name="descripcion" class="adm-textarea" rows="3"><?= htmlspecialchars($producto['descripcion']) ?></textarea>
                </div>

                <!-- Precio y Stock -->
                <div class="adm-form-row">
                    <div>
                        <label class="adm-label">Precio (COP) *</label>
                        <input type="number" name="precio" min="0" step="1" class="adm-input" value="<?= htmlspecialchars($producto['precio']) ?>" required>
                    </div>
                    <div>
                        <label class="adm-label">Stock *</label>
                        <input type="number" name="stock" min="0" class="adm-input" value="<?= htmlspecialchars($producto['stock']) ?>" required>
                    </div>
                </div>

                <!-- Imagen actual -->
                <div class="adm-form-group">
                    <label class="adm-label">Imagen principal actual</label>
                    <div class="mb-3">
                        <img src="<?= htmlspecialchars((strpos($producto['imagen'], 'http') === 0) ? $producto['imagen'] : '../' . ($producto['imagen'] ?: 'uploads/products/default.png')) ?>" alt="Imagen actual" class="w-[120px] h-20 object-cover rounded-lg border border-[var(--adm-border)]">
                    </div>
                    <label class="adm-label">Cambiar imagen principal (Solo PNG)</label>
                    <input type="file" name="imagen" accept="image/png" class="adm-input !p-2">
                    <div class="adm-form-help">⚠️ Solo se permiten imágenes en formato <strong>PNG</strong>. Ésta será la portada del producto.</div>
                </div>

                <!-- Categoría y Marca -->
                <div class="adm-form-row">
                    <div>
                        <label class="adm-label">Categoría *</label>
                        <select name="id_categoria" class="adm-select" required>
                            <option value="">Selecciona</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?php if($producto['id_categoria']==$cat['id']) echo 'selected'; ?>><?= htmlspecialchars($cat['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="adm-label">Marca *</label>
                        <select name="id_marca" class="adm-select" required>
                            <option value="">Selecciona</option>
                            <?php foreach ($marcas as $marca): ?>
                                <option value="<?= $marca['id'] ?>" <?php if($producto['id_marca']==$marca['id']) echo 'selected'; ?>><?= htmlspecialchars($marca['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Imágenes actuales -->
                <?php if (count($imagenes) > 0): ?>
                <div class="adm-form-group">
                    <label class="adm-label">Galería de imágenes</label>
                    <div class="flex flex-wrap gap-3 mb-3">
                        <?php foreach ($imagenes as $img): ?>
                            <div class="relative">
                                <img src="<?= htmlspecialchars((strpos($img['url_imagen'], 'http') === 0) ? $img['url_imagen'] : '../' . $img['url_imagen']) ?>" alt="img" class="w-[90px] h-[60px] object-cover rounded-lg border border-[var(--adm-border)]">
                                <label class="absolute -top-1.5 -right-1.5 bg-[var(--adm-red)] text-white w-5 h-5 rounded-full flex items-center justify-center cursor-pointer text-[0.75rem] font-bold">
                                    <input type="checkbox" name="eliminar_imagen[<?= $img['id'] ?>]" value="1" class="hidden">
                                    ×
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Agregar nuevas imágenes -->
                <div class="adm-form-group">
                    <label class="adm-label">Agregar nuevas imágenes a la galería (Solo PNG)</label>
                    <input type="file" name="imagenes[]" accept="image/png" class="adm-input !p-2" multiple>
                    <div class="adm-form-help">Puedes seleccionar múltiples imágenes adicionales para la galería del producto.</div>
                </div>

                <!-- Separador visual -->
                <div class="adm-form-section">
                    <h3 class="adm-form-section-title">Visibilidad y Promociones</h3>

                    <!-- Destacado -->
                    <div class="adm-form-group-box">
                        <div class="adm-toggle-wrap">
                            <label class="relative inline-block w-12 h-[26px] shrink-0">
                                <input type="checkbox" name="destacado" value="1" class="opacity-0 w-0 h-0" id="toggle-destacado" <?php if(!empty($producto['destacado'])) echo 'checked'; ?>>
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
                            <?php
                                $nuevo_activo = !empty($producto['nuevo_hasta']) && strtotime($producto['nuevo_hasta']) >= strtotime('today');
                            ?>
                            <?php if ($nuevo_activo): ?>
                                <span class="adm-badge adm-badge-blue ml-auto">ACTIVO</span>
                            <?php elseif (!empty($producto['nuevo_hasta'])): ?>
                                <span class="adm-badge adm-badge-gray ml-auto">VENCIDO</span>
                            <?php endif; ?>
                        </div>
                        <label class="adm-label">Mostrar como nuevo hasta</label>
                        <input type="date" name="nuevo_hasta" class="adm-input" value="<?= htmlspecialchars($producto['nuevo_hasta'] ?? '') ?>">
                        <div class="adm-form-help">Déjalo vacío para que no muestre la badge "NUEVO". El producto mostrará la badge hasta la fecha indicada.</div>
                    </div>

                    <!-- Oferta -->
                    <div class="adm-form-group-box">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-2 h-2 bg-[#ef4444] rounded-full inline-block"></span>
                            <span class="text-[0.88rem] font-semibold text-[#e7e7ea]">Badge "OFERTA"</span>
                            <?php
                                $oferta_activa = $producto['oferta'] && (empty($producto['oferta_hasta']) || strtotime($producto['oferta_hasta']) >= strtotime('today'));
                            ?>
                            <?php if ($oferta_activa): ?>
                                <span class="adm-badge adm-badge-red ml-auto">ACTIVA</span>
                            <?php elseif (!empty($producto['oferta_hasta']) && strtotime($producto['oferta_hasta']) < strtotime('today')): ?>
                                <span class="adm-badge adm-badge-gray ml-auto">VENCIDA</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-3 mb-3">
                            <label class="relative inline-block w-12 h-[26px] shrink-0">
                                <input type="checkbox" name="oferta" value="1" class="opacity-0 w-0 h-0" id="toggle-oferta" <?php if($producto['oferta']) echo 'checked'; ?>>
                                <span class="adm-toggle-slider"></span>
                            </label>
                            <span class="text-[0.85rem] text-[#aaa]">Activar oferta</span>
                        </div>
                        <label class="adm-label">Oferta válida hasta</label>
                        <input type="date" name="oferta_hasta" class="adm-input" value="<?= htmlspecialchars($producto['oferta_hasta'] ?? '') ?>">
                        <div class="adm-form-help">Si pones una fecha, la oferta se desactivará automáticamente al vencer. Déjalo vacío para oferta permanente (mientras esté activada).</div>
                    </div>
                </div>

                <div id="modal-edit-msg" class="hidden text-center text-[#ef4444] text-[0.85rem] mt-2"></div>
                <button type="submit" class="adm-btn adm-btn-primary w-full justify-center !p-[0.85rem] !text-[0.95rem] mt-2">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="adm-btn-icon"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Guardar Cambios
                </button>
            </form>
    </div>
</div>
