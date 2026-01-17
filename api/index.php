<?php
// 1. FORCE COOKIE SETTINGS FOR VERCEL
// This tells the browser: "This session is valid for the WHOLE website (/)"
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',           // <--- THIS IS THE KEY FIX
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,        // Required for Vercel (HTTPS)
    'httponly' => true,
    'samesite' => 'Lax'
]);

// 2. NOW start the session
session_start();

require __DIR__ . '/../config/db.php';

// ... rest of your existing logic ...

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Now when we set these, the cookie will actually "stick"
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['full_name'];

        // 3. DEBUG: Uncomment this to verify before redirecting
        // echo "Session saved! ID: " . $_SESSION['user_id']; exit;

        // 4. REDIRECT (Use absolute paths for safety)
        if ($user['role'] === 'admin') {
            header("Location: /admin/dashboard.php");
        } elseif ($user['role'] === 'teacher') {
            header("Location: /teacher/dashboard.php");
        } elseif ($user['role'] === 'student') {
            header("Location: /student/dashboard.php");
        } elseif ($user['role'] === 'parent') {
            header("Location: /parent/dashboard.php");
        }
        exit;
    } else {
        $error = "Invalid email or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Bridge | New Generation Academy</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
<?php include __DIR__ . '/../includes/preloader.php'; ?>
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
                    <img src="/assets/images/logo.png" alt="NGA Logo" class="school-logo fire-glow">
                </div>
            </div>

            <div class="form-header">
                <h2>Welcome Back</h2>
                <p>Login to access your personalized dashboard.</p>
            </div>
            <a href="setup_admin.php">
                <button>Click here to set admin</button>
            </a>
            <?php if($error): ?>
                <div style="background:#fee2e2; color:#b91c1c; padding:10px; border-radius:8px; margin-bottom:15px; text-align:center;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="/" method="POST">
                <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                <input type="password" name="password" class="form-control" placeholder="Password" required>
                
                <button type="submit" class="btn-login">Sign In</button>
            </form>
            
            <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #f0f0f0;">
                <p style="color: #666; margin-bottom: 10px;">New student or parent?</p>
                <a href="/activate.php" style="text-decoration: none;">
                    <button style="background: white; border: 2px solid #FF6600; color: #FF6600; padding: 10px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%;">
                        Activate Student Account
                    </button><br><br>
                </a>
                <a href="/parent-register.php">
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