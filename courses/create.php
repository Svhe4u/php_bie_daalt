<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle course creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $teacher_id = filter_var($_POST['teacher_id'], FILTER_SANITIZE_NUMBER_INT);
    
    $stmt = $conn->prepare("INSERT INTO courses (name, teacher_id) VALUES (?, ?)");
    $stmt->bind_param("si", $name, $teacher_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Хичээл амжилттай нэмэгдлээ";
    } else {
        $_SESSION['error'] = "Хичээл нэмэхэд алдаа гарлаа";
    }
    
    // Redirect back to the admin dashboard
    header("Location: ../dashboard/admin.php");
    exit();
} else {
    // If not POST request, redirect to admin dashboard
    header("Location: ../dashboard/admin.php");
    exit();
}
?> 