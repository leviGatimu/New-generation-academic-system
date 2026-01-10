<?php
// activate.php
session_start();
require 'config/db.php';

$step = 1;
$error = '';
$msg = '';

// --- STEP 1: VALIDATE KEY & "SEND" OTP ---
if (isset($_POST['step1'])) {
    $key = trim($_POST['access_key']);
    $email = trim($_POST['email']);
    
    // Check if key exists and isn't already used (email is usually NULL or empty for new users)
    // We check 'email IS NULL' to ensure the account wasn't already activated
    $stmt = $pdo->prepare("SELECT * FROM users WHERE access_key = :key AND (email IS NULL OR email = '')");
    $stmt->execute(['key' => $key]);
    $user = $stmt->fetch();

    if ($user) {
        // Generate 6-Digit OTP
        $otp = rand(100000, 999999);
        
        // Save OTP to DB so we can verify it in Step 2
        $update = $pdo->prepare("UPDATE users SET email_otp = :otp WHERE user_id = :uid");
        $update->execute(['otp' => $otp, 'uid' => $user['user_id']]);

        // Save State to Session
        $_SESSION['activate_user_id'] = $user['user_id'];
        $_SESSION['activate_email'] = $email;
        $_SESSION['activate_role'] = $user['role']; // Just for info

        // SIMULATE EMAIL (Javascript Alert)
        $js_code = "<script>alert('SIMULATED EMAIL:\\n\\nHello, your Verification Code is: $otp');</script>";
        echo $js_code;

        $msg = "We have sent a verification code to <strong>$email</strong>.";
        $step = 2;
    } else {
        $error = "Invalid Key, or this account is already active.";
    }
}

// --- STEP 2: VERIFY OTP & SET PASSWORD ---
if (isset($_POST['step2'])) {
    $otp_input = trim($_POST['otp']);
    $pass = trim($_POST['password']);
    $confirm = trim($_POST['confirm_password']);
    $user_id = $_SESSION['activate_user_id'];
    $email = $_SESSION['activate_email'];

    if ($pass !== $confirm) {
        $error = "Passwords do not match.";
        $step = 2;
    } else {
        // Verify OTP from DB
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :uid AND email_otp = :otp");
        $stmt->execute(['uid' => $user_id, 'otp' => $otp_input]);
        $check = $stmt->fetch();

        if ($check) {
            // SUCCESS: Update Account
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            
            // 1. Set Email & Password
            // 2. Clear OTP
            // 3. Clear Access Key (Security: Key can't be used again)
            $sql = "UPDATE users SET email = :email, password = :pass, email_otp = NULL, access_key = NULL WHERE user_id = :uid";
            $pdo->prepare($sql)->execute(['email' => $email, 'pass' => $hashed_pass, 'uid' => $user_id]);

            // Cleanup
            session_destroy();
            
            echo "<script>
                alert('Account Activated! You can now login.');
                window.location.href='index.php';
            </script>";
            exit;
        } else {
            $error = "Incorrect Code. Please check the alert box again.";
            $step = 2;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activate Account | NGA</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body style="background: #f4f6f9; display: flex; justify-content: center; align-items: center; min-height: 100vh;">

<div class="login-wrapper" style="width: 100%; max-width: 450px; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    
    <div style="text-align: center; margin-bottom: 20px;">
        <img src="assets/images/logo.png" style="height: 60px;">
        <h2 style="color: #2c3e50; margin-top: 10px;">Activate Account</h2>
        <p style="color: #7f8c8d;">New Generation Academy</p>
    </div>

    <?php if($error): ?>
        <div style="background:#fee2e2; color:#b91c1c; padding:10px; border-radius:6px; margin-bottom:15px; text-align:center;">
            <i class='bx bxs-error-circle'></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if($msg): ?>
        <div style="background:#d1e7dd; color:#0f5132; padding:10px; border-radius:6px; margin-bottom:15px; text-align:center;">
            <?php echo $msg; ?>
        </div>
    <?php endif; ?>

    <?php if($step == 1): ?>
        <form method="POST">
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="font-weight: bold; color: #2c3e50;">Access Key</label>
                <input type="text" name="access_key" class="form-control" placeholder="e.g. NGA-1234" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                <small style="color: #999;">Provided by your Admin or Teacher</small>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: bold; color: #2c3e50;">Your Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="student@gmail.com" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>

            <button type="submit" name="step1" class="btn-login" style="width: 100%; padding: 12px; font-size: 1rem;">
                Send Verification Code
            </button>
        </form>
    <?php endif; ?>

    <?php if($step == 2): ?>
        <form method="POST">
            <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid var(--primary-orange);">
                <small>Check your screen (or simulated email) for the code.</small>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="font-weight: bold;">Verification Code (OTP)</label>
                <input type="text" name="otp" class="form-control" placeholder="6-Digit Code" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; letter-spacing: 3px; font-weight: bold; text-align: center;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="font-weight: bold;">Create Password</label>
                <input type="password" name="password" class="form-control" placeholder="New Password" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: bold;">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat Password" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
            </div>

            <button type="submit" name="step2" class="btn-login" style="width: 100%; padding: 12px; font-size: 1rem;">
                Activate & Login
            </button>
        </form>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 20px;">
        <a href="index.php" style="color: #777; text-decoration: none; font-size: 0.9rem;">&larr; Back to Login</a>
    </div>

</div>

</body>
</html>