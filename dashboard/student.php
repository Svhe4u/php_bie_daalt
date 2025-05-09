<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Get student's enrolled courses with evaluation data
$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.name,
        u.name as teacher_name,
        e.score,
        e.comment,
        e.created_at as evaluation_date,
        ce.enrolled_at
    FROM course_enrollments ce
    JOIN courses c ON ce.course_id = c.id
    JOIN users u ON c.teacher_id = u.id
    LEFT JOIN evaluations e ON e.course_id = c.id AND e.student_id = ce.student_id
    WHERE ce.student_id = ?
    ORDER BY c.name
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$total_courses = count($courses);
$evaluated_courses = count(array_filter($courses, function($course) {
    return !is_null($course['score']);
}));
$average_score = 0;
if ($evaluated_courses > 0) {
    $total_score = array_sum(array_column($courses, 'score'));
    $average_score = $total_score / $evaluated_courses;
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
        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Миний хичээлүүд</h4>
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
                                            <th>Үнэлгээ</th>
                                            <th>Сэтгэгдэл</th>
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
                                                    <?php if ($course['score']): ?>
                                                        <div class="rating">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="bi bi-star<?php echo $i <= $course['score'] ? '-fill' : ''; ?> text-warning"></i>
                                                            <?php endfor; ?>
                                                            <span class="ms-1"><?php echo $course['score']; ?>/5</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Үнэлгээ байхгүй</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($course['comment']): ?>
                                                        <?php echo htmlspecialchars($course['comment']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Сэтгэгдэл байхгүй</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
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
            
            <div class="col-md-4">
                <div class="card shadow-sm">
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
                        <div class="d-flex justify-content-between">
                            <span>Дундаж оноо:</span>
                            <span class="fw-bold">
                                <?php echo number_format($average_score, 1); ?>/5
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 