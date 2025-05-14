<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is an admin or teacher
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher')) {
    header("Location: ../login.php");
    exit();
}

// Check if course_id and student_id are provided
if (!isset($_POST['course_id']) || !isset($_POST['student_id'])) {
    $_SESSION['error'] = "Хичээл эсвэл оюутны мэдээлэл дутуу байна.";
    header("Location: ../dashboard/" . $_SESSION['role'] . ".php");
    exit();
}

$course_id = (int)$_POST['course_id'];
$student_id = (int)$_POST['student_id'];

// Check if course exists
$stmt = $conn->prepare("SELECT id, teacher_id FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    $_SESSION['error'] = "Хичээл олдсонгүй.";
    header("Location: ../dashboard/" . $_SESSION['role'] . ".php");
    exit();
}

// If teacher, verify they own the course
if ($_SESSION['role'] === 'teacher' && $course['teacher_id'] != $_SESSION['user_id']) {
    $_SESSION['error'] = "Энэ хичээлийг удирдах эрх байхгүй байна.";
    header("Location: ../dashboard/teacher.php");
    exit();
}

// Check if student exists and is actually a student
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'student'");
$stmt->bind_param("i", $student_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    $_SESSION['error'] = "Оюутан олдсонгүй.";
    header("Location: ../dashboard/" . $_SESSION['role'] . ".php");
    exit();
}

// Check if already enrolled
$stmt = $conn->prepare("SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ?");
$stmt->bind_param("ii", $course_id, $student_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $_SESSION['error'] = "Энэ оюутан аль хэдийн энэ хичээлд бүртгэгдсэн байна.";
    header("Location: ../dashboard/" . $_SESSION['role'] . ".php");
    exit();
}

// Create enrollment
$stmt = $conn->prepare("INSERT INTO course_enrollments (course_id, student_id) VALUES (?, ?)");
$stmt->bind_param("ii", $course_id, $student_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Оюутан амжилттай бүртгэгдлээ.";
} else {
    $_SESSION['error'] = "Оюутан бүртгэхэд алдаа гарлаа: " . $stmt->error;
}

header("Location: ../dashboard/" . $_SESSION['role'] . ".php");
exit();
?> 