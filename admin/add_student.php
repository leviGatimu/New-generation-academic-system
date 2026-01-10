<?php
// admin/add_student.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = '';
$error = '';

// Helper: Generate Access Key (e.g., NGA-7392)
function generateAccessKey() {
    return "NGA-" . rand(1000, 9999);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['full_name']);
    $class_id = $_POST['class_id'];
    
    $access_key = generateAccessKey();

    try {
        $pdo->beginTransaction();

        $temp_pass = password_hash("temp123", PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (full_name, role, access_key, password) VALUES (:name, 'student', :key, :pass)");
        $stmt->execute([
            'name' => $name,
            'key' => $access_key,
            'pass' => $temp_pass
        ]);
        $new_user_id = $pdo->lastInsertId();

        $year = date("Y");
        $adm_number = "NGA-" . $year . "-" . str_pad($new_user_id, 3, "0", STR_PAD_LEFT);

        $stmt = $pdo->prepare("INSERT INTO students (student_id, class_id, admission_number) VALUES (:uid, :cid, :adm)");
        $stmt->execute([
            'uid' => $new_user_id,
            'cid' => $class_id,
            'adm' => $adm_number
        ]);

        $pdo->commit();
        $success = "Student Pre-Registered! <br><strong>Give them this Access Key:</strong> <span style='font-size:1.8rem; color:#FF6600; display:block; margin-top:10px;'>$access_key</span>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "System Error: " . $e->getMessage();
    }
}

$classes = $pdo->query("SELECT * FROM classes ORDER BY class_name ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Student | NGA Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <style>
        /* === THEME VARIABLES === */
        :root { 
            --primary: #FF6600; 
            --primary-hover: #e65c00;
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

        /* === TOP NAVIGATION BAR === */
        .top-navbar {
            position: fixed; top: 0; left: 0; width: 100%; height: var(--nav-height);
            background: var(--white); z-index: 1000;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-bottom: 1px solid var(--border);
            box-sizing: border-box;
        }

        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .logo-box { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center;  }
        .logo-box img { width: 80%; height: 80%; object-fit: contain; }
        .nav-brand-text { font-size: 1.25rem; font-weight: 800; color: var(--dark); letter-spacing: -0.5px; }

        .nav-menu { display: flex; gap: 5px; align-items: center; }
        .nav-item {
            text-decoration: none; color: #637381; font-weight: 600; font-size: 0.95rem;
            padding: 10px 15px; border-radius: 8px; transition: 0.2s;
            display: flex; align-items: center; gap: 6px;
        }
        .nav-item:hover { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .nav-item.active { background: var(--primary); color: white; }

        .btn-logout {
            text-decoration: none; color: #ff4d4f; font-weight: 700; font-size: 0.85rem;
            padding: 8px 16px; border: 1.5px solid #ff4d4f; border-radius: 8px; transition: 0.2s;
        }
        .btn-logout:hover { background: #ff4d4f; color: white; }

        /* === CONTENT AREA === */
        .main-content {
            margin-top: var(--nav-height);
            padding: 40px 5%;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
            min-height: calc(100vh - var(--nav-height));
        }

        .page-header {
            background: var(--white); padding: 20px 40px;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid var(--border);
            border-radius: 16px 16px 0 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }
        .page-title { margin: 0; font-size: 1.5rem; color: var(--dark); font-weight: 700; }

        /* === FORM CARD === */
        .form-container {
            display: flex; justify-content: center; padding-top: 30px;
        }
        .card {
            background: var(--white); padding: 40px; border-radius: 0 0 16px 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            max-width: 550px; width: 100%; border: 1px solid var(--border); border-top: none;
        }

        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 700; margin-bottom: 10px; color: var(--dark); font-size: 0.9rem; }
        input[type="text"], select {
            width: 100%; padding: 14px 18px; border: 1px solid var(--border);
            border-radius: 10px; font-size: 1rem; outline: none; transition: 0.2s;
            box-sizing: border-box; background-color: var(--white);
        }
        input:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(255,102,0,0.1); }

        .btn-submit {
            background: var(--primary); color: white; width: 100%; padding: 14px;
            border: none; border-radius: 10px; font-weight: 700; font-size: 1rem;
            cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px;
            margin-top: 10px;
        }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255,102,0,0.3); }

        .btn-back { color: #637381; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .btn-back:hover { color: var(--dark); }

        /* Alerts */
        .status-alert { padding: 25px; border-radius: 12px; margin-bottom: 25px; text-align: center; line-height: 1.6; }
        .alert-success { background: #e6f7ed; color: #1e4620; border: 1px solid #c3e6cb; }
        .alert-danger { background: #ffebe9; color: #cc3123; border: 1px solid #ffd3cf; }
        .alert-info { background: #f0f7ff; color: #004085; border: 1px solid #b8daff; font-size: 0.85rem; padding: 15px; border-radius: 8px; margin-bottom: 25px; }
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
        <a href="students.php" class="nav-item active"><i class='bx bxs-user-detail'></i> <span>Students</span></a>
        <a href="teachers.php" class="nav-item"><i class='bx bxs-id-card'></i> <span>Teachers</span></a>
        <a href="classes.php" class="nav-item"><i class='bx bxs-school'></i> <span>Classes</span></a>
        <a href="settings.php" class="nav-item"><i class='bx bxs-cog'></i> <span>Settings</span></a>
    </div>

    <div class="nav-user">
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="main-content">
    
    <div class="page-header">
        <h1 class="page-title">Pre-Register Student</h1>
        <a href="students.php" class="btn-back"><i class='bx bx-arrow-back'></i> Back to List</a>
    </div>

    <div class="form-container">
        <div class="card">
            <?php if($success): ?>
                <div class="status-alert alert-success">
                    <i class='bx bxs-check-circle' style="font-size: 2.5rem; margin-bottom:10px; display:block;"></i>
                    <?php echo $success; ?>
                    <p style="margin-top:15px; font-size: 0.85rem; opacity: 0.8;">The student will use this key to set up their private login credentials.</p>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="status-alert alert-danger">
                    <i class='bx bxs-error-circle'></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="add_student.php" method="POST">
                <div class="form-group">
                    <label>Student's Full Name</label>
                    <input type="text" name="full_name" required placeholder="e.g. Keza Marie" autocomplete="off">
                </div>

                <div class="form-group">
                    <label>Assign Class Level</label>
                    <select name="class_id" required>
                        <option value="" disabled selected>-- Select Class --</option>
                        <?php foreach($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="alert-info">
                    <i class='bx bxs-info-circle'></i> 
                    <strong>Account Setup:</strong> Access Keys allow students to register their own email and password during their first visit.
                </div>

                <button type="submit" class="btn-submit">
                    <i class='bx bx-key'></i> Generate Access Key
                </button>
            </form>
        </div>
    </div>

    <div style="height: 60px;"></div>
</div>

</body>
</html>