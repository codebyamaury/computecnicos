<?php
// Bootstrap PRIMERO: inicia sesión desde la BD antes de verificar permisos
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}

$productos = $pdo->query('SELECT id, nombre, stock FROM productos ORDER BY nombre')->fetchAll();
$proveedores = $pdo->query('SELECT id, nombre FROM proveedores ORDER BY nombre')->fetchAll();
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
                    WHERE id_producto = ? AND tipo = 'entrada' AND soporte_documental IS NOT NULL 
                    ORDER BY fecha DESC LIMIT 1');
                $stmt_existente->execute([$id_producto]);
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
                header('Location: inventario.php?exito=1');
                exit;
            } else {
                throw new Exception('Error al guardar el movimiento.');
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Movimiento | Computécnicos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#181818] text-white min-h-screen flex flex-col">
    <header class="bg-[#232323] border-b border-[#333] py-4 px-8 flex items-center justify-between">
        <span class="text-2xl font-bold text-red-600">Nuevo Movimiento</span>
        <a href="inventario.php" class="text-gray-300 hover:text-red-500 transition">Volver a inventario</a>
    </header>
    <main class="flex-1 container mx-auto px-4 py-12 max-w-2xl">
        <h2 class="text-2xl font-bold mb-8">Registrar movimiento de inventario</h2>
        <?php if ($errores): ?>
            <div class="bg-red-800 text-white rounded p-4 mb-6">
                <?php foreach ($errores as $e): ?>
                    <div>• <?php echo htmlspecialchars($e); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="space-y-5 bg-[#232323] p-8 rounded-lg shadow">
            <div>
                <label class="block mb-1 font-semibold">Producto *</label>
                <select name="id_producto" id="id_producto" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" required>
                    <option value="">Selecciona un producto</option>
                    <?php foreach ($productos as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php if(isset($_POST['id_producto']) && $_POST['id_producto']==$p['id']) echo 'selected'; ?>><?php echo htmlspecialchars($p['nombre']) . ' (Stock actual: ' . $p['stock'] . ')'; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block mb-1 font-semibold">Tipo *</label>
                <select name="tipo" id="tipo" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" required onchange="mostrarCamposEntrada()">
                    <option value="">Selecciona tipo</option>
                    <option value="entrada" <?php if(($_POST['tipo'] ?? '')==='entrada') echo 'selected'; ?>>Entrada (compra)</option>
                    <option value="salida" <?php if(($_POST['tipo'] ?? '')==='salida') echo 'selected'; ?>>Salida (venta/ajuste)</option>
                    <option value="ajuste" <?php if(($_POST['tipo'] ?? '')==='ajuste') echo 'selected'; ?>>Ajuste</option>
                </select>
            </div>
            
            <!-- Campos específicos para entradas (compras) -->
            <div id="campos_entrada" style="display: none;" class="space-y-4 p-4 bg-[#181818] rounded border border-[#333]">
                <h3 class="font-semibold text-green-400 mb-3">Información de Compra</h3>
                <div>
                    <label class="block mb-1 font-semibold">Proveedor *</label>
                    <select name="id_proveedor" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white">
                        <option value="">Selecciona un proveedor</option>
                        <?php foreach ($proveedores as $prov): ?>
                            <option value="<?php echo $prov['id']; ?>" <?php if(isset($_POST['id_proveedor']) && $_POST['id_proveedor']==$prov['id']) echo 'selected'; ?>><?php echo htmlspecialchars($prov['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Número de factura/soporte *</label>
                    <input type="text" name="numero_factura" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['numero_factura'] ?? ''); ?>">
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Fecha de factura</label>
                    <input type="date" name="fecha_factura" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['fecha_factura'] ?? ''); ?>">
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Precio unitario de compra *</label>
                    <input type="number" name="precio_unitario" min="0" step="0.01" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['precio_unitario'] ?? ''); ?>">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-1 font-semibold">IVA (valor pagado)</label>
                        <input type="number" name="iva" min="0" step="0.01" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['iva'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="block mb-1 font-semibold">Retención (valor)</label>
                        <input type="number" name="retencion" min="0" step="0.01" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['retencion'] ?? ''); ?>">
                    </div>
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Soporte (PDF/JPG)</label>
                    <input type="file" name="soporte" accept=".pdf,image/*" class="w-full text-white">
                    <p class="text-xs text-gray-400 mt-1">Máximo 5MB. Formatos: PDF, JPG, JPEG, PNG</p>
                </div>
            </div>
            
            <div>
                <label class="block mb-1 font-semibold">Cantidad *</label>
                <input type="number" name="cantidad" min="1" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" required value="<?php echo htmlspecialchars($_POST['cantidad'] ?? ''); ?>">
            </div>
            <div>
                <label class="block mb-1 font-semibold">Motivo</label>
                <input type="text" name="motivo" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['motivo'] ?? ''); ?>">
            </div>
            <div class="pt-4">
                <button type="submit" class="w-full bg-green-700 hover:bg-green-800 text-white font-bold py-2 rounded">Registrar movimiento</button>
            </div>
        </form>
    </main>
    
    <script>
    function mostrarCamposEntrada() {
        var tipo = document.getElementById('tipo').value;
        var camposEntrada = document.getElementById('campos_entrada');
        if (tipo === 'entrada') {
            camposEntrada.style.display = 'block';
        } else {
            camposEntrada.style.display = 'none';
        }
    }
    
    // Mostrar campos si ya está seleccionado entrada
    document.addEventListener('DOMContentLoaded', function() {
        mostrarCamposEntrada();
    });
    </script>
</body>
</html>