<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Get student's profile information
$stmt = $conn->prepare("
    SELECT u.*, us.language, us.timezone, us.theme, us.font_size
    FROM users u
    LEFT JOIN user_settings us ON u.id = us.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

// Get student's enrolled courses with statistics
$stmt = $conn->prepare("
    SELECT c.*, u.name as teacher_name,
           COUNT(DISTINCT a.id) as total_assignments,
           COUNT(DISTINCT s.id) as submitted_assignments,
           COUNT(DISTINCT g.id) as graded_assignments,
           AVG(g.grade) as average_grade,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as total_materials
    FROM courses c 
    JOIN course_enrollments ce ON c.id = ce.course_id 
    JOIN users u ON c.teacher_id = u.id 
    LEFT JOIN assignments a ON c.id = a.course_id
    LEFT JOIN assignment_submissions s ON a.id = s.assignment_id AND s.student_id = ?
    LEFT JOIN grades g ON c.id = g.course_id AND g.student_id = ?
    WHERE ce.student_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->bind_param("iii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get upcoming assignments
$stmt = $conn->prepare("
    SELECT a.*, c.name as course_name, c.id as course_id,
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as is_submitted
    FROM assignments a 
    JOIN courses c ON a.course_id = c.id 
    JOIN course_enrollments ce ON c.id = ce.course_id 
    WHERE ce.student_id = ? 
    AND a.due_date > NOW() 
    ORDER BY a.due_date ASC 
    LIMIT 5
");
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$upcoming_assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent announcements
$stmt = $conn->prepare("
    SELECT a.*, c.name as course_name, u.name as author_name
    FROM announcements a 
    JOIN courses c ON a.course_id = c.id 
    JOIN course_enrollments ce ON c.id = ce.course_id 
    JOIN users u ON a.author_id = u.id
    WHERE ce.student_id = ? 
    ORDER BY a.created_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get grades
$stmt = $conn->prepare("
    SELECT g.*, c.name as course_name
    FROM grades g 
    JOIN courses c ON g.course_id = c.id 
    WHERE g.student_id = ?
    ORDER BY g.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get unread messages count
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM messages 
    WHERE receiver_id = ? AND is_read = 0
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$messages = $stmt->get_result()->fetch_assoc();

// Calculate statistics
$total_courses = count($courses);
$total_assignments = array_sum(array_column($courses, 'total_assignments'));
$submitted_assignments = array_sum(array_column($courses, 'submitted_assignments'));
$graded_assignments = array_sum(array_column($courses, 'graded_assignments'));

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
    <title>Оюутны хяналтын самбар - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
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
        .dashboard-section {
            background: #fff;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .nav-pills .nav-link {
            color: #495057;
            border-radius: 8px;
            padding: 10px 20px;
            margin: 5px;
        }
        .nav-pills .nav-link.active {
            background-color: #0d6efd;
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <?php if ($profile['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($profile['profile_picture']); ?>" alt="Profile" class="rounded-circle" width="80">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto" 
                                 style="width: 80px; height: 80px; font-size: 2rem;">
                                <?php echo strtoupper(substr($profile['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <h5 class="mt-2"><?php echo htmlspecialchars($profile['name']); ?></h5>
                        <p class="text-muted"><?php echo htmlspecialchars($profile['email']); ?></p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="student.php">
                                <i class="bi bi-house-door"></i> Хяналтын самбар
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="student_courses.php">
                                <i class="bi bi-book"></i> Хичээлүүд
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tabs/settings.php">
                                <i class="bi bi-gear"></i> Тохиргоо
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="messages.php">
                                <i class="bi bi-envelope"></i> Мессежүүд
                                <?php if ($messages['count'] > 0): ?>
                                    <span class="badge bg-danger rounded-pill"><?php echo $messages['count']; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Гарах
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Хяналтын самбар</h1>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-book text-primary"></i>
                                    Нийт хичээл
                                </h5>
                                <h2 class="mb-0"><?php echo $total_courses; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-journal-text text-primary"></i>
                                    Нийт даалгавар
                                </h5>
                                <h2 class="mb-0"><?php echo $total_assignments; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-check-circle text-primary"></i>
                                    Илгээсэн даалгавар
                                </h5>
                                <h2 class="mb-0"><?php echo $submitted_assignments; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-star text-primary"></i>
                                    Дундаж дүн
                                </h5>
                                <h2 class="mb-0"><?php echo number_format($average_grade, 1); ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Enrolled Courses -->
                    <div class="col-md-8 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-book text-primary me-2"></i>
                                    Миний хичээлүүд
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($courses)): ?>
                                    <p class="text-muted">Одоогоор ямар нэгэн хичээлд бүртгүүлээгүй байна.</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($courses as $course): ?>
                                            <a href="../courses/view.php?id=<?php echo $course['id']; ?>" 
                                               class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($course['name']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($course['teacher_name']); ?>
                                                    </small>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <small class="text-muted">
                                                        <i class="bi bi-journal-text"></i> <?php echo $course['total_assignments']; ?> даалгавар
                                                        <i class="bi bi-check-circle ms-2"></i> <?php echo $course['submitted_assignments']; ?> илгээсэн
                                                        <i class="bi bi-star ms-2"></i> <?php echo number_format($course['average_grade'] ?? 0, 1); ?> дундаж
                                                    </small>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Assignments -->
                    <div class="col-md-4 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-journal-text text-primary me-2"></i>
                                    Ирэх даалгавар
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($upcoming_assignments)): ?>
                                    <p class="text-muted">Ирэх даалгавар байхгүй байна.</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($upcoming_assignments as $assignment): ?>
                                            <a href="../assignments/view.php?id=<?php echo $assignment['id']; ?>" 
                                               class="list-group-item list-group-item-action">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo date('Y-m-d', strtotime($assignment['due_date'])); ?>
                                                    </small>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($assignment['course_name']); ?>
                                                    <?php if ($assignment['is_submitted']): ?>
                                                        <span class="badge bg-success ms-2">Илгээсэн</span>
                                                    <?php endif; ?>
                                                </small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Announcements -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-bell text-primary me-2"></i>
                                    Шинэ мэдэгдэл
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($announcements)): ?>
                                    <p class="text-muted">Шинэ мэдэгдэл байхгүй байна.</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($announcements as $announcement): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo date('Y-m-d', strtotime($announcement['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <p class="mb-1"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($announcement['course_name']); ?> - 
                                                    <?php echo htmlspecialchars($announcement['author_name']); ?>
                                                </small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Grades -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-star text-primary me-2"></i>
                                    Сүүлийн дүнгүүд
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($grades)): ?>
                                    <p class="text-muted">Одоогоор дүн байхгүй байна.</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($grades as $grade): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($grade['course_name']); ?></h6>
                                                    <span class="badge bg-primary">
                                                        <?php echo number_format($grade['grade'], 1); ?>
                                                    </span>
                                                </div>
                                                <?php if ($grade['feedback']): ?>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($grade['feedback']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 