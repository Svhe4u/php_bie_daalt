<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get file path from URL
$file_path = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($file_path)) {
    die("File not specified");
}

// Validate file path to prevent directory traversal
$file_path = str_replace(['..', '\\'], '', $file_path);
$full_path = __DIR__ . '/uploads/materials/' . $file_path;

// Check if file exists
if (!file_exists($full_path)) {
    die("File not found");
}

// Get file information from database
$stmt = $conn->prepare("
    SELECT m.*, c.id as course_id 
    FROM materials m
    JOIN courses c ON m.course_id = c.id
    WHERE m.file_path = ?
");
$stmt->bind_param("s", $file_path);
$stmt->execute();
$material = $stmt->get_result()->fetch_assoc();

if (!$material) {
    die("Material not found in database");
}

// Check if user has access to the course
$stmt = $conn->prepare("
    SELECT 1 FROM course_enrollments 
    WHERE course_id = ? AND student_id = ?
");
$stmt->bind_param("ii", $material['course_id'], $_SESSION['user_id']);
$stmt->execute();
$has_access = $stmt->get_result()->num_rows > 0;

if (!$has_access) {
    die("You don't have permission to access this file");
}

// Get file information
$file_name = basename($full_path);
$file_size = filesize($full_path);
$file_type = mime_content_type($full_path);

// Set headers for file download
header('Content-Description: File Transfer');
header('Content-Type: ' . $file_type);
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Output file
readfile($full_path);
exit; 