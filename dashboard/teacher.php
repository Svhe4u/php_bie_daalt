<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// Get all courses for this teacher
$stmt = $conn->prepare("
    SELECT c.*, 
           COUNT(DISTINCT ce.student_id) as enrolled_students,
           COUNT(DISTINCT a.id) as total_assignments,
           COUNT(DISTINCT m.id) as total_materials,
           (SELECT AVG(score) FROM evaluations WHERE course_id = c.id) as average_score,
           (SELECT AVG(grade) FROM grades WHERE course_id = c.id) as average_grade,
           (SELECT COUNT(*) FROM evaluations WHERE course_id = c.id) as total_evaluations,
           (SELECT COUNT(*) FROM grades WHERE course_id = c.id) as graded_count,
           (SELECT COUNT(*) FROM assignment_submissions a 
            JOIN assignments ass ON a.assignment_id = ass.id 
            WHERE ass.course_id = c.id AND a.status = 'pending') as pending_submissions
    FROM courses c
    LEFT JOIN course_enrollments ce ON c.id = ce.course_id
    LEFT JOIN assignments a ON c.id = a.course_id
    LEFT JOIN materials m ON c.id = m.course_id
    WHERE c.teacher_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get upcoming classes for the next 7 days
$stmt = $conn->prepare("
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
    LIMIT 7
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$upcoming_classes_result = $stmt->get_result();
$upcoming_classes = [];
while ($row = $upcoming_classes_result->fetch_assoc()) {
    $upcoming_classes[] = $row;
}

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
        a.id,
        a.title,
        a.content,
        a.created_at,
        u.name as author_name,
        c.name as course_name
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    JOIN courses c ON a.course_id = c.id
    WHERE a.target_role = 'teacher' OR a.target_role = 'all'
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent messages
$stmt = $conn->prepare("
    SELECT 
        m.id,
        m.content,
        m.created_at,
        m.is_read,
        u.name as sender_name,
        c.name as course_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    JOIN courses c ON m.course_id = c.id
    WHERE m.receiver_id = ?
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_courses = count($courses);
$total_students = array_sum(array_column($courses, 'enrolled_students'));
$total_assignments = array_sum(array_column($courses, 'total_assignments'));
$total_pending = array_sum(array_column($courses, 'pending_submissions'));
$total_evaluations = array_sum(array_column($courses, 'total_evaluations'));

$average_score = 0;
$evaluated_courses = array_filter($courses, function($course) {
    return isset($course['average_score']) && $course['average_score'] !== null && $course['average_score'] > 0;
});
if (count($evaluated_courses) > 0) {
    $average_score = array_sum(array_column($evaluated_courses, 'average_score')) / count($evaluated_courses);
}

$average_grade = 0;
$graded_courses = array_filter($courses, function($course) {
    return isset($course['average_grade']) && $course['average_grade'] !== null && $course['average_grade'] > 0;
});
if (count($graded_courses) > 0) {
    $average_grade = array_sum(array_column($graded_courses, 'average_grade')) / count($graded_courses);
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Багшийн хяналтын самбар - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .stat-card {
            transition: transform 0.2s;
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .calendar-container {
            height: 600px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 10px 10px 0 0 !important;
            padding: 1rem;
        }
        .table th {
            border-top: none;
            font-weight: 600;
        }
        .badge {
            padding: 0.5em 0.8em;
            font-weight: 500;
        }
        .rating {
            display: inline-flex;
            align-items: center;
        }
        .rating i {
            margin-right: 2px;
        }
        .task-item {
            border-left: 4px solid #4b6cb7;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .task-item.assignment { border-left-color: #28a745; }
        .task-item.enrollment { border-left-color: #ffc107; }
        .task-item.feedback { border-left-color: #17a2b8; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="teacher.php">
                <img src="../assets/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                Багшийн хяналтын самбар
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="bi bi-person-circle me-1"></i>
                            Тавтай морил, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Гарах</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
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
                        <h5 class="card-title">Дундаж үнэлгээ</h5>
                        <h2 class="mb-0"><?php echo number_format($average_score, 1); ?></h2>
                        <p class="mb-0 mt-2">
                            <i class="bi bi-star me-1"></i>
                            /5 оноо
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Дундаж дүн</h5>
                        <h2 class="mb-0"><?php echo number_format($average_grade, 1); ?></h2>
                        <p class="mb-0 mt-2">
                            <i class="bi bi-graph-up me-1"></i>
                            /100 оноо
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- Pending Tasks -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Хүлээгдэж буй даалгавар</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_tasks)): ?>
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-check-circle fs-4 d-block mb-2"></i>
                                    Хүлээгдэж буй даалгавар байхгүй байна
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Гарчиг</th>
                                            <th>Хичээл</th>
                                            <th>Дуусах огноо</th>
                                            <th>Төрөл</th>
                                            <th>Үйлдэл</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_tasks as $task): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($task['title']); ?></td>
                                                <td><?php echo htmlspecialchars($task['course_name']); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($task['due_date'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $task['type'] === 'assignment' ? 'success' : 'warning'; ?>">
                                                        <?php echo $task['type'] === 'assignment' ? 'Даалгавар' : 'Бүртгэл'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($task['type'] === 'assignment'): ?>
                                                        <a href="grade.php?assignment_id=<?php echo $task['id']; ?>" class="btn btn-primary btn-sm">Шалгах</a>
                                                    <?php else: ?>
                                                        <a href="enrollments.php?request_id=<?php echo $task['id']; ?>" class="btn btn-warning btn-sm">Шалгах</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Course Management Table -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Миний хичээлүүд</h4>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                            <i class="bi bi-plus-circle"></i> Шинэ хичээл
                        </button>
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
                                            <th>Дүн</th>
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
                                                        <?php echo $course['enrolled_students']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?php echo $course['total_assignments']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning">
                                                        <?php echo $course['pending_submissions']; ?>
                                                    </span>
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
                                                    <?php if ($course['graded_count'] > 0): ?>
                                                        <span class="badge bg-<?php echo $course['average_grade'] >= 60 ? 'success' : 'danger'; ?>">
                                                            <?php echo number_format($course['average_grade'], 1); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Дүн байхгүй</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="course.php?id=<?php echo $course['id']; ?>" 
                                                           class="btn btn-primary btn-sm" title="Харах">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-info btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#studentsModal<?php echo $course['id']; ?>"
                                                                title="Оюутнууд">
                                                            <i class="bi bi-people"></i>
                                                        </button>
                                                        <a href="../materials/list.php?course_id=<?php echo $course['id']; ?>" 
                                                           class="btn btn-success btn-sm" title="Материал">
                                                            <i class="bi bi-file-earmark"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-warning btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editModal<?php echo $course['id']; ?>"
                                                                title="Засах">
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
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Хичээлийн хуваарь (Календарь)</h4>
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
                        <?php if (empty($upcoming_classes)): ?>
                            <p class="text-muted mb-0">No upcoming classes scheduled.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($upcoming_classes as $class): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($class['course_name']); ?></h6>
                                            <small><?php echo htmlspecialchars($class['day_of_week']); ?></small>
                                        </div>
                                        <p class="mb-1">
                                            <i class="bi bi-clock me-2"></i><?php echo htmlspecialchars($class['start_time']); ?> - <?php echo htmlspecialchars($class['end_time']); ?>
                                            <br>
                                            <i class="bi bi-door-open me-2"></i><?php echo htmlspecialchars($class['room']); ?>
                                            <br>
                                            <i class="bi bi-people me-2"></i><?php echo $class['student_count']; ?> оюутан
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Announcements -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Сүүлийн зарлалууд</h4>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                            <i class="bi bi-plus-circle"></i> Шинэ
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($announcements)): ?>
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-megaphone fs-4 d-block mb-2"></i>
                                    Зарлал байхгүй байна
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="mb-3">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($announcement['course_name']); ?> - 
                                        <?php echo htmlspecialchars($announcement['author_name']); ?> - 
                                        <?php echo date('Y-m-d', strtotime($announcement['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Шинэ хичээл</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_course.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Хичээлийн нэр</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Тайлбар</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                        <button type="submit" class="btn btn-primary">Нэмэх</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Announcement Modal -->
    <div class="modal fade" id="addAnnouncementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Шинэ мэдэгдэл</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_announcement.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="course_id" class="form-label">Хичээл</label>
                            <select class="form-select" id="course_id" name="course_id" required>
                                <option value="">Сонгох...</option>
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
                            <textarea class="form-control" id="content" name="content" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="target_role" class="form-label">Хүлээн авагч</label>
                            <select class="form-select" id="target_role" name="target_role" required>
                                <option value="all">Бүгд</option>
                                <option value="student">Оюутнууд</option>
                                <option value="teacher">Багш нар</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                        <button type="submit" class="btn btn-primary">Нэмэх</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Course Modal -->
    <?php foreach ($courses as $course): ?>
    <div class="modal fade" id="editModal<?php echo $course['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Хичээл засах</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="edit_course.php" method="POST">
                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name<?php echo $course['id']; ?>" class="form-label">Хичээлийн нэр</label>
                            <input type="text" class="form-control" id="name<?php echo $course['id']; ?>" 
                                   name="name" value="<?php echo htmlspecialchars($course['name']); ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                        <button type="submit" class="btn btn-primary">Хадгалах</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Students Modal -->
    <?php foreach ($courses as $course): ?>
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
                        WHERE c.id = ? AND c.teacher_id = ?
                        ORDER BY u.name
                    ");
                    $stmt->bind_param("ii", $course['id'], $_SESSION['user_id']);
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
                                        <th>Үнэлгээ</th>
                                        <th>Дүн</th>
                                        <th>Даалгавар</th>
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
                                                <?php if ($student['score']): ?>
                                                    <div class="rating">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="bi bi-star<?php echo $i <= round($student['score']) ? '-fill' : ''; ?> text-warning"></i>
                                                        <?php endfor; ?>
                                                        <span class="ms-1"><?php echo number_format($student['score'], 1); ?>/5</span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Үнэлгээ байхгүй</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($student['grade']): ?>
                                                    <span class="badge bg-<?php echo $student['grade'] >= 60 ? 'success' : 'danger'; ?>">
                                                        <?php echo number_format($student['grade'], 1); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Дүн байхгүй</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $student['pending_submissions'] > 0 ? 'warning' : 'success'; ?>">
                                                    <?php echo $student['total_submissions']; ?>/<?php echo $student['pending_submissions']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="student.php?id=<?php echo $student['id']; ?>&course_id=<?php echo $course['id']; ?>" 
                                                       class="btn btn-primary btn-sm" title="Харах">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-warning btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#gradeModal<?php echo $course['id']; ?>_<?php echo $student['id']; ?>"
                                                            title="Дүн оруулах">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" 
                                                            class="btn btn-info btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#messageModal<?php echo $course['id']; ?>_<?php echo $student['id']; ?>"
                                                            title="Мессэж илгээх">
                                                        <i class="bi bi-envelope"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Grade Modal -->
                                        <div class="modal fade" id="gradeModal<?php echo $course['id']; ?>_<?php echo $student['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Дүн оруулах</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form action="update_grade.php" method="POST">
                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label for="grade" class="form-label">Дүн</label>
                                                                <input type="number" class="form-control" id="grade" name="grade" 
                                                                       min="0" max="100" step="0.1" 
                                                                       value="<?php echo $student['grade'] ?? ''; ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="feedback" class="form-label">Санал хүсэлт</label>
                                                                <textarea class="form-control" id="feedback" name="feedback" rows="3"><?php echo $student['grade_feedback'] ?? ''; ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                                                            <button type="submit" class="btn btn-primary">Хадгалах</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Message Modal -->
                                        <div class="modal fade" id="messageModal<?php echo $course['id']; ?>_<?php echo $student['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Мессэж илгээх</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form action="send_message.php" method="POST">
                                                        <input type="hidden" name="receiver_id" value="<?php echo $student['id']; ?>">
                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label for="content" class="form-label">Мессэж</label>
                                                                <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                                                            <button type="submit" class="btn btn-primary">Илгээх</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/mn.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            locale: 'mn',
            height: '600px',
            events: [
                <?php
                // Fetch all schedules for this teacher's courses
                $stmt = $conn->prepare("
                    SELECT cs.*, c.name as course_name
                    FROM course_schedule cs
                    JOIN courses c ON cs.course_id = c.id
                    WHERE c.teacher_id = ?
                ");
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $all_schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                // Map day_of_week to numeric (0=Sunday, 1=Monday, ...)
                $day_map = [
                    'Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3,
                    'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6
                ];

                echo "{ title: 'Test Event', start: '2024-06-10T10:00:00', end: '2024-06-10T11:00:00', color: '#ff0000' },";

                foreach ($all_schedules as $class) {
                    // Find the next date for this day_of_week
                    $dow = $day_map[$class['day_of_week']];
                    echo "{
                        title: '" . addslashes($class['course_name']) . " (" . addslashes($class['room']) . ")',
                        daysOfWeek: [$dow],
                        startTime: '" . $class['start_time'] . "',
                        endTime: '" . $class['end_time'] . "',
                        color: '#4b6cb7',
                        url: 'course.php?id=" . $class['course_id'] . "'
                    },";
                }
                ?>
            ],
            eventClick: function(info) {
                info.jsEvent.preventDefault();
                if (info.event.url) {
                    window.location.href = info.event.url;
                }
            }
        });
        calendar.render();
    });
    </script>
</body>
</html> 