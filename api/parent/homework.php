<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') { header("Location: ../index.php"); exit; }

$parent_id = $_SESSION['user_id'];

// 1. Fetch Children
$stmt = $pdo->prepare("SELECT s.student_id, u.full_name, c.class_name, c.class_id 
                       FROM parent_student_link psl
                       JOIN students s ON psl.student_id = s.student_id
                       JOIN users u ON s.student_id = u.user_id
                       JOIN classes c ON s.class_id = c.class_id
                       WHERE psl.parent_id = ?");
$stmt->execute([$parent_id]);
$children = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Homework & Packages | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #fff; --nav-height: 80px; }
        body { background: var(--light-bg); margin: 0; font-family: 'Public Sans', sans-serif; }
        
        /* Reusing your Premium Header CSS */
        .top-navbar { position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height); background: rgba(255,255,255,0.95); backdrop-filter: blur(12px); z-index: 1000; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; border-bottom: 1px solid rgba(0,0,0,0.05); box-sizing: border-box; }
        .nav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; font-weight: 800; color: var(--dark); font-size: 1.2rem; }
        .nav-link { text-decoration: none; color: #637381; font-weight: 700; padding: 10px 18px; border-radius: 50px; transition: 0.3s; display: flex; align-items: center; gap: 8px; font-size: 0.95rem; }
        .nav-link:hover { background-color: #fff0e6; color: var(--primary); transform: translateY(-2px); }
        .nav-link.active { background: linear-gradient(135deg, #FF6600 0%, #ff8533 100%); color: white !important; box-shadow: 0 6px 15px rgba(255, 102, 0, 0.3); }

        .main-content { margin-top: var(--nav-height); padding: 40px 10%; }
        
        .white-card { background: white; border-radius: 16px; border: 1px solid #dfe3e8; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); margin-bottom: 30px; }
        
        .task-card { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #f4f6f8; transition: 0.2s; }
        .task-card:last-child { border-bottom: none; }
        .task-card:hover { background: #fafbfc; }
        
        .task-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-right: 15px; }
        .icon-hw { background: #e6f7ff; color: #007bff; }
        .icon-hol { background: #fff7e6; color: #d48806; }
        
        .badge { padding: 5px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; }
        .badge-hw { background: #e6f7ff; color: #007bff; }
        .badge-hol { background: #fff7e6; color: #d48806; }

        .btn-download { text-decoration: none; padding: 8px 15px; background: var(--primary); color: white; border-radius: 6px; font-weight: bold; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 5px; }
        .btn-download:hover { background: #e65c00; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <img src="../assets/images/logo.png" width="40" alt="Logo"> Parent Portal
    </a>
    <div style="display:flex; gap:10px;">
        <a href="dashboard.php" class="nav-link"><i class='bx bxs-dashboard'></i> Dashboard</a>
        <a href="homework.php" class="nav-link active"><i class='bx bxs-book-content'></i> Homework</a>
        <a href="../logout.php" class="nav-link" style="color:#ff4d4f;"><i class='bx bx-log-out'></i> Logout</a>
    </div>
</nav>

<div class="main-content">

    <h1 style="color:var(--dark); margin-bottom: 30px;">Homework & Holiday Packages</h1>

    <?php foreach ($children as $child): ?>
        <?php
            // Fetch Homework & Packages for this child's class
            $hw_stmt = $pdo->prepare("SELECT h.*, s.subject_name 
                                      FROM class_homework h 
                                      JOIN subjects s ON h.subject_id = s.subject_id 
                                      WHERE h.class_id = ? 
                                      ORDER BY h.due_date DESC");
            $hw_stmt->execute([$child['class_id']]);
            $tasks = $hw_stmt->fetchAll();
        ?>

        <div class="white-card">
            <div style="display:flex; align-items:center; gap:15px; margin-bottom:20px; border-bottom:2px solid #f4f6f8; padding-bottom:15px;">
                <div style="width:40px; height:40px; background:var(--primary); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                    <?php echo substr($child['full_name'], 0, 1); ?>
                </div>
                <div>
                    <h2 style="margin:0; font-size:1.2rem;"><?php echo $child['full_name']; ?></h2>
                    <span style="color:#637381; font-size:0.9rem;"><?php echo $child['class_name']; ?></span>
                </div>
            </div>

            <?php if(count($tasks) > 0): ?>
                <?php foreach($tasks as $t): ?>
                    <div class="task-card">
                        <div style="display:flex; align-items:center;">
                            <div class="task-icon <?php echo $t['type'] == 'Holiday Package' ? 'icon-hol' : 'icon-hw'; ?>">
                                <i class='bx <?php echo $t['type'] == 'Holiday Package' ? 'bxs-briefcase-alt-2' : 'bxs-pencil'; ?>'></i>
                            </div>
                            <div>
                                <div style="font-weight:700; color:var(--dark); font-size:1rem;"><?php echo $t['title']; ?></div>
                                <div style="color:#637381; font-size:0.85rem; margin-top:2px;">
                                    <?php echo $t['subject_name']; ?> • <span style="color:var(--primary);">Due: <?php echo $t['due_date']; ?></span>
                                </div>
                                <div style="color:#999; font-size:0.8rem; margin-top:4px;">
                                    <?php echo $t['description']; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div style="text-align:right;">
                            <span class="badge <?php echo $t['type'] == 'Holiday Package' ? 'badge-hol' : 'badge-hw'; ?>">
                                <?php echo $t['type']; ?>
                            </span>
                            
                            <?php if(!empty($t['file_path'])): ?>
                                <div style="margin-top:10px;">
                                    <a href="../<?php echo $t['file_path']; ?>" class="btn-download" download>
                                        <i class='bx bxs-download'></i> Download File
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align:center; padding:30px; color:#999;">
                    <i class='bx bx-check-circle' style="font-size:2rem; color:#00ab55;"></i>
                    <p>No pending homework or holiday packages.</p>
                </div>
            <?php endif; ?>
        </div>

    <?php endforeach; ?>
</div>
<script>
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
</script>
</body>
</html>