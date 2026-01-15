<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$student_id = $_SESSION['user_id'];
$active_group_id = $_GET['group_id'] ?? null;
$active_dm_id = $_GET['dm_id'] ?? null;

// 1. Get My Class Info
$stmt = $pdo->prepare("SELECT s.class_id, c.class_name 
                       FROM students s 
                       JOIN classes c ON s.class_id = c.class_id 
                       WHERE s.student_id = ?");
$stmt->execute([$student_id]);
$my_class = $stmt->fetch();
$class_id = $my_class['class_id'];

// 2. Fetch Classmates (For DMs)
// Get everyone in my class EXCEPT me
$mates_sql = "SELECT u.user_id, u.full_name, s.class_role 
              FROM students s 
              JOIN users u ON s.student_id = u.user_id 
              WHERE s.class_id = ? AND s.student_id != ? 
              ORDER BY u.full_name";
$mates_stmt = $pdo->prepare($mates_sql);
$mates_stmt->execute([$class_id, $student_id]);
$classmates = $mates_stmt->fetchAll();

// 3. Handle Sending Messages
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $msg = trim($_POST['message']);
    if ($msg) {
        if ($active_group_id) {
            // Group Message
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, class_id, message, msg_type) VALUES (?, ?, ?, 'text')");
            $stmt->execute([$student_id, $class_id, $msg]);
        } elseif ($active_dm_id) {
            // DM
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, msg_type) VALUES (?, ?, ?, 'text')");
            $stmt->execute([$student_id, $active_dm_id, $msg]);
        }
        header("Location: " . $_SERVER['REQUEST_URI']); exit;
    }
}

// 4. Fetch Chat History
$chat_history = [];
$chat_title = "Select a conversation";

if ($active_group_id) {
    // Class Group Chat
    $chat_title = $my_class['class_name'] . " Group Chat";
    $stmt = $pdo->prepare("SELECT m.*, u.full_name, s.class_role 
                           FROM messages m 
                           JOIN users u ON m.sender_id = u.user_id 
                           LEFT JOIN students s ON u.user_id = s.student_id
                           WHERE m.class_id = ? 
                           ORDER BY m.created_at ASC");
    $stmt->execute([$class_id]);
    $chat_history = $stmt->fetchAll();

} elseif ($active_dm_id) {
    // Direct Message
    // Find name of person we are chatting with
    foreach($classmates as $mate) { if($mate['user_id'] == $active_dm_id) $chat_title = $mate['full_name']; }
    
    $stmt = $pdo->prepare("SELECT m.*, u.full_name 
                           FROM messages m 
                           JOIN users u ON m.sender_id = u.user_id 
                           WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                              OR (m.sender_id = ? AND m.receiver_id = ?) 
                           ORDER BY m.created_at ASC");
    $stmt->execute([$student_id, $active_dm_id, $active_dm_id, $student_id]);
    $chat_history = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Class Chat | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #fff; --border: #dfe3e8; }
        body { margin: 0; background: var(--light-bg); font-family: 'Public Sans', sans-serif; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        .top-navbar { height: 60px; background: var(--white); border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 20px; flex-shrink: 0; }
        .nav-link { text-decoration: none; color: var(--dark); font-weight: 700; display: flex; align-items: center; gap: 5px; }

        .chat-layout { display: flex; flex: 1; height: calc(100vh - 60px); }
        
        /* Sidebar */
        .sidebar { width: 280px; background: var(--white); border-right: 1px solid var(--border); overflow-y: auto; display: flex; flex-direction: column; }
        .sb-header { padding: 15px; font-size: 0.75rem; font-weight: 800; color: #919eab; text-transform: uppercase; background: #fafbfc; border-bottom: 1px solid var(--border); }
        
        .chat-item { padding: 12px 15px; display: flex; align-items: center; gap: 10px; cursor: pointer; text-decoration: none; color: var(--dark); border-bottom: 1px solid #f9fafb; transition: 0.1s; }
        .chat-item:hover, .chat-item.active { background: #fff5f0; border-left: 3px solid var(--primary); }
        
        .avatar { width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; flex-shrink: 0; font-size: 0.9rem; }
        .av-group { background: linear-gradient(135deg, #FF6600 0%, #ff8533 100%); }
        .av-user { background: #637381; }

        /* Roles in list */
        .role-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-left: 5px; }
        .r-pres { background: #faad14; box-shadow: 0 0 5px #faad14; }

        /* Chat Area */
        .chat-box { flex: 1; display: flex; flex-direction: column; background: #eef2f5; }
        .chat-header { padding: 15px 20px; background: var(--white); border-bottom: 1px solid var(--border); font-weight: 800; color: var(--dark); }
        
        .messages-area { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; }
        
        .msg-row { display: flex; width: 100%; margin-bottom: 5px; }
        .msg-bubble { max-width: 70%; padding: 10px 14px; border-radius: 12px; font-size: 0.9rem; line-height: 1.4; box-shadow: 0 1px 2px rgba(0,0,0,0.05); position: relative; }
        
        .sent { justify-content: flex-end; }
        .sent .msg-bubble { background: var(--primary); color: white; border-bottom-right-radius: 2px; }
        
        .received { justify-content: flex-start; }
        .received .msg-bubble { background: var(--white); color: var(--dark); border-bottom-left-radius: 2px; }
        
        .sender-info { font-size: 0.7rem; font-weight: 700; margin-bottom: 2px; display: block; color: var(--primary); }
        .sys-msg { align-self: center; background: #e3f2fd; color: #007bff; padding: 5px 15px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-align: center; border: 1px solid #bbdefb; margin: 10px 0; }

        .input-area { padding: 15px; background: var(--white); border-top: 1px solid var(--border); display: flex; gap: 10px; }
        .input-field { flex: 1; padding: 10px 15px; border: 1px solid var(--border); border-radius: 20px; outline: none; }
        .send-btn { background: var(--primary); color: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-link"><i class='bx bx-arrow-back'></i> Dashboard</a>
    <span style="font-weight:800; font-size:1.1rem; margin-left:15px; color:var(--dark);">Messages</span>
</nav>

<div class="chat-layout">
    <div class="sidebar">
        <div class="sb-header">Classroom</div>
        <a href="?group_id=1" class="chat-item <?php echo $active_group_id ? 'active' : ''; ?>">
            <div class="avatar av-group"><i class='bx bxs-group'></i></div>
            <div style="font-weight:700;"><?php echo htmlspecialchars($my_class['class_name']); ?></div>
        </a>

        <div class="sb-header">Classmates</div>
        <?php foreach($classmates as $mate): ?>
            <a href="?dm_id=<?php echo $mate['user_id']; ?>" class="chat-item <?php echo $active_dm_id == $mate['user_id'] ? 'active' : ''; ?>">
                <div class="avatar av-user"><?php echo substr($mate['full_name'], 0, 1); ?></div>
                <div>
                    <div style="font-size:0.9rem; font-weight:600;">
                        <?php echo htmlspecialchars($mate['full_name']); ?>
                        <?php if($mate['class_role'] == 'President') echo '<span class="role-dot r-pres" title="President"></span>'; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="chat-box">
        <div class="chat-header"><?php echo htmlspecialchars($chat_title); ?></div>
        
        <div class="messages-area" id="msgArea">
            <?php if(empty($chat_history)): ?>
                <div style="text-align:center; margin-top:50px; color:#919eab;">Start a conversation!</div>
            <?php else: ?>
                <?php foreach($chat_history as $msg): ?>
                    <?php if($msg['msg_type'] == 'system'): ?>
                        <div class="sys-msg"><i class='bx bxs-megaphone'></i> <?php echo $msg['message']; ?></div>
                    
                    <?php else: $is_me = ($msg['sender_id'] == $student_id); ?>
                        <div class="msg-row <?php echo $is_me ? 'sent' : 'received'; ?>">
                            <div class="msg-bubble">
                                <?php if(!$is_me && $active_group_id): ?>
                                    <span class="sender-info">
                                        <?php echo htmlspecialchars($msg['full_name']); ?>
                                        <?php if(isset($msg['class_role']) && $msg['class_role'] == 'President') echo '👑'; ?>
                                    </span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($msg['message']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if($active_group_id || $active_dm_id): ?>
        <form method="POST" class="input-area">
            <input type="text" name="message" class="input-field" placeholder="Type a message..." required autocomplete="off">
            <button type="submit" class="send-btn"><i class='bx bxs-send'></i></button>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
    var box = document.getElementById("msgArea");
    box.scrollTop = box.scrollHeight;
</script>

</body>
</html>