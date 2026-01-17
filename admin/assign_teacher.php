<?php
// admin/assign_teacher.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin' || !isset($_GET['id'])) {
    die("Access Denied");
}

$teacher_id = $_GET['id'];

// 1. FETCH TEACHER INFO
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :id");
$stmt->execute(['id' => $teacher_id]);
$teacher = $stmt->fetch();

// 2. HANDLE: SET CLASS TEACHER ROLE
if (isset($_POST['set_class_teacher'])) {
    $class_id = $_POST['class_teacher_select']; // Can be empty if unassigning
    
    // First, remove them from any previous class leadership
    $pdo->prepare("UPDATE classes SET class_teacher_id = NULL WHERE class_teacher_id = :tid")->execute(['tid' => $teacher_id]);
    
    // Then, assign new if selected
    if (!empty($class_id)) {
        $pdo->prepare("UPDATE classes SET class_teacher_id = :tid WHERE class_id = :cid")->execute(['tid' => $teacher_id, 'cid' => $class_id]);
    }
    $success_msg = "Class Teacher status updated.";
}

// 3. HANDLE: ADD SUBJECT ALLOCATION
if (isset($_POST['add_allocation'])) {
    $c_id = $_POST['class_id'];
    $s_id = $_POST['subject_id'];
    
    // Prevent duplicates
    $check = $pdo->prepare("SELECT * FROM teacher_allocations WHERE teacher_id=:t AND class_id=:c AND subject_id=:s");
    $check->execute(['t'=>$teacher_id, 'c'=>$c_id, 's'=>$s_id]);
    
    if($check->rowCount() == 0) {
        $stmt = $pdo->prepare("INSERT INTO teacher_allocations (teacher_id, class_id, subject_id) VALUES (:t, :c, :s)");
        $stmt->execute(['t'=>$teacher_id, 'c'=>$c_id, 's'=>$s_id]);
        $success_msg = "Subject assigned successfully.";
    }
}

// 4. HANDLE: REMOVE ALLOCATION
if (isset($_GET['remove'])) {
    $alloc_id = $_GET['remove'];
    $pdo->prepare("DELETE FROM teacher_allocations WHERE allocation_id = :aid")->execute(['aid' => $alloc_id]);
    header("Location: assign_teacher.php?id=$teacher_id"); // Refresh to clear URL
    exit;
}

// FETCH DATA FOR DROPDOWNS
$classes = $pdo->query("SELECT * FROM classes")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects")->fetchAll();

// FETCH CURRENT ALLOCATIONS
$allocs = $pdo->prepare("SELECT ta.*, c.class_name, s.subject_name 
                         FROM teacher_allocations ta 
                         JOIN classes c ON ta.class_id = c.class_id 
                         JOIN subjects s ON ta.subject_id = s.subject_id 
                         WHERE ta.teacher_id = :tid");
$allocs->execute(['tid' => $teacher_id]);
$current_allocations = $allocs->fetchAll();

// CHECK IF THEY ARE A CLASS TEACHER ALREADY
$ct_check = $pdo->prepare("SELECT * FROM classes WHERE class_teacher_id = :tid");
$ct_check->execute(['tid' => $teacher_id]);
$is_class_teacher_of = $ct_check->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Teacher | NGA Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include '../includes/preloader.php'; ?>
<div class="dashboard-container">
    <div class="main-content" style="margin-left: 0; width: 100%;">
        <div class="top-bar">
            <h2>Allocations: <?php echo htmlspecialchars($teacher['full_name']); ?></h2>
            <a href="teachers.php" style="color: #7f8c8d; text-decoration: none;">&larr; Back</a>
        </div>

        <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid #3498db;">
            <h3><i class='bx bxs-star'></i> Class Teacher Responsibility</h3>
            <p>Is this teacher the main "Homeroom" teacher for a class? (Can see all marks)</p>
            
            <form method="POST" style="display: flex; gap: 10px;">
                <select name="class_teacher_select" class="form-control" style="width: auto;">
                    <option value="">-- No Class Leadership --</option>
                    <?php foreach($classes as $c): ?>
                        <option value="<?php echo $c['class_id']; ?>" 
                            <?php if($is_class_teacher_of && $is_class_teacher_of['class_id'] == $c['class_id']) echo 'selected'; ?>>
                            <?php echo $c['class_name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="set_class_teacher" class="btn-login" style="width: auto;">Save Role</button>
            </form>
        </div>

        <div style="background: white; padding: 20px; border-radius: 8px;">
            <h3><i class='bx bxs-book'></i> Subject Allocations</h3>
            <p>Which subjects do they teach, and in which classes?</p>

            <form method="POST" style="background: #f9f9f9; padding: 15px; border-radius: 8px; display: flex; gap: 10px; align-items: end; margin-bottom: 20px;">
                <div style="flex: 1;">
                    <label>Subject</label>
                    <select name="subject_id" class="form-control" required>
                        <?php foreach($subjects as $s): ?>
                            <option value="<?php echo $s['subject_id']; ?>"><?php echo $s['subject_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex: 1;">
                    <label>Class</label>
                    <select name="class_id" class="form-control" required>
                        <?php foreach($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>"><?php echo $c['class_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="add_allocation" class="btn-login" style="width: auto; margin-bottom: 15px;">+ Assign</button>
            </form>

            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #eee;">
                        <th style="padding: 10px;">Subject</th>
                        <th style="padding: 10px;">Class</th>
                        <th style="padding: 10px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($current_allocations as $row): ?>
                    <tr>
                        <td style="padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;"><?php echo $row['subject_name']; ?></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><?php echo $row['class_name']; ?></td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">
                            <a href="assign_teacher.php?id=<?php echo $teacher_id; ?>&remove=<?php echo $row['allocation_id']; ?>" style="color: red;">Remove</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($current_allocations)): ?>
                        <tr><td colspan="3" style="text-align: center; padding: 20px; color: #aaa;">No subjects assigned yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>
</body>
</html>