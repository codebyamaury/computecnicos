<?php
session_start();
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin    = $_GET['fecha_fin']    ?? date('Y-m-t');
$tipo_reporte = $_GET['tipo']         ?? 'general';

function formatearMoneda($v) { return '$' . number_format($v, 2, ',', '.'); }

function calcularTotales($movimientos) {
    $t = ['compras'=>0,'ventas'=>0,'iva_pagado'=>0,'iva_cobrado'=>0,'retenciones'=>0];
    foreach ($movimientos as $m) {
        if ($m['tipo'] === 'entrada' && $m['precio_unitario']) {
            $t['compras']    += $m['precio_unitario'] * $m['cantidad'];
            $t['iva_pagado'] += $m['iva'] ?? 0;
            $t['retenciones']+= $m['retencion'] ?? 0;
        }
    }
    return $t;
}

switch ($tipo_reporte) {
    case 'compras':
        $sql = "SELECT m.*, p.nombre AS producto, prov.nombre AS proveedor, u.nombre AS usuario
                FROM movimientos_inventario m JOIN productos p ON m.id_producto=p.id
                LEFT JOIN proveedores prov ON m.id_proveedor=prov.id
                LEFT JOIN usuarios u ON m.id_usuario=u.id
                WHERE m.tipo='entrada' AND m.fecha BETWEEN ? AND ? ORDER BY m.fecha DESC";
        $stmt = $pdo->prepare($sql); $stmt->execute([$fecha_inicio, $fecha_fin.' 23:59:59']);
        $movimientos = $stmt->fetchAll(); $totales = [];
        break;
    case 'inventario':
        $sql = "SELECT p.*, c.nombre AS categoria,
                COALESCE(SUM(CASE WHEN m.tipo='entrada' THEN m.cantidad ELSE 0 END),0)-
                COALESCE(SUM(CASE WHEN m.tipo='salida' THEN m.cantidad ELSE 0 END),0) AS stock_actual,
                AVG(CASE WHEN m.tipo='entrada' THEN m.precio_unitario END) AS precio_promedio
                FROM productos p LEFT JOIN categorias c ON p.id_categoria=c.id
                LEFT JOIN movimientos_inventario m ON p.id=m.id_producto
                GROUP BY p.id ORDER BY p.nombre";
        $stmt = $pdo->prepare($sql); $stmt->execute();
        $inventario = $stmt->fetchAll(); $movimientos=[]; $totales=[];
        break;
    default:
        $sql = "SELECT m.*, p.nombre AS producto, prov.nombre AS proveedor, u.nombre AS usuario
                FROM movimientos_inventario m JOIN productos p ON m.id_producto=p.id
                LEFT JOIN proveedores prov ON m.id_proveedor=prov.id
                LEFT JOIN usuarios u ON m.id_usuario=u.id
                WHERE m.fecha BETWEEN ? AND ? ORDER BY m.fecha DESC";
        $stmt = $pdo->prepare($sql); $stmt->execute([$fecha_inicio, $fecha_fin.' 23:59:59']);
        $movimientos = $stmt->fetchAll(); $totales = calcularTotales($movimientos);
}

if (isset($_GET['exportar']) && $_GET['exportar'] === 'excel') {
    header('Location: reporte_contable.php?tipo='.$tipo_reporte.'&fecha_inicio='.$fecha_inicio.'&fecha_fin='.$fecha_fin);
    exit;
}

$page_title       = 'Reporte Contable | Computécnicos';
$admin_page       = 'reportes';
$admin_title      = 'Reporte Contable';
$admin_breadcrumb = [['label' => 'Reporte Contable']];

include '_layout.php';
?>

<main class="admin-content">
<div class="admin-content-inner">

    <!-- Filtros -->
    <div class="adm-card">
        <form method="get">
            <div class="adm-form-row" style="flex-wrap:wrap;align-items:flex-end">
                <div>
                    <label class="adm-label">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" value="<?= $fecha_inicio ?>" class="adm-input">
                </div>
                <div>
                    <label class="adm-label">Fecha Fin</label>
                    <input type="date" name="fecha_fin" value="<?= $fecha_fin ?>" class="adm-input">
                </div>
                <div>
                    <label class="adm-label">Tipo de Reporte</label>
                    <select name="tipo" class="adm-select">
                        <option value="general"    <?= $tipo_reporte==='general'    ? 'selected':'' ?>>General</option>
                        <option value="compras"    <?= $tipo_reporte==='compras'    ? 'selected':'' ?>>Compras</option>
                        <option value="inventario" <?= $tipo_reporte==='inventario' ? 'selected':'' ?>>Inventario Valorizado</option>
                    </select>
                </div>
                <div style="display:flex;gap:8px;align-items:flex-end">
                    <button type="submit" class="adm-btn adm-btn-blue">Filtrar</button>
                    <a href="?exportar=excel&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&tipo=<?= $tipo_reporte ?>" class="adm-btn adm-btn-success">Exportar Excel</a>
                </div>
            </div>
        </form>
    </div>

    <?php if ($tipo_reporte === 'general' && isset($totales) && !empty($totales)): ?>
    <!-- KPIs Financieros -->
    <div class="adm-kpi-grid" style="--kpi-cols:3">
        <div class="adm-kpi green">
            <div class="adm-kpi-label">Compras</div>
            <div class="adm-kpi-value"><?= formatearMoneda($totales['compras']) ?></div>
            <div class="adm-kpi-sub">IVA Pagado: <?= formatearMoneda($totales['iva_pagado']) ?></div>
        </div>
        <div class="adm-kpi blue">
            <div class="adm-kpi-label">Ventas</div>
            <div class="adm-kpi-value"><?= formatearMoneda($totales['ventas']) ?></div>
            <div class="adm-kpi-sub">IVA Cobrado: <?= formatearMoneda($totales['iva_cobrado']) ?></div>
        </div>
        <div class="adm-kpi yellow">
            <div class="adm-kpi-label">Utilidad</div>
            <div class="adm-kpi-value"><?= formatearMoneda($totales['ventas'] - $totales['compras']) ?></div>
            <div class="adm-kpi-sub">Retenciones: <?= formatearMoneda($totales['retenciones']) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabla de datos -->
    <div class="adm-card" style="padding:0;overflow:hidden">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.04)">
            <span class="adm-card-title-text">
                <?php
                switch($tipo_reporte) {
                    case 'compras': echo 'Reporte de Compras'; break;
                    case 'inventario': echo 'Inventario Valorizado'; break;
                    default: echo 'Movimientos Generales'; break;
                }
                ?>
            </span>
        </div>

        <?php if ($tipo_reporte === 'inventario' && isset($inventario)): ?>
        <div class="adm-table-wrap" style="border:none;border-radius:0">
            <table class="adm-table">
                <thead>
                    <tr><th>Producto</th><th>Categoría</th><th>Stock Actual</th><th>Precio Promedio</th><th>Valor Total</th></tr>
                </thead>
                <tbody>
                <?php $valor_total=0; foreach ($inventario as $item):
                    $vi = $item['stock_actual'] * ($item['precio_promedio'] ?? 0);
                    $valor_total += $vi;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($item['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars($item['categoria'] ?? '—') ?></td>
                    <td><span class="adm-badge <?= $item['stock_actual'] <= 0 ? 'adm-badge-red' : ($item['stock_actual'] <= 5 ? 'adm-badge-yellow' : 'adm-badge-green') ?>"><?= $item['stock_actual'] ?></span></td>
                    <td><?= $item['precio_promedio'] ? formatearMoneda($item['precio_promedio']) : '—' ?></td>
                    <td style="font-weight:600"><?= formatearMoneda($vi) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:rgba(255,255,255,0.02)">
                        <td colspan="4" style="text-align:right;padding:0.875rem 1rem;font-weight:700;color:#888">Valor Total del Inventario:</td>
                        <td style="padding:0.875rem 1rem;font-weight:700;color:#22c55e"><?= formatearMoneda($valor_total) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
        <div class="adm-table-wrap" style="border:none;border-radius:0">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <?php if ($tipo_reporte !== 'compras'): ?><th>Tipo</th><?php endif; ?>
                        <th>Producto</th>
                        <?php if ($tipo_reporte === 'compras'): ?><th>Proveedor</th><th>Factura</th><?php endif; ?>
                        <th>Cantidad</th><th>Precio Unit.</th>
                        <?php if ($tipo_reporte === 'compras'): ?><th>Total</th><?php endif; ?>
                        <th>IVA</th><th>Retención</th>
                        <?php if ($tipo_reporte !== 'compras'): ?><th>Usuario</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($movimientos)): ?>
                <tr><td colspan="12" style="text-align:center;padding:2rem;color:#444">Sin registros en el período seleccionado</td></tr>
                <?php else: foreach ($movimientos as $m): ?>
                <tr>
                    <td style="color:#555;font-size:0.75rem;white-space:nowrap"><?= date('d/m/Y', strtotime($m['fecha'])) ?></td>
                    <?php if ($tipo_reporte !== 'compras'): ?>
                    <td>
                        <?php if ($m['tipo']==='entrada'): ?><span class="adm-badge adm-badge-green">Entrada</span>
                        <?php elseif ($m['tipo']==='salida'): ?><span class="adm-badge adm-badge-red">Salida</span>
                        <?php else: ?><span class="adm-badge adm-badge-yellow">Ajuste</span><?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td><strong><?= htmlspecialchars($m['producto']) ?></strong></td>
                    <?php if ($tipo_reporte === 'compras'): ?>
                    <td><?= htmlspecialchars($m['proveedor'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($m['numero_factura'] ?? '—') ?></td>
                    <?php endif; ?>
                    <td><?= $m['cantidad'] ?></td>
                    <td><?= $m['precio_unitario'] ? formatearMoneda($m['precio_unitario']) : '—' ?></td>
                    <?php if ($tipo_reporte === 'compras'): ?>
                    <td style="font-weight:600"><?= formatearMoneda(($m['precio_unitario']??0)*$m['cantidad']) ?></td>
                    <?php endif; ?>
                    <td><?= $m['iva'] ? formatearMoneda($m['iva']) : '—' ?></td>
                    <td><?= $m['retencion'] ? formatearMoneda($m['retencion']) : '—' ?></td>
                    <?php if ($tipo_reporte !== 'compras'): ?>
                    <td><?= htmlspecialchars($m['usuario'] ?? '—') ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>
</main>

<?php include '_layout_end.php'; ?>