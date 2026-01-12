<?php
session_start();
require '../config/db.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../index.php"); exit;
}

$parent_id = $_SESSION['user_id'];

// 1. Get the Linked Child's Info
$stmt = $pdo->prepare("SELECT s.student_id, u.full_name, u.email, c.class_name, s.admission_number 
                       FROM parent_student_link psl
                       JOIN students s ON psl.student_id = s.student_id
                       JOIN users u ON s.student_id = u.user_id
                       JOIN classes c ON s.class_id = c.class_id
                       WHERE psl.parent_id = ?");
$stmt->execute([$parent_id]);
$child = $stmt->fetch();

// 2. If a child is found, get their marks (FIXED QUERY)
$marks = [];
if ($child) {
    // We join 'class_assessments' (ca) to find the 'subject_id'
    $m_stmt = $pdo->prepare("SELECT sub.subject_name, mk.score, ca.max_score, 
                             (mk.score/ca.max_score)*100 as percentage 
                             FROM student_marks mk
                             JOIN class_assessments ca ON mk.assessment_id = ca.assessment_id
                             JOIN subjects sub ON ca.subject_id = sub.subject_id 
                             WHERE mk.student_id = ?");
    $m_stmt->execute([$child['student_id']]);
    $marks = $m_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Parent Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>

<div style="background:white; padding:15px 40px; border-bottom:1px solid #dfe3e8; display:flex; justify-content:space-between; align-items:center;">
    <h3 style="margin:0; color:#FF6600;">Parent Portal</h3>
    <div>
        <span>Welcome, <?php echo $_SESSION['name']; ?></span>
        <a href="../logout.php" style="margin-left:20px; color:red; text-decoration:none;">Logout</a>
    </div>
</div>

<div class="main-content" style="padding:40px;">
    
    <?php if ($child): ?>
        <div class="white-card" style="background:white; padding:30px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; border:1px solid #dfe3e8; margin-bottom:30px;">
            <div>
                <h2 style="margin:0; color:#212b36;"><?php echo $child['full_name']; ?></h2>
                <p style="color:#637381; margin:5px 0;">Class: <?php echo $child['class_name']; ?> | Adm No: <?php echo $child['admission_number']; ?></p>
            </div>
            <div style="text-align:right;">
                <span style="display:block; font-size:0.8rem; color:#919eab; font-weight:bold;">STUDENT STATUS</span>
                <span style="color:#00ab55; font-weight:bold;">● Active</span>
            </div>
        </div>

        <div class="white-card" style="background:white; padding:30px; border-radius:12px; border:1px solid #dfe3e8;">
            <h3 style="margin-top:0;">Academic Performance</h3>
            <table style="width:100%; border-collapse:collapse; margin-top:20px;">
                <thead>
                    <tr style="background:#f4f6f8; text-align:left;">
                        <th style="padding:12px; border-bottom:2px solid #dfe3e8;">Subject</th>
                        <th style="padding:12px; border-bottom:2px solid #dfe3e8;">Score</th>
                        <th style="padding:12px; border-bottom:2px solid #dfe3e8;">Percentage</th>
                        <th style="padding:12px; border-bottom:2px solid #dfe3e8;">Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($marks) > 0): ?>
                        <?php foreach($marks as $m): ?>
                        <tr>
                            <td style="padding:12px; border-bottom:1px solid #eee;"><?php echo $m['subject_name']; ?></td>
                            <td style="padding:12px; border-bottom:1px solid #eee;"><?php echo $m['score'] . ' / ' . $m['max_score']; ?></td>
                            <td style="padding:12px; border-bottom:1px solid #eee; font-weight:bold; color:#FF6600;"><?php echo round($m['percentage']); ?>%</td>
                            <td style="padding:12px; border-bottom:1px solid #eee;">
                                <?php 
                                    $p = $m['percentage'];
                                    if($p >= 80) echo '<span style="color:green; font-weight:bold;">A</span>';
                                    elseif($p >= 70) echo '<span style="color:blue; font-weight:bold;">B</span>';
                                    elseif($p >= 50) echo '<span style="color:orange; font-weight:bold;">C</span>';
                                    else echo '<span style="color:red; font-weight:bold;">F</span>';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="padding:20px; text-align:center; color: #777;">No marks recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <div style="text-align:center; padding:50px;">
            <h3>No Student Linked</h3>
            <p>Please register using a valid Parent Code provided by your child.</p>
        </div>
    <?php endif; ?>

</div>

</body>
</html>