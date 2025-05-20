<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $_SESSION['error'] = "You must be logged in as a teacher to edit attendance.";
    header("Location: teacher.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: teacher.php#attendance");
    exit();
}

// Get form data
$attendance_id = filter_input(INPUT_POST, 'attendance_id', FILTER_VALIDATE_INT);
$status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
$notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);

// Validate input
if (!$attendance_id || !$status) {
    $_SESSION['error'] = "Attendance ID and status are required.";
    header("Location: teacher.php#attendance");
    exit();
}

// Validate status
$allowed_statuses = ['present', 'absent', 'late', 'excused'];
if (!in_array($status, $allowed_statuses)) {
    $_SESSION['error'] = "Invalid attendance status.";
    header("Location: teacher.php#attendance");
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Update attendance record
    $stmt = $conn->prepare("
        UPDATE attendance 
        SET status = ?, note = ?, recorded_by = ?
        WHERE id = ? AND EXISTS (
            SELECT 1 FROM courses c 
            WHERE c.id = attendance.course_id 
            AND c.teacher_id = ?
        )
    ");
    
    $stmt->bind_param("ssiii", $status, $notes, $_SESSION['user_id'], $attendance_id, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            $_SESSION['success'] = "Ирцийн бүртгэл амжилттай шинэчлэгдлээ.";
        } else {
            throw new Exception("No attendance record was updated.");
        }
    } else {
        throw new Exception("Failed to update attendance record.");
    }

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Ирцийн бүртгэл шинэчлэхэд алдаа гарлаа: " . $e->getMessage();
}

$stmt->close();
$conn->close();

header("Location: teacher.php#attendance");
exit();
?> 