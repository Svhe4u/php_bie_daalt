<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = $_POST['course_id'] ?? null;
    $student_id = $_POST['student_id'] ?? null;
    $grade = $_POST['grade'] ?? null;
    $feedback = $_POST['feedback'] ?? '';

    if (!$course_id || !$student_id || $grade === null) {
        $_SESSION['error'] = 'Бүх талбарыг бөглөнө үү.';
        header('Location: teacher.php');
        exit();
    }

    // Verify that the course belongs to the teacher
    $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $_SESSION['error'] = 'Хичээл олдсонгүй.';
        header('Location: teacher.php');
        exit();
    }

    // Update or insert grade
    $stmt = $conn->prepare("
        INSERT INTO grades (course_id, student_id, grade, feedback)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        grade = VALUES(grade),
        feedback = VALUES(feedback)
    ");
    $stmt->bind_param("iids", $course_id, $student_id, $grade, $feedback);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Дүн амжилттай хадгалагдлаа.';
    } else {
        $_SESSION['error'] = 'Дүн хадгалахад алдаа гарлаа.';
    }

    header('Location: teacher.php');
    exit();
} 