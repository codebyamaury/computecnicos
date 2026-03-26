<?php
// Sesión manejada por bootstrap (DB handler)
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
    $t = [
        'ventas_brutas' => 0, 
        'devoluciones' => 0, 
        'ventas_netas' => 0,
        'iva_cobrado' => 0, 
        'iva_devuelto' => 0,
        'inversion_stock' => 0
    ];
    foreach ($movimientos as $m) {
        $monto = ($m['precio_unitario'] ?? 0) * $m['cantidad'];
        $iva = (!empty($m['iva'])) ? (float)$m['iva'] : round($monto * 0.19, 2); // Estimar IVA 19% si no está desglosado

        $motivo = mb_strtolower($m['motivo'] ?? '', 'UTF-8');
        // Un movimiento se considera derivado de un pedido si incluye las palabras clave
        $isPedido = strpos($motivo, 'pedido') !== false || strpos($motivo, 'pago') !== false || strpos($motivo, 'reembolso') !== false;

        if ($m['tipo'] === 'salida' && $isPedido) {
            // Venta formal
            $t['ventas_brutas'] += $monto;
            $t['iva_cobrado']   += $iva;
        } elseif ($m['tipo'] === 'entrada' && $isPedido) {
            // Devolución / Cancelación de Venta
            $t['devoluciones'] += $monto;
            $t['iva_devuelto'] += $iva;
        } elseif ($m['tipo'] === 'entrada' && !$isPedido) {
            // Ingreso de inventario desde panel (Ajuste/Reposición valorizado a precio de venta actual)
            $t['inversion_stock'] += $monto;
        }
    }
    
    // Calcular netos consolidados
    $t['ventas_netas'] = max(0, $t['ventas_brutas'] - $t['devoluciones']);
    $t['iva_neto'] = max(0, $t['iva_cobrado'] - $t['iva_devuelto']);
    
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
        $sql = "SELECT p.id, p.nombre, p.stock AS stock_actual, p.precio,
                c.nombre AS categoria,
                AVG(CASE WHEN m.tipo='entrada' AND m.precio_unitario > 0 THEN m.precio_unitario END) AS precio_promedio
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
    $filename = "reporte_{$tipo_reporte}_{$fecha_inicio}_a_{$fecha_fin}.csv";
    if (ob_get_length()) ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    // BOM for Excel UTF-8
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    
    if ($tipo_reporte === 'inventario') {
        fputcsv($output, ['Producto', 'Categoria', 'Stock Actual', 'Precio Ref.', 'Valor Total'], ';');
        foreach ($inventario as $item) {
            $precio_ref = $item['precio_promedio'] ?? $item['precio'] ?? 0;
            $vi = max(0, $item['stock_actual']) * $precio_ref;
            fputcsv($output, [
                $item['nombre'], $item['categoria'] ?? '', $item['stock_actual'], 
                $precio_ref, $vi
            ], ';');
        }
    } elseif ($tipo_reporte === 'compras') {
        fputcsv($output, ['Fecha', 'Producto', 'Proveedor', 'Factura', 'Cantidad', 'Precio Unit.', 'Total', 'IVA', 'Retencion'], ';');
        foreach ($movimientos as $m) {
            fputcsv($output, [
                date('d/m/Y', strtotime($m['fecha'])), $m['producto'], $m['proveedor'] ?? '', 
                $m['numero_factura'] ?? '', $m['cantidad'], $m['precio_unitario'] ?? 0, 
                ($m['precio_unitario'] ?? 0) * $m['cantidad'], $m['iva'] ?? 0, $m['retencion'] ?? 0
            ], ';');
        }
    } else {
        fputcsv($output, ['Fecha', 'Tipo', 'Producto', 'Cantidad', 'Precio Unit.', 'IVA', 'Retencion', 'Usuario'], ';');
        foreach ($movimientos as $m) {
            fputcsv($output, [
                date('d/m/Y', strtotime($m['fecha'])), ucfirst($m['tipo']), $m['producto'], 
                $m['cantidad'], $m['precio_unitario'] ?? 0, $m['iva'] ?? 0, $m['retencion'] ?? 0, $m['usuario'] ?? ''
            ], ';');
        }
    }
    fclose($output);
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
    <!-- KPIs Financieros Profesionales -->
    <div class="adm-kpi-grid" style="--kpi-cols:3">
        <div class="adm-kpi blue">
            <div class="adm-kpi-label">Ingresos Brutos</div>
            <div class="adm-kpi-value text-white" style="font-size:1.4rem"><?= formatearMoneda($totales['ventas_brutas']) ?></div>
            <div class="adm-kpi-sub">IVA Cobrado: <?= formatearMoneda($totales['iva_cobrado']) ?></div>
        </div>
        <div class="adm-kpi yellow">
            <div class="adm-kpi-label">Devoluciones y Cancelaciones</div>
            <div class="adm-kpi-value" style="color:#eab308;font-size:1.4rem">-<?= formatearMoneda($totales['devoluciones']) ?></div>
            <div class="adm-kpi-sub" style="color:rgba(234,179,8,0.7)">Retorno IVA: -<?= formatearMoneda($totales['iva_devuelto']) ?></div>
        </div>
        <div class="adm-kpi green">
            <div class="adm-kpi-label">Ventas Netas Realizadas</div>
            <div class="adm-kpi-value" style="color:#22c55e;font-size:1.4rem"><?= formatearMoneda($totales['ventas_netas']) ?></div>
            <div class="adm-kpi-sub" style="color:rgba(34,197,94,0.7)">IVA Neto Generado: <?= formatearMoneda($totales['iva_neto']) ?></div>
        </div>
    </div>

    <!-- Indicador de Abastecimiento Opcional -->
    <?php if ($totales['inversion_stock'] > 0): ?>
    <div style="background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); padding:1rem 1.5rem; margin-top:1.5rem; margin-bottom:0.5rem; border-radius:12px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:0.85rem; color:#888;">
            <i data-lucide="package-plus" style="width:18px;height:18px;display:inline-block;vertical-align:middle;margin-right:8px;color:#a855f7;"></i>
            Abastecimientos/Ajustes de Stock manuales incorporados al inventario (Capitalizados a precio de venta final):
        </div>
        <div style="font-weight:700; color:#fff; font-size:1.1rem; letter-spacing:0.5px;"><?= formatearMoneda($totales['inversion_stock']) ?></div>
    </div>
    <?php endif; ?>
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
                    $precio_ref = $item['precio_promedio'] ?? $item['precio'] ?? 0;
                    $vi = max(0, $item['stock_actual']) * $precio_ref;
                    $valor_total += $vi;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($item['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars($item['categoria'] ?? '—') ?></td>
                    <td><span class="adm-badge <?= $item['stock_actual'] <= 0 ? 'adm-badge-red' : ($item['stock_actual'] <= 5 ? 'adm-badge-yellow' : 'adm-badge-green') ?>"><?= $item['stock_actual'] ?></span></td>
                    <td><?= $precio_ref > 0 ? formatearMoneda($precio_ref) : '—' ?></td>
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