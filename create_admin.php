<?php
// create_admin.php
require 'config/db.php';

$email = 'admin@nga.rw';
$password = 'admin123';
$full_name = 'System Admin';

// 1. Generate a fresh hash using YOUR server's algorithm
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // 2. Delete old admin if exists (to avoid duplicates)
    $sql_delete = "DELETE FROM users WHERE email = :email";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute(['email' => $email]);

    // 3. Insert the new Admin
    $sql_insert = "INSERT INTO users (full_name, email, password, role) VALUES (:name, :email, :pass, 'admin')";
    $stmt_insert = $pdo->prepare($sql_insert);
    
    $stmt_insert->execute([
        'name' => $full_name,
        'email' => $email,
        'pass' => $hashed_password
    ]);

    echo "<h1 style='color: green;'>SUCCESS!</h1>";
    echo "<p>Admin user created successfully.</p>";
    echo "<p><strong>Email:</strong> $email</p>";
    echo "<p><strong>Password:</strong> $password</p>";
    echo "<br><a href='index.php'>Go to Login Page</a>";

} catch (PDOException $e) {
    echo "<h1 style='color: red;'>ERROR</h1>";
    echo "Database error: " . $e->getMessage();
}
?>