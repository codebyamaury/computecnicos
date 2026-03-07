<?php
// Bootstrap PRIMERO: inicia sesión desde la BD antes de verificar permisos
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: usuarios.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = ?');
$stmt->execute([$id]);
$usuario = $stmt->fetch();
if (!$usuario) {
    header('Location: usuarios.php');
    exit;
}

$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $rol = $_POST['rol'] ?? 'cliente';
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($nombre === '' || $email === '') {
        $errores[] = 'Nombre y email son obligatorios.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no es válido.';
    }
    // Verificar email único (excepto el propio)
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id != ?');
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
        $errores[] = 'Ya existe otro usuario con ese email.';
    }
    if ($password !== '' && $password !== $password2) {
        $errores[] = 'Las contraseñas no coinciden.';
    }
    if (empty($errores)) {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE usuarios SET nombre=?, email=?, telefono=?, direccion=?, rol=?, password=? WHERE id=?');
            $ok = $stmt->execute([$nombre, $email, $telefono, $direccion, $rol, $hash, $id]);
            // Invalidar tokens Remember Me del usuario editado (forzar re-login)
            if ($ok) {
                $rememberMe->invalidateAllTokens($id);
            }
        } else {
            $stmt = $pdo->prepare('UPDATE usuarios SET nombre=?, email=?, telefono=?, direccion=?, rol=? WHERE id=?');
            $ok = $stmt->execute([$nombre, $email, $telefono, $direccion, $rol, $id]);
        }
        if ($ok) {
            header('Location: usuarios.php?editado=1');
            exit;
        } else {
            $errores[] = 'Error al actualizar el usuario.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario | Computécnicos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#181818] text-white min-h-screen flex flex-col">
    <header class="bg-[#232323] border-b border-[#333] py-4 px-8 flex items-center justify-between">
        <span class="text-2xl font-bold text-red-600">Editar Usuario</span>
        <a href="usuarios.php" class="text-gray-300 hover:text-red-500 transition">Volver a usuarios</a>
    </header>
    <main class="flex-1 container mx-auto px-4 py-12 max-w-lg">
        <h2 class="text-2xl font-bold mb-8">Editar usuario</h2>
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
                <input type="text" name="nombre" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" required value="<?php echo htmlspecialchars($_POST['nombre'] ?? $usuario['nombre']); ?>">
            </div>
            <div>
                <label class="block mb-1 font-semibold">Email *</label>
                <input type="email" name="email" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" required value="<?php echo htmlspecialchars($_POST['email'] ?? $usuario['email']); ?>">
            </div>
            <div>
                <label class="block mb-1 font-semibold">Teléfono</label>
                <input type="text" name="telefono" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['telefono'] ?? $usuario['telefono']); ?>">
            </div>
            <div>
                <label class="block mb-1 font-semibold">Dirección</label>
                <input type="text" name="direccion" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['direccion'] ?? $usuario['direccion']); ?>">
            </div>
            <div>
                <label class="block mb-1 font-semibold">Rol *</label>
                <select name="rol" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white">
                    <option value="cliente" <?php if(($_POST['rol'] ?? $usuario['rol'])==='cliente') echo 'selected'; ?>>Cliente</option>
                    <option value="admin" <?php if(($_POST['rol'] ?? $usuario['rol'])==='admin') echo 'selected'; ?>>Admin</option>
                </select>
            </div>
            <div>
                <label class="block mb-1 font-semibold">Nueva contraseña</label>
                <input type="password" name="password" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" placeholder="Dejar en blanco para no cambiar">
            </div>
            <div>
                <label class="block mb-1 font-semibold">Repetir nueva contraseña</label>
                <input type="password" name="password2" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" placeholder="Dejar en blanco para no cambiar">
            </div>
            <div class="pt-4">
                <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 rounded">Guardar cambios</button>
            </div>
        </form>
    </main>
</body>
</html>