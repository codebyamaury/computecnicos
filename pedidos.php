<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php?login=1');
    exit;
}
require_once __DIR__ . '/app/Core/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$id_usuario = $_SESSION['usuario']['id'];
// Obtener pedidos del usuario
$stmt = $pdo->prepare('SELECT * FROM pedidos WHERE id_usuario = ? ORDER BY fecha DESC');
$stmt->execute([$id_usuario]);
$pedidos = $stmt->fetchAll();

// Obtener detalles de todos los pedidos
$detalles = [];
if ($pedidos) {
    $ids = array_column($pedidos, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare('SELECT d.*, p.nombre, p.imagen FROM detalle_pedido d JOIN productos p ON d.id_producto = p.id WHERE d.id_pedido IN (' . $in . ')');
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $detalles[$row['id_pedido']][] = $row;
    }
}

$page_title = 'Mis Pedidos - Computécnicos';
$extra_css = '<link rel="stylesheet" href="' . asset('css/index.css') . '">' . "\n" .
             '<link rel="stylesheet" href="' . asset('css/pedidos.css') . '">';
include 'includes/header.php';

$labels = [
    'pendiente'   => 'Pendiente',
    'pagado'      => 'Pagado',
    'preparacion' => 'En preparación',
    'enviado'     => 'En camino',
    'entregado'   => 'Entregado',
    'cancelado'   => 'Cancelado'
];
?>

<main class="flex-1 relative z-10">
    <!-- ═══ HERO ═══ -->
    <section class="pedidos-hero">
        <canvas id="pedidos-particles"></canvas>
        <h1 class="pedidos-title">Mis Pedidos</h1>
        <p class="pedidos-subtitle">Historial y seguimiento de tus compras</p>
    </section>

    <!-- ═══ CONTENIDO ═══ -->
    <section class="pedidos-container">
        <?php if (!$pedidos): ?>
            <div class="pedidos-empty">
                <div class="pedidos-empty-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                    </svg>
                </div>
                <h2 class="pedidos-empty-title">No tienes pedidos aún</h2>
                <p class="pedidos-empty-text">Cuando realices tu primera compra, aparecerá aquí.</p>
                <a href="productos.php" class="pedidos-empty-btn">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                    Explorar productos
                </a>
            </div>
        <?php else: ?>
            <div class="pedidos-list">
                <?php foreach ($pedidos as $pedido):
                    // State class
                    $state_class = 'state-default';
                    switch($pedido['estado']) {
                        case 'pendiente': $state_class = 'state-pending'; break;
                        case 'pagado': $state_class = 'state-paid'; break;
                        case 'preparacion': $state_class = 'state-prep'; break;
                        case 'enviado': $state_class = 'state-shipped'; break;
                        case 'entregado': $state_class = 'state-delivered'; break;
                        case 'cancelado': $state_class = 'state-cancelled'; break;
                    }

                    // Tracking
                    $estado_actual = $pedido['estado'];
                    $hist = $pdo->prepare('SELECT estado, fecha, comentario FROM pedido_estados WHERE id_pedido = ? ORDER BY fecha ASC');
                    $hist->execute([$pedido['id']]);
                    $historial = $hist->fetchAll(PDO::FETCH_ASSOC);

                    $etapas = [
                        'pendiente'   => ['titulo' => 'Pedido recibido',  'icon' => 'M8 4a2 2 0 012-2h4a2 2 0 012 2h1a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2zm8 4H6v8h10V8zm-8-4v2h4V4H8z'],
                        'pagado'      => ['titulo' => 'Pagado',           'icon' => 'M4 6h16a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2zm0 4h16v2H4v-2zm3 4h4v2H7v-2z'],
                        'preparacion' => ['titulo' => 'En preparación',   'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
                        'enviado'     => ['titulo' => 'En camino',        'icon' => 'M3 7h10v6h5l-2-3h-3V7h4l3 4v5h-3a2 2 0 11-4 0H9a2 2 0 11-4 0H3V7zm4 7a1 1 0 102 0 1 1 0 00-2 0zm8 0a1 1 0 102 0 1 1 0 00-2 0z'],
                        'entregado'   => ['titulo' => 'Entregado',        'icon' => 'M10 17l-4-4 1.414-1.414L10 14.172l6.586-6.586L18 9l-8 8z'],
                    ];
                    $orden = ['pendiente','pagado','preparacion','enviado','entregado'];
                    $indice_actual = array_search($estado_actual, $orden);
                    if ($indice_actual === false) { $indice_actual = 0; }
                    $porcentaje = intval(($indice_actual) / (count($orden)-1) * 100);
                    if ($estado_actual === 'entregado') { $porcentaje = 100; }

                    $ahora = new DateTime();
                    $estimado = '';
                    if ($estado_actual === 'pendiente') { $tmp = clone $ahora; $tmp->modify('+3 days'); $estimado = 'Est. entrega ' . $tmp->format('d/m H:i'); }
                    elseif ($estado_actual === 'pagado') { $tmp = clone $ahora; $tmp->modify('+2 days'); $estimado = 'Est. entrega ' . $tmp->format('d/m H:i'); }
                    elseif ($estado_actual === 'preparacion') { $tmp = clone $ahora; $tmp->modify('+36 hours'); $estimado = 'Est. entrega ' . $tmp->format('d/m H:i'); }
                    elseif ($estado_actual === 'enviado') { $tmp = clone $ahora; $tmp->modify('+1 day'); $estimado = 'Est. entrega ' . $tmp->format('d/m H:i'); }
                    elseif ($estado_actual === 'entregado') { $estimado = '✓ Entregado'; }
                ?>
                <div class="order-card">
                    <!-- HEADER -->
                    <div class="order-header">
                        <div class="order-header-left">
                            <span class="order-id">Pedido #<?= $pedido['id'] ?></span>
                            <span class="order-date"><?= date('d/m/Y H:i', strtotime($pedido['fecha'])) ?></span>
                            <span class="order-state-badge <?= $state_class ?>"><?= $labels[$pedido['estado']] ?? ucfirst($pedido['estado']) ?></span>
                        </div>
                        <span class="order-total">$<?= number_format($pedido['total'], 0, ',', '.') ?> COP</span>
                    </div>

                    <!-- BODY -->
                    <div class="order-body">
                        <?php if (in_array($pedido['estado'], ['pagado','preparacion','enviado','entregado'])): ?>
                        <a href="<?= base_url() ?>/factura_pdf.php?id=<?= $pedido['id'] ?>&download=1" target="_blank" rel="noopener" class="btn-factura">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Descargar factura
                        </a>
                        <?php endif; ?>

                        <!-- Tabla de productos -->
                        <table class="order-table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Precio unitario</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detalles[$pedido['id']] ?? [] as $detalle): ?>
                                <tr>
                                    <td>
                                        <div class="order-product-cell">
                                            <img src="<?= htmlspecialchars($detalle['imagen'] ?? 'https://via.placeholder.com/60x40?text=Prod') ?>" alt="<?= htmlspecialchars($detalle['nombre'] ?? '') ?>" class="order-product-img">
                                            <span class="order-product-name"><?= htmlspecialchars($detalle['nombre'] ?? '') ?></span>
                                        </div>
                                    </td>
                                    <td><?= $detalle['cantidad'] ?></td>
                                    <td class="order-price">$<?= number_format($detalle['precio_unitario'], 0, ',', '.') ?></td>
                                    <td class="order-price">$<?= number_format($detalle['precio_unitario'] * $detalle['cantidad'], 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Tracking -->
                        <?php if ($estado_actual !== 'cancelado'): ?>
                        <div class="statusbar-wrap">
                            <div class="status-meta">
                                <span class="pill">Progreso: <?= $porcentaje ?>%</span>
                                <span class="pill"><?= $estimado ?></span>
                            </div>

                            <div class="statusbar-track">
                                <div class="statusbar-progress" style="width: <?= $porcentaje ?>%"></div>
                            </div>

                            <div class="statusbar-steps">
                                <?php foreach ($orden as $i => $clave):
                                    $step_class = 'status-step';
                                    if ($i < $indice_actual) $step_class .= ' completed';
                                    if ($i === $indice_actual) $step_class .= ' current';
                                    $fecha_etapa = '';
                                    foreach ($historial as $h) { if ($h['estado'] === $clave) { $fecha_etapa = date('d/m/Y H:i', strtotime($h['fecha'])); break; } }
                                ?>
                                <button class="<?= $step_class ?>" type="button" onclick="toggleDetails('<?= $pedido['id'] ?>')">
                                    <svg class="icon" viewBox="0 0 20 20" fill="currentColor"><path d="<?= $etapas[$clave]['icon'] ?>"/></svg>
                                    <span class="step-title"><?= $etapas[$clave]['titulo'] ?></span>
                                    <span class="step-date"><?= $fecha_etapa ?: '—' ?></span>
                                </button>
                                <?php endforeach; ?>
                            </div>

                            <div class="status-meta">
                                <button type="button" class="pill" onclick="toggleDetails('<?= $pedido['id'] ?>')">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:12px;height:12px;display:inline;vertical-align:middle;margin-right:2px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Ver historial
                                </button>
                            </div>

                            <div class="status-details" id="details-<?= $pedido['id'] ?>">
                                <?php if (!empty($historial)): ?>
                                    <?php foreach ($historial as $h): ?>
                                    <div class="history-item">
                                        <span class="history-dot"></span>
                                        <strong><?= $labels[$h['estado']] ?? ucfirst($h['estado']) ?></strong> —
                                        <?= date('d/m/Y H:i', strtotime($h['fecha'])) ?>
                                        <?php if ($h['comentario']): ?>
                                            <span style="color:#666;margin-left:4px">(<?= htmlspecialchars($h['comentario'] ?? '') ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="color:#555">Sin eventos registrados.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Número de guía -->
                        <?php if (isset($pedido['numero_guia']) && $pedido['numero_guia']): ?>
                        <div class="order-tracking-box">
                            <div class="order-tracking-label">Número de guía</div>
                            <div class="order-tracking-value">
                                <span class="order-tracking-code"><?= htmlspecialchars($pedido['numero_guia'] ?? '') ?></span>
                                <button class="btn-copy" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($pedido['numero_guia'] ?? '', ENT_QUOTES, 'UTF-8') ?>');this.textContent='✓ Copiado';setTimeout(()=>this.textContent='Copiar',1500)">Copiar</button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Cancelado -->
                        <?php if ($pedido['estado'] === 'cancelado'): ?>
                        <div class="order-cancelled-notice">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px;flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                            Este pedido fue cancelado.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>

<script>
function toggleDetails(id) {
    const box = document.getElementById('details-' + id);
    if (box) box.classList.toggle('show');
}

// Partículas del hero
(function() {
    const canvas = document.getElementById('pedidos-particles');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    function resize() { canvas.width = canvas.offsetWidth; canvas.height = canvas.offsetHeight; }
    resize();
    window.addEventListener('resize', resize);

    const particles = Array.from({ length: 40 }, () => ({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        r: Math.random() * 1.4 + 0.3,
        vx: (Math.random() - 0.5) * 0.25,
        vy: (Math.random() - 0.5) * 0.25,
        alpha: Math.random() * 0.35 + 0.08,
        red: Math.random() > 0.7,
    }));

    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(p => {
            p.x += p.vx; p.y += p.vy;
            if (p.x < 0) p.x = canvas.width;
            if (p.x > canvas.width) p.x = 0;
            if (p.y < 0) p.y = canvas.height;
            if (p.y > canvas.height) p.y = 0;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = p.red
                ? `rgba(255,0,0,${p.alpha})`
                : `rgba(255,255,255,${p.alpha * 0.5})`;
            ctx.fill();
        });
        requestAnimationFrame(draw);
    }
    draw();
})();
</script>
</body>
</html>