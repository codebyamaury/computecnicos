<!-- Modal Nuevo movimiento -->
<div id="modal-nuevo-bg" class="adm-modal-overlay"></div>
<div id="modal-nuevo-movimiento" class="adm-modal hidden">
    <div class="adm-modal-box">
        <button class="adm-modal-close" onclick="cerrarModalMovimiento()">&times;</button>
        <div class="adm-modal-title">Nuevo Movimiento</div>
        <form id="form-nuevo-movimiento" method="post" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:0.875rem">
                    <?= csrf_field() ?>
            <div>
                <label class="adm-label">Producto *</label>
                <select name="id_producto" class="adm-select" required>
                    <option value="">Selecciona un producto</option>
                    <?php foreach ($pdo->query('SELECT id, nombre, stock FROM productos ORDER BY nombre') as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?> (Stock: <?= $p['stock'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="adm-label">Tipo *</label>
                <select name="tipo" id="nuevo-tipo" class="adm-select" required onchange="mostrarCamposEntrada()">
                    <option value="">Selecciona tipo</option>
                    <option value="entrada">Entrada (compra)</option>
                    <option value="salida">Salida (venta/ajuste)</option>
                    <option value="ajuste">Ajuste</option>
                </select>
            </div>
            <div id="campos-entrada" style="display:none;display:flex;flex-direction:column;gap:0.875rem">
                <div>
                    <label class="adm-label">Proveedor</label>
                    <select name="id_proveedor" class="adm-select">
                        <option value="">Selecciona un proveedor</option>
                        <?php foreach ($pdo->query('SELECT id, nombre FROM proveedores ORDER BY nombre') as $prov): ?>
                        <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="adm-label">Número de factura/soporte</label><input type="text" name="numero_factura" class="adm-input"></div>
                <div class="adm-form-row" style="margin-bottom:0">
                    <div><label class="adm-label">Precio unitario</label><input type="number" name="precio_unitario" min="0" step="0.01" class="adm-input"></div>
                    <div><label class="adm-label">IVA (valor)</label><input type="number" name="iva" min="0" step="0.01" class="adm-input"></div>
                </div>
                <div class="adm-form-row" style="margin-bottom:0">
                    <div><label class="adm-label">Retención (valor)</label><input type="number" name="retencion" min="0" step="0.01" class="adm-input"></div>
                    <div><label class="adm-label">Soporte (PDF/JPG)</label><input type="file" name="soporte" accept=".pdf,image/*" class="adm-input" style="padding:0.35rem"></div>
                </div>
            </div>
            <div class="adm-form-row" style="margin-bottom:0">
                <div><label class="adm-label">Cantidad *</label><input type="number" name="cantidad" min="1" class="adm-input" required></div>
                <div><label class="adm-label">Motivo</label><input type="text" name="motivo" class="adm-input"></div>
            </div>
            <button type="submit" class="adm-btn adm-btn-primary" style="width:100%;justify-content:center;margin-top:0.25rem">Registrar movimiento</button>
        </form>
        <div id="modal-nuevo-msg" style="display:none;margin-top:0.75rem;text-align:center;color:#ef4444;font-size:0.8rem"></div>
    </div>
</div>

<script>
function abrirModalMovimiento(e) {
    if (e) e.preventDefault();
    document.getElementById('modal-nuevo-bg').classList.add('show');
    document.getElementById('modal-nuevo-movimiento').classList.remove('hidden');
    document.getElementById('modal-nuevo-movimiento').classList.add('show');
    document.getElementById('form-nuevo-movimiento').reset();
    document.getElementById('campos-entrada').style.display='none';
    document.body.style.overflow='hidden';
}

function cerrarModalMovimiento() {
    document.getElementById('modal-nuevo-bg').classList.remove('show');
    document.getElementById('modal-nuevo-movimiento').classList.add('hidden');
    document.getElementById('modal-nuevo-movimiento').classList.remove('show');
    document.body.style.overflow='';
}

function mostrarCamposEntrada() {
    var tipo = document.getElementById('nuevo-tipo').value;
    document.getElementById('campos-entrada').style.display = (tipo === 'entrada') ? 'flex' : 'none';
}

document.getElementById('modal-nuevo-bg').addEventListener('click', cerrarModalMovimiento);

document.getElementById('form-nuevo-movimiento').addEventListener('submit', async function(e) {
    e.preventDefault();
    const data = new FormData(this);
    try {
        const res = await fetch('inventario_nuevo.php', { method: 'POST', body: data });
        const result = await res.text();
        if (result.trim() === 'success') {
            window.location.href = window.location.pathname + '?exito=1';
        } else {
            const m = document.getElementById('modal-nuevo-msg');
            m.textContent = result || 'Error al registrar movimiento. Revisa los datos.';
            m.style.display = 'block';
        }
    } catch (err) {
        const m = document.getElementById('modal-nuevo-msg');
        m.textContent = 'Error de conexión al registrar.';
        m.style.display = 'block';
    }
});
</script>
