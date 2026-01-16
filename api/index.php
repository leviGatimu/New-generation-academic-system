<?php
// index.php
session_start();
require 'config/db.php';

$error = '';

// Handle Login Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // The query remains the same—it finds the user regardless of their role
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['full_name'];

        // Automatic Redirect based on the role found in the DB
        $destinations = [
            'admin' => 'admin/dashboard.php',
            'teacher' => 'teacher/dashboard.php',
            'student' => 'student/dashboard.php',
            'parent' => 'parent/dashboard.php'
        ];
        
        if(array_key_exists($user['role'], $destinations)){
            header("Location: " . $destinations[$user['role']]);
            exit;
        }
    } else {
        $error = "Invalid credentials. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Bridge | New Generation Academy</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
<?php include 'includes/preloader.php'; ?>
<div class="main-container">
    
    <div class="info-section">
        <div>
            <div class="school-title">New Generation Academy</div>
            <p class="school-desc">
                Welcome to the official <strong>Academic Bridge</strong>. 
                A unified system for results, attendance, and growth tracking.
            </p>
            <ul class="feature-list">
                <li>Real-time Academic Reports</li>
                <li>Digital Attendance Tracking</li>
                <li>Parent-Teacher Communication</li>
                <li>Secure & Private Data</li>
            </ul>
        </div>
    </div>

    <div class="login-section">
        <div class="login-wrapper">
            
            <div style="text-align: center; margin-bottom: 20px;">
                <div class="logo-area">
                    <img src="assets/images/logo.png" alt="NGA Logo" class="school-logo fire-glow">
                </div>
            </div>

            <div class="form-header">
                <h2>Welcome Back</h2>
                <p>Login to access your personalized dashboard.</p>
            </div>

            <?php if($error): ?>
                <div style="background:#fee2e2; color:#b91c1c; padding:10px; border-radius:8px; margin-bottom:15px; text-align:center;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="index.php" method="POST">
                <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                <input type="password" name="password" class="form-control" placeholder="Password" required>
                
                <button type="submit" class="btn-login">Sign In</button>
            </form>
            
            <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #f0f0f0;">
                <p style="color: #666; margin-bottom: 10px;">New student or parent?</p>
                <a href="activate.php" style="text-decoration: none;">
                    <button style="background: white; border: 2px solid #FF6600; color: #FF6600; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%;">
                        Activate Student Account
                    </button><br><br>
                </a>
                <a href="parent-register.php">
                    <button style="background: white; border: 2px solid #FF6600; color: #FF6600; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%;">
                        Activate Parent Account
                    </button>
                </a>
            </div>

            <p style="text-align:center; margin-top:30px; color:#ccc; font-size:0.8rem;">
                &copy; <?php echo date("Y"); ?> New Generation Academy.
            </p>
        </div>
    </div>
</div>
</body>
</html>