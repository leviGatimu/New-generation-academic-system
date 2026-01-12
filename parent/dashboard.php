<?php
session_start();
require '../config/db.php';

if ($_SESSION['role'] !== 'parent') { header("Location: ../index.php"); exit; }

$parent_id = $_SESSION['user_id'];

// 1. Find the linked child (Erica)
$stmt = $pdo->prepare("SELECT s.*, u.full_name, c.class_name 
                       FROM parent_student_link psl 
                       JOIN students s ON psl.student_id = s.student_id 
                       JOIN users u ON s.student_id = u.user_id 
                       JOIN classes c ON s.class_id = c.class_id 
                       WHERE psl.parent_id = ?");
$stmt->execute([$parent_id]);
$child = $stmt->fetch();

// 2. Fetch Performance for Graph
$marks_stmt = $pdo->prepare("SELECT sub.subject_name, AVG(m.marks) as avg_score 
                             FROM exam_marks m 
                             JOIN subjects sub ON m.subject_id = sub.subject_id 
                             WHERE m.student_id = ? GROUP BY m.subject_id");
$marks_stmt->execute([$child['student_id']]);
$stats = $marks_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Parent Portal | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="main-content">
        <div class="welcome-card">
            <h1>Monitoring: <?php echo $child['full_name']; ?></h1>
            <p>Class: <?php echo $child['class_name']; ?></p>
        </div>

        <div class="grid">
            <div class="white-card">
                <h3>Subject Analysis</h3>
                <canvas id="performanceChart"></canvas>
            </div>

            <div class="white-card">
                <h3>Contact Teacher</h3>
                <a href="messages.php" class="btn-link"><i class='bx bx-message-detail'></i> Open Chat</a>
                <a href="report_card.php?id=<?php echo $child['student_id']; ?>" class="btn-link"><i class='bx bx-download'></i> Download Report Card</a>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('performanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($stats, 'subject_name')); ?>,
                datasets: [{
                    label: 'Average Score (%)',
                    data: <?php echo json_encode(array_column($stats, 'avg_score')); ?>,
                    backgroundColor: '#FF6600'
                }]
            }
        });
    </script>
</body>
</html>