<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = filter_var($_POST['course_id'], FILTER_VALIDATE_INT);
    $student_id = filter_var($_POST['student_id'], FILTER_VALIDATE_INT);

    if ($course_id && $student_id) {
        // Check if teacher owns this course
        $stmt = $conn->prepare("SELECT teacher_id FROM courses WHERE id = ?");
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $course = $stmt->get_result()->fetch_assoc();

        if ($course && $course['teacher_id'] == $_SESSION['user_id']) {
            // Check if student is already enrolled
            $stmt = $conn->prepare("
                SELECT 1 FROM course_enrollments 
                WHERE course_id = ? AND student_id = ?
            ");
            $stmt->bind_param("ii", $course_id, $student_id);
            $stmt->execute();
            
            if (!$stmt->get_result()->fetch_assoc()) {
                // Enroll student
                $stmt = $conn->prepare("
                    INSERT INTO course_enrollments (course_id, student_id)
                    VALUES (?, ?)
                ");
                $stmt->bind_param("ii", $course_id, $student_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Оюутан амжилттай нэмэгдлээ.";
                } else {
                    $_SESSION['error'] = "Оюутан нэмэхэд алдаа гарлаа.";
                }
            } else {
                $_SESSION['error'] = "Энэ оюутан аль хэдийн элссэн байна.";
            }
        } else {
            $_SESSION['error'] = "Та энэ хичээлийн багш биш байна.";
        }
    } else {
        $_SESSION['error'] = "Хүсэлт буруу байна.";
    }
}

header("Location: ../dashboard/teacher.php");
exit(); 