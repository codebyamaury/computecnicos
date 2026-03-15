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
        if (!isset($_SESSION['usuario']['es_principal'])) {
            $stmtUser = $pdo->prepare('SELECT es_principal FROM usuarios WHERE id = ?');
            $stmtUser->execute([$_SESSION['usuario']['id']]);
            $_SESSION['usuario']['es_principal'] = $stmtUser->fetchColumn() ? 1 : 0;
        }
        $is_main_admin = $_SESSION['usuario']['es_principal'];
        
        $can_delete = true;
        if (!$is_main_admin) {
            $stmtRole = $pdo->prepare('SELECT rol FROM usuarios WHERE id = ?');
            $stmtRole->execute([$id]);
            if ($stmtRole->fetchColumn() === 'admin') {
                $can_delete = false;
            }
        }
        
        if ($can_delete) {
            $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
        }
    }
    header('Location: usuarios.php?eliminado=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_rol'])) {
    $id_usuario = intval($_POST['id_usuario'] ?? 0);
    $nuevo_rol = $_POST['nuevo_rol'] ?? '';
    if ($id_usuario > 0 && $id_usuario !== (int)$_SESSION['usuario']['id'] && in_array($nuevo_rol, ['cliente', 'admin'])) {
        if (!isset($_SESSION['usuario']['es_principal'])) {
            $stmtUser = $pdo->prepare('SELECT es_principal FROM usuarios WHERE id = ?');
            $stmtUser->execute([$_SESSION['usuario']['id']]);
            $_SESSION['usuario']['es_principal'] = $stmtUser->fetchColumn() ? 1 : 0;
        }
        $is_main_admin = $_SESSION['usuario']['es_principal'];
        
        $u_to_edit = $pdo->prepare('SELECT rol FROM usuarios WHERE id = ?');
        $u_to_edit->execute([$id_usuario]);
        $u_role = $u_to_edit->fetchColumn();
        
        $can_edit = true;
        if (!$is_main_admin && ($u_role === 'admin' || $nuevo_rol === 'admin')) {
            $can_edit = false; 
        }
        
        if ($can_edit) {
            $pdo->prepare('UPDATE usuarios SET rol = ? WHERE id = ?')->execute([$nuevo_rol, $id_usuario]);
        }
    }
    header('Location: usuarios.php?editado=1');
    exit;
}

$usuarios = $pdo->query('SELECT * FROM usuarios ORDER BY fecha_registro DESC')->fetchAll();

$page_title       = 'Usuarios | Computécnicos';
$admin_page       = 'usuarios';
$admin_title      = 'Usuarios';
$admin_breadcrumb = [['label' => 'Usuarios']];
$admin_header_extra = '<button id="btn-abrir-nuevo-usuario" class="adm-btn adm-btn-success"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="adm-btn-icon"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Nuevo usuario</button>';

if (!isset($_SESSION['usuario']['es_principal'])) {
    $stmtUser = $pdo->prepare('SELECT es_principal FROM usuarios WHERE id = ?');
    $stmtUser->execute([$_SESSION['usuario']['id']]);
    $_SESSION['usuario']['es_principal'] = $stmtUser->fetchColumn() ? 1 : 0;
}
$is_main_admin = $_SESSION['usuario']['es_principal'];

include '_layout.php';
?>

<main class="admin-content">
<div class="admin-content-inner">

    <div class="adm-card !p-0 overflow-hidden">
        <div class="adm-usuario-header">
            <div class="adm-card-title mb-0">
                <span class="adm-card-title-text">Usuarios Registrados</span>
                <span class="adm-badge adm-badge-gray"><?= count($usuarios) ?> total</span>
            </div>
        </div>
        <div class="adm-table-wrap !border-none !rounded-none overflow-x-auto min-w-0">
            <table class="adm-table min-w-[800px]" id="tabla-usuarios">
                <thead>
                    <tr>
                        <th class="min-w-[180px]">Nombre</th><th class="min-w-[180px]">Email</th><th class="min-w-[120px]">Teléfono</th><th class="min-w-[100px]">Rol</th><th class="min-w-[100px]">Registro</th><th class="min-w-[140px]">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td>
                        <strong class="adm-usuario-name"><?= htmlspecialchars($u['nombre']) ?></strong>
                        <?php if ($u['id'] == $_SESSION['usuario']['id']): ?>
                        <span class="adm-badge adm-badge-blue adm-usuario-you">Tú</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['telefono'] ?: '—') ?></td>
                    <td>
                        <?php if ($u['id'] == $_SESSION['usuario']['id']): ?>
                            <select class="adm-select !px-[0.6rem] !py-[0.3rem] !text-[0.72rem] w-auto" disabled>
                                <option><?= ucfirst(htmlspecialchars($u['rol'])) ?></option>
                            </select>
                        <?php elseif (!$is_main_admin && $u['rol'] === 'admin'): ?>
                            <select class="adm-select !px-[0.6rem] !py-[0.3rem] !text-[0.72rem] w-auto" disabled>
                                <option>Admin</option>
                            </select>
                        <?php else: ?>
                        <form method="post" class="adm-usuario-rol-form">
                            <input type="hidden" name="id_usuario" value="<?= $u['id'] ?>">
                            <input type="hidden" name="cambiar_rol" value="1">
                            <select name="nuevo_rol" class="adm-select !px-[0.6rem] !py-[0.3rem] !text-[0.72rem] w-auto" onchange="this.form.submit()">
                                <option value="cliente" <?= $u['rol'] === 'cliente' ? 'selected' : '' ?>>Cliente</option>
                                <?php if ($is_main_admin): ?>
                                <option value="admin"   <?= $u['rol'] === 'admin'   ? 'selected' : '' ?>>Admin</option>
                                <?php endif; ?>
                            </select>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td class="adm-usuario-date"><?= date('d/m/Y', strtotime($u['fecha_registro'])) ?></td>
                    <td>
                        <?php if ($u['id'] == $_SESSION['usuario']['id'] || (!$is_main_admin && $u['rol'] === 'admin')): ?>
                            <span class="text-[#444] text-[0.75rem]">—</span>
                        <?php else: ?>
                        <div class="adm-usuario-actions">
                            <button class="adm-btn adm-btn-warning btn-editar-usuario !text-[0.72rem] !px-[0.7rem] !py-[0.3rem]"
                                data-usuario='<?= json_encode(["id"=>$u["id"],"nombre"=>$u["nombre"],"email"=>$u["email"],"telefono"=>$u["telefono"],"direccion"=>$u["direccion"],"rol"=>$u["rol"]]) ?>'>
                                Editar
                            </button>
                            <button type="button" class="adm-btn adm-btn-danger !text-[0.72rem] !px-[0.7rem] !py-[0.3rem]"
                               onclick="confirmarEliminar('?eliminar=<?= $u['id'] ?>', '<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>', 'usuario')">
                                Eliminar
                            </button>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="pag-usuarios" class="adm-pagination-wrap mt-4"></div>

</div>
</main>

<!-- Modal Editar -->
<div id="modal-editar-bg" class="adm-modal-overlay"></div>
<div id="modal-editar-usuario" class="adm-modal hidden">
    <div class="adm-modal-box !max-w-[500px]">
        <button class="adm-modal-close" onclick="cerrarEditar()">&times;</button>
        <div class="adm-modal-title">Editar Usuario</div>
        <form id="form-editar-usuario" class="flex flex-col gap-[0.875rem]">
            <input type="hidden" name="id" id="edit-id">
            <div><label class="adm-label">Nombre *</label><input type="text" name="nombre" id="edit-nombre" class="adm-input" required></div>
            <div><label class="adm-label">Email *</label><input type="email" name="email" id="edit-email" class="adm-input" required></div>
            <div class="adm-form-row !mb-0">
                <div><label class="adm-label">Teléfono</label><input type="text" name="telefono" id="edit-telefono" class="adm-input"></div>
                <div><label class="adm-label">Rol</label>
                    <select name="rol" id="edit-rol" class="adm-select">
                        <option value="cliente">Cliente</option>
                        <?php if ($is_main_admin): ?>
                        <option value="admin">Admin</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div><label class="adm-label">Dirección</label><input type="text" name="direccion" id="edit-direccion" class="adm-input"></div>
            <div class="adm-form-row !mb-0">
                <div><label class="adm-label">Nueva contraseña</label><input type="password" name="password" id="edit-password" class="adm-input" placeholder="Dejar en blanco para no cambiar"></div>
                <div><label class="adm-label">Repetir contraseña</label><input type="password" name="password2" id="edit-password2" class="adm-input" placeholder="Dejar en blanco para no cambiar"></div>
            </div>
            <button type="submit" class="adm-btn adm-btn-primary w-full justify-center mt-1">Guardar cambios</button>
        </form>
        <div id="modal-editar-msg" class="hidden mt-3 text-center text-[#ef4444] text-[0.8rem]"></div>
    </div>
</div>

<!-- Modal Nuevo -->
<div id="modal-nuevo-bg" class="adm-modal-overlay"></div>
<div id="modal-nuevo-usuario" class="adm-modal hidden">
    <div class="adm-modal-box !max-w-[500px]">
        <button class="adm-modal-close" onclick="cerrarNuevo()">&times;</button>
        <div class="adm-modal-title">Nuevo Usuario</div>
        <form id="form-nuevo-usuario" class="flex flex-col gap-[0.875rem]">
            <div><label class="adm-label">Nombre *</label><input type="text" name="nombre" class="adm-input" required></div>
            <div><label class="adm-label">Email *</label><input type="email" name="email" class="adm-input" required></div>
            <div class="adm-form-row !mb-0">
                <div><label class="adm-label">Teléfono</label><input type="text" name="telefono" class="adm-input"></div>
                <div><label class="adm-label">Rol</label>
                    <select name="rol" class="adm-select">
                        <option value="cliente">Cliente</option>
                        <?php if ($is_main_admin): ?>
                        <option value="admin">Admin</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div><label class="adm-label">Dirección</label><input type="text" name="direccion" class="adm-input"></div>
            <div class="adm-form-row !mb-0">
                <div><label class="adm-label">Contraseña *</label><input type="password" name="password" class="adm-input" required></div>
                <div><label class="adm-label">Repetir contraseña *</label><input type="password" name="password2" class="adm-input" required></div>
            </div>
            <button type="submit" class="adm-btn adm-btn-primary w-full justify-center mt-1">Registrar</button>
        </form>
        <div id="modal-nuevo-msg" class="hidden mt-3 text-center text-[#ef4444] text-[0.8rem]"></div>
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
    document.body.classList.add('overflow-hidden');
}
function cerrarEditar() {
    document.getElementById('modal-editar-bg').classList.remove('show');
    document.getElementById('modal-editar-usuario').classList.add('hidden');
    document.getElementById('modal-editar-usuario').classList.remove('show');
    document.body.classList.remove('overflow-hidden');
}
function abrirNuevo() {
    document.getElementById('modal-nuevo-bg').classList.add('show');
    document.getElementById('modal-nuevo-usuario').classList.remove('hidden');
    document.getElementById('modal-nuevo-usuario').classList.add('show');
    document.getElementById('form-nuevo-usuario').reset();
    document.body.classList.add('overflow-hidden');
}
function cerrarNuevo() {
    document.getElementById('modal-nuevo-bg').classList.remove('show');
    document.getElementById('modal-nuevo-usuario').classList.add('hidden');
    document.getElementById('modal-nuevo-usuario').classList.remove('show');
    document.body.classList.remove('overflow-hidden');
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
    if (text.includes('Location: usuarios.php') || text.includes('actualizado')) { window.location.href = window.location.pathname + '?editado=1'; }
    else { document.getElementById('modal-editar-msg').innerText = text; document.getElementById('modal-editar-msg').classList.remove('hidden'); }
});
document.getElementById('form-nuevo-usuario').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = new FormData(this);
    const res = await fetch('usuario_nuevo.php', { method: 'POST', body: data });
    const text = await res.text();
    if (text.includes('Location: usuarios.php') || text.includes('registrado')) { window.location.href = window.location.pathname + '?exito=1'; }
    else {
        const msg = document.getElementById('modal-nuevo-msg');
        msg.textContent = 'Error al registrar usuario. Revisa los datos.';
        msg.classList.remove('hidden');
    }
});
</script>
<?php include '_layout_end.php'; ?>
<script>initPagination('#tabla-usuarios tbody','pag-usuarios',10);</script>