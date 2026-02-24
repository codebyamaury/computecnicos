<?php
// Sesión manejada por bootstrap (DB handler)
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
require_once __DIR__ . '/../app/Core/bootstrap.php';

// Eliminar mensaje
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $stmt = $pdo->prepare('DELETE FROM contactos WHERE id = ?');
    $stmt->execute([$id]);
    header('Location: contactos.php');
    exit;
}
// Obtener mensajes
$mensajes = $pdo->query('SELECT * FROM contactos ORDER BY fecha DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mensajes de Contacto | Computécnicos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#181818] text-white min-h-screen flex flex-col">
    <header class="bg-[#232323] border-b border-[#333] py-4 px-8 flex items-center justify-between">
        <span class="text-2xl font-bold text-red-600">Mensajes de Contacto</span>
        <a href="dashboard.php" class="text-gray-300 hover:text-red-500 transition">Volver al panel</a>
    </header>
    <main class="flex-1 container mx-auto px-4 py-12 max-w-4xl">
        <h2 class="text-3xl font-bold mb-8">Mensajes recibidos</h2>
        <?php if (!$mensajes): ?>
            <div class="text-center text-gray-400">No hay mensajes recibidos.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-[#232323]">
                        <tr>
                            <th class="px-4 py-2">Nombre</th>
                            <th class="px-4 py-2">Email</th>
                            <th class="px-4 py-2">Mensaje</th>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mensajes as $m): ?>
                        <tr class="border-b border-[#333]">
                            <td class="px-4 py-2 font-bold"><?php echo htmlspecialchars($m['nombre']); ?></td>
                            <td class="px-4 py-2"><?php echo htmlspecialchars($m['email']); ?></td>
                            <td class="px-4 py-2"><?php echo nl2br(htmlspecialchars($m['mensaje'])); ?></td>
                            <td class="px-4 py-2"><?php echo date('d/m/Y H:i', strtotime($m['fecha'])); ?></td>
                            <td class="px-4 py-2">
                                <a href="?eliminar=<?php echo $m['id']; ?>" class="bg-red-700 hover:bg-red-800 text-white px-3 py-1 rounded text-xs" onclick="return confirm('¿Seguro que deseas eliminar este mensaje?');">Eliminar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>