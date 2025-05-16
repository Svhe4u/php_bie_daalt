<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Check if course ID is provided
if (!isset($_GET['id'])) {
    header("Location: courses.php");
    exit();
}

$course_id = $_GET['id'];

// Get course information
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
    WHERE c.id = ? AND c.teacher_id = ?
");
$stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    header("Location: courses.php");
    exit();
}

// Get course schedule
$stmt = $conn->prepare("
    SELECT *
    FROM course_schedule
    WHERE course_id = ?
    ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'),
             start_time ASC
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent announcements
$stmt = $conn->prepare("
    SELECT 
        a.*,
        u.name as author_name
    FROM announcements a
    JOIN users u ON a.author_id = u.id
    WHERE a.course_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent assignments
$stmt = $conn->prepare("
    SELECT 
        a.*,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) as submission_count,
        (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND status = 'pending') as pending_count
    FROM assignments a
    WHERE a.course_id = ?
    ORDER BY a.due_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get course materials
$stmt = $conn->prepare("
    SELECT *
    FROM materials
    WHERE course_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get student statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_students,
        SUM(CASE WHEN g.grade >= 60 THEN 1 ELSE 0 END) as passing_students,
        AVG(g.grade) as average_grade,
        MIN(g.grade) as min_grade,
        MAX(g.grade) as max_grade
    FROM course_enrollments ce
    LEFT JOIN grades g ON g.course_id = ce.course_id AND g.student_id = ce.student_id
    WHERE ce.course_id = ?
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['name']); ?> - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><?php echo htmlspecialchars($course['name']); ?></h1>
                <p class="text-muted mb-0">
                    <i class="bi bi-person text-primary"></i>
                    <?php echo htmlspecialchars($course['teacher_name']); ?>
                </p>
            </div>
            <div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#announcementModal">
                    <i class="bi bi-megaphone"></i> Зарлал
                </button>
                <button type="button" class="btn btn-success ms-2" data-bs-toggle="modal" data-bs-target="#assignmentModal">
                    <i class="bi bi-file-text"></i> Даалгавар
                </button>
                <button type="button" class="btn btn-info ms-2" data-bs-toggle="modal" data-bs-target="#materialModal">
                    <i class="bi bi-file-earmark"></i> Материал
                </button>
                <a href="courses.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-arrow-left"></i> Буцах
                </a>
            </div>
        </div>

        <!-- Course Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Оюутны тоо</h5>
                        <h2 class="mb-0"><?php echo $course['student_count']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Даалгавар</h5>
                        <h2 class="mb-0"><?php echo $course['total_assignments']; ?></h2>
                        <?php if ($course['pending_submissions'] > 0): ?>
                            <small><?php echo $course['pending_submissions']; ?> хүлээгдэж буй</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Үнэлгээ</h5>
                        <?php if ($course['total_evaluations'] > 0): ?>
                            <div class="rating mb-1">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star<?php echo $i <= round($course['average_score']) ? '-fill' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <small><?php echo number_format($course['average_score'], 1); ?>/5</small>
                        <?php else: ?>
                            <small>Үнэлгээ байхгүй</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Дундаж дүн</h5>
                        <?php if ($course['graded_count'] > 0): ?>
                            <h2 class="mb-0"><?php echo number_format($course['average_grade'], 1); ?></h2>
                            <small><?php echo $stats['passing_students']; ?> тэнцсэн</small>
                        <?php else: ?>
                            <small>Дүн байхгүй</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-md-8">
                <!-- Course Description -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Хичээлийн тайлбар</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($course['description']): ?>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                        <?php else: ?>
                            <p class="text-muted mb-0">Тайлбар байхгүй байна</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Course Schedule -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Хичээлийн хуваарь</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                            <i class="bi bi-plus-circle"></i> Нэмэх
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($schedule)): ?>
                            <p class="text-muted mb-0">Хуваарь байхгүй байна</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Гараг</th>
                                            <th>Эхлэх цаг</th>
                                            <th>Дуусах цаг</th>
                                            <th>Өрөө</th>
                                            <th>Үйлдэл</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($schedule as $class): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($class['day_of_week']); ?></td>
                                                <td><?php echo htmlspecialchars($class['start_time']); ?></td>
                                                <td><?php echo htmlspecialchars($class['end_time']); ?></td>
                                                <td><?php echo htmlspecialchars($class['room']); ?></td>
                                                <td>
                                                    <button type="button" 
                                                            class="btn btn-warning btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editScheduleModal<?php echo $class['id']; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" 
                                                            class="btn btn-danger btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteScheduleModal<?php echo $class['id']; ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Assignments -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Сүүлийн даалгаврууд</h5>
                        <a href="assignments.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-grid"></i> Бүгдийг харах
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assignments)): ?>
                            <p class="text-muted mb-0">Даалгавар байхгүй байна</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($assignments as $assignment): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                            <small class="text-<?php echo strtotime($assignment['due_date']) < time() ? 'danger' : 'muted'; ?>">
                                                <?php echo date('Y-m-d', strtotime($assignment['due_date'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-1 small"><?php echo htmlspecialchars($assignment['description']); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <?php echo $assignment['submission_count']; ?> илгээсэн
                                                <?php if ($assignment['pending_count'] > 0): ?>
                                                    (<?php echo $assignment['pending_count']; ?> хүлээгдэж буй)
                                                <?php endif; ?>
                                            </small>
                                            <div class="btn-group">
                                                <a href="assignment.php?id=<?php echo $assignment['id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="bi bi-eye"></i> Харах
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-warning btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editAssignmentModal<?php echo $assignment['id']; ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Recent Announcements -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Сүүлийн зарлалууд</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($announcements)): ?>
                            <p class="text-muted mb-0">Зарлал байхгүй байна</p>
                        <?php else: ?>
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="mb-3 pb-3 border-bottom">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                    <p class="mb-1 small"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($announcement['author_name']); ?> - 
                                        <?php echo date('Y-m-d', strtotime($announcement['created_at'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Course Materials -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Хичээлийн материал</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($materials)): ?>
                            <p class="text-muted mb-0">Материал байхгүй байна</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($materials as $material): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($material['title']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo date('Y-m-d', strtotime($material['created_at'])); ?>
                                            </small>
                                        </div>
                                        <p class="mb-1 small"><?php echo htmlspecialchars($material['description']); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <?php echo $material['file_type']; ?> файл
                                            </small>
                                            <div class="btn-group">
                                                <a href="../materials/download.php?id=<?php echo $material['id']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="bi bi-download"></i> Татах
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-danger btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteMaterialModal<?php echo $material['id']; ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Student Statistics -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Оюутны статистик</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Нийт оюутан:</span>
                            <span class="fw-bold"><?php echo $stats['total_students']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Тэнцсэн оюутан:</span>
                            <span class="fw-bold"><?php echo $stats['passing_students']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Дундаж дүн:</span>
                            <span class="fw-bold">
                                <?php if ($stats['average_grade']): ?>
                                    <?php echo number_format($stats['average_grade'], 1); ?>
                                <?php else: ?>
                                    <span class="text-muted">Дүн байхгүй</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Хамгийн бага дүн:</span>
                            <span class="fw-bold">
                                <?php if ($stats['min_grade']): ?>
                                    <?php echo number_format($stats['min_grade'], 1); ?>
                                <?php else: ?>
                                    <span class="text-muted">Дүн байхгүй</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Хамгийн өндөр дүн:</span>
                            <span class="fw-bold">
                                <?php if ($stats['max_grade']): ?>
                                    <?php echo number_format($stats['max_grade'], 1); ?>
                                <?php else: ?>
                                    <span class="text-muted">Дүн байхгүй</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Announcement Modal -->
    <div class="modal fade" id="announcementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Шинэ зарлал</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="../announcements/create.php" method="POST">
                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                        <div class="mb-3">
                            <label for="title" class="form-label">Гарчиг</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Агуулга</label>
                            <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Илгээх
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Assignment Modal -->
    <div class="modal fade" id="assignmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Шинэ даалгавар</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="../assignments/create.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                        <div class="mb-3">
                            <label for="title" class="form-label">Гарчиг</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Тайлбар</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="due_date" class="form-label">Дуусах огноо</label>
                            <input type="datetime-local" class="form-control" id="due_date" name="due_date" required>
                        </div>
                        <div class="mb-3">
                            <label for="file" class="form-label">Файл</label>
                            <input type="file" class="form-control" id="file" name="file">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Нэмэх
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Material Modal -->
    <div class="modal fade" id="materialModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Шинэ материал</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="../materials/create.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                        <div class="mb-3">
                            <label for="title" class="form-label">Гарчиг</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Тайлбар</label>
                            <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="file" class="form-label">Файл</label>
                            <input type="file" class="form-control" id="file" name="file" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Нэмэх
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Хичээлийн хуваарь нэмэх</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="../schedule/create.php" method="POST">
                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">
                        <div class="mb-3">
                            <label for="day_of_week" class="form-label">Гараг</label>
                            <select class="form-select" id="day_of_week" name="day_of_week" required>
                                <option value="Monday">Даваа</option>
                                <option value="Tuesday">Мягмар</option>
                                <option value="Wednesday">Лхагва</option>
                                <option value="Thursday">Пүрэв</option>
                                <option value="Friday">Баасан</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="start_time" class="form-label">Эхлэх цаг</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="end_time" class="form-label">Дуусах цаг</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="room" class="form-label">Өрөө</label>
                            <input type="text" class="form-control" id="room" name="room" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Нэмэх
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Edit Modals -->
    <?php foreach ($schedule as $class): ?>
        <div class="modal fade" id="editScheduleModal<?php echo $class['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Хуваарь засах</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form action="../schedule/update.php" method="POST">
                            <input type="hidden" name="schedule_id" value="<?php echo $class['id']; ?>">
                            <div class="mb-3">
                                <label for="day_of_week<?php echo $class['id']; ?>" class="form-label">Гараг</label>
                                <select class="form-select" id="day_of_week<?php echo $class['id']; ?>" 
                                        name="day_of_week" required>
                                    <option value="Monday" <?php echo $class['day_of_week'] === 'Monday' ? 'selected' : ''; ?>>Даваа</option>
                                    <option value="Tuesday" <?php echo $class['day_of_week'] === 'Tuesday' ? 'selected' : ''; ?>>Мягмар</option>
                                    <option value="Wednesday" <?php echo $class['day_of_week'] === 'Wednesday' ? 'selected' : ''; ?>>Лхагва</option>
                                    <option value="Thursday" <?php echo $class['day_of_week'] === 'Thursday' ? 'selected' : ''; ?>>Пүрэв</option>
                                    <option value="Friday" <?php echo $class['day_of_week'] === 'Friday' ? 'selected' : ''; ?>>Баасан</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="start_time<?php echo $class['id']; ?>" class="form-label">Эхлэх цаг</label>
                                <input type="time" class="form-control" id="start_time<?php echo $class['id']; ?>" 
                                       name="start_time" value="<?php echo $class['start_time']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="end_time<?php echo $class['id']; ?>" class="form-label">Дуусах цаг</label>
                                <input type="time" class="form-control" id="end_time<?php echo $class['id']; ?>" 
                                       name="end_time" value="<?php echo $class['end_time']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="room<?php echo $class['id']; ?>" class="form-label">Өрөө</label>
                                <input type="text" class="form-control" id="room<?php echo $class['id']; ?>" 
                                       name="room" value="<?php echo htmlspecialchars($class['room']); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Хадгалах
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule Delete Modal -->
        <div class="modal fade" id="deleteScheduleModal<?php echo $class['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Хуваарь устгах</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Энэ хуваарийг устгахдаа итгэлтэй байна уу?</p>
                        <form action="../schedule/delete.php" method="POST">
                            <input type="hidden" name="schedule_id" value="<?php echo $class['id']; ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Устгах
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Material Delete Modals -->
    <?php foreach ($materials as $material): ?>
        <div class="modal fade" id="deleteMaterialModal<?php echo $material['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Материал устгах</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Энэ материалыг устгахдаа итгэлтэй байна уу?</p>
                        <form action="../materials/delete.php" method="POST">
                            <input type="hidden" name="material_id" value="<?php echo $material['id']; ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash"></i> Устгах
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 