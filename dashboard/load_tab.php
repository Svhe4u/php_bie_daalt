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

// Get the requested tab
$tab = $_GET['tab'] ?? '';

// Validate tab name
$allowed_tabs = ['classes', 'students', 'assignments', 'attendance', 'messages', 'resources', 'settings'];
if (!in_array($tab, $allowed_tabs)) {
    die("Invalid tab requested");
}

// Load the appropriate content based on the tab
switch ($tab) {
    case 'classes':
        // Get all courses for this teacher
        $stmt = $conn->prepare("
            SELECT c.*, 
                   COUNT(DISTINCT ce.student_id) as enrolled_students,
                   COUNT(DISTINCT a.id) as total_assignments,
                   COUNT(DISTINCT m.id) as total_materials,
                   (SELECT AVG(score) FROM evaluations WHERE course_id = c.id) as average_score,
                   (SELECT AVG(grade) FROM grades WHERE course_id = c.id) as average_grade
            FROM courses c
            LEFT JOIN course_enrollments ce ON c.id = ce.course_id
            LEFT JOIN assignments a ON c.id = a.course_id
            LEFT JOIN materials m ON c.id = m.course_id
            LEFT JOIN evaluations e ON c.id = e.course_id
            LEFT JOIN grades g ON c.id = g.course_id
            WHERE c.teacher_id = ?
            GROUP BY c.id, c.name, c.teacher_id, c.created_at
            ORDER BY c.created_at DESC
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        include 'tabs/classes.php';
        break;

    case 'students':
        // Get all students enrolled in teacher's courses
        $stmt = $conn->prepare("
            SELECT DISTINCT u.*, 
                   COUNT(DISTINCT c.id) as total_courses,
                   AVG(g.grade) as average_grade
            FROM users u
            JOIN course_enrollments ce ON u.id = ce.student_id
            JOIN courses c ON ce.course_id = c.id
            LEFT JOIN grades g ON u.id = g.student_id AND c.id = g.course_id
            WHERE c.teacher_id = ?
            GROUP BY u.id
            ORDER BY u.name
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        include 'tabs/students.php';
        break;

    case 'assignments':
        // Get all assignments for teacher's courses
        $stmt = $conn->prepare("
            SELECT a.*, c.name as course_name,
                   COUNT(s.id) as total_submissions,
                   COUNT(CASE WHEN s.status = 'pending' THEN 1 END) as pending_submissions
            FROM assignments a
            JOIN courses c ON a.course_id = c.id
            LEFT JOIN assignment_submissions s ON a.id = s.assignment_id
            WHERE c.teacher_id = ?
            GROUP BY a.id
            ORDER BY a.due_date DESC
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        include 'tabs/assignments.php';
        break;

    case 'attendance':
        // Get attendance records for teacher's courses
        $stmt = $conn->prepare("
            SELECT a.*, c.name as course_name, u.name as student_name,
                   DATE(a.date) as attendance_date,
                   a.status,
                   a.note
            FROM attendance a
            JOIN courses c ON a.course_id = c.id
            JOIN users u ON a.student_id = u.id
            WHERE c.teacher_id = ?
            ORDER BY a.date DESC, c.name, u.name
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $attendance_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        include 'tabs/attendance.php';
        break;

    case 'messages':
        // Get unread message count
        $stmt = $conn->prepare("
            SELECT COUNT(*) as unread_count
            FROM messages
            WHERE receiver_id = ? AND is_read = 0
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];

        // Get all messages for the teacher
        $stmt = $conn->prepare("
            SELECT m.*, 
                   u1.name as sender_name,
                   u2.name as recipient_name,
                   c.name as course_name
            FROM messages m
            LEFT JOIN users u1 ON m.sender_id = u1.id
            LEFT JOIN users u2 ON m.receiver_id = u2.id
            LEFT JOIN courses c ON m.course_id = c.id
            WHERE m.receiver_id = ?
            ORDER BY m.created_at DESC
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        include 'tabs/messages.php';
        break;

    case 'resources':
        // Get all materials for teacher's courses
        $stmt = $conn->prepare("
            SELECT m.*, c.name as course_name, u.name as uploaded_by,
                   m.title, m.description, m.file_name, m.file_path,
                   DATE_FORMAT(m.created_at, '%Y-%m-%d %H:%i') as uploaded_at
            FROM materials m
            JOIN courses c ON m.course_id = c.id
            JOIN users u ON m.created_by = u.id
            WHERE c.teacher_id = ?
            ORDER BY m.created_at DESC
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Get courses for the upload form
        $stmt = $conn->prepare("
            SELECT id, name
            FROM courses
            WHERE teacher_id = ?
            ORDER BY name
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        include 'tabs/resources.php';
        break;

    case 'settings':
        // Get teacher's settings and user info
        $stmt = $conn->prepare("
            SELECT ts.*, u.*
            FROM teacher_settings ts
            JOIN users u ON ts.user_id = u.id
            WHERE ts.user_id = ?
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $settings = $result->fetch_assoc();
        $user = $settings; // Use the same data for user info
        
        include 'tabs/settings.php';
        break;
}
?> 