<?php
/**
 * ═══════════════════════════════════════════════════════════════════
 *  SEED DE PRODUCTOS — CompuTécnicos
 *  Ejecutar: php seed_productos.php  (CLI)
 *  O abrir:  http://localhost/computecnicosproject/seed_productos.php
 * ═══════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo->beginTransaction();

    // ─────────────────────────────────────
    // 1. CATEGORÍAS
    // ─────────────────────────────────────
    $categorias = [
        ['id' => 1, 'nombre' => 'Laptops', 'descripcion' => 'Portátiles para trabajo, estudio y gaming'],
        ['id' => 2, 'nombre' => 'Computadoras', 'descripcion' => 'Equipos de escritorio y estaciones de trabajo'],
        ['id' => 3, 'nombre' => 'Componentes', 'descripcion' => 'Partes y piezas para ensamblaje y upgrade'],
        ['id' => 4, 'nombre' => 'Accesorios', 'descripcion' => 'Periféricos y complementos para tu equipo'],
    ];

    $stmtCat = $pdo->prepare(
        "INSERT INTO categorias (id, nombre, descripcion)
         VALUES (:id, :nombre, :descripcion)
         ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), descripcion=VALUES(descripcion)"
    );
    foreach ($categorias as $c) {
        $stmtCat->execute($c);
    }
    echo "✔ Categorías insertadas/actualizadas\n";

    // ─────────────────────────────────────
    // 2. MARCAS
    // ─────────────────────────────────────
    $marcas = [
        ['id' => 1, 'nombre' => 'ASUS', 'descripcion' => 'Líder en hardware y electrónica'],
        ['id' => 2, 'nombre' => 'HP', 'descripcion' => 'Hewlett-Packard — soluciones informáticas'],
        ['id' => 3, 'nombre' => 'Lenovo', 'descripcion' => 'Innovación para todos'],
        ['id' => 4, 'nombre' => 'Dell', 'descripcion' => 'Tecnología empresarial y personal'],
        ['id' => 5, 'nombre' => 'MSI', 'descripcion' => 'Hardware gaming y profesional'],
        ['id' => 6, 'nombre' => 'Corsair', 'descripcion' => 'Periféricos y componentes gaming'],
        ['id' => 7, 'nombre' => 'Logitech', 'descripcion' => 'Periféricos de alta calidad'],
        ['id' => 8, 'nombre' => 'Kingston', 'descripcion' => 'Memorias y almacenamiento'],
        ['id' => 9, 'nombre' => 'Samsung', 'descripcion' => 'Electrónica y tecnología global'],
        ['id' => 10, 'nombre' => 'Razer', 'descripcion' => 'Gaming y estilo de vida gamer'],
        ['id' => 11, 'nombre' => 'Acer', 'descripcion' => 'Soluciones tecnológicas accesibles'],
        ['id' => 12, 'nombre' => 'Apple', 'descripcion' => 'Innovación y diseño premium'],
    ];

    $stmtBrand = $pdo->prepare(
        "INSERT INTO marcas (id, nombre, descripcion)
         VALUES (:id, :nombre, :descripcion)
         ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), descripcion=VALUES(descripcion)"
    );
    foreach ($marcas as $m) {
        $stmtBrand->execute($m);
    }
    echo "✔ Marcas insertadas/actualizadas\n";

    // ─────────────────────────────────────
    // 3. PRODUCTOS
    // ─────────────────────────────────────
    $productos = [

        // ══════ LAPTOPS (categoría 1) ══════
        [
            'nombre' => 'ASUS ROG Strix G16',
            'descripcion' => 'Laptop gaming con Intel Core i7-13650HX, 16GB DDR5 RAM, 512GB SSD NVMe, NVIDIA RTX 4060 8GB, pantalla 16" FHD+ 165Hz, teclado RGB, Wi-Fi 6E.',
            'precio' => 5499900,
            'stock' => 8,
            'imagen' => 'https://dlcdnwebimgs.asus.com/gain/82E3E463-B3E4-4895-B48D-C649B62B579E/w1000/h732',
            'id_categoria' => 1,
            'id_marca' => 1,
            'oferta' => 1,
            'sku' => 'LAP-ASUS-001',
            'stock_minimo' => 2,
            'costo_unitario' => 4200000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'HP Pavilion 15-eh2025',
            'descripcion' => 'Laptop AMD Ryzen 5 7530U, 8GB DDR4 RAM, 256GB SSD, pantalla 15.6" FHD IPS, Windows 11 Home, diseño delgado y ligero ideal para productividad.',
            'precio' => 2199900,
            'stock' => 15,
            'imagen' => 'https://ssl-product-images.www8-hp.com/digmedialib/prodimg/lowres/c08520866.png',
            'id_categoria' => 1,
            'id_marca' => 2,
            'oferta' => 0,
            'sku' => 'LAP-HP-001',
            'stock_minimo' => 3,
            'costo_unitario' => 1700000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'Lenovo IdeaPad 3 15IAU7',
            'descripcion' => 'Laptop Intel Core i5-1235U, 8GB DDR4, 512GB SSD NVMe, pantalla 15.6" FHD anti-reflejo, lector de huellas, batería hasta 8 horas.',
            'precio' => 2499900,
            'stock' => 12,
            'imagen' => 'https://p4-ofp.static.pub/ShareResource/na/subseries/hero/lenovo-laptops-ideapad-3-background.png',
            'id_categoria' => 1,
            'id_marca' => 3,
            'oferta' => 0,
            'sku' => 'LAP-LEN-001',
            'stock_minimo' => 3,
            'costo_unitario' => 1900000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'Dell Inspiron 15 3520',
            'descripcion' => 'Laptop Intel Core i5-1235U, 16GB DDR4 RAM, 512GB SSD, pantalla 15.6" FHD, cámara HD con micrófono, HDMI, USB-C, Windows 11.',
            'precio' => 2799900,
            'stock' => 10,
            'imagen' => 'https://i.dell.com/is/image/DellContent/content/dam/ss2/product-images/dell-client-products/notebooks/inspiron-notebooks/15-3520/media-gallery/in3520nt-cnb-00000ff090-sl.psd',
            'id_categoria' => 1,
            'id_marca' => 4,
            'oferta' => 1,
            'sku' => 'LAP-DELL-001',
            'stock_minimo' => 2,
            'costo_unitario' => 2100000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'MSI Katana 15 B13VFK',
            'descripcion' => 'Laptop gaming Intel Core i7-13620H, 16GB DDR5, 1TB SSD NVMe, NVIDIA RTX 4060 8GB, pantalla 15.6" FHD 144Hz, retroiluminación RGB.',
            'precio' => 5999900,
            'stock' => 5,
            'imagen' => 'https://asset.msi.com/resize/image/global/product/product_1678435030c06ee8ccddafdee481ba4f90e5a21c76.png62405b38c58fe0f07fcef2367d8a9ba1/1024.png',
            'id_categoria' => 1,
            'id_marca' => 5,
            'oferta' => 1,
            'sku' => 'LAP-MSI-001',
            'stock_minimo' => 2,
            'costo_unitario' => 4600000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'Acer Nitro 5 AN515-58',
            'descripcion' => 'Laptop gaming Intel Core i5-12500H, 16GB DDR4, 512GB SSD, NVIDIA RTX 3050 Ti 4GB, pantalla 15.6" FHD 144Hz, teclado retroiluminado.',
            'precio' => 3899900,
            'stock' => 7,
            'imagen' => 'https://static-ecapac.acer.com/media/catalog/product/cache/884b10e0aef487e0f2fb110558566a9f/n/i/nitro5_an515-58_bl_modelmain.png',
            'id_categoria' => 1,
            'id_marca' => 11,
            'oferta' => 0,
            'sku' => 'LAP-ACER-001',
            'stock_minimo' => 2,
            'costo_unitario' => 2900000,
            'iva_porcentaje' => 19.00,
        ],

        // ══════ COMPUTADORAS (categoría 2) ══════
        [
            'nombre' => 'ASUS ExpertCenter D5 Mini Tower',
            'descripcion' => 'PC de escritorio Intel Core i5-13400, 16GB DDR4, 512GB SSD + 1TB HDD, Intel UHD 730, Wi-Fi 6, Windows 11 Pro, ideal para oficina y negocios.',
            'precio' => 3299900,
            'stock' => 6,
            'imagen' => 'https://dlcdnwebimgs.asus.com/gain/07F37D58-F519-4D4E-8EDB-2C3E0A126C56/w1000/h732',
            'id_categoria' => 2,
            'id_marca' => 1,
            'oferta' => 0,
            'sku' => 'PC-ASUS-001',
            'stock_minimo' => 2,
            'costo_unitario' => 2500000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'HP Pro Tower 400 G9',
            'descripcion' => 'Desktop empresarial Intel Core i7-12700, 16GB DDR4, 512GB SSD NVMe, Intel UHD 770, puertos USB-C, Windows 11 Pro, garantía 3 años.',
            'precio' => 3999900,
            'stock' => 4,
            'imagen' => 'https://ssl-product-images.www8-hp.com/digmedialib/prodimg/lowres/c08413850.png',
            'id_categoria' => 2,
            'id_marca' => 2,
            'oferta' => 0,
            'sku' => 'PC-HP-001',
            'stock_minimo' => 2,
            'costo_unitario' => 3100000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'Lenovo ThinkCentre M70q Gen4',
            'descripcion' => 'Mini PC Intel Core i5-13400T, 8GB DDR4, 256GB SSD, ultra compacto 1L, puertos DisplayPort y HDMI, Wi-Fi 6E, perfecto para espacios reducidos.',
            'precio' => 2599900,
            'stock' => 9,
            'imagen' => 'https://p4-ofp.static.pub/ShareResource/na/subseries/hero/lenovo-desktops-thinkcentre-702.png',
            'id_categoria' => 2,
            'id_marca' => 3,
            'oferta' => 1,
            'sku' => 'PC-LEN-001',
            'stock_minimo' => 3,
            'costo_unitario' => 1900000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'MSI MAG Infinite S3 13-661',
            'descripcion' => 'PC gaming Intel Core i5-13400F, 16GB DDR5, 512GB SSD NVMe, NVIDIA RTX 4060 8GB, iluminación RGB, fuente 500W 80+ Bronze, Windows 11 Home.',
            'precio' => 4799900,
            'stock' => 3,
            'imagen' => 'https://asset.msi.com/resize/image/global/product/product_16784360805fbd1b08b5c0d54e6f7ab3a9f2c94a79.png62405b38c58fe0f07fcef2367d8a9ba1/1024.png',
            'id_categoria' => 2,
            'id_marca' => 5,
            'oferta' => 1,
            'sku' => 'PC-MSI-001',
            'stock_minimo' => 1,
            'costo_unitario' => 3700000,
            'iva_porcentaje' => 19.00,
        ],

        // ══════ COMPONENTES (categoría 3) ══════
        [
            'nombre' => 'ASUS ROG Strix B760-F WiFi',
            'descripcion' => 'Placa madre LGA1700 ATX, DDR5, PCIe 5.0, Wi-Fi 6E, Bluetooth 5.3, USB 3.2 Gen 2x2, iluminación Aura Sync RGB, compatible Intel 12va y 13va gen.',
            'precio' => 899900,
            'stock' => 14,
            'imagen' => 'https://dlcdnwebimgs.asus.com/gain/D6EDBA14-02F3-4E8B-89CD-C53E24DB42AE/w1000/h732',
            'id_categoria' => 3,
            'id_marca' => 1,
            'oferta' => 0,
            'sku' => 'COM-ASUS-001',
            'stock_minimo' => 5,
            'costo_unitario' => 680000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'Corsair Vengeance DDR5 32GB (2x16GB)',
            'descripcion' => 'Kit de memoria RAM DDR5 5600MHz, CL36, Intel XMP 3.0, disipador de aluminio negro, optimizada para gaming y productividad.',
            'precio' => 449900,
            'stock' => 25,
            'imagen' => 'https://www.corsair.com/medias/sys_master/images/images/h1e/h39/67388309913630/CMK32GX5M2B5600C36.png',
            'id_categoria' => 3,
            'id_marca' => 6,
            'oferta' => 1,
            'sku' => 'COM-COR-001',
            'stock_minimo' => 5,
            'costo_unitario' => 320000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'Kingston NV2 SSD 1TB NVMe',
            'descripcion' => 'SSD M.2 2280 NVMe PCIe 4.0 x4, lectura hasta 3500 MB/s, escritura hasta 2100 MB/s, compacto y eficiente para upgrades.',
            'precio' => 219900,
            'stock' => 30,
            'imagen' => 'https://media.kingston.com/kingston/product/ktc-product-ssd-snv2s-702x702.png',
            'id_categoria' => 3,
            'id_marca' => 8,
            'oferta' => 0,
            'sku' => 'COM-KNG-001',
            'stock_minimo' => 8,
            'costo_unitario' => 155000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'MSI GeForce RTX 4070 Ventus 2X',
            'descripcion' => 'Tarjeta gráfica NVIDIA RTX 4070 12GB GDDR6X, DLSS 3.0, Ray Tracing, doble ventilador Torx 4.0, reloj boost 2475 MHz, DisplayPort 1.4a x3, HDMI 2.1.',
            'precio' => 2899900,
            'stock' => 6,
            'imagen' => 'https://asset.msi.com/resize/image/global/product/product_16798373902c3da5ef8f6be78f3e93c85c3edb5cc5.png62405b38c58fe0f07fcef2367d8a9ba1/1024.png',
            'id_categoria' => 3,
            'id_marca' => 5,
            'oferta' => 1,
            'sku' => 'COM-MSI-001',
            'stock_minimo' => 2,
            'costo_unitario' => 2200000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'Corsair RM850x Fuente 850W 80+ Gold',
            'descripcion' => 'Fuente de poder ATX 850W, certificación 80+ Gold, completamente modular, ventilador 135mm Zero RPM, condensadores japoneses, garantía 10 años.',
            'precio' => 549900,
            'stock' => 11,
            'imagen' => 'https://www.corsair.com/medias/sys_master/images/images/hc3/h72/67302988029982/CP-9020200-NA.png',
            'id_categoria' => 3,
            'id_marca' => 6,
            'oferta' => 0,
            'sku' => 'COM-COR-002',
            'stock_minimo' => 3,
            'costo_unitario' => 400000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'Samsung 990 PRO SSD 2TB NVMe',
            'descripcion' => 'SSD M.2 2280 NVMe PCIe 4.0 x4, lectura hasta 7450 MB/s, escritura hasta 6900 MB/s, disipador de calor integrado, ideal para PS5 y PC.',
            'precio' => 749900,
            'stock' => 12,
            'imagen' => 'https://image-us.samsung.com/SamsungUS/home/computing/memory-storage/solid-state-drives/01202023/MZ-V9P2T0B-AM_001_Front_Black.jpg',
            'id_categoria' => 3,
            'id_marca' => 9,
            'oferta' => 1,
            'sku' => 'COM-SAM-001',
            'stock_minimo' => 3,
            'costo_unitario' => 550000,
            'iva_porcentaje' => 19.00,
        ],

        // ══════ ACCESORIOS (categoría 4) ══════
        [
            'nombre' => 'Logitech G Pro X Superlight 2',
            'descripcion' => 'Mouse gaming inalámbrico, sensor HERO 2, 32K DPI, 60g ultraligero, LIGHTSPEED wireless, batería 95 horas, switches óptico-mecánicos LIGHTFORCE.',
            'precio' => 599900,
            'stock' => 18,
            'imagen' => 'https://resource.logitechg.com/w_1000,c_limit,q_auto,f_auto,dpr_1.0/d_transparent.gif/content/dam/gaming/en/products/pro-x2-702x702-702x702-702x702.png',
            'id_categoria' => 4,
            'id_marca' => 7,
            'oferta' => 1,
            'sku' => 'ACC-LOG-001',
            'stock_minimo' => 5,
            'costo_unitario' => 430000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'Corsair K70 RGB PRO',
            'descripcion' => 'Teclado mecánico gaming, switches Cherry MX Red, marco de aluminio, iluminación RGB individual por tecla, reposamuñecas magnético, USB passthrough.',
            'precio' => 649900,
            'stock' => 10,
            'imagen' => 'https://www.corsair.com/medias/sys_master/images/images/hd0/h05/67192397578270/CH-9109410-NA.png',
            'id_categoria' => 4,
            'id_marca' => 6,
            'oferta' => 0,
            'sku' => 'ACC-COR-001',
            'stock_minimo' => 3,
            'costo_unitario' => 480000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'Razer DeathAdder V3 Pro',
            'descripcion' => 'Mouse gaming inalámbrico, sensor Focus Pro 30K, 63g ultraligero, HyperSpeed wireless, switches ópticos Gen-3, perfil ergonómico, hasta 90 horas de batería.',
            'precio' => 649900,
            'stock' => 14,
            'imagen' => 'https://assets3.razerzone.com/davINcaXJ3nbFpvbfHdMfsPUho=/1500x1000/https%3A%2F%2Fhybrismediaprod.blob.core.windows.net%2Fsys-master-phoenix-images-container%2Fh47%2Fhb8%2F9524992245790.png',
            'id_categoria' => 4,
            'id_marca' => 10,
            'oferta' => 0,
            'sku' => 'ACC-RAZ-001',
            'stock_minimo' => 4,
            'costo_unitario' => 470000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'ASUS VG249Q1A Monitor 24" Gaming',
            'descripcion' => 'Monitor gaming 23.8" Full HD IPS, 165Hz, 1ms MPRT, AMD FreeSync Premium, Eye Care, HDMI x2, DisplayPort, altavoces integrados.',
            'precio' => 849900,
            'stock' => 7,
            'imagen' => 'https://dlcdnwebimgs.asus.com/gain/BD15BDB5-E1BD-4CA6-BB0F-C7E9D6ABD3FD/w1000/h732',
            'id_categoria' => 4,
            'id_marca' => 1,
            'oferta' => 1,
            'sku' => 'ACC-ASUS-001',
            'stock_minimo' => 2,
            'costo_unitario' => 620000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'Logitech G733 Lightspeed',
            'descripcion' => 'Audífonos gaming inalámbricos, DTS Headphone:X 2.0, transductores PRO-G 40mm, micrófono Blue VO!CE, batería 29 horas, peso 278g, diadema reversible.',
            'precio' => 449900,
            'stock' => 13,
            'imagen' => 'https://resource.logitechg.com/w_1000,c_limit,q_auto,f_auto,dpr_1.0/d_transparent.gif/content/dam/gaming/en/products/g733/g733-702x702-702x702.png',
            'id_categoria' => 4,
            'id_marca' => 7,
            'oferta' => 0,
            'sku' => 'ACC-LOG-002',
            'stock_minimo' => 4,
            'costo_unitario' => 320000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'Kingston DataTraveler Max USB-C 256GB',
            'descripcion' => 'Memoria USB-C 3.2 Gen 2, velocidad lectura hasta 1000 MB/s, escritura hasta 900 MB/s, diseño con anilla acoplable, compatible USB-C.',
            'precio' => 149900,
            'stock' => 35,
            'imagen' => 'https://media.kingston.com/kingston/product/ktc-product-flash-702x702.png',
            'id_categoria' => 4,
            'id_marca' => 8,
            'oferta' => 0,
            'sku' => 'ACC-KNG-001',
            'stock_minimo' => 10,
            'costo_unitario' => 95000,
            'iva_porcentaje' => 19.00,
        ],
        [
            'nombre' => 'Razer BlackShark V2 Pro',
            'descripcion' => 'Audífonos gaming inalámbricos, HyperSpeed wireless, transductores TriForce Titanium 50mm, micrófono HyperClear Super Wideband, THX Spatial Audio.',
            'precio' => 749900,
            'stock' => 9,
            'imagen' => 'https://assets3.razerzone.com/davINcaXJ3nbFpvbfHdMfsPUho=/1500x1000/https%3A%2F%2Fhybrismediaprod.blob.core.windows.net%2Fsys-master-phoenix-images-container%2Fhb0%2Fh0d%2F9606741680158.png',
            'id_categoria' => 4,
            'id_marca' => 10,
            'oferta' => 1,
            'sku' => 'ACC-RAZ-002',
            'stock_minimo' => 3,
            'costo_unitario' => 540000,
            'iva_porcentaje' => 19.00,
        ],
    ];

    // Query de inserción con ON DUPLICATE KEY UPDATE (usa SKU como control)
    $sql = "INSERT INTO productos
                (nombre, descripcion, precio, stock, imagen, id_categoria, id_marca, oferta, fecha_creacion, sku, stock_minimo, costo_unitario, iva_porcentaje)
            VALUES
                (:nombre, :descripcion, :precio, :stock, :imagen, :id_categoria, :id_marca, :oferta, NOW(), :sku, :stock_minimo, :costo_unitario, :iva_porcentaje)
            ON DUPLICATE KEY UPDATE
                nombre         = VALUES(nombre),
                descripcion    = VALUES(descripcion),
                precio         = VALUES(precio),
                stock          = VALUES(stock),
                imagen         = VALUES(imagen),
                id_categoria   = VALUES(id_categoria),
                id_marca       = VALUES(id_marca),
                oferta         = VALUES(oferta),
                stock_minimo   = VALUES(stock_minimo),
                costo_unitario = VALUES(costo_unitario),
                iva_porcentaje = VALUES(iva_porcentaje)";

    $stmtProd = $pdo->prepare($sql);

    $insertados = 0;
    foreach ($productos as $p) {
        $stmtProd->execute($p);
        $insertados++;
        echo "  ✔ Producto: {$p['nombre']}  (SKU: {$p['sku']})\n";
    }

    $pdo->commit();

    echo "\n═══════════════════════════════════════\n";
    echo "  ✅ SEED COMPLETADO\n";
    echo "  📦 $insertados productos procesados\n";
    echo "  📂 4 categorías\n";
    echo "  🏷️ " . count($marcas) . " marcas\n";
    echo "═══════════════════════════════════════\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "📍 En línea: " . $e->getLine() . "\n";
}
