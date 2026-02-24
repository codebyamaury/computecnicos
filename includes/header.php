<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Detectar la página actual
$current = basename($_SERVER['PHP_SELF']);
// Sincronizar rol desde la base de datos si existe sesión y conexión
if (isset($_SESSION['usuario']['id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare('SELECT rol FROM usuarios WHERE id = ?');
        $stmt->execute([$_SESSION['usuario']['id']]);
        $row = $stmt->fetch();
        if ($row && isset($row['rol'])) {
            $_SESSION['usuario']['rol'] = $row['rol'];
        }
    } catch (Exception $e) {
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' | ' : ''; ?>Computecnicos</title>
    <link rel="icon" type="image/svg+xml" href="<?= asset('img/favicon.svg') ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Orbitron:wght@400;500;700;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/main.css') ?>">
    <?php if (isset($extra_css))
        echo $extra_css; ?>
    <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <script src="<?= asset('js/cart.js') ?>"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="min-h-screen flex flex-col">
    <header class="py-4 px-4 flex justify-between items-center relative z-50">
        <!-- Logo y Menú Hamburger a la izquierda -->
        <div class="flex items-center gap-2">
            <button id="mobile-menu-btn" class="md:hidden p-2 text-white hover:text-red-500 transition-colors" aria-label="Abrir menú">
                <i data-lucide="menu" class="w-7 h-7"></i>
            </button>
            <a href="index.php"
                class="flex items-center justify-center p-2 rounded-full hover:bg-white/5 transition-all group"
                aria-label="Inicio">
                <span class="text-3xl text-red-600 group-hover:scale-110 transition-transform">
                    <i data-lucide="power" class="w-8 h-8 md:w-10 md:h-10"></i>
                </span>
            </a>
        </div>

        <!-- Menú centrado (Desktop) -->
        <nav class="hidden md:flex justify-center absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
            <ul class="flex gap-4 lg:gap-6 text-base lg:text-lg font-semibold">
                <li>
                    <a href="index.php"
                        class="main-menu-link flex items-center gap-1 lg:gap-2 <?php if ($current == 'index.php') echo 'active-menu'; ?>">
                        <i data-lucide="house" class="w-5 h-5"></i>
                        Inicio
                    </a>
                </li>
                <li>
                    <a href="productos.php"
                        class="main-menu-link flex items-center gap-1 lg:gap-2 <?php if ($current == 'productos.php') echo 'active-menu'; ?>">
                        <i data-lucide="box" class="w-5 h-5"></i>
                        Productos
                    </a>
                </li>
                <li>
                    <a href="servicios.php"
                        class="main-menu-link flex items-center gap-1 lg:gap-2 <?php if ($current == 'servicios.php') echo 'active-menu'; ?>">
                        <i data-lucide="wrench" class="w-5 h-5"></i>
                        Servicios
                    </a>
                </li>
                <li>
                    <a href="contacto.php"
                        class="main-menu-link flex items-center gap-1 lg:gap-2 <?php if ($current == 'contacto.php') echo 'active-menu'; ?>">
                        <i data-lucide="mail" class="w-5 h-5"></i>
                        Contacto
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Controles a la derecha -->
        <div class="flex items-center justify-end gap-2 md:gap-4">
            <a href="carrito.php"
                class="relative flex items-center gap-2 hover:text-red-500 transition px-2 md:px-3 py-2 rounded-lg hover:bg-white/5"
                aria-label="Carrito">
                <i data-lucide="shopping-cart" class="w-6 h-6 text-white"></i>
                <span class="font-semibold text-white hidden sm:inline">Carrito</span>
                <span
                    class="cart-counter absolute -top-1 -right-1 text-xs font-bold text-white bg-red-600 rounded-full px-1.5 py-0.5 leading-none border-2 border-[#111] shadow-md flex items-center justify-center min-w-[20px] h-[20px]">
                    <?php echo isset($_SESSION['carrito']) ? array_sum(array_column($_SESSION['carrito'], 'cantidad')) : 0; ?>
                </span>
            </a>
            <?php if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario'])): ?>
                <div class="relative" id="user-menu-container">
                    <button id="user-menu-button"
                        class="hover:text-red-500 transition flex items-center gap-1 md:gap-2 p-1 md:p-2 rounded-full hover:bg-[#333]">
                        <?php if (!empty($_SESSION['usuario']['foto'])): ?>
                            <img src="<?php echo htmlspecialchars($_SESSION['usuario']['foto']); ?>" alt="Foto de perfil"
                                class="w-7 h-7 md:w-8 md:h-8 rounded-full object-cover border-2 border-[#333]">
                        <?php else: ?>
                            <div
                                class="w-7 h-7 md:w-8 md:h-8 rounded-full bg-red-600 flex items-center justify-center text-white font-bold text-xs md:text-sm">
                                <?php echo strtoupper(substr($_SESSION['usuario']['nombre'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <i data-lucide="chevron-down" class="w-4 h-4 md:w-5 md:h-5 text-white user-chevron" aria-hidden="true"></i>
                    </button>
                    <div id="user-menu-dropdown"
                        class="absolute right-0 mt-2 w-48 bg-[#232323] border border-[#333] rounded-lg shadow-lg py-2 z-50 hidden">
                        <div class="px-4 py-2 border-b border-[#333]">
                            <div class="font-semibold text-white user-name truncate">
                                <?php echo htmlspecialchars($_SESSION['usuario']['nombre']); ?></div>
                            <div class="text-xs md:text-sm text-gray-400 user-email truncate">
                                <?php echo htmlspecialchars($_SESSION['usuario']['email']); ?></div>
                        </div>
                        <a href="perfil.php" class="block px-4 py-2 hover:bg-[#181818] transition text-white">
                            <i data-lucide="user" class="w-4 h-4 inline mr-2"></i>
                            Mi perfil
                        </a>
                        <a href="pedidos.php" class="block px-4 py-2 hover:bg-[#181818] transition text-white">
                            <i data-lucide="file-text" class="w-4 h-4 inline mr-2"></i>
                            Mis pedidos
                        </a>
                        <?php if ($_SESSION['usuario']['rol'] === 'admin'): ?>
                            <a href="admin/dashboard.php" class="block px-4 py-2 text-red-400 hover:bg-[#181818] transition">
                                <i data-lucide="layout-dashboard" class="w-4 h-4 inline mr-2"></i>
                                Panel Admin
                            </a>
                        <?php endif; ?>
                        <div class="border-t border-[#333] mt-2 pt-2">
                            <a href="logout.php" class="block px-4 py-2 hover:bg-[#181818] transition text-white">
                                <i data-lucide="log-out" class="w-4 h-4 inline mr-2"></i>
                                Cerrar sesión
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <a href="#" onclick="abrirModalLogin(); return false;" id="btn-login-header"
                    class="hover:text-red-500 transition flex items-center gap-1 md:gap-2 px-2 md:px-3 py-2 rounded-lg hover:bg-white/5">
                    <span class="font-semibold text-white hidden sm:inline">Iniciar Sesión</span>
                    <i data-lucide="user-circle" class="w-6 h-6"></i>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Mobile Menu (Futuristic Style) -->
    <div id="mobile-menu" class="hidden md:hidden mobile-menu-panel">
        <!-- Scan line animation -->
        <div class="mobile-menu-scanline"></div>

        <!-- Close button -->
        <div class="mobile-menu-header">
            <span class="mobile-menu-label">// NAVEGACIÓN</span>
            <button id="mobile-menu-close" class="mobile-menu-close" aria-label="Cerrar menú">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <nav class="mobile-menu-nav">
            <a href="index.php" class="mobile-menu-item <?php if ($current == 'index.php') echo 'active'; ?>">
                <span class="mobile-menu-icon"><i data-lucide="house" class="w-5 h-5"></i></span>
                <span class="mobile-menu-text">Inicio</span>
                <span class="mobile-menu-arrow"><i data-lucide="chevron-right" class="w-4 h-4"></i></span>
            </a>
            <a href="productos.php" class="mobile-menu-item <?php if ($current == 'productos.php') echo 'active'; ?>">
                <span class="mobile-menu-icon"><i data-lucide="box" class="w-5 h-5"></i></span>
                <span class="mobile-menu-text">Productos</span>
                <span class="mobile-menu-arrow"><i data-lucide="chevron-right" class="w-4 h-4"></i></span>
            </a>
            <a href="servicios.php" class="mobile-menu-item <?php if ($current == 'servicios.php') echo 'active'; ?>">
                <span class="mobile-menu-icon"><i data-lucide="wrench" class="w-5 h-5"></i></span>
                <span class="mobile-menu-text">Servicios</span>
                <span class="mobile-menu-arrow"><i data-lucide="chevron-right" class="w-4 h-4"></i></span>
            </a>
            <a href="contacto.php" class="mobile-menu-item <?php if ($current == 'contacto.php') echo 'active'; ?>">
                <span class="mobile-menu-icon"><i data-lucide="mail" class="w-5 h-5"></i></span>
                <span class="mobile-menu-text">Contacto</span>
                <span class="mobile-menu-arrow"><i data-lucide="chevron-right" class="w-4 h-4"></i></span>
            </a>
        </nav>

        <!-- Footer del menú -->
        <div class="mobile-menu-footer">
            <div class="mobile-menu-divider"></div>
            <p class="mobile-menu-status">
                <span class="mobile-menu-dot"></span>
                SYSTEM STATUS: <span style="color: #ff0000;">ONLINE</span>
            </p>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // User menu logic
        const userMenuButton = document.getElementById('user-menu-button');
        const userMenuDropdown = document.getElementById('user-menu-dropdown');
        if (userMenuButton && userMenuDropdown) {
            userMenuButton.addEventListener('click', function (e) {
                e.stopPropagation();
                userMenuDropdown.classList.toggle('hidden');
                userMenuButton.classList.toggle('open', !userMenuDropdown.classList.contains('hidden'));
            });
            document.addEventListener('click', function (event) {
                if (!userMenuButton.contains(event.target) && !userMenuDropdown.contains(event.target)) {
                    userMenuDropdown.classList.add('hidden');
                    userMenuButton.classList.remove('open');
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    userMenuDropdown.classList.add('hidden');
                    userMenuButton.classList.remove('open');
                }
            });
        }

        // Mobile menu logic
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenuClose = document.getElementById('mobile-menu-close');
        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
                if (!mobileMenu.classList.contains('hidden')) {
                    lucide.createIcons({ nodes: mobileMenu.querySelectorAll('[data-lucide]') });
                }
            });
            if (mobileMenuClose) {
                mobileMenuClose.addEventListener('click', function() {
                    mobileMenu.classList.add('hidden');
                });
            }
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !mobileMenu.classList.contains('hidden')) {
                    mobileMenu.classList.add('hidden');
                }
            });
        }
    </script>