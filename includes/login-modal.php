<?php
// Modal de Login/Registro Global
// Este archivo se incluye en footer.php para estar disponible en todas las páginas
?>

<!-- Modal Login/Registro -->
<div id="modal-login" class="fixed inset-0 bg-black/70 z-[9999] flex items-center justify-center hidden">
  <div class="absolute inset-0" onclick="cerrarModalLogin()"></div>
  <div class="glass-card relative animate-popIn">
    <button onclick="cerrarModalLogin()" class="absolute top-4 right-6 text-gray-400 hover:text-red-600 text-3xl font-bold">&times;</button>
    <div class="glass-icon">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v9m6.364-4.364a9 9 0 11-12.728 0" />
      </svg>
    </div>
    <div class="glass-title" id="modal-login-title">Iniciar Sesión</div>
    <div class="glass-subtitle" id="modal-login-subtitle">Accede a tu cuenta para continuar.</div>
    <div class="glass-tabs">
      <button class="glass-tab-btn active" id="modal-tab-login">Iniciar sesión</button>
      <button class="glass-tab-btn" id="modal-tab-register">Registrarse</button>
    </div>

    <!-- ========== FORMULARIO LOGIN ========== -->
    <div id="modal-glass-login-form">
      <form class="space-y-2" id="modal-form-login">
        <div id="modal-login-error" class="error-msg"></div>
        <div class="glass-input-group">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          <input type="email" id="modal-login-email" class="glass-input" placeholder="Correo electrónico" required>
        </div>
        <div class="glass-input-group">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c1.657 0 3-1.343 3-3V7a3 3 0 10-6 0v1c0 1.657 1.343 3 3 3zM5 11h14v10a2 2 0 01-2 2H7a2 2 0 01-2-2V11z"/></svg>
          <input type="password" id="modal-login-password" class="glass-input" placeholder="Contraseña" required>
        </div>
        <div class="flex justify-between items-center mb-2">
          <a href="#" class="text-sm text-red-500 hover:underline" onclick="mostrarForgotPassword(); return false;">¿Olvidaste tu contraseña?</a>
        </div>
        <button type="submit" class="glass-btn" id="modal-btn-login">
          Iniciar sesión
          <span class="spinner hidden" id="modal-spinner-login"></span>
        </button>
      </form>
      <div class="glass-separator">
        <span></span>
        <span class="sep-circle">o</span>
        <span></span>
      </div>
      <a href="api/google-login.php" class="glass-google-btn">
        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/google/google-original.svg" alt="Google" class="w-5 h-5">
        Iniciar sesión con Google
      </a>
    </div>

    <!-- ========== FORMULARIO OLVIDAR CONTRASEÑA ========== -->
    <div id="modal-glass-forgot-form" style="display:none;">
      <div id="forgot-msg" style="display:none;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:0.85rem;font-weight:600;text-align:center;"></div>
      <p style="color:#888;font-size:0.85rem;margin-bottom:16px;text-align:center;line-height:1.5">
        Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
      </p>
      <form id="modal-form-forgot">
        <div class="glass-input-group">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          <input type="email" id="forgot-email" class="glass-input" placeholder="tu@correo.com" required>
        </div>
        <button type="submit" class="glass-btn" id="forgot-btn" style="margin-top:12px">
          Enviar enlace de recuperación
          <span class="spinner hidden" id="forgot-spinner"></span>
        </button>
      </form>
      <div style="text-align:center;margin-top:14px">
        <a href="#" onclick="volverALogin(); return false;" style="color:#ff4444;font-size:0.82rem;text-decoration:none;font-weight:600">← Volver a iniciar sesión</a>
      </div>
    </div>

    <!-- ========== FORMULARIO REGISTRO MULTI-PASO ========== -->
    <div id="modal-glass-register-form" style="display:none;" class="flex flex-col items-center justify-center">
      <form class="w-full" id="modal-form-register-simple" autocomplete="off">
        <div id="modal-register-error" style="color:#ff4747;background:#ff474722;border-radius:6px;padding:8px 12px;margin-bottom:10px;font-size:0.85rem;font-weight:600;text-align:center;display:none;"></div>

        <!-- Barra de progreso -->
        <div class="reg-progress-bar" id="reg-progress">
          <div class="reg-progress-dot reg-dot-current" id="reg-dot-1"></div>
          <div class="reg-progress-dot" id="reg-dot-2"></div>
          <div class="reg-progress-dot" id="reg-dot-3"></div>
          <div class="reg-progress-dot" id="reg-dot-4"></div>
          <div class="reg-progress-dot" id="reg-dot-5"></div>
          <div class="reg-progress-dot" id="reg-dot-6"></div>
        </div>

        <!-- PASO 1: Nombre -->
        <div class="reg-step reg-step-active" id="reg-step-1">
          <div class="reg-step-title">Paso 1 de 6 — ¿Cuál es tu nombre?</div>
          <div class="glass-input-group">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12A4 4 0 118 12a4 4 0 018 0z"/></svg>
            <input type="text" id="register-simple-nombre" class="glass-input" placeholder="Nombre completo" autofocus>
          </div>
          <button type="button" class="glass-btn" style="margin-top:14px;" onclick="regValidateAndNext(1)">SIGUIENTE →</button>
        </div>

        <!-- PASO 2: Email -->
        <div class="reg-step" id="reg-step-2">
          <div class="reg-step-title">Paso 2 de 6 — Tu correo electrónico</div>
          <div class="glass-input-group">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <input type="email" id="register-simple-email" class="glass-input" placeholder="Correo electrónico">
          </div>
          <div style="display:flex;gap:8px;margin-top:14px;">
            <button type="button" class="reg-back-btn" style="flex:1;" onclick="regGoTo(1)">← ATRÁS</button>
            <button type="button" class="glass-btn" style="flex:2;" id="reg-send-code-btn" onclick="regSendVerificationCode()">ENVIAR CÓDIGO →</button>
          </div>
        </div>

        <!-- PASO 3: Verificación de código -->
        <div class="reg-step" id="reg-step-3">
          <div class="reg-step-title">Paso 3 de 6 — Verifica tu correo</div>
          <p style="color:#999;font-size:0.82rem;text-align:center;margin:0 0 14px;line-height:1.5">
            Enviamos un código de 6 dígitos a <strong id="reg-verify-email-display" style="color:#ff4444"></strong>. Ingrésalo a continuación.
          </p>
          <div class="glass-input-group">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <input type="text" id="register-simple-codigo" class="glass-input" placeholder="Código de 6 dígitos" maxlength="6" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code">
          </div>
          <div id="reg-code-timer" style="text-align:center;margin:8px 0;font-size:0.78rem;color:#666"></div>
          <div style="text-align:center;margin-bottom:10px">
            <button type="button" id="reg-resend-btn" class="reg-resend-link" onclick="regResendCode()" disabled>Reenviar código</button>
          </div>
          <div style="display:flex;gap:8px;margin-top:8px;">
            <button type="button" class="reg-back-btn" style="flex:1;" onclick="regGoTo(2)">← ATRÁS</button>
            <button type="button" class="glass-btn" style="flex:2;" id="reg-verify-btn" onclick="regVerifyCode()">VERIFICAR ✓</button>
          </div>
        </div>

        <!-- PASO 4: Contraseña -->
        <div class="reg-step" id="reg-step-4">
          <div class="reg-step-title">Paso 4 de 6 — Crea una contraseña</div>
          <div class="glass-input-group" style="margin-bottom:10px;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c1.657 0 3-1.343 3-3V7a3 3 0 10-6 0v1c0 1.657 1.343 3 3 3zM5 11h14v10a2 2 0 01-2 2H7a2 2 0 01-2-2V11z"/></svg>
            <input type="password" id="register-simple-password" class="glass-input" placeholder="Contraseña (mín. 6 caracteres)">
          </div>
          <div class="glass-input-group">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c1.657 0 3-1.343 3-3V7a3 3 0 10-6 0v1c0 1.657 1.343 3 3 3zM5 11h14v10a2 2 0 01-2 2H7a2 2 0 01-2-2V11z"/></svg>
            <input type="password" id="register-simple-password2" class="glass-input" placeholder="Repetir contraseña">
          </div>
          <div style="display:flex;gap:8px;margin-top:14px;">
            <button type="button" class="reg-back-btn" style="flex:1;" onclick="regGoTo(3)">← ATRÁS</button>
            <button type="button" class="glass-btn" style="flex:2;" onclick="regValidateAndNext(4)">SIGUIENTE →</button>
          </div>
        </div>

        <!-- PASO 5: Dirección -->
        <div class="reg-step" id="reg-step-5">
          <div class="reg-step-title">Paso 5 de 6 — Tu dirección</div>
          <div class="glass-input-group">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 12.414a4 4 0 10-5.657 5.657l4.243 4.243a8 8 0 1011.314-11.314l-4.243 4.243z"/></svg>
            <input type="text" id="register-simple-direccion" class="glass-input" placeholder="Dirección completa">
          </div>
          <div style="display:flex;gap:8px;margin-top:14px;">
            <button type="button" class="reg-back-btn" style="flex:1;" onclick="regGoTo(4)">← ATRÁS</button>
            <button type="button" class="glass-btn" style="flex:2;" onclick="regValidateAndNext(5)">SIGUIENTE →</button>
          </div>
        </div>

        <!-- PASO 6: Teléfono + Submit -->
        <div class="reg-step" id="reg-step-6">
          <div class="reg-step-title">Paso 6 de 6 — Tu teléfono</div>
          <div class="glass-input-group">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2a1 1 0 011.11-.21c1.21.49 2.53.76 3.88.76a1 1 0 011 1v3.5a1 1 0 01-1 1C10.07 22 2 13.93 2 4.5A1 1 0 013 3.5H6.5a1 1 0 011 1c0 1.35.27 2.67.76 3.88a1 1 0 01-.21 1.11l-2.2 2.2z"/></svg>
            <input type="tel" id="register-simple-telefono" class="glass-input" placeholder="Número de teléfono">
          </div>
          <div style="display:flex;gap:8px;margin-top:14px;">
            <button type="button" class="reg-back-btn" style="flex:1;" onclick="regGoTo(5)">← ATRÁS</button>
            <button type="submit" class="glass-btn" style="flex:2;" id="reg-submit-btn">✓ COMPLETAR REGISTRO</button>
          </div>
        </div>
      </form>

      <div class="w-full flex items-center my-2">
        <span class="flex-1 h-px bg-[#333]"></span>
        <span class="mx-3 text-gray-400 font-bold text-base">o</span>
        <span class="flex-1 h-px bg-[#333]"></span>
      </div>
      <a href="api/google-login.php" class="google-btn-compact mt-1">
        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/google/google-original.svg" alt="Google" class="google-icon-compact">
        <span class="google-btn-compact-text">Continuar con Google</span>
      </a>
    </div>
  </div>
</div>

<script>
// ========== FUNCIONES GLOBALES DEL MODAL ==========

// Abrir/Cerrar modal
function abrirModalLogin() {
  document.getElementById('modal-login').classList.remove('hidden');
}
function cerrarModalLogin() {
  document.getElementById('modal-login').classList.add('hidden');
}

// ========== TABS ==========
document.addEventListener('DOMContentLoaded', function() {
  var modalTabLogin = document.getElementById('modal-tab-login');
  var modalTabRegister = document.getElementById('modal-tab-register');
  var modalLoginForm = document.getElementById('modal-glass-login-form');
  var modalRegisterForm = document.getElementById('modal-glass-register-form');
  var modalLoginTitle = document.getElementById('modal-login-title');
  var modalLoginSubtitle = document.getElementById('modal-login-subtitle');

  if (modalTabLogin && modalTabRegister) {
    modalTabLogin.onclick = function() {
      modalTabLogin.classList.add('active');
      modalTabRegister.classList.remove('active');
      modalLoginForm.style.display = '';
      modalLoginForm.classList.remove('hidden');
      modalRegisterForm.style.display = 'none';
      modalRegisterForm.classList.add('hidden');
      modalLoginTitle.textContent = 'Iniciar Sesión';
      modalLoginSubtitle.textContent = 'Accede a tu cuenta para continuar.';
    };

    modalTabRegister.onclick = function() {
      modalTabRegister.classList.add('active');
      modalTabLogin.classList.remove('active');
      modalRegisterForm.style.display = '';
      modalRegisterForm.classList.remove('hidden');
      modalLoginForm.style.display = 'none';
      modalLoginForm.classList.add('hidden');
      modalLoginTitle.textContent = 'Crear Cuenta';
      modalLoginSubtitle.textContent = 'Regístrate paso a paso.';
      // Resetear al paso 1
      regGoTo(1);
      var form = document.getElementById('modal-form-register-simple');
      if (form) form.reset();
      regHideError();
    };
  }

  // Cerrar modal con Escape
  window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarModalLogin();
  });
});

// ========== REGISTRO MULTI-PASO ==========

var regCurrentStep = 1;
var regTotalSteps = 6;
var regCodeSent = false;
var regCodeVerified = false;
var regResendTimer = null;
var regResendCountdown = 0;

function regShowError(msg) {
  var err = document.getElementById('modal-register-error');
  if (err) { err.textContent = msg; err.style.display = 'block'; }
}
function regHideError() {
  var err = document.getElementById('modal-register-error');
  if (err) { err.textContent = ''; err.style.display = 'none'; }
}

function regGoTo(step) {
  regHideError();
  regCurrentStep = step;
  // Ocultar todos los pasos
  for (var i = 1; i <= regTotalSteps; i++) {
    var el = document.getElementById('reg-step-' + i);
    if (el) { el.classList.remove('reg-step-active'); }
  }
  // Mostrar paso actual
  var current = document.getElementById('reg-step-' + step);
  if (current) { current.classList.add('reg-step-active'); }
  // Actualizar barra de progreso
  for (var j = 1; j <= regTotalSteps; j++) {
    var dot = document.getElementById('reg-dot-' + j);
    if (!dot) continue;
    dot.className = 'reg-progress-dot';
    if (j < step) dot.classList.add('reg-dot-done');
    else if (j === step) dot.classList.add('reg-dot-current');
  }
  // Autofocus al input del paso
  if (current) {
    var inp = current.querySelector('input');
    if (inp) setTimeout(function(){ inp.focus(); }, 80);
  }
}

function regValidateAndNext(step) {
  regHideError();
  if (step === 1) {
    var v = document.getElementById('register-simple-nombre').value.trim();
    if (!v) { regShowError('Ingresa tu nombre completo para continuar.'); return; }
    regGoTo(2);
  } else if (step === 4) {
    var p1 = document.getElementById('register-simple-password').value;
    var p2 = document.getElementById('register-simple-password2').value;
    if (p1.length < 6) { regShowError('La contraseña debe tener al menos 6 caracteres.'); return; }
    if (p1 !== p2) { regShowError('Las contraseñas no coinciden.'); return; }
    regGoTo(5);
  } else if (step === 5) {
    var v = document.getElementById('register-simple-direccion').value.trim();
    if (!v) { regShowError('Ingresa tu dirección para continuar.'); return; }
    regGoTo(6);
  }
}

// ========== VALIDACIÓN DE EMAIL GLOBAL ==========

/**
 * Valida formato de email estrictamente.
 * Requiere: usuario@dominio.extension
 * La extensión debe tener entre 2 y 10 caracteres (soporta .com, .co, .org, .com.co, etc.)
 * No permite: espacios, caracteres especiales inválidos, dominios sin TLD real
 */
function validarEmail(email) {
    if (!email || typeof email !== 'string') return false;
    email = email.trim();
    // Regex: usuario válido @ dominio con al menos un punto y TLD de 2-10 chars
    var re = /^[a-zA-Z0-9](?:[a-zA-Z0-9._%+\-]*[a-zA-Z0-9])?@[a-zA-Z0-9](?:[a-zA-Z0-9\-]*[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9\-]*[a-zA-Z0-9])?)*\.[a-zA-Z]{2,10}$/;
    if (!re.test(email)) return false;
    // No permitir puntos consecutivos en la parte local
    if (email.split('@')[0].indexOf('..') !== -1) return false;
    return true;
}

// ========== ENVÍO Y VERIFICACIÓN DE CÓDIGO ==========

async function regSendVerificationCode() {
  regHideError();
  var email = document.getElementById('register-simple-email').value.trim();
  var nombre = document.getElementById('register-simple-nombre').value.trim();
  if (!validarEmail(email)) {
    regShowError('Ingresa un correo electrónico válido (ejemplo: nombre@gmail.com).');
    return;
  }

  var btn = document.getElementById('reg-send-code-btn');
  btn.disabled = true;
  btn.textContent = 'Enviando...';

  try {
    var fd = new FormData();
    fd.append('email', email);
    fd.append('nombre', nombre);
    var res = await fetch('api/enviar_codigo_registro.php', { method: 'POST', body: fd });
    var data = await res.json();

    if (data.ok) {
      regCodeSent = true;
      document.getElementById('reg-verify-email-display').textContent = email;
      regGoTo(3);
      regStartResendTimer(60);
    } else {
      regShowError(data.msg || 'Error al enviar código.');
    }
  } catch(err) {
    regShowError('Error de conexión. Intenta de nuevo.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'ENVIAR CÓDIGO →';
  }
}

async function regVerifyCode() {
  regHideError();
  var codigo = document.getElementById('register-simple-codigo').value.trim();
  var email = document.getElementById('register-simple-email').value.trim();

  if (!codigo || codigo.length !== 6) {
    regShowError('Ingresa el código de 6 dígitos.');
    return;
  }

  var btn = document.getElementById('reg-verify-btn');
  btn.disabled = true;
  btn.textContent = 'Verificando...';

  try {
    var fd = new FormData();
    fd.append('codigo', codigo);
    fd.append('email', email);
    var res = await fetch('api/verificar_codigo_registro.php', { method: 'POST', body: fd });
    var data = await res.json();

    if (data.ok) {
      regCodeVerified = true;
      if (typeof showToast === 'function') showToast('¡Correo verificado!', 'success', 3000);
      regGoTo(4);
    } else {
      regShowError(data.msg || 'Código incorrecto.');
    }
  } catch(err) {
    regShowError('Error de conexión. Intenta de nuevo.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'VERIFICAR ✓';
  }
}

async function regResendCode() {
  regHideError();
  var email = document.getElementById('register-simple-email').value.trim();
  var nombre = document.getElementById('register-simple-nombre').value.trim();

  var btn = document.getElementById('reg-resend-btn');
  btn.disabled = true;
  btn.textContent = 'Enviando...';

  try {
    var fd = new FormData();
    fd.append('email', email);
    fd.append('nombre', nombre);
    var res = await fetch('api/enviar_codigo_registro.php', { method: 'POST', body: fd });
    var data = await res.json();

    if (data.ok) {
      regStartResendTimer(60);
      if (typeof showToast === 'function') showToast('Nuevo código enviado a tu correo.', 'success', 3000);
    } else {
      regShowError(data.msg || 'Error al reenviar código.');
      btn.disabled = false;
      btn.textContent = 'Reenviar código';
    }
  } catch(err) {
    regShowError('Error de conexión.');
    btn.disabled = false;
    btn.textContent = 'Reenviar código';
  }
}

function regStartResendTimer(seconds) {
  regResendCountdown = seconds;
  var btn = document.getElementById('reg-resend-btn');
  var timerEl = document.getElementById('reg-code-timer');
  btn.disabled = true;

  if (regResendTimer) clearInterval(regResendTimer);

  function tick() {
    if (regResendCountdown <= 0) {
      clearInterval(regResendTimer);
      btn.disabled = false;
      btn.textContent = 'Reenviar código';
      if (timerEl) timerEl.textContent = '';
      return;
    }
    btn.textContent = 'Reenviar en ' + regResendCountdown + 's';
    if (timerEl) timerEl.textContent = 'El código expira en 10 minutos';
    regResendCountdown--;
  }
  tick();
  regResendTimer = setInterval(tick, 1000);
}

// Avanzar con Enter en cada paso
document.addEventListener('keydown', function(e) {
  if (e.key !== 'Enter') return;
  var t = e.target;
  if (!t || !t.closest) return;
  if (!t.closest('#modal-form-register-simple')) return;
  var stepDiv = t.closest('.reg-step');
  if (!stepDiv) return;
  var stepNum = parseInt(stepDiv.id.replace('reg-step-', ''));
  if (stepNum < regTotalSteps) {
    e.preventDefault();
    regValidateAndNext(stepNum);
  }
});

// ========== SUBMIT DE REGISTRO ==========
document.addEventListener('DOMContentLoaded', function() {
  var formReg = document.getElementById('modal-form-register-simple');
  if (!formReg) return;

  formReg.addEventListener('submit', async function(e) {
    e.preventDefault();
    regHideError();

    var nombre = document.getElementById('register-simple-nombre').value.trim();
    var email = document.getElementById('register-simple-email').value.trim();
    var pass = document.getElementById('register-simple-password').value;
    var direccion = document.getElementById('register-simple-direccion').value.trim();
    var telefono = document.getElementById('register-simple-telefono').value.trim();

    if (!telefono) { regShowError('Ingresa tu teléfono para finalizar.'); return; }

    var btn = document.getElementById('reg-submit-btn');
    btn.disabled = true;
    btn.textContent = 'Registrando...';

    try {
      var formData = new FormData();
      formData.append('nombre', nombre);
      formData.append('email', email);
      formData.append('password', pass);
      formData.append('direccion', direccion);
      formData.append('telefono', telefono);

      var res = await fetch('api/registro.php', { method: 'POST', body: formData });
      var data = await res.json();

      if (data.ok) {
        showToast(data.msg, 'success', 4000);
        setTimeout(function() { window.location.reload(); }, 1000);
      } else {
        regShowError(data.msg || 'Error al registrarse.');
      }
    } catch (err) {
      console.error(err);
      regShowError('Error de conexión. Intenta de nuevo.');
    } finally {
      btn.disabled = false;
      btn.textContent = '✓ COMPLETAR REGISTRO';
    }
  });
});

// ========== LOGIN AJAX ==========
document.addEventListener('DOMContentLoaded', function() {
  var formLogin = document.getElementById('modal-form-login');
  if (!formLogin) return;
  var emailInput = document.getElementById('modal-login-email');
  var passInput = document.getElementById('modal-login-password');
  var errorBox = document.getElementById('modal-login-error');
  var btn = document.getElementById('modal-btn-login');
  var spinner = document.getElementById('modal-spinner-login');

  formLogin.addEventListener('submit', async function(e) {
    e.preventDefault();
    errorBox.textContent = '';
    btn.setAttribute('disabled', 'disabled');
    if (spinner) spinner.classList.remove('hidden');
    try {
      var formData = new FormData();
      formData.append('email', emailInput.value.trim());
      formData.append('password', passInput.value);
      var res = await fetch('api/login_backend.php', { method: 'POST', body: formData });
      var data = await res.json();
      if (data.ok) {
        cerrarModalLogin();
        window.location.reload();
      } else {
        errorBox.textContent = data.msg || 'Error al iniciar sesión.';
      }
    } catch (err) {
      errorBox.textContent = 'Error de conexión. Intenta de nuevo.';
    } finally {
      btn.removeAttribute('disabled');
      if (spinner) spinner.classList.add('hidden');
    }
  });
});

// ========== FORGOT PASSWORD ==========
function mostrarForgotPassword() {
  document.getElementById('modal-glass-login-form').style.display = 'none';
  document.getElementById('modal-glass-register-form').style.display = 'none';
  document.getElementById('modal-glass-forgot-form').style.display = '';
  document.getElementById('modal-login-title').textContent = 'Recuperar Contraseña';
  document.getElementById('modal-login-subtitle').textContent = 'Te ayudamos a recuperar el acceso a tu cuenta.';
  document.querySelector('.glass-tabs').style.display = 'none';
  var fm = document.getElementById('forgot-msg');
  fm.style.display = 'none';
  document.getElementById('forgot-email').value = '';
  document.getElementById('forgot-email').focus();
}

function volverALogin() {
  document.getElementById('modal-glass-forgot-form').style.display = 'none';
  document.getElementById('modal-glass-login-form').style.display = '';
  document.querySelector('.glass-tabs').style.display = '';
  document.getElementById('modal-login-title').textContent = 'Iniciar Sesión';
  document.getElementById('modal-login-subtitle').textContent = 'Accede a tu cuenta para continuar.';
  document.getElementById('modal-tab-login').classList.add('active');
  document.getElementById('modal-tab-register').classList.remove('active');
}

document.addEventListener('DOMContentLoaded', function() {
  var formForgot = document.getElementById('modal-form-forgot');
  if (!formForgot) return;
  
  formForgot.addEventListener('submit', async function(e) {
    e.preventDefault();
    var email = document.getElementById('forgot-email').value.trim();
    var btn = document.getElementById('forgot-btn');
    var spinner = document.getElementById('forgot-spinner');
    var fm = document.getElementById('forgot-msg');
    
    if (!email) return;
    
    btn.disabled = true;
    if (spinner) spinner.classList.remove('hidden');
    fm.style.display = 'none';
    
    try {
      var fd = new FormData();
      fd.append('email', email);
      var res = await fetch('api/forgot_password.php', { method: 'POST', body: fd });
      var data = await res.json();
      
      fm.style.display = 'block';
      if (data.ok) {
        fm.style.background = 'rgba(74,222,128,0.1)';
        fm.style.border = '1px solid rgba(74,222,128,0.25)';
        fm.style.color = '#4ade80';
        fm.textContent = data.msg;
        document.getElementById('modal-form-forgot').style.display = 'none';
      } else {
        fm.style.background = '#ff474722';
        fm.style.border = '1px solid rgba(255,71,71,0.25)';
        fm.style.color = '#ff4747';
        fm.textContent = data.msg;
      }
    } catch(err) {
      fm.style.display = 'block';
      fm.style.background = '#ff474722';
      fm.style.border = '1px solid rgba(255,71,71,0.25)';
      fm.style.color = '#ff4747';
      fm.textContent = 'Error de conexión. Intenta de nuevo.';
    } finally {
      btn.disabled = false;
      if (spinner) spinner.classList.add('hidden');
    }
  });
});
</script>

<?php if (!isset($_SESSION['usuario'])): ?>
<script>
// Abrir modal si la URL tiene ?login=1
if (window.location.search.indexOf('login=1') !== -1) {
  window.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('modal-login');
    if (modal) {
        modal.classList.remove('hidden');
        var newUrl = window.location.pathname + window.location.hash;
        window.history.replaceState({path:newUrl}, document.title, newUrl);
    }
  });
}
</script>
<?php endif; ?>

<!-- Contenedor de notificaciones toast -->
<div id="toast-container" class="fixed top-20 right-4 z-[900] space-y-2"></div>

<script>
// Función para mostrar notificaciones toast
function showToast(message, type, duration) {
    type = type || 'error';
    duration = duration || 5000;
    var toastContainer = document.getElementById('toast-container');

    var toast = document.createElement('div');
    toast.className = 'toast-notification ' + type + ' transform translate-x-full opacity-0 transition-all duration-300 ease-in-out';

    var ringColor, icon, barGradient, title;
    switch (type) {
        case 'success':
            ringColor = 'ring-red-500/40';
            icon = '<svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
            barGradient = 'from-red-500 via-red-600 to-red-700';
            title = 'Acción realizada';
            break;
        case 'warning':
            ringColor = 'ring-red-500/40';
            icon = '<svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-2.5L13.73 4c-.77-.83-1.96-.83-2.73 0L3.2 16.5c-.77.83.19 2.5 1.73 2.5z"/></svg>';
            barGradient = 'from-red-500 via-red-600 to-red-700';
            title = 'Atención';
            break;
        case 'error':
        default:
            ringColor = 'ring-red-500/40';
            icon = '<svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-2.5L13.73 4c-.77-.83-1.96-.83-2.73 0L3.2 16.5c-.77.83.19 2.5 1.73 2.5z"/></svg>';
            barGradient = 'from-red-500 via-red-600 to-red-700';
            title = 'Ocurrió un error';
            break;
    }

    var actionHtml = (type === 'error')
        ? '<button onclick="document.getElementById(\'modal-login\').classList.remove(\'hidden\'); document.getElementById(\'modal-tab-register\').click();" class="mt-2 text-xs bg-red-600 hover:bg-red-700 text-white font-bold py-1.5 px-2.5 rounded-md transition-colors">Registrarse ahora</button>'
        : '';

    toast.innerHTML =
        '<div class="relative max-w-sm p-4 rounded-2xl bg-[#141414]/85 text-gray-100 border border-[#333] backdrop-blur-md shadow-2xl ring-1 ' + ringColor + '">' +
            '<div class="flex items-start gap-3">' +
                '<div class="flex-shrink-0">' + icon + '</div>' +
                '<div class="flex-1">' +
                    '<div class="text-sm font-semibold tracking-wide">' + title + '</div>' +
                    '<div class="text-xs mt-1 opacity-90">' + message + '</div>' +
                    actionHtml +
                '</div>' +
                '<button onclick="this.closest(\'.toast-notification\').remove()" class="flex-shrink-0 ml-1 text-gray-400 hover:text-white">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>' +
                '</button>' +
            '</div>' +
            '<div class="absolute left-0 right-0 bottom-0 h-1 overflow-hidden rounded-b-2xl">' +
                '<div class="progress-bar h-1 bg-gradient-to-r ' + barGradient + '"></div>' +
            '</div>' +
        '</div>';

    toastContainer.appendChild(toast);

    setTimeout(function() {
        toast.classList.remove('translate-x-full', 'opacity-0');
        toast.classList.add('translate-x-0', 'opacity-100');
    }, 100);

    var bar = toast.querySelector('.progress-bar');
    if (bar) {
        bar.style.width = '100%';
        bar.style.transition = 'width ' + duration + 'ms linear';
        requestAnimationFrame(function() { bar.style.width = '0%'; });
    }

    setTimeout(function() {
        toast.classList.add('translate-x-full', 'opacity-0');
        setTimeout(function() { if (toast.parentNode) toast.remove(); }, 300);
    }, duration);
}

// Mostrar notificación si hay error de Google
<?php if (isset($_SESSION['error_google'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    var message = '<?php echo addslashes($_SESSION['error_google']); ?>';
    showToast(message, 'error', 5000);
    try {
        var url = new URL(window.location.href);
        if (url.searchParams.has('error')) {
            url.searchParams.delete('error');
            window.history.replaceState({}, document.title, url.pathname + (url.search ? '?' + url.searchParams.toString() : '') + url.hash);
        }
    } catch(e) {}
});
<?php unset($_SESSION['error_google']); ?>
<?php endif; ?>

// Toast de éxito al iniciar sesión
<?php if (isset($_SESSION['login_success'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    var message = '<?php echo addslashes($_SESSION['login_success']); ?>';
    showToast(message, 'success', 4000);
});
<?php unset($_SESSION['login_success']); ?>
<?php endif; ?>

// Toast de cierre de sesión
<?php if (isset($_GET['event']) && $_GET['event'] === 'logout'): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast('Sesión cerrada correctamente.', 'success', 3500);
    try {
        var url = new URL(window.location.href);
        url.searchParams.delete('event');
        window.history.replaceState({}, document.title, url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash);
    } catch(e) {}
});
<?php endif; ?>
</script>
