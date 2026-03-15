// Aquí puedes agregar scripts personalizados si lo necesitas 

document.addEventListener('DOMContentLoaded', function () {

    // Registro paso a paso (wizard)
    const steps = [
        'register-step-1',
        'register-step-2',
        'register-step-3',
        'register-step-4',
        'register-step-5',
        'register-step-6',
        'register-step-7'
    ];
    let currentStep = 0;
    const errorDiv = document.getElementById('modal-register-error');
    function showStep(idx) {
        steps.forEach((id, i) => {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('hidden', i !== idx);
        });
        errorDiv.textContent = '';
    }
    function validateStep(idx) {
        // Validación básica por paso
        switch (idx) {
            case 0:
                const nombre = document.getElementById('register-nombre').value.trim();
                if (!nombre) return 'Por favor ingresa tu nombre completo.';
                break;
            case 1:
                const email = document.getElementById('register-email').value.trim();
                if (!email || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return 'Ingresa un correo válido.';
                break;
            case 2:
                const pass = document.getElementById('register-password').value;
                if (!pass || pass.length < 6) return 'La contraseña debe tener al menos 6 caracteres.';
                break;
            case 3:
                const dir = document.getElementById('register-direccion').value.trim();
                if (!dir) return 'Ingresa tu dirección.';
                break;
            case 4:
                const tel = document.getElementById('register-telefono').value.trim();
                if (!tel || tel.length < 7) return 'Ingresa un número de teléfono válido.';
                break;
            case 6:
                const term = document.getElementById('register-terminos').checked;
                if (!term) return 'Debes aceptar los Términos y Condiciones.';
                break;
        }
        return '';
    }
    // Siguiente
    steps.forEach((id, idx) => {
        const nextBtn = document.getElementById('register-next-' + (idx + 1));
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                const err = validateStep(idx);
                if (err) {
                    errorDiv.textContent = err;
                    return;
                }
                currentStep = idx + 1;
                showStep(currentStep);
            });
        }
    });
    // Atrás
    steps.forEach((id, idx) => {
        const backBtn = document.getElementById('register-back-' + (idx + 1));
        if (backBtn) {
            backBtn.addEventListener('click', function () {
                currentStep = idx - 1;
                showStep(currentStep);
            });
        }
    });
    // Enviar
    const form = document.getElementById('modal-form-register-wizard');
    if (form) {
        form.addEventListener('submit', async function (e) {
            const err = validateStep(6);
            if (err) {
                errorDiv.textContent = err;
                e.preventDefault();
                return false;
            }
            e.preventDefault();
            // Enviar por AJAX para mantener UX
            const formData = new FormData(form);
            formData.set('nombre', document.getElementById('register-nombre').value.trim());
            formData.set('email', document.getElementById('register-email').value.trim());
            formData.set('password', document.getElementById('register-password').value);
            formData.set('direccion', document.getElementById('register-direccion').value.trim());
            formData.set('telefono', document.getElementById('register-telefono').value.trim());
            const fotoInput = document.getElementById('register-foto');
            if (fotoInput && fotoInput.files.length > 0) {
                formData.set('foto', fotoInput.files[0]);
            }
            try {
                const res = await fetch('api/registro.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                const data = await res.json();
                console.log('Respuesta registro:', data); // DEBUG
                if (data.ok) {
                    // Redirigir a index.php para reflejar la sesión
                    window.location.href = 'index.php';
                } else {
                    errorDiv.textContent = data.msg;
                }
            } catch (err) {
                errorDiv.textContent = 'Error de conexión. Intenta de nuevo.';
            }
        });
    }
    // Registro simple (nombre, email, password, password2)
    const formSimple = document.getElementById('modal-form-register-simple');
    if (formSimple) {
        formSimple.addEventListener('submit', async function (e) {
            e.preventDefault();
            const nombre = document.getElementById('register-simple-nombre').value.trim();
            const email = document.getElementById('register-simple-email').value.trim();
            const password = document.getElementById('register-simple-password').value;
            const password2 = document.getElementById('register-simple-password2').value;
            const direccion = document.getElementById('register-simple-direccion').value.trim();
            const telefono = document.getElementById('register-simple-telefono').value.trim();
            const fotoInput = document.getElementById('register-simple-foto');
            const errorDiv = document.getElementById('modal-register-error');
            // Validaciones
            if (!nombre || !email || !password || !password2 || !direccion || !telefono) {
                errorDiv.textContent = 'Todos los campos son obligatorios.';
                return;
            }
            if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
                errorDiv.textContent = 'Ingresa un correo válido.';
                return;
            }
            if (password.length < 6) {
                errorDiv.textContent = 'La contraseña debe tener al menos 6 caracteres.';
                return;
            }
            if (password !== password2) {
                errorDiv.textContent = 'Las contraseñas no coinciden.';
                return;
            }
            if (telefono.length < 7) {
                errorDiv.textContent = 'Ingresa un número de teléfono válido.';
                return;
            }
            // Enviar por AJAX
            const formData = new FormData();
            formData.set('nombre', nombre);
            formData.set('email', email);
            formData.set('password', password);
            formData.set('direccion', direccion);
            formData.set('telefono', telefono);
            if (fotoInput && fotoInput.files.length > 0) {
                formData.set('foto', fotoInput.files[0]);
            }
            try {
                const res = await fetch('api/registro.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                const data = await res.json();
                if (data.ok) {
                    window.location.href = 'index.php';
                } else {
                    errorDiv.textContent = data.msg;
                }
            } catch (err) {
                errorDiv.textContent = 'Error de conexión. Intenta de nuevo.';
            }
        });
    }
    // Mostrar primer paso SIEMPRE que se muestre el registro
    function resetRegisterWizard() {
        currentStep = 0;
        showStep(currentStep);
    }
    // Al abrir el modal desde cualquier lugar
    const btnLoginHeader = document.querySelector('a[href="login.php"]');
    if (btnLoginHeader) {
        btnLoginHeader.addEventListener('click', function (e) {
            setTimeout(resetRegisterWizard, 100); // Espera a que el modal esté visible
        });
    }
    // Al cambiar a la pestaña de registro
    const tabRegister = document.getElementById('modal-tab-register');
    if (tabRegister) {
        tabRegister.addEventListener('click', function () {
            resetRegisterWizard();
        });
    }
    // Inicializar por defecto si el modal ya está abierto
    if (document.getElementById('modal-glass-register-form') && !document.getElementById('modal-glass-register-form').classList.contains('hidden')) {
        resetRegisterWizard();
    }
}); 