<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = $_POST['course_id'];
    $title = $_POST['title'];
    $content = $_POST['content'];
    
    // Validate input
    if (empty($title) || empty($content)) {
        $_SESSION['error'] = "Бүх талбарыг бөглөнө үү.";
        header("Location: ../dashboard/course.php?id=" . $course_id);
        exit();
    }
    
    // Insert announcement
    $stmt = $conn->prepare("
        INSERT INTO announcements (course_id, author_id, title, content, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iiss", $course_id, $_SESSION['user_id'], $title, $content);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Зарлал амжилттай нэмэгдлээ.";
    } else {
        $_SESSION['error'] = "Зарлал нэмэхэд алдаа гарлаа.";
    }
    
    header("Location: ../dashboard/course.php?id=" . $course_id);
    exit();
} else {
    header("Location: ../dashboard/teacher.php");
    exit();
} 