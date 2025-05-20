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

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

// Get form data
$course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
$date = $_POST['date'] ?? '';
$statuses = $_POST['status'] ?? [];
$notes = $_POST['notes'] ?? [];

// Validate input
if (!$course_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Хичээл сонгоно уу']);
    exit();
}

if (empty($date)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Огноо сонгоно уу']);
    exit();
}

if (empty($statuses)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Дор хаяж нэг сурагчийн ирцийг тэмдэглэнэ үү']);
    exit();
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Огноо буруу форматтай байна']);
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if course belongs to teacher
    $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        throw new Exception("Хичээл олдсонгүй эсвэл эрх байхгүй байна");
    }

    // Insert attendance records
    $stmt = $conn->prepare("
        INSERT INTO attendance (course_id, student_id, date, status, note, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($statuses as $student_id => $status) {
        // Validate status
        if (!in_array($status, ['present', 'absent', 'late', 'excused'])) {
            throw new Exception("Сурагчийн ирцийн төлөв буруу байна: $student_id");
        }

        // Check if student is enrolled in the course
        $check_stmt = $conn->prepare("
            SELECT 1 FROM course_enrollments
            WHERE course_id = ? AND student_id = ?
        ");
        $check_stmt->bind_param("ii", $course_id, $student_id);
        $check_stmt->execute();
        if (!$check_stmt->get_result()->fetch_assoc()) {
            throw new Exception("Сурагч энэ хичээлд бүртгэлтэй биш байна: $student_id");
        }

        // Insert attendance record
        $note = isset($notes[$student_id]) ? htmlspecialchars($notes[$student_id], ENT_QUOTES, 'UTF-8') : '';
        $stmt->bind_param("iisssi", $course_id, $student_id, $date, $status, $note, $_SESSION['user_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Сурагчийн ирцийн бүртгэл хадгалахад алдаа гарлаа: $student_id");
        }
    }

    // Commit transaction
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?> 