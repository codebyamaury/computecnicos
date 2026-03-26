<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
$usuario = $_SESSION['usuario'];

// ─── KPIs ────────────────────────────────────────────────────────────────────
$ventas_mes = $pdo->query("SELECT DATE_FORMAT(fecha,'%Y-%m') as mes, SUM(total) as total, COUNT(*) as pedidos FROM pedidos WHERE estado IN ('pagado','enviado','entregado') GROUP BY mes ORDER BY mes DESC LIMIT 12")->fetchAll();
$ventas_dia = $pdo->query("SELECT DATE(fecha) as dia, SUM(total) as total, COUNT(*) as pedidos FROM pedidos WHERE estado IN ('pagado','enviado','entregado') GROUP BY dia ORDER BY dia DESC LIMIT 15")->fetchAll();
$estados = $pdo->query("SELECT estado, COUNT(*) as cantidad FROM pedidos GROUP BY estado")->fetchAll();
$mas_vendidos = $pdo->query("SELECT p.nombre, SUM(d.cantidad) as total_vendidos FROM detalle_pedido d JOIN productos p ON d.id_producto = p.id GROUP BY d.id_producto ORDER BY total_vendidos DESC LIMIT 10")->fetchAll();
$stmt = $pdo->query('SELECT p.*, c.nombre AS categoria, m.nombre AS marca FROM productos p LEFT JOIN categorias c ON p.id_categoria = c.id LEFT JOIN marcas m ON p.id_marca = m.id WHERE p.stock <= 5 ORDER BY p.stock ASC, p.nombre ASC');
$productos_bajo = $stmt->fetchAll();

$total_ventas_mes = $ventas_mes ? $ventas_mes[0]['total'] : 0;
$total_pedidos_mes = $ventas_mes ? $ventas_mes[0]['pedidos'] : 0;
$total_productos_bajo = count($productos_bajo);
$utilidad_mes = $total_ventas_mes * 0.18;

// ─── Resumen Contable del Mes ──────────────────────────────────────────────
// Ventas netas del mes (pedidos pagados, enviados, entregados)
$ventas_netas_mes = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE estado IN ('pagado','enviado','entregado') AND fecha >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();

// Devoluciones / Reembolsos del mes
try {
    $devoluciones_mes = $pdo->query("SELECT COALESCE(SUM(monto), 0) FROM reembolsos WHERE estado = 'aprobado' AND fecha_resolucion >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
} catch (Throwable $e) { $devoluciones_mes = 0; }

// Compras del mes (entradas de inventario con precio)
$compras_mes = $pdo->query("SELECT COALESCE(SUM(precio_unitario * cantidad), 0) FROM movimientos_inventario WHERE tipo = 'entrada' AND precio_unitario IS NOT NULL AND precio_unitario > 0 AND fecha >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();

// IVA cobrado estimado (19% sobre ventas netas)
$iva_cobrado_mes = round($ventas_netas_mes * 0.19 / 1.19); // IVA incluido en precio

// Valor total del inventario (stock * precio de venta)
$valor_inventario = $pdo->query("SELECT COALESCE(SUM(stock * precio), 0) FROM productos WHERE stock > 0 AND precio > 0")->fetchColumn();

// Total pedidos pendientes de pago
$pedidos_pendientes = $pdo->query("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE estado = 'pendiente' AND fecha >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();

// ─── Layout vars ─────────────────────────────────────────────────────────────
$page_title = 'Dashboard | Computécnicos';
$admin_page = 'dashboard';
$admin_title = 'Dashboard';
$admin_breadcrumb = [['label' => 'Dashboard']];
$admin_extra_css = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
$admin_header_extra = '<a href="reporte_contable.php" class="adm-btn adm-btn-blue">Reporte Legal y Contable</a>';

include '_layout.php';
?>

<main class="admin-content">
    <div class="admin-content-inner">

        <!-- ── KPI Cards ── -->
        <div class="adm-kpi-grid">
            <div class="adm-kpi red">
                <div class="adm-kpi-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="adm-kpi-label">Ventas del Mes</div>
                <div class="adm-kpi-value">$<?= number_format($total_ventas_mes, 0, ',', '.') ?></div>
                <div class="adm-kpi-sub">Total facturado</div>
            </div>
            <div class="adm-kpi blue">
                <div class="adm-kpi-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div class="adm-kpi-label">Pedidos del Mes</div>
                <div class="adm-kpi-value"><?= $total_pedidos_mes ?></div>
                <div class="adm-kpi-sub">Pedidos procesados</div>
            </div>
            <div class="adm-kpi yellow">
                <div class="adm-kpi-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
                <div class="adm-kpi-label">Utilidad Estimada</div>
                <div class="adm-kpi-value">$<?= number_format($utilidad_mes, 0, ',', '.') ?></div>
                <div class="adm-kpi-sub">18% estimado</div>
            </div>
            <div class="adm-kpi <?= $total_productos_bajo > 0 ? 'red' : 'green' ?>">
                <div class="adm-kpi-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                    </svg>
                </div>
                <div class="adm-kpi-label">Stock Bajo</div>
                <div class="adm-kpi-value"><?= $total_productos_bajo ?></div>
                <div class="adm-kpi-sub">Productos ≤ 5 unidades</div>
            </div>
        </div>


        <!-- ── Gráficas ── -->
        <div class="adm-chart-grid">
            <div class="adm-card">
                <div class="adm-card-title"><span class="adm-card-title-text">Ventas por mes</span></div>
                <canvas id="chartVentasMes" height="140"></canvas>
            </div>
            <div class="adm-card">
                <div class="adm-card-title"><span class="adm-card-title-text">Ventas por día (últimos 15 días)</span>
                </div>
                <canvas id="chartVentasDia" height="140"></canvas>
            </div>
            <div class="adm-card">
                <div class="adm-card-title"><span class="adm-card-title-text">Pedidos por estado</span></div>
                <canvas id="chartEstados" height="140"></canvas>
            </div>
            <div class="adm-card">
                <div class="adm-card-title"><span class="adm-card-title-text">Top 10 más vendidos</span></div>
                <canvas id="chartMasVendidos" height="140"></canvas>
            </div>
        </div>

        <!-- ── Stock Bajo ── -->
        <div class="adm-card">
            <div class="adm-card-title">
                <span class="adm-card-title-text">Productos con stock bajo (≤ 5 unidades)</span>
                <a href="#" onclick="abrirModalMovimiento(event)" class="adm-btn adm-btn-warning"
                    style="font-size:0.75rem;padding:0.4rem 0.85rem">+ Reponer stock</a>
            </div>
            <?php if (!$productos_bajo): ?>
                <div class="adm-alert adm-alert-success" style="text-align:center;margin:0">✓ No hay productos con stock
                    bajo</div>
            <?php else: ?>
                <div class="adm-table-wrap">
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Stock</th>
                                <th>Categoría</th>
                                <th>Marca</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos_bajo as $p): ?>
                                <tr class="<?= $p['stock'] <= 0 ? 'row-danger' : 'row-warning' ?>">
                                    <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
                                    <td>
                                        <span class="adm-badge <?= $p['stock'] <= 0 ? 'adm-badge-red' : 'adm-badge-yellow' ?>">
                                            <?= $p['stock'] ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($p['categoria']) ?></td>
                                    <td><?= htmlspecialchars($p['marca']) ?></td>
                                    <td>
                                        <button type="button" onclick="abrirModalEditarProducto(<?= $p['id'] ?>, event)" class="adm-btn adm-btn-warning"
                                            style="font-size:0.72rem;padding:0.35rem 0.75rem">Editar</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Resumen Contable del Mes ── -->
        <div class="adm-card">
            <div class="adm-card-title">
                <span class="adm-card-title-text">Resumen Contable del Mes</span>
                <a href="reporte_contable.php" class="adm-btn adm-btn-blue">Ver reporte completo</a>
            </div>
            <div class="adm-kpi-grid" style="margin-bottom:1rem">
                <div class="adm-kpi green">
                    <div class="adm-kpi-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="adm-kpi-label">Ventas Netas</div>
                    <div class="adm-kpi-value" style="font-size:1.5rem;color:#22c55e">
                        $<?= number_format($ventas_netas_mes ?? 0, 0, ',', '.') ?></div>
                    <div class="adm-kpi-sub">Pedidos cobrados del mes</div>
                </div>
                <div class="adm-kpi yellow">
                    <div class="adm-kpi-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                    </div>
                    <div class="adm-kpi-label">Devoluciones</div>
                    <div class="adm-kpi-value" style="font-size:1.5rem;color:#eab308">
                        $<?= number_format($devoluciones_mes ?? 0, 0, ',', '.') ?></div>
                    <div class="adm-kpi-sub">Reembolsos aprobados</div>
                </div>
                <div class="adm-kpi blue">
                    <div class="adm-kpi-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    </div>
                    <div class="adm-kpi-label">Compras del Mes</div>
                    <div class="adm-kpi-value" style="font-size:1.5rem">
                        $<?= number_format($compras_mes ?? 0, 0, ',', '.') ?></div>
                    <div class="adm-kpi-sub">Entradas de inventario</div>
                </div>
            </div>
            <div class="adm-kpi-grid" style="margin-bottom:0">
                <div class="adm-kpi gray">
                    <div class="adm-kpi-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                    </div>
                    <div class="adm-kpi-label">IVA Cobrado (est.)</div>
                    <div class="adm-kpi-value" style="font-size:1.5rem">
                        $<?= number_format($iva_cobrado_mes ?? 0, 0, ',', '.') ?></div>
                    <div class="adm-kpi-sub">19% estimado sobre ventas</div>
                </div>
                <div class="adm-kpi green">
                    <div class="adm-kpi-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                    </div>
                    <div class="adm-kpi-label">Valor Inventario</div>
                    <div class="adm-kpi-value" style="font-size:1.5rem">
                        $<?= number_format($valor_inventario ?? 0, 0, ',', '.') ?></div>
                    <div class="adm-kpi-sub">Stock actual × precio venta</div>
                </div>
                <div class="adm-kpi red">
                    <div class="adm-kpi-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="adm-kpi-label">Pendiente de Cobro</div>
                    <div class="adm-kpi-value" style="font-size:1.5rem">
                        $<?= number_format($pedidos_pendientes ?? 0, 0, ',', '.') ?></div>
                    <div class="adm-kpi-sub">Pedidos sin pagar este mes</div>
                </div>
            </div>
        </div>

    </div><!-- /admin-content-inner -->
</main>

<script>
    const chartDefaults = {
        gridColor: 'rgba(255,255,255,0.04)',
        tickColor: '#666',
        font: { family: 'Inter, sans-serif', size: 11 }
    };

    const scaleOpts = {
        y: { beginAtZero: true, ticks: { color: chartDefaults.tickColor, font: chartDefaults.font }, grid: { color: chartDefaults.gridColor } },
        x: { ticks: { color: chartDefaults.tickColor, font: chartDefaults.font }, grid: { color: chartDefaults.gridColor } }
    };

    // Ventas por mes
    new Chart(document.getElementById('chartVentasMes'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_reverse(array_column($ventas_mes, 'mes'))) ?>,
            datasets: [{ label: 'Ventas ($)', data: <?= json_encode(array_reverse(array_map(fn($v) => (float) $v['total'], $ventas_mes))) ?>, backgroundColor: 'rgba(224,0,0,0.65)', borderColor: '#e00000', borderWidth: 1, borderRadius: 6 }]
        },
        options: { plugins: { legend: { display: false } }, scales: scaleOpts }
    });

    // Ventas por día
    new Chart(document.getElementById('chartVentasDia'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_reverse(array_column($ventas_dia, 'dia'))) ?>,
            datasets: [{ label: 'Ventas ($)', data: <?= json_encode(array_reverse(array_map(fn($v) => (float) $v['total'], $ventas_dia))) ?>, fill: true, backgroundColor: 'rgba(59,130,246,0.12)', borderColor: '#3b82f6', tension: 0.4, pointBackgroundColor: '#3b82f6', pointRadius: 3 }]
        },
        options: { plugins: { legend: { display: false } }, scales: scaleOpts }
    });

    // Pedidos por estado
    new Chart(document.getElementById('chartEstados'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($e) => ucfirst($e['estado']), $estados)) ?>,
            datasets: [{ data: <?= json_encode(array_map(fn($e) => (int) $e['cantidad'], $estados)) ?>, backgroundColor: ['rgba(224,0,0,0.75)', 'rgba(59,130,246,0.75)', 'rgba(234,179,8,0.75)', 'rgba(34,197,94,0.75)', 'rgba(124,58,237,0.75)', 'rgba(107,114,128,0.75)'], borderColor: 'rgba(20,20,20,0.5)', borderWidth: 2 }]
        },
        options: { plugins: { legend: { labels: { color: '#888', font: { size: 11, family: 'Inter' } } } }, cutout: '60%' }
    });

    // Top vendidos
    new Chart(document.getElementById('chartMasVendidos'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($mv) => $mv['nombre'], $mas_vendidos)) ?>,
            datasets: [{ label: 'Unidades', data: <?= json_encode(array_map(fn($mv) => (int) $mv['total_vendidos'], $mas_vendidos)) ?>, backgroundColor: 'rgba(234,179,8,0.65)', borderColor: '#eab308', borderWidth: 1, borderRadius: 6 }]
        },
        options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { y: { ticks: { color: '#666', font: { size: 10 } }, grid: { color: chartDefaults.gridColor } }, x: { ticks: { color: '#666' }, grid: { color: chartDefaults.gridColor }, beginAtZero: true } } }
    });
</script>

<?php include '_modal_movimiento.php'; ?>
<?php include '_layout_end.php'; ?>