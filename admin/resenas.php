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
    header('Location: resenas.php');
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
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem">
        <div class="adm-card" style="padding:1.25rem">
            <div style="display:flex;align-items:center;gap:0.75rem">
                <div style="width:42px;height:42px;background:rgba(250,204,21,0.1);border:1px solid rgba(250,204,21,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center">
                    <i data-lucide="star" style="width:20px;height:20px;color:#facc15"></i>
                </div>
                <div>
                    <div style="font-size:1.75rem;font-weight:800;color:#fff"><?= $totalResenas ?></div>
                    <div style="font-size:0.75rem;color:#666">Reseñas totales</div>
                </div>
            </div>
        </div>
        <div class="adm-card" style="padding:1.25rem">
            <div style="display:flex;align-items:center;gap:0.75rem">
                <div style="width:42px;height:42px;background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center">
                    <i data-lucide="trending-up" style="width:20px;height:20px;color:#4ade80"></i>
                </div>
                <div>
                    <div style="font-size:1.75rem;font-weight:800;color:#fff"><?= $promedioGlobal ?> <span style="font-size:0.9rem;color:#facc15">★</span></div>
                    <div style="font-size:0.75rem;color:#666">Promedio global</div>
                </div>
            </div>
        </div>
        <div class="adm-card" style="padding:1.25rem">
            <div style="display:flex;align-items:center;gap:0.75rem">
                <div style="width:42px;height:42px;background:rgba(255,0,0,0.1);border:1px solid rgba(255,0,0,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center">
                    <i data-lucide="thumbs-up" style="width:20px;height:20px;color:#ff6666"></i>
                </div>
                <div>
                    <div style="font-size:1.75rem;font-weight:800;color:#fff"><?= $distribucion[5] + $distribucion[4] ?></div>
                    <div style="font-size:0.75rem;color:#666">Positivas (4-5★)</div>
                </div>
            </div>
        </div>
        <div class="adm-card" style="padding:1.25rem">
            <div style="display:flex;align-items:center;gap:0.75rem">
                <div style="width:42px;height:42px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center">
                    <i data-lucide="thumbs-down" style="width:20px;height:20px;color:#ef4444"></i>
                </div>
                <div>
                    <div style="font-size:1.75rem;font-weight:800;color:#fff"><?= $distribucion[1] + $distribucion[2] ?></div>
                    <div style="font-size:0.75rem;color:#666">Negativas (1-2★)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="adm-card" style="padding:1rem 1.25rem;margin-bottom:1.5rem">
        <form method="get" style="display:flex;flex-wrap:wrap;align-items:center;gap:0.75rem">
            <i data-lucide="filter" style="width:16px;height:16px;color:#666"></i>
            <select name="producto" class="adm-select" style="width:auto;min-width:180px;padding:0.4rem 0.75rem;font-size:0.82rem">
                <option value="0">Todos los productos</option>
                <?php foreach ($productosLista as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $filtroProducto == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="estrellas" class="adm-select" style="width:auto;min-width:120px;padding:0.4rem 0.75rem;font-size:0.82rem">
                <option value="0">Todas las ★</option>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>" <?= $filtroEstrellas == $i ? 'selected' : '' ?>><?= $i ?> estrella<?= $i > 1 ? 's' : '' ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="adm-btn adm-btn-primary" style="font-size:0.78rem;padding:0.4rem 1rem">Filtrar</button>
            <?php if ($filtroProducto || $filtroEstrellas): ?>
                <a href="resenas.php" class="adm-btn" style="font-size:0.78rem;padding:0.4rem 1rem">Limpiar</a>
            <?php endif; ?>
            <div style="margin-left:auto;display:flex;align-items:center;gap:0.5rem">
                <input type="text" id="search-resenas" placeholder="Buscar..." class="adm-input" style="width:180px;padding:0.4rem 0.75rem;font-size:0.82rem">
            </div>
        </form>
    </div>

    <!-- Tabla de Reseñas -->
    <div class="adm-card" style="padding:0;overflow:hidden">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.04)">
            <div class="adm-card-title" style="margin-bottom:0">
                <span class="adm-card-title-text">Listado de Reseñas</span>
                <span class="adm-badge adm-badge-gray"><?= count($resenas) ?> resultado<?= count($resenas) !== 1 ? 's' : '' ?></span>
            </div>
        </div>

        <?php if (empty($resenas)): ?>
            <div style="padding:3rem;text-align:center;color:#555">
                <i data-lucide="message-circle" style="width:48px;height:48px;color:#333;margin-bottom:1rem"></i>
                <p style="font-size:0.95rem;color:#888">No hay reseñas <?= ($filtroProducto || $filtroEstrellas) ? 'con estos filtros' : 'aún' ?>.</p>
            </div>
        <?php else: ?>
            <div class="adm-table-wrap" style="border:none;border-radius:0">
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
                                <div style="display:flex;align-items:center;gap:0.6rem;min-width:140px">
                                    <?php if (!empty($r['producto_imagen'])): ?>
                                        <img src="<?= htmlspecialchars($r['producto_imagen']) ?>" 
                                             style="width:36px;height:36px;border-radius:6px;object-fit:contain;background:#fff;border:1px solid #222;flex-shrink:0">
                                    <?php endif; ?>
                                    <a href="../producto.php?id=<?= $r['id_producto'] ?>" target="_blank" 
                                       style="color:#fff;text-decoration:none;font-weight:600;font-size:0.82rem;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
                                        <?= htmlspecialchars($r['producto_nombre'] ?? 'Producto #' . $r['id_producto']) ?>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <div style="font-weight:600;font-size:0.82rem;color:#fff"><?= htmlspecialchars($r['usuario_nombre'] ?? 'Usuario') ?></div>
                                    <div style="font-size:0.72rem;color:#555"><?= htmlspecialchars($r['usuario_email'] ?? '') ?></div>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex;align-items:center;gap:0.3rem">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <span style="color:<?= $i <= $r['calificacion'] ? '#facc15' : '#333' ?>;font-size:0.9rem">★</span>
                                    <?php endfor; ?>
                                </div>
                            </td>
                            <td style="max-width:280px">
                                <?php if (!empty($r['titulo'])): ?>
                                    <div style="font-weight:700;font-size:0.82rem;color:#fff;margin-bottom:0.2rem"><?= htmlspecialchars($r['titulo']) ?></div>
                                <?php endif; ?>
                                <div style="font-size:0.78rem;color:#999;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;line-height:1.5">
                                    <?= htmlspecialchars($r['comentario'] ?? '') ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($r['imagenes'])): ?>
                                    <div style="display:flex;gap:4px">
                                        <?php foreach ($r['imagenes'] as $img): ?>
                                            <img src="<?= base_url() . '/' . htmlspecialchars($img['url_imagen']) ?>" 
                                                 style="width:36px;height:36px;border-radius:4px;object-fit:cover;border:1px solid #333;cursor:pointer"
                                                 onclick="window.open(this.src,'_blank')">
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#444;font-size:0.75rem">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#555;font-size:0.75rem;white-space:nowrap">
                                <?= date('d/m/Y', strtotime($r['fecha'])) ?><br>
                                <span style="color:#444"><?= date('H:i', strtotime($r['fecha'])) ?></span>
                            </td>
                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap">
                                    <a href="../producto.php?id=<?= $r['id_producto'] ?>#reviews-section" target="_blank"
                                       class="adm-btn adm-btn-blue" style="font-size:0.72rem;padding:0.3rem 0.7rem">
                                        Ver
                                    </a>
                                    <button type="button" class="adm-btn adm-btn-danger" style="font-size:0.72rem;padding:0.3rem 0.7rem"
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

    <div id="pag-resenas" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:1rem;flex-wrap:wrap"></div>

</div>
</main>

<?php include '_layout_end.php'; ?>
<script>initPagination('#tabla-resenas tbody','pag-resenas',10,'search-resenas');</script>
