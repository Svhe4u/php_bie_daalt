-- Create database
CREATE DATABASE IF NOT EXISTS student_feedback DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE student_feedback;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    profile_picture VARCHAR(255),
    phone_number VARCHAR(20),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Add missing columns to users table if they don't exist
ALTER TABLE users
ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) AFTER profile_picture,
ADD COLUMN IF NOT EXISTS description TEXT AFTER phone_number;

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
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author_id INT NOT NULL,
    target_role ENUM('student', 'teacher', 'all') NOT NULL DEFAULT 'all',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create assignments table
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    due_date TIMESTAMP NOT NULL,    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Create assignment_submissions table
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

-- Create attendance table
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    date TIMESTAMP NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
    note TEXT,
    recorded_by INT NOT NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY (course_id, student_id, date)
);

-- Create materials table
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255),
    file_type VARCHAR(50),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Create course_schedule table
CREATE TABLE IF NOT EXISTS course_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Create messages table
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    course_id INT,
    subject VARCHAR(255),
    content TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);

-- Create teacher_settings table
CREATE TABLE IF NOT EXISTS teacher_settings (
    user_id INT PRIMARY KEY,
    office_hours TEXT,
    notification_preferences JSON,
    availability JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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

-- Insert sample grades
INSERT INTO grades (course_id, student_id, grade, feedback) VALUES
(1, 4, 95.5, 'Маш сайн ажил. Бүх даалгавруудыг зөв гүйцэтгэсэн.'),
(1, 5, 88.0, 'Сайн ажил. Зарим даалгаварт алдаа гарсан.'),
(2, 4, 92.5, 'Өгөгдлийн сангийн үндсэн ойлголтуудыг маш сайн эзэмшсэн.'),
(2, 6, 75.0, 'Дундаж дүн. Илүү их дадлага хийх хэрэгтэй.'),
(3, 5, 90.0, 'HTML/CSS-ийн үндсэн ойлголтуудыг сайн эзэмшсэн.'),
(3, 6, 85.5, 'Сайн ажил. Зарим CSS загваруудыг сайжруулах хэрэгтэй.'),
(4, 4, 94.0, 'JavaScript-ийн үндсэн ойлголтуудыг маш сайн эзэмшсэн.'),
(4, 5, 89.5, 'Сайн ажил. Зарим функцүүдийг илүү сайн ашиглах хэрэгтэй.')
ON DUPLICATE KEY UPDATE id = id;

-- Insert sample announcements
INSERT INTO announcements (course_id, title, content, author_id, target_role) VALUES
(1, 'PHP хичээлийн анхны мэдэгдэл', 'Энэ долоо хоногт PHP хичээлийн анхны хичээл болно. Бэлтгэлээ хийгээрэй.', 2, 'all'),
(2, 'MySQL хичээлийн мэдэгдэл', 'MySQL хичээлийн дараагийн хичээл дээр практик дасгал хийх болно.', 2, 'all'),
(3, 'HTML/CSS хичээлийн мэдэгдэл', 'HTML/CSS хичээлийн дараагийн хичээл дээр responsive design-ийн талаар судлах болно.', 3, 'all'),
(4, 'JavaScript хичээлийн мэдэгдэл', 'JavaScript хичээлийн дараагийн хичээл дээр DOM manipulation-ийн талаар судлах болно.', 3, 'all')
ON DUPLICATE KEY UPDATE id = id;

-- Insert sample assignments
INSERT INTO assignments (course_id, title, description, due_date) VALUES
(1, 'PHP анхны даалгавар', 'PHP-ийн үндсэн синтакс, хувьсагч, операторуудыг ашиглан энгийн тооцоолуур хийх.', DATE_ADD(NOW(), INTERVAL 7 DAY)),
(1, 'PHP функц даалгавар', 'PHP функцүүдийг ашиглан тооны факториал, фибоначчийн тоонуудыг тооцоолох.', DATE_ADD(NOW(), INTERVAL 14 DAY)),
(2, 'MySQL анхны даалгавар', 'MySQL-ийн үндсэн командуудыг ашиглан энгийн өгөгдлийн сан үүсгэх.', DATE_ADD(NOW(), INTERVAL 7 DAY)),
(2, 'MySQL JOIN даалгавар', 'MySQL JOIN командуудыг ашиглан өгөгдлийн сангийн хүснэгтүүдийг холбох.', DATE_ADD(NOW(), INTERVAL 14 DAY)),
(3, 'HTML анхны даалгавар', 'HTML тегүүдийг ашиглан энгийн вэб хуудас хийх.', DATE_ADD(NOW(), INTERVAL 7 DAY)),
(3, 'CSS даалгавар', 'CSS ашиглан вэб хуудсыг загварчлах.', DATE_ADD(NOW(), INTERVAL 14 DAY)),
(4, 'JavaScript анхны даалгавар', 'JavaScript-ийн үндсэн синтакс, хувьсагч, операторуудыг ашиглан энгийн тооцоолуур хийх.', DATE_ADD(NOW(), INTERVAL 7 DAY)),
(4, 'JavaScript DOM даалгавар', 'JavaScript DOM API ашиглан вэб хуудсыг динамикаар өөрчлөх.', DATE_ADD(NOW(), INTERVAL 14 DAY))
ON DUPLICATE KEY UPDATE id = id;

-- Insert sample course schedules
INSERT INTO course_schedule (course_id, day_of_week, start_time, end_time, room) VALUES
(1, 'Monday', '09:00:00', '10:30:00', 'Room 101'),
(1, 'Wednesday', '09:00:00', '10:30:00', 'Room 101'),
(2, 'Tuesday', '13:00:00', '14:30:00', 'Room 102'),
(2, 'Thursday', '13:00:00', '14:30:00', 'Room 102'),
(3, 'Monday', '13:00:00', '14:30:00', 'Room 103'),
(3, 'Wednesday', '13:00:00', '14:30:00', 'Room 103'),
(4, 'Tuesday', '09:00:00', '10:30:00', 'Room 104'),
(4, 'Thursday', '09:00:00', '10:30:00', 'Room 104')
ON DUPLICATE KEY UPDATE id = id;

-- Insert sample teacher settings
INSERT INTO teacher_settings (user_id, office_hours, notification_preferences, availability) VALUES
(2, 'Monday, Wednesday 14:00-16:00', '{"email": true, "sms": false}', '{"monday": true, "tuesday": true, "wednesday": true, "thursday": true, "friday": true}'),
(3, 'Tuesday, Thursday 14:00-16:00', '{"email": true, "sms": true}', '{"monday": true, "tuesday": true, "wednesday": true, "thursday": true, "friday": true}')
ON DUPLICATE KEY UPDATE user_id = user_id;

-- Insert sample messages
INSERT INTO messages (sender_id, receiver_id, course_id, subject, content) VALUES
(4, 2, 1, 'PHP даалгаврын тухай', 'Багш аа, PHP даалгаврын тухай асуух зүйл байна.'),
(2, 4, 1, 'Re: PHP даалгаврын тухай', 'Тиймээ, асуух зүйлээ бичнэ үү.'),
(5, 3, 3, 'HTML/CSS даалгаврын тухай', 'Багш аа, HTML/CSS даалгаврын тухай асуух зүйл байна.'),
(3, 5, 3, 'Re: HTML/CSS даалгаврын тухай', 'Тиймээ, асуух зүйлээ бичнэ үү.')
ON DUPLICATE KEY UPDATE id = id;

-- Update materials table to add created_by column
ALTER TABLE materials
ADD COLUMN created_by INT NOT NULL AFTER file_type,
ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE;

-- Add file_name column to materials table
ALTER TABLE materials ADD COLUMN file_name VARCHAR(255) AFTER file_path; 