-- Create database
CREATE DATABASE IF NOT EXISTS student_feedback DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_feedback;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create courses table
CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    teacher_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create course_enrollments table
CREATE TABLE IF NOT EXISTS course_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (course_id, student_id)
);

-- Create evaluations table
CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    score INT NOT NULL CHECK (score BETWEEN 1 AND 5),
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_evaluation (course_id, student_id)
);

-- Create grades table
CREATE TABLE IF NOT EXISTS grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    grade DECIMAL(5,2) NOT NULL,
    feedback TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_grade (course_id, student_id)
);

-- Create announcements table
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author_id INT NOT NULL,
    target_role ENUM('student', 'teacher', 'all') NOT NULL DEFAULT 'all',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create assignments table (for course assignments)
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    due_date TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Create assignment_submissions table (for student submissions)
CREATE TABLE IF NOT EXISTS assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_path VARCHAR(255) NOT NULL,
    status ENUM('pending', 'submitted', 'graded') NOT NULL DEFAULT 'pending',
    grade DECIMAL(5,2) DEFAULT NULL,
    feedback TEXT,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (assignment_id, student_id)
);

-- Create attendance table (for tracking student attendance)
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    date TIMESTAMP NOT NULL,
    status ENUM('present', 'absent', 'late') NOT NULL,
    note TEXT,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (course_id, student_id, date)
);

-- Create materials table (for lecture notes, slides, etc.)
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Create schedule table (for class schedule, lectures, exams, meetings)
CREATE TABLE IF NOT EXISTS schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    type ENUM('lecture', 'exam', 'meeting') NOT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Create messages table (for communication between teachers and students)
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create message_replies table (for replies to messages)
CREATE TABLE IF NOT EXISTS message_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    sender_id INT NOT NULL,
    body TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create discussions table (for discussion board topics)
CREATE TABLE IF NOT EXISTS discussions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    author_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create feedback table (for feedback forms or surveys)
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    feedback TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (course_id, student_id)
);

-- Insert default admin user (password: admin123)
INSERT INTO users (name, email, password, role) 
VALUES ('System Administrator', 'admin@system.com', '$2y$10$8K1p/a0dR1xqM8ZxK1p/a0dR1xqM8ZxK1p/a0dR1xqM8ZxK1p/a0dR1xqM', 'admin')
ON DUPLICATE KEY UPDATE id = id;

-- Insert sample teachers
INSERT INTO users (name, email, password, role) VALUES
('Батбаяр', 'batbayar@example.com', '$2y$10$8K1p/a0dR1xqM8ZxK1p/a0dR1xqM8ZxK1p/a0dR1xqM8ZxK1p/a0dR1xqM', 'teacher'),
('Дэлгэрмаа', 'delgermaa@example.com', '$2y$10$8K1p/a0dR1xqM8ZxK1p/a0dR1xqM8ZxK1p/a0dR1xqM8ZxK1p/a0dR1xqM', 'teacher')
ON DUPLICATE KEY UPDATE id = id;

-- Insert sample students
INSERT INTO users (name, email, password, role) VALUES
('Болормаа', 'bolormaa@example.com', '$2y$10$8K1p/a0dR1xqM8ZxK1p/a0dR1xqM8ZxK1p/a0dR1xqM8ZxK1p/a0dR1xqM', 'student'),
('Төгсжаргал', 'tugsjargal@example.com', '$2y$10$8K1p/a0dR1xqM8ZxK1p/a0dR1xqM8ZxK1p/a0dR1xqM8ZxK1p/a0dR1xqM', 'student'),
('Батзориг', 'batzorig@example.com', '$2y$10$8K1p/a0dR1xqM8ZxK1p/a0dR1xqM8ZxK1p/a0dR1xqM8ZxK1p/a0dR1xqM', 'student')
ON DUPLICATE KEY UPDATE id = id;

-- Insert sample courses
INSERT INTO courses (name, teacher_id) VALUES
('PHP програмчлал', 2),
('MySQL өгөгдлийн сан', 2),
('HTML/CSS', 3),
('JavaScript', 3)
ON DUPLICATE KEY UPDATE id = id;

-- Insert sample enrollments
INSERT INTO course_enrollments (course_id, student_id) VALUES
(1, 4), -- Болормаа in PHP програмчлал
(1, 5), -- Төгсжаргал in PHP програмчлал
(2, 4), -- Болормаа in MySQL өгөгдлийн сан
(2, 6), -- Батзориг in MySQL өгөгдлийн сан
(3, 5), -- Төгсжаргал in HTML/CSS
(3, 6), -- Батзориг in HTML/CSS
(4, 4), -- Болормаа in JavaScript
(4, 5)  -- Төгсжаргал in JavaScript
ON DUPLICATE KEY UPDATE id = id;

-- Insert sample evaluations
INSERT INTO evaluations (course_id, student_id, score, comment) VALUES
(1, 4, 5, 'Маш сайн хичээл байсан. Багш маш сайн тайлбарласан.'),
(1, 5, 4, 'Хичээл сайн байсан, гэхдээ дасгалуудыг илүү олон болгох хэрэгтэй.'),
(2, 4, 5, 'Өгөгдлийн сангийн үндсэн ойлголтуудыг маш сайн тайлбарласан.'),
(2, 6, 3, 'Зарим сэдвүүд төвөгтэй байсан.'),
(3, 5, 5, 'HTML/CSS-ийн үндсэн ойлголтуудыг маш сайн тайлбарласан.'),
(3, 6, 4, 'Хичээл сайн байсан, гэхдээ илүү практик дасгал хэрэгтэй.'),
(4, 4, 5, 'JavaScript-ийн үндсэн ойлголтуудыг маш сайн тайлбарласан.'),
(4, 5, 4, 'Хичээл сайн байсан, гэхдээ илүү практик дасгал хэрэгтэй.')
ON DUPLICATE KEY UPDATE id = id; 