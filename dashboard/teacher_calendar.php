<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get teacher's profile information
$stmt = $conn->prepare("
    SELECT u.*, ts.office_hours, ts.notification_preferences, ts.availability
    FROM users u
    LEFT JOIN teacher_settings ts ON u.id = ts.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

// Get teacher's courses with evaluation and grade statistics
$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.name,
        u.name as teacher_name,
        c.created_at,
        (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) as student_count,
        (SELECT COUNT(*) FROM evaluations WHERE course_id = c.id) as total_evaluations,
        (SELECT AVG(score) FROM evaluations WHERE course_id = c.id) as average_score,
        (SELECT COUNT(*) FROM grades WHERE course_id = c.id) as graded_count,
        (SELECT AVG(grade) FROM grades WHERE course_id = c.id) as average_grade,
        (SELECT COUNT(*) FROM assignments WHERE course_id = c.id) as total_assignments,
        (SELECT COUNT(*) FROM assignment_submissions a 
         JOIN assignments ass ON a.assignment_id = ass.id 
         WHERE ass.course_id = c.id AND a.status = 'pending') as pending_submissions
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    WHERE c.teacher_id = ?
    GROUP BY c.id
    ORDER BY c.name
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get upcoming classes for the next 7 days
$upcoming_classes_query = "
    SELECT cs.*, c.name as course_name, c.id as course_id,
           (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) as student_count
    FROM course_schedule cs
    JOIN courses c ON cs.course_id = c.id
    WHERE cs.day_of_week IN (
        DAYNAME(CURRENT_DATE),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 4 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 6 DAY))
    )
    AND c.teacher_id = ?
    ORDER BY FIELD(cs.day_of_week, 
        DAYNAME(CURRENT_DATE),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 2 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 3 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 4 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY)),
        DAYNAME(DATE_ADD(CURRENT_DATE, INTERVAL 6 DAY))
    ), cs.start_time ASC
    LIMIT 7";
$stmt = $conn->prepare($upcoming_classes_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$upcoming_classes = $stmt->get_result();

// Get pending tasks
$stmt = $conn->prepare("
    SELECT 
        'assignment' as type,
        a.id,
        a.title,
        c.name as course_name,
        a.due_date,
        COUNT(s.id) as pending_count
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.status = 'pending'
    WHERE c.teacher_id = ?
    AND a.due_date >= CURDATE()
    GROUP BY a.id
    HAVING pending_count > 0
    UNION ALL
    SELECT 
        'enrollment' as type,
        er.id,
        'Enrollment Request' as title,
        c.name as course_name,
        er.created_at as due_date,
        1 as pending_count
    FROM enrollment_requests er
    JOIN courses c ON er.course_id = c.id
    WHERE c.teacher_id = ? AND er.status = 'pending'
    ORDER BY due_date ASC
    LIMIT 10
");
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$pending_tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent announcements
$stmt = $conn->prepare("
    SELECT 
        a.*,
        c.name as course_name,
        u.name as created_by_name
    FROM announcements a
    JOIN courses c ON a.course_id = c.id
    JOIN users u ON a.author_id = u.id
    WHERE c.teacher_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recent_announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unread messages
$stmt = $conn->prepare("
    SELECT 
        m.*,
        s.name as sender_name,
        c.name as course_name
    FROM messages m
    JOIN users s ON m.sender_id = s.id
    LEFT JOIN courses c ON m.course_id = c.id
    WHERE m.receiver_id = ? AND m.is_read = FALSE
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$unread_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_courses = count($courses);
$total_students = array_sum(array_column($courses, 'student_count'));
$total_assignments = array_sum(array_column($courses, 'total_assignments'));
$total_pending = array_sum(array_column($courses, 'pending_submissions'));
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Багшийн хяналтын самбар - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .calendar-container {
            height: 600px;
        }
        .task-item {
            border-left: 4px solid #4b6cb7;
            padding-left: 1rem;
            margin-bottom: 1rem;
        }
        .task-item.assignment {
            border-left-color: #28a745;
        }
        .task-item.enrollment {
            border-left-color: #ffc107;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-auto">
                    <img src="<?php echo $profile['profile_picture'] ?? '../assets/default-profile.png'; ?>" 
                         alt="Profile Picture" 
                         class="profile-picture">
                </div>
                <div class="col">
                    <h1 class="mb-1"><?php echo htmlspecialchars($profile['name']); ?></h1>
                    <p class="mb-0">Багш</p>
                </div>
                <div class="col-auto">
                    <a href="settings.php" class="btn btn-light">
                        <i class="bi bi-gear"></i> Тохиргоо
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Нийт хичээл</h5>
                        <h2 class="mb-0"><?php echo $total_courses; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Нийт оюутан</h5>
                        <h2 class="mb-0"><?php echo $total_students; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Нийт даалгавар</h5>
                        <h2 class="mb-0"><?php echo $total_assignments; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Хүлээгдэж буй</h5>
                        <h2 class="mb-0"><?php echo $total_pending; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-md-8">
                <!-- Pending Tasks -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Хүлээгдэж буй ажлууд</h4>
                        <a href="tasks.php" class="btn btn-primary btn-sm">Бүгдийг харах</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_tasks)): ?>
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-check-circle fs-4 d-block mb-2"></i>
                                    Хүлээгдэж буй ажил байхгүй байна
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pending_tasks as $task): ?>
                                <div class="task-item <?php echo $task['type']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($task['title']); ?></h6>
                                            <p class="mb-1 text-muted">
                                                <?php echo htmlspecialchars($task['course_name']); ?>
                                                <?php if ($task['type'] === 'assignment'): ?>
                                                    - <?php echo $task['pending_count']; ?> оюутан
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted">
                                                <?php echo date('Y-m-d H:i', strtotime($task['due_date'])); ?>
                                            </small>
                                            <?php if ($task['type'] === 'assignment'): ?>
                                                <a href="grade.php?assignment_id=<?php echo $task['id']; ?>" 
                                                   class="btn btn-primary btn-sm ms-2">
                                                    <i class="bi bi-check-circle"></i> Шалгах
                                                </a>
                                            <?php else: ?>
                                                <a href="enrollments.php" class="btn btn-warning btn-sm ms-2">
                                                    <i class="bi bi-person-plus"></i> Шалгах
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Course List -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Миний хичээлүүд</h4>
                        <div>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                                <i class="bi bi-plus-circle"></i> Шинэ хичээл
                            </button>
                            <a href="courses.php" class="btn btn-outline-primary btn-sm ms-2">
                                <i class="bi bi-grid"></i> Бүгдийг харах
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($courses)): ?>
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-book fs-4 d-block mb-2"></i>
                                    Хичээл байхгүй байна
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Хичээл</th>
                                            <th>Оюутны тоо</th>
                                            <th>Даалгавар</th>
                                            <th>Хүлээгдэж буй</th>
                                            <th>Үнэлгээ</th>
                                            <th>Үйлдэл</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($courses as $course): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-book text-primary me-2"></i>
                                                        <?php echo htmlspecialchars($course['name']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <i class="bi bi-people me-1"></i>
                                                        <?php echo $course['student_count']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?php echo $course['total_assignments']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($course['pending_submissions'] > 0): ?>
                                                        <span class="badge bg-warning">
                                                            <?php echo $course['pending_submissions']; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($course['total_evaluations'] > 0): ?>
                                                        <div class="rating">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="bi bi-star<?php echo $i <= round($course['average_score']) ? '-fill' : ''; ?> text-warning"></i>
                                                            <?php endfor; ?>
                                                            <span class="ms-1"><?php echo number_format($course['average_score'], 1); ?>/5</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Үнэлгээ байхгүй</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="course.php?id=<?php echo $course['id']; ?>" 
                                                           class="btn btn-primary btn-sm">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-info btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#studentsModal<?php echo $course['id']; ?>">
                                                            <i class="bi bi-people"></i>
                                                        </button>
                                                        <a href="materials.php?course_id=<?php echo $course['id']; ?>" 
                                                           class="btn btn-success btn-sm">
                                                            <i class="bi bi-file-earmark"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-warning btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editModal<?php echo $course['id']; ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Calendar -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Хичээлийн хуваарь</h4>
                    </div>
                    <div class="card-body">
                        <div id="calendar" class="calendar-container"></div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Upcoming Classes -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Удахгүй болох хичээлүүд</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($upcoming_classes->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($class = $upcoming_classes->fetch_assoc()): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($class['course_name']); ?></h6>
                                            <small><?php echo htmlspecialchars($class['day_of_week']); ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <i class="fas fa-clock me-2"></i><?php echo htmlspecialchars($class['start_time']); ?> - <?php echo htmlspecialchars($class['end_time']); ?>
                                            <br>
                                            <i class="fas fa-door-open me-2"></i><?php echo htmlspecialchars($class['room']); ?>
                                            <br>
                                            <i class="fas fa-users me-2"></i><?php echo $class['student_count']; ?> students
                                        </p>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No upcoming classes scheduled.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Announcements -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Сүүлийн зарлалууд</h4>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#announcementModal">
                            <i class="bi bi-plus-circle"></i> Шинэ
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_announcements)): ?>
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-megaphone fs-4 d-block mb-2"></i>
                                    Зарлал байхгүй байна
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_announcements as $announcement): ?>
                                <div class="mb-3">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($announcement['course_name']); ?> - 
                                        <?php echo date('Y-m-d', strtotime($announcement['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Unread Messages -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Уншаагүй мессежүүд</h4>
                        <a href="messages.php" class="btn btn-primary btn-sm">Бүгдийг харах</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($unread_messages)): ?>
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-envelope fs-4 d-block mb-2"></i>
                                    Уншаагүй мессеж байхгүй байна
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($unread_messages as $message): ?>
                                <div class="mb-3">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($message['subject']); ?></h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($message['content']); ?></p>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($message['sender_name']); ?>
                                        <?php if ($message['course_name']): ?>
                                            - <?php echo htmlspecialchars($message['course_name']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Course Modals -->
    <?php foreach ($courses as $course): ?>
        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?php echo $course['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Хичээл засах</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form action="../courses/update.php" method="POST">
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            <div class="mb-3">
                                <label for="name<?php echo $course['id']; ?>" class="form-label">Хичээлийн нэр</label>
                                <input type="text" class="form-control" id="name<?php echo $course['id']; ?>" 
                                       name="name" value="<?php echo htmlspecialchars($course['name']); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Хадгалах
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Students Modal -->
        <div class="modal fade" id="studentsModal<?php echo $course['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <?php echo htmlspecialchars($course['name']); ?> - Оюутнууд
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php
                        $stmt = $conn->prepare("
                            SELECT 
                                u.id,
                                u.name,
                                u.email,
                                ce.enrolled_at,
                                e.score,
                                e.comment,
                                e.created_at as evaluation_date,
                                g.grade,
                                g.feedback as grade_feedback,
                                g.updated_at as grade_updated_at,
                                (SELECT COUNT(*) FROM assignment_submissions a 
                                 JOIN assignments ass ON a.assignment_id = ass.id 
                                 WHERE ass.course_id = c.id AND a.student_id = u.id) as total_submissions,
                                (SELECT COUNT(*) FROM assignment_submissions a 
                                 JOIN assignments ass ON a.assignment_id = ass.id 
                                 WHERE ass.course_id = c.id AND a.student_id = u.id AND a.status = 'pending') as pending_submissions
                            FROM course_enrollments ce
                            JOIN users u ON ce.student_id = u.id
                            JOIN courses c ON ce.course_id = c.id
                            LEFT JOIN evaluations e ON e.course_id = c.id AND e.student_id = ce.student_id
                            LEFT JOIN grades g ON g.course_id = c.id AND g.student_id = ce.student_id
                            WHERE ce.course_id = ?
                            ORDER BY u.name
                        ");
                        $stmt->bind_param("i", $course['id']);
                        $stmt->execute();
                        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        ?>

                        <?php if (empty($students)): ?>
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-people fs-4 d-block mb-2"></i>
                                    Оюутан байхгүй байна
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Оюутан</th>
                                            <th>И-мэйл</th>
                                            <th>Даалгавар</th>
                                            <th>Үнэлгээ</th>
                                            <th>Дүн</th>
                                            <th>Үйлдэл</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-person text-primary me-2"></i>
                                                        <?php echo htmlspecialchars($student['name']); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                <td>
                                                    <?php if ($student['total_submissions'] > 0): ?>
                                                        <span class="badge bg-<?php echo $student['pending_submissions'] > 0 ? 'warning' : 'success'; ?>">
                                                            <?php echo $student['total_submissions']; ?>
                                                            <?php if ($student['pending_submissions'] > 0): ?>
                                                                (<?php echo $student['pending_submissions']; ?> хүлээгдэж буй)
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($student['score']): ?>
                                                        <div class="rating">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="bi bi-star<?php echo $i <= $student['score'] ? '-fill' : ''; ?> text-warning"></i>
                                                            <?php endfor; ?>
                                                            <span class="ms-1"><?php echo $student['score']; ?>/5</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Үнэлгээ байхгүй</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($student['grade'] !== null): ?>
                                                        <span class="badge bg-<?php echo $student['grade'] >= 60 ? 'success' : 'danger'; ?>">
                                                            <?php echo number_format($student['grade'], 1); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Дүн байхгүй</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="student.php?id=<?php echo $student['id']; ?>&course_id=<?php echo $course['id']; ?>" 
                                                           class="btn btn-primary btn-sm">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-warning btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#gradeModal<?php echo $course['id']; ?>_<?php echo $student['id']; ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-info btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#messageModal<?php echo $course['id']; ?>_<?php echo $student['id']; ?>">
                                                            <i class="bi bi-envelope"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Announcement Modal -->
    <div class="modal fade" id="announcementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Шинэ зарлал</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="../announcements/create.php" method="POST">
                        <div class="mb-3">
                            <label for="course_id" class="form-label">Хичээл</label>
                            <select class="form-select" id="course_id" name="course_id" required>
                                <option value="">Хичээл сонгоно уу</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>">
                                        <?php echo htmlspecialchars($course['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="title" class="form-label">Гарчиг</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Агуулга</label>
                            <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Илгээх
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
        // Initialize FullCalendar
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: [
                    <?php foreach ($upcoming_classes as $class): ?>
                    {
                        title: '<?php echo addslashes($class['course_name']); ?>',
                        start: '<?php echo $class['date']; ?>T<?php echo $class['start_time']; ?>',
                        end: '<?php echo $class['date']; ?>T<?php echo $class['end_time']; ?>',
                        url: 'course.php?id=<?php echo $class['course_id']; ?>'
                    },
                    <?php endforeach; ?>
                ],
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    window.location.href = info.event.url;
                }
            });
            calendar.render();
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html> 