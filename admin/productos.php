<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
$usuario = $_SESSION['usuario'];

if (isset($_GET['eliminar'])) {
    $idEliminar = intval($_GET['eliminar']);
    if ($idEliminar > 0) {
        try {
            $stmt = $pdo->prepare('SELECT imagen FROM productos WHERE id=?');
            $stmt->execute([$idEliminar]);
            $productoRow = $stmt->fetch();
            if ($productoRow) {
                if (!empty($productoRow['imagen'])) {
                    $ruta = '../' . $productoRow['imagen'];
                    if (is_file($ruta)) @unlink($ruta);
                }
                $stmtImg = $pdo->prepare('SELECT url_imagen FROM imagenes_producto WHERE id_producto=?');
                $stmtImg->execute([$idEliminar]);
                foreach ($stmtImg->fetchAll() as $img) {
                    $r = '../' . $img['url_imagen'];
                    if (is_file($r)) @unlink($r);
                }
                $pdo->prepare('DELETE FROM imagenes_producto WHERE id_producto=?')->execute([$idEliminar]);
                $pdo->prepare('DELETE FROM productos WHERE id=?')->execute([$idEliminar]);
            }
        } catch (Exception $e) {}
    }
    header('Location: productos.php?eliminado=1');
    exit;
}

$productos  = $pdo->query("SELECT p.*, c.nombre AS categoria, m.nombre AS marca, (SELECT GROUP_CONCAT(url_imagen SEPARATOR ',') FROM imagenes_producto WHERE id_producto = p.id) AS imagenes_galeria FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id LEFT JOIN marcas m ON p.id_marca = m.id ORDER BY p.fecha_creacion DESC")->fetchAll();
$categorias = $pdo->query('SELECT id, nombre FROM categorias ORDER BY nombre')->fetchAll();
$marcas     = $pdo->query('SELECT id, nombre FROM marcas ORDER BY nombre')->fetchAll();

$page_title       = 'Productos | Computécnicos';
$admin_page       = 'productos';
$admin_title      = 'Productos';
$admin_breadcrumb = [['label' => 'Productos']];
$admin_header_extra = '<button id="btn-abrir-nuevo-producto" class="adm-btn adm-btn-success"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Nuevo producto</button>';

include '_layout.php';
?>

<main class="admin-content">
<div class="admin-content-inner">

    <div class="adm-card" style="padding:0;overflow:hidden">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.04);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem">
            <div class="adm-card-title" style="margin-bottom:0">
                <span class="adm-card-title-text">Catálogo de Productos</span>
                <span class="adm-badge adm-badge-gray"><?= count($productos) ?> productos</span>
            </div>
            <!-- Búsqueda -->
            <input type="text" id="buscar-producto" class="adm-input" style="max-width:260px;padding:0.4rem 0.8rem;font-size:0.8rem" placeholder="🔍 Buscar producto...">
        </div>
        <div class="adm-table-wrap" style="border:none;border-radius:0">
            <table class="adm-table" id="tabla-productos">
                <thead>
                    <tr>
                        <th>Imagen</th><th>Nombre</th><th>Categoría</th><th>Marca</th><th>Precio</th><th>Stock</th><th>Estado</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($productos as $p):
                    $imagenes = [];
                    if (!empty($p['imagen'])) $imagenes[] = $p['imagen'];
                    if (!empty($p['imagenes_galeria'])) {
                        foreach (explode(',', $p['imagenes_galeria']) as $img) {
                            if ($img && !in_array($img, $imagenes)) $imagenes[] = $img;
                        }
                    }
                ?>
                <tr>
                    <td>
                        <?php if (count($imagenes) > 0): ?>
                        <img src="<?= htmlspecialchars(strpos($imagenes[0], 'http') === 0 ? $imagenes[0] : '../' . $imagenes[0]) ?>" alt="img" style="width:48px;height:38px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,0.06)">
                        <?php else: ?>
                        <div style="width:48px;height:38px;background:rgba(255,255,255,0.04);border-radius:6px;display:flex;align-items:center;justify-content:center">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:16px;height:16px;color:#444"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars($p['categoria']) ?></td>
                    <td><?= htmlspecialchars($p['marca']) ?></td>
                    <td style="font-weight:600;color:#e7e7ea">$<?= number_format($p['precio'], 0, ',', '.') ?></td>
                    <td>
                        <span class="adm-badge <?= $p['stock'] <= 0 ? 'adm-badge-red' : ($p['stock'] <= 5 ? 'adm-badge-yellow' : 'adm-badge-green') ?>">
                            <?= $p['stock'] ?>
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;flex-wrap:wrap;gap:3px">
                            <?php if (!empty($p['destacado'])): ?>
                                <span class="adm-badge adm-badge-purple" style="font-size:0.62rem">⭐ Destacado</span>
                            <?php endif; ?>
                            <?php
                                $nuevo_activo = !empty($p['nuevo_hasta']) && strtotime($p['nuevo_hasta']) >= strtotime('today');
                                $oferta_activa = !empty($p['oferta']) && (empty($p['oferta_hasta']) || strtotime($p['oferta_hasta']) >= strtotime('today'));
                            ?>
                            <?php if ($nuevo_activo): ?>
                                <span class="adm-badge adm-badge-blue" style="font-size:0.62rem">NUEVO</span>
                            <?php endif; ?>
                            <?php if ($oferta_activa): ?>
                                <span class="adm-badge adm-badge-red" style="font-size:0.62rem">OFERTA</span>
                            <?php elseif (!empty($p['oferta_hasta']) && strtotime($p['oferta_hasta']) < strtotime('today')): ?>
                                <span class="adm-badge adm-badge-gray" style="font-size:0.62rem">Oferta vencida</span>
                            <?php endif; ?>
                            <?php if (empty($p['destacado']) && !$nuevo_activo && !$oferta_activa): ?>
                                <span style="color:#444;font-size:0.72rem">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <button type="button" onclick="abrirModalEditarProducto(<?= $p['id'] ?>, event)" class="adm-btn adm-btn-warning" style="font-size:0.72rem;padding:0.3rem 0.7rem;text-decoration:none">
                                Editar
                            </button>
                            <button type="button" class="adm-btn adm-btn-danger" style="font-size:0.72rem;padding:0.3rem 0.7rem"
                               onclick="confirmarEliminar('?eliminar=<?= $p['id'] ?>', '<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>', 'producto')">
                                Eliminar
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginación -->
    <div id="paginacion" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:1rem;flex-wrap:wrap"></div>

</div>
</main>

<!-- Modal Nuevo -->
<div id="modal-nuevo-producto-bg" class="adm-modal-overlay"></div>
<div id="modal-nuevo-producto" class="adm-modal hidden">
    <div class="adm-modal-box">
        <button class="adm-modal-close" onclick="cerrarNuevo()">&times;</button>
        <div class="adm-modal-title">Nuevo Producto</div>
        <form id="form-nuevo-producto" method="post" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:0.875rem">
            <div><label class="adm-label">Nombre *</label><input type="text" name="nombre" class="adm-input" required></div>
            <div><label class="adm-label">Descripción</label><textarea name="descripcion" rows="2" class="adm-textarea"></textarea></div>
            <div class="adm-form-row" style="margin-bottom:0">
                <div><label class="adm-label">Precio (COP) *</label><input type="text" inputmode="decimal" pattern="[0-9,\.]+" name="precio" class="adm-input" required></div>
                <div><label class="adm-label">Stock *</label><input type="number" name="stock" min="0" class="adm-input" required></div>
            </div>
            <div class="adm-form-row" style="margin-bottom:0">
                <div><label class="adm-label">Categoría *</label>
                    <select name="id_categoria" class="adm-select" required>
                        <option value="">Selecciona</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="adm-label">Marca *</label>
                    <select name="id_marca" class="adm-select" required>
                        <option value="">Selecciona</option>
                        <?php foreach ($marcas as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="adm-label">Imagen principal (Solo PNG) *</label>
                <input type="file" name="imagen" accept="image/png" required class="adm-input" style="padding:0.4rem">
                <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">⚠️ Solo se permiten imágenes en formato <strong style="color:#fff">PNG</strong>. Ésta será la portada.</div>
            </div>
            <div>
                <label class="adm-label">Imágenes de galería (Solo PNG, Opcional)</label>
                <input type="file" name="imagenes[]" accept="image/png" multiple class="adm-input" style="padding:0.4rem">
                <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Puedes seleccionar múltiples imágenes adicionales.</div>
            </div>
            <!-- Separador visual -->
            <div style="border-top:1px solid var(--adm-border);margin:1.5rem 0;padding-top:1.5rem">
                <h3 style="color:#fff;font-size:0.95rem;font-weight:700;margin-bottom:1rem;display:flex;align-items:center;gap:8px">
                    <span style="width:3px;height:16px;background:var(--adm-red);border-radius:2px;display:inline-block"></span>
                    Visibilidad y Promociones
                </h3>

                <!-- Destacado -->
                <div class="adm-form-group" style="display:flex;align-items:center;gap:0.75rem;padding:1rem;background:rgba(255,255,255,0.03);border-radius:0.75rem;border:1px solid var(--adm-border)">
                    <label style="position:relative;display:inline-block;width:48px;height:26px;flex-shrink:0">
                        <input type="checkbox" name="destacado" value="1" style="opacity:0;width:0;height:0" id="toggle-destacado-nuevo">
                        <span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.08);border-radius:13px;transition:0.3s" id="slider-destacado-nuevo"></span>
                    </label>
                    <div style="flex:1">
                        <div style="font-size:0.88rem;font-weight:600;color:#e7e7ea">Producto Destacado</div>
                        <div style="font-size:0.72rem;color:#666;line-height:1.2;margin-top:2px">Se mostrará en la sección "Destacados" de la página principal.</div>
                    </div>
                </div>

                <!-- Tiempo como Nuevo -->
                <div class="adm-form-group" style="padding:1rem;background:rgba(255,255,255,0.03);border-radius:0.75rem;border:1px solid var(--adm-border)">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                        <span style="width:8px;height:8px;background:#3b82f6;border-radius:50%;display:inline-block"></span>
                        <span style="font-size:0.88rem;font-weight:600;color:#e7e7ea">Badge "NUEVO"</span>
                    </div>
                    <label class="adm-label">Mostrar como nuevo hasta</label>
                    <input type="date" name="nuevo_hasta" class="adm-input">
                    <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Déjalo vacío para que no muestre la badge "NUEVO". El producto mostrará la badge hasta la fecha indicada.</div>
                </div>

                <!-- Oferta -->
                <div class="adm-form-group" style="padding:1rem;background:rgba(255,255,255,0.03);border-radius:0.75rem;border:1px solid var(--adm-border)">
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                        <span style="width:8px;height:8px;background:#ef4444;border-radius:50%;display:inline-block"></span>
                        <span style="font-size:0.88rem;font-weight:600;color:#e7e7ea">Badge "OFERTA"</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem">
                        <label style="position:relative;display:inline-block;width:48px;height:26px;flex-shrink:0">
                            <input type="checkbox" name="oferta" value="1" style="opacity:0;width:0;height:0" id="toggle-oferta-nuevo">
                            <span style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.08);border-radius:13px;transition:0.3s" id="slider-oferta-nuevo"></span>
                        </label>
                        <span style="font-size:0.85rem;color:#aaa">Activar oferta</span>
                    </div>
                    <label class="adm-label">Oferta válida hasta</label>
                    <input type="date" name="oferta_hasta" class="adm-input">
                    <div style="font-size:0.7rem;color:#555;margin-top:0.35rem">Si pones una fecha, la oferta se desactivará automáticamente al vencer. Déjalo vacío para oferta permanente (mientras esté activada).</div>
                </div>
            </div>
            <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;padding:0.85rem;font-size:0.95rem;margin-top:0.5rem">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Guardar Cambios
            </button>
        </form>
        <div id="modal-nuevo-producto-msg" style="display:none;margin-top:0.75rem;text-align:center;color:#ef4444;font-size:0.8rem"></div>
    </div>
</div>

<script>
// --- Búsqueda ---
// (La búsqueda se gestiona dentro del bloque de paginación más abajo)

// --- Modal Editar ---
async function cargarGaleria(id) {
    const div = document.getElementById('edit-galeria');
    div.innerHTML = '<span style="font-size:0.72rem;color:#555">Cargando...</span>';
    try {
        const imgs = await fetch('producto_galeria.php?id='+id).then(r=>r.json());
        div.innerHTML = imgs.length
            ? imgs.map(i=>`<img src="../${i.url_imagen}" style="width:54px;height:40px;object-fit:cover;border-radius:4px;border:1px solid rgba(255,255,255,0.06)">`).join('')
            : '<span style="font-size:0.72rem;color:#555">Sin imágenes extra</span>';
    } catch { div.innerHTML = ''; }
// La carga y envío del modal de Edición es ahora dinámica y se gestiona en _layout_end.php

// --- Modal Nuevo ---
function abrirNuevo() {
    document.getElementById('modal-nuevo-producto-bg').classList.add('show');
    document.getElementById('modal-nuevo-producto').classList.remove('hidden');
    document.getElementById('modal-nuevo-producto').classList.add('show');
    document.getElementById('form-nuevo-producto').reset();
    document.body.style.overflow='hidden';
}
function cerrarNuevo() {
    document.getElementById('modal-nuevo-producto-bg').classList.remove('show');
    document.getElementById('modal-nuevo-producto').classList.add('hidden');
    document.getElementById('modal-nuevo-producto').classList.remove('show');
    document.body.style.overflow='';
}
document.getElementById('modal-nuevo-producto-bg').addEventListener('click', cerrarNuevo);
document.getElementById('btn-abrir-nuevo-producto').addEventListener('click', abrirNuevo);
document.getElementById('form-nuevo-producto').addEventListener('submit', async function(e){
    e.preventDefault();
    
    const btn = this.querySelector('button[type="submit"]');
    const oldText = btn.innerHTML;
    btn.innerHTML = 'Guardando...';
    btn.disabled = true;
    const m = document.getElementById('modal-nuevo-producto-msg');
    m.style.display = 'none';

    try {
        const data = new FormData(this);
        const res = await fetch('producto_nuevo.php', {method:'POST', body:data});
        if (!res.ok) throw new Error('HTTP ' + res.status);
        
        const text = await res.text();
        if (text.includes('Producto agregado correctamente')) { 
            window.location.reload(); 
        } else {
            console.error('Server response:', text);
            // Extraer el mensaje del Toast que renderiza producto_nuevo.php (es un escape JS dentro de una etiqueta <script>)
            const toastMatch = text.match(/admToast\('([^']+)'/);
            let serverError = 'Error al registrar. Revisa los datos y asegúrate de elegir imágenes válidas.';
            if (toastMatch && toastMatch[1]) {
                serverError = toastMatch[1];
            } else if (text.includes('Las imágenes seleccionadas superan')) {
                serverError = 'Las imágenes seleccionadas superan el límite de tamaño del servidor.';
            }
            m.textContent = serverError; 
            m.style.display = 'block';
        }
    } catch (error) {
        console.error("Submit error:", error);
        m.textContent = 'Error de conexión. ¿Son las imágenes muy pesadas (>8MB)? Intenta subir de menor peso.';
        m.style.display = 'block';
    } finally {
        btn.innerHTML = oldText;
        btn.disabled = false;
    }
});
</script>

<?php include '_layout_end.php'; ?>
<style>
    /* Toggle switch styling override para nuevo modal interno */
    #toggle-destacado-nuevo:checked + #slider-destacado-nuevo,
    #toggle-oferta-nuevo:checked + #slider-oferta-nuevo {
        background: var(--adm-red) !important;
    }
    #toggle-destacado-nuevo:checked + #slider-destacado-nuevo::before,
    #toggle-oferta-nuevo:checked + #slider-oferta-nuevo::before {
        transform: translateX(22px);
    }
    #slider-destacado-nuevo::before,
    #slider-oferta-nuevo::before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        border-radius: 50%;
        transition: 0.3s;
    }
</style>
<script>initPagination('#tabla-productos tbody','paginacion',10,'buscar-producto');</script>
