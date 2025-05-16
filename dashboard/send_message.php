<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiver_id = $_POST['receiver_id'] ?? null;
    $course_id = !empty($_POST['course_id']) ? $_POST['course_id'] : null;
    $content = $_POST['content'] ?? '';

    if (!$receiver_id || !$content) {
        $_SESSION['error'] = 'Бүх талбарыг бөглөнө үү.';
        header('Location: messages.php');
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, receiver_id, course_id, content)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiis", $_SESSION['user_id'], $receiver_id, $course_id, $content);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Мессэж амжилттай илгээгдлээ.';
    } else {
        $_SESSION['error'] = 'Мессэж илгээхэд алдаа гарлаа.';
    }

    header('Location: messages.php');
    exit();
} 