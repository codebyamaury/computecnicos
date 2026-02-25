<?php
// Bootstrap PRIMERO: inicia sesión desde la BD antes de verificar permisos
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('Location: categorias.php');
    exit;
}
// Obtener categoría actual
$stmt = $pdo->prepare('SELECT * FROM categorias WHERE id = ?');
$stmt->execute([$id]);
$categoria = $stmt->fetch();
if (!$categoria) {
    header('Location: categorias.php');
    exit;
}
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($nombre) {
        $stmt = $pdo->prepare('UPDATE categorias SET nombre=?, descripcion=? WHERE id=?');
        $stmt->execute([$nombre, $descripcion, $id]);
        $mensaje = 'Categoría actualizada correctamente.';
        // Refrescar datos
        $stmt = $pdo->prepare('SELECT * FROM categorias WHERE id = ?');
        $stmt->execute([$id]);
        $categoria = $stmt->fetch();
    } else {
        $mensaje = 'El nombre es obligatorio.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Categoría | Computécnicos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#181818] text-white min-h-screen flex flex-col">
    <header class="bg-[#232323] border-b border-[#333] py-4 px-8 flex items-center justify-between">
        <span class="text-2xl font-bold text-red-600">Editar Categoría</span>
        <a href="categorias.php" class="text-gray-300 hover:text-red-500 transition">Volver a categorías</a>
    </header>
    <main class="flex-1 container mx-auto px-4 py-12 max-w-xl">
        <h2 class="text-2xl font-bold mb-6">Editar Categoría</h2>
        <?php if ($mensaje): ?>
            <div class="mb-4 p-3 rounded text-white <?php echo strpos($mensaje, 'correctamente') !== false ? 'bg-green-600' : 'bg-red-600'; ?>"> <?php echo $mensaje; ?> </div>
        <?php endif; ?>
        <form method="post" class="space-y-5 bg-[#232323] p-6 rounded-xl border border-[#333] shadow-lg">
            <div>
                <label class="block mb-1">Nombre *</label>
                <input type="text" name="nombre" class="w-full bg-[#181818] border border-[#333] rounded-lg px-3 py-2 text-white focus:border-red-600 outline-none" value="<?php echo htmlspecialchars($categoria['nombre']); ?>" required>
            </div>
            <div>
                <label class="block mb-1">Descripción</label>
                <textarea name="descripcion" rows="3" class="w-full bg-[#181818] border border-[#333] rounded-lg px-3 py-2 text-white focus:border-red-600 outline-none"><?php echo htmlspecialchars($categoria['descripcion']); ?></textarea>
            </div>
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 rounded-lg mt-4">Guardar cambios</button>
        </form>
    </main>
</body>
</html>