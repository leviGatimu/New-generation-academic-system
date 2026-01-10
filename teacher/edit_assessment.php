<?php
// teacher/edit_assessment.php
session_start();
require '../config/db.php';

// 1. SECURITY CHECKS
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php"); exit;
}

$teacher_id = $_SESSION['user_id'];
$assessment_id = $_GET['id'] ?? $_POST['assessment_id'] ?? null;
$success = '';
$error = '';

if (!$assessment_id) { header("Location: assessments.php"); exit; }

// 2. FETCH EXISTING ASSESSMENT DATA
// We use $exam_stmt here to keep it distinct
$exam_stmt = $pdo->prepare("SELECT * FROM online_assessments WHERE id = ? AND teacher_id = ?");
$exam_stmt->execute([$assessment_id, $teacher_id]);
$exam = $exam_stmt->fetch();

if (!$exam) { die("Error: Assessment not found or access denied."); }

// 3. FETCH TEACHER'S CLASSES (For dropdown)
// We use $opt_stmt here to avoid overwriting the first query
$opt_stmt = $pdo->prepare("SELECT c.class_id, c.class_name, s.subject_id, s.subject_name 
        FROM teacher_allocations ta
        JOIN classes c ON ta.class_id = c.class_id
        JOIN subjects s ON ta.subject_id = s.subject_id
        WHERE ta.teacher_id = ? ORDER BY c.class_name, s.subject_name");
$opt_stmt->execute([$teacher_id]);
$my_options = $opt_stmt->fetchAll();

// 4. HANDLE UPDATE SUBMISSION
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Check if DELETE
    if (isset($_POST['delete_assessment'])) {
        $pdo->prepare("DELETE FROM assessment_submissions WHERE assessment_id = ?")->execute([$assessment_id]);
        $pdo->prepare("DELETE FROM online_assessments WHERE id = ?")->execute([$assessment_id]);
        header("Location: assessments.php"); exit;
    }

    // UPDATE Logic
    $new_status = $exam['status'];
    if (isset($_POST['save_draft'])) { $new_status = 'draft'; }
    elseif (isset($_POST['publish'])) { $new_status = 'published'; }
    elseif (isset($_POST['unpublish'])) { $new_status = 'draft'; }

    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $target = explode('_', $_POST['target_class_subject']); 
    $class_id = $target[0];
    $subject_id = $target[1];
    
    $start = str_replace('T', ' ', $_POST['start_time']);
    $end = str_replace('T', ' ', $_POST['end_time']);
    
    $duration = $_POST['duration'];
    $marks = $_POST['total_marks'];
    $type = $_POST['type'];

    if (empty($title)) {
        $error = "Title is required.";
    } else {
        // Prepare Update Query
        $sql_update = "UPDATE online_assessments SET 
            title=?, description=?, class_id=?, subject_id=?, 
            start_time=?, end_time=?, duration_minutes=?, 
            total_marks=?, type=?, status=?
            WHERE id=? AND teacher_id=?";
        
        $upd = $pdo->prepare($sql_update);
        
        try {
            $upd->execute([
                $title, $desc, $class_id, $subject_id, 
                $start, $end, $duration, 
                $marks, $type, $new_status,
                $assessment_id, $teacher_id
            ]);
            
            $success = "Assessment updated successfully!";
            
            // Refresh Data using the CORRECT statement ($exam_stmt)
            $exam_stmt->execute([$assessment_id, $teacher_id]);
            $exam = $exam_stmt->fetch();
            
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Assessment | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --border: #dfe3e8; --light-bg: #f4f6f8; --nav-height: 75px; }
        body { background: var(--light-bg); margin: 0; font-family: 'Public Sans', sans-serif; }

        /* FIXED HEADER CSS */
        .top-navbar { position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height); background: white; z-index: 1000; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; border-bottom: 1px solid var(--border); box-sizing: border-box; }
        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; background: #fafbfc; border-radius: 8px; border: 1px solid var(--border); }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }
        .nav-menu { display: flex; gap: 10px; align-items: center; }
        .nav-item { text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem; padding: 10px 15px; border-radius: 8px; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }
        .btn-logout { text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s; }
        .btn-logout:hover { background: #ff4d4f; color: white; }

        /* CONTENT */
        .main-content { margin-top: var(--nav-height); padding: 40px 5%; max-width: 1400px; margin-left: auto; margin-right: auto; }
        .page-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        
        /* Pills & Badges */
        .status-pill { padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; display: flex; align-items: center; gap: 5px; }
        .pill-draft { background: #dfe3e8; color: #637381; }
        .pill-published { background: #e6f7ed; color: #00ab55; }

        .creation-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 40px; align-items: start; }
        .form-card { background: white; padding: 40px; border-radius: 20px; border: 1px solid var(--border); box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .form-group { margin-bottom: 25px; }
        label { display: block; font-weight: 700; color: var(--dark); margin-bottom: 8px; font-size: 0.9rem; }
        input, select, textarea { width: 100%; padding: 12px 15px; border: 1px solid var(--border); border-radius: 10px; font-size: 0.95rem; background: #fafbfc; box-sizing: border-box; }
        
        .action-bar { margin-top: 30px; padding-top: 30px; border-top: 1px solid #f4f6f8; display: flex; justify-content: space-between; align-items: center; }
        .right-actions { display: flex; gap: 15px; }
        .btn-main { background: var(--dark); color: white; padding: 12px 25px; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-main:hover { background: var(--primary); }
        .btn-publish { background: #00ab55; color: white; padding: 12px 25px; border: none; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 8px; }
        .btn-publish:hover { background: #007b55; }
        .btn-draft { background: white; border: 1px solid var(--border); color: #637381; padding: 12px 25px; border-radius: 10px; font-weight: 700; cursor: pointer; }
        .btn-danger { color: #ff4d4f; background: none; border: none; font-weight: 700; cursor: pointer; text-decoration: underline; font-size: 0.9rem; }

        .preview-wrapper { position: sticky; top: 100px; }
        .task-card-preview { background: white; border-radius: 16px; padding: 30px; border: 1px solid var(--border); position: relative; box-shadow: 0 20px 40px rgba(0,0,0,0.08); }
        .preview-badge { position: absolute; top: 20px; right: 20px; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; background: #fef3c7; color: #d97706; }
        
        .alert-success { background: #e6f7ed; color: #00ab55; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fee2e2; color: #dc2626; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; }
    </style>
</head>
<body>

<?php include '../includes/preloader.php'; ?>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box"><img src="../assets/images/logo.png" alt="NGA"></div>
        <span class="nav-brand-text">Teacher Portal</span>
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item">Dashboard</a>
        <a href="my_students.php" class="nav-item">Students</a>
        <a href="assessments.php" class="nav-item active">Assessments</a>
        <a href="view_all_marks.php" class="nav-item">Grading</a>
    </div>
    <a href="../logout.php" class="btn-logout">Logout</a>
</nav>

<div class="main-content">
    
    <div class="page-header">
        <div style="display:flex; align-items:center; gap:15px;">
            <h1 style="margin:0; color:var(--dark);">Edit Assessment</h1>
            <?php if($exam['status'] == 'published'): ?>
                <span class="status-pill pill-published"><i class='bx bxs-circle'></i> Live / Visible</span>
            <?php else: ?>
                <span class="status-pill pill-draft"><i class='bx bxs-circle'></i> Draft / Hidden</span>
            <?php endif; ?>
        </div>
        <a href="assessments.php" style="text-decoration:none; color:#637381; font-weight:700;">
            <i class='bx bx-arrow-back'></i> Return to Manager
        </a>
    </div>

    <div class="creation-grid">
        
        <div class="form-card">
            <?php if($success): ?>
                <div class="alert-success">
                    <i class='bx bxs-check-circle'></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert-error">
                    <i class='bx bxs-error-circle'></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="editForm">
                <input type="hidden" name="assessment_id" value="<?php echo htmlspecialchars($assessment_id); ?>">

                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" id="inpTitle" value="<?php echo htmlspecialchars($exam['title']); ?>" required onkeyup="updatePreview()">
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <div class="form-group">
                        <label>Target Class & Subject</label>
                        <select name="target_class_subject" id="inpSubject" onchange="updatePreview()">
                            <?php foreach($my_options as $opt): 
                                $val = $opt['class_id'].'_'.$opt['subject_id'];
                                $is_sel = ($opt['class_id'] == $exam['class_id'] && $opt['subject_id'] == $exam['subject_id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $val; ?>" data-subject="<?php echo htmlspecialchars($opt['subject_name']); ?>" <?php echo $is_sel; ?>>
                                    <?php echo htmlspecialchars($opt['class_name'] . ' - ' . $opt['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Assessment Type</label>
                        <select name="type" id="inpType" onchange="updatePreview()">
                            <option value="quiz" <?php if($exam['type']=='quiz') echo 'selected'; ?>>Quiz</option>
                            <option value="exam" <?php if($exam['type']=='exam') echo 'selected'; ?>>Exam</option>
                            <option value="assignment" <?php if($exam['type']=='assignment') echo 'selected'; ?>>Assignment</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="datetime-local" name="start_time" value="<?php echo $exam['start_time']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="datetime-local" name="end_time" value="<?php echo $exam['end_time']; ?>" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <div class="form-group">
                        <label>Duration (Minutes)</label>
                        <input type="number" name="duration" id="inpDuration" value="<?php echo $exam['duration_minutes']; ?>" required onkeyup="updatePreview()">
                    </div>
                    <div class="form-group">
                        <label>Total Marks</label>
                        <input type="number" name="total_marks" value="<?php echo $exam['total_marks']; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Instructions</label>
                    <textarea name="description" rows="4"><?php echo htmlspecialchars($exam['description']); ?></textarea>
                </div>

                <div class="action-bar">
                    <button type="submit" name="delete_assessment" class="btn-danger" onclick="return confirm('DANGER: Delete this assessment? This cannot be undone.');">
                        <i class='bx bx-trash'></i> Delete Assessment
                    </button>

                    <div class="right-actions">
                        <?php if($exam['status'] == 'draft'): ?>
                            <button type="submit" name="save_draft" class="btn-draft">Save Draft</button>
                            <button type="submit" name="publish" class="btn-publish" onclick="return confirm('Publish this assessment now? Students will be able to see it.');">
                                <i class='bx bx-rocket'></i> Publish Now
                            </button>
                        <?php else: ?>
                            <button type="submit" name="unpublish" class="btn-draft" onclick="return confirm('Unpublish this assessment? It will be hidden from students.');">
                                Unpublish (Draft)
                            </button>
                            <button type="submit" name="save_changes" class="btn-main">
                                <i class='bx bx-save'></i> Save Changes
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <div class="preview-wrapper">
            <span style="font-size:0.8rem; text-transform:uppercase; color:#919eab; font-weight:800; display:block; margin-bottom:15px;">Student Portal Preview</span>
            <div class="task-card-preview">
                <span class="preview-badge" id="prevBadge">QUIZ</span>
                <h3 style="margin: 0 0 10px; color: var(--dark); font-size:1.3rem;" id="prevTitle">Title</h3>
                <p style="margin:0; font-size: 0.95rem; color: #637381; font-weight: 600;">
                    <i class='bx bx-book-alt' style="color: var(--primary);"></i> <span id="prevSubject">Subject</span>
                </p>
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px dashed #dfe3e8; color: #637381; font-size: 0.85rem;">
                    <div style="margin-bottom:8px;"><i class='bx bx-time-five'></i> Duration: <span id="prevDuration">60</span> Minutes</div>
                    <div><i class='bx bx-calendar-event'></i> Availability: Scheduled</div>
                </div>
                <div style="margin-top: 25px; padding: 12px; background: var(--dark); color: white; text-align: center; border-radius: 8px; font-weight: 700; font-size: 0.9rem; opacity: 0.8;">
                    Start Assessment <i class='bx bx-right-arrow-alt'></i>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    function updatePreview() {
        const title = document.getElementById('inpTitle').value;
        document.getElementById('prevTitle').innerText = title ? title : 'Untitled Assessment';

        const type = document.getElementById('inpType').value;
        const badge = document.getElementById('prevBadge');
        badge.innerText = type.toUpperCase();
        if(type === 'exam') { badge.style.background = '#fee2e2'; badge.style.color = '#dc2626'; }
        else { badge.style.background = '#fef3c7'; badge.style.color = '#d97706'; }

        const sel = document.getElementById('inpSubject');
        const subject = sel.options[sel.selectedIndex].getAttribute('data-subject');
        document.getElementById('prevSubject').innerText = subject;

        const dur = document.getElementById('inpDuration').value;
        document.getElementById('prevDuration').innerText = dur ? dur : '0';
    }
    updatePreview();
</script>

</body>
</html>