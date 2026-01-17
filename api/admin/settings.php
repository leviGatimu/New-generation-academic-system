<?php
// admin/settings.php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = '';

// 1. HANDLE UPDATES
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_name = trim($_POST['school_name']);
    $new_term_id = $_POST['current_term_id'];
    
    // Update Settings Table
    $stmt = $pdo->prepare("UPDATE settings SET school_name = :name, current_term_id = :term WHERE setting_id = 1");
    $stmt->execute(['name' => $new_name, 'term' => $new_term_id]);
    
    // Update Academic Terms Table (Set only ONE term to is_active = 1)
    $pdo->query("UPDATE academic_terms SET is_active = 0"); // Reset all
    $pdo->prepare("UPDATE academic_terms SET is_active = 1 WHERE term_id = :id")->execute(['id' => $new_term_id]);
    
    $success = "System settings updated successfully!";
}

// 2. FETCH CURRENT DATA
$settings = $pdo->query("SELECT * FROM settings WHERE setting_id = 1")->fetch();
$terms = $pdo->query("SELECT * FROM academic_terms ORDER BY term_id ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Settings | NGA Admin</title>
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
        .logo-box { width: 45px; height: 45px; display: center; align-items: center; justify-content: center; overflow: hidden; display: flex;}
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

        /* === SETTINGS CARD === */
        .form-container {
            display: flex; justify-content: center; padding-top: 30px;
        }
        .card {
            background: var(--white); padding: 40px; border-radius: 0 0 16px 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            max-width: 600px; width: 100%; border: 1px solid var(--border); border-top: none;
        }

        .form-group { margin-bottom: 30px; }
        label { display: block; font-weight: 700; margin-bottom: 8px; color: var(--dark); font-size: 0.95rem; }
        .help-text { color: #637381; font-size: 0.85rem; margin-top: 0; margin-bottom: 15px; line-height: 1.4; }
        
        input[type="text"], select {
            width: 100%; padding: 14px 18px; border: 1px solid var(--border);
            border-radius: 10px; font-size: 1rem; outline: none; transition: 0.2s;
            box-sizing: border-box; background-color: var(--white);
        }
        input:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(255,102,0,0.1); }

        .btn-save {
            background: var(--primary); color: white; width: 100%; padding: 15px;
            border: none; border-radius: 10px; font-weight: 700; font-size: 1rem;
            cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .btn-save:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255,102,0,0.3); }

        /* Success Alert */
        .alert-success { 
            background: #e6f7ed; color: #1e4620; padding: 15px 20px; 
            border-radius: 12px; margin-bottom: 25px; border: 1px solid #c3e6cb;
            display: flex; align-items: center; gap: 10px; font-weight: 500;
        }
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
        <a href="classes.php" class="nav-item"><i class='bx bxs-school'></i> <span>Classes</span></a>
        <a href="settings.php" class="nav-item active"><i class='bx bxs-cog'></i> <span>Settings</span></a>
    </div>

    <div class="nav-user">
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="main-content">
    
    <div class="page-header">
        <h1 class="page-title">System Configuration</h1>
    </div>

    <div class="form-container">
        <div class="card">
            <?php if($success): ?>
                <div class="alert-success">
                    <i class='bx bxs-check-circle' style="font-size: 1.2rem;"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>School Name (Branding)</label>
                    <p class="help-text">This name appears on the login screen, navigation bar, and student reports.</p>
                    <input type="text" name="school_name" 
                           value="<?php echo htmlspecialchars($settings['school_name'] ?? 'New Generation Academy'); ?>" required>
                </div>

                <hr style="border: 0; border-top: 1px solid var(--border); margin: 30px 0;">

                <div class="form-group">
                    <label>Current Active Academic Term</label>
                    <p class="help-text">Changing the active term will switch the gradebook view and records for all users immediately.</p>
                    
                    <select name="current_term_id" required>
                        <?php foreach($terms as $term): ?>
                            <option value="<?php echo $term['term_id']; ?>" 
                                <?php if($term['term_id'] == $settings['current_term_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($term['term_name']); ?> 
                                <?php if($term['is_active']) echo '(Currently Active)'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-save">
                    <i class='bx bx-save'></i> Apply System Changes
                </button>
            </form>
        </div>
    </div>
    <div style="height: 60px;"></div>
</div>

</body>
</html>