<?php
/**
 * Script de conversión masiva de imágenes existentes a PNG
 * 
 * INSTRUCCIONES:
 * 1. Accede a este script SOLO desde el panel de admin (requiere sesión admin)
 * 2. URL: /admin/convertir_imagenes.php
 * 3. Haz clic en "Iniciar Conversión" para convertir todas las imágenes a PNG
 * 4. Opcionalmente activa "Remover fondo" para quitar el fondo de las imágenes
 * 5. ELIMINA este archivo después de usarlo por seguridad
 */

require_once __DIR__ . '/../app/Core/bootstrap.php';
require_once __DIR__ . '/../app/Core/image_helper.php';

// Solo admin puede acceder
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}

$resultados = [];
$total_convertidas = 0;
$total_errores = 0;
$total_ya_png = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convertir'])) {
    $remove_bg = isset($_POST['remove_bg']) ? true : false;
    
    // Obtener todas las imágenes de productos
    $productos = $pdo->query('SELECT id, nombre, imagen FROM productos WHERE imagen IS NOT NULL AND imagen != ""')->fetchAll();
    
    foreach ($productos as $prod) {
        $imagen_url = $prod['imagen'];
        
        // Si ya es PNG, saltar
        if (strtolower(pathinfo($imagen_url, PATHINFO_EXTENSION)) === 'png' && !$remove_bg) {
            $total_ya_png++;
            $resultados[] = [
                'producto' => $prod['nombre'],
                'status' => 'skip',
                'msg' => 'Ya es PNG'
            ];
            continue;
        }
        
        // Determinar la ruta física de la imagen
        $ruta_fisica = resolve_image_path($imagen_url);
        
        if (!$ruta_fisica || !file_exists($ruta_fisica)) {
            $total_errores++;
            $resultados[] = [
                'producto' => $prod['nombre'],
                'status' => 'error',
                'msg' => 'Archivo no encontrado: ' . $imagen_url
            ];
            continue;
        }
        
        // Convertir a PNG
        $upload_dir = __DIR__ . '/../uploads/productos/';
        $result = process_product_image($ruta_fisica, $upload_dir, 'conv_', $remove_bg);
        
        if ($result['ok']) {
            $nueva_url = base_url() . '/uploads/productos/' . $result['filename'];
            
            // Actualizar en la base de datos — tabla productos
            $stmt = $pdo->prepare('UPDATE productos SET imagen = ? WHERE id = ?');
            $stmt->execute([$nueva_url, $prod['id']]);
            
            // Actualizar en imagenes_producto también
            $stmt2 = $pdo->prepare('UPDATE imagenes_producto SET url_imagen = ? WHERE id_producto = ? AND url_imagen = ?');
            $stmt2->execute([$nueva_url, $prod['id'], $imagen_url]);
            
            $total_convertidas++;
            $resultados[] = [
                'producto' => $prod['nombre'],
                'status' => 'ok',
                'msg' => 'Convertida a PNG correctamente'
            ];
        } else {
            $total_errores++;
            $resultados[] = [
                'producto' => $prod['nombre'],
                'status' => 'error',
                'msg' => $result['error']
            ];
        }
    }
    
    // También convertir imágenes adicionales de la galería
    $imagenes_extra = $pdo->query('
        SELECT ip.id, ip.id_producto, ip.url_imagen, p.nombre AS producto_nombre 
        FROM imagenes_producto ip 
        LEFT JOIN productos p ON ip.id_producto = p.id 
        WHERE ip.url_imagen IS NOT NULL AND ip.url_imagen != ""
    ')->fetchAll();
    
    foreach ($imagenes_extra as $img) {
        $imagen_url = $img['url_imagen'];
        
        // Si ya es PNG y no se pide remover fondo, saltar
        if (strtolower(pathinfo($imagen_url, PATHINFO_EXTENSION)) === 'png' && !$remove_bg) {
            continue; // Ya contada arriba
        }
        
        // Verificar que no sea la misma imagen principal (ya convertida)
        $prod_check = $pdo->prepare('SELECT imagen FROM productos WHERE id = ?');
        $prod_check->execute([$img['id_producto']]);
        $prod_data = $prod_check->fetch();
        if ($prod_data && $prod_data['imagen'] === $imagen_url) {
            continue; // Ya fue convertida como imagen principal
        }
        
        $ruta_fisica = resolve_image_path($imagen_url);
        
        if (!$ruta_fisica || !file_exists($ruta_fisica)) {
            $total_errores++;
            $resultados[] = [
                'producto' => $img['producto_nombre'] . ' (galería)',
                'status' => 'error',
                'msg' => 'Archivo no encontrado: ' . $imagen_url
            ];
            continue;
        }
        
        $upload_dir = __DIR__ . '/../uploads/productos/';
        $result = process_product_image($ruta_fisica, $upload_dir, 'gal_', $remove_bg);
        
        if ($result['ok']) {
            $nueva_url = base_url() . '/uploads/productos/' . $result['filename'];
            $stmt = $pdo->prepare('UPDATE imagenes_producto SET url_imagen = ? WHERE id = ?');
            $stmt->execute([$nueva_url, $img['id']]);
            
            $total_convertidas++;
            $resultados[] = [
                'producto' => $img['producto_nombre'] . ' (galería)',
                'status' => 'ok',
                'msg' => 'Imagen de galería convertida'
            ];
        } else {
            $total_errores++;
            $resultados[] = [
                'producto' => $img['producto_nombre'] . ' (galería)',
                'status' => 'error',
                'msg' => $result['error']
            ];
        }
    }
}

/**
 * Resuelve la ruta física de una imagen a partir de su URL
 */
function resolve_image_path($url) {
    $base = __DIR__ . '/../';
    
    // Si es URL absoluta del sitio, extraer la ruta relativa
    if (strpos($url, 'http') === 0) {
        // Extraer path después del dominio
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '';
        // Quitar slash inicial
        $path = ltrim($path, '/');
        $ruta = $base . $path;
        if (file_exists($ruta)) return $ruta;
    }
    
    // Si es ruta relativa directa
    $ruta = $base . ltrim($url, '/');
    if (file_exists($ruta)) return $ruta;
    
    // Si está en uploads/productos/
    $ruta = $base . 'uploads/productos/' . basename($url);
    if (file_exists($ruta)) return $ruta;
    
    return null;
}

// Layout del admin
$page_title = 'Convertir Imágenes | Computécnicos';
$admin_page = 'productos';
$admin_title = 'Convertir Imágenes a PNG';
$admin_breadcrumb = [['label' => 'Productos', 'href' => 'productos.php'], ['label' => 'Convertir Imágenes']];
$admin_header_extra = '<a href="productos.php" class="adm-btn adm-btn-secondary">← Volver a productos</a>';

include '_layout.php';
?>

<main class="admin-content">
    <div class="admin-content-inner">
        
        <!-- Advertencia -->
        <div style="background:rgba(250,204,21,0.08);border:1px solid rgba(250,204,21,0.2);border-radius:0.75rem;padding:1.25rem;margin-bottom:1.5rem;display:flex;align-items:flex-start;gap:0.75rem">
            <i data-lucide="alert-triangle" style="width:24px;height:24px;color:#facc15;flex-shrink:0;margin-top:2px"></i>
            <div>
                <div style="font-weight:700;color:#facc15;font-size:0.95rem;margin-bottom:0.35rem">⚠️ Herramienta de Conversión Masiva</div>
                <div style="font-size:0.82rem;color:#999;line-height:1.6">
                    Esta herramienta convierte todas las imágenes de productos existentes a formato <strong style="color:#fff">PNG</strong>.
                    <br>• Las imágenes originales <strong style="color:#fff">NO se eliminan</strong>, se crean copias nuevas en PNG.
                    <br>• La base de datos se actualizará con las nuevas rutas.
                    <br>• <strong style="color:#ef4444">Elimina este archivo después de usarlo</strong> por seguridad.
                </div>
            </div>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convertir'])): ?>
        <!-- Resultados -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem">
            <div class="adm-card" style="padding:1.25rem;text-align:center">
                <div style="font-size:2rem;font-weight:800;color:#4ade80"><?= $total_convertidas ?></div>
                <div style="font-size:0.75rem;color:#666">Convertidas</div>
            </div>
            <div class="adm-card" style="padding:1.25rem;text-align:center">
                <div style="font-size:2rem;font-weight:800;color:#facc15"><?= $total_ya_png ?></div>
                <div style="font-size:0.75rem;color:#666">Ya eran PNG</div>
            </div>
            <div class="adm-card" style="padding:1.25rem;text-align:center">
                <div style="font-size:2rem;font-weight:800;color:#ef4444"><?= $total_errores ?></div>
                <div style="font-size:0.75rem;color:#666">Errores</div>
            </div>
        </div>

        <!-- Tabla de resultados -->
        <div class="adm-card" style="padding:0;overflow:hidden">
            <div style="padding:1rem 1.25rem;border-bottom:1px solid rgba(255,255,255,0.04)">
                <div class="adm-card-title" style="margin-bottom:0">
                    <span class="adm-card-title-text">Resultados de Conversión</span>
                </div>
            </div>
            <div class="adm-table-wrap" style="border:none;border-radius:0">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Estado</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($resultados as $r): ?>
                        <tr>
                            <td style="font-weight:600;font-size:0.82rem;color:#fff"><?= htmlspecialchars($r['producto']) ?></td>
                            <td>
                                <?php if ($r['status'] === 'ok'): ?>
                                    <span class="adm-badge adm-badge-green">✓ OK</span>
                                <?php elseif ($r['status'] === 'skip'): ?>
                                    <span class="adm-badge adm-badge-gray">⏭ Omitido</span>
                                <?php else: ?>
                                    <span class="adm-badge adm-badge-red">✗ Error</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.78rem;color:#888"><?= htmlspecialchars($r['msg']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulario -->
        <div class="adm-card" style="padding:1.5rem;margin-top:1.5rem">
            <form method="post">
                <input type="hidden" name="convertir" value="1">
                
                <div style="display:flex;align-items:center;gap:0.75rem;padding:1rem;background:rgba(255,255,255,0.03);border-radius:0.75rem;border:1px solid rgba(255,255,255,0.06);margin-bottom:1.25rem">
                    <label style="position:relative;display:inline-block;width:48px;height:26px;flex-shrink:0">
                        <input type="checkbox" name="remove_bg" value="1" style="opacity:0;width:0;height:0" id="toggle-removebg-conv">
                        <span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.08);border-radius:13px;transition:0.3s" id="slider-removebg-conv"></span>
                    </label>
                    <div>
                        <div style="font-size:0.88rem;font-weight:600;color:#e7e7ea">🎨 Remover fondo automáticamente</div>
                        <div style="font-size:0.72rem;color:#666">Detecta y elimina el fondo de las imágenes. Funciona mejor con fondos sólidos (blanco, gris).</div>
                    </div>
                </div>

                <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;padding:0.85rem;font-size:0.95rem"
                        onclick="return confirm('¿Estás seguro de convertir TODAS las imágenes de productos a PNG? Este proceso puede tardar varios minutos.')">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Iniciar Conversión a PNG
                </button>
            </form>
        </div>
    </div>
</main>

<style>
    #toggle-removebg-conv:checked + #slider-removebg-conv {
        background: #cc0000 !important;
    }
    #toggle-removebg-conv:checked + #slider-removebg-conv::before {
        transform: translateX(22px);
    }
    #slider-removebg-conv::before {
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
