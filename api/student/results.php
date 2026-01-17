<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$current_user_id = $_SESSION['user_id'];
$view_target_id = $_GET['view_student'] ?? $current_user_id;

// 1. Check Permissions (Is User President?)
$role_stmt = $pdo->prepare("SELECT class_role, full_name FROM students s JOIN users u ON s.student_id = u.user_id WHERE s.student_id = ?");
$role_stmt->execute([$current_user_id]);
$me = $role_stmt->fetch();

// Logic: If trying to view someone else, MUST be President
if ($view_target_id != $current_user_id && $me['class_role'] !== 'President') {
    die("Access Denied: Only the Class President can view other reports.");
}

// 2. Fetch Target Student Name (For display)
if ($view_target_id != $current_user_id) {
    $target_stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ?");
    $target_stmt->execute([$view_target_id]);
    $target_name = $target_stmt->fetchColumn();
    $page_title = $target_name . "'s Results";
    $show_back_btn = true;
} else {
    $target_name = "My Results";
    $show_back_btn = false;
}

// 3. Fetch Terms
$terms = $pdo->query("SELECT * FROM academic_terms ORDER BY term_id ASC")->fetchAll();
$selected_term = $_GET['term_id'] ?? 1;

// 4. Initialize Data Container
$grouped_results = [];

// --- QUERY A: MANUAL MARKS ---
$sql_manual = "
    SELECT 
        s.subject_name, 
        sm.score, 
        ca.max_score, 
        gc.name as type, 
        ca.created_at as date
    FROM student_marks sm
    JOIN class_assessments ca ON sm.assessment_id = ca.assessment_id
    JOIN subjects s ON ca.subject_id = s.subject_id
    JOIN grading_categories gc ON ca.category_id = gc.id
    WHERE sm.student_id = ? AND ca.term_id = ?
";
$stmt = $pdo->prepare($sql_manual);
$stmt->execute([$view_target_id, $selected_term]); // Use Target ID
$manual_data = $stmt->fetchAll();

foreach ($manual_data as $row) {
    $grouped_results[$row['subject_name']][] = [
        'title' => $row['type'],
        'score' => $row['score'],
        'max'   => $row['max_score'],
        'date'  => $row['date'],
        'source'=> 'Manual'
    ];
}

// --- QUERY B: ONLINE MARKS ---
$sql_online = "
    SELECT 
        s.subject_name, 
        sub.obtained_marks as score, 
        oa.total_marks as max_score, 
        oa.title as type, 
        sub.submitted_at as date
    FROM assessment_submissions sub
    JOIN online_assessments oa ON sub.assessment_id = oa.id
    JOIN subjects s ON oa.subject_id = s.subject_id
    WHERE sub.student_id = ? AND sub.is_marked = 1
";
$stmt = $pdo->prepare($sql_online);
$stmt->execute([$view_target_id]); // Use Target ID
$online_data = $stmt->fetchAll();

foreach ($online_data as $row) {
    $grouped_results[$row['subject_name']][] = [
        'title' => $row['type'] . ' (Online)',
        'score' => $row['score'],
        'max'   => $row['max_score'],
        'date'  => $row['date'],
        'source'=> 'Online'
    ];
}

ksort($grouped_results);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo htmlspecialchars($target_name); ?> | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* ... [Keep existing styles] ... */
        :root { 
            --primary: #FF6600; 
            --dark: #212b36; 
            --light-bg: #f4f6f8; 
            --white: #ffffff; 
            --text-main: #212b36;
            --text-muted: #637381;
            --border: #e0e6ed;
            --nav-height: 75px; 
        }
        body { background-color: var(--light-bg); margin: 0; font-family: 'Public Sans', sans-serif; color: var(--text-main); }

        /* Navbar */
        .top-navbar { position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height); background: var(--white); z-index: 1000; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-bottom: 1px solid var(--border); box-sizing: border-box; }
        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; background: #fafbfc; border-radius: 8px; border: 1px solid #dfe3e8; }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }
        .nav-menu { display: flex; gap: 5px; align-items: center; }
        .nav-item { text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem; padding: 10px 15px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }
        .btn-logout { text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s; }
        .btn-logout:hover { background: #ff4d4f; color: white; }

        /* Header */
        .page-header { margin-top: var(--nav-height); background: white; padding: 30px 10%; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .page-title h1 { margin: 0; font-size: 1.8rem; color: var(--dark); }
        .page-title p { margin: 5px 0 0; color: var(--text-muted); }
        .term-selector { padding: 10px 15px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.95rem; color: var(--dark); outline: none; cursor: pointer; background: #f9fafb; font-weight: 600; }

        /* Content */
        .content-area { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        .subject-block { margin-bottom: 40px; }
        .subject-header { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; border-bottom: 2px solid var(--primary); padding-bottom: 10px; width: fit-content; }
        .subject-header h2 { margin: 0; font-size: 1.4rem; color: var(--dark); text-transform: uppercase; letter-spacing: 0.5px; }
        .subject-icon { color: var(--primary); font-size: 1.5rem; }

        /* Table */
        .marks-table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid var(--border); }
        .marks-table th { background: #f4f6f8; color: #637381; text-align: left; padding: 12px 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        .marks-table td { padding: 15px 20px; border-bottom: 1px solid #f4f6f8; color: var(--text-main); font-size: 0.95rem; vertical-align: middle; }
        .marks-table tr:last-child td { border-bottom: none; }
        .marks-table tr:hover { background: #fafbfc; }

        /* Pills */
        .score-pill { display: inline-block; padding: 5px 12px; border-radius: 20px; font-weight: 800; font-size: 0.9rem; min-width: 60px; text-align: center; }
        .pass { background: #e6f7ed; color: #00ab55; }
        .avg { background: #fff7e6; color: #ffc107; }
        .fail { background: #fff1f0; color: #ff4d4f; }
        .max-score { color: #919eab; font-size: 0.8rem; margin-left: 5px; font-weight: 600; }
        .empty-state { text-align: center; padding: 60px; color: #919eab; background: white; border-radius: 12px; border: 1px dashed var(--border); }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div style="width:40px;"><img src="../assets/images/logo.png" alt="" style="width:100%;"></div>
        Student Portal
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item"><i class='bx bxs-dashboard'></i> Dashboard</a>
        <a href="academics.php" class="nav-item"><i class='bx bxs-graduation'></i> Academics</a>
        <a href="results.php" class="nav-item active"><i class='bx bxs-bar-chart-alt-2'></i> My Results</a>
        <a href="messages.php" class="nav-item"><i class='bx bxs-chat'></i> Messages</a>
        <a href="attendance.php" class="nav-item"><i class='bx bxs-calendar-check'></i> <span>Attendance</span></a>
         <a href="class_ranking.php" class="nav-item">
            <i class='bx bxs-chat'></i> <span>Ranking</span>
        </a>
        <a href="profile.php" class="nav-item">
    <i class='bx bxs-user-circle'></i> <span>Profile</span>
</a>
    </div>
    <a href="../logout.php" class="btn-logout">Logout</a>
</nav>

<div class="page-header">
    <div class="page-title">
        <?php if($show_back_btn): ?>
            <a href="class_ranking.php" style="text-decoration:none; color:var(--primary); font-weight:bold; font-size:0.9rem; display:block; margin-bottom:5px;">&larr; Back to Leaderboard</a>
        <?php endif; ?>
        
        <h1><?php echo htmlspecialchars($target_name); ?></h1>
        <p>Official Academic Records</p>
    </div>
    
    <form method="GET">
        <?php if($show_back_btn): ?>
            <input type="hidden" name="view_student" value="<?php echo $view_target_id; ?>">
        <?php endif; ?>
        <select name="term_id" class="term-selector" onchange="this.form.submit()">
            <?php foreach($terms as $t): ?>
                <option value="<?php echo $t['term_id']; ?>" <?php if($t['term_id'] == $selected_term) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($t['term_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<div class="content-area">
    <?php if (empty($grouped_results)): ?>
        <div class="empty-state">
            <i class='bx bx-folder-open' style="font-size: 3rem; margin-bottom: 10px;"></i>
            <h3>No results found</h3>
            <p>Marks for this term have not been published yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped_results as $subject => $marks): ?>
            <div class="subject-block">
                <div class="subject-header">
                    <i class='bx bxs-book-bookmark subject-icon'></i>
                    <h2><?php echo htmlspecialchars($subject); ?></h2>
                </div>
                <table class="marks-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Assessment</th>
                            <th style="width: 25%;">Date</th>
                            <th style="width: 25%; text-align: right;">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($marks as $mark): 
                            $pct = ($mark['max'] > 0) ? ($mark['score'] / $mark['max']) * 100 : 0;
                            $grade_class = ($pct >= 70) ? 'pass' : (($pct >= 50) ? 'avg' : 'fail');
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($mark['title']); ?></strong></td>
                            <td style="color: #637381;"><?php echo date("d M, Y", strtotime($mark['date'])); ?></td>
                            <td style="text-align: right;">
                                <span class="score-pill <?php echo $grade_class; ?>"><?php echo $mark['score']; ?></span>
                                <span class="max-score">/ <?php echo $mark['max']; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>