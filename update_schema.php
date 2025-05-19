<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start(); // Make sure session is started if needed
require_once 'db.php';

try {
    // Check if phone_number column exists in users table
    $result_phone = $conn->query("SHOW COLUMNS FROM users LIKE 'phone_number'");
    
    if ($result_phone->num_rows == 0) {
        // Add phone_number column to users table
        $sql_phone = "ALTER TABLE users ADD COLUMN phone_number VARCHAR(20) AFTER profile_picture";
        
        if ($conn->query($sql_phone)) {
            echo "Successfully added phone_number column to users table.<br>";
        } else {
            echo "Error adding phone_number column: " . $conn->error . "<br>";
        }
    } else {
        echo "Phone number column already exists in users table.<br>";
    }

    // Check if description column exists in users table
    $result_desc = $conn->query("SHOW COLUMNS FROM users LIKE 'description'");
    
    if ($result_desc->num_rows == 0) {
        // Add description column to users table
        $sql_desc = "ALTER TABLE users ADD COLUMN description TEXT AFTER phone_number";
        
        if ($conn->query($sql_desc)) {
            echo "Successfully added description column to users table.<br>";
        } else {
            echo "Error adding description column: " . $conn->error . "<br>";
        }
    } else {
        echo "Description column already exists in users table.<br>";
    }

    // Check if file_name column exists in materials table
    $result_file = $conn->query("SHOW COLUMNS FROM materials LIKE 'file_name'");
    
    if ($result_file->num_rows == 0) {
        // Add file_name column to materials table
        $sql_file = "ALTER TABLE materials ADD COLUMN file_name VARCHAR(255) AFTER file_path";
        
        if ($conn->query($sql_file)) {
            echo "Successfully added file_name column to materials table.<br>";
        } else {
            echo "Error adding file_name column: " . $conn->error . "<br>";
        }
    } else {
        echo "File name column already exists in materials table.<br>";
    }

} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "<br>";
}

$conn->close();
?> 