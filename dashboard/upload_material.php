<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $_SESSION['error'] = "You must be logged in as a teacher to upload materials.";
    header("Location: teacher.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: teacher.php#resources");
    exit();
}

// Get form data
$course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$user_id = $_SESSION['user_id'];

// File upload handling
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = "File upload failed with error code " . $_FILES['file']['error'] . ".";
    header("Location: teacher.php#resources");
    exit();
}

$file = $_FILES['file'];
$allowed_types = [
    'application/pdf',
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
    'application/vnd.ms-powerpoint', // .ppt
    'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
    'application/vnd.ms-excel', // .xls
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
    'application/zip',
    'application/x-rar-compressed', // .rar
];
$max_size = 10 * 1024 * 1024; // 10MB limit (adjust as needed)

// Validate input and file
if (!$course_id || empty($title)) {
    $_SESSION['error'] = "Course and Title are required.";
    header("Location: teacher.php#resources");
    exit();
}

if (!in_array($file['type'], $allowed_types)) {
    $_SESSION['error'] = "Invalid file type.";
    header("Location: teacher.php#resources");
    exit();
}

if ($file['size'] > $max_size) {
    $_SESSION['error'] = "File size exceeds the limit (10MB).";
    header("Location: teacher.php#resources");
    exit();
}

// Directory to save files
$upload_dir = '../uploads/materials/' . $course_id . '/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename to prevent overwriting
$original_filename = basename($file['name']);
$file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
$new_filename = uniqid() . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// Move the uploaded file
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    // File uploaded successfully, now save info to database
    $file_path_db = 'uploads/materials/' . $course_id . '/' . $new_filename; // Path relative to site root
    $file_type = $file['type'];
    
    $stmt = $conn->prepare("INSERT INTO materials (course_id, title, description, file_path, file_name, file_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssi", $course_id, $title, $description, $file_path_db, $original_filename, $file_type, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Материал амжилттай орууллаа.";
    } else {
        $_SESSION['error'] = "Мэдээллийн санд хадгалахад алдаа гарлаа: " . $conn->error;
        // Optionally delete the uploaded file if DB save fails
        unlink($upload_path);
    }
} else {
    $_SESSION['error'] = "Файл хадгалахад алдаа гарлаа.";
}

$conn->close();
header("Location: teacher.php#resources");
exit();
?> 