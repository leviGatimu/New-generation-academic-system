<?php
// student/results.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$student_id = $_SESSION['user_id'];
$selected_term = $_GET['term_id'] ?? 1;

// 1. Fetch Terms
$terms = $pdo->query("SELECT * FROM academic_terms ORDER BY term_id ASC")->fetchAll();

// 2. Initialize Results Array
$results = [];
$total_percentage = 0;
$count_assessments = 0;

// --- QUERY A: Fetch Standard Manual Marks (student_marks) ---
$sql_manual = "SELECT s.subject_name, sm.score, ca.max_score, gc.name as assessment_type, ca.created_at
               FROM student_marks sm
               JOIN class_assessments ca ON sm.assessment_id = ca.assessment_id
               JOIN subjects s ON ca.subject_id = s.subject_id
               JOIN grading_categories gc ON ca.category_id = gc.id
               WHERE sm.student_id = ? AND ca.term_id = ?
               ORDER BY s.subject_name ASC";
$stmt = $pdo->prepare($sql_manual);
$stmt->execute([$student_id, $selected_term]);
$manual_marks = $stmt->fetchAll();

foreach ($manual_marks as $m) {
    $results[$m['subject_name']][] = [
        'score' => $m['score'],
        'max_score' => $m['max_score'],
        'type' => $m['assessment_type'], // e.g., "Exam", "Assignment"
        'date' => $m['created_at'],
        'is_online' => false
    ];
}

// --- QUERY B: Fetch Online Synced Marks (assessment_submissions) ---
// Note: Online assessments in your DB don't have a 'term_id', so we fetch all marked ones.
$sql_online = "SELECT s.subject_name, sub.obtained_marks as score, oa.total_marks as max_score, 
                      oa.type, sub.submitted_at
               FROM assessment_submissions sub
               JOIN online_assessments oa ON sub.assessment_id = oa.id
               JOIN subjects s ON oa.subject_id = s.subject_id
               WHERE sub.student_id = ? AND sub.is_marked = 1";
$stmt = $pdo->prepare($sql_online);
$stmt->execute([$student_id]);
$online_marks = $stmt->fetchAll();

foreach ($online_marks as $om) {
    $results[$om['subject_name']][] = [
        'score' => $om['score'],
        'max_score' => $om['max_score'],
        'type' => ucfirst($om['type']) . ' (Sync)', // e.g., "Quiz (Sync)"
        'date' => $om['submitted_at'],
        'is_online' => true
    ];
}

// 3. Calculate Stats from the Merged Data
foreach ($results as $subject => $marks) {
    foreach ($marks as $m) {
        if ($m['max_score'] > 0) {
            $pct = ($m['score'] / $m['max_score']) * 100;
            $total_percentage += $pct;
            $count_assessments++;
        }
    }
}

// Term Average
$term_average = $count_assessments > 0 ? round($total_percentage / $count_assessments, 1) : 0;

// Grade Color Logic
$avg_color = '#00ab55'; // Green
if($term_average < 70) $avg_color = '#ffc107'; // Orange
if($term_average < 50) $avg_color = '#ff4d4f'; // Red
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Academic Performance | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* === STANDARD VARIABLES === */
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; --border: #dfe3e8; --nav-height: 75px; }
        body { background-color: var(--light-bg); margin: 0; font-family: 'Public Sans', sans-serif; }

        /* === HEADER === */
        .top-navbar { position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height); background: var(--white); z-index: 1000; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-bottom: 1px solid var(--border); box-sizing: border-box; }
        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; background: #fafbfc; border-radius: 8px; border: 1px solid var(--border); }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }

        .nav-menu { display: flex; gap: 5px; align-items: center; }
        .nav-item { text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem; padding: 10px 15px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }
        .btn-logout { text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s; }
        .btn-logout:hover { background: #ff4d4f; color: white; }

        /* === HERO === */
        .hero-section { margin-top: var(--nav-height); background: linear-gradient(135deg, #212b36 0%, #161c24 100%); color: white; padding: 50px 5% 90px; display: flex; justify-content: space-between; align-items: center; }
        .hero-text h1 { margin: 0 0 10px 0; font-size: 2.2rem; }
        .hero-text p { color: rgba(255,255,255,0.7); margin: 0; }
        
        .term-select { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-size: 1rem; outline: none; margin-top: 15px; }
        .term-select option { background: var(--dark); color: white; }

        .stats-circle { width: 120px; height: 120px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.05); box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .circle-inner { width: 85%; height: 85%; background: #212b36; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .avg-num { font-size: 2rem; font-weight: 800; line-height: 1; }
        .avg-lbl { font-size: 0.75rem; text-transform: uppercase; color: rgba(255,255,255,0.5); margin-top: 5px; }

        /* === RESULTS GRID === */
        .results-container { max-width: 1200px; margin: -50px auto 0; padding: 0 20px 60px; display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 25px; }

        /* CARD */
        .subject-card { background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.05); animation: fadeUp 0.5s ease forwards; opacity: 0; transform: translateY(20px); }
        @keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }

        .card-header { padding: 20px 25px; border-bottom: 1px solid #f4f6f8; display: flex; justify-content: space-between; align-items: center; background: white; }
        .sub-title { font-size: 1.1rem; font-weight: 800; color: var(--dark); display: flex; align-items: center; gap: 8px; }
        .sub-avg { background: #f4f6f8; padding: 6px 12px; border-radius: 20px; font-weight: 700; font-size: 0.85rem; }

        .marks-list { padding: 5px 0; }
        .mark-row { padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #f4f6f8; transition: 0.2s; }
        .mark-row:last-child { border-bottom: none; }
        .mark-row:hover { background: #fafbfc; }

        .mark-info h4 { margin: 0; font-size: 0.95rem; color: #454f5b; display: flex; align-items: center; gap: 5px; }
        .mark-info span { font-size: 0.75rem; color: #919eab; text-transform: uppercase; font-weight: 700; }
        
        .mark-score { text-align: right; }
        .score-val { font-weight: 800; color: var(--dark); font-size: 1rem; }
        .max-val { font-size: 0.8rem; color: #919eab; }

        .progress-track { width: 100%; height: 6px; background: #f0f0f0; border-radius: 3px; margin-top: 8px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 3px; }

        .empty-state { grid-column: 1 / -1; background: white; padding: 80px; text-align: center; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        
        /* New Badge for Online Marks */
        .sync-badge { font-size: 0.65rem; background: #e3f2fd; color: #1565c0; padding: 2px 6px; border-radius: 4px; font-weight: 700; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box"><img src="../assets/images/logo.png" alt="NGA"></div>
        <span class="nav-brand-text">Student Portal</span>
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item"><i class='bx bxs-dashboard'></i> <span>Dashboard</span></a>
        <a href="academics.php" class="nav-item"><i class='bx bxs-graduation'></i> <span>Academics</span></a>
        <a href="results.php" class="nav-item active"><i class='bx bxs-spreadsheet'></i> <span>My Results</span></a>
        <a href="attendance.php" class="nav-item"><i class='bx bxs-calendar-check'></i> <span>Attendance</span></a>
    </div>
    <a href="../logout.php" class="btn-logout">Logout</a>
</nav>

<div class="hero-section">
    <div class="hero-text">
        <h1>Performance Report</h1>
        <p>Track your grades and academic progress.</p>
        
        <form method="GET">
            <select name="term_id" class="term-select" onchange="this.form.submit()">
                <?php foreach($terms as $t): ?>
                    <option value="<?php echo $t['term_id']; ?>" <?php if($t['term_id'] == $selected_term) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($t['term_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="stats-circle" style="background: conic-gradient(<?php echo $avg_color; ?> <?php echo $term_average; ?>%, rgba(255,255,255,0.1) 0);">
        <div class="circle-inner">
            <div class="avg-num" style="color: <?php echo $avg_color; ?>"><?php echo $term_average; ?>%</div>
            <div class="avg-lbl">Average</div>
        </div>
    </div>
</div>

<div class="results-container">
    <?php if (empty($results)): ?>
        <div class="empty-state">
            <i class='bx bx-bar-chart-alt-2' style="font-size: 4rem; color: #dfe3e8; margin-bottom: 20px;"></i>
            <h2 style="margin:0; color:var(--dark);">No Marks Yet</h2>
            <p style="color:#919eab;">Marks for this term haven't been published.</p>
        </div>
    <?php else: 
        $delay = 0;
        foreach ($results as $subject => $marks): 
            $delay += 0.1;
            
            // Calculate Subject Average
            $sub_total = 0; $sub_max = 0;
            foreach($marks as $m) { $sub_total += $m['score']; $sub_max += $m['max_score']; }
            $sub_avg = ($sub_max > 0) ? round(($sub_total / $sub_max) * 100) : 0;
            
            $bar_color = ($sub_avg >= 70) ? '#00ab55' : (($sub_avg >= 50) ? '#ffc107' : '#ff4d4f');
    ?>
    <div class="subject-card" style="animation-delay: <?php echo $delay; ?>s;">
        <div class="card-header">
            <div class="sub-title">
                <i class='bx bxs-book-bookmark' style="color:var(--primary);"></i>
                <?php echo htmlspecialchars($subject); ?>
            </div>
            <div class="sub-avg" style="color:<?php echo $bar_color; ?>">
                <?php echo $sub_avg; ?>% Avg
            </div>
        </div>

        <div class="marks-list">
            <?php foreach ($marks as $row): 
                $pct = ($row['max_score'] > 0) ? ($row['score'] / $row['max_score']) * 100 : 0;
            ?>
            <div class="mark-row">
                <div class="mark-info">
                    <h4>
                        <?php echo htmlspecialchars($row['type']); ?>
                        <?php if($row['is_online']): ?><i class='bx bx-cloud-download' style="color:#007bff; font-size:0.8rem;" title="Synced from Online Exam"></i><?php endif; ?>
                    </h4>
                    <span><?php echo date("d M", strtotime($row['date'])); ?></span>
                </div>
                <div class="mark-score" style="width: 40%;">
                    <div style="display:flex; justify-content:flex-end; align-items:baseline; gap:3px;">
                        <span class="score-val"><?php echo $row['score']; ?></span>
                        <span class="max-val">/ <?php echo $row['max_score']; ?></span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $bar_color; ?>;"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

</body>
</html>