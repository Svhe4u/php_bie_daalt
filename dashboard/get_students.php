<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode([]); // Return empty array if not authorized
    exit();
}

// Get course ID from GET request
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);

// Validate course ID
if (!$course_id) {
    echo json_encode([]); // Return empty array for invalid course ID
    exit();
}

try {
    // Prepare and execute the query to get students enrolled in the course
    $stmt = $conn->prepare("
        SELECT u.id, u.name
        FROM users u
        JOIN course_enrollments ce ON u.id = ce.student_id
        WHERE ce.course_id = ?
        ORDER BY u.name
    ");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $students = $result->fetch_all(MYSQLI_ASSOC);
    
    // Return students as JSON
    header('Content-Type: application/json');
    echo json_encode($students);

} catch (Exception $e) {
    // Log the error and return an empty array or error indicator
    error_log("Error fetching students for attendance: " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?> 