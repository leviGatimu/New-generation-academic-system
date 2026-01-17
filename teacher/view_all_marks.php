<?php
// teacher/view_all_marks.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php"); exit;
}

$teacher_id = $_SESSION['user_id'];

// 1. GET CLASSES
$class_stmt = $pdo->prepare("SELECT DISTINCT c.class_id, c.class_name 
                             FROM teacher_allocations ta
                             JOIN classes c ON ta.class_id = c.class_id
                             WHERE ta.teacher_id = ?
                             ORDER BY c.class_name");
$class_stmt->execute([$teacher_id]);
$my_classes = $class_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Marks | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; --border: #dfe3e8; --nav-height: 75px; }
        body { background: var(--light-bg); font-family: 'Public Sans', sans-serif; margin: 0; }

        /* === STANDARD TEACHER NAVBAR === */
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

        /* === MAIN CONTENT === */
        .main-content { margin-top: var(--nav-height); padding: 40px 5%; max-width: 1000px; margin-left: auto; margin-right: auto; padding-bottom: 100px; }
        
        .page-header h1 { font-size: 2rem; color: var(--dark); margin: 0; }
        .page-header p { color: #637381; margin: 5px 0 30px; }

        /* ACCORDION (CLASS) */
        .class-accordion {
            background: white; border-radius: 16px; margin-bottom: 20px; border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.02); overflow: hidden; transition: all 0.3s ease;
        }
        .class-header {
            padding: 20px 25px; cursor: pointer; display: flex; justify-content: space-between; align-items: center;
            background: white; border-bottom: 1px solid transparent; transition: 0.2s;
        }
        .class-header:hover { background: #fafbfc; }
        .class-title { font-size: 1.1rem; font-weight: 800; color: var(--dark); display: flex; align-items: center; gap: 10px; }
        .toggle-icon { font-size: 1.5rem; color: #919eab; transition: transform 0.3s ease; }

        /* Active State */
        .class-accordion.active { border-color: var(--primary); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
        .class-accordion.active .class-header { border-bottom-color: var(--border); background: #fff5f0; }
        .class-accordion.active .class-title { color: var(--primary); }
        .class-accordion.active .toggle-icon { transform: rotate(180deg); color: var(--primary); }
        .class-content { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-out; background: #fafbfc; }

        /* STUDENT LIST */
        .student-list { padding: 20px; display: grid; gap: 15px; }

        /* STUDENT CARD */
        .st-card {
            background: white; border: 1px solid var(--border); border-radius: 12px;
            overflow: hidden; transition: 0.2s;
        }
        .st-card:hover { border-color: #919eab; transform: translateY(-2px); }
        .st-card.open { border-color: var(--dark); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }

        .st-summary {
            padding: 15px 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center;
        }
        .st-name { font-weight: 700; color: var(--dark); font-size: 1rem; }
        .st-adm { font-size: 0.8rem; color: #919eab; }
        
        /* MARKS DRAWER */
        .marks-drawer {
            max-height: 0; overflow: hidden; transition: max-height 0.3s ease;
            background: #fff; border-top: 1px solid transparent;
        }
        .st-card.open .marks-drawer { border-top-color: #f0f0f0; max-height: 1000px; } /* Large max-height to allow expansion */

        .marks-grid {
            padding: 15px 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;
        }

        /* MARK PILL */
        .mark-pill {
            padding: 12px; border-radius: 10px; border: 1px solid #f0f0f0; background: #f9fafb;
            display: flex; flex-direction: column; gap: 5px; position: relative; overflow: hidden;
        }
        
        /* Online Marks Style */
        .mark-pill.online { background: #f0f9ff; border-color: #bae0ff; }
        .mark-pill.online .mk-type { color: #007bff; }
        
        /* Manual Marks Style */
        .mark-pill.manual { background: #fff; border-color: #e6e6e6; }
        
        .mk-subj { font-size: 0.75rem; font-weight: 800; color: #637381; text-transform: uppercase; }
        .mk-val { font-size: 1.1rem; font-weight: 800; color: var(--dark); }
        .mk-type { font-size: 0.75rem; color: #919eab; display: flex; align-items: center; gap: 4px; font-weight: 600; }

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
        <a href="assessments.php" class="nav-item"> <i class='bx bxs-layer'></i> <span>Assessments</span>
        </a>
        <a href="view_all_marks.php" class="nav-item active">
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
    <div class="page-header">
        <h1>Detailed Gradebook</h1>
        <p>Unified view of all Online Assessments and Manual Entries.</p>
    </div>

    <?php foreach($my_classes as $class): 
        $cid = $class['class_id'];
        
        // Fetch Students
        $st_sql = "SELECT s.student_id, u.full_name, s.admission_number 
                   FROM students s JOIN users u ON s.student_id = u.user_id 
                   WHERE s.class_id = ? ORDER BY u.full_name";
        $st_stmt = $pdo->prepare($st_sql);
        $st_stmt->execute([$cid]);
        $students = $st_stmt->fetchAll();
    ?>
    
    <div class="class-accordion">
        <div class="class-header" onclick="toggleClass(this)">
            <div class="class-title">
                <i class='bx bxs-school' style="color:var(--primary); font-size: 1.3rem;"></i> 
                <?php echo htmlspecialchars($class['class_name']); ?>
                <span style="font-size:0.8rem; background:#f0f0f0; padding:4px 10px; border-radius:12px; color:#637381; margin-left: 10px;">
                    <?php echo count($students); ?> Students
                </span>
            </div>
            <i class='bx bx-chevron-down toggle-icon'></i>
        </div>

        <div class="class-content">
            <div class="student-list">
                <?php foreach($students as $st): 
                    $sid = $st['student_id'];

                    // 1. MANUAL MARKS
                    $m_sql = "SELECT sm.score, ca.max_score, s.subject_name, 'Manual' as source, gc.name as type
                              FROM student_marks sm
                              JOIN class_assessments ca ON sm.assessment_id = ca.assessment_id
                              JOIN subjects s ON ca.subject_id = s.subject_id
                              LEFT JOIN grading_categories gc ON ca.category_id = gc.id
                              WHERE sm.student_id = ?";
                    $m_stmt = $pdo->prepare($m_sql);
                    $m_stmt->execute([$sid]);
                    $manual_marks = $m_stmt->fetchAll();

                    // 2. ONLINE MARKS
                    $o_sql = "SELECT sub.obtained_marks as score, oa.total_marks as max_score, s.subject_name, 'Online' as source, oa.type
                              FROM assessment_submissions sub
                              JOIN online_assessments oa ON sub.assessment_id = oa.id
                              JOIN subjects s ON oa.subject_id = s.subject_id
                              WHERE sub.student_id = ? AND sub.is_marked = 1";
                    $o_stmt = $pdo->prepare($o_sql);
                    $o_stmt->execute([$sid]);
                    $online_marks = $o_stmt->fetchAll();

                    // MERGE
                    $all_marks = array_merge($manual_marks, $online_marks);
                ?>
                
                <div class="st-card">
                    <div class="st-summary" onclick="toggleStudent(this)">
                        <div>
                            <div class="st-name"><?php echo htmlspecialchars($st['full_name']); ?></div>
                            <div class="st-adm">ID: <?php echo htmlspecialchars($st['admission_number']); ?></div>
                        </div>
                        <div style="font-size:0.8rem; color:#919eab; display:flex; align-items:center; gap:5px;">
                            <?php echo count($all_marks); ?> Records <i class='bx bx-chevron-right'></i>
                        </div>
                    </div>

                    <div class="marks-drawer">
                        <?php if(empty($all_marks)): ?>
                            <div style="padding:20px; text-align:center; color:#ccc; font-style:italic;">No marks recorded yet.</div>
                        <?php else: ?>
                            <div class="marks-grid">
                                <?php foreach($all_marks as $mk): 
                                    $is_online = ($mk['source'] == 'Online');
                                    $pill_class = $is_online ? 'online' : 'manual';
                                    $icon = $is_online ? 'bx-laptop' : 'bx-edit-alt';
                                ?>
                                <div class="mark-pill <?php echo $pill_class; ?>">
                                    <div class="mk-subj"><?php echo htmlspecialchars($mk['subject_name']); ?></div>
                                    <div class="mk-val"><?php echo $mk['score']; ?> <span style="font-size:0.8rem; color:#919eab;">/ <?php echo $mk['max_score']; ?></span></div>
                                    <div class="mk-type">
                                        <i class='bx <?php echo $icon; ?>'></i> <?php echo ucfirst($mk['type']); ?>
                                        <?php if($is_online) echo '<span style="font-size:0.7rem; color:#007bff; font-weight:800;">(Sync)</span>'; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
    function toggleClass(header) {
        const accordion = header.parentElement;
        const content = accordion.querySelector('.class-content');
        
        // Close others
        document.querySelectorAll('.class-accordion.active').forEach(acc => {
            if(acc !== accordion) {
                acc.classList.remove('active');
                acc.querySelector('.class-content').style.maxHeight = null;
            }
        });

        accordion.classList.toggle('active');
        if (accordion.classList.contains('active')) {
            content.style.maxHeight = content.scrollHeight + "px";
        } else {
            content.style.maxHeight = null;
        }
    }

    function toggleStudent(summary) {
        const card = summary.parentElement;
        card.classList.toggle('open');
        
        // Recalculate parent height to fit new content (Dynamic resizing)
        const parentContent = card.closest('.class-content');
        if(parentContent.style.maxHeight) {
            // Add a generous buffer to ensure inner content fits
            parentContent.style.maxHeight = (parentContent.scrollHeight + 1000) + "px"; 
        }
    }
</script>

</body>
</html>