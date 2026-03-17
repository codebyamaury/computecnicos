// Función para mostrar notificaciones
function showNotification(message, type = 'success') {
    // Remover notificaciones existentes
    const existingNotifications = document.querySelectorAll('.cart-notification');
    existingNotifications.forEach(notification => {
        document.body.removeChild(notification);
    });

    const notification = document.createElement('div');
    notification.className = `cart-notification fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transition-all transform translate-x-full ${
        type === 'success' ? 'bg-green-600' : 'bg-red-600'
    } text-white flex items-center gap-2`;
    
    // Agregar ícono según el tipo
    const icon = document.createElement('span');
    if (type === 'success') {
        icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
        </svg>`;
    } else {
        icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
        </svg>`;
    }
    notification.appendChild(icon);
    
    const messageSpan = document.createElement('span');
    messageSpan.textContent = message;
    notification.appendChild(messageSpan);
    
    document.body.appendChild(notification);
    
    // Animar entrada
    requestAnimationFrame(() => {
        notification.style.transform = 'translateX(0)';
    });
    
    // Remover después de 3 segundos
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// Función para actualizar el contador del carrito
function updateCartCounter(total) {
    const counters = document.querySelectorAll('.cart-counter');
    counters.forEach(counter => {
        counter.textContent = total;
    });
}

// Función para agregar al carrito
async function agregarAlCarrito(idProducto, cantidad = 1) {
    try {
        const formData = new FormData();
        formData.append('id_producto', idProducto);
        formData.append('cantidad', cantidad);
        
        const res = await fetch('api/agregar_carrito.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        });
        const data = await res.json();
        
        if (data.ok) {
            showNotification(data.msg);
            if (data.total !== null) {
                updateCartCounter(data.total);
            }
        } else {
            showNotification(data.msg, 'error');
        }
    } catch (err) {
        showNotification('Error al agregar al carrito. Intenta de nuevo.', 'error');
        console.error('Error:', err);
    }
}

// Agregar estilos para las notificaciones
const style = document.createElement('style');
style.textContent = `
    .cart-notification {
        transition: transform 0.3s ease-in-out;
    }
`;
document.head.appendChild(style);