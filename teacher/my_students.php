<?php
// teacher/my_students.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php"); exit;
}

$teacher_id = $_SESSION['user_id'];
$view_student_id = $_GET['student_id'] ?? null;
$selected_subject_id = $_GET['subject_id'] ?? null;

// --- DATA FETCHING ---
$sql = "SELECT DISTINCT c.class_id, c.class_name, s.subject_id, s.subject_name, cat.color_code
        FROM teacher_allocations ta
        JOIN classes c ON ta.class_id = c.class_id 
        JOIN subjects s ON ta.subject_id = s.subject_id
        LEFT JOIN class_categories cat ON c.category_id = cat.category_id
        WHERE ta.teacher_id = ? ORDER BY c.class_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$teacher_id]);
$allocations = $stmt->fetchAll();

$classes_data = [];
foreach ($allocations as $row) {
    $classes_data[$row['class_id']]['name'] = $row['class_name'];
    $classes_data[$row['class_id']]['subjects'][] = ['id' => $row['subject_id'], 'name' => $row['subject_name']];
}

$student_data = null;
$subject_performance = [];
$chart_labels = [];
$chart_scores = [];

if ($view_student_id && $selected_subject_id) {
    $stmt = $pdo->prepare("SELECT u.full_name, u.email, st.admission_number, c.class_name 
                           FROM users u JOIN students st ON u.user_id = st.student_id 
                           JOIN classes c ON st.class_id = c.class_id WHERE u.user_id = ?");
    $stmt->execute([$view_student_id]);
    $student_data = $stmt->fetch();

    if ($student_data) {
        $m_sql = "SELECT sm.score, ca.max_score, gc.name as assessment_type, ca.created_at
                  FROM student_marks sm
                  JOIN class_assessments ca ON sm.assessment_id = ca.assessment_id
                  JOIN grading_categories gc ON ca.category_id = gc.id
                  WHERE sm.student_id = ? AND ca.subject_id = ?
                  ORDER BY ca.created_at ASC";
        $stmt = $pdo->prepare($m_sql);
        $stmt->execute([$view_student_id, $selected_subject_id]);
        $subject_performance = $stmt->fetchAll();
        
        foreach($subject_performance as $p) {
            $chart_labels[] = date("M d", strtotime($p['created_at']));
            $chart_scores[] = round(($p['score'] / $p['max_score']) * 100, 1);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Insights | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* === THEME VARIABLES === */
        :root { 
            --primary: #FF6600; 
            --primary-hover: #e65c00;
            --dark: #212b36; 
            --light-bg: #f4f6f8; 
            --white: #ffffff; 
            --border: #dfe3e8; 
            --nav-height: 75px;
        }
        
        html, body { 
            background-color: var(--light-bg); 
            margin: 0; padding: 0; 
            font-family: 'Public Sans', sans-serif;
            overflow-y: auto;
        }

        /* === TOP NAVIGATION BAR (Dashboard Match) === */
        .top-navbar {
            position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height);
            background: var(--white); z-index: 1000;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-bottom: 1px solid var(--border);
            box-sizing: border-box;
        }

        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }

        .nav-menu { display: flex; gap: 5px; align-items: center; }
        .nav-item {
            text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem;
            padding: 10px 15px; border-radius: 8px; transition: 0.2s;
            display: flex; align-items: center; gap: 6px;
        }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }

        .btn-logout {
            text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.85rem;
            padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s;
        }
        .btn-logout:hover { background: #ff4d4f; color: white; }

        /* === CONTENT LAYOUT === */
        .main-content { margin-top: var(--nav-height); padding: 40px 5%; }
        .container { max-width: 1300px; margin: 0 auto; }

        /* Structure Analytics */
        .top-stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px; }
        .main-dashboard-grid { display: grid; grid-template-columns: 320px 1fr; gap: 25px; }
        .white-card { background: white; border-radius: 20px; border: 1px solid var(--border); padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }

        .summary-box { display: flex; justify-content: space-between; align-items: center; }
        .stat-label { font-size: 0.8rem; color: #637381; font-weight: 700; text-transform: uppercase; }
        .stat-val { font-size: 1.8rem; font-weight: 800; color: var(--dark); display: block; }
        .stat-icon { font-size: 2.5rem; color: var(--primary); opacity: 0.15; }

        /* List Styling */
        .folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .folder-card { background: white; padding: 30px; border-radius: 16px; border: 1px solid var(--border); text-align: center; cursor: pointer; transition: 0.3s; }
        .folder-card:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .folder-icon { font-size: 3.5rem; color: #ffd1b3; margin-bottom: 10px; }

        .styled-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .styled-table th { text-align: left; padding: 12px; color: #637381; font-size: 0.75rem; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        .styled-table td { padding: 12px; border-bottom: 1px solid #f4f6f8; font-size: 0.9rem; }
        
        .btn-message { width: 100%; background: var(--dark); color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; }
    </style>
</head>
<body>


<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box">
            <img src="../assets/images/logo.png" alt="NGA">
        </div>
        <span class="nav-brand-text">Teacher Portal</span>
    </a>

    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item">
            <i class='bx bxs-dashboard'></i> <span>Overview</span>
        </a>
        <a href="my_students.php" class="nav-item active">
            <i class='bx bxs-user-detail'></i> <span>My Students</span>
        </a>
       <a href="view_all_marks.php" class="nav-item">
            <i class='bx bxs-edit'></i> <span>View Marks</span>
        </a>
    </div>

    <div class="nav-user">
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="main-content">
    <div class="container">

        <?php if ($student_data): ?>
            <a href="my_students.php" style="text-decoration:none; color:#637381; font-weight:600; display:flex; align-items:center; gap:5px; margin-bottom:20px;">
                <i class='bx bx-arrow-back'></i> Back to List
            </a>

            <div class="top-stats-row">
                <div class="white-card summary-box">
                    <div><span class="stat-label">Current Average</span><span class="stat-val">84.2%</span></div>
                    <i class='bx bx-trending-up stat-icon'></i>
                </div>
                <div class="white-card summary-box">
                    <div><span class="stat-label">Assessments</span><span class="stat-val"><?php echo count($subject_performance); ?></span></div>
                    <i class='bx bx-check-double stat-icon'></i>
                </div>
                <div class="white-card summary-box">
                    <div><span class="stat-label">Class Rank</span><span class="stat-val">#4 / 35</span></div>
                    <i class='bx bx-medal stat-icon'></i>
                </div>
            </div>

            <div class="main-dashboard-grid">
                <div class="white-card">
                    <div style="text-align: center; border-bottom: 1px solid #f4f6f8; padding-bottom: 20px; margin-bottom: 20px;">
                        <div style="width: 90px; height: 90px; background: #fff0e6; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; font-weight: 800; margin: 0 auto 15px;">
                            <?php echo substr($student_data['full_name'], 0, 1); ?>
                        </div>
                        <h2 style="margin:0; font-size:1.3rem;"><?php echo htmlspecialchars($student_data['full_name']); ?></h2>
                        <p style="color:#637381; font-size:0.9rem;"><?php echo $student_data['admission_number']; ?></p>
                    </div>
                    <p style="font-size:0.9rem; color:#637381; margin-bottom: 20px;"><strong>Class:</strong> <?php echo $student_data['class_name']; ?></p>
                    <button class="btn-message"><i class='bx bxs-chat'></i> Send Message</button>
                </div>

                <div style="display: flex; flex-direction: column; gap: 25px;">
                    <div class="white-card">
                        <h3 style="margin-top:0; font-size:1.1rem;"><i class='bx bx-bar-chart-alt-2' style="color:var(--primary);"></i> Score Velocity</h3>
                        <div style="height: 350px;"><canvas id="performanceChart"></canvas></div>
                    </div>
                </div>
            </div>

        <?php elseif (isset($_GET['view_list'])): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h1 style="margin:0; font-size:1.5rem;"><?php echo $classes_data[$_GET['class_id']]['name']; ?> Students</h1>
                <a href="my_students.php" style="color:var(--primary); text-decoration:none; font-weight:700;">Back to Classes</a>
            </div>
            <div class="white-card">
                <table class="styled-table">
                    <thead><tr><th>Student Name</th><th>Adm No</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php 
                        $stmt = $pdo->prepare("SELECT u.user_id, u.full_name, st.admission_number FROM users u JOIN students st ON u.user_id = st.student_id WHERE st.class_id = ? ORDER BY u.full_name ASC");
                        $stmt->execute([$_GET['class_id']]);
                        foreach($stmt->fetchAll() as $st): ?>
                        <tr>
                            <td style="font-weight:700;"><?php echo htmlspecialchars($st['full_name']); ?></td>
                            <td><?php echo $st['admission_number']; ?></td>
                            <td><a href="?student_id=<?php echo $st['user_id']; ?>&subject_id=<?php echo $_GET['subject_id']; ?>" style="color:var(--primary); text-decoration:none; font-weight:800;">OPEN FILE</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <h1 style="margin-bottom:10px; font-size:1.5rem;">Managed Students</h1>
            <p style="color:#637381; margin-bottom:40px;">Select a class to analyze student performance metrics.</p>
            <div class="folder-grid">
                <?php foreach($classes_data as $class_id => $data): ?>
                    <?php foreach($data['subjects'] as $sub): ?>
                    <div class="folder-card" onclick="window.location.href='?class_id=<?php echo $class_id; ?>&subject_id=<?php echo $sub['id']; ?>&view_list=1'">
                        <i class='bx bxs-folder-open folder-icon'></i>
                        <h3 style="margin:0; font-size:1.1rem;"><?php echo $sub['name']; ?></h3>
                        <div style="color:#637381; font-weight:600; margin-top:5px;"><?php echo $data['name']; ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
<?php if ($student_data): ?>
const ctx = document.getElementById('performanceChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Score %',
            data: <?php echo json_encode($chart_scores); ?>,
            backgroundColor: '#FF6600',
            borderRadius: 8,
            barThickness: 30
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, max: 100, grid: { color: '#f0f0f0' } },
            x: { grid: { display: false } }
        }
    }
});
<?php endif; ?>
</script>
</body>
</html>