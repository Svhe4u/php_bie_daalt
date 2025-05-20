<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get parameters
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
$date_range = filter_input(INPUT_GET, 'date_range', FILTER_SANITIZE_STRING);

// Validate input
if (!$course_id || !$date_range) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Course ID and date range are required']);
    exit();
}

// Parse date range
$dates = explode(' - ', $date_range);
if (count($dates) !== 2) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid date range format']);
    exit();
}

$start_date = date('Y-m-d', strtotime($dates[0]));
$end_date = date('Y-m-d', strtotime($dates[1]));

try {
    // Get attendance report
    $stmt = $conn->prepare("
        SELECT 
            u.id as student_id,
            u.name as student_name,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent,
            COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late,
            COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused,
            COUNT(*) as total_classes,
            ROUND(
                (COUNT(CASE WHEN a.status IN ('present', 'late') THEN 1 END) * 100.0 / COUNT(*)),
                1
            ) as attendance_rate
        FROM users u
        JOIN course_enrollments ce ON u.id = ce.student_id
        LEFT JOIN attendance a ON u.id = a.student_id 
            AND a.course_id = ce.course_id
            AND a.date BETWEEN ? AND ?
        WHERE ce.course_id = ?
            AND ce.status = 'approved'
        GROUP BY u.id, u.name
        ORDER BY u.name
    ");
    
    $stmt->bind_param("ssi", $start_date, $end_date, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_all(MYSQLI_ASSOC);
    
    // Return report as JSON
    header('Content-Type: application/json');
    echo json_encode($report);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to generate report: ' . $e->getMessage()]);
}

$stmt->close();
$conn->close();
?> 