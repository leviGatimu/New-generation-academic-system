<?php
// parent/messages.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') { header("Location: ../index.php"); exit; }

$parent_id = $_SESSION['user_id'];
// Fetch teachers
$teachers = $pdo->query("SELECT user_id, full_name FROM users WHERE role = 'teacher'")->fetchAll();

// Handle Sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send'])) {
    $receiver = $_POST['receiver_id'];
    $text = trim($_POST['message']);
    if($text) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$parent_id, $receiver, $text]);
        header("Location: messages.php?chat=" . $receiver); exit;
    }
}

$active_id = $_GET['chat'] ?? ($teachers[0]['user_id'] ?? 0);

// Fetch Chat
$chats = [];
if ($active_id) {
    // Mark read
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")->execute([$active_id, $parent_id]);

    $stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
    $stmt->execute([$parent_id, $active_id, $active_id, $parent_id]);
    $chats = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Messages | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --blue: #007bff; --white: #fff; --light: #f4f6f8; --dark-text: #212b36; --nav-height: 75px; }
        body { margin: 0; background: var(--light); font-family: 'Public Sans', sans-serif; height: 100vh; display: flex; flex-direction: column; }
        
        /* Navbar */
        .top-navbar { height: var(--nav-height); background: var(--white); border-bottom: 1px solid #dfe3e8; display: flex; align-items: center; justify-content: space-between; padding: 0 40px; flex-shrink: 0; }
        .nav-link { text-decoration: none; color: var(--dark-text); font-weight: bold; display: flex; align-items: center; gap: 5px; }

        .chat-container { flex: 1; display: flex; overflow: hidden; }
        
        /* Sidebar Fix: Explicit Color */
        .sidebar { width: 320px; background: var(--white); border-right: 1px solid #dfe3e8; overflow-y: auto; }
        .user-item { 
            padding: 15px 20px; 
            display: flex; align-items: center; gap: 15px; 
            border-bottom: 1px solid #f9fafb; 
            cursor: pointer; 
            text-decoration: none; 
            color: #212b36 !important; /* FORCED DARK COLOR */
            transition: 0.2s; 
        }
        .user-item:hover, .user-item.active { background: #fff7e6; }
        .avatar { width: 45px; height: 45px; background: #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #555; }
        .active .avatar { background: var(--primary); color: white; }

        /* Chat Area */
        .chat-area { flex: 1; display: flex; flex-direction: column; background: #e5ddd5; position: relative; }
        .messages-box { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
        
        .msg { max-width: 65%; padding: 10px 15px; border-radius: 8px; font-size: 0.95rem; line-height: 1.4; position: relative; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .sent { align-self: flex-end; background: var(--primary); color: white; border-top-right-radius: 0; }
        .received { align-self: flex-start; background: var(--blue); color: white; border-top-left-radius: 0; }
        .time { font-size: 0.7rem; opacity: 0.8; text-align: right; display: block; margin-top: 5px; }

        .input-area { background: var(--white); padding: 15px; border-top: 1px solid #dfe3e8; display: flex; gap: 10px; }
        .input-field { flex: 1; padding: 12px; border: 1px solid #dfe3e8; border-radius: 25px; outline: none; }
        .send-btn { background: var(--primary); color: white; border: none; padding: 0 25px; border-radius: 25px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-link"><i class='bx bx-arrow-back'></i> Back to Dashboard</a>
    <span style="font-weight:800; color:var(--dark-text);">Teacher Messages</span>
</nav>

<div class="chat-container">
    <div class="sidebar">
        <div style="padding:15px; font-weight:bold; color:#637381; font-size:0.8rem; background:#f9fafb;">CONTACTS</div>
        <?php foreach($teachers as $t): ?>
            <a href="messages.php?chat=<?php echo $t['user_id']; ?>" class="user-item <?php echo $active_id == $t['user_id'] ? 'active' : ''; ?>">
                <div class="avatar"><?php echo substr($t['full_name'], 0, 1); ?></div>
                <div>
                    <div style="font-weight:bold;"><?php echo $t['full_name']; ?></div>
                    <div style="font-size:0.8rem; color:#637381;">Class Teacher</div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="chat-area">
        <div class="messages-box" id="msgBox">
            <?php if(count($chats) > 0): ?>
                <?php foreach($chats as $c): ?>
                    <div class="msg <?php echo $c['sender_id'] == $parent_id ? 'sent' : 'received'; ?>">
                        <?php echo htmlspecialchars($c['message']); ?>
                        <span class="time"><?php echo date('H:i', strtotime($c['created_at'])); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; margin-top:50px; color:#666;">
                    <i class='bx bxs-chat' style="font-size:3rem; opacity:0.3;"></i>
                    <p>Start a conversation with this teacher.</p>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" class="input-area">
            <input type="hidden" name="receiver_id" value="<?php echo $active_id; ?>">
            <input type="text" name="message" class="input-field" placeholder="Type a message..." autocomplete="off" required>
            <button type="submit" name="send" class="send-btn"><i class='bx bxs-send'></i></button>
        </form>
    </div>
</div>

<script>
    var box = document.getElementById('msgBox');
    box.scrollTop = box.scrollHeight;
</script>
<script>
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
</script>
</body>
</html>