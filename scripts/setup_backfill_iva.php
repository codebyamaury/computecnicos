<?php
require_once __DIR__ . '/app/Core/bootstrap.php';

try {
    $updated = $pdo->exec("UPDATE productos SET iva_porcentaje = 19.00 WHERE iva_porcentaje IS NULL");
    echo "Backfill IVA completado. Registros actualizados: " . (int)$updated . "\n";
} catch (PDOException $e) {
    http_response_code(500);
    echo "Error en backfill IVA: " . $e->getMessage();
}
?>