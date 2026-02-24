<?php
session_start();
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

<style>
/* ── Estilos de la página de resultado PayPal ──────── */
.paypal-hero {
    position: relative;
    min-height: 70vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4rem 1rem;
    overflow: hidden;
}

.paypal-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 60% 50% at 50% 30%,
        <?= $esExito ? 'rgba(0,200,80,0.12)' : 'rgba(255,180,0,0.12)' ?> 0%, transparent 70%);
    pointer-events: none;
}

/* Partículas canvas */
.paypal-hero canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
}

.paypal-card {
    position: relative;
    z-index: 1;
    background: rgba(20, 20, 20, 0.75);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 1.5rem;
    padding: 3rem 2.5rem;
    max-width: 520px;
    width: 100%;
    text-align: center;
    box-shadow: 0 25px 60px rgba(0,0,0,0.4);
    animation: cardPop 0.5s cubic-bezier(0.22,1,0.36,1) forwards;
    opacity: 0;
}

@keyframes cardPop {
    from { opacity: 0; transform: translateY(30px) scale(0.96); }
    to   { opacity: 1; transform: translateY(0)    scale(1); }
}

/* Línea decorativa superior */
.paypal-card::before {
    content: '';
    position: absolute;
    top: 0; left: 15%; right: 15%;
    height: 2px;
    background: linear-gradient(90deg, transparent,
        <?= $esExito ? '#22c55e' : '#eab308' ?>, transparent);
    border-radius: 0 0 4px 4px;
}

/* Ícono grande animado */
.paypal-icon {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    margin: 0 auto 1.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: iconPulse 2s ease-in-out infinite;
}

.paypal-icon.success {
    background: rgba(34, 197, 94, 0.12);
    border: 2px solid rgba(34, 197, 94, 0.35);
    color: #22c55e;
}

.paypal-icon.warning {
    background: rgba(234, 179, 8, 0.12);
    border: 2px solid rgba(234, 179, 8, 0.35);
    color: #eab308;
}

@keyframes iconPulse {
    0%, 100% { box-shadow: 0 0 0 0 <?= $esExito ? 'rgba(34,197,94,0.25)' : 'rgba(234,179,8,0.25)' ?>; }
    50%       { box-shadow: 0 0 0 16px transparent; }
}

.paypal-icon svg {
    width: 44px;
    height: 44px;
    animation: iconDraw 0.6s ease-out 0.3s forwards;
    stroke-dasharray: 100;
    stroke-dashoffset: 100;
}

@keyframes iconDraw {
    to { stroke-dashoffset: 0; }
}

.paypal-title {
    font-size: 1.75rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    letter-spacing: -0.02em;
}

.paypal-title.success { color: #22c55e; }
.paypal-title.warning { color: #eab308; }

.paypal-subtitle {
    color: #888;
    font-size: 1rem;
    margin-bottom: 0.5rem;
    line-height: 1.6;
}

.paypal-pedido-id {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #666;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 9999px;
    padding: 6px 16px;
    margin-bottom: 2rem;
}

.paypal-pedido-id span {
    color: <?= $esExito ? '#22c55e' : '#eab308' ?>;
    font-weight: 700;
}

/* Detalles del pedido */
.paypal-details {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.05);
    border-radius: 0.75rem;
    padding: 1rem 1.25rem;
    margin-bottom: 2rem;
    text-align: left;
}

.paypal-detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}

.paypal-detail-row:last-child { border-bottom: none; }

.paypal-detail-label {
    color: #666;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.paypal-detail-value {
    color: #e7e7ea;
    font-size: 0.9rem;
    font-weight: 500;
}

/* Botones */
.paypal-btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 0.875rem 1.5rem;
    background: linear-gradient(135deg, #cc0000, #ff0000);
    color: #fff;
    font-weight: 700;
    font-size: 0.95rem;
    border: none;
    border-radius: 0.75rem;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.25s ease;
    box-shadow: 0 4px 20px rgba(255,0,0,0.25);
    margin-bottom: 0.75rem;
}

.paypal-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(255,0,0,0.4);
}

.paypal-btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 0.75rem 1.5rem;
    background: transparent;
    color: #888;
    font-weight: 600;
    font-size: 0.85rem;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 0.75rem;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.25s ease;
}

.paypal-btn-secondary:hover {
    border-color: rgba(255,0,0,0.3);
    color: #ff0000;
}

/* Confetti animation (solo en éxito) */
<?php if ($esExito): ?>
.confetti-piece {
    position: absolute;
    width: 8px;
    height: 8px;
    border-radius: 2px;
    animation: confettiFall linear forwards;
    pointer-events: none;
    z-index: 0;
}

@keyframes confettiFall {
    0%   { opacity: 1; transform: translateY(-20px) rotate(0deg); }
    100% { opacity: 0; transform: translateY(400px) rotate(720deg); }
}
<?php endif; ?>

@media (max-width: 480px) {
    .paypal-card { padding: 2rem 1.5rem; }
    .paypal-title { font-size: 1.4rem; }
    .paypal-icon { width: 72px; height: 72px; }
    .paypal-icon svg { width: 36px; height: 36px; }
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
                        <span class="paypal-detail-value" style="color:#22c55e;">✓ Pagado</span>
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
                        <span class="paypal-detail-value" style="color:#eab308;">⚠ Pendiente</span>
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