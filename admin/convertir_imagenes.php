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

// Aumentar límites para procesamiento masivo de imágenes
set_time_limit(300);
ini_set('memory_limit', '512M');

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
$archivos_temp = []; // Para limpiar al final

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convertir'])) {
    $remove_bg = isset($_POST['remove_bg']) ? true : false;
    
    // Obtener todas las imágenes de productos
    $productos = $pdo->query('SELECT id, nombre, imagen FROM productos WHERE imagen IS NOT NULL AND imagen != ""')->fetchAll();
    
    // Registro de URLs ya procesadas para evitar duplicados
    $urls_procesadas = [];
    
    foreach ($productos as $prod) {
        $imagen_url = $prod['imagen'];
        
        // Si ya es PNG local y no se pide remover fondo, saltar
        $ext_url = strtolower(pathinfo(parse_url($imagen_url, PHP_URL_PATH) ?: $imagen_url, PATHINFO_EXTENSION));
        if ($ext_url === 'png' && !$remove_bg && strpos($imagen_url, base_url()) !== false) {
            $total_ya_png++;
            $resultados[] = [
                'producto' => $prod['nombre'],
                'status' => 'skip',
                'msg' => 'Ya es PNG local'
            ];
            continue;
        }
        
        // Obtener la imagen (local o remota)
        $ruta_fisica = resolve_image_path($imagen_url, $archivos_temp);
        
        if (!$ruta_fisica) {
            $total_errores++;
            $resultados[] = [
                'producto' => $prod['nombre'],
                'status' => 'error',
                'msg' => 'No se pudo obtener la imagen: ' . mb_substr($imagen_url, 0, 80) . '...'
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
            
            $urls_procesadas[$imagen_url] = $nueva_url;
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
        
        // Si ya fue procesada como imagen principal, actualizar directamente
        if (isset($urls_procesadas[$imagen_url])) {
            $stmt = $pdo->prepare('UPDATE imagenes_producto SET url_imagen = ? WHERE id = ?');
            $stmt->execute([$urls_procesadas[$imagen_url], $img['id']]);
            continue;
        }
        
        // Si ya es PNG local y no se pide remover fondo, saltar
        $ext_url = strtolower(pathinfo(parse_url($imagen_url, PHP_URL_PATH) ?: $imagen_url, PATHINFO_EXTENSION));
        if ($ext_url === 'png' && !$remove_bg && strpos($imagen_url, base_url()) !== false) {
            continue;
        }
        
        $ruta_fisica = resolve_image_path($imagen_url, $archivos_temp);
        
        if (!$ruta_fisica) {
            $total_errores++;
            $resultados[] = [
                'producto' => ($img['producto_nombre'] ?? 'Producto') . ' (galería)',
                'status' => 'error',
                'msg' => 'No se pudo obtener: ' . mb_substr($imagen_url, 0, 80) . '...'
            ];
            continue;
        }
        
        $upload_dir = __DIR__ . '/../uploads/productos/';
        $result = process_product_image($ruta_fisica, $upload_dir, 'gal_', $remove_bg);
        
        if ($result['ok']) {
            $nueva_url = base_url() . '/uploads/productos/' . $result['filename'];
            $stmt = $pdo->prepare('UPDATE imagenes_producto SET url_imagen = ? WHERE id = ?');
            $stmt->execute([$nueva_url, $img['id']]);
            
            $urls_procesadas[$imagen_url] = $nueva_url;
            $total_convertidas++;
            $resultados[] = [
                'producto' => ($img['producto_nombre'] ?? 'Producto') . ' (galería)',
                'status' => 'ok',
                'msg' => 'Imagen de galería convertida'
            ];
        } else {
            $total_errores++;
            $resultados[] = [
                'producto' => ($img['producto_nombre'] ?? 'Producto') . ' (galería)',
                'status' => 'error',
                'msg' => $result['error']
            ];
        }
    }
    
    // Limpiar archivos temporales descargados
    foreach ($archivos_temp as $tmp) {
        if (file_exists($tmp)) @unlink($tmp);
    }
}

/**
 * Resuelve la ruta física de una imagen a partir de su URL.
 * Si la imagen es remota (http/https), la descarga a un archivo temporal.
 * 
 * @param string $url            URL o ruta de la imagen
 * @param array  &$archivos_temp Array para trackear archivos temporales a limpiar
 * @return string|null           Ruta física del archivo o null si falla
 */
function resolve_image_path($url, &$archivos_temp = []) {
    $base = __DIR__ . '/../';
    
    // Caso 1: URL remota (http/https) — descargar a temporal
    if (strpos($url, 'http') === 0) {
        // Primero verificar si es una URL de nuestro propio sitio
        $our_base = base_url();
        if (strpos($url, $our_base) === 0) {
            // Es una URL local del sitio — intentar resolver como archivo
            $relative_path = str_replace($our_base, '', $url);
            $relative_path = ltrim($relative_path, '/');
            $local_path = $base . $relative_path;
            if (file_exists($local_path)) return $local_path;
        }
        
        // Es URL externa — descargar
        $tmp_file = download_remote_image($url);
        if ($tmp_file) {
            $archivos_temp[] = $tmp_file;
            return $tmp_file;
        }
        return null;
    }
    
    // Caso 2: Ruta relativa directa
    $ruta = $base . ltrim($url, '/');
    if (file_exists($ruta)) return $ruta;
    
    // Caso 3: Solo el nombre del archivo en uploads/productos/
    $ruta = $base . 'uploads/productos/' . basename($url);
    if (file_exists($ruta)) return $ruta;
    
    return null;
}

/**
 * Descarga una imagen remota a un archivo temporal.
 * Soporta redirecciones y establece un User-Agent válido.
 * 
 * @param string $url URL de la imagen
 * @return string|null Ruta del archivo temporal o null si falla
 */
function download_remote_image($url) {
    // Intentar con cURL primero (mejor para redirecciones y headers)
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $tmp_file = tempnam(sys_get_temp_dir(), 'img_conv_');
        $fp = fopen($tmp_file, 'wb');
        
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_HTTPHEADER => ['Accept: image/*'],
        ]);
        
        $success = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        
        if ($success && $http_code === 200 && filesize($tmp_file) > 100) {
            return $tmp_file;
        }
        
        // Falló — limpiar
        @unlink($tmp_file);
    }
    
    // Fallback con file_get_contents
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'header' => "Accept: image/*\r\n",
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);
    
    $data = @file_get_contents($url, false, $context);
    if ($data && strlen($data) > 100) {
        $tmp_file = tempnam(sys_get_temp_dir(), 'img_conv_');
        file_put_contents($tmp_file, $data);
        return $tmp_file;
    }
    
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
        <div class="bg-[#facc15]/10 border border-[#facc15]/20 rounded-xl p-5 mb-6 flex items-start gap-3">
            <i data-lucide="alert-triangle" class="w-6 h-6 text-[#facc15] shrink-0 mt-0.5"></i>
            <div>
                <div class="font-bold text-[#facc15] text-[0.95rem] mb-1.5">⚠️ Herramienta de Conversión Masiva</div>
                <div class="text-[0.82rem] text-[#999] leading-relaxed">
                    Esta herramienta convierte todas las imágenes de productos existentes a formato <strong class="text-white">PNG</strong>.
                    <br>• Las imágenes originales <strong class="text-white">NO se eliminan</strong>, se crean copias nuevas en PNG.
                    <br>• La base de datos se actualizará con las nuevas rutas.
                    <br>• <strong class="text-[#ef4444]">Elimina este archivo después de usarlo</strong> por seguridad.
                </div>
            </div>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convertir'])): ?>
        <!-- Resultados -->
        <div class="grid grid-cols-[repeat(auto-fit,minmax(160px,1fr))] gap-4 mb-6">
            <div class="adm-card !p-5 text-center">
                <div class="text-[2rem] font-extrabold text-[#4ade80]"><?= $total_convertidas ?></div>
                <div class="text-[0.75rem] text-[#666]">Convertidas</div>
            </div>
            <div class="adm-card !p-5 text-center">
                <div class="text-[2rem] font-extrabold text-[#facc15]"><?= $total_ya_png ?></div>
                <div class="text-[0.75rem] text-[#666]">Ya eran PNG</div>
            </div>
            <div class="adm-card !p-5 text-center">
                <div class="text-[2rem] font-extrabold text-[#ef4444]"><?= $total_errores ?></div>
                <div class="text-[0.75rem] text-[#666]">Errores</div>
            </div>
        </div>

        <!-- Tabla de resultados -->
        <div class="adm-card !p-0 overflow-hidden">
            <div class="py-4 px-5 border-b border-white/5">
                <div class="adm-card-title !mb-0">
                    <span class="adm-card-title-text">Resultados de Conversión</span>
                </div>
            </div>
            <div class="adm-table-wrap !border-none !rounded-none">
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
                            <td class="font-semibold text-[0.82rem] text-white"><?= htmlspecialchars($r['producto']) ?></td>
                            <td>
                                <?php if ($r['status'] === 'ok'): ?>
                                    <span class="adm-badge adm-badge-green">✓ OK</span>
                                <?php elseif ($r['status'] === 'skip'): ?>
                                    <span class="adm-badge adm-badge-gray">⏭ Omitido</span>
                                <?php else: ?>
                                    <span class="adm-badge adm-badge-red">✗ Error</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-[0.78rem] text-[#888]"><?= htmlspecialchars($r['msg']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulario -->
        <div class="adm-card !p-6 mt-6">
            <form method="post">
                <input type="hidden" name="convertir" value="1">
                
                <div class="flex items-center gap-3 p-4 bg-white/[0.03] rounded-xl border border-white/[0.06] mb-5">
                    <label class="relative inline-block w-12 h-[26px] shrink-0">
                        <input type="checkbox" name="remove_bg" value="1" class="opacity-0 w-0 h-0" id="toggle-removebg-conv">
                        <span class="adm-toggle-slider"></span>
                    </label>
                    <div>
                        <div class="text-[0.88rem] font-semibold text-[#e7e7ea]">🎨 Remover fondo automáticamente</div>
                        <div class="text-[0.72rem] text-[#666]">Detecta y elimina el fondo de las imágenes. Funciona mejor con fondos sólidos (blanco, gris).</div>
                    </div>
                </div>

                <button type="submit" class="adm-btn adm-btn-primary w-full justify-center !p-[0.85rem] !text-[0.95rem]"
                        onclick="return confirm('¿Estás seguro de convertir TODAS las imágenes de productos a PNG? Este proceso puede tardar varios minutos.')">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-[18px] h-[18px]"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Iniciar Conversión a PNG
                </button>
            </form>
        </div>
    </div>
</main>


<?php include '_layout_end.php'; ?>
