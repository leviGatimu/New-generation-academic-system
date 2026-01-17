<?php
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../index.php"); exit;
}

$current_user_id = $_SESSION['user_id'];
$target_id = $_GET['user_id'] ?? $current_user_id; // Default to self

// If viewing self, go to edit page
if ($target_id == $current_user_id) {
    header("Location: profile.php"); exit;
}

// ... Rest of the code ...
$error_msg = "";
$user = null;
$details = [];
$initials = "?";
$bg_color = "#333";

try {
    // 3. Fetch User Info
    $stmt = $pdo->prepare("SELECT user_id, full_name, email, role, profile_pic, bio, phone_number, created_at FROM users WHERE user_id = ?");
    $stmt->execute([$target_id]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User not found.");
    }

    // 4. Generate Initials & Color
    $name = trim($user['full_name']);
    $initials = strtoupper(substr($name, 0, 1));
    $parts = explode(' ', $name);
    if (count($parts) > 1) {
        $initials .= strtoupper(substr(end($parts), 0, 1));
    }

    // Generate Color from Name
    $hash = md5($user['full_name']);
    $bg_color = '#' . substr($hash, 0, 6);

    // 5. Fetch Role-Specific Details
    if ($user['role'] == 'teacher') {
        $t_stmt = $pdo->prepare("SELECT DISTINCT s.subject_name, c.class_name 
                                 FROM teacher_allocations ta
                                 JOIN subjects s ON ta.subject_id = s.subject_id
                                 JOIN classes c ON ta.class_id = c.class_id
                                 WHERE ta.teacher_id = ?");
        $t_stmt->execute([$target_id]);
        $details = $t_stmt->fetchAll();
        $role_display = "Teacher";
        
    } elseif ($user['role'] == 'student') {
        $s_stmt = $pdo->prepare("SELECT s.class_role, c.class_name 
                                 FROM students s 
                                 JOIN classes c ON s.class_id = c.class_id 
                                 WHERE s.student_id = ?");
        $s_stmt->execute([$target_id]);
        $student_info = $s_stmt->fetch();
        
        if ($student_info) {
            $role_display = $student_info['class_role'];
            $details[] = ['label' => 'Class', 'value' => $student_info['class_name']];
        } else {
            $role_display = "Student";
        }
    } else {
        $role_display = ucfirst($user['role']);
    }

} catch (Exception $e) {
    $error_msg = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Profile View | NGA</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        :root { --primary: #FF6600; --dark: #212b36; --light-bg: #f4f6f8; --white: #ffffff; }
        body { background: var(--light-bg); font-family: 'Public Sans', sans-serif; margin: 0; padding-top: 80px; }
        
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        
        /* ERROR CARD */
        .error-card { text-align: center; padding: 50px 20px; background: white; border-radius: 16px; border: 1px solid #dfe3e8; }
        .error-icon { font-size: 3rem; color: #ff4d4f; margin-bottom: 15px; }
        .btn-back { display: inline-block; margin-top: 20px; padding: 10px 20px; background: var(--dark); color: white; text-decoration: none; border-radius: 8px; font-weight: bold; }

        /* PROFILE CARD */
        .profile-card { background: white; border-radius: 16px; border: 1px solid #dfe3e8; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .cover-photo { height: 120px; background: linear-gradient(135deg, #FF6600 0%, #ff9e42 100%); }
        .profile-body { padding: 0 30px 30px; text-align: center; position: relative; }
        
        /* AVATAR LOGIC */
        .avatar-container { 
            width: 110px; height: 110px; border-radius: 50%; border: 5px solid white; 
            margin: -60px auto 15px; overflow: hidden; background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center;
        }
        .avatar-img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-initials { font-size: 2.5rem; font-weight: 800; color: white; letter-spacing: -1px; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
        
        h1 { margin: 0; color: var(--dark); font-size: 1.5rem; }
        
        .role-badge { 
            display: inline-block; padding: 4px 12px; border-radius: 20px; 
            font-size: 0.8rem; font-weight: 700; margin-top: 5px; 
            background: #e3f2fd; color: #007bff; text-transform: uppercase;
        }
        
        .bio-section { margin-top: 20px; color: #637381; font-size: 0.95rem; line-height: 1.6; font-style: italic; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 25px; text-align: left; }
        .info-item { background: #f9fafb; padding: 15px; border-radius: 10px; }
        .label { font-size: 0.75rem; color: #919eab; font-weight: 700; text-transform: uppercase; display: block; margin-bottom: 5px; }
        .value { font-size: 0.95rem; color: var(--dark); font-weight: 600; display: flex; align-items: center; gap: 8px; overflow: hidden; text-overflow: ellipsis; }
        
        .btn-chat { display: block; width: 100%; padding: 12px; background: var(--dark); color: white; text-decoration: none; border-radius: 10px; font-weight: bold; margin-top: 25px; transition: 0.2s; }
        .btn-chat:hover { background: var(--primary); }
    </style>
</head>
<body>

<div style="position:fixed; top:0; width:100%; height:60px; background:white; border-bottom:1px solid #dfe3e8; display:flex; align-items:center; padding:0 20px; z-index:100;">
    <a href="javascript:history.back()" style="text-decoration:none; color:#637381; font-weight:bold; display:flex; align-items:center; gap:5px;">
        <i class='bx bx-arrow-back'></i> Back
    </a>
</div>

<div class="container">
    
    <?php if ($error_msg): ?>
        <div class="error-card">
            <i class='bx bxs-error-circle error-icon'></i>
            <h3>Oops!</h3>
            <p><?php echo htmlspecialchars($error_msg); ?></p>
            <a href="dashboard.php" class="btn-back">Go to Dashboard</a>
        </div>
    <?php else: ?>

        <div class="profile-card">
            <div class="cover-photo"></div>
            
            <div class="profile-body">
                <div class="avatar-container">
                    <?php if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.png'): ?>
                        <img src="../assets/uploads/<?php echo htmlspecialchars($user['profile_pic']); ?>" class="avatar-img">
                    <?php else: ?>
                        <div class="avatar-initials" style="background-color: <?php echo $bg_color; ?>;">
                            <?php echo $initials; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                <span class="role-badge"><?php echo $role_display; ?></span>
                
                <?php if(!empty($user['bio'])): ?>
                    <div class="bio-section">"<?php echo htmlspecialchars($user['bio']); ?>"</div>
                <?php endif; ?>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Contact Email</span>
                        <span class="value"><i class='bx bxs-envelope'></i> <?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    
                    <?php if($user['role'] == 'teacher'): ?>
                        <div class="info-item">
                            <span class="label">Teaching Portfolio</span>
                            <div class="value" style="display:block;">
                                <?php if(empty($details)): ?>
                                    <span style="color:#999; font-weight:400;">No classes assigned yet.</span>
                                <?php else: ?>
                                    <?php foreach(array_slice($details, 0, 5) as $d): ?>
                                        <div style="font-size:0.85rem; margin-bottom:2px;">
                                            <i class='bx bxs-book'></i> <?php echo htmlspecialchars($d['subject_name']); ?> 
                                            <span style="color:#637381;">(<?php echo htmlspecialchars($d['class_name']); ?>)</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif($user['role'] == 'student'): ?>
                        <?php foreach($details as $d): ?>
                            <div class="info-item">
                                <span class="label"><?php echo $d['label']; ?></span>
                                <span class="value"><i class='bx bxs-school'></i> <?php echo htmlspecialchars($d['value']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <a href="messages.php?dm_id=<?php echo $user['user_id']; ?>" class="btn-chat">
                    <i class='bx bxs-chat'></i> Send Message
                </a>
            </div>
        </div>

    <?php endif; ?>
</div>

</body>
</html>