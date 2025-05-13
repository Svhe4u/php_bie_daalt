<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get all courses with teacher information and enrollment status
$query = "
    SELECT 
        c.id,
        c.name,
        u.name as teacher_name,
        CASE 
            WHEN ce.student_id IS NOT NULL THEN 1 
            ELSE 0 
        END as is_enrolled
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    LEFT JOIN course_enrollments ce ON c.id = ce.course_id AND ce.student_id = ?
    ORDER BY c.name
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get enrollment statistics for each course
$enrollment_stats = [];
foreach ($courses as $course) {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_students,
            COUNT(CASE WHEN e.score IS NOT NULL THEN 1 END) as evaluated_count,
            AVG(e.score) as avg_score
        FROM course_enrollments ce
        LEFT JOIN evaluations e ON e.course_id = ce.course_id AND e.student_id = ce.student_id
        WHERE ce.course_id = ?
    ");
    $stmt->bind_param("i", $course['id']);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $enrollment_stats[$course['id']] = $stats;
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Хичээлүүд - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard/<?php echo $_SESSION['role']; ?>.php">
                <img src="../assets/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                Хичээлүүд
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard/<?php echo $_SESSION['role']; ?>.php">
                            <i class="bi bi-speedometer2 me-1"></i>Хяналтын самбар
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">
                            <i class="bi bi-box-arrow-right me-1"></i>Гарах
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h4 class="card-title mb-3">
                            <i class="bi bi-book me-2"></i>Боломжтой хичээлүүд
                        </h4>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php foreach ($courses as $course): ?>
                                <div class="col">
                                    <div class="card h-100 shadow-sm">
                                        <div class="card-body">
                                            <h5 class="card-title">
                                                <?php echo htmlspecialchars($course['name']); ?>
                                                <?php if ($course['is_enrolled']): ?>
                                                    <span class="badge bg-success ms-2">Элссэн</span>
                                                <?php endif; ?>
                                            </h5>
                                            <p class="card-text text-muted">
                                                <i class="bi bi-person me-1"></i>
                                                <?php echo htmlspecialchars($course['teacher_name']); ?>
                                            </p>
                                            
                                            <div class="mt-3">
                                                <div class="d-flex justify-content-between text-muted small mb-2">
                                                    <span>
                                                        <i class="bi bi-people me-1"></i>
                                                        <?php echo $enrollment_stats[$course['id']]['total_students']; ?> оюутан
                                                    </span>
                                                    <?php if ($enrollment_stats[$course['id']]['evaluated_count'] > 0): ?>
                                                        <span>
                                                            <i class="bi bi-star-fill text-warning me-1"></i>
                                                            <?php echo number_format($enrollment_stats[$course['id']]['avg_score'], 1); ?>/5
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if ($_SESSION['role'] === 'student'): ?>
                                                    <?php if (!$course['is_enrolled']): ?>
                                                        <button type="button" 
                                                                class="btn btn-primary btn-sm w-100"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#enrollModal<?php echo $course['id']; ?>">
                                                            <i class="bi bi-plus-circle me-1"></i>Элсэх хүсэлт илгээх
                                                        </button>
                                                    <?php else: ?>
                                                        <a href="../dashboard/student.php" class="btn btn-outline-primary btn-sm w-100">
                                                            <i class="bi bi-check-circle me-1"></i>Элссэн
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($_SESSION['role'] === 'student' && !$course['is_enrolled']): ?>
                                    <!-- Enrollment Modal -->
                                    <div class="modal fade" id="enrollModal<?php echo $course['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Хичээлд элсэх хүсэлт</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>
                                                        Та <strong><?php echo htmlspecialchars($course['name']); ?></strong> 
                                                        хичээлд элсэх хүсэлт илгээхдээ итгэлтэй байна уу?
                                                    </p>
                                                    <p class="text-muted small">
                                                        Хүсэлт илгээсний дараа багш таны хүсэлтийг хянаж, батлах болно.
                                                    </p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                                                    <form action="enroll.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="bi bi-send me-1"></i>Хүсэлт илгээх
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 