<?php
// Bootstrap PRIMERO: inicia sesión desde la BD antes de verificar permisos
require_once __DIR__ . '/../app/Core/bootstrap.php';
require_once __DIR__ . '/../app/Core/image_helper.php';
require_once __DIR__ . '/../app/Core/video_helper.php';
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
    $precio_normal = floatval($_POST['precio_normal'] ?? 0);
    $precio_descuento = !empty($_POST['precio_descuento']) ? floatval($_POST['precio_descuento']) : null;
    $porcentaje_descuento = !empty($_POST['porcentaje_descuento']) ? floatval($_POST['porcentaje_descuento']) : null;
    $stock = intval($_POST['stock'] ?? 0);
    $id_categoria = intval($_POST['id_categoria'] ?? 0);
    $id_marca = intval($_POST['id_marca'] ?? 0);
    $oferta = isset($_POST['oferta']) ? 1 : 0;
    $destacado = isset($_POST['destacado']) ? 1 : 0;
    $nuevo_hasta = !empty($_POST['nuevo_hasta']) ? $_POST['nuevo_hasta'] : null;
    $oferta_hasta = !empty($_POST['oferta_hasta']) ? $_POST['oferta_hasta'] : null;

    // Determinar precio final y descuento
    $precio_original = null;
    $descuento = null;
    $precio = $precio_normal;

    // Prioridad: si hay porcentaje, calcular precio con descuento
    if ($porcentaje_descuento && $porcentaje_descuento > 0 && $porcentaje_descuento < 100 && $precio_normal > 0) {
        $precio_descuento = round($precio_normal * (1 - $porcentaje_descuento / 100));
    }

    if ($precio_descuento && $precio_descuento > 0 && $precio_descuento < $precio_normal) {
        $precio = $precio_descuento;
        $precio_original = $precio_normal;
        $descuento = round((($precio_normal - $precio_descuento) / $precio_normal) * 100, 2);
        $oferta = 1;
    }

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
    
    // Subida de video (Opcional)
    $video_path = null;
    if (!$error_imagen && isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/videos/';
        $video_result = upload_product_video($_FILES['video'], $upload_dir, base_url());
        if ($video_result['ok']) {
            $video_path = $video_result['url'];
        } else {
            $error_imagen = $video_result['error']; // Reutilizamos error_imagen para mostrar el mensaje
        }
    }

    if ($error_imagen) {
        $mensaje = $error_imagen;
    } else if ($nombre && $precio > 0 && $id_categoria && $id_marca && $imagen_principal) {
        try {
            $stmt = $pdo->prepare('INSERT INTO productos (nombre, descripcion, precio, stock, imagen, id_categoria, id_marca, oferta, destacado, nuevo_hasta, oferta_hasta, precio_original, descuento, video_url, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$nombre, $descripcion, $precio, $stock, $imagen_principal, $id_categoria, $id_marca, $oferta, $destacado, $nuevo_hasta, $oferta_hasta, $precio_original, $descuento, $video_path]);
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
                    <?= csrf_field() ?>
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

                <!-- Precio y Descuento -->
                <div class="adm-form-group" style="padding:1rem;background:rgba(255,255,255,0.03);border-radius:0.75rem;border:1px solid var(--adm-border)">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                        <span style="width:8px;height:8px;background:#f59e0b;border-radius:50%;display:inline-block"></span>
                        <span style="font-size:0.88rem;font-weight:600;color:#e7e7ea">💰 Precio y Descuento</span>
                    </div>

                    <!-- Precio Normal -->
                    <div class="adm-form-row">
                        <div>
                            <label class="adm-label">Precio Normal (COP) *</label>
                            <input type="number" name="precio_normal" id="precio_normal" min="0" step="1" class="adm-input" required placeholder="Ej: 5499900">
                            <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">El precio regular del producto</div>
                        </div>
                        <div>
                            <label class="adm-label">Stock *</label>
                            <input type="number" name="stock" min="0" class="adm-input" required>
                        </div>
                    </div>

                    <!-- Precio con Descuento -->
                    <div style="margin-top:1rem;padding:1rem;background:rgba(239,68,68,0.04);border:1px dashed rgba(239,68,68,0.2);border-radius:0.75rem">
                        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                            <span style="font-size:0.85rem;font-weight:600;color:#e7e7ea">¿Tiene descuento?</span>
                            <span style="font-size:0.7rem;color:#666">(opcional — usa porcentaje o precio)</span>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.75rem">
                            <!-- Porcentaje de descuento -->
                            <div>
                                <label class="adm-label">% Descuento</label>
                                <div style="position:relative">
                                    <input type="number" name="porcentaje_descuento" id="porcentaje_descuento" min="0" max="99" step="1" class="adm-input" style="padding-right:2.5rem" placeholder="Ej: 20">
                                    <span style="position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);color:#f87171;font-weight:800;font-size:1.1rem">%</span>
                                </div>
                                <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Escribe el % que se le resta al precio.</div>
                            </div>
                            <!-- Precio con descuento -->
                            <div>
                                <label class="adm-label">Precio con Descuento</label>
                                <input type="number" name="precio_descuento" id="precio_descuento" min="0" step="1" class="adm-input" placeholder="Ej: 4399920">
                                <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Se calcula auto o ponlo manual.</div>
                            </div>
                            <!-- Preview del descuento -->
                            <div>
                                <label class="adm-label">Resultado</label>
                                <div id="descuento_preview" style="padding:0.65rem 1rem;background:rgba(0,0,0,0.2);border:1px solid var(--adm-border);border-radius:0.5rem;font-size:1rem;font-weight:700;color:#666;min-height:42px;display:flex;align-items:center">
                                    Sin descuento
                                </div>
                            </div>
                        </div>
                        <div id="descuento_resumen" style="margin-top:0.75rem;display:none;padding:0.75rem;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:0.5rem;align-items:center;gap:0.75rem;flex-wrap:wrap"></div>
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
                    <input type="file" name="imagen" accept="image/png" class="adm-input" required style="padding:0.5rem">
                    <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">⚠️ Solo se permiten imágenes en formato <strong style="color:#fff">PNG</strong>. Ésta será la portada del producto.</div>
                </div>

                <div class="adm-form-group">
                    <label class="adm-label">Agregar nuevas imágenes a la galería (Solo PNG, Opcional)</label>
                    <input type="file" name="imagenes[]" accept="image/png" class="adm-input" multiple style="padding:0.5rem">
                    <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Puedes seleccionar varias imágenes para la galería del producto.</div>
                </div>

                <!-- Video del Producto -->
                <div class="adm-form-group" style="padding:1rem;background:rgba(139,92,246,0.05);border:1px dashed rgba(139,92,246,0.3);border-radius:0.75rem">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                        <span style="font-size:0.85rem;font-weight:700;color:#fff">Video del Producto</span>
                        <span style="font-size:0.7rem;color:#888">(mp4, webm - max 50MB)</span>
                    </div>
                    <input type="file" name="video" accept="video/mp4,video/webm,video/ogg" class="adm-input" style="padding:0.5rem">
                    <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Sube un archivo de video desde tu computadora.</div>
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
                            <span class="adm-toggle-slider"></span>
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
                                <span class="adm-toggle-slider"></span>
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



<?php include '_layout_end.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const precioNormal = document.getElementById('precio_normal');
    const precioDescuento = document.getElementById('precio_descuento');
    const porcentajeDescuento = document.getElementById('porcentaje_descuento');
    const descuentoPreview = document.getElementById('descuento_preview');
    const resumenBox = document.getElementById('descuento_resumen');
    let lastEdited = 'none';

    function formatCOP(n) {
        return '$' + n.toLocaleString('es-CO', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    }

    function onPctChange() {
        lastEdited = 'pct';
        const normal = parseFloat(precioNormal?.value) || 0;
        const pct = parseFloat(porcentajeDescuento?.value) || 0;
        if (normal > 0 && pct > 0 && pct < 100) {
            const calculado = Math.round(normal * (1 - pct / 100));
            if (precioDescuento) precioDescuento.value = calculado;
        } else if (pct === 0 || !porcentajeDescuento?.value) {
            if (precioDescuento) precioDescuento.value = '';
        }
        updatePreview();
    }

    function onPriceChange() {
        lastEdited = 'price';
        const normal = parseFloat(precioNormal?.value) || 0;
        const dcto = parseFloat(precioDescuento?.value) || 0;
        if (normal > 0 && dcto > 0 && dcto < normal) {
            const pct = Math.round(((normal - dcto) / normal) * 100);
            if (porcentajeDescuento) porcentajeDescuento.value = pct;
        } else if (!precioDescuento?.value) {
            if (porcentajeDescuento) porcentajeDescuento.value = '';
        }
        updatePreview();
    }

    function onNormalChange() {
        if (lastEdited === 'pct') {
            onPctChange();
        } else if (lastEdited === 'price') {
            onPriceChange();
        } else {
            updatePreview();
        }
    }

    function updatePreview() {
        const normal = parseFloat(precioNormal?.value) || 0;
        const dcto = parseFloat(precioDescuento?.value) || 0;

        if (normal > 0 && dcto > 0 && dcto < normal) {
            const pct = Math.round(((normal - dcto) / normal) * 100);
            const ahorro = normal - dcto;
            if (descuentoPreview) {
                descuentoPreview.innerHTML = '<span style="color:#f87171;font-size:1.2rem">-' + pct + '%</span>';
            }
            if (resumenBox) {
                resumenBox.style.display = 'flex';
                resumenBox.innerHTML = 
                    '<span style="font-size:0.8rem;color:#999;text-decoration:line-through">' + formatCOP(normal) + '</span>' +
                    '<span style="font-size:1.1rem;font-weight:800;color:#ff4444">' + formatCOP(dcto) + '</span>' +
                    '<span style="font-size:0.78rem;font-weight:700;color:#4ade80;background:rgba(74,222,128,0.1);padding:0.2rem 0.5rem;border-radius:4px">Ahorras ' + formatCOP(ahorro) + '</span>';
            }
        } else {
            if (descuentoPreview) {
                descuentoPreview.innerHTML = '<span style="color:#666">Sin descuento</span>';
            }
            if (resumenBox) {
                resumenBox.style.display = 'none';
            }
        }
    }

    if (porcentajeDescuento) porcentajeDescuento.addEventListener('input', onPctChange);
    if (precioDescuento) precioDescuento.addEventListener('input', onPriceChange);
    if (precioNormal) precioNormal.addEventListener('input', onNormalChange);
});
</script>