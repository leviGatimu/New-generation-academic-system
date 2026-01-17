<?php
// teacher/dashboard.php
session_start();
require '../config/db.php';

// SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$teacher_name = $_SESSION['name'];

// 1. FETCH ALLOCATIONS
$sql = "SELECT ta.*, c.class_name, s.subject_name, cat.category_name, cat.color_code 
        FROM teacher_allocations ta 
        JOIN classes c ON ta.class_id = c.class_id 
        JOIN subjects s ON ta.subject_id = s.subject_id
        LEFT JOIN class_categories cat ON c.category_id = cat.category_id
        WHERE ta.teacher_id = :tid 
        ORDER BY c.class_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['tid' => $teacher_id]);
$allocations = $stmt->fetchAll();

// 2. FETCH CLASS TEACHER ROLE
$ct_sql = "SELECT c.*, cat.category_name FROM classes c 
           LEFT JOIN class_categories cat ON c.category_id = cat.category_id
           WHERE c.class_teacher_id = :tid";
$stmt = $pdo->prepare($ct_sql);
$stmt->execute(['tid' => $teacher_id]);
$my_class = $stmt->fetch();

$total_courses = count($allocations);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | NGA</title>
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
        
        html, body { 
            background-color: var(--light-bg); 
            margin: 0; padding: 0; 
            font-family: 'Public Sans', sans-serif;
            overflow-y: auto;
        }

        /* === TOP NAVIGATION BAR (Standard) === */
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
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; background: #fafbfc; border-radius: 8px; border: 1px solid var(--border); }
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
            padding: 40px 5%;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        .welcome-banner {
            background: var(--white); padding: 30px; border-radius: 16px;
            margin-bottom: 35px; border: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }

        /* STATS */
        .stats-row {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px; margin-bottom: 40px;
        }
        .stat-card {
            background: var(--white); padding: 25px; border-radius: 16px;
            border: 1px solid var(--border); display: flex; align-items: center; gap: 20px;
        }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; background: #fff0e6; color: var(--primary); }

        /* COURSE GRID */
        .course-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }
        .course-card {
            background: var(--white);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 25px;
            transition: 0.3s;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
            min-height: 200px;
        }
        .course-card:hover { transform: translateY(-5px); box-shadow: 0 12px 24px rgba(0,0,0,0.1); border-color: var(--primary); }
        
        .cat-badge { font-size: 0.7rem; padding: 3px 8px; border-radius: 10px; color: white; font-weight: 700; text-transform: uppercase; }
        
        .course-actions {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
        }

        .btn-dash {
            padding: 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.2s;
            box-sizing: border-box;
        }

        .btn-primary-dash { background: var(--dark); color: white; }
        .btn-outline-dash { background: white; border: 1.5px solid var(--border); color: var(--dark); }

        .btn-dash:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
        .btn-primary-dash:hover { background: var(--primary); color: white; }

        @media (max-width: 1000px) { .nav-menu span { display: none; } }
    </style>
</head>
<body>

<?php include '../includes/preloader.php'; ?>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box">
            <img src="../assets/images/logo.png" alt="NGA">
        </div>
        <span class="nav-brand-text">Teacher Portal</span>
    </a>

    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item active">
            <i class='bx bxs-dashboard'></i> <span>Dashboard</span>
        </a>
        <a href="my_students.php" class="nav-item">
            <i class='bx bxs-user-detail'></i> <span>Students</span>
        </a>
        <a href="assessments.php" class="nav-item"> <i class='bx bxs-layer'></i> <span>Assessments</span>
        </a>
        <a href="view_all_marks.php" class="nav-item">
            <i class='bx bxs-edit'></i> <span>Grading</span>
        </a>
        <a href="messages.php" class="nav-item">
            <i class='bx bxs-chat'></i> <span>Chat</span>
        </a>
        
        <a href="take_attendance.php" class="nav-item">
            <i class='bx bxs-file-doc'></i> <span>Attendance</span>
        </a>
        <a href="profile.php" class="nav-item">
    <i class='bx bxs-user-circle'></i> <span>Profile</span>
</a>
    </div>

    <div class="nav-user">
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="main-content">
    
    <div class="welcome-banner">
        <div>
            <h2 style="margin:0; font-size:1.8rem; color:var(--dark);">Welcome, <?php echo htmlspecialchars($teacher_name); ?></h2>
            <p style="color: #637381; margin: 8px 0 0;">Manage your classes and academic results for the current term.</p>
        </div>
        <div style="text-align: right;">
            <div style="font-weight: 800; color: var(--dark);"><?php echo date("l, d M"); ?></div>
            <div style="color: var(--primary); font-weight: 700; font-size: 0.9rem;">Term 1 Active</div>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon"><i class='bx bxs-book-open'></i></div>
            <div>
                <div style="font-size: 1.5rem; font-weight: 800;"><?php echo $total_courses; ?></div>
                <div style="font-size: 0.85rem; color: #637381; font-weight: 600;">Active Subjects</div>
            </div>
        </div>

        <?php if($my_class): ?>
        <div class="stat-card" style="border-left: 5px solid var(--primary);">
            <div class="stat-icon" style="background:#fff3cd; color:#856404;"><i class='bx bxs-star'></i></div>
            <div>
                <div style="font-size: 1.2rem; font-weight: 800;"><?php echo htmlspecialchars($my_class['class_name']); ?></div>
                <div style="font-size: 0.85rem; color: #637381; font-weight: 600;">Class Teacher Role</div>
            </div>
        </div>
        <?php endif; ?>

        <div class="stat-card">
            <div class="stat-icon" style="background:#e6f7ed; color:#00ab55;"><i class='bx bxs-check-shield'></i></div>
            <div>
                <div style="font-size: 1.5rem; font-weight: 800;">Secure</div>
                <div style="font-size: 0.85rem; color: #637381; font-weight: 600;">Data Protection</div>
            </div>
        </div>
    </div>

    <h3 style="margin-bottom: 25px; color: var(--dark); font-size: 1.2rem; font-weight: 800;">My Teaching Allocations</h3>
    
    <div class="course-grid">
        <?php foreach($allocations as $row): ?>
        <div class="course-card">
            <span class="cat-badge" style="background: <?php echo $row['color_code']; ?>;">
                <?php echo htmlspecialchars($row['category_name']); ?>
            </span>
            <h3 style="margin: 15px 0 5px 0; color: var(--dark);"><?php echo htmlspecialchars($row['subject_name']); ?></h3>
            <p style="color: #637381; font-weight: 600; margin: 0;"><?php echo htmlspecialchars($row['class_name']); ?></p>
            
            <div class="course-actions">
                <a href="enter_marks.php?alloc_id=<?php echo $row['allocation_id']; ?>" class="btn-dash btn-primary-dash">
                    <i class='bx bxs-edit-alt'></i> Enter Marks
                </a>
                <a href="view_marks.php?alloc_id=<?php echo $row['allocation_id']; ?>" class="btn-dash btn-outline-dash">
                    <i class='bx bxs-spreadsheet'></i> View Marks
                </a>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if($total_courses == 0): ?>
            <div style="grid-column: 1/-1; text-align: center; padding: 50px; color: #919eab;">
                <i class='bx bx-folder-open' style="font-size: 3rem;"></i>
                <p>No subjects have been allocated to you yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <div style="height: 60px;"></div>
</div>

</body>
</html>