<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Нэвтрэх шаардлагатай.";
    header("Location: ../login.php");
    exit();
}

// Check if form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Буруу хүсэлт.";
    header("Location: teacher.php"); // Redirect back to settings or dashboard
    exit();
}

$user_id = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Basic validation
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    $_SESSION['error'] = "Бүх талбарыг бөглөнө үү.";
    header("Location: teacher.php#settings");
    exit();
}

if ($new_password !== $confirm_password) {
    $_SESSION['error'] = "Шинэ нууц үг таарахгүй байна.";
    header("Location: teacher.php#settings");
    exit();
}

// Fetch current user's password hash
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($current_password, $user['password'])) {
    $_SESSION['error'] = "Одоогийн нууц үг буруу байна.";
    header("Location: teacher.php#settings");
    exit();
}

// Hash the new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update the password in the database
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt->bind_param("si", $hashed_password, $user_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Нууц үг амжилттай солигдлоо.";
} else {
    $_SESSION['error'] = "Нууц үг солиход алдаа гарлаа: " . $conn->error;
}

header("Location: teacher.php#settings");
exit();
?> 