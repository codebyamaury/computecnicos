<?php
// Bootstrap PRIMERO: inicia sesión desde la BD antes de verificar permisos
require_once __DIR__ . '/../app/Core/bootstrap.php';
require_once __DIR__ . '/../app/Core/image_helper.php';
require_once __DIR__ . '/../app/Core/video_helper.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('Location: productos.php');
    exit;
}
// Obtener producto actual
$stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
$stmt->execute([$id]);
$producto = $stmt->fetch();
if (!$producto) {
    header('Location: productos.php');
    exit;
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
    $eliminar_video = isset($_POST['eliminar_video']) ? 1 : 0;

    // Determinar precio final y descuento
    $precio_original = null;
    $descuento = null;
    $precio = $precio_normal; // por defecto, el precio es el normal

    // Si el usuario DESACTIVÓ la oferta, limpiar todo descuento
    if ($oferta == 0) {
        $precio = $precio_normal;
        $precio_original = null;
        $descuento = null;
    } else {
        // Oferta activa: calcular descuento si hay porcentaje
        if ($porcentaje_descuento && $porcentaje_descuento > 0 && $porcentaje_descuento < 100 && $precio_normal > 0) {
            $precio_descuento = round($precio_normal * (1 - $porcentaje_descuento / 100));
        }

        if ($precio_descuento && $precio_descuento > 0 && $precio_descuento < $precio_normal) {
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

    // Si se pone fecha de oferta, activar oferta automáticamente
    if ($oferta_hasta && !$oferta) {
        $oferta = 1;
    }

    // Subida de nuevas imágenes — solo formato PNG
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
    // Reemplazar imagen principal si se sube una nueva — solo formato PNG
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
    // Manejo de Video Local
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
            // Eliminar video anterior si existe
            if (!empty($video_path)) {
                $ruta_fisica = __DIR__ . '/../' . $video_path;
                if (file_exists($ruta_fisica)) @unlink($ruta_fisica);
            }
            $video_path = $result['url'];
        }
    }

    // Actualizar datos del producto
    if ($nombre && $precio > 0 && $id_categoria && $id_marca) {
        $stmt = $pdo->prepare('UPDATE productos SET nombre=?, descripcion=?, precio=?, stock=?, imagen=?, id_categoria=?, id_marca=?, oferta=?, destacado=?, nuevo_hasta=?, oferta_hasta=?, precio_original=?, descuento=?, video_url=? WHERE id=?');
        $stmt->execute([$nombre, $descripcion, $precio, $stock, $producto['imagen'], $id_categoria, $id_marca, $oferta, $destacado, $nuevo_hasta, $oferta_hasta, $precio_original, $descuento, $video_path, $id]);
        $mensaje = 'Producto actualizado correctamente.';
        // Refrescar datos e imágenes
        $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
        $stmt->execute([$id]);
        $producto = $stmt->fetch();
        $imagenes = $pdo->prepare('SELECT * FROM imagenes_producto WHERE id_producto = ?');
        $imagenes->execute([$id]);
        $imagenes = $imagenes->fetchAll();
    } else {
        $mensaje = 'Por favor completa todos los campos obligatorios.';
    }
}

// Variables para el layout del admin
$page_title = 'Editar Producto | Computécnicos';
$admin_page = 'productos';
$admin_title = 'Editar: ' . htmlspecialchars($producto['nombre']);
$admin_breadcrumb = [['label' => 'Productos', 'href' => 'productos.php'], ['label' => 'Editar']];
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
                    <input type="text" name="nombre" class="adm-input" value="<?= htmlspecialchars($producto['nombre']) ?>" required>
                </div>

                <!-- Descripción -->
                <div class="adm-form-group">
                    <label class="adm-label">Descripción</label>
                    <textarea name="descripcion" class="adm-textarea" rows="3"><?= htmlspecialchars($producto['descripcion']) ?></textarea>
                </div>

                <!-- Precio y Descuento -->
                <div class="adm-form-group" style="padding:1rem;background:rgba(255,255,255,0.03);border-radius:0.75rem;border:1px solid var(--adm-border)">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                        <span style="width:8px;height:8px;background:#f59e0b;border-radius:50%;display:inline-block"></span>
                        <span style="font-size:0.88rem;font-weight:600;color:#e7e7ea">💰 Precio y Descuento</span>
                        <?php if (!empty($producto['descuento']) && $producto['descuento'] > 0): ?>
                            <span class="adm-badge" style="margin-left:auto;background:rgba(239,68,68,0.15);color:#f87171;font-size:0.72rem;padding:0.2rem 0.6rem;border-radius:100px;font-weight:700">-<?= number_format($producto['descuento'], 0) ?>% DCTO</span>
                        <?php endif; ?>
                    </div>

                    <!-- Precio Normal -->
                    <div class="adm-form-row">
                        <div>
                            <label class="adm-label">Precio Normal (COP) *</label>
                            <input type="number" name="precio_normal" id="precio_normal" min="0" step="1" class="adm-input" value="<?= htmlspecialchars(!empty($producto['precio_original']) ? $producto['precio_original'] : $producto['precio']) ?>" required placeholder="Ej: 5499900">
                            <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">El precio regular del producto</div>
                        </div>
                        <div>
                            <label class="adm-label">Stock *</label>
                            <input type="number" name="stock" min="0" class="adm-input" value="<?= htmlspecialchars($producto['stock']) ?>" required>
                        </div>
                    </div>

                    <!-- Precio con Descuento -->
                    <div style="margin-top:1rem;padding:1rem;background:rgba(239,68,68,0.04);border:1px dashed rgba(239,68,68,0.2);border-radius:0.75rem">
                        <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                            <span style="font-size:0.85rem;font-weight:600;color:#e7e7ea">¿Tiene descuento?</span>
                            <span style="font-size:0.7rem;color:#666">(opcional — usa porcentaje o precio)</span>
                        </div>

                        <!-- Fila: Porcentaje + Precio con Descuento + Preview -->
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.75rem">
                            <!-- Porcentaje de descuento -->
                            <div>
                                <label class="adm-label">% Descuento</label>
                                <div style="position:relative">
                                    <input type="number" name="porcentaje_descuento" id="porcentaje_descuento" min="0" max="99" step="1" class="adm-input" style="padding-right:2.5rem" value="<?= (!empty($producto['descuento']) && $producto['descuento'] > 0) ? number_format($producto['descuento'], 0) : '' ?>" placeholder="Ej: 20">
                                    <span style="position:absolute;right:0.75rem;top:50%;transform:translateY(-50%);color:#f87171;font-weight:800;font-size:1.1rem">%</span>
                                </div>
                                <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Escribe el % que se le resta al precio.</div>
                            </div>
                            <!-- Precio con descuento -->
                            <div>
                                <label class="adm-label">Precio con Descuento</label>
                                <input type="number" name="precio_descuento" id="precio_descuento" min="0" step="1" class="adm-input" value="<?= (!empty($producto['precio_original']) && $producto['precio_original'] > $producto['precio']) ? htmlspecialchars($producto['precio']) : '' ?>" placeholder="Ej: 4399920">
                                <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Se calcula auto o ponlo manual.</div>
                            </div>
                            <!-- Preview del descuento -->
                            <div>
                                <label class="adm-label">Resultado</label>
                                <div id="descuento_preview" style="padding:0.65rem 1rem;background:rgba(0,0,0,0.2);border:1px solid var(--adm-border);border-radius:0.5rem;font-size:1rem;font-weight:700;color:#666;min-height:42px;display:flex;align-items:center">
                                    <?php if (!empty($producto['descuento']) && $producto['descuento'] > 0): ?>
                                        <span style="color:#f87171;font-size:1.2rem">-<?= number_format($producto['descuento'], 0) ?>%</span>
                                    <?php else: ?>
                                        Sin descuento
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen visual -->
                        <?php if (!empty($producto['precio_original']) && $producto['precio_original'] > $producto['precio']): ?>
                        <div id="descuento_resumen" style="margin-top:0.75rem;padding:0.75rem;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:0.5rem;display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
                            <span style="font-size:0.8rem;color:#999;text-decoration:line-through">$<?= number_format($producto['precio_original'], 0, ',', '.') ?></span>
                            <span style="font-size:1.1rem;font-weight:800;color:#ff4444">$<?= number_format($producto['precio'], 0, ',', '.') ?></span>
                            <span style="font-size:0.78rem;font-weight:700;color:#4ade80;background:rgba(74,222,128,0.1);padding:0.2rem 0.5rem;border-radius:4px">Ahorras $<?= number_format($producto['precio_original'] - $producto['precio'], 0, ',', '.') ?></span>
                        </div>
                        <?php else: ?>
                        <div id="descuento_resumen" style="margin-top:0.75rem;display:none;padding:0.75rem;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:0.5rem;align-items:center;gap:0.75rem;flex-wrap:wrap"></div>
                        <?php endif; ?>
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
                                    <input type="checkbox" name="eliminar_imagen[<?= $img['id'] ?>]" value="1" style="display:none" onchange="if(this.checked) this.closest('div').style.display='none'">
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

                <!-- Video del Producto -->
                <div class="adm-form-group" style="padding:1rem;background:rgba(139,92,246,0.05);border:1px dashed rgba(139,92,246,0.3);border-radius:0.75rem">
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
                    <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Sube un archivo de video para tu servidor. Se mostrará en la página del producto.</div>
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
                                <span class="adm-toggle-slider"></span>
                            </label>
                            <span style="font-size:0.85rem;color:#aaa">Activar oferta</span>
                        </div>
                        <label class="adm-label">Oferta válida hasta</label>
                        <input type="date" name="oferta_hasta" id="oferta_hasta_input" class="adm-input" value="<?= htmlspecialchars($producto['oferta_hasta'] ?? '') ?>">
                        <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Si pones una fecha, la oferta se desactivará automáticamente al vencer. Déjalo vacío para oferta permanente (mientras esté activada).</div>

                        <?php if ($oferta_activa && !empty($producto['oferta_hasta'])): ?>
                        <!-- Countdown Timer -->
                        <div id="adm-offer-countdown" style="margin-top:1rem;padding:1rem;background:linear-gradient(135deg,rgba(239,68,68,0.1),rgba(239,68,68,0.05));border:1px solid rgba(239,68,68,0.25);border-radius:0.75rem">
                            <div style="font-size:0.78rem;font-weight:600;color:#f87171;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.4rem">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                Tiempo restante de la oferta
                            </div>
                            <div id="countdown-display" style="display:flex;gap:0.5rem;flex-wrap:wrap">
                                <div style="text-align:center;background:rgba(0,0,0,0.3);border-radius:0.5rem;padding:0.5rem 0.75rem;min-width:60px">
                                    <div id="cd-days" style="font-size:1.5rem;font-weight:900;color:#fff;line-height:1">--</div>
                                    <div style="font-size:0.65rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-top:0.2rem">Días</div>
                                </div>
                                <div style="text-align:center;background:rgba(0,0,0,0.3);border-radius:0.5rem;padding:0.5rem 0.75rem;min-width:60px">
                                    <div id="cd-hours" style="font-size:1.5rem;font-weight:900;color:#fff;line-height:1">--</div>
                                    <div style="font-size:0.65rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-top:0.2rem">Horas</div>
                                </div>
                                <div style="text-align:center;background:rgba(0,0,0,0.3);border-radius:0.5rem;padding:0.5rem 0.75rem;min-width:60px">
                                    <div id="cd-mins" style="font-size:1.5rem;font-weight:900;color:#fff;line-height:1">--</div>
                                    <div style="font-size:0.65rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-top:0.2rem">Min</div>
                                </div>
                                <div style="text-align:center;background:rgba(0,0,0,0.3);border-radius:0.5rem;padding:0.5rem 0.75rem;min-width:60px">
                                    <div id="cd-secs" style="font-size:1.5rem;font-weight:900;color:#ff4444;line-height:1">--</div>
                                    <div style="font-size:0.65rem;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-top:0.2rem">Seg</div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;padding:0.85rem;font-size:0.95rem;margin-top:0.5rem">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Guardar Cambios
                </button>
            </form>
        </div>
    </div>
</main>

<script>
// Auto-calcular descuento y preview en tiempo real con sincronización bidireccional
document.addEventListener('DOMContentLoaded', function() {
    const precioNormal = document.getElementById('precio_normal');
    const precioDescuento = document.getElementById('precio_descuento');
    const porcentajeDescuento = document.getElementById('porcentaje_descuento');
    const descuentoPreview = document.getElementById('descuento_preview');
    const resumenBox = document.getElementById('descuento_resumen');
    let lastEdited = 'none'; // 'pct' or 'price'

    function formatCOP(n) {
        return '$' + n.toLocaleString('es-CO', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    }

    // Cuando el admin escribe un porcentaje → calcular precio con descuento
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

    // Cuando el admin escribe un precio con descuento → calcular porcentaje
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

    // Cuando cambia el precio normal, recalcular según lo último editado
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

    // Countdown timer for active offers
    const ofertaHasta = '<?= htmlspecialchars($producto['oferta_hasta'] ?? '') ?>';
    if (ofertaHasta && document.getElementById('cd-days')) {
        const endDate = new Date(ofertaHasta + 'T23:59:59');
        function updateCountdown() {
            const now = new Date();
            const diff = endDate - now;
            if (diff <= 0) {
                document.getElementById('cd-days').textContent = '0';
                document.getElementById('cd-hours').textContent = '00';
                document.getElementById('cd-mins').textContent = '00';
                document.getElementById('cd-secs').textContent = '00';
                return;
            }
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const secs = Math.floor((diff % (1000 * 60)) / 1000);
            document.getElementById('cd-days').textContent = days;
            document.getElementById('cd-hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('cd-mins').textContent = String(mins).padStart(2, '0');
            document.getElementById('cd-secs').textContent = String(secs).padStart(2, '0');
        }
        updateCountdown();
        setInterval(updateCountdown, 1000);
    }
});
</script>

<?php include '_layout_end.php'; ?>