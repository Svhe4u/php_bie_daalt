<?php
require_once 'db.php';

try {
    // Check if profile_picture column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    
    if ($result->num_rows == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL";
        
        if ($conn->query($sql)) {
            echo "Successfully added profile_picture column to users table";
        } else {
            echo "Error adding profile_picture column: " . $conn->error;
        }
    } else {
        echo "profile_picture column already exists";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?> 