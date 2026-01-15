<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$student_id = $_SESSION['user_id'];
$active_group_id = $_GET['group_id'] ?? null;
$active_dm_id = $_GET['dm_id'] ?? null;

// --- 1. GET CLASS ---
$stmt = $pdo->prepare("SELECT s.class_id, c.class_name FROM students s JOIN classes c ON s.class_id = c.class_id WHERE s.student_id = ?");
$stmt->execute([$student_id]);
$my_class = $stmt->fetch();
$class_id = $my_class['class_id'];

// --- 2. FETCH CONTACTS ---
$contacts = [];

// Teachers
$t_sql = "SELECT DISTINCT u.user_id, u.full_name, u.profile_pic, 'Teacher' as role FROM teacher_allocations ta JOIN users u ON ta.teacher_id = u.user_id WHERE ta.class_id = ? ORDER BY u.full_name";
$t_stmt = $pdo->prepare($t_sql);
$t_stmt->execute([$class_id]);
$teachers = $t_stmt->fetchAll();

// Classmates
$s_sql = "SELECT u.user_id, u.full_name, u.profile_pic, s.class_role as role FROM students s JOIN users u ON s.student_id = u.user_id WHERE s.class_id = ? AND s.student_id != ? ORDER BY u.full_name";
$s_stmt = $pdo->prepare($s_sql);
$s_stmt->execute([$class_id, $student_id]);
$classmates = $s_stmt->fetchAll();

foreach(array_merge($teachers, $classmates) as $user) {
    $contacts[$user['user_id']] = $user;
}

// Helper: Initials
function getInitials($name) {
    $name = trim($name);
    if (empty($name)) return "?";
    $parts = explode(' ', $name);
    $first = strtoupper(substr($parts[0], 0, 1));
    $last = (count($parts) > 1) ? strtoupper(substr(end($parts), 0, 1)) : '';
    return $first . $last;
}

// Helper: Color
function stringToColor($str) {
    $code = dechex(crc32($str));
    $code = substr($code, 0, 6);
    return "#" . $code;
}

// --- 3. SEND MESSAGE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message'])) {
    $msg = trim($_POST['message']);
    if ($msg) {
        if ($active_group_id) {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, class_id, message, msg_type) VALUES (?, ?, ?, 'text')");
            $stmt->execute([$student_id, $class_id, $msg]);
        } elseif ($active_dm_id) {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message, msg_type) VALUES (?, ?, ?, 'text')");
            $stmt->execute([$student_id, $active_dm_id, $msg]);
        }
        header("Location: " . $_SERVER['REQUEST_URI']); exit;
    }
}

// --- 4. HISTORY ---
$chat_history = [];
$chat_title = "Select a conversation";
$chat_pic = "";
$chat_initials = "";
$chat_link = "#";
$header_bg = "#212b36"; // Default color

if ($active_group_id) {
    $chat_title = $my_class['class_name'] . " Group";
    $stmt = $pdo->prepare("SELECT m.*, u.full_name, u.profile_pic FROM messages m JOIN users u ON m.sender_id = u.user_id WHERE m.class_id = ? ORDER BY m.created_at ASC");
    $stmt->execute([$class_id]);
    $chat_history = $stmt->fetchAll();
} elseif ($active_dm_id) {
    if (isset($contacts[$active_dm_id])) {
        $user = $contacts[$active_dm_id];
        $chat_title = $user['full_name'];
        $chat_link = "view_profile.php?user_id=" . $active_dm_id;
        
        if (!empty($user['profile_pic'])) {
            $chat_pic = "../assets/uploads/" . $user['profile_pic'];
        } else {
            $chat_initials = getInitials($user['full_name']);
            $header_bg = stringToColor($user['full_name']);
        }
    }
    $stmt = $pdo->prepare("SELECT m.*, u.full_name FROM messages m JOIN users u ON m.sender_id = u.user_id WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?) ORDER BY m.created_at ASC");
    $stmt->execute([$student_id, $active_dm_id, $active_dm_id, $student_id]);
    $chat_history = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Chat | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #fff; --border: #dfe3e8; }
        body { margin: 0; background: var(--light-bg); font-family: 'Public Sans', sans-serif; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        .top-navbar { height: 60px; background: var(--white); border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 20px; flex-shrink: 0; }
        .nav-link { text-decoration: none; color: var(--dark); font-weight: 700; display: flex; align-items: center; gap: 5px; }

        .chat-layout { display: flex; flex: 1; height: calc(100vh - 60px); }
        .sidebar { width: 300px; background: var(--white); border-right: 1px solid var(--border); overflow-y: auto; display: flex; flex-direction: column; }
        .sb-header { padding: 12px 15px; font-size: 0.7rem; font-weight: 800; color: #919eab; text-transform: uppercase; background: #fafbfc; border-bottom: 1px solid var(--border); position: sticky; top: 0; }
        
        .chat-item { padding: 10px 15px; display: flex; align-items: center; gap: 10px; cursor: pointer; text-decoration: none; color: var(--dark); border-bottom: 1px solid #f9fafb; transition: 0.1s; }
        .chat-item:hover, .chat-item.active { background: #fff5f0; border-left: 3px solid var(--primary); }
        
        /* FIXED: Avatar Styles */
        .avatar-img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .avatar-placeholder { width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 0.8rem; }
        
        .av-group { background: linear-gradient(135deg, #FF6600 0%, #ff8533 100%); font-size: 1rem; color:white; }

        .role-badge { font-size: 0.65rem; background: #212b36; color: white; padding: 2px 6px; border-radius: 4px; margin-left: 5px; }
        .role-pres { color: #faad14; margin-left: 5px; }

        /* Chat Header */
        .chat-header { padding: 10px 20px; background: var(--white); border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; height: 60px; box-sizing: border-box; }
        .header-pic { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; }
        .header-init { width: 38px; height: 38px; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        
        /* Chat Body */
        .chat-box { flex: 1; display: flex; flex-direction: column; background: #eef2f5; }
        .messages-area { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
        .msg-row { display: flex; width: 100%; margin-bottom: 5px; }
        .msg-bubble { max-width: 70%; padding: 10px 14px; border-radius: 12px; font-size: 0.9rem; line-height: 1.4; box-shadow: 0 1px 2px rgba(0,0,0,0.05); position: relative; }
        .sent { justify-content: flex-end; }
        .sent .msg-bubble { background: var(--primary); color: white; border-bottom-right-radius: 2px; }
        .received { justify-content: flex-start; }
        .received .msg-bubble { background: var(--white); color: var(--dark); border-bottom-left-radius: 2px; }
        .sys-msg { align-self: center; background: #e3f2fd; color: #007bff; padding: 6px 15px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-align: center; border: 1px solid #bbdefb; margin: 10px 0; }
        .input-area { padding: 15px; background: var(--white); border-top: 1px solid var(--border); display: flex; gap: 10px; }
        .input-field { flex: 1; padding: 10px 15px; border: 1px solid var(--border); border-radius: 20px; outline: none; background: #f9fafb; }
        .send-btn { background: var(--primary); color: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
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
            <div class="avatar-placeholder av-group"><i class='bx bxs-group'></i></div>
            <div style="font-weight:700; font-size:0.9rem;"><?php echo htmlspecialchars($my_class['class_name']); ?></div>
        </a>

        <div class="sb-header">Teachers</div>
        <?php foreach($teachers as $t): ?>
            <a href="?dm_id=<?php echo $t['user_id']; ?>" class="chat-item <?php echo $active_dm_id == $t['user_id'] ? 'active' : ''; ?>">
                <?php if(!empty($t['profile_pic'])): ?>
                    <img src="../assets/uploads/<?php echo $t['profile_pic']; ?>" class="avatar-img">
                <?php else: ?>
                    <div class="avatar-placeholder" style="background-color: <?php echo stringToColor($t['full_name']); ?>;">
                        <?php echo getInitials($t['full_name']); ?>
                    </div>
                <?php endif; ?>
                <div>
                    <div style="font-size:0.9rem; font-weight:600;">
                        <?php echo htmlspecialchars($t['full_name']); ?>
                        <span class="role-badge">Teacher</span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>

        <div class="sb-header">Classmates</div>
        <?php foreach($classmates as $mate): ?>
            <a href="?dm_id=<?php echo $mate['user_id']; ?>" class="chat-item <?php echo $active_dm_id == $mate['user_id'] ? 'active' : ''; ?>">
                <?php if(!empty($mate['profile_pic'])): ?>
                    <img src="../assets/uploads/<?php echo $mate['profile_pic']; ?>" class="avatar-img">
                <?php else: ?>
                    <div class="avatar-placeholder" style="background-color: <?php echo stringToColor($mate['full_name']); ?>;">
                        <?php echo getInitials($mate['full_name']); ?>
                    </div>
                <?php endif; ?>
                <div>
                    <div style="font-size:0.9rem; font-weight:600;">
                        <?php echo htmlspecialchars($mate['full_name']); ?>
                        <?php if($mate['role'] == 'President') echo '<i class="bx bxs-crown role-pres" title="President"></i>'; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="chat-box">
        <div class="chat-header">
            <?php if($active_dm_id): ?>
                <a href="<?php echo $chat_link; ?>" style="text-decoration:none; display:flex; align-items:center; gap:10px;">
                    <?php if($chat_pic): ?>
                        <img src="<?php echo $chat_pic; ?>" class="header-pic">
                    <?php else: ?>
                        <div class="header-init" style="background-color: <?php echo $header_bg; ?>;">
                            <?php echo $chat_initials; ?>
                        </div>
                    <?php endif; ?>
                    <span style="font-weight:800; color:var(--dark); font-size:1.1rem;"><?php echo htmlspecialchars($chat_title); ?></span>
                </a>
            <?php else: ?>
                <span style="font-weight:800; color:var(--dark); font-size:1.1rem;"><?php echo htmlspecialchars($chat_title); ?></span>
            <?php endif; ?>
        </div>
        
        <div class="messages-area" id="msgArea">
            <?php if(empty($chat_history)): ?>
                <div style="text-align:center; margin-top:50px; color:#919eab;">
                    <i class='bx bx-message-square-dots' style="font-size:3rem; opacity:0.3;"></i>
                    <p>Start a conversation!</p>
                </div>
            <?php else: ?>
                <?php foreach($chat_history as $msg): ?>
                    <?php if($msg['msg_type'] == 'system'): ?>
                        <div class="sys-msg"><i class='bx bxs-megaphone'></i> <?php echo $msg['message']; ?></div>
                    <?php else: 
                        $is_me = ($msg['sender_id'] == $student_id);
                    ?>
                        <div class="msg-row <?php echo $is_me ? 'sent' : 'received'; ?>">
                            <?php if(!$is_me): ?>
                                <a href="view_profile.php?user_id=<?php echo $msg['sender_id']; ?>">
                                    <div class="avatar-placeholder" style="width:24px; height:24px; font-size:0.6rem; margin-right:5px; align-self:flex-end; margin-bottom:5px; background-color:#ccc;">
                                        <i class='bx bxs-user'></i>
                                    </div>
                                </a>
                            <?php endif; ?>
                            <div class="msg-bubble">
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