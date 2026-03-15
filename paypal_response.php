<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/app/Core/bootstrap.php';

$estado    = $_GET['status'] ?? ($_GET['estado'] ?? 'UNKNOWN');
$pedido_id = (int)($_GET['pedido_id'] ?? 0);

// Si el pago fue exitoso, vaciar carrito
if ($estado === 'APPROVED' || $estado === 'COMPLETED') {
    if (isset($_SESSION['carrito'])) {
        $_SESSION['carrito'] = [];
    }
}

$esExito = ($estado === 'APPROVED' || $estado === 'COMPLETED');

$page_title = ($esExito ? '¡Pago Exitoso!' : 'Pago no completado') . ' - Computécnicos';
$extra_css = '<link rel="stylesheet" href="' . asset('css/index.css') . '">';
include 'includes/header.php';
?>

    <link rel="stylesheet" href="<?= asset('css/paypal.css') ?>">
    <style>
        :root {
            --paypal-accent: <?= $esExito ? '#22c55e' : '#eab308' ?>;
            --paypal-glow: <?= $esExito ? 'rgba(0,200,80,0.12)' : 'rgba(255,180,0,0.12)' ?>;
            --paypal-shadow: <?= $esExito ? 'rgba(34,197,94,0.25)' : 'rgba(234,179,8,0.25)' ?>;
        }
    </style>

<main class="flex-1 relative z-10">
    <section class="paypal-hero">
        <canvas id="paypal-particles"></canvas>

        <div class="paypal-card">
            <?php if ($esExito): ?>
                <!-- ═══ PAGO EXITOSO ═══ -->
                <div class="paypal-icon success">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h1 class="paypal-title success">¡Pago Exitoso!</h1>
                <p class="paypal-subtitle">Tu compra ha sido procesada correctamente.</p>

                <?php if ($pedido_id): ?>
                <div class="paypal-pedido-id">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Pedido <span>#<?= $pedido_id ?></span>
                </div>

                <div class="paypal-details">
                    <div class="paypal-detail-row">
                        <span class="paypal-detail-label">Estado</span>
                        <span class="paypal-detail-value text-green-500">✓ Pagado</span>
                    </div>
                    <div class="paypal-detail-row">
                        <span class="paypal-detail-label">Método</span>
                        <span class="paypal-detail-value">PayPal</span>
                    </div>
                    <div class="paypal-detail-row">
                        <span class="paypal-detail-label">Fecha</span>
                        <span class="paypal-detail-value"><?= date('d/m/Y H:i') ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <a href="pedidos.php" class="paypal-btn-primary">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Ver mis pedidos
                </a>
                <a href="productos.php" class="paypal-btn-secondary">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                    Seguir comprando
                </a>

            <?php else: ?>
                <!-- ═══ PAGO NO COMPLETADO ═══ -->
                <div class="paypal-icon warning">
                    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                </div>
                <h1 class="paypal-title warning">Pago no completado</h1>
                <p class="paypal-subtitle">No pudimos procesar tu pago. No se ha realizado ningún cargo a tu cuenta.</p>

                <div class="paypal-details">
                    <div class="paypal-detail-row">
                        <span class="paypal-detail-label">Estado</span>
                        <span class="paypal-detail-value text-yellow-500">⚠ Pendiente</span>
                    </div>
                    <div class="paypal-detail-row">
                        <span class="paypal-detail-label">Motivo</span>
                        <span class="paypal-detail-value">Pago cancelado o rechazado</span>
                    </div>
                </div>

                <a href="checkout.php" class="paypal-btn-primary">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Intentar de nuevo
                </a>
                <a href="productos.php" class="paypal-btn-secondary">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Volver a productos
                </a>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>

<script>
// ── Partículas flotantes ─────────────────────────────────────────────────
(function() {
    const canvas = document.getElementById('paypal-particles');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    function resize() { canvas.width = canvas.offsetWidth; canvas.height = canvas.offsetHeight; }
    resize();
    window.addEventListener('resize', resize);

    const isSuccess = <?= $esExito ? 'true' : 'false' ?>;
    const particles = Array.from({ length: 50 }, () => ({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        r: Math.random() * 1.6 + 0.3,
        vx: (Math.random() - 0.5) * 0.3,
        vy: (Math.random() - 0.5) * 0.3,
        alpha: Math.random() * 0.4 + 0.1,
        colored: Math.random() > 0.65,
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
            if (p.colored) {
                ctx.fillStyle = isSuccess
                    ? `rgba(34,197,94,${p.alpha})`
                    : `rgba(234,179,8,${p.alpha})`;
            } else {
                ctx.fillStyle = `rgba(255,255,255,${p.alpha * 0.5})`;
            }
            ctx.fill();
        });
        requestAnimationFrame(draw);
    }
    draw();
})();

<?php if ($esExito): ?>
// ── Confetti burst ───────────────────────────────────────────────────────
(function() {
    const hero = document.querySelector('.paypal-hero');
    const colors = ['#ff0000','#22c55e','#3b82f6','#eab308','#a855f7','#f43f5e'];
    for (let i = 0; i < 40; i++) {
        const piece = document.createElement('div');
        piece.className = 'confetti-piece';
        piece.style.left = Math.random() * 100 + '%';
        piece.style.top  = Math.random() * 30 + '%';
        piece.style.background = colors[Math.floor(Math.random() * colors.length)];
        piece.style.animationDuration = (Math.random() * 2 + 1.5) + 's';
        piece.style.animationDelay    = (Math.random() * 0.8) + 's';
        piece.style.width  = (Math.random() * 6 + 4) + 'px';
        piece.style.height = (Math.random() * 6 + 4) + 'px';
        hero.appendChild(piece);
    }
})();
<?php endif; ?>
</script>
</body>
</html>