-- SQL consolidado: base + funcionalidades (facturación, inventario, comentarios, impuestos, notas crédito)
-- Ejecuta este archivo para tener todo listo en una sola importación.

-- Crear/usar base de datos
-- CREATE DATABASE IF NOT EXISTS computecnicos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE computecnicos;

-- =========================
-- BASE: Tablas principales
-- =========================
-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    telefono VARCHAR(30),
    direccion VARCHAR(255),
    password VARCHAR(255) NOT NULL,
    foto VARCHAR(255),
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    rol ENUM('cliente', 'admin') NOT NULL DEFAULT 'cliente',
    es_principal TINYINT(1) NOT NULL DEFAULT 0
);

-- Tabla de categorías
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT
);

-- Tabla de marcas
CREATE TABLE IF NOT EXISTS marcas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT
);

-- Tabla de productos
CREATE TABLE IF NOT EXISTS productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(12, 2) NOT NULL,
    stock INT DEFAULT 0,
    imagen VARCHAR(255),
    id_categoria INT,
    id_marca INT,
    oferta BOOLEAN DEFAULT 0,
    destacado TINYINT(1) NOT NULL DEFAULT 0,
    nuevo_hasta DATE DEFAULT NULL,
    oferta_hasta DATE DEFAULT NULL,
    precio_original DECIMAL(12, 2) DEFAULT NULL,
    descuento DECIMAL(5, 2) DEFAULT NULL,
    video_url VARCHAR(500) DEFAULT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_categoria) REFERENCES categorias (id) ON DELETE SET NULL,
    FOREIGN KEY (id_marca) REFERENCES marcas (id) ON DELETE SET NULL
);

-- Tabla de imágenes adicionales de productos
CREATE TABLE IF NOT EXISTS imagenes_producto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    url_imagen VARCHAR(255) NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_producto) REFERENCES productos (id) ON DELETE CASCADE
);

-- Tabla de pedidos
CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado ENUM(
        'pendiente',
        'pagado',
        'preparacion',
        'enviado',
        'entregado',
        'cancelado'
    ) DEFAULT 'pendiente',
    total DECIMAL(12, 2) NOT NULL,
    direccion_envio VARCHAR(255),
    numero_guia VARCHAR(100) NULL,
    notificado_admin TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (id_usuario) REFERENCES usuarios (id) ON DELETE CASCADE
);

-- Historial de estados del pedido
CREATE TABLE IF NOT EXISTS pedido_estados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    estado ENUM(
        'pendiente',
        'pagado',
        'preparacion',
        'enviado',
        'entregado',
        'cancelado'
    ) NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    comentario VARCHAR(255) NULL,
    FOREIGN KEY (id_pedido) REFERENCES pedidos (id) ON DELETE CASCADE,
    INDEX idx_pedido_estado (id_pedido, estado)
);

-- Tabla de detalle de pedido
CREATE TABLE IF NOT EXISTS detalle_pedido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT,
    id_producto INT,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(12, 2) NOT NULL,
    descuento DECIMAL(5, 2) NOT NULL DEFAULT 0,
    FOREIGN KEY (id_pedido) REFERENCES pedidos (id) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES productos (id) ON DELETE CASCADE
);

-- Tabla de servicios
CREATE TABLE IF NOT EXISTS servicios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    precio DECIMAL(12, 2),
    icono VARCHAR(100)
);

-- Tabla de contactos (mensajes de contacto)
CREATE TABLE IF NOT EXISTS contactos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    mensaje TEXT NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de proveedores
CREATE TABLE IF NOT EXISTS proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    telefono VARCHAR(30),
    direccion VARCHAR(255),
    contacto VARCHAR(100),
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de movimientos de inventario
CREATE TABLE IF NOT EXISTS movimientos_inventario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    tipo ENUM('entrada', 'salida', 'ajuste') NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(12, 2) DEFAULT NULL,
    id_proveedor INT DEFAULT NULL,
    numero_factura VARCHAR(50) DEFAULT NULL,
    iva DECIMAL(12, 2) DEFAULT 0,
    retencion DECIMAL(12, 2) DEFAULT 0,
    soporte VARCHAR(255) DEFAULT NULL,
    motivo VARCHAR(255),
    id_usuario INT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_producto) REFERENCES productos (id) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios (id) ON DELETE SET NULL,
    FOREIGN KEY (id_proveedor) REFERENCES proveedores (id) ON DELETE SET NULL
);

-- Tabla de sesiones PHP (DB Handler)
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    data TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX idx_expires (expires_at)
);

-- Tabla de tokens para "Recordarme"
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios (id) ON DELETE CASCADE,
    INDEX idx_token (token_hash)
);

-- =====================================
-- FEATURE: Actualización de inventario
-- =====================================
-- Agrega columnas para SKU, stock mínimo y costo unitario
ALTER TABLE productos
ADD COLUMN sku VARCHAR(64) NULL,
ADD COLUMN stock_minimo INT DEFAULT 0,
ADD COLUMN costo_unitario DECIMAL(12, 2) DEFAULT NULL;

-- Índice para SKU
CREATE INDEX idx_productos_sku ON productos (sku);

-- =====================================
-- FEATURE: Facturación electrónica
-- =====================================
CREATE TABLE IF NOT EXISTS facturas_electronicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    provider VARCHAR(32) NOT NULL,
    external_id VARCHAR(128) NULL,
    estado VARCHAR(32) NOT NULL,
    numero VARCHAR(64) NULL,
    pdf_url VARCHAR(255) NULL,
    xml_url VARCHAR(255) NULL,
    total DECIMAL(12, 2) NULL,
    error_msg TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    UNIQUE KEY uniq_factura_pedido (id_pedido)
);

-- =====================================
-- FEATURE: Comentarios de producto
-- =====================================
CREATE TABLE IF NOT EXISTS comentarios_producto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    id_usuario INT NOT NULL,
    calificacion TINYINT NOT NULL DEFAULT 5,
    comentario TEXT,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_producto) REFERENCES productos (id) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios (id) ON DELETE CASCADE,
    INDEX idx_producto (id_producto),
    INDEX idx_usuario (id_usuario)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =====================================
-- FEATURE: Impuestos por producto (IVA)
-- =====================================
ALTER TABLE productos
ADD COLUMN iva_porcentaje DECIMAL(5, 2) NULL DEFAULT 19.00;

-- =====================================
-- FEATURE: Notas crédito
-- =====================================
CREATE TABLE IF NOT EXISTS notas_credito (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    provider VARCHAR(32) NOT NULL,
    external_id VARCHAR(128) NULL,
    estado VARCHAR(32) NOT NULL,
    numero VARCHAR(64) NULL,
    total DECIMAL(12, 2) NULL,
    motivo VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
-- =====================================
-- FEATURE: Reseñas de productos (robusto)
-- =====================================
CREATE TABLE IF NOT EXISTS resenas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    id_usuario INT NOT NULL,
    calificacion TINYINT NOT NULL DEFAULT 5,
    titulo VARCHAR(150) NULL,
    comentario TEXT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    verificado TINYINT(1) DEFAULT 1,
    FOREIGN KEY (id_producto) REFERENCES productos (id) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios (id) ON DELETE CASCADE,
    UNIQUE KEY uniq_resena_usuario_producto (id_usuario, id_producto),
    INDEX idx_producto (id_producto),
    INDEX idx_fecha (fecha)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resenas_imagenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_resena INT NOT NULL,
    url_imagen VARCHAR(500) NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_resena) REFERENCES resenas (id) ON DELETE CASCADE,
    INDEX idx_resena (id_resena)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =====================================
-- FEATURE: Olvidar contraseña
-- =====================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expira DATETIME NOT NULL,
    usado TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =====================================
-- FEATURE: Sesion persistente (Remember Me)
-- =====================================
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    token VARCHAR(128) NOT NULL UNIQUE,
    expira DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_usuario (id_usuario),
    INDEX idx_expira (expira),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =====================================
-- FEATURE: Reembolsos / Desembolsos
-- =====================================
CREATE TABLE IF NOT EXISTS reembolsos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_usuario INT NOT NULL,
    motivo VARCHAR(500) NOT NULL,
    monto DECIMAL(12, 2) NOT NULL,
    estado ENUM('solicitado', 'aprobado', 'procesado', 'rechazado') DEFAULT 'solicitado',
    paypal_refund_id VARCHAR(128) NULL,
    paypal_capture_id VARCHAR(128) NULL,
    nota_admin VARCHAR(500) NULL,
    fecha_solicitud DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_resolucion DATETIME NULL,
    id_admin_resolucion INT NULL,
    stock_devuelto TINYINT(1) DEFAULT 0,
    FOREIGN KEY (id_pedido) REFERENCES pedidos (id) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES usuarios (id) ON DELETE CASCADE,
    FOREIGN KEY (id_admin_resolucion) REFERENCES usuarios (id) ON DELETE SET NULL,
    INDEX idx_pedido (id_pedido),
    INDEX idx_estado (estado),
    INDEX idx_usuario (id_usuario)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =====================================
-- DATOS DE PRUEBA
-- =====================================

-- Categorías
INSERT INTO
    categorias (id, nombre, descripcion)
VALUES (
        1,
        'Laptops',
        'Portátiles para trabajo, estudio y gaming'
    ),
    (
        2,
        'Computadoras',
        'Equipos de escritorio y estaciones de trabajo'
    ),
    (
        3,
        'Componentes',
        'Partes y piezas para ensamblaje y upgrade'
    ),
    (
        4,
        'Accesorios',
        'Periféricos y complementos para tu equipo'
    )
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion);

-- Marcas
INSERT INTO
    marcas (id, nombre, descripcion)
VALUES (
        1,
        'ASUS',
        'Líder en hardware y electrónica'
    ),
    (
        2,
        'HP',
        'Hewlett-Packard — soluciones informáticas'
    ),
    (
        3,
        'Lenovo',
        'Innovación para todos'
    ),
    (
        4,
        'Dell',
        'Tecnología empresarial y personal'
    ),
    (
        5,
        'MSI',
        'Hardware gaming y profesional'
    ),
    (
        6,
        'Corsair',
        'Periféricos y componentes gaming'
    ),
    (
        7,
        'Logitech',
        'Periféricos de alta calidad'
    ),
    (
        8,
        'Kingston',
        'Memorias y almacenamiento'
    )
ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    descripcion = VALUES(descripcion);

-- ══════════════════════════════════════
-- PRODUCTOS: Laptops (categoría 1)
-- ══════════════════════════════════════
INSERT INTO
    productos (
        nombre,
        descripcion,
        precio,
        stock,
        imagen,
        id_categoria,
        id_marca,
        oferta,
        sku,
        stock_minimo,
        costo_unitario,
        iva_porcentaje
    )
VALUES (
        'ASUS ROG Strix G16',
        'Laptop gaming con Intel Core i7-13650HX, 16GB DDR5 RAM, 512GB SSD NVMe, NVIDIA RTX 4060 8GB, pantalla 16" FHD+ 165Hz, teclado RGB, Wi-Fi 6E.',
        5499900,
        8,
        'https://dlcdnwebimgs.asus.com/gain/82E3E463-B3E4-4895-B48D-C649B62B579E/w1000/h732',
        1,
        1,
        1,
        'LAP-ASUS-001',
        2,
        4200000,
        19.00
    ),
    (
        'HP Pavilion 15-eh2025',
        'Laptop AMD Ryzen 5 7530U, 8GB DDR4 RAM, 256GB SSD, pantalla 15.6" FHD IPS, Windows 11 Home, diseño delgado y ligero ideal para productividad.',
        2199900,
        15,
        'https://ssl-product-images.www8-hp.com/digmedialib/prodimg/lowres/c08520866.png',
        1,
        2,
        0,
        'LAP-HP-001',
        3,
        1700000,
        19.00
    ),
    (
        'Lenovo IdeaPad 3 15IAU7',
        'Laptop Intel Core i5-1235U, 8GB DDR4, 512GB SSD NVMe, pantalla 15.6" FHD anti-reflejo, lector de huellas, batería hasta 8 horas.',
        2499900,
        12,
        'https://p4-ofp.static.pub/ShareResource/na/subseries/hero/lenovo-laptops-ideapad-3-background.png',
        1,
        3,
        0,
        'LAP-LEN-001',
        3,
        1900000,
        19.00
    ),
    (
        'Dell Inspiron 15 3520',
        'Laptop Intel Core i5-1235U, 16GB DDR4 RAM, 512GB SSD, pantalla 15.6" FHD, cámara HD con micrófono, HDMI, USB-C, Windows 11.',
        2799900,
        10,
        'https://i.dell.com/is/image/DellContent/content/dam/ss2/product-images/dell-client-products/notebooks/inspiron-notebooks/15-3520/media-gallery/in3520nt-cnb-00000ff090-sl.psd',
        1,
        4,
        1,
        'LAP-DELL-001',
        2,
        2100000,
        19.00
    ),
    (
        'MSI Katana 15 B13VFK',
        'Laptop gaming Intel Core i7-13620H, 16GB DDR5, 1TB SSD NVMe, NVIDIA RTX 4060 8GB, pantalla 15.6" FHD 144Hz, retroiluminación RGB.',
        5999900,
        5,
        'https://asset.msi.com/resize/image/global/product/product_1678435030c06ee8ccddafdee481ba4f90e5a21c76.png62405b38c58fe0f07fcef2367d8a9ba1/1024.png',
        1,
        5,
        1,
        'LAP-MSI-001',
        2,
        4600000,
        19.00
    );

-- ══════════════════════════════════════
-- PRODUCTOS: Computadoras (categoría 2)
-- ══════════════════════════════════════
INSERT INTO
    productos (
        nombre,
        descripcion,
        precio,
        stock,
        imagen,
        id_categoria,
        id_marca,
        oferta,
        sku,
        stock_minimo,
        costo_unitario,
        iva_porcentaje
    )
VALUES (
        'ASUS ExpertCenter D5 Mini Tower',
        'PC de escritorio Intel Core i5-13400, 16GB DDR4, 512GB SSD + 1TB HDD, Intel UHD 730, Wi-Fi 6, Windows 11 Pro, ideal para oficina y negocios.',
        3299900,
        6,
        'https://dlcdnwebimgs.asus.com/gain/07F37D58-F519-4D4E-8EDB-2C3E0A126C56/w1000/h732',
        2,
        1,
        0,
        'PC-ASUS-001',
        2,
        2500000,
        19.00
    ),
    (
        'HP Pro Tower 400 G9',
        'Desktop empresarial Intel Core i7-12700, 16GB DDR4, 512GB SSD NVMe, Intel UHD 770, puertos USB-C, Windows 11 Pro, garantía 3 años.',
        3999900,
        4,
        'https://ssl-product-images.www8-hp.com/digmedialib/prodimg/lowres/c08413850.png',
        2,
        2,
        0,
        'PC-HP-001',
        2,
        3100000,
        19.00
    ),
    (
        'Lenovo ThinkCentre M70q Gen4',
        'Mini PC Intel Core i5-13400T, 8GB DDR4, 256GB SSD, ultra compacto 1L, puertos DisplayPort y HDMI, Wi-Fi 6E, perfecto para espacios reducidos.',
        2599900,
        9,
        'https://p4-ofp.static.pub/ShareResource/na/subseries/hero/lenovo-desktops-thinkcentre-702.png',
        2,
        3,
        1,
        'PC-LEN-001',
        3,
        1900000,
        19.00
    ),
    (
        'Dell OptiPlex 7010 SFF',
        'PC corporativo Intel Core i5-13500, 16GB DDR5, 512GB SSD, formato Small Form Factor, vPro Enterprise, puerto serie opcional, Windows 11 Pro.',
        3699900,
        7,
        'https://i.dell.com/is/image/DellContent/content/dam/ss2/product-images/dell-client-products/desktops/optiplex-702x-702x-702x/media-gallery/optiplex-702x-702x-702x-702x.psd',
        2,
        4,
        0,
        'PC-DELL-001',
        2,
        2800000,
        19.00
    ),
    (
        'MSI MAG Infinite S3 13-661',
        'PC gaming Intel Core i5-13400F, 16GB DDR5, 512GB SSD NVMe, NVIDIA RTX 4060 8GB, iluminación RGB, fuente 500W 80+ Bronze, Windows 11 Home.',
        4799900,
        3,
        'https://asset.msi.com/resize/image/global/product/product_16784360805fbd1b08b5c0d54e6f7ab3a9f2c94a79.png62405b38c58fe0f07fcef2367d8a9ba1/1024.png',
        2,
        5,
        1,
        'PC-MSI-001',
        1,
        3700000,
        19.00
    );

-- ══════════════════════════════════════
-- PRODUCTOS: Componentes (categoría 3)
-- ══════════════════════════════════════
INSERT INTO
    productos (
        nombre,
        descripcion,
        precio,
        stock,
        imagen,
        id_categoria,
        id_marca,
        oferta,
        sku,
        stock_minimo,
        costo_unitario,
        iva_porcentaje
    )
VALUES (
        'ASUS ROG Strix B760-F WiFi',
        'Placa madre LGA1700 ATX, DDR5, PCIe 5.0, Wi-Fi 6E, Bluetooth 5.3, USB 3.2 Gen 2x2, iluminación Aura Sync RGB, compatible Intel 12va y 13va gen.',
        899900,
        14,
        'https://dlcdnwebimgs.asus.com/gain/D6EDBA14-02F3-4E8B-89CD-C53E24DB42AE/w1000/h732',
        3,
        1,
        0,
        'COM-ASUS-001',
        5,
        680000,
        19.00
    ),
    (
        'Corsair Vengeance DDR5 32GB (2x16GB)',
        'Kit de memoria RAM DDR5 5600MHz, CL36, Intel XMP 3.0, disipador de aluminio negro, optimizada para gaming y productividad.',
        449900,
        25,
        'https://www.corsair.com/medias/sys_master/images/images/h1e/h39/67388309913630/CMK32GX5M2B5600C36.png',
        3,
        6,
        1,
        'COM-COR-001',
        5,
        320000,
        19.00
    ),
    (
        'Kingston NV2 SSD 1TB NVMe',
        'SSD M.2 2280 NVMe PCIe 4.0 x4, lectura hasta 3500 MB/s, escritura hasta 2100 MB/s, compacto y eficiente para upgrades.',
        219900,
        30,
        'https://media.kingston.com/kingston/product/ktc-product-ssd-snv2s-702x702.png',
        3,
        8,
        0,
        'COM-KNG-001',
        8,
        155000,
        19.00
    ),
    (
        'MSI GeForce RTX 4070 Ventus 2X',
        'Tarjeta gráfica NVIDIA RTX 4070 12GB GDDR6X, DLSS 3.0, Ray Tracing, doble ventilador Torx 4.0, reloj boost 2475 MHz, DisplayPort 1.4a x3, HDMI 2.1.',
        2899900,
        6,
        'https://asset.msi.com/resize/image/global/product/product_16798373902c3da5ef8f6be78f3e93c85c3edb5cc5.png62405b38c58fe0f07fcef2367d8a9ba1/1024.png',
        3,
        5,
        1,
        'COM-MSI-001',
        2,
        2200000,
        19.00
    ),
    (
        'Corsair RM850x Fuente 850W 80+ Gold',
        'Fuente de poder ATX 850W, certificación 80+ Gold, completamente modular, ventilador 135mm Zero RPM, condensadores japoneses, garantía 10 años.',
        549900,
        11,
        'https://www.corsair.com/medias/sys_master/images/images/hc3/h72/67302988029982/CP-9020200-NA.png',
        3,
        6,
        0,
        'COM-COR-002',
        3,
        400000,
        19.00
    );

-- ══════════════════════════════════════
-- PRODUCTOS: Accesorios (categoría 4)
-- ══════════════════════════════════════
INSERT INTO
    productos (
        nombre,
        descripcion,
        precio,
        stock,
        imagen,
        id_categoria,
        id_marca,
        oferta,
        sku,
        stock_minimo,
        costo_unitario,
        iva_porcentaje
    )
VALUES (
        'Logitech G Pro X Superlight 2',
        'Mouse gaming inalámbrico, sensor HERO 2, 32K DPI, 60g ultraligero, LIGHTSPEED wireless, batería 95 horas, switches óptico-mecánicos LIGHTFORCE.',
        599900,
        18,
        'https://resource.logitechg.com/w_1000,c_limit,q_auto,f_auto,dpr_1.0/d_transparent.gif/content/dam/gaming/en/products/pro-x2-702x702-702x702-702x702.png',
        4,
        7,
        1,
        'ACC-LOG-001',
        5,
        430000,
        19.00
    ),
    (
        'Corsair K70 RGB PRO',
        'Teclado mecánico gaming, switches Cherry MX Red, marco de aluminio, iluminación RGB individual por tecla, reposamuñecas magnético, USB passthrough.',
        649900,
        10,
        'https://www.corsair.com/medias/sys_master/images/images/hd0/h05/67192397578270/CH-9109410-NA.png',
        4,
        6,
        0,
        'ACC-COR-001',
        3,
        480000,
        19.00
    ),
    (
        'Logitech G733 Lightspeed',
        'Audífonos gaming inalámbricos, DTS Headphone:X 2.0, transductores PRO-G 40mm, micrófono Blue VO!CE, batería 29 horas, peso 278g, diadema reversible.',
        449900,
        13,
        'https://resource.logitechg.com/w_1000,c_limit,q_auto,f_auto,dpr_1.0/d_transparent.gif/content/dam/gaming/en/products/g733/g733-702x702-702x702.png',
        4,
        7,
        0,
        'ACC-LOG-002',
        4,
        320000,
        19.00
    ),
    (
        'ASUS VG249Q1A Monitor 24" Gaming',
        'Monitor gaming 23.8" Full HD IPS, 165Hz, 1ms MPRT, AMD FreeSync Premium, Eye Care, HDMI x2, DisplayPort, altavoces integrados.',
        849900,
        7,
        'https://dlcdnwebimgs.asus.com/gain/BD15BDB5-E1BD-4CA6-BB0F-C7E9D6ABD3FD/w1000/h732',
        4,
        1,
        1,
        'ACC-ASUS-001',
        2,
        620000,
        19.00
    ),
    (
        'Kingston DataTraveler Max USB-C 256GB',
        'Memoria USB-C 3.2 Gen 2, velocidad lectura hasta 1000 MB/s, escritura hasta 900 MB/s, diseño con anilla acoplable, compatible USB-C.',
        149900,
        35,
        'https://media.kingston.com/kingston/product/ktc-product-flash-702x702.png',
        4,
        8,
        0,
        'ACC-KNG-001',
        10,
        95000,
        19.00
    );