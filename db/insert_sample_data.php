<?php
require_once __DIR__ . '/../db.php';

try {
    // Insert sample teachers
    $teachers = [
        [
            'name' => 'Батбаяр',
            'email' => 'batbayar@example.com',
            'password' => password_hash('Teacher123!', PASSWORD_DEFAULT),
            'role' => 'teacher'
        ],
        [
            'name' => 'Дэлгэрмаа',
            'email' => 'delgermaa@example.com',
            'password' => password_hash('Teacher123!', PASSWORD_DEFAULT),
            'role' => 'teacher'
        ],
        [
            'name' => 'Болормаа',
            'email' => 'bolormaa@example.com',
            'password' => password_hash('Teacher123!', PASSWORD_DEFAULT),
            'role' => 'teacher'
        ]
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    foreach ($teachers as $teacher) {
        $stmt->bind_param("ssss", $teacher['name'], $teacher['email'], $teacher['password'], $teacher['role']);
        $stmt->execute();
    }

    // Insert sample students
    $students = [
        [
            'name' => 'Бат-Эрдэнэ',
            'email' => 'baterdene@example.com',
            'password' => password_hash('Student123!', PASSWORD_DEFAULT),
            'role' => 'student'
        ],
        [
            'name' => 'Оюунчимэг',
            'email' => 'oyunchimeg@example.com',
            'password' => password_hash('Student123!', PASSWORD_DEFAULT),
            'role' => 'student'
        ],
        [
            'name' => 'Төгсжаргал',
            'email' => 'tugsjargal@example.com',
            'password' => password_hash('Student123!', PASSWORD_DEFAULT),
            'role' => 'student'
        ],
        [
            'name' => 'Мөнхбат',
            'email' => 'monkhbat@example.com',
            'password' => password_hash('Student123!', PASSWORD_DEFAULT),
            'role' => 'student'
        ],
        [
            'name' => 'Алтанцэцэг',
            'email' => 'altantsetseg@example.com',
            'password' => password_hash('Student123!', PASSWORD_DEFAULT),
            'role' => 'student'
        ]
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    foreach ($students as $student) {
        $stmt->bind_param("ssss", $student['name'], $student['email'], $student['password'], $student['role']);
        $stmt->execute();
    }

    // Get teacher IDs
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'teacher'");
    $stmt->execute();
    $teacher_ids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Insert sample courses
    $courses = [
        [
            'name' => 'Вэб хөгжүүлэлт',
            'teacher_id' => $teacher_ids[0]['id']
        ],
        [
            'name' => 'Мобайл хөгжүүлэлт',
            'teacher_id' => $teacher_ids[1]['id']
        ],
        [
            'name' => 'Өгөгдлийн сан',
            'teacher_id' => $teacher_ids[2]['id']
        ],
        [
            'name' => 'Програмчлалын үндэс',
            'teacher_id' => $teacher_ids[0]['id']
        ],
        [
            'name' => 'Системийн анализ',
            'teacher_id' => $teacher_ids[1]['id']
        ]
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO courses (name, teacher_id) VALUES (?, ?)");
    foreach ($courses as $course) {
        $stmt->bind_param("si", $course['name'], $course['teacher_id']);
        $stmt->execute();
    }

    // Get course and student IDs
    $stmt = $conn->prepare("SELECT id FROM courses");
    $stmt->execute();
    $course_ids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'student'");
    $stmt->execute();
    $student_ids = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Enroll students in courses
    $stmt = $conn->prepare("INSERT IGNORE INTO course_enrollments (course_id, student_id) VALUES (?, ?)");
    foreach ($course_ids as $course) {
        // Enroll 2-3 random students in each course
        $num_students = rand(2, 3);
        $selected_students = array_rand($student_ids, $num_students);
        if (!is_array($selected_students)) {
            $selected_students = [$selected_students];
        }
        foreach ($selected_students as $student_index) {
            $stmt->bind_param("ii", $course['id'], $student_ids[$student_index]['id']);
            $stmt->execute();
        }
    }

    // Add some evaluations
    $stmt = $conn->prepare("INSERT IGNORE INTO evaluations (course_id, student_id, score, comment) VALUES (?, ?, ?, ?)");
    foreach ($course_ids as $course) {
        // Add 1-2 evaluations per course
        $num_evaluations = rand(1, 2);
        $selected_students = array_rand($student_ids, $num_evaluations);
        if (!is_array($selected_students)) {
            $selected_students = [$selected_students];
        }
        foreach ($selected_students as $student_index) {
            $score = rand(3, 5);
            $comments = [
                "Маш сайн хичээл байсан",
                "Багш маш сайн зааж өгч байна",
                "Хичээл сонирхолтой байсан",
                "Даалгаврууд тодорхой байсан",
                "Хичээлийн агуулга сайн байсан"
            ];
            $comment = $comments[array_rand($comments)];
            $stmt->bind_param("iiis", $course['id'], $student_ids[$student_index]['id'], $score, $comment);
            $stmt->execute();
        }
    }

    // Add some grades
    $stmt = $conn->prepare("INSERT IGNORE INTO grades (course_id, student_id, grade, feedback) VALUES (?, ?, ?, ?)");
    foreach ($course_ids as $course) {
        // Add grades for 2-3 students per course
        $num_grades = rand(2, 3);
        $selected_students = array_rand($student_ids, $num_grades);
        if (!is_array($selected_students)) {
            $selected_students = [$selected_students];
        }
        foreach ($selected_students as $student_index) {
            $grade = rand(60, 95);
            $feedbacks = [
                "Сайн ажилласан",
                "Илүү сайн боломжтой",
                "Маш сайн",
                "Дундаж дээш",
                "Онцгой сайн"
            ];
            $feedback = $feedbacks[array_rand($feedbacks)];
            $stmt->bind_param("iids", $course['id'], $student_ids[$student_index]['id'], $grade, $feedback);
            $stmt->execute();
        }
    }

    // Add teacher settings
    $stmt = $conn->prepare("INSERT IGNORE INTO teacher_settings (user_id, office_hours, notification_preferences, availability) VALUES (?, ?, ?, ?)");
    foreach ($teacher_ids as $teacher) {
        $office_hours = "Даваа, Мягмар: 10:00-12:00\nЛхагва, Пүрэв: 14:00-16:00";
        $notification_preferences = json_encode([
            'email' => true,
            'sms' => false,
            'announcements' => true,
            'messages' => true
        ]);
        $availability = json_encode([
            'monday' => ['09:00-17:00'],
            'tuesday' => ['09:00-17:00'],
            'wednesday' => ['09:00-17:00'],
            'thursday' => ['09:00-17:00'],
            'friday' => ['09:00-17:00']
        ]);
        $stmt->bind_param("isss", $teacher['id'], $office_hours, $notification_preferences, $availability);
        $stmt->execute();
    }

    // Add course schedules
    $stmt = $conn->prepare("INSERT IGNORE INTO course_schedule (course_id, day_of_week, start_time, end_time, room) VALUES (?, ?, ?, ?, ?)");
    
    // Get all course IDs
    $stmt_courses = $conn->prepare("SELECT id FROM courses");
    $stmt_courses->execute();
    $all_courses = $stmt_courses->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $rooms = ['Room 101', 'Room 102', 'Room 103', 'Room 201', 'Room 202'];
    $time_slots = [
        ['09:00:00', '10:30:00'],
        ['11:00:00', '12:30:00'],
        ['14:00:00', '15:30:00'],
        ['16:00:00', '17:30:00']
    ];
    
    foreach ($all_courses as $course) {
        // Add 2-3 schedule entries per course
        $num_schedules = rand(2, 3);
        $selected_days = array_rand($days, $num_schedules);
        if (!is_array($selected_days)) {
            $selected_days = [$selected_days];
        }
        
        foreach ($selected_days as $day_index) {
            $time_slot = $time_slots[array_rand($time_slots)];
            $room = $rooms[array_rand($rooms)];
            
            $stmt->bind_param("issss", 
                $course['id'],
                $days[$day_index],
                $time_slot[0],
                $time_slot[1],
                $room
            );
            $stmt->execute();
        }
    }

    echo "Sample data inserted successfully!\n";
    echo "Teachers: " . count($teachers) . "\n";
    echo "Students: " . count($students) . "\n";
    echo "Courses: " . count($courses) . "\n";

} catch (Exception $e) {
    echo "Error inserting sample data: " . $e->getMessage() . "\n";
}

$conn->close();
?> 