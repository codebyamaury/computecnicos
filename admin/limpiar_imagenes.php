<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/../app/Core/bootstrap.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}

function collectReferencedImages(PDO $pdo): array {
    $referenced = [];
    // Imagen principal de productos
    $stmt = $pdo->query("SELECT imagen FROM productos WHERE imagen IS NOT NULL AND imagen <> ''");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $img) {
        $referenced[normalizePath($img)] = true;
    }
    // Galería de productos
    $stmt = $pdo->query("SELECT url_imagen FROM imagenes_producto WHERE url_imagen IS NOT NULL AND url_imagen <> ''");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $img) {
        $referenced[normalizePath($img)] = true;
    }
    // Fotos de perfil locales
    $stmt = $pdo->query("SELECT foto FROM usuarios WHERE foto IS NOT NULL AND foto <> ''");
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $img) {
        if (strpos($img, 'uploads/') === 0) {
            $referenced[normalizePath($img)] = true;
        }
    }
    return $referenced;
}

function listDirectoryFiles(string $dir): array {
    $files = [];
    if (!is_dir($dir)) return $files;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_file($path)) {
            $files[] = $path;
        }
    }
    return $files;
}

function normalizePath(string $relative): string {
    // Asegura formato con '/' y sin '../' inicial
    $relative = str_replace('\\', '/', $relative);
    if (strpos($relative, './') === 0) $relative = substr($relative, 2);
    if (strpos($relative, '../') === 0) $relative = substr($relative, 3);
    return $relative;
}

$doRun = isset($_GET['run']) && $_GET['run'] === '1';

$baseDir = realpath(__DIR__ . '/..');
$productsDir = $baseDir . '/uploads/products';
$profilesDir = $baseDir . '/uploads/profiles';

$referenced = collectReferencedImages($pdo);

// Excepciones que nunca deben borrarse
$neverDelete = [
    normalizePath('uploads/products/.gitkeep'),
    normalizePath('uploads/profiles/.gitkeep'),
    normalizePath('uploads/products/default.png'),
];

$foundFiles = [];
$foundFiles = array_merge($foundFiles, listDirectoryFiles($productsDir));
$foundFiles = array_merge($foundFiles, listDirectoryFiles($profilesDir));

$orphans = [];
foreach ($foundFiles as $filePath) {
    $relative = normalizePath(str_replace($baseDir . '/', '', $filePath));
    if (in_array($relative, $neverDelete, true)) continue;
    if (!isset($referenced[$relative])) {
        $orphans[] = $relative;
    }
}

$deleted = [];
if ($doRun && !empty($orphans)) {
    foreach ($orphans as $relative) {
        $full = $baseDir . '/' . $relative;
        if (is_file($full)) {
            @unlink($full);
            $deleted[] = $relative;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Limpiar imágenes huérfanas | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="bg-[#181818] text-white min-h-screen">
    <header class="bg-[#232323] border-b border-[#333] py-4 px-8 flex items-center justify-between">
        <span class="text-xl font-bold text-red-600">Limpiar imágenes huérfanas</span>
        <nav class="space-x-4 text-sm">
            <a href="dashboard.php" class="text-gray-300 hover:text-red-500">Dashboard</a>
            <a href="productos.php" class="text-gray-300 hover:text-red-500">Productos</a>
        </nav>
    </header>
    <main class="max-w-[1000px] mx-auto px-4 py-8">
        <section class="bg-[#232323] border border-[#333] rounded-xl p-6 mb-8">
            <h2 class="text-lg font-bold mb-4">Resumen</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-[#1d1d1d] p-4 rounded-lg border border-[#333]">
                    <p class="text-sm text-gray-300">Archivos encontrados en <code>uploads/products</code> y <code>uploads/profiles</code>:</p>
                    <p class="text-2xl font-extrabold mt-1"><?php echo count($foundFiles); ?></p>
                </div>
                <div class="bg-[#1d1d1d] p-4 rounded-lg border border-[#333]">
                    <p class="text-sm text-gray-300">Referencias activas en BD:</p>
                    <p class="text-2xl font-extrabold mt-1"><?php echo count($referenced); ?></p>
                </div>
                <div class="bg-[#1d1d1d] p-4 rounded-lg border border-[#333]">
                    <p class="text-sm text-gray-300">Imágenes huérfanas detectadas:</p>
                    <p class="text-2xl font-extrabold mt-1 text-red-500"><?php echo count($orphans); ?></p>
                </div>
            </div>
            <div class="mt-6">
                <?php if (!$doRun): ?>
                    <a href="?run=1" class="inline-block bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded-lg">Eliminar huérfanas ahora</a>
                <?php else: ?>
                    <span class="inline-block bg-green-600 text-white font-semibold px-4 py-2 rounded-lg">Eliminación ejecutada</span>
                <?php endif; ?>
            </div>
        </section>

        <section class="bg-[#232323] border border-[#333] rounded-xl p-6">
            <h3 class="text-lg font-bold mb-4">Detalle de imágenes huérfanas</h3>
            <?php if (empty($orphans)): ?>
                <p class="text-gray-300">No se encontraron imágenes huérfanas.</p>
            <?php else: ?>
                <ul class="space-y-2 text-sm">
                    <?php foreach ($orphans as $rel): ?>
                        <li class="bg-[#1d1d1d] border border-[#333] rounded p-2 flex justify-between items-center">
                            <code class="text-gray-300"><?php echo htmlspecialchars($rel); ?></code>
                            <?php if ($doRun && in_array($rel, $deleted, true)): ?>
                                <span class="text-green-500">eliminado</span>
                            <?php else: ?>
                                <span class="text-yellow-500">pendiente</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>