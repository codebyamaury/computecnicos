<?php
// Sesión manejada por bootstrap (DB handler)
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
require_once __DIR__ . '/../app/Core/bootstrap.php';
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
    $precio = floatval($_POST['precio'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $id_categoria = intval($_POST['id_categoria'] ?? 0);
    $id_marca = intval($_POST['id_marca'] ?? 0);
    $oferta = isset($_POST['oferta']) ? 1 : 0;
    // Subida de nuevas imágenes
    $nuevas_imagenes = [];
    if (isset($_FILES['imagenes']) && count($_FILES['imagenes']['name']) > 0) {
        foreach ($_FILES['imagenes']['tmp_name'] as $idx => $tmp_name) {
            if ($_FILES['imagenes']['error'][$idx] === UPLOAD_ERR_OK) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.imgur.com/3/image');
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Client-ID 546c25a59c58ad7']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => base64_encode(file_get_contents($tmp_name))]);
                $reply = json_decode(curl_exec($ch));
                curl_close($ch);
                if (isset($reply->data->link)) {
                    $nuevas_imagenes[] = $reply->data->link;
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
    // Reemplazar imagen principal si se sube una nueva
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK && !empty($_FILES['imagen']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.imgur.com/3/image');
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Client-ID 546c25a59c58ad7']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => base64_encode(file_get_contents($_FILES['imagen']['tmp_name']))]);
            $reply = json_decode(curl_exec($ch));
            curl_close($ch);
            
            if (isset($reply->data->link)) {
                if (!empty($producto['imagen']) && strpos($producto['imagen'], 'http') !== 0) {
                    $ruta_anterior = '../' . $producto['imagen'];
                    if (is_file($ruta_anterior)) { @unlink($ruta_anterior); }
                }
                $producto['imagen'] = $reply->data->link;
            }
        }
    }
    // Eliminar imágenes seleccionadas
    if (!empty($_POST['eliminar_imagen'])) {
        foreach ($_POST['eliminar_imagen'] as $id_img => $val) {
            // Obtener la URL de la imagen
            $stmt = $pdo->prepare('SELECT url_imagen FROM imagenes_producto WHERE id=? AND id_producto=?');
            $stmt->execute([$id_img, $id]);
            $img = $stmt->fetch();
            if ($img) {
                // Eliminar archivo físico
                $ruta_fisica = '../' . $img['url_imagen'];
                if (file_exists($ruta_fisica)) unlink($ruta_fisica);
                // Eliminar de la base de datos
                $pdo->prepare('DELETE FROM imagenes_producto WHERE id=?')->execute([$id_img]);
                // Si era la imagen principal, actualizar principal
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
    // Actualizar datos del producto
    if ($nombre && $precio > 0 && $id_categoria && $id_marca) {
        $stmt = $pdo->prepare('UPDATE productos SET nombre=?, descripcion=?, precio=?, stock=?, imagen=?, id_categoria=?, id_marca=?, oferta=? WHERE id=?');
        $stmt->execute([$nombre, $descripcion, $precio, $stock, $producto['imagen'], $id_categoria, $id_marca, $oferta, $id]);
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto | Computécnicos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#181818] text-white min-h-screen flex flex-col">
    <header class="bg-[#232323] border-b border-[#333] py-4 px-8 flex items-center justify-between">
        <span class="text-2xl font-bold text-red-600">Editar Producto</span>
        <a href="productos.php" class="text-gray-300 hover:text-red-500 transition">Volver a productos</a>
    </header>
    <main class="flex-1 container mx-auto px-4 py-12 max-w-xl">
        <h2 class="text-2xl font-bold mb-6">Editar Producto</h2>
        <?php if ($mensaje): ?>
            <div class="mb-4 p-3 rounded text-white <?php echo strpos($mensaje, 'correctamente') !== false ? 'bg-green-600' : 'bg-red-600'; ?>"> <?php echo $mensaje; ?> </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="space-y-5 bg-[#232323] p-6 rounded-xl border border-[#333] shadow-lg">
            <div>
                <label class="block mb-1">Nombre *</label>
                <input type="text" name="nombre" class="w-full bg-[#181818] border border-[#333] rounded-lg px-3 py-2 text-white focus:border-red-600 outline-none" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required>
            </div>
            <div>
                <label class="block mb-1">Descripción</label>
                <textarea name="descripcion" rows="3" class="w-full bg-[#181818] border border-[#333] rounded-lg px-3 py-2 text-white focus:border-red-600 outline-none"><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
            </div>
            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block mb-1">Precio (COP) *</label>
                    <input type="number" name="precio" min="0" step="0.01" class="w-full bg-[#181818] border border-[#333] rounded-lg px-3 py-2 text-white focus:border-red-600 outline-none" value="<?php echo htmlspecialchars($producto['precio']); ?>" required>
                </div>
                <div class="flex-1">
                    <label class="block mb-1">Stock *</label>
                    <input type="number" name="stock" min="0" class="w-full bg-[#181818] border border-[#333] rounded-lg px-3 py-2 text-white focus:border-red-600 outline-none" value="<?php echo htmlspecialchars($producto['stock']); ?>" required>
                </div>
            </div>
            <div>
                <label class="block mb-1">Imagen actual</label>
                <img src="<?php echo htmlspecialchars((strpos($producto['imagen'], 'http') === 0) ? $producto['imagen'] : '../' . ($producto['imagen'] ?: 'uploads/products/default.png')); ?>" alt="Imagen actual" class="w-32 h-20 rounded object-cover mb-2">
                <input type="file" name="imagen" accept="image/*" class="w-full text-white">
            </div>
            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block mb-1">Categoría *</label>
                    <select name="id_categoria" class="w-full bg-[#181818] border border-[#333] rounded-lg px-3 py-2 text-white focus:border-red-600 outline-none" required>
                        <option value="">Selecciona</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php if($producto['id_categoria']==$cat['id']) echo 'selected'; ?>><?php echo htmlspecialchars($cat['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block mb-1">Marca *</label>
                    <select name="id_marca" class="w-full bg-[#181818] border border-[#333] rounded-lg px-3 py-2 text-white focus:border-red-600 outline-none" required>
                        <option value="">Selecciona</option>
                        <?php foreach ($marcas as $marca): ?>
                            <option value="<?php echo $marca['id']; ?>" <?php if($producto['id_marca']==$marca['id']) echo 'selected'; ?>><?php echo htmlspecialchars($marca['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="oferta" id="oferta" value="1" class="accent-red-600" <?php if($producto['oferta']) echo 'checked'; ?>>
                <label for="oferta">¿Producto en oferta?</label>
            </div>
            <div>
                <label class="block mb-1">Imágenes actuales</label>
                <div class="flex flex-wrap gap-3 mb-2">
                <?php foreach ($imagenes as $img): ?>
                    <div class="relative inline-block">
                        <img src="<?php echo htmlspecialchars((strpos($img['url_imagen'], 'http') === 0) ? $img['url_imagen'] : '../' . $img['url_imagen']); ?>" alt="img" class="w-24 h-16 object-cover rounded border border-[#333]">
                        <label class="absolute top-0 right-0 bg-red-600 text-white text-xs rounded-full px-1 cursor-pointer">
                            <input type="checkbox" name="eliminar_imagen[<?php echo $img['id']; ?>]" value="1" class="hidden">
                            ×
                        </label>
                    </div>
                <?php endforeach; ?>
                </div>
                <label class="block mb-1">Agregar nuevas imágenes</label>
                <input type="file" name="imagenes[]" accept="image/*" class="w-full text-white" multiple>
            </div>
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded-lg mt-4">Guardar cambios</button>
        </form>
    </main>
</body>
</html>