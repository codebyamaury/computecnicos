<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
$usuario = $_SESSION['usuario'];

$mensaje = '';
$mensaje_tipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_categoria'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($nombre) {
        $stmt = $pdo->prepare('INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)');
        $stmt->execute([$nombre, $descripcion]);
        $mensaje = 'Categoría agregada correctamente.';
    } else {
        $mensaje = 'El nombre es obligatorio.';
        $mensaje_tipo = 'error';
    }
}

if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $count = $pdo->prepare('SELECT COUNT(*) FROM productos WHERE id_categoria = ?');
    $count->execute([$id]);
    $asociados = (int)$count->fetchColumn();
    if ($asociados > 0) {
        $mensaje = "No puedes eliminar esta categoría porque tiene {$asociados} producto(s) asociado(s).";
        $mensaje_tipo = 'error';
    } else {
        $pdo->prepare('DELETE FROM categorias WHERE id = ?')->execute([$id]);
        header('Location: categorias.php?eliminado=1');
        exit;
    }
}

$categorias = $pdo->query('SELECT * FROM categorias ORDER BY nombre')->fetchAll();

$page_title       = 'Categorías | Computécnicos';
$admin_page       = 'categorias';
$admin_title      = 'Categorías';
$admin_breadcrumb = [['label' => 'Categorías']];

include '_layout.php';
?>

<main class="admin-content">
<div class="admin-content-inner" style="max-width:860px">

    <?php if ($mensaje): ?>
    <div class="adm-alert adm-alert-<?= $mensaje_tipo === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($mensaje) ?>
    </div>
    <?php endif; ?>

    <!-- Formulario agregar -->
    <div class="adm-card">
        <div class="adm-card-title"><span class="adm-card-title-text">Nueva Categoría</span></div>
        <form method="post">
                    <?= csrf_field() ?>
            <div class="adm-form-row">
                <div class="adm-form-group" style="margin-bottom:0">
                    <label class="adm-label">Nombre *</label>
                    <input type="text" name="nombre" class="adm-input" required placeholder="Ej: Laptops">
                </div>
                <div class="adm-form-group" style="margin-bottom:0">
                    <label class="adm-label">Descripción</label>
                    <input type="text" name="descripcion" class="adm-input" placeholder="Descripción opcional">
                </div>
            </div>
            <div style="margin-top:1rem;text-align:right">
                <button type="submit" name="nueva_categoria" class="adm-btn adm-btn-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Agregar Categoría
                </button>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="adm-card" style="padding:0;overflow:hidden">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.04)">
            <div class="adm-card-title" style="margin-bottom:0"><span class="adm-card-title-text">Categorías Registradas</span>
                <span class="adm-badge adm-badge-gray"><?= count($categorias) ?> total</span>
            </div>
        </div>
        <div class="adm-table-wrap" style="border:none;border-radius:0">
            <table class="adm-table" id="tabla-categorias">
                <thead><tr><th>#</th><th>Nombre</th><th>Descripción</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php if (empty($categorias)): ?>
                <tr><td colspan="4" style="text-align:center;padding:2rem;color:#555">No hay categorías registradas</td></tr>
                <?php else: ?>
                <?php foreach ($categorias as $i => $cat): ?>
                <tr>
                    <td style="color:#555;font-size:0.72rem"><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($cat['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars($cat['descripcion'] ?: '—') ?></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <button class="adm-btn adm-btn-warning btn-editar-categoria" style="font-size:0.72rem;padding:0.3rem 0.7rem"
                                data-categoria='<?= json_encode(["id"=>$cat["id"],"nombre"=>$cat["nombre"],"descripcion"=>$cat["descripcion"]]) ?>'>
                                Editar
                            </button>
                            <button type="button" class="adm-btn adm-btn-danger" style="font-size:0.72rem;padding:0.3rem 0.7rem"
                               onclick="confirmarEliminar('?eliminar=<?= $cat['id'] ?>', '<?= htmlspecialchars($cat['nombre'], ENT_QUOTES) ?>', 'categoría')">
                                Eliminar
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="pag-categorias" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:1rem;flex-wrap:wrap"></div>

</div>
</main>

<!-- Modal editar -->
<div id="modal-editar-bg" class="adm-modal-overlay"></div>
<div id="modal-editar-categoria" class="adm-modal hidden">
    <div class="adm-modal-box">
        <button class="adm-modal-close" onclick="cerrarModal()">&times;</button>
        <div class="adm-modal-title">Editar Categoría</div>
        <form id="form-editar-categoria" method="post" style="display:flex;flex-direction:column;gap:1rem">
                    <?= csrf_field() ?>
            <input type="hidden" name="id" id="edit-id">
            <div>
                <label class="adm-label">Nombre *</label>
                <input type="text" name="nombre" id="edit-nombre" class="adm-input" required>
            </div>
            <div>
                <label class="adm-label">Descripción</label>
                <textarea name="descripcion" id="edit-descripcion" rows="3" class="adm-textarea"></textarea>
            </div>
            <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center">Guardar cambios</button>
        </form>
        <div id="modal-editar-msg" style="display:none;margin-top:0.75rem;text-align:center;color:#ef4444;font-size:0.8rem"></div>
    </div>
</div>

<script>
function abrirModal(cat) {
    document.getElementById('modal-editar-bg').classList.add('show');
    document.getElementById('modal-editar-categoria').classList.remove('hidden');
    document.getElementById('modal-editar-categoria').classList.add('show');
    document.getElementById('edit-id').value = cat.id;
    document.getElementById('edit-nombre').value = cat.nombre;
    document.getElementById('edit-descripcion').value = cat.descripcion || '';
    document.getElementById('modal-editar-msg').style.display = 'none';
    document.body.style.overflow = 'hidden';
}
function cerrarModal() {
    document.getElementById('modal-editar-bg').classList.remove('show');
    document.getElementById('modal-editar-categoria').classList.add('hidden');
    document.getElementById('modal-editar-categoria').classList.remove('show');
    document.body.style.overflow = '';
    document.getElementById('form-editar-categoria').reset();
    document.getElementById('modal-editar-msg').style.display = 'none';
}
document.getElementById('modal-editar-bg').addEventListener('click', cerrarModal);
document.querySelectorAll('.btn-editar-categoria').forEach(btn => {
    btn.addEventListener('click', e => { e.preventDefault(); abrirModal(JSON.parse(btn.dataset.categoria)); });
});
document.getElementById('form-editar-categoria').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = new FormData(this);
    const res = await fetch('categoria_editar.php?ajax=1&id=' + data.get('id'), { method: 'POST', body: data });
    const text = await res.text();
    if (text.includes('Location: categorias.php') || text.includes('actualizada') || text.includes('actualizado')) { 
        window.location.href = window.location.pathname + '?editado=1'; 
    } else {
        const msg = document.getElementById('modal-editar-msg');
        msg.textContent = 'Error al editar. Revisa los datos.';
        msg.style.display = 'block';
    }
});
</script>
<script>initPagination('#tabla-categorias tbody','pag-categorias',10);</script>

<?php include '_layout_end.php'; ?>