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
        c.teacher_id,
        u.name as teacher_name,
        (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) as total_students,
        (SELECT COUNT(*) FROM evaluations WHERE course_id = c.id) as total_evaluations,
        (SELECT AVG(score) FROM evaluations WHERE course_id = c.id) as average_score,
        (SELECT COUNT(*) FROM grades WHERE course_id = c.id) as graded_count,
        (SELECT AVG(grade) FROM grades WHERE course_id = c.id) as average_grade
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

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['role'] === 'teacher') {
    $student_id = filter_var($_POST['student_id'], FILTER_VALIDATE_INT);
    $grade = filter_var($_POST['grade'], FILTER_VALIDATE_FLOAT);
    $feedback = trim($_POST['feedback'] ?? '');

    if ($student_id && $grade >= 0 && $grade <= 100) {
        // Check if grade already exists
        $stmt = $conn->prepare("SELECT id FROM grades WHERE course_id = ? AND student_id = ?");
        $stmt->bind_param("ii", $course_id, $student_id);
        $stmt->execute();
        $existing_grade = $stmt->get_result()->fetch_assoc();

        if ($existing_grade) {
            // Update existing grade
            $stmt = $conn->prepare("UPDATE grades SET grade = ?, feedback = ?, updated_at = NOW() WHERE course_id = ? AND student_id = ?");
            $stmt->bind_param("dsii", $grade, $feedback, $course_id, $student_id);
        } else {
            // Insert new grade
            $stmt = $conn->prepare("INSERT INTO grades (course_id, student_id, grade, feedback) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iids", $course_id, $student_id, $grade, $feedback);
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = "Оюутны дүн амжилттай хадгалагдлаа.";
        } else {
            $_SESSION['error'] = "Дүн хадгалахад алдаа гарлаа.";
        }
    } else {
        $_SESSION['error'] = "Дүн буруу байна.";
    }
    header("Location: view.php?course_id=" . $course_id);
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

// Get all enrolled students with their grades
$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.name,
        u.email,
        ce.enrolled_at,
        e.score,
        e.created_at as evaluation_date,
        g.grade,
        g.feedback,
        g.updated_at as grade_updated_at
    FROM course_enrollments ce
    JOIN users u ON ce.student_id = u.id
    LEFT JOIN evaluations e ON e.course_id = ce.course_id AND e.student_id = ce.student_id
    LEFT JOIN grades g ON g.course_id = ce.course_id AND g.student_id = ce.student_id
    WHERE ce.course_id = ?
    ORDER BY u.name
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$enrolled_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

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
                <div class="card shadow-sm mb-4">
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
                        <div class="d-flex justify-content-between mb-3">
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
                        <div class="d-flex justify-content-between">
                            <span>Дундаж дүн:</span>
                            <span class="fw-bold">
                                <?php if ($course['graded_count'] > 0): ?>
                                    <span class="badge bg-<?php echo $course['average_grade'] >= 60 ? 'success' : 'danger'; ?>">
                                        <?php echo number_format($course['average_grade'], 1); ?>
                                    </span>
                                    <small class="text-muted ms-1">
                                        (<?php echo $course['graded_count']; ?>)
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">Дүн байхгүй</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Элссэн оюутнууд</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($enrolled_students)): ?>
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-people fs-4 d-block mb-2"></i>
                                    Оюутан байхгүй байна
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($enrolled_students as $student): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($student['name']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($student['email']); ?>
                                                </small>
                                            </div>
                                            <?php if ($student['grade'] !== null): ?>
                                                <div class="text-end">
                                                    <span class="badge bg-<?php echo $student['grade'] >= 60 ? 'success' : 'danger'; ?>">
                                                        <?php echo number_format($student['grade'], 1); ?>
                                                    </span>
                                                    <?php if ($_SESSION['role'] === 'teacher'): ?>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-primary ms-2"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#gradeModal<?php echo $student['id']; ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <?php if ($_SESSION['role'] === 'teacher'): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-primary"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#gradeModal<?php echo $student['id']; ?>">
                                                        <i class="bi bi-plus"></i> Дүн оруулах
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Дүн байхгүй</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            <i class="bi bi-calendar3"></i>
                                            Элссэн: <?php echo date('Y-m-d', strtotime($student['enrolled_at'])); ?>
                                            <?php if ($student['evaluation_date']): ?>
                                                <br>
                                                <i class="bi bi-star"></i>
                                                Үнэлсэн: <?php echo date('Y-m-d', strtotime($student['evaluation_date'])); ?>
                                            <?php endif; ?>
                                            <?php if ($student['grade_updated_at']): ?>
                                                <br>
                                                <i class="bi bi-check-circle"></i>
                                                Дүн шинэчлэгдсэн: <?php echo date('Y-m-d', strtotime($student['grade_updated_at'])); ?>
                                            <?php endif; ?>
                                        </small>
                                        <?php if ($student['feedback']): ?>
                                            <div class="mt-2 small">
                                                <strong>Тайлбар:</strong> <?php echo htmlspecialchars($student['feedback']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($_SESSION['role'] === 'teacher'): ?>
                                        <!-- Grade Modal -->
                                        <div class="modal fade" id="gradeModal<?php echo $student['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">
                                                                <?php echo htmlspecialchars($student['name']); ?> - Дүн оруулах
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label for="grade<?php echo $student['id']; ?>" class="form-label">Дүн</label>
                                                                <input type="number" 
                                                                       class="form-control" 
                                                                       id="grade<?php echo $student['id']; ?>" 
                                                                       name="grade" 
                                                                       min="0" 
                                                                       max="100" 
                                                                       step="0.1" 
                                                                       value="<?php echo $student['grade'] ?? ''; ?>" 
                                                                       required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="feedback<?php echo $student['id']; ?>" class="form-label">Тайлбар</label>
                                                                <textarea class="form-control" 
                                                                          id="feedback<?php echo $student['id']; ?>" 
                                                                          name="feedback" 
                                                                          rows="3"><?php echo htmlspecialchars($student['feedback'] ?? ''); ?></textarea>
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
                                    <?php endif; ?>
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
