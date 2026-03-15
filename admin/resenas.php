<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
$usuario = $_SESSION['usuario'];

// ── Eliminar reseña ──
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    if ($id > 0) {
        // Eliminar imágenes del disco primero
        $imgs = $pdo->prepare("SELECT url_imagen FROM resenas_imagenes WHERE id_resena = ?");
        $imgs->execute([$id]);
        foreach ($imgs->fetchAll() as $img) {
            $path = BASE_PATH . '/' . $img['url_imagen'];
            if (is_file($path)) @unlink($path);
        }
        $pdo->prepare('DELETE FROM resenas WHERE id = ?')->execute([$id]);
    }
    header('Location: resenas.php?eliminado=1');
    exit;
}

// ── Filtros ──
$filtroProducto = intval($_GET['producto'] ?? 0);
$filtroEstrellas = intval($_GET['estrellas'] ?? 0);

// ── Obtener reseñas con JOINs ──
$sql = "SELECT r.*, u.nombre AS usuario_nombre, u.email AS usuario_email, 
               p.nombre AS producto_nombre, p.imagen AS producto_imagen
        FROM resenas r
        LEFT JOIN usuarios u ON r.id_usuario = u.id
        LEFT JOIN productos p ON r.id_producto = p.id
        WHERE 1=1";
$params = [];

if ($filtroProducto > 0) {
    $sql .= " AND r.id_producto = ?";
    $params[] = $filtroProducto;
}
if ($filtroEstrellas >= 1 && $filtroEstrellas <= 5) {
    $sql .= " AND r.calificacion = ?";
    $params[] = $filtroEstrellas;
}

$sql .= " ORDER BY r.fecha DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resenas = $stmt->fetchAll();

// Obtener imágenes de cada reseña
foreach ($resenas as &$r) {
    $stmtImg = $pdo->prepare("SELECT id, url_imagen FROM resenas_imagenes WHERE id_resena = ?");
    $stmtImg->execute([$r['id']]);
    $r['imagenes'] = $stmtImg->fetchAll();
}
unset($r);

// ── Estadísticas generales ──
$statsQuery = $pdo->query("SELECT COUNT(*) as total, AVG(calificacion) as promedio FROM resenas");
$stats = $statsQuery->fetch();
$totalResenas = intval($stats['total']);
$promedioGlobal = $totalResenas > 0 ? round($stats['promedio'], 1) : 0;

// Distribución
$distQuery = $pdo->query("SELECT calificacion, COUNT(*) as cnt FROM resenas GROUP BY calificacion ORDER BY calificacion DESC");
$distribucion = [1=>0,2=>0,3=>0,4=>0,5=>0];
foreach ($distQuery->fetchAll() as $d) {
    $distribucion[intval($d['calificacion'])] = intval($d['cnt']);
}

// Productos para el filtro
$productosLista = $pdo->query("SELECT DISTINCT p.id, p.nombre FROM resenas r INNER JOIN productos p ON r.id_producto = p.id ORDER BY p.nombre")->fetchAll();

$page_title       = 'Reseñas | Computécnicos';
$admin_page       = 'resenas';
$admin_title      = 'Reseñas';
$admin_breadcrumb = [['label' => 'Reseñas']];

include '_layout.php';
?>

<main class="admin-content">
<div class="admin-content-inner">

    <!-- Stats Cards -->
    <div class="adm-kpi-grid !grid-cols-[repeat(auto-fit,minmax(180px,1fr))] !mb-6">
        <div class="adm-card !p-5">
            <div class="flex items-center gap-3">
                <div class="w-[42px] h-[42px] bg-yellow-400/10 border border-yellow-400/20 rounded-[10px] flex items-center justify-center">
                    <i data-lucide="star" class="w-5 h-5 text-[#facc15]"></i>
                </div>
                <div>
                    <div class="text-[1.75rem] font-extrabold text-white"><?= $totalResenas ?></div>
                    <div class="text-[0.75rem] text-[#666]">Reseñas totales</div>
                </div>
            </div>
        </div>
        <div class="adm-card !p-5">
            <div class="flex items-center gap-3">
                <div class="w-[42px] h-[42px] bg-green-400/10 border border-green-400/20 rounded-[10px] flex items-center justify-center">
                    <i data-lucide="trending-up" class="w-5 h-5 text-[#4ade80]"></i>
                </div>
                <div>
                    <div class="text-[1.75rem] font-extrabold text-white"><?= $promedioGlobal ?> <span class="text-[0.9rem] text-[#facc15]">★</span></div>
                    <div class="text-[0.75rem] text-[#666]">Promedio global</div>
                </div>
            </div>
        </div>
        <div class="adm-card !p-5">
            <div class="flex items-center gap-3">
                <div class="w-[42px] h-[42px] bg-red-400/10 border border-red-400/20 rounded-[10px] flex items-center justify-center">
                    <i data-lucide="thumbs-up" class="w-5 h-5 text-[#ff6666]"></i>
                </div>
                <div>
                    <div class="text-[1.75rem] font-extrabold text-white"><?= $distribucion[5] + $distribucion[4] ?></div>
                    <div class="text-[0.75rem] text-[#666]">Positivas (4-5★)</div>
                </div>
            </div>
        </div>
        <div class="adm-card !p-5">
            <div class="flex items-center gap-3">
                <div class="w-[42px] h-[42px] bg-red-500/10 border border-red-500/20 rounded-[10px] flex items-center justify-center">
                    <i data-lucide="thumbs-down" class="w-5 h-5 text-[#ef4444]"></i>
                </div>
                <div>
                    <div class="text-[1.75rem] font-extrabold text-white"><?= $distribucion[1] + $distribucion[2] ?></div>
                    <div class="text-[0.75rem] text-[#666]">Negativas (1-2★)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="adm-card !p-[1rem_1.25rem] !mb-6">
        <form method="get" class="flex flex-wrap items-center gap-3">
            <i data-lucide="filter" class="w-4 h-4 text-[#666]"></i>
            <select name="producto" class="adm-select !w-auto !min-w-[180px] !p-[0.4rem_0.75rem] !text-[0.82rem]">
                <option value="0">Todos los productos</option>
                <?php foreach ($productosLista as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $filtroProducto == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="estrellas" class="adm-select !w-auto !min-w-[120px] !p-[0.4rem_0.75rem] !text-[0.82rem]">
                <option value="0">Todas las ★</option>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>" <?= $filtroEstrellas == $i ? 'selected' : '' ?>><?= $i ?> estrella<?= $i > 1 ? 's' : '' ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="adm-btn adm-btn-primary !text-[0.78rem] !p-[0.4rem_1rem]">Filtrar</button>
            <?php if ($filtroProducto || $filtroEstrellas): ?>
                <a href="resenas.php" class="adm-btn !text-[0.78rem] !p-[0.4rem_1rem]">Limpiar</a>
            <?php endif; ?>
            <div class="ml-auto flex items-center gap-2">
                <input type="text" id="search-resenas" placeholder="Buscar..." class="adm-input !w-[180px] !p-[0.4rem_0.75rem] !text-[0.82rem]">
            </div>
        </form>
    </div>

    <!-- Tabla de Reseñas -->
    <div class="adm-card !p-0 overflow-hidden">
        <div class="adm-card-header">
            <div class="adm-card-title !mb-0">
                <span class="adm-card-title-text">Listado de Reseñas</span>
                <span class="adm-badge adm-badge-gray"><?= count($resenas) ?> resultado<?= count($resenas) !== 1 ? 's' : '' ?></span>
            </div>
        </div>

        <?php if (empty($resenas)): ?>
            <div class="p-12 text-center text-[#555]">
                <i data-lucide="message-circle" class="w-12 h-12 text-[#333] mb-4 mx-auto"></i>
                <p class="text-[0.95rem] text-[#888]">No hay reseñas <?= ($filtroProducto || $filtroEstrellas) ? 'con estos filtros' : 'aún' ?>.</p>
            </div>
        <?php else: ?>
            <div class="adm-table-wrap !border-none !rounded-none">
                <table class="adm-table" id="tabla-resenas">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Usuario</th>
                            <th>Calificación</th>
                            <th>Reseña</th>
                            <th>Imágenes</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($resenas as $r): ?>
                        <tr>
                            <td>
                                <div class="flex items-center gap-[0.6rem] min-w-[140px]">
                                    <?php if (!empty($r['producto_imagen'])): ?>
                                        <img src="<?= htmlspecialchars($r['producto_imagen']) ?>" 
                                             class="w-9 h-9 rounded-md object-contain bg-white border border-[#222] flex-shrink-0">
                                    <?php endif; ?>
                                    <a href="../producto.php?id=<?= $r['id_producto'] ?>" target="_blank" 
                                       class="text-white no-underline font-semibold text-[0.82rem] leading-tight line-clamp-2">
                                        <?= htmlspecialchars($r['producto_nombre'] ?? 'Producto #' . $r['id_producto']) ?>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div class="font-semibold text-[0.82rem] text-white"><?= htmlspecialchars($r['usuario_nombre'] ?? 'Usuario') ?></div>
                                    <div class="text-[0.72rem] text-[#555]"><?= htmlspecialchars($r['usuario_email'] ?? '') ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="flex items-center gap-[0.3rem]">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span class="text-[0.9rem] <?= $i <= $r['calificacion'] ? 'text-[#facc15]' : 'text-[#333]' ?>">★</span>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td class="max-w-[280px]">
                                <?php if (!empty($r['titulo'])): ?>
                                    <div class="font-bold text-[0.82rem] text-white mb-[0.2rem]"><?= htmlspecialchars($r['titulo']) ?></div>
                                <?php endif; ?>
                                <div class="text-[0.78rem] text-[#999] line-clamp-3 leading-relaxed">
                                    <?= htmlspecialchars($r['comentario'] ?? '') ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($r['imagenes'])): ?>
                                    <div class="flex gap-1">
                                        <?php foreach ($r['imagenes'] as $img): ?>
                                            <img src="<?= base_url() . '/' . htmlspecialchars($img['url_imagen']) ?>" 
                                                 class="w-9 h-9 rounded object-cover border border-[#333] cursor-pointer"
                                                 onclick="window.open(this.src,'_blank')">
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-[#444] text-[0.75rem]">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-[#555] text-[0.75rem] whitespace-nowrap">
                                <?= date('d/m/Y', strtotime($r['fecha'])) ?><br>
                                <span class="text-[#444]"><?= date('H:i', strtotime($r['fecha'])) ?></span>
                            </td>
                            <td>
                                <div class="adm-flex-actions">
                                    <a href="../producto.php?id=<?= $r['id_producto'] ?>#reviews-section" target="_blank"
                                       class="adm-btn adm-btn-blue !text-[0.72rem] !p-[0.3rem_0.7rem]">
                                        Ver
                                    </a>
                                    <button type="button" class="adm-btn adm-btn-danger !text-[0.72rem] !p-[0.3rem_0.7rem]"
                                       onclick="confirmarEliminar('?eliminar=<?= $r['id'] ?>', '<?= htmlspecialchars(mb_substr($r['comentario'] ?? '', 0, 40), ENT_QUOTES) ?>...', 'reseña')">
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div id="pag-resenas" class="adm-pagination-wrap"></div>

</div>
</main>

<?php include '_layout_end.php'; ?>
<script>initPagination('#tabla-resenas tbody','pag-resenas',10,'search-resenas');</script>
