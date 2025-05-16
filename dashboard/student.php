<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Get student's enrolled courses
$stmt = $conn->prepare("
    SELECT c.*, u.name as teacher_name 
    FROM courses c 
    JOIN course_enrollments ce ON c.id = ce.course_id 
    JOIN users u ON c.teacher_id = u.id 
    WHERE ce.student_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get upcoming assignments
$stmt = $conn->prepare("
    SELECT a.*, c.name as course_name 
    FROM assignments a 
    JOIN courses c ON a.course_id = c.id 
    JOIN course_enrollments ce ON c.id = ce.course_id 
    WHERE ce.student_id = ? 
    AND a.due_date > NOW() 
    ORDER BY a.due_date ASC 
    LIMIT 5
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$upcoming_assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent announcements
$stmt = $conn->prepare("
    SELECT a.*, c.name as course_name 
    FROM announcements a 
    JOIN courses c ON a.course_id = c.id 
    JOIN course_enrollments ce ON c.id = ce.course_id 
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
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оюутны Хяналтын Самбар</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="student.php">
                <img src="../assets/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                Сургалтын Үнэлгээний Систем
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
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
        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-book text-primary me-2"></i>
                            Нийт хичээл
                        </h5>
                        <h2 class="mb-0"><?php echo count($courses); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-journal-text text-primary me-2"></i>
                            Ирэх даалгавар
                        </h5>
                        <h2 class="mb-0"><?php echo count($upcoming_assignments); ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-bell text-primary me-2"></i>
                            Шинэ мэдэгдэл
                        </h5>
                        <h2 class="mb-0"><?php echo count($announcements); ?></h2>
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
                                            <?php echo htmlspecialchars($announcement['course_name']); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Grades -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-star text-primary me-2"></i>
                            Дүнгүүд
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 