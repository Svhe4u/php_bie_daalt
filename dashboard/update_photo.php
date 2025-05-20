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
    
    // Check if file was uploaded
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        $response['message'] = 'No file uploaded or upload error occurred.';
        echo json_encode($response);
        exit();
    }

    $file = $_FILES['profile_picture'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        http_response_code(400);
        $response['message'] = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
        echo json_encode($response);
        exit();
    }

    // Validate file size (2MB max)
    $max_size = 2 * 1024 * 1024; // 2MB in bytes
    if ($file['size'] > $max_size) {
        http_response_code(400);
        $response['message'] = 'File size too large. Maximum size is 2MB.';
        echo json_encode($response);
        exit();
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/profile_pictures/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('profile_') . '.' . $extension;
    $target_path = $upload_dir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        // Update database with new profile picture path
        $relative_path = 'uploads/profile_pictures/' . $filename;
        $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $stmt->bind_param("si", $relative_path, $user_id);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Profile picture updated successfully.';
            $response['new_photo_url'] = $relative_path;
            http_response_code(200);
        } else {
            http_response_code(500);
            $response['message'] = 'Failed to update profile picture in database: ' . $conn->error;
            // Clean up uploaded file if database update fails
            unlink($target_path);
        }

        $stmt->close();
    } else {
        http_response_code(500);
        $response['message'] = 'Failed to save uploaded file.';
    }
} else {
    http_response_code(405);
    $response['message'] = 'Method not allowed.';
}

echo json_encode($response);
?> 