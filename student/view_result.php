<?php
// student/view_result.php
session_start();
require '../config/db.php';

// --- TIMEZONE FIX ---
date_default_timezone_set('Africa/Kigali'); 

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: results.php"); exit;
}

$submission_id = $_GET['id'];
$student_id = $_SESSION['user_id'];

// 1. FETCH SUBMISSION
$stmt = $pdo->prepare("SELECT sub.*, oa.title, oa.total_marks, oa.id as exam_id 
                       FROM assessment_submissions sub
                       JOIN online_assessments oa ON sub.assessment_id = oa.id
                       WHERE sub.id = ? AND sub.student_id = ?");
$stmt->execute([$submission_id, $student_id]);
$sub_data = $stmt->fetch();

if (!$sub_data) die("Result not found.");

// Decode answers
$my_answers = json_decode($sub_data['submission_text'], true) ?? [];

// 2. FETCH QUESTIONS
$q_stmt = $pdo->prepare("SELECT * FROM assessment_questions WHERE assessment_id = ?");
$q_stmt->execute([$sub_data['exam_id']]);
$questions = $q_stmt->fetchAll();

$opt_stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id IN (SELECT id FROM assessment_questions WHERE assessment_id = ?)");
$opt_stmt->execute([$sub_data['exam_id']]);
$options = $opt_stmt->fetchAll();

$opt_map = [];
foreach($options as $opt) { $opt_map[$opt['question_id']][] = $opt; }

// Calculate Percentage for graphical display
$percentage = ($sub_data['obtained_marks'] / $sub_data['total_marks']) * 100;
$grade_color = ($percentage >= 50) ? '#00ab55' : '#ff4d4f';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review: <?php echo htmlspecialchars($sub_data['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; --success: #00ab55; --error: #ff4d4f; }
        body { background: var(--light-bg); font-family: 'Public Sans', sans-serif; margin: 0; padding-bottom: 60px; }

        /* HERO SECTION */
        .result-hero {
            background: linear-gradient(135deg, var(--dark) 0%, #434343 100%);
            color: white; padding: 40px 20px 80px; text-align: center;
            position: relative;
        }
        .back-link { position: absolute; top: 20px; left: 20px; color: rgba(255,255,255,0.7); text-decoration: none; display: flex; align-items: center; gap: 5px; font-weight: 600; transition: 0.2s; }
        .back-link:hover { color: white; }
        
        /* SCORE CIRCLE */
        .score-circle-container {
            width: 150px; height: 150px; margin: 30px auto; position: relative;
        }
        .score-circle {
            width: 100%; height: 100%; border-radius: 50%;
            /* The background property is now moved to HTML inline style to fix VS Code error */
            display: flex; align-items: center; justify-content: center;
            animation: scaleIn 0.5s ease-out cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .score-inner {
            width: 85%; height: 85%; background: var(--dark); border-radius: 50%;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .score-number { font-size: 2.5rem; font-weight: 800; line-height: 1; }
        .score-total { font-size: 0.9rem; opacity: 0.7; }
        @keyframes scaleIn { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        /* MAIN CONTENT */
        .paper-container { max-width: 800px; margin: -50px auto 0; padding: 0 20px; position: relative; z-index: 10; }

        /* QUESTION CARDS */
        .q-card {
            background: white; border-radius: 16px; padding: 25px; margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            opacity: 0; transform: translateY(20px);
            animation: slideUp 0.5s ease-out forwards;
        }
        @keyframes slideUp { to { opacity: 1; transform: translateY(0); } }

        .q-header { display: flex; justify-content: space-between; margin-bottom: 15px; font-weight: 700; color: var(--dark); }
        .points-badge { background: #f4f6f8; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; color: #637381; }
        
        .opt-row { 
            display: flex; align-items: center; gap: 12px; padding: 12px 15px; 
            border-radius: 10px; margin-bottom: 8px; border: 2px solid transparent; font-size: 0.95rem;
            transition: 0.2s;
        }

        /* STATUS COLORS */
        .user-correct { background: #e6f7ed; border-color: var(--success); color: #006b36; }
        .user-wrong { background: #fff0f0; border-color: var(--error); color: #b71d18; opacity: 0.8; }
        .show-correct { background: #e6f7ed; border: 2px dashed var(--success); font-weight: 600; position: relative; }
        .show-correct::after { content: 'Correct Answer'; position: absolute; right: 10px; font-size: 0.7rem; color: var(--success); text-transform: uppercase; font-weight: 800; }

        .status-icon { font-size: 1.2rem; width: 24px; display: flex; justify-content: center; }
        .icon-check { color: var(--success); }
        .icon-cross { color: var(--error); }
        .icon-neutral { color: #dfe3e8; }

        .manual-box { background: #fafbfc; padding: 20px; border-radius: 12px; border: 1px dashed var(--border); }
        .teacher-feedback { margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border); color: #637381; font-size: 0.9rem; }
        .feedback-label { display: flex; align-items: center; gap: 5px; font-weight: 700; color: var(--primary); margin-bottom: 5px; }
    </style>
</head>
<body>

<div class="result-hero">
    <a href="results.php" class="back-link"><i class='bx bx-arrow-back'></i> Back to Results</a>
    <h1 style="margin:0 0 5px 0;"><?php echo htmlspecialchars($sub_data['title']); ?></h1>
    <p style="margin:0; opacity:0.8;">Reviewing your submission</p>
    
    <div class="score-circle-container">
        <div class="score-circle" style="background: conic-gradient(<?php echo $grade_color; ?> <?php echo $percentage; ?>%, rgba(255,255,255,0.1) 0);">
            <div class="score-inner">
                <div class="score-number"><?php echo $sub_data['obtained_marks']; ?></div>
                <div class="score-total">OF <?php echo $sub_data['total_marks']; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="paper-container">

    <?php if (empty($questions)) { ?>
        <div class="q-card" style="text-align:center; padding:40px;">
            <i class='bx bx-error-circle' style="font-size:3rem; color:#919eab;"></i>
            <p>No questions found for this assessment.</p>
        </div>
    <?php } else { 
        $delay_counter = 0;
        foreach($questions as $index => $q) { 
            $qid = $q['id'];
            $my_ans_id = $my_answers[$qid] ?? null;
            $delay_counter++;
            $delay_time = $delay_counter * 0.1; 
    ?>
        <div class="q-card" style="animation-delay: <?php echo $delay_time; ?>s;">
            <div class="q-header">
                <span><?php echo ($index + 1) . ". " . htmlspecialchars($q['question_text']); ?></span>
                <span class="points-badge"><?php echo $q['points']; ?> pts</span>
            </div>

            <?php if($q['question_type'] == 'multiple_choice') { ?>
                <div class="opt-list">
                    <?php 
                    if(isset($opt_map[$qid])) {
                        foreach($opt_map[$qid] as $opt) { 
                            $row_class = '';
                            $icon = '<i class="bx bx-radio-circle icon-neutral"></i>';
                            
                            if ($opt['id'] == $my_ans_id) {
                                if ($opt['is_correct']) {
                                    $row_class = 'user-correct';
                                    $icon = '<i class="bx bxs-check-circle icon-check"></i>';
                                } else {
                                    $row_class = 'user-wrong';
                                    $icon = '<i class="bx bxs-x-circle icon-cross"></i>';
                                }
                            } elseif ($opt['is_correct'] && $my_ans_id != $opt['id']) {
                                $row_class = 'show-correct';
                                $icon = '<i class="bx bx-check icon-check"></i>';
                            }
                    ?>
                        <div class="opt-row <?php echo $row_class; ?>">
                            <div class="status-icon"><?php echo $icon; ?></div>
                            <div><?php echo htmlspecialchars($opt['option_text']); ?></div>
                        </div>
                    <?php 
                        } 
                    } 
                    ?>
                </div>
            
            <?php } else { 
                $user_text_ans = $my_answers[$qid] ?? 'No answer provided.';
            ?>
                <div class="manual-box">
                    <strong>Your Answer:</strong>
                    <p style="white-space: pre-wrap; margin-top:5px;"><?php echo htmlspecialchars($user_text_ans); ?></p>
                    
                    <?php if(!empty($sub_data['teacher_comment'])) { ?>
                    <div class="teacher-feedback">
                        <span class="feedback-label"><i class='bx bxs-message-dots'></i> Teacher Feedback</span>
                        <?php echo nl2br(htmlspecialchars($sub_data['teacher_comment'])); ?>
                    </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    <?php } 
    } ?>

</div>

</body>
</html>