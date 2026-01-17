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
        // Force SSL for TiDB Cloud
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        // Some PHP environments need this to trigger the SSL layer
        PDO::MYSQL_ATTR_SSL_CA => true, 
    ];
    
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>