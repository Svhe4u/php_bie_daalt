USE student_feedback;

-- First, drop the existing announcements table if it exists
DROP TABLE IF EXISTS announcements;

-- Recreate the announcements table with the correct structure
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author_id INT NOT NULL,
    target_role ENUM('student', 'teacher', 'all') NOT NULL DEFAULT 'all',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert sample announcements
INSERT INTO announcements (course_id, title, content, author_id, target_role) VALUES
(1, 'PHP хичээлийн анхны мэдэгдэл', 'Энэ долоо хоногт PHP хичээлийн анхны хичээл болно. Бэлтгэлээ хийгээрэй.', 2, 'all'),
(2, 'MySQL хичээлийн мэдэгдэл', 'MySQL хичээлийн дараагийн хичээл дээр практик дасгал хийх болно.', 2, 'all'),
(3, 'HTML/CSS хичээлийн мэдэгдэл', 'HTML/CSS хичээлийн дараагийн хичээл дээр responsive design-ийн талаар судлах болно.', 3, 'all'),
(4, 'JavaScript хичээлийн мэдэгдэл', 'JavaScript хичээлийн дараагийн хичээл дээр DOM manipulation-ийн талаар судлах болно.', 3, 'all'); 