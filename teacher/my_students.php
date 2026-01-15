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
$message = "";

// --- 1. HANDLE ROLE ASSIGNMENT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_role'])) {
    $target_student_id = $_POST['student_id'];
    $new_role = $_POST['class_role'];
    
    $stmt = $pdo->prepare("UPDATE students SET class_role = ? WHERE student_id = ?");
    if ($stmt->execute([$new_role, $target_student_id])) {
        $redirect_url = "?class_id=" . $_POST['redirect_class'] . "&subject_id=" . $_POST['redirect_subject'] . "&view_list=1";
        header("Location: " . $redirect_url); exit;
    }
}

// --- 2. DATA FETCHING (Classes) ---
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
$real_average = 0;
$class_rank = "-";
$total_students = 0;

// --- 3. SINGLE STUDENT VIEW LOGIC ---
if ($view_student_id && $selected_subject_id) {
    // A. Fetch Student Details
    $stmt = $pdo->prepare("SELECT u.full_name, u.email, st.admission_number, c.class_name, c.class_id, st.class_role 
                           FROM users u JOIN students st ON u.user_id = st.student_id 
                           JOIN classes c ON st.class_id = c.class_id WHERE u.user_id = ?");
    $stmt->execute([$view_student_id]);
    $student_data = $stmt->fetch();

    if ($student_data) {
        $class_id_target = $student_data['class_id'];

        // B. Fetch This Student's Marks (Manual + Online)
        // Manual Marks
        $m_sql = "SELECT sm.score, ca.max_score, gc.name as type, ca.created_at
                  FROM student_marks sm
                  JOIN class_assessments ca ON sm.assessment_id = ca.assessment_id
                  LEFT JOIN grading_categories gc ON ca.category_id = gc.id
                  WHERE sm.student_id = ? AND ca.subject_id = ?";
        $stmt = $pdo->prepare($m_sql);
        $stmt->execute([$view_student_id, $selected_subject_id]);
        $manual_marks = $stmt->fetchAll();

        // Online Marks
        $o_sql = "SELECT sub.obtained_marks as score, oa.total_marks as max_score, oa.type, sub.submitted_at as created_at
                  FROM assessment_submissions sub
                  JOIN online_assessments oa ON sub.assessment_id = oa.id
                  WHERE sub.student_id = ? AND oa.subject_id = ? AND sub.is_marked = 1";
        $stmt = $pdo->prepare($o_sql);
        $stmt->execute([$view_student_id, $selected_subject_id]);
        $online_marks = $stmt->fetchAll();

        // Combine & Sort for Chart
        $subject_performance = array_merge($manual_marks, $online_marks);
        usort($subject_performance, function($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });

        // C. Calculate Real Average for THIS Student
        $total_obt = 0;
        $total_max = 0;
        foreach($subject_performance as $p) {
            $total_obt += $p['score'];
            $total_max += $p['max_score'];
            // Prepare Chart Data
            $chart_labels[] = date("M d", strtotime($p['created_at']));
            $chart_scores[] = ($p['max_score'] > 0) ? round(($p['score'] / $p['max_score']) * 100, 1) : 0;
        }
        $real_average = ($total_max > 0) ? round(($total_obt / $total_max) * 100, 1) : 0;

        // D. Calculate Class Rank (The hard part!)
        // 1. Get all students in this class
        $peers_stmt = $pdo->prepare("SELECT student_id FROM students WHERE class_id = ?");
        $peers_stmt->execute([$class_id_target]);
        $classmates = $peers_stmt->fetchAll(PDO::FETCH_COLUMN);
        $total_students = count($classmates);

        $student_averages = [];

        // 2. Loop through every student to calc their avg in this subject
        foreach ($classmates as $sid) {
            // Manual Sum
            $q1 = $pdo->prepare("SELECT SUM(sm.score) as obt, SUM(ca.max_score) as max 
                                 FROM student_marks sm JOIN class_assessments ca ON sm.assessment_id = ca.assessment_id 
                                 WHERE sm.student_id = ? AND ca.subject_id = ?");
            $q1->execute([$sid, $selected_subject_id]);
            $r1 = $q1->fetch();

            // Online Sum
            $q2 = $pdo->prepare("SELECT SUM(sub.obtained_marks) as obt, SUM(oa.total_marks) as max 
                                 FROM assessment_submissions sub JOIN online_assessments oa ON sub.assessment_id = oa.id 
                                 WHERE sub.student_id = ? AND oa.subject_id = ? AND sub.is_marked = 1");
            $q2->execute([$sid, $selected_subject_id]);
            $r2 = $q2->fetch();

            $s_obt = ($r1['obt'] ?? 0) + ($r2['obt'] ?? 0);
            $s_max = ($r1['max'] ?? 0) + ($r2['max'] ?? 0);
            $s_avg = ($s_max > 0) ? ($s_obt / $s_max) * 100 : 0;
            
            $student_averages[$sid] = $s_avg;
        }

        // 3. Sort & Find Rank
        arsort($student_averages); // Sort high to low
        $rank = 1;
        foreach ($student_averages as $sid => $avg) {
            if ($sid == $view_student_id) {
                $class_rank = $rank;
                break;
            }
            $rank++;
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
        :root { --primary: #FF6600; --primary-hover: #e65c00; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; --border: #dfe3e8; --nav-height: 75px; }
        html, body { background-color: var(--light-bg); margin: 0; padding: 0; font-family: 'Public Sans', sans-serif; overflow-y: auto; }

        /* === NAVBAR === */
        .top-navbar { position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height); background: var(--white); z-index: 1000; display: flex; justify-content: space-between; align-items: center; padding: 0 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-bottom: 1px solid var(--border); box-sizing: border-box; }
        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }
        .nav-menu { display: flex; gap: 5px; align-items: center; }
        .nav-item { text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem; padding: 10px 15px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }
        .btn-logout { text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s; }
        .btn-logout:hover { background: #ff4d4f; color: white; }

        /* === CONTENT === */
        .main-content { margin-top: var(--nav-height); padding: 40px 5%; }
        .container { max-width: 1300px; margin: 0 auto; }

        /* Stats */
        .top-stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 25px; }
        .main-dashboard-grid { display: grid; grid-template-columns: 320px 1fr; gap: 25px; }
        .white-card { background: white; border-radius: 20px; border: 1px solid var(--border); padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .summary-box { display: flex; justify-content: space-between; align-items: center; }
        .stat-label { font-size: 0.8rem; color: #637381; font-weight: 700; text-transform: uppercase; }
        .stat-val { font-size: 1.8rem; font-weight: 800; color: var(--dark); display: block; margin-top:5px; }
        .stat-icon { font-size: 2.5rem; color: var(--primary); opacity: 0.15; }

        /* Folders & Lists */
        .folder-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .folder-card { background: white; padding: 30px; border-radius: 16px; border: 1px solid var(--border); text-align: center; cursor: pointer; transition: 0.3s; }
        .folder-card:hover { transform: translateY(-5px); border-color: var(--primary); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .folder-icon { font-size: 3.5rem; color: #ffd1b3; margin-bottom: 10px; }

        .styled-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .styled-table th { text-align: left; padding: 12px; color: #637381; font-size: 0.75rem; text-transform: uppercase; border-bottom: 1px solid var(--border); }
        .styled-table td { padding: 12px; border-bottom: 1px solid #f4f6f8; font-size: 0.9rem; vertical-align: middle; }
        
        .btn-message { width: 100%; background: var(--dark); color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.2s; }

        /* Roles */
        .role-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; display: inline-flex; align-items: center; gap: 5px; }
        .role-Citizen { background: #f4f6f8; color: #637381; }
        .role-President { background: #fff7e6; color: #b78103; border: 1px solid #ffe7ba; }
        .role-Vice { background: #e6f7ff; color: #0050b3; border: 1px solid #bae7ff; }
        .role-Devotion { background: #f9f0ff; color: #722ed1; border: 1px solid #d3adf7; }
        .role-Time { background: #f6ffed; color: #389e0d; border: 1px solid #d9f7be; }

        .btn-assign-role { background: none; border: 1px solid var(--border); border-radius: 6px; padding: 4px 8px; cursor: pointer; font-size: 0.8rem; color: var(--dark); transition: 0.2s; }
        .btn-assign-role:hover { background: var(--primary); color: white; border-color: var(--primary); }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 2000; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; width: 350px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .modal-open { display: flex; }
        .role-select { width: 100%; padding: 12px; margin: 15px 0; border-radius: 10px; border: 1px solid var(--border); font-size: 1rem; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box"><img src="../assets/images/logo.png" alt="NGA"></div>
        <span class="nav-brand-text">Teacher Portal</span>
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item"><i class='bx bxs-dashboard'></i> <span>Dashboard</span></a>
        <a href="my_students.php" class="nav-item active"><i class='bx bxs-user-detail'></i> <span>Students</span></a>
        <a href="assessments.php" class="nav-item"><i class='bx bxs-layer'></i> <span>Assessments</span></a>
        <a href="view_all_marks.php" class="nav-item"><i class='bx bxs-edit'></i> <span>Grading</span></a>
        <a href="messages.php" class="nav-item"><i class='bx bxs-chat'></i> <span>Chat</span></a>
        <a href="take_attendance.php" class="nav-item"><i class='bx bxs-file-doc'></i> <span>Attendance</span></a>
    </div>
    <div class="nav-user"><a href="../logout.php" class="btn-logout">Logout</a></div>
</nav>

<div class="main-content">
    <div class="container">

        <?php if ($student_data): ?>
            <a href="my_students.php?class_id=<?php echo $_GET['class_id'] ?? ''; ?>&subject_id=<?php echo $selected_subject_id; ?>&view_list=1" style="text-decoration:none; color:#637381; font-weight:600; display:flex; align-items:center; gap:5px; margin-bottom:20px;">
                <i class='bx bx-arrow-back'></i> Back to List
            </a>

            <div class="top-stats-row">
                <div class="white-card summary-box">
                    <div>
                        <span class="stat-label">Current Average</span>
                        <span class="stat-val" style="color:<?php echo $real_average >= 70 ? '#00ab55' : '#ff4d4f'; ?>;">
                            <?php echo $real_average; ?>%
                        </span>
                    </div>
                    <i class='bx bx-trending-up stat-icon'></i>
                </div>
                <div class="white-card summary-box">
                    <div><span class="stat-label">Subject Rank</span><span class="stat-val">#<?php echo $class_rank; ?> / <?php echo $total_students; ?></span></div>
                    <i class='bx bx-medal stat-icon'></i>
                </div>
                <div class="white-card summary-box">
                    <div><span class="stat-label">Assessments</span><span class="stat-val"><?php echo count($subject_performance); ?></span></div>
                    <i class='bx bx-check-double stat-icon'></i>
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
                        
                        <span class="role-badge role-<?php echo explode(' ', $student_data['class_role'])[0]; ?>" style="margin-top:10px;">
                            <?php echo $student_data['class_role']; ?>
                        </span>
                    </div>
                    <p style="font-size:0.9rem; color:#637381; margin-bottom: 20px;"><strong>Class:</strong> <?php echo $student_data['class_name']; ?></p>
                    <a href="messages.php?user_id=<?php echo $view_student_id; ?>" class="btn-message" style="text-decoration:none;"><i class='bx bxs-chat'></i> Send Message</a>
                </div>

                <div style="display: flex; flex-direction: column; gap: 25px;">
                    <div class="white-card">
                        <h3 style="margin-top:0; font-size:1.1rem;"><i class='bx bx-bar-chart-alt-2' style="color:var(--primary);"></i> Score Velocity</h3>
                        <div style="height: 350px;"><canvas id="performanceChart"></canvas></div>
                    </div>
                </div>
            </div>

        <?php elseif (isset($_GET['view_list'])): ?>
            <?php 
            $class_id = $_GET['class_id'];
            // Check if Homeroom Teacher
            $ct_stmt = $pdo->prepare("SELECT class_teacher_id FROM classes WHERE class_id = ?");
            $ct_stmt->execute([$class_id]);
            $ct_id = $ct_stmt->fetchColumn();
            $is_homeroom = ($ct_id == $teacher_id);
            ?>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h1 style="margin:0; font-size:1.5rem;">
                    <?php echo $classes_data[$class_id]['name']; ?> Students 
                    <?php if($is_homeroom): ?><span style="font-size:0.8rem; background:#e6f7ff; color:#0050b3; padding:4px 8px; border-radius:10px; margin-left:10px;">Homeroom</span><?php endif; ?>
                </h1>
                <a href="my_students.php" style="color:var(--primary); text-decoration:none; font-weight:700;">Back to Classes</a>
            </div>
            
            <div class="white-card">
                <table class="styled-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Adm No</th>
                            <th>Class Role</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $stmt = $pdo->prepare("SELECT u.user_id, u.full_name, st.admission_number, st.class_role FROM users u JOIN students st ON u.user_id = st.student_id WHERE st.class_id = ? ORDER BY u.full_name ASC");
                        $stmt->execute([$class_id]);
                        foreach($stmt->fetchAll() as $st): 
                            $r_class = explode(' ', $st['class_role'])[0];
                        ?>
                        <tr>
                            <td style="font-weight:700;"><?php echo htmlspecialchars($st['full_name']); ?></td>
                            <td><?php echo $st['admission_number']; ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $r_class; ?>">
                                    <?php echo $st['class_role']; ?>
                                </span>
                            </td>
                            <td>
                                <div style="display:flex; gap:10px;">
                                    <a href="?student_id=<?php echo $st['user_id']; ?>&subject_id=<?php echo $_GET['subject_id']; ?>&class_id=<?php echo $class_id; ?>" style="color:var(--primary); text-decoration:none; font-weight:800; font-size:0.85rem;">STATS</a>
                                    
                                    <?php if($is_homeroom): ?>
                                    <button class="btn-assign-role" onclick="openRoleModal(<?php echo $st['user_id']; ?>, '<?php echo $st['class_role']; ?>', '<?php echo addslashes($st['full_name']); ?>')">
                                        Assign Role
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>
            <h1 style="margin-bottom:10px; font-size:1.5rem;">Managed Students</h1>
            <p style="color:#637381; margin-bottom:40px;">Select a class to analyze metrics.</p>
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

<div id="roleModal" class="modal">
    <form method="POST" class="modal-content">
        <h3 style="color:var(--dark); margin-top:0;">Assign Class Role</h3>
        <p id="studentNameDisplay" style="color:#637381; margin-bottom:15px; font-size:0.9rem;"></p>
        
        <input type="hidden" name="student_id" id="modalStudentId">
        <input type="hidden" name="update_role" value="1">
        <input type="hidden" name="redirect_class" value="<?php echo $_GET['class_id'] ?? ''; ?>">
        <input type="hidden" name="redirect_subject" value="<?php echo $_GET['subject_id'] ?? ''; ?>">
        
        <label style="text-align:left; display:block; font-weight:bold; font-size:0.8rem; color:var(--dark);">Select Role</label>
        <select name="class_role" id="modalRoleSelect" class="role-select">
            <option value="Citizen">Citizen (Standard)</option>
            <option value="President">Class President 👑</option>
            <option value="Vice President">Vice President 🛡️</option>
            <option value="Devotion Leader">Devotion Leader 🙏</option>
            <option value="Time Keeper">Time Keeper ⏰</option>
        </select>
        
        <div style="margin-top:20px; display:flex; gap:10px;">
            <button type="button" style="flex:1; background:#f4f6f8; color:#637381; border:none; padding:12px; border-radius:8px; cursor:pointer; font-weight:bold;" onclick="document.getElementById('roleModal').classList.remove('modal-open')">Cancel</button>
            <button type="submit" style="flex:1; background:var(--primary); color:white; border:none; padding:12px; border-radius:8px; cursor:pointer; font-weight:bold;">Save Role</button>
        </div>
    </form>
</div>

<script>
<?php if ($student_data): ?>
const ctx = document.getElementById('performanceChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Score %',
            data: <?php echo json_encode($chart_scores); ?>,
            borderColor: '#FF6600',
            backgroundColor: 'rgba(255, 102, 0, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4
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

function openRoleModal(id, role, name) {
    document.getElementById('modalStudentId').value = id;
    document.getElementById('modalRoleSelect').value = role;
    document.getElementById('studentNameDisplay').innerText = "Student: " + name;
    document.getElementById('roleModal').classList.add('modal-open');
}
</script>
</body>
</html>