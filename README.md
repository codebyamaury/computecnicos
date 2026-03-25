<div align="center">
  <img src="https://raw.githubusercontent.com/codebyamaury/computecnicos/main/assets/images/logo.png" alt="CompuTécnicos Logo" width="150" />
  <h1>CompuTécnicos - Plataforma E-Commerce Avanzada</h1>
  <p>🚀 Un sistema de tienda en línea integral, escalable y ultra rápido construido en PHP Nativo + MySQL.</p>
</div>

---

## 📖 Sobre el Proyecto

**CompuTécnicos** es una plataforma completa de comercio electrónico optimizada para la venta de componentes y equipos tecnológicos. Diseñada desde cero fusionando alto rendimiento (PHP Nativo) con un Frontend moderno y reactivo (Animaciones en Canvas 3D, Glassmorphism y utilidades CSS). 

El sistema va más allá de un simple carrito de compras, integrando un panel administrativo financiero profesional, gestión de inventarios, pagos instantáneos y un sistema completo de devoluciones.

---

## ✨ Características Principales

### 🛒 Experiencia de Compra (Frontend)
- **Catálogo Dinámico:** Filtrado avanzado por categorías y marcas con insignias de stock en tiempo real.
- **Carrito Inteligente:**
  - Validación de reglas de inventario (impide agregar productos sin stock).
  - Motor automático de descuentos en "combos" empresariales (Ej: Descuento cruzado al comprar CPU + RAM).
  - Barra de progreso interactiva para calcular cuánto le falta al cliente para "Envío Gratis".
- **Checkout Seguro:** Integración con la pasarela de pagos oficial de **PayPal** (Botones Inteligentes, captura asíncrona).
- **UX Inmersiva:** Fondos de partículas en Canvas (`paypal_response.php`), transiciones suaves y notificaciones Toast personalizadas (`z-index` ultra-dinámico).
- **Autenticación Completa:** Inicio de sesión clásico y registro optimizado, verificación de sintaxis de correo / validación de dominios MX (vía API), e inicio con Google OAuth.

### ⚙️ Panel de Control Administrativo (Backend)
- **Gestión Financiera (Net Sales):** Reporte contable profesional que distingue automáticamente las Ventas Brutas, las Devoluciones y rastrea las repocisiones manuales del inventario para arrojar flujos de caja limpios.
- **Gestor de Inventarios (Movements Logging):** Seguimiento del historial de todo producto (Entradas/Salidas/Ajustes) garantizando un control granular antipérdidas donde cada unidad registra su costo histórico.
- **Panel de Reembolsos Reales:** Interfaz donde el administrador aprueba/rechaza devoluciones de clientes, modificando órdenes pasadas y liberando el stock devuelto para ser vendido otra vez.
- **Exportación de Datos:** Generador dinámico en CSV (codificado en UTF-8 BOM para perfecta compatibilidad con Excel) para auditorías visuales externas de las ventas en el mes.

### 📧 Sistema de Correos (Transaccionales)
- **Plantillas HTML Profesionales:** Diseños exclusivos (`email_helper.php`) para dar la bienvenida a nuevos clientes y emitir comprobantes de pago instantáneos, detallando su #ID, desglose de orden y total pagado.
- Motores de entrega múltiples: Prioridad sobre API pura (Brevo/Sendinblue) para máxima entregabilidad, con *fallback* al protocolo local `mail()` si fuese necesario.

### 🌐 SEO e Indexación Moderna
- **JSON-LD Integrado:** Esquemas estructurados adaptados directamente al DOM para ayudar a Google a leer rutas comerciales y productos rápidamente.
- Optimización técnica de Meta Etiquetas, Favicons en alta definición estructural y jerarquía canónica de URL.

---

## 🛠 Entorno de Desarrollo (Stack)

* **Backend:** PHP 8.x (Vanilla / Custom MVC Architecture)
* **Base de Datos:** MySQL / MariaDB (Relacional robusta con transacciones ACID)
* **Frontend:** HTML5, JS (ES6), CSS3, Flowbite/TailwindCSS (selectivo)
* **Integraciones:** API de PayPal, API de envío Brevo, APIs de validación de dominios.

---

## 🚀 Despliegue e Instalación Recomendada

1. Clona el repositorio en tu servidor (`www` / `htdocs`):
```bash
git clone https://github.com/codebyamaury/computecnicos.git
```
2. Ejecuta los scripts SQL provistos para montar el esquema en tu servidor de MySQL.
3. Copia el archivo `.env.example` a un nuevo `.env` y rellena las variables de entorno principales:
   - Credenciales de Base de Datos.
   - `BREVO_API_KEY` (Notificaciones).
   - Secretos y Client ID de `PayPal` / `Google`.
4. Todos los estilos e imágenes se renderizarán solos. ¡Listo para vender!

---

*Coded with passionate UI UX engineering.* 💻
