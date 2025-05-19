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

// Get notification preferences from POST data
$email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
$assignment_notifications = isset($_POST['assignment_notifications']) ? 1 : 0;
$message_notifications = isset($_POST['message_notifications']) ? 1 : 0;

// You might want to add more specific validation here

// Update notification preferences in the database
// Assuming you have a teacher_settings table with columns like email_notifications, assignment_notifications, message_notifications
// and a user_id column as primary key or foreign key

// First, check if a record for this user exists
$stmt = $conn->prepare("SELECT user_id FROM teacher_settings WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Record exists, update it
    $stmt = $conn->prepare("UPDATE teacher_settings SET notification_preferences = ? WHERE user_id = ?");
    $notification_preferences = json_encode([
        'email' => (bool)$email_notifications,
        'assignment' => (bool)$assignment_notifications,
        'message' => (bool)$message_notifications
    ]);
    $stmt->bind_param("si", $notification_preferences, $user_id);
} else {
    // No record exists, insert a new one
     $stmt = $conn->prepare("INSERT INTO teacher_settings (user_id, notification_preferences) VALUES (?, ?)");
     $notification_preferences = json_encode([
        'email' => (bool)$email_notifications,
        'assignment' => (bool)$assignment_notifications,
        'message' => (bool)$message_notifications
    ]);
     $stmt->bind_param("is", $user_id, $notification_preferences);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Мэдэгдлийн тохиргоо амжилттай шинэчлэгдлээ.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Мэдэгдлийн тохиргоо шинэчлэхэд алдаа гарлаа: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?> 