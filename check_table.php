<?php
require_once 'db.php';

$result = $conn->query("SHOW TABLES LIKE 'grades'");
if ($result->num_rows > 0) {
    echo "Grades table exists\n";
    
    // Show table structure
    $result = $conn->query("DESCRIBE grades");
    echo "\nTable structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Grades table does not exist\n";
}

$conn->close();
?> 