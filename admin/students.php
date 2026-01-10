<?php
// admin/students.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$view_student_id = $_GET['view_id'] ?? null;
$message = '';

// --- ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_password'])) {
        $pass = password_hash("123456", PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")->execute([$pass, $_POST['student_id']]);
        $message = "Password reset to default (123456).";
        $view_student_id = $_POST['student_id'];
    }
}

// --- DATA FETCHING ---
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_id ASC")->fetchAll();

$student_data = null;
$student_marks = [];

if ($view_student_id) {
    $stmt = $pdo->prepare("SELECT u.*, s.admission_number, c.class_name 
                           FROM users u 
                           JOIN students s ON u.user_id = s.student_id 
                           LEFT JOIN classes c ON u.class_id = c.class_id
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
    $sql = "SELECT users.user_id, users.full_name, users.email, students.admission_number, students.class_id 
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
        /* === THEME VARIABLES === */
        :root { 
            --primary: #FF6600; 
            --primary-hover: #e65c00;
            --dark: #212b36; 
            --light-bg: #f4f6f8; 
            --white: #ffffff; 
            --border: #dfe3e8; 
            --nav-height: 75px;
        }
        
        /* Layout Fix: Allow natural scrolling */
        html, body { 
            background-color: var(--light-bg); 
            margin: 0; padding: 0; 
            font-family: 'Public Sans', sans-serif;
            overflow-y: auto;
            height: auto;
        }

        /* === TOP NAVIGATION BAR === */
        .top-navbar {
            position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height);
            background: var(--white); z-index: 1000;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-bottom: 1px solid var(--border);
            box-sizing: border-box;
        }

        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }

        .nav-menu { display: flex; gap: 5px; align-items: center; }
        .nav-item {
            text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem;
            padding: 10px 15px; border-radius: 8px; transition: 0.2s;
            display: flex; align-items: center; gap: 6px;
        }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }

        .btn-logout {
            text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.85rem;
            padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s;
        }
        .btn-logout:hover { background: #ff4d4f; color: white; }

        /* === CONTENT AREA (SCROLLABLE) === */
        .main-content {
            margin-top: var(--nav-height);
            padding: 0;
            width: 100%;
            min-height: calc(100vh - var(--nav-height));
            display: block;
        }

        .page-header {
            background: var(--white); padding: 20px 40px;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid var(--border);
        }
        .page-title { margin: 0; font-size: 1.5rem; color: var(--dark); font-weight: 700; }

        .content-container { padding: 30px 40px; }

        /* === GRID & FOLDERS === */
        .grid-container {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 25px;
        }
        .folder-card {
            background: var(--white); padding: 30px; border-radius: 16px;
            text-align: center; cursor: pointer; transition: 0.3s;
            border: 1px solid transparent; box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }
        .folder-card:hover { transform: translateY(-5px); box-shadow: 0 12px 24px rgba(0,0,0,0.08); border-color: rgba(255, 102, 0, 0.3); }
        .folder-icon { font-size: 3.5rem; color: #ffd1b3; margin-bottom: 10px; }
        .folder-card:hover .folder-icon { color: var(--primary); }

        /* === PROFILE VIEW === */
        .profile-wrapper { padding: 40px; display: grid; grid-template-columns: 300px 1fr; gap: 40px; }
        .profile-card { background: var(--white); padding: 30px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid var(--border); }
        .avatar-circle { width: 100px; height: 100px; background: #fff0e6; color: var(--primary); font-size: 3rem; font-weight: 700; display: flex; align-items: center; justify-content: center; border-radius: 50%; margin: 0 auto 20px; }
        .stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px; }
        .stat-item { background: var(--white); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid var(--border); box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .stat-val { font-size: 1.8rem; font-weight: 800; color: var(--dark); display: block; margin-bottom: 5px; }
        .stat-lbl { font-size: 0.75rem; text-transform: uppercase; color: #637381; font-weight: 700; letter-spacing: 0.5px; }

        /* === MODALS & TABLES === */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(33, 43, 54, 0.5); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center; }
        .modal-box { background: var(--white); width: 90%; max-width: 900px; max-height: 85vh; border-radius: 16px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 25px 50px rgba(0,0,0,0.2); }
        .modal-header { padding: 20px 30px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #fafbfc; }
        .modal-body { padding: 0; overflow-y: auto; }
        
        .styled-table { width: 100%; border-collapse: collapse; }
        .styled-table th { background: #f4f6f8; padding: 15px 30px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #637381; font-weight: 700; position: sticky; top: 0; z-index: 5; }
        .styled-table td { padding: 15px 30px; border-bottom: 1px solid var(--border); vertical-align: middle; }

        .btn-action { background: var(--primary); color: white; padding: 10px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer; transition: 0.2s; }
        .btn-outline { background: white; border: 1px solid var(--border); color: #637381; padding: 8px 16px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-weight: 500; }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box">
            <img src="../assets/images/logo.png" alt="NGA">
        </div>
        <span class="nav-brand-text">NGA Admin</span>
    </a>

    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item">
            <i class='bx bxs-dashboard'></i> <span>Dashboard</span>
        </a>
        <a href="students.php" class="nav-item active">
            <i class='bx bxs-user-detail'></i> <span>Students</span>
        </a>
        <a href="teachers.php" class="nav-item">
            <i class='bx bxs-id-card'></i> <span>Teachers</span>
        </a>
        <a href="classes.php" class="nav-item">
            <i class='bx bxs-school'></i> <span>Classes</span>
        </a>
        <a href="settings.php" class="nav-item">
            <i class='bx bxs-cog'></i> <span>Settings</span>
        </a>
    </div>

    <div class="nav-user">
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="main-content">

    <?php if ($view_student_id && $student_data): ?>
        
        <div class="page-header">
            <div style="display:flex; align-items:center; gap:15px;">
                <a href="students.php" class="btn-outline" style="border:none; padding-left:0;"><i class='bx bx-arrow-back'></i> Back</a>
                <h1 class="page-title">Student Profile</h1>
            </div>
            <button class="btn-outline" onclick="window.print()"><i class='bx bx-printer'></i> Print Report</button>
        </div>

        <div class="profile-wrapper">
            <div class="profile-card" style="text-align: center; height: fit-content;">
                <div class="avatar-circle">
                    <?php echo strtoupper(substr($student_data['full_name'], 0, 1)); ?>
                </div>
                <h2 style="margin:0; font-size:1.4rem; color:var(--dark);"><?php echo htmlspecialchars($student_data['full_name']); ?></h2>
                <p style="color:#637381; margin:5px 0 20px; font-weight: 500;"><?php echo htmlspecialchars($student_data['class_name'] ?? 'Unassigned'); ?></p>
                
                <div style="text-align: left; background: #f8f9fa; padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
                    <p style="margin-bottom:12px;"><strong style="color:var(--dark);">Adm No:</strong> <span style="float:right; color:#666;"><?php echo $student_data['admission_number']; ?></span></p>
                    <p style="margin-bottom:12px;"><strong style="color:var(--dark);">Email:</strong> <span style="float:right; color:#666; font-size:0.9rem;"><?php echo $student_data['email'] ?: '-'; ?></span></p>
                    <p style="margin-bottom:0;"><strong style="color:var(--dark);">Code:</strong> <span style="float:right; font-family:monospace; background:#e0e0e0; padding:2px 8px; border-radius:4px; font-weight:bold;"><?php echo $student_data['access_key']; ?></span></p>
                </div>

                <div style="margin-top: 25px;">
                    <form method="POST" onsubmit="return confirm('Reset password to 123456?');">
                        <input type="hidden" name="student_id" value="<?php echo $student_data['user_id']; ?>">
                        <button type="submit" name="reset_password" class="btn-outline" style="width:100%; justify-content:center; border-color:#ffccbc; color:#d84315;">
                            <i class='bx bx-reset'></i> Reset Password
                        </button>
                    </form>
                </div>
            </div>

            <div style="display: flex; flex-direction: column;">
                <div class="stat-row">
                    <div class="stat-item">
                        <span class="stat-val"><?php echo count($student_marks); ?></span>
                        <span class="stat-lbl">Subjects Graded</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-val" style="color:var(--primary);">96%</span>
                        <span class="stat-lbl">Attendance</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-val" style="color:#00c853;">Active</span>
                        <span class="stat-lbl">Account Status</span>
                    </div>
                </div>

                <div class="profile-card">
                    <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:15px;">Academic Performance</h3>
                    <?php if (empty($student_marks)): ?>
                        <div style="text-align:center; padding:40px; color:#999;">
                            <i class='bx bx-book-open' style="font-size:40px; margin-bottom:15px; color:#ddd;"></i><br>
                            No academic records found for this student yet.
                        </div>
                    <?php else: ?>
                        <table class="styled-table">
                            <thead><tr><th>Subject</th><th>Details</th><th>Score</th><th>Grade</th></tr></thead>
                            <tbody>
                                <?php foreach($student_marks as $sub => $data): 
                                    $pct = ($data['max'] > 0) ? ($data['total'] / $data['max']) * 100 : 0;
                                    $grade = ($pct >= 80) ? 'A' : (($pct >= 70) ? 'B' : (($pct >= 50) ? 'C' : 'F'));
                                    $color = ($grade == 'A') ? '#00c853' : (($grade == 'F') ? '#ff1744' : '#ffab00');
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($sub); ?></strong></td>
                                    <td style="font-size:0.85rem; color:#666;"><?php echo implode(", ", $data['details']); ?></td>
                                    <td><?php echo $data['total']; ?> <small style="color:#999;">/ <?php echo $data['max']; ?></small></td>
                                    <td><span style="color:white; background:<?php echo $color; ?>; padding:4px 12px; border-radius:20px; font-weight:bold; font-size:0.75rem;"><?php echo $grade; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php else: ?>
        
        <div class="page-header">
            <h1 class="page-title">Manage Students</h1>
            <a href="add_student.php" class="btn-action"><i class='bx bx-plus'></i> Add New Student</a>
        </div>

        <?php if($message): ?>
            <div style="margin:20px 40px 0; background:#d4edda; color:#155724; padding:15px; border-radius:8px; border:1px solid #c3e6cb;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="content-container">
            <div class="search-container" style="margin-bottom: 25px;">
                <input type="text" class="search-input" placeholder="Search for a student name..." style="width: 100%; max-width: 600px; padding: 15px 20px; border: 1px solid var(--border); border-radius: 12px; background: var(--white); box-shadow: 0 4px 15px rgba(0,0,0,0.03); outline: none;">
            </div>

            <div class="grid-container">
                <?php foreach($classes as $class): ?>
                    <?php $count = isset($students_by_class[$class['class_id']]) ? count($students_by_class[$class['class_id']]) : 0; ?>
                    
                    <div class="folder-card" onclick="openModal('modal-<?php echo $class['class_id']; ?>')">
                        <i class='bx bxs-folder folder-icon'></i>
                        <h3 style="margin:0; font-size:1.1rem; font-weight:700; color:var(--dark);"><?php echo htmlspecialchars($class['class_name']); ?></h3>
                        <div class="folder-meta" style="color: #919eab; font-size: 0.85rem; font-weight: 600; margin-top: 5px;"><?php echo $count; ?> Students</div>
                    </div>

                    <div id="modal-<?php echo $class['class_id']; ?>" class="modal">
                        <div class="modal-box">
                            <div class="modal-header">
                                <h2 style="margin:0; font-size:1.2rem;"><?php echo htmlspecialchars($class['class_name']); ?></h2>
                                <button class="btn-outline" onclick="closeModal('modal-<?php echo $class['class_id']; ?>')"><i class='bx bx-x'></i> Close</button>
                            </div>
                            <div class="modal-body">
                                <table class="styled-table">
                                    <thead>
                                        <tr><th>Name</th><th>Admission No</th><th>Status</th><th>Actions</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if($count > 0): ?>
                                            <?php foreach($students_by_class[$class['class_id']] as $s): ?>
                                            <tr>
                                                <td style="font-weight:600;"><?php echo htmlspecialchars($s['full_name']); ?></td>
                                                <td style="color:#666;"><?php echo $s['admission_number']; ?></td>
                                                <td>
                                                    <?php if($s['email']): ?>
                                                        <span style="background:#d1e7dd; color:#0f5132; padding:2px 8px; border-radius:4px; font-size:0.75rem; font-weight:700;">ACTIVE</span>
                                                    <?php else: ?>
                                                        <span style="background:#fff3cd; color:#856404; padding:2px 8px; border-radius:4px; font-size:0.75rem; font-weight:700;">PENDING</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="?view_id=<?php echo $s['user_id']; ?>" class="btn-outline" style="padding:5px 10px; font-size:0.75rem;">View Profile</a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" style="text-align:center; padding:40px; color:#999;">No students in this class.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    window.onclick = function(e) { if (e.target.classList.contains('modal')) { e.target.style.display = 'none'; } }
</script>

</body>
</html>