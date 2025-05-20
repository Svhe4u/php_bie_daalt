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
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        http_response_code(400);
        $response['message'] = 'All password fields are required.';
        echo json_encode($response);
        exit();
    }

    if ($new_password !== $confirm_password) {
        http_response_code(400);
        $response['message'] = 'New passwords do not match.';
        echo json_encode($response);
        exit();
    }

    // Password strength validation
    if (strlen($new_password) < 8 ||
        !preg_match('/[A-Z]/', $new_password) ||
        !preg_match('/[a-z]/', $new_password) ||
        !preg_match('/[0-9]/', $new_password)) {
        http_response_code(400);
        $response['message'] = 'Password must be at least 8 characters long and contain uppercase, lowercase, and numbers.';
        echo json_encode($response);
        exit();
    }

    // Verify current password
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($current_password, $user['password'])) {
        http_response_code(400);
        $response['message'] = 'Current password is incorrect.';
        echo json_encode($response);
        exit();
    }

    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Password updated successfully.';
        http_response_code(200);
    } else {
        http_response_code(500);
        $response['message'] = 'Failed to update password: ' . $conn->error;
    }

    $stmt->close();
} else {
    http_response_code(405);
    $response['message'] = 'Method not allowed.';
}

echo json_encode($response);
?> 