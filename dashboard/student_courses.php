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

// Get student's enrolled courses with detailed statistics
$stmt = $conn->prepare("
    SELECT c.*, u.name as teacher_name,
           COUNT(DISTINCT a.id) as total_assignments,
           COUNT(DISTINCT s.id) as submitted_assignments,
           COUNT(DISTINCT g.id) as graded_assignments,
           AVG(g.grade) as average_grade,
           (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as total_materials,
           (SELECT COUNT(*) FROM announcements WHERE course_id = c.id) as total_announcements
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
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Миний хичээлүүд - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .course-card {
            transition: transform 0.2s;
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            height: 100%;
        }
        .course-card:hover {
            transform: translateY(-5px);
        }
        .stat-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            margin-right: 0.5rem;
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
                            <a class="nav-link" href="student.php">
                                <i class="bi bi-house-door"></i> Хяналтын самбар
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="student_courses.php">
                                <i class="bi bi-book"></i> Хичээлүүд
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tabs/settings.php">
                                <i class="bi bi-gear"></i> Тохиргоо
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
                    <h1 class="h2">Миний хичээлүүд</h1>
                </div>

                <?php if (empty($courses)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        Одоогоор ямар нэгэн хичээлд бүртгүүлээгүй байна.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($courses as $course): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card course-card">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($course['name']); ?></h5>
                                        <p class="text-muted mb-3">
                                            <i class="bi bi-person"></i> <?php echo htmlspecialchars($course['teacher_name']); ?>
                                        </p>
                                        
                                        <div class="mb-3">
                                            <span class="badge bg-primary stat-badge">
                                                <i class="bi bi-journal-text"></i> <?php echo $course['total_assignments']; ?> даалгавар
                                            </span>
                                            <span class="badge bg-success stat-badge">
                                                <i class="bi bi-check-circle"></i> <?php echo $course['submitted_assignments']; ?> илгээсэн
                                            </span>
                                            <span class="badge bg-info stat-badge">
                                                <i class="bi bi-star"></i> <?php echo number_format($course['average_grade'] ?? 0, 1); ?> дундаж
                                            </span>
                                        </div>

                                        <div class="mb-3">
                                            <span class="badge bg-secondary stat-badge">
                                                <i class="bi bi-file-earmark-text"></i> <?php echo $course['total_materials']; ?> материал
                                            </span>
                                            <span class="badge bg-secondary stat-badge">
                                                <i class="bi bi-bell"></i> <?php echo $course['total_announcements']; ?> мэдэгдэл
                                            </span>
                                        </div>

                                        <div class="d-grid gap-2">
                                            <a href="../courses/view.php?id=<?php echo $course['id']; ?>" class="btn btn-primary">
                                                <i class="bi bi-eye"></i> Дэлгэрэнгүй
                                            </a>
                                            <a href="../assignments/list.php?course_id=<?php echo $course['id']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-journal-text"></i> Даалгаврууд
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 