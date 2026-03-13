<?php
/**
 * image_helper.php — Utilidades de procesamiento de imágenes
 * 
 * Convierte cualquier imagen subida a PNG con fondo transparente.
 * Utiliza la librería GD de PHP para la conversión y remoción de fondo.
 */

/**
 * Procesa una imagen subida: la convierte a PNG y opcionalmente remueve el fondo.
 * 
 * @param string $tmp_path    Ruta temporal del archivo subido ($_FILES['x']['tmp_name'])
 * @param string $dest_dir    Directorio destino (se creará si no existe)
 * @param string $prefix      Prefijo para el nombre de archivo (ej: 'prod_', 'main_')
 * @param bool   $remove_bg   Si debe intentar remover el fondo
 * @param int    $tolerance   Tolerancia de color para remoción de fondo (0-100)
 * @return array ['ok' => bool, 'filename' => string, 'error' => string]
 */
function process_product_image($tmp_path, $dest_dir, $prefix = 'prod_', $remove_bg = true, $tolerance = 30) {
    // Verificar que GD esté disponible
    if (!extension_loaded('gd')) {
        return ['ok' => false, 'filename' => '', 'error' => 'La extensión GD de PHP no está instalada'];
    }

    // Crear directorio si no existe
    if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0777, true);
    }

    // Detectar tipo de imagen
    $image_info = @getimagesize($tmp_path);
    if (!$image_info) {
        return ['ok' => false, 'filename' => '', 'error' => 'No se pudo leer la imagen'];
    }

    $mime = $image_info['mime'];
    $source = null;

    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $source = @imagecreatefromjpeg($tmp_path);
            break;
        case 'image/png':
            $source = @imagecreatefrompng($tmp_path);
            break;
        case 'image/gif':
            $source = @imagecreatefromgif($tmp_path);
            break;
        case 'image/webp':
            $source = @imagecreatefromwebp($tmp_path);
            break;
        case 'image/bmp':
            $source = @imagecreatefrombmp($tmp_path);
            break;
        default:
            return ['ok' => false, 'filename' => '', 'error' => 'Formato de imagen no soportado: ' . $mime];
    }

    if (!$source) {
        return ['ok' => false, 'filename' => '', 'error' => 'Error al procesar la imagen'];
    }

    $width = imagesx($source);
    $height = imagesy($source);

    // Crear nueva imagen con soporte de transparencia
    $output = imagecreatetruecolor($width, $height);
    imagealphablending($output, false);
    imagesavealpha($output, true);

    // Rellenar con transparencia
    $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
    imagefill($output, 0, 0, $transparent);

    // Copiar imagen original
    imagealphablending($output, true);
    imagecopy($output, $source, 0, 0, 0, 0, $width, $height);

    // Remover fondo si se solicita
    if ($remove_bg) {
        $output = remove_background($output, $width, $height, $tolerance);
    }

    // Guardar como PNG
    $nuevo_nombre = $prefix . uniqid() . '_' . time() . '.png';
    $destino_fisico = rtrim($dest_dir, '/\\') . '/' . $nuevo_nombre;

    imagealphablending($output, false);
    imagesavealpha($output, true);

    $saved = imagepng($output, $destino_fisico, 6); // Compresión nivel 6 (0-9)

    // Liberar memoria
    imagedestroy($source);
    imagedestroy($output);

    if (!$saved) {
        return ['ok' => false, 'filename' => '', 'error' => 'Error al guardar la imagen PNG'];
    }

    return ['ok' => true, 'filename' => $nuevo_nombre, 'error' => ''];
}

/**
 * Remueve el fondo de una imagen usando el color de las esquinas como referencia.
 * Usa flood fill transparente desde las esquinas y bordes.
 * 
 * @param resource $image      Imagen GD
 * @param int      $width      Ancho
 * @param int      $height     Alto
 * @param int      $tolerance  Tolerancia de color (0-100)
 * @return resource Imagen procesada
 */
function remove_background($image, $width, $height, $tolerance = 30) {
    // Muestrear color de las esquinas para determinar el color de fondo
    $corners = [
        [0, 0],                     // Esquina superior izquierda
        [$width - 1, 0],            // Esquina superior derecha
        [0, $height - 1],           // Esquina inferior izquierda
        [$width - 1, $height - 1],  // Esquina inferior derecha
    ];

    // Obtener colores de las esquinas
    $corner_colors = [];
    foreach ($corners as $corner) {
        $rgb = imagecolorat($image, $corner[0], $corner[1]);
        $corner_colors[] = [
            'r' => ($rgb >> 16) & 0xFF,
            'g' => ($rgb >> 8) & 0xFF,
            'b' => $rgb & 0xFF,
        ];
    }

    // Encontrar el color más común entre las esquinas (el fondo probable)
    $bg_color = find_dominant_corner_color($corner_colors);

    if (!$bg_color) {
        return $image; // No se pudo determinar el color de fondo
    }

    // Crear imagen de salida con transparencia
    $output = imagecreatetruecolor($width, $height);
    imagealphablending($output, false);
    imagesavealpha($output, true);
    $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
    imagefill($output, 0, 0, $transparent);

    // Copiar píxeles, haciendo transparentes los que coincidan con el fondo
    $tolerance_sq = $tolerance * $tolerance * 3; // Tolerancia al cuadrado para distancia euclidiana

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $a = ($rgb >> 24) & 0x7F; // Alpha existente

            // Si ya es transparente, mantener
            if ($a > 0) {
                $color = imagecolorallocatealpha($output, $r, $g, $b, $a);
                imagesetpixel($output, $x, $y, $color);
                continue;
            }

            // Calcular distancia de color al fondo
            $dr = $r - $bg_color['r'];
            $dg = $g - $bg_color['g'];
            $db = $b - $bg_color['b'];
            $dist = ($dr * $dr) + ($dg * $dg) + ($db * $db);

            if ($dist <= $tolerance_sq) {
                // Es parte del fondo — hacer transparente
                imagesetpixel($output, $x, $y, $transparent);
            } else {
                // Es parte del objeto — mantener opaco
                $color = imagecolorallocatealpha($output, $r, $g, $b, 0);
                imagesetpixel($output, $x, $y, $color);
            }
        }
    }

    imagedestroy($image);
    return $output;
}

/**
 * Encuentra el color dominante entre los colores de las esquinas.
 * Si hay colores similares, los agrupa y devuelve el promedio del grupo más grande.
 * 
 * @param array $colors Array de colores ['r','g','b']
 * @return array|null Color dominante ['r','g','b'] o null
 */
function find_dominant_corner_color($colors) {
    if (empty($colors)) return null;
    if (count($colors) === 1) return $colors[0];

    // Agrupar colores similares (distancia < 50)
    $groups = [];
    $used = [];

    for ($i = 0; $i < count($colors); $i++) {
        if (in_array($i, $used)) continue;

        $group = [$colors[$i]];
        $used[] = $i;

        for ($j = $i + 1; $j < count($colors); $j++) {
            if (in_array($j, $used)) continue;

            $dr = $colors[$i]['r'] - $colors[$j]['r'];
            $dg = $colors[$i]['g'] - $colors[$j]['g'];
            $db = $colors[$i]['b'] - $colors[$j]['b'];
            $dist = sqrt($dr * $dr + $dg * $dg + $db * $db);

            if ($dist < 50) {
                $group[] = $colors[$j];
                $used[] = $j;
            }
        }

        $groups[] = $group;
    }

    // Encontrar el grupo más grande
    usort($groups, function ($a, $b) {
        return count($b) - count($a);
    });

    $dominant = $groups[0];

    // Calcular promedio del grupo
    $sum_r = $sum_g = $sum_b = 0;
    foreach ($dominant as $c) {
        $sum_r += $c['r'];
        $sum_g += $c['g'];
        $sum_b += $c['b'];
    }
    $count = count($dominant);

    return [
        'r' => intval($sum_r / $count),
        'g' => intval($sum_g / $count),
        'b' => intval($sum_b / $count),
    ];
}

/**
 * Wrapper para mover y procesar una imagen subida de producto.
 * Reemplaza la lógica de move_uploaded_file + extensión original.
 * 
 * @param string $tmp_path   Ruta temporal del archivo
 * @param string $upload_dir Directorio destino físico
 * @param string $base_url   URL base del sitio
 * @param string $prefix     Prefijo del archivo
 * @param bool   $remove_bg  Si remover fondo
 * @return array ['ok' => bool, 'url' => string, 'error' => string]
 */
function upload_product_image($tmp_path, $upload_dir, $base_url, $prefix = 'prod_', $remove_bg = true) {
    $result = process_product_image($tmp_path, $upload_dir, $prefix, $remove_bg);

    if (!$result['ok']) {
        // Fallback: si GD falla, hacer subida tradicional como PNG sin procesar
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $nuevo_nombre = $prefix . uniqid() . '_' . time() . '.png';
        $destino = rtrim($upload_dir, '/\\') . '/' . $nuevo_nombre;

        if (move_uploaded_file($tmp_path, $destino)) {
            return [
                'ok' => true,
                'url' => rtrim($base_url, '/') . '/uploads/productos/' . $nuevo_nombre,
                'error' => 'Imagen subida sin procesamiento (GD no disponible): ' . $result['error']
            ];
        }
        return ['ok' => false, 'url' => '', 'error' => $result['error']];
    }

    return [
        'ok' => true,
        'url' => rtrim($base_url, '/') . '/uploads/productos/' . $result['filename'],
        'error' => ''
    ];
}
