<?php
// admin/teachers.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Fetch all teachers
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'teacher' ORDER BY full_name ASC");
$teachers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Teachers | NGA Admin</title>
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
            box-sizing: border-box; /* Fix for Logout button off-screen */
        }

        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;  }
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

        /* === CONTENT AREA === */
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

        /* === TEACHER CARD STYLES === */
        .content-container { padding: 30px 40px; max-width: 1000px; margin: 0 auto; }

        .teacher-card {
            background: var(--white); padding: 25px; border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; border: 1px solid var(--border); border-left: 5px solid var(--primary);
            transition: transform 0.2s;
        }
        .teacher-card:hover { transform: translateX(5px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }

        .teacher-info h3 { margin: 0 0 5px 0; color: var(--dark); font-size: 1.1rem; }
        .teacher-meta { display: flex; align-items: center; gap: 15px; font-size: 0.9rem; color: #637381; }
        
        .badge { padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge-active { background: #d1e7dd; color: #0f5132; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .key-box { font-family: monospace; background: #eee; padding: 2px 6px; border-radius: 4px; color: var(--dark); }

        /* Buttons */
        .btn-action {
            background: var(--primary); color: white; padding: 10px 24px;
            border-radius: 8px; text-decoration: none; font-weight: 600;
            display: inline-flex; align-items: center; gap: 8px; border: none; cursor: pointer;
            transition: 0.2s; box-shadow: 0 4px 10px rgba(255, 102, 0, 0.2);
        }
        .btn-action:hover { background: var(--primary-hover); transform: translateY(-2px); }

        .btn-assign {
            background: var(--dark); color: white; padding: 8px 16px; 
            border-radius: 6px; font-size: 0.85rem; text-decoration: none; font-weight: 500;
            display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;
        }
        .btn-assign:hover { background: #334155; }

        /* Responsive */
        @media (max-width: 900px) {
            .nav-menu span { display: none; }
            .teacher-card { flex-direction: column; align-items: flex-start; gap: 15px; }
            .teacher-actions { width: 100%; display: flex; justify-content: flex-end; }
        }
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
        <a href="students.php" class="nav-item">
            <i class='bx bxs-user-detail'></i> <span>Students</span>
        </a>
        <a href="teachers.php" class="nav-item active">
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
    
    <div class="page-header">
        <h1 class="page-title">Manage Teachers</h1>
        <a href="add_teacher.php" class="btn-action">
            <i class='bx bx-user-plus'></i> Add New Teacher
        </a>
    </div>

    <div class="content-container">
        <?php if(count($teachers) > 0): ?>
            <?php foreach($teachers as $teacher): ?>
                <?php $isActive = !empty($teacher['email']); ?>
                
                <div class="teacher-card">
                    <div class="teacher-info">
                        <h3><?php echo htmlspecialchars($teacher['full_name']); ?></h3>
                        <div class="teacher-meta">
                            <?php if($isActive): ?>
                                <span class="badge badge-active">Active</span>
                                <span><i class='bx bx-envelope'></i> <?php echo htmlspecialchars($teacher['email']); ?></span>
                            <?php else: ?>
                                <span class="badge badge-pending">Pending Activation</span>
                                <span>Key: <span class="key-box"><?php echo $teacher['access_key']; ?></span></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="teacher-actions">
                        <a href="assign_teacher.php?id=<?php echo $teacher['user_id']; ?>" class="btn-assign">
                            <i class='bx bx-layer'></i> Assign Subjects
                        </a>
                    </div>
                </div>

            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 50px; color: #999;">
                <i class='bx bx-user-x' style="font-size: 3rem; margin-bottom: 10px;"></i>
                <p>No teachers found in the system.</p>
            </div>
        <?php endif; ?>
    </div>

    <div style="height: 60px;"></div>
</div>

</body>
</html>