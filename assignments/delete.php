<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id'])) {
    $assignment_id = $_POST['assignment_id'];
    $course_id = $_POST['course_id'];
    
    // Get file path before deleting
    $stmt = $conn->prepare("SELECT file_path FROM assignments WHERE id = ? AND course_id = ?");
    $stmt->bind_param("ii", $assignment_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignment = $result->fetch_assoc();
    
    // Delete assignment
    $stmt = $conn->prepare("DELETE FROM assignments WHERE id = ? AND course_id = ?");
    $stmt->bind_param("ii", $assignment_id, $course_id);
    
    if ($stmt->execute()) {
        // Delete file if exists
        if ($assignment && $assignment['file_path']) {
            $file_path = '../' . $assignment['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        $_SESSION['success'] = "Даалгавар амжилттай устгагдлаа.";
    } else {
        $_SESSION['error'] = "Даалгавар устгахад алдаа гарлаа.";
    }
    
    header("Location: ../dashboard/course.php?id=" . $course_id);
    exit();
} else {
    header("Location: ../dashboard/teacher.php");
    exit();
} 