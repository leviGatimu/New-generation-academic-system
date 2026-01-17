<?php
// admin/classes.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = ''; $error = '';

// --- ACTIONS: CATEGORIES ---
if (isset($_POST['add_category'])) {
    $name = trim($_POST['cat_name']);
    $color = $_POST['cat_color'];
    if(!empty($name)) {
        $pdo->prepare("INSERT INTO class_categories (category_name, color_code) VALUES (:n, :c)")
            ->execute(['n'=>$name, 'c'=>$color]);
        $success = "Category created.";
    }
}
if (isset($_GET['del_cat'])) {
    $count = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE category_id = ?");
    $count->execute([$_GET['del_cat']]);
    if($count->fetchColumn() > 0) {
        $error = "Cannot delete: Classes exist in this category.";
    } else {
        $pdo->prepare("DELETE FROM class_categories WHERE category_id = ?")->execute([$_GET['del_cat']]);
        $success = "Category deleted.";
    }
}

// --- ACTIONS: CLASSES ---
if (isset($_POST['add_class'])) {
    $name = trim($_POST['class_name']);
    $cat_id = $_POST['category_id'];
    if(!empty($name)) {
        $pdo->prepare("INSERT INTO classes (class_name, category_id) VALUES (:n, :c)")
            ->execute(['n'=>$name, 'c'=>$cat_id]);
        $success = "Class added.";
    }
}
if (isset($_GET['del_class'])) {
    $pdo->prepare("DELETE FROM classes WHERE class_id = ?")->execute([$_GET['del_class']]);
    header("Location: classes.php"); exit;
}

// --- ACTIONS: CURRICULUM ---
if (isset($_POST['assign_subject'])) {
    $class_id = $_POST['class_id'];
    $subject_id = $_POST['subject_id'];
    $check = $pdo->prepare("SELECT * FROM class_subjects WHERE class_id=:c AND subject_id=:s");
    $check->execute(['c'=>$class_id, 's'=>$subject_id]);
    if($check->rowCount() == 0) {
        $pdo->prepare("INSERT INTO class_subjects (class_id, subject_id) VALUES (:c, :s)")
            ->execute(['c'=>$class_id, 's'=>$subject_id]);
        $success = "Subject assigned.";
    }
}
if (isset($_POST['remove_assigned_subject'])) {
    $link_id = $_POST['link_id'];
    $pdo->prepare("DELETE FROM class_subjects WHERE id = :id")->execute(['id'=>$link_id]);
    $success = "Subject removed.";
}

// --- ACTIONS: MASTER SUBJECTS ---
if (isset($_POST['add_subject'])) {
    $name = trim($_POST['subject_name']);
    $cat_id = $_POST['category_id'];
    if(!empty($name)) {
        $pdo->prepare("INSERT INTO subjects (subject_name, category_id) VALUES (:n, :c)")
            ->execute(['n'=>$name, 'c'=>$cat_id]);
        $success = "Subject added.";
    }
}
if (isset($_GET['del_subject'])) {
    $pdo->prepare("DELETE FROM subjects WHERE subject_id = ?")->execute([$_GET['del_subject']]);
    header("Location: classes.php"); exit;
}

// FETCH DATA
$categories = $pdo->query("SELECT * FROM class_categories")->fetchAll();
$sub_sql = "SELECT s.*, c.category_name, c.color_code FROM subjects s LEFT JOIN class_categories c ON s.category_id = c.category_id ORDER BY s.subject_name ASC";
$subjects = $pdo->query($sub_sql)->fetchAll();
$class_sql = "SELECT c.*, cat.category_name, cat.color_code FROM classes c LEFT JOIN class_categories cat ON c.category_id = cat.category_id ORDER BY c.class_name ASC";
$classes = $pdo->query($class_sql)->fetchAll();
$curr_sql = "SELECT cs.id as link_id, cs.class_id, s.subject_id, s.subject_name FROM class_subjects cs JOIN subjects s ON cs.subject_id = s.subject_id";
$curr_links = $pdo->query($curr_sql)->fetchAll();
$class_assignments = [];
foreach($curr_links as $link) { $class_assignments[$link['class_id']][] = $link; }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Classes Manager | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* === THEME VARIABLES === */
        :root { 
            --primary: #FF6600; 
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

        /* === TOP NAVBAR === */
        .top-navbar { 
            position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height); 
            background: var(--white); z-index: 1000; 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 0 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); 
            border-bottom: 1px solid var(--border); box-sizing: border-box; 
        }
        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;  }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }
        
        .nav-menu { display: flex; gap: 5px; align-items: center; }
        .nav-item { text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem; padding: 10px 15px; border-radius: 8px; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }
        
        .btn-logout { text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.85rem; padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s; }

        /* === CONTENT LAYOUT === */
        .main-content { margin-top: var(--nav-height); padding: 0; min-height: calc(100vh - var(--nav-height)); display: block; }
        .page-header { background: var(--white); padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); }
        .content-container { padding: 30px 40px; display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }

        /* === UI COMPONENTS === */
        .tab-container { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px; }
        .tab-btn { padding: 8px 18px; border: 1px solid var(--border); background: var(--white); border-radius: 20px; cursor: pointer; font-weight: 600; color: #637381; transition: 0.2s; white-space: nowrap; }
        .tab-btn.active { background: var(--primary); color: var(--white); border-color: var(--primary); }

        .card { background: var(--white); border-radius: 12px; padding: 20px; border: 1px solid var(--border); box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .class-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: var(--white); border-radius: 10px; margin-bottom: 12px; border: 1px solid var(--border); border-left-width: 6px; transition: 0.2s; }
        .class-item:hover { transform: translateX(5px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }

        .cat-badge { font-size: 0.7rem; padding: 3px 8px; border-radius: 10px; color: white; font-weight: 700; text-transform: uppercase; margin-left: 10px; }
        .form-row { display: flex; gap: 10px; margin-bottom: 20px; }
        input, select { padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; outline: none; background: white; }
        input:focus, select:focus { border-color: var(--primary); }

        .btn-main { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-dark { background: var(--dark); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-outline { background: white; border: 1px solid var(--border); color: #637381; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 5px; }

        /* === MODAL === */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(33, 43, 54, 0.5); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: var(--white); border-radius: 16px; padding: 30px; width: 90%; max-width: 650px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        .assigned-tag { display: flex; justify-content: space-between; align-items: center; background: #f0f9ff; border: 1px solid #bae6fd; color: #0369a1; padding: 10px 15px; border-radius: 8px; margin-bottom: 10px; font-weight: 600; }
    </style>
</head>
<body>
<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box">
            <img src="../assets/images/logo.png" alt="NGA">
        </div>
        <span class="nav-brand-text">NGA Admin</span>
    </a>
    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item"><i class='bx bxs-dashboard'></i> <span>Dashboard</span></a>
        <a href="students.php" class="nav-item"><i class='bx bxs-user-detail'></i> <span>Students</span></a>
        <a href="teachers.php" class="nav-item"><i class='bx bxs-id-card'></i> <span>Teachers</span></a>
        <a href="classes.php" class="nav-item active"><i class='bx bxs-school'></i> <span>Classes</span></a>
        <a href="settings.php" class="nav-item"><i class='bx bxs-cog'></i> <span>Settings</span></a>
    </div>
    <div class="nav-user">
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="main-content">
    <div class="page-header">
        <h1 style="margin:0; font-size:1.5rem;">Academics Manager</h1>
        <button onclick="document.getElementById('catModal').style.display='flex'" class="btn-dark">
            <i class='bx bx-category'></i> Manage Categories
        </button>
    </div>

    <div class="content-container">
        <div>
            <div class="tab-container">
                <button class="tab-btn active" onclick="filterClasses('all', this)">All Levels</button>
                <?php foreach($categories as $cat): ?>
                    <button class="tab-btn" onclick="filterClasses(<?php echo $cat['category_id']; ?>, this)">
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php if($success): ?><div style="background:#e6f7ed; color:#1e4620; padding:12px; margin-bottom:15px; border-radius:8px; border:1px solid #c3e6cb;"><?php echo $success; ?></div><?php endif; ?>
            <?php if($error): ?><div style="background:#ffebe9; color:#cc3123; padding:12px; margin-bottom:15px; border-radius:8px; border:1px solid #ffd3cf;"><?php echo $error; ?></div><?php endif; ?>

            <form method="POST" class="form-row">
                <input type="text" name="class_name" placeholder="Class Name (e.g. Grade 9)" required style="flex:2;">
                <select name="category_id" required style="flex:1;">
                    <option value="" disabled selected>Category...</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="add_class" class="btn-main">Add Class</button>
            </form>

            <div id="classList">
                <?php foreach($classes as $c): ?>
                    <?php $count = isset($class_assignments[$c['class_id']]) ? count($class_assignments[$c['class_id']]) : 0; ?>
                    <div class="class-item" data-category="<?php echo $c['category_id']; ?>" style="border-left-color: <?php echo $c['color_code']; ?>;">
                        <div>
                            <span style="font-weight: 700; font-size: 1.1rem;"><?php echo htmlspecialchars($c['class_name']); ?></span>
                            <span class="cat-badge" style="background: <?php echo $c['color_code']; ?>;">
                                <?php echo htmlspecialchars($c['category_name']); ?>
                            </span>
                        </div>
                        <div style="display:flex; align-items:center; gap:15px;">
                            <button class="btn-outline" onclick='openCurriculum(<?php echo $c['class_id']; ?>, "<?php echo addslashes($c['class_name']); ?>", <?php echo $c['category_id']; ?>, <?php echo json_encode($class_assignments[$c['class_id']] ?? []); ?>)'>
                                <i class='bx bx-book'></i> <?php echo $count; ?> Subjects
                            </button>
                            <a href="classes.php?del_class=<?php echo $c['class_id']; ?>" onclick="return confirm('Delete class?')" style="color: #ff4d4f; font-size: 1.2rem;"><i class='bx bx-trash'></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Master Subjects</h3>
            <p style="font-size:0.8rem; color:#919eab; margin-bottom:15px;">Universal subjects available for curriculum mapping.</p>
            
            <form method="POST" style="margin-bottom:20px;">
                <input type="text" name="subject_name" placeholder="Subject Name" required style="width:100%; margin-bottom:10px;">
                <select name="category_id" required style="width:100%; margin-bottom:15px;">
                    <option value="" disabled selected>Select Category...</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="add_subject" class="btn-main" style="width:100%;">Create Subject</button>
            </form>

            <div style="max-height: 450px; overflow-y: auto;">
                <?php foreach($subjects as $s): ?>
                    <div style="display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f4f6f8;">
                        <div>
                            <div style="font-weight:600; font-size:0.9rem;"><?php echo htmlspecialchars($s['subject_name']); ?></div>
                            <span style="font-size:0.7rem; color:<?php echo $s['color_code']; ?>; font-weight:700;"><?php echo htmlspecialchars($s['category_name']); ?></span>
                        </div>
                        <a href="classes.php?del_subject=<?php echo $s['subject_id']; ?>" style="color: #919eab; font-size: 1.2rem;"><i class='bx bx-x'></i></a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div style="height: 60px;"></div>
</div>

<div id="currModal" class="modal">
    <div class="modal-content">
        <h2 id="currTitle" style="margin-top: 0; color: var(--primary);">Curriculum</h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top:20px;">
            <div>
                <h4 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Assigned</h4>
                <div id="assignedList" style="max-height: 300px; overflow-y: auto;"></div>
                <div id="emptyAssigned" style="color: #919eab; font-style: italic; display: none;">None.</div>
            </div>
            <div>
                <h4 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Add Subject</h4>
                <form method="POST">
                    <input type="hidden" name="class_id" id="modalClassId">
                    <select name="subject_id" id="availableSelect" size="8" style="width: 100%; height: 200px; margin-bottom:15px; border: 1px solid var(--border); border-radius: 8px;"></select>
                    <button type="submit" name="assign_subject" class="btn-main" style="width: 100%;">Assign to Class</button>
                </form>
            </div>
        </div>
        <button onclick="document.getElementById('currModal').style.display='none'" class="btn-outline" style="width: 100%; margin-top: 25px; justify-content: center;">Close Window</button>
    </div>
</div>

<div id="catModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <h3 style="margin-top:0;">Manage Categories</h3>
        <form method="POST">
            <label style="display:block; font-weight:700; margin-bottom:5px;">Category Name</label>
            <input type="text" name="cat_name" required style="width:100%; margin-bottom:15px;">
            <label style="display:block; font-weight:700; margin-bottom:5px;">Identity Color</label>
            <input type="color" name="cat_color" value="#FF6600" style="width:100%; height:45px; margin-bottom:20px; padding:4px;">
            <button type="submit" name="add_category" class="btn-main" style="width:100%;">Create Category</button>
        </form>
        <div style="margin-top:25px; border-top:1px solid #eee; padding-top:15px; max-height: 200px; overflow-y: auto;">
            <?php foreach($categories as $cat): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding-bottom:8px; border-bottom: 1px solid #f9f9f9;">
                    <span><i class='bx bxs-circle' style="color:<?php echo $cat['color_code']; ?>;"></i> <?php echo htmlspecialchars($cat['category_name']); ?></span>
                    <a href="classes.php?del_cat=<?php echo $cat['category_id']; ?>" style="color:#ff4d4f; font-size:1.1rem;"><i class='bx bx-trash'></i></a>
                </div>
            <?php endforeach; ?>
        </div>
        <button onclick="document.getElementById('catModal').style.display='none'" class="btn-outline" style="width: 100%; margin-top: 20px; justify-content: center;">Close</button>
    </div>
</div>

<script>
    function filterClasses(catId, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.class-item').forEach(item => {
            if (catId === 'all' || item.dataset.category == catId) item.style.display = 'flex';
            else item.style.display = 'none';
        });
    }

    const allSubjects = <?php echo json_encode($subjects); ?>;

    function openCurriculum(classId, className, classCatId, assignedSubjects) {
        document.getElementById('modalClassId').value = classId;
        document.getElementById('currTitle').innerText = className + " Curriculum";
        document.getElementById('currModal').style.display = 'flex';
        const assignedContainer = document.getElementById('assignedList');
        assignedContainer.innerHTML = ''; 
        const assignedIds = [];
        if (assignedSubjects.length === 0) {
            document.getElementById('emptyAssigned').style.display = 'block';
        } else {
            document.getElementById('emptyAssigned').style.display = 'none';
            assignedSubjects.forEach(sub => {
                assignedIds.push(parseInt(sub.subject_id));
                const div = document.createElement('div');
                div.className = 'assigned-tag';
                div.innerHTML = `<span>${sub.subject_name}</span>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="link_id" value="${sub.link_id}">
                        <button type="submit" name="remove_assigned_subject" style="background:none; border:none; color:#ff4d4f; cursor:pointer; display:flex; align-items:center;"><i class='bx bx-x-circle' style="font-size:1.2rem;"></i></button>
                    </form>`;
                assignedContainer.appendChild(div);
            });
        }
        const availableSelect = document.getElementById('availableSelect');
        availableSelect.innerHTML = ''; 
        allSubjects.forEach(s => {
            if (s.category_id == classCatId && !assignedIds.includes(parseInt(s.subject_id))) {
                const option = document.createElement('option');
                option.value = s.subject_id;
                option.innerText = s.subject_name;
                availableSelect.appendChild(option);
            }
        });
    }

    window.onclick = function(e) { if (e.target.className === 'modal') e.target.style.display = 'none'; }
</script>

</body>
</html>