<?php
require_once '../db.php';

// Drop the existing messages table
$conn->query("DROP TABLE IF EXISTS messages");

// Create the messages table with correct structure
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

if ($conn->query($create_table_sql)) {
    echo "Messages table has been fixed successfully.";
} else {
    echo "Error fixing messages table: " . $conn->error;
}
?> 