<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check if course_id is provided
if (!isset($_GET['course_id'])) {
    header("Location: ../dashboard/" . $_SESSION['role'] . ".php");
    exit();
}

$course_id = filter_var($_GET['course_id'], FILTER_VALIDATE_INT);

// Get course details
$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.name as course_name,
        u.name as teacher_name,
        (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) as total_students,
        (SELECT COUNT(*) FROM evaluations WHERE course_id = c.id) as total_evaluations,
        (SELECT AVG(score) FROM evaluations WHERE course_id = c.id) as average_score
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    WHERE c.id = ?
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    header("Location: ../dashboard/" . $_SESSION['role'] . ".php");
    exit();
}

// Check if user has permission to view this course
if ($_SESSION['role'] === 'teacher' && $course['teacher_id'] != $_SESSION['user_id']) {
    header("Location: ../dashboard/teacher.php");
    exit();
}

// Get evaluations
$stmt = $conn->prepare("
    SELECT 
        e.id,
        e.score,
        e.comment,
        e.created_at,
        u.name as student_name,
        u.email as student_email
    FROM evaluations e
    JOIN users u ON e.student_id = u.id
    WHERE e.course_id = ?
    ORDER BY e.created_at DESC
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$evaluations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Хичээлийн үнэлгээ - <?php echo htmlspecialchars($course['course_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard/<?php echo $_SESSION['role']; ?>.php">
                <img src="../assets/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                Хичээлийн үнэлгээ
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
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0"><?php echo htmlspecialchars($course['course_name']); ?></h4>
                                <small class="text-muted">
                                    <i class="bi bi-person text-primary me-1"></i>
                                    Багш: <?php echo htmlspecialchars($course['teacher_name']); ?>
                                </small>
                            </div>
                            <a href="../dashboard/<?php echo $_SESSION['role']; ?>.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-arrow-left"></i> Буцах
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($evaluations)): ?>
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-star fs-4 d-block mb-2"></i>
                                    Үнэлгээ байхгүй байна
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($evaluations as $eval): ?>
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($eval['student_name']); ?></h6>
                                            <p class="mb-1"><?php echo htmlspecialchars($eval['comment']); ?></p>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($eval['student_email']); ?> - 
                                                <?php echo date('Y-m-d', strtotime($eval['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <div class="rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?php echo $i <= $eval['score'] ? '-fill' : ''; ?> text-warning"></i>
                                                <?php endfor; ?>
                                                <span class="ms-1"><?php echo $eval['score']; ?>/5</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Статистик</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Нийт оюутан:</span>
                            <span class="fw-bold"><?php echo $course['total_students']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Нийт үнэлгээ:</span>
                            <span class="fw-bold"><?php echo $course['total_evaluations']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Дундаж оноо:</span>
                            <span class="fw-bold">
                                <?php if ($course['average_score']): ?>
                                    <div class="rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?php echo $i <= round($course['average_score']) ? '-fill' : ''; ?> text-warning"></i>
                                        <?php endfor; ?>
                                        <span class="ms-1"><?php echo number_format($course['average_score'], 1); ?>/5</span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Үнэлгээ байхгүй</span>
                                <?php endif; ?>
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
