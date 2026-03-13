<?php
/**
 * image_helper.php — Validación de imágenes de productos
 * 
 * Solo se permiten imágenes en formato PNG.
 */

/**
 * Valida que el archivo subido sea PNG y lo mueve al directorio destino.
 * 
 * @param string $tmp_path    Ruta temporal del archivo subido
 * @param string $dest_dir    Directorio destino
 * @param string $prefix      Prefijo para el nombre (ej: 'prod_', 'main_')
 * @return array ['ok' => bool, 'url' => string, 'error' => string]
 */
function upload_product_image($tmp_path, $dest_dir, $base_url, $prefix = 'prod_') {
    // Crear directorio si no existe
    if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0777, true);
    }

    // Verificar que sea PNG real (no solo por extensión)
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    if ($finfo) {
        $mime = finfo_file($finfo, $tmp_path);
        finfo_close($finfo);
    } else {
        $info = @getimagesize($tmp_path);
        $mime = $info['mime'] ?? '';
    }

    if ($mime !== 'image/png') {
        return ['ok' => false, 'url' => '', 'error' => 'Solo se permiten imágenes PNG. Formato detectado: ' . $mime];
    }

    // Generar nombre único y mover
    $nuevo_nombre = $prefix . uniqid() . '_' . time() . '.png';
    $destino = rtrim($dest_dir, '/\\') . '/' . $nuevo_nombre;

    if (move_uploaded_file($tmp_path, $destino)) {
        return [
            'ok' => true,
            'url' => rtrim($base_url, '/') . '/uploads/productos/' . $nuevo_nombre,
            'error' => ''
        ];
    }

    return ['ok' => false, 'url' => '', 'error' => 'Error al guardar el archivo en el servidor'];
}
