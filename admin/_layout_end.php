<?php
/**
 * Footer compartido del Admin Layout
 * Cierra los divs abiertos por _layout.php y agrega el JS del sidebar
 */
?>
</div><!-- /admin-main -->
</div><!-- /admin-layout -->

<script>
    (function () {
        const sidebar = document.getElementById('admin-sidebar');
        const overlay = document.getElementById('admin-overlay');
        const btnHamburger = document.getElementById('btn-sidebar-toggle');
        const btnCollapse = document.getElementById('sidebar-collapse-toggle');
        const mainContent = document.getElementById('admin-main-content');
        const STORAGE_KEY = 'admin-sidebar-collapsed';

        /* ── Mobile drawer (hamburger) ── */
        function openDrawer() {
            sidebar.classList.add('open');
            overlay.classList.add('show');
            sidebar.classList.remove('closed');
        }

        function closeDrawer() {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            sidebar.classList.add('closed');
        }

        function toggleDrawer() {
            sidebar.classList.contains('open') ? closeDrawer() : openDrawer();
        }

        /* ── Desktop collapse (arrow) ── */
        function collapseSidebar() {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('sidebar-collapsed');
            if (btnCollapse) btnCollapse.classList.add('collapsed');
            localStorage.setItem(STORAGE_KEY, '1');
        }

        function expandSidebar() {
            sidebar.classList.remove('collapsed');
            mainContent.classList.remove('sidebar-collapsed');
            if (btnCollapse) btnCollapse.classList.remove('collapsed');
            localStorage.setItem(STORAGE_KEY, '0');
        }

        function toggleCollapse() {
            sidebar.classList.contains('collapsed') ? expandSidebar() : collapseSidebar();
        }

        /* ── Init ── */
        function initSidebar() {
            if (window.innerWidth >= 1024) {
                /* Desktop: always visible, restore collapse state */
                sidebar.classList.remove('closed');
                sidebar.classList.add('open');
                overlay.classList.remove('show');

                const saved = localStorage.getItem(STORAGE_KEY);
                if (saved === '1') {
                    collapseSidebar();
                } else {
                    expandSidebar();
                }
            } else {
                /* Mobile: hidden drawer */
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('sidebar-collapsed');
                closeDrawer();
            }
        }

        if (btnHamburger) btnHamburger.addEventListener('click', toggleDrawer);
        if (btnCollapse) btnCollapse.addEventListener('click', toggleCollapse);

        /* On mobile, nav links just navigate normally (page reloads).
           No need to close the drawer — the new page will load fresh. */

        /* Close drawer when clicking overlay */
        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (window.innerWidth < 1024) {
                    closeDrawer();
                }
            });
        }

        window.addEventListener('resize', initSidebar);
        document.addEventListener('DOMContentLoaded', initSidebar);
    })();
</script>

<!-- Utilidad de paginación universal -->
<script>
    function initPagination(containerSel, pagDivId, perPage, searchInputId) {
        perPage = perPage || 10;
        var container = document.querySelector(containerSel);
        if (!container) return;
        var isTable = container.tagName === 'TBODY';
        var allItems = Array.from(isTable ? container.querySelectorAll('tr') : container.children);
        var pagDiv = document.getElementById(pagDivId);
        if (!pagDiv) return;
        var filtered = allItems.slice();
        var page = 1;
        var uid = pagDivId;

        function render() {
            var total = Math.max(1, Math.ceil(filtered.length / perPage));
            if (page > total) page = total;
            var s = (page - 1) * perPage, e = s + perPage;
            allItems.forEach(function (r) { r.style.display = 'none'; });
            filtered.slice(s, e).forEach(function (r) { r.style.display = ''; });
            var h = '';
            
            // Botón Anterior
            h += '<button onclick="pagNav_' + uid + '(\'prev\')" class="adm-pag-btn ' + (page <= 1 ? 'disabled' : '') + '" title="Anterior">';
            h += '<svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg></button>';

            for (var i = 1; i <= total; i++) {
                if (total > 7 && i > 2 && i < total - 1 && Math.abs(i - page) > 1) {
                    if (i === 3 || i === total - 2) h += '<span style="color:#444;margin:0 4px">…</span>'; 
                    continue; 
                }
                h += '<button onclick="pagNav_' + uid + '(' + i + ')" class="adm-pag-btn ' + (i === page ? 'active' : '') + '">' + i + '</button>';
            }

            // Botón Siguiente
            h += '<button onclick="pagNav_' + uid + '(\'next\')" class="adm-pag-btn ' + (page >= total ? 'disabled' : '') + '" title="Siguiente">';
            h += '<svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg></button>';
            
            pagDiv.innerHTML = h;
        }

        window['pagNav_' + uid] = function (action) {
            var total = Math.max(1, Math.ceil(filtered.length / perPage));
            if (action === 'prev') page = Math.max(1, page - 1);
            else if (action === 'next') page = Math.min(total, page + 1);
            else page = parseInt(action);
            render();
        };

        if (searchInputId) {
            var inp = document.getElementById(searchInputId);
            if (inp) inp.addEventListener('input', function () {
                var q = this.value.toLowerCase().trim();
                filtered = allItems.filter(function (r) { return !q || r.textContent.toLowerCase().indexOf(q) >= 0; });
                page = 1;
                render();
            });
        }
        render();
    }
</script>

<!-- Modal universal de confirmación de eliminación -->
<div id="modal-confirm-del-bg" class="adm-modal-overlay"></div>
<div id="modal-confirm-del" class="adm-modal hidden">
    <div class="adm-modal-box" style="max-width:380px;text-align:center">
        <div
            style="width:56px;height:56px;background:rgba(239,68,68,.12);border:2px solid rgba(239,68,68,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.1rem">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                stroke-linejoin="round" style="width:26px;height:26px;stroke:#ef4444">
                <path
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
        </div>
        <div class="adm-modal-title" id="confirm-del-title" style="margin-bottom:.4rem;font-size:1.1rem">¿Eliminar?
        </div>
        <p id="confirm-del-msg" style="color:#888;font-size:.82rem;line-height:1.5;margin-bottom:1.4rem"></p>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="cerrarConfirmDel()" class="adm-btn"
                style="flex:1;justify-content:center">Cancelar</button>
            <a id="confirm-del-href" href="#" class="adm-btn adm-btn-danger" style="flex:1;justify-content:center">Sí,
                eliminar</a>
        </div>
    </div>
</div>
<script>
    function confirmarEliminar(href, nombre, tipo) {
        tipo = tipo || 'elemento';
        document.getElementById('confirm-del-title').textContent = '¿Eliminar ' + tipo + '?';
        document.getElementById('confirm-del-msg').textContent = '«' + nombre + '» será eliminado permanentemente. Esta acción no se puede deshacer.';
        document.getElementById('confirm-del-href').href = href;
        document.getElementById('modal-confirm-del-bg').classList.add('show');
        document.getElementById('modal-confirm-del').classList.remove('hidden');
        document.getElementById('modal-confirm-del').classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function cerrarConfirmDel() {
        document.getElementById('modal-confirm-del-bg').classList.remove('show');
        document.getElementById('modal-confirm-del').classList.add('hidden');
        document.getElementById('modal-confirm-del').classList.remove('show');
        document.body.style.overflow = '';
    }
    document.getElementById('modal-confirm-del-bg').addEventListener('click', cerrarConfirmDel);
</script>

<script>if (typeof lucide !== 'undefined') lucide.createIcons();</script>

<!-- Sistema de Toast Admin (reutilizable) -->


<script>
function admToast(message, type, duration, customTitle) {
    type = type || 'success';
    duration = duration || 4500;
    // Eliminar toasts anteriores
    document.querySelectorAll('.adm-toast').forEach(function(t){ t.remove(); });

    var iconSvg = type === 'success'
        ? '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
        : '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>';
    var title = customTitle ? customTitle : (type === 'success' ? 'Acción completada' : 'Ocurrió un error');

    var toast = document.createElement('div');
    toast.className = 'adm-toast adm-toast-' + type;
    toast.innerHTML =
        '<div class="at-icon">' + iconSvg + '</div>' +
        '<div class="at-body">' +
            '<div class="at-title">' + title + '</div>' +
            '<div class="at-msg">' + message + '</div>' +
        '</div>' +
        '<button class="at-close" onclick="this.parentElement.classList.add(\'adm-toast-hide\');setTimeout(function(){document.querySelectorAll(\'.adm-toast\').forEach(function(t){t.remove()})},320)">&#x2715;</button>' +
        '<div class="at-bar" style="animation:barShrink ' + duration + 'ms linear forwards"></div>';
    document.body.appendChild(toast);
    setTimeout(function(){
        toast.classList.add('adm-toast-hide');
        setTimeout(function(){ if(toast.parentNode) toast.remove(); }, 320);
    }, duration);
}
</script>

<script>
// Funciones globales para Editar Producto en Modal
async function abrirModalEditarProducto(id, event) {
    if (event) event.preventDefault();
    try {
        const res = await fetch('modal_producto_editar?id=' + id);
        const html = await res.text();
        let container = document.getElementById('modal-editar-producto-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'modal-editar-producto-container';
            document.body.appendChild(container);
        }
        container.innerHTML = html;
        document.getElementById('modal-edit-bg').classList.add('show');
        document.getElementById('modal-edit-producto').classList.remove('hidden');
        document.getElementById('modal-edit-producto').classList.add('show');
        document.body.style.overflow = 'hidden';
    } catch (e) {
        admToast('Error al cargar formulario de edición.', 'error');
    }
}

function cerrarModalEditarProducto() {
    const bg = document.getElementById('modal-edit-bg');
    const modal = document.getElementById('modal-edit-producto');
    if (bg) bg.classList.remove('show');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('show');
    }
    document.body.style.overflow = '';
}

async function guardarEdicionProducto(e, id) {
    e.preventDefault();
    const data = new FormData(e.target);
    try {
        const res = await fetch('modal_producto_editar?id=' + id, { method: 'POST', body: data });
        const result = await res.text();
        if (result.trim() === 'success') {
            window.location.href = window.location.pathname + '?editado=1';
        } else {
            const m = document.getElementById('modal-edit-msg');
            if (m) {
                m.innerHTML = result || 'Error crítico al guardar. Revisa los datos.';
                m.style.display = 'block';
            } else {
                admToast(result || 'Error crítico al guardar.', 'error');
            }
        }
    } catch (err) {
        admToast('Error de conexión persistente.', 'error');
    }
}
</script>

<?php if (!empty($_SESSION['admin_toast'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    admToast(
        '<?= addslashes($_SESSION['admin_toast']['msg']) ?>',
        '<?= $_SESSION['admin_toast']['type'] ?? 'success' ?>',
        <?= intval($_SESSION['admin_toast']['duration'] ?? 4500) ?>,
        <?= !empty($_SESSION['admin_toast']['title']) ? "'" . addslashes($_SESSION['admin_toast']['title']) . "'" : 'null' ?>
    );
});
</script>
<?php unset($_SESSION['admin_toast']); endif; ?>

<!-- Toast universal por URL params (aplica a TODAS las páginas admin) -->
<script>
document.addEventListener('DOMContentLoaded', function(){
    var params = new URLSearchParams(window.location.search);
    if (params.has('exito')) {
        admToast('Acción realizada exitosamente.', 'success', 5000);
    }
    if (params.has('editado')) {
        admToast('Registro editado exitosamente.', 'success', 5000);
    }
    if (params.has('eliminado')) {
        admToast('Registro eliminado exitosamente.', 'success', 5000);
    }
    if (params.has('error')) {
        var errorMsg = params.get('error') || 'Ocurrió un error procesando la solicitud.';
        admToast(decodeURIComponent(errorMsg), 'error', 6000);
    }
    // Limpiar la URL después de mostrar el toast (quita los params sin recargar)
    if (params.has('exito') || params.has('editado') || params.has('eliminado') || params.has('error')) {
        var cleanUrl = window.location.pathname;
        // Preservar params que NO son de toast (ej: estado=pagado)
        var keepParams = new URLSearchParams();
        params.forEach(function(val, key){
            if (!['exito','editado','eliminado','error'].includes(key)) {
                keepParams.set(key, val);
            }
        });
        var qs = keepParams.toString();
        history.replaceState(null, '', cleanUrl + (qs ? '?' + qs : ''));
    }
});
</script>

<!-- Notificaciones en vivo de pedidos nuevos -->
<script>
    (function () {
        // Solo corre si estamos en el panel de admin (asegurado por PHP pero no esta de mas
        function checkNewOrders() {
            fetch('api_check_orders.php')
                .then(res => res.json())
                .then(data => {
                    if (data && data.nuevos && data.nuevos.length > 0) {
                        data.nuevos.forEach(pedido => {
                            admToast(
                                `¡A las armas! Nuevo pedido de ${pedido.nombre} por $${Number(pedido.total).toLocaleString('es-CO')} COP`,
                                'success',
                                8000
                            );
                            // Opcionalmente recargar la página si estamos en pedidos.php
                            if (window.location.pathname.includes('pedidos.php')) {
                                setTimeout(() => window.location.reload(), 2000);
                            }
                        });
                    }
                })
                .catch(err => console.error('Error revisando pedidos:', err));
        }

        // Revisar cada 15 segundos
        setInterval(checkNewOrders, 15000);
        
        // Primera revision al cargar la página a los 3 segundos
        setTimeout(checkNewOrders, 3000);
    })();
</script>

<!-- Admin Particle Background -->
<script>
    (function () {
        const canvas = document.querySelector('.admin-particles-canvas');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        let W, H;

        function resize() {
            W = canvas.width = window.innerWidth;
            H = canvas.height = window.innerHeight;
        }
        resize();
        window.addEventListener('resize', resize);

        const PARTICLE_COUNT = 80;
        const particles = [];

        for (let i = 0; i < PARTICLE_COUNT; i++) {
            particles.push({
                x: Math.random() * (W || 1920),
                y: Math.random() * (H || 1080),
                r: Math.random() * 2.5 + 0.5,
                vx: (Math.random() - 0.5) * 0.3,
                vy: (Math.random() - 0.5) * 0.3,
                alpha: Math.random() * 0.25 + 0.08,
                pulse: Math.random() * Math.PI * 2,
                pulseSpeed: Math.random() * 0.01 + 0.005
            });
        }

        const LINE_DIST = 150;

        function draw() {
            ctx.clearRect(0, 0, W, H);

            // Draw connection lines
            for (let i = 0; i < particles.length; i++) {
                for (let j = i + 1; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x;
                    const dy = particles[i].y - particles[j].y;
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < LINE_DIST) {
                        const lineAlpha = (1 - dist / LINE_DIST) * 0.07;
                        ctx.beginPath();
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.strokeStyle = 'rgba(200, 200, 210, ' + lineAlpha + ')';
                        ctx.lineWidth = 0.6;
                        ctx.stroke();
                    }
                }
            }

            // Draw particles with subtle pulse
            for (let i = 0; i < particles.length; i++) {
                const p = particles[i];
                p.x += p.vx;
                p.y += p.vy;
                p.pulse += p.pulseSpeed;

                // Wrap around
                if (p.x < -5) p.x = W + 5;
                if (p.x > W + 5) p.x = -5;
                if (p.y < -5) p.y = H + 5;
                if (p.y > H + 5) p.y = -5;

                const pulseAlpha = p.alpha + Math.sin(p.pulse) * 0.06;

                // Soft glow
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r * 2.5, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(160, 165, 175, ' + (pulseAlpha * 0.15) + ')';
                ctx.fill();

                // Core dot
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(190, 195, 205, ' + pulseAlpha + ')';
                ctx.fill();
            }

            requestAnimationFrame(draw);
        }
        draw();
    })();
</script>

</body>

</html>