<?php
// api/admin/dashboard.php

// 1. SESSION SETUP (Safer Version)
// We removed 'domain' to prevent conflicts. 
// This MUST match the login page exactly.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',           // Critical: Allows sharing session across folders
    'secure' => true,        // Required for Vercel
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// 2. SECURITY CHECK
// If user is not logged in OR not an admin, send them back to login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /"); 
    exit;
}

// 3. DATABASE CONNECTION
require __DIR__ . '/../../config/db.php';

// Initialize variables
$admin_id = $_SESSION['user_id'];
$message = "";
$msg_type = "";

// --- HANDLE ANNOUNCEMENT BROADCAST ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['broadcast_msg'])) {
    $txt = trim($_POST['message']);
    
    if (!empty($txt)) {
        try {
            $classes = $pdo->query("SELECT class_id FROM classes")->fetchAll(PDO::FETCH_COLUMN);
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, class_id, message, msg_type) VALUES (?, ?, ?, 'system')");
            
            $count = 0;
            foreach ($classes as $class_id) {
                $formatted_msg = "📢 SYSTEM NOTICE: " . $txt;
                $stmt->execute([$admin_id, $class_id, $formatted_msg]);
                $count++;
            }
            
            $message = "Broadcast sent successfully to $count classes.";
            $msg_type = "success";
        } catch (PDOException $e) {
            $message = "Error sending broadcast: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

// --- FETCH DASHBOARD DATA ---
// We use try-catch to prevent crashing if tables are empty
try {
    $student_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    $teacher_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
    $parent_count  = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'parent'")->fetchColumn();
    $class_count   = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

    $new_users = $pdo->query("SELECT full_name, role, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // Recent System Broadcasts
    $recent_broadcasts = $pdo->prepare("SELECT DISTINCT message, created_at FROM messages WHERE sender_id = ? AND msg_type = 'system' ORDER BY created_at DESC LIMIT 3");
    $recent_broadcasts->execute([$admin_id]);
    $broadcasts = $recent_broadcasts->fetchAll();
} catch (PDOException $e) {
    $student_count = 0; $teacher_count = 0; $parent_count = 0; $class_count = 0;
    $message = "Database Error: " . $e->getMessage();
    $msg_type = "error";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | NGA</title>
    </head>
<body>
    <h1>Welcome Admin</h1>
    </body>
</html>