<?php
session_start();
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
$usuario = $_SESSION['usuario'];
$proveedores = $pdo->query('SELECT * FROM proveedores ORDER BY fecha_registro DESC')->fetchAll();

$page_title       = 'Proveedores | Computécnicos';
$admin_page       = 'proveedores';
$admin_title      = 'Proveedores';
$admin_breadcrumb = [['label' => 'Proveedores']];
$admin_header_extra = '<button id="btn-nuevo-proveedor" class="adm-btn adm-btn-success"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Nuevo proveedor</button>';

include '_layout.php';
?>

<main class="admin-content">
<div class="admin-content-inner">

    <div class="adm-card" style="padding:0;overflow:hidden">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.04)">
            <div class="adm-card-title" style="margin-bottom:0">
                <span class="adm-card-title-text">Proveedores Registrados</span>
                <span class="adm-badge adm-badge-gray"><?= count($proveedores) ?> total</span>
            </div>
        </div>
        <div class="adm-table-wrap" style="border:none;border-radius:0">
            <table class="adm-table" id="tabla-proveedores">
                <thead>
                    <tr><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Dirección</th><th>Contacto</th><th>Registro</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                <?php if (empty($proveedores)): ?>
                <tr><td colspan="7" style="text-align:center;padding:2.5rem;color:#444">No hay proveedores registrados</td></tr>
                <?php else: ?>
                <?php foreach ($proveedores as $p): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars($p['email'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($p['telefono'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($p['direccion'] ?: '—') ?></td>
                    <td><?= htmlspecialchars($p['contacto'] ?: '—') ?></td>
                    <td style="color:#555;font-size:0.75rem"><?= date('d/m/Y', strtotime($p['fecha_registro'])) ?></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <button class="adm-btn adm-btn-warning btn-editar-proveedor" style="font-size:0.72rem;padding:0.3rem 0.7rem"
                                data-proveedor='<?= json_encode(["id"=>$p["id"],"nombre"=>$p["nombre"],"email"=>$p["email"],"telefono"=>$p["telefono"],"direccion"=>$p["direccion"],"contacto"=>$p["contacto"]]) ?>'>
                                Editar
                            </button>
                            <button type="button" class="adm-btn adm-btn-danger" style="font-size:0.72rem;padding:0.3rem 0.7rem"
                               onclick="confirmarEliminar('proveedor_eliminar.php?id=<?= $p['id'] ?>', '<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>', 'proveedor')">
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

    <div id="pag-proveedores" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:1rem;flex-wrap:wrap"></div>

</div>
</main>

<!-- Modal Nuevo -->
<div id="modal-nuevo-bg" class="adm-modal-overlay"></div>
<div id="modal-proveedor" class="adm-modal hidden">
    <div class="adm-modal-box" style="max-width:500px">
        <button class="adm-modal-close" onclick="cerrarNuevo()">&times;</button>
        <div class="adm-modal-title">Nuevo Proveedor</div>
        <form id="form-proveedor" style="display:flex;flex-direction:column;gap:0.875rem">
            <div><label class="adm-label">Nombre *</label><input type="text" name="nombre" class="adm-input" required></div>
            <div><label class="adm-label">Email</label><input type="email" name="email" class="adm-input"></div>
            <div class="adm-form-row" style="margin-bottom:0">
                <div><label class="adm-label">Teléfono</label><input type="text" name="telefono" class="adm-input"></div>
                <div><label class="adm-label">Contacto</label><input type="text" name="contacto" class="adm-input"></div>
            </div>
            <div><label class="adm-label">Dirección</label><input type="text" name="direccion" class="adm-input"></div>
            <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;margin-top:0.25rem">Registrar proveedor</button>
        </form>
        <div id="modal-nuevo-msg" style="display:none;margin-top:0.75rem;text-align:center;color:#ef4444;font-size:0.8rem"></div>
    </div>
</div>

<!-- Modal Editar -->
<div id="modal-editar-bg" class="adm-modal-overlay"></div>
<div id="modal-editar-proveedor" class="adm-modal hidden">
    <div class="adm-modal-box" style="max-width:500px">
        <button class="adm-modal-close" onclick="cerrarEditar()">&times;</button>
        <div class="adm-modal-title">Editar Proveedor</div>
        <form id="form-editar-proveedor" style="display:flex;flex-direction:column;gap:0.875rem">
            <input type="hidden" name="id" id="edit-id">
            <div><label class="adm-label">Nombre *</label><input type="text" name="nombre" id="edit-nombre" class="adm-input" required></div>
            <div><label class="adm-label">Email</label><input type="email" name="email" id="edit-email" class="adm-input"></div>
            <div class="adm-form-row" style="margin-bottom:0">
                <div><label class="adm-label">Teléfono</label><input type="text" name="telefono" id="edit-telefono" class="adm-input"></div>
                <div><label class="adm-label">Contacto</label><input type="text" name="contacto" id="edit-contacto" class="adm-input"></div>
            </div>
            <div><label class="adm-label">Dirección</label><input type="text" name="direccion" id="edit-direccion" class="adm-input"></div>
            <button type="submit" class="adm-btn adm-btn-warning" style="width:100%;justify-content:center;margin-top:0.25rem">Guardar cambios</button>
        </form>
        <div id="modal-editar-msg" style="display:none;margin-top:0.75rem;text-align:center;color:#ef4444;font-size:0.8rem"></div>
    </div>
</div>

<script>
function abrirNuevo() {
    document.getElementById('modal-nuevo-bg').classList.add('show');
    document.getElementById('modal-proveedor').classList.remove('hidden');
    document.getElementById('modal-proveedor').classList.add('show');
    document.getElementById('form-proveedor').reset();
    document.body.style.overflow='hidden';
}
function cerrarNuevo() {
    document.getElementById('modal-nuevo-bg').classList.remove('show');
    document.getElementById('modal-proveedor').classList.add('hidden');
    document.getElementById('modal-proveedor').classList.remove('show');
    document.body.style.overflow='';
}
function abrirEditar(p) {
    document.getElementById('modal-editar-bg').classList.add('show');
    document.getElementById('modal-editar-proveedor').classList.remove('hidden');
    document.getElementById('modal-editar-proveedor').classList.add('show');
    document.getElementById('edit-id').value = p.id;
    document.getElementById('edit-nombre').value = p.nombre;
    document.getElementById('edit-email').value = p.email || '';
    document.getElementById('edit-telefono').value = p.telefono || '';
    document.getElementById('edit-direccion').value = p.direccion || '';
    document.getElementById('edit-contacto').value = p.contacto || '';
    document.body.style.overflow='hidden';
}
function cerrarEditar() {
    document.getElementById('modal-editar-bg').classList.remove('show');
    document.getElementById('modal-editar-proveedor').classList.add('hidden');
    document.getElementById('modal-editar-proveedor').classList.remove('show');
    document.body.style.overflow='';
}
document.getElementById('modal-nuevo-bg').addEventListener('click', cerrarNuevo);
document.getElementById('modal-editar-bg').addEventListener('click', cerrarEditar);
document.getElementById('btn-nuevo-proveedor').addEventListener('click', abrirNuevo);
document.querySelectorAll('.btn-editar-proveedor').forEach(btn => {
    btn.addEventListener('click', e => { e.preventDefault(); abrirEditar(JSON.parse(btn.dataset.proveedor)); });
});
document.getElementById('form-proveedor').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = new FormData(this);
    const res = await fetch('proveedor_nuevo.php', { method: 'POST', body: data });
    const text = await res.text();
    if (text.includes('Location: proveedores.php') || text.includes('registrado')) { window.location.reload(); }
    else {
        const m = document.getElementById('modal-nuevo-msg');
        m.textContent='Error al registrar proveedor.'; m.style.display='block';
    }
});
document.getElementById('form-editar-proveedor').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = new FormData(this);
    const res = await fetch('proveedor_editar.php?id=' + data.get('id'), { method: 'POST', body: data });
    const text = await res.text();
    if (text.includes('Location: proveedores.php') || text.includes('actualizado')) { window.location.reload(); }
    else {
        const m = document.getElementById('modal-editar-msg');
        m.textContent='Error al editar proveedor.'; m.style.display='block';
    }
});
</script>
<script>initPagination('#tabla-proveedores tbody','pag-proveedores',10);</script>

<?php include '_layout_end.php'; ?>