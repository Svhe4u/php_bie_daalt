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
    
    // Get preferences from POST data
    $language = $_POST['language'] ?? 'mn';
    $timezone = $_POST['timezone'] ?? 'Asia/Ulaanbaatar';
    $date_format = $_POST['date_format'] ?? 'Y-m-d';
    $theme = $_POST['theme'] ?? 'light';
    $font_size = $_POST['font_size'] ?? 'medium';

    // Validate inputs
    $valid_languages = ['mn', 'en'];
    $valid_themes = ['light', 'dark', 'system'];
    $valid_font_sizes = ['small', 'medium', 'large'];
    $valid_date_formats = ['Y-m-d', 'd/m/Y'];

    if (!in_array($language, $valid_languages) ||
        !in_array($theme, $valid_themes) ||
        !in_array($font_size, $valid_font_sizes) ||
        !in_array($date_format, $valid_date_formats)) {
        http_response_code(400);
        $response['message'] = 'Invalid preference values.';
        echo json_encode($response);
        exit();
    }

    // Update preferences
    $stmt = $conn->prepare("UPDATE user_settings SET 
        language = ?,
        timezone = ?,
        date_format = ?,
        theme = ?,
        font_size = ?
        WHERE user_id = ?");
    
    $stmt->bind_param("sssssi", 
        $language,
        $timezone,
        $date_format,
        $theme,
        $font_size,
        $user_id
    );

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Preferences updated successfully.';
        
        // Update session data
        $_SESSION['language'] = $language;
        $_SESSION['timezone'] = $timezone;
        $_SESSION['theme'] = $theme;
        
        http_response_code(200);
    } else {
        http_response_code(500);
        $response['message'] = 'Failed to update preferences: ' . $conn->error;
    }

    $stmt->close();
} else {
    http_response_code(405);
    $response['message'] = 'Method not allowed.';
}

echo json_encode($response);
?> 