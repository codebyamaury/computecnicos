<?php
class PaypalHelper
{
    private $pdo;
    private $config;
    private $baseUrl;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->config = require __DIR__ . '/../../config/paypal_config.php';
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
            $stmtDet = $this->pdo->prepare('SELECT id_producto, cantidad, precio_unitario FROM detalle_pedido WHERE id_pedido = ?');
            $stmtDet->execute([$pedido_id]);
            $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
            $stmtUpd = $this->pdo->prepare('UPDATE productos SET stock = GREATEST(0, stock - ?) WHERE id = ?');
            $stmtMov = $this->pdo->prepare("INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, precio_unitario, motivo, id_usuario) VALUES (?, 'salida', ?, ?, ?, ?)");
            foreach ($detalles as $d) {
                $stmtUpd->execute([$d['cantidad'], $d['id_producto']]);
                // id_usuario = 0 o nulo si no tenemos la sesion aquí conectada. O podemos coger el id_usuario del pedido.
                $stmtUser = $this->pdo->prepare('SELECT id_usuario FROM pedidos WHERE id = ?');
                $stmtUser->execute([$pedido_id]);
                $cliente_id = $stmtUser->fetchColumn();
                $stmtMov->execute([$d['id_producto'], $d['cantidad'], $d['precio_unitario'], 'Pago PayPal Pedido #' . $pedido_id, $cliente_id]);
            }
        }
    }

    /**
     * Get order details from PayPal to extract capture ID
     */
    public function getOrderDetails($orderId)
    {
        $token = $this->getAccessToken();
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/v2/checkout/orders/' . urlencode($orderId),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
            CURLOPT_RETURNTRANSFER => true
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new Exception('Error obteniendo detalles de orden PayPal: ' . curl_error($ch));
        }
        curl_close($ch);
        return json_decode($resp, true);
    }

    /**
     * Refund a captured payment
     * @param string $captureId The PayPal capture ID
     * @param float|null $amount Amount to refund (null = full refund)
     * @param string $currency Currency code
     * @param string $note Note for the refund
     * @return array PayPal response
     */
    public function refundCapture($captureId, $amount = null, $currency = 'USD', $note = '')
    {
        $token = $this->getAccessToken();

        $payload = [];
        if ($note) {
            $payload['note_to_payer'] = mb_substr($note, 0, 255);
        }
        if ($amount !== null) {
            $payload['amount'] = [
                'value' => number_format($amount, 2, '.', ''),
                'currency_code' => $currency
            ];
        }

        $ch = curl_init();
        $opts = [
            CURLOPT_URL => $this->baseUrl . '/v2/payments/captures/' . urlencode($captureId) . '/refund',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token
            ],
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true
        ];

        if (!empty($payload)) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        }

        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($resp === false) {
            throw new Exception('Error realizando reembolso PayPal: ' . curl_error($ch));
        }
        curl_close($ch);

        $data = json_decode($resp, true);
        if ($httpCode >= 400) {
            $errorMsg = $data['message'] ?? ($data['details'][0]['description'] ?? 'Error desconocido de PayPal');
            throw new Exception('PayPal Refund Error: ' . $errorMsg);
        }

        return $data;
    }

    /**
     * Process a full refund for an order:
     * 1. Find the capture ID from PayPal order
     * 2. Execute the refund
     * 3. Update order status
     * 4. Restore stock
     */
    public function processFullRefund($pedido_id, $reembolso_id, $admin_id)
    {
        // Get order total and convert to USD
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
            $amount = round($total_cop * $rate, 2);
        } else {
            $amount = $total_cop;
        }

        // Get the capture ID from the reembolso record
        $stmtR = $this->pdo->prepare('SELECT paypal_capture_id FROM reembolsos WHERE id = ?');
        $stmtR->execute([$reembolso_id]);
        $captureId = $stmtR->fetchColumn();

        if (!$captureId) {
            throw new Exception('No se encontró el ID de captura de PayPal para este reembolso');
        }

        // Execute refund via PayPal
        $refundResult = $this->refundCapture($captureId, $amount, $currency, 'Reembolso Pedido #' . $pedido_id);

        $refundId = $refundResult['id'] ?? null;
        $refundStatus = $refundResult['status'] ?? 'UNKNOWN';

        if ($refundStatus === 'COMPLETED' || $refundStatus === 'PENDING') {
            $this->pdo->beginTransaction();
            try {
                // Update reembolso record
                $this->pdo->prepare('UPDATE reembolsos SET estado = ?, paypal_refund_id = ?, fecha_resolucion = NOW(), id_admin_resolucion = ? WHERE id = ?')
                    ->execute(['procesado', $refundId, $admin_id, $reembolso_id]);

                // Update order status to cancelado
                $this->pdo->prepare('UPDATE pedidos SET estado = ? WHERE id = ?')
                    ->execute(['cancelado', $pedido_id]);

                // Add to order history
                $this->pdo->prepare('INSERT INTO pedido_estados (id_pedido, estado, comentario) VALUES (?, ?, ?)')
                    ->execute([$pedido_id, 'cancelado', 'Reembolso procesado vía PayPal (Ref: ' . ($refundId ?? 'N/A') . ')']);

                // Restore stock
                $stmtDet = $this->pdo->prepare('SELECT id_producto, cantidad, precio_unitario FROM detalle_pedido WHERE id_pedido = ?');
                $stmtDet->execute([$pedido_id]);
                $detalles = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

                foreach ($detalles as $d) {
                    $this->pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?')
                        ->execute([$d['cantidad'], $d['id_producto']]);
                    $this->pdo->prepare("INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, precio_unitario, motivo, id_usuario) VALUES (?, 'entrada', ?, ?, ?, ?)")
                        ->execute([$d['id_producto'], $d['cantidad'], $d['precio_unitario'], 'Reembolso Pedido #' . $pedido_id, $admin_id]);
                }

                // Mark stock as restored
                $this->pdo->prepare('UPDATE reembolsos SET stock_devuelto = 1 WHERE id = ?')
                    ->execute([$reembolso_id]);

                $this->pdo->commit();
            } catch (Exception $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        }

        return [
            'refund_id' => $refundId,
            'status' => $refundStatus,
            'result' => $refundResult
        ];
    }
}