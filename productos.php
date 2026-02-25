<?php
require_once __DIR__ . '/app/Core/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
// Sesión manejada por bootstrap (DB handler)
}

// Configuración del header
$page_title = 'Productos';
$extra_css = '<link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.1/flowbite.min.css" rel="stylesheet" />' . "\n" .
             '<link rel="stylesheet" href="' . asset('css/index.css') . '">' . "\n" .
             '<link rel="stylesheet" href="' . asset('css/productos.css') . '?v=' . time() . '_1">';

// Obtener categorías y marcas para filtros
$categorias = $pdo->query('SELECT id, nombre FROM categorias ORDER BY nombre ASC')->fetchAll();
$marcas = $pdo->query('SELECT id, nombre FROM marcas ORDER BY nombre ASC')->fetchAll();

// Filtros
$filtro_categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
$filtro_marca = isset($_GET['marca']) ? intval($_GET['marca']) : 0;
$filtro_precio_min = isset($_GET['precio_min']) ? intval($_GET['precio_min']) : 0;
$filtro_precio_max = isset($_GET['precio_max']) ? intval($_GET['precio_max']) : 0;
$filtro_orden = isset($_GET['orden']) ? trim($_GET['orden']) : 'recientes';
$filtro_busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$filtro_oferta = isset($_GET['oferta']) ? 1 : 0;

// Construir consulta
$sql = 'SELECT p.*, c.nombre AS categoria, m.nombre AS marca 
        FROM productos p 
        LEFT JOIN categorias c ON p.id_categoria = c.id 
        LEFT JOIN marcas m ON p.id_marca = m.id 
        WHERE 1=1';
$params = [];

// Aplicar filtros
if (!empty($filtro_busqueda)) {
    $sql .= ' AND (p.nombre LIKE ? OR p.descripcion LIKE ?)';
    $params[] = '%' . $filtro_busqueda . '%';
    $params[] = '%' . $filtro_busqueda . '%';
}

if (!empty($filtro_categoria)) {
    $sql .= ' AND LOWER(c.nombre) = LOWER(?)';
    $params[] = $filtro_categoria;
}

if ($filtro_marca > 0) {
    $sql .= ' AND p.id_marca = ?';
    $params[] = $filtro_marca;
}

if ($filtro_precio_min > 0) {
    $sql .= ' AND p.precio >= ?';
    $params[] = $filtro_precio_min;
}

if ($filtro_precio_max > 0) {
    $sql .= ' AND p.precio <= ?';
    $params[] = $filtro_precio_max;
}

if ($filtro_oferta) {
    $sql .= ' AND p.oferta = 1';
}

// Ordenar
switch ($filtro_orden) {
    case 'precio_asc':
        $sql .= ' ORDER BY p.precio ASC';
        break;
    case 'precio_desc':
        $sql .= ' ORDER BY p.precio DESC';
        break;
    case 'nombre_asc':
        $sql .= ' ORDER BY p.nombre ASC';
        break;
    case 'nombre_desc':
        $sql .= ' ORDER BY p.nombre DESC';
        break;
    case 'recientes':
    default:
        $sql .= ' ORDER BY p.fecha_creacion DESC';
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Contar productos con oferta
$stmt_ofertas = $pdo->query('SELECT COUNT(*) FROM productos WHERE oferta = 1');
$total_ofertas = $stmt_ofertas->fetchColumn();

include __DIR__ . '/includes/header.php';
?>

<main class="flex-1 bg-[#050505] text-white relative z-10">
    
    <!-- Hero Section -->
    <section class="products-hero">
        <div class="container mx-auto px-4 relative z-10">
            <h1 class="section-title animate-slide-up">Nuestros Productos</h1>
            <p class="text-gray-400 max-w-2xl mx-auto mt-4 animate-slide-up delay-100 text-center">
                Explora nuestra selección de productos tecnológicos de alta calidad. Encuentra lo que necesitas para potenciar tu experiencia digital.
            </p>
            
            <!-- Barra de búsqueda rápida -->
            <div class="search-hero-container animate-slide-up delay-200">
                <form method="GET" action="productos.php" class="search-hero-form">
                    <div class="search-input-wrapper">
                        <i data-lucide="search" class="search-icon" style="width:20px;height:20px"></i>
                        <input type="text" name="busqueda" placeholder="Buscar productos..." 
                               value="<?php echo htmlspecialchars($filtro_busqueda); ?>" 
                               class="search-hero-input"
                               autocomplete="off">
                        <button type="submit" class="search-hero-btn">
                            <span>Buscar</span>
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <!-- Productos Section -->
    <section class="container mx-auto px-4 pb-20">
        <div class="products-layout">
            
            <!-- Sidebar de Filtros -->
            <aside class="filters-sidebar animate-slide-up delay-200" id="filters-sidebar">
                <div class="filters-header">
                    <h3>
                        <i data-lucide="sliders-horizontal" class="w-5 h-5"></i>
                        Filtros
                    </h3>
                    <div style="display:flex;align-items:center;gap:0.5rem;">
                        <a href="productos.php" class="clear-filters-btn">Limpiar</a>
                        <button type="button" id="mobile-filter-close" class="mobile-filter-close-btn" aria-label="Cerrar filtros">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
                
                <form method="GET" action="productos.php" id="filters-form">
                    <!-- Mantener búsqueda si existe -->
                    <?php if (!empty($filtro_busqueda)): ?>
                        <input type="hidden" name="busqueda" value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                    <?php endif; ?>
                    
                    <!-- Categoría -->
                    <div class="filter-group">
                        <label class="filter-label">Categoría</label>
                        <select name="categoria" class="filter-select">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo htmlspecialchars(strtolower($cat['nombre'])); ?>" 
                                        <?php echo (strtolower($filtro_categoria) === strtolower($cat['nombre'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Marca -->
                    <div class="filter-group">
                        <label class="filter-label">Marca</label>
                        <select name="marca" class="filter-select">
                            <option value="">Todas las marcas</option>
                            <?php foreach ($marcas as $mar): ?>
                                <option value="<?php echo intval($mar['id']); ?>" 
                                        <?php echo ($filtro_marca === intval($mar['id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mar['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Rango de Precio -->
                    <div class="filter-group">
                        <label class="filter-label">Rango de Precio</label>
                        <div class="price-range">
                            <input type="number" name="precio_min" placeholder="Min" 
                                   value="<?php echo $filtro_precio_min > 0 ? $filtro_precio_min : ''; ?>" 
                                   class="filter-input-price">
                            <span class="price-separator">-</span>
                            <input type="number" name="precio_max" placeholder="Max" 
                                   value="<?php echo $filtro_precio_max > 0 ? $filtro_precio_max : ''; ?>" 
                                   class="filter-input-price">
                        </div>
                    </div>
                    
                    <!-- Ordenar por -->
                    <div class="filter-group">
                        <label class="filter-label">Ordenar por</label>
                        <select name="orden" class="filter-select">
                            <option value="recientes" <?php echo $filtro_orden === 'recientes' ? 'selected' : ''; ?>>Más recientes</option>
                            <option value="precio_asc" <?php echo $filtro_orden === 'precio_asc' ? 'selected' : ''; ?>>Precio: Menor a Mayor</option>
                            <option value="precio_desc" <?php echo $filtro_orden === 'precio_desc' ? 'selected' : ''; ?>>Precio: Mayor a Menor</option>
                            <option value="nombre_asc" <?php echo $filtro_orden === 'nombre_asc' ? 'selected' : ''; ?>>Nombre: A-Z</option>
                            <option value="nombre_desc" <?php echo $filtro_orden === 'nombre_desc' ? 'selected' : ''; ?>>Nombre: Z-A</option>
                        </select>
                    </div>
                    
                    <!-- Solo ofertas -->
                    <div class="filter-group">
                        <label class="filter-checkbox-label">
                            <input type="checkbox" name="oferta" value="1" 
                                   <?php echo $filtro_oferta ? 'checked' : ''; ?> 
                                   class="filter-checkbox">
                            <span class="checkmark"></span>
                            <span>Solo ofertas</span>
                            <?php if ($total_ofertas > 0): ?>
                                <span class="offers-count"><?php echo $total_ofertas; ?></span>
                            <?php endif; ?>
                        </label>
                    </div>
                    
                    <button type="submit" class="apply-filters-btn">
                        <span>Aplicar Filtros</span>
                        <i data-lucide="check" class="w-4 h-4"></i>
                    </button>
                </form>
            </aside>
            
            <!-- Grid de Productos -->
            <div class="products-content">
                <!-- Contador y vista -->
                <div class="products-header animate-slide-up delay-100">
                    <div class="products-count">
                        <span class="count-number"><?php echo count($productos); ?></span>
                        <span class="count-text">productos encontrados</span>
                    </div>
                    
                    <!-- Filtro móvil toggle -->
                    <button type="button" id="mobile-filter-toggle" class="mobile-filter-btn">
                        <i data-lucide="sliders-horizontal" class="w-5 h-5"></i>
                        <span>Filtros</span>
                    </button>
                </div>
                
                <?php if (empty($productos)): ?>
                    <div class="no-products animate-slide-up delay-200">
                        <div class="no-products-icon">
                            <i data-lucide="frown" class="w-16 h-16"></i>
                        </div>
                        <h3>No se encontraron productos</h3>
                        <p>Intenta ajustar los filtros o busca algo diferente.</p>
                        <a href="productos.php" class="reset-search-btn">Ver todos los productos</a>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php 
                        $delay = 0;
                        foreach ($productos as $p): 
                            $esNuevo = (strtotime($p['fecha_creacion']) > strtotime('-15 days'));
                            $delay_class = 'delay-' . min($delay * 100, 500);
                        ?>
                            <article class="product-card animate-slide-up <?php echo $delay_class; ?>" 
                                     onclick="window.location.href='producto.php?id=<?php echo intval($p['id']); ?>'">
                                <div class="product-image-container">
                                    <img src="<?php echo htmlspecialchars($p['imagen'] ?: 'https://via.placeholder.com/400x300?text=Sin+Imagen'); ?>" 
                                         alt="<?php echo htmlspecialchars($p['nombre']); ?>"
                                         loading="lazy">
                                    
                                    <div class="product-badges">
                                        <?php if ($esNuevo): ?>
                                            <span class="badge badge-new">Nuevo</span>
                                        <?php endif; ?>
                                        <?php if (!empty($p['oferta'])): ?>
                                            <span class="badge badge-offer">Oferta</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-overlay">
                                        <span class="view-product-btn">Ver detalles</span>
                                    </div>
                                </div>
                                
                                <div class="product-info">
                                    <div class="product-category"><?php echo htmlspecialchars($p['categoria']); ?></div>
                                    <h3 class="product-name"><?php echo htmlspecialchars($p['nombre']); ?></h3>
                                    
                                    <div class="product-meta">
                                        <span class="product-brand"><?php echo htmlspecialchars($p['marca']); ?></span>
                                        <?php if ((int)$p['stock'] > 0): ?>
                                            <span class="product-stock in-stock">En stock</span>
                                        <?php else: ?>
                                            <span class="product-stock out-stock">Agotado</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="product-footer">
                                        <div class="product-price">$<?php echo number_format($p['precio'], 0, ',', '.'); ?></div>
                                    </div>
                                </div>
                            </article>
                        <?php 
                            $delay++;
                        endforeach; 
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('.search-hero-input');
    const urlParams = new URLSearchParams(window.location.search);
    if (searchInput && !urlParams.has('busqueda')) {
        searchInput.value = '';
    }

    // ── Búsqueda en tiempo real ──────────────────────────────────────────
    const productCards = document.querySelectorAll('.product-card');
    const countNumber  = document.querySelector('.count-number');
    const productsGrid = document.querySelector('.products-grid');
    let debounceTimer;

    function filterProducts(query) {
        const q = query.trim().toLowerCase();
        let visible = 0;
        productCards.forEach(card => {
            const name     = (card.querySelector('.product-name')?.textContent     || '').toLowerCase();
            const category = (card.querySelector('.product-category')?.textContent || '').toLowerCase();
            const brand    = (card.querySelector('.product-brand')?.textContent    || '').toLowerCase();
            const match = !q || name.includes(q) || category.includes(q) || brand.includes(q);
            card.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        if (countNumber) countNumber.textContent = visible;
        if (productsGrid) {
            if (visible === 0 && productCards.length > 0) {
                let noResultsMsg = document.getElementById('realtime-no-results');
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'realtime-no-results';
                    noResultsMsg.className = 'no-products';
                    noResultsMsg.innerHTML = '<div class="no-products-icon"><svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><h3>No se encontraron productos</h3><p>Intenta con otro término de búsqueda.</p>';
                    productsGrid.parentNode.insertBefore(noResultsMsg, productsGrid.nextSibling);
                }
                noResultsMsg.style.display = '';
                productsGrid.style.display = 'none';
            } else {
                const noResultsMsg = document.getElementById('realtime-no-results');
                if (noResultsMsg) noResultsMsg.style.display = 'none';
                productsGrid.style.display = '';
            }
        }
    }

    if (searchInput && productCards.length > 0) {
        if (searchInput.value) filterProducts(searchInput.value);
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => filterProducts(this.value), 250);
        });
        const searchForm = searchInput.closest('form');
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                const hasSidebarFilters = urlParams.has('categoria') || urlParams.has('marca') ||
                                          urlParams.has('precio_min') || urlParams.has('precio_max') ||
                                          urlParams.has('oferta');
                if (!hasSidebarFilters) {
                    e.preventDefault();
                    filterProducts(searchInput.value);
                }
            });
        }
    }

    // ── FILTROS MOBILE — SOLUCIÓN ROBUSTA ─────────────────────────────────
    // Mover sidebar a document.body cuando se abre para eliminar
    // TODOS los problemas de stacking context de padres con transform/z-index
    var filterToggle  = document.getElementById('mobile-filter-toggle');
    var sidebar       = document.getElementById('filters-sidebar');
    var filterClose   = document.getElementById('mobile-filter-close');
    var isFilterOpen  = false;
    var sidebarOriginalParent = null;
    var sidebarOriginalNext = null;

    function abrirFiltros() {
        if (!sidebar) return;
        isFilterOpen = true;

        // 1. Guardar posición original del sidebar en el DOM
        sidebarOriginalParent = sidebar.parentNode;
        sidebarOriginalNext = sidebar.nextSibling;

        // 2. Crear backdrop como hijo directo de body
        var bd = document.getElementById('filter-backdrop');
        if (!bd) {
            bd = document.createElement('div');
            bd.id = 'filter-backdrop';
            bd.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;width:100vw;height:100vh;background:rgba(0,0,0,0.75);z-index:9990;cursor:pointer;';
            document.body.appendChild(bd);
            bd.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                cerrarFiltros();
            });
        } else {
            bd.style.display = 'block';
        }

        // 3. Mover sidebar a document.body (escapa de todo stacking context)
        document.body.appendChild(sidebar);

        // 4. Forzar estilos inline (ignorar cualquier CSS de padre)
        sidebar.style.cssText = 'position:fixed !important;top:0 !important;left:0 !important;width:300px;max-width:85vw;height:100vh !important;z-index:9995 !important;overflow-y:auto;background:rgba(14,14,14,0.98);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-right:1px solid rgba(255,0,0,0.15);transition:none;';

        document.body.style.overflow = 'hidden';

        // 5. Re-renderizar iconos de Lucide dentro del sidebar
        if (typeof lucide !== 'undefined') {
            setTimeout(function() { lucide.createIcons(); }, 50);
        }
    }

    function cerrarFiltros() {
        if (!sidebar) return;
        isFilterOpen = false;

        // 1. Devolver sidebar a su posición original en el DOM
        if (sidebarOriginalParent) {
            if (sidebarOriginalNext) {
                sidebarOriginalParent.insertBefore(sidebar, sidebarOriginalNext);
            } else {
                sidebarOriginalParent.appendChild(sidebar);
            }
        }

        // 2. Resetear estilos inline (volver al CSS normal del archivo)
        sidebar.style.cssText = '';

        // 3. Ocultar backdrop
        var bd = document.getElementById('filter-backdrop');
        if (bd) bd.style.display = 'none';

        document.body.style.overflow = '';
    }

    // Botón "Filtros"
    if (filterToggle) {
        filterToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (isFilterOpen) {
                cerrarFiltros();
            } else {
                abrirFiltros();
            }
        });
    }

    // Botón X cerrar
    if (filterClose) {
        filterClose.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            cerrarFiltros();
        });
    }

    // BLOQUEAR todos los eventos dentro del sidebar para que no cierren nada
    if (sidebar) {
        ['click','touchstart','touchend','mousedown','mouseup','pointerdown','pointerup'].forEach(function(evtName) {
            sidebar.addEventListener(evtName, function(e) {
                e.stopPropagation();
            }, true); // useCapture = true para interceptar ANTES que cualquier hijo
        });
    }

    // Escape cierra
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isFilterOpen) cerrarFiltros();
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
