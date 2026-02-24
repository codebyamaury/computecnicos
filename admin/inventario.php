<?php
session_start();
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
$usuario = $_SESSION['usuario'];

$stmt = $pdo->query('SELECT m.*, p.nombre AS producto, u.nombre AS usuario,
    prov.nombre AS proveedor
    FROM movimientos_inventario m
    JOIN productos p ON m.id_producto = p.id
    LEFT JOIN usuarios u ON m.id_usuario = u.id
    LEFT JOIN proveedores prov ON m.id_proveedor = prov.id
    ORDER BY m.fecha DESC');
$movimientos = $stmt->fetchAll();

$page_title       = 'Inventario | Computécnicos';
$admin_page       = 'inventario';
$admin_title      = 'Inventario';
$admin_breadcrumb = [['label' => 'Inventario']];
$admin_header_extra = '
    <a href="limpiar_soportes.php" class="adm-btn adm-btn-warning">🗑️ Limpiar Soportes</a>
    <button id="btn-nuevo-movimiento" class="adm-btn adm-btn-success"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Nuevo movimiento</button>';

include '_layout.php';
?>

<main class="admin-content" style="padding:1.25rem 1.5rem;display:flex;flex-direction:column;overflow:hidden;height:calc(100vh - 60px)">

    <!-- Card estática con scroll interno -->
    <div class="adm-card" style="padding:0;overflow:hidden;display:flex;flex-direction:column;flex:1;min-height:0">
        <!-- Header de la card (estático) -->
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.04);flex-shrink:0">
            <div class="adm-card-title" style="margin-bottom:0">
                <span class="adm-card-title-text">Movimientos de Inventario</span>
                <span class="adm-badge adm-badge-gray"><?= count($movimientos) ?> registros</span>
            </div>
        </div>
        <!-- Zona scrolleable (solo vertical) -->
        <div style="flex:1;overflow-y:auto;overflow-x:hidden;min-height:0">
            <table class="adm-table" id="tabla-inventario" style="font-size:0.7rem;table-layout:fixed;width:100%">
                <colgroup>
                    <col style="width:12%"><!-- Producto -->
                    <col style="width:6%"><!-- Tipo -->
                    <col style="width:4%"><!-- Cant -->
                    <col style="width:9%"><!-- Proveedor -->
                    <col style="width:7%"><!-- Factura -->
                    <col style="width:7%"><!-- Precio -->
                    <col style="width:6%"><!-- IVA -->
                    <col style="width:6%"><!-- Ret -->
                    <col style="width:13%"><!-- Motivo -->
                    <col style="width:7%"><!-- Usuario -->
                    <col style="width:9%"><!-- Fecha -->
                    <col style="width:5%"><!-- Soporte -->
                    <col style="width:6%"><!-- Acciones -->
                </colgroup>
                <thead style="position:sticky;top:0;z-index:2">
                    <tr>
                        <th style="padding:.5rem .4rem">Producto</th><th style="padding:.5rem .4rem">Tipo</th><th style="padding:.5rem .4rem">Cant.</th><th style="padding:.5rem .4rem">Proveedor</th>
                        <th style="padding:.5rem .4rem">Factura</th><th style="padding:.5rem .4rem">Precio U.</th><th style="padding:.5rem .4rem">IVA</th><th style="padding:.5rem .4rem">Ret.</th>
                        <th style="padding:.5rem .4rem">Motivo</th><th style="padding:.5rem .4rem">Usuario</th><th style="padding:.5rem .4rem">Fecha</th><th style="padding:.5rem .4rem">Sop.</th><th style="padding:.5rem .4rem">Acc.</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($movimientos)): ?>
                <tr><td colspan="13" style="text-align:center;padding:2.5rem;color:#444">No hay movimientos de inventario</td></tr>
                <?php else: ?>
                <?php foreach ($movimientos as $m): ?>
                <tr>
                    <td style="padding:.45rem .4rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($m['producto']) ?>"><strong><?= htmlspecialchars($m['producto']) ?></strong></td>
                    <td style="padding:.45rem .4rem">
                        <?php if ($m['tipo'] === 'entrada'): ?>
                        <span class="adm-badge adm-badge-green" style="font-size:.6rem;padding:.15rem .4rem">Entrada</span>
                        <?php elseif ($m['tipo'] === 'salida'): ?>
                        <span class="adm-badge adm-badge-red" style="font-size:.6rem;padding:.15rem .4rem">Salida</span>
                        <?php else: ?>
                        <span class="adm-badge adm-badge-yellow" style="font-size:.6rem;padding:.15rem .4rem">Ajuste</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:.45rem .4rem"><?= $m['cantidad'] ?></td>
                    <td style="padding:.45rem .4rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($m['proveedor'] ?? '—') ?></td>
                    <td style="padding:.45rem .4rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($m['numero_factura'] ?? '—') ?></td>
                    <td style="padding:.45rem .4rem;white-space:nowrap"><?= $m['precio_unitario'] ? '$' . number_format($m['precio_unitario'], 0, ',', '.') : '—' ?></td>
                    <td style="padding:.45rem .4rem;white-space:nowrap"><?= $m['iva'] ? '$' . number_format($m['iva'], 0, ',', '.') : '—' ?></td>
                    <td style="padding:.45rem .4rem;white-space:nowrap"><?= $m['retencion'] ? '$' . number_format($m['retencion'], 0, ',', '.') : '—' ?></td>
                    <td style="padding:.45rem .4rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($m['motivo'] ?? '') ?>"><?= htmlspecialchars($m['motivo'] ?? '—') ?></td>
                    <td style="padding:.45rem .4rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($m['usuario'] ?? '—') ?></td>
                    <td style="padding:.45rem .4rem;color:#555;white-space:nowrap"><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
                    <td style="padding:.45rem .4rem">
                        <?php if (!empty($m['soporte_documental'])): ?>
                        <a href="../<?= htmlspecialchars($m['soporte_documental']) ?>" target="_blank" class="adm-badge adm-badge-blue" style="text-decoration:none;font-size:.6rem;padding:.15rem .35rem">Ver</a>
                        <?php else: ?>
                        <span style="color:#444">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:.45rem .4rem">
                        <button onclick="eliminarMovimiento(<?= $m['id'] ?>)" class="adm-btn adm-btn-danger" style="font-size:.6rem;padding:.2rem .45rem">Eliminar</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="pag-inventario" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:0.75rem;flex-wrap:wrap"></div>

</main>


<!-- Modal Nuevo movimiento -->
<div id="modal-nuevo-bg" class="adm-modal-overlay"></div>
<div id="modal-nuevo-movimiento" class="adm-modal hidden">
    <div class="adm-modal-box">
        <button class="adm-modal-close" onclick="cerrarModal()">&times;</button>
        <div class="adm-modal-title">Nuevo Movimiento</div>
        <form id="form-nuevo-movimiento" method="post" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:0.875rem">
            <div>
                <label class="adm-label">Producto *</label>
                <select name="id_producto" class="adm-select" required>
                    <option value="">Selecciona un producto</option>
                    <?php foreach ($pdo->query('SELECT id, nombre, stock FROM productos ORDER BY nombre') as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?> (Stock: <?= $p['stock'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="adm-label">Tipo *</label>
                <select name="tipo" id="nuevo-tipo" class="adm-select" required onchange="mostrarCamposEntrada()">
                    <option value="">Selecciona tipo</option>
                    <option value="entrada">Entrada (compra)</option>
                    <option value="salida">Salida (venta/ajuste)</option>
                    <option value="ajuste">Ajuste</option>
                </select>
            </div>
            <div id="campos-entrada" style="display:none;display:flex;flex-direction:column;gap:0.875rem">
                <div>
                    <label class="adm-label">Proveedor</label>
                    <select name="id_proveedor" class="adm-select">
                        <option value="">Selecciona un proveedor</option>
                        <?php foreach ($pdo->query('SELECT id, nombre FROM proveedores ORDER BY nombre') as $prov): ?>
                        <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="adm-label">Número de factura/soporte</label><input type="text" name="numero_factura" class="adm-input"></div>
                <div class="adm-form-row" style="margin-bottom:0">
                    <div><label class="adm-label">Precio unitario</label><input type="number" name="precio_unitario" min="0" step="0.01" class="adm-input"></div>
                    <div><label class="adm-label">IVA (valor)</label><input type="number" name="iva" min="0" step="0.01" class="adm-input"></div>
                </div>
                <div class="adm-form-row" style="margin-bottom:0">
                    <div><label class="adm-label">Retención (valor)</label><input type="number" name="retencion" min="0" step="0.01" class="adm-input"></div>
                    <div><label class="adm-label">Soporte (PDF/JPG)</label><input type="file" name="soporte" accept=".pdf,image/*" class="adm-input" style="padding:0.35rem"></div>
                </div>
            </div>
            <div class="adm-form-row" style="margin-bottom:0">
                <div><label class="adm-label">Cantidad *</label><input type="number" name="cantidad" min="1" class="adm-input" required></div>
                <div><label class="adm-label">Motivo</label><input type="text" name="motivo" class="adm-input"></div>
            </div>
            <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;margin-top:0.25rem">Registrar movimiento</button>
        </form>
        <div id="modal-nuevo-msg" style="display:none;margin-top:0.75rem;text-align:center;color:#ef4444;font-size:0.8rem"></div>
    </div>
</div>

<script>
function abrirModal() {
    document.getElementById('modal-nuevo-bg').classList.add('show');
    document.getElementById('modal-nuevo-movimiento').classList.remove('hidden');
    document.getElementById('modal-nuevo-movimiento').classList.add('show');
    document.getElementById('form-nuevo-movimiento').reset();
    document.getElementById('campos-entrada').style.display='none';
    document.body.style.overflow='hidden';
}
function cerrarModal() {
    document.getElementById('modal-nuevo-bg').classList.remove('show');
    document.getElementById('modal-nuevo-movimiento').classList.add('hidden');
    document.getElementById('modal-nuevo-movimiento').classList.remove('show');
    document.body.style.overflow='';
}
function mostrarCamposEntrada() {
    var tipo = document.getElementById('nuevo-tipo').value;
    document.getElementById('campos-entrada').style.display = (tipo === 'entrada') ? 'flex' : 'none';
}
async function eliminarMovimiento(id) {
    // Abrir modal de confirmación
    document.getElementById('del-mov-href').dataset.id = id;
    document.getElementById('del-mov-msg').textContent = 'El movimiento #' + id + ' será eliminado permanentemente.';
    document.getElementById('modal-del-mov-bg').classList.add('show');
    document.getElementById('modal-del-mov').classList.remove('hidden');
    document.getElementById('modal-del-mov').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function cerrarDelMov() {
    document.getElementById('modal-del-mov-bg').classList.remove('show');
    document.getElementById('modal-del-mov').classList.add('hidden');
    document.getElementById('modal-del-mov').classList.remove('show');
    document.body.style.overflow = '';
}
async function confirmarEliminarMov() {
    var id = document.getElementById('del-mov-href').dataset.id;
    cerrarDelMov();
    const res = await fetch('eliminar_movimiento.php', { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: 'id=' + id });
    const text = await res.text();
    if (text === 'success') { window.location.reload(); }
    else {
        // Mostrar toast de error
        var t = document.createElement('div');
        t.id = 'err-toast';
        t.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;align-items:flex-start;gap:12px;background:#1e1e1e;border:1px solid rgba(239,68,68,.35);border-left:4px solid #ef4444;border-radius:12px;padding:1rem;min-width:280px;max-width:360px;box-shadow:0 8px 32px rgba(0,0,0,.55);animation:toastIn .35s cubic-bezier(.21,1.02,.73,1) forwards;overflow:hidden';
        t.innerHTML = '<div style="flex:1"><div style="font-weight:700;font-size:.85rem;color:#fff;margin-bottom:.2rem">Error al eliminar</div><div style="font-size:.78rem;color:#999">' + text + '</div></div><button onclick="this.parentElement.remove()" style="background:none;border:none;color:#555;cursor:pointer;font-size:1.1rem">✕</button>';
        document.body.appendChild(t);
        setTimeout(function(){ if(document.getElementById('err-toast')) document.getElementById('err-toast').remove(); }, 5000);
    }
}
document.getElementById('modal-nuevo-bg').addEventListener('click', cerrarModal);
document.getElementById('modal-del-mov-bg').addEventListener('click', cerrarDelMov);
document.getElementById('btn-nuevo-movimiento').addEventListener('click', abrirModal);
document.getElementById('form-nuevo-movimiento').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = new FormData(this);
    const res = await fetch('inventario_nuevo.php', { method: 'POST', body: data });
    const text = await res.text();
    if (text.includes('Location: inventario.php') || text.includes('registrado')) { window.location.reload(); }
    else {
        const m = document.getElementById('modal-nuevo-msg');
        m.textContent='Error al registrar movimiento. Revisa los datos.'; m.style.display='block';
    }
});
</script>

<!-- Modal Confirmar Eliminar Movimiento -->
<div id="modal-del-mov-bg" class="adm-modal-overlay"></div>
<div id="modal-del-mov" class="adm-modal hidden">
    <div class="adm-modal-box" style="max-width:380px;text-align:center">
        <div style="width:56px;height:56px;background:rgba(239,68,68,.12);border:2px solid rgba(239,68,68,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.1rem">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:26px;height:26px;stroke:#ef4444">
                <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </div>
        <div class="adm-modal-title" style="margin-bottom:.4rem;font-size:1.1rem">¿Eliminar movimiento?</div>
        <p id="del-mov-msg" style="color:#888;font-size:.82rem;line-height:1.5;margin-bottom:1.4rem">Este movimiento será eliminado permanentemente.</p>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="cerrarDelMov()" class="adm-btn" style="flex:1;justify-content:center">Cancelar</button>
            <button type="button" id="del-mov-href" onclick="confirmarEliminarMov()" class="adm-btn adm-btn-danger" style="flex:1;justify-content:center">Sí, eliminar</button>
        </div>
    </div>
</div>

<?php include '_layout_end.php'; ?>
<script>initPagination('#tabla-inventario tbody','pag-inventario',10);</script>
