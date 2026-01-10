<?php
// teacher/take_attendance.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php"); exit;
}

$teacher_id = $_SESSION['user_id'];
$message = "";

// 1. FETCH CLASSES & SUBJECTS ASSIGNED TO TEACHER
$sql = "SELECT c.class_id, c.class_name, s.subject_id, s.subject_name 
        FROM teacher_allocations ta
        JOIN classes c ON ta.class_id = c.class_id
        JOIN subjects s ON ta.subject_id = s.subject_id
        WHERE ta.teacher_id = ? ORDER BY c.class_name";
$stmt = $pdo->prepare($sql);
$stmt->execute([$teacher_id]);
$allocations = $stmt->fetchAll();

// 2. HANDLE SELECTION
$selected_allocation = $_GET['allocation'] ?? ($allocations[0]['class_id'].'_'.$allocations[0]['subject_id'] ?? '');
$selected_date = $_GET['date'] ?? date('Y-m-d');

if ($selected_allocation) {
    list($class_id, $subject_id) = explode('_', $selected_allocation);

    // 3. HANDLE FORM SUBMISSION
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO attendance (student_id, class_id, subject_id, teacher_id, attendance_date, status) 
                                   VALUES (?, ?, ?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE status = VALUES(status)");
            
            foreach ($_POST['status'] as $std_id => $status) {
                $stmt->execute([$std_id, $class_id, $subject_id, $teacher_id, $selected_date, $status]);
            }
            $pdo->commit();
            $message = "Attendance saved successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
        }
    }

    // 4. FETCH STUDENTS & EXISTING ATTENDANCE
    // We LEFT JOIN attendance to see if marks exist for this specific date/subject
    $std_sql = "SELECT s.student_id, u.full_name, s.admission_number, a.status 
                FROM students s
                JOIN users u ON s.student_id = u.user_id
                LEFT JOIN attendance a ON s.student_id = a.student_id 
                     AND a.subject_id = ? AND a.attendance_date = ?
                WHERE s.class_id = ? ORDER BY u.full_name";
    $std_stmt = $pdo->prepare($std_sql);
    $std_stmt->execute([$subject_id, $selected_date, $class_id]);
    $students = $std_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Take Attendance | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --success: #00ab55; --danger: #ff4d4f; --warning: #ffc107; }
        body { background: var(--light-bg); font-family: 'Public Sans', sans-serif; margin: 0; padding-bottom: 80px; }

        .top-navbar { position: fixed; top: 0; width: 100%; height: 75px; background: white; z-index: 1000; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; border-bottom: 1px solid #dfe3e8; box-sizing: border-box; }
        .nav-brand { font-weight: 800; font-size: 1.2rem; color: var(--dark); display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .btn-back { background: #f4f6f8; color: var(--dark); padding: 8px 15px; border-radius: 8px; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 5px; }

        .container { max-width: 900px; margin: 100px auto 0; padding: 0 20px; }

        /* CONTROLS CARD */
        .controls-card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); display: flex; gap: 20px; align-items: flex-end; margin-bottom: 25px; }
        .form-group { flex: 1; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 700; color: #637381; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #dfe3e8; border-radius: 10px; font-size: 0.95rem; }
        .btn-load { background: var(--dark); color: white; border: none; padding: 12px 25px; border-radius: 10px; font-weight: 700; cursor: pointer; }

        /* STUDENT LIST */
        .student-card { background: white; padding: 15px 20px; border-radius: 12px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #dfe3e8; transition: 0.2s; }
        .student-card:hover { border-color: var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        
        .st-info h4 { margin: 0; color: var(--dark); font-size: 1rem; }
        .st-info span { font-size: 0.8rem; color: #919eab; }

        /* ATTENDANCE TOGGLES */
        .status-options { display: flex; gap: 5px; background: #f4f6f8; padding: 4px; border-radius: 8px; }
        .status-radio { display: none; }
        .status-label { 
            padding: 8px 15px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; 
            cursor: pointer; transition: 0.2s; color: #637381; user-select: none;
        }
        
        /* Interactive Colors when checked */
        .status-radio[value="Present"]:checked + .status-label { background: var(--success); color: white; }
        .status-radio[value="Absent"]:checked + .status-label { background: var(--danger); color: white; }
        .status-radio[value="Late"]:checked + .status-label { background: var(--warning); color: white; }
        .status-radio[value="Excused"]:checked + .status-label { background: #00bcd4; color: white; }

        .sticky-footer { position: fixed; bottom: 0; left: 0; width: 100%; background: white; padding: 15px 0; border-top: 1px solid #dfe3e8; display: flex; justify-content: center; gap: 15px; box-shadow: 0 -5px 20px rgba(0,0,0,0.05); }
        .btn-save { background: var(--primary); color: white; border: none; padding: 12px 50px; border-radius: 12px; font-weight: 800; font-size: 1rem; cursor: pointer; transition: 0.2s; }
        .btn-save:hover { background: #e65c00; transform: translateY(-2px); }
        .btn-mark-all { background: white; border: 2px solid var(--success); color: var(--success); padding: 12px 20px; border-radius: 12px; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand"><i class='bx bxs-calendar-check'></i> Attendance Register</a>
    <a href="dashboard.php" class="btn-back"><i class='bx bx-arrow-back'></i> Dashboard</a>
</nav>

<div class="container">
    
    <?php if($message): ?>
        <div style="background:#e6f7ed; color:#00ab55; padding:15px; border-radius:10px; margin-bottom:20px; font-weight:600;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="GET" class="controls-card">
        <div class="form-group">
            <label>Select Class & Subject</label>
            <select name="allocation" class="form-control">
                <?php foreach($allocations as $alloc): ?>
                    <option value="<?php echo $alloc['class_id'].'_'.$alloc['subject_id']; ?>" <?php if($selected_allocation == $alloc['class_id'].'_'.$alloc['subject_id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($alloc['class_name'] . ' - ' . $alloc['subject_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Date</label>
            <input type="date" name="date" value="<?php echo $selected_date; ?>" class="form-control">
        </div>
        <button type="submit" class="btn-load">Load List</button>
    </form>

    <?php if(isset($students)): ?>
    <form method="POST">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0; color:var(--dark);">Student List (<?php echo count($students); ?>)</h3>
            <span style="font-size:0.9rem; color:#637381;">Marking for: <strong><?php echo date("D, d M Y", strtotime($selected_date)); ?></strong></span>
        </div>

        <?php foreach($students as $st): 
            $current_status = $st['status'] ?? 'Present'; // Default to present
        ?>
        <div class="student-card">
            <div class="st-info">
                <h4><?php echo htmlspecialchars($st['full_name']); ?></h4>
                <span><?php echo $st['admission_number']; ?></span>
            </div>
            
            <div class="status-options">
                <?php 
                $statuses = ['Present', 'Absent', 'Late', 'Excused'];
                foreach($statuses as $opt): 
                ?>
                <input type="radio" class="status-radio js-radio" 
                       name="status[<?php echo $st['student_id']; ?>]" 
                       id="st_<?php echo $st['student_id'].'_'.$opt; ?>" 
                       value="<?php echo $opt; ?>"
                       <?php if($current_status == $opt) echo 'checked'; ?>>
                <label class="status-label" for="st_<?php echo $st['student_id'].'_'.$opt; ?>"><?php echo substr($opt, 0, 1); ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div style="height:100px;"></div> <div class="sticky-footer">
            <button type="button" class="btn-mark-all" onclick="markAllPresent()">Mark All Present</button>
            <button type="submit" class="btn-save">Save Attendance</button>
        </div>
    </form>
    <?php endif; ?>

</div>

<script>
    function markAllPresent() {
        // Select all 'Present' radio buttons
        const presentRadios = document.querySelectorAll('input[value="Present"]');
        presentRadios.forEach(radio => {
            radio.checked = true;
        });
    }
</script>

</body>
</html>