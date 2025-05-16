<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get teacher's courses with detailed statistics
$stmt = $conn->prepare("
    SELECT 
        c.*,
        u.name as teacher_name,
        (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) as student_count,
        (SELECT COUNT(*) FROM evaluations WHERE course_id = c.id) as total_evaluations,
        (SELECT AVG(score) FROM evaluations WHERE course_id = c.id) as average_score,
        (SELECT COUNT(*) FROM grades WHERE course_id = c.id) as graded_count,
        (SELECT AVG(grade) FROM grades WHERE course_id = c.id) as average_grade,
        (SELECT COUNT(*) FROM assignments WHERE course_id = c.id) as total_assignments,
        (SELECT COUNT(*) FROM assignment_submissions a 
         JOIN assignments ass ON a.assignment_id = ass.id 
         WHERE ass.course_id = c.id AND a.status = 'pending') as pending_submissions,
        (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as total_materials,
        (SELECT COUNT(*) FROM announcements WHERE course_id = c.id) as total_announcements
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    WHERE c.teacher_id = ?
    GROUP BY c.id
    ORDER BY c.name
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate overall statistics
$total_courses = count($courses);
$total_students = array_sum(array_column($courses, 'student_count'));
$total_assignments = array_sum(array_column($courses, 'total_assignments'));
$total_pending = array_sum(array_column($courses, 'pending_submissions'));
$total_evaluations = array_sum(array_column($courses, 'total_evaluations'));
$total_materials = array_sum(array_column($courses, 'total_materials'));
$total_announcements = array_sum(array_column($courses, 'total_announcements'));

// Calculate average scores and grades
$average_score = 0;
if ($total_evaluations > 0) {
    $total_score = array_sum(array_map(function($course) {
        return $course['average_score'] * $course['total_evaluations'];
    }, $courses));
    $average_score = $total_score / $total_evaluations;
}

$total_grades = array_sum(array_column($courses, 'graded_count'));
$average_grade = 0;
if ($total_grades > 0) {
    $total_grade = array_sum(array_map(function($course) {
        return $course['average_grade'] * $course['graded_count'];
    }, $courses));
    $average_grade = $total_grade / $total_grades;
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
    <style>
        .course-card {
            transition: transform 0.2s;
        }
        .course-card:hover {
            transform: translateY(-5px);
        }
        .stat-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Хичээлүүд</h1>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                    <i class="bi bi-plus-circle"></i> Шинэ хичээл
                </button>
                <a href="teacher.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-arrow-left"></i> Буцах
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Нийт хичээл</h5>
                        <h2 class="mb-0"><?php echo $total_courses; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Нийт оюутан</h5>
                        <h2 class="mb-0"><?php echo $total_students; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Нийт даалгавар</h5>
                        <h2 class="mb-0"><?php echo $total_assignments; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Хүлээгдэж буй</h5>
                        <h2 class="mb-0"><?php echo $total_pending; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Grid -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($courses as $course): ?>
                <div class="col">
                    <div class="card h-100 course-card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">
                                <i class="bi bi-book text-primary me-2"></i>
                                <?php echo htmlspecialchars($course['name']); ?>
                            </h5>
                            
                            <!-- Course Statistics -->
                            <div class="mb-3">
                                <span class="badge bg-info stat-badge me-2 mb-2">
                                    <i class="bi bi-people me-1"></i>
                                    <?php echo $course['student_count']; ?> оюутан
                                </span>
                                <span class="badge bg-success stat-badge me-2 mb-2">
                                    <i class="bi bi-file-text me-1"></i>
                                    <?php echo $course['total_assignments']; ?> даалгавар
                                </span>
                                <?php if ($course['pending_submissions'] > 0): ?>
                                    <span class="badge bg-warning stat-badge me-2 mb-2">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo $course['pending_submissions']; ?> хүлээгдэж буй
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Course Details -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Үнэлгээ:</span>
                                    <?php if ($course['total_evaluations'] > 0): ?>
                                        <div class="rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?php echo $i <= round($course['average_score']) ? '-fill' : ''; ?> text-warning"></i>
                                            <?php endfor; ?>
                                            <span class="ms-1"><?php echo number_format($course['average_score'], 1); ?>/5</span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Үнэлгээ байхгүй</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Дундаж дүн:</span>
                                    <?php if ($course['graded_count'] > 0): ?>
                                        <span class="badge bg-<?php echo $course['average_grade'] >= 60 ? 'success' : 'danger'; ?>">
                                            <?php echo number_format($course['average_grade'], 1); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Дүн байхгүй</span>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Материал:</span>
                                    <span><?php echo $course['total_materials']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Зарлал:</span>
                                    <span><?php echo $course['total_announcements']; ?></span>
                                </div>
                            </div>

                            <!-- Course Actions -->
                            <div class="btn-group w-100">
                                <a href="course.php?id=<?php echo $course['id']; ?>" 
                                   class="btn btn-primary">
                                    <i class="bi bi-eye"></i> Дэлгэрэнгүй
                                </a>
                                <button type="button" 
                                        class="btn btn-info" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#studentsModal<?php echo $course['id']; ?>">
                                    <i class="bi bi-people"></i> Оюутнууд
                                </button>
                                <button type="button" 
                                        class="btn btn-warning" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editModal<?php echo $course['id']; ?>">
                                    <i class="bi bi-pencil"></i> Засах
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($courses)): ?>
            <div class="text-center py-5">
                <div class="text-muted">
                    <i class="bi bi-book fs-1 d-block mb-3"></i>
                    Хичээл байхгүй байна
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                    <i class="bi bi-plus-circle"></i> Шинэ хичээл нэмэх
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Шинэ хичээл</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="../courses/create.php" method="POST">
                        <div class="mb-3">
                            <label for="name" class="form-label">Хичээлийн нэр</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Тайлбар</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Нэмэх
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Course Modals -->
    <?php foreach ($courses as $course): ?>
        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?php echo $course['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Хичээл засах</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form action="../courses/update.php" method="POST">
                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                            <div class="mb-3">
                                <label for="name<?php echo $course['id']; ?>" class="form-label">Хичээлийн нэр</label>
                                <input type="text" class="form-control" id="name<?php echo $course['id']; ?>" 
                                       name="name" value="<?php echo htmlspecialchars($course['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="description<?php echo $course['id']; ?>" class="form-label">Тайлбар</label>
                                <textarea class="form-control" id="description<?php echo $course['id']; ?>" 
                                          name="description" rows="3"><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Хадгалах
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Students Modal -->
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
                        <?php
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
                                g.updated_at as grade_updated_at,
                                (SELECT COUNT(*) FROM assignment_submissions a 
                                 JOIN assignments ass ON a.assignment_id = ass.id 
                                 WHERE ass.course_id = c.id AND a.student_id = u.id) as total_submissions,
                                (SELECT COUNT(*) FROM assignment_submissions a 
                                 JOIN assignments ass ON a.assignment_id = ass.id 
                                 WHERE ass.course_id = c.id AND a.student_id = u.id AND a.status = 'pending') as pending_submissions
                            FROM course_enrollments ce
                            JOIN users u ON ce.student_id = u.id
                            JOIN courses c ON ce.course_id = c.id
                            LEFT JOIN evaluations e ON e.course_id = c.id AND e.student_id = ce.student_id
                            LEFT JOIN grades g ON g.course_id = c.id AND g.student_id = ce.student_id
                            WHERE ce.course_id = ?
                            ORDER BY u.name
                        ");
                        $stmt->bind_param("i", $course['id']);
                        $stmt->execute();
                        $students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        ?>

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
                                            <th>Даалгавар</th>
                                            <th>Үнэлгээ</th>
                                            <th>Дүн</th>
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
                                                    <?php if ($student['total_submissions'] > 0): ?>
                                                        <span class="badge bg-<?php echo $student['pending_submissions'] > 0 ? 'warning' : 'success'; ?>">
                                                            <?php echo $student['total_submissions']; ?>
                                                            <?php if ($student['pending_submissions'] > 0): ?>
                                                                (<?php echo $student['pending_submissions']; ?> хүлээгдэж буй)
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
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
                                                    <?php else: ?>
                                                        <span class="text-muted">Дүн байхгүй</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="student.php?id=<?php echo $student['id']; ?>&course_id=<?php echo $course['id']; ?>" 
                                                           class="btn btn-primary btn-sm">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-warning btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#gradeModal<?php echo $course['id']; ?>_<?php echo $student['id']; ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-info btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#messageModal<?php echo $course['id']; ?>_<?php echo $student['id']; ?>">
                                                            <i class="bi bi-envelope"></i>
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
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 