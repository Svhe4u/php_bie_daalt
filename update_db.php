<?php
require_once 'db.php';

// Add new columns to assignments table
$sql = "ALTER TABLE assignments 
        ADD COLUMN file_path VARCHAR(255) AFTER due_date,
        ADD COLUMN max_score INT DEFAULT 100 AFTER file_path,
        ADD COLUMN allow_late TINYINT(1) DEFAULT 1 AFTER max_score,
        ADD COLUMN created_by INT NOT NULL AFTER allow_late,
        ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE";

if ($conn->query($sql)) {
    echo "Database structure updated successfully";
} else {
    echo "Error updating database structure: " . $conn->error;
}

$conn->close();
?> 