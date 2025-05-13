<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get course_id from either POST or GET
$course_id = null;
if (isset($_POST['course_id'])) {
    $course_id = filter_var($_POST['course_id'], FILTER_SANITIZE_NUMBER_INT);
} elseif (isset($_GET['id'])) {
    $course_id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
}

// If no course_id provided, redirect back
if (!$course_id) {
    $_SESSION['error'] = "Хичээлийн ID олдсонгүй";
    header("Location: ../dashboard/admin.php");
    exit();
}

// Check if course exists
$stmt = $conn->prepare("SELECT id FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    $_SESSION['error'] = "Хичээл олдсонгүй";
    header("Location: ../dashboard/admin.php");
    exit();
}

// Delete course
$stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Хичээл амжилттай устгагдлаа";
} else {
    $_SESSION['error'] = "Хичээл устгахад алдаа гарлаа";
}

// Redirect back to admin dashboard
header("Location: ../dashboard/admin.php");
exit();
?> 