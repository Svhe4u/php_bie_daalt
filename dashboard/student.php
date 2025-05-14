<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Get student's enrolled courses with evaluation and grade data
$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.name,
        u.name as teacher_name,
        e.score,
        e.comment,
        e.created_at as evaluation_date,
        ce.enrolled_at,
        g.grade,
        g.feedback as grade_feedback,
        g.updated_at as grade_updated_at,
        (SELECT COUNT(*) FROM course_enrollments WHERE student_id = ?) as total_enrolled,
        (SELECT COUNT(*) FROM evaluations WHERE student_id = ?) as total_evaluations,
        (SELECT COUNT(*) FROM grades WHERE student_id = ?) as total_grades
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.id
    JOIN users u ON c.teacher_id = u.id
    LEFT JOIN evaluations e ON e.course_id = c.id AND e.student_id = ce.student_id
    LEFT JOIN grades g ON g.course_id = c.id AND g.student_id = ce.student_id
    WHERE ce.student_id = ?
    ORDER BY c.name
");
$stmt->bind_param("iiii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get student's profile information
$stmt = $conn->prepare("
    SELECT name, email, created_at, profile_picture
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

// Calculate statistics
$total_courses = count($courses);
$evaluated_courses = count(array_filter($courses, function($course) {
    return !is_null($course['score']);
}));
$graded_courses = count(array_filter($courses, function($course) {
    return !is_null($course['grade']);
}));

$average_grade = 0;
if ($graded_courses > 0) {
    $total_grade = array_sum(array_column($courses, 'grade'));
    $average_grade = $total_grade / $graded_courses;
}

// Get recent announcements
$stmt = $conn->prepare("
    SELECT 
        a.id,
        a.title,
        a.content,
        a.created_at,
        u.name as author_name
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    WHERE a.target_role = 'student' OR a.target_role = 'all'
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оюутны хяналтын самбар - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="student.php">
                <img src="../assets/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                Оюутны хяналтын самбар
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">Тавтай морил, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Гарах</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Overview Section -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <img src="<?php echo $profile['profile_picture'] ?? '../assets/default-avatar.png'; ?>" 
                             alt="Profile" 
                             class="rounded-circle mb-3" 
                             style="width: 120px; height: 120px; object-fit: cover;">
                        <h4 class="mb-1"><?php echo htmlspecialchars($profile['name']); ?></h4>
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($profile['email']); ?></p>
                        <div class="d-grid">
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#profileModal">
                                <i class="bi bi-pencil"></i> Профайл засах
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Товч мэдээлэл</h5>
                        <div class="row g-3">
                            <div class="col-sm-6 col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <i class="bi bi-book text-primary fs-4"></i>
                                    <h6 class="mt-2 mb-0"><?php echo $total_courses; ?></h6>
                                    <small class="text-muted">Нийт хичээл</small>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <i class="bi bi-star text-warning fs-4"></i>
                                    <h6 class="mt-2 mb-0"><?php echo $evaluated_courses; ?></h6>
                                    <small class="text-muted">Үнэлсэн</small>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <i class="bi bi-check-circle text-success fs-4"></i>
                                    <h6 class="mt-2 mb-0"><?php echo $graded_courses; ?></h6>
                                    <small class="text-muted">Дүн авсан</small>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="border rounded p-3 text-center">
                                    <i class="bi bi-graph-up text-info fs-4"></i>
                                    <h6 class="mt-2 mb-0">
                                        <?php echo $average_grade > 0 ? number_format($average_grade, 1) : '-'; ?>
                                    </h6>
                                    <small class="text-muted">Дундаж дүн</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row">
            <!-- Left Column -->
            <div class="col-md-8">
                <!-- Courses Section -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Миний хичээлүүд</h4>
                        <a href="../courses/list.php" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-plus-lg"></i> Шинэ хичээл
                        </a>
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
                                            <th>Багш</th>
                                            <th>Дүн</th>
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
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-person text-success me-2"></i>
                                                        <?php echo htmlspecialchars($course['teacher_name']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($course['grade'] !== null): ?>
                                                        <div class="d-flex align-items-center">
                                                            <span class="badge bg-<?php echo $course['grade'] >= 60 ? 'success' : 'danger'; ?>">
                                                                <?php echo number_format($course['grade'], 1); ?>
                                                            </span>
                                                            <?php if ($course['grade_feedback']): ?>
                                                                <button type="button" 
                                                                        class="btn btn-link btn-sm text-muted ms-2 p-0" 
                                                                        data-bs-toggle="tooltip" 
                                                                        title="<?php echo htmlspecialchars($course['grade_feedback']); ?>">
                                                                    <i class="bi bi-info-circle"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted d-block">
                                                            <?php echo date('Y-m-d', strtotime($course['grade_updated_at'])); ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Дүн байхгүй</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($course['score']): ?>
                                                        <div class="rating">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="bi bi-star<?php echo $i <= $course['score'] ? '-fill' : ''; ?> text-warning"></i>
                                                            <?php endfor; ?>
                                                            <span class="ms-1"><?php echo $course['score']; ?>/5</span>
                                                        </div>
                                                        <?php if ($course['comment']): ?>
                                                            <button type="button" 
                                                                    class="btn btn-link btn-sm text-muted p-0" 
                                                                    data-bs-toggle="tooltip" 
                                                                    title="<?php echo htmlspecialchars($course['comment']); ?>">
                                                                <i class="bi bi-chat-left-text"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Үнэлгээ байхгүй</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <?php if (!$course['score']): ?>
                                                            <a href="../evaluation/submit.php?course_id=<?php echo $course['id']; ?>" 
                                                               class="btn btn-primary btn-sm">
                                                                <i class="bi bi-star"></i> Үнэлэх
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="../evaluation/view.php?course_id=<?php echo $course['id']; ?>" 
                                                               class="btn btn-info btn-sm">
                                                                <i class="bi bi-eye"></i> Харах
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="../courses/view.php?id=<?php echo $course['id']; ?>" 
                                                           class="btn btn-outline-primary btn-sm">
                                                            <i class="bi bi-folder"></i> Материал
                                                        </a>
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

                <!-- Calendar Section -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Хуанли</h4>
                    </div>
                    <div class="card-body">
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-md-4">
                <!-- Announcements Section -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Мэдэгдлүүд</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($announcements)): ?>
                            <div class="text-center py-4">
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
                                        <?php echo htmlspecialchars($announcement['author_name']); ?> - 
                                        <?php echo date('Y-m-d', strtotime($announcement['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
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
                            <span>Үнэлсэн хичээл:</span>
                            <span class="fw-bold"><?php echo $evaluated_courses; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Дүн авсан хичээл:</span>
                            <span class="fw-bold"><?php echo $graded_courses; ?></span>
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

                <!-- Quick Links -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Холбоосууд</h4>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="../courses/list.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-search me-2"></i>Хичээлүүдийг харах
                            </a>
                            <a href="../profile.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-person me-2"></i>Профайл засах
                            </a>
                            <a href="../messages.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-envelope me-2"></i>Мессэж
                            </a>
                            <a href="../resources.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-file-earmark-text me-2"></i>Материалууд
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Профайл засах</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="../profile/update.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Зураг</label>
                            <input type="file" class="form-control" name="profile_picture" accept="image/*">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Нэр</label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($profile['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">И-мэйл</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Шинэ нууц үг</label>
                            <input type="password" class="form-control" name="new_password">
                            <small class="text-muted">Хоосон үлдээвэл нууц үг өөрчлөгдөхгүй</small>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        // Initialize calendar
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
                events: [
                    // Add your events here
                    // Example:
                    // {
                    //     title: 'Хичээл',
                    //     start: '2024-05-15T10:00:00',
                    //     end: '2024-05-15T12:00:00'
                    // }
                ]
            });
            calendar.render();
        });
    </script>
</body>
</html> 