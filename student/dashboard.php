<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$student_id = $_SESSION['user_id'];
$message = "";

// --- 1. FETCH STUDENT INFO & PARENT LINK STATUS ---
$stmt = $pdo->prepare("SELECT u.full_name, s.admission_number, s.class_id, s.class_role, s.parent_access_code, c.class_name,
                       (SELECT COUNT(*) FROM parent_student_link WHERE student_id = s.student_id) as is_linked 
                       FROM users u 
                       JOIN students s ON u.user_id = s.student_id 
                       LEFT JOIN classes c ON s.class_id = c.class_id 
                       WHERE u.user_id = ?");
$stmt->execute([$student_id]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);

$my_class_id = $me['class_id'];
$my_role = $me['class_role'];

// --- 2. HANDLE PRESIDENT ANNOUNCEMENTS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_announcement'])) {
    if ($my_role === 'President' && !empty($_POST['msg'])) {
        $msg = "🔔 Class Announcement: " . trim($_POST['msg']);
        // Send as 'system' type so it looks official
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, class_id, message, msg_type) VALUES (?, ?, ?, 'system')");
        if($stmt->execute([$student_id, $my_class_id, $msg])) {
            $message = "Announcement posted successfully!";
        }
    }
}

// --- 3. CALCULATE DASHBOARD STATS ---

// A. Subject Count
$sub_stmt = $pdo->prepare("SELECT COUNT(*) FROM class_subjects WHERE class_id = ?");
$sub_stmt->execute([$my_class_id]);
$subject_count = $sub_stmt->fetchColumn();

// B. Overall Average
$avg_query = "SELECT 
        (SUM(COALESCE(sm.score, 0)) + SUM(COALESCE(sub.obtained_marks, 0))) as total_score,
        (SUM(COALESCE(ca.max_score, 0)) + SUM(COALESCE(oa.total_marks, 0))) as total_max
    FROM students s
    LEFT JOIN student_marks sm ON s.student_id = sm.student_id
    LEFT JOIN class_assessments ca ON sm.assessment_id = ca.assessment_id
    LEFT JOIN assessment_submissions sub ON s.student_id = sub.student_id AND sub.is_marked = 1
    LEFT JOIN online_assessments oa ON sub.assessment_id = oa.id
    WHERE s.student_id = ?";
$avg_stmt = $pdo->prepare($avg_query);
$avg_stmt->execute([$student_id]);
$stats = $avg_stmt->fetch(PDO::FETCH_ASSOC);

$total_obtained = $stats['total_score'] ?? 0;
$total_max = $stats['total_max'] ?? 0;
$overall_avg = ($total_max > 0) ? round(($total_obtained / $total_max) * 100) : 0;

// --- 4. SYSTEM LOGIC: FIND CLASS #1 (Top Performer) ---
$top_student_id = null;
$highest_avg = -1;

if ($my_class_id) {
    // Get all students in my class
    $peers_stmt = $pdo->prepare("SELECT student_id FROM students WHERE class_id = ?");
    $peers_stmt->execute([$my_class_id]);
    $all_students = $peers_stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($all_students as $sid) {
        // Calculate Avg for Peer
        $q1 = $pdo->prepare("SELECT SUM(score) as s, SUM(max_score) as m FROM student_marks m JOIN class_assessments a ON m.assessment_id = a.assessment_id WHERE m.student_id = ?");
        $q1->execute([$sid]);
        $r1 = $q1->fetch();
        
        $q2 = $pdo->prepare("SELECT SUM(obtained_marks) as s, SUM(total_marks) as m FROM assessment_submissions s JOIN online_assessments o ON s.assessment_id = o.id WHERE s.student_id = ? AND s.is_marked = 1");
        $q2->execute([$sid]);
        $r2 = $q2->fetch();

        $t_obt = ($r1['s'] ?? 0) + ($r2['s'] ?? 0);
        $t_max = ($r1['m'] ?? 0) + ($r2['m'] ?? 0);
        $s_avg = ($t_max > 0) ? ($t_obt / $t_max) * 100 : 0;

        if ($s_avg > $highest_avg) {
            $highest_avg = $s_avg;
            $top_student_id = $sid;
        }
    }
}
$is_top_student = ($student_id == $top_student_id && $highest_avg > 0);

// --- 5. FETCH RECENT ANNOUNCEMENTS ---
$msg_sql = "SELECT message, created_at FROM messages WHERE class_id = ? AND msg_type = 'system' ORDER BY created_at DESC LIMIT 3";
$msg_stmt = $pdo->prepare($msg_sql);
$msg_stmt->execute([$my_class_id]);
$announcements = $msg_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Student Dashboard | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; --border: #dfe3e8; }
        body { background: var(--light-bg); font-family: 'Public Sans', sans-serif; margin: 0; padding-bottom: 50px; }

        /* Navbar */
        .top-navbar { position: fixed; top: 0; width: 100%; height: 70px; background: white; border-bottom: 1px solid #dfe3e8; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; box-sizing: border-box; z-index: 100; }
        .nav-brand { font-weight: 800; font-size: 1.2rem; display: flex; align-items: center; gap: 10px; color: var(--dark); text-decoration: none; }
        .nav-menu { display: flex; gap: 10px; }
        .nav-item { color: #637381; text-decoration: none; font-weight: 600; padding: 8px 15px; border-radius: 6px; transition: 0.2s; }
        .nav-item:hover, .nav-item.active { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .btn-logout { color: #ff4d4f; border: 1px solid #ff4d4f; padding: 6px 15px; border-radius: 6px; text-decoration: none; font-weight: bold; }

        /* Layout */
        .container { max-width: 1200px; margin: 100px auto 0; padding: 0 20px; }
        
        /* --- ROLE WIDGETS --- */
        .role-widget { background: white; border-radius: 16px; padding: 25px; margin-bottom: 25px; display: flex; align-items: center; gap: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); position: relative; overflow: hidden; }
        .role-icon { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; flex-shrink: 0; }
        .role-content h3 { margin: 0 0 5px 0; font-size: 1.3rem; color: #212b36; }
        .role-content p { margin: 0; color: #637381; font-size: 0.95rem; }

        /* Role Themes */
        .role-widget.rank-one { background: linear-gradient(135deg, #fff 0%, #fffbe6 100%); border: 1px solid #ffe58f; }
        .role-widget.rank-one .role-icon { background: #fff1b8; box-shadow: 0 0 15px #ffe58f; font-size: 2.5rem; }
        .role-widget.rank-one h3 { color: #d48806; }
        
        .role-widget.president { border-left: 5px solid #faad14; }
        .role-widget.president .role-icon { background: #fff7e6; color: #faad14; }
        
        .role-widget.vp { border-left: 5px solid #69c0ff; }
        .role-widget.vp .role-icon { background: #e6f7ff; color: #1890ff; }

        .role-widget.devotion { border-left: 5px solid #722ed1; }
        .role-widget.devotion .role-icon { background: #f9f0ff; color: #722ed1; }

        .role-widget.timekeeper { border-left: 5px solid #52c41a; }
        .role-widget.timekeeper .role-icon { background: #f6ffed; color: #52c41a; }

        /* Animation for #1 */
        .shine-effect { position: absolute; top: 0; left: -100%; width: 50%; height: 100%; background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.8) 50%, rgba(255,255,255,0) 100%); transform: skewX(-25deg); animation: shine 6s infinite; }
        @keyframes shine { 0% { left: -100%; } 20% { left: 200%; } 100% { left: 200%; } }

        /* --- DASHBOARD GRID --- */
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 25px; }
        .left-col { display: flex; flex-direction: column; gap: 20px; }
        .right-col { display: flex; flex-direction: column; gap: 20px; }

        /* Cards */
        .card { background: white; border-radius: 16px; border: 1px solid var(--border); padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .welcome-header h1 { margin: 0; color: var(--dark); }
        .class-tag { display: inline-block; background: #e3f2fd; color: #1565c0; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; margin-top: 10px; }

        /* Stats Row */
        .stats-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px; }
        .mini-stat { background: #f9fafb; padding: 15px; border-radius: 12px; display: flex; align-items: center; gap: 15px; }
        .mini-icon { font-size: 1.5rem; color: var(--primary); }

        /* Parent Code Box */
        .code-box { background: #fff0e6; border: 1px dashed var(--primary); padding: 15px; text-align: center; border-radius: 10px; margin: 15px 0; }
        .code-text { font-size: 1.4rem; font-weight: 800; letter-spacing: 2px; color: var(--dark); }
        .btn-copy { background: var(--primary); color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; width: 100%; }

        /* Announcements */
        .announcement-item { border-bottom: 1px solid #f0f0f0; padding: 10px 0; }
        .announcement-item:last-child { border-bottom: none; }
        .ann-time { font-size: 0.75rem; color: #999; }
        .ann-text { font-size: 0.9rem; color: #333; margin: 5px 0 0; }

        /* President Form */
        .pres-form textarea { width: 100%; border: 1px solid var(--border); border-radius: 8px; padding: 10px; margin-bottom: 10px; font-family: inherit; }
        .btn-post { background: var(--dark); color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div style="width:40px;"><img src="../assets/images/logo.png" alt="" style="width:100%;"></div>
        Student Portal
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item active"><i class='bx bxs-dashboard'></i> Dashboard</a>
        <a href="academics.php" class="nav-item"><i class='bx bxs-graduation'></i> Academics</a>
        <a href="results.php" class="nav-item"><i class='bx bxs-bar-chart-alt-2'></i> My Results</a>
        <a href="messages.php" class="nav-item"><i class='bx bxs-chat'></i> Messages</a>
        <a href="attendance.php" class="nav-item"><i class='bx bxs-calendar-check'></i> <span>Attendance</span></a>
         <a href="class_ranking.php" class="nav-item">
            <i class='bx bxs-chat'></i> <span>Ranking</span>
        </a>
        <a href="profile.php" class="nav-item">
    <i class='bx bxs-user-circle'></i> <span>Profile</span>
</a>
    </div>
    <a href="../logout.php" class="btn-logout">Logout</a>
</nav>

<div class="container">

    <?php if ($is_top_student): ?>
    <div class="role-widget rank-one">
        <div class="role-icon">🏆</div>
        <div class="role-content">
            <h3>Class Academic Champion</h3>
            <p>You have the highest average in the class (<?php echo round($highest_avg, 1); ?>%). Outstanding work!</p>
        </div>
        <div class="shine-effect"></div>
    </div>
    <?php endif; ?>

    <?php if ($my_role == 'President'): ?>
        <div class="role-widget president">
            <div class="role-icon"><i class='bx bxs-crown'></i></div>
            <div class="role-content">
                <h3>Class President</h3>
                <p>You have the authority to post announcements to the class board.</p>
            </div>
        </div>
    <?php elseif ($my_role == 'Vice President'): ?>
        <div class="role-widget vp">
            <div class="role-icon"><i class='bx bxs-shield-alt-2'></i></div>
            <div class="role-content">
                <h3>Vice President</h3>
                <p>Support the President and help maintain order.</p>
            </div>
        </div>
    <?php elseif ($my_role == 'Devotion Leader'): ?>
        <div class="role-widget devotion">
            <div class="role-icon"><i class='bx bxs-book-heart'></i></div>
            <div class="role-content">
                <h3>Devotion Leader</h3>
                <p>Prepare the scripture for today's assembly.</p>
            </div>
        </div>
    <?php elseif ($my_role == 'Time Keeper'): ?>
        <div class="role-widget timekeeper">
            <div class="role-icon"><i class='bx bxs-timer'></i></div>
            <div class="role-content">
                <h3>Time Keeper</h3>
                <p>Ensure all class activities start and end on schedule.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="dashboard-grid">
        
        <div class="left-col">
            <div class="card welcome-header">
                <h1>Hello, <?php echo htmlspecialchars($me['full_name']); ?></h1>
                <p style="color:#637381; margin:5px 0;">Ready to learn something new today?</p>
                <span class="class-tag"><?php echo htmlspecialchars($me['class_name'] ?? 'No Class'); ?> • <?php echo htmlspecialchars($me['admission_number']); ?></span>

                <div class="stats-row">
                    <div class="mini-stat">
                        <i class='bx bx-trending-up mini-icon'></i>
                        <div>
                            <span style="font-size:1.4rem; font-weight:800; color:var(--dark);"><?php echo $overall_avg; ?>%</span>
                            <div style="font-size:0.8rem; color:#637381;">Overall Avg</div>
                        </div>
                    </div>
                    <div class="mini-stat">
                        <i class='bx bx-book-open mini-icon'></i>
                        <div>
                            <span style="font-size:1.4rem; font-weight:800; color:var(--dark);"><?php echo $subject_count; ?></span>
                            <div style="font-size:0.8rem; color:#637381;">Subjects</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">
                    <i class='bx bxs-bell'></i> Class Board
                </h3>
                
                <?php if(empty($announcements)): ?>
                    <p style="color:#999; font-style:italic;">No recent announcements.</p>
                <?php else: ?>
                    <?php foreach($announcements as $ann): ?>
                        <div class="announcement-item">
                            <div class="ann-text"><?php echo htmlspecialchars($ann['message']); ?></div>
                            <div class="ann-time"><?php echo date("d M, H:i", strtotime($ann['created_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if($my_role === 'President'): ?>
                    <div style="margin-top:20px; background:#f9fafb; padding:15px; border-radius:10px; border:1px solid #eee;">
                        <h4 style="margin:0 0 10px 0;">📢 Post Announcement</h4>
                        <form method="POST" class="pres-form">
                            <textarea name="msg" rows="2" placeholder="Write a message for your class..." required></textarea>
                            <button type="submit" name="post_announcement" class="btn-post">Post to Board</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="right-col">
            <div class="card" style="border-top: 4px solid var(--primary);">
                <h3 style="margin-top:0; font-size:1rem;">Parent Access</h3>
                
                <?php if ($me['is_linked'] > 0): ?>
                    <div style="text-align:center; padding:10px;">
                        <i class='bx bxs-check-circle' style="font-size:3rem; color:#00ab55;"></i>
                        <p style="font-weight:bold; margin:5px 0;">Account Linked</p>
                        <small style="color:#637381;">Your parent can view your results.</small>
                    </div>
                <?php else: ?>
                    <p style="font-size:0.85rem; color:#637381;">Share this code with your parents.</p>
                    <div class="code-box">
                        <div class="code-text"><?php echo !empty($me['parent_access_code']) ? $me['parent_access_code'] : 'N/A'; ?></div>
                    </div>
                    <?php if(!empty($me['parent_access_code'])): ?>
                        <button onclick="copyCode('<?php echo $me['parent_access_code']; ?>')" class="btn-copy">
                            <i class='bx bx-copy'></i> Copy Code
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3 style="margin-top:0; font-size:1rem;">Quick Links</h3>
                <a href="results.php" style="display:block; padding:10px; margin-bottom:5px; background:#f4f6f8; border-radius:8px; text-decoration:none; color:var(--dark); font-weight:600;">
                    📊 View Report Card
                </a>
                <a href="messages.php" style="display:block; padding:10px; background:#f4f6f8; border-radius:8px; text-decoration:none; color:var(--dark); font-weight:600;">
                    💬 Class Chat
                </a>
                <a href="class_ranking.php" style="display:block; padding:10px; margin-top:5px; background:#f4f6f8; border-radius:8px; text-decoration:none; color:var(--dark); font-weight:600;">
                    🏆 Leaderboard
                </a>
            </div>
        </div>

    </div>
</div>

<script>
function copyCode(code) {
    navigator.clipboard.writeText(code);
    alert("Parent code copied!");
}
</script>

</body>
</html>