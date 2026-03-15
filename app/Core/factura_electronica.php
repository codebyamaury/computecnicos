<?php
// Funciones para facturación electrónica automática
// Crea la tabla de facturas, arma el payload y registra la factura con proveedor (o simulado)

function fe_config() {
    $cfg_path = __DIR__ . '/../../config/factura_config.php';
    if (file_exists($cfg_path)) {
        return include $cfg_path;
    }
    return [ 'provider' => 'alegra', 'simulate' => true, 'alegra' => ['token' => '', 'email' => ''], 'siigo' => ['client_id'=>'','client_secret'=>'','username'=>'','password'=>''] ];
}

function fe_crear_tabla($pdo) {
    $sql = 'CREATE TABLE IF NOT EXISTS facturas_electronicas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_pedido INT NOT NULL,
        provider VARCHAR(32) NOT NULL,
        external_id VARCHAR(128) NULL,
        estado VARCHAR(32) NOT NULL,
        numero VARCHAR(64) NULL,
        pdf_url VARCHAR(255) NULL,
        xml_url VARCHAR(255) NULL,
        total DECIMAL(12,2) NULL,
        error_msg TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        UNIQUE KEY uniq_factura_pedido (id_pedido)
    )';
    $pdo->exec($sql);
}

function fe_crear_tabla_nc($pdo) {
    $sql = 'CREATE TABLE IF NOT EXISTS notas_credito (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_pedido INT NOT NULL,
        provider VARCHAR(32) NOT NULL,
        external_id VARCHAR(128) NULL,
        estado VARCHAR(32) NOT NULL,
        numero VARCHAR(64) NULL,
        total DECIMAL(12,2) NULL,
        motivo VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    )';
    $pdo->exec($sql);
}

function fe_obtener_factura_por_pedido($pdo, $id_pedido) {
    $stmt = $pdo->prepare('SELECT * FROM facturas_electronicas WHERE id_pedido = ?');
    $stmt->execute([$id_pedido]);
    return $stmt->fetch();
}

function fe_crear_factura_pedido($pdo, $id_pedido) {
    fe_crear_tabla($pdo);
    $cfg = fe_config();
    // Si ya existe factura y no está en error, no crear de nuevo
    $ex = fe_obtener_factura_por_pedido($pdo, $id_pedido);
    if ($ex && $ex['estado'] !== 'error') {
        return ['ok' => true, 'msg' => 'Factura ya registrada', 'data' => $ex];
    }

    // Cargar pedido, usuario y detalles
    $stmtP = $pdo->prepare('SELECT p.*, u.nombre AS cliente_nombre, u.email AS cliente_email FROM pedidos p LEFT JOIN usuarios u ON p.id_usuario = u.id WHERE p.id = ?');
    $stmtP->execute([$id_pedido]);
    $pedido = $stmtP->fetch();
    if (!$pedido) {
        throw new Exception('Pedido no encontrado para facturación');
    }
    $stmtD = $pdo->prepare('SELECT d.*, pr.nombre AS producto_nombre, pr.sku, pr.costo_unitario, pr.iva_porcentaje FROM detalle_pedido d LEFT JOIN productos pr ON pr.id = d.id_producto WHERE d.id_pedido = ?');
    $stmtD->execute([$id_pedido]);
    $detalles = $stmtD->fetchAll();
    if (!$detalles) {
        throw new Exception('El pedido no tiene detalles');
    }

    // Armar payload genérico
    $items = [];
    foreach ($detalles as $d) {
        $items[] = [
            'sku' => $d['sku'] ?? null,
            'name' => $d['producto_nombre'] ?? ('Producto ' . $d['id_producto']),
            'quantity' => (int)$d['cantidad'],
            'price' => (float)$d['precio_unitario'],
            'discount' => isset($d['descuento']) ? (float)$d['descuento'] : 0.0,
            'cost' => isset($d['costo_unitario']) ? (float)$d['costo_unitario'] : null,
            'tax' => isset($d['iva_porcentaje']) && $d['iva_porcentaje'] !== null ? (float)$d['iva_porcentaje'] / 100.0 : 0.19
        ];
    }
    $payload = [
        'customer' => [
            'name' => $pedido['cliente_nombre'] ?? 'Cliente',
            'email' => $pedido['cliente_email'] ?? null,
            'address' => $pedido['direccion_envio'] ?? null,
        ],
        'order' => [
            'id' => (int)$id_pedido,
            'total' => (float)$pedido['total'],
            'date' => $pedido['fecha'] ?? date('Y-m-d H:i:s'),
        ],
        'items' => $items,
    ];

    $provider = $cfg['provider'];
    $simulate = !empty($cfg['simulate']);
    $numero = null; $external_id = null; $pdf_url = null; $xml_url = null; $estado = 'enviado';

    if ($simulate) {
        // Registrar factura simulada
        $numero = 'SIM-' . $id_pedido . '-' . time();
        $estado = 'simulado';
    } else {
        if ($provider === 'alegra') {
            $token = $cfg['alegra']['token'] ?? '';
            if (!$token) throw new Exception('Token de Alegra no configurado');
            $ch = curl_init('https://api.alegra.com/api/v1/invoices');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($resp === false || $http >= 400) {
                throw new Exception('Error API Alegra: HTTP ' . $http . ' ' . curl_error($ch));
            }
            $data = json_decode($resp, true);
            $external_id = $data['id'] ?? null;
            $numero = $data['number'] ?? null;
            $pdf_url = $data['pdf'] ?? null;
            $xml_url = $data['xml'] ?? null;
        } elseif ($provider === 'siigo') {
            // Placeholder básico. Siigo requiere OAuth; se asume token ya obtenido.
            $token = getenv('SIIGO_ACCESS_TOKEN') ?: '';
            if (!$token) throw new Exception('Token de acceso de Siigo no configurado');
            $ch = curl_init('https://api.siigo.com/v1/invoices');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($resp === false || $http >= 400) {
                throw new Exception('Error API Siigo: HTTP ' . $http . ' ' . curl_error($ch));
            }
            $data = json_decode($resp, true);
            $external_id = $data['id'] ?? null;
            $numero = $data['number'] ?? null;
            $pdf_url = $data['pdf'] ?? null;
            $xml_url = $data['xml'] ?? null;
        } else {
            throw new Exception('Proveedor de facturación no soportado: ' . $provider);
        }
    }

    // Insertar/actualizar registro local
    if ($ex) {
        $stmtU = $pdo->prepare('UPDATE facturas_electronicas SET provider=?, external_id=?, estado=?, numero=?, pdf_url=?, xml_url=?, total=?, error_msg=NULL, updated_at=NOW() WHERE id_pedido=?');
        $stmtU->execute([$provider, $external_id, $estado, $numero, $pdf_url, $xml_url, $pedido['total'], $id_pedido]);
    } else {
        $stmtI = $pdo->prepare('INSERT INTO facturas_electronicas (id_pedido, provider, external_id, estado, numero, pdf_url, xml_url, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmtI->execute([$id_pedido, $provider, $external_id, $estado, $numero, $pdf_url, $xml_url, $pedido['total']]);
    }
    return ['ok' => true, 'msg' => $simulate ? 'Factura simulada registrada' : 'Factura creada', 'data' => [
        'provider' => $provider, 'external_id' => $external_id, 'numero' => $numero, 'pdf_url' => $pdf_url, 'xml_url' => $xml_url, 'estado' => $estado
    ]];
}

function fe_crear_nota_credito_pedido($pdo, $id_pedido, $motivo = null) {
    fe_crear_tabla($pdo);
    fe_crear_tabla_nc($pdo);
    $cfg = fe_config();
    $provider = $cfg['provider'];
    $simulate = !empty($cfg['simulate']);
    // Obtener datos de pedido/factura
    $fact = fe_obtener_factura_por_pedido($pdo, $id_pedido);
    $stmtP = $pdo->prepare('SELECT * FROM pedidos WHERE id = ?');
    $stmtP->execute([$id_pedido]);
    $pedido = $stmtP->fetch();
    if (!$pedido) throw new Exception('Pedido no encontrado para nota crédito');

    $numero = null; $external_id = null; $estado = 'enviado';
    if ($simulate) {
        $numero = 'NC-SIM-' . $id_pedido . '-' . time();
        $estado = 'simulado';
    } else {
        // Placeholder: integración real con proveedor
        // Para ahora, marcamos como simulado si no hay integración configurada
        $numero = 'NC-' . $id_pedido . '-' . time();
        $estado = 'registrado';
    }
    $total = $fact['total'] ?? $pedido['total'] ?? null;
    $stmtI = $pdo->prepare('INSERT INTO notas_credito (id_pedido, provider, external_id, estado, numero, total, motivo) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmtI->execute([$id_pedido, $provider, $external_id, $estado, $numero, $total, $motivo]);
    // Marcar factura como anulada si existía
    if ($fact) {
        $pdo->prepare('UPDATE facturas_electronicas SET estado="anulado", updated_at=NOW() WHERE id_pedido = ?')->execute([$id_pedido]);
    }
    return ['ok' => true, 'msg' => 'Nota crédito registrada', 'data' => ['numero' => $numero, 'estado' => $estado]];
}

?>