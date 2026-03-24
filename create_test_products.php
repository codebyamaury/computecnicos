<?php
require_once 'app/Core/bootstrap.php';

try {
    $pdo->beginTransaction();

    // 1. Asegurar Categorías (Sin columna imagen, según schema)
    $cats = ['Portátiles', 'Componentes', 'Accesorios'];
    $catIds = [];

    foreach ($cats as $name) {
        $stmt = $pdo->prepare("SELECT id FROM categorias WHERE nombre = ?");
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            $stmt = $pdo->prepare("INSERT INTO categorias (nombre) VALUES (?)");
            $stmt->execute([$name]);
            $id = $pdo->lastInsertId();
            echo "Categoría creada: $name (ID: $id)\n";
        } else {
            echo "Categoría existente: $name (ID: $id)\n";
        }
        $catIds[$name] = $id;
    }

    // 2. Asegurar Marca Test
    $brandName = 'CyberTech';
    $stmt = $pdo->prepare("SELECT id FROM marcas WHERE nombre = ?");
    $stmt->execute([$brandName]);
    $brandId = $stmt->fetchColumn();

    if (!$brandId) {
        $stmt = $pdo->prepare("INSERT INTO marcas (nombre) VALUES (?)");
        $stmt->execute([$brandName]);
        $brandId = $pdo->lastInsertId();
        echo "Marca creada: $brandName (ID: $brandId)\n";
    } else {
        echo "Marca existente: $brandName (ID: $brandId)\n";
    }

    // 3. Insertar Productos
    $products = [
        [
            'nombre' => 'Laptop Gamer CyberOne X',
            'descripcion' => 'Potencia extrema para gaming y diseño. Procesador i9 de última generación, 32GB RAM y RTX 4080.',
            'precio' => 2499.99,
            'stock' => 10,
            'imagen' => 'assets/img/products/laptop_cyberone.jpg',
            'id_categoria' => $catIds['Portátiles'],
            'id_marca' => $brandId,
            'oferta' => 0,
            'sku' => 'LPT-CYB-001'
        ],
        [
            'nombre' => 'Tarjeta Gráfica RTX 4090',
            'descripcion' => 'La tarjeta gráfica más potente del mercado. DLSS 3.0, Ray Tracing y rendimiento sin límites.',
            'precio' => 1599.00,
            'stock' => 5,
            'imagen' => 'assets/img/products/rtx4090.jpg',
            'id_categoria' => $catIds['Componentes'],
            'id_marca' => $brandId,
            'oferta' => 1,
            'sku' => 'GPU-NV-4090'
        ],
        [
            'nombre' => 'Teclado Mecánico Neon RGB',
            'descripcion' => 'Switches mecánicos azules, retroiluminación RGB personalizable y diseño ergonómico.',
            'precio' => 89.50,
            'stock' => 50,
            'imagen' => 'assets/img/products/keyboard_neon.jpg',
            'id_categoria' => $catIds['Accesorios'],
            'id_marca' => $brandId,
            'oferta' => 0,
            'sku' => 'KBD-NEO-001'
        ]
    ];

    foreach ($products as $p) {
        // Verificar si existe por nombre para no duplicar
        $stmt = $pdo->prepare("SELECT id FROM productos WHERE nombre = ?");
        $stmt->execute([$p['nombre']]);
        if ($stmt->fetch()) {
            echo "Producto ya existe: {$p['nombre']} - Omitiendo.\n";
            continue;
        }

        // Agregar campos faltantes con valores por defecto
        $p['stock_minimo'] = 1;
        $p['costo_unitario'] = $p['precio'] * 0.7;

        $sql = "INSERT INTO productos (nombre, descripcion, precio, stock, imagen, id_categoria, id_marca, oferta, fecha_creacion, sku, stock_minimo, costo_unitario) 
                VALUES (:nombre, :descripcion, :precio, :stock, :imagen, :id_categoria, :id_marca, :oferta, NOW(), :sku, :stock_minimo, :costo_unitario)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($p);
        echo "Producto creado: {$p['nombre']}\n";
    }

    $pdo->commit();
    echo "¡Proceso completado con éxito!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
?>
