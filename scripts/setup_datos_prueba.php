<?php
require_once __DIR__ . '/app/Core/bootstrap.php';

try {
    // Leer el archivo SQL
    $sql = file_get_contents('sql/datos_prueba.sql');
    
    // Ejecutar las consultas
    $pdo->exec($sql);
    
    echo "Datos de prueba creados exitosamente.\n";
} catch (PDOException $e) {
    die("Error al crear datos de prueba: " . $e->getMessage() . "\n");
}