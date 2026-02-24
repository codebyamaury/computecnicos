<?php
session_start();
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
$usuario = $_SESSION['usuario'];

$mensaje = '';
$mensaje_tipo = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_marca'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($nombre) {
        $stmt = $pdo->prepare('INSERT INTO marcas (nombre, descripcion) VALUES (?, ?)');
        $stmt->execute([$nombre, $descripcion]);
        $mensaje = 'Marca agregada correctamente.';
    } else {
        $mensaje = 'El nombre es obligatorio.';
        $mensaje_tipo = 'error';
    }
}

if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $pdo->prepare('DELETE FROM marcas WHERE id = ?')->execute([$id]);
    header('Location: marcas.php');
    exit;
}

$marcas = $pdo->query('SELECT * FROM marcas ORDER BY nombre')->fetchAll();

$page_title       = 'Marcas | Computécnicos';
$admin_page       = 'marcas';
$admin_title      = 'Marcas';
$admin_breadcrumb = [['label' => 'Marcas']];

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
        <div class="adm-card-title"><span class="adm-card-title-text">Nueva Marca</span></div>
        <form method="post">
            <div class="adm-form-row">
                <div class="adm-form-group" style="margin-bottom:0">
                    <label class="adm-label">Nombre *</label>
                    <input type="text" name="nombre" class="adm-input" required placeholder="Ej: HP, Lenovo, Samsung">
                </div>
                <div class="adm-form-group" style="margin-bottom:0">
                    <label class="adm-label">Descripción</label>
                    <input type="text" name="descripcion" class="adm-input" placeholder="Descripción opcional">
                </div>
            </div>
            <div style="margin-top:1rem;text-align:right">
                <button type="submit" name="nueva_marca" class="adm-btn adm-btn-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Agregar Marca
                </button>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="adm-card" style="padding:0;overflow:hidden">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.04)">
            <div class="adm-card-title" style="margin-bottom:0">
                <span class="adm-card-title-text">Marcas Registradas</span>
                <span class="adm-badge adm-badge-gray"><?= count($marcas) ?> total</span>
            </div>
        </div>
        <div class="adm-table-wrap" style="border:none;border-radius:0">
            <table class="adm-table" id="tabla-marcas">
                <thead><tr><th>#</th><th>Nombre</th><th>Descripción</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php if (empty($marcas)): ?>
                <tr><td colspan="4" style="text-align:center;padding:2rem;color:#555">No hay marcas registradas</td></tr>
                <?php else: ?>
                <?php foreach ($marcas as $i => $m): ?>
                <tr>
                    <td style="color:#555;font-size:0.72rem"><?= $i+1 ?></td>
                    <td><strong><?= htmlspecialchars($m['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars($m['descripcion'] ?: '—') ?></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <button class="adm-btn adm-btn-warning btn-editar-marca" style="font-size:0.72rem;padding:0.3rem 0.7rem"
                                data-marca='<?= json_encode(["id"=>$m["id"],"nombre"=>$m["nombre"],"descripcion"=>$m["descripcion"]]) ?>'>
                                Editar
                            </button>
                            <button type="button" class="adm-btn adm-btn-danger" style="font-size:0.72rem;padding:0.3rem 0.7rem"
                               onclick="confirmarEliminar('?eliminar=<?= $m['id'] ?>', '<?= htmlspecialchars($m['nombre'], ENT_QUOTES) ?>', 'marca')">
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

    <div id="pag-marcas" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:1rem;flex-wrap:wrap"></div>

</div>
</main>

<!-- Modal editar -->
<div id="modal-editar-bg" class="adm-modal-overlay"></div>
<div id="modal-editar-marca" class="adm-modal hidden">
    <div class="adm-modal-box">
        <button class="adm-modal-close" onclick="cerrarModal()">&times;</button>
        <div class="adm-modal-title">Editar Marca</div>
        <form id="form-editar-marca" style="display:flex;flex-direction:column;gap:1rem">
            <input type="hidden" name="id" id="edit-id">
            <div>
                <label class="adm-label">Nombre *</label>
                <input type="text" name="nombre" id="edit-nombre" class="adm-input" required>
            </div>
            <div>
                <label class="adm-label">Descripción</label>
                <input type="text" name="descripcion" id="edit-descripcion" class="adm-input">
            </div>
            <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center">Guardar cambios</button>
        </form>
        <div id="modal-editar-msg" style="display:none;margin-top:0.75rem;text-align:center;color:#ef4444;font-size:0.8rem"></div>
    </div>
</div>

<script>
function abrirModal(m) {
    document.getElementById('modal-editar-bg').classList.add('show');
    document.getElementById('modal-editar-marca').classList.remove('hidden');
    document.getElementById('modal-editar-marca').classList.add('show');
    document.getElementById('edit-id').value = m.id;
    document.getElementById('edit-nombre').value = m.nombre;
    document.getElementById('edit-descripcion').value = m.descripcion || '';
    document.getElementById('modal-editar-msg').style.display = 'none';
    document.body.style.overflow = 'hidden';
}
function cerrarModal() {
    document.getElementById('modal-editar-bg').classList.remove('show');
    document.getElementById('modal-editar-marca').classList.add('hidden');
    document.getElementById('modal-editar-marca').classList.remove('show');
    document.body.style.overflow = '';
}
document.getElementById('modal-editar-bg').addEventListener('click', cerrarModal);
document.querySelectorAll('.btn-editar-marca').forEach(btn => {
    btn.addEventListener('click', e => { e.preventDefault(); abrirModal(JSON.parse(btn.dataset.marca)); });
});
document.getElementById('form-editar-marca').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = new FormData(this);
    const res = await fetch('marca_editar.php?id=' + data.get('id'), { method: 'POST', body: data });
    const text = await res.text();
    if (text.includes('Marca actualizada correctamente')) {
        window.location.reload();
    } else {
        const msg = document.getElementById('modal-editar-msg');
        msg.textContent = 'Error al editar marca. Revisa los datos.';
        msg.style.display = 'block';
    }
});
</script>
<script>initPagination('#tabla-marcas tbody','pag-marcas',10);</script>

<?php include '_layout_end.php'; ?>