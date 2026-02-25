<?php
// Bootstrap PRIMERO: inicia sesión desde la BD antes de verificar permisos
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}

// Obtener usuarios, productos y empresas (si existe la tabla)
$usuarios = $pdo->query('SELECT id, nombre, email FROM usuarios ORDER BY nombre')->fetchAll();
$productos = $pdo->query('SELECT id, nombre, precio, stock FROM productos ORDER BY nombre')->fetchAll();
// $empresas = [];
// try {
//     $empresas = $pdo->query('SELECT id, nombre FROM empresas ORDER BY nombre')->fetchAll();
// } catch (Exception $e) {
//     // Si no existe la tabla, dejar el select vacío
// }

$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = intval($_POST['id_usuario'] ?? 0);
    // $id_empresa = intval($_POST['id_empresa'] ?? 0); // Eliminado el campo de empresa
    $direccion_envio = trim($_POST['direccion_envio'] ?? '');
    $productos_sel = $_POST['productos'] ?? [];
    $cantidades = $_POST['cantidades'] ?? [];
    $precios = $_POST['precios'] ?? [];
    $descuentos = $_POST['descuentos'] ?? [];
    $metodo_pago = $_POST['metodo_pago'] ?? '';
    $total = 0;
    $subtotal = 0;
    $total_descuentos = 0;
    $iva = 0;
    $detalles = [];
    foreach ($productos_sel as $i => $id_prod) {
        $id_prod = intval($id_prod);
        $cantidad = intval($cantidades[$i] ?? 0);
        $precio = floatval($precios[$i] ?? 0);
        $descuento = floatval($descuentos[$i] ?? 0);
        if ($id_prod > 0 && $cantidad > 0 && $precio > 0) {
            $item_subtotal = $cantidad * $precio;
            $item_descuento = $item_subtotal * ($descuento / 100);
            $subtotal += $item_subtotal;
            $total_descuentos += $item_descuento;
            $detalles[] = [
                'id_producto' => $id_prod,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'descuento' => $descuento
            ];
        }
    }
    $iva = ($subtotal - $total_descuentos) * 0.19;
    $total = ($subtotal - $total_descuentos) + $iva;
    if ($id_usuario <= 0) $errores[] = 'Selecciona un cliente.';
    if ($direccion_envio === '') $errores[] = 'La dirección de envío es obligatoria.';
    if (empty($detalles)) $errores[] = 'Agrega al menos un producto.';
    if ($metodo_pago === '') $errores[] = 'Selecciona un método de pago.';
    // Validar stock suficiente para cada producto
    foreach ($detalles as $d) {
        $stmt = $pdo->prepare('SELECT stock FROM productos WHERE id = ?');
        $stmt->execute([$d['id_producto']]);
        $stock_actual = $stmt->fetchColumn();
        if ($stock_actual < $d['cantidad']) {
            $errores[] = 'Stock bajo para el producto ID ' . $d['id_producto'] . '. Solo quedan ' . $stock_actual . ' unidades.';
        }
    }
    if (empty($errores)) {
        $pdo->beginTransaction();
        try {
            $estado = 'pendiente';
            $stmt = $pdo->prepare('INSERT INTO pedidos (id_usuario, direccion_envio, total, estado, fecha) VALUES (?, ?, ?, ?, NOW())');
            $ok = $stmt->execute([$id_usuario, $direccion_envio, $total, $estado]);
            if ($ok) {
                $id_pedido = $pdo->lastInsertId();
                $stmt_det = $pdo->prepare('INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, descuento) VALUES (?, ?, ?, ?, ?)');
                foreach ($detalles as $d) {
                    $stmt_det->execute([$id_pedido, $d['id_producto'], $d['cantidad'], $d['precio_unitario'], $d['descuento']]);
                }
                $pdo->commit();
                header('Location: pedidos.php?exito=1');
                exit;
            } else {
                $pdo->rollBack();
                $errores[] = 'Error al guardar el pedido.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $errores[] = 'Error al guardar el pedido: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Facturación Clientes | Computécnicos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    // --- Autocompletar productos ---
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('producto_search');
        const select = document.getElementById('producto_sel');
        if (input && select) {
            input.addEventListener('input', function() {
                const val = input.value.toLowerCase();
                for (let i = 0; i < select.options.length; i++) {
                    const opt = select.options[i];
                    opt.style.display = opt.text.toLowerCase().includes(val) ? '' : 'none';
                }
            });
        }
        calcularTotales(); // Calcular totales al cargar la página
    });
    // --- Agregar producto ---
    function agregarProducto() {
        const prodSel = document.getElementById('producto_sel');
        const prodId = prodSel.value;
        const prodText = prodSel.options[prodSel.selectedIndex].text;
        const prodPrecio = prodSel.options[prodSel.selectedIndex].getAttribute('data-precio');
        const prodStock = prodSel.options[prodSel.selectedIndex].getAttribute('data-stock');
        if (!prodId) return;
        const tbody = document.getElementById('productos_tabla');
        const row = document.createElement('tr');
        row.innerHTML = `<td><input type='hidden' name='productos[]' value='${prodId}'>${prodText}</td>
            <td>Und</td>
            <td><input type='number' name='cantidades[]' min='1' value='1' class='w-20 bg-[#181818] border border-[#333] rounded px-2 py-1 text-white' onchange='calcularTotales()'></td>
            <td><input type='number' name='precios[]' min='0' step='0.01' value='${prodPrecio}' class='w-28 bg-[#181818] border border-[#333] rounded px-2 py-1 text-white' onchange='calcularTotales()'></td>
            <td><input type='number' name='descuentos[]' min='0' max='100' value='0' class='w-20 bg-[#181818] border border-[#333] rounded px-2 py-1 text-white' onchange='calcularTotales()'></td>
            <td class='stock-alert'>${prodStock <= 2 ? `<span class='text-red-500 font-bold'>${prodStock}</span>` : prodStock}</td>
            <td><button type='button' onclick='this.parentNode.parentNode.remove(); calcularTotales();' class='bg-red-700 hover:bg-red-800 text-white px-2 py-1 rounded'>Quitar</button></td>`;
        tbody.appendChild(row);
        calcularTotales();
    }
    // --- Cálculo en tiempo real ---
    function calcularTotales() {
        let subtotal = 0, descuentos = 0, iva = 0, total = 0;
        const rows = document.querySelectorAll('#productos_tabla tr');
        rows.forEach(row => {
            const cantidad = parseFloat(row.querySelector('input[name="cantidades[]"]').value) || 0;
            const precio = parseFloat(row.querySelector('input[name="precios[]"]').value) || 0;
            const descuento = parseFloat(row.querySelector('input[name="descuentos[]"]').value) || 0;
            const stockCell = row.querySelector('.stock-alert');
            const stock = parseInt(stockCell.textContent) || 0;
            // Validación de stock
            if (cantidad > stock) {
                stockCell.innerHTML = `<span class='text-red-500 font-bold'>${stock} (Stock insuficiente)</span>`;
            } else if (stock <= 2) {
                stockCell.innerHTML = `<span class='text-red-500 font-bold'>${stock}</span>`;
            } else {
                stockCell.textContent = stock;
            }
            const itemSubtotal = cantidad * precio;
            const itemDescuento = itemSubtotal * (descuento / 100);
            subtotal += itemSubtotal;
            descuentos += itemDescuento;
        });
        iva = (subtotal - descuentos) * 0.19;
        total = (subtotal - descuentos) + iva;
        document.getElementById('subtotal').value = subtotal.toLocaleString('es-CO', {minimumFractionDigits:2});
        document.getElementById('descuentos').value = descuentos.toLocaleString('es-CO', {minimumFractionDigits:2});
        document.getElementById('iva').value = iva.toLocaleString('es-CO', {minimumFractionDigits:2});
        document.getElementById('total').value = total.toLocaleString('es-CO', {minimumFractionDigits:2});
    }
    </script>
</head>
<body class="bg-[#181818] text-white min-h-screen">
<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-[#232323] border-r border-[#333] flex flex-col py-6 px-4 fixed h-full z-20">
        <div class="flex items-center gap-3 mb-8 px-2">
            <img src="/assets/logo.png" alt="Logo" class="h-10 w-10 object-contain rounded bg-white p-1">
                    <span class="text-xl font-bold tracking-widest text-red-600">COMPUTÉCNICOS</span>
        </div>
        <nav class="flex-1">
            <ul class="space-y-2">
                <li><a href="dashboard.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Dashboard</a></li>
                <li><a href="productos.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Productos</a></li>
                <li><a href="categorias.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Categorías</a></li>
                <li><a href="marcas.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Marcas</a></li>
                <li><a href="usuarios.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Usuarios</a></li>
                <li><a href="pedidos.php" class="block px-3 py-2 rounded bg-[#181818] font-semibold text-red-500">Pedidos</a></li>
                <li><a href="proveedores.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Proveedores</a></li>
                <li><a href="inventario.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Inventario</a></li>
                <li><a href="reporte_contable.php" class="block px-3 py-2 rounded hover:bg-[#181818]">Reportes</a></li>
            </ul>
        </nav>
        <div class="mt-8 border-t border-[#333] pt-4 px-2">
            <div class="text-xs text-gray-400 mb-1">Usuario:</div>
            <div class="font-semibold text-sm text-white mb-2"><?php echo htmlspecialchars($_SESSION['usuario']['nombre']); ?> (<?php echo htmlspecialchars($_SESSION['usuario']['rol']); ?>)</div>
            <a href="../logout.php" class="block text-red-500 hover:underline text-xs">Cerrar sesión</a>
        </div>
    </aside>
    <!-- Main content -->
    <div class="flex-1 ml-64 flex flex-col min-h-screen">
        <!-- Header -->
        <header class="bg-[#232323] border-b border-[#333] px-8 py-4 flex items-center justify-between sticky top-0 z-10">
            <div>
                <div class="text-lg font-bold text-white">Facturación Clientes</div>
                <nav class="text-xs text-gray-400 mt-1">
                    <a href="dashboard.php" class="hover:underline">Panel</a> <span class="mx-1">/</span> <span class="text-red-500">Facturación Clientes</span>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <a href="../index.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition duration-200 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Ir a la Tienda
                </a>
            </div>
        </header>
        <!-- Content -->
        <main class="flex-1 px-8 py-10 bg-[#181818]">
            <form method="post" class="max-w-5xl mx-auto bg-[#232323] p-8 rounded-xl border border-[#333] shadow space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block mb-1 font-semibold">Cliente *</label>
                        <select name="id_usuario" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" required>
                            <option value="">Selecciona un cliente</option>
                            <?php foreach ($usuarios as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php if(isset($_POST['id_usuario']) && $_POST['id_usuario']==$u['id']) echo 'selected'; ?>><?php echo htmlspecialchars($u['nombre']) . ' (' . htmlspecialchars($u['email']) . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-1 font-semibold">Dirección de envío *</label>
                        <input type="text" name="direccion_envio" class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white" required value="<?php echo htmlspecialchars($_POST['direccion_envio'] ?? ''); ?>">
                    </div>
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Items</label>
                    <div class="flex gap-2 mb-2">
                        <input type="text" id="producto_search" placeholder="Buscar producto..." class="bg-[#181818] border border-[#333] rounded px-3 py-2 text-white w-1/2" autocomplete="off">
                        <select id="producto_sel" class="bg-[#181818] border border-[#333] rounded px-3 py-2 text-white w-1/2">
                            <option value="">Selecciona producto</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?php echo $p['id']; ?>" data-precio="<?php echo $p['precio']; ?>" data-stock="<?php echo $p['stock']; ?>"><?php echo htmlspecialchars($p['nombre']) . ' (Stock: ' . $p['stock'] . ')'; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="agregarProducto()" class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded">+ Agregar ítem</button>
                    </div>
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-[#181818]">
                            <tr>
                                <th class="px-4 py-2">Producto</th>
                                <th class="px-4 py-2">Unidad</th>
                                <th class="px-4 py-2">Cantidad</th>
                                <th class="px-4 py-2">Precio</th>
                                <th class="px-4 py-2">Descuento (%)</th>
                                <th class="px-4 py-2">Stock</th>
                                <th class="px-4 py-2">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="productos_tabla">
                            <!-- Productos agregados -->
                            <?php if (!empty($_POST['productos'])): foreach ($_POST['productos'] as $i => $idp): ?>
                            <tr>
                                <td><input type='hidden' name='productos[]' value='<?php echo $idp; ?>'><?php
                                    foreach ($productos as $p) if ($p['id']==$idp) echo htmlspecialchars($p['nombre']);
                                ?></td>
                                <td>Und</td>
                                <td><input type='number' name='cantidades[]' min='1' value='<?php echo intval($_POST['cantidades'][$i]); ?>' class='w-20 bg-[#181818] border border-[#333] rounded px-2 py-1 text-white' onchange='calcularTotales()'></td>
                                <td><input type='number' name='precios[]' min='0' step='0.01' value='<?php echo floatval($_POST['precios'][$i]); ?>' class='w-28 bg-[#181818] border border-[#333] rounded px-2 py-1 text-white' onchange='calcularTotales()'></td>
                                <td><input type='number' name='descuentos[]' min='0' max='100' value='<?php echo floatval($_POST['descuentos'][$i] ?? 0); ?>' class='w-20 bg-[#181818] border border-[#333] rounded px-2 py-1 text-white' onchange='calcularTotales()'></td>
                                <td class='stock-alert'><?php
                                    foreach ($productos as $p) if ($p['id']==$idp) echo ($p['stock'] <= 2 ? "<span class='text-red-500 font-bold'>".$p['stock']."</span>" : $p['stock']);
                                ?></td>
                                <td><button type='button' onclick='this.parentNode.parentNode.remove(); calcularTotales();' class='bg-red-700 hover:bg-red-800 text-white px-2 py-1 rounded'>Quitar</button></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-8">
                    <div>
                        <label class="block text-gray-400 text-xs mb-1">Subtotal</label>
                        <input type="text" id="subtotal" readonly class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-xs mb-1">Descuentos</label>
                        <input type="text" id="descuentos" readonly class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-xs mb-1">IVA (19%)</label>
                        <input type="text" id="iva" readonly class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-xs mb-1">Total Neto</label>
                        <input type="text" id="total" readonly class="w-full bg-[#181818] border border-[#333] rounded px-3 py-2 text-white font-bold">
                    </div>
                </div>
                <div class="mt-8">
                    <label class="block mb-1 font-semibold">Método de Pago *</label>
                    <div class="flex gap-8">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="metodo_pago" value="efectivo" class="accent-red-600" <?php if(isset($_POST['metodo_pago']) && $_POST['metodo_pago']==='efectivo') echo 'checked'; ?>> Efectivo
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="metodo_pago" value="datfono" class="accent-red-600" <?php if(isset($_POST['metodo_pago']) && $_POST['metodo_pago']==='datfono') echo 'checked'; ?>> Datáfono
                        </label>

                    </div>
                </div>
                <div class="flex justify-end gap-4 mt-8">
                    <a href="pedidos.php" class="bg-gray-700 hover:bg-gray-800 text-white px-6 py-2 rounded font-semibold">Cancelar</a>
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded font-bold">Guardar Factura</button>
                </div>
                <?php if ($errores): ?>
                    <div class="bg-red-800 text-white rounded p-4 mt-6">
                        <?php foreach ($errores as $e): ?>
                            <div>• <?php echo htmlspecialchars($e); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </form>
        </main>
    </div>
</div>
</body>
</html>