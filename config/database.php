<?php
$host = $_ENV['DB_HOST'] ?? 'localhost';
$db = $_ENV['DB_NAME'] ?? 'computecnicos';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '982/gs2pcbV76093fny34_';
$port = $_ENV['DB_PORT'] ?? '3306';
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_PERSISTENT => true, // Reusar conexiones (reduce latencia con DB remota)
    PDO::ATTR_EMULATE_PREPARES => false, // Prepared statements nativos
    PDO::MYSQL_ATTR_FOUND_ROWS => true,
];
try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, $options);
} catch (PDOException $e) {
    throw $e;
} 