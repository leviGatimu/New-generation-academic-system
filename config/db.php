<?php
// config/db.php

$host = 'localhost';
$db_name = 'new_generation_db';
$username = 'root'; // Default XAMPP username
$password = '';     // Default XAMPP password (leave empty)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    // Set error mode to exception to catch any issues easily
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>