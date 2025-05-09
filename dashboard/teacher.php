<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get teacher's courses with evaluation statistics
$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.name,
        u.name as teacher_name,
        c.created_at,
        (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) as student_count,
        (SELECT COUNT(*) FROM evaluations WHERE course_id = c.id) as total_evaluations,
        (SELECT AVG(score) FROM evaluations WHERE course_id = c.id) as average_score
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    WHERE c.teacher_id = ?
    GROUP BY c.id
    ORDER BY c.name
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent evaluations
$stmt = $conn->prepare("
    SELECT 
        e.id,
        e.score,
        e.comment,
        e.created_at,
        c.name as course_name,
        u.name as student_name
    FROM evaluations e
    JOIN courses c ON e.course_id = c.id
    JOIN users u ON e.student_id = u.id
    WHERE c.teacher_id = ?
    ORDER BY e.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recent_evaluations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Багшийн хяналтын самбар - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
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
                                            <th>Оюутны тоо</th>
                                            <th>Үнэлгээ</th>
                                            <th>Дундаж оноо</th>
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
                                                        <?php echo $course['student_count']; ?> оюутан
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <i class="bi bi-star me-1"></i>
                                                        <?php echo $course['total_evaluations']; ?> үнэлгээ
                                                    </span>
                                                </td>
                                                <td>
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
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="../evaluation/view.php?course_id=<?php echo $course['id']; ?>" 
                                                           class="btn btn-primary btn-sm">
                                                            <i class="bi bi-eye"></i> Үнэлгээ
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-info btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#studentsModal<?php echo $course['id']; ?>">
                                                            <i class="bi bi-people"></i> Оюутан
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

                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Сүүлийн үнэлгээнүүд</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_evaluations)): ?>
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-star fs-4 d-block mb-2"></i>
                                    Үнэлгээ байхгүй байна
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_evaluations as $eval): ?>
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($eval['course_name']); ?></h6>
                                            <p class="mb-1"><?php echo htmlspecialchars($eval['comment']); ?></p>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($eval['student_name']); ?> - 
                                                <?php echo date('Y-m-d', strtotime($eval['created_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <div class="rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?php echo $i <= $eval['score'] ? '-fill' : ''; ?> text-warning"></i>
                                                <?php endfor; ?>
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
                        <h4 class="mb-0">Статистик</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        $total_courses = count($courses);
                        $total_students = array_sum(array_column($courses, 'student_count'));
                        $total_evaluations = array_sum(array_column($courses, 'total_evaluations'));
                        $avg_score = array_sum(array_column($courses, 'average_score')) / max($total_courses, 1);
                        ?>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Нийт хичээл:</span>
                            <span class="fw-bold"><?php echo $total_courses; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Нийт оюутан:</span>
                            <span class="fw-bold"><?php echo $total_students; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Нийт үнэлгээ:</span>
                            <span class="fw-bold"><?php echo $total_evaluations; ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Дундаж оноо:</span>
                            <span class="fw-bold">
                                <?php echo number_format($avg_score, 1); ?>/5
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Modals -->
    <?php foreach ($courses as $course): ?>
        <?php
        // Get enrolled students for this course
        $stmt = $conn->prepare("
            SELECT 
                u.id,
                u.name,
                u.email,
                ce.enrolled_at,
                e.score,
                e.comment,
                e.created_at as evaluation_date
            FROM course_enrollments ce
            JOIN users u ON ce.student_id = u.id
            LEFT JOIN evaluations e ON e.course_id = ce.course_id AND e.student_id = ce.student_id
            WHERE ce.course_id = ?
            ORDER BY u.name
        ");
        $stmt->bind_param("i", $course['id']);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Get all students not enrolled in this course
        $stmt = $conn->prepare("
            SELECT id, name, email
            FROM users
            WHERE role = 'student'
            AND id NOT IN (
                SELECT student_id 
                FROM course_enrollments 
                WHERE course_id = ?
            )
            ORDER BY name
        ");
        $stmt->bind_param("i", $course['id']);
        $stmt->execute();
        $available_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        ?>
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
                        <div class="mb-4">
                            <button type="button" 
                                    class="btn btn-primary" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#addStudentForm<?php echo $course['id']; ?>">
                                <i class="bi bi-person-plus"></i> Оюутан нэмэх
                            </button>
                            
                            <div class="collapse mt-3" id="addStudentForm<?php echo $course['id']; ?>">
                                <div class="card card-body">
                                    <form action="../courses/enroll.php" method="POST">
                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                        <div class="mb-3">
                                            <label for="student_id" class="form-label">Оюутан сонгох</label>
                                            <select class="form-select" name="student_id" required>
                                                <option value="">Оюутан сонгоно уу</option>
                                                <?php foreach ($available_students as $student): ?>
                                                    <option value="<?php echo $student['id']; ?>">
                                                        <?php echo htmlspecialchars($student['name']); ?> 
                                                        (<?php echo htmlspecialchars($student['email']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-circle"></i> Нэмэх
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

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
                                            <th>Сэтгэгдэл</th>
                                            <th>Элссэн</th>
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
                                                                <i class="bi bi-star<?php echo $i <= $student['score'] ? '-fill' : ''; ?> text-warning"></i>
                                                            <?php endfor; ?>
                                                            <span class="ms-1"><?php echo $student['score']; ?>/5</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Үнэлгээ байхгүй</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($student['comment']): ?>
                                                        <?php echo htmlspecialchars($student['comment']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Сэтгэгдэл байхгүй</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('Y-m-d', strtotime($student['enrolled_at'])); ?></td>
                                                <td>
                                                    <form action="../courses/unenroll.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('Энэ оюутныг хичээлээс хасах уу?')">
                                                            <i class="bi bi-person-dash"></i>
                                                        </button>
                                                    </form>
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
</body>
</html> 