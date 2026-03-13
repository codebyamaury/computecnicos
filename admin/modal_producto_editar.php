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
    $precio = floatval(str_replace(',', '.', $_POST['precio'] ?? 0));
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
    <div class="adm-modal-box" style="width:100%;max-width:700px;max-height:90vh;overflow-y:auto;padding:1.5rem;text-align:left">
        <button type="button" class="adm-modal-close" onclick="cerrarModalEditarProducto()">&times;</button>
        <div class="adm-modal-title" style="margin-bottom:1.5rem">Editar Producto: <?= htmlspecialchars($producto['nombre']) ?></div>
        
        <form id="form-editar-producto" onsubmit="guardarEdicionProducto(event, <?= $id ?>)" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:1.2rem">
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
                        <input type="text" inputmode="decimal" pattern="[0-9,\.]+" name="precio" class="adm-input" value="<?= htmlspecialchars($producto['precio']) ?>" required>
                    </div>
                    <div>
                        <label class="adm-label">Stock *</label>
                        <input type="number" name="stock" min="0" class="adm-input" value="<?= htmlspecialchars($producto['stock']) ?>" required>
                    </div>
                </div>

                <!-- Imagen actual -->
                <div class="adm-form-group">
                    <label class="adm-label">Imagen principal actual</label>
                    <div style="margin-bottom:0.75rem">
                        <img src="<?= htmlspecialchars((strpos($producto['imagen'], 'http') === 0) ? $producto['imagen'] : '../' . ($producto['imagen'] ?: 'uploads/products/default.png')) ?>" alt="Imagen actual" style="width:120px;height:80px;object-fit:cover;border-radius:0.5rem;border:1px solid var(--adm-border)">
                    </div>
                    <label class="adm-label">Cambiar imagen principal (Solo PNG)</label>
                    <input type="file" name="imagen" accept="image/png" class="adm-input" style="padding:0.5rem">
                    <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">⚠️ Solo se permiten imágenes en formato <strong style="color:#fff">PNG</strong>. Ésta será la portada del producto.</div>
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
                    <div style="display:flex;flex-wrap:wrap;gap:0.75rem;margin-bottom:0.75rem">
                        <?php foreach ($imagenes as $img): ?>
                            <div style="position:relative">
                                <img src="<?= htmlspecialchars((strpos($img['url_imagen'], 'http') === 0) ? $img['url_imagen'] : '../' . $img['url_imagen']) ?>" alt="img" style="width:90px;height:60px;object-fit:cover;border-radius:0.5rem;border:1px solid var(--adm-border)">
                                <label style="position:absolute;top:-6px;right:-6px;background:var(--adm-red);color:#fff;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.75rem;font-weight:700">
                                    <input type="checkbox" name="eliminar_imagen[<?= $img['id'] ?>]" value="1" style="display:none">
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
                    <input type="file" name="imagenes[]" accept="image/png" class="adm-input" multiple style="padding:0.5rem">
                    <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Puedes seleccionar múltiples imágenes adicionales para la galería del producto.</div>
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
                            <input type="checkbox" name="destacado" value="1" style="opacity:0;width:0;height:0" id="toggle-destacado" <?php if(!empty($producto['destacado'])) echo 'checked'; ?>>
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
                            <?php
                                $nuevo_activo = !empty($producto['nuevo_hasta']) && strtotime($producto['nuevo_hasta']) >= strtotime('today');
                            ?>
                            <?php if ($nuevo_activo): ?>
                                <span class="adm-badge adm-badge-blue" style="margin-left:auto">ACTIVO</span>
                            <?php elseif (!empty($producto['nuevo_hasta'])): ?>
                                <span class="adm-badge adm-badge-gray" style="margin-left:auto">VENCIDO</span>
                            <?php endif; ?>
                        </div>
                        <label class="adm-label">Mostrar como nuevo hasta</label>
                        <input type="date" name="nuevo_hasta" class="adm-input" value="<?= htmlspecialchars($producto['nuevo_hasta'] ?? '') ?>">
                        <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Déjalo vacío para que no muestre la badge "NUEVO". El producto mostrará la badge hasta la fecha indicada.</div>
                    </div>

                    <!-- Oferta -->
                    <div class="adm-form-group" style="padding:1rem;background:rgba(255,255,255,0.03);border-radius:0.75rem;border:1px solid var(--adm-border)">
                        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                            <span style="width:8px;height:8px;background:#ef4444;border-radius:50%;display:inline-block"></span>
                            <span style="font-size:0.88rem;font-weight:600;color:#e7e7ea">Badge "OFERTA"</span>
                            <?php
                                $oferta_activa = $producto['oferta'] && (empty($producto['oferta_hasta']) || strtotime($producto['oferta_hasta']) >= strtotime('today'));
                            ?>
                            <?php if ($oferta_activa): ?>
                                <span class="adm-badge adm-badge-red" style="margin-left:auto">ACTIVA</span>
                            <?php elseif (!empty($producto['oferta_hasta']) && strtotime($producto['oferta_hasta']) < strtotime('today')): ?>
                                <span class="adm-badge adm-badge-gray" style="margin-left:auto">VENCIDA</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem">
                            <label style="position:relative;display:inline-block;width:48px;height:26px;flex-shrink:0">
                                <input type="checkbox" name="oferta" value="1" style="opacity:0;width:0;height:0" id="toggle-oferta" <?php if($producto['oferta']) echo 'checked'; ?>>
                                <span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.08);border-radius:13px;transition:0.3s" id="slider-oferta"></span>
                            </label>
                            <span style="font-size:0.85rem;color:#aaa">Activar oferta</span>
                        </div>
                        <label class="adm-label">Oferta válida hasta</label>
                        <input type="date" name="oferta_hasta" class="adm-input" value="<?= htmlspecialchars($producto['oferta_hasta'] ?? '') ?>">
                        <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Si pones una fecha, la oferta se desactivará automáticamente al vencer. Déjalo vacío para oferta permanente (mientras esté activada).</div>
                    </div>
                </div>

                <div id="modal-edit-msg" style="display:none;text-align:center;color:#ef4444;font-size:0.85rem;margin-top:0.5rem"></div>
                <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;padding:0.85rem;font-size:0.95rem;margin-top:0.5rem">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Guardar Cambios
                </button>
            </form>
    </div>
</div>

<style>
    /* Toggle switch styling override for modals if needed */
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