<?php
// teacher/sync_marks.php
session_start();
require '../config/db.php';

// SECURITY
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    die("Access Denied");
}

if (!isset($_POST['assessment_id'])) {
    die("Error: Missing Data");
}

$assessment_id = $_POST['assessment_id'];
$teacher_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // 1. Get Assessment Details (to know subject, class, type)
    $stmt = $pdo->prepare("SELECT * FROM online_assessments WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$assessment_id, $teacher_id]);
    $exam = $stmt->fetch();

    if (!$exam) die("Assessment not found or permission denied.");

    // 2. Get all fully marked submissions for this assessment
    // We join with students table to ensure we have the correct class_id for the record
    $sub_stmt = $pdo->prepare("
        SELECT sub.student_id, sub.obtained_marks, st.class_id
        FROM assessment_submissions sub
        JOIN students st ON sub.student_id = st.student_id
        WHERE sub.assessment_id = ? AND sub.is_marked = 1 AND sub.obtained_marks IS NOT NULL
    ");
    $sub_stmt->execute([$assessment_id]);
    $submissions = $sub_stmt->fetchAll();

    if (empty($submissions)) {
        die("No marked submissions found to sync.");
    }

    // 3. Prepare the Insert/Update Statement for the main records table
    // IMPORTANT: Adjust 'exam_marks' and column names to match your actual database structure.
    // We use ON DUPLICATE KEY UPDATE to handle re-syncs cleanly.
    $sync_sql = "INSERT INTO exam_marks 
                 (student_id, class_id, subject_id, exam_type, marks, recorded_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE marks = VALUES(marks), recorded_at = NOW()";
    
    $sync_stmt = $pdo->prepare($sync_sql);

    $synced_count = 0;
    foreach ($submissions as $sub) {
        // Map assessment type to your DB enum if needed (e.g., 'quiz' -> 'Quiz 1')
        $db_exam_type = ucfirst($exam['type']); 

        $sync_stmt->execute([
            $sub['student_id'],
            $sub['class_id'],
            $exam['subject_id'],
            $db_exam_type,
            $sub['obtained_marks']
        ]);
        $synced_count++;
    }

    $pdo->commit();
    
    // Return success message for AJAX or redirect
    $_SESSION['sync_success'] = "Successfully synced $synced_count grades to the main records.";
    header("Location: assessments.php");

} catch (Exception $e) {
    $pdo->rollBack();
    die("Sync Error: " . $e->getMessage());
}
?>