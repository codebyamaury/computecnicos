<?php
session_start();
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

$compras_mes = $pdo->query("SELECT SUM(precio_unitario * cantidad) as total FROM movimientos_inventario WHERE tipo = 'entrada' AND precio_unitario IS NOT NULL AND fecha >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
$iva_pagado = $pdo->query("SELECT SUM(iva) as total FROM movimientos_inventario WHERE tipo = 'entrada' AND iva IS NOT NULL AND fecha >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();
$valor_inventario = $pdo->query("SELECT SUM(p.stock * COALESCE(avg_precio.precio_promedio, 0)) as total FROM productos p LEFT JOIN (SELECT id_producto, AVG(precio_unitario) as precio_promedio FROM movimientos_inventario WHERE tipo = 'entrada' AND precio_unitario IS NOT NULL GROUP BY id_producto) avg_precio ON p.id = avg_precio.id_producto")->fetchColumn();

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
    <style>
        .admin-content::before {
            content: '';
            position: fixed;
            top: 0;
            left: var(--adm-sidebar-w, 220px);
            right: 0;
            bottom: 0;
            background:
                radial-gradient(ellipse 60% 40% at 50% 0%, rgba(224, 0, 0, 0.045) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .admin-content>* {
            position: relative;
            z-index: 1;
        }
    </style>
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

        <!-- ── Accesos Rápidos ── -->
        <div class="adm-quick-grid" style="margin-bottom:1.75rem">
            <a href="productos.php" class="adm-quick-card">
                <div class="adm-quick-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <div class="adm-quick-name">Productos</div>
                <div class="adm-quick-desc">Gestión de catálogo</div>
            </a>
            <a href="pedidos.php" class="adm-quick-card">
                <div class="adm-quick-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>
                <div class="adm-quick-name">Pedidos</div>
                <div class="adm-quick-desc">Facturación y ventas</div>
            </a>
            <a href="inventario.php" class="adm-quick-card">
                <div class="adm-quick-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                    </svg>
                </div>
                <div class="adm-quick-name">Inventario</div>
                <div class="adm-quick-desc">Movimientos y stock</div>
            </a>
            <a href="usuarios.php" class="adm-quick-card">
                <div class="adm-quick-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div class="adm-quick-name">Usuarios</div>
                <div class="adm-quick-desc">Gestión de cuentas</div>
            </a>
            <a href="proveedores.php" class="adm-quick-card">
                <div class="adm-quick-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <div class="adm-quick-name">Proveedores</div>
                <div class="adm-quick-desc">Gestión de proveedores</div>
            </a>
            <a href="reporte_contable.php" class="adm-quick-card">
                <div class="adm-quick-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div class="adm-quick-name">Reportes</div>
                <div class="adm-quick-desc">Legal y contable</div>
            </a>
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
                <a href="inventario_nuevo.php" class="adm-btn adm-btn-warning"
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
                                        <a href="producto_editar.php?id=<?= $p['id'] ?>" class="adm-btn adm-btn-warning"
                                            style="font-size:0.72rem;padding:0.35rem 0.75rem">Editar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Reporte Contable ── -->
        <div class="adm-card">
            <div class="adm-card-title">
                <span class="adm-card-title-text">Resumen Contable del Mes</span>
                <a href="reporte_contable.php" class="adm-btn adm-btn-blue">Ver reporte completo</a>
            </div>
            <div class="adm-kpi-grid" style="margin-bottom:0">
                <div class="adm-kpi green">
                    <div class="adm-kpi-label">Compras del Mes</div>
                    <div class="adm-kpi-value" style="font-size:1.5rem">
                        $<?= number_format($compras_mes ?? 0, 0, ',', '.') ?></div>
                    <div class="adm-kpi-sub">Entradas de inventario</div>
                </div>
                <div class="adm-kpi blue">
                    <div class="adm-kpi-label">IVA Pagado</div>
                    <div class="adm-kpi-value" style="font-size:1.5rem">
                        $<?= number_format($iva_pagado ?? 0, 0, ',', '.') ?></div>
                    <div class="adm-kpi-sub">IVA en compras del mes</div>
                </div>
                <div class="adm-kpi yellow">
                    <div class="adm-kpi-label">Valor Inventario</div>
                    <div class="adm-kpi-value" style="font-size:1.5rem">
                        $<?= number_format($valor_inventario ?? 0, 0, ',', '.') ?></div>
                    <div class="adm-kpi-sub">Precio promedio de compra</div>
                </div>
            </div>
            <div style="margin-top:1rem;font-size:0.75rem;color:#444;line-height:1.7">
                <p>• <strong style="color:#666">Reporte General:</strong> Todos los movimientos de inventario con
                    información contable</p>
                <p>• <strong style="color:#666">Reporte de Compras:</strong> Solo entradas con datos de proveedores,
                    facturas e impuestos</p>
                <p>• <strong style="color:#666">Inventario Valorizado:</strong> Stock actual valorizado al precio
                    promedio de compra</p>
                <p>• <strong style="color:#666">Exportación Excel:</strong> Reportes profesionales para contabilidad y
                    auditoría</p>
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

<?php include '_layout_end.php'; ?>