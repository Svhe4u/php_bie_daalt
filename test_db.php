<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "student_feedback";

try {
    $conn = new mysqli($host, $user, $pass);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Check if database exists
    $result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
    
    if ($result->num_rows == 0) {
        // Create database if it doesn't exist
        if (!$conn->query("CREATE DATABASE $dbname")) {
            throw new Exception("Error creating database: " . $conn->error);
        }
        echo "Database created successfully<br>";
    } else {
        echo "Database exists<br>";
    }
    
    // Select the database
    if (!$conn->select_db($dbname)) {
        throw new Exception("Error selecting database: " . $conn->error);
    }
    
    echo "Database connection successful!<br>";
    
    // Test if tables exist
    $tables = ['users', 'courses', 'evaluations', 'course_enrollments', 'grades', 'enrollment_requests', 'teacher_settings'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows == 0) {
            echo "Table '$table' does not exist<br>";
        } else {
            echo "Table '$table' exists<br>";
        }
    }
    
    // Drop existing schedule table if it exists
    $conn->query("DROP TABLE IF EXISTS schedule");

    // Create schedule table with new schema
    $sql = "CREATE TABLE IF NOT EXISTS schedule (
        id INT PRIMARY KEY AUTO_INCREMENT,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        location VARCHAR(255),
        type ENUM('lecture', 'exam', 'assignment', 'other') NOT NULL,
        teacher_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql)) {
        echo "Schedule table created successfully<br>";
    } else {
        echo "Error creating schedule table: " . $conn->error . "<br>";
    }

    // Check for existing courses
    $result = $conn->query("SELECT id, name FROM courses LIMIT 1");
    if ($result->num_rows > 0) {
        $course = $result->fetch_assoc();
        $course_id = $course['id'];
        echo "Found course: " . htmlspecialchars($course['name']) . " (ID: $course_id)<br>";

        // Check for existing teachers
        $result = $conn->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
        if ($result->num_rows > 0) {
            $teacher = $result->fetch_assoc();
            $teacher_id = $teacher['id'];
            echo "Found teacher with ID: $teacher_id<br>";

            // Insert a test schedule entry
            $test_sql = "INSERT INTO schedule (course_id, title, description, start_time, end_time, location, type, teacher_id) 
                         VALUES (?, 'Test Schedule', 'Test Description', NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR), 'Room 101', 'lecture', ?)";
            
            $stmt = $conn->prepare($test_sql);
            $stmt->bind_param("ii", $course_id, $teacher_id);
            
            if ($stmt->execute()) {
                echo "Test schedule entry created successfully<br>";
            } else {
                echo "Error creating test schedule entry: " . $stmt->error . "<br>";
            }
        } else {
            echo "No teachers found in the database. Please create a teacher account first.<br>";
        }
    } else {
        echo "No courses found in the database. Please create a course first.<br>";
    }

    echo "Database update completed.<br>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 