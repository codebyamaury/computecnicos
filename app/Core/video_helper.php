<?php
/**
 * video_helper.php — Gestión de carga de videos de productos
 */

/**
 * Valida que el archivo subido sea un video compatible y lo mueve al directorio destino.
 * 
 * @param array  $file        Array del archivo de $_FILES['video']
 * @param string $dest_dir    Directorio destino físico
 * @param string $base_url    URL base para la respuesta
 * @param string $prefix      Prefijo para el nombre
 * @return array ['ok' => bool, 'url' => string, 'error' => string]
 */
function upload_product_video($file, $dest_dir, $base_url, $prefix = 'vid_') {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errores = [
            UPLOAD_ERR_INI_SIZE   => 'El video supera el límite de tamaño del servidor (upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE  => 'El video supera el límite de tamaño del formulario',
            UPLOAD_ERR_PARTIAL    => 'El video se subió parcialmente',
            UPLOAD_ERR_NO_FILE    => 'No se seleccionó ningún video',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal en el servidor',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el video en disco',
        ];
        $msg = $errores[$file['error']] ?? 'Error desconocido al subir el video (código ' . $file['error'] . ')';
        return ['ok' => false, 'url' => '', 'error' => $msg];
    }

    // Crear directorio si no existe
    if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0777, true);
    }

    // Extensiones permitidas
    $allowed_exts = ['mp4', 'webm', 'ogg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_exts)) {
        return ['ok' => false, 'url' => '', 'error' => 'Extensión no permitida (' . $ext . '). Usa MP4, WebM o OGG.'];
    }

    // Verificar MIME — aceptar mimes de video Y application/octet-stream (común en servidores Linux)
    $allowed_mimes = ['video/mp4', 'video/webm', 'video/ogg', 'application/octet-stream', 'video/x-m4v', 'video/x-matroska'];
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    if ($finfo) {
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    } else {
        $mime = $file['type'];
    }

    if (!in_array($mime, $allowed_mimes)) {
        return ['ok' => false, 'url' => '', 'error' => 'Tipo de archivo no permitido (' . $mime . '). Usa MP4, WebM o OGG.'];
    }

    // Tamaño máximo: 100MB
    $max_size = 100 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return ['ok' => false, 'url' => '', 'error' => 'El video supera el límite de 100MB'];
    }

    // Generar nombre único
    $nuevo_nombre = $prefix . uniqid() . '_' . time() . '.' . $ext;
    $destino = rtrim($dest_dir, '/\\') . '/' . $nuevo_nombre;

    if (move_uploaded_file($file['tmp_name'], $destino)) {
        return [
            'ok' => true,
            'url' => 'uploads/videos/' . $nuevo_nombre,
            'error' => ''
        ];
    }

    return ['ok' => false, 'url' => '', 'error' => 'Error al guardar el video en el servidor. Verifica permisos de la carpeta uploads/videos/'];
}
