<?php
// student/take_assessment.php
session_start();
require '../config/db.php';

// --- TIMEZONE FIX ---
date_default_timezone_set('Africa/Kigali'); 

// --- CACHE PREVENTION (Fixes "Stuck on previous exam" issue) ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 1. SECURITY & ACCESS CHECKS
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$student_id = $_SESSION['user_id'];
$assessment_id = $_GET['id'] ?? null;
$current_time = date("Y-m-d H:i:s");

if (!$assessment_id) { header("Location: academics.php"); exit; }

// 2. FETCH EXAM DETAILS
$stmt = $pdo->prepare("SELECT * FROM online_assessments WHERE id = ?");
$stmt->execute([$assessment_id]);
$exam = $stmt->fetch();

if (!$exam) { die("Error: Assessment not found."); }

// 3. CHECK IF ALREADY SUBMITTED (Crucial Fix)
$check_sub = $pdo->prepare("SELECT id FROM assessment_submissions WHERE assessment_id = ? AND student_id = ?");
$check_sub->execute([$assessment_id, $student_id]);
$existing_submission = $check_sub->fetch();

if ($existing_submission) {
    // FIX: Redirect to the SPECIFIC result of THIS exam, not the general list.
    header("Location: view_result.php?id=" . $existing_submission['id']); 
    exit;
}

// 4. CHECK TIME WINDOW
if ($current_time < $exam['start_time']) { 
    die("Error: This exam has not started yet. (Opens: " . date("d M H:i", strtotime($exam['start_time'])) . ")"); 
}
if ($current_time > $exam['end_time']) { 
    die("Error: This exam is closed."); 
}

// 5. FETCH QUESTIONS
$q_stmt = $pdo->prepare("SELECT * FROM assessment_questions WHERE assessment_id = ? ORDER BY id ASC");
$q_stmt->execute([$assessment_id]);
$questions = $q_stmt->fetchAll();

// Group options
$opt_stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id IN (SELECT id FROM assessment_questions WHERE assessment_id = ?)");
$opt_stmt->execute([$assessment_id]);
$all_options = $opt_stmt->fetchAll();

$options_map = [];
foreach ($all_options as $opt) {
    $options_map[$opt['question_id']][] = $opt;
}

// 6. HANDLE SUBMISSION
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $answers = $_POST['answers'] ?? []; 
    $total_score = 0;
    $is_fully_graded = true; 

    foreach ($questions as $q) {
        $qid = $q['id'];
        $user_ans = $answers[$qid] ?? null;

        if ($q['question_type'] == 'multiple_choice') {
            if(isset($options_map[$qid])) {
                foreach ($options_map[$qid] as $opt) {
                    if ($opt['is_correct'] && $opt['id'] == $user_ans) {
                        $total_score += $q['points'];
                        break;
                    }
                }
            }
        } else {
            $is_fully_graded = false;
        }
    }

    $json_answers = json_encode($answers);
    
    // Insert Submission
    $save_stmt = $pdo->prepare("INSERT INTO assessment_submissions 
        (assessment_id, student_id, submission_text, obtained_marks, is_marked, submitted_at) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    $marked_status = $is_fully_graded ? 1 : 0;
    $save_stmt->execute([$assessment_id, $student_id, $json_answers, $total_score, $marked_status, $current_time]);
    
    $new_sub_id = $pdo->lastInsertId();

    // Redirect to the SPECIFIC result we just created
    header("Location: view_result.php?id=" . $new_sub_id); 
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam: <?php echo htmlspecialchars($exam['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; }
        body { background: var(--light-bg); font-family: 'Public Sans', sans-serif; margin: 0; padding-top: 80px; }

        .exam-header {
            position: fixed; top: 0; left: 0; width: 100%; height: 80px; background: white; z-index: 1000;
            display: flex; justify-content: space-between; align-items: center; padding: 0 5%; box-sizing: border-box;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }
        .timer-badge {
            background: #212b36; color: white; padding: 10px 20px; border-radius: 30px; 
            font-weight: 800; font-size: 1.1rem; display: flex; align-items: center; gap: 10px;
        }
        .timer-warning { background: #ff4d4f; animation: pulse 1s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.8; } 100% { opacity: 1; } }

        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }

        .question-card {
            background: white; padding: 30px; border-radius: 16px; border: 1px solid #dfe3e8; margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }
        .q-num { font-size: 0.85rem; font-weight: 800; color: #919eab; text-transform: uppercase; margin-bottom: 10px; display: block; }
        .q-text { font-size: 1.1rem; font-weight: 600; color: var(--dark); margin-bottom: 20px; line-height: 1.5; }
        .points-tag { float: right; background: #f4f6f8; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; color: #637381; font-weight: 700; }

        .option-label {
            display: flex; align-items: center; gap: 12px; padding: 15px; border: 2px solid #f4f6f8; border-radius: 10px;
            margin-bottom: 10px; cursor: pointer; transition: 0.2s;
        }
        .option-label:hover { border-color: #ffdcb5; background: #fff5eb; }
        input[type="radio"] { transform: scale(1.3); accent-color: var(--primary); }
        input[type="radio"]:checked + span { font-weight: 700; color: var(--primary); }
        
        textarea, input[type="text"] {
            width: 100%; padding: 15px; border: 1px solid #dfe3e8; border-radius: 10px; 
            font-size: 1rem; font-family: inherit; background: #fafbfc; box-sizing: border-box;
        }
        textarea:focus, input:focus { outline: none; border-color: var(--primary); background: white; }

        .submit-section { text-align: center; margin-top: 40px; padding-bottom: 60px; }
        .btn-submit {
            background: var(--primary); color: white; border: none; padding: 15px 50px; 
            border-radius: 12px; font-size: 1.1rem; font-weight: 800; cursor: pointer; 
            transition: 0.2s; box-shadow: 0 5px 20px rgba(255, 102, 0, 0.3);
        }
        .btn-submit:hover { transform: translateY(-3px); background: #e65c00; }
    </style>
</head>
<body>

<form method="POST" id="examForm">
    <div class="exam-header">
        <div>
            <h2 style="margin:0; font-size:1.2rem; color:var(--dark);"><?php echo htmlspecialchars($exam['title']); ?></h2>
            <small style="color:#637381;">Answer all questions</small>
        </div>
        <div class="timer-badge" id="timerBadge">
            <i class='bx bx-time'></i> <span id="timeLeft">00:00:00</span>
        </div>
    </div>

    <div class="container">
        <?php foreach($questions as $index => $q): ?>
            <div class="question-card">
                <span class="points-tag"><?php echo $q['points']; ?> Points</span>
                <span class="q-num">Question <?php echo $index + 1; ?></span>
                <div class="q-text"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></div>

                <?php if($q['question_type'] == 'multiple_choice'): ?>
                    <div class="options-list">
                        <?php if(isset($options_map[$q['id']])): ?>
                            <?php foreach($options_map[$q['id']] as $opt): ?>
                                <label class="option-label">
                                    <input type="radio" name="answers[<?php echo $q['id']; ?>]" value="<?php echo $opt['id']; ?>">
                                    <span><?php echo htmlspecialchars($opt['option_text']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php elseif($q['question_type'] == 'long_text'): ?>
                    <textarea name="answers[<?php echo $q['id']; ?>]" rows="5" placeholder="Type your answer here..."></textarea>
                <?php else: ?>
                    <input type="text" name="answers[<?php echo $q['id']; ?>]" placeholder="Your answer">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="submit-section">
            <button type="submit" class="btn-submit" onclick="return confirm('Submit Assessment? You cannot change answers after this.')">
                Submit Assessment
            </button>
        </div>
    </div>
</form>

<script>
    // TIMER LOGIC
    const endTime = new Date("<?php echo $exam['end_time']; ?>").getTime();

    const timerInterval = setInterval(function() {
        const now = new Date().getTime();
        const distance = endTime - now;

        if (distance < 0) {
            clearInterval(timerInterval);
            document.getElementById("timeLeft").innerHTML = "EXPIRED";
            alert("Time is up! Submitting answers...");
            document.getElementById("examForm").submit();
            return;
        }

        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        document.getElementById("timeLeft").innerHTML = 
            (hours < 10 ? "0" + hours : hours) + ":" + 
            (minutes < 10 ? "0" + minutes : minutes) + ":" + 
            (seconds < 10 ? "0" + seconds : seconds);

        if (distance < 300000) {
            document.getElementById("timerBadge").classList.add("timer-warning");
        }
    }, 1000);
</script>

</body>
</html>