<?php
/**
 * Página de Restablecimiento de Contraseña
 * GET  /reset_password.php?token=XXX  → Muestra formulario
 * POST /reset_password.php            → Procesa el nuevo password
 */

require_once __DIR__ . '/app/Core/bootstrap.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$msg = '';
$msgType = '';
$showForm = false;
$success = false;

// ── Procesar POST (nueva contraseña) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    
    if (empty($token)) {
        $msg = 'Token inválido o expirado.';
        $msgType = 'error';
    } elseif (strlen($password) < 6) {
        $msg = 'La contraseña debe tener al menos 6 caracteres.';
        $msgType = 'error';
        $showForm = true;
    } elseif ($password !== $password2) {
        $msg = 'Las contraseñas no coinciden.';
        $msgType = 'error';
        $showForm = true;
    } else {
        // Verificar token
        $stmt = $pdo->prepare("
            SELECT pr.*, u.id AS user_id 
            FROM password_resets pr 
            INNER JOIN usuarios u ON u.email COLLATE utf8mb4_unicode_ci = pr.email
            WHERE pr.token = ? AND pr.usado = 0 AND pr.expira > UTC_TIMESTAMP()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        
        if (!$reset) {
            $msg = 'El enlace ha expirado o ya fue utilizado. Solicita uno nuevo.';
            $msgType = 'error';
        } else {
            // Actualizar contraseña
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?")->execute([$hashedPassword, $reset['user_id']]);
            
            // Marcar token como usado
            $pdo->prepare("UPDATE password_resets SET usado = 1 WHERE id = ?")->execute([$reset['id']]);
            
            // Invalidar todos los tokens del email
            $pdo->prepare("UPDATE password_resets SET usado = 1 WHERE email = ?")->execute([$reset['email']]);
            
            // Invalidar tokens Remember Me de todos los dispositivos (forzar re-login)
            $rememberMe->invalidateAllTokens($reset['user_id']);
            
            $msg = '¡Contraseña actualizada exitosamente! Ya puedes iniciar sesión.';
            $msgType = 'success';
            $success = true;
            
            log_event("Contraseña restablecida para: " . $reset['email']);
        }
    }
} else {
    // ── GET: Verificar token ──
    if (empty($token)) {
        $msg = 'No se proporcionó un token válido.';
        $msgType = 'error';
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM password_resets 
            WHERE token = ? AND usado = 0 AND expira > UTC_TIMESTAMP()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        
        if (!$reset) {
            $msg = 'El enlace ha expirado o ya fue utilizado. Solicita uno nuevo.';
            $msgType = 'error';
        } else {
            $showForm = true;
        }
    }
}

$page_title = 'Restablecer Contraseña';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | Computécnicos</title>
    <link rel="icon" type="image/svg+xml" href="<?= asset('images/favicon.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Orbitron:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/reset_password.css') ?>">
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="reset-logo">
                <a href="index.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                    <span class="reset-logo-text">COMPU<em>TÉCNICOS</em></span>
                </a>
            </div>

            <?php if ($msg): ?>
                <div class="reset-msg <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <?php if ($showForm): ?>
                <div class="reset-title">Nueva Contraseña</div>
                <div class="reset-subtitle">Ingresa tu nueva contraseña. Asegúrate de que sea segura.</div>

                <form method="POST" id="reset-form">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    
                    <div class="reset-field">
                        <label>Nueva contraseña</label>
                        <div class="reset-input-wrap" style="position:relative;">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" name="password" id="pw-new" class="reset-input" placeholder="Mínimo 6 caracteres" required minlength="6" style="padding-right:40px;">
                            <button type="button" onclick="const input = this.previousElementSibling; if(input.type==='password'){input.type='text';this.innerHTML='<svg width=\'18\' height=\'18\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'><path d=\'M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22\'/></svg>';}else{input.type='password';this.innerHTML='<svg width=\'18\' height=\'18\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'><path d=\'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z\'></path><circle cx=\'12\' cy=\'12\' r=\'3\'></circle></svg>';}" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#666;cursor:pointer;padding:0;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                        <div class="pw-strength" id="pw-strength">
                            <div class="pw-strength-bar"></div>
                            <div class="pw-strength-bar"></div>
                            <div class="pw-strength-bar"></div>
                            <div class="pw-strength-bar"></div>
                        </div>
                        <div class="pw-strength-text" id="pw-strength-text"></div>
                    </div>
                    
                    <div class="reset-field">
                        <label>Confirmar contraseña</label>
                        <div class="reset-input-wrap" style="position:relative;">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" name="password2" id="pw-confirm" class="reset-input" placeholder="Repite tu contraseña" required minlength="6" style="padding-right:40px;">
                            <button type="button" onclick="const input = this.previousElementSibling; if(input.type==='password'){input.type='text';this.innerHTML='<svg width=\'18\' height=\'18\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'><path d=\'M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24M1 1l22 22\'/></svg>';}else{input.type='password';this.innerHTML='<svg width=\'18\' height=\'18\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'><path d=\'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z\'></path><circle cx=\'12\' cy=\'12\' r=\'3\'></circle></svg>';}" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:#666;cursor:pointer;padding:0;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="reset-btn" id="reset-submit">
                        Restablecer Contraseña
                    </button>
                </form>

                <script>
                    // Password strength meter
                    document.getElementById('pw-new').addEventListener('input', function() {
                        const pw = this.value;
                        let score = 0;
                        if (pw.length >= 6) score++;
                        if (pw.length >= 10) score++;
                        if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
                        if (/[0-9]/.test(pw) && /[^a-zA-Z0-9]/.test(pw)) score++;
                        
                        const bars = document.querySelectorAll('.pw-strength-bar');
                        const colors = ['#ef4444', '#f59e0b', '#facc15', '#4ade80'];
                        const labels = ['Muy débil', 'Débil', 'Buena', 'Muy segura'];
                        
                        bars.forEach((bar, i) => {
                            bar.style.background = i < score ? colors[Math.min(score-1, 3)] : '#222';
                        });
                        
                        const text = document.getElementById('pw-strength-text');
                        if (pw.length > 0) {
                            text.textContent = labels[Math.min(score-1, 3)] || 'Muy débil';
                            text.style.color = colors[Math.min(score-1, 3)] || '#555';
                        } else {
                            text.textContent = '';
                        }
                    });
                </script>

            <?php elseif ($success): ?>
                <div style="text-align:center;padding:1rem 0">
                    <div style="width:64px;height:64px;background:rgba(74,222,128,0.1);border:2px solid rgba(74,222,128,0.3);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div class="reset-title">¡Listo!</div>
                    <p style="color:#888;font-size:0.85rem;margin:0.5rem 0 1.5rem">Tu contraseña ha sido actualizada correctamente.</p>
                    <a href="index.php?login=1" class="reset-btn" style="display:inline-flex;text-decoration:none;max-width:280px;margin:0 auto">
                        Iniciar Sesión
                    </a>
                </div>

            <?php else: ?>
                <!-- Token inválido/expirado -->
                <div style="text-align:center;padding:1rem 0">
                    <div style="width:64px;height:64px;background:rgba(239,68,68,0.1);border:2px solid rgba(239,68,68,0.3);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </div>
                    <div class="reset-title">Enlace Inválido</div>
                    <p style="color:#888;font-size:0.85rem;margin:0.5rem 0">El enlace ha expirado o ya fue utilizado.</p>
                </div>
            <?php endif; ?>

            <div class="reset-link">
                <a href="index.php">← Volver al inicio</a>
            </div>
        </div>
    </div>
</body>
</html>
