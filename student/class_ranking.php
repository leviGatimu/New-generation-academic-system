<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$student_id = $_SESSION['user_id'];

// 1. Get My Class & Role
$stmt = $pdo->prepare("SELECT class_id, class_role FROM students WHERE student_id = ?");
$stmt->execute([$student_id]);
$me = $stmt->fetch();
$my_class_id = $me['class_id'];
$is_president = ($me['class_role'] === 'President');

// 2. Fetch All Classmates
$peers_stmt = $pdo->prepare("SELECT s.student_id, u.full_name, s.admission_number, s.class_role 
                             FROM students s JOIN users u ON s.student_id = u.user_id 
                             WHERE s.class_id = ?");
$peers_stmt->execute([$my_class_id]);
$all_students = $peers_stmt->fetchAll();

// 3. Calculate Averages & Sort
$rankings = [];

foreach ($all_students as $st) {
    $sid = $st['student_id'];
    
    // Manual Marks
    $q1 = $pdo->prepare("SELECT SUM(score) as s, SUM(max_score) as m FROM student_marks m JOIN class_assessments a ON m.assessment_id = a.assessment_id WHERE m.student_id = ?");
    $q1->execute([$sid]);
    $r1 = $q1->fetch();
    
    // Online Marks
    $q2 = $pdo->prepare("SELECT SUM(obtained_marks) as s, SUM(total_marks) as m FROM assessment_submissions s JOIN online_assessments o ON s.assessment_id = o.id WHERE s.student_id = ? AND s.is_marked = 1");
    $q2->execute([$sid]);
    $r2 = $q2->fetch();

    $total_obt = ($r1['s'] ?? 0) + ($r2['s'] ?? 0);
    $total_max = ($r1['m'] ?? 0) + ($r2['m'] ?? 0);
    $avg = ($total_max > 0) ? ($total_obt / $total_max) * 100 : 0;

    $rankings[] = [
        'id' => $sid,
        'name' => $st['full_name'],
        'role' => $st['class_role'],
        'avg' => round($avg, 1)
    ];
}

// Sort by Average Descending
usort($rankings, function($a, $b) {
    return $b['avg'] <=> $a['avg'];
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Class Leaderboard | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; }
        body { background: var(--light-bg); font-family: 'Public Sans', sans-serif; margin: 0; padding-bottom: 50px; }
        
        .container { max-width: 800px; margin: 40px auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 40px; }
        .header h1 { color: var(--dark); margin: 0; font-size: 2rem; }
        
        /* Leaderboard Card */
        .lb-card { background: white; border-radius: 16px; border: 1px solid #dfe3e8; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        
        .lb-row { display: flex; align-items: center; padding: 20px; border-bottom: 1px solid #f0f0f0; transition: 0.2s; position: relative; }
        .lb-row:hover { background: #fafbfc; }
        .lb-row:last-child { border-bottom: none; }
        
        /* Rank Styles */
        .rank-num { width: 40px; height: 40px; border-radius: 50%; background: #f0f0f0; color: #637381; display: flex; align-items: center; justify-content: center; font-weight: 800; margin-right: 20px; flex-shrink: 0; }
        
        /* #1 Rank Special Styling */
        .rank-1 { 
            background: linear-gradient(135deg, #fff 0%, #fffbe6 100%); 
            border-bottom: 2px solid #ffe58f;
            padding: 30px 20px;
        }
        .rank-1 .rank-num { 
            background: #fff1b8; color: #d48806; border: 2px solid #ffe58f; 
            font-size: 1.5rem; width: 60px; height: 60px; 
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.4);
            animation: pulse-gold 2s infinite;
        }
        .rank-1 .st-name { font-size: 1.3rem; color: #d48806; }
        .rank-1 .st-score { font-size: 1.8rem; color: #d48806; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .trophy-icon { font-size: 2rem; color: #ffec3d; margin-right: 15px; filter: drop-shadow(0 2px 4px rgba(212, 136, 6, 0.3)); animation: float 3s ease-in-out infinite; }

        /* Other Ranks */
        .rank-2 .rank-num { background: #f0f0f0; color: #595959; border: 2px solid #d9d9d9; }
        .rank-3 .rank-num { background: #fff1f0; color: #cf1322; border: 2px solid #ffa39e; }

        .st-info { flex: 1; }
        .st-name { font-weight: 700; color: var(--dark); font-size: 1.1rem; }
        .st-role { font-size: 0.7rem; background: #e6f7ff; color: #0050b3; padding: 3px 8px; border-radius: 10px; margin-left: 8px; font-weight: 700; text-transform: uppercase; }
        
        .st-score { font-weight: 800; font-size: 1.2rem; color: var(--primary); }
        
        .btn-view { padding: 8px 15px; background: var(--dark); color: white; border-radius: 8px; text-decoration: none; font-size: 0.8rem; font-weight: 700; margin-left: 15px; }
        .btn-view:hover { background: var(--primary); }

        @keyframes pulse-gold { 0% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(255, 215, 0, 0); } 100% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0); } }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-5px); } 100% { transform: translateY(0px); } }
    </style>
</head>
<body>

<div style="position:fixed; top:20px; left:20px;">
    <a href="dashboard.php" style="text-decoration:none; background:white; padding:10px 15px; border-radius:30px; font-weight:bold; color:#333; box-shadow:0 2px 10px rgba(0,0,0,0.1); display:flex; align-items:center; gap:5px;">
        <i class='bx bx-arrow-back'></i> Dashboard
    </a>
</div>

<div class="container">
    <div class="header">
        <h1>🏆 Class Leaderboard</h1>
        <p style="color:#637381;">Ranking based on overall academic performance.</p>
    </div>

    <div class="lb-card">
        <?php foreach($rankings as $index => $r): 
            $rank = $index + 1;
            $row_class = "rank-" . $rank;
        ?>
        <div class="lb-row <?php echo $row_class; ?>">
            
            <?php if($rank == 1): ?>
                <i class='bx bxs-trophy trophy-icon'></i>
            <?php else: ?>
                <div class="rank-num"><?php echo $rank; ?></div>
            <?php endif; ?>
            
            <div class="st-info">
                <span class="st-name">
                    <?php echo htmlspecialchars($r['name']); ?>
                    <?php if($rank == 1) echo ' 👑'; ?>
                </span>
                
                <?php if($r['role'] !== 'Citizen'): ?>
                    <span class="st-role"><?php echo $r['role']; ?></span>
                <?php endif; ?>
                
                <?php if($r['id'] == $student_id): ?>
                    <span style="font-size:0.7rem; background:#f6ffed; color:#389e0d; padding:2px 6px; border-radius:4px; font-weight:bold; margin-left:5px;">YOU</span>
                <?php endif; ?>
            </div>

            <div class="st-score"><?php echo $r['avg']; ?>%</div>

            <?php if($is_president && $r['id'] != $student_id): ?>
                <a href="results.php?view_student=<?php echo $r['id']; ?>" class="btn-view">View Report</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>