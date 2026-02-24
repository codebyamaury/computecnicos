<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
require_once __DIR__ . '/../app/Core/bootstrap.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: marcas.php');
    exit;
}

// Obtener marca
$stmt = $pdo->prepare('SELECT * FROM marcas WHERE id = ?');
$stmt->execute([$id]);
$marca = $stmt->fetch();

if (!$marca) {
    header('Location: marcas.php');
    exit;
}

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    if ($nombre) {
        $stmt = $pdo->prepare('UPDATE marcas SET nombre = ?, descripcion = ? WHERE id = ?');
        $stmt->execute([$nombre, $descripcion, $id]);
        $mensaje = 'Marca actualizada correctamente.';
        
        // Actualizar datos en la variable
        $marca['nombre'] = $nombre;
        $marca['descripcion'] = $descripcion;
    } else {
        $mensaje = 'El nombre es obligatorio.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Marca | Computécnicos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#181818] text-white min-h-screen">
<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-[#232323] border-r border-[#333] flex flex-col py-6 px-4 fixed h-full z-20">
        <div class="flex items-center gap-3 mb-10 px-2 justify-center">
            <span class="text-3xl text-red-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-8 h-8 inline-block align-middle relative -top-1">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v9m6.364-4.364a9 9 0 11-12.728 0" />
                </svg>
            </span>
            <span class="text-xl font-extrabold tracking-tight"><span class="text-red-600">COMPU</span><span class="text-white">TECNICOS</span></span>
        </div>
        <nav class="flex-1">
            <ul class="space-y-2">
                <li><a href="dashboard.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Dashboard</a></li>
                <li><a href="productos.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Productos</a></li>
                <li><a href="categorias.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Categorías</a></li>
                <li><a href="marcas.php" class="block px-3 py-2 rounded bg-[#181818] font-semibold text-red-500">Marcas</a></li>
                <li><a href="usuarios.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Usuarios</a></li>
                <li><a href="pedidos.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Pedidos</a></li>
                <li><a href="proveedores.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Proveedores</a></li>
                <li><a href="inventario.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Inventario</a></li>
                <li><a href="reporte_contable.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Reportes</a></li>
                
            </ul>
        </nav>
        <div class="mt-8 border-t border-[#333] pt-4 px-2">
            <div class="text-xs text-gray-400 mb-1">Usuario:</div>
            <div class="font-semibold text-sm text-white mb-2"><?php echo htmlspecialchars($_SESSION['usuario']['nombre']); ?> (<?php echo htmlspecialchars($_SESSION['usuario']['rol']); ?>)</div>
            <a href="../logout.php" class="block text-red-500 hover:underline text-xs">Cerrar sesión</a>
        </div>
    </aside>
    
    <!-- Main content -->
    <div class="flex-1 ml-64 flex flex-col min-h-screen">
        <!-- Header -->
        <header class="bg-[#232323] border-b border-[#333] px-8 py-4 flex items-center justify-between sticky top-0 z-10">
            <div>
                <div class="text-lg font-bold text-white">Editar Marca</div>
                <nav class="text-xs text-gray-400 mt-1">
                    <a href="dashboard.php" class="hover:underline">Panel</a> <span class="mx-1">/</span> 
                    <a href="marcas.php" class="hover:underline">Marcas</a> <span class="mx-1">/</span> 
                    <span class="text-red-500">Editar</span>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <a href="../index.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-200 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Ir a la Tienda
                </a>
            </div>
        </header>
        
        <!-- Content -->
        <main class="flex-1 px-8 py-10 bg-[#181818]">
            <div class="max-w-2xl mx-auto">
                <h1 class="text-2xl font-bold mb-6">Editar Marca</h1>
                
                <?php if ($mensaje): ?>
                    <div class="mb-6 p-4 rounded-lg text-white <?php echo strpos($mensaje, 'correctamente') !== false ? 'bg-green-600' : 'bg-red-600'; ?>">
                        <?php echo htmlspecialchars($mensaje); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Formulario para editar marca -->
                <div class="bg-[#232323] rounded-xl border border-[#333] p-6">
                    <h2 class="text-xl font-bold mb-4">Información de la Marca</h2>
                    <form method="post" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block mb-1 font-semibold">Nombre *</label>
                                <input type="text" name="nombre" value="<?php echo htmlspecialchars($marca['nombre']); ?>" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white focus:border-red-600 outline-none" required>
                            </div>
                            <div>
                                <label class="block mb-1 font-semibold">Descripción</label>
                                <input type="text" name="descripcion" value="<?php echo htmlspecialchars($marca['descripcion']); ?>" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white focus:border-red-600 outline-none" placeholder="Descripción opcional">
                            </div>
                        </div>
                        <div class="flex justify-end gap-4 pt-4">
                            <a href="marcas.php" class="bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-6 rounded">Cancelar</a>
                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
</body>
</html>