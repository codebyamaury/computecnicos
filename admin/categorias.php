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
<div class="admin-content-inner !max-w-[860px]">

    <?php if ($mensaje): ?>
    <div class="adm-alert adm-alert-<?= $mensaje_tipo === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($mensaje) ?>
    </div>
    <?php endif; ?>

    <!-- Formulario agregar -->
    <div class="adm-card">
        <div class="adm-card-title"><span class="adm-card-title-text">Nueva Categoría</span></div>
        <form method="post">
            <div class="adm-form-row">
                <div class="adm-form-group !mb-0">
                    <label class="adm-label">Nombre *</label>
                    <input type="text" name="nombre" class="adm-input" required placeholder="Ej: Laptops">
                </div>
                <div class="adm-form-group !mb-0">
                    <label class="adm-label">Descripción</label>
                    <input type="text" name="descripcion" class="adm-input" placeholder="Descripción opcional">
                </div>
            </div>
            <div class="mt-4 text-right">
                <button type="submit" name="nueva_categoria" class="adm-btn adm-btn-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="adm-btn-icon"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Agregar Categoría
                </button>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="adm-card !p-0 overflow-hidden">
        <div class="adm-card-header">
            <div class="adm-card-title !mb-0"><span class="adm-card-title-text">Categorías Registradas</span>
                <span class="adm-badge adm-badge-gray"><?= count($categorias) ?> total</span>
            </div>
        </div>
        <div class="adm-table-wrap !border-none !rounded-none">
            <table class="adm-table" id="tabla-categorias">
                <thead><tr><th>#</th><th>Nombre</th><th>Descripción</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php if (empty($categorias)): ?>
                <tr><td colspan="4" class="text-center py-8 text-[#555]">No hay categorías registradas</td></tr>
                <?php else: ?>
                <?php foreach ($categorias as $i => $cat): ?>
                <tr>
                    <td class="text-[#555] text-[0.72rem]"><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($cat['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars($cat['descripcion'] ?: '—') ?></td>
                    <td>
                        <div class="adm-flex-actions">
                            <button class="adm-btn adm-btn-warning btn-editar-categoria !text-[0.72rem] !px-[0.7rem] !py-[0.3rem]"
                                data-categoria='<?= json_encode(["id"=>$cat["id"],"nombre"=>$cat["nombre"],"descripcion"=>$cat["descripcion"]]) ?>'>
                                Editar
                            </button>
                            <button type="button" class="adm-btn adm-btn-danger !text-[0.72rem] !px-[0.7rem] !py-[0.3rem]"
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

    <div id="pag-categorias" class="adm-pagination-wrap"></div>

</div>
</main>

<!-- Modal editar -->
<div id="modal-editar-bg" class="adm-modal-overlay"></div>
<div id="modal-editar-categoria" class="adm-modal hidden">
    <div class="adm-modal-box">
        <button class="adm-modal-close" onclick="cerrarModal()">&times;</button>
        <div class="adm-modal-title">Editar Categoría</div>
        <form id="form-editar-categoria" method="post" class="flex flex-col gap-4">
            <input type="hidden" name="id" id="edit-id">
            <div>
                <label class="adm-label">Nombre *</label>
                <input type="text" name="nombre" id="edit-nombre" class="adm-input" required>
            </div>
            <div>
                <label class="adm-label">Descripción</label>
                <textarea name="descripcion" id="edit-descripcion" rows="3" class="adm-textarea"></textarea>
            </div>
            <button type="submit" class="adm-btn adm-btn-primary w-full justify-center">Guardar cambios</button>
        </form>
        <div id="modal-editar-msg" class="hidden mt-3 text-center text-[#ef4444] text-[0.8rem]"></div>
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
    document.getElementById('modal-editar-msg').classList.add('hidden');
    document.body.style.overflow = 'hidden';
}
function cerrarModal() {
    document.getElementById('modal-editar-bg').classList.remove('show');
    document.getElementById('modal-editar-categoria').classList.add('hidden');
    document.getElementById('modal-editar-categoria').classList.remove('show');
    document.body.style.overflow = '';
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
        msg.classList.remove('hidden');
    }
});
</script>
<script>initPagination('#tabla-categorias tbody','pag-categorias',10);</script>

<?php include '_layout_end.php'; ?>