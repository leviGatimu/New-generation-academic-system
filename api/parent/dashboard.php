<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') {
    header("Location: ../index.php"); exit;
}

$parent_id = $_SESSION['user_id'];
$parent_name = $_SESSION['name'];

try {
    // 1. Get Unread Notifications Count (For the Bell)
    $notif_stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $notif_stmt->execute([$parent_id]);
    $unread_count = $notif_stmt->fetchColumn();

    // 2. Fetch ALL Linked Children
    $stmt = $pdo->prepare("SELECT s.student_id, u.full_name, c.class_name, s.admission_number 
                           FROM parent_student_link psl
                           JOIN students s ON psl.student_id = s.student_id
                           JOIN users u ON s.student_id = u.user_id
                           JOIN classes c ON s.class_id = c.class_id
                           WHERE psl.parent_id = ?");
    $stmt->execute([$parent_id]);
    $children = $stmt->fetchAll();

    $total_children = count($children);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parent Portal | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
   <style>
    /* === 1. VARIABLES & THEME CONFIGURATION === */
    :root { 
        /* Colors for Light Mode (Default) */
        --primary: #FF6600; 
        --primary-hover: #e65c00; 
        
        --bg-body: #f4f6f8; 
        --bg-card: #ffffff; 
        --bg-nav: rgba(255, 255, 255, 0.95);
        --bg-item: #f9fafb; /* For fee boxes */
        
        --text-main: #212b36; 
        --text-muted: #637381; 
        
        --border: #dfe3e8; 
        --shadow: 0 4px 12px rgba(0,0,0,0.03);
        
        --nav-height: 80px; 
    }

    /* Colors for Dark Mode */
    [data-theme="dark"] {
        --bg-body: #161c24; /* Deep dark background */
        --bg-card: #212b36; /* Dark grey cards */
        --bg-nav: rgba(33, 43, 54, 0.95); /* Dark glass header */
        --bg-item: #28313c;
        
        --text-main: #ffffff; /* White text */
        --text-muted: #919eab; /* Light grey text */
        
        --border: #353f49; /* Dark borders */
        --shadow: 0 4px 12px rgba(0,0,0,0.5); /* Stronger shadow */
    }

    /* === 2. GENERAL STYLES === */
    body { 
        background-color: var(--bg-body); 
        color: var(--text-main);
        margin: 0; 
        font-family: 'Public Sans', sans-serif; 
        transition: background-color 0.3s ease, color 0.3s ease;
    }
    
    /* === 3. HEADER STYLES === */
    .top-navbar {
        position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height);
        background: var(--bg-nav); /* Uses variable */
        backdrop-filter: blur(12px);
        z-index: 1000; 
        display: flex; justify-content: space-between; align-items: center; 
        padding: 0 40px; 
        border-bottom: 1px solid var(--border);
        box-shadow: var(--shadow);
        box-sizing: border-box;
        transition: background 0.3s ease;
    }

    .nav-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
    .logo-box { width: 40px; display: flex; align-items: center; }
    .logo-box img { width: 100%; height: auto; }
    .nav-brand-text { font-size: 1.2rem; font-weight: 800; color: var(--text-main); letter-spacing: -0.5px; }

    /* Menu Items */
    .nav-menu { display: flex; gap: 15px; align-items: center; }

    .nav-link, .notif-box { 
        text-decoration: none; 
        color: var(--text-muted); /* Variable */
        font-weight: 700; 
        font-size: 0.95rem; 
        padding: 10px 20px; 
        border-radius: 50px; 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex; align-items: center; gap: 8px;
        border: 1px solid transparent;
    }

    .nav-link:hover, .notif-box:hover {
        background-color: rgba(255, 102, 0, 0.1); /* Transparent Orange */
        color: var(--primary); 
        transform: translateY(-2px);
    }

    .nav-link.active {
        background: linear-gradient(135deg, #FF6600 0%, #ff8533 100%);
        color: white !important;
        box-shadow: 0 6px 15px rgba(255, 102, 0, 0.3);
        transform: translateY(0);
    }
    
    /* Icons */
    .nav-link i { transition: transform 0.3s ease; }
    .nav-link:hover i { transform: rotate(-10deg) scale(1.1); }

    /* Theme Toggle Button Style */
    .theme-btn {
        background: none; border: none; cursor: pointer;
        font-size: 1.5rem; color: var(--text-muted);
        display: flex; align-items: center; justify-content: center;
        padding: 8px; border-radius: 50%; transition: 0.3s;
    }
    .theme-btn:hover { background: rgba(0,0,0,0.05); color: var(--primary); }

    /* Logout */
    .btn-logout {
        text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.9rem;
        padding: 10px 20px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s;
        display: flex; align-items: center; gap: 6px;
    }
    .btn-logout:hover { background: #ff4d4f; color: white; box-shadow: 0 4px 12px rgba(255, 77, 79, 0.2); }

    /* === 4. DASHBOARD CONTENT STYLES === */
    .main-content { margin-top: var(--nav-height); padding: 40px 5%; }
    
    /* Cards */
    .welcome-card, .white-card, .stat-card { 
        background: var(--bg-card); /* Variable */
        border-radius: 16px; 
        border: 1px solid var(--border); 
        padding: 25px; 
        box-shadow: var(--shadow); 
        margin-bottom: 30px; 
        transition: background 0.3s ease;
    }
    
    .welcome-card { padding: 40px; display: flex; justify-content: space-between; align-items: center; border-radius: 20px; }
    
    /* Text Colors inside cards */
    h1, h2, h3, h4 { color: var(--text-main); }
    p, span, small, td { color: var(--text-muted); }
    
    /* Stats Grid */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-icon { width: 50px; height: 50px; border-radius: 12px; background: rgba(255, 102, 0, 0.1); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    
    /* Fee Grid */
    .fee-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-bottom: 20px; }
    .fee-item { 
        background: var(--bg-item); /* Variable */
        padding: 15px; border-radius: 10px; border: 1px solid var(--border); text-align: center; 
    }
    .fee-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .fee-amount { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-top: 5px; display: block; }
    
    /* Tables */
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th { text-align: left; padding: 12px; border-bottom: 2px solid var(--border); color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; }
    td { padding: 12px; border-bottom: 1px solid var(--border); color: var(--text-main); font-size: 0.95rem; }
    .grade-badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85rem; }

    /* Action Buttons */
    .btn-action { display: inline-flex; align-items: center; gap: 8px; padding: 10px 15px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: 0.2s; cursor: pointer; border: none; }
    .btn-chat { background: rgba(0, 123, 255, 0.1); color: #007bff; } .btn-chat:hover { background: #007bff; color: white; }
    .btn-view { background: rgba(255, 102, 0, 0.1); color: var(--primary); } .btn-view:hover { background: var(--primary); color: white; }
    
    /* Notification Badge */
    .notif-box { position: relative; cursor: pointer; padding: 10px; }
    .notif-badge { position: absolute; top: 5px; right: 5px; background: #ff4d4f; color: white; font-size: 0.65rem; padding: 2px 5px; border-radius: 50%; font-weight: bold; border: 2px solid var(--bg-card); }
</style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box"><img src="../assets/images/logo.png" alt="NGA"></div>
        <span class="nav-brand-text">Parent Portal</span>
    </a>
    
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-link active">
            <i class='bx bxs-dashboard'></i> Dashboard
        </a>
        <a href="messages.php" class="nav-link">
            <i class='bx bxs-chat'></i> Messages
        </a>
        <a href="report_card.php" class="nav-link">
            <i class='bx bxs-file-pdf'></i> Report Cards
        </a>
        <a href="homework.php" class="nav-link">
            <i class='bx bxs-book-content'></i> Homework
        </a>
         <a href="notifications.php" class="notif-box" title="Notifications">
            <i class='bx bxs-bell' style="font-size: 1.5rem; color: #637381;"></i>
            <?php if($unread_count > 0): ?>
                <span class="notif-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>
        <button onclick="toggleTheme()" style="background:none; border:none; cursor:pointer; font-size:1.4rem; color:var(--text-sec); display:flex; align-items:center;">
    <i class='bx bxs-moon' id="themeIcon"></i>
</button>
        
    
    </div>

    <a href="../logout.php" class="btn-logout" style="margin-left: 20px;">
        <i class='bx bx-log-out'></i> Logout
    </a>
</nav>

<div class="main-content">
    
    <div class="welcome-card">
        <div>
            <h1 style="margin:0; font-size:2rem; color: var(--dark);">Welcome, <?php echo htmlspecialchars($parent_name); ?></h1>
            <p style="color:#637381; margin-top:10px;">Here is the academic overview for your children.</p>
        </div>
        <div style="text-align:right;">
            <span style="display:block; color:#919eab; font-size:0.75rem; font-weight:700;">TOTAL CHILDREN</span>
            <span style="font-size:1.5rem; font-weight:800; color:var(--dark);"><?php echo $total_children; ?></span>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class='bx bxs-user-check'></i></div>
            <div>
                <span style="font-size:1.5rem; font-weight:800; color: var(--dark);"><?php echo $total_children; ?></span><br>
                <small style="color:#637381; font-weight: 600;">Linked Accounts</small>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#fff0e6; color:#FF6600;"><i class='bx bxs-message-dots'></i></div>
            <div>
                <span style="font-size:1.5rem; font-weight:800; color: var(--dark);"><?php echo $unread_count; ?></span><br>
                <small style="color:#637381; font-weight: 600;">Unread Messages</small>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#f0f7ff; color:#007bff;"><i class='bx bxs-calendar'></i></div>
            <div>
                <span style="font-size:1.5rem; font-weight:800; color: var(--dark);">Term 1</span><br>
                <small style="color:#637381; font-weight: 600;">Current Term</small>
            </div>
        </div>
    </div>

    <?php if ($total_children > 0): ?>
        <?php foreach ($children as $child): ?>
            
            <?php
            // Fetch Marks
            $m_stmt = $pdo->prepare("SELECT sub.subject_name, mk.score, ca.max_score, 
                                     (mk.score/ca.max_score)*100 as percentage 
                                     FROM student_marks mk
                                     JOIN class_assessments ca ON mk.assessment_id = ca.assessment_id
                                     JOIN subjects sub ON ca.subject_id = sub.subject_id 
                                     WHERE mk.student_id = ?");
            $m_stmt->execute([$child['student_id']]);
            $marks = $m_stmt->fetchAll();

            // Calculate Avg
            $total_score = 0; $count = 0;
            foreach($marks as $m) { $total_score += $m['percentage']; $count++; }
            $avg = $count > 0 ? round($total_score / $count, 1) : 0;

            // Fetch Fees
            $f_stmt = $pdo->prepare("SELECT * FROM student_fees WHERE student_id = ?");
            $f_stmt->execute([$child['student_id']]);
            $fee = $f_stmt->fetch();
            
            $total_fee = $fee['total_amount'] ?? 0;
            $paid_fee  = $fee['paid_amount'] ?? 0;
            $balance   = $total_fee - $paid_fee;
            $due_date  = $fee['next_due_date'] ?? 'N/A';
            $has_loan  = ($fee['loan_amount'] ?? 0) > 0;
            ?>

            <div class="white-card">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:15px; margin-bottom:15px;">
                    <div style="display:flex; align-items:center; gap:15px;">
                        <div style="width:50px; height:50px; background:#FF6600; color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:1.2rem;">
                            <?php echo strtoupper(substr($child['full_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h2 style="margin:0; font-size:1.3rem; color:var(--dark);"><?php echo htmlspecialchars($child['full_name']); ?></h2>
                            <p style="margin:0; color:#637381; font-size:0.9rem;">
                                <?php echo $child['class_name']; ?> | Adm: <?php echo $child['admission_number']; ?>
                            </p>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span style="display:block; font-size:0.8rem; color:#919eab; font-weight:700;">PERFORMANCE</span>
                        <span style="font-size:1.4rem; font-weight:800; color:<?php echo $avg >= 50 ? '#00ab55' : '#ff4d4f'; ?>;">
                            <?php echo $avg; ?>%
                        </span>
                    </div>
                </div>

                <h4 style="margin:0 0 10px 0; color:#212b36;"><i class='bx bxs-wallet' style="color:#28a745;"></i> School Fees</h4>
                <div class="fee-grid">
                    <div class="fee-item">
                        <span class="fee-label">Total Fee</span>
                        <span class="fee-amount">RWF <?php echo number_format($total_fee); ?></span>
                    </div>
                    <div class="fee-item" style="background:#fff1f0; border-color:#ffa39e;">
                        <span class="fee-label" style="color:#cf1322;">What You Owe</span>
                        <span class="fee-amount" style="color:#cf1322;">RWF <?php echo number_format($balance); ?></span>
                    </div>
                    <div class="fee-item">
                        <span class="fee-label">Next Due Date</span>
                        <span class="fee-amount" style="font-size:1rem; margin-top:8px;">
                            <?php echo $due_date; ?>
                        </span>
                    </div>
                    <?php if($has_loan): ?>
                    <div class="fee-item" style="background:#fffbe6; border-color:#ffe58f;">
                        <span class="fee-label" style="color:#d48806;">Equipment Loan</span>
                        <span class="fee-amount" style="color:#d48806;">RWF <?php echo number_format($fee['loan_amount']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="display:flex; gap:10px; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:20px;">
                    <a href="messages.php?chat=<?php echo $child['student_id']; ?>" class="btn-action btn-chat">
                        <i class='bx bxs-chat'></i> Message Teacher
                    </a>
                    <a href="report_card.php?student_id=<?php echo $child['student_id']; ?>" class="btn-action btn-view">
                        <i class='bx bxs-file-pdf'></i> View Report Card
                    </a>
                </div>

                <h4 style="margin:0 0 10px 0; color:#637381;">Recent Results</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Score</th>
                            <th>Performance</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($marks) > 0): ?>
                            <?php foreach($marks as $m): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($m['subject_name']); ?></td>
                                <td><b><?php echo $m['score']; ?></b> / <?php echo $m['max_score']; ?></td>
                                <td>
                                    <div style="width:100px; height:6px; background:#eee; border-radius:3px; overflow:hidden;">
                                        <div style="width:<?php echo $m['percentage']; ?>%; height:100%; background:var(--primary);"></div>
                                    </div>
                                    <span style="font-size:0.8rem; color:#637381;"><?php echo round($m['percentage']); ?>%</span>
                                </td>
                                <td>
                                    <?php 
                                        $p = $m['percentage'];
                                        if($p >= 80) echo '<span class="grade-badge" style="background:#e6f7ed; color:#00ab55;">A (Excellent)</span>';
                                        elseif($p >= 70) echo '<span class="grade-badge" style="background:#e6f7ff; color:#007bff;">B (Good)</span>';
                                        elseif($p >= 50) echo '<span class="grade-badge" style="background:#fff7e6; color:#ffc107;">C (Average)</span>';
                                        else echo '<span class="grade-badge" style="background:#fff1f0; color:#ff4d4f;">F (Fail)</span>';
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; padding:20px;">No marks uploaded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php endforeach; ?>
    <?php else: ?>
        <div class="white-card" style="text-align:center; padding:50px;">
            <i class='bx bxs-user-plus' style="font-size:4rem; color:#dfe3e8; margin-bottom:20px;"></i>
            <h3>No Linked Children Found</h3>
            <p style="color:#637381; max-width:400px; margin:0 auto 20px auto;">It seems you haven't linked any student accounts yet. Please use the <b>Parent Access Code</b> provided by your child.</p>
            <a href="../parent_register.php" style="background:var(--primary); color:white; padding:12px 25px; border-radius:8px; text-decoration:none; font-weight:bold;">Link New Student</a>
        </div>
    <?php endif; ?>

</div>
<script>
    // Check saved theme on load
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        document.getElementById('themeIcon').classList.replace('bxs-moon', 'bxs-sun');
    }

    function toggleTheme() {
        const html = document.documentElement;
        const icon = document.getElementById('themeIcon');
        
        if (html.getAttribute('data-theme') === 'dark') {
            html.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
            icon.classList.replace('bxs-sun', 'bxs-moon');
        } else {
            html.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
            icon.classList.replace('bxs-moon', 'bxs-sun');
        }
    }
</script>
</body>
</html>