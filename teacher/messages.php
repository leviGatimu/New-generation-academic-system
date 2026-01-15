<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') { header("Location: ../index.php"); exit; }

$teacher_id = $_SESSION['user_id'];
$active_class_id = $_GET['class_id'] ?? null;
$active_user_id = $_GET['user_id'] ?? null;

// --- 1. FETCH TEACHER'S CLASSES (For Group Chats) ---
$groups_stmt = $pdo->prepare("SELECT DISTINCT c.class_id, c.class_name 
                              FROM classes c 
                              JOIN teacher_allocations ta ON c.class_id = ta.class_id 
                              WHERE ta.teacher_id = ?");
$groups_stmt->execute([$teacher_id]);
$my_classes = $groups_stmt->fetchAll();

// Get list of Class IDs to find students/parents
$class_ids = array_column($my_classes, 'class_id');

// --- 2. FETCH CONTACTS (Students & Parents) ---
$contacts = [];

if (!empty($class_ids)) {
    // Helper for SQL IN clause
    $placeholders = str_repeat('?,', count($class_ids) - 1) . '?';

    // A. FETCH STUDENTS
    // We get all students in the classes this teacher teaches
    $s_sql = "SELECT u.user_id, u.full_name, 'Student' as role_desc, s.class_id
              FROM students s 
              JOIN users u ON s.student_id = u.user_id 
              WHERE s.class_id IN ($placeholders)
              ORDER BY u.full_name";
    $s_stmt = $pdo->prepare($s_sql);
    $s_stmt->execute($class_ids);
    $students = $s_stmt->fetchAll();

    // Add to contacts list
    foreach($students as $st) {
        $contacts[$st['user_id']] = [
            'user_id' => $st['user_id'],
            'name' => $st['full_name'],
            'role' => 'Student', // Badge type
            'desc' => 'Student'
        ];
    }

    // B. FETCH PARENTS
    // We find parents linked to these students and format "Parent of [Child]"
    $p_sql = "SELECT p.user_id, p.full_name, GROUP_CONCAT(u_child.full_name SEPARATOR ', ') as children
              FROM parent_student_link psl
              JOIN users p ON psl.parent_id = p.user_id
              JOIN students s ON psl.student_id = s.student_id
              JOIN users u_child ON s.student_id = u_child.user_id
              WHERE s.class_id IN ($placeholders)
              GROUP BY p.user_id
              ORDER BY p.full_name";
    $p_stmt = $pdo->prepare($p_sql);
    $p_stmt->execute($class_ids);
    $parents = $p_stmt->fetchAll();

    // Add to contacts list
    foreach($parents as $pr) {
        // Truncate if too many kids
        $kids = $pr['children'];
        if (strlen($kids) > 20) $kids = substr($kids, 0, 18) . '...';

        $contacts[$pr['user_id']] = [
            'user_id' => $pr['user_id'],
            'name' => $pr['full_name'],
            'role' => 'Parent', // Badge type
            'desc' => 'Parent of ' . $kids
        ];
    }
}

// --- 3. HANDLE SENDING MESSAGES ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_msg'])) {
    $msg = trim($_POST['message']);
    
    if ($msg) {
        if ($active_class_id) {
            // Group Chat
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, class_id, message, msg_type) VALUES (?, ?, ?, 'text')");
            $stmt->execute([$teacher_id, $active_class_id, $msg]);
        } elseif ($active_user_id) {
            // Direct Message
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, msg_type) VALUES (?, ?, ?, 'text')");
            $stmt->execute([$teacher_id, $active_user_id, $msg]);
        }
        header("Location: " . $_SERVER['REQUEST_URI']); exit;
    }
}

// --- 4. FETCH CHAT HISTORY ---
$chat_history = [];
$chat_title = "Select a conversation";

if ($active_class_id) {
    // Group Chat History
    $stmt = $pdo->prepare("SELECT m.*, u.full_name, u.role 
                           FROM messages m 
                           JOIN users u ON m.sender_id = u.user_id 
                           WHERE m.class_id = ? 
                           ORDER BY m.created_at ASC");
    $stmt->execute([$active_class_id]);
    $chat_history = $stmt->fetchAll();
    
    foreach($my_classes as $c) { if($c['class_id'] == $active_class_id) $chat_title = $c['class_name'] . " Group"; }

} elseif ($active_user_id) {
    // DM History
    $stmt = $pdo->prepare("SELECT m.*, u.full_name 
                           FROM messages m 
                           JOIN users u ON m.sender_id = u.user_id 
                           WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                              OR (m.sender_id = ? AND m.receiver_id = ?) 
                           ORDER BY m.created_at ASC");
    $stmt->execute([$teacher_id, $active_user_id, $active_user_id, $teacher_id]);
    $chat_history = $stmt->fetchAll();
    
    if (isset($contacts[$active_user_id])) {
        $chat_title = $contacts[$active_user_id]['name'];
    }
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
        body { margin: 0; background: var(--light); font-family: 'Public Sans', sans-serif; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        /* Navbar */
        .top-navbar { height: 60px; background: var(--white); border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 20px; flex-shrink: 0; }
        .nav-link { text-decoration: none; color: var(--dark); font-weight: bold; display: flex; align-items: center; gap: 5px; }

        .chat-layout { display: flex; flex: 1; height: calc(100vh - 60px); }
        
        /* Sidebar */
        .sidebar { width: 320px; background: var(--white); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow-y: auto; }
        
        .list-header { padding: 15px; font-size: 0.75rem; font-weight: 800; color: #919eab; text-transform: uppercase; background: #fafbfc; border-bottom: 1px solid var(--border); position: sticky; top: 0; }
        
        .chat-item { padding: 12px 15px; display: flex; align-items: center; gap: 12px; cursor: pointer; text-decoration: none; color: var(--dark); border-bottom: 1px solid #f4f6f8; transition: 0.1s; }
        .chat-item:hover, .chat-item.active { background: #fff5f0; border-left: 3px solid var(--primary); }
        
        .avatar { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white; flex-shrink: 0; font-size: 0.9rem; }
        .av-group { background: linear-gradient(135deg, #FF6600 0%, #ff8533 100%); }
        .av-Student { background: #00ab55; }
        .av-Parent { background: #007bff; }
        
        .role-tag { font-size: 0.7rem; color: #919eab; font-weight: 600; display: block; }

        /* Chat Area */
        .chat-box { flex: 1; display: flex; flex-direction: column; background: #eef2f5; }
        .chat-header { padding: 15px 25px; background: var(--white); border-bottom: 1px solid var(--border); font-weight: 800; color: var(--dark); font-size: 1.1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        
        .messages-area { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
        
        .msg-row { display: flex; width: 100%; margin-bottom: 5px; }
        .msg-bubble { max-width: 65%; padding: 10px 15px; border-radius: 12px; font-size: 0.9rem; line-height: 1.4; position: relative; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        
        .sent { justify-content: flex-end; }
        .sent .msg-bubble { background: var(--primary); color: white; border-bottom-right-radius: 2px; }
        
        .received { justify-content: flex-start; }
        .received .msg-bubble { background: var(--white); color: var(--dark); border-bottom-left-radius: 2px; }
        .sender-name { font-size: 0.7rem; font-weight: 700; color: var(--primary); margin-bottom: 2px; display: block; }
        
        /* System Notification */
        .system-alert { 
            align-self: center; background: #e3f2fd; color: #1565c0; 
            padding: 8px 20px; border-radius: 30px; font-size: 0.8rem; font-weight: 600; 
            border: 1px solid #bbdefb; margin: 15px 0; text-align: center;
        }

        /* Input Area */
        .input-area { padding: 15px; background: var(--white); border-top: 1px solid var(--border); display: flex; gap: 10px; }
        .input-field { flex: 1; padding: 10px 15px; border: 1px solid var(--border); border-radius: 20px; outline: none; background: #f9fafb; transition: 0.2s; }
        .input-field:focus { background: white; border-color: var(--primary); }
        .send-btn { background: var(--primary); color: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; transition: 0.2s; }
        .send-btn:hover { transform: scale(1.05); }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-link"><i class='bx bx-arrow-back'></i> Dashboard</a>
    <span style="font-weight:800; font-size:1.1rem; margin-left:15px;">Messages</span>
</nav>

<div class="chat-layout">
    
    <div class="sidebar">
        <div class="list-header">Groups</div>
        <?php foreach($my_classes as $g): ?>
            <a href="messages.php?class_id=<?php echo $g['class_id']; ?>" class="chat-item <?php echo $active_class_id == $g['class_id'] ? 'active' : ''; ?>">
                <div class="avatar av-group"><i class='bx bxs-group'></i></div>
                <div style="font-weight:700; font-size:0.9rem;"><?php echo $g['class_name']; ?></div>
            </a>
        <?php endforeach; ?>

        <div class="list-header">Contacts</div>
        <?php if(empty($contacts)): ?>
            <div style="padding:20px; text-align:center; color:#999; font-size:0.85rem;">
                No students assigned to your classes yet.
            </div>
        <?php else: ?>
            <?php foreach($contacts as $user): ?>
                <a href="messages.php?user_id=<?php echo $user['user_id']; ?>" class="chat-item <?php echo $active_user_id == $user['user_id'] ? 'active' : ''; ?>">
                    <div class="avatar av-<?php echo $user['role']; ?>">
                        <?php echo substr($user['name'], 0, 1); ?>
                    </div>
                    <div style="overflow:hidden;">
                        <div style="font-weight:700; font-size:0.9rem; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;">
                            <?php echo $user['name']; ?>
                        </div>
                        <span class="role-tag"><?php echo $user['desc']; ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="chat-box">
        <div class="chat-header">
            <?php echo $chat_title; ?>
        </div>

        <div class="messages-area" id="msgArea">
            <?php if(empty($chat_history)): ?>
                <div style="text-align:center; margin-top:50px; color:#919eab;">
                    <i class='bx bx-message-dots' style="font-size:3rem; opacity:0.5;"></i>
                    <p>Start the conversation!</p>
                </div>
            <?php else: ?>
                <?php foreach($chat_history as $msg): ?>
                    
                    <?php if($msg['msg_type'] == 'system'): ?>
                        <div class="system-alert">
                            <i class='bx bxs-megaphone'></i> <?php echo $msg['message']; ?>
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
                                <div style="font-size:0.65rem; opacity:0.6; text-align:right; margin-top:3px;">
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
            <input type="text" name="message" class="input-field" placeholder="Type a message..." required autocomplete="off">
            <button type="submit" name="send_msg" class="send-btn"><i class='bx bxs-send'></i></button>
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