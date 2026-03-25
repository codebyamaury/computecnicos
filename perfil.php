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

// Detectar si es usuario de Google (foto externa de Google o sin contraseña propia)
$es_google = false;
if (!empty($usuario['foto']) && (strpos($usuario['foto'], 'googleusercontent.com') !== false || strpos($usuario['foto'], 'googleapis.com') !== false)) {
    $es_google = true;
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
        
        if (!empty($telefono)) {
            $telefono_limpio = preg_replace('/\s+/', '', $telefono);
            if (!preg_match('/^3\d{9}$/', $telefono_limpio)) {
                echo json_encode(['ok' => false, 'msg' => 'El teléfono debe ser un celular válido de 10 dígitos.']); exit;
            }
            $telefono = $telefono_limpio;
        }

        if (!$nombre || !$email) {
            echo json_encode(['ok' => false, 'msg' => 'Nombre y correo son obligatorios.']); exit;
        }
        // Validar email completo (formato + DNS + detección de typos)
        require_once __DIR__ . '/app/Core/email_validator.php';
        $email_check = validar_email_completo($email);
        if (!$email_check['ok']) {
            echo json_encode(['ok' => false, 'msg' => $email_check['msg']]); exit;
        }
        // Verificar que el email no esté en uso por otro usuario
        $stmt_check = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id != ?');
        $stmt_check->execute([$email, $usuario_id]);
        if ($stmt_check->fetch()) {
            echo json_encode(['ok' => false, 'msg' => 'Este correo ya está en uso por otra cuenta.']); exit;
        }
        // Bloquear cambio de email para usuarios de Google
        if ($es_google && strtolower($email) !== strtolower($usuario['email'])) {
            echo json_encode(['ok' => false, 'msg' => 'No puedes cambiar el correo de una cuenta vinculada con Google.']); exit;
        }
        // Si el email cambió, exigir verificación con código
        if (strtolower($email) !== strtolower($usuario['email'])) {
            $codigo_enviado = trim($_POST['codigo_verificacion'] ?? '');
            if (empty($codigo_enviado)) {
                echo json_encode(['ok' => false, 'msg' => 'Debes verificar tu nuevo correo con un código.', 'requires_verification' => true]); exit;
            }
            $verif = $_SESSION['cambio_email_verificacion'] ?? null;
            if (!$verif || strtolower($verif['email']) !== strtolower($email)) {
                echo json_encode(['ok' => false, 'msg' => 'Solicita un nuevo código de verificación para este correo.', 'requires_verification' => true]); exit;
            }
            if (time() > $verif['expira']) {
                unset($_SESSION['cambio_email_verificacion']);
                echo json_encode(['ok' => false, 'msg' => 'El código ha expirado. Solicita uno nuevo.', 'requires_verification' => true]); exit;
            }
            if ($verif['intentos'] >= 5) {
                unset($_SESSION['cambio_email_verificacion']);
                echo json_encode(['ok' => false, 'msg' => 'Demasiados intentos fallidos. Solicita un nuevo código.', 'requires_verification' => true]); exit;
            }
            if ($codigo_enviado !== $verif['codigo']) {
                $_SESSION['cambio_email_verificacion']['intentos']++;
                echo json_encode(['ok' => false, 'msg' => 'Código incorrecto. Intento ' . $_SESSION['cambio_email_verificacion']['intentos'] . ' de 5.']); exit;
            }
            // Código correcto — limpiar verificación
            unset($_SESSION['cambio_email_verificacion']);
        }
        $foto_url = $usuario['foto'];
        if (!empty($_FILES['foto']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                echo json_encode(['ok' => false, 'msg' => 'Solo se permiten imágenes JPG, PNG o WebP.']); exit;
            }
            if ($_FILES['foto']['size'] > 2*1024*1024) {
                echo json_encode(['ok' => false, 'msg' => 'La imagen no puede superar los 2MB.']); exit;
            }
            // Eliminar foto anterior si era una subida local
            if (!empty($usuario['foto']) && strpos($usuario['foto'], 'uploads/') === 0) {
                $old = __DIR__ . '/' . $usuario['foto'];
                if (is_file($old)) @unlink($old);
            }
            // Crear directorio si no existe
            $upload_dir = __DIR__ . '/uploads/profiles';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $nombre_archivo = uniqid('profile_') . '.' . $ext;
            $destino_relativo = 'uploads/profiles/' . $nombre_archivo;
            $destino_absoluto = $upload_dir . '/' . $nombre_archivo;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino_absoluto)) {
                $foto_url = $destino_relativo;
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Error al guardar la imagen. Intenta de nuevo.']); exit;
            }
        }
        $pdo->prepare('UPDATE usuarios SET nombre=?, email=?, telefono=?, foto=? WHERE id=?')
            ->execute([$nombre, $email, $telefono, $foto_url, $usuario_id]);
        $_SESSION['usuario']['nombre'] = $nombre;
        $_SESSION['usuario']['email']  = $email;
        $_SESSION['usuario']['foto']   = $foto_url;
        echo json_encode(['ok' => true, 'msg' => 'Datos actualizados correctamente.', 'foto' => $foto_url, 'nombre' => $nombre, 'email' => $email, 'telefono' => $telefono]); exit;

    } elseif ($accion === 'direccion') {
        $direccion = trim($_POST['direccion'] ?? '');
        if (!$direccion) {
            echo json_encode(['ok' => false, 'msg' => 'La dirección es obligatoria.']); exit;
        }

        if (strlen($direccion) < 10) {
            echo json_encode(['ok' => false, 'msg' => 'La dirección es demasiado corta. Usa un formato completo.']); exit;
        }
        if (!preg_match('/[a-zA-ZáéíóúÁÉÍÓÚñÑ]/', $direccion) || !preg_match('/[0-9]/', $direccion)) {
            echo json_encode(['ok' => false, 'msg' => 'La dirección es inválida. Debe contener un formato con texto y números (ej. Cra 17 #45-20).']); exit;
        }
        if (preg_match('/^\.+$/', $direccion)) {
            echo json_encode(['ok' => false, 'msg' => 'La dirección no puede contener solo puntos o símbolos.']); exit;
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
            <!-- Avatar con anillo animado + overlay de cámara -->
            <div class="perfil-avatar-wrapper">
                <div class="perfil-avatar" id="avatar-container">
                    <?php if ($usuario['foto']): ?>
                        <img src="<?php echo htmlspecialchars($usuario['foto'] ?? ''); ?>" alt="Foto de perfil" id="avatar-img">
                    <?php else: ?>
                        <span id="avatar-letter"><?php echo strtoupper(substr($usuario['nombre'], 0, 1)); ?></span>
                    <?php endif; ?>
                    <!-- Overlay de cámara al hacer hover -->
                    <label for="avatar-upload-input" class="perfil-avatar-overlay" title="Cambiar foto de perfil">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/>
                        </svg>
                    </label>
                </div>
                <input type="file" id="avatar-upload-input" accept="image/jpeg,image/png,image/webp" class="hidden" style="display:none;">
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
                <label class="perfil-label">Nombre completo</label>
                <input type="text" name="nombre" class="perfil-input" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                <label class="perfil-label">Correo electrónico <?php if ($es_google): ?><span style="color:#666;font-size:11px;">(vinculado con Google)</span><?php endif; ?></label>
                <input type="email" name="email" id="input-email-perfil" class="perfil-input" value="<?php echo htmlspecialchars($usuario['email']); ?>" required <?php echo $es_google ? 'readonly style="opacity:0.5;cursor:not-allowed;"' : ''; ?>>
                <!-- Verificación de cambio de email -->
                <div id="email-verify-section" class="hidden" style="margin-top:8px;margin-bottom:12px;">
                    <div id="email-verify-send" style="margin-bottom:8px;">
                        <button type="button" id="btn-enviar-codigo-email" class="perfil-btn" style="width:100%;background:#222;border:1px solid #444;color:#ccc;font-size:13px;padding:10px;" onclick="enviarCodigoCambioEmail()">
                            <span id="btn-codigo-text"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px;display:inline-block;vertical-align:middle;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg> Enviar código al nuevo correo</span>
                            <span id="btn-codigo-spinner" class="hidden">Enviando...</span>
                        </button>
                    </div>
                    <div id="email-verify-code" class="hidden">
                        <label class="perfil-label" style="color:#ff4444;">Código de verificación</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="input-codigo-email" maxlength="6" placeholder="000000" class="perfil-input" style="text-align:center;letter-spacing:8px;font-size:20px;font-weight:bold;font-family:monospace;flex:1;">
                        </div>
                        <p style="color:#666;font-size:11px;margin-top:4px;">Revisa tu correo. El código expira en 10 minutos.</p>
                    </div>
                </div>
                <input type="hidden" name="codigo_verificacion" id="hidden-codigo-verificacion" value="">
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
                <div style="position:relative;">
                    <input type="password" name="password_actual" class="perfil-input" required autocomplete="current-password" style="padding-right:40px; width:100%;">
                    <button type="button" onclick="const input = this.previousElementSibling; if(input.type==='password'){input.type='text';this.innerHTML='<svg width=\'18\' height=\'18\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'><path d=\'M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22\'/></svg>';}else{input.type='password';this.innerHTML='<svg width=\'18\' height=\'18\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'><path d=\'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z\'></path><circle cx=\'12\' cy=\'12\' r=\'3\'></circle></svg>';}" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#888;cursor:pointer;padding:0;display:flex;align-items:center;justify-content:center;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
                <label class="perfil-label">Nueva contraseña</label>
                <div style="position:relative;">
                    <input type="password" name="password_nueva" class="perfil-input" required autocomplete="new-password" style="padding-right:40px; width:100%;">
                    <button type="button" onclick="const input = this.previousElementSibling; if(input.type==='password'){input.type='text';this.innerHTML='<svg width=\'18\' height=\'18\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'><path d=\'M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22\'/></svg>';}else{input.type='password';this.innerHTML='<svg width=\'18\' height=\'18\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'><path d=\'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z\'></path><circle cx=\'12\' cy=\'12\' r=\'3\'></circle></svg>';}" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#888;cursor:pointer;padding:0;display:flex;align-items:center;justify-content:center;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
                <label class="perfil-label">Confirmar nueva contraseña</label>
                <div style="position:relative;">
                    <input type="password" name="password_confirmar" class="perfil-input" required autocomplete="new-password" style="padding-right:40px; width:100%;">
                    <button type="button" onclick="const input = this.previousElementSibling; if(input.type==='password'){input.type='text';this.innerHTML='<svg width=\'18\' height=\'18\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'><path d=\'M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22\'/></svg>';}else{input.type='password';this.innerHTML='<svg width=\'18\' height=\'18\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'><path d=\'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z\'></path><circle cx=\'12\' cy=\'12\' r=\'3\'></circle></svg>';}" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#888;cursor:pointer;padding:0;display:flex;align-items:center;justify-content:center;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
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
                <div style="position:relative;">
                    <input type="password" name="password" id="eliminar-password" class="perfil-input" placeholder="Tu contraseña actual" required style="padding-right:40px; width:100%;">
                    <button type="button" onclick="const input = this.previousElementSibling; if(input.type==='password'){input.type='text';this.innerHTML='<svg width=\'18\' height=\'18\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'><path d=\'M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22\'/></svg>';}else{input.type='password';this.innerHTML='<svg width=\'18\' height=\'18\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'><path d=\'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z\'></path><circle cx=\'12\' cy=\'12\' r=\'3\'></circle></svg>';}" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#888;cursor:pointer;padding:0;display:flex;align-items:center;justify-content:center;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
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
    
    // Restaurar valores y limpiar inputs al cerrar para que no persistan
    if (id === 'modal-personal') {
        let formPersonal = document.getElementById('form-personal');
        if(formPersonal) formPersonal.reset();
        
        let currentNombre = document.getElementById('field-nombre').textContent.trim();
        let currentEmail = document.getElementById('field-email').textContent.trim();
        let currentTelefono = document.getElementById('field-telefono').textContent.trim();
        
        document.querySelector('#form-personal input[name="nombre"]').value = currentNombre;
        document.querySelector('#form-personal input[name="email"]').value = currentEmail;
        document.querySelector('#form-personal input[name="telefono"]').value = currentTelefono === '—' ? '' : currentTelefono;

        // Limpiar secciones de código si estaban abiertas
        const verifySection = document.getElementById('email-verify-section');
        if(verifySection) verifySection.classList.add('hidden');
        const verifyCode = document.getElementById('email-verify-code');
        if(verifyCode) verifyCode.classList.add('hidden');
        const hiddenCod = document.getElementById('hidden-codigo-verificacion');
        if(hiddenCod) hiddenCod.value = '';
        const inputCod = document.getElementById('input-codigo-email');
        if(inputCod) inputCod.value = '';
        if(typeof emailCodigoEnviado !== 'undefined') emailCodigoEnviado = false;
        
    } else if (id === 'modal-direccion') {
        let formDireccion = document.getElementById('form-direccion');
        if(formDireccion) formDireccion.reset();
        let currentDireccion = document.getElementById('field-direccion').textContent.trim();
        document.querySelector('#form-direccion input[name="direccion"]').value = currentDireccion === '—' ? '' : currentDireccion;
    } else if (id === 'modal-seguridad') {
        let fl = document.getElementById('form-seguridad');
        if(fl) fl.reset();
    } else if (id === 'modal-eliminar-cuenta') {
        let fe = document.getElementById('form-eliminar-cuenta');
        if(fe) fe.reset();
        let be = document.getElementById('btn-eliminar');
        if(be) be.disabled = true;
    }
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

// ── Subida directa de foto desde el avatar del header ────────────────────
const avatarUploadInput = document.getElementById('avatar-upload-input');
if (avatarUploadInput) {
    avatarUploadInput.addEventListener('change', async function() {
        const file = this.files[0];
        if (!file) return;

        // Validar tipo
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            showToast('Solo se permiten imágenes JPG, PNG o WebP.', 'error', 5000);
            this.value = '';
            return;
        }
        // Validar tamaño (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            showToast('La imagen no puede superar los 2MB.', 'error', 5000);
            this.value = '';
            return;
        }

        // Mostrar preview inmediata en el avatar
        const previewUrl = URL.createObjectURL(file);
        const avatarContainer = document.getElementById('avatar-container');
        const existingImg = avatarContainer.querySelector('#avatar-img');
        const existingLetter = avatarContainer.querySelector('#avatar-letter');
        if (existingImg) {
            existingImg.src = previewUrl;
        } else if (existingLetter) {
            existingLetter.outerHTML = `<img src="${previewUrl}" alt="Foto de perfil" id="avatar-img" style="width:100%;height:100%;object-fit:cover;">`;
        }

        // Subir vía AJAX
        const fd = new FormData();
        fd.append('ajax', '1');
        fd.append('accion', 'personal');
        fd.append('nombre', '<?php echo addslashes($usuario['nombre']); ?>');
        fd.append('email', '<?php echo addslashes($usuario['email']); ?>');
        fd.append('telefono', '<?php echo addslashes($usuario['telefono'] ?? ''); ?>');
        fd.append('foto', file);

        try {
            const res = await fetch('perfil.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                showToast('Foto de perfil actualizada.', 'success', 3000);
                // Actualizar avatar en header
                if (data.foto) {
                    avatarContainer.innerHTML = `<img src="${data.foto}" alt="Foto de perfil" id="avatar-img" style="width:100%;height:100%;object-fit:cover;">` +
                        avatarContainer.querySelector('.perfil-avatar-overlay').outerHTML;
                    // Actualizar foto en el modal de edición si existe
                    const modalPreview = document.getElementById('foto-preview');
                    if (modalPreview) modalPreview.src = data.foto;
                    // Actualizar foto en el menú del header (esquina superior derecha)
                    const headerPhoto = document.querySelector('#user-menu-button img');
                    if (headerPhoto) headerPhoto.src = data.foto;
                }
            } else {
                showToast(data.msg || 'Error al subir la foto.', 'error', 5000);
            }
        } catch (err) {
            showToast('Error de conexión al subir la foto.', 'error', 5000);
        }
        this.value = '';
    });
}

// ── Verificación de cambio de email ─────────────────────────────────────
var emailOriginal = '<?php echo addslashes($usuario['email']); ?>';
var emailCodigoEnviado = false;

// Detectar cambios en el campo de email
document.getElementById('input-email-perfil').addEventListener('input', function() {
    var nuevoEmail = this.value.trim().toLowerCase();
    var section = document.getElementById('email-verify-section');
    if (nuevoEmail !== emailOriginal.toLowerCase() && nuevoEmail.length > 5) {
        section.classList.remove('hidden');
    } else {
        section.classList.add('hidden');
        document.getElementById('email-verify-code').classList.add('hidden');
        document.getElementById('hidden-codigo-verificacion').value = '';
        emailCodigoEnviado = false;
    }
});

// Enviar código al nuevo email
async function enviarCodigoCambioEmail() {
    var nuevoEmail = document.getElementById('input-email-perfil').value.trim();
    var emailCheck = validarEmail(nuevoEmail);
    if (!emailCheck.ok) {
        showToast(emailCheck.msg, 'error', 5000);
        return;
    }

    var btn = document.getElementById('btn-enviar-codigo-email');
    var btnText = document.getElementById('btn-codigo-text');
    var btnSpinner = document.getElementById('btn-codigo-spinner');
    btn.disabled = true;
    btnText.classList.add('hidden');
    btnSpinner.classList.remove('hidden');

    try {
        var fd = new FormData();
        fd.append('email', nuevoEmail);
        var res = await fetch('api/enviar_codigo_cambio_email.php', { method: 'POST', body: fd });
        var data = await res.json();
        if (data.ok) {
            showToast(data.msg, 'success', 5000);
            document.getElementById('email-verify-code').classList.remove('hidden');
            document.getElementById('input-codigo-email').focus();
            emailCodigoEnviado = true;
            // Cambiar botón a "Reenviar"
            btnText.innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px;display:inline-block;vertical-align:middle;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M20.015 4.652v4.992"/></svg> Reenviar código';
        } else {
            showToast(data.msg, 'error', 5000);
        }
    } catch (err) {
        showToast('Error de conexión. Intenta de nuevo.', 'error', 5000);
    } finally {
        btn.disabled = false;
        btnText.classList.remove('hidden');
        btnSpinner.classList.add('hidden');
    }
}

// Copiar código al hidden antes del submit
document.getElementById('input-codigo-email').addEventListener('input', function() {
    document.getElementById('hidden-codigo-verificacion').value = this.value.trim();
});

// ── Envío AJAX genérico ──────────────────────────────────────────────────
function enviarFormAjax(formId, btnId, msgId, onSuccess) {
    const form = document.getElementById(formId);
    const btn  = document.getElementById(btnId);
    const textSpan    = document.getElementById(btnId + '-text');
    const spinnerSpan = document.getElementById(btnId + '-spinner');

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Si es form-personal y el email cambió, verificar que haya código
        if (formId === 'form-personal') {
            var emailActual = document.getElementById('input-email-perfil').value.trim().toLowerCase();
            if (emailActual !== emailOriginal.toLowerCase()) {
                var codigo = document.getElementById('hidden-codigo-verificacion').value.trim();
                if (!codigo || codigo.length !== 6) {
                    showToast('Debes enviar y escribir el código de verificación de 6 dígitos.', 'error', 5000);
                    document.getElementById('email-verify-section').classList.remove('hidden');
                    if (!emailCodigoEnviado) {
                        document.getElementById('email-verify-code').classList.add('hidden');
                    }
                    return;
                }
            }
        }

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
                // Resetear estado de verificación de email
                if (formId === 'form-personal' && data.email) {
                    emailOriginal = data.email;
                    emailCodigoEnviado = false;
                    document.getElementById('hidden-codigo-verificacion').value = '';
                    document.getElementById('input-codigo-email').value = '';
                    document.getElementById('email-verify-section').classList.add('hidden');
                    document.getElementById('email-verify-code').classList.add('hidden');
                    document.getElementById('btn-codigo-text').innerHTML = '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:16px;height:16px;display:inline-block;vertical-align:middle;margin-right:4px;"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg> Enviar código al nuevo correo';
                }
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
    // Actualizar teléfono
    const fieldTel = document.getElementById('field-telefono');
    if (fieldTel) {
        fieldTel.textContent = data.telefono || '—';
    }
    if (data.foto) {
        const avatarContainer = document.getElementById('avatar-container');
        const overlayHTML = avatarContainer.querySelector('.perfil-avatar-overlay');
        avatarContainer.innerHTML = `<img src="${data.foto}" alt="Foto de perfil" id="avatar-img" style="width:100%;height:100%;object-fit:cover;">` +
            (overlayHTML ? overlayHTML.outerHTML : '');
        // También actualizar foto en el menú del header
        const headerPhoto = document.querySelector('#user-menu-button img');
        if (headerPhoto) headerPhoto.src = data.foto;
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
