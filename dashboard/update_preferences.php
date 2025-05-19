<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Нэвтрэх шаардлагатай.']);
    exit();
}

// Check if form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Буруу хүсэлт.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get preferences from POST data
$language = $_POST['language'] ?? '';
$timezone = $_POST['timezone'] ?? '';
$date_format = $_POST['date_format'] ?? '';

// Basic validation (you might want more robust validation)
$allowed_languages = ['mn', 'en'];
$allowed_timezones = ['Asia/Ulaanbaatar', 'UTC'];
$allowed_date_formats = ['Y-m-d', 'd/m/Y'];

if (!in_array($language, $allowed_languages) || !in_array($timezone, $allowed_timezones) || !in_array($date_format, $allowed_date_formats)) {
     echo json_encode(['success' => false, 'message' => 'Буруу оруулга.']);
     exit();
}


// Update preferences in the database
// Assuming you have a teacher_settings table with columns like language, timezone, date_format
// and a user_id column as primary key or foreign key

// First, check if a record for this user exists
$stmt = $conn->prepare("SELECT user_id FROM teacher_settings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Record exists, update it
    $stmt = $conn->prepare("UPDATE teacher_settings SET language = ?, timezone = ?, date_format = ? WHERE user_id = ?");
    $stmt->bind_param("sssi", $language, $timezone, $date_format, $user_id);
} else {
    // No record exists, insert a new one
     $stmt = $conn->prepare("INSERT INTO teacher_settings (user_id, language, timezone, date_format) VALUES (?, ?, ?, ?)");
     $stmt->bind_param("issss", $user_id, $language, $timezone, $date_format);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Тохиргоо амжилттай хадгалагдлаа.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Тохиргоо хадгалахад алдаа гарлаа: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?> 