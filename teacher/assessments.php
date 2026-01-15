<?php
// teacher/assessments.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php"); exit;
}

$teacher_id = $_SESSION['user_id'];

// 1. FETCH EXAMS (Sorted by ID DESC)
$stmt = $pdo->prepare("SELECT oa.*, c.class_name, s.subject_name,
                      (SELECT COUNT(*) FROM assessment_submissions sub WHERE sub.assessment_id = oa.id) as submission_count
                      FROM online_assessments oa
                      JOIN classes c ON oa.class_id = c.class_id
                      JOIN subjects s ON oa.subject_id = s.subject_id
                      WHERE oa.teacher_id = ?
                      ORDER BY oa.id DESC");
$stmt->execute([$teacher_id]);
$my_exams = $stmt->fetchAll();

// 2. CALCULATE STATS
$total_exams = count($my_exams);
$total_subs = 0;
$active_exams = 0;
$current_time = date("Y-m-d H:i:s");

foreach($my_exams as $ex) {
    $total_subs += $ex['submission_count'];
    if($ex['status'] == 'published' && $current_time <= $ex['end_time']) {
        $active_exams++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assessment Manager | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; --border: #dfe3e8; --nav-height: 75px; }
        body { background: var(--light-bg); margin: 0; font-family: 'Public Sans', sans-serif; }

        /* === NAVBAR === */
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

        /* === LAYOUT === */
        .main-content { margin-top: var(--nav-height); padding: 40px 5%; max-width: 1400px; margin-left: auto; margin-right: auto; }
        
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px; }
        .page-title h1 { margin: 0; font-size: 1.8rem; color: var(--dark); }
        .page-title p { margin: 5px 0 0; color: #637381; }

        .btn-create { 
            background: var(--dark); color: white; padding: 12px 25px; border-radius: 12px; 
            font-weight: 700; text-decoration: none; display: flex; align-items: center; gap: 10px; 
            box-shadow: 0 4px 12px rgba(33, 43, 54, 0.2); transition: 0.2s; 
        }
        .btn-create:hover { background: var(--primary); transform: translateY(-2px); }

        /* === STATS ROW === */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 20px; border-radius: 16px; border: 1px solid var(--border); display: flex; align-items: center; gap: 20px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; background: #f4f6f8; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--dark); }
        .stat-info h3 { margin: 0; font-size: 1.5rem; color: var(--dark); }
        .stat-info span { font-size: 0.85rem; color: #637381; font-weight: 600; }

        /* === EXAM GRID === */
        .exam-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; }
        
        .exam-card { 
            background: white; border-radius: 20px; border: 1px solid var(--border); padding: 25px; 
            position: relative; transition: all 0.3s ease; display: flex; flex-direction: column; justify-content: space-between;
        }
        .exam-card:hover { transform: translateY(-5px); box-shadow: 0 12px 24px rgba(0,0,0,0.06); border-color: var(--primary); }

        .card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
        .st-published { background: #e6f7ed; color: #00ab55; }
        .st-draft { background: #dfe3e8; color: #637381; }

        .card-title { margin: 0 0 5px 0; font-size: 1.1rem; color: var(--dark); padding-right: 20px; }
        .card-sub { font-size: 0.9rem; color: #637381; font-weight: 500; display: flex; align-items: center; gap: 6px; }

        .card-meta { 
            margin-top: 20px; padding-top: 15px; border-top: 1px dashed var(--border); 
            display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: #637381; 
        }
        .meta-item { display: flex; align-items: center; gap: 5px; }
        .meta-item b { color: var(--dark); }

        .card-actions { 
            margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; 
        }
        .btn-action { 
            padding: 10px; border-radius: 10px; text-align: center; text-decoration: none; 
            font-weight: 700; font-size: 0.9rem; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .btn-mark { background: var(--dark); color: white; }
        .btn-mark:hover { background: var(--primary); }
        .btn-edit { background: white; border: 1px solid var(--border); color: var(--dark); }
        .btn-edit:hover { border-color: var(--dark); background: #f4f6f8; }

        /* Empty State */
        .empty-box { grid-column: 1/-1; text-align: center; padding: 80px; background: white; border-radius: 20px; border: 2px dashed var(--border); }
        .empty-icon { font-size: 4rem; color: #dfe3e8; margin-bottom: 15px; }
    </style>
</head>
<body>


<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box">
            <img src="../assets/images/logo.png" alt="NGA">
        </div>
        <span class="nav-brand-text">Teacher Portal</span>
    </a>

    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item">
            <i class='bx bxs-dashboard'></i> <span>Dashboard</span>
        </a>
        <a href="my_students.php" class="nav-item">
            <i class='bx bxs-user-detail'></i> <span>Students</span>
        </a>
        <a href="assessments.php" class="nav-item active"> <i class='bx bxs-layer'></i> <span>Assessments</span>
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
<?php if(isset($_SESSION['sync_success'])): ?>
    <div style="background:#e6f7ed; color:#00ab55; padding:15px; border-radius:10px; margin-bottom:20px; display:flex; align-items:center; gap:10px;">
        <i class='bx bxs-check-circle'></i> <?php echo $_SESSION['sync_success']; unset($_SESSION['sync_success']); ?>
    </div>
<?php endif; ?>

<div class="main-content">
    
    <div class="page-header">
        <div class="page-title">
            <h1>Assessment Manager</h1>
            <p>Create, manage, and grade your digital exams.</p>
        </div>
        <a href="create_assessment.php" class="btn-create">
            <i class='bx bx-plus-circle'></i> Create New
        </a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="color:#007bff; background:#f0f7ff;"><i class='bx bx-layer'></i></div>
            <div class="stat-info">
                <h3><?php echo $total_exams; ?></h3>
                <span>Total Created</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="color:#00ab55; background:#e6f7ed;"><i class='bx bx-radar'></i></div>
            <div class="stat-info">
                <h3><?php echo $active_exams; ?></h3>
                <span>Active Now</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="color:#FF6600; background:#fff5f0;"><i class='bx bx-file'></i></div>
            <div class="stat-info">
                <h3><?php echo $total_subs; ?></h3>
                <span>Submissions</span>
            </div>
        </div>
    </div>

    <?php if (empty($my_exams)): ?>
        <div class="empty-box">
            <i class='bx bx-edit empty-icon'></i>
            <h3 style="margin:0; color:var(--dark);">No Assessments Yet</h3>
            <p style="color:#919eab; margin-top:5px;">Click "Create New" to set up your first exam or assignment.</p>
        </div>
    <?php else: ?>
        <div class="exam-grid">
            <?php foreach($my_exams as $exam): 
                $status_class = ($exam['status'] == 'published') ? 'st-published' : 'st-draft';
                $status_label = ($exam['status'] == 'published') ? 'Live' : 'Draft';
                $type_icon = ($exam['type'] == 'exam') ? 'bx-joystick' : 'bx-task';
            ?>
            <div class="exam-card">
                <div>
                    <div class="card-top">
                        <span style="font-size:0.8rem; font-weight:700; color:#919eab; text-transform:uppercase;">
                            <i class='bx <?php echo $type_icon; ?>'></i> <?php echo $exam['type']; ?>
                        </span>
                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                    </div>
                    
                    <h3 class="card-title"><?php echo htmlspecialchars($exam['title']); ?></h3>
                    <div class="card-sub">
                        <i class='bx bx-book-bookmark' style="color:var(--primary);"></i>
                        <?php echo htmlspecialchars($exam['subject_name']); ?>
                        <span style="color:#dfe3e8;">|</span>
                        <?php echo htmlspecialchars($exam['class_name']); ?>
                    </div>
                </div>

                <div>
                    <div class="card-meta">
                        <div class="meta-item"><i class='bx bx-time'></i> <b><?php echo $exam['duration_minutes']; ?></b> min</div>
                        <div class="meta-item"><i class='bx bx-user-check'></i> <b><?php echo $exam['submission_count']; ?></b> done</div>
                    </div>

                    <<div class="card-actions" style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:8px;">
    <a href="mark_online.php?id=<?php echo $exam['id']; ?>" class="btn-action btn-mark" title="Grade Submissions">
        <i class='bx bx-check-double'></i> Mark
    </a>
    
    <a href="edit_assessment.php?id=<?php echo $exam['id']; ?>" class="btn-action btn-edit" title="Edit Details">
        <i class='bx bx-edit-alt'></i> Edit
    </a>

    <?php if($exam['submission_count'] > 0): ?>
    <form action="sync_marks.php" method="POST" onsubmit="return confirm('Sync these grades to the main academic records? Existing records for this exam type will be updated.');">
        <input type="hidden" name="assessment_id" value="<?php echo $exam['id']; ?>">
        <button type="submit" class="btn-action" style="background:#00ab55; color:white; border:none; width:100%; cursor:pointer;" title="Sync to Official Records">
            <i class='bx bx-sync'></i> Sync
        </button>
    </form>
    <?php else: ?>
         <button class="btn-action" style="background:#f4f6f8; color:#ccc; border:none; cursor:not-allowed;" disabled>
            <i class='bx bx-sync'></i> Sync
        </button>
    <?php endif; ?>
</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

</body>
</html>