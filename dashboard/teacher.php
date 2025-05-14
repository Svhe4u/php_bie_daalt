<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

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
        (SELECT AVG(grade) FROM grades WHERE course_id = c.id) as average_grade
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

// Get recent grades
$stmt = $conn->prepare("
    SELECT 
        g.id,
        g.grade,
        g.feedback,
        g.updated_at,
        c.name as course_name,
        u.name as student_name
    FROM grades g
    JOIN courses c ON g.course_id = c.id
    JOIN users u ON g.student_id = u.id
    WHERE c.teacher_id = ?
    ORDER BY g.updated_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recent_grades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
        <?php
        // Get students who haven't received grades
        $stmt = $conn->prepare("
            SELECT 
                c.id as course_id,
                c.name as course_name,
                COUNT(DISTINCT ce.student_id) as ungraded_count
            FROM courses c
            JOIN course_enrollments ce ON c.id = ce.course_id
            LEFT JOIN grades g ON g.course_id = ce.course_id AND g.student_id = ce.student_id
            WHERE c.teacher_id = ? AND g.id IS NULL
            GROUP BY c.id
            HAVING ungraded_count > 0
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $ungraded_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (!empty($ungraded_courses)): ?>
            <div class="alert alert-warning mb-4">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
                    <div>
                        <h5 class="alert-heading mb-1">Дүн оруулаагүй оюутнууд байна!</h5>
                        <p class="mb-0">Дараах хичээлүүдэд дүн оруулаагүй оюутнууд байна:</p>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($ungraded_courses as $course): ?>
                                <li>
                                    <a href="../evaluation/view.php?course_id=<?php echo $course['course_id']; ?>" 
                                       class="alert-link">
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                        <span class="badge bg-warning text-dark ms-2">
                                            <?php echo $course['ungraded_count']; ?> оюутан
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <!-- Enrollment Requests Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Элсэх хүсэлтүүд</h4>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get pending enrollment requests
                        $stmt = $conn->prepare("
                            SELECT 
                                er.id as request_id,
                                er.created_at as requested_at,
                                c.id as course_id,
                                c.name as course_name,
                                u.id as student_id,
                                u.name as student_name,
                                u.email as student_email
                            FROM enrollment_requests er
                            JOIN courses c ON er.course_id = c.id
                            JOIN users u ON er.student_id = u.id
                            WHERE c.teacher_id = ? AND er.status = 'pending'
                            ORDER BY er.created_at DESC
                        ");
                        $stmt->bind_param("i", $_SESSION['user_id']);
                        $stmt->execute();
                        $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        ?>

                        <?php if (empty($requests)): ?>
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                                    Хүсэлт байхгүй байна
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Хичээл</th>
                                            <th>Оюутан</th>
                                            <th>И-мэйл</th>
                                            <th>Хүсэлт</th>
                                            <th>Үйлдэл</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requests as $request): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-book text-primary me-2"></i>
                                                        <?php echo htmlspecialchars($request['course_name']); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($request['student_name']); ?></td>
                                                <td><?php echo htmlspecialchars($request['student_email']); ?></td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('Y-m-d H:i', strtotime($request['requested_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <form action="../courses/handle_request.php" method="POST" class="d-inline">
                                                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                            <input type="hidden" name="course_id" value="<?php echo $request['course_id']; ?>">
                                                            <input type="hidden" name="student_id" value="<?php echo $request['student_id']; ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <button type="submit" class="btn btn-success btn-sm">
                                                                <i class="bi bi-check-lg"></i> Зөвшөөрөх
                                                            </button>
                                                        </form>
                                                        <form action="../courses/handle_request.php" method="POST" class="d-inline ms-1">
                                                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Энэ хүсэлтийг татгалзах уу?')">
                                                                <i class="bi bi-x-lg"></i> Татгалзах
                                                            </button>
                                                        </form>
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
                                                        <?php echo $course['student_count']; ?> оюутан
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($course['total_evaluations'] > 0): ?>
                                                        <div class="d-flex align-items-center">
                                                            <div class="rating me-2">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <i class="bi bi-star<?php echo $i <= round($course['average_score']) ? '-fill' : ''; ?> text-warning"></i>
                                                                <?php endfor; ?>
                                                            </div>
                                                            <span class="text-muted small">
                                                                (<?php echo $course['total_evaluations']; ?>)
                                                            </span>
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
                                                        <small class="text-muted ms-1">
                                                            (<?php echo $course['graded_count']; ?>)
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted">Дүн байхгүй</span>
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

                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow-sm mb-4">
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
                                        <div class="d-flex justify-content-between align-items-start mb-3">
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
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h4 class="mb-0">Сүүлийн дүнгүүд</h4>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_grades)): ?>
                                    <div class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="bi bi-check-circle fs-4 d-block mb-2"></i>
                                            Дүн байхгүй байна
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_grades as $grade): ?>
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($grade['course_name']); ?></h6>
                                                <p class="mb-1"><?php echo htmlspecialchars($grade['feedback']); ?></p>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($grade['student_name']); ?> - 
                                                    <?php echo date('Y-m-d', strtotime($grade['updated_at'])); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?php echo $grade['grade'] >= 60 ? 'success' : 'danger'; ?>">
                                                    <?php echo number_format($grade['grade'], 1); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
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
                        $total_grades = array_sum(array_column($courses, 'graded_count'));
                        
                        $avg_grade = 0;
                        if ($total_grades > 0) {
                            $avg_grade = array_sum(array_map(function($course) {
                                return $course['average_grade'] * $course['graded_count'];
                            }, $courses)) / $total_grades;
                        }
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
                        <div class="d-flex justify-content-between mb-3">
                            <span>Нийт дүн:</span>
                            <span class="fw-bold"><?php echo $total_grades; ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Дундаж дүн:</span>
                            <span class="fw-bold">
                                <?php echo number_format($avg_grade, 1); ?>
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
        // Get enrolled students for this course with grades
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
                g.updated_at as grade_updated_at
            FROM course_enrollments ce
            JOIN users u ON ce.student_id = u.id
            LEFT JOIN evaluations e ON e.course_id = ce.course_id AND e.student_id = ce.student_id
            LEFT JOIN grades g ON g.course_id = ce.course_id AND g.student_id = ce.student_id
            WHERE ce.course_id = ?
            ORDER BY u.name
        ");
        $stmt->bind_param("i", $course['id']);
        $stmt->execute();
        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
                                                    <?php if ($student['grade'] !== null): ?>
                                                        <span class="badge bg-<?php echo $student['grade'] >= 60 ? 'success' : 'danger'; ?>">
                                                            <?php echo number_format($student['grade'], 1); ?>
                                                        </span>
                                                        <?php if ($student['grade_feedback']): ?>
                                                            <i class="bi bi-info-circle text-primary ms-1" 
                                                               data-bs-toggle="tooltip" 
                                                               title="<?php echo htmlspecialchars($student['grade_feedback']); ?>"></i>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Дүн байхгүй</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('Y-m-d', strtotime($student['enrolled_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-primary"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#gradeModal<?php echo $course['id']; ?>_<?php echo $student['id']; ?>">
                                                            <i class="bi bi-pencil"></i> Дүн
                                                        </button>
                                                        <form action="../courses/unenroll.php" method="POST" class="d-inline" onsubmit="return confirm('Энэ оюутныг хичээлээс хасах уу?');">
                                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                <i class="bi bi-person-dash"></i> Хасах
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Grade Modal -->
                                            <div class="modal fade" id="gradeModal<?php echo $course['id']; ?>_<?php echo $student['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">
                                                                <?php echo htmlspecialchars($student['name']); ?> - Дүн оруулах
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form action="../evaluation/view.php" method="POST">
                                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                                            <div class="modal-body">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Дүн</label>
                                                                    <input type="number" 
                                                                           class="form-control" 
                                                                           name="grade" 
                                                                           min="0" 
                                                                           max="100" 
                                                                           step="0.1"
                                                                           value="<?php echo $student['grade'] ?? ''; ?>"
                                                                           required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Тайлбар</label>
                                                                    <textarea class="form-control" 
                                                                              name="feedback" 
                                                                              rows="3"><?php echo $student['grade_feedback'] ?? ''; ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                                                                <button type="submit" class="btn btn-primary">
                                                                    <i class="bi bi-save me-1"></i>Хадгалах
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
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
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    </script>
</body>
</html> 