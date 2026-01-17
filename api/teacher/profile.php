<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../index.php"); exit;
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = "";

// --- 1. HANDLE PROFILE UPDATES ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone_number']);
    $bio = trim($_POST['bio']);
    $email = trim($_POST['email']);
    
    // Image Upload Logic
    $profile_pic = $_POST['current_pic']; // Default to existing
    if (!empty($_FILES['profile_pic']['name'])) {
        $target_dir = "../assets/uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true); // Create folder if not exists
        
        $file_name = time() . "_" . basename($_FILES["profile_pic"]["name"]);
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            $profile_pic = $file_name;
        } else {
            $msg = "Error uploading image."; $msg_type = "error";
        }
    }

    // Update DB
    if (empty($msg)) {
        $stmt = $pdo->prepare("UPDATE users SET full_name=?, phone_number=?, email=?, bio=?, profile_pic=? WHERE user_id=?");
        if ($stmt->execute([$full_name, $phone, $email, $bio, $profile_pic, $user_id])) {
            $msg = "Profile updated successfully!"; $msg_type = "success";
            // Update Session Name immediately
            $_SESSION['full_name'] = $full_name; 
        } else {
            $msg = "Database update failed."; $msg_type = "error";
        }
    }
}

// --- 2. FETCH USER DATA ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();

// --- 3. FETCH AUTOMATIC SYSTEM ROLES (Classes & Subjects) ---
$alloc_stmt = $pdo->prepare("SELECT c.class_name, s.subject_name 
                             FROM teacher_allocations ta
                             JOIN classes c ON ta.class_id = c.class_id
                             JOIN subjects s ON ta.subject_id = s.subject_id
                             WHERE ta.teacher_id = ?
                             ORDER BY c.class_name");
$alloc_stmt->execute([$user_id]);
$my_classes = $alloc_stmt->fetchAll();

// Group subjects by class for cleaner display
$portfolio = [];
foreach($my_classes as $row) {
    $portfolio[$row['class_name']][] = $row['subject_name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Profile | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #fff; --border: #dfe3e8; }
        body { background: var(--light-bg); font-family: 'Public Sans', sans-serif; margin: 0; padding-bottom: 50px; }

        /* Navbar */
        .top-navbar { position: fixed; top: 0; width: 100%; height: 70px; background: white; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; padding: 0 40px; z-index: 1000; box-sizing: border-box; }
        .nav-brand { font-weight: 800; font-size: 1.2rem; display: flex; align-items: center; gap: 10px; color: var(--dark); text-decoration: none; }
        .nav-menu { display: flex; gap: 10px; }
        .nav-item { color: #637381; text-decoration: none; font-weight: 600; padding: 8px 15px; border-radius: 6px; transition: 0.2s; display: flex; align-items: center; gap: 5px; }
        .nav-item:hover, .nav-item.active { color: var(--primary); background: rgba(255, 102, 0, 0.05); }
        .btn-logout { color: #ff4d4f; border: 1px solid #ff4d4f; padding: 6px 15px; border-radius: 6px; text-decoration: none; font-weight: bold; }

        /* Layout */
        .container { max-width: 1100px; margin: 100px auto 0; padding: 0 20px; display: grid; grid-template-columns: 350px 1fr; gap: 30px; }
        
        /* Cards */
        .card { background: white; border-radius: 16px; border: 1px solid var(--border); padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); }
        
        /* Profile Card (Left) */
        .profile-header { text-align: center; margin-bottom: 20px; }
        .profile-img-container { 
            width: 120px; height: 120px; margin: 0 auto 15px; position: relative; 
            border-radius: 50%; border: 4px solid white; box-shadow: 0 5px 15px rgba(0,0,0,0.1); 
            overflow: hidden; background: #eee;
        }
        .profile-img { width: 100%; height: 100%; object-fit: cover; }
        
        .role-badge { background: #e3f2fd; color: #007bff; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; display: inline-block; margin-top: 5px; }
        
        .info-list { margin-top: 30px; }
        .info-item { display: flex; align-items: center; gap: 15px; padding: 15px 0; border-bottom: 1px solid #f4f6f8; font-size: 0.95rem; color: #454f5b; }
        .info-item i { font-size: 1.3rem; color: var(--primary); width: 20px; text-align: center; }
        .info-item:last-child { border-bottom: none; }

        /* Edit Form (Right) */
        h2 { margin: 0 0 20px 0; color: var(--dark); font-size: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 700; color: #637381; font-size: 0.85rem; }
        .form-control { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.95rem; box-sizing: border-box; transition: 0.2s; }
        .form-control:focus { border-color: var(--primary); outline: none; }
        
        textarea.form-control { resize: vertical; min-height: 100px; }

        .btn-save { background: var(--dark); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 1rem; transition: 0.2s; }
        .btn-save:hover { background: var(--primary); transform: translateY(-2px); }

        /* System Roles Section */
        .system-roles { margin-top: 30px; padding-top: 30px; border-top: 1px dashed var(--border); }
        .class-tag { display: inline-flex; align-items: center; background: #fff7e6; color: #b78103; padding: 8px 15px; border-radius: 8px; margin: 0 10px 10px 0; border: 1px solid #ffe7ba; font-size: 0.9rem; font-weight: 600; }
        .class-tag i { margin-right: 6px; }

        /* Alerts */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert.success { background: #e6f7ed; color: #00ab55; border: 1px solid #b7eb8f; }
        .alert.error { background: #fff1f0; color: #ff4d4f; border: 1px solid #ffa39e; }
    </style>
</head>
<body>

<nav class="top-navbar">
    <a href="dashboard.php" class="nav-brand">
        <div class="logo-box" >
            <img src="../assets/images/logo.png" alt="NGA" width="40px">
        </div>
        <span class="nav-brand-text">Teacher Portal</span>
    </a>

    <div class="nav-menu">
        <a href="dashboard.php" class="nav-item ">
            <i class='bx bxs-dashboard'></i> <span>Dashboard</span>
        </a>
        <a href="my_students.php" class="nav-item">
            <i class='bx bxs-user-detail'></i> <span>Students</span>
        </a>
        <a href="assessments.php" class="nav-item"> <i class='bx bxs-layer'></i> <span>Assessments</span>
        </a>
        <a href="view_all_marks.php" class="nav-item">
            <i class='bx bxs-edit'></i> <span>Grading</span>
        </a>
        <a href="messages.php" class="nav-item">
            <i class='bx bxs-chat'></i> <span>Chat</span>
        </a>
        
        <a href="take_attendance.php" class="nav-item">
            <i class='bx bxs-file-doc'></i> <span>Attendance</span>
        </a>
        <a href="profile.php" class="nav-item active">
    <i class='bx bxs-user-circle'></i> <span>Profile</span>
</a>
    </div>

    <div class="nav-user">
        <a href="../logout.php" class="btn-logout">Logout</a>
    </div>
</nav>

<div class="container">
    
    <div class="card">
        <div class="profile-header">
            <div class="profile-img-container">
                <?php 
                    $pic = !empty($me['profile_pic']) && $me['profile_pic'] !== 'default.png' 
                           ? "../assets/uploads/" . $me['profile_pic'] 
                           : "../assets/images/default.png"; 
                ?>
                <img src="<?php echo htmlspecialchars($pic); ?>" alt="Profile" class="profile-img">
            </div>
            <h2 style="justify-content: center; margin-bottom: 5px;"><?php echo htmlspecialchars($me['full_name']); ?></h2>
            <span class="role-badge">Teacher</span>
        </div>

        <div class="info-list">
            <div class="info-item">
                <i class='bx bxs-envelope'></i> <?php echo htmlspecialchars($me['email']); ?>
            </div>
            <div class="info-item">
                <i class='bx bxs-phone'></i> <?php echo htmlspecialchars($me['phone_number'] ?: 'No phone added'); ?>
            </div>
            <div class="info-item">
                <i class='bx bxs-calendar'></i> Joined <?php echo date("M Y", strtotime($me['created_at'])); ?>
            </div>
        </div>

        <div class="system-roles">
            <h4 style="margin:0 0 15px 0; color:#637381; text-transform:uppercase; font-size:0.75rem;">Assigned Classes</h4>
            <?php if(empty($portfolio)): ?>
                <p style="color:#999; font-style:italic; font-size:0.9rem;">No classes assigned yet.</p>
            <?php else: ?>
                <?php foreach($portfolio as $class => $subjects): ?>
                    <div class="class-tag" title="<?php echo implode(', ', $subjects); ?>">
                        <i class='bx bxs-graduation'></i> <?php echo htmlspecialchars($class); ?>
                        <span style="font-weight:400; font-size:0.8rem; margin-left:5px; opacity:0.8;">
                            (<?php echo count($subjects); ?> Subs)
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h2><i class='bx bx-edit-alt'></i> Edit Profile</h2>
        
        <?php if($msg): ?>
            <div class="alert <?php echo $msg_type; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="current_pic" value="<?php echo $me['profile_pic']; ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($me['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone_number" class="form-control" value="<?php echo htmlspecialchars($me['phone_number']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Email Address (Login)</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($me['email']); ?>" required>
            </div>

            <div class="form-group">
                <label>Bio / Description</label>
                <textarea name="bio" class="form-control" placeholder="Tell students and parents a bit about yourself..."><?php echo htmlspecialchars($me['bio']); ?></textarea>
            </div>

            <div class="form-group">
                <label>Profile Picture</label>
                <input type="file" name="profile_pic" class="form-control" accept="image/*">
                <small style="color:#999;">Leave empty to keep current picture.</small>
            </div>

            <div style="text-align:right; margin-top:20px;">
                <button type="submit" name="update_profile" class="btn-save">
                    <i class='bx bxs-save'></i> Save Changes
                </button>
            </div>
        </form>
    </div>

</div>

</body>
</html>