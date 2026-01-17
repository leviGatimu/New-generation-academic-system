<?php
// parent/notifications.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') { header("Location: ../index.php"); exit; }

$parent_id = $_SESSION['user_id'];

// 1. Mark all as read when visiting this page
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$parent_id]);

// 2. Fetch Notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$parent_id]);
$notifs = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Notifications | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* Reusing your standard Dashboard CSS */
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #fff; --nav-height: 75px; }
        body { background: var(--light-bg); margin: 0; font-family: 'Public Sans', sans-serif; }
        
        .top-navbar { position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height); background: var(--white); z-index: 1000; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; border-bottom: 1px solid #dfe3e8; box-sizing: border-box; }
        .main-content { margin-top: var(--nav-height); padding: 40px 20%; } /* Centered layout */

        .notif-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid #dfe3e8; margin-bottom: 15px; display: flex; gap: 15px; align-items: start; }
        .notif-icon { min-width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: white; }
        
        .type-fee { background: #ff4d4f; }
        .type-mark { background: #00ab55; }
        .type-message { background: #007bff; }
        .type-system { background: #FF6600; }
        
        .time { font-size: 0.8rem; color: #637381; margin-top: 5px; display: block; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" style="text-decoration:none; color:#212b36; font-weight:800; font-size:1.2rem;">
        <i class='bx bx-arrow-back'></i> Back to Dashboard
    </a>
    <span style="font-weight:bold; color:#FF6600;">Notifications</span>
</nav>

<div class="main-content">
    <h2 style="margin-bottom:30px; color:var(--dark);">Recent Alerts</h2>

    <?php if(count($notifs) > 0): ?>
        <?php foreach($notifs as $n): ?>
            <div class="notif-card">
                <div class="notif-icon type-<?php echo $n['type']; ?>">
                    <?php 
                        if($n['type'] == 'fee') echo "<i class='bx bxs-wallet'></i>";
                        elseif($n['type'] == 'mark') echo "<i class='bx bxs-graduation'></i>";
                        elseif($n['type'] == 'message') echo "<i class='bx bxs-chat'></i>";
                        else echo "<i class='bx bxs-bell'></i>";
                    ?>
                </div>
                <div>
                    <div style="font-size:1rem; color:#212b36; font-weight:600;"><?php echo $n['message']; ?></div>
                    <span class="time"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align:center; padding:50px; color:#637381;">
            <i class='bx bx-bell-off' style="font-size:3rem; opacity:0.5;"></i>
            <p>No new notifications.</p>
        </div>
    <?php endif; ?>
</div>
<script>
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
</script>
</body>
</html>