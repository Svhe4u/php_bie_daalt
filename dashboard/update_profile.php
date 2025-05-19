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

// Validate input
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

if (empty($name) || empty($email)) {
    $_SESSION['error'] = "Нэр болон и-мэйл заавал оруулна уу.";
    header("Location: teacher.php");
    exit();
}

// Check if email is already taken by another user
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->bind_param("si", $email, $_SESSION['user_id']);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    $_SESSION['error'] = "Энэ и-мэйл хаяг бүртгэлтэй байна.";
    header("Location: teacher.php");
    exit();
}

// Update user profile
$stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone_number = ? WHERE id = ?");
$stmt->bind_param("sssi", $name, $email, $phone, $_SESSION['user_id']);

if ($stmt->execute()) {
    $_SESSION['success'] = "Профайл амжилттай шинэчлэгдлээ.";
} else {
    $_SESSION['error'] = "Профайл шинэчлэхэд алдаа гарлаа.";
}

header("Location: teacher.php");
exit();
?> 