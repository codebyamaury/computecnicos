<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
$usuario = $_SESSION['usuario'];

// Ensure table exists
try {
    $pdo->query("SELECT 1 FROM reembolsos LIMIT 1");
} catch (Exception $e) {
    // Table doesn't exist yet, create it
    $pdo->exec("CREATE TABLE IF NOT EXISTS reembolsos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_pedido INT NOT NULL,
        id_usuario INT NOT NULL,
        motivo VARCHAR(500) NOT NULL,
        monto DECIMAL(12, 2) NOT NULL,
        estado ENUM('solicitado', 'aprobado', 'procesado', 'rechazado') DEFAULT 'solicitado',
        paypal_refund_id VARCHAR(128) NULL,
        paypal_capture_id VARCHAR(128) NULL,
        nota_admin VARCHAR(500) NULL,
        fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_resolucion DATETIME NULL,
        id_admin_resolucion INT NULL,
        stock_devuelto TINYINT(1) DEFAULT 0,
        INDEX idx_pedido (id_pedido),
        INDEX idx_estado (estado),
        INDEX idx_usuario (id_usuario)
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci");
}

// Filter
$estado_filtro = isset($_GET['estado']) ? strtolower(trim($_GET['estado'])) : 'todos';
$valid_estados = ['solicitado', 'aprobado', 'procesado', 'rechazado'];

if (in_array($estado_filtro, $valid_estados)) {
    $stmt = $pdo->prepare('SELECT r.*, u.nombre AS cliente_nombre, u.email AS cliente_email, p.total AS pedido_total, p.estado AS pedido_estado, p.fecha AS pedido_fecha, a.nombre AS admin_nombre FROM reembolsos r LEFT JOIN usuarios u ON r.id_usuario = u.id LEFT JOIN pedidos p ON r.id_pedido = p.id LEFT JOIN usuarios a ON r.id_admin_resolucion = a.id WHERE r.estado = ? ORDER BY r.fecha_solicitud DESC');
    $stmt->execute([$estado_filtro]);
} else {
    $stmt = $pdo->query('SELECT r.*, u.nombre AS cliente_nombre, u.email AS cliente_email, p.total AS pedido_total, p.estado AS pedido_estado, p.fecha AS pedido_fecha, a.nombre AS admin_nombre FROM reembolsos r LEFT JOIN usuarios u ON r.id_usuario = u.id LEFT JOIN pedidos p ON r.id_pedido = p.id LEFT JOIN usuarios a ON r.id_admin_resolucion = a.id ORDER BY r.fecha_solicitud DESC');
}
$reembolsos = $stmt->fetchAll();

// Count by state
$counts = $pdo->query("SELECT estado, COUNT(*) as total FROM reembolsos GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
$count_solicitado = $counts['solicitado'] ?? 0;
$count_aprobado = $counts['aprobado'] ?? 0;
$count_procesado = $counts['procesado'] ?? 0;
$count_rechazado = $counts['rechazado'] ?? 0;
$count_total = array_sum($counts ?: []);

// Get order details for each refund
$detalles = [];
if ($reembolsos) {
    $pedido_ids = array_unique(array_column($reembolsos, 'id_pedido'));
    if ($pedido_ids) {
        $in = implode(',', array_fill(0, count($pedido_ids), '?'));
        $stmtDet = $pdo->prepare("SELECT d.*, pr.nombre, pr.imagen FROM detalle_pedido d JOIN productos pr ON d.id_producto = pr.id WHERE d.id_pedido IN ($in)");
        $stmtDet->execute(array_values($pedido_ids));
        foreach ($stmtDet->fetchAll() as $row) {
            $detalles[$row['id_pedido']][] = $row;
        }
    }
}

function estadoReembolsoBadge($estado) {
    $map = [
        'solicitado' => 'adm-badge-yellow',
        'aprobado'   => 'adm-badge-blue',
        'procesado'  => 'adm-badge-green',
        'rechazado'  => 'adm-badge-red',
    ];
    return $map[$estado] ?? 'adm-badge-gray';
}

function estadoReembolsoLabel($estado) {
    $map = [
        'solicitado' => 'Solicitado',
        'aprobado'   => 'Aprobado',
        'procesado'  => 'Procesado',
        'rechazado'  => 'Rechazado',
    ];
    return $map[$estado] ?? ucfirst($estado);
}

$page_title = 'Reembolsos | Computécnicos';
$admin_page = 'reembolsos';
$admin_title = 'Reembolsos';
$admin_breadcrumb = [['label' => 'Reembolsos']];

$admin_extra_css = '
<style>
/* Premium refund filters */
.adm-filters-container { display:flex!important; justify-content:center!important; width:100%!important; margin-bottom:2rem!important; }
.adm-reembolso-filters { display:flex!important; justify-content:center!important; align-items:center!important; flex-wrap:wrap!important; gap:12px!important; padding:12px 20px!important; background:rgba(255,255,255,0.03)!important; border-radius:50px!important; border:1px solid rgba(255,255,255,0.08)!important; backdrop-filter:blur(15px)!important; width:fit-content!important; box-shadow:0 10px 40px rgba(0,0,0,0.4),inset 0 1px 1px rgba(255,255,255,0.05)!important; }
.adm-reembolso-filters .adm-btn { padding:0.65rem 1.4rem!important; border-radius:30px!important; font-size:0.8rem!important; font-weight:600!important; display:flex!important; align-items:center!important; gap:8px!important; text-decoration:none!important; transition:all 0.3s cubic-bezier(0.4,0,0.2,1)!important; background:rgba(255,255,255,0.05)!important; border:1px solid rgba(255,255,255,0.06)!important; color:#cbd5e1!important; }
.adm-reembolso-filters .adm-btn:hover { background:rgba(255,255,255,0.1)!important; color:#fff!important; transform:translateY(-2px)!important; }
.adm-reembolso-filters .adm-btn.active { color:#fff!important; border-color:transparent!important; }
.adm-btn-r-todos.active,.adm-btn-r-todos:hover { background:linear-gradient(135deg,#e11d48,#be123c)!important; box-shadow:0 0 20px rgba(225,29,72,0.4)!important; }
.adm-btn-r-solicitado.active,.adm-btn-r-solicitado:hover { background:rgba(251,191,36,0.25)!important; color:#fbbf24!important; border-color:rgba(251,191,36,0.5)!important; }
.adm-btn-r-aprobado.active,.adm-btn-r-aprobado:hover { background:rgba(59,130,246,0.25)!important; color:#60a5fa!important; border-color:rgba(59,130,246,0.5)!important; }
.adm-btn-r-procesado.active,.adm-btn-r-procesado:hover { background:rgba(34,197,94,0.25)!important; color:#4ade80!important; border-color:rgba(34,197,94,0.5)!important; }
.adm-btn-r-rechazado.active,.adm-btn-r-rechazado:hover { background:rgba(239,68,68,0.25)!important; color:#f87171!important; border-color:rgba(239,68,68,0.5)!important; }

/* Refund Cards */
.reembolso-card { background:rgba(15,15,15,0.6); border:1px solid rgba(255,255,255,0.06); border-radius:16px; padding:1.5rem; margin-bottom:1rem; backdrop-filter:blur(10px); transition:all 0.3s ease; }
.reembolso-card:hover { border-color:rgba(255,255,255,0.12); transform:translateY(-2px); box-shadow:0 8px 32px rgba(0,0,0,0.3); }
.reembolso-header { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1rem; }
.reembolso-meta { display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap; }
.reembolso-id { font-weight:700; font-size:1rem; color:#fff; }
.reembolso-date { font-size:0.78rem; color:#666; }
.reembolso-monto { font-size:1.25rem; font-weight:800; color:#ef4444; font-family:"Inter",sans-serif; }
.reembolso-info { display:flex; flex-direction:column; gap:0.4rem; font-size:0.82rem; color:#888; margin-bottom:1rem; padding:0.75rem 1rem; background:rgba(255,255,255,0.02); border-radius:10px; border:1px solid rgba(255,255,255,0.04); }
.reembolso-info span { display:flex; align-items:center; gap:6px; }
.reembolso-motivo { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:0.75rem 1rem; margin-bottom:1rem; }
.reembolso-motivo-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:1px; color:#666; margin-bottom:0.25rem; font-weight:600; }
.reembolso-motivo-text { font-size:0.85rem; color:#ccc; line-height:1.5; }
.reembolso-nota { background:rgba(59,130,246,0.05); border:1px solid rgba(59,130,246,0.15); border-radius:10px; padding:0.75rem 1rem; margin-bottom:1rem; }
.reembolso-nota-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:1px; color:#60a5fa; margin-bottom:0.25rem; font-weight:600; }
.reembolso-nota-text { font-size:0.85rem; color:#93c5fd; line-height:1.5; }
.reembolso-actions { display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center; }
.reembolso-actions .adm-btn { font-size:0.75rem; padding:0.4rem 0.85rem; border-radius:8px; }

/* Stats cards */
.reembolso-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-bottom:2rem; }
.stat-card { background:rgba(15,15,15,0.6); border:1px solid rgba(255,255,255,0.06); border-radius:14px; padding:1.25rem; text-align:center; backdrop-filter:blur(10px); }
.stat-card-number { font-size:2rem; font-weight:800; font-family:"Inter",sans-serif; }
.stat-card-label { font-size:0.75rem; color:#888; text-transform:uppercase; letter-spacing:1px; margin-top:0.25rem; }
.stat-yellow .stat-card-number { color:#fbbf24; }
.stat-blue .stat-card-number { color:#60a5fa; }
.stat-green .stat-card-number { color:#4ade80; }
.stat-red .stat-card-number { color:#f87171; }

/* Modal */
.reembolso-modal-textarea { width:100%; min-height:80px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:0.75rem; color:#fff; font-size:0.85rem; resize:vertical; font-family:inherit; }
.reembolso-modal-textarea:focus { outline:none; border-color:rgba(239,68,68,0.5); }
</style>
';

include '_layout.php';
?>

<main class="admin-content">
<div class="admin-content-inner">

    <!-- Stats -->
    <div class="reembolso-stats">
        <div class="stat-card stat-yellow">
            <div class="stat-card-number"><?= $count_solicitado ?></div>
            <div class="stat-card-label">Pendientes</div>
        </div>
        <div class="stat-card stat-blue">
            <div class="stat-card-number"><?= $count_aprobado ?></div>
            <div class="stat-card-label">Aprobados</div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-card-number"><?= $count_procesado ?></div>
            <div class="stat-card-label">Procesados</div>
        </div>
        <div class="stat-card stat-red">
            <div class="stat-card-number"><?= $count_rechazado ?></div>
            <div class="stat-card-label">Rechazados</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="adm-filters-container">
        <div class="adm-reembolso-filters">
            <?php
            $filtros_ui = [
                'todos'       => ['label' => 'Todos ('.$count_total.')',         'icon' => 'layers',       'class' => 'adm-btn-r-todos'],
                'solicitado'  => ['label' => 'Pendientes ('.$count_solicitado.')', 'icon' => 'clock',        'class' => 'adm-btn-r-solicitado'],
                'aprobado'    => ['label' => 'Aprobados ('.$count_aprobado.')',    'icon' => 'check-circle', 'class' => 'adm-btn-r-aprobado'],
                'procesado'   => ['label' => 'Procesados ('.$count_procesado.')', 'icon' => 'banknote',     'class' => 'adm-btn-r-procesado'],
                'rechazado'   => ['label' => 'Rechazados ('.$count_rechazado.')', 'icon' => 'x-circle',     'class' => 'adm-btn-r-rechazado'],
            ];
            foreach ($filtros_ui as $estado => $data):
                $active = ($estado_filtro === $estado);
            ?>
            <a href="reembolsos.php<?= $estado === 'todos' ? '' : ('?estado='.$estado) ?>"
               class="adm-btn <?= $data['class'] ?> <?= $active ? 'active' : '' ?>">
                <i data-lucide="<?= $data['icon'] ?>" style="width:16px;height:16px"></i>
                <span><?= $data['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Refund List -->
    <?php if (!$reembolsos): ?>
    <div class="adm-card" style="text-align:center;color:#555;padding:3rem">
        <i data-lucide="inbox" style="width:48px;height:48px;margin:0 auto 1rem;opacity:0.3"></i>
        <p>No hay reembolsos <?= $estado_filtro !== 'todos' ? 'en estado "'.estadoReembolsoLabel($estado_filtro).'"' : 'registrados' ?>.</p>
    </div>
    <?php else: ?>
    <div id="lista-reembolsos">
        <?php foreach ($reembolsos as $r): ?>
        <div class="reembolso-card">
            <div class="reembolso-header">
                <div class="reembolso-meta">
                    <span class="reembolso-id">Reembolso #<?= $r['id'] ?></span>
                    <span class="adm-badge <?= estadoReembolsoBadge($r['estado']) ?>"><?= estadoReembolsoLabel($r['estado']) ?></span>
                    <span class="reembolso-date"><?= date('d/m/Y H:i', strtotime($r['fecha_solicitud'])) ?></span>
                </div>
                <div class="reembolso-monto">$<?= number_format($r['monto'], 0, ',', '.') ?> COP</div>
            </div>

            <div class="reembolso-info">
                <span>👤 <?= htmlspecialchars($r['cliente_nombre'] ?? 'Sin nombre') ?> — <?= htmlspecialchars($r['cliente_email'] ?? '') ?></span>
                <span>📦 Pedido #<?= $r['id_pedido'] ?> — <?= ucfirst($r['pedido_estado'] ?? 'N/A') ?> — <?= date('d/m/Y', strtotime($r['pedido_fecha'] ?? 'now')) ?></span>
                <?php if ($r['fecha_resolucion']): ?>
                <span>📅 Resuelto: <?= date('d/m/Y H:i', strtotime($r['fecha_resolucion'])) ?> <?= $r['admin_nombre'] ? 'por '.$r['admin_nombre'] : '' ?></span>
                <?php endif; ?>
                <?php if ($r['paypal_refund_id']): ?>
                <span>💳 PayPal Ref: <?= htmlspecialchars($r['paypal_refund_id']) ?></span>
                <?php endif; ?>
                <?php if ($r['stock_devuelto']): ?>
                <span>📦 Stock restaurado ✓</span>
                <?php endif; ?>
            </div>

            <!-- Order items -->
            <?php if (!empty($detalles[$r['id_pedido']])): ?>
            <div class="adm-table-wrap" style="margin-bottom:0.875rem">
                <table class="adm-table" style="font-size:0.78rem">
                    <thead><tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($detalles[$r['id_pedido']] as $det): ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <?php if (!empty($det['imagen'])): ?>
                                <img src="<?= htmlspecialchars($det['imagen']) ?>" alt="" style="width:36px;height:28px;object-fit:cover;border-radius:4px;opacity:.85">
                                <?php endif; ?>
                                <?= htmlspecialchars($det['nombre']) ?>
                            </div>
                        </td>
                        <td><?= $det['cantidad'] ?></td>
                        <td>$<?= number_format($det['precio_unitario'], 0, ',', '.') ?></td>
                        <td style="font-weight:600">$<?= number_format($det['precio_unitario'] * $det['cantidad'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Motivo -->
            <div class="reembolso-motivo">
                <div class="reembolso-motivo-label">Motivo del cliente</div>
                <div class="reembolso-motivo-text"><?= htmlspecialchars($r['motivo']) ?></div>
            </div>

            <!-- Nota admin -->
            <?php if ($r['nota_admin']): ?>
            <div class="reembolso-nota">
                <div class="reembolso-nota-label">Nota del administrador</div>
                <div class="reembolso-nota-text"><?= htmlspecialchars($r['nota_admin']) ?></div>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="reembolso-actions">
                <?php if ($r['estado'] === 'solicitado'): ?>
                    <button type="button" class="adm-btn adm-btn-blue" onclick="abrirModalConfirmacion(<?= $r['id'] ?>, 'aprobar', '¿Aprobar este reembolso?', 'El reembolso pasará a estado Aprobado y estará listo para ejecutarse.', 'blue')">
                        <i data-lucide="check" style="width:14px;height:14px"></i> Aprobar
                    </button>
                    <button type="button" class="adm-btn adm-btn-danger" onclick="abrirModalRechazo(<?= $r['id'] ?>)">
                        <i data-lucide="x" style="width:14px;height:14px"></i> Rechazar
                    </button>
                <?php endif; ?>
                <?php if ($r['estado'] === 'aprobado'): ?>
                    <button type="button" class="adm-btn adm-btn-primary" onclick="abrirModalConfirmacion(<?= $r['id'] ?>, 'procesar_paypal', '¿Ejecutar el desembolso?', '<?= $r['paypal_capture_id'] ? 'Se enviará el dinero de vuelta al cliente vía PayPal automáticamente y se restaurará el inventario.' : 'Modo Manual: Asegúrate de haber devuelto el dinero por tu cuenta (sin PayPal ID).' ?>')">
                        <i data-lucide="banknote" style="width:14px;height:14px"></i> Ejecutar Desembolso
                    </button>
                    <button type="button" class="adm-btn adm-btn-danger" onclick="abrirModalRechazo(<?= $r['id'] ?>)">
                        <i data-lucide="x" style="width:14px;height:14px"></i> Rechazar
                    </button>
                <?php endif; ?>
                <?php if ($r['estado'] === 'procesado'): ?>
                    <span style="color:#4ade80;font-size:0.8rem;font-weight:600">✓ Desembolso completado</span>
                <?php endif; ?>
                <?php if ($r['estado'] === 'rechazado'): ?>
                    <span style="color:#f87171;font-size:0.8rem;font-weight:600">✕ Rechazado</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
</main>

<!-- Modal Rechazo -->
<div id="modal-rechazo-bg" class="adm-modal-overlay"></div>
<div id="modal-rechazo" class="adm-modal hidden">
    <div class="adm-modal-box" style="max-width:450px">
        <button class="adm-modal-close" onclick="cerrarRechazo()">&times;</button>
        <div class="adm-modal-title">Rechazar Reembolso</div>
        <p style="color:#888;font-size:0.85rem;margin-bottom:1rem">Explica al cliente el motivo del rechazo:</p>
        <textarea id="rechazo-nota" class="reembolso-modal-textarea" placeholder="Motivo del rechazo..." minlength="5" maxlength="500"></textarea>
        <div style="display:flex;gap:10px;margin-top:1rem">
            <button type="button" onclick="cerrarRechazo()" class="adm-btn" style="flex:1;justify-content:center">Cancelar</button>
            <button type="button" id="btn-confirmar-rechazo" class="adm-btn adm-btn-danger" style="flex:1;justify-content:center">Confirmar Rechazo</button>
        </div>
    </div>
</div>

<!-- Modal Confirmación Genérico -->
<div id="modal-confirm-bg" class="adm-modal-overlay"></div>
<div id="modal-confirm" class="adm-modal hidden">
    <div class="adm-modal-box" style="max-width:450px">
        <button class="adm-modal-close" onclick="cerrarConfirmacion()">&times;</button>
        <div class="adm-modal-title" id="confirm-title" style="margin-bottom:0.5rem">¿Estás seguro?</div>
        <p id="confirm-desc" style="color:#888;font-size:0.85rem;margin-bottom:1.5rem;line-height:1.5">Esta acción no se puede deshacer.</p>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="cerrarConfirmacion()" class="adm-btn" style="flex:1;justify-content:center">Cancelar</button>
            <button type="button" id="btn-confirmar-accion" class="adm-btn adm-btn-primary" style="flex:1;justify-content:center">Confirmar</button>
        </div>
    </div>
</div>

<script>
var rechazoId = null;
var confirmAccionData = null;

function abrirModalConfirmacion(id, accion, title, desc, actionColor = 'primary') {
    confirmAccionData = { id: id, accion: accion };
    document.getElementById('confirm-title').innerText = title;
    document.getElementById('confirm-desc').innerText = desc;
    
    var btn = document.getElementById('btn-confirmar-accion');
    btn.className = 'adm-btn adm-btn-' + actionColor;
    
    document.getElementById('modal-confirm-bg').classList.add('show');
    document.getElementById('modal-confirm').classList.remove('hidden');
    document.getElementById('modal-confirm').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function cerrarConfirmacion() {
    document.getElementById('modal-confirm-bg').classList.remove('show');
    document.getElementById('modal-confirm').classList.add('hidden');
    document.getElementById('modal-confirm').classList.remove('show');
    document.body.style.overflow = '';
    confirmAccionData = null;
}

document.getElementById('btn-confirmar-accion').addEventListener('click', function() {
    if (!confirmAccionData) return;
    cerrarConfirmacion();
    accionReembolso(confirmAccionData.id, confirmAccionData.accion);
});

function accionReembolso(id, accion) {

    var fd = new FormData();
    fd.append('id_reembolso', id);
    fd.append('accion', accion);
    fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

    fetch('../api/procesar_reembolso.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                admToast(data.msg, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                admToast(data.msg || 'Error al procesar', 'error');
            }
        })
        .catch(() => admToast('Error de conexión', 'error'));
}

function abrirModalRechazo(id) {
    rechazoId = id;
    document.getElementById('rechazo-nota').value = '';
    document.getElementById('modal-rechazo-bg').classList.add('show');
    document.getElementById('modal-rechazo').classList.remove('hidden');
    document.getElementById('modal-rechazo').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function cerrarRechazo() {
    document.getElementById('modal-rechazo-bg').classList.remove('show');
    document.getElementById('modal-rechazo').classList.add('hidden');
    document.getElementById('modal-rechazo').classList.remove('show');
    document.body.style.overflow = '';
    rechazoId = null;
}

document.getElementById('btn-confirmar-rechazo').addEventListener('click', function() {
    var nota = document.getElementById('rechazo-nota').value.trim();
    if (nota.length < 5) {
        admToast('Escribe un motivo de al menos 5 caracteres', 'error');
        return;
    }

    var fd = new FormData();
    fd.append('id_reembolso', rechazoId);
    fd.append('accion', 'rechazar');
    fd.append('nota_admin', nota);
    fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

    fetch('../api/procesar_reembolso.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                cerrarRechazo();
                admToast(data.msg, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                admToast(data.msg || 'Error', 'error');
            }
        })
        .catch(() => admToast('Error de conexión', 'error'));
});

document.getElementById('modal-rechazo-bg').addEventListener('click', cerrarRechazo);
document.getElementById('modal-confirm-bg').addEventListener('click', cerrarConfirmacion);
</script>

<?php include '_layout_end.php'; ?>
