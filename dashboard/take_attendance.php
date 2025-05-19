<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $_SESSION['error'] = "You must be logged in as a teacher to take attendance.";
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
$course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
$date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
$statuses = $_POST['status'] ?? []; // Array of student_id => status
$notes = $_POST['notes'] ?? []; // Array of student_id => notes
$recorded_by = $_SESSION['user_id'];

// Validate input
if (!$course_id || empty($date) || empty($statuses)) {
    $_SESSION['error'] = "Course, date, and at least one student status are required.";
    header("Location: teacher.php#attendance");
    exit();
}

// Prepare data for insertion
$attendance_records = [];
foreach ($statuses as $student_id => $status) {
    // Basic validation for status (optional, but good practice)
    $allowed_statuses = ['present', 'absent', 'late', 'excused'];
    if (!in_array($status, $allowed_statuses)) {
        // Skip invalid status or handle as error
        continue;
    }
    $attendance_records[] = [
        'course_id' => $course_id,
        'student_id' => $student_id,
        'date' => $date,
        'status' => $status,
        'note' => $notes[$student_id] ?? '',
        'recorded_by' => $recorded_by,
    ];
}

if (empty($attendance_records)) {
     $_SESSION['error'] = "No valid attendance records to save.";
     header("Location: teacher.php#attendance");
     exit();
}

// Start transaction
$conn->begin_transaction();
$success = true;

// Insert attendance records
$stmt = $conn->prepare("INSERT INTO attendance (course_id, student_id, date, status, note, recorded_by) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), note = VALUES(note), recorded_by = VALUES(recorded_by)");

foreach ($attendance_records as $record) {
    $stmt->bind_param("iissis", 
        $record['course_id'],
        $record['student_id'],
        $record['date'],
        $record['status'],
        $record['note'],
        $record['recorded_by']
    );
    if (!$stmt->execute()) {
        $success = false;
        break; // Exit loop on first error
    }
}

if ($success) {
    $conn->commit();
    $_SESSION['success'] = "Ирцийн бүртгэл амжилттай хадгалагдлаа.";
} else {
    $conn->rollback();
    $_SESSION['error'] = "Ирцийн бүртгэл хадгалахад алдаа гарлаа: " . $conn->error;
}

$stmt->close();
$conn->close();

// header("Location: teacher.php#attendance");
// exit();
?> 