<?php
require_once '../vendor/autoload.php'; // dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['id'])) {
    die('ID de pedido no especificado.');
}
$id_pedido = intval($_GET['id']);

require_once __DIR__ . '/../app/Core/bootstrap.php';

// Datos de la tienda (puedes personalizar estos datos)
$tienda_nombre = "COMPUTÉCNICOS";
$tienda_direccion = "Paseo Bolívar Cra 17 #45-20, Cartagena";
$tienda_nit = "NIT 900000000-1";
$tienda_tel = "Tel: 316 850 0131";

// Obtener datos del pedido
$stmt = $pdo->prepare('SELECT p.*, u.nombre AS cliente, u.email, p.direccion_envio, p.fecha FROM pedidos p LEFT JOIN usuarios u ON p.id_usuario = u.id WHERE p.id = ?');
$stmt->execute([$id_pedido]);
$pedido = $stmt->fetch();
if (!$pedido) die('Pedido no encontrado.');

// Obtener detalles del pedido
$stmt = $pdo->prepare('SELECT d.*, pr.nombre FROM detalle_pedido d JOIN productos pr ON d.id_producto = pr.id WHERE d.id_pedido = ?');
$stmt->execute([$id_pedido]);
$detalles = $stmt->fetchAll();

// Calcular totales
$subtotal = 0; $total_descuentos = 0;
foreach ($detalles as $d) {
    $item_subtotal = $d['cantidad'] * $d['precio_unitario'];
    $item_descuento = $item_subtotal * ($d['descuento'] / 100);
    $subtotal += $item_subtotal;
    $total_descuentos += $item_descuento;
}
$iva = ($subtotal - $total_descuentos) * 0.19;
$total = ($subtotal - $total_descuentos) + $iva;

// HTML tipo ticket centrado horizontalmente, sin flexbox ni min-height
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
    $item_subtotal = $d['cantidad'] * $d['precio_unitario'];
    $item_descuento = $item_subtotal * ($d['descuento'] / 100);
    $item_total = $item_subtotal - $item_descuento;
    $html .= '<tr>
        <td class="center">' . htmlspecialchars($d['nombre']) . '</td>
        <td class="center">' . $d['cantidad'] . '</td>
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
$html .= '<div class="small" style="font-size:16px;">www.computecnicos.com</div>';
$html .= '<div>----------------------</div>';
$html .= '</div>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream('ticket_' . $pedido['id'] . '.pdf', ['Attachment' => false]);
exit;