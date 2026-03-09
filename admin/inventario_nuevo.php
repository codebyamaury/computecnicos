<?php
// Bootstrap PRIMERO: inicia sesión desde la BD antes de verificar permisos
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    die('Acceso denegado');
}

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_producto = intval($_POST['id_producto'] ?? 0);
    $tipo = $_POST['tipo'] ?? '';
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? '');
    $id_usuario = $_SESSION['usuario']['id'];
    
    // Nuevos campos contables
    $id_proveedor = !empty($_POST['id_proveedor']) ? intval($_POST['id_proveedor']) : null;
    $numero_factura = trim($_POST['numero_factura'] ?? '');
    $precio_unitario = !empty($_POST['precio_unitario']) ? floatval($_POST['precio_unitario']) : null;
    $iva = !empty($_POST['iva']) ? floatval($_POST['iva']) : null;
    $retencion = !empty($_POST['retencion']) ? floatval($_POST['retencion']) : null;
    $fecha_factura = !empty($_POST['fecha_factura']) ? $_POST['fecha_factura'] : null;
    
    // Validaciones básicas
    if ($id_producto <= 0) $errores[] = 'Selecciona un producto.';
    if (!in_array($tipo, ['entrada','salida','ajuste'])) $errores[] = 'Selecciona un tipo válido.';
    if ($cantidad <= 0) $errores[] = 'La cantidad debe ser mayor a cero.';
    
    // Validaciones específicas para entradas (compras)
    if ($tipo === 'entrada') {
        if (empty($id_proveedor)) $errores[] = 'Selecciona un proveedor para la compra.';
        if (empty($numero_factura)) $errores[] = 'El número de factura es obligatorio para compras.';
        if ($precio_unitario === null || $precio_unitario <= 0) $errores[] = 'El precio unitario es obligatorio para compras.';
    }
    
    // Manejo de archivo de soporte
    $soporte_documental = null;
    if (isset($_FILES['soporte']) && $_FILES['soporte']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['soporte'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
        
        if (!in_array($extension, $extensiones_permitidas)) {
            $errores[] = 'El archivo debe ser PDF, JPG, JPEG o PNG.';
        } elseif ($archivo['size'] > 5 * 1024 * 1024) { // 5MB máximo
            $errores[] = 'El archivo no puede superar 5MB.';
        } else {
            // Crear directorio si no existe
            $upload_dir = '../uploads/soportes/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Verificar si ya existe un soporte para este producto y compra
            $soporte_existente = null;
            if ($id_producto && $tipo === 'entrada') {
                $stmt_existente = $pdo->prepare('SELECT soporte_documental FROM movimientos_inventario 
                    WHERE id_producto = ? AND tipo = ? AND soporte_documental IS NOT NULL 
                    ORDER BY fecha DESC LIMIT 1');
                $stmt_existente->execute([$id_producto, 'entrada']);
                $soporte_existente = $stmt_existente->fetchColumn();
            }
            
            // Eliminar el soporte anterior si existe
            if ($soporte_existente && file_exists('../' . $soporte_existente)) {
                unlink('../' . $soporte_existente);
            }
            
            // Generar nombre único para el archivo basado en producto y timestamp
            $nombre_archivo = 'soporte_' . $id_producto . '_' . time() . '.' . $extension;
            $ruta_completa = $upload_dir . $nombre_archivo;
            
            if (move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
                $soporte_documental = 'uploads/soportes/' . $nombre_archivo;
            } else {
                $errores[] = 'Error al subir el archivo.';
            }
        }
    }
    
    if (empty($errores)) {
        try {
            $pdo->beginTransaction();
            
            // Preparar la consulta SQL con los nuevos campos
            $sql = 'INSERT INTO movimientos_inventario (id_producto, id_proveedor, numero_factura, precio_unitario, iva, retencion, soporte_documental, fecha_factura, tipo, cantidad, motivo, id_usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = $pdo->prepare($sql);
            
            $ok = $stmt->execute([
                $id_producto,
                $id_proveedor,
                $numero_factura ?: null,
                $precio_unitario,
                $iva,
                $retencion,
                $soporte_documental,
                $fecha_factura,
                $tipo,
                $cantidad,
                $motivo,
                $id_usuario
            ]);
            
            if ($ok) {
                // Actualizar stock del producto
                if ($tipo === 'entrada') {
                    $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?')->execute([$cantidad, $id_producto]);
                } elseif ($tipo === 'salida') {
                    // Verificar que hay suficiente stock
                    $stock_actual = $pdo->query("SELECT stock FROM productos WHERE id = $id_producto")->fetchColumn();
                    if ($stock_actual < $cantidad) {
                        throw new Exception('No hay suficiente stock disponible.');
                    }
                    $pdo->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?')->execute([$cantidad, $id_producto]);
                }
                // ajuste no modifica stock automáticamente
                
                $pdo->commit();
                echo 'success';
                exit;
            } else {
                throw new Exception('Error al guardar el movimiento.');
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = $e->getMessage();
        }
    }

    if (!empty($errores)) {
        echo implode('<br>', $errores);
    }

} else {
    // Si entran por GET (URL), redirigir directamente al modal en inventario.php
    header('Location: inventario.php?nuevo=1');
    exit;
}