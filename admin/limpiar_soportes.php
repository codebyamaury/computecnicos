<?php
session_start();
require_once __DIR__ . '/../app/Core/bootstrap.php';
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'admin') {
    header('Location: ../index.php?login=1');
    exit;
}

// Verificar si la columna soporte_documental existe
$col_soporte = false;
try {
    $chk = $pdo->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "movimientos_inventario" AND COLUMN_NAME = "soporte_documental"');
    $col_soporte = (int)$chk->fetchColumn() > 0;
} catch (Exception $e) {
    // tabla o columna no existe
}

// Función para limpiar soportes huérfanos
function limpiarSoportesHuerfanos($pdo, $col_soporte) {
    $upload_dir = '../uploads/soportes/';
    $archivos_eliminados = 0;
    $errores = [];

    if (!is_dir($upload_dir)) {
        return ['archivos_eliminados' => 0, 'errores' => ['Directorio de soportes no existe']];
    }

    $archivos = glob($upload_dir . '*');

    foreach ($archivos as $archivo) {
        if (is_file($archivo)) {
            $ruta_relativa = 'uploads/soportes/' . basename($archivo);
            $existe_en_bd = false;

            if ($col_soporte) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM movimientos_inventario WHERE soporte_documental = ?');
                $stmt->execute([$ruta_relativa]);
                $existe_en_bd = $stmt->fetchColumn() > 0;
            }

            if (!$existe_en_bd) {
                if (unlink($archivo)) {
                    $archivos_eliminados++;
                } else {
                    $errores[] = 'No se pudo eliminar: ' . basename($archivo);
                }
            }
        }
    }

    return ['archivos_eliminados' => $archivos_eliminados, 'errores' => $errores];
}

// Procesar la limpieza
$resultado = null;
if (isset($_POST['limpiar'])) {
    $resultado = limpiarSoportesHuerfanos($pdo, $col_soporte);
}

// Estadísticas
$total_soportes_bd = 0;
if ($col_soporte) {
    try {
        $total_soportes_bd = (int)$pdo->query('SELECT COUNT(*) FROM movimientos_inventario WHERE soporte_documental IS NOT NULL AND soporte_documental != ""')->fetchColumn();
    } catch (Exception $e) {}
}
$total_archivos_fs = count(glob('../uploads/soportes/*') ?: []);

$page_title       = 'Limpiar Soportes | Computécnicos';
$admin_page       = 'inventario';
$admin_title      = 'Limpiar Soportes';
$admin_breadcrumb = [['label' => 'Inventario', 'href' => 'inventario.php'], ['label' => 'Limpiar Soportes']];
$admin_header_extra = '<a href="inventario.php" class="adm-btn adm-btn-blue">← Volver a Inventario</a>';

include '_layout.php';
?>

<main class="admin-content" style="padding:1.25rem 1.5rem;display:flex;flex-direction:column;overflow-y:auto;height:calc(100vh - 60px)">

    <?php if (!$col_soporte): ?>
    <div style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:10px;padding:1rem 1.25rem;margin-bottom:1.25rem;color:#f59e0b;font-size:.82rem">
        ⚠️ La columna <code>soporte_documental</code> no existe en la tabla <code>movimientos_inventario</code>. Agrégala con:<br>
        <code style="display:block;margin-top:.5rem;background:#181818;padding:.5rem .75rem;border-radius:6px;color:#ccc">ALTER TABLE movimientos_inventario ADD COLUMN soporte_documental VARCHAR(255) DEFAULT NULL;</code>
    </div>
    <?php endif; ?>

    <!-- KPIs -->
    <div class="adm-kpi-grid" style="--kpi-cols:3;margin-bottom:1.25rem">
        <div class="adm-kpi blue">
            <div class="adm-kpi-label">Soportes en BD</div>
            <div class="adm-kpi-value"><?= $total_soportes_bd ?></div>
            <div class="adm-kpi-sub">Referencias en base de datos</div>
        </div>
        <div class="adm-kpi green">
            <div class="adm-kpi-label">Archivos en FS</div>
            <div class="adm-kpi-value"><?= $total_archivos_fs ?></div>
            <div class="adm-kpi-sub">Archivos en sistema de archivos</div>
        </div>
        <div class="adm-kpi yellow">
            <div class="adm-kpi-label">Huérfanos</div>
            <div class="adm-kpi-value"><?= max(0, $total_archivos_fs - $total_soportes_bd) ?></div>
            <div class="adm-kpi-sub">Archivos sin referencia</div>
        </div>
    </div>

    <?php if ($resultado): ?>
    <div class="adm-card" style="padding:1.25rem;margin-bottom:1.25rem;border-left:4px solid #22c55e">
        <div style="font-weight:700;margin-bottom:.5rem;color:#22c55e">Resultado de la Limpieza</div>
        <div style="font-size:.85rem;color:#aaa">✅ Se eliminaron <strong style="color:#fff"><?= $resultado['archivos_eliminados'] ?></strong> archivos huérfanos.</div>
        <?php if (!empty($resultado['errores'])): ?>
        <div style="margin-top:.75rem;color:#ef4444;font-size:.8rem">
            <strong>Errores:</strong>
            <ul style="margin-top:.25rem;padding-left:1rem">
                <?php foreach ($resultado['errores'] as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Info -->
    <div class="adm-card" style="padding:1.25rem;margin-bottom:1.25rem">
        <div style="font-weight:700;margin-bottom:.75rem;color:#f59e0b">¿Qué hace esta herramienta?</div>
        <div style="font-size:.8rem;color:#999;line-height:1.7">
            • <strong style="color:#ccc">Detecta archivos huérfanos:</strong> Archivos que existen en el servidor pero no están referenciados en la BD<br>
            • <strong style="color:#ccc">Elimina automáticamente:</strong> Libera espacio eliminando archivos innecesarios<br>
            • <strong style="color:#ccc">Mantiene integridad:</strong> Solo elimina archivos no asociados a ningún movimiento<br>
            • <strong style="color:#ccc">Seguro:</strong> No afecta soportes correctamente vinculados a compras
        </div>
    </div>

    <!-- Acción -->
    <div class="adm-card" style="padding:1.25rem">
        <div style="font-weight:700;margin-bottom:.5rem;color:#ef4444">Ejecutar Limpieza</div>
        <p style="font-size:.8rem;color:#666;margin-bottom:1rem">Esta acción eliminará permanentemente los archivos de soporte no referenciados. No se puede deshacer.</p>
        <form id="form-limpiar" method="post" style="display:inline">
            <input type="hidden" name="limpiar" value="1">
        </form>
        <button type="button" class="adm-btn adm-btn-danger" onclick="abrirConfirmLimpieza()">🗑️ Ejecutar Limpieza de Soportes</button>
    </div>

</main>

<!-- Modal Confirmar Limpieza -->
<div id="modal-limpiar-bg" class="adm-modal-overlay"></div>
<div id="modal-limpiar" class="adm-modal hidden">
    <div class="adm-modal-box" style="max-width:380px;text-align:center">
        <div style="width:56px;height:56px;background:rgba(239,68,68,.12);border:2px solid rgba(239,68,68,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.1rem">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:26px;height:26px;stroke:#ef4444">
                <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
        </div>
        <div class="adm-modal-title" style="margin-bottom:.4rem;font-size:1.1rem">¿Ejecutar limpieza?</div>
        <p style="color:#888;font-size:.82rem;line-height:1.5;margin-bottom:1.4rem">Se eliminarán permanentemente todos los archivos de soporte huérfanos. Esta acción no se puede deshacer.</p>
        <div style="display:flex;gap:10px">
            <button type="button" onclick="cerrarConfirmLimpieza()" class="adm-btn" style="flex:1;justify-content:center">Cancelar</button>
            <button type="button" onclick="document.getElementById('form-limpiar').submit()" class="adm-btn adm-btn-danger" style="flex:1;justify-content:center">Sí, limpiar</button>
        </div>
    </div>
</div>

<script>
function abrirConfirmLimpieza() {
    document.getElementById('modal-limpiar-bg').classList.add('show');
    document.getElementById('modal-limpiar').classList.remove('hidden');
    document.getElementById('modal-limpiar').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function cerrarConfirmLimpieza() {
    document.getElementById('modal-limpiar-bg').classList.remove('show');
    document.getElementById('modal-limpiar').classList.add('hidden');
    document.getElementById('modal-limpiar').classList.remove('show');
    document.body.style.overflow = '';
}
document.getElementById('modal-limpiar-bg').addEventListener('click', cerrarConfirmLimpieza);
</script>

<?php include '_layout_end.php'; ?>
