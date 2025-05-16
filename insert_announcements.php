<?php
require_once 'db.php';

$sql = "INSERT INTO announcements (course_id, title, content, author_id, target_role) 
        VALUES 
        (1, 'PHP хичээлийн анхны мэдэгдэл', 'Энэ долоо хоногт PHP хичээлийн анхны хичээл болно. Бэлтгэлээ хийгээрэй.', 2, 'all'),
        (2, 'MySQL хичээлийн мэдэгдэл', 'MySQL хичээлийн дараагийн хичээл дээр практик дасгал хийх болно.', 2, 'all')";

if ($conn->query($sql)) {
    echo "Sample announcements inserted successfully\n";
} else {
    echo "Error inserting announcements: " . $conn->error . "\n";
}

$conn->close();
?> 