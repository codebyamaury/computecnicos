<?php
session_start();
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}
require_once __DIR__ . '/../app/Core/bootstrap.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: pedidos.php');
    exit;
}
// Obtener pedido
$stmt = $pdo->prepare('SELECT * FROM pedidos WHERE id = ?');
$stmt->execute([$id]);
$pedido = $stmt->fetch();
if (!$pedido) {
    header('Location: pedidos.php');
    exit;
}
// Obtener detalles
$stmt = $pdo->prepare('SELECT * FROM detalle_pedido WHERE id_pedido = ?');
$stmt->execute([$id]);
$detalles = $stmt->fetchAll();
// Obtener usuarios y productos
$usuarios = $pdo->query('SELECT id, nombre, email FROM usuarios ORDER BY nombre')->fetchAll();
$productos = $pdo->query('SELECT id, nombre, precio, stock FROM productos ORDER BY nombre')->fetchAll();

$errores = [];
// Helper para verificar si existe una columna en la tabla
function columnaExiste(PDO $pdo, $tabla, $columna) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$tabla, $columna]);
    return (int)$stmt->fetchColumn() > 0;
}
$tiene_numero_guia = columnaExiste($pdo, 'pedidos', 'numero_guia');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = intval($_POST['id_usuario'] ?? 0);
    $direccion_envio = trim($_POST['direccion_envio'] ?? '');
    $estado = $_POST['estado'] ?? 'pendiente';
    $numero_guia = $tiene_numero_guia ? trim($_POST['numero_guia'] ?? '') : '';
    $productos_sel = $_POST['productos'] ?? [];
    $cantidades = $_POST['cantidades'] ?? [];
    $precios = $_POST['precios'] ?? [];
    $total = 0;
    $detalles_nuevos = [];
    foreach ($productos_sel as $i => $id_prod) {
        $id_prod = intval($id_prod);
        $cantidad = intval($cantidades[$i] ?? 0);
        $precio = floatval($precios[$i] ?? 0);
        if ($id_prod > 0 && $cantidad > 0 && $precio > 0) {
            $detalles_nuevos[] = [
                'id_producto' => $id_prod,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio
            ];
            $total += $cantidad * $precio;
        }
    }
    if ($id_usuario <= 0) $errores[] = 'Selecciona un cliente.';
    if ($direccion_envio === '') $errores[] = 'La dirección de envío es obligatoria.';
    if (empty($detalles_nuevos)) $errores[] = 'Agrega al menos un producto.';
    // Validar stock suficiente para cada producto si el estado es pagado/entregado
    if (empty($errores) && in_array($estado, ['pagado','entregado'])) {
        foreach ($detalles_nuevos as $d) {
            $stmt = $pdo->prepare('SELECT stock FROM productos WHERE id = ?');
            $stmt->execute([$d['id_producto']]);
            $stock_actual = $stmt->fetchColumn();
            // Si el pedido ya era pagado/entregado, sumar la cantidad anterior para ese producto (se va a restar después)
            $stmt = $pdo->prepare('SELECT cantidad FROM detalle_pedido WHERE id_pedido = ? AND id_producto = ?');
            $stmt->execute([$id, $d['id_producto']]);
            $cant_ant = $stmt->fetchColumn();
            $cant_ant = $cant_ant ? intval($cant_ant) : 0;
            $stock_disponible = $stock_actual + $cant_ant;
            if ($stock_disponible < $d['cantidad']) {
                $errores[] = 'Stock insuficiente para el producto ID ' . $d['id_producto'] . '. Disponible: ' . $stock_disponible . ', solicitado: ' . $d['cantidad'];
            }
        }
    }
    if (empty($errores)) {
        // Obtener estado anterior y detalles anteriores
        $stmt = $pdo->prepare('SELECT estado FROM pedidos WHERE id = ?');
        $stmt->execute([$id]);
        $estado_anterior = $stmt->fetchColumn();
        // Validar transiciones permitidas
        $allowed_transitions = [
            'pendiente' => ['pagado','cancelado'],
            'pagado' => ['preparacion','cancelado'],
            'preparacion' => ['enviado','cancelado'],
            'enviado' => ['entregado','cancelado'],
            'entregado' => [],
            'cancelado' => []
        ];
        if ($estado !== $estado_anterior) {
            if (!isset($allowed_transitions[$estado_anterior]) || !in_array($estado, $allowed_transitions[$estado_anterior])) {
                $errores[] = 'Transición no permitida de ' . $estado_anterior . ' a ' . $estado;
            }
        }
        $stmt = $pdo->prepare('SELECT * FROM detalle_pedido WHERE id_pedido = ?');
        $stmt->execute([$id]);
        $detalles_anteriores = $stmt->fetchAll();
        $pdo->beginTransaction();
        try {
            if ($tiene_numero_guia) {
                $stmt = $pdo->prepare('UPDATE pedidos SET id_usuario=?, direccion_envio=?, total=?, estado=?, numero_guia=? WHERE id=?');
                $ok = $stmt->execute([$id_usuario, $direccion_envio, $total, $estado, $numero_guia, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE pedidos SET id_usuario=?, direccion_envio=?, total=?, estado=? WHERE id=?');
                $ok = $stmt->execute([$id_usuario, $direccion_envio, $total, $estado, $id]);
            }
            // Registrar historial si cambia el estado
            if ($estado !== $estado_anterior) {
                $pdo->prepare('INSERT INTO pedido_estados (id_pedido, estado, comentario) VALUES (?, ?, ?)')
                    ->execute([$id, $estado, 'Edición realizada desde Admin']);
            }
            if ($ok) {
                // Eliminar detalles anteriores
                $stmt = $pdo->prepare('DELETE FROM detalle_pedido WHERE id_pedido = ?');
                $stmt->execute([$id]);
                // Insertar nuevos detalles
                $stmt_det = $pdo->prepare('INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario) VALUES (?, ?, ?, ?)');
                foreach ($detalles_nuevos as $d) {
                    $stmt_det->execute([$id, $d['id_producto'], $d['cantidad'], $d['precio_unitario']]);
                }
                $id_admin = $_SESSION['usuario']['id'];
                // --- Automatización de inventario ---
                // Mapear cantidades anteriores y nuevas por producto
                $mapa_ant = [];
                foreach ($detalles_anteriores as $d) {
                    $mapa_ant[$d['id_producto']] = $d['cantidad'];
                }
                $mapa_nuevo = [];
                foreach ($detalles_nuevos as $d) {
                    $mapa_nuevo[$d['id_producto']] = $d['cantidad'];
                }
                // Si pasa a pagado/entregado y antes no lo era
                if (in_array($estado, ['pagado','entregado']) && !in_array($estado_anterior, ['pagado','entregado'])) {
                    foreach ($detalles_nuevos as $d) {
                        $pdo->prepare('INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, motivo, id_usuario) VALUES (?, "salida", ?, ?, ?)')
                            ->execute([$d['id_producto'], $d['cantidad'], 'Venta/Pedido #' . $id, $id_admin]);
                        $pdo->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?')
                            ->execute([$d['cantidad'], $d['id_producto']]);
                    }
                }
                // Si pasa a cancelado y antes era pagado/entregado
                if ($estado === 'cancelado' && in_array($estado_anterior, ['pagado','entregado'])) {
                    foreach ($detalles_anteriores as $d) {
                        $pdo->prepare('INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, motivo, id_usuario) VALUES (?, "entrada", ?, ?, ?)')
                            ->execute([$d['id_producto'], $d['cantidad'], 'Cancelación Pedido #' . $id, $id_admin]);
                        $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?')
                            ->execute([$d['cantidad'], $d['id_producto']]);
                    }
                }
                // Si ya era pagado/entregado y se cambian productos/cantidades
                if (in_array($estado, ['pagado','entregado']) && in_array($estado_anterior, ['pagado','entregado'])) {
                    // Ajustar diferencias
                    foreach ($mapa_ant as $idp => $cant_ant) {
                        $cant_nuevo = $mapa_nuevo[$idp] ?? 0;
                        if ($cant_nuevo < $cant_ant) {
                            // Devolver stock
                            $diff = $cant_ant - $cant_nuevo;
                            $pdo->prepare('INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, motivo, id_usuario) VALUES (?, "entrada", ?, ?, ?)')
                                ->execute([$idp, $diff, 'Ajuste edición Pedido #' . $id, $id_admin]);
                            $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?')
                                ->execute([$diff, $idp]);
                        } elseif ($cant_nuevo > $cant_ant) {
                            // Descontar stock extra
                            $diff = $cant_nuevo - $cant_ant;
                            $pdo->prepare('INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, motivo, id_usuario) VALUES (?, "salida", ?, ?, ?)')
                                ->execute([$idp, $diff, 'Ajuste edición Pedido #' . $id, $id_admin]);
                            $pdo->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?')
                                ->execute([$diff, $idp]);
                        }
                    }
                    // Si hay productos nuevos
                    foreach ($mapa_nuevo as $idp => $cant_nuevo) {
                        if (!isset($mapa_ant[$idp])) {
                            $pdo->prepare('INSERT INTO movimientos_inventario (id_producto, tipo, cantidad, motivo, id_usuario) VALUES (?, "salida", ?, ?, ?)')
                                ->execute([$idp, $cant_nuevo, 'Ajuste edición Pedido #' . $id, $id_admin]);
                            $pdo->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?')
                                ->execute([$cant_nuevo, $idp]);
                        }
                    }
                }
                // --- Fin automatización ---
                $pdo->commit();
                header('Location: pedidos.php?editado=1');
                exit;
            } else {
                $pdo->rollBack();
                $errores[] = 'Error al actualizar el pedido.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = 'Error al actualizar el pedido: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Pedido | Computécnicos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    function agregarProducto() {
        const prodSel = document.getElementById('producto_sel');
        const prodId = prodSel.value;
        const prodText = prodSel.options[prodSel.selectedIndex].text;
        const prodPrecio = prodSel.options[prodSel.selectedIndex].getAttribute('data-precio');
        if (!prodId) return;
        const tbody = document.getElementById('productos_tabla');
        const row = document.createElement('tr');
        row.innerHTML = `<td><input type='hidden' name='productos[]' value='${prodId}'>${prodText}</td>
            <td><input type='number' name='cantidades[]' min='1' value='1' class='w-20 bg-[#181818] border border-[#333] rounded px-2 py-1 text-white'></td>
            <td><input type='number' name='precios[]' min='0' step='0.01' value='${prodPrecio}' class='w-28 bg-[#181818] border border-[#333] rounded px-2 py-1 text-white'></td>
            <td><button type='button' onclick='this.parentNode.parentNode.remove()' class='bg-red-700 hover:bg-red-800 text-white px-2 py-1 rounded'>Quitar</button></td>`;
        tbody.appendChild(row);
    }
    </script>
</head>
<body class="bg-[#181818] text-white min-h-screen flex flex-col">
    <header class="bg-[#232323] border-b border-[#333] py-4 px-8 flex items-center justify-between">
        <span class="text-2xl font-bold text-red-600">Editar Pedido</span>
        <a href="pedidos.php" class="text-gray-300 hover:text-red-500 transition">Volver a pedidos</a>
    </header>
    <main class="flex-1 container mx-auto px-4 py-12 max-w-2xl">
        <h2 class="text-2xl font-bold mb-8">Editar pedido</h2>
        <?php if ($errores): ?>
            <div class="bg-red-800 text-white rounded p-4 mb-6">
                <?php foreach ($errores as $e): ?>
                    <div>• <?php echo htmlspecialchars($e); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form method="post" class="space-y-6 bg-[#232323] p-8 rounded-lg shadow">
            <div>
                <label class="block mb-1 font-semibold">Cliente *</label>
                <select name="id_usuario" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" required>
                    <option value="">Selecciona un cliente</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php if(isset($_POST['id_usuario'])) {if($_POST['id_usuario']==$u['id']) echo 'selected';} else {if($pedido['id_usuario']==$u['id']) echo 'selected';} ?>><?php echo htmlspecialchars($u['nombre']) . ' (' . htmlspecialchars($u['email']) . ')'; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block mb-1 font-semibold">Dirección de envío *</label>
                <input type="text" name="direccion_envio" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" required value="<?php echo htmlspecialchars($_POST['direccion_envio'] ?? $pedido['direccion_envio']); ?>">
            </div>
            <?php if ($tiene_numero_guia): ?>
            <div>
                <label class="block mb-1 font-semibold">Número de guía (opcional)</label>
                <input type="text" name="numero_guia" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" value="<?php echo htmlspecialchars($_POST['numero_guia'] ?? ($pedido['numero_guia'] ?? '')); ?>" placeholder="Ej: 1234567890">
            </div>
            <?php endif; ?>
            <div>
                <label class="block mb-1 font-semibold">Estado *</label>
                <select name="estado" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white">
                    <?php foreach ([
                        'pendiente' => 'Pendiente',
                        'pagado' => 'Pagado',
                        'preparacion' => 'En preparación',
                        'enviado' => 'Enviado',
                        'entregado' => 'Entregado',
                        'cancelado' => 'Cancelado',
                    ] as $valor => $texto): ?>
                        <option value="<?php echo $valor; ?>" <?php if(($_POST['estado'] ?? $pedido['estado'])==$valor) echo 'selected'; ?>><?php echo $texto; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block mb-1 font-semibold">Productos *</label>
                <div class="flex gap-2 mb-2">
                    <select id="producto_sel" class="bg-[#181818] border border-[#333] rounded px-3 py-2 text-white">
                        <option value="">Selecciona producto</option>
                        <?php foreach ($productos as $p): ?>
                            <option value="<?php echo $p['id']; ?>" data-precio="<?php echo $p['precio']; ?>"><?php echo htmlspecialchars($p['nombre']) . ' ($' . number_format($p['precio'],0,',','.') . ' COP, stock: ' . $p['stock'] . ')'; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" onclick="agregarProducto()" class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded">Agregar</button>
                </div>
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-[#181818]">
                        <tr>
                            <th class="px-4 py-2">Producto</th>
                            <th class="px-4 py-2">Cantidad</th>
                            <th class="px-4 py-2">Precio unitario</th>
                            <th class="px-4 py-2">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="productos_tabla">
                        <!-- Productos agregados -->
                        <?php
                        $prods = [];
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['productos'])) {
                            foreach ($_POST['productos'] as $i => $idp) {
                                $prods[] = [
                                    'id_producto' => $idp,
                                    'cantidad' => intval($_POST['cantidades'][$i]),
                                    'precio_unitario' => floatval($_POST['precios'][$i])
                                ];
                            }
                        } else {
                            foreach ($detalles as $d) {
                                $prods[] = [
                                    'id_producto' => $d['id_producto'],
                                    'cantidad' => $d['cantidad'],
                                    'precio_unitario' => $d['precio_unitario']
                                ];
                            }
                        }
                        foreach ($prods as $i => $prod): ?>
                        <tr>
                            <td><input type='hidden' name='productos[]' value='<?php echo $prod['id_producto']; ?>'><?php
                                foreach ($productos as $p) if ($p['id']==$prod['id_producto']) echo htmlspecialchars($p['nombre']);
                            ?></td>
                            <td><input type='number' name='cantidades[]' min='1' value='<?php echo intval($prod['cantidad']); ?>' class='w-20 bg-[#181818] border border-[#333] rounded px-2 py-1 text-white'></td>
                            <td><input type='number' name='precios[]' min='0' step='0.01' value='<?php echo floatval($prod['precio_unitario']); ?>' class='w-28 bg-[#181818] border border-[#333] rounded px-2 py-1 text-white'></td>
                            <td><button type='button' onclick='this.parentNode.parentNode.remove()' class='bg-red-700 hover:bg-red-800 text-white px-2 py-1 rounded'>Quitar</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pt-4">
                <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 rounded">Guardar cambios</button>
            </div>
        </form>
    </main>
</body>
</html>