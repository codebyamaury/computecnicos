<?php
require_once 'app/Core/bootstrap.php';
ob_start();

try {
    echo "--- Columnas de Productos ---\n";
    $stmt = $pdo->query("DESCRIBE productos");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    
    echo "\n--- Columnas de Categorias ---\n";
    $stmt = $pdo->query("DESCRIBE categorias");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }

    echo "\n--- Columnas de Marcas ---\n";
    $stmt = $pdo->query("DESCRIBE marcas");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

file_put_contents('schema_dump.txt', ob_get_clean());
?>
