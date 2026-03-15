<?php
// Sesión manejada por bootstrap (DB handler)
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
    <button id="btn-nuevo-movimiento" class="adm-btn adm-btn-success" onclick="abrirModalMovimiento(event)"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="adm-btn-icon"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Nuevo movimiento</button>';

include '_layout.php';
?>

<main class="admin-content">
<div class="admin-content-inner">
    <!-- Card estática con scroll interno -->
    <div class="adm-card !p-0 overflow-hidden">
        <!-- Header de la card (estático) -->
        <div class="adm-inv-header flex-shrink-0">
            <div class="adm-card-title mb-0">
                <span class="adm-card-title-text">Movimientos de Inventario</span>
                <span class="adm-badge adm-badge-gray"><?= count($movimientos) ?> registros</span>
            </div>
        </div>
        <!-- Zona scrolleable (con scroll horizontal en móvil) -->
        <div class="adm-table-wrap !border-none !rounded-none overflow-x-auto min-w-0">
            <table class="adm-table min-w-[1200px]" id="tabla-inventario">
                <thead class="adm-inv-sticky-thead">
                    <tr>
                        <th class="adm-inv-th-padding min-w-[140px]">Producto</th><th class="adm-inv-th-padding min-w-[70px]">Tipo</th><th class="adm-inv-th-padding min-w-[60px]">Cant.</th><th class="adm-inv-th-padding min-w-[120px]">Proveedor</th>
                        <th class="adm-inv-th-padding min-w-[90px]">Factura</th><th class="adm-inv-th-padding min-w-[100px]">Precio U.</th><th class="adm-inv-th-padding min-w-[90px]">IVA</th><th class="adm-inv-th-padding min-w-[90px]">Ret.</th>
                        <th class="adm-inv-th-padding min-w-[160px]">Motivo</th><th class="adm-inv-th-padding min-w-[100px]">Usuario</th><th class="adm-inv-th-padding min-w-[120px]">Fecha</th><th class="adm-inv-th-padding min-w-[60px]">Sop.</th><th class="adm-inv-th-padding min-w-[80px]">Acc.</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($movimientos)): ?>
                <tr><td colspan="13" class="text-center p-10 text-[#444]">No hay movimientos de inventario</td></tr>
                <?php else: ?>
                <?php foreach ($movimientos as $m): ?>
                <tr>
                    <td class="adm-inv-row-padding"><strong><?= htmlspecialchars($m['producto']) ?></strong></td>
                    <td class="adm-inv-row-padding">
                        <?php if ($m['tipo'] === 'entrada'): ?>
                        <span class="adm-badge adm-badge-green adm-inv-badge-mini">Entrada</span>
                        <?php elseif ($m['tipo'] === 'salida'): ?>
                        <span class="adm-badge adm-badge-red adm-inv-badge-mini">Salida</span>
                        <?php else: ?>
                        <span class="adm-badge adm-badge-yellow adm-inv-badge-mini">Ajuste</span>
                        <?php endif; ?>
                    </td>
                    <td class="adm-inv-row-padding"><?= $m['cantidad'] ?></td>
                    <td class="adm-inv-row-padding whitespace-nowrap"><?= htmlspecialchars($m['proveedor'] ?? '—') ?></td>
                    <td class="adm-inv-row-padding whitespace-nowrap"><?= htmlspecialchars($m['numero_factura'] ?? '—') ?></td>
                    <td class="adm-inv-row-padding whitespace-nowrap"><?= $m['precio_unitario'] ? '$' . number_format($m['precio_unitario'], 0, ',', '.') : '—' ?></td>
                    <td class="adm-inv-row-padding whitespace-nowrap"><?= $m['iva'] ? '$' . number_format($m['iva'], 0, ',', '.') : '—' ?></td>
                    <td class="adm-inv-row-padding whitespace-nowrap"><?= $m['retencion'] ? '$' . number_format($m['retencion'], 0, ',', '.') : '—' ?></td>
                    <td class="adm-inv-row-padding max-w-[180px] overflow-hidden text-ellipsis whitespace-nowrap" title="<?= htmlspecialchars($m['motivo'] ?? '') ?>"><?= htmlspecialchars($m['motivo'] ?? '—') ?></td>
                    <td class="adm-inv-row-padding whitespace-nowrap"><?= htmlspecialchars($m['usuario'] ?? '—') ?></td>
                    <td class="adm-inv-row-padding adm-inv-date whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
                    <td class="adm-inv-row-padding">
                        <?php if (!empty($m['soporte_documental'])): ?>
                        <a href="../<?= htmlspecialchars($m['soporte_documental']) ?>" target="_blank" class="adm-badge adm-badge-blue !no-underline adm-inv-badge-mini">Ver</a>
                        <?php else: ?>
                        <span class="text-[#444]">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="adm-inv-row-padding">
                        <button onclick="eliminarMovimiento(<?= $m['id'] ?>)" class="adm-btn adm-btn-danger adm-inv-badge-mini !py-[0.3rem] !px-[0.5rem]">Eliminar</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="pag-inventario" class="flex items-center justify-center gap-2 my-4 flex-wrap"></div>
    </div>
</div>
</main>


<?php include '_modal_movimiento.php'; ?>

<script>
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
    if (text === 'success') { window.location.href = window.location.pathname + '?exito=1'; }
    else {
        // Mostrar toast de error
        const t = document.createElement('div');
        t.id = 'err-toast';
        t.className = 'fixed bottom-6 right-6 z-[9999] flex items-start gap-3 bg-[#1e1e1e] border border-red-500/35 border-l-4 border-l-red-500 rounded-xl p-4 min-w-[280px] max-w-[360px] shadow-2xl animate-[toastIn_0.35s_ease-out_forwards] overflow-hidden';
        t.innerHTML = `
            <div class="flex-1">
                <div class="font-bold text-[0.85rem] text-white mb-0.5">Error al eliminar</div>
                <div class="text-[0.78rem] text-[#999]">${text}</div>
            </div>
            <button onclick="this.parentElement.remove()" class="bg-none border-none text-[#555] cursor-pointer text-[1.1rem]">✕</button>
        `;
        document.body.appendChild(t);
        setTimeout(() => { if(document.getElementById('err-toast')) document.getElementById('err-toast').remove(); }, 5000);
    }
}
document.getElementById('modal-del-mov-bg').addEventListener('click', cerrarDelMov);
</script>

<!-- Modal Confirmar Eliminar Movimiento -->
<div id="modal-del-mov-bg" class="adm-modal-overlay"></div>
<div id="modal-del-mov" class="adm-modal hidden">
    <div class="adm-modal-box !max-w-[380px] text-center">
        <div class="w-14 h-14 bg-[#ef4444]/15 border-2 border-[#ef4444]/30 rounded-full flex items-center justify-center mx-auto mb-[1.1rem]">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-[26px] h-[26px] stroke-[#ef4444]">
                <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </div>
        <div class="adm-modal-title mb-1.5 !text-[1.1rem]">¿Eliminar movimiento?</div>
        <p id="del-mov-msg" class="text-[#888] text-[0.82rem] leading-relaxed mb-[1.4rem]">Este movimiento será eliminado permanentemente.</p>
        <div class="flex gap-[10px]">
            <button type="button" onclick="cerrarDelMov()" class="adm-btn flex-1 justify-center">Cancelar</button>
            <button type="button" id="del-mov-href" onclick="confirmarEliminarMov()" class="adm-btn adm-btn-danger flex-1 justify-center">Sí, eliminar</button>
        </div>
    </div>
</div>

<?php include '_layout_end.php'; ?>
<script>initPagination('#tabla-inventario tbody','pag-inventario',10);</script>
