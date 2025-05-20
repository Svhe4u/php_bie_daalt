<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $_SESSION['error'] = "You must be logged in as a teacher to export attendance reports.";
    header("Location: teacher.php");
    exit();
}

// Get parameters
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
$date_range = filter_input(INPUT_GET, 'date_range', FILTER_SANITIZE_STRING);

// Validate input
if (!$course_id || !$date_range) {
    $_SESSION['error'] = "Course ID and date range are required.";
    header("Location: teacher.php#attendance");
    exit();
}

// Parse date range
$dates = explode(' - ', $date_range);
if (count($dates) !== 2) {
    $_SESSION['error'] = "Invalid date range format.";
    header("Location: teacher.php#attendance");
    exit();
}

$start_date = date('Y-m-d', strtotime($dates[0]));
$end_date = date('Y-m-d', strtotime($dates[1]));

try {
    // Get course name
    $stmt = $conn->prepare("SELECT name FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    
    if (!$course) {
        throw new Exception("Course not found or unauthorized.");
    }
    
    // Get attendance report
    $stmt = $conn->prepare("
        SELECT 
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
        GROUP BY u.id, u.name
        ORDER BY u.name
    ");
    
    $stmt->bind_param("ssi", $start_date, $end_date, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $report = $result->fetch_all(MYSQLI_ASSOC);
    
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="attendance_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Output Excel content
    echo "Ирцийн тайлан\n";
    echo "Хичээл: " . $course['name'] . "\n";
    echo "Хугацаа: " . $date_range . "\n\n";
    
    echo "Сурагч\tИрсэн\tТасалсан\tХоцорсон\tЗөвшөөрөлтэй\tНийт хичээл\tИрцийн хувь\n";
    
    foreach ($report as $row) {
        echo implode("\t", [
            $row['student_name'],
            $row['present'],
            $row['absent'],
            $row['late'],
            $row['excused'],
            $row['total_classes'],
            $row['attendance_rate'] . '%'
        ]) . "\n";
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Тайлан экспортлоход алдаа гарлаа: " . $e->getMessage();
    header("Location: teacher.php#attendance");
    exit();
}

$stmt->close();
$conn->close();
?> 