<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
$usuario = $_SESSION['usuario'];

// Verificar si columna numero_guia existe
$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pedidos' AND COLUMN_NAME = 'numero_guia'");
$stmt->execute();
$col_guia_disponible = (int)$stmt->fetchColumn() > 0;

// Cambiar estado del pedido
if (isset($_POST['cambiar_estado'], $_POST['id_pedido'], $_POST['nuevo_estado'])) {
    $id_pedido    = intval($_POST['id_pedido']);
    $nuevo_estado = $_POST['nuevo_estado'];
    $stmt = $pdo->prepare('SELECT estado FROM pedidos WHERE id = ?');
    $stmt->execute([$id_pedido]);
    $anterior = $stmt->fetchColumn();
    // En panel admin permitimos cualquier transición para máxima flexibilidad (corrección de errores, devoluciones, etc).
    // La lógica de inventario abajo se encargará siempre de cuadrar el stock según el grupo de estado.
    $estados_reservados = ['pagado', 'preparacion', 'enviado', 'entregado'];
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE pedidos SET estado = ? WHERE id = ?')->execute([$nuevo_estado, $id_pedido]);
        $pdo->prepare('INSERT INTO pedido_estados (id_pedido, estado, comentario) VALUES (?, ?, ?)')->execute([$id_pedido, $nuevo_estado, 'Cambio realizado desde Admin']);
        $stmt = $pdo->prepare('SELECT * FROM detalle_pedido WHERE id_pedido = ?');
        $stmt->execute([$id_pedido]);
        $detalles_upd = $stmt->fetchAll();
        $id_usuario   = $_SESSION['usuario']['id'];
        if ($nuevo_estado !== $anterior) {
            if (in_array($nuevo_estado, $estados_reservados) && !in_array($anterior, $estados_reservados)) {
                // Pasó de no-reservado a reservado -> Restar stock
                foreach ($detalles_upd as $d) {
                    $pdo->prepare("INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, motivo, id_usuario) VALUES (?, 'salida', ?, ?, ?)")
                        ->execute([$d['id_producto'], $d['cantidad'], 'Reserva Pedido #'.$id_pedido, $id_usuario]);
                    $pdo->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?')
                        ->execute([$d['cantidad'], $d['id_producto']]);
                }
            } elseif (!in_array($nuevo_estado, $estados_reservados) && in_array($anterior, $estados_reservados)) {
                // Pasó de reservado a no-reservado (ej. cancelado, o devuelto a pendiente) -> Devolver stock
                foreach ($detalles_upd as $d) {
                    $pdo->prepare("INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, motivo, id_usuario) VALUES (?, 'entrada', ?, ?, ?)")
                        ->execute([$d['id_producto'], $d['cantidad'], 'Liberación Pedido #'.$id_pedido, $id_usuario]);
                    $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?')
                        ->execute([$d['cantidad'], $d['id_producto']]);
                }
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die('Error al actualizar el estado del pedido: ' . $e->getMessage());
    }
}

// Filtro estado
$estado_filtro = isset($_GET['estado']) ? strtolower(trim($_GET['estado'])) : 'todos';
$valid_estados = ['pendiente','pagado','preparacion','enviado','entregado','cancelado'];
if (in_array($estado_filtro, $valid_estados)) {
    $stmt = $pdo->prepare('SELECT p.*, u.nombre AS usuario_nombre, u.email FROM pedidos p LEFT JOIN usuarios u ON p.id_usuario=u.id WHERE p.estado=? ORDER BY p.fecha DESC');
    $stmt->execute([$estado_filtro]);
    $pedidos = $stmt->fetchAll();
} else {
    $pedidos = $pdo->query('SELECT p.*, u.nombre AS usuario_nombre, u.email FROM pedidos p LEFT JOIN usuarios u ON p.id_usuario=u.id ORDER BY p.fecha DESC')->fetchAll();
}

// Detalles de todos los pedidos
$detalles = [];
if ($pedidos) {
    $ids = array_column($pedidos, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT d.*, pr.nombre, pr.imagen FROM detalle_pedido d JOIN productos pr ON d.id_producto=pr.id WHERE d.id_pedido IN ($in)");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) { $detalles[$row['id_pedido']][] = $row; }
}

// Helpers de color
function estadoBadge($estado) {
    $map = [
        'pendiente'   => 'adm-badge-yellow',
        'pagado'      => 'adm-badge-blue',
        'preparacion' => 'adm-badge-purple',
        'enviado'     => 'adm-badge-blue',
        'entregado'   => 'adm-badge-green',
        'cancelado'   => 'adm-badge-red',
    ];
    return $map[$estado] ?? 'adm-badge-gray';
}

$page_title       = 'Pedidos | Computécnicos';
$admin_page       = 'pedidos';
$admin_title      = 'Pedidos';
$admin_breadcrumb = [['label' => 'Pedidos']];
$admin_header_extra = '<button id="btn-nuevo-pedido" class="adm-btn adm-btn-success"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Nuevo pedido</button>';

include '_layout.php';
?>

<main class="admin-content">
<div class="admin-content-inner">

    <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="adm-alert adm-alert-error"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <!-- Filtros de estado -->
    <div class="adm-pedido-filters">
        <?php
        $estados_ui = ['todos'=>'Todos','pendiente'=>'Pendientes','pagado'=>'Pagados','preparacion'=>'En preparación','enviado'=>'Enviados','entregado'=>'Entregados','cancelado'=>'Cancelados'];
        foreach ($estados_ui as $estado => $label):
            $active = ($estado_filtro === $estado);
        ?>
        <a href="pedidos.php<?= $estado === 'todos' ? '' : ('?estado='.$estado) ?>"
           class="adm-btn <?= $active ? 'adm-btn-primary' : 'bg-white/5 text-[#aaa] border border-white/10' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$pedidos): ?>
    <div class="adm-card text-center text-[#555] py-12">No hay pedidos registrados.</div>
    <?php else: ?>
    <div id="lista-pedidos" class="flex flex-col gap-[1.25rem]">
        <?php foreach ($pedidos as $pedido): ?>
        <div class="adm-pedido-card">
            <!-- Cabecera del pedido -->
            <div class="adm-pedido-header">
                <div class="adm-pedido-meta">
                    <span class="adm-pedido-id">Pedido #<?= $pedido['id'] ?></span>
                    <span class="adm-badge <?= estadoBadge($pedido['estado']) ?>"><?= ucfirst($pedido['estado']) ?></span>
                    <span class="adm-pedido-date"><?= date('d/m/Y H:i', strtotime($pedido['fecha'])) ?></span>
                </div>
                <div class="adm-pedido-total">$<?= number_format($pedido['total'], 0, ',', '.') ?> COP</div>
            </div>

            <!-- Info cliente -->
            <div class="adm-pedido-info">
                <span>👤 <?= htmlspecialchars($pedido['usuario_nombre'] ?: 'Invitado') ?> &mdash; <?= htmlspecialchars($pedido['email'] ?? '') ?></span>
                <span>📍 <?= htmlspecialchars($pedido['direccion_envio']) ?></span>
                <?php if ($col_guia_disponible && !empty($pedido['numero_guia'])): ?>
                <span>🚚 Guía: <strong><?= htmlspecialchars($pedido['numero_guia']) ?></strong></span>
                <?php endif; ?>
            </div>

            <!-- Items del pedido -->
            <?php if (!empty($detalles[$pedido['id']])): ?>
            <div class="adm-table-wrap !mb-[0.875rem]">
                <table class="adm-table !text-[0.78rem]">
                    <thead>
                        <tr><th>Producto</th><th>Cant.</th><th>Precio Unit.</th><th>Dcto.</th><th>IVA</th><th>Subtotal</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($detalles[$pedido['id']] as $det):
                        $base     = $det['precio_unitario'] * $det['cantidad'];
                        $dcto     = $base * (($det['descuento'] ?? 0) / 100);
                        $neto     = $base - $dcto;
                        $iva      = $neto * 0.19;
                    ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-2">
                                <?php if (!empty($det['imagen'])): ?>
                                <img src="<?= htmlspecialchars($det['imagen']) ?>" alt="" class="w-9 h-7 object-cover rounded opacity-85">
                                <?php endif; ?>
                                <?= htmlspecialchars($det['nombre']) ?>
                            </div>
                        </td>
                        <td><?= $det['cantidad'] ?></td>
                        <td>$<?= number_format($det['precio_unitario'], 0, ',', '.') ?></td>
                        <td><?= isset($det['descuento']) ? $det['descuento'] : 0 ?>%</td>
                        <td>$<?= number_format($iva, 0, ',', '.') ?></td>
                        <td class="font-semibold">$<?= number_format($neto, 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Acciones -->
            <div class="adm-pedido-actions">
                <!-- Cambiar estado -->
                <form method="post" class="adm-pedido-status-form">
                    <input type="hidden" name="id_pedido" value="<?= $pedido['id'] ?>">
                    <label class="adm-pedido-status-label">Estado:</label>
                    <input type="hidden" name="cambiar_estado" value="1">
                    <select name="nuevo_estado" class="adm-select w-auto py-[0.3rem] px-[0.65rem] !text-[0.78rem]" onchange="this.form.submit()">
                        <?php foreach (['pendiente'=>'Pendiente','pagado'=>'Pagado','preparacion'=>'En preparación','enviado'=>'Enviado','entregado'=>'Entregado','cancelado'=>'Cancelado'] as $v=>$t): ?>
                        <option value="<?= $v ?>" <?= $pedido['estado']==$v ? 'selected':'' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <!-- Factura PDF -->
                <?php if (in_array($pedido['estado'], ['pagado', 'preparacion', 'enviado', 'entregado'])): ?>
                    <a href="<?= base_url() ?>/factura_pdf.php?id=<?= $pedido['id'] ?>&download=1" target="_blank"
                       class="adm-btn bg-white/5 !text-[0.75rem] py-[0.3rem] px-[0.75rem]">
                       📄 PDF
                    </a>
                <?php else: ?>
                    <button type="button" class="adm-btn bg-white/5 !text-[0.75rem] py-[0.3rem] px-[0.75rem] opacity-60 cursor-not-allowed" 
                            onclick="admToast('La factura estará disponible una vez se confirme el pago.', 'error', 4500, 'Factura no disponible')">
                       📄 PDF
                    </button>
                <?php endif; ?>


                <!-- Editar pedido -->
                <button type="button"
                    class="adm-btn adm-btn-warning !text-[0.75rem] py-[0.3rem] px-[0.75rem] btn-editar-pedido"
                    data-pedido='<?= htmlspecialchars(json_encode([
                        "id"            => $pedido["id"],
                        "id_usuario"    => $pedido["id_usuario"],
                        "direccion_envio"=> $pedido["direccion_envio"],
                        "estado"        => $pedido["estado"],
                        "numero_guia"   => $col_guia_disponible ? ($pedido["numero_guia"] ?? null) : null,
                        "detalles"      => $detalles[$pedido["id"]] ?? []
                    ]), ENT_QUOTES, 'UTF-8') ?>'>
                    Editar
                </button>

                <button type="button" class="adm-btn adm-btn-danger !text-[0.75rem] py-[0.3rem] px-[0.75rem]"
                   onclick="abrirConfirmEliminar(<?= $pedido['id'] ?>)">Eliminar</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div id="pag-pedidos" class="flex items-center justify-center gap-2 mt-4 flex-wrap"></div>

</div>
</main>

<!-- Modal Confirmar Eliminación -->
<div id="modal-del-bg" class="adm-modal-overlay"></div>
<div id="modal-del" class="adm-modal hidden">
    <div class="adm-modal-box !max-w-[380px] text-center">
        <div class="w-14 h-14 bg-[#ef4444]/15 border-2 border-[#ef4444]/30 rounded-full flex items-center justify-center mx-auto mb-[1.1rem]">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-[26px] h-[26px] stroke-[#ef4444]">
                <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </div>
        <div class="adm-modal-title !mb-2 !text-[1.15rem]">¿Eliminar pedido?</div>
        <p id="modal-del-msg" class="text-[#888] text-[0.82rem] leading-relaxed mb-[1.4rem]">Esta acción no se puede deshacer.</p>
        <div class="flex gap-[10px]">
            <button type="button" onclick="cerrarConfirmEliminar()" class="adm-btn flex-1 justify-center">Cancelar</button>
            <a id="modal-del-href" href="#" class="adm-btn adm-btn-danger flex-1 justify-center">Sí, eliminar</a>
        </div>
    </div>
</div>

<!-- Modal Editar Pedido -->
<div id="modal-editar-bg" class="adm-modal-overlay"></div>
<div id="modal-editar-pedido" class="adm-modal hidden">
    <div class="adm-modal-box !max-w-[700px]">
        <button class="adm-modal-close" onclick="cerrarEditar()">&times;</button>
        <div class="adm-modal-title">Editar Pedido</div>
        <form id="form-editar-pedido" class="flex flex-col gap-4">
            <input type="hidden" name="id" id="edit-id">
            <div class="adm-form-row !mb-0">
                <div class="flex-[2]">
                    <label class="adm-label">Cliente *</label>
                    <select name="id_usuario" id="edit-id-usuario" class="adm-select" required>
                        <option value="">Selecciona un cliente</option>
                        <?php foreach ($pdo->query('SELECT id, nombre, email FROM usuarios ORDER BY nombre') as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nombre'].' ('.$u['email'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-[2]">
                    <label class="adm-label">Dirección de envío *</label>
                    <input type="text" name="direccion_envio" id="edit-direccion-envio" class="adm-input" required>
                </div>
            </div>
            <div class="adm-form-row !mb-0">
                <div>
                    <label class="adm-label">Estado</label>
                    <select name="estado" id="edit-estado" class="adm-select">
                        <option value="pendiente">Pendiente</option>
                        <option value="pagado">Pagado</option>
                        <option value="preparacion">En preparación</option>
                        <option value="enviado">Enviado</option>
                        <option value="entregado">Entregado</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
                <?php if ($col_guia_disponible): ?>
                <div>
                    <label class="adm-label">Nº Guía</label>
                    <input type="text" name="numero_guia" id="edit-numero-guia" class="adm-input" placeholder="Ej: 1234567890">
                </div>
                <?php endif; ?>
            </div>
            <!-- Agregar productos -->
            <div>
                <label class="adm-label">Productos</label>
                <div class="flex gap-2 mb-2">
                    <select id="edit-producto-sel" class="adm-select flex-1">
                        <option value="">Selecciona producto</option>
                        <?php foreach ($pdo->query('SELECT id, nombre, precio, stock FROM productos ORDER BY nombre') as $p): ?>
                        <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>" data-stock="<?= $p['stock'] ?>"><?= htmlspecialchars($p['nombre']) ?> (Stock: <?= $p['stock'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="agregarProductoEditar()" class="adm-btn adm-btn-blue">+ Ítem</button>
                </div>
                <div class="adm-table-wrap">
                    <table class="adm-table !text-[0.78rem]">
                        <thead><tr><th>Producto</th><th>Und</th><th>Cant.</th><th>Precio</th><th></th></tr></thead>
                        <tbody id="edit-productos-tabla"></tbody>
                    </table>
                </div>
            </div>
            <div class="flex justify-end gap-[10px] mt-2">
                <button type="button" onclick="cerrarEditar()" class="adm-btn">Cancelar</button>
                <button type="submit" class="adm-btn adm-btn-warning">Guardar cambios</button>
            </div>
        </form>
        <div id="modal-editar-msg" class="hidden mt-2 text-center text-[#ef4444] text-[0.8rem]"></div>
    </div>
</div>

<!-- Modal Nuevo Pedido -->
<div id="modal-nuevo-bg" class="adm-modal-overlay"></div>
<div id="modal-nuevo-pedido" class="adm-modal hidden">
    <div class="adm-modal-box !max-w-[700px]">
        <button class="adm-modal-close" onclick="cerrarNuevo()">&times;</button>
        <div class="adm-modal-title">Nuevo Pedido</div>
        <form id="form-nuevo-pedido" class="flex flex-col gap-4">
            <div class="adm-form-row !mb-0">
                <div class="flex-[2]">
                    <label class="adm-label">Cliente *</label>
                    <select name="id_usuario" id="nuevo-id-usuario" class="adm-select" required>
                        <option value="">Selecciona un cliente</option>
                        <?php foreach ($pdo->query('SELECT id, nombre, email FROM usuarios ORDER BY nombre') as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nombre'].' ('.$u['email'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-[2]">
                    <label class="adm-label">Dirección de envío *</label>
                    <input type="text" name="direccion_envio" id="nuevo-direccion-envio" class="adm-input" required>
                </div>
            </div>
            <!-- Items -->
            <div>
                <label class="adm-label">Items</label>
                <div class="flex gap-2 mb-2">
                    <input type="text" id="nuevo-producto-search" placeholder="Buscar..." class="adm-input flex-1" autocomplete="off">
                    <select id="nuevo-producto-sel" class="adm-select flex-[2]">
                        <option value="">Selecciona producto</option>
                        <?php foreach ($pdo->query('SELECT id, nombre, precio, stock FROM productos ORDER BY nombre') as $p): ?>
                        <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>" data-stock="<?= $p['stock'] ?>"><?= htmlspecialchars($p['nombre']) ?> (Stock: <?= $p['stock'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="agregarProductoNuevo()" class="adm-btn adm-btn-blue">+ Ítem</button>
                </div>
                <div class="adm-table-wrap">
                    <table class="adm-table !text-[0.78rem]">
                        <thead><tr><th>Producto</th><th>Und</th><th>Cant.</th><th>Precio</th><th>Dcto%</th><th>Stock</th><th></th></tr></thead>
                        <tbody id="nuevo-productos-tabla"></tbody>
                    </table>
                </div>
            </div>
            <!-- Totales -->
            <div class="adm-form-row !gap-2 !mb-0">
                <div><label class="adm-label">Subtotal</label><input type="text" id="nuevo-subtotal" readonly class="adm-input cursor-default opacity-60"></div>
                <div><label class="adm-label">Descuentos</label><input type="text" id="nuevo-descuentos" readonly class="adm-input cursor-default opacity-60"></div>
                <div><label class="adm-label">IVA (19%)</label><input type="text" id="nuevo-iva" readonly class="adm-input cursor-default opacity-60"></div>
                <div><label class="adm-label">Total Neto</label><input type="text" id="nuevo-total" readonly class="adm-input cursor-default font-bold"></div>
            </div>
            <!-- Método de pago -->
            <div>
                <label class="adm-label">Método de Pago *</label>
                <div class="flex gap-6 mt-1">
                    <label class="flex items-center gap-1.5 text-[0.85rem] cursor-pointer"><input type="radio" name="metodo_pago" value="efectivo" class="accent-[#ef4444]"> Efectivo</label>
                    <label class="flex items-center gap-1.5 text-[0.85rem] cursor-pointer"><input type="radio" name="metodo_pago" value="datfono" class="accent-[#ef4444]"> Datáfono</label>
                </div>
            </div>
            <div class="flex justify-end gap-[10px] mt-2">
                <button type="button" onclick="cerrarNuevo()" class="adm-btn">Cancelar</button>
                <button type="submit" class="adm-btn adm-btn-primary">Guardar Factura</button>
            </div>
        </form>
        <div id="modal-nuevo-msg" class="hidden mt-2 text-center text-[#ef4444] text-[0.8rem]"></div>
    </div>
</div>

<script>
/* ---- Helpers modal ---- */
function abrirEditar(pedido) {
    document.getElementById('modal-editar-bg').classList.add('show');
    document.getElementById('modal-editar-pedido').classList.remove('hidden');
    document.getElementById('modal-editar-pedido').classList.add('show');
    document.getElementById('edit-id').value = pedido.id;
    document.getElementById('edit-id-usuario').value = pedido.id_usuario;
    document.getElementById('edit-direccion-envio').value = pedido.direccion_envio;
    document.getElementById('edit-estado').value = pedido.estado;
    var guia = document.getElementById('edit-numero-guia');
    if (guia) guia.value = pedido.numero_guia || '';
    var tbody = document.getElementById('edit-productos-tabla');
    tbody.innerHTML = '';
    (pedido.detalles || []).forEach(d => {
        var row = document.createElement('tr');
        row.innerHTML = `<td><input type="hidden" name="productos[]" value="${d.id_producto}">${d.nombre}</td>
            <td>Und</td>
            <td><input type="number" name="cantidades[]" min="1" value="${d.cantidad}" class="adm-input w-[60px] !p-1"></td>
            <td><input type="number" name="precios[]" min="0" step="0.01" value="${d.precio_unitario}" class="adm-input w-[90px] !p-1"></td>
            <td><button type="button" onclick="this.closest('tr').remove()" class="adm-btn adm-btn-danger !text-[0.7rem] !p-1 px-2">✕</button></td>`;
        tbody.appendChild(row);
    });
    document.body.style.overflow = 'hidden';
}
function cerrarEditar() {
    document.getElementById('modal-editar-bg').classList.remove('show');
    document.getElementById('modal-editar-pedido').classList.add('hidden');
    document.getElementById('modal-editar-pedido').classList.remove('show');
    document.body.style.overflow = '';
}
function abrirNuevo() {
    document.getElementById('modal-nuevo-bg').classList.add('show');
    document.getElementById('modal-nuevo-pedido').classList.remove('hidden');
    document.getElementById('modal-nuevo-pedido').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function cerrarNuevo() {
    document.getElementById('modal-nuevo-bg').classList.remove('show');
    document.getElementById('modal-nuevo-pedido').classList.add('hidden');
    document.getElementById('modal-nuevo-pedido').classList.remove('show');
    document.body.style.overflow = '';
}
function abrirConfirmEliminar(id) {
    document.getElementById('modal-del-href').href = 'pedido_eliminar.php?id=' + id;
    document.getElementById('modal-del-msg').textContent = 'El Pedido #' + id + ' será eliminado permanentemente y no se podrá recuperar.';
    document.getElementById('modal-del-bg').classList.add('show');
    document.getElementById('modal-del').classList.remove('hidden');
    document.getElementById('modal-del').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function cerrarConfirmEliminar() {
    document.getElementById('modal-del-bg').classList.remove('show');
    document.getElementById('modal-del').classList.add('hidden');
    document.getElementById('modal-del').classList.remove('show');
    document.body.style.overflow = '';
}
function agregarProductoEditar() {
    var sel = document.getElementById('edit-producto-sel');
    if (!sel.value) return;
    var opt  = sel.options[sel.selectedIndex];
    var row  = document.createElement('tr');
    row.innerHTML = `<td><input type="hidden" name="productos[]" value="${sel.value}">${opt.text.split(' (Stock')[0]}</td>
        <td>Und</td>
        <td><input type="number" name="cantidades[]" min="1" value="1" class="adm-input w-[60px] !p-1"></td>
        <td><input type="number" name="precios[]" min="0" step="0.01" value="${opt.getAttribute('data-precio')}" class="adm-input w-[90px] !p-1"></td>
        <td><button type="button" onclick="this.closest('tr').remove()" class="adm-btn adm-btn-danger !text-[0.7rem] !p-1 px-2">✕</button></td>`;
    document.getElementById('edit-productos-tabla').appendChild(row);
}
function agregarProductoNuevo() {
    var sel   = document.getElementById('nuevo-producto-sel');
    var opt   = sel.options[sel.selectedIndex];
    if (!sel.value) return;
    var stock = opt.getAttribute('data-stock');
    var row   = document.createElement('tr');
    row.innerHTML = `<td><input type="hidden" name="productos[]" value="${sel.value}">${opt.text.split(' (Stock')[0]}</td>
        <td>Und</td>
        <td><input type="number" name="cantidades[]" min="1" value="1" class="adm-input w-[60px] !p-1" onchange="calcularTotalesNuevo()"></td>
        <td><input type="number" name="precios[]" min="0" step="0.01" value="${opt.getAttribute('data-precio')}" class="adm-input w-[90px] !p-1" onchange="calcularTotalesNuevo()"></td>
        <td><input type="number" name="descuentos[]" min="0" max="100" value="0" class="adm-input w-[55px] !p-1" onchange="calcularTotalesNuevo()"></td>
        <td class="stock-cell ${stock <= 2 ? 'text-[#ef4444] font-bold' : ''}">${stock}</td>
        <td><button type="button" onclick="this.closest('tr').remove(); calcularTotalesNuevo();" class="adm-btn adm-btn-danger !text-[0.7rem] !p-1 px-2">✕</button></td>`;
    document.getElementById('nuevo-productos-tabla').appendChild(row);
    calcularTotalesNuevo();
}
function calcularTotalesNuevo() {
    var subtotal=0, descuentos=0;
    document.querySelectorAll('#nuevo-productos-tabla tr').forEach(row => {
        var cant = parseFloat(row.querySelector('input[name="cantidades[]"]')?.value) || 0;
        var prec = parseFloat(row.querySelector('input[name="precios[]"]')?.value) || 0;
        var dc   = parseFloat(row.querySelector('input[name="descuentos[]"]')?.value) || 0;
        subtotal  += cant * prec;
        descuentos += cant * prec * (dc/100);
    });
    var iva   = (subtotal - descuentos) * 0.19;
    var total = (subtotal - descuentos) + iva;
    document.getElementById('nuevo-subtotal').value   = subtotal.toLocaleString('es-CO', {minimumFractionDigits:2});
    document.getElementById('nuevo-descuentos').value = descuentos.toLocaleString('es-CO', {minimumFractionDigits:2});
    document.getElementById('nuevo-iva').value        = iva.toLocaleString('es-CO', {minimumFractionDigits:2});
    document.getElementById('nuevo-total').value      = total.toLocaleString('es-CO', {minimumFractionDigits:2});
}
/* Búsqueda en tiempo real */
document.getElementById('nuevo-producto-search').addEventListener('input', function() {
    var val = this.value.toLowerCase();
    var sel = document.getElementById('nuevo-producto-sel');
    for (var i=0; i<sel.options.length; i++) {
        var opt=sel.options[i];
        opt.style.display = opt.text.toLowerCase().includes(val) ? '' : 'none';
    }
});
/* Submit nuevo */
document.getElementById('form-nuevo-pedido').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = new FormData(this);
    const res  = await fetch('pedido_nuevo.php', { method:'POST', body:data });
    const text = await res.text();
    if (text.includes('Location: pedidos.php') || text.includes('registrado')) { window.location.href = window.location.pathname + '?exito=1'; }
    else {
        var m = document.getElementById('modal-nuevo-msg');
        m.textContent = 'Error al registrar pedido. Revisa los datos.'; m.classList.remove('hidden');
    }
});
/* Submit editar */
document.getElementById('form-editar-pedido').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = new FormData(this);
    const res  = await fetch('pedido_editar.php?id=' + data.get('id'), { method:'POST', body:data });
    const text = await res.text();
    if (text.includes('Location: pedidos.php') || text.includes('actualizado')) { window.location.href = window.location.pathname + '?editado=1'; }
    else {
        var m = document.getElementById('modal-editar-msg');
        m.textContent = 'Error al editar pedido.'; m.classList.remove('hidden');
    }
});
/* Overlay click */
document.getElementById('modal-editar-bg').addEventListener('click', cerrarEditar);
document.getElementById('modal-nuevo-bg').addEventListener('click', cerrarNuevo);
document.getElementById('modal-del-bg').addEventListener('click', cerrarConfirmEliminar);

/* Botones editar */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-editar-pedido').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            abrirEditar(JSON.parse(this.dataset.pedido));
        });
    });
    document.getElementById('btn-nuevo-pedido').addEventListener('click', abrirNuevo);
});
</script>
<script>initPagination('#lista-pedidos','pag-pedidos',10);</script>

<?php include '_layout_end.php'; ?>