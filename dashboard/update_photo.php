<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = "Файл оруулахад алдаа гарлаа.";
    header("Location: teacher.php");
    exit();
}

$file = $_FILES['profile_image'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$max_size = 2 * 1024 * 1024; // 2MB

// Validate file type
if (!in_array($file['type'], $allowed_types)) {
    $_SESSION['error'] = "Зөвшөөрөгдөөгүй файлын төрөл байна. Зөвшөөрөгдсөн төрлүүд: JPG, PNG, GIF";
    header("Location: teacher.php");
    exit();
}

// Validate file size
if ($file['size'] > $max_size) {
    $_SESSION['error'] = "Файлын хэмжээ хэт их байна. Хамгийн их хэмжээ: 2MB";
    header("Location: teacher.php");
    exit();
}

// Create uploads directory if it doesn't exist
$upload_dir = '../uploads/profile_images/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_filename = uniqid('profile_') . '.' . $file_extension;
$upload_path = $upload_dir . $new_filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    // Update database
    $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
    $profile_picture_path = 'uploads/profile_images/' . $new_filename;
    $stmt->bind_param("si", $profile_picture_path, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Профайл зураг амжилттай шинэчлэгдлээ.";
    } else {
        $_SESSION['error'] = "Мэдээллийн сангийн алдаа гарлаа.";
        // Delete uploaded file if database update fails
        unlink($upload_path);
    }
} else {
    $_SESSION['error'] = "Файл хадгалахад алдаа гарлаа.";
}

header("Location: teacher.php");
exit();
?> 