<?php
// Sesión manejada por bootstrap (DB handler)
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
require_once __DIR__ . '/../app/Core/bootstrap.php';
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
    $imagenes_urls = [];
    $error_imagen = '';
    // Subida de imágenes múltiples
    if (isset($_FILES['imagenes']) && count($_FILES['imagenes']['name']) > 0) {
        foreach ($_FILES['imagenes']['tmp_name'] as $idx => $tmp_name) {
            if ($_FILES['imagenes']['error'][$idx] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['imagenes']['name'][$idx], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                    $error_imagen = 'Formato de imagen no permitido: ' . htmlspecialchars($ext);
                    break;
                }
                // Subir a Imgur API de forma anónima para evitar fallos de Vercel (Read-Only)
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.imgur.com/3/image');
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Client-ID 546c25a59c58ad7']);
                curl_setopt($ch, CURLOPT_POSTFIELDS, [
                    'image' => base64_encode(file_get_contents($tmp_name)),
                    'type'  => 'base64'
                ]);
                $reply = json_decode(curl_exec($ch));
                curl_close($ch);
                
                if (isset($reply->data->link)) {
                    $imagenes_urls[] = $reply->data->link;
                } else {
                    $error_imagen = 'Imgur Error: ' . ($reply->data->error->message ?? json_encode($reply->data->error ?? 'Desconocido'));
                    break;
                }
            } else if ($_FILES['imagenes']['error'][$idx] !== UPLOAD_ERR_NO_FILE) {
                $error_imagen = 'Error al subir la imagen: ' . $_FILES['imagenes']['name'][$idx];
                break;
            }
        }
    }
    $imagen_principal = $imagenes_urls[0] ?? '';
    if ($error_imagen) {
        $mensaje = $error_imagen;
    } else if ($nombre && $precio > 0 && $id_categoria && $id_marca && $imagen_principal) {
        try {
            $stmt = $pdo->prepare('INSERT INTO productos (nombre, descripcion, precio, stock, imagen, id_categoria, id_marca, oferta, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$nombre, $descripcion, $precio, $stock, $imagen_principal, $id_categoria, $id_marca, $oferta]);
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Producto | Computécnicos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#181818] text-white min-h-screen flex flex-col">
    <header class="bg-[#232323] border-b border-[#333] py-4 px-8 flex items-center justify-between">
        <span class="text-2xl font-bold text-red-600">Agregar Producto</span>
        <a href="productos.php" class="text-gray-300 hover:text-red-500 transition">Volver a productos</a>
    </header>
    <main class="flex-1 container mx-auto px-4 py-12 max-w-xl">
        <h2 class="text-2xl font-bold mb-6">Nuevo Producto</h2>
        <?php if ($mensaje): ?>
            <div class="mb-4 p-3 rounded text-white <?php echo strpos($mensaje, 'correctamente') !== false ? 'bg-green-600' : 'bg-red-600'; ?>"> <?php echo $mensaje; ?> </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="space-y-5 bg-[#232323] p-6 rounded-xl border border-[#333] shadow-lg">
            <div>
                <label class="block mb-1">Nombre *</label>
                <input type="text" name="nombre" class="w-full bg-[#181818] border border-[#333] rounded-lg px-3 py-2 text-white focus:border-red-600 outline-none" required>
            </div>
            <div>
                <label class="block mb-1">Descripción</label>
                <textarea name="descripcion" rows="3" class="w-full bg-[#181818] border border-[#333] rounded-lg px-3 py-2 text-white focus:border-red-600 outline-none"></textarea>
            </div>
            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block mb-1">Precio (COP) *</label>
                    <input type="number" name="precio" min="0" step="0.01" class="w-full bg-[#181818] border border-[#333] rounded-lg px-3 py-2 text-white focus:border-red-600 outline-none" required>
                </div>
                <div class="flex-1">
                    <label class="block mb-1">Stock *</label>
                    <input type="number" name="stock" min="0" class="w-full bg-[#181818] border border-[#333] rounded-lg px-3 py-2 text-white focus:border-red-600 outline-none" required>
                </div>
            </div>
            <div>
                <label class="block mb-1">Imágenes (puedes seleccionar varias)</label>
                <input type="file" name="imagenes[]" accept="image/*" class="w-full text-white" multiple required>
            </div>
            <div class="flex gap-4">
                <div class="flex-1">
                    <label class="block mb-1">Categoría *</label>
                    <select name="id_categoria" class="w-full bg-[#181818] border border-[#333] rounded-lg px-3 py-2 text-white focus:border-red-600 outline-none" required>
                        <option value="">Selecciona</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block mb-1">Marca *</label>
                    <select name="id_marca" class="w-full bg-[#181818] border border-[#333] rounded-lg px-3 py-2 text-white focus:border-red-600 outline-none" required>
                        <option value="">Selecciona</option>
                        <?php foreach ($marcas as $marca): ?>
                            <option value="<?php echo $marca['id']; ?>"><?php echo htmlspecialchars($marca['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="oferta" id="oferta" value="1" class="accent-red-600">
                <label for="oferta">¿Producto en oferta?</label>
            </div>
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded-lg mt-4">Guardar producto</button>
        </form>
    </main>
</body>
</html>