<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Check if course_id is provided
if (!isset($_POST['course_id'])) {
    $_SESSION['error'] = "Хичээлийн мэдээлэл олдсонгүй.";
    header("Location: list.php");
    exit();
}

$course_id = (int)$_POST['course_id'];

// Check if course exists and get teacher information
$stmt = $conn->prepare("
    SELECT c.*, u.name as teacher_name 
    FROM courses c 
    JOIN users u ON c.teacher_id = u.id 
    WHERE c.id = ?
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    $_SESSION['error'] = "Хичээл олдсонгүй.";
    header("Location: list.php");
    exit();
}

// Check if already enrolled
$stmt = $conn->prepare("
    SELECT id FROM course_enrollments 
    WHERE course_id = ? AND student_id = ?
");
$stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $_SESSION['error'] = "Та энэ хичээлд аль хэдийн элссэн байна.";
    header("Location: list.php");
    exit();
}

// Create enrollment
$stmt = $conn->prepare("
    INSERT INTO course_enrollments (course_id, student_id) 
    VALUES (?, ?)
");
$stmt->bind_param("ii", $course_id, $_SESSION['user_id']);

if ($stmt->execute()) {
    $_SESSION['success'] = "Хичээлд амжилттай элслээ.";
} else {
    $_SESSION['error'] = "Хичээлд элсэхэд алдаа гарлаа. Дараа дахин оролдоно уу.";
}

header("Location: list.php");
exit(); 