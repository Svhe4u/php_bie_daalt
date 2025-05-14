<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = filter_var($_POST['request_id'], FILTER_VALIDATE_INT);
    $action = $_POST['action'];

    if (!$request_id || !in_array($action, ['approve', 'reject'])) {
        $_SESSION['error'] = "Хүсэлт буруу байна.";
        header("Location: ../dashboard/teacher.php");
        exit();
    }

    // Get request details and verify teacher owns the course
    $stmt = $conn->prepare("
        SELECT er.*, c.teacher_id 
        FROM enrollment_requests er
        JOIN courses c ON er.course_id = c.id
        WHERE er.id = ? AND er.status = 'pending'
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();

    if (!$request || $request['teacher_id'] != $_SESSION['user_id']) {
        $_SESSION['error'] = "Хүсэлт олдсонгүй эсвэл энэ хичээлийг удирдах эрх байхгүй байна.";
        header("Location: ../dashboard/teacher.php");
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        if ($action === 'approve') {
            // Create enrollment
            $stmt = $conn->prepare("
                INSERT INTO course_enrollments (course_id, student_id) 
                VALUES (?, ?)
            ");
            $stmt->bind_param("ii", $request['course_id'], $request['student_id']);
            $stmt->execute();

            // Update request status
            $stmt = $conn->prepare("
                UPDATE enrollment_requests 
                SET status = 'approved' 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();

            $_SESSION['success'] = "Оюутны хүсэлтийг зөвшөөрлөө.";
        } else {
            // Update request status to rejected
            $stmt = $conn->prepare("
                UPDATE enrollment_requests 
                SET status = 'rejected' 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();

            $_SESSION['success'] = "Оюутны хүсэлтийг татгалзлаа.";
        }

        // Commit transaction
        $conn->commit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = "Хүсэлт боловсруулахад алдаа гарлаа: " . $e->getMessage();
    }
}

header("Location: ../dashboard/teacher.php");
exit();
?> 