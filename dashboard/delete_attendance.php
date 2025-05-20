<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $_SESSION['error'] = "You must be logged in as a teacher to delete attendance.";
    header("Location: teacher.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: teacher.php#attendance");
    exit();
}

// Get attendance ID
$attendance_id = filter_input(INPUT_POST, 'attendance_id', FILTER_VALIDATE_INT);

// Validate input
if (!$attendance_id) {
    $_SESSION['error'] = "Attendance ID is required.";
    header("Location: teacher.php#attendance");
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Delete attendance record
    $stmt = $conn->prepare("
        DELETE FROM attendance 
        WHERE id = ? AND EXISTS (
            SELECT 1 FROM courses c 
            WHERE c.id = attendance.course_id 
            AND c.teacher_id = ?
        )
    ");
    
    $stmt->bind_param("ii", $attendance_id, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            $_SESSION['success'] = "Ирцийн бүртгэл амжилттай устгагдлаа.";
        } else {
            throw new Exception("No attendance record was deleted.");
        }
    } else {
        throw new Exception("Failed to delete attendance record.");
    }

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Ирцийн бүртгэл устгахад алдаа гарлаа: " . $e->getMessage();
}

$stmt->close();
$conn->close();

header("Location: teacher.php#attendance");
exit();
?> 