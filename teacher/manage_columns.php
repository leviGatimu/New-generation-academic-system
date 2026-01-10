<?php
// teacher/manage_columns.php
session_start();
require '../config/db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    die("Access Denied");
}

$teacher_id = $_SESSION['user_id'];
$alloc_id = $_GET['alloc_id'] ?? 0;

// 2. GET CONTEXT (Class/Subject)
$stmt = $pdo->prepare("SELECT ta.*, c.class_name, c.class_id, s.subject_name, s.subject_id 
                       FROM teacher_allocations ta
                       JOIN classes c ON ta.class_id = c.class_id
                       JOIN subjects s ON ta.subject_id = s.subject_id
                       WHERE ta.allocation_id = :aid AND ta.teacher_id = :tid");
$stmt->execute(['aid' => $alloc_id, 'tid' => $teacher_id]);
$details = $stmt->fetch();

if (!$details) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>Error: Allocation not found or access denied.</h3>");
}

// 3. GET ACTIVE TERM
$term = $pdo->query("SELECT * FROM academic_terms WHERE is_active = 1")->fetch();
if (!$term) die("Error: No active term found.");

// 4. HANDLE ADD COLUMN
if (isset($_POST['add_column'])) {
    $cat_id = $_POST['category_id'];
    $max_score = (int) $_POST['max_score'];
    
    if ($max_score > 0) {
        // Insert new column definition
        $sql = "INSERT INTO class_assessments (class_id, subject_id, term_id, teacher_id, category_id, max_score) 
                VALUES (:cid, :sid, :term, :tid, :cat, :max)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'cid' => $details['class_id'], 
            'sid' => $details['subject_id'], 
            'term' => $term['term_id'], 
            'tid' => $teacher_id, 
            'cat' => $cat_id, 
            'max' => $max_score
        ]);
        
        // Refresh to show new column
        header("Location: manage_columns.php?alloc_id=$alloc_id");
        exit;
    }
}

// 5. HANDLE DELETE COLUMN
if (isset($_GET['del'])) {
    $aid = $_GET['del'];
    // Only delete if it belongs to this teacher
    $stmt = $pdo->prepare("DELETE FROM class_assessments WHERE assessment_id = :aid AND teacher_id = :tid");
    $stmt->execute(['aid' => $aid, 'tid' => $teacher_id]);
    
    header("Location: manage_columns.php?alloc_id=$alloc_id");
    exit;
}

// 6. FETCH DATA FOR VIEW
// A. Available Categories (from Admin)
$categories = $pdo->query("SELECT * FROM grading_categories ORDER BY name ASC")->fetchAll();

// B. Existing Columns (Created by this Teacher for this Subject)
$my_cols_sql = "SELECT ca.*, gc.name as cat_name 
                FROM class_assessments ca 
                JOIN grading_categories gc ON ca.category_id = gc.id
                WHERE ca.class_id = :cid AND ca.subject_id = :sid AND ca.term_id = :term
                ORDER BY ca.created_at ASC";
$my_cols_stmt = $pdo->prepare($my_cols_sql);
$my_cols_stmt->execute([
    'cid' => $details['class_id'], 
    'sid' => $details['subject_id'], 
    'term' => $term['term_id']
]);
$my_columns = $my_cols_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Assessments</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        body { background: #f4f6f8; font-family: sans-serif; }
        .container { max-width: 800px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        
        .setup-box { background: #f9fafb; padding: 20px; border-radius: 8px; border: 1px solid #dfe3e8; margin: 20px 0; }
        
        label { display: block; font-size: 0.85rem; font-weight: bold; margin-bottom: 5px; color: #637381; }
        select, input { width: 100%; padding: 10px; border: 1px solid #dfe3e8; border-radius: 4px; background: white; }
        
        .btn-add { background: #2c3e50; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 23px; }
        .btn-add:hover { background: #1a252f; }

        .col-list { margin-top: 30px; }
        .col-item { display: flex; justify-content: space-between; padding: 15px; border-bottom: 1px solid #eee; align-items: center; }
        .col-item:last-child { border-bottom: none; }
        
        .badge { background: #e3f2fd; color: #1565c0; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; margin-left: 10px; }
        
        .btn-del { color: #ff5630; text-decoration: none; font-size: 1.2rem; padding: 5px; }
        .btn-del:hover { background: #ffe4de; border-radius: 4px; }

        .back-link { text-decoration: none; color: #637381; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 5px; }
        .back-link:hover { color: #212b36; }
    </style>
</head>
<body>

<div class="container">
    <a href="enter_marks.php?alloc_id=<?php echo $alloc_id; ?>" class="back-link">
        <i class='bx bx-arrow-back'></i> Back to Marks Entry
    </a>
    
    <h2 style="margin-top: 15px; color: #212b36;">Setup Gradebook</h2>
    <h4 style="margin: 0; color: #637381; font-weight: normal;">
        <?php echo htmlspecialchars($details['subject_name']); ?> - <?php echo htmlspecialchars($details['class_name']); ?>
    </h4>

    <div class="setup-box">
        <form method="POST" style="display: flex; gap: 15px;">
            <div style="flex: 2;">
                <label>Assessment Type</label>
                <select name="category_id" required>
                    <?php foreach($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1;">
                <label>Max Score</label>
                <input type="number" name="max_score" placeholder="e.g. 20" required min="1">
            </div>
            <div style="flex: 1;">
                <button type="submit" name="add_column" class="btn-add"><i class='bx bx-plus'></i> Add</button>
            </div>
        </form>
        <div style="font-size: 0.8rem; color: #919eab; margin-top: 10px;">
            <i class='bx bxs-info-circle'></i> Select a category (e.g., Test) and define what it is marked out of.
        </div>
    </div>

    <div class="col-list">
        <h3 style="border-bottom: 2px solid #dfe3e8; padding-bottom: 10px; font-size: 1rem; color: #212b36;">
            Current Columns
        </h3>
        
        <?php if(count($my_columns) > 0): ?>
            <?php foreach($my_columns as $col): ?>
                <div class="col-item">
                    <div style="display: flex; align-items: center;">
                        <span style="font-weight: 600; color: #212b36; font-size: 1.1rem;">
                            <?php echo htmlspecialchars($col['cat_name']); ?>
                        </span>
                        <span class="badge">
                            / <?php echo $col['max_score']; ?>
                        </span>
                    </div>
                    
                    <a href="?alloc_id=<?php echo $alloc_id; ?>&del=<?php echo $col['assessment_id']; ?>" 
                       onclick="return confirm('WARNING: Deleting this column will PERMANENTLY DELETE all student marks entered for it. Are you sure?')"
                       class="btn-del" title="Remove Column">
                       <i class='bx bx-trash'></i>
                    </a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; color: #919eab; padding: 30px;">
                No columns added yet.<br>Use the form above to add your first assessment column.
            </p>
        <?php endif; ?>
    </div>
    
    <div style="margin-top: 30px; text-align: right;">
        <a href="enter_marks.php?alloc_id=<?php echo $alloc_id; ?>" 
           style="background: #00AB55; color: white; text-decoration: none; padding: 12px 25px; border-radius: 4px; font-weight: bold; display: inline-block;">
           Done! Start Grading <i class='bx bx-right-arrow-alt'></i>
        </a>
    </div>

</div>

</body>
</html>