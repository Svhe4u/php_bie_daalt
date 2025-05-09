<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $_SESSION['user_id'];
    $course_id = filter_var($_POST['course_id'], FILTER_SANITIZE_NUMBER_INT);
    $score = filter_var($_POST['score'], FILTER_SANITIZE_NUMBER_INT);
    $comment = filter_var($_POST['comment'], FILTER_SANITIZE_STRING);
    
    // Validate score
    if ($score < 1 || $score > 5) {
        $_SESSION['error'] = "Invalid score. Please provide a score between 1 and 5.";
        header("Location: ../dashboard/student.php");
        exit();
    }
    
    // Check if student has already evaluated this course
    $stmt = $conn->prepare("SELECT id FROM evaluations WHERE student_id = ? AND course_id = ?");
    $stmt->bind_param("ii", $student_id, $course_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = "You have already evaluated this course.";
        header("Location: ../dashboard/student.php");
        exit();
    }
    
    // Insert evaluation
    $stmt = $conn->prepare("INSERT INTO evaluations (student_id, course_id, score, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $student_id, $course_id, $score, $comment);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Thank you for your feedback!";
    } else {
        $_SESSION['error'] = "Failed to submit evaluation. Please try again.";
    }
    
    header("Location: ../dashboard/student.php");
    exit();
} else {
    header("Location: ../dashboard/student.php");
    exit();
}
?>
