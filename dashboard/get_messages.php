<?php
// Prevent any output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set JSON header
header('Content-Type: application/json');

try {
    require_once '../db.php';
    require_once '../auth.php';

    // Check if user is logged in
    if (!isLoggedIn()) {
        throw new Exception('Unauthorized');
    }

    // Get folder type from query parameter
    $folder = isset($_GET['folder']) ? $_GET['folder'] : 'inbox';
    $user_id = $_SESSION['user_id'];

    // Validate folder type
    $valid_folders = ['inbox', 'sent', 'announcements'];
    if (!in_array($folder, $valid_folders)) {
        throw new Exception('Invalid folder type');
    }

    // Check if messages table exists
    $result = $conn->query("SHOW TABLES LIKE 'messages'");
    if ($result->num_rows === 0) {
        // Create messages table if it doesn't exist
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS messages (
                id INT PRIMARY KEY AUTO_INCREMENT,
                sender_id INT,
                recipient_id INT,
                course_id INT,
                type ENUM('message', 'announcement') DEFAULT 'message',
                subject VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        
        if (!$conn->query($create_table_sql)) {
            throw new Exception('Failed to create messages table: ' . $conn->error);
        }

        // Add foreign keys after table creation
        $foreign_keys = [
            "ALTER TABLE messages ADD FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL",
            "ALTER TABLE messages ADD FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE SET NULL",
            "ALTER TABLE messages ADD FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL"
        ];

        foreach ($foreign_keys as $fk_sql) {
            try {
                $conn->query($fk_sql);
            } catch (Exception $e) {
                // Log the error but continue - foreign key might already exist
                error_log("Warning: Could not add foreign key: " . $e->getMessage());
            }
        }
    }

    // Prepare the base query
    $base_query = "
        SELECT m.*, 
               u1.name as sender_name,
               u2.name as recipient_name,
               c.name as course_name
        FROM messages m
        LEFT JOIN users u1 ON m.sender_id = u1.id
        LEFT JOIN users u2 ON m.recipient_id = u2.id
        LEFT JOIN courses c ON m.course_id = c.id
    ";

    // Build the query based on folder type
    switch ($folder) {
        case 'inbox':
            $query = $base_query . " WHERE m.recipient_id = ? ORDER BY m.created_at DESC";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Failed to prepare inbox query: ' . $conn->error);
            }
            $stmt->bind_param("i", $user_id);
            break;

        case 'sent':
            $query = $base_query . " WHERE m.sender_id = ? ORDER BY m.created_at DESC";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Failed to prepare sent query: ' . $conn->error);
            }
            $stmt->bind_param("i", $user_id);
            break;

        case 'announcements':
            $query = $base_query . " 
                WHERE m.type = 'announcement'
                AND (m.course_id IN (
                    SELECT course_id 
                    FROM course_enrollments 
                    WHERE student_id = ?
                ) OR m.sender_id = ?)
                ORDER BY m.created_at DESC";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception('Failed to prepare announcements query: ' . $conn->error);
            }
            $stmt->bind_param("ii", $user_id, $user_id);
            break;
    }

    if (!$stmt->execute()) {
        throw new Exception('Database query failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception('Failed to get query result: ' . $stmt->error);
    }

    $messages = $result->fetch_all(MYSQLI_ASSOC);

    // Format dates and add is_read flag
    foreach ($messages as &$message) {
        $message['created_at'] = date('Y-m-d H:i:s', strtotime($message['created_at']));
        $message['is_read'] = (bool)$message['is_read'];
    }

    echo json_encode($messages);

} catch (Exception $e) {
    error_log("Messages Error: " . $e->getMessage());
    http_response_code($e->getMessage() === 'Unauthorized' ? 401 : 500);
    echo json_encode([
        'error' => $e->getMessage(),
        'status' => 'error',
        'details' => 'Check server logs for more information'
    ]);
} 