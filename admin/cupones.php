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

// ── Crear cupón ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_cupon'])) {
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $tipo_descuento = $_POST['tipo_descuento'] ?? 'porcentaje';
    $valor = floatval($_POST['valor'] ?? 0);
    $fecha_expiracion = $_POST['fecha_expiracion'] ?? null;
    $limite_usos = intval($_POST['limite_usos'] ?? 0);
    $monto_minimo = floatval($_POST['monto_minimo'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (!$codigo || $valor <= 0) {
        $mensaje = 'El código y el valor del descuento son obligatorios.';
        $mensaje_tipo = 'error';
    } else {
        // Verificar que no exista
        $check = $pdo->prepare('SELECT id FROM cupones WHERE codigo = ?');
        $check->execute([$codigo]);
        if ($check->fetch()) {
            $mensaje = 'Ya existe un cupón con ese código.';
            $mensaje_tipo = 'error';
        } else {
            $stmt = $pdo->prepare('INSERT INTO cupones (codigo, tipo_descuento, valor, fecha_expiracion, limite_usos, monto_minimo, activo) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$codigo, $tipo_descuento, $valor, $fecha_expiracion ?: null, $limite_usos, $monto_minimo, $activo]);
            header('Location: cupones.php?exito=1');
            exit;
        }
    }
}

// ── Editar cupón (AJAX) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_cupon'])) {
    $id = intval($_POST['id'] ?? 0);
    $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
    $tipo_descuento = $_POST['tipo_descuento'] ?? 'porcentaje';
    $valor = floatval($_POST['valor'] ?? 0);
    $fecha_expiracion = $_POST['fecha_expiracion'] ?? null;
    $limite_usos = intval($_POST['limite_usos'] ?? 0);
    $monto_minimo = floatval($_POST['monto_minimo'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (!$codigo || $valor <= 0) {
        $mensaje = 'El código y el valor del descuento son obligatorios.';
        $mensaje_tipo = 'error';
    } else {
        // Verificar unicidad (excluir el actual)
        $check = $pdo->prepare('SELECT id FROM cupones WHERE codigo = ? AND id != ?');
        $check->execute([$codigo, $id]);
        if ($check->fetch()) {
            $mensaje = 'Ya existe otro cupón con ese código.';
            $mensaje_tipo = 'error';
        } else {
            $stmt = $pdo->prepare('UPDATE cupones SET codigo=?, tipo_descuento=?, valor=?, fecha_expiracion=?, limite_usos=?, monto_minimo=?, activo=? WHERE id=?');
            $stmt->execute([$codigo, $tipo_descuento, $valor, $fecha_expiracion ?: null, $limite_usos, $monto_minimo, $activo, $id]);
            header('Location: cupones.php?editado=1');
            exit;
        }
    }
}

// ── Eliminar cupón ──
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $pdo->prepare('DELETE FROM cupones WHERE id = ?')->execute([$id]);
    header('Location: cupones.php?eliminado=1');
    exit;
}

// ── Toggle estado ──
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    $pdo->exec("UPDATE cupones SET activo = NOT activo WHERE id = $id");
    header('Location: cupones.php?editado=1');
    exit;
}

$cupones = $pdo->query('SELECT * FROM cupones ORDER BY created_at DESC')->fetchAll();

$page_title       = 'Cupones | Computécnicos';
$admin_page       = 'cupones';
$admin_title      = 'Cupones de Descuento';
$admin_breadcrumb = [['label' => 'Cupones']];

include '_layout.php';
?>

<main class="admin-content">
<div class="admin-content-inner" style="max-width:1100px">

    <?php if ($mensaje): ?>
    <div class="adm-alert adm-alert-<?= $mensaje_tipo === 'success' ? 'success' : 'error' ?>">
        <?= htmlspecialchars($mensaje) ?>
    </div>
    <?php endif; ?>

    <!-- Formulario crear cupón -->
    <div class="adm-card">
        <div class="adm-card-title"><span class="adm-card-title-text">Nuevo Cupón</span></div>
        <form method="post">
            <?= csrf_field() ?>
            <div class="adm-form-row" style="flex-wrap:wrap;gap:1rem">
                <div class="adm-form-group" style="flex:1;min-width:160px;margin-bottom:0">
                    <label class="adm-label">Código *</label>
                    <input type="text" name="codigo" class="adm-input" required placeholder="Ej: DESCUENTO20" style="text-transform:uppercase">
                </div>
                <div class="adm-form-group" style="flex:0.7;min-width:140px;margin-bottom:0">
                    <label class="adm-label">Tipo de descuento</label>
                    <select name="tipo_descuento" class="adm-input" style="padding:0.55rem 0.75rem">
                        <option value="porcentaje">Porcentaje (%)</option>
                        <option value="fijo">Monto Fijo ($)</option>
                    </select>
                </div>
                <div class="adm-form-group" style="flex:0.5;min-width:120px;margin-bottom:0">
                    <label class="adm-label">Valor *</label>
                    <input type="number" name="valor" class="adm-input" required placeholder="10" step="0.01" min="0.01">
                </div>
                <div class="adm-form-group" style="flex:0.7;min-width:140px;margin-bottom:0">
                    <label class="adm-label">Expira</label>
                    <input type="date" name="fecha_expiracion" class="adm-input">
                </div>
                <div class="adm-form-group" style="flex:0.5;min-width:100px;margin-bottom:0">
                    <label class="adm-label">Límite usos</label>
                    <input type="number" name="limite_usos" class="adm-input" placeholder="0=ilimitado" min="0" value="0">
                </div>
                <div class="adm-form-group" style="flex:0.6;min-width:130px;margin-bottom:0">
                    <label class="adm-label">Monto mínimo</label>
                    <input type="number" name="monto_minimo" class="adm-input" placeholder="0" min="0" step="1" value="0">
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:1rem;flex-wrap:wrap;gap:0.5rem">
                <label style="display:flex;align-items:center;gap:8px;color:#aaa;font-size:0.85rem;cursor:pointer">
                    <input type="checkbox" name="activo" checked style="accent-color:#ff0000;width:16px;height:16px">
                    Activo al crear
                </label>
                <button type="submit" name="nuevo_cupon" class="adm-btn adm-btn-primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Crear Cupón
                </button>
            </div>
        </form>
    </div>

    <!-- Tabla de cupones -->
    <div class="adm-card" style="padding:0;overflow:hidden">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.04);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem">
            <div class="adm-card-title" style="margin-bottom:0"><span class="adm-card-title-text">Cupones Registrados</span>
                <span class="adm-badge adm-badge-gray"><?= count($cupones) ?> total</span>
            </div>
            <input type="text" id="buscar-cupon" class="adm-input" placeholder="Buscar cupón..." style="max-width:220px;padding:0.4rem 0.75rem;font-size:0.82rem">
        </div>
        <div class="adm-table-wrap" style="border:none;border-radius:0">
            <table class="adm-table" id="tabla-cupones">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Descuento</th>
                        <th>Expira</th>
                        <th>Usos</th>
                        <th>Monto mín.</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($cupones)): ?>
                <tr><td colspan="7" style="text-align:center;padding:2rem;color:#555">No hay cupones registrados</td></tr>
                <?php else: ?>
                <?php foreach ($cupones as $cup): 
                    $expirado = !empty($cup['fecha_expiracion']) && strtotime($cup['fecha_expiracion']) < strtotime('today');
                    $agotado = $cup['limite_usos'] > 0 && $cup['usos_actuales'] >= $cup['limite_usos'];
                    $estadoClase = '';
                    $estadoTexto = '';
                    if (!$cup['activo']) { $estadoClase = 'adm-badge-gray'; $estadoTexto = 'Inactivo'; }
                    elseif ($expirado) { $estadoClase = 'adm-badge-gray'; $estadoTexto = 'Expirado'; }
                    elseif ($agotado) { $estadoClase = 'adm-badge-gray'; $estadoTexto = 'Agotado'; }
                    else { $estadoClase = 'adm-badge-green'; $estadoTexto = 'Activo'; }
                ?>
                <tr>
                    <td>
                        <strong style="color:#ff4444;font-family:monospace;font-size:0.95rem;letter-spacing:1px"><?= e($cup['codigo']) ?></strong>
                    </td>
                    <td>
                        <?php if ($cup['tipo_descuento'] === 'porcentaje'): ?>
                            <span class="adm-badge" style="background:rgba(255,0,0,0.12);color:#ff4444;border:1px solid rgba(255,0,0,0.25)"><?= number_format($cup['valor'], 0) ?>%</span>
                        <?php else: ?>
                            <span class="adm-badge" style="background:rgba(34,197,94,0.12);color:#22c55e;border:1px solid rgba(34,197,94,0.25)">$<?= number_format($cup['valor'], 0, ',', '.') ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.82rem;color:#888">
                        <?= $cup['fecha_expiracion'] ? date('d/m/Y', strtotime($cup['fecha_expiracion'])) : '—' ?>
                        <?php if ($expirado): ?><br><span style="color:#ef4444;font-size:0.72rem">Expirado</span><?php endif; ?>
                    </td>
                    <td style="font-size:0.85rem">
                        <span style="color:#fff"><?= $cup['usos_actuales'] ?></span>
                        <span style="color:#555">/</span>
                        <span style="color:#888"><?= $cup['limite_usos'] > 0 ? $cup['limite_usos'] : '∞' ?></span>
                    </td>
                    <td style="font-size:0.85rem;color:#888">
                        <?= $cup['monto_minimo'] > 0 ? '$' . number_format($cup['monto_minimo'], 0, ',', '.') : '—' ?>
                    </td>
                    <td><span class="adm-badge <?= $estadoClase ?>"><?= $estadoTexto ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <button class="adm-btn adm-btn-warning btn-editar-cupon" style="font-size:0.72rem;padding:0.3rem 0.7rem"
                                data-cupon='<?= json_encode([
                                    "id" => $cup["id"],
                                    "codigo" => $cup["codigo"],
                                    "tipo_descuento" => $cup["tipo_descuento"],
                                    "valor" => $cup["valor"],
                                    "fecha_expiracion" => $cup["fecha_expiracion"],
                                    "limite_usos" => $cup["limite_usos"],
                                    "monto_minimo" => $cup["monto_minimo"],
                                    "activo" => $cup["activo"]
                                ]) ?>'>
                                Editar
                            </button>
                            <a href="?toggle=<?= $cup['id'] ?>&csrf_token=<?= csrf_token() ?>" class="adm-btn <?= $cup['activo'] ? 'adm-btn-secondary' : 'adm-btn-success' ?>" style="font-size:0.72rem;padding:0.3rem 0.7rem">
                                <?= $cup['activo'] ? 'Desactivar' : 'Activar' ?>
                            </a>
                            <button type="button" class="adm-btn adm-btn-danger" style="font-size:0.72rem;padding:0.3rem 0.7rem"
                                onclick="confirmarEliminar('?eliminar=<?= $cup['id'] ?>', '<?= e($cup['codigo']) ?>', 'cupón')">
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

    <div id="pag-cupones" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:1rem;flex-wrap:wrap"></div>

</div>
</main>

<!-- Modal editar cupón -->
<div id="modal-editar-bg" class="adm-modal-overlay"></div>
<div id="modal-editar-cupon" class="adm-modal hidden">
    <div class="adm-modal-box" style="max-width:480px">
        <button class="adm-modal-close" onclick="cerrarModalCupon()">&times;</button>
        <div class="adm-modal-title">Editar Cupón</div>
        <form id="form-editar-cupon" method="post" style="display:flex;flex-direction:column;gap:1rem">
            <?= csrf_field() ?>
            <input type="hidden" name="editar_cupon" value="1">
            <input type="hidden" name="id" id="edit-id">
            <div>
                <label class="adm-label">Código *</label>
                <input type="text" name="codigo" id="edit-codigo" class="adm-input" required style="text-transform:uppercase">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div>
                    <label class="adm-label">Tipo de descuento</label>
                    <select name="tipo_descuento" id="edit-tipo" class="adm-input" style="padding:0.55rem 0.75rem">
                        <option value="porcentaje">Porcentaje (%)</option>
                        <option value="fijo">Monto Fijo ($)</option>
                    </select>
                </div>
                <div>
                    <label class="adm-label">Valor *</label>
                    <input type="number" name="valor" id="edit-valor" class="adm-input" required step="0.01" min="0.01">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div>
                    <label class="adm-label">Fecha expiración</label>
                    <input type="date" name="fecha_expiracion" id="edit-fecha" class="adm-input">
                </div>
                <div>
                    <label class="adm-label">Límite de usos</label>
                    <input type="number" name="limite_usos" id="edit-limite" class="adm-input" min="0">
                </div>
            </div>
            <div>
                <label class="adm-label">Monto mínimo ($)</label>
                <input type="number" name="monto_minimo" id="edit-monto" class="adm-input" min="0" step="1">
            </div>
            <label style="display:flex;align-items:center;gap:8px;color:#aaa;font-size:0.85rem;cursor:pointer">
                <input type="checkbox" name="activo" id="edit-activo" style="accent-color:#ff0000;width:16px;height:16px">
                Cupón activo
            </label>
            <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center">Guardar cambios</button>
        </form>
        <div id="modal-editar-msg" style="display:none;margin-top:0.75rem;text-align:center;color:#ef4444;font-size:0.8rem"></div>
    </div>
</div>

<script>
function abrirModalCupon(cup) {
    document.getElementById('modal-editar-bg').classList.add('show');
    document.getElementById('modal-editar-cupon').classList.remove('hidden');
    document.getElementById('modal-editar-cupon').classList.add('show');
    document.getElementById('edit-id').value = cup.id;
    document.getElementById('edit-codigo').value = cup.codigo;
    document.getElementById('edit-tipo').value = cup.tipo_descuento;
    document.getElementById('edit-valor').value = cup.valor;
    document.getElementById('edit-fecha').value = cup.fecha_expiracion || '';
    document.getElementById('edit-limite').value = cup.limite_usos;
    document.getElementById('edit-monto').value = cup.monto_minimo;
    document.getElementById('edit-activo').checked = !!parseInt(cup.activo);
    document.getElementById('modal-editar-msg').style.display = 'none';
    document.body.style.overflow = 'hidden';
}
function cerrarModalCupon() {
    document.getElementById('modal-editar-bg').classList.remove('show');
    document.getElementById('modal-editar-cupon').classList.add('hidden');
    document.getElementById('modal-editar-cupon').classList.remove('show');
    document.body.style.overflow = '';
    document.getElementById('form-editar-cupon').reset();
    document.getElementById('modal-editar-msg').style.display = 'none';
}
document.getElementById('modal-editar-bg').addEventListener('click', cerrarModalCupon);
document.querySelectorAll('.btn-editar-cupon').forEach(btn => {
    btn.addEventListener('click', e => { e.preventDefault(); abrirModalCupon(JSON.parse(btn.dataset.cupon)); });
});
</script>
<script>initPagination('#tabla-cupones tbody','pag-cupones',10,'buscar-cupon');</script>

<?php include '_layout_end.php'; ?>
