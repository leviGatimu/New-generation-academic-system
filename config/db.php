<?php
$host = 'localhost';
$db_name = 'new_generation_db';
$username = 'root';
$password = ''; // Most XAMPP setups have no password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Local Database Connection Failed: " . $e->getMessage());
}
?>