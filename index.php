<?php
// index.php
session_start();
require 'config/db.php';

$error = '';
// Handle Login Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // We check all users regardless of role button clicked
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['full_name'];

        // Redirect Logic
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
                Welcome to the official <strong>Academic</strong> system. 
                Manage results, track attendance, and monitor student growth in real-time.
                Empowering the future leaders of Rwanda through technology and excellence.
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

            <div class="role-tabs">
                <div class="role-tab active" onclick="setRole('student')">Student</div>
                <div class="role-tab" onclick="setRole('teacher')">Teacher</div>
                <div class="role-tab" onclick="setRole('parent')">Parent</div>
                <div class="role-tab" onclick="setRole('admin')">Admin</div>
            </div>

            <div class="form-header">
                <h2 id="login-title">Student Login</h2>
                <p>Please enter your credentials to access the portal.</p>
            </div>

            <?php if($error): ?>
                <div style="background:#fee2e2; color:#b91c1c; padding:10px; border-radius:8px; margin-bottom:15px; text-align:center;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="index.php" method="POST">
                <input type="hidden" name="login_role" id="login_role" value="student">
                
                <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                <input type="password" name="password" class="form-control" placeholder="Password" required>
                
                <button type="submit" class="btn-login">Access Dashboard</button>
            </form>
            
            <div style="text-align: center; margin-top: 25px; padding-top: 20px; border-top: 1px solid #f0f0f0;">
                <p style="color: #666; margin-bottom: 10px;">Don't have an account?</p>
                <a href="activate.php" style="text-decoration: none;">
                    <button style="background: white; border: 2px solid var(--primary-orange); color: var(--primary-orange); padding: 10px 25px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: all 0.3s; width: 100%;">
                        Register (Activate Account)
                    </button>
                </a>
            </div>

            <p style="text-align:center; margin-top:30px; color:#ccc; font-size:0.8rem;">
                &copy; <?php echo date("Y"); ?> New Generation Academy.
            </p>
        </div>
    </div>

</div>

<script>
    function setRole(role) {
        // 1. Update Buttons
        document.querySelectorAll('.role-tab').forEach(el => el.classList.remove('active'));
        event.target.classList.add('active');

        // 2. Update Title
        const titleMap = {
            'student': 'Student Login',
            'teacher': 'Teacher Portal',
            'parent': 'Parent Access',
            'admin': 'Admin Control'
        };
        document.getElementById('login-title').innerText = titleMap[role];
        document.getElementById('login_role').value = role;
    }
</script>

</body>
</html>