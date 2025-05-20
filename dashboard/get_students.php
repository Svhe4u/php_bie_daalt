<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db.php';

// Set proper JSON header
header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    error_log("Unauthorized access attempt to get_students.php. User ID: " . ($_SESSION['user_id'] ?? 'N/A') . ", Role: " . ($_SESSION['role'] ?? 'N/A'));
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get course ID from GET request
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);

// Log the received course ID
error_log("Received course_id in get_students.php: " . ($course_id ?? 'NULL'));

// Validate course ID
if (!$course_id) {
    error_log("Invalid or missing course_id in get_students.php.");
    echo json_encode(['error' => 'Invalid course ID']);
    exit();
}

try {
    // Check if the course belongs to the logged-in teacher
    $check_stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
    if (!$check_stmt) {
        throw new Exception("Failed to prepare course check statement: " . $conn->error);
    }
    
    $check_stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
    $check_stmt->execute();
    if (!$check_stmt->get_result()->fetch_assoc()) {
        error_log("Attempted to access students for a course not owned by the teacher. Course ID: " . $course_id . ", Teacher ID: " . $_SESSION['user_id']);
        echo json_encode(['error' => 'Course not found or unauthorized']);
        exit();
    }

    // Prepare and execute the query to get students enrolled in the course
    $stmt = $conn->prepare("
        SELECT u.id, u.name
        FROM users u
        JOIN course_enrollments ce ON u.id = ce.student_id
        WHERE ce.course_id = ?
        ORDER BY u.name
    ");
    
    if (!$stmt) {
        throw new Exception("Failed to prepare students query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);
    
    // Log the number of students found
    error_log("Found " . count($students) . " students for course ID: " . $course_id);

    // Return students as JSON
    echo json_encode($students);

} catch (Exception $e) {
    error_log("Database error fetching students for attendance: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch students: ' . $e->getMessage()]);
} finally {
    // Close statements if they exist
    if (isset($check_stmt)) {
        $check_stmt->close();
    }
    if (isset($stmt)) {
        $stmt->close();
    }
    // Close connection if it's open
    if (isset($conn)) {
        $conn->close();
    }
}
?> 