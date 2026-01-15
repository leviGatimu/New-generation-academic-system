<?php
// teacher/mark_online.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php"); exit;
}

$teacher_id = $_SESSION['user_id'];
$assessment_id = $_GET['id'] ?? null;
$current_student_id = $_GET['student_id'] ?? null;

// 1. FETCH EXAM DETAILS
// Note: Depending on your DB, ensure 'online_assessments' ID maps to 'assessment_id' in report cards
$stmt = $pdo->prepare("SELECT oa.*, c.class_name, s.subject_name 
                        FROM online_assessments oa
                        JOIN classes c ON oa.class_id = c.class_id
                        JOIN subjects s ON oa.subject_id = s.subject_id
                        WHERE oa.id = ? AND oa.teacher_id = ?");
$stmt->execute([$assessment_id, $teacher_id]);
$exam = $stmt->fetch();

if (!$exam) die("Assessment not found or access denied.");

// 2. FETCH STUDENTS & SUBMISSIONS
$std_sql = "SELECT st.student_id, u.full_name, st.admission_number, sub.id as sub_id, sub.is_marked, sub.obtained_marks
            FROM students st
            JOIN users u ON st.student_id = u.user_id
            LEFT JOIN assessment_submissions sub ON (st.student_id = sub.student_id AND sub.assessment_id = ?)
            WHERE st.class_id = ?
            ORDER BY u.full_name ASC";
$std_stmt = $pdo->prepare($std_sql);
$std_stmt->execute([$assessment_id, $exam['class_id']]);
$students = $std_stmt->fetchAll();

// Default to first student if none selected
if (!$current_student_id && !empty($students)) {
    $current_student_id = $students[0]['student_id'];
}

// 3. FETCH DATA FOR SELECTED STUDENT
$current_submission = null;
$student_answers = [];

if ($current_student_id) {
    $sub_stmt = $pdo->prepare("SELECT * FROM assessment_submissions WHERE assessment_id = ? AND student_id = ?");
    $sub_stmt->execute([$assessment_id, $current_student_id]);
    $current_submission = $sub_stmt->fetch();

    if ($current_submission) {
        $student_answers = json_decode($current_submission['submission_text'], true) ?? [];
    }
}

// 4. FETCH QUESTIONS & OPTIONS
$q_stmt = $pdo->prepare("SELECT * FROM assessment_questions WHERE assessment_id = ? ORDER BY id ASC");
$q_stmt->execute([$assessment_id]);
$questions = $q_stmt->fetchAll();

$opt_stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id IN (SELECT id FROM assessment_questions WHERE assessment_id = ?)");
$opt_stmt->execute([$assessment_id]);
$options = $opt_stmt->fetchAll();
$opt_map = [];
foreach($options as $opt) { $opt_map[$opt['question_id']][] = $opt; }

// 5. HANDLE SAVE & SYNC (THE FIX IS HERE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    $total_score = 0;
    
    // A. Calculate Score (Auto + Manual)
    foreach($questions as $q) {
        if ($q['question_type'] == 'multiple_choice') {
            $user_ans = $student_answers[$q['id']] ?? null;
            foreach ($opt_map[$q['id']] as $opt) {
                if ($opt['is_correct'] && $opt['id'] == $user_ans) {
                    $total_score += $q['points'];
                }
            }
        } else {
            // Add Manual Points from Form
            $manual_points = $_POST['points'][$q['id']] ?? 0;
            $total_score += $manual_points;
        }
    }

    $teacher_comment = $_POST['teacher_comment'] ?? '';
    
    // B. Update the Online Submission Record
    $upd = $pdo->prepare("UPDATE assessment_submissions SET obtained_marks = ?, teacher_comment = ?, is_marked = 1 WHERE id = ?");
    $upd->execute([$total_score, $teacher_comment, $current_submission['id']]);

    // C. SYNC TO MAIN REPORT CARD (student_marks table)
    if (isset($_POST['sync_to_records'])) {
        
        // 1. Check if mark exists in main table
        $chk = $pdo->prepare("SELECT mark_id FROM student_marks WHERE student_id = ? AND assessment_id = ?");
        $chk->execute([$current_student_id, $assessment_id]);
        $exists = $chk->fetch();

        if ($exists) {
            // Update existing mark
            $sync_stmt = $pdo->prepare("UPDATE student_marks SET score = ? WHERE mark_id = ?");
            $sync_stmt->execute([$total_score, $exists['mark_id']]);
        } else {
            // Insert new mark
            $sync_stmt = $pdo->prepare("INSERT INTO student_marks (student_id, assessment_id, subject_id, score) VALUES (?, ?, ?, ?)");
            $sync_stmt->execute([
                $current_student_id, 
                $assessment_id, 
                $exam['subject_id'], 
                $total_score
            ]);
        }
        
        $msg = "Graded and Synced to Report Card!";
    } else {
        $msg = "Grade Saved (Not Synced).";
    }

    // Refresh
    header("Location: mark_online.php?id=$assessment_id&student_id=$current_student_id&msg=" . urlencode($msg));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Grading Station | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light: #f4f6f8; --border: #dfe3e8; }
        body { background: var(--light); height: 100vh; overflow: hidden; display: flex; flex-direction: column; font-family: 'Public Sans', sans-serif; margin: 0; }

        /* NAVBAR */
        .top-bar { height: 60px; background: white; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 20px; flex-shrink: 0; }
        .back-btn { text-decoration: none; color: #637381; font-weight: 600; display: flex; align-items: center; gap: 5px; }

        /* MAIN LAYOUT */
        .workspace { display: flex; flex: 1; overflow: hidden; }

        /* SIDEBAR */
        .student-sidebar { width: 300px; background: white; border-right: 1px solid var(--border); overflow-y: auto; display: flex; flex-direction: column; }
        .sidebar-header { padding: 15px; border-bottom: 1px solid var(--border); background: #fafbfc; }
        .student-item { padding: 15px; border-bottom: 1px solid #f0f0f0; cursor: pointer; display: flex; justify-content: space-between; align-items: center; text-decoration: none; color: inherit; transition:0.2s; }
        .student-item:hover { background: #f8f9fa; }
        .student-item.active { background: #fff5f0; border-left: 4px solid var(--primary); }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; background: #ccc; }
        .status-dot.marked { background: #00ab55; box-shadow: 0 0 0 2px rgba(0,171,85,0.2); }
        .status-dot.pending { background: #ffc107; }

        /* GRADING CANVAS */
        .grading-area { flex: 1; overflow-y: auto; padding: 40px; background: #f4f6f8; }
        .paper { max-width: 800px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; }
        
        .paper-header { padding: 25px; border-bottom: 1px solid var(--border); background: #fff; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
        .total-display { font-size: 1.5rem; font-weight: 800; color: var(--primary); }

        .q-item { padding: 25px; border-bottom: 1px dashed var(--border); }
        .q-text { font-weight: 700; color: var(--dark); margin-bottom: 10px; font-size: 1.05rem; }
        .q-meta { font-size: 0.8rem; color: #919eab; text-transform: uppercase; font-weight: 700; margin-bottom: 15px; display: block; }

        /* Answer Boxes */
        .ans-box { background: #fafbfc; padding: 15px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 15px; }
        .mcq-option { display: flex; align-items: center; gap: 10px; padding: 8px; border-radius: 6px; }
        .mcq-correct { background: #e6f7ed; color: #00ab55; font-weight: 700; }
        .mcq-wrong { background: #fff0f0; color: #ff4d4f; }
        
        /* Grading Inputs */
        .grade-controls { display: flex; align-items: center; gap: 15px; background: #fff5f0; padding: 15px; border-radius: 8px; }
        .points-input { width: 60px; padding: 8px; border: 2px solid var(--primary); border-radius: 6px; font-weight: 700; text-align: center; font-size: 1rem; }

        /* FOOTER ACTIONS */
        .action-bar { background: white; padding: 20px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; position: sticky; bottom: 0; }
        
        .sync-toggle { display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
        .sync-checkbox { width: 20px; height: 20px; accent-color: var(--primary); }
        
        .btn-save { background: var(--dark); color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 1rem; transition: 0.2s; }
        .btn-save:hover { background: var(--primary); transform: translateY(-2px); }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="assessments.php" class="back-btn"><i class='bx bx-arrow-back'></i> Exit Grading</a>
    <h3 style="margin:0; font-size:1.1rem; color:var(--dark);"><?php echo htmlspecialchars($exam['title']); ?></h3>
    <div style="font-size:0.9rem; color:#637381;"><?php echo $exam['class_name']; ?> • <?php echo $exam['subject_name']; ?></div>
</div>

<div class="workspace">
    
    <div class="student-sidebar">
        <div class="sidebar-header">
            <input type="text" placeholder="Search student..." style="width:100%; padding:8px; border:1px solid #dfe3e8; border-radius:6px; box-sizing: border-box;">
        </div>
        <?php foreach($students as $st): 
            $active = ($st['student_id'] == $current_student_id) ? 'active' : '';
            $status_class = $st['is_marked'] ? 'marked' : ($st['sub_id'] ? 'pending' : '');
            $link = "?id=$assessment_id&student_id=" . $st['student_id'];
        ?>
        <a href="<?php echo $link; ?>" class="student-item <?php echo $active; ?>">
            <div>
                <div style="font-weight:700; font-size:0.9rem;"><?php echo htmlspecialchars($st['full_name']); ?></div>
                <div style="font-size:0.75rem; color:#919eab;"><?php echo $st['admission_number']; ?></div>
            </div>
            <div class="status-dot <?php echo $status_class; ?>"></div>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="grading-area">
        <?php if (isset($_GET['msg'])): ?>
            <div style="background:#e6f7ed; color:#00ab55; padding:15px; border-radius:8px; margin-bottom:20px; text-align:center; font-weight:600;">
                <i class='bx bxs-check-circle'></i> <?php echo htmlspecialchars($_GET['msg']); ?>
            </div>
        <?php endif; ?>

        <?php if(!$current_submission): ?>
            <div style="text-align:center; padding:100px; color:#919eab;">
                <i class='bx bx-user-x' style="font-size:4rem;"></i>
                <h2>Not Submitted</h2>
                <p>This student has not submitted the assessment yet.</p>
            </div>
        <?php else: ?>
        
        <form method="POST" class="paper">
            <div class="paper-header">
                <div>
                    <h2 style="margin:0; color:var(--dark);">Grading</h2>
                    <span style="font-size:0.85rem; color:#637381;">Review answers and assign points.</span>
                </div>
                <div>
                    <span style="display:block; font-size:0.75rem; color:#919eab; text-transform:uppercase;">Current Total</span>
                    <span class="total-display" id="grandTotal">0</span> <span style="font-size:1rem; color:#919eab;">/ <?php echo $exam['total_marks']; ?></span>
                </div>
            </div>

            <?php 
            $running_total = 0;
            foreach($questions as $index => $q): 
                $qid = $q['id'];
                $user_ans = $student_answers[$qid] ?? null;
                $points_earned = 0; 
            ?>
            <div class="q-item">
                <span class="q-meta">Question <?php echo $index + 1; ?> • <?php echo $q['points']; ?> Points</span>
                <div class="q-text"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></div>

                <?php if($q['question_type'] == 'multiple_choice'): ?>
                    <div class="ans-box">
                        <?php 
                        $is_correct = false;
                        foreach($opt_map[$qid] as $opt):
                            $class = '';
                            if($opt['id'] == $user_ans) {
                                if($opt['is_correct']) { $class = 'mcq-correct'; $is_correct = true; } 
                                else { $class = 'mcq-wrong'; }
                            } elseif ($opt['is_correct']) {
                                $class = 'mcq-correct'; 
                            }
                        ?>
                        <div class="mcq-option <?php echo $class; ?>">
                            <i class='bx <?php echo ($opt['id'] == $user_ans) ? 'bx-check-circle' : 'bx-circle'; ?>'></i>
                            <?php echo htmlspecialchars($opt['option_text']); ?>
                            <?php if($opt['id'] == $user_ans && $opt['is_correct']) echo '<span style="margin-left:auto; font-size:0.8rem;">(Student Chose)</span>'; ?>
                        </div>
                        <?php endforeach; 
                        
                        if($is_correct) { 
                            $points_earned = $q['points']; 
                            $running_total += $points_earned;
                        }
                        ?>
                    </div>
                    <div style="font-size:0.9rem; font-weight:700; color:<?php echo $is_correct ? '#00ab55':'#ff4d4f'; ?>">
                        Auto-Graded: <?php echo $points_earned; ?> / <?php echo $q['points']; ?>
                        <input type="hidden" class="calc-points" value="<?php echo $points_earned; ?>">
                    </div>

                <?php else: ?>
                    <div class="ans-box">
                        <p style="white-space: pre-wrap; margin:0;"><?php echo htmlspecialchars($user_ans ?? 'No answer.'); ?></p>
                    </div>
                    
                    <div class="grade-controls">
                        <label style="font-weight:700; color:var(--dark);">Score:</label>
                        <input type="number" name="points[<?php echo $qid; ?>]" 
                               class="points-input calc-points" 
                               value="0" min="0" max="<?php echo $q['points']; ?>" 
                               oninput="calculateTotal()">
                        <span>/ <?php echo $q['points']; ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div style="padding:25px;">
                <label style="font-weight:700; display:block; margin-bottom:10px;">Teacher Feedback</label>
                <textarea name="teacher_comment" rows="3" style="width:100%; padding:10px; border:1px solid var(--border); border-radius:8px; box-sizing: border-box; font-family: inherit;"><?php echo htmlspecialchars($current_submission['teacher_comment'] ?? ''); ?></textarea>
            </div>

            <div class="action-bar">
                <label class="sync-toggle">
                    <input type="checkbox" name="sync_to_records" class="sync-checkbox" checked>
                    <div>
                        <div style="font-weight:700; color:var(--dark);">Record to Report Card</div>
                        <div style="font-size:0.8rem; color:#637381;">Automatically updates the official academic records.</div>
                    </div>
                </label>

                <button type="submit" name="save_grades" class="btn-save">
                    <i class='bx bxs-save'></i> Save & Record
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
    function calculateTotal() {
        let total = 0;
        document.querySelectorAll('.calc-points').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        document.getElementById('grandTotal').innerText = total;
    }

    // Run once on load to set initial total (includes MCQ auto-scores)
    calculateTotal();
</script>

</body>
</html>