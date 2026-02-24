<?php
// Sesión manejada por bootstrap (DB handler)
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
require_once __DIR__ . '/../app/Core/bootstrap.php';

$errores = [];
$exito = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $rol = $_POST['rol'] ?? 'cliente';
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($nombre === '' || $email === '' || $password === '' || $password2 === '') {
        $errores[] = 'Todos los campos marcados con * son obligatorios.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El email no es válido.';
    }
    if ($password !== $password2) {
        $errores[] = 'Las contraseñas no coinciden.';
    }
    // Verificar email único
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errores[] = 'Ya existe un usuario con ese email.';
    }
    if (empty($errores)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, email, telefono, direccion, rol, password, fecha_registro) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $ok = $stmt->execute([$nombre, $email, $telefono, $direccion, $rol, $hash]);
        if ($ok) {
            header('Location: usuarios.php?exito=1');
            exit;
        } else {
            $errores[] = 'Error al guardar el usuario.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Usuario | Computécnicos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#181818] text-white min-h-screen flex flex-col">
    <header class="bg-[#232323] border-b border-[#333] py-4 px-8 flex items-center justify-between">
        <span class="text-2xl font-bold text-red-600">Nuevo Usuario</span>
        <a href="usuarios.php" class="text-gray-300 hover:text-red-500 transition">Volver a usuarios</a>
    </header>
    <main class="flex-1 container mx-auto px-4 py-12 max-w-lg">
        <h2 class="text-2xl font-bold mb-8">Registrar nuevo usuario</h2>
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
                <input type="text" name="nombre" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" required value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
            </div>
            <div>
                <label class="block mb-1 font-semibold">Email *</label>
                <input type="email" name="email" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div>
                <label class="block mb-1 font-semibold">Teléfono</label>
                <input type="text" name="telefono" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
            </div>
            <div>
                <label class="block mb-1 font-semibold">Dirección</label>
                <input type="text" name="direccion" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['direccion'] ?? ''); ?>">
            </div>
            <div>
                <label class="block mb-1 font-semibold">Rol *</label>
                <select name="rol" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white">
                    <option value="cliente" <?php if(($_POST['rol'] ?? '')==='cliente') echo 'selected'; ?>>Cliente</option>
                    <option value="admin" <?php if(($_POST['rol'] ?? '')==='admin') echo 'selected'; ?>>Admin</option>
                </select>
            </div>
            <div>
                <label class="block mb-1 font-semibold">Contraseña *</label>
                <input type="password" name="password" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" required>
            </div>
            <div>
                <label class="block mb-1 font-semibold">Repetir contraseña *</label>
                <input type="password" name="password2" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" required>
            </div>
            <div class="pt-4">
                <button type="submit" class="w-full bg-green-700 hover:bg-green-800 text-white font-bold py-2 rounded">Registrar usuario</button>
            </div>
        </form>
    </main>
</body>
</html>