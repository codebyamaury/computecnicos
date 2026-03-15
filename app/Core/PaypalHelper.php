<?php
class PaypalHelper
{
    private $pdo;
    private $config;
    private $baseUrl;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->config = require __DIR__ . '/../config/paypal_config.php';
        $this->baseUrl = ($this->config['environment'] ?? 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function getAccessToken()
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/v1/oauth2/token',
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: en_US'],
            CURLOPT_USERPWD => ($this->config['client_id'] ?? '') . ':' . ($this->config['client_secret'] ?? ''),
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new Exception('Error obteniendo token PayPal: ' . curl_error($ch));
        }
        $data = json_decode($resp, true);
        curl_close($ch);
        if (!isset($data['access_token'])) {
            throw new Exception('Respuesta inválida obteniendo token PayPal');
        }
        return $data['access_token'];
    }

    private function buildDescription($pedido_id)
    {
        $stmt = $this->pdo->prepare('SELECT d.cantidad, p.nombre FROM detalle_pedido d INNER JOIN productos p ON p.id = d.id_producto WHERE d.id_pedido = ?');
        $stmt->execute([$pedido_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$items) {
            return 'Pedido #' . $pedido_id;
        }
        $parts = [];
        foreach ($items as $it) {
            $parts[] = $it['nombre'] . ' x' . $it['cantidad'];
        }
        $desc = 'Pedido #' . $pedido_id . ' - ' . implode(', ', $parts);
        return mb_substr($desc, 0, 127);
    }

    public function createOrder($pedido_id)
    {
        $stmt = $this->pdo->prepare('SELECT total FROM pedidos WHERE id = ?');
        $stmt->execute([$pedido_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('Pedido no encontrado');
        }
        $total_cop = (float) $row['total'];
        $currency = $this->config['currency'] ?? 'USD';
        if ($currency === 'USD') {
            $rate = (float) ($this->config['exchange_rate_cop_to_usd'] ?? 0.00025);
            $converted = $total_cop * $rate;
            // PayPal requiere un mínimo de 0.01 en currency de 2 decimales
            $amount_value = number_format(max($converted, 0.01), 2, '.', '');
        } else {
            // Para monedas distintas a USD asumimos que el total ya está en la moneda destino
            $amount_value = number_format(max($total_cop, 0.01), 2, '.', '');
        }

        $description = $this->buildDescription($pedido_id);
        $token = $this->getAccessToken();

        $payload = json_encode([
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $amount_value
                    ],
                    'description' => $description
                ]
            ]
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/v2/checkout/orders',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new Exception('Error creando orden PayPal: ' . curl_error($ch));
        }
        curl_close($ch);
        $data = json_decode($resp, true);
        if (!isset($data['id'])) {
            throw new Exception('Respuesta inválida creando orden PayPal');
        }
        return $data;
    }

    public function captureOrder($orderId, $pedido_id)
    {
        $token = $this->getAccessToken();
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/v2/checkout/orders/' . urlencode($orderId) . '/capture',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new Exception('Error capturando orden PayPal: ' . curl_error($ch));
        }
        curl_close($ch);
        $data = json_decode($resp, true);
        if (isset($data['status']) && $data['status'] === 'COMPLETED') {
            $this->updatePedidoEstado($pedido_id, 'pagado');
        }
        return $data;
    }

    private function updatePedidoEstado($pedido_id, $estado)
    {
        $stmt = $this->pdo->prepare('UPDATE pedidos SET estado = ? WHERE id = ?');
        $stmt->execute([$estado, $pedido_id]);
        // Registrar historial de cambio vía PayPal
        $stmt2 = $this->pdo->prepare('INSERT INTO pedido_estados (id_pedido, estado, comentario) VALUES (?, ?, ?)');
        $stmt2->execute([$pedido_id, $estado, 'Actualización automática por PayPal']);

        // Reducir stock al confirmar el pago — el stock solo se gasta al comprar
        if ($estado === 'pagado') {
            $stmtDet = $this->pdo->prepare('SELECT id_producto, cantidad FROM detalle_pedido WHERE id_pedido = ?');
            $stmtDet->execute([$pedido_id]);
            $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
            $stmtUpd = $this->pdo->prepare('UPDATE productos SET stock = GREATEST(0, stock - ?) WHERE id = ?');
            $stmtMov = $this->pdo->prepare("INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, motivo, id_usuario) VALUES (?, 'salida', ?, ?, ?)");
            foreach ($detalles as $d) {
                $stmtUpd->execute([$d['cantidad'], $d['id_producto']]);
                // id_usuario = 0 o nulo si no tenemos la sesion aquí conectada. O podemos coger el id_usuario del pedido.
                $stmtUser = $this->pdo->prepare('SELECT id_usuario FROM pedidos WHERE id = ?');
                $stmtUser->execute([$pedido_id]);
                $cliente_id = $stmtUser->fetchColumn();
                $stmtMov->execute([$d['id_producto'], $d['cantidad'], 'Pago PayPal Pedido #' . $pedido_id, $cliente_id]);
            }
        }
    }
}