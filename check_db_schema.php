<?php
require_once __DIR__ . '/app/Core/bootstrap.php';
require_once __DIR__ . '/config/database.php';

echo "Table: productos\n";
echo "-----------------\n";
try {
    $stmt = $pdo->query("DESCRIBE productos");
    while($row = $stmt->fetch()) {
        echo sprintf("%-20s %-20s %s\n", $row['Field'], $row['Type'], $row['Null'] === 'NO' ? 'NOT NULL' : 'NULL');
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
