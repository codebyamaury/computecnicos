<?php
// Bootstrap PRIMERO: inicia sesión desde la BD antes de verificar permisos
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: proveedores.php');
    exit;
}
$stmt = $pdo->prepare('SELECT * FROM proveedores WHERE id = ?');
$stmt->execute([$id]);
$proveedor = $stmt->fetch();
if (!$proveedor) {
    header('Location: proveedores.php');
    exit;
}
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $contacto = trim($_POST['contacto'] ?? '');
    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio.';
    }
    if (empty($errores)) {
        $stmt = $pdo->prepare('UPDATE proveedores SET nombre=?, email=?, telefono=?, direccion=?, contacto=? WHERE id=?');
        $ok = $stmt->execute([$nombre, $email, $telefono, $direccion, $contacto, $id]);
        if ($ok) {
            header('Location: proveedores.php?editado=1');
            exit;
        } else {
            $errores[] = 'Error al actualizar el proveedor.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Proveedor | Computécnicos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#181818] text-white min-h-screen flex flex-col">
    <header class="bg-[#232323] border-b border-[#333] py-4 px-8 flex items-center justify-between">
        <span class="text-2xl font-bold text-red-600">Editar Proveedor</span>
        <a href="proveedores.php" class="text-gray-300 hover:text-red-500 transition">Volver a proveedores</a>
    </header>
    <main class="flex-1 container mx-auto px-4 py-12 max-w-lg">
        <h2 class="text-2xl font-bold mb-8">Editar proveedor</h2>
        <?php if ($errores): ?>
            <div class="bg-red-800 text-white rounded p-4 mb-6">
                <?php foreach ($errores as $e): ?>
                    <div>• <?php echo htmlspecialchars($e); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" class="space-y-5 bg-[#232323] p-8 rounded-lg shadow">
            <div>
                <label class="block mb-1 font-semibold">Nombre *</label>
                <input type="text" name="nombre" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" required value="<?php echo htmlspecialchars($_POST['nombre'] ?? $proveedor['nombre']); ?>">
            </div>
            <div>
                <label class="block mb-1 font-semibold">Email</label>
                <input type="email" name="email" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['email'] ?? $proveedor['email']); ?>">
            </div>
            <div>
                <label class="block mb-1 font-semibold">Teléfono</label>
                <input type="text" name="telefono" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['telefono'] ?? $proveedor['telefono']); ?>">
            </div>
            <div>
                <label class="block mb-1 font-semibold">Dirección</label>
                <input type="text" name="direccion" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['direccion'] ?? $proveedor['direccion']); ?>">
            </div>
            <div>
                <label class="block mb-1 font-semibold">Contacto</label>
                <input type="text" name="contacto" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['contacto'] ?? $proveedor['contacto']); ?>">
            </div>
            <div class="pt-4">
                <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 rounded">Guardar cambios</button>
            </div>
        </form>
    </main>
</body>
</html>