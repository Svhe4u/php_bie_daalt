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
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone_number = $_POST['phone'] ?? '';
    $description = $_POST['description'] ?? '';

    // Basic validation
    if (empty($name) || empty($email)) {
        http_response_code(400);
        $response['message'] = 'Name and email are required.';
        echo json_encode($response);
        exit();
    }

    // Update user data
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone_number = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $name, $email, $phone_number, $description, $user_id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Profile updated successfully.';
        // Update session data if email changed
        if ($_SESSION['email'] !== $email) {
            $_SESSION['email'] = $email;
        }
        // Update session data if name changed
        if ($_SESSION['username'] !== $name) {
            $_SESSION['username'] = $name;
        }
        
        http_response_code(200);
    } else {
        http_response_code(500);
        $response['message'] = 'Failed to update profile: ' . $conn->error;
    }

    $stmt->close();
} else {
    http_response_code(405);
    $response['message'] = 'Method not allowed.';
}

echo json_encode($response);
?> 