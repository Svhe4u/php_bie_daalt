<?php
require_once 'db.php';

// Add file_type column to materials table
$sql = "ALTER TABLE materials 
        ADD COLUMN file_type VARCHAR(50) AFTER file_path";

if ($conn->query($sql)) {
    echo "Materials table updated successfully";
} else {
    echo "Error updating materials table: " . $conn->error;
}

$conn->close();
?> 