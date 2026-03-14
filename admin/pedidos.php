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
    $allowed_transitions = [
        'pendiente'  => ['pagado','cancelado'],
        'pagado'     => ['preparacion','cancelado'],
        'preparacion'=> ['enviado','cancelado'],
        'enviado'    => ['entregado','cancelado'],
        'entregado'  => [],
        'cancelado'  => []
    ];
    if ($nuevo_estado !== $anterior) {
        if (!isset($allowed_transitions[$anterior]) || !in_array($nuevo_estado, $allowed_transitions[$anterior])) {
            $_SESSION['flash_error'] = "Transición no permitida de '$anterior' a '$nuevo_estado'.";
            header('Location: pedidos.php');
            exit;
        }
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE pedidos SET estado = ? WHERE id = ?')->execute([$nuevo_estado, $id_pedido]);
        $pdo->prepare('INSERT INTO pedido_estados (id_pedido, estado, comentario) VALUES (?, ?, ?)')->execute([$id_pedido, $nuevo_estado, 'Cambio realizado desde Admin']);
        $stmt = $pdo->prepare('SELECT * FROM detalle_pedido WHERE id_pedido = ?');
        $stmt->execute([$id_pedido]);
        $detalles_upd = $stmt->fetchAll();
        $id_usuario   = $_SESSION['usuario']['id'];
        if (in_array($nuevo_estado, ['entregado','pagado']) && !in_array($anterior, ['entregado','pagado'])) {
            foreach ($detalles_upd as $d) {
                $pdo->prepare("INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, motivo, id_usuario) VALUES (?, 'salida', ?, ?, ?)")->execute([$d['id_producto'], $d['cantidad'], 'Venta/Pedido #'.$id_pedido, $id_usuario]);
                $pdo->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?')->execute([$d['cantidad'], $d['id_producto']]);
            }
        }
        if ($nuevo_estado === 'cancelado' && in_array($anterior, ['entregado','pagado'])) {
            foreach ($detalles_upd as $d) {
                $pdo->prepare("INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, motivo, id_usuario) VALUES (?, 'entrada', ?, ?, ?)")->execute([$d['id_producto'], $d['cantidad'], 'Cancelación Pedido #'.$id_pedido, $id_usuario]);
                $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?')->execute([$d['cantidad'], $d['id_producto']]);
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
$admin_header_extra = '<button id="btn-nuevo-pedido" class="adm-btn adm-btn-success"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Nuevo pedido</button>';

include '_layout.php';
?>

<main class="admin-content">
<div class="admin-content-inner">

    <?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="adm-alert adm-alert-error"><?= htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <!-- Filtros de estado -->
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:1.5rem">
        <?php
        $estados_ui = ['todos'=>'Todos','pendiente'=>'Pendientes','pagado'=>'Pagados','preparacion'=>'En preparación','enviado'=>'Enviados','entregado'=>'Entregados','cancelado'=>'Cancelados'];
        foreach ($estados_ui as $estado => $label):
            $active = ($estado_filtro === $estado);
        ?>
        <a href="pedidos.php<?= $estado === 'todos' ? '' : ('?estado='.$estado) ?>"
           class="adm-btn <?= $active ? 'adm-btn-primary' : '' ?>"
           style="<?= !$active ? 'background:rgba(255,255,255,0.04);color:#aaa;border:1px solid rgba(255,255,255,0.07)' : '' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (!$pedidos): ?>
    <div class="adm-card" style="text-align:center;color:#555;padding:3rem">No hay pedidos registrados.</div>
    <?php else: ?>
    <div id="lista-pedidos" style="display:flex;flex-direction:column;gap:1.25rem">
        <?php foreach ($pedidos as $pedido): ?>
        <div class="adm-card" style="padding:1.5rem">
            <!-- Cabecera del pedido -->
            <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;margin-bottom:0.875rem">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <span style="font-weight:700;font-size:1rem">Pedido #<?= $pedido['id'] ?></span>
                    <span class="adm-badge <?= estadoBadge($pedido['estado']) ?>"><?= ucfirst($pedido['estado']) ?></span>
                    <span style="color:#555;font-size:0.78rem"><?= date('d/m/Y H:i', strtotime($pedido['fecha'])) ?></span>
                </div>
                <div style="font-weight:700;font-size:1rem;color:#ef4444">$<?= number_format($pedido['total'], 0, ',', '.') ?> COP</div>
            </div>

            <!-- Info cliente -->
            <div style="display:flex;flex-wrap:wrap;gap:1.25rem;margin-bottom:0.875rem;font-size:0.8rem;color:#666">
                <span>👤 <?= htmlspecialchars($pedido['usuario_nombre'] ?: 'Invitado') ?> &mdash; <?= htmlspecialchars($pedido['email'] ?? '') ?></span>
                <span>📍 <?= htmlspecialchars($pedido['direccion_envio']) ?></span>
                <?php if ($col_guia_disponible && !empty($pedido['numero_guia'])): ?>
                <span>🚚 Guía: <strong><?= htmlspecialchars($pedido['numero_guia']) ?></strong></span>
                <?php endif; ?>
            </div>

            <!-- Items del pedido -->
            <?php if (!empty($detalles[$pedido['id']])): ?>
            <div class="adm-table-wrap" style="margin-bottom:0.875rem">
                <table class="adm-table" style="font-size:0.78rem">
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
                            <div style="display:flex;align-items:center;gap:8px">
                                <?php if (!empty($det['imagen'])): ?>
                                <img src="<?= htmlspecialchars($det['imagen']) ?>" alt="" style="width:36px;height:28px;object-fit:cover;border-radius:4px;opacity:.85">
                                <?php endif; ?>
                                <?= htmlspecialchars($det['nombre']) ?>
                            </div>
                        </td>
                        <td><?= $det['cantidad'] ?></td>
                        <td>$<?= number_format($det['precio_unitario'], 0, ',', '.') ?></td>
                        <td><?= isset($det['descuento']) ? $det['descuento'] : 0 ?>%</td>
                        <td>$<?= number_format($iva, 0, ',', '.') ?></td>
                        <td style="font-weight:600">$<?= number_format($neto, 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Acciones -->
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px">
                <!-- Cambiar estado -->
                <form method="post" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <input type="hidden" name="id_pedido" value="<?= $pedido['id'] ?>">
                    <label style="font-size:0.78rem;color:#777;font-weight:600">Estado:</label>
                    <input type="hidden" name="cambiar_estado" value="1">
                    <select name="nuevo_estado" class="adm-select" style="width:auto;padding:0.3rem 0.65rem;font-size:0.78rem" onchange="this.form.submit()">
                        <?php foreach (['pendiente'=>'Pendiente','pagado'=>'Pagado','preparacion'=>'En preparación','enviado'=>'Enviado','entregado'=>'Entregado','cancelado'=>'Cancelado'] as $v=>$t): ?>
                        <option value="<?= $v ?>" <?= $pedido['estado']==$v ? 'selected':'' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <!-- Factura PDF -->
                <?php if (in_array($pedido['estado'], ['pagado', 'preparacion', 'enviado', 'entregado'])): ?>
                    <a href="<?= base_url() ?>/factura_pdf.php?id=<?= $pedido['id'] ?>&download=1" target="_blank"
                       class="adm-btn" style="background:rgba(255,255,255,0.05);font-size:0.75rem;padding:0.3rem 0.75rem">
                       📄 PDF
                    </a>
                <?php else: ?>
                    <button type="button" class="adm-btn" 
                            style="background:rgba(255,255,255,0.02);font-size:0.75rem;padding:0.3rem 0.75rem;opacity:0.6;cursor:not-allowed;"
                            onclick="admToast('La factura estará disponible una vez se confirme el pago.', 'error', 4500, 'Factura no disponible')">
                       📄 PDF
                    </button>
                <?php endif; ?>


                <!-- Editar pedido -->
                <button type="button"
                    class="adm-btn adm-btn-warning btn-editar-pedido"
                    style="font-size:0.75rem;padding:0.3rem 0.75rem"
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

                <button type="button" class="adm-btn adm-btn-danger"
                   style="font-size:0.75rem;padding:0.3rem 0.75rem"
                   onclick="abrirConfirmEliminar(<?= $pedido['id'] ?>)">Eliminar</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div id="pag-pedidos" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:1rem;flex-wrap:wrap"></div>

</div>
</main>

<!-- Modal Confirmar Eliminación -->
<div id="modal-del-bg" class="adm-modal-overlay"></div>
<div id="modal-del" class="adm-modal hidden">
    <div class="adm-modal-box" style="max-width:380px;text-align:center">
        <div style="width:56px;height:56px;background:rgba(239,68,68,.12);border:2px solid rgba(239,68,68,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.1rem">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:26px;height:26px;stroke:#ef4444">
                <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </div>
        <div class="adm-modal-title" style="margin-bottom:.4rem;font-size:1.15rem">¿Eliminar pedido?</div>
        <p id="modal-del-msg" style="color:#888;font-size:.82rem;line-height:1.5;margin-bottom:1.4rem">Esta acción no se puede deshacer.</p>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="cerrarConfirmEliminar()" class="adm-btn" style="flex:1;justify-content:center">Cancelar</button>
            <a id="modal-del-href" href="#" class="adm-btn adm-btn-danger" style="flex:1;justify-content:center">Sí, eliminar</a>
        </div>
    </div>
</div>

<!-- Modal Editar Pedido -->
<div id="modal-editar-bg" class="adm-modal-overlay"></div>
<div id="modal-editar-pedido" class="adm-modal hidden">
    <div class="adm-modal-box" style="max-width:700px">
        <button class="adm-modal-close" onclick="cerrarEditar()">&times;</button>
        <div class="adm-modal-title">Editar Pedido</div>
        <form id="form-editar-pedido" style="display:flex;flex-direction:column;gap:1rem">
            <input type="hidden" name="id" id="edit-id">
            <div class="adm-form-row" style="margin-bottom:0">
                <div style="flex:2">
                    <label class="adm-label">Cliente *</label>
                    <select name="id_usuario" id="edit-id-usuario" class="adm-select" required>
                        <option value="">Selecciona un cliente</option>
                        <?php foreach ($pdo->query('SELECT id, nombre, email FROM usuarios ORDER BY nombre') as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nombre'].' ('.$u['email'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:2">
                    <label class="adm-label">Dirección de envío *</label>
                    <input type="text" name="direccion_envio" id="edit-direccion-envio" class="adm-input" required>
                </div>
            </div>
            <div class="adm-form-row" style="margin-bottom:0">
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
                <div style="display:flex;gap:8px;margin-bottom:0.5rem">
                    <select id="edit-producto-sel" class="adm-select" style="flex:1">
                        <option value="">Selecciona producto</option>
                        <?php foreach ($pdo->query('SELECT id, nombre, precio, stock FROM productos ORDER BY nombre') as $p): ?>
                        <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>" data-stock="<?= $p['stock'] ?>"><?= htmlspecialchars($p['nombre']) ?> (Stock: <?= $p['stock'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="agregarProductoEditar()" class="adm-btn adm-btn-blue">+ Ítem</button>
                </div>
                <div class="adm-table-wrap">
                    <table class="adm-table" style="font-size:0.78rem">
                        <thead><tr><th>Producto</th><th>Und</th><th>Cant.</th><th>Precio</th><th></th></tr></thead>
                        <tbody id="edit-productos-tabla"></tbody>
                    </table>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:0.5rem">
                <button type="button" onclick="cerrarEditar()" class="adm-btn">Cancelar</button>
                <button type="submit" class="adm-btn adm-btn-warning">Guardar cambios</button>
            </div>
        </form>
        <div id="modal-editar-msg" style="display:none;margin-top:0.5rem;text-align:center;color:#ef4444;font-size:0.8rem"></div>
    </div>
</div>

<!-- Modal Nuevo Pedido -->
<div id="modal-nuevo-bg" class="adm-modal-overlay"></div>
<div id="modal-nuevo-pedido" class="adm-modal hidden">
    <div class="adm-modal-box" style="max-width:700px">
        <button class="adm-modal-close" onclick="cerrarNuevo()">&times;</button>
        <div class="adm-modal-title">Nuevo Pedido</div>
        <form id="form-nuevo-pedido" style="display:flex;flex-direction:column;gap:1rem">
            <div class="adm-form-row" style="margin-bottom:0">
                <div style="flex:2">
                    <label class="adm-label">Cliente *</label>
                    <select name="id_usuario" id="nuevo-id-usuario" class="adm-select" required>
                        <option value="">Selecciona un cliente</option>
                        <?php foreach ($pdo->query('SELECT id, nombre, email FROM usuarios ORDER BY nombre') as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nombre'].' ('.$u['email'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:2">
                    <label class="adm-label">Dirección de envío *</label>
                    <input type="text" name="direccion_envio" id="nuevo-direccion-envio" class="adm-input" required>
                </div>
            </div>
            <!-- Items -->
            <div>
                <label class="adm-label">Items</label>
                <div style="display:flex;gap:8px;margin-bottom:0.5rem">
                    <input type="text" id="nuevo-producto-search" placeholder="Buscar..." class="adm-input" style="flex:1" autocomplete="off">
                    <select id="nuevo-producto-sel" class="adm-select" style="flex:2">
                        <option value="">Selecciona producto</option>
                        <?php foreach ($pdo->query('SELECT id, nombre, precio, stock FROM productos ORDER BY nombre') as $p): ?>
                        <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>" data-stock="<?= $p['stock'] ?>"><?= htmlspecialchars($p['nombre']) ?> (Stock: <?= $p['stock'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="agregarProductoNuevo()" class="adm-btn adm-btn-blue">+ Ítem</button>
                </div>
                <div class="adm-table-wrap">
                    <table class="adm-table" style="font-size:0.78rem">
                        <thead><tr><th>Producto</th><th>Und</th><th>Cant.</th><th>Precio</th><th>Dcto%</th><th>Stock</th><th></th></tr></thead>
                        <tbody id="nuevo-productos-tabla"></tbody>
                    </table>
                </div>
            </div>
            <!-- Totales -->
            <div class="adm-form-row" style="gap:8px;margin-bottom:0">
                <div><label class="adm-label">Subtotal</label><input type="text" id="nuevo-subtotal" readonly class="adm-input" style="cursor:default;opacity:.6"></div>
                <div><label class="adm-label">Descuentos</label><input type="text" id="nuevo-descuentos" readonly class="adm-input" style="cursor:default;opacity:.6"></div>
                <div><label class="adm-label">IVA (19%)</label><input type="text" id="nuevo-iva" readonly class="adm-input" style="cursor:default;opacity:.6"></div>
                <div><label class="adm-label">Total Neto</label><input type="text" id="nuevo-total" readonly class="adm-input" style="cursor:default;font-weight:700"></div>
            </div>
            <!-- Método de pago -->
            <div>
                <label class="adm-label">Método de Pago *</label>
                <div style="display:flex;gap:1.5rem;margin-top:0.25rem">
                    <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;cursor:pointer"><input type="radio" name="metodo_pago" value="efectivo" style="accent-color:#ef4444"> Efectivo</label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;cursor:pointer"><input type="radio" name="metodo_pago" value="datfono" style="accent-color:#ef4444"> Datáfono</label>
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:0.5rem">
                <button type="button" onclick="cerrarNuevo()" class="adm-btn">Cancelar</button>
                <button type="submit" class="adm-btn adm-btn-primary">Guardar Factura</button>
            </div>
        </form>
        <div id="modal-nuevo-msg" style="display:none;margin-top:0.5rem;text-align:center;color:#ef4444;font-size:0.8rem"></div>
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
            <td><input type="number" name="cantidades[]" min="1" value="${d.cantidad}" class="adm-input" style="width:60px;padding:0.25rem 0.4rem"></td>
            <td><input type="number" name="precios[]" min="0" step="0.01" value="${d.precio_unitario}" class="adm-input" style="width:90px;padding:0.25rem 0.4rem"></td>
            <td><button type="button" onclick="this.closest('tr').remove()" class="adm-btn adm-btn-danger" style="font-size:0.7rem;padding:0.2rem 0.5rem">✕</button></td>`;
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
        <td><input type="number" name="cantidades[]" min="1" value="1" class="adm-input" style="width:60px;padding:0.25rem 0.4rem"></td>
        <td><input type="number" name="precios[]" min="0" step="0.01" value="${opt.getAttribute('data-precio')}" class="adm-input" style="width:90px;padding:0.25rem 0.4rem"></td>
        <td><button type="button" onclick="this.closest('tr').remove()" class="adm-btn adm-btn-danger" style="font-size:0.7rem;padding:0.2rem 0.5rem">✕</button></td>`;
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
        <td><input type="number" name="cantidades[]" min="1" value="1" class="adm-input" style="width:60px;padding:0.25rem 0.4rem" onchange="calcularTotalesNuevo()"></td>
        <td><input type="number" name="precios[]" min="0" step="0.01" value="${opt.getAttribute('data-precio')}" class="adm-input" style="width:90px;padding:0.25rem 0.4rem" onchange="calcularTotalesNuevo()"></td>
        <td><input type="number" name="descuentos[]" min="0" max="100" value="0" class="adm-input" style="width:55px;padding:0.25rem 0.4rem" onchange="calcularTotalesNuevo()"></td>
        <td class="stock-cell" style="${stock <= 2 ? 'color:#ef4444;font-weight:700' : ''}">${stock}</td>
        <td><button type="button" onclick="this.closest('tr').remove(); calcularTotalesNuevo();" class="adm-btn adm-btn-danger" style="font-size:0.7rem;padding:0.2rem 0.5rem">✕</button></td>`;
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
        m.textContent = 'Error al registrar pedido. Revisa los datos.'; m.style.display='block';
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
        m.textContent = 'Error al editar pedido.'; m.style.display='block';
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