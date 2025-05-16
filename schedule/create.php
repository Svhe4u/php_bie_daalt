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
    $day_of_week = $_POST['day_of_week'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $room = $_POST['room'];
    
    // Validate input
    if (empty($day_of_week) || empty($start_time) || empty($end_time) || empty($room)) {
        $_SESSION['error'] = "Бүх талбарыг бөглөнө үү.";
        header("Location: ../dashboard/course.php?id=" . $course_id);
        exit();
    }
    
    // Insert schedule
    $stmt = $conn->prepare("
        INSERT INTO course_schedule (course_id, day_of_week, start_time, end_time, room, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("issss", $course_id, $day_of_week, $start_time, $end_time, $room);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Хуваарь амжилттай нэмэгдлээ.";
    } else {
        $_SESSION['error'] = "Хуваарь нэмэхэд алдаа гарлаа.";
    }
    
    header("Location: ../dashboard/course.php?id=" . $course_id);
    exit();
} else {
    header("Location: ../dashboard/teacher.php");
    exit();
} 