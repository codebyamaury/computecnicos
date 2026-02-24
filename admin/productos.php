<?php
session_start();
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
    header('Location: productos.php');
    exit;
}

$productos  = $pdo->query('SELECT p.*, c.nombre AS categoria, m.nombre AS marca, (SELECT GROUP_CONCAT(url_imagen SEPARATOR ",") FROM imagenes_producto WHERE id_producto = p.id) AS imagenes_galeria FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id LEFT JOIN marcas m ON p.id_marca = m.id ORDER BY p.fecha_creacion DESC')->fetchAll();
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
                        <th>Imagen</th><th>Nombre</th><th>Categoría</th><th>Marca</th><th>Precio</th><th>Stock</th><th>Oferta</th><th>Acciones</th>
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
                        <img src="../<?= htmlspecialchars($imagenes[0]) ?>" alt="img" style="width:48px;height:38px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,0.06)">
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
                        <?php if ($p['oferta']): ?>
                        <span class="adm-badge adm-badge-red">Oferta</span>
                        <?php else: ?>
                        <span style="color:#444;font-size:0.72rem">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <button class="adm-btn adm-btn-warning btn-editar-producto" style="font-size:0.72rem;padding:0.3rem 0.7rem"
                                data-producto='<?= json_encode(["id"=>$p["id"],"nombre"=>$p["nombre"],"categoria"=>$p["id_categoria"],"marca"=>$p["id_marca"],"precio"=>$p["precio"],"stock"=>$p["stock"],"oferta"=>$p["oferta"],"descripcion"=>$p["descripcion"],"imagen"=>$p["imagen"]??null]) ?>'>
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

<!-- Modal Editar -->
<div id="modal-editar-bg" class="adm-modal-overlay"></div>
<div id="modal-editar-producto" class="adm-modal hidden">
    <div class="adm-modal-box">
        <button class="adm-modal-close" onclick="cerrarEditar()">&times;</button>
        <div class="adm-modal-title">Editar Producto</div>
        <form id="form-editar-producto" method="post" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:0.875rem">
            <input type="hidden" name="id" id="edit-id">
            <div><label class="adm-label">Nombre *</label><input type="text" name="nombre" id="edit-nombre" class="adm-input" required></div>
            <div><label class="adm-label">Descripción</label><textarea name="descripcion" id="edit-descripcion" rows="2" class="adm-textarea"></textarea></div>
            <div class="adm-form-row" style="margin-bottom:0">
                <div><label class="adm-label">Precio (COP) *</label><input type="number" name="precio" id="edit-precio" min="0" step="0.01" class="adm-input" required></div>
                <div><label class="adm-label">Stock *</label><input type="number" name="stock" id="edit-stock" min="0" class="adm-input" required></div>
            </div>
            <div class="adm-form-row" style="margin-bottom:0">
                <div><label class="adm-label">Categoría *</label>
                    <select name="id_categoria" id="edit-id_categoria" class="adm-select" required>
                        <option value="">Selecciona</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="adm-label">Marca *</label>
                    <select name="id_marca" id="edit-id_marca" class="adm-select" required>
                        <option value="">Selecciona</option>
                        <?php foreach ($marcas as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="adm-label">Imágenes (nueva(s))</label>
                <input type="file" name="imagenes[]" id="edit-imagen" accept="image/*" multiple class="adm-input" style="padding:0.4rem">
                <img id="edit-imagen-preview" src="" alt="preview" style="display:none;width:80px;height:56px;object-fit:cover;border-radius:6px;margin-top:8px">
                <div id="edit-galeria" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px"></div>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="oferta" id="edit-oferta" value="1" style="accent-color:#e00000;width:15px;height:15px">
                <label for="edit-oferta" style="font-size:0.82rem;color:#888;cursor:pointer">¿Producto en oferta?</label>
            </div>
            <button type="submit" class="adm-btn adm-btn-warning" style="width:100%;justify-content:center">Guardar cambios</button>
        </form>
        <div id="modal-editar-msg" style="display:none;margin-top:0.75rem;text-align:center;color:#ef4444;font-size:0.8rem"></div>
    </div>
</div>

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
                <div><label class="adm-label">Precio (COP) *</label><input type="number" name="precio" min="0" step="0.01" class="adm-input" required></div>
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
            <div><label class="adm-label">Imágenes *</label><input type="file" name="imagenes[]" accept="image/*" multiple required class="adm-input" style="padding:0.4rem"></div>
            <div style="display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="oferta" id="oferta" value="1" style="accent-color:#e00000;width:15px;height:15px">
                <label for="oferta" style="font-size:0.82rem;color:#888;cursor:pointer">¿Producto en oferta?</label>
            </div>
            <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center">Guardar producto</button>
        </form>
        <div id="modal-nuevo-producto-msg" style="display:none;margin-top:0.75rem;text-align:center;color:#ef4444;font-size:0.8rem"></div>
    </div>
</div>

<script>
// --- Búsqueda ---
document.getElementById('buscar-producto').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#tabla-productos tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});

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
}
function abrirEditar(p) {
    document.getElementById('modal-editar-bg').classList.add('show');
    document.getElementById('modal-editar-producto').classList.remove('hidden');
    document.getElementById('modal-editar-producto').classList.add('show');
    document.getElementById('edit-id').value = p.id;
    document.getElementById('edit-nombre').value = p.nombre;
    document.getElementById('edit-descripcion').value = p.descripcion;
    document.getElementById('edit-precio').value = p.precio;
    document.getElementById('edit-stock').value = p.stock;
    document.getElementById('edit-oferta').checked = p.oferta == 1;
    document.getElementById('edit-id_categoria').value = String(p.categoria||'');
    document.getElementById('edit-id_marca').value = String(p.marca||'');
    const prev = document.getElementById('edit-imagen-preview');
    if (p.imagen) { prev.src='../'+p.imagen; prev.style.display=''; } else { prev.style.display='none'; }
    cargarGaleria(p.id);
    document.getElementById('modal-editar-msg').style.display='none';
    document.body.style.overflow='hidden';
}
function cerrarEditar() {
    document.getElementById('modal-editar-bg').classList.remove('show');
    document.getElementById('modal-editar-producto').classList.add('hidden');
    document.getElementById('modal-editar-producto').classList.remove('show');
    document.body.style.overflow='';
}
document.getElementById('modal-editar-bg').addEventListener('click', cerrarEditar);
document.querySelectorAll('.btn-editar-producto').forEach(btn=>{
    btn.addEventListener('click', e=>{ e.preventDefault(); abrirEditar(JSON.parse(btn.dataset.producto)); });
});
document.getElementById('form-editar-producto').addEventListener('submit', async function(e){
    e.preventDefault();
    const data = new FormData(this);
    const res = await fetch('producto_editar.php?id='+data.get('id'), {method:'POST',body:data});
    const text = await res.text();
    if (text.includes('actualizado correctamente')) { window.location.reload(); }
    else {
        const m = document.getElementById('modal-editar-msg');
        m.textContent='Error al editar. Revisa los datos.'; m.style.display='block';
    }
});

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
    const data = new FormData(this);
    const res = await fetch('producto_nuevo.php', {method:'POST',body:data});
    const text = await res.text();
    if (text.includes('Producto agregado correctamente')) { window.location.reload(); }
    else {
        const m = document.getElementById('modal-nuevo-producto-msg');
        m.textContent='Error al registrar. Revisa los datos.'; m.style.display='block';
    }
});
// --- Paginación + Búsqueda ---
(function(){
    const PER_PAGE = 10;
    const tbody = document.querySelector('#tabla-productos tbody');
    const allRows = Array.from(tbody.querySelectorAll('tr'));
    const pagDiv = document.getElementById('paginacion');
    const buscar = document.getElementById('buscar-producto');
    let filteredRows = allRows.slice();
    let currentPage = 1;

    function renderPage() {
        const totalPages = Math.max(1, Math.ceil(filteredRows.length / PER_PAGE));
        if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * PER_PAGE;
        const end = start + PER_PAGE;
        allRows.forEach(r => r.style.display = 'none');
        filteredRows.slice(start, end).forEach(r => r.style.display = '');
        // Render controls
        let html = '';
        html += '<button onclick="paginaProductos(\'prev\')" class="adm-btn" style="font-size:.72rem;padding:.3rem .7rem"' + (currentPage <= 1 ? ' disabled style="font-size:.72rem;padding:.3rem .7rem;opacity:.4;pointer-events:none"' : '') + '>← Anterior</button>';
        for (let i = 1; i <= totalPages; i++) {
            if (totalPages > 7 && i > 2 && i < totalPages - 1 && Math.abs(i - currentPage) > 1) {
                if (i === 3 || i === totalPages - 2) html += '<span style="color:#555;font-size:.8rem">…</span>';
                continue;
            }
            html += '<button onclick="paginaProductos(' + i + ')" class="adm-btn' + (i === currentPage ? ' adm-btn-primary' : '') + '" style="font-size:.72rem;padding:.3rem .65rem;min-width:30px">' + i + '</button>';
        }
        html += '<button onclick="paginaProductos(\'next\')" class="adm-btn" style="font-size:.72rem;padding:.3rem .7rem"' + (currentPage >= totalPages ? ' disabled style="font-size:.72rem;padding:.3rem .7rem;opacity:.4;pointer-events:none"' : '') + '>Siguiente →</button>';
        html += '<span style="color:#555;font-size:.72rem;margin-left:8px">Mostrando ' + (filteredRows.length ? start+1 : 0) + '-' + Math.min(end, filteredRows.length) + ' de ' + filteredRows.length + '</span>';
        pagDiv.innerHTML = html;
    }

    window.paginaProductos = function(action) {
        const totalPages = Math.max(1, Math.ceil(filteredRows.length / PER_PAGE));
        if (action === 'prev') currentPage = Math.max(1, currentPage - 1);
        else if (action === 'next') currentPage = Math.min(totalPages, currentPage + 1);
        else currentPage = parseInt(action);
        renderPage();
    };

    if (buscar) {
        buscar.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            filteredRows = allRows.filter(r => {
                const text = r.textContent.toLowerCase();
                return !q || text.includes(q);
            });
            currentPage = 1;
            renderPage();
        });
    }

    renderPage();
})();
</script>

<?php include '_layout_end.php'; ?>
