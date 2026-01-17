<?php
// setup_admin.php
require __DIR__ . '/../config/db.php';

// --- SETTINGS ---
$admin_name  = "Super Admin";
$admin_email = "admin@nga.com";
$admin_pass  = "admin123"; // This will be the password
// ----------------

try {
    // 1. Hash the password
    $hashed_password = password_hash($admin_pass, PASSWORD_DEFAULT);

    // 2. Check if this email already exists
    $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $check->execute([$admin_email]);
    $exists = $check->fetch();

    if ($exists) {
        // UPDATE EXISTING USER
        $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin', full_name = ? WHERE email = ?");
        $stmt->execute([$hashed_password, $admin_name, $admin_email]);
        echo "<h2 style='color:green;'>Success! Admin Updated.</h2>";
    } else {
        // CREATE NEW USER
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$admin_name, $admin_email, $hashed_password]);
        echo "<h2 style='color:green;'>Success! Admin Created.</h2>";
    }

    echo "<p><strong>Email:</strong> $admin_email</p>";
    echo "<p><strong>Password:</strong> $admin_pass</p>";
    echo "<br><a href='index.php'>Go to Login</a>";

} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Error: " . $e->getMessage() . "</h2>";
}
?>