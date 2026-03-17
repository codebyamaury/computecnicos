<?php
// ═══════════════════════════════════════════════════════════
// MODAL EDITAR PRODUCTO — Bootstrap, Auth y Datos
// ═══════════════════════════════════════════════════════════
require_once __DIR__ . '/../app/Core/bootstrap.php';
require_once __DIR__ . '/../app/Core/image_helper.php';
require_once __DIR__ . '/../app/Core/video_helper.php';

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

// ═══════════════════════════════════════════════════════════
// PROCESAMIENTO DEL FORMULARIO (POST)
// ═══════════════════════════════════════════════════════════
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Protección: post_max_size excedido ──
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        echo 'Error: El archivo supera el límite del servidor. Sube un video más liviano (máx 100MB) o contacta al administrador para aumentar el límite en php.ini.';
        exit;
    }

    // ── 1. Recoger datos del formulario ──
    $nombre      = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $precio_normal       = floatval($_POST['precio_normal'] ?? 0);
    $precio_descuento    = !empty($_POST['precio_descuento']) ? floatval($_POST['precio_descuento']) : null;
    $porcentaje_descuento = !empty($_POST['porcentaje_descuento']) ? floatval($_POST['porcentaje_descuento']) : null;
    $stock         = intval($_POST['stock'] ?? 0);
    $id_categoria  = intval($_POST['id_categoria'] ?? 0);
    $id_marca      = intval($_POST['id_marca'] ?? 0);
    $oferta        = isset($_POST['oferta']) ? 1 : 0;
    $destacado     = isset($_POST['destacado']) ? 1 : 0;
    $nuevo_hasta   = !empty($_POST['nuevo_hasta']) ? $_POST['nuevo_hasta'] : null;
    $oferta_hasta  = !empty($_POST['oferta_hasta']) ? $_POST['oferta_hasta'] : null;
    $eliminar_video = isset($_POST['eliminar_video']) ? 1 : 0;

    // ── 2. Calcular precio y descuento ──
    $precio_original = null;
    $descuento = null;
    $precio = $precio_normal;

    // Si el usuario DESACTIVÓ la oferta, limpiar todo descuento
    if ($oferta == 0) {
        $precio = $precio_normal;
        $precio_original = null;
        $descuento = null;
    } else {
        // Oferta activa: calcular descuento si hay porcentaje
        if ($porcentaje_descuento !== null && $porcentaje_descuento > 0 && $porcentaje_descuento < 100 && $precio_normal > 0) {
            $precio_descuento = round($precio_normal * (1 - $porcentaje_descuento / 100));
        }

        if ($precio_descuento !== null && $precio_descuento > 0 && $precio_descuento < $precio_normal) {
            $precio = $precio_descuento;
            $precio_original = $precio_normal;
            $descuento = round((($precio_normal - $precio_descuento) / $precio_normal) * 100, 2);
        } else {
            // Conservar descuento existente si no se modificó y oferta sigue activa
            if (!empty($producto['precio_original']) && $producto['precio_original'] > $producto['precio'] && $porcentaje_descuento === null && $precio_descuento === null) {
                $precio = $producto['precio'];
                $precio_original = $producto['precio_original'];
                $descuento = $producto['descuento'];
            }
        }
    }

    if ($oferta_hasta && !$oferta) {
        $oferta = 1;
    }

    // ── 3. Subida de nuevas imágenes ──
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

    if (count($nuevas_imagenes) > 0) {
        $stmtImg = $pdo->prepare('INSERT INTO imagenes_producto (id_producto, url_imagen) VALUES (?, ?)');
        foreach ($nuevas_imagenes as $url) {
            $stmtImg->execute([$id, $url]);
        }
        if (empty($producto['imagen'])) {
            $pdo->prepare('UPDATE productos SET imagen=? WHERE id=?')->execute([$nuevas_imagenes[0], $id]);
            $producto['imagen'] = $nuevas_imagenes[0];
        }
    }

    // ── 4. Reemplazar imagen principal ──
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

    // ── 5. Eliminar imágenes seleccionadas ──
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

    // ── 6. Manejo de video local ──
    $video_path = $producto['video_url'];

    if ($eliminar_video && !empty($video_path)) {
        $ruta_fisica = __DIR__ . '/../' . $video_path;
        if (file_exists($ruta_fisica)) @unlink($ruta_fisica);
        $video_path = null;
    }

    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/videos/';
        $result = upload_product_video($_FILES['video'], $upload_dir, base_url());
        if ($result['ok']) {
            if (!empty($video_path)) {
                $ruta_fisica = __DIR__ . '/../' . $video_path;
                if (file_exists($ruta_fisica)) @unlink($ruta_fisica);
            }
            $video_path = $result['url'];
        }
    }

    // ── 7. Guardar en la base de datos ──
    if ($nombre && $precio > 0 && $id_categoria && $id_marca) {
        $stmt = $pdo->prepare('UPDATE productos SET nombre=?, descripcion=?, precio=?, stock=?, imagen=?, id_categoria=?, id_marca=?, oferta=?, destacado=?, nuevo_hasta=?, oferta_hasta=?, precio_original=?, descuento=?, video_url=? WHERE id=?');
        $stmt->execute([$nombre, $descripcion, $precio, $stock, $producto['imagen'], $id_categoria, $id_marca, $oferta, $destacado, $nuevo_hasta, $oferta_hasta, $precio_original, $descuento, $video_path, $id]);
        echo 'success';
        exit;
    } else {
        echo 'Por favor completa todos los campos obligatorios.';
        exit;
    }
}
?>

<!-- ═══════════════════════════════════════════════════════════
     HTML DEL MODAL
     ═══════════════════════════════════════════════════════════ -->
<div id="modal-edit-bg" class="adm-modal-overlay" onclick="cerrarModalEditarProducto()"></div>
<div id="modal-edit-producto" class="adm-modal hidden">
    <div class="adm-modal-box" style="width:100%;max-width:700px;max-height:90vh;overflow-y:auto;padding:1.5rem;text-align:left">
        <button type="button" class="adm-modal-close" onclick="cerrarModalEditarProducto()">&times;</button>
        <div class="adm-modal-title" style="margin-bottom:1.5rem">Editar Producto: <?= htmlspecialchars($producto['nombre']) ?></div>
        
        <form id="form-editar-producto" onsubmit="guardarEdicionProducto(event, <?= $id ?>)" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:1.2rem">
            <?= csrf_field() ?>

            <!-- ╔═══════════════════════════════════════════╗
                 ║  SECCIÓN 1: INFORMACIÓN BÁSICA            ║
                 ╚═══════════════════════════════════════════╝ -->
            <div style="border-bottom:1px solid var(--adm-border);padding-bottom:1.2rem">
                <h3 style="color:#fff;font-size:0.95rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:8px">
                    <span style="width:3px;height:16px;background:#3b82f6;border-radius:2px;display:inline-block"></span>
                    Información Básica
                </h3>

                <!-- Nombre -->
                <div class="adm-form-group">
                    <label class="adm-label">Nombre del producto *</label>
                    <input type="text" name="nombre" class="adm-input" value="<?= htmlspecialchars($producto['nombre']) ?>" required>
                </div>

                <!-- Descripción -->
                <div class="adm-form-group" style="margin-top:0.75rem">
                    <label class="adm-label">Descripción</label>
                    <textarea name="descripcion" class="adm-textarea" rows="3"><?= htmlspecialchars($producto['descripcion']) ?></textarea>
                </div>

                <!-- Categoría y Marca -->
                <div class="adm-form-row" style="margin-top:0.75rem">
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
            </div>

            <!-- ╔═══════════════════════════════════════════╗
                 ║  SECCIÓN 2: MULTIMEDIA                    ║
                 ╚═══════════════════════════════════════════╝ -->
            <div style="border-bottom:1px solid var(--adm-border);padding-bottom:1.2rem">
                <h3 style="color:#fff;font-size:0.95rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:8px">
                    <span style="width:3px;height:16px;background:#10b981;border-radius:2px;display:inline-block"></span>
                    Multimedia
                </h3>

                <!-- Imagen principal -->
                <div class="adm-form-group">
                    <label class="adm-label">Imagen principal actual</label>
                    <div style="margin-bottom:0.75rem">
                        <img src="<?= htmlspecialchars((strpos($producto['imagen'], 'http') === 0) ? $producto['imagen'] : '../' . ($producto['imagen'] ?: 'uploads/products/default.png')) ?>" alt="Imagen actual" style="width:120px;height:80px;object-fit:contain;border-radius:0.5rem;border:1px solid var(--adm-border);background:#1a1a1a;padding:8px">
                    </div>
                    <label class="adm-label">Cambiar imagen principal (Solo PNG)</label>
                    <input type="file" name="imagen" accept="image/png" class="adm-input" style="padding:0.5rem">
                </div>

                <!-- Galería de imágenes -->
                <?php if (count($imagenes) > 0): ?>
                <div class="adm-form-group" style="margin-top:0.75rem">
                    <label class="adm-label">Galería de imágenes</label>
                    <div style="display:flex;flex-wrap:wrap;gap:0.75rem;margin-bottom:0.75rem">
                        <?php foreach ($imagenes as $img): ?>
                            <div style="position:relative">
                                <img src="<?= htmlspecialchars((strpos($img['url_imagen'], 'http') === 0) ? $img['url_imagen'] : '../' . $img['url_imagen']) ?>" alt="img" style="width:90px;height:60px;object-fit:contain;border-radius:0.5rem;border:1px solid var(--adm-border);background:#1a1a1a;padding:5px">
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
                <div class="adm-form-group" style="margin-top:0.75rem">
                    <label class="adm-label">Agregar nuevas imágenes (Solo PNG)</label>
                    <input type="file" name="imagenes[]" accept="image/png" class="adm-input" multiple style="padding:0.5rem">
                </div>

                <!-- Video del Producto -->
                <div class="adm-form-group" style="padding:1rem;background:rgba(139,92,246,0.05);border:1px dashed rgba(139,92,246,0.3);border-radius:0.75rem;margin-top:0.75rem">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                        <span style="font-size:0.85rem;font-weight:700;color:#fff">Video del Producto</span>
                        <span style="font-size:0.7rem;color:#888">(mp4, webm - max 50MB)</span>
                    </div>
                    <?php if (!empty($producto['video_url'])): ?>
                        <div style="margin-bottom:0.75rem;padding:0.5rem;background:rgba(0,0,0,0.2);border-radius:0.5rem;display:flex;align-items:center;justify-content:space-between">
                            <span style="font-size:0.75rem;color:#a78bfa;display:flex;align-items:center;gap:5px">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                Video actual cargado
                            </span>
                            <label style="display:flex;align-items:center;gap:5px;cursor:pointer;background:rgba(239,68,68,0.2);padding:2px 8px;border-radius:4px;font-size:0.7rem;color:#f87171">
                                <input type="checkbox" name="eliminar_video" value="1"> Eliminar
                            </label>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="video" accept="video/mp4,video/webm,video/ogg" class="adm-input" style="padding:0.5rem">
                    <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Sube un archivo de video para mostrar en el detalle del producto.</div>
                </div>
            </div>

            <!-- ╔═══════════════════════════════════════════╗
                 ║  SECCIÓN 3: PRECIO Y STOCK                ║
                 ╚═══════════════════════════════════════════╝ -->
            <div>
                <h3 style="color:#fff;font-size:0.95rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:8px">
                    <span style="width:3px;height:16px;background:#f59e0b;border-radius:2px;display:inline-block"></span>
                    Precio y Stock
                </h3>

                <!-- Precio y Stock -->
                <div class="adm-form-row">
                    <div>
                        <label class="adm-label">Precio Normal (COP) *</label>
                        <input type="number" name="precio_normal" id="precio_normal" min="0" step="1" class="adm-input" value="<?= htmlspecialchars(!empty($producto['precio_original']) ? (int)$producto['precio_original'] : (int)$producto['precio']) ?>" required>
                    </div>
                    <div>
                        <label class="adm-label">Stock *</label>
                        <input type="number" name="stock" min="0" class="adm-input" value="<?= htmlspecialchars($producto['stock']) ?>" required>
                    </div>
                </div>

                <!-- Configurar Descuento -->
                <div style="padding:1rem;background:rgba(239,68,68,0.05);border:1px dashed rgba(239,68,68,0.3);border-radius:0.75rem;margin-top:0.75rem">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                        <span style="font-size:0.85rem;font-weight:700;color:#fff">Configurar Descuento</span>
                    </div>
                    <input type="hidden" name="precio_descuento" id="precio_descuento" value="<?= (!empty($producto['precio_original']) && $producto['precio_original'] > $producto['precio']) ? (int)$producto['precio'] : '' ?>">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
                        <div>
                            <label class="adm-label" style="font-size:0.7rem;color:#888">% Dcto</label>
                            <div style="position:relative">
                                <input type="number" name="porcentaje_descuento" id="porcentaje_descuento" min="0" max="99" step="1" class="adm-input" value="<?= (!empty($producto['descuento']) && $producto['descuento'] > 0) ? round($producto['descuento']) : '' ?>" placeholder="Ej: 20">
                                <span style="position:absolute;right:0.6rem;top:50%;transform:translateY(-50%);color:#f87171;font-weight:900">%</span>
                            </div>
                        </div>
                        <div>
                            <label class="adm-label" style="font-size:0.7rem;color:#888">Precio con Descuento</label>
                            <div id="descuento_preview_modal" style="padding:0.65rem;background:rgba(0,0,0,0.3);border:1px solid rgba(255,255,255,0.1);border-radius:0.5rem;font-weight:800;color:#666;text-align:center;min-height:38px;display:flex;align-items:center;justify-content:center">
                                <?php if (!empty($producto['descuento']) && $producto['descuento'] > 0): ?>
                                    <span style="color:#10b981">$<?= number_format((int)$producto['precio'], 0, ',', '.') ?></span>
                                <?php else: ?>
                                    ---
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div id="dcto-validation-msg" style="display:none;font-size:0.72rem;color:#f87171;margin-top:0.5rem;display:flex;align-items:center;gap:4px"></div>

                    <!-- Oferta (dentro del bloque de descuento) -->
                    <div style="border-top:1px solid rgba(239,68,68,0.2);margin-top:0.75rem;padding-top:0.75rem;display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
                        <label style="position:relative;display:inline-block;width:42px;height:22px;flex-shrink:0">
                            <input type="checkbox" name="oferta" value="1" style="opacity:0;width:0;height:0" id="toggle-oferta" <?php if($producto['oferta']) echo 'checked'; ?>>
                            <span class="adm-toggle-slider"></span>
                        </label>
                        <span style="font-size:0.78rem;color:#f87171;font-weight:600">Activar oferta</span>
                        <div style="flex:1;min-width:140px">
                            <label class="adm-label" style="font-size:0.7rem;color:#888">Válida hasta</label>
                            <input type="date" name="oferta_hasta" class="adm-input" style="padding:0.4rem 0.5rem;font-size:0.8rem" value="<?= htmlspecialchars($producto['oferta_hasta'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Destacado -->
                <div class="adm-form-group" style="display:flex;align-items:center;gap:0.75rem;padding:1rem;background:rgba(255,255,255,0.03);border-radius:0.75rem;border:1px solid var(--adm-border);margin-top:0.75rem">
                    <label style="position:relative;display:inline-block;width:48px;height:26px;flex-shrink:0">
                        <input type="checkbox" name="destacado" value="1" style="opacity:0;width:0;height:0" id="toggle-destacado" <?php if(!empty($producto['destacado'])) echo 'checked'; ?>>
                        <span class="adm-toggle-slider"></span>
                    </label>
                    <div>
                        <div style="font-size:0.88rem;font-weight:600;color:#e7e7ea">Producto Destacado</div>
                        <div style="font-size:0.72rem;color:#666">Se mostrará en el carrusel de inicio</div>
                    </div>
                </div>

                <!-- Badge "NUEVO" -->
                <div class="adm-form-group" style="padding:1rem;background:rgba(255,255,255,0.03);border-radius:0.75rem;border:1px solid var(--adm-border);margin-top:0.75rem">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                        <span style="width:8px;height:8px;background:#3b82f6;border-radius:50%;display:inline-block"></span>
                        <span style="font-size:0.88rem;font-weight:600;color:#e7e7ea">Badge "NUEVO"</span>
                    </div>
                    <label class="adm-label">Mostrar como nuevo hasta</label>
                    <input type="date" name="nuevo_hasta" class="adm-input" value="<?= htmlspecialchars($producto['nuevo_hasta'] ?? '') ?>">
                </div>
            </div>

            <!-- Mensaje de error y botón guardar -->
            <div id="modal-edit-msg" style="display:none;text-align:center;color:#ef4444;font-size:0.85rem;margin-top:0.5rem"></div>
            <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;padding:0.85rem;font-size:0.95rem;margin-top:0.5rem">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Guardar Cambios
            </button>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     JAVASCRIPT: Cálculo de descuento en tiempo real
     ═══════════════════════════════════════════════════════════ -->
<script>
(function() {
    const previewModal = document.getElementById('descuento_preview_modal');
    const pNormal = document.getElementById('precio_normal');
    const pDcto = document.getElementById('precio_descuento');
    const pPct = document.getElementById('porcentaje_descuento');
    const toggleOferta = document.getElementById('toggle-oferta');
    const validationMsg = document.getElementById('dcto-validation-msg');
    const form = document.getElementById('form-editar-producto');

    function formatCOP(num) {
        return '$' + Math.round(num).toLocaleString('es-CO');
    }

    function validateDiscount() {
        if (!toggleOferta || !toggleOferta.checked) {
            // Oferta desactivada: sin validación, limpiar todo
            if (pPct) pPct.style.borderColor = '';
            if (validationMsg) { validationMsg.style.display = 'none'; validationMsg.innerHTML = ''; }
            return true;
        }
        // Oferta activada: verificar que haya porcentaje válido
        const pct = parseFloat(pPct?.value) || 0;
        const normal = parseFloat(pNormal?.value) || 0;
        if (pct <= 0 || pct >= 100) {
            if (pPct) pPct.style.borderColor = '#f87171';
            if (validationMsg) {
                validationMsg.style.display = 'flex';
                validationMsg.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg> Ingresa un porcentaje de descuento entre 1 y 99';
            }
            return false;
        }
        if (normal <= 0) {
            if (pPct) pPct.style.borderColor = '#f87171';
            if (validationMsg) {
                validationMsg.style.display = 'flex';
                validationMsg.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg> Ingresa un precio normal válido primero';
            }
            return false;
        }
        // Todo OK
        if (pPct) pPct.style.borderColor = '#10b981';
        if (validationMsg) { validationMsg.style.display = 'none'; validationMsg.innerHTML = ''; }
        return true;
    }

    function calcAndPreview() {
        const normal = parseFloat(pNormal.value) || 0;
        const pct = parseFloat(pPct.value) || 0;
        if (normal > 0 && pct > 0 && pct < 100) {
            const precioFinal = Math.round(normal * (1 - pct / 100));
            pDcto.value = precioFinal;
            previewModal.innerHTML = '<span style="color:#10b981;font-size:1rem">' + formatCOP(precioFinal) + '</span><span style="color:#f87171;font-size:0.7rem;margin-left:6px">(-' + Math.round(pct) + '%)</span>';
        } else {
            pDcto.value = '';
            previewModal.innerHTML = '<span style="color:#666">---</span>';
        }
        validateDiscount();
    }

    // Validar al cambiar el toggle
    toggleOferta?.addEventListener('change', function() {
        calcAndPreview();
    });

    pPct?.addEventListener('input', calcAndPreview);
    pNormal?.addEventListener('input', calcAndPreview);

    // Validar antes de enviar
    if (form) {
        const originalOnsubmit = form.onsubmit;
        form.onsubmit = function(e) {
            if (toggleOferta && toggleOferta.checked && !validateDiscount()) {
                e.preventDefault();
                pPct?.focus();
                return false;
            }
            if (originalOnsubmit) return originalOnsubmit.call(this, e);
        };
    }
})();
</script>