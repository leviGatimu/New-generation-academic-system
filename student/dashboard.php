<?php
// student/dashboard.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$student_id = $_SESSION['user_id'];

try {
    // 1. Fetch Student/Class Info AND Check Linking Status
    // We added a subquery (is_linked) to see if a parent exists for this student
    $stmt = $pdo->prepare("SELECT u.full_name, st.admission_number, st.parent_access_code, c.class_name, c.class_id,
                           (SELECT COUNT(*) FROM parent_student_link WHERE student_id = st.student_id) as is_linked 
                           FROM users u 
                           JOIN students st ON u.user_id = st.student_id 
                           JOIN classes c ON st.class_id = c.class_id 
                           WHERE u.user_id = ?");
    $stmt->execute([$student_id]);
    $me = $stmt->fetch();

    // 2. Calculate Overall Average
    $avg_stmt = $pdo->prepare("SELECT AVG((sm.score / ca.max_score) * 100) as overall_avg 
                               FROM student_marks sm 
                               JOIN class_assessments ca ON sm.assessment_id = ca.assessment_id 
                               WHERE sm.student_id = ?");
    $avg_stmt->execute([$student_id]);
    $overall_avg = round($avg_stmt->fetchColumn(), 1);

    // 3. Count Total Subjects
    $sub_stmt = $pdo->prepare("SELECT COUNT(*) FROM class_subjects WHERE class_id = ?");
    $sub_stmt->execute([$me['class_id']]);
    $subject_count = $sub_stmt->fetchColumn();

} catch (PDOException $e) {
    die("Dashboard Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Hub | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* === RESTORED YOUR THEME VARIABLES === */
        :root { --primary: #FF6600; --primary-hover: #e65c00; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; --border: #dfe3e8; --nav-height: 75px; }
        body { background-color: var(--light-bg); margin: 0; font-family: 'Public Sans', sans-serif; }
        
        /* Navbar & General Styles */
        .top-navbar { position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height); background: var(--white); z-index: 1000; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-bottom: 1px solid var(--border); box-sizing: border-box; }
        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; background: #fafbfc; border-radius: 8px; border: 1px solid var(--border); }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }
        .nav-menu { display: flex; gap: 5px; align-items: center; }
        .nav-item { text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem; padding: 10px 15px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }
        .btn-logout { text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s; }
        .btn-logout:hover { background: #ff4d4f; color: white; }
        
        /* Content Styles */
        .main-content { margin-top: var(--nav-height); padding: 40px 5%; }
        .welcome-card { background: white; border-radius: 20px; padding: 40px; display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--border); margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 16px; border: 1px solid var(--border); display: flex; align-items: center; gap: 20px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; background: #fff0e6; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .widget-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .white-card { background: white; border-radius: 16px; border: 1px solid var(--border); padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        .btn-link { width: 100%; background: var(--primary); color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; text-decoration: none; margin-top: 10px; }
        .btn-link:hover { background: var(--primary-hover); }
    </style>
</head>
<body>

<?php include '../includes/preloader.php'; ?>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box"><img src="../assets/images/logo.png" alt="NGA"></div>
        <span class="nav-brand-text">Student Portal</span>
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item active"><i class='bx bxs-dashboard'></i> <span>Dashboard</span></a>
        <a href="academics.php" class="nav-item"><i class='bx bxs-graduation'></i> <span>Academics</span></a>
        <a href="results.php" class="nav-item"><i class='bx bxs-spreadsheet'></i> <span>My Results</span></a>
        <a href="attendance.php" class="nav-item"><i class='bx bxs-calendar-check'></i> <span>Attendance</span></a>
    </div>
    <div class="nav-user"><a href="../logout.php" class="btn-logout">Logout</a></div>
</nav>

<div class="main-content">
    <div class="welcome-card">
        <div>
            <h1 style="margin:0; font-size:2rem; color: var(--dark);">Hello, <?php echo explode(' ', $me['full_name'])[0]; ?>!</h1>
            <p style="color:#637381; margin-top:10px;">Class: <strong><?php echo htmlspecialchars($me['class_name']); ?></strong></p>
        </div>
        <div style="text-align:right;">
            <span style="display:block; color:#919eab; font-size:0.75rem; font-weight:700;">ADMISSION NUMBER</span>
            <span style="font-size:1.3rem; font-weight:800; color:var(--dark);"><?php echo $me['admission_number']; ?></span>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class='bx bxs-medal'></i></div>
            <div><span style="font-size:1.5rem; font-weight:800;"><?php echo $overall_avg ?: '0'; ?>%</span><br><small>Overall Average</small></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#f0f7ff; color:#007bff;"><i class='bx bxs-book'></i></div>
            <div><span style="font-size:1.5rem; font-weight:800;"><?php echo $subject_count; ?></span><br><small>Active Subjects</small></div>
        </div>
    </div>

    <div class="widget-grid">
        <div class="white-card">
            <h3 style="margin-top:0;"><i class='bx bxs-bell-ring' style="color:var(--primary);"></i> Announcements</h3>
            <p style="color:#637381; font-size: 0.9rem;">No new announcements at this time.</p>
        </div>
        
        <div class="white-card" style="border-top: 4px solid var(--primary);">
            <h3 style="margin-top:0;"><i class='bx bxs-user-voice' style="color:var(--primary);"></i> Parent Access</h3>
            
            <?php if ($me['is_linked'] > 0): ?>
                <div style="text-align:center; padding: 20px;">
                    <i class='bx bxs-check-circle' style="font-size:3rem; color:#00ab55;"></i>
                    <p style="font-weight:bold; margin-top:10px; color:#212b36;">Account Linked</p>
                    <small style="color:#637381;">Your parent can now view your results.</small>
                </div>
            
            <?php else: ?>
                <p style="color:#637381; font-size: 0.85rem;">Share this code with your parents to link accounts.</p>
                
                <div style="background: #fff0e6; padding: 15px; border-radius: 12px; margin: 15px 0; border: 1px dashed var(--primary); text-align: center;">
                    <span style="font-size:1.4rem; font-weight:800; color:var(--dark); letter-spacing: 2px;">
                        <?php echo !empty($me['parent_access_code']) ? $me['parent_access_code'] : 'CONTACT ADMIN'; ?>
                    </span>
                </div>

                <?php if(!empty($me['parent_access_code'])): ?>
                    <button onclick="copyCode('<?php echo $me['parent_access_code']; ?>')" class="btn-link">
                        <i class='bx bx-copy'></i> Copy Code
                    </button>
                    <p style="font-size:0.8rem; color:#919eab; text-align:center; margin-top:10px;">
                        Parent should go to: <b>Sign Up > Parent</b>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyCode(code) {
    navigator.clipboard.writeText(code);
    alert("Code copied to clipboard!");
}
</script>
</body>
</html>