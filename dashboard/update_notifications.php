<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$response = ['success' => false, 'message' => 'An error occurred.'];

if (!isLoggedIn()) {
    http_response_code(401);
    $response['message'] = 'Unauthorized';
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    // Get notification settings from POST data
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $email_assignments = isset($_POST['email_assignments']) ? 1 : 0;
    $email_messages = isset($_POST['email_messages']) ? 1 : 0;
    $system_notifications = isset($_POST['system_notifications']) ? 1 : 0;
    $notification_assignments = isset($_POST['notification_assignments']) ? 1 : 0;
    $notification_messages = isset($_POST['notification_messages']) ? 1 : 0;
    $notification_announcements = isset($_POST['notification_announcements']) ? 1 : 0;

    // Update notification settings
    $stmt = $conn->prepare("UPDATE user_settings SET 
        email_notifications = ?,
        email_assignments = ?,
        email_messages = ?,
        system_notifications = ?,
        notification_assignments = ?,
        notification_messages = ?,
        notification_announcements = ?
        WHERE user_id = ?");
    
    $stmt->bind_param("iiiiiiii", 
        $email_notifications,
        $email_assignments,
        $email_messages,
        $system_notifications,
        $notification_assignments,
        $notification_messages,
        $notification_announcements,
        $user_id
    );

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Notification settings updated successfully.';
        http_response_code(200);
    } else {
        http_response_code(500);
        $response['message'] = 'Failed to update notification settings: ' . $conn->error;
    }

    $stmt->close();
} else {
    http_response_code(405);
    $response['message'] = 'Method not allowed.';
}

echo json_encode($response);
?> 