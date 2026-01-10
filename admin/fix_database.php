<?php
// admin/fix_database.php
require '../config/db.php';

try {
    echo "<h1>Starting Database Cleanup...</h1>";

    // 1. TURN OFF SAFETY LOCK
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "✅ Safety Lock Disabled<br>";

    // 2. EMPTY THE TABLES (Order doesn't matter now)
    $pdo->exec("TRUNCATE TABLE class_subjects");
    echo "✅ Class Subjects Cleared<br>";
    
    $pdo->exec("TRUNCATE TABLE subjects");
    echo "✅ Subjects Cleared<br>";
    
    $pdo->exec("TRUNCATE TABLE classes");
    echo "✅ Classes Cleared<br>";
    
    $pdo->exec("TRUNCATE TABLE class_categories");
    echo "✅ Categories Cleared<br>";

    // 3. RE-INSERT DEFAULT CATEGORIES (So you aren't left with nothing)
    $pdo->exec("INSERT INTO class_categories (category_name, color_code) VALUES 
        ('Standard School', '#FF6600'), 
        ('Coding Academy', '#3498db')");
    echo "✅ Default Categories Restored<br>";

    // 4. TURN SAFETY LOCK BACK ON
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "✅ Safety Lock Re-enabled<br>";

    echo "<h2 style='color:green;'>SUCCESS: Database is clean and fixed.</h2>";
    echo "<a href='classes.php'>Go back to Classes Manager</a>";

} catch (PDOException $e) {
    echo "<h2 style='color:red;'>Error: " . $e->getMessage() . "</h2>";
}
?>