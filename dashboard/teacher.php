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
$total_students = array_sum(array_column($courses, 'student_count'));
$total_assignments = array_sum(array_column($courses, 'total_assignments'));
$total_pending = array_sum(array_column($courses, 'pending_submissions'));
$total_evaluations = array_sum(array_column($courses, 'total_evaluations'));

$average_score = 0;
$evaluated_courses = array_filter($courses, function($course) {
    return !is_null($course['average_score']);
});
if (count($evaluated_courses) > 0) {
    $average_score = array_sum(array_column($evaluated_courses, 'average_score')) / count($evaluated_courses);
}

$average_grade = 0;
$graded_courses = array_filter($courses, function($course) {
    return !is_null($course['average_grade']);
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

    <div class="container py-4">
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
                                                        <?php echo $course['student_count']; ?>
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

                <!-- Statistics Section -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Статистик</h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Нийт хичээл:</span>
                            <span class="fw-bold"><?php echo $total_courses; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Нийт оюутан:</span>
                            <span class="fw-bold"><?php echo $total_students; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Нийт даалгавар:</span>
                            <span class="fw-bold"><?php echo $total_assignments; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Хүлээгдэж буй:</span>
                            <span class="fw-bold"><?php echo $total_pending; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Нийт үнэлгээ:</span>
                            <span class="fw-bold"><?php echo $total_evaluations; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Дундаж үнэлгээ:</span>
                            <span class="fw-bold">
                                <?php if ($average_score > 0): ?>
                                    <span class="badge bg-<?php echo $average_score >= 3 ? 'success' : 'warning'; ?>">
                                        <?php echo number_format($average_score, 1); ?>/5
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Үнэлгээ байхгүй</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Дундаж дүн:</span>
                            <span class="fw-bold">
                                <?php if ($average_grade > 0): ?>
                                    <span class="badge bg-<?php echo $average_grade >= 60 ? 'success' : 'danger'; ?>">
                                        <?php echo number_format($average_grade, 1); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Дүн байхгүй</span>
                                <?php endif; ?>
                            </span>
                        </div>
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
                <!-- Announcements -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Мэдэгдлүүд</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($announcements)): ?>
                            <div class="text-center py-3">
                                <div class="text-muted">
                                    <i class="bi bi-megaphone fs-4 d-block mb-2"></i>
                                    Мэдэгдэл байхгүй байна
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="mb-3 pb-3 border-bottom">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                    <p class="mb-1 small"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                    <small class="text-muted">
                                        <i class="bi bi-book me-1"></i><?php echo htmlspecialchars($announcement['course_name']); ?> - 
                                        <?php echo htmlspecialchars($announcement['author_name']); ?> - 
                                        <?php echo date('Y-m-d', strtotime($announcement['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Messages -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Мессэжүүд</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($messages)): ?>
                            <div class="text-center py-3">
                                <div class="text-muted">
                                    <i class="bi bi-envelope fs-4 d-block mb-2"></i>
                                    Мессэж байхгүй байна
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <div class="mb-3 pb-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($message['sender_name']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($message['content']); ?></p>
                                            <small class="text-muted">
                                                <i class="bi bi-book me-1"></i><?php echo htmlspecialchars($message['course_name']); ?> - 
                                                <?php echo date('Y-m-d', strtotime($message['created_at'])); ?>
                                            </small>
                                        </div>
                                        <?php if (!$message['is_read']): ?>
                                            <span class="badge bg-primary">Шинэ</span>
                                        <?php endif; ?>
                                    </div>
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
                        <div class="mb-3">
                            <label for="description<?php echo $course['id']; ?>" class="form-label">Тайлбар</label>
                            <textarea class="form-control" id="description<?php echo $course['id']; ?>" 
                                      name="description" rows="3"><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                locale: 'mn',
                slotMinTime: '08:00:00',
                slotMaxTime: '20:00:00',
                allDaySlot: false,
                height: 'auto',
                events: [
                    <?php 
                    while ($class = $upcoming_classes->fetch_assoc()) {
                        $start_time = date('H:i:s', strtotime($class['start_time']));
                        $end_time = date('H:i:s', strtotime($class['end_time']));
                        echo "{
                            title: '" . addslashes($class['course_name']) . "',
                            daysOfWeek: [" . (array_search($class['day_of_week'], ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']) + 1) . "],
                            startTime: '" . $start_time . "',
                            endTime: '" . $end_time . "',
                            url: 'course.php?id=" . $class['course_id'] . "',
                            backgroundColor: '#4b6cb7',
                            borderColor: '#4b6cb7'
                        },";
                    }
                    ?>
                ],
                eventClick: function(info) {
                    if (info.event.url) {
                        window.location.href = info.event.url;
                        return false;
                    }
                }
            });
            calendar.render();
        });
    </script>
</body>
</html> 