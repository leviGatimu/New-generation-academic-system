<?php
// parent_register.php
session_start();
require 'config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $parent_code = trim($_POST['parent_code']);
    $full_name   = trim($_POST['full_name']);
    $email       = trim($_POST['email']);
    $password    = trim($_POST['password']);
    
    // 1. Verify the Code: Does this code exist in the STUDENTS table?
    // We also check if it's already linked to ensure one-time use if you want.
    $stmt = $pdo->prepare("SELECT * FROM students WHERE parent_access_code = ?");
    $stmt->execute([$parent_code]);
    $student = $stmt->fetch();

    if ($student) {
        // 2. Check if Email is already taken (Standard Registration Check)
        $checkEmail = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $checkEmail->execute([$email]);
        
        if ($checkEmail->rowCount() > 0) {
            $error = "This email is already registered. Please Login.";
        } else {
            // 3. Create the Parent Account
            try {
                $pdo->beginTransaction();

                // A. Insert into USERS table
                $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                $sql_user = "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'parent')";
                $stmt_user = $pdo->prepare($sql_user);
                $stmt_user->execute([$full_name, $email, $hashed_pass]);
                $parent_id = $pdo->lastInsertId();

                // B. Create the LINK (The most important part)
                // This connects the new Parent ID to the Student ID found via the code
                $sql_link = "INSERT INTO parent_student_link (parent_id, student_id) VALUES (?, ?)";
                $stmt_link = $pdo->prepare($sql_link);
                $stmt_link->execute([$parent_id, $student['student_id']]);

                // C. Optional: Clear the code so it can't be used again (Security)
                // $pdo->prepare("UPDATE students SET parent_access_code = NULL WHERE student_id = ?")->execute([$student['student_id']]);

                $pdo->commit();
                
                // Login the user automatically
                $_SESSION['user_id'] = $parent_id;
                $_SESSION['role'] = 'parent';
                $_SESSION['name'] = $full_name;
                
                header("Location: parent/dashboard.php");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "System Error: " . $e->getMessage();
            }
        }
    } else {
        $error = "Invalid Parent Code. Please check the code provided by your child.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parent Registration | NGA</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="background:#f4f6f8; display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif;">

<div style="background:white; padding:40px; border-radius:12px; width:100%; max-width:400px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
    <h2 style="text-align:center; color:#212b36; margin-top:0;">Parent Registration</h2>
    <p style="text-align:center; color:#637381; font-size:0.9rem;">Connect to your child's academic records.</p>
    
    <?php if($error): ?>
        <div style="background:#fee2e2; color:#b91c1c; padding:10px; border-radius:6px; margin-bottom:15px; text-align:center; font-size:0.9rem;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <label style="font-weight:bold; font-size:0.85rem; display:block; margin-bottom:5px;">Parent Access Code</label>
        <input type="text" name="parent_code" placeholder="e.g. PARENT-1234" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #dfe3e8; border-radius:6px; box-sizing:border-box;">
        
        <label style="font-weight:bold; font-size:0.85rem; display:block; margin-bottom:5px;">Your Full Name</label>
        <input type="text" name="full_name" placeholder="John Doe" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #dfe3e8; border-radius:6px; box-sizing:border-box;">

        <label style="font-weight:bold; font-size:0.85rem; display:block; margin-bottom:5px;">Email Address</label>
        <input type="email" name="email" placeholder="parent@email.com" required style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #dfe3e8; border-radius:6px; box-sizing:border-box;">

        <label style="font-weight:bold; font-size:0.85rem; display:block; margin-bottom:5px;">Create Password</label>
        <input type="password" name="password" placeholder="********" required style="width:100%; padding:10px; margin-bottom:20px; border:1px solid #dfe3e8; border-radius:6px; box-sizing:border-box;">

        <button type="submit" style="width:100%; padding:12px; background:#FF6600; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">Register & Link Account</button>
    </form>
    
    <div style="text-align:center; margin-top:20px;">
        <a href="index.php" style="color:#637381; text-decoration:none; font-size:0.9rem;">Already have an account? Login</a>
    </div>
</div>

</body>
</html>