<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
$usuario = $_SESSION['usuario'];

if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    if ($id > 0 && $id !== (int)$_SESSION['usuario']['id']) {
        $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
    }
    header('Location: usuarios.php?eliminado=1');
    exit;
}

$usuarios = $pdo->query('SELECT * FROM usuarios ORDER BY fecha_registro DESC')->fetchAll();

$page_title       = 'Usuarios | Computécnicos';
$admin_page       = 'usuarios';
$admin_title      = 'Usuarios';
$admin_breadcrumb = [['label' => 'Usuarios']];
$admin_header_extra = '<button id="btn-abrir-nuevo-usuario" class="adm-btn adm-btn-success"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Nuevo usuario</button>';

include '_layout.php';
?>

<main class="admin-content">
<div class="admin-content-inner">

    <div class="adm-card" style="padding:0;overflow:hidden">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.04)">
            <div class="adm-card-title" style="margin-bottom:0">
                <span class="adm-card-title-text">Usuarios Registrados</span>
                <span class="adm-badge adm-badge-gray"><?= count($usuarios) ?> total</span>
            </div>
        </div>
        <div class="adm-table-wrap" style="border:none;border-radius:0">
            <table class="adm-table" id="tabla-usuarios">
                <thead>
                    <tr>
                        <th>Nombre</th><th>Email</th><th>Teléfono</th><th>Rol</th><th>Registro</th><th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($u['nombre']) ?></strong>
                        <?php if ($u['id'] == $_SESSION['usuario']['id']): ?>
                        <span class="adm-badge adm-badge-blue" style="margin-left:6px">Tú</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['telefono'] ?: '—') ?></td>
                    <td>
                        <form method="post" style="display:inline-flex;align-items:center;gap:6px">
                            <input type="hidden" name="id_usuario" value="<?= $u['id'] ?>">
                            <select name="nuevo_rol" class="adm-select" style="padding:0.3rem 0.6rem;font-size:0.72rem;width:auto" <?= $u['id'] == $_SESSION['usuario']['id'] ? 'disabled' : '' ?>>
                                <option value="cliente" <?= $u['rol'] === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                                <option value="admin"   <?= $u['rol'] === 'admin'   ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <?php if ($u['id'] != $_SESSION['usuario']['id']): ?>
                            <button type="submit" name="cambiar_rol" class="adm-btn adm-btn-blue" style="font-size:0.68rem;padding:0.25rem 0.6rem">OK</button>
                            <?php endif; ?>
                        </form>
                    </td>
                    <td style="color:#555;font-size:0.75rem"><?= date('d/m/Y', strtotime($u['fecha_registro'])) ?></td>
                    <td>
                        <?php if ($u['id'] != $_SESSION['usuario']['id']): ?>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <button class="adm-btn adm-btn-warning btn-editar-usuario" style="font-size:0.72rem;padding:0.3rem 0.7rem"
                                data-usuario='<?= json_encode(["id"=>$u["id"],"nombre"=>$u["nombre"],"email"=>$u["email"],"telefono"=>$u["telefono"],"direccion"=>$u["direccion"],"rol"=>$u["rol"]]) ?>'>
                                Editar
                            </button>
                            <button type="button" class="adm-btn adm-btn-danger" style="font-size:0.72rem;padding:0.3rem 0.7rem"
                               onclick="confirmarEliminar('?eliminar=<?= $u['id'] ?>', '<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>', 'usuario')">
                                Eliminar
                            </button>
                        </div>
                        <?php else: ?>
                        <span style="color:#444;font-size:0.75rem">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="pag-usuarios" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:1rem;flex-wrap:wrap"></div>

</div>
</main>

<!-- Modal Editar -->
<div id="modal-editar-bg" class="adm-modal-overlay"></div>
<div id="modal-editar-usuario" class="adm-modal hidden">
    <div class="adm-modal-box" style="max-width:500px">
        <button class="adm-modal-close" onclick="cerrarEditar()">&times;</button>
        <div class="adm-modal-title">Editar Usuario</div>
        <form id="form-editar-usuario" style="display:flex;flex-direction:column;gap:0.875rem">
            <input type="hidden" name="id" id="edit-id">
            <div><label class="adm-label">Nombre *</label><input type="text" name="nombre" id="edit-nombre" class="adm-input" required></div>
            <div><label class="adm-label">Email *</label><input type="email" name="email" id="edit-email" class="adm-input" required></div>
            <div class="adm-form-row" style="margin-bottom:0">
                <div><label class="adm-label">Teléfono</label><input type="text" name="telefono" id="edit-telefono" class="adm-input"></div>
                <div><label class="adm-label">Rol</label>
                    <select name="rol" id="edit-rol" class="adm-select">
                        <option value="cliente">Cliente</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div><label class="adm-label">Dirección</label><input type="text" name="direccion" id="edit-direccion" class="adm-input"></div>
            <div class="adm-form-row" style="margin-bottom:0">
                <div><label class="adm-label">Nueva contraseña</label><input type="password" name="password" id="edit-password" class="adm-input" placeholder="Dejar en blanco para no cambiar"></div>
                <div><label class="adm-label">Repetir contraseña</label><input type="password" name="password2" id="edit-password2" class="adm-input" placeholder="Dejar en blanco para no cambiar"></div>
            </div>
            <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;margin-top:0.25rem">Guardar cambios</button>
        </form>
        <div id="modal-editar-msg" style="display:none;margin-top:0.75rem;text-align:center;color:#ef4444;font-size:0.8rem"></div>
    </div>
</div>

<!-- Modal Nuevo -->
<div id="modal-nuevo-bg" class="adm-modal-overlay"></div>
<div id="modal-nuevo-usuario" class="adm-modal hidden">
    <div class="adm-modal-box" style="max-width:500px">
        <button class="adm-modal-close" onclick="cerrarNuevo()">&times;</button>
        <div class="adm-modal-title">Nuevo Usuario</div>
        <form id="form-nuevo-usuario" style="display:flex;flex-direction:column;gap:0.875rem">
            <div><label class="adm-label">Nombre *</label><input type="text" name="nombre" class="adm-input" required></div>
            <div><label class="adm-label">Email *</label><input type="email" name="email" class="adm-input" required></div>
            <div class="adm-form-row" style="margin-bottom:0">
                <div><label class="adm-label">Teléfono</label><input type="text" name="telefono" class="adm-input"></div>
                <div><label class="adm-label">Rol</label>
                    <select name="rol" class="adm-select">
                        <option value="cliente">Cliente</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div><label class="adm-label">Dirección</label><input type="text" name="direccion" class="adm-input"></div>
            <div class="adm-form-row" style="margin-bottom:0">
                <div><label class="adm-label">Contraseña *</label><input type="password" name="password" class="adm-input" required></div>
                <div><label class="adm-label">Repetir contraseña *</label><input type="password" name="password2" class="adm-input" required></div>
            </div>
            <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;margin-top:0.25rem">Registrar</button>
        </form>
        <div id="modal-nuevo-msg" style="display:none;margin-top:0.75rem;text-align:center;color:#ef4444;font-size:0.8rem"></div>
    </div>
</div>

<script>
function abrirEditar(u) {
    document.getElementById('modal-editar-bg').classList.add('show');
    document.getElementById('modal-editar-usuario').classList.remove('hidden');
    document.getElementById('modal-editar-usuario').classList.add('show');
    document.getElementById('edit-id').value = u.id;
    document.getElementById('edit-nombre').value = u.nombre;
    document.getElementById('edit-email').value = u.email;
    document.getElementById('edit-telefono').value = u.telefono || '';
    document.getElementById('edit-direccion').value = u.direccion || '';
    document.getElementById('edit-rol').value = u.rol;
    document.getElementById('edit-password').value = '';
    document.getElementById('edit-password2').value = '';
    document.body.style.overflow = 'hidden';
}
function cerrarEditar() {
    document.getElementById('modal-editar-bg').classList.remove('show');
    document.getElementById('modal-editar-usuario').classList.add('hidden');
    document.getElementById('modal-editar-usuario').classList.remove('show');
    document.body.style.overflow = '';
}
function abrirNuevo() {
    document.getElementById('modal-nuevo-bg').classList.add('show');
    document.getElementById('modal-nuevo-usuario').classList.remove('hidden');
    document.getElementById('modal-nuevo-usuario').classList.add('show');
    document.getElementById('form-nuevo-usuario').reset();
    document.body.style.overflow = 'hidden';
}
function cerrarNuevo() {
    document.getElementById('modal-nuevo-bg').classList.remove('show');
    document.getElementById('modal-nuevo-usuario').classList.add('hidden');
    document.getElementById('modal-nuevo-usuario').classList.remove('show');
    document.body.style.overflow = '';
}
document.getElementById('modal-editar-bg').addEventListener('click', cerrarEditar);
document.getElementById('modal-nuevo-bg').addEventListener('click', cerrarNuevo);
document.getElementById('btn-abrir-nuevo-usuario').addEventListener('click', abrirNuevo);
document.querySelectorAll('.btn-editar-usuario').forEach(btn => {
    btn.addEventListener('click', e => { e.preventDefault(); abrirEditar(JSON.parse(btn.dataset.usuario)); });
});
document.getElementById('form-editar-usuario').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = new FormData(this);
    const res = await fetch('usuario_editar.php?id=' + data.get('id'), { method: 'POST', body: data });
    const text = await res.text();
    if (text.includes('Location: usuarios.php') || text.includes('actualizado')) { window.location.reload(); }
    else {
        const msg = document.getElementById('modal-editar-msg');
        msg.textContent = 'Error al editar. Revisa los datos.';
        msg.style.display = 'block';
    }
});
document.getElementById('form-nuevo-usuario').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = new FormData(this);
    const res = await fetch('usuario_nuevo.php', { method: 'POST', body: data });
    const text = await res.text();
    if (text.includes('Location: usuarios.php') || text.includes('registrado')) { window.location.reload(); }
    else {
        const msg = document.getElementById('modal-nuevo-msg');
        msg.textContent = 'Error al registrar usuario. Revisa los datos.';
        msg.style.display = 'block';
    }
});
</script>
<?php include '_layout_end.php'; ?>
<script>initPagination('#tabla-usuarios tbody','pag-usuarios',10);</script>