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
          <a href="#" class="text-sm text-red-500 hover:underline">¿Olvidaste tu contraseña?</a>
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
      <a href="google-login.php" class="glass-google-btn">
        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/google/google-original.svg" alt="Google" class="w-5 h-5">
        Iniciar sesión con Google
      </a>
    </div>
    <div id="modal-glass-register-form" class="hidden flex flex-col items-center justify-center">
      <form class="space-y-2 w-full max-w-xs" id="modal-form-register-simple" autocomplete="off">
        <div id="modal-register-error" class="error-msg mb-2"></div>
        <div class="glass-input-group">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12A4 4 0 118 12a4 4 0 018 0z"/></svg>
          <input type="text" id="register-simple-nombre" class="glass-input" placeholder="Nombre completo">
        </div>
        <div class="glass-input-group">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          <input type="email" id="register-simple-email" class="glass-input" placeholder="Correo electrónico">
        </div>
        <div class="glass-input-group">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c1.657 0 3-1.343 3-3V7a3 3 0 10-6 0v1c0 1.657 1.343 3 3 3zM5 11h14v10a2 2 0 01-2 2H7a2 2 0 01-2-2V11z"/></svg>
          <input type="password" id="register-simple-password" class="glass-input" placeholder="Contraseña">
        </div>
        <div class="glass-input-group">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c1.657 0 3-1.343 3-3V7a3 3 0 10-6 0v1c0 1.657 1.343 3 3 3zM5 11h14v10a2 2 0 01-2 2H7a2 2 0 01-2-2V11z"/></svg>
          <input type="password" id="register-simple-password2" class="glass-input" placeholder="Repetir contraseña">
        </div>
        <div class="glass-input-group">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 12.414a4 4 0 10-5.657 5.657l4.243 4.243a8 8 0 1011.314-11.314l-4.243 4.243z"/></svg>
          <input type="text" id="register-simple-direccion" class="glass-input" placeholder="Dirección">
        </div>
        <div class="glass-input-group">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2a1 1 0 011.11-.21c1.21.49 2.53.76 3.88.76a1 1 0 011 1v3.5a1 1 0 01-1 1C10.07 22 2 13.93 2 4.5A1 1 0 013 3.5H6.5a1 1 0 011 1c0 1.35.27 2.67.76 3.88a1 1 0 01-.21 1.11l-2.2 2.2z"/></svg>
          <input type="tel" id="register-simple-telefono" class="glass-input" placeholder="Número de teléfono">
        </div>
        <div class="glass-input-group">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.586-6.586a2 2 0 10-2.828-2.828z"/></svg>
          <input type="file" id="register-simple-foto" class="glass-input" placeholder="Foto de perfil (opcional)">
        </div>
        <button type="submit" class="glass-btn w-full">Registrarse</button>
      </form>
      <div class="w-full flex items-center my-2">
        <span class="flex-1 h-px bg-[#333]"></span>
        <span class="mx-3 text-gray-400 font-bold text-base">o</span>
        <span class="flex-1 h-px bg-[#333]"></span>
      </div>
      <a href="google-login.php" class="google-btn-compact mt-2">
        <img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/google/google-original.svg" alt="Google" class="google-icon-compact">
        <span class="google-btn-compact-text">Continuar con Google</span>
      </a>
    </div>
  </div>
</div>

<script>
// Función global para abrir el modal de login
function abrirModalLogin() {
  document.getElementById('modal-login').classList.remove('hidden');
}

// Función global para cerrar el modal de login
function cerrarModalLogin() {
  document.getElementById('modal-login').classList.add('hidden');
}

// Tabs funcionalidad
document.addEventListener('DOMContentLoaded', function() {
  const modalTabLogin = document.getElementById('modal-tab-login');
  const modalTabRegister = document.getElementById('modal-tab-register');
  const modalLoginForm = document.getElementById('modal-glass-login-form');
  const modalRegisterForm = document.getElementById('modal-glass-register-form');
  const modalLoginTitle = document.getElementById('modal-login-title');
  const modalLoginSubtitle = document.getElementById('modal-login-subtitle');
  
  if (modalTabLogin && modalTabRegister) {
    modalTabLogin.onclick = () => {
      modalTabLogin.classList.add('active');
      modalTabRegister.classList.remove('active');
      modalLoginForm.classList.remove('hidden');
      modalRegisterForm.classList.add('hidden');
      modalLoginTitle.textContent = 'Iniciar Sesión';
      modalLoginSubtitle.textContent = 'Accede a tu cuenta para continuar.';
    };
    
    modalTabRegister.onclick = () => {
      modalTabRegister.classList.add('active');
      modalTabLogin.classList.remove('active');
      modalRegisterForm.classList.remove('hidden');
      modalLoginForm.classList.add('hidden');
      modalLoginTitle.textContent = 'Crear Cuenta';
      modalLoginSubtitle.textContent = 'Regístrate para disfrutar de la mejor tecnología.';
    };
  }
  
  // Cerrar modal con Escape
  window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') cerrarModalLogin();
  });
});
</script>

<?php if (!isset($_SESSION['usuario'])): ?>
<script>
// Abrir modal de login automáticamente si la URL tiene ?login=1 (solo si no hay sesión)
// Y limpiar la URL para evitar que se abra al navegar en el historial
if (window.location.search.includes('login=1')) {
  window.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modal-login');
    if (modal) {
        modal.classList.remove('hidden');
        // Limpiar el parámetro de la URL sin recargar la página
        const newUrl = window.location.pathname + window.location.hash;
        window.history.replaceState({path:newUrl}, document.title, newUrl);
    }
  });
}
</script>
<?php endif; ?>

<script>
// Enviar login del modal por AJAX y recargar en éxito
document.addEventListener('DOMContentLoaded', function() {
  const formLogin = document.getElementById('modal-form-login');
  if (!formLogin) return;
  const emailInput = document.getElementById('modal-login-email');
  const passInput = document.getElementById('modal-login-password');
  const errorBox = document.getElementById('modal-login-error');
  const btn = document.getElementById('modal-btn-login');
  const spinner = document.getElementById('modal-spinner-login');

  formLogin.addEventListener('submit', async function(e) {
    e.preventDefault();
    errorBox.textContent = '';
    btn.setAttribute('disabled', 'disabled');
    if (spinner) spinner.classList.remove('hidden');
    try {
      const formData = new FormData();
      formData.append('email', emailInput.value.trim());
      formData.append('password', passInput.value);
      const res = await fetch('login_backend.php', { method: 'POST', body: formData });
      const data = await res.json();
      if (data.ok) {
        // Cerrar modal y recargar para refrescar header y mostrar toast desde sesión
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
</script>

<script>
// Manejo del formulario de registro simple
document.addEventListener('DOMContentLoaded', function() {
    const formRegister = document.getElementById('modal-form-register-simple');
    if (!formRegister) return;

    const errorBox = document.getElementById('modal-register-error');
    const btnRegister = formRegister.querySelector('button[type="submit"]');
    
    // Inputs
    const inputNombre = document.getElementById('register-simple-nombre');
    const inputEmail = document.getElementById('register-simple-email');
    const inputPass = document.getElementById('register-simple-password');
    const inputPass2 = document.getElementById('register-simple-password2');
    const inputDireccion = document.getElementById('register-simple-direccion');
    const inputTelefono = document.getElementById('register-simple-telefono');
    const inputFoto = document.getElementById('register-simple-foto');

    formRegister.addEventListener('submit', async function(e) {
        e.preventDefault();
        errorBox.textContent = '';
        errorBox.className = 'error-msg mb-2 text-red-500 text-sm text-center font-bold block'; // Asegurar visibilidad
        
        // Validaciones básicas
        if (!inputNombre.value.trim() || !inputEmail.value.trim() || !inputPass.value || !inputDireccion.value.trim() || !inputTelefono.value.trim()) {
            errorBox.textContent = 'Por favor completa todos los campos obligatorios.';
            return;
        }
        
        if (inputPass.value.length < 6) {
            errorBox.textContent = 'La contraseña debe tener al menos 6 caracteres.';
            return;
        }

        if (inputPass.value !== inputPass2.value) {
            errorBox.textContent = 'Las contraseñas no coinciden.';
            return;
        }

        // Preparar envío
        btnRegister.disabled = true;
        btnRegister.textContent = 'Registrando...';

        try {
            const formData = new FormData();
            formData.append('nombre', inputNombre.value.trim());
            formData.append('email', inputEmail.value.trim());
            formData.append('password', inputPass.value);
            formData.append('direccion', inputDireccion.value.trim());
            formData.append('telefono', inputTelefono.value.trim());
            
            if (inputFoto.files[0]) {
                formData.append('foto', inputFoto.files[0]);
            }

            const res = await fetch('registro.php', {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (data.ok) {
                // Éxito: mostrar mensaje y recargar
                // Usar showToast si está disponible, o alert
                if (typeof showToast === 'function') {
                     showToast(data.msg, 'success');
                } else {
                     alert(data.msg);
                }
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                errorBox.textContent = data.msg || 'Error al registrarse.';
            }

        } catch (err) {
            console.error(err);
            errorBox.textContent = 'Error de conexión. Intenta de nuevo.';
        } finally {
            btnRegister.disabled = false;
            btnRegister.textContent = 'Registrarse';
        }
    });

    // Resetear formulario al cambiar de tab
    const tabRegister = document.getElementById('modal-tab-register');
    if (tabRegister) {
        tabRegister.addEventListener('click', function() {
            formRegister.reset();
            errorBox.textContent = '';
        });
    }
});
</script>

<!-- Contenedor de notificaciones toast -->
<div id="toast-container" class="fixed top-20 right-4 z-[900] space-y-2"></div>

<script>
// Función para mostrar notificaciones toast (diseño moderno y genérico)
function showToast(message, type = 'error', duration = 5000) {
    const toastContainer = document.getElementById('toast-container');

    // Crear el elemento toast
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type} transform translate-x-full opacity-0 transition-all duration-300 ease-in-out`;

    // Estilos por tipo (todo en paleta roja para cohesión visual)
    let ringColor, icon, barGradient, title;
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

    // Si es error, ofrecer CTA de registro si aplica
    const actionHtml = (type === 'error')
        ? '<button onclick="document.getElementById(\'modal-login\').classList.remove(\'hidden\'); document.getElementById(\'modal-tab-register\').click();" class="mt-2 text-xs bg-red-600 hover:bg-red-700 text-white font-bold py-1.5 px-2.5 rounded-md transition-colors">Registrarse ahora</button>'
        : '';

    // Plantilla moderna con glassmorphism y barra de progreso
    toast.innerHTML = `
        <div class="relative max-w-sm p-4 rounded-2xl bg-[#141414]/85 text-gray-100 border border-[#333] backdrop-blur-md shadow-2xl ring-1 ${ringColor}">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">${icon}</div>
                <div class="flex-1">
                    <div class="text-sm font-semibold tracking-wide">${title}</div>
                    <div class="text-xs mt-1 opacity-90">${message}</div>
                    ${actionHtml}
                </div>
                <button onclick="this.closest('.toast-notification').remove()" class="flex-shrink-0 ml-1 text-gray-400 hover:text-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="absolute left-0 right-0 bottom-0 h-1 overflow-hidden rounded-b-2xl">
                <div class="progress-bar h-1 bg-gradient-to-r ${barGradient}"></div>
            </div>
        </div>
    `;

    // Agregar al contenedor
    toastContainer.appendChild(toast);

    // Animar entrada
    setTimeout(() => {
        toast.classList.remove('translate-x-full', 'opacity-0');
        toast.classList.add('translate-x-0', 'opacity-100');
    }, 100);

    // Animar barra de progreso durante "duration"
    const bar = toast.querySelector('.progress-bar');
    if (bar) {
        bar.style.width = '100%';
        bar.style.transition = `width ${duration}ms linear`;
        requestAnimationFrame(() => {
            bar.style.width = '0%';
        });
    }

    // Auto-remover
    setTimeout(() => {
        toast.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => {
            if (toast.parentNode) toast.remove();
        }, 300);
    }, duration);
}

// Mostrar notificación si hay error de Google
<?php if (isset($_SESSION['error_google'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    const message = '<?php echo addslashes($_SESSION['error_google']); ?>';
    showToast(message, 'error', 5000);
    // Limpiar el parámetro `error` de la URL para que no reaparezca al refrescar
    try {
        const url = new URL(window.location.href);
        if (url.searchParams.has('error')) {
            url.searchParams.delete('error');
            window.history.replaceState({}, document.title, url.pathname + (url.search ? '?' + url.searchParams.toString() : '') + url.hash);
        }
    } catch(e) {}
});
<?php unset($_SESSION['error_google']); ?>
<?php endif; ?>

// Toast de éxito al iniciar sesión (normal o Google)
<?php if (isset($_SESSION['login_success'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    const message = '<?php echo addslashes($_SESSION['login_success']); ?>';
    showToast(message, 'success', 4000);
});
<?php unset($_SESSION['login_success']); ?>
<?php endif; ?>

// Toast de cierre de sesión
<?php if (isset($_GET['event']) && $_GET['event'] === 'logout'): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast('Sesión cerrada correctamente.', 'success', 3500);
    // Limpiar el parámetro de la URL para evitar repetición
    try {
        const url = new URL(window.location.href);
        url.searchParams.delete('event');
        window.history.replaceState({}, document.title, url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash);
    } catch(e) {}
});
<?php endif; ?>
</script>
