<?php
/**
 * API: Validar y aplicar cupón de descuento
 * POST: { codigo: 'CUPON123', total: 5000000 }
 * Response: { ok: true/false, descuento: 500000, tipo: 'porcentaje', valor: 10, msg: '...' }
 */
require_once __DIR__ . '/../app/Core/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Inicia sesión para usar cupones']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$codigo = strtoupper(trim($input['codigo'] ?? ''));
$total = floatval($input['total'] ?? 0);

if (!$codigo) {
    echo json_encode(['ok' => false, 'msg' => 'Ingresa un código de cupón']);
    exit;
}

// Buscar cupón
$stmt = $pdo->prepare('SELECT * FROM cupones WHERE codigo = ?');
$stmt->execute([$codigo]);
$cupon = $stmt->fetch();

if (!$cupon) {
    echo json_encode(['ok' => false, 'msg' => 'El cupón no existe']);
    exit;
}

// Verificar activo
if (!$cupon['activo']) {
    echo json_encode(['ok' => false, 'msg' => 'Este cupón no está activo']);
    exit;
}

// Verificar expiración
if (!empty($cupon['fecha_expiracion']) && strtotime($cupon['fecha_expiracion']) < strtotime('today')) {
    echo json_encode(['ok' => false, 'msg' => 'Este cupón ha expirado']);
    exit;
}

// Verificar límite de usos
if ($cupon['limite_usos'] > 0 && $cupon['usos_actuales'] >= $cupon['limite_usos']) {
    echo json_encode(['ok' => false, 'msg' => 'Este cupón ha alcanzado su límite de usos']);
    exit;
}

// Verificar monto mínimo
if ($cupon['monto_minimo'] > 0 && $total < $cupon['monto_minimo']) {
    echo json_encode([
        'ok' => false, 
        'msg' => 'El monto mínimo para usar este cupón es $' . number_format($cupon['monto_minimo'], 0, ',', '.') . ' COP'
    ]);
    exit;
}

// Calcular descuento
$descuento = 0;
if ($cupon['tipo_descuento'] === 'porcentaje') {
    $descuento = round($total * ($cupon['valor'] / 100));
} else {
    $descuento = round($cupon['valor']);
}

// No permitir descuento mayor al total
if ($descuento > $total) {
    $descuento = $total;
}

// Guardar cupón en sesión para aplicar al hacer el pedido
$_SESSION['cupon_aplicado'] = [
    'id' => $cupon['id'],
    'codigo' => $cupon['codigo'],
    'tipo_descuento' => $cupon['tipo_descuento'],
    'valor' => $cupon['valor'],
    'descuento_calculado' => $descuento
];

echo json_encode([
    'ok' => true,
    'descuento' => $descuento,
    'tipo' => $cupon['tipo_descuento'],
    'valor' => $cupon['valor'],
    'codigo' => $cupon['codigo'],
    'msg' => '¡Cupón aplicado! Descuento de $' . number_format($descuento, 0, ',', '.') . ' COP'
]);
