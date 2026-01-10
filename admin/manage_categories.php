<?php
// admin/manage_categories.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

$success = '';
$error = '';

// 1. ADD CATEGORY
if (isset($_POST['add_category'])) {
    $name = trim($_POST['category_name']);
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO grading_categories (name) VALUES (:name)");
        $stmt->execute(['name' => $name]);
        $success = "Category '$name' added!";
    }
}

// 2. DELETE CATEGORY
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Use 'id' here, matching the database
    $stmt = $pdo->prepare("DELETE FROM grading_categories WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $success = "Category deleted.";
}

// 3. FETCH CATEGORIES (Fixed the ORDER BY bug)
$categories = $pdo->query("SELECT * FROM grading_categories ORDER BY id ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Categories</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        body { background: #f4f6f8; font-family: sans-serif; }
        .container { max-width: 800px; margin: 50px auto; display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        input[type="text"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #dfe3e8; border-radius: 4px; }
        button { width: 100%; background: #2c3e50; color: white; border: none; padding: 10px; cursor: pointer; border-radius: 4px; }
        .item { display: flex; justify-content: space-between; padding: 10px; border-bottom: 1px solid #eee; }
        .delete-btn { color: #e74c3c; cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <div class="card" style="height: fit-content;">
        <h3>Add Category</h3>
        <?php if($success) echo "<p style='color:green; font-size:0.9rem;'>$success</p>"; ?>
        <form method="POST">
            <input type="text" name="category_name" placeholder="e.g. Lab Report" required>
            <button type="submit" name="add_category">Add</button>
        </form>
    </div>

    <div class="card">
        <h3>Available Categories</h3>
        <?php foreach($categories as $cat): ?>
            <div class="item">
                <span><?php echo htmlspecialchars($cat['name']); ?></span>
                <a href="?delete=<?php echo $cat['id']; ?>" class="delete-btn" onclick="return confirm('Delete this?')">
                    <i class='bx bx-trash'></i>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>