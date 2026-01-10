<?php
// admin/dashboard.php
session_start();
require '../config/db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// 2. GET LIVE DATA
$student_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$teacher_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher'")->fetchColumn();
$parent_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'parent'")->fetchColumn();
$class_count = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | NGA</title>
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
            margin: 0; 
            padding: 0; 
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

        /* PROFESSIONAL LOGO BOX */
        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        
        .logo-box {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
           
            border-radius: 8px;
     
        }
        
        .logo-box img {
            width: 80%;
            height: 80%;
            object-fit: contain;
        }

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

        /* === MAIN CONTENT (SCROLLABLE) === */
        .main-content {
            margin-top: var(--nav-height);
            padding: 40px 5%;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
            display: block;
            min-height: calc(100vh - var(--nav-height));
        }

        .welcome-banner {
            background: var(--white); padding: 30px; border-radius: 16px;
            margin-bottom: 35px; border: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        /* STATS GRID */
        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px; margin-bottom: 40px;
        }

        .stat-card {
            background: var(--white); padding: 25px; border-radius: 16px;
            border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            transition: 0.3s; position: relative;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        
        .stat-label { font-size: 0.85rem; color: #637381; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-number { font-size: 2.2rem; font-weight: 800; color: var(--dark); margin: 12px 0; }
        .stat-icon { position: absolute; right: 20px; top: 20px; font-size: 40px; opacity: 0.05; color: var(--dark); }

        /* BUTTONS */
        .action-grid { display: flex; gap: 15px; flex-wrap: wrap; }
        .btn-quick {
            padding: 14px 24px; background: var(--dark); color: white; border: none; 
            border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 10px;
            font-weight: 700; font-size: 0.95rem; transition: 0.2s; text-decoration: none;
        }
        .btn-quick:hover { background: #334155; transform: translateY(-2px); }
        .btn-orange { background: var(--primary); }
        .btn-orange:hover { background: var(--primary-hover); }

        @media (max-width: 1000px) {
            .nav-menu span { display: none; }
            .welcome-banner { flex-direction: column; text-align: center; gap: 20px; }
        }
    </style>
</head>
<body>
<?php include '../includes/preloader.php'; ?>
<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box">
            <img src="../assets/images/logo.png" alt="NGA">
        </div>
        <span class="nav-brand-text">NGA Admin</span>
    </a>

    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item active">
            <i class='bx bxs-dashboard'></i> <span>Dashboard</span>
        </a>
        <a href="students.php" class="nav-item">
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
    
    <div class="welcome-banner">
        <div>
            <h2 style="margin:0; font-size:1.8rem; color:var(--dark);">Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Administrator'); ?></h2>
            <p style="color: #637381; margin: 8px 0 0; font-size: 0.95rem;">System overview for New Generation Academy.</p>
        </div>
        <div style="text-align: right;">
            <div style="font-weight: 800; color: var(--dark); font-size: 1rem;"><?php echo date("l, d M Y"); ?></div>
            <div style="color: var(--primary); font-weight: 700; font-size: 0.9rem;">Term 1 Active</div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <i class='bx bxs-user-detail stat-icon'></i>
            <div class="stat-label">Total Students</div>
            <div class="stat-number"><?php echo $student_count; ?></div>
            <div style="color: #00ab55; font-size: 0.85rem; font-weight: 700;">
                Enrolled students
            </div>
        </div>

        <div class="stat-card">
            <i class='bx bxs-id-card stat-icon'></i>
            <div class="stat-label">Faculty Staff</div>
            <div class="stat-number"><?php echo $teacher_count; ?></div>
            <div style="color: #637381; font-size: 0.85rem;">Registered teachers</div>
        </div>

        <div class="stat-card">
            <i class='bx bxs-face stat-icon'></i>
            <div class="stat-label">Guardian Accounts</div>
            <div class="stat-number"><?php echo $parent_count; ?></div>
            <div style="color: #637381; font-size: 0.85rem;">Active parents</div>
        </div>

        <div class="stat-card">
            <i class='bx bxs-school stat-icon'></i>
            <div class="stat-label">Academic Levels</div>
            <div class="stat-number"><?php echo $class_count; ?></div>
            <div style="color: var(--primary); font-size: 0.85rem; font-weight: 700;">
                Classes managed
            </div>
        </div>
    </div>

    <h3 style="margin-bottom: 25px; color: var(--dark); font-size: 1.15rem; font-weight: 800;">Quick Actions</h3>
    <div class="action-grid">
        <a href="add_student.php" class="btn-quick btn-orange">
            <i class='bx bx-plus-circle'></i> Add New Student
        </a>
        <a href="add_teacher.php" class="btn-quick">
            <i class='bx bx-user-plus'></i> Register Teacher
        </a>
        <button class="btn-quick" onclick="alert('Module loading...')">
            <i class='bx bx-broadcast'></i> System Notice
        </button>
    </div>

    <div style="height: 60px;"></div>

</div>

</body>
</html>