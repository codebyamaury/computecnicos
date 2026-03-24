<?php
// Sesión manejada por bootstrap (DB handler)
require_once __DIR__ . '/../app/Core/bootstrap.php';

require_once __DIR__ . '/../vendor/autoload.php'; // dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

try {
    if (!isset($_SESSION['usuario'])) {
        http_response_code(401);
        throw new Exception('Debes iniciar sesión para descargar tu factura.');
    }

    if (!isset($_GET['id'])) {
        http_response_code(400);
        throw new Exception('ID de pedido no especificado.');
    }
    $id_pedido = intval($_GET['id']);
    $forceDownload = isset($_GET['download']) && (($_GET['download'] === '1') || (strtolower($_GET['download']) === 'true'));

    // Verificar que el pedido pertenece al usuario (o que sea admin)
    $user_id = $_SESSION['usuario']['id'];
    $user_role = $_SESSION['usuario']['rol'] ?? 'user';

    $stmt = $pdo->prepare('SELECT p.*, u.nombre AS cliente, u.email, p.direccion_envio, p.fecha FROM pedidos p LEFT JOIN usuarios u ON p.id_usuario = u.id WHERE p.id = ?');
    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch();
    if (!$pedido) {
        http_response_code(404);
        throw new Exception('Pedido no encontrado.');
    }

    if ($pedido['id_usuario'] != $user_id && $user_role !== 'admin') {
        http_response_code(403);
        throw new Exception('No tienes permiso para descargar esta factura.');
    }

    // La factura solo está disponible desde estado pagado en adelante
    $estados_permitidos = ['pagado','preparacion','enviado','entregado'];
    if (!in_array($pedido['estado'], $estados_permitidos, true)) {
        http_response_code(409);
        throw new Exception('La factura estará disponible una vez se confirme el pago.');
    }

    // Obtener detalles del pedido
    $stmt = $pdo->prepare('SELECT d.*, pr.nombre FROM detalle_pedido d JOIN productos pr ON d.id_producto = pr.id WHERE d.id_pedido = ?');
    $stmt->execute([$id_pedido]);
    $detalles = $stmt->fetchAll();

    // Datos de la tienda (personalizables)
    $tienda_nombre = "COMPUTÉCNICOS";
    $tienda_direccion = "Paseo Bolívar Cra 17 #45-20, Cartagena";
    $tienda_nit = "NIT 900000000-1";
    $tienda_tel = "Tel: 316 850 0131";

    // Calcular totales con casting seguro
    $subtotal = 0; $total_descuentos = 0;
    foreach ($detalles as $d) {
        $item_subtotal = ((float)$d['cantidad']) * ((float)$d['precio_unitario']);
        $desc = is_numeric($d['descuento'] ?? null) ? (float)$d['descuento'] : 0.0;
        $item_descuento = $item_subtotal * ($desc / 100);
        $subtotal += $item_subtotal;
        $total_descuentos += $item_descuento;
    }
    $iva = ($subtotal - $total_descuentos) * 0.19;
    $total = ($subtotal - $total_descuentos) + $iva;

    // HTML tipo ticket
    $html = '<style>
    body { font-family: monospace; font-size: 18px; color: #222; margin: 0; padding: 0; }
    .ticket-box { width: 380px; border: 2px dashed #aaa; padding: 32px 24px; margin: 80px auto 60px auto; background: #fff; }
    hr { border: none; border-top: 2px dashed #aaa; margin: 16px 0; }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .small { font-size: 14px; }
    .table-ticket { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 18px; }
    .table-ticket th, .table-ticket td { padding: 2px 4px; }
    </style>';
    $html .= '<div class="ticket-box center">
        <div class="bold" style="font-size:28px;">' . $tienda_nombre . '</div>
        <div class="small">' . $tienda_direccion . '</div>
        <div class="small">' . $tienda_nit . '</div>
        <div class="small">' . $tienda_tel . '</div>
        <div>----------------------</div>
        <div style="margin-bottom:8px;">Factura N°: <span class="bold">' . $pedido['id'] . '</span></div>
        <div style="margin-bottom:8px;">Fecha: ' . date('d/m/Y H:i', strtotime($pedido['fecha'])) . '</div>
        <div style="margin-bottom:8px;">Cliente: ' . htmlspecialchars($pedido['cliente']) . '</div>
        <div class="small" style="margin-bottom:8px;">Dir: ' . htmlspecialchars($pedido['direccion_envio']) . '</div>
        <div>----------------------</div>
        <div class="bold" style="font-size:20px;">DETALLE</div>';
    $html .= '<table class="table-ticket center">
    <thead><tr><th class="center">Producto</th><th class="center">Cant</th><th class="center">Vlr</th></tr></thead><tbody>';
    foreach ($detalles as $d) {
        $item_subtotal = ((float)$d['cantidad']) * ((float)$d['precio_unitario']);
        $desc = is_numeric($d['descuento'] ?? null) ? (float)$d['descuento'] : 0.0;
        $item_descuento = $item_subtotal * ($desc / 100);
        $item_total = $item_subtotal - $item_descuento;
        $html .= '<tr>
            <td class="center">' . htmlspecialchars($d['nombre']) . '</td>
            <td class="center">' . (int)$d['cantidad'] . '</td>
            <td class="center">' . number_format($item_total, 0, ',', '.') . '</td>
        </tr>';
    }
    $html .= '</tbody></table>';
    $html .= '<div>----------------------</div>';
    $html .= '<div style="font-size:18px;" class="center">Subtotal: $' . number_format($subtotal, 0, ',', '.') . '</div>';
    $html .= '<div style="font-size:18px;" class="center">Descuentos: $' . number_format($total_descuentos, 0, ',', '.') . '</div>';
    $html .= '<div style="font-size:18px;" class="center">IVA (19%): $' . number_format($iva, 0, ',', '.') . '</div>';
    $html .= '<div class="bold center" style="font-size:22px;">TOTAL: $' . number_format($total, 0, ',', '.') . '</div>';
    $html .= '<div>----------------------</div>';
    $html .= '<div class="small" style="font-size:16px;">Gracias por su compra</div>';
    $html .= '<div class="small" style="font-size:16px;">computecnicos.duckdns.org</div>';
    $html .= '<div>----------------------</div>';
    $html .= '</div>';

    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('factura_' . $pedido['id'] . '.pdf', ['Attachment' => $forceDownload]);
    exit;
} catch (Throwable $e) {
    log_event('Error factura_pdf: ' . $e->getMessage());
    $_SESSION['admin_toast'] = [
        'msg' => $e->getMessage(),
        'type' => 'error',
        'title' => 'Factura no disponible'
    ];
    $back = $_SERVER['HTTP_REFERER'] ?? 'pedidos.php';
    header('Location: ' . $back);
    exit;
}