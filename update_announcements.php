<?php
require_once 'db.php';

// Read the SQL file
$sql = file_get_contents('update_announcements.sql');

// Execute the SQL commands
if ($conn->multi_query($sql)) {
    echo "Announcements table updated successfully\n";
} else {
    echo "Error updating announcements table: " . $conn->error . "\n";
}

$conn->close();
?> 