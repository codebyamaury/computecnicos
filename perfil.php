<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/app/Core/bootstrap.php';
if (!isset($_SESSION['usuario'])) {
    header('Location: index.php?login=1');
    exit;
}
$usuario_id = $_SESSION['usuario']['id'];

// Obtener datos actuales
$stmt = $pdo->prepare('SELECT nombre, email, telefono, direccion, foto, fecha_registro, password FROM usuarios WHERE id = ?');
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    echo '<div class="max-w-xl mx-auto mt-10 p-6 bg-red-900 text-white rounded-xl text-center text-lg font-bold">No se encontró el usuario.</div>';
    exit;
}

// ── Procesar formularios AJAX ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'seguridad') {
        $actual    = $_POST['password_actual']   ?? '';
        $nueva     = $_POST['password_nueva']    ?? '';
        $confirmar = $_POST['password_confirmar'] ?? '';
        if (!$actual || !$nueva || !$confirmar) {
            echo json_encode(['ok' => false, 'msg' => 'Todos los campos son obligatorios.']); exit;
        }
        if (!password_verify($actual, $usuario['password'])) {
            echo json_encode(['ok' => false, 'msg' => 'La contraseña actual es incorrecta.']); exit;
        }
        if (strlen($nueva) < 6) {
            echo json_encode(['ok' => false, 'msg' => 'La nueva contraseña debe tener al menos 6 caracteres.']); exit;
        }
        if ($nueva !== $confirmar) {
            echo json_encode(['ok' => false, 'msg' => 'Las contraseñas nuevas no coinciden.']); exit;
        }
        $hash = password_hash($nueva, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE usuarios SET password=? WHERE id=?')->execute([$hash, $usuario_id]);
        // Invalidar todos los tokens Remember Me del usuario (cierra sesion en otros dispositivos)
        $rememberMe->invalidateAllTokens($usuario_id);
        // Crear nuevo token para el dispositivo actual
        $rememberMe->createToken($usuario_id);
        echo json_encode(['ok' => true, 'msg' => 'Contraseña actualizada correctamente. Se cerró la sesión en otros dispositivos.']); exit;

    } elseif ($accion === 'personal') {
        $nombre   = trim($_POST['nombre']   ?? '');
        $email    = trim($_POST['email']    ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        if (!$nombre || !$email) {
            echo json_encode(['ok' => false, 'msg' => 'Nombre y correo son obligatorios.']); exit;
        }
        $foto_url = $usuario['foto'];
        if (!empty($_FILES['foto']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp']) && $_FILES['foto']['size'] <= 2*1024*1024) {
                if (!empty($usuario['foto']) && strpos($usuario['foto'], 'uploads/') === 0) {
                    $old = __DIR__ . '/' . $usuario['foto'];
                    if (is_file($old)) @unlink($old);
                }
                $nombre_archivo = uniqid('profile_') . '.' . $ext;
                $destino = 'uploads/profiles/' . $nombre_archivo;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                    $foto_url = $destino;
                }
            }
        }
        $pdo->prepare('UPDATE usuarios SET nombre=?, email=?, telefono=?, foto=? WHERE id=?')
            ->execute([$nombre, $email, $telefono, $foto_url, $usuario_id]);
        $_SESSION['usuario']['nombre'] = $nombre;
        $_SESSION['usuario']['email']  = $email;
        $_SESSION['usuario']['foto']   = $foto_url;
        echo json_encode(['ok' => true, 'msg' => 'Datos actualizados correctamente.', 'foto' => $foto_url, 'nombre' => $nombre, 'email' => $email]); exit;

    } elseif ($accion === 'direccion') {
        $direccion = trim($_POST['direccion'] ?? '');
        if (!$direccion) {
            echo json_encode(['ok' => false, 'msg' => 'La dirección es obligatoria.']); exit;
        }
        $pdo->prepare('UPDATE usuarios SET direccion=? WHERE id=?')->execute([$direccion, $usuario_id]);
        echo json_encode(['ok' => true, 'msg' => 'Dirección actualizada correctamente.', 'direccion' => $direccion]); exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Acción no reconocida.']); exit;
}
?>
<?php
$page_title = 'Perfil - Computécnicos';
$extra_css = '<link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet" />' . "\n" .
             '<link rel="stylesheet" href="' . asset('css/index.css') . '">' . "\n" .
             '<link rel="stylesheet" href="' . asset('css/perfil.css') . '">';
include 'includes/header.php';
?>
<main class="flex-1 relative z-10">

    <!-- Cabecera del perfil -->
    <div class="perfil-header">
        <!-- Canvas de partículas -->
        <canvas id="perfil-particles" class="absolute inset-0 w-full h-full pointer-events-none" style="z-index:0;"></canvas>
        <div class="perfil-header-inner animate-slide-up">
            <!-- Avatar con anillo animado -->
            <div class="perfil-avatar-wrapper">
                <div class="perfil-avatar" id="avatar-container">
                    <?php if ($usuario['foto']): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto'] ?? ''); ?>" alt="Foto de perfil" id="avatar-img">
                    <?php else: ?>
                        <span id="avatar-letter"><?php echo strtoupper(substr($usuario['nombre'], 0, 1)); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="perfil-header-info">
                <h1 class="perfil-name" id="header-nombre"><?php echo htmlspecialchars($usuario['nombre']); ?></h1>
                <p class="perfil-email" id="header-email"><?php echo htmlspecialchars($usuario['email']); ?></p>
                <span class="perfil-badge">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Miembro desde <?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="perfil-container">
        <div class="perfil-grid">

            <!-- Información Personal -->
            <div class="perfil-card animate-slide-up">
                <div class="perfil-card-header">
                    <div class="perfil-card-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                    </div>
                    <div>
                        <h3 class="perfil-card-title">Información Personal</h3>
                        <p class="perfil-card-subtitle">Nombre, correo y teléfono</p>
                    </div>
                </div>
                <div class="perfil-card-body">
                    <div class="perfil-field">
                        <span class="perfil-field-label">Nombre</span>
                        <span class="perfil-field-value" id="field-nombre"><?php echo htmlspecialchars($usuario['nombre']); ?></span>
                    </div>
                    <div class="perfil-field">
                        <span class="perfil-field-label">Correo</span>
                        <span class="perfil-field-value" id="field-email"><?php echo htmlspecialchars($usuario['email']); ?></span>
                    </div>
                    <div class="perfil-field">
                        <span class="perfil-field-label">Teléfono</span>
                        <span class="perfil-field-value" id="field-telefono"><?php echo htmlspecialchars($usuario['telefono'] ?: '—'); ?></span>
                    </div>
                </div>
                <div class="perfil-card-footer">
                    <button type="button" class="perfil-card-action" onclick="abrirModal('modal-personal')">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                        Editar
                    </button>
                </div>
            </div>

            <!-- Seguridad -->
            <div class="perfil-card animate-slide-up delay-100">
                <div class="perfil-card-header">
                    <div class="perfil-card-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    </div>
                    <div>
                        <h3 class="perfil-card-title">Seguridad</h3>
                        <p class="perfil-card-subtitle">Cambia tu contraseña</p>
                    </div>
                </div>
                <div class="perfil-card-body">
                    <div class="perfil-field">
                        <span class="perfil-field-label">Contraseña</span>
                        <span class="perfil-field-value">••••••••</span>
                    </div>
                </div>
                <div class="perfil-card-footer">
                    <button type="button" class="perfil-card-action" onclick="abrirModal('modal-seguridad')">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                        Editar
                    </button>
                </div>
            </div>

            <!-- Dirección -->
            <div class="perfil-card animate-slide-up delay-200">
                <div class="perfil-card-header">
                    <div class="perfil-card-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="perfil-card-title">Dirección</h3>
                        <p class="perfil-card-subtitle">Dirección de envío</p>
                    </div>
                </div>
                <div class="perfil-card-body">
                    <div class="perfil-field">
                        <span class="perfil-field-label">Dirección</span>
                        <span class="perfil-field-value" id="field-direccion"><?php echo htmlspecialchars($usuario['direccion'] ?: '—'); ?></span>
                    </div>
                </div>
                <div class="perfil-card-footer">
                    <button type="button" class="perfil-card-action" onclick="abrirModal('modal-direccion')">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                        Editar
                    </button>
                </div>
            </div>

            <!-- Privacidad -->
            <div class="perfil-card animate-slide-up delay-300">
                <div class="perfil-card-header">
                    <div class="perfil-card-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    </div>
                    <div>
                        <h3 class="perfil-card-title">Privacidad</h3>
                        <p class="perfil-card-subtitle">Controla tus datos</p>
                    </div>
                </div>
                <div class="perfil-card-body">
                    <p class="text-sm" style="color:#666;">Próximamente podrás gestionar tus preferencias de privacidad.</p>
                </div>
                <div class="perfil-card-footer">
                    <span class="perfil-card-action disabled">No disponible</span>
                </div>
            </div>
        </div>

        <!-- Eliminar cuenta -->
        <div class="flex justify-center mt-10 mb-6">
            <button id="btn-eliminar-cuenta" class="perfil-danger-btn">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                Eliminar cuenta
            </button>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         MODAL: Información Personal
    ══════════════════════════════════════════════════════════ -->
    <div id="modal-personal" class="perfil-modal hidden" role="dialog" aria-modal="true">
        <div class="perfil-modal-box">
            <button class="perfil-modal-close" onclick="cerrarModal('modal-personal')">&times;</button>
            <h2 class="perfil-modal-title">Editar información personal</h2>
            <div id="msg-personal" class="perfil-msg hidden"></div>
            <form id="form-personal" enctype="multipart/form-data">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="accion" value="personal">
                <!-- Avatar preview -->
                <div class="perfil-modal-avatar-area">
                    <?php if ($usuario['foto']): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto'] ?? ''); ?>" alt="Foto" class="perfil-modal-avatar" id="foto-preview">
                    <?php else: ?>
                        <div class="perfil-modal-avatar perfil-modal-avatar-letter" id="foto-preview-letter"><?php echo strtoupper(substr($usuario['nombre'], 0, 1)); ?></div>
                    <?php endif; ?>
                    <label for="foto-input" class="perfil-change-photo">Cambiar foto</label>
                    <input type="file" id="foto-input" name="foto" accept="image/*" class="hidden">
                </div>
                <label class="perfil-label">Nombre completo</label>
                <input type="text" name="nombre" class="perfil-input" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                <label class="perfil-label">Correo electrónico</label>
                <input type="email" name="email" class="perfil-input" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                <label class="perfil-label">Teléfono</label>
                <input type="text" name="telefono" class="perfil-input" value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>">
                <button type="submit" class="perfil-btn primary w-full" id="btn-personal">
                    <span id="btn-personal-text">Guardar cambios</span>
                    <span id="btn-personal-spinner" class="hidden">Guardando…</span>
                </button>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         MODAL: Seguridad
    ══════════════════════════════════════════════════════════ -->
    <div id="modal-seguridad" class="perfil-modal hidden" role="dialog" aria-modal="true">
        <div class="perfil-modal-box">
            <button class="perfil-modal-close" onclick="cerrarModal('modal-seguridad')">&times;</button>
            <h2 class="perfil-modal-title">Cambiar contraseña</h2>
            <div id="msg-seguridad" class="perfil-msg hidden"></div>
            <form id="form-seguridad">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="accion" value="seguridad">
                <label class="perfil-label">Contraseña actual</label>
                <input type="password" name="password_actual" class="perfil-input" required autocomplete="current-password">
                <label class="perfil-label">Nueva contraseña</label>
                <input type="password" name="password_nueva" class="perfil-input" required autocomplete="new-password">
                <label class="perfil-label">Confirmar nueva contraseña</label>
                <input type="password" name="password_confirmar" class="perfil-input" required autocomplete="new-password">
                <button type="submit" class="perfil-btn primary w-full" id="btn-seguridad">
                    <span id="btn-seguridad-text">Guardar contraseña</span>
                    <span id="btn-seguridad-spinner" class="hidden">Guardando…</span>
                </button>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         MODAL: Dirección
    ══════════════════════════════════════════════════════════ -->
    <div id="modal-direccion" class="perfil-modal hidden" role="dialog" aria-modal="true">
        <div class="perfil-modal-box">
            <button class="perfil-modal-close" onclick="cerrarModal('modal-direccion')">&times;</button>
            <h2 class="perfil-modal-title">Editar dirección</h2>
            <div id="msg-direccion" class="perfil-msg hidden"></div>
            <form id="form-direccion">
                <input type="hidden" name="ajax" value="1">
                <input type="hidden" name="accion" value="direccion">
                <label class="perfil-label">Dirección de envío</label>
                <input type="text" name="direccion" class="perfil-input" value="<?php echo htmlspecialchars($usuario['direccion'] ?? ''); ?>" required>
                <button type="submit" class="perfil-btn primary w-full" id="btn-direccion">
                    <span id="btn-direccion-text">Guardar dirección</span>
                    <span id="btn-direccion-spinner" class="hidden">Guardando…</span>
                </button>
            </form>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         MODAL: Eliminar cuenta
    ══════════════════════════════════════════════════════════ -->
    <div id="modal-eliminar-cuenta" class="perfil-modal hidden" role="dialog" aria-modal="true">
        <div class="perfil-modal-box">
            <button class="perfil-modal-close" onclick="cerrarModal('modal-eliminar-cuenta')">&times;</button>
            <h2 class="perfil-modal-title" style="color:#ff4444">Eliminar cuenta</h2>
            <p class="perfil-modal-desc">¿Estás seguro? <strong>Esta acción es irreversible.</strong> Se eliminarán todos tus datos, pedidos e historial. Ingresa tu contraseña para confirmar:</p>
            <div id="msg-eliminar" class="perfil-msg hidden"></div>
            <form id="form-eliminar-cuenta">
                <input type="password" name="password" id="eliminar-password" class="perfil-input" placeholder="Tu contraseña actual" required>
                <label style="display:flex;align-items:center;gap:8px;margin:12px 0;color:#999;font-size:0.85rem;cursor:pointer">
                    <input type="checkbox" id="eliminar-confirm-check" style="accent-color:#ff0000;width:16px;height:16px">
                    Confirmo que quiero eliminar mi cuenta permanentemente
                </label>
                <button type="submit" class="perfil-btn danger w-full" id="btn-eliminar" disabled>
                    <span id="btn-eliminar-text">Eliminar definitivamente</span>
                    <span id="btn-eliminar-spinner" class="hidden">Eliminando…</span>
                </button>
            </form>
        </div>
    </div>

</main>

<?php include 'includes/footer.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.js"></script>
<script>
// ── Helpers de modal ─────────────────────────────────────────────────────
function abrirModal(id) {
    document.getElementById(id).classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function cerrarModal(id) {
    document.getElementById(id).classList.add('hidden');
    document.body.style.overflow = '';
    // Limpiar mensajes al cerrar
    const msg = document.getElementById('msg-' + id.replace('modal-', ''));
    if (msg) { msg.className = 'perfil-msg hidden'; msg.textContent = ''; }
}

// Cerrar con Escape o clic en el backdrop
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.perfil-modal:not(.hidden)').forEach(m => cerrarModal(m.id)); });
document.querySelectorAll('.perfil-modal').forEach(modal => {
    modal.addEventListener('click', e => { if (e.target === modal) cerrarModal(modal.id); });
});

// Botón eliminar cuenta
document.getElementById('btn-eliminar-cuenta').onclick = () => abrirModal('modal-eliminar-cuenta');

// Checkbox de confirmación habilita/deshabilita el botón de eliminar
document.getElementById('eliminar-confirm-check').addEventListener('change', function() {
    document.getElementById('btn-eliminar').disabled = !this.checked;
});

// Form eliminar cuenta (AJAX)
document.getElementById('form-eliminar-cuenta').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-eliminar');
    const textSpan = document.getElementById('btn-eliminar-text');
    const spinnerSpan = document.getElementById('btn-eliminar-spinner');
    const password = document.getElementById('eliminar-password').value;

    if (!password) {
        showToast('Ingresa tu contraseña para confirmar.', 'error', 4000);
        return;
    }

    btn.disabled = true;
    if (textSpan) textSpan.classList.add('hidden');
    if (spinnerSpan) spinnerSpan.classList.remove('hidden');

    try {
        const fd = new FormData();
        fd.append('password', password);
        const res = await fetch('api/eliminar_cuenta.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
            cerrarModal('modal-eliminar-cuenta');
            showToast(data.msg || 'Cuenta eliminada correctamente. Redirigiendo...', 'success', 3000);
            setTimeout(() => { window.location.href = 'index.php?cuenta_eliminada=1'; }, 1500);
        } else {
            showToast(data.msg || 'Error al eliminar la cuenta.', 'error', 5000);
        }
    } catch(err) {
        showToast('Error de conexión. Intenta de nuevo.', 'error', 5000);
    } finally {
        btn.disabled = false;
        if (textSpan) textSpan.classList.remove('hidden');
        if (spinnerSpan) spinnerSpan.classList.add('hidden');
    }
});

// ── Mostrar mensaje como toast ───────────────────────────────────────────
function mostrarMsg(id, texto, tipo) {
    // Usar showToast global en lugar de mensajes inline
    const toastType = tipo === 'success' ? 'success' : 'error';
    const duration = tipo === 'success' ? 4000 : 5000;
    if (typeof showToast === 'function') {
        showToast(texto, toastType, duration);
    }
}

// ── Preview de foto ──────────────────────────────────────────────────────
const fotoInput = document.getElementById('foto-input');
if (fotoInput) {
    fotoInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const url = URL.createObjectURL(file);
        const preview = document.getElementById('foto-preview');
        const previewLetter = document.getElementById('foto-preview-letter');
        if (preview) {
            preview.src = url;
        } else if (previewLetter) {
            // Convertir letra a imagen
            previewLetter.outerHTML = `<img src="${url}" alt="Foto" class="perfil-modal-avatar" id="foto-preview">`;
        }
    });
}

// ── Envío AJAX genérico ──────────────────────────────────────────────────
function enviarFormAjax(formId, btnId, msgId, onSuccess) {
    const form = document.getElementById(formId);
    const btn  = document.getElementById(btnId);
    const textSpan    = document.getElementById(btnId + '-text');
    const spinnerSpan = document.getElementById(btnId + '-spinner');

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        // Estado cargando
        btn.disabled = true;
        if (textSpan)    textSpan.classList.add('hidden');
        if (spinnerSpan) spinnerSpan.classList.remove('hidden');

        try {
            const res  = await fetch('perfil.php', { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            if (data.ok) {
                // Cerrar modal inmediatamente y mostrar toast
                cerrarModal(form.closest('.perfil-modal').id);
                showToast(data.msg, 'success', 4000);
                if (onSuccess) onSuccess(data);
            } else {
                showToast(data.msg, 'error', 5000);
            }
        } catch (err) {
            showToast('Error de conexión. Intenta de nuevo.', 'error', 5000);
        } finally {
            btn.disabled = false;
            if (textSpan)    textSpan.classList.remove('hidden');
            if (spinnerSpan) spinnerSpan.classList.add('hidden');
        }
    });
}

// ── Registrar formularios ────────────────────────────────────────────────
enviarFormAjax('form-personal', 'btn-personal', 'msg-personal', function(data) {
    // Actualizar campos visibles en la página sin recargar
    if (data.nombre) {
        document.getElementById('field-nombre').textContent  = data.nombre;
        document.getElementById('header-nombre').textContent = data.nombre;
    }
    if (data.email) {
        document.getElementById('field-email').textContent  = data.email;
        document.getElementById('header-email').textContent = data.email;
    }
    if (data.foto) {
        const avatarContainer = document.getElementById('avatar-container');
        avatarContainer.innerHTML = `<img src="${data.foto}" alt="Foto de perfil" id="avatar-img" style="width:100%;height:100%;object-fit:cover;">`;
    }
});

enviarFormAjax('form-seguridad', 'btn-seguridad', 'msg-seguridad', function() {
    // Limpiar campos de contraseña tras éxito
    document.getElementById('form-seguridad').reset();
});

enviarFormAjax('form-direccion', 'btn-direccion', 'msg-direccion', function(data) {
    if (data.direccion) {
        document.getElementById('field-direccion').textContent = data.direccion;
    }
});

// ── Partículas flotantes en el hero ─────────────────────────────────────
(function() {
    const canvas = document.getElementById('perfil-particles');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    function resize() { canvas.width = canvas.offsetWidth; canvas.height = canvas.offsetHeight; }
    resize();
    window.addEventListener('resize', resize);
    const particles = Array.from({ length: 55 }, () => ({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        r: Math.random() * 1.8 + 0.4,
        vx: (Math.random() - 0.5) * 0.35,
        vy: (Math.random() - 0.5) * 0.35,
        alpha: Math.random() * 0.5 + 0.1,
        red: Math.random() > 0.6,
    }));
    function draw() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(p => {
            p.x += p.vx; p.y += p.vy;
            if (p.x < 0) p.x = canvas.width;
            if (p.x > canvas.width)  p.x = 0;
            if (p.y < 0) p.y = canvas.height;
            if (p.y > canvas.height) p.y = 0;
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = p.red ? `rgba(255,0,0,${p.alpha})` : `rgba(255,255,255,${p.alpha * 0.6})`;
            ctx.fill();
        });
        requestAnimationFrame(draw);
    }
    draw();
})();
</script>
</body>
</html>
