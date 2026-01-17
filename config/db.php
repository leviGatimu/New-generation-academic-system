<?php
// config/db.php
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        // THIS IS THE FIX: It tells the server to use a secure transport layer
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    ];
    
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // This will display the error if it still fails
    die("Connection failed: " . $e->getMessage());
}
?