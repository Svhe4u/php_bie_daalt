<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['announcement_id'])) {
    $announcement_id = $_POST['announcement_id'];
    $course_id = $_POST['course_id'];
    
    // Delete announcement
    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ? AND course_id = ?");
    $stmt->bind_param("ii", $announcement_id, $course_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Мэдэгдэл амжилттай устгагдлаа.";
    } else {
        $_SESSION['error'] = "Мэдэгдэл устгахад алдаа гарлаа.";
    }
    
    header("Location: ../dashboard/course.php?id=" . $course_id);
    exit();
} else {
    header("Location: ../dashboard/teacher.php");
    exit();
} 