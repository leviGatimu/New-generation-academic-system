<?php
// teacher/view_marks.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php"); exit;
}

$alloc_id = $_GET['alloc_id'] ?? null;

// Fetch Allocation Details (Class & Subject)
$stmt = $pdo->prepare("SELECT ta.*, c.class_name, s.subject_name 
                       FROM teacher_allocations ta 
                       JOIN classes c ON ta.class_id = c.class_id 
                       JOIN subjects s ON ta.subject_id = s.subject_id
                       WHERE ta.allocation_id = ? AND ta.teacher_id = ?");
$stmt->execute([$alloc_id, $_SESSION['user_id']]);
$details = $stmt->fetch();

if (!$details) { die("Access Denied or Invalid Allocation."); }

// Fetch marks for this specific class and subject
$marks_sql = "SELECT u.full_name, st.admission_number, sm.score, sm.mark_id, gc.name as assessment_name, ca.max_score
              FROM users u
              JOIN students st ON u.user_id = st.student_id
              LEFT JOIN student_marks sm ON st.student_id = sm.student_id
              JOIN class_assessments ca ON sm.assessment_id = ca.assessment_id
              JOIN grading_categories gc ON ca.category_id = gc.id
              WHERE st.class_id = ? AND ca.subject_id = ?
              ORDER BY u.full_name ASC";
$stmt = $pdo->prepare($marks_sql);
$stmt->execute([$details['class_id'], $details['subject_id']]);
$marks_data = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Marks | <?php echo $details['subject_name']; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --border: #dfe3e8; --light-bg: #f4f6f8; }
        body { background: var(--light-bg); font-family: sans-serif; margin: 0; padding-top: 80px; }
        
        .container { max-width: 1100px; margin: 0 auto; padding: 20px; }
        
        /* Professional Table Card */
        .marks-card {
            background: white; border-radius: 16px; border: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden;
        }
        
        .header-strip {
            padding: 20px 30px; border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
        }
        
        .styled-table { width: 100%; border-collapse: collapse; }
        .styled-table th { background: #fafbfc; padding: 15px 25px; text-align: left; color: #637381; font-size: 0.85rem; text-transform: uppercase; }
        .styled-table td { padding: 15px 25px; border-bottom: 1px solid #f4f6f8; color: var(--dark); }
        
        /* Edit Input Styling */
        .edit-input {
            width: 60px; padding: 5px; border: 1px solid var(--border);
            border-radius: 4px; text-align: center; display: none; /* Hidden by default */
        }
        
        .edit-mode .edit-input { display: inline-block; }
        .edit-mode .score-text { display: none; }
        
        .btn-toggle-edit {
            background: var(--dark); color: white; border: none; padding: 10px 20px;
            border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px;
        }
        .btn-save { background: #00ab55; display: none; }
    </style>
</head>
<body>

<?php include '../includes/preloader.php'; ?>

<div class="container">
    <a href="dashboard.php" style="text-decoration:none; color:#637381; font-weight:600; display:flex; align-items:center; gap:5px; margin-bottom:15px;">
        <i class='bx bx-arrow-back'></i> Back to Dashboard
    </a>

    <div class="marks-card" id="marksContainer">
        <div class="header-strip">
            <div>
                <h2 style="margin:0; font-size:1.3rem;"><?php echo $details['subject_name']; ?> Records</h2>
                <p style="margin:5px 0 0; color:#637381;"><?php echo $details['class_name']; ?></p>
            </div>
            
            <button class="btn-toggle-edit" onclick="toggleEditMode()" id="editBtn">
                <i class='bx bxs-edit'></i> Edit Marks
            </button>
            <button class="btn-toggle-edit btn-save" id="saveBtn" onclick="saveChanges()">
                <i class='bx bxs-save'></i> Save Changes
            </button>
        </div>

        <table class="styled-table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Adm No</th>
                    <th>Assessment</th>
                    <th style="text-align:center;">Score</th>
                    <th style="text-align:center;">Max</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($marks_data as $m): ?>
                <tr>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($m['full_name']); ?></td>
                    <td style="color:#919eab;"><?php echo $m['admission_number']; ?></td>
                    <td><span class="badge" style="background:#e3f2fd; color:#1565c0;"><?php echo $m['assessment_name']; ?></span></td>
                    <td style="text-align:center;">
                        <span class="score-text"><?php echo $m['score']; ?></span>
                        <input type="number" class="edit-input" value="<?php echo $m['score']; ?>" max="<?php echo $m['max_score']; ?>" data-id="<?php echo $m['mark_id']; ?>">
                    </td>
                    <td style="text-align:center; color:#919eab;">/ <?php echo $m['max_score']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleEditMode() {
    const container = document.getElementById('marksContainer');
    container.classList.toggle('edit-mode');
    
    const isEdit = container.classList.contains('edit-mode');
    document.getElementById('editBtn').style.display = isEdit ? 'none' : 'flex';
    document.getElementById('saveBtn').style.display = isEdit ? 'flex' : 'none';
}

function saveChanges() {
    // Here you would collect all .edit-input values and send to an update_marks.php via AJAX
    alert('Logic for saving marks would go here. Would you like me to build the AJAX handler?');
    toggleEditMode();
}
</script>

</body>
</html>