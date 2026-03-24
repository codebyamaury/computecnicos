<?php
// Generador de productos aleatorios para pruebas
// Uso: php scripts/seed_random_products.php [cantidad]

require_once __DIR__ . '/../app/Core/bootstrap.php';

// Config de categorías orientadas a electrónica
$CATEGORY_CONFIG = [
    'Computadores'   => ['tipos' => ['Laptop', 'Desktop', 'All-in-One'], 'price' => [1500000, 8000000]],
    'Celulares'      => ['tipos' => ['Smartphone'], 'price' => [400000, 7000000]],
    'Tablets'        => ['tipos' => ['Tablet'], 'price' => [300000, 4000000]],
    'Parlantes'      => ['tipos' => ['Parlante Bluetooth', 'Parlante Inteligente'], 'price' => [80000, 800000]],
    'Monitores'      => ['tipos' => ['Monitor LED', 'Monitor Gaming'], 'price' => [400000, 4000000]],
    'Audífonos'      => ['tipos' => ['Audífonos Inalámbricos', 'Audífonos Over-Ear'], 'price' => [60000, 1200000]],
    'Routers'        => ['tipos' => ['Router WiFi 6', 'Router Mesh'], 'price' => [90000, 900000]],
    'Almacenamiento' => ['tipos' => ['SSD NVMe', 'HDD 2.5"'], 'price' => [120000, 1600000]],
    'Impresoras'     => ['tipos' => ['Impresora Multifuncional'], 'price' => [300000, 2500000]],
    'Smartwatch'     => ['tipos' => ['Reloj Inteligente'], 'price' => [200000, 3000000]],
];

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?');
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

function ensureBaseTaxonomy(PDO $pdo): array {
    global $CATEGORY_CONFIG;
    $categoriasBase = array_keys($CATEGORY_CONFIG);
    $marcasBase = ['Apple','Samsung','Xiaomi','Lenovo','HP','Dell','ASUS','Acer','Huawei','JBL','Sony','Logitech','TP-Link','Kingston','Western Digital','Motorola','Realme','Honor'];

    // Asegurar categorías específicas
    $catMap = [];
    $selCat = $pdo->prepare('SELECT id FROM categorias WHERE nombre = ?');
    $insCat = $pdo->prepare('INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)');
    foreach ($categoriasBase as $c) {
        $selCat->execute([$c]);
        $id = $selCat->fetchColumn();
        if (!$id) {
            $insCat->execute([$c, 'Categoría de electrónica: ' . $c]);
            $id = (int)$pdo->lastInsertId();
        }
        $catMap[$c] = (int)$id;
    }

    // Asegurar marcas específicas
    $brandMap = [];
    $selBrand = $pdo->prepare('SELECT id FROM marcas WHERE nombre = ?');
    $insBrand = $pdo->prepare('INSERT INTO marcas (nombre, descripcion) VALUES (?, ?)');
    foreach ($marcasBase as $m) {
        $selBrand->execute([$m]);
        $id = $selBrand->fetchColumn();
        if (!$id) {
            $insBrand->execute([$m, 'Marca de prueba: ' . $m]);
            $id = (int)$pdo->lastInsertId();
        }
        $brandMap[$m] = (int)$id;
    }

    return [
        'categorias' => $catMap,
        'marcas' => $brandMap,
    ];
}

function randomProductData(array $catMap, array $brandMap): array {
    global $CATEGORY_CONFIG;

    $brandName = array_rand($brandMap);
    $brandId = $brandMap[$brandName];

    $catName = array_rand($catMap);
    $catId = $catMap[$catName];

    $conf = $CATEGORY_CONFIG[$catName];
    $tipo = $conf['tipos'][array_rand($conf['tipos'])];
    $series = ['A', 'S', 'V', 'Z', 'G', 'M', 'P', 'X'];
    $nums = ['10', '20', '30', '50', '70', '90', '100', '200', '300', '500', '700'];
    $ramOpts = ['4GB','6GB','8GB','12GB','16GB','32GB'];
    $storageOpts = ['64GB','128GB','256GB','512GB','1TB','2TB'];
    $screenOpts = ['6.1"','6.7"','11"','13"','14"','15.6"','27"','32"'];

    $model = $series[array_rand($series)] . $nums[array_rand($nums)];
    $spec = '';
    switch ($catName) {
        case 'Computadores':
            $spec = $ramOpts[array_rand($ramOpts)] . ', ' . $storageOpts[array_rand($storageOpts)];
            break;
        case 'Celulares':
        case 'Tablets':
            $spec = $storageOpts[array_rand($storageOpts)] . ', pantalla ' . $screenOpts[array_rand($screenOpts)];
            break;
        case 'Monitores':
            $spec = 'Pantalla ' . $screenOpts[array_rand($screenOpts)] . ', 144Hz';
            break;
        case 'Parlantes':
            $spec = 'Bluetooth 5.3, 20W RMS';
            break;
        case 'Audífonos':
            $spec = 'ANC, Bluetooth 5.2';
            break;
        case 'Routers':
            $spec = 'WiFi 6, MU-MIMO';
            break;
        case 'Almacenamiento':
            $spec = $storageOpts[array_rand($storageOpts)] . ', NVMe';
            break;
        case 'Impresoras':
            $spec = 'WiFi, Dúplex automático';
            break;
        case 'Smartwatch':
            $spec = 'GPS, NFC';
            break;
    }

    $name = "$brandName $tipo $model";
    $desc = 'Producto electrónico de prueba: ' . $catName . ' — Especificaciones: ' . $spec;
    $min = $conf['price'][0];
    $max = $conf['price'][1];
    $price = mt_rand($min, $max);
    $stock = mt_rand(0, 50);
    $imgSeed = bin2hex(random_bytes(4));
    $image = "https://picsum.photos/seed/$imgSeed/600/400";
    $offer = (mt_rand(0, 100) < 25) ? 1 : 0; // 25% en oferta
    $sku = strtoupper(substr($brandName,0,2)) . '-' . strtoupper(substr($tipo,0,2)) . '-' . $model . '-' . strtoupper(substr($imgSeed,0,4));
    $stockMin = mt_rand(0, 10);
    $costUnit = round($price * mt_rand(60, 85) / 100, 2);
    $iva = 19.00;

    return compact('name','desc','price','stock','image','catId','brandId','offer','sku','stockMin','costUnit','iva');
}

try {
    $cantidad = isset($argv[1]) && is_numeric($argv[1]) ? max(1, (int)$argv[1]) : 30;

    [$colsCats, $colsBrands] = [true, true];
    // Verificar columnas opcionales en productos
    $hasSku = columnExists($pdo, 'productos', 'sku');
    $hasStockMin = columnExists($pdo, 'productos', 'stock_minimo');
    $hasCostoUnit = columnExists($pdo, 'productos', 'costo_unitario');
    $hasIva = columnExists($pdo, 'productos', 'iva_porcentaje');

    $tax = ensureBaseTaxonomy($pdo);
    $catMap = $tax['categorias'];
    $brandMap = $tax['marcas'];

    $insertSql = 'INSERT INTO productos (nombre, descripcion, precio, stock, imagen, id_categoria, id_marca, oferta, fecha_creacion';
    $valuesSql = ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW()';
    $extra = [];
    if ($hasSku) { $insertSql .= ', sku'; $valuesSql .= ', ?'; $extra[] = 'sku'; }
    if ($hasStockMin) { $insertSql .= ', stock_minimo'; $valuesSql .= ', ?'; $extra[] = 'stockMin'; }
    if ($hasCostoUnit) { $insertSql .= ', costo_unitario'; $valuesSql .= ', ?'; $extra[] = 'costUnit'; }
    if ($hasIva) { $insertSql .= ', iva_porcentaje'; $valuesSql .= ', ?'; $extra[] = 'iva'; }
    $insertSql .= $valuesSql . ')';

    $stmt = $pdo->prepare($insertSql);

    $created = 0;
    for ($i = 0; $i < $cantidad; $i++) {
        $data = randomProductData($catMap, $brandMap);
        $params = [
            $data['name'],
            $data['desc'],
            $data['price'],
            $data['stock'],
            $data['image'],
            $data['catId'],
            $data['brandId'],
            $data['offer'],
        ];
        foreach ($extra as $key) { $params[] = $data[$key]; }

        $stmt->execute($params);
        $created++;
    }

    echo "Productos creados: $created\n";
    echo "Sugerencia: visita /productos.php para verlos en el listado.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error al generar productos: ' . $e->getMessage() . "\n");
    exit(1);
}

?>