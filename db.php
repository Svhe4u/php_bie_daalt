<?php
$host = "localhost";
$user = "root";  // AMMPS default user
$pass = "mysql";      // AMMPS default password is empty
$dbname = "student_feedback";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create tables if they don't exist
$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('student', 'teacher', 'admin') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        teacher_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES users(id)
    )",
    
    "CREATE TABLE IF NOT EXISTS evaluations (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT,
        course_id INT,
        score INT,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (course_id) REFERENCES courses(id)
    )",
    
    "CREATE TABLE IF NOT EXISTS course_enrollments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT,
        course_id INT,
        enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id),
        FOREIGN KEY (course_id) REFERENCES courses(id),
        UNIQUE KEY unique_enrollment (student_id, course_id)
    )"
];

foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        die("Error creating table: " . $conn->error);
    }
}

// Check if default admin exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = 'admin@system.com'");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Create default admin user
    $admin_name = "System Administrator";
    $admin_email = "admin@system.com";
    $admin_password = password_hash("Admin123!", PASSWORD_DEFAULT);
    $admin_role = "admin";
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $admin_name, $admin_email, $admin_password, $admin_role);
    
    if (!$stmt->execute()) {
        die("Error creating default admin: " . $stmt->error);
    }
}
?>
