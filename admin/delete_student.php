<?php
// admin/delete_student.php
session_start();
require '../config/db.php';

// Security: Only Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized");
}

if (isset($_GET['id'])) {
    $student_id = $_GET['id'];
    
    try {
        // Because of Foreign Keys (ON DELETE CASCADE), 
        // deleting the user will automatically delete the student record and marks.
        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :id");
        $stmt->execute(['id' => $student_id]);
        
        header("Location: students.php?msg=deleted");
    } catch (PDOException $e) {
        die("Error deleting student: " . $e->getMessage());
    }
}
?>