<?php
/**
 * Admin Layout Shared
 * Variables esperadas antes de incluir este archivo:
 *   $admin_page      string  — nombre de la página activa para resaltar en el nav
 *   $admin_title     string  — título que se muestra en el header
 *   $admin_breadcrumb array  — [['label'=>'...', 'href'=>'...'], ...] (último sin href = activo)
 *   $admin_extra_css string  — (opcional) CSS adicional
 *   $admin_head_scripts string — (opcional) scripts en el <head>
 *   $page_title      string  — <title> de la página
 *   $usuario         array   — $_SESSION['usuario']
 */
if (!isset($admin_page))
    $admin_page = '';
if (!isset($admin_title))
    $admin_title = 'Panel de Administración';
if (!isset($admin_breadcrumb))
    $admin_breadcrumb = [];
if (!isset($page_title))
    $page_title = 'Admin | Computécnicos';
if (!isset($admin_extra_css))
    $admin_extra_css = '';
if (!isset($admin_head_scripts))
    $admin_head_scripts = '';
if (!isset($usuario))
    $usuario = $_SESSION['usuario'] ?? ['nombre' => 'Admin', 'rol' => 'admin'];

// Chequear rol real en base de datos para expulsar en tiempo real si fue degradado a cliente
if (isset($usuario['id'])) {
    $stmtRoleCheck = $pdo->prepare('SELECT rol FROM usuarios WHERE id = ?');
    $stmtRoleCheck->execute([$usuario['id']]);
    $realRole = $stmtRoleCheck->fetchColumn();
    if ($realRole !== 'admin') {
        $_SESSION['usuario']['rol'] = $realRole;
        header('Location: ../index.php');
        exit;
    }
}


$nav_items = [
    ['href' => 'dashboard.php', 'label' => 'Dashboard', 'key' => 'dashboard', 'icon' => 'layout-dashboard'],
    ['href' => 'productos.php', 'label' => 'Productos', 'key' => 'productos', 'icon' => 'box'],
    ['href' => 'categorias.php', 'label' => 'Categorías', 'key' => 'categorias', 'icon' => 'tags'],
    ['href' => 'marcas.php', 'label' => 'Marcas', 'key' => 'marcas', 'icon' => 'badge-check'],
    ['href' => 'usuarios.php', 'label' => 'Usuarios', 'key' => 'usuarios', 'icon' => 'users'],
    ['href' => 'pedidos.php', 'label' => 'Pedidos', 'key' => 'pedidos', 'icon' => 'clipboard-list'],
    ['href' => 'resenas.php', 'label' => 'Reseñas', 'key' => 'resenas', 'icon' => 'star'],
    ['href' => 'proveedores.php', 'label' => 'Proveedores', 'key' => 'proveedores', 'icon' => 'building-2'],
    ['href' => 'inventario.php', 'label' => 'Inventario', 'key' => 'inventario', 'icon' => 'warehouse'],
    ['href' => 'reporte_contable.php', 'label' => 'Reportes', 'key' => 'reportes', 'icon' => 'bar-chart-3'],
];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= asset('img/favicon.svg') ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/large-screens.css') ?>">
    <script src="https://unpkg.com/lucide@latest"></script>
    <?= $admin_extra_css ?>
    <?= $admin_head_scripts ?>

    <!-- Driver.js CSS & Professional Theme -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.css"/>
    <style>
      .driver-popover {
        background-color: #1e1e1e !important;
        color: #f1f1f1 !important;
        border: 1px solid rgba(255,255,255,0.08) !important;
        box-shadow: 0 16px 48px rgba(0,0,0,0.6) !important;
        border-radius: 12px !important;
        font-family: 'Inter', sans-serif !important;
        padding: 18px !important;
      }
      .driver-popover-title {
        font-size: 1.15rem !important;
        font-weight: 700 !important;
        margin-bottom: 0.6rem !important;
        color: #fff !important;
      }
      .driver-popover-description {
        font-size: 0.88rem !important;
        color: #aaa !important;
        line-height: 1.5 !important;
      }
      .driver-popover-footer { margin-top: 1.2rem !important; }
      .driver-popover-progress-text {
        font-size: 0.8rem !important;
        color: #777 !important;
        font-weight: 500 !important;
      }
      .driver-popover-navigation-btns button {
        background-color: #2a2a2a !important;
        color: #eee !important;
        border: 1px solid rgba(255,255,255,0.1) !important;
        border-radius: 6px !important;
        font-size: 0.85rem !important;
        font-weight: 600 !important;
        padding: 0.45rem 0.9rem !important;
        cursor: pointer !important;
        transition: all 0.2s !important;
        text-shadow: none !important;
      }
      .driver-popover-navigation-btns button:hover {
        background-color: #353535 !important;
        border-color: rgba(255,255,255,0.2) !important;
      }
      .driver-popover-navigation-btns button.driver-popover-next-btn {
        background-color: #e00000 !important;
        color: #fff !important;
        border-color: #e00000 !important;
      }
      .driver-popover-navigation-btns button.driver-popover-next-btn:hover {
        background-color: #ff1a1a !important;
        box-shadow: 0 0 12px rgba(224, 0, 0, 0.4) !important;
      }
      .driver-popover-close-btn {
        color: #777 !important;
        transition: color 0.2s !important;
        top: 12px !important;
        right: 12px !important;
      }
      .driver-popover-close-btn:hover { color: #e00000 !important; }
      .driver-popover-arrow-side-top { border-top-color: #1e1e1e !important; }
      .driver-popover-arrow-side-bottom { border-bottom-color: #1e1e1e !important; }
      .driver-popover-arrow-side-left { border-left-color: #1e1e1e !important; }
      .driver-popover-arrow-side-right { border-right-color: #1e1e1e !important; }
    </style>
</head>

<body>

    <!-- Particle Background -->
    <canvas class="admin-particles-canvas"></canvas>

    <!-- OVERLAY -->
    <div id="admin-overlay" class="admin-overlay"></div>

    <div class="admin-layout">
        <!-- ═══ SIDEBAR ═══ -->
        <aside id="admin-sidebar" class="admin-sidebar drawer">
            <div class="sidebar-inner">
                <a href="dashboard.php" class="admin-logo">
                    <span class="admin-logo-power-wrap">
                        <i data-lucide="power" class="admin-logo-icon" style="width:32px;height:32px"></i>
                    </span>
                </a>

                <nav class="admin-nav">
                    <span class="admin-nav-label">Menú</span>
                    <ul>
                        <?php foreach ($nav_items as $item): ?>
                            <li class="admin-nav-item">
                                <a href="<?= $item['href'] ?>"
                                    class="admin-nav-link <?= $admin_page === $item['key'] ? 'active' : '' ?>">
                                    <i data-lucide="<?= $item['icon'] ?>" style="width:20px;height:20px"></i>
                                    <span class="nav-link-text"><?= $item['label'] ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>

                <div class="admin-user">
                    <div class="admin-user-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
                    <div class="admin-user-role"><?= htmlspecialchars($usuario['rol']) ?></div>
                    <a href="../logout.php" class="admin-logout">
                        <i data-lucide="log-out" style="width:12px;height:12px"></i>
                        Cerrar sesión
                    </a>
                </div>
            </div>
        </aside>

        <!-- Toggle Arrow (outside sidebar to avoid overflow clipping) -->
        <button id="sidebar-collapse-toggle" class="sidebar-toggle-arrow" title="Colapsar menú">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
        </button>

        <!-- ═══ MAIN ═══ -->
        <div id="admin-main-content" class="admin-main">
            <!-- Header sticky -->
            <header class="admin-header">
                <div class="admin-header-left">
                    <button id="btn-sidebar-toggle" class="admin-hamburger">
                        <i data-lucide="menu" style="width:24px;height:24px"></i>
                    </button>
                    <div>
                        <div class="admin-page-title"><?= htmlspecialchars($admin_title) ?></div>
                    </div>
                </div>
                <div class="admin-header-actions">
                    <a href="../index.php" class="adm-btn adm-btn-primary">
                        <i data-lucide="store" style="width:18px;height:18px"></i>
                        Ir a la Tienda
                    </a>
                    <?php if (isset($admin_header_extra))
                        echo $admin_header_extra; ?>
                </div>
            </header>

            <!-- Content (cada página lo llena) -->