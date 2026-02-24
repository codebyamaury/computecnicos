<?php
require_once __DIR__ . '/app/Core/bootstrap.php';

function run_sql_file($pdo, $path) {
    if (!file_exists($path)) return;
    $sql = file_get_contents($path);
    if (!$sql) return;
    $pdo->exec($sql);
}

function add_column_if_missing($pdo, $table, $column, $definitionSql) {
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$dbName, $table, $column]);
    $exists = $stmt->fetchColumn() > 0;
    if (!$exists) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN $definitionSql");
        return true;
    }
    return false;
}

try {
    run_sql_file($pdo, 'sql/facturacion_electronica.sql');
    echo "Tabla facturas_electronicas verificada.\n";
    run_sql_file($pdo, 'sql/notas_credito.sql');
    echo "Tabla notas_credito verificada.\n";
    $added = add_column_if_missing($pdo, 'productos', 'iva_porcentaje', 'iva_porcentaje DECIMAL(5,2) NULL DEFAULT 19.00');
    echo $added ? "Columna iva_porcentaje agregada.\n" : "Columna iva_porcentaje ya existe.\n";
} catch (PDOException $e) {
    die("Error en setup de facturación: " . $e->getMessage() . "\n");
}
?>