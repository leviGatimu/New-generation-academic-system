<?php
// admin/students.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); exit;
}

$view_student_id = $_GET['view_id'] ?? null;
$message = '';
$msg_type = ''; 

// --- FETCH CLASSES ---
$classes_stmt = $pdo->query("SELECT * FROM classes ORDER BY class_id ASC");
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);
$class_ids = array_column($classes, 'class_id'); 

// --- ACTIONS HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s_id = $_POST['student_id'] ?? null;
    $c_id = $_POST['current_class_id'] ?? null;

    // 1. RESET PASSWORD
    if (isset($_POST['reset_password'])) {
        $pass = password_hash("123456", PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")->execute([$pass, $s_id]);
        $message = "Password reset to default (123456)."; $msg_type = "success";
        $view_student_id = $s_id;
    }

    // 2. DELETE STUDENT
    if (isset($_POST['delete_student'])) {
        $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$s_id]);
        $message = "Student removed permanently."; $msg_type = "error";
    }

    // 3. PROMOTE STUDENT
    if (isset($_POST['promote_student'])) {
        $current_index = array_search($c_id, $class_ids);
        if ($current_index !== false && isset($class_ids[$current_index + 1])) {
            $new_class_id = $class_ids[$current_index + 1];
            $pdo->prepare("UPDATE students SET class_id = ? WHERE student_id = ?")->execute([$new_class_id, $s_id]);
            $message = "Student promoted successfully."; $msg_type = "success";
        } else {
            $message = "Student is already in the highest class."; $msg_type = "warning";
        }
    }

    // 4. DEMOTE STUDENT
    if (isset($_POST['demote_student'])) {
        $current_index = array_search($c_id, $class_ids);
        if ($current_index !== false && isset($class_ids[$current_index - 1])) {
            $new_class_id = $class_ids[$current_index - 1];
            $pdo->prepare("UPDATE students SET class_id = ? WHERE student_id = ?")->execute([$new_class_id, $s_id]);
            $message = "Student demoted."; $msg_type = "warning";
        } else {
            $message = "Student is in the lowest class."; $msg_type = "warning";
        }
    }
}

// --- DATA FETCHING ---
$student_data = null;
$student_marks = [];

if ($view_student_id) {
    // FIXED: Changed access_key to parent_access_code
    $stmt = $pdo->prepare("SELECT u.*, s.admission_number, s.parent_access_code, c.class_name, c.class_id 
                           FROM users u 
                           JOIN students s ON u.user_id = s.student_id 
                           LEFT JOIN classes c ON s.class_id = c.class_id
                           WHERE u.user_id = ?");
    $stmt->execute([$view_student_id]);
    $student_data = $stmt->fetch();

    if ($student_data) {
        $m_sql = "SELECT sm.score, s.subject_name, ca.max_score, gc.name as cat_name
                  FROM student_marks sm
                  JOIN class_assessments ca ON sm.assessment_id = ca.assessment_id
                  JOIN subjects s ON ca.subject_id = s.subject_id
                  JOIN grading_categories gc ON ca.category_id = gc.id
                  WHERE sm.student_id = ?";
        $stmt = $pdo->prepare($m_sql);
        $stmt->execute([$view_student_id]);
        $raw_marks = $stmt->fetchAll();

        foreach ($raw_marks as $m) {
            $sub = $m['subject_name'];
            if (!isset($student_marks[$sub])) { $student_marks[$sub] = ['total'=>0, 'max'=>0, 'details'=>[]]; }
            $student_marks[$sub]['details'][] = $m['cat_name'] . " (" . $m['score'] . "/" . $m['max_score'] . ")";
            $student_marks[$sub]['total'] += $m['score'];
            $student_marks[$sub]['max'] += $m['max_score'];
        }
    }
} else {
    // FIXED: Changed access_key to parent_access_code
    $sql = "SELECT users.user_id, users.full_name, users.email, students.admission_number, students.class_id, students.parent_access_code 
            FROM students JOIN users ON students.student_id = users.user_id 
            ORDER BY users.full_name ASC";
    $all_students = $pdo->query($sql)->fetchAll();
    $students_by_class = [];
    foreach ($all_students as $s) { $students_by_class[$s['class_id']][] = $s; }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* === VARIABLES === */
        :root { --primary: #FF6600; --primary-light: #fff0e6; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; --border: #dfe3e8; --nav-height: 75px; --danger: #ff4d4f; --success: #00ab55; --warning: #ffc107; }
        html, body { background-color: var(--light-bg); margin: 0; padding: 0; font-family: 'Public Sans', sans-serif; overflow-y: auto; height: auto; }

        /* === NAV === */
        .top-navbar { position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height); background: var(--white); z-index: 1000; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-bottom: 1px solid var(--border); box-sizing: border-box; }
        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }
        .nav-menu { display: flex; gap: 5px; align-items: center; }
        .nav-item { text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem; padding: 10px 15px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }
        .btn-logout { text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s; }
        .btn-logout:hover { background: #ff4d4f; color: white; }

        /* === CONTENT === */
        .main-content { margin-top: var(--nav-height); padding: 40px 5%; width: 100%; box-sizing: border-box; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { margin: 0; font-size: 1.8rem; color: var(--dark); font-weight: 800; }

        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #e9fcd4; color: #229a16; border: 1px solid #b7eb8f; }
        .alert-error { background: #ffe7d9; color: #7a0c2e; border: 1px solid #ffa39e; }
        .alert-warning { background: #fff7cd; color: #7a4f01; border: 1px solid #ffe58f; }

        .grid-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .class-card { background: var(--white); border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); border: 1px solid var(--border); transition: 0.3s; position: relative; overflow: hidden; cursor: pointer; }
        .class-card:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: 0 12px 24px rgba(0,0,0,0.08); }
        .class-card::before { content: ''; position: absolute; top: 0; left: 0; width: 6px; height: 100%; background: var(--primary); opacity: 0.5; }
        .class-icon { font-size: 2.5rem; color: var(--primary); margin-bottom: 15px; background: var(--primary-light); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .class-name { margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--dark); }
        .class-meta { color: #637381; font-size: 0.9rem; margin-top: 5px; font-weight: 500; }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(33, 43, 54, 0.6); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center; }
        .modal-box { background: var(--white); width: 95%; max-width: 1000px; height: 85vh; border-radius: 16px; display: flex; flex-direction: column; box-shadow: 0 20px 40px rgba(0,0,0,0.2); animation: slideUp 0.3s ease-out; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .modal-header { padding: 20px 30px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #fafbfc; border-radius: 16px 16px 0 0; }
        .modal-body { padding: 0; overflow-y: auto; flex: 1; }

        .styled-table { width: 100%; border-collapse: collapse; }
        .styled-table th { background: #f9fafb; padding: 15px 25px; text-align: left; font-size: 0.75rem; text-transform: uppercase; color: #637381; font-weight: 700; position: sticky; top: 0; border-bottom: 1px solid var(--border); z-index: 10; }
        .styled-table td { padding: 15px 25px; border-bottom: 1px solid #f4f6f8; font-size: 0.9rem; color: var(--dark); vertical-align: middle; }
        .styled-table tr:hover { background: #f9fafb; }

        .action-menu { position: relative; display: inline-block; }
        .action-btn { background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #637381; padding: 5px; border-radius: 50%; transition: 0.2s; }
        .action-btn:hover { background: #f4f6f8; color: var(--dark); }
        .dropdown-content { display: none; position: absolute; right: 0; background-color: white; min-width: 160px; box-shadow: 0 8px 16px rgba(0,0,0,0.15); border-radius: 8px; z-index: 20; border: 1px solid var(--border); overflow: hidden; }
        .dropdown-content button { color: var(--dark); padding: 12px 16px; text-decoration: none; display: block; width: 100%; text-align: left; border: none; background: none; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .dropdown-content button:hover { background-color: #f4f6f8; }
        .dropdown-content button.danger { color: var(--danger); }
        .dropdown-content button.danger:hover { background-color: #fff1f0; }
        .show { display: block; }

        .badge { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .bg-green { background: #e9fcd4; color: #229a16; }
        .bg-orange { background: #fff7cd; color: #b78103; }

        .profile-container { display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
        .profile-sidebar { background: white; padding: 30px; border-radius: 16px; text-align: center; border: 1px solid var(--border); height: fit-content; }
        .large-avatar { width: 120px; height: 120px; background: #212b36; color: white; font-size: 3.5rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto 20px; font-weight: 800; }
        .profile-content { background: white; padding: 30px; border-radius: 16px; border: 1px solid var(--border); }
        
        .btn-add { background: var(--dark); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; }
        .btn-add:hover { background: #000; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box"><img src="../assets/images/logo.png" alt="NGA"></div>
        <span class="nav-brand-text">NGA Admin</span>
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item"><i class='bx bxs-dashboard'></i> <span>Dashboard</span></a>
        <a href="students.php" class="nav-item active"><i class='bx bxs-user-detail'></i> <span>Students</span></a>
        <a href="teachers.php" class="nav-item"><i class='bx bxs-id-card'></i> <span>Teachers</span></a>
        <a href="classes.php" class="nav-item"><i class='bx bxs-school'></i> <span>Classes</span></a>
        <a href="settings.php" class="nav-item"><i class='bx bxs-cog'></i> <span>Settings</span></a>
    </div>
    <div class="nav-user"><a href="../logout.php" class="btn-logout">Logout</a></div>
</nav>

<div class="main-content">

    <?php if ($view_student_id && $student_data): ?>
        <div class="page-header">
            <div style="display:flex; align-items:center; gap:15px;">
                <a href="students.php" style="color:#637381; font-size:1.5rem;"><i class='bx bx-arrow-back'></i></a>
                <h1 class="page-title">Student Profile</h1>
            </div>
        </div>

        <?php if($message): ?>
            <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="profile-container">
            <div class="profile-sidebar">
                <div class="large-avatar">
                    <?php echo strtoupper(substr($student_data['full_name'], 0, 1)); ?>
                </div>
                <h2 style="margin:0;"><?php echo htmlspecialchars($student_data['full_name']); ?></h2>
                <p style="color:#637381; font-weight:600;"><?php echo htmlspecialchars($student_data['class_name'] ?? 'Unassigned'); ?></p>
                
                <div style="text-align:left; background:#f9fafb; padding:15px; border-radius:10px; margin-top:20px; font-size:0.9rem;">
                    <div style="margin-bottom:10px;"><strong>Adm No:</strong> <span style="float:right;"><?php echo $student_data['admission_number']; ?></span></div>
                    <div style="margin-bottom:10px;"><strong>Email:</strong> <span style="float:right;"><?php echo $student_data['email'] ?: 'N/A'; ?></span></div>
                    <div><strong>Access Code:</strong> <span style="float:right; font-family:monospace; background:#e0e0e0; padding:2px 6px; border-radius:4px;"><?php echo $student_data['parent_access_code']; ?></span></div>
                </div>

                <form method="POST" style="margin-top:20px;" onsubmit="return confirm('Reset password to 123456?');">
                    <input type="hidden" name="student_id" value="<?php echo $student_data['user_id']; ?>">
                    <button type="submit" name="reset_password" style="width:100%; padding:10px; background:white; border:1px solid #ffccbc; color:#d84315; border-radius:8px; cursor:pointer; font-weight:bold;">
                        <i class='bx bx-reset'></i> Reset Password
                    </button>
                </form>
            </div>

            <div class="profile-content">
                <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px;">Academic Results</h3>
                <?php if (empty($student_marks)): ?>
                    <p style="color:#999; text-align:center; padding:30px;">No marks recorded yet.</p>
                <?php else: ?>
                    <table class="styled-table">
                        <thead><tr><th>Subject</th><th>Breakdown</th><th>Score</th><th>Grade</th></tr></thead>
                        <tbody>
                            <?php foreach($student_marks as $sub => $data): 
                                $pct = ($data['max'] > 0) ? ($data['total'] / $data['max']) * 100 : 0;
                                $grade = ($pct >= 80) ? 'A' : (($pct >= 70) ? 'B' : (($pct >= 50) ? 'C' : 'F'));
                                $color = ($grade == 'A' || $grade == 'B') ? '#229a16' : (($grade == 'F') ? '#b72136' : '#b78103');
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($sub); ?></strong></td>
                                <td style="color:#637381; font-size:0.85rem;"><?php echo implode(", ", $data['details']); ?></td>
                                <td><?php echo $data['total']; ?> / <?php echo $data['max']; ?></td>
                                <td style="font-weight:800; color:<?php echo $color; ?>;"><?php echo $grade; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <div class="page-header">
            <div>
                <h1 class="page-title">Manage Students</h1>
                <p style="color:#637381; margin:5px 0 0;">Select a class to view, promote, or remove students.</p>
            </div>
            <a href="add_student.php" class="btn-add"><i class='bx bx-plus'></i> Add Student</a>
        </div>

        <?php if($message): ?>
            <div class="alert alert-<?php echo $msg_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="grid-container">
            <?php foreach($classes as $class): ?>
                <?php $count = isset($students_by_class[$class['class_id']]) ? count($students_by_class[$class['class_id']]) : 0; ?>
                
                <div class="class-card" onclick="openModal('modal-<?php echo $class['class_id']; ?>')">
                    <div class="class-icon"><i class='bx bxs-school'></i></div>
                    <h3 class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></h3>
                    <div class="class-meta"><?php echo $count; ?> Students Enrolled</div>
                </div>

                <div id="modal-<?php echo $class['class_id']; ?>" class="modal">
                    <div class="modal-box">
                        <div class="modal-header">
                            <div>
                                <h2 style="margin:0;"><?php echo htmlspecialchars($class['class_name']); ?></h2>
                                <span style="font-size:0.9rem; color:#637381;">Class Management</span>
                            </div>
                            <button onclick="closeModal('modal-<?php echo $class['class_id']; ?>')" style="background:none; border:none; font-size:1.5rem; cursor:pointer;"><i class='bx bx-x'></i></button>
                        </div>
                        <div class="modal-body">
                            <table class="styled-table">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Admission No</th>
                                        <th>Status</th>
                                        <th style="text-align:right;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($count > 0): ?>
                                        <?php foreach($students_by_class[$class['class_id']] as $s): ?>
                                        <tr>
                                            <td style="font-weight:600;">
                                                <div style="display:flex; align-items:center; gap:10px;">
                                                    <div style="width:30px; height:30px; background:#f4f6f8; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.8rem; color:#637381;">
                                                        <?php echo substr($s['full_name'], 0, 1); ?>
                                                    </div>
                                                    <?php echo htmlspecialchars($s['full_name']); ?>
                                                </div>
                                            </td>
                                            <td style="color:#637381; font-family:monospace;"><?php echo $s['admission_number']; ?></td>
                                            <td>
                                                <?php if($s['email']): ?>
                                                    <span class="badge bg-green">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-orange">No Email</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align:right;">
                                                <div class="action-menu">
                                                    <button class="action-btn" onclick="toggleMenu(event, 'menu-<?php echo $s['user_id']; ?>')"><i class='bx bx-dots-vertical-rounded'></i></button>
                                                    <div id="menu-<?php echo $s['user_id']; ?>" class="dropdown-content">
                                                        <a href="?view_id=<?php echo $s['user_id']; ?>" style="display:block; padding:12px 16px; color:var(--dark); text-decoration:none; font-size:0.85rem; font-weight:600; text-align:left;"><i class='bx bx-id-card'></i> View Profile</a>
                                                        
                                                        <form method="POST">
                                                            <input type="hidden" name="student_id" value="<?php echo $s['user_id']; ?>">
                                                            <input type="hidden" name="current_class_id" value="<?php echo $class['class_id']; ?>">
                                                            <button type="submit" name="promote_student"><i class='bx bx-up-arrow-alt'></i> Promote</button>
                                                            <button type="submit" name="demote_student"><i class='bx bx-down-arrow-alt'></i> Demote</button>
                                                            <button type="submit" name="delete_student" class="danger" onclick="return confirm('Are you sure you want to remove this student? This cannot be undone.');"><i class='bx bx-trash'></i> Remove</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" style="text-align:center; padding:50px; color:#999;">No students found in this class.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script>
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    
    function toggleMenu(event, menuId) {
        event.stopPropagation();
        var dropdowns = document.getElementsByClassName("dropdown-content");
        for (var i = 0; i < dropdowns.length; i++) {
            if (dropdowns[i].id !== menuId) { dropdowns[i].classList.remove('show'); }
        }
        document.getElementById(menuId).classList.toggle("show");
    }

    window.onclick = function(event) {
        if (!event.target.matches('.action-btn') && !event.target.matches('.action-btn i')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                if (dropdowns[i].classList.contains('show')) {
                    dropdowns[i].classList.remove('show');
                }
            }
        }
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    }
</script>

</body>
</html>