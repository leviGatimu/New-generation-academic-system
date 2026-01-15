<?php
// teacher/create_assessment.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php"); exit;
}

$teacher_id = $_SESSION['user_id'];

// 1. FETCH CLASS/SUBJECT OPTIONS
$sql = "SELECT c.class_id, c.class_name, s.subject_id, s.subject_name 
        FROM teacher_allocations ta
        JOIN classes c ON ta.class_id = c.class_id
        JOIN subjects s ON ta.subject_id = s.subject_id
        WHERE ta.teacher_id = ? ORDER BY c.class_name";
$stmt = $pdo->prepare($sql);
$stmt->execute([$teacher_id]);
$my_options = $stmt->fetchAll();

// 2. HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        // A. Insert Assessment Header
        $target = explode('_', $_POST['target_class_subject']);
        $class_id = $target[0];
        $subject_id = $target[1];
        
        $status = isset($_POST['publish']) ? 'published' : 'draft';
        $start = str_replace('T', ' ', $_POST['start_time']);
        $end = str_replace('T', ' ', $_POST['end_time']);

        $stmt = $pdo->prepare("INSERT INTO online_assessments 
            (title, description, class_id, subject_id, teacher_id, start_time, end_time, duration_minutes, total_marks, type, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['title'], 
            $_POST['description'], 
            $class_id, 
            $subject_id, 
            $teacher_id, 
            $start, 
            $end, 
            $_POST['duration'], 
            $_POST['calculated_total_marks'], 
            $_POST['type'], 
            $status
        ]);
        
        $assessment_id = $pdo->lastInsertId();

        // === SYSTEM NOTIFICATION (Only if published) ===
        if ($status === 'published') {
            $announcement = "📢 New " . ucfirst($_POST['type']) . " Posted: " . $_POST['title'] . ". Due: " . date("d M H:i", strtotime($end));
            
            // Insert into the Group Chat
            $alert = $pdo->prepare("INSERT INTO messages (sender_id, class_id, message, msg_type) VALUES (?, ?, ?, 'system')");
            $alert->execute([$teacher_id, $class_id, $announcement]);
        }
        // ===============================================

        // B. Insert Questions & Options
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            $q_stmt = $pdo->prepare("INSERT INTO assessment_questions (assessment_id, question_text, question_type, points) VALUES (?, ?, ?, ?)");
            $opt_stmt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");

            foreach ($_POST['questions'] as $key => $q) {
                // 1. Save Question
                $q_stmt->execute([$assessment_id, $q['text'], $q['type'], $q['points']]);
                $question_id = $pdo->lastInsertId();

                // 2. Save Options (Only for Multiple Choice)
                if ($q['type'] === 'multiple_choice' && isset($q['options'])) {
                    foreach ($q['options'] as $opt_key => $opt_text) {
                        $is_correct = (isset($q['correct_option']) && $q['correct_option'] == $opt_key) ? 1 : 0;
                        $opt_stmt->execute([$question_id, $opt_text, $is_correct]);
                    }
                }
            }
        }

        $pdo->commit();
        header("Location: assessments.php?msg=created"); exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("System Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam Builder | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f0f2f5; --white: #ffffff; --border: #dfe3e8; }
        body { background: var(--light-bg); font-family: 'Public Sans', sans-serif; margin: 0; padding-bottom: 100px; }

        /* === TOP NAV === */
        .builder-nav {
            position: fixed; top: 0; width: 100%; height: 70px; background: white; z-index: 1000;
            display: flex; justify-content: space-between; align-items: center; padding: 0 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05); box-sizing: border-box;
        }
        .nav-left { display: flex; align-items: center; gap: 15px; }
        .back-btn { text-decoration: none; color: #637381; font-size: 1.5rem; display: flex; align-items: center; transition: 0.2s; }
        .back-btn:hover { color: var(--dark); transform: translateX(-3px); }
        .nav-title { font-weight: 800; font-size: 1.2rem; color: var(--dark); }

        /* === MAIN CONTAINER === */
        .container { max-width: 850px; margin: 100px auto 0; padding: 0 20px; }

        /* CARDS */
        .card { background: white; border-radius: 16px; padding: 35px; margin-bottom: 25px; border: 1px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.03); transition: 0.2s; }
        .card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
        
        .section-title { font-size: 0.85rem; font-weight: 800; color: var(--primary); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        
        /* FORM ELEMENTS */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 0.9rem; font-weight: 700; color: var(--dark); margin-bottom: 8px; }
        input[type="text"], input[type="number"], input[type="datetime-local"], select, textarea {
            width: 100%; padding: 12px 15px; border: 1px solid var(--border); border-radius: 10px;
            font-size: 0.95rem; font-family: inherit; background: #fafbfc; transition: 0.2s; box-sizing: border-box;
        }
        input:focus, select:focus, textarea:focus { border-color: var(--primary); background: white; outline: none; box-shadow: 0 0 0 4px rgba(255,102,0,0.1); }

        /* === QUESTION BUILDER STYLES === */
        .question-card {
            border-left: 5px solid var(--primary);
            position: relative;
            animation: slideUp 0.3s ease-out;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .q-top-row { display: flex; gap: 20px; margin-bottom: 15px; }
        .q-text-area { flex-grow: 1; }
        .q-controls { width: 220px; flex-shrink: 0; display: flex; flex-direction: column; gap: 15px; }

        /* Options List */
        .options-wrapper { background: #f9fafb; padding: 20px; border-radius: 12px; border: 1px dashed var(--border); margin-top: 15px; display: none; }
        .option-row { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
        
        .radio-btn { 
            width: 20px; height: 20px; accent-color: var(--primary); cursor: pointer; 
            transform: scale(1.2); 
        }
        
        .btn-icon { background: none; border: none; font-size: 1.2rem; color: #919eab; cursor: pointer; transition: 0.2s; }
        .btn-icon:hover { color: #ff4d4f; }

        .btn-add-opt {
            font-size: 0.85rem; font-weight: 700; color: var(--primary); background: none; border: none;
            cursor: pointer; display: flex; align-items: center; gap: 5px; margin-top: 5px;
        }
        .btn-add-opt:hover { text-decoration: underline; }

        /* Remove Question Button */
        .btn-remove-q {
            position: absolute; top: 20px; right: 20px; width: 30px; height: 30px; border-radius: 50%;
            background: #fff0f0; color: #ff4d4f; border: none; display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: 0.2s;
        }
        .btn-remove-q:hover { background: #ff4d4f; color: white; }

        /* Big Add Button */
        .btn-add-question {
            width: 100%; padding: 20px; border: 2px dashed var(--border); border-radius: 16px;
            background: white; color: #637381; font-weight: 700; font-size: 1rem; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 10px; transition: 0.2s;
        }
        .btn-add-question:hover { border-color: var(--primary); color: var(--primary); background: #fff5f0; }

        /* === FOOTER BAR === */
        .footer-bar {
            position: fixed; bottom: 0; left: 0; width: 100%; height: 80px; background: white;
            border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;
            padding: 0 10%; box-sizing: border-box; z-index: 999; box-shadow: 0 -5px 20px rgba(0,0,0,0.03);
        }
        .total-badge { font-size: 1.1rem; font-weight: 800; color: var(--dark); background: #f4f6f8; padding: 8px 20px; border-radius: 30px; }
        
        .btn-action { padding: 12px 30px; border-radius: 12px; font-weight: 700; border: none; cursor: pointer; transition: 0.2s; font-size: 0.95rem; }
        .btn-sec { background: white; border: 2px solid var(--border); color: #637381; margin-right: 10px; }
        .btn-sec:hover { border-color: var(--dark); color: var(--dark); }
        .btn-pri { background: var(--dark); color: white; }
        .btn-pri:hover { background: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255, 102, 0, 0.3); }

    </style>
</head>
<body>

<form method="POST" id="examForm">

    <nav class="builder-nav">
        <div class="nav-left">
            <a href="assessments.php" class="back-btn"><i class='bx bx-arrow-back'></i></a>
            <span class="nav-title">Exam Builder</span>
        </div>
        <div style="font-size:0.9rem; color:#637381; display:flex; align-items:center; gap:8px;">
            <i class='bx bxs-user-circle'></i> <?php echo $_SESSION['name']; ?>
        </div>
    </nav>

    <div class="container">
        
        <div class="card">
            <div class="section-title"><i class='bx bx-slider-alt'></i> Assessment Details</div>
            
            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" placeholder="e.g. Mid-Term Geometry Exam" required style="font-size:1.2rem; font-weight:700;">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Assign To (Class & Subject)</label>
                    <select name="target_class_subject" required>
                        <?php foreach($my_options as $opt): ?>
                            <option value="<?php echo $opt['class_id'].'_'.$opt['subject_id']; ?>">
                                <?php echo htmlspecialchars($opt['class_name'] . ' - ' . $opt['subject_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Assessment Type</label>
                    <select name="type">
                        <option value="quiz">Quiz (Quick Check)</option>
                        <option value="exam">Exam (Formal)</option>
                        <option value="assignment">Assignment (Homework)</option>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Opens At</label>
                    <input type="datetime-local" name="start_time" required>
                </div>
                <div class="form-group">
                    <label>Closes At</label>
                    <input type="datetime-local" name="end_time" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Time Limit (Minutes)</label>
                    <input type="number" name="duration" placeholder="60" required>
                </div>
                </div>

            <div class="form-group">
                <label>Instructions</label>
                <textarea name="description" rows="3" placeholder="Enter rules, guidelines, or a welcome message..."></textarea>
            </div>
        </div>

        <div id="questions-container">
            </div>

        <button type="button" class="btn-add-question" onclick="addQuestion()">
            <i class='bx bx-plus-circle' style="font-size:1.5rem;"></i> Add New Question
        </button>

        <div style="height: 120px;"></div> </div>

    <div class="footer-bar">
        <div class="total-badge">
            Total Points: <span id="displayTotal" style="color:var(--primary);">0</span>
            <input type="hidden" name="calculated_total_marks" id="inputTotal" value="0">
        </div>
        <div>
            <button type="submit" name="draft" class="btn-action btn-sec">Save Draft</button>
            <button type="submit" name="publish" class="btn-action btn-pri">Publish Exam</button>
        </div>
    </div>

</form>

<script>
    let qCount = 0;

    function addQuestion() {
        qCount++;
        const container = document.getElementById('questions-container');
        
        const html = `
        <div class="card question-card" id="q_card_${qCount}">
            <button type="button" class="btn-remove-q" onclick="removeQuestion(${qCount})" title="Remove Question">
                <i class='bx bx-trash'></i>
            </button>
            
            <div class="section-title" style="color:var(--dark); margin-bottom:15px;">
                <span style="background:var(--dark); color:white; width:24px; height:24px; display:flex; align-items:center; justify-content:center; border-radius:50%; font-size:0.8rem;">${qCount}</span>
                Question Content
            </div>

            <div class="q-top-row">
                <div class="q-text-area">
                    <textarea name="questions[${qCount}][text]" rows="2" placeholder="Type your question here..." required style="resize:vertical;"></textarea>
                </div>
                <div class="q-controls">
                    <select name="questions[${qCount}][type]" onchange="toggleOptions(${qCount}, this.value)">
                        <option value="short_answer">Short Answer</option>
                        <option value="long_text">Paragraph / Essay</option>
                        <option value="multiple_choice">Multiple Choice</option>
                    </select>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:0.85rem; font-weight:700; color:#637381;">Points:</span>
                        <input type="number" name="questions[${qCount}][points]" value="1" min="1" class="points-input" style="width:70px;" onchange="calculateTotal()">
                    </div>
                </div>
            </div>

            <div id="options_wrapper_${qCount}" class="options-wrapper">
                <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                    <label style="color:var(--primary); font-size:0.8rem;">Answer Options</label>
                    <label style="color:#637381; font-size:0.75rem;">Select the correct answer dot</label>
                </div>
                
                <div id="opt_list_${qCount}"></div>
                
                <button type="button" class="btn-add-opt" onclick="addOption(${qCount})">
                    <i class='bx bx-plus'></i> Add Another Option
                </button>
            </div>
        </div>
        `;
        
        container.insertAdjacentHTML('beforeend', html);
        calculateTotal();
        
        // Scroll to new question smoothly
        // document.getElementById(`q_card_${qCount}`).scrollIntoView({behavior: "smooth", block: "center"});
    }

    function removeQuestion(id) {
        if(confirm("Remove this question?")) {
            document.getElementById(`q_card_${id}`).remove();
            calculateTotal();
        }
    }

    function toggleOptions(id, type) {
        const wrapper = document.getElementById(`options_wrapper_${id}`);
        const list = document.getElementById(`opt_list_${id}`);
        
        if (type === 'multiple_choice') {
            wrapper.style.display = 'block';
            if(list.children.length === 0) {
                addOption(id);
                addOption(id);
            }
        } else {
            wrapper.style.display = 'none';
        }
    }

    function addOption(qId) {
        const list = document.getElementById(`opt_list_${qId}`);
        const optIndex = list.children.length;
        
        const html = `
        <div class="option-row">
            <input type="radio" name="questions[${qId}][correct_option]" value="${optIndex}" class="radio-btn" required title="Mark as correct answer">
            <input type="text" name="questions[${qId}][options][${optIndex}]" placeholder="Option ${optIndex + 1}" required>
            <button type="button" class="btn-icon" onclick="this.parentElement.remove()">
                <i class='bx bx-x'></i>
            </button>
        </div>
        `;
        list.insertAdjacentHTML('beforeend', html);
    }

    function calculateTotal() {
        let total = 0;
        document.querySelectorAll('.points-input').forEach(input => {
            total += parseInt(input.value) || 0;
        });
        document.getElementById('displayTotal').innerText = total;
        document.getElementById('inputTotal').value = total;
    }

    // Start with 1 question ready
    addQuestion();
</script>

</body>
</html>