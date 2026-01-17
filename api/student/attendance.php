<?php
// student/attendance.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$student_id = $_SESSION['user_id'];

// 1. CALCULATE OVERALL STATS
$total_sql = "SELECT 
                COUNT(*) as total_classes,
                SUM(CASE WHEN status IN ('Present', 'Late') THEN 1 ELSE 0 END) as present_count
              FROM attendance WHERE student_id = ?";
$stmt = $pdo->prepare($total_sql);
$stmt->execute([$student_id]);
$overall = $stmt->fetch();

$attendance_pct = ($overall['total_classes'] > 0) 
    ? round(($overall['present_count'] / $overall['total_classes']) * 100) 
    : 0;

// Determine Overall Color
$gauge_color = '#00ab55'; // Green
$status_label = "Excellent";
if($attendance_pct < 85) { $gauge_color = '#ffc107'; $status_label = "Average"; } // Orange
if($attendance_pct < 65) { $gauge_color = '#ff4d4f'; $status_label = "Warning"; } // Red

// 2. FETCH PER SUBJECT STATS
// We group by subject to show specific attendance for each class
$sub_sql = "SELECT s.subject_name,
            COUNT(*) as total,
            SUM(CASE WHEN a.status IN ('Present', 'Late') THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN a.status = 'Excused' THEN 1 ELSE 0 END) as excused
            FROM attendance a
            JOIN subjects s ON a.subject_id = s.subject_id
            WHERE a.student_id = ?
            GROUP BY s.subject_name
            ORDER BY s.subject_name";
$sub_stmt = $pdo->prepare($sub_sql);
$sub_stmt->execute([$student_id]);
$subjects = $sub_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Attendance | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* === SHARED VARIABLES === */
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f0f2f5; --white: #ffffff; --nav-height: 75px; }
        body { background: var(--light-bg); font-family: 'Public Sans', sans-serif; margin: 0; padding-bottom: 80px; }

        /* HEADER */
        .top-navbar {
            position: fixed; top: 0; width: 100%; height: var(--nav-height);
            background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); z-index: 1000;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 40px; border-bottom: 1px solid rgba(0,0,0,0.05); box-sizing: border-box;
        }
        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; font-weight: 800; color: var(--dark); font-size: 1.2rem; }
        .logo-box { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: #fafbfc; border-radius: 8px; border: 1px solid #dfe3e8; }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }

        .nav-menu { display: flex; gap: 5px; align-items: center; }
        .nav-item { text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem; padding: 10px 15px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }
        .btn-logout { text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s; }
        .btn-logout:hover { background: #ff4d4f; color: white; }

        /* HERO SECTION */
        .hero-section {
            margin-top: var(--nav-height);
            background: linear-gradient(135deg, #212b36 0%, #161c24 100%);
            color: white; padding: 50px 5% 90px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .hero-text h1 { margin: 0 0 10px 0; font-size: 2.2rem; }
        .hero-text p { color: rgba(255,255,255,0.7); margin: 0; }

        /* CIRCLE GAUGE */
        .gauge-container { width: 130px; height: 130px; position: relative; }
        .gauge-circle {
            width: 100%; height: 100%; border-radius: 50%;
            /* PHP Gradient moved to inline style in body to avoid VS Code errors */
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: scaleIn 0.5s ease-out;
        }
        .gauge-inner {
            width: 85%; height: 85%; background: #212b36; border-radius: 50%;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .pct-num { font-size: 2.2rem; font-weight: 800; line-height: 1; color: white; }
        .pct-label { font-size: 0.75rem; text-transform: uppercase; color: rgba(255,255,255,0.5); margin-top: 5px; }

        /* MAIN GRID */
        .grid-container {
            max-width: 1200px; margin: -50px auto 0; padding: 0 20px;
            display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px;
        }

        /* SUBJECT CARDS */
        .att-card {
            background: white; border-radius: 16px; padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            animation: slideUp 0.5s ease forwards; opacity: 0; transform: translateY(20px);
        }
        @keyframes slideUp { to { opacity: 1; transform: translateY(0); } }
        @keyframes scaleIn { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .sub-title { font-size: 1.1rem; font-weight: 800; color: var(--dark); display: flex; align-items: center; gap: 10px; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }

        /* PROGRESS BARS */
        .progress-row { margin-bottom: 15px; }
        .progress-info { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 5px; font-weight: 600; color: #637381; }
        .progress-bg { width: 100%; height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 4px; transition: width 1s ease; }

        /* STATS ROW */
        .stats-mini-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-top: 20px; padding-top: 15px; border-top: 1px dashed #dfe3e8; }
        .mini-stat { text-align: center; }
        .mini-val { display: block; font-weight: 800; font-size: 1.1rem; color: var(--dark); }
        .mini-lbl { font-size: 0.7rem; color: #919eab; text-transform: uppercase; }

        /* EMPTY STATE */
        .empty-state {
            grid-column: 1 / -1; background: white; padding: 60px; text-align: center;
            border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>



<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div style="width:40px;"><img src="../assets/images/logo.png" alt="" style="width:100%;"></div>
        Student Portal
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item"><i class='bx bxs-dashboard'></i> Dashboard</a>
        <a href="academics.php" class="nav-item"><i class='bx bxs-graduation'></i> Academics</a>
        <a href="results.php" class="nav-item"><i class='bx bxs-bar-chart-alt-2'></i> My Results</a>
        <a href="messages.php" class="nav-item"><i class='bx bxs-chat'></i> Messages</a>
        <a href="attendance.php" class="nav-item active"><i class='bx bxs-calendar-check'></i> <span>Attendance</span></a>
         <a href="class_ranking.php" class="nav-item">
            <i class='bx bxs-chat'></i> <span>Ranking</span>
        </a>
        <a href="profile.php" class="nav-item">
    <i class='bx bxs-user-circle'></i> <span>Profile</span>
</a>
    </div>
    <a href="../logout.php" class="btn-logout">Logout</a>
</nav>

<div class="hero-section">
    <div class="hero-text">
        <h1>Attendance Record</h1>
        <p>Your class presence overview.</p>
        <div style="margin-top:15px; display:inline-block; padding:8px 15px; background:rgba(255,255,255,0.1); border-radius:8px; font-size:0.9rem;">
            Status: <strong style="color:<?php echo $gauge_color; ?>"><?php echo $status_label; ?></strong>
        </div>
    </div>
    
    <div class="gauge-container">
        <div class="gauge-circle" style="background: conic-gradient(<?php echo $gauge_color; ?> <?php echo $attendance_pct; ?>%, rgba(255,255,255,0.1) 0);">
            <div class="gauge-inner">
                <div class="pct-num" style="color:<?php echo $gauge_color; ?>"><?php echo $attendance_pct; ?>%</div>
                <div class="pct-label">Present</div>
            </div>
        </div>
    </div>
</div>

<div class="grid-container">
    
    <?php if(empty($subjects)): ?>
        <div class="empty-state">
            <i class='bx bx-calendar-x' style="font-size: 4rem; color: #dfe3e8; margin-bottom: 20px;"></i>
            <h2 style="margin:0; color:var(--dark);">No Records Yet</h2>
            <p style="color:#919eab;">Attendance has not been marked for any subject yet.</p>
        </div>
    <?php else: 
        $delay = 0;
        foreach($subjects as $sub): 
            $delay += 0.1;
            $sub_pct = ($sub['total'] > 0) ? round(($sub['present'] / $sub['total']) * 100) : 0;
            
            // Color Logic
            $bar_color = '#00ab55';
            $badge_bg = '#e6f7ed'; $badge_color = '#00ab55'; $badge_text = 'Good';
            
            if($sub_pct < 85) { $bar_color = '#ffc107'; $badge_bg='#fff7e6'; $badge_color='#ffc107'; $badge_text='Avg'; }
            if($sub_pct < 65) { $bar_color = '#ff4d4f'; $badge_bg='#fff0f0'; $badge_color='#ff4d4f'; $badge_text='Low'; }
    ?>
    <div class="att-card" style="animation-delay: <?php echo $delay; ?>s;">
        <div class="card-header">
            <div class="sub-title">
                <i class='bx bxs-book-alt' style="color:var(--primary);"></i>
                <?php echo htmlspecialchars($sub['subject_name']); ?>
            </div>
            <span class="status-badge" style="background:<?php echo $badge_bg; ?>; color:<?php echo $badge_color; ?>">
                <?php echo $badge_text; ?>
            </span>
        </div>

        <div class="progress-row">
            <div class="progress-info">
                <span>Attendance Rate</span>
                <span><?php echo $sub_pct; ?>%</span>
            </div>
            <div class="progress-bg">
                <div class="progress-fill" style="width: <?php echo $sub_pct; ?>%; background: <?php echo $bar_color; ?>;"></div>
            </div>
        </div>

        <div class="stats-mini-grid">
            <div class="mini-stat">
                <span class="mini-val" style="color:#00ab55;"><?php echo $sub['present']; ?></span>
                <span class="mini-lbl">Present</span>
            </div>
            <div class="mini-stat">
                <span class="mini-val" style="color:#ff4d4f;"><?php echo $sub['absent']; ?></span>
                <span class="mini-lbl">Absent</span>
            </div>
            <div class="mini-stat">
                <span class="mini-val" style="color:#ffc107;"><?php echo $sub['late']; ?></span>
                <span class="mini-lbl">Late</span>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>

</div>

</body>
</html>