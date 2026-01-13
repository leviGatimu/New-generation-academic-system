<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'parent') { header("Location: ../index.php"); exit; }

$parent_id = $_SESSION['user_id'];
$student_id = $_GET['student_id'] ?? null;

// 1. Fetch Children (For the dropdown)
$stmt = $pdo->prepare("SELECT s.student_id, u.full_name FROM parent_student_link psl JOIN students s ON psl.student_id = s.student_id JOIN users u ON s.student_id = u.user_id WHERE psl.parent_id = ?");
$stmt->execute([$parent_id]);
$children = $stmt->fetchAll();

// Default to first child
if (!$student_id && count($children) > 0) {
    $student_id = $children[0]['student_id'];
}

$marks = [];
$student_info = null;

if ($student_id) {
    // 2. Fetch Student Details
    $info_stmt = $pdo->prepare("SELECT u.full_name, s.admission_number, c.class_name FROM students s JOIN users u ON s.student_id = u.user_id JOIN classes c ON s.class_id = c.class_id WHERE s.student_id = ?");
    $info_stmt->execute([$student_id]);
    $student_info = $info_stmt->fetch();

    // 3. Fetch Marks
    $m_stmt = $pdo->prepare("SELECT sub.subject_name, mk.score, ca.max_score, (mk.score/ca.max_score)*100 as percentage FROM student_marks mk JOIN class_assessments ca ON mk.assessment_id = ca.assessment_id JOIN subjects sub ON ca.subject_id = sub.subject_id WHERE mk.student_id = ?");
    $m_stmt->execute([$student_id]);
    $marks = $m_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Report Card | NGA</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* === 1. GLOBAL THEME VARIABLES (Same as Dashboard) === */
        :root { 
            --primary: #FF6600; 
            --bg-body: #f4f6f8; 
            --bg-nav: rgba(255, 255, 255, 0.95);
            --bg-paper: #ffffff;
            --text-main: #212b36; 
            --text-muted: #637381; 
            --border: #dfe3e8; 
            --shadow: 0 4px 12px rgba(0,0,0,0.05);
            --nav-height: 80px;
        }

        /* DARK MODE OVERRIDES */
        [data-theme="dark"] {
            --bg-body: #161c24;
            --bg-nav: rgba(33, 43, 54, 0.95);
            --bg-paper: #212b36; /* Dark Paper for viewing */
            --text-main: #ffffff;
            --text-muted: #919eab;
            --border: #454f5b;
            --shadow: 0 4px 20px rgba(0,0,0,0.5);
        }

        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            margin: 0; 
            transition: 0.3s;
        }

        /* === 2. NAVBAR (Consistent Navigation) === */
        .top-navbar {
            position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height);
            background: var(--bg-nav); backdrop-filter: blur(12px);
            z-index: 1000; display: flex; justify-content: space-between; align-items: center; 
            padding: 0 40px; border-bottom: 1px solid var(--border); box-sizing: border-box;
        }
        .nav-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text-main); font-weight: 800; font-size: 1.2rem; }
        .back-link { text-decoration: none; color: var(--text-muted); font-weight: 700; display: flex; align-items: center; gap: 5px; }
        .back-link:hover { color: var(--primary); }

        /* === 3. REPORT CARD "PAPER" STYLING === */
        .main-content { margin-top: var(--nav-height); padding: 40px 0; display: flex; flex-direction: column; align-items: center; }

        .controls { width: 100%; max-width: 210mm; display: flex; justify-content: space-between; margin-bottom: 20px; }
        
        select { padding: 10px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-paper); color: var(--text-main); outline: none; cursor: pointer; }
        
        .btn-print { 
            background: var(--primary); color: white; border: none; padding: 10px 20px; 
            border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px; 
            box-shadow: 0 4px 10px rgba(255, 102, 0, 0.2);
        }
        .btn-print:hover { background: #e65c00; transform: translateY(-2px); }

        /* The A4 Sheet */
        .paper { 
            background: var(--bg-paper); 
            width: 210mm; 
            min-height: 297mm; /* A4 Height */
            padding: 20mm; 
            box-shadow: var(--shadow); 
            box-sizing: border-box; 
            position: relative;
            border-top: 5px solid var(--primary); /* Nice accent on top */
        }

        /* Report Header */
        .report-header { text-align: center; border-bottom: 2px solid var(--border); padding-bottom: 20px; margin-bottom: 30px; }
        .school-logo { width: 80px; margin-bottom: 10px; }
        .school-title { font-size: 1.8rem; font-weight: 900; text-transform: uppercase; color: var(--primary); letter-spacing: 1px; margin: 0; }
        .school-sub { color: var(--text-muted); font-size: 0.9rem; margin-top: 5px; }

        /* Student Info Grid */
        .info-grid { 
            display: grid; grid-template-columns: 1fr 1fr; gap: 20px; 
            background: rgba(0,0,0,0.02); padding: 15px; border-radius: 8px; border: 1px solid var(--border); 
            margin-bottom: 30px; 
        }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .label { font-weight: bold; color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; }
        .value { font-weight: 800; color: var(--text-main); font-size: 1rem; }

        /* Table */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 12px; background: rgba(0,0,0,0.03); color: var(--text-muted); text-transform: uppercase; font-size: 0.8rem; border-bottom: 2px solid var(--border); }
        td { padding: 14px 12px; border-bottom: 1px solid var(--border); color: var(--text-main); font-size: 0.95rem; }
        tr:last-child td { border-bottom: none; }
        
        .grade-a { color: #00ab55; font-weight: bold; }
        .grade-b { color: #007bff; font-weight: bold; }
        .grade-c { color: #d48806; font-weight: bold; }
        .grade-f { color: #ff4d4f; font-weight: bold; }

        /* Footer / Signatures */
        .report-footer { margin-top: 60px; display: flex; justify-content: space-between; }
        .sig-box { text-align: center; width: 40%; }
        .sig-line { border-bottom: 1px solid var(--text-muted); height: 40px; margin-bottom: 10px; }
        .sig-text { font-size: 0.8rem; font-weight: bold; color: var(--text-muted); text-transform: uppercase; }

        /* === PRINT MODE (ALWAYS WHITE PAPER) === */
        @media print {
            body { background: white; -webkit-print-color-adjust: exact; }
            .top-navbar, .controls, .btn-print { display: none !important; }
            .main-content { margin: 0; padding: 0; }
            .paper { 
                box-shadow: none; border: none; width: 100%; margin: 0; padding: 0; 
                background: white !important; color: black !important;
            }
            .school-title { color: #FF6600 !important; } /* Force color print */
            td, th, .value { color: black !important; }
            .info-grid { border: 1px solid #ccc; }
        }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <img src="../assets/images/logo.png" width="30" alt=""> Report Portal
    </a>
    <a href="dashboard.php" class="back-link">
        <i class='bx bx-arrow-back'></i> Back to Dashboard
    </a>
</nav>

<div class="main-content">
    
    <div class="controls">
        <form method="GET">
            <select name="student_id" onchange="this.form.submit()">
                <?php foreach($children as $c): ?>
                    <option value="<?php echo $c['student_id']; ?>" <?php if($c['student_id'] == $student_id) echo 'selected'; ?>>
                        Report for: <?php echo $c['full_name']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <button onclick="window.print()" class="btn-print">
            <i class='bx bxs-printer'></i> Print Report
        </button>
    </div>

    <div class="paper">
        
        <div class="report-header">
            <img src="../assets/images/logo.png" class="school-logo" alt="Logo">
            <h1 class="school-title">New Generation Academy</h1>
            <div class="school-sub">Kigali, Rwanda • Excellence in Technology & Arts • Term 1 2026</div>
        </div>

        <?php if($student_info): ?>
        <div class="info-grid">
            <div>
                <div class="info-row"><span class="label">Student Name:</span> <span class="value"><?php echo $student_info['full_name']; ?></span></div>
                <div class="info-row"><span class="label">Admission No:</span> <span class="value"><?php echo $student_info['admission_number']; ?></span></div>
            </div>
            <div style="text-align:right;">
                <div class="info-row" style="justify-content:flex-end; gap:20px;"><span class="label">Class:</span> <span class="value"><?php echo $student_info['class_name']; ?></span></div>
                <div class="info-row" style="justify-content:flex-end; gap:20px;"><span class="label">Date Issued:</span> <span class="value"><?php echo date("d M Y"); ?></span></div>
            </div>
        </div>
        <?php endif; ?>

        <h3 style="text-align:center; text-transform:uppercase; font-size:1.1rem; border-bottom:2px solid var(--primary); display:inline-block; margin: 0 auto 20px auto; padding-bottom:5px; color:var(--text-main);">Official Results</h3>
        
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Marks Obtained</th>
                    <th>Total Possible</th>
                    <th>Percentage</th>
                    <th>Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($marks) > 0): ?>
                    <?php 
                        $total_p = 0; 
                        foreach($marks as $m): 
                        $total_p += $m['percentage'];
                        $gradeClass = 'grade-f';
                        $gradeChar = 'F';
                        
                        if($m['percentage'] >= 80) { $gradeClass = 'grade-a'; $gradeChar = 'A'; }
                        elseif($m['percentage'] >= 70) { $gradeClass = 'grade-b'; $gradeChar = 'B'; }
                        elseif($m['percentage'] >= 50) { $gradeClass = 'grade-c'; $gradeChar = 'C'; }
                    ?>
                    <tr>
                        <td style="font-weight:600;"><?php echo $m['subject_name']; ?></td>
                        <td><?php echo $m['score']; ?></td>
                        <td><?php echo $m['max_score']; ?></td>
                        <td><?php echo round($m['percentage']); ?>%</td>
                        <td class="<?php echo $gradeClass; ?>"><?php echo $gradeChar; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <tr style="background:rgba(0,0,0,0.02); font-weight:bold; border-top:2px solid var(--border);">
                        <td>OVERALL AVERAGE</td>
                        <td colspan="2"></td>
                        <td style="color:var(--primary); font-size:1.1rem;"><?php echo round($total_p / count($marks), 1); ?>%</td>
                        <td></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:30px;">No academic records found for this term.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="report-footer">
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-text">Class Teacher Signature</div>
            </div>
            <div class="sig-box">
                <div class="sig-line"><img src="../assets/images/signature.png" style="height:40px; opacity:0.6;" alt="(Signed)"></div>
                <div class="sig-text">Principal Signature</div>
            </div>
        </div>

        <div style="margin-top:50px; text-align:center; font-size:0.8rem; color:var(--text-muted); font-style:italic;">
            This document is electronically generated by Academic Bridge. Any alteration renders it invalid.
        </div>

    </div>
</div>

<script>
    // 1. Immediately apply saved theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
</script>

</body>
</html>