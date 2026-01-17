<?php
// teacher/enter_marks.php
session_start();
require '../config/db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    die("Access Denied");
}

$teacher_id = $_SESSION['user_id'];
$alloc_id = $_GET['alloc_id'] ?? 0;
$success = '';
$error = '';

// 2. GET CONTEXT (Class/Subject) -> This defines $class_id and $subject_id
$stmt = $pdo->prepare("SELECT ta.*, c.class_name, c.class_id, s.subject_name, s.subject_id 
                       FROM teacher_allocations ta
                       JOIN classes c ON ta.class_id = c.class_id
                       JOIN subjects s ON ta.subject_id = s.subject_id
                       WHERE ta.allocation_id = :aid AND ta.teacher_id = :tid");
$stmt->execute(['aid' => $alloc_id, 'tid' => $teacher_id]);
$details = $stmt->fetch();
// ... after $details = $stmt->fetch();


if (!$details) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>Error: You are not assigned to this class.</h3>");
}

$class_id = $details['class_id'];
$subject_id = $details['subject_id'];

// 3. GET ACTIVE TERM -> This defines $current_term
$term_q = $pdo->query("SELECT * FROM academic_terms WHERE is_active = 1");
$current_term = $term_q->fetch();

if (!$current_term) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>Error: No active term found. Please contact Admin.</h3>");
}

// 4. FETCH TEACHER DEFINED COLUMNS
$cols_sql = "SELECT ca.*, gc.name 
             FROM class_assessments ca 
             JOIN grading_categories gc ON ca.category_id = gc.id
             WHERE ca.class_id = :cid AND ca.subject_id = :sid AND ca.term_id = :term 
             ORDER BY ca.created_at ASC";
$stmt = $pdo->prepare($cols_sql);
$stmt->execute([
    'cid' => $class_id, 
    'sid' => $subject_id, 
    'term' => $current_term['term_id']
]);
$columns = $stmt->fetchAll();

// 5. FETCH STUDENTS (Updated to join users and students tables)
$stud_sql = "SELECT u.user_id, u.full_name, u.access_key 
             FROM users u 
             JOIN students s ON u.user_id = s.student_id 
             WHERE s.class_id = :cid 
             ORDER BY u.full_name";

$stmt = $pdo->prepare($stud_sql);
$stmt->execute(['cid' => $class_id]);
$students = $stmt->fetchAll();

// 6. FETCH EXISTING MARKS
$marks_sql = "SELECT student_id, assessment_id, score FROM student_marks 
              WHERE assessment_id IN (SELECT assessment_id FROM class_assessments WHERE class_id=:cid AND subject_id=:sid AND term_id=:term)";
$stmt = $pdo->prepare($marks_sql);
$stmt->execute([
    'cid' => $class_id, 
    'sid' => $subject_id,
    'term' => $current_term['term_id']
]);
$raw_marks = $stmt->fetchAll();

// Map marks: $marks_map[student_id][assessment_id] = score
$marks_map = [];
foreach($raw_marks as $m) {
    $marks_map[$m['student_id']][$m['assessment_id']] = $m['score'];
}

// 7. SAVE LOGIC
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input_marks = $_POST['marks'] ?? []; // Structure: [student_id][assessment_id] = score
    
    try {
        $pdo->beginTransaction();
        $ins_stmt = $pdo->prepare("INSERT INTO student_marks (student_id, assessment_id, score) 
                                   VALUES (:uid, :aid, :score) 
                                   ON DUPLICATE KEY UPDATE score = :score_upd");
        
        foreach ($input_marks as $s_id => $asses) {
            foreach ($asses as $a_id => $val) {
                // If value is empty string, save as NULL, otherwise save the number
                $val = ($val === '') ? NULL : $val;
                $ins_stmt->execute(['uid'=>$s_id, 'aid'=>$a_id, 'score'=>$val, 'score_upd'=>$val]);
            }
        }
        $pdo->commit();
        $success = "Marks saved successfully!";
        
        // Refresh marks map to show updated data immediately
        $stmt = $pdo->prepare($marks_sql);
        $stmt->execute(['cid' => $class_id, 'sid' => $subject_id, 'term' => $current_term['term_id']]);
        $raw_marks = $stmt->fetchAll();
        $marks_map = [];
        foreach($raw_marks as $m) {
            $marks_map[$m['student_id']][$m['assessment_id']] = $m['score'];
        }
        
    } catch (Exception $e) { 
        $pdo->rollBack(); 
        $error = "Save Failed: " . $e->getMessage(); 
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enter Marks | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --sys-gray: #f4f6f8; --sys-border: #dfe3e8; --sys-dark: #212b36; }
        body { background-color: var(--sys-gray); }
        
        .header-strip {
            background: white; padding: 15px 30px; border-bottom: 1px solid #dfe3e8;
            display: flex; justify-content: space-between; align-items: center;
        }

        .marks-table { width: 100%; border-collapse: collapse; background: white; margin-top: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .marks-table th {
            background: #f9fafb; padding: 15px; text-align: left; font-size: 0.85rem;
            color: #637381; text-transform: uppercase; border-bottom: 2px solid #dfe3e8;
        }
        .marks-table td { padding: 10px 15px; border-bottom: 1px solid #eee; vertical-align: middle; }
        
        .mark-input {
            width: 80px; padding: 8px; border: 1px solid #dfe3e8; border-radius: 4px;
            font-weight: 600; text-align: center; color: #212b36;
        }
        .mark-input:focus { border-color: #2c3e50; outline: none; background: #f0f8ff; }

        .floating-save-bar {
            position: fixed; bottom: 0; left: 0; width: 100%;
            background: white; padding: 15px; border-top: 1px solid #dfe3e8;
            display: flex; justify-content: flex-end; gap: 15px; z-index: 100;
        }
        
        .btn-col { background: #2c3e50; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 5px; }
        .btn-save { background: #00AB55; color: white; border: none; padding: 10px 30px; border-radius: 4px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>

<div class="header-strip">
    <div>
        <a href="dashboard.php" style="color: #637381; text-decoration: none; font-size: 0.9rem;">
            <i class='bx bx-arrow-back'></i> Back
        </a>
        <h2 style="margin: 5px 0 0; color: #212b36;">
            <?php echo htmlspecialchars($details['subject_name']); ?> 
            <span style="color: #919eab; font-weight: normal;">/ <?php echo htmlspecialchars($details['class_name']); ?></span>
        </h2>
    </div>
    
    <div>
        <a href="manage_columns.php?alloc_id=<?php echo $alloc_id; ?>" class="btn-col">
           <i class='bx bx-cog'></i> Setup Columns
        </a>
    </div>
</div>

<?php if($success): ?>
    <div style="margin: 20px 30px; padding: 15px; background: #d1e7dd; color: #0f5132; border-radius: 8px;">
        <i class='bx bxs-check-circle'></i> <?php echo $success; ?>
    </div>
<?php endif; ?>

<div style="padding: 30px; padding-bottom: 100px; max-width: 1200px; margin: 0 auto;">
    
    <?php if(count($columns) == 0): ?>
        <div style="text-align: center; padding: 50px; background: white; border-radius: 8px;">
            <i class='bx bx-table' style="font-size: 3rem; color: #dfe3e8;"></i>
            <h3>No Gradebook Columns Yet</h3>
            <p style="color: #637381;">You haven't set up any grading columns (like Tests, Exams) for this class yet.</p>
            <a href="manage_columns.php?alloc_id=<?php echo $alloc_id; ?>" class="btn-col" style="margin-top: 10px;">
                Create Your First Column
            </a>
        </div>
    <?php else: ?>

        <form method="POST">
            <div style="overflow-x: auto; border-radius: 8px;">
                <table class="marks-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <?php foreach($columns as $col): ?>
                                <th style="text-align: center;">
                                    <?php echo htmlspecialchars($col['name']); ?> 
                                    <small style="display:block; color:#919eab;">(/<?php echo $col['max_score']; ?>)</small>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($students) > 0): ?>
                            <?php foreach($students as $s): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: #212b36;"><?php echo htmlspecialchars($s['full_name']); ?></div>
                                        <div style="font-size: 0.8rem; color: #919eab;"><?php echo htmlspecialchars($s['access_key']); ?></div>
                                    </td>
                                    
                                    <?php foreach($columns as $col): ?>
                                        <?php 
                                            // Get existing mark if any
                                            $val = $marks_map[$s['user_id']][$col['assessment_id']] ?? ''; 
                                        ?>
                                        <td style="text-align: center;">
                                            <input type="number" step="0.1" 
                                                   name="marks[<?php echo $s['user_id']; ?>][<?php echo $col['assessment_id']; ?>]" 
                                                   value="<?php echo $val; ?>" 
                                                   max="<?php echo $col['max_score']; ?>"
                                                   class="mark-input">
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="10" style="text-align:center; padding:30px;">No students found in this class.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="floating-save-bar">
                <span style="margin-right: auto; align-self: center; color: #637381; font-size: 0.9rem;">
                    <i class='bx bxs-info-circle'></i> Don't forget to save your changes.
                </span>
                <button type="submit" class="btn-save">Save Marks</button>
            </div>
        </form>

    <?php endif; ?>

</div>

</body>
</html>