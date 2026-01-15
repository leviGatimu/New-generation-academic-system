<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header("Location: ../index.php"); exit; }

$teacher_id = $_SESSION['user_id'];
$active_class_id = $_GET['class_id'] ?? null;
$active_user_id = $_GET['user_id'] ?? null;

// 1. Fetch Teacher's Classes (For Group Chats)
// (Assuming teacher_allocations table links teachers to classes)
$groups = $pdo->query("SELECT DISTINCT c.class_id, c.class_name 
                       FROM classes c 
                       JOIN teacher_allocations ta ON c.class_id = ta.class_id 
                       WHERE ta.teacher_id = $teacher_id")->fetchAll();

// 2. Fetch Direct Message Contacts (Parents/Students who have messaged this teacher)
// This query finds unique users who exchanged messages with this teacher
$dm_sql = "SELECT DISTINCT u.user_id, u.full_name, u.role 
           FROM messages m 
           JOIN users u ON (m.sender_id = u.user_id OR m.receiver_id = u.user_id)
           WHERE (m.sender_id = ? OR m.receiver_id = ?) AND u.user_id != ? AND m.class_id IS NULL";
$dm_stmt = $pdo->prepare($dm_sql);
$dm_stmt->execute([$teacher_id, $teacher_id, $teacher_id]);
$dms = $dm_stmt->fetchAll();

// 3. Handle Sending Message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_msg'])) {
    $msg = trim($_POST['message']);
    $cls_id = $_POST['target_class_id'] ?? null;
    $usr_id = $_POST['target_user_id'] ?? null;

    if ($msg) {
        if ($cls_id) {
            // Send to Group
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, class_id, message, msg_type) VALUES (?, ?, ?, 'text')");
            $stmt->execute([$teacher_id, $cls_id, $msg]);
            header("Location: messages.php?class_id=$cls_id");
        } elseif ($usr_id) {
            // Send DM
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, msg_type) VALUES (?, ?, ?, 'text')");
            $stmt->execute([$teacher_id, $usr_id, $msg]);
            header("Location: messages.php?user_id=$usr_id");
        }
        exit;
    }
}

// 4. Fetch Chat History
$chat_history = [];
$chat_title = "Select a conversation";

if ($active_class_id) {
    // Get Group Chat
    $stmt = $pdo->prepare("SELECT m.*, u.full_name, u.role FROM messages m JOIN users u ON m.sender_id = u.user_id WHERE m.class_id = ? ORDER BY m.created_at ASC");
    $stmt->execute([$active_class_id]);
    $chat_history = $stmt->fetchAll();
    
    // Get Class Name
    foreach($groups as $g) { if($g['class_id'] == $active_class_id) $chat_title = $g['class_name'] . " Group Chat"; }

} elseif ($active_user_id) {
    // Get DM Chat
    $stmt = $pdo->prepare("SELECT m.*, u.full_name FROM messages m JOIN users u ON m.sender_id = u.user_id 
                           WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) 
                           ORDER BY m.created_at ASC");
    $stmt->execute([$teacher_id, $active_user_id, $active_user_id, $teacher_id]);
    $chat_history = $stmt->fetchAll();
    
    foreach($dms as $d) { if($d['user_id'] == $active_user_id) $chat_title = $d['full_name']; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Messages | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --light: #f4f6f8; --dark: #212b36; --white: #fff; --border: #dfe3e8; }
        body { margin: 0; background: var(--light); font-family: 'Public Sans', sans-serif; height: 100vh; display: flex; flex-direction: column; }
        
        /* Navbar */
        .top-navbar { height: 70px; background: var(--white); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 40px; flex-shrink: 0; }
        .nav-link { text-decoration: none; color: var(--dark); font-weight: bold; display: flex; align-items: center; gap: 5px; }

        /* Layout */
        .chat-layout { display: flex; flex: 1; overflow: hidden; }
        
        /* Sidebar */
        .sidebar { width: 300px; background: var(--white); border-right: 1px solid var(--border); display: flex; flex-direction: column; }
        .list-header { padding: 15px 20px; font-size: 0.85rem; font-weight: 800; color: #637381; text-transform: uppercase; background: #fafbfc; border-bottom: 1px solid var(--border); border-top: 1px solid var(--border); }
        .list-header:first-child { border-top: none; }
        
        .chat-item { padding: 15px 20px; display: flex; align-items: center; gap: 12px; cursor: pointer; text-decoration: none; color: var(--dark); transition: 0.2s; border-bottom: 1px solid #f9fafb; }
        .chat-item:hover, .chat-item.active { background: #fff5f0; }
        
        .avatar { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; flex-shrink: 0; }
        .av-group { background: #00ab55; }
        .av-user { background: #007bff; }
        
        /* Chat Area */
        .chat-box { flex: 1; display: flex; flex-direction: column; background: #eef2f5; }
        .chat-header { padding: 15px 25px; background: var(--white); border-bottom: 1px solid var(--border); font-weight: 800; color: var(--dark); font-size: 1.1rem; }
        
        .messages-area { flex: 1; padding: 25px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; }
        
        /* Message Bubbles */
        .msg-row { display: flex; width: 100%; }
        .msg-bubble { max-width: 60%; padding: 12px 18px; border-radius: 12px; font-size: 0.95rem; line-height: 1.5; position: relative; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .sender-name { font-size: 0.75rem; font-weight: 700; margin-bottom: 4px; display: block; opacity: 0.8; }
        
        /* Styles based on sender */
        .sent { justify-content: flex-end; }
        .sent .msg-bubble { background: var(--primary); color: white; border-bottom-right-radius: 2px; }
        
        .received { justify-content: flex-start; }
        .received .msg-bubble { background: var(--white); color: var(--dark); border-bottom-left-radius: 2px; }
        .received .sender-name { color: var(--primary); }

        /* System Notification Style */
        .system-msg { align-self: center; background: #e3f2fd; color: #0d47a1; padding: 8px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-align: center; border: 1px solid #bbdefb; margin: 10px 0; width: fit-content; margin-left: auto; margin-right: auto; }

        /* Input Area */
        .input-area { padding: 20px; background: var(--white); border-top: 1px solid var(--border); display: flex; gap: 10px; }
        .input-field { flex: 1; padding: 12px 15px; border: 1px solid var(--border); border-radius: 25px; outline: none; }
        .send-btn { background: var(--primary); color: white; border: none; width: 45px; height: 45px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-link"><i class='bx bx-arrow-back'></i> Dashboard</a>
    <span style="font-weight:800; font-size:1.2rem; color:var(--primary);">Teacher Communication Hub</span>
</nav>

<div class="chat-layout">
    
    <div class="sidebar">
        <div class="list-header">Class Groups</div>
        <?php foreach($groups as $g): ?>
            <a href="messages.php?class_id=<?php echo $g['class_id']; ?>" class="chat-item <?php echo $active_class_id == $g['class_id'] ? 'active' : ''; ?>">
                <div class="avatar av-group"><i class='bx bxs-group'></i></div>
                <div><?php echo $g['class_name']; ?></div>
            </a>
        <?php endforeach; ?>

        <div class="list-header">Direct Messages</div>
        <?php foreach($dms as $d): ?>
            <a href="messages.php?user_id=<?php echo $d['user_id']; ?>" class="chat-item <?php echo $active_user_id == $d['user_id'] ? 'active' : ''; ?>">
                <div class="avatar av-user"><?php echo substr($d['full_name'], 0, 1); ?></div>
                <div>
                    <div><?php echo $d['full_name']; ?></div>
                    <small style="color:#999;"><?php echo ucfirst($d['role']); ?></small>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="chat-box">
        <div class="chat-header">
            <?php echo $chat_title; ?>
        </div>

        <div class="messages-area" id="msgArea">
            <?php if(empty($chat_history)): ?>
                <div style="text-align:center; margin-top:50px; color:#999;">No messages yet. Start the conversation!</div>
            <?php else: ?>
                <?php foreach($chat_history as $msg): ?>
                    
                    <?php if($msg['msg_type'] == 'system'): ?>
                        <div class="system-msg">
                            <i class='bx bxs-bell-ring'></i> <?php echo htmlspecialchars($msg['message']); ?>
                        </div>
                    
                    <?php else: 
                        $is_me = ($msg['sender_id'] == $teacher_id);
                    ?>
                        <div class="msg-row <?php echo $is_me ? 'sent' : 'received'; ?>">
                            <div class="msg-bubble">
                                <?php if(!$is_me && $active_class_id): ?>
                                    <span class="sender-name"><?php echo $msg['full_name']; ?></span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($msg['message']); ?>
                                <div style="font-size:0.65rem; opacity:0.6; text-align:right; margin-top:5px;">
                                    <?php echo date("H:i", strtotime($msg['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if($active_class_id || $active_user_id): ?>
        <form method="POST" class="input-area">
            <?php if($active_class_id): ?>
                <input type="hidden" name="target_class_id" value="<?php echo $active_class_id; ?>">
            <?php else: ?>
                <input type="hidden" name="target_user_id" value="<?php echo $active_user_id; ?>">
            <?php endif; ?>
            
            <input type="text" name="message" class="input-field" placeholder="Type your message..." required autocomplete="off">
            <button type="submit" name="send_msg" class="send-btn"><i class='bx bxs-send'></i></button>
        </form>
        <?php endif; ?>
    </div>

</div>

<script>
    // Scroll to bottom
    var objDiv = document.getElementById("msgArea");
    objDiv.scrollTop = objDiv.scrollHeight;
</script>

</body>
</html>