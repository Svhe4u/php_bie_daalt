<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get course ID from URL
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$course_id) {
    header("Location: ../dashboard/student_courses.php");
    exit();
}

// Get course details
$stmt = $conn->prepare("
    SELECT c.*, u.name as teacher_name, u.profile_picture as teacher_photo
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    WHERE c.id = ?
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    header("Location: ../dashboard/student_courses.php");
    exit();
}

// Get course materials
$stmt = $conn->prepare("
    SELECT * FROM materials 
    WHERE course_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get course assignments
$stmt = $conn->prepare("
    SELECT a.*, 
           (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as submitted,
           (SELECT grade FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) as grade
    FROM assignments a
    WHERE a.course_id = ?
    ORDER BY a.due_date ASC
");
$stmt->bind_param("iii", $_SESSION['user_id'], $_SESSION['user_id'], $course_id);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get course announcements
$stmt = $conn->prepare("
    SELECT * FROM announcements 
    WHERE course_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
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
        .course-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
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
        .nav-link {
            color: #6c757d;
            transition: all 0.2s;
        }
        .nav-link:hover {
            color: #0d6efd;
            background-color: #f8f9fa;
        }
        .nav-link.active {
            color: #0d6efd;
            background-color: #e9ecef;
        }
        .material-item, .assignment-item, .announcement-item {
            transition: all 0.2s;
            border-radius: 8px;
        }
        .material-item:hover, .assignment-item:hover, .announcement-item:hover {
            background-color: #f8f9fa;
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
                        <?php if ($user['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile" class="rounded-circle" width="80">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto" 
                                 style="width: 80px; height: 80px; font-size: 2rem;">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <h5 class="mt-2"><?php echo htmlspecialchars($user['name']); ?></h5>
                        <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard/student.php">
                                <i class="bi bi-house-door"></i> Хяналтын самбар
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="../dashboard/student_courses.php">
                                <i class="bi bi-book"></i> Хичээлүүд
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../dashboard/tabs/settings.php">
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
                <!-- Course Header -->
                <div class="course-header">
                    <div class="container">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1 class="display-4 mb-3"><?php echo htmlspecialchars($course['name']); ?></h1>
                                <?php if (!empty($course['description'])): ?>
                                <p class="lead mb-4"><?php echo htmlspecialchars($course['description']); ?></p>
                                <?php endif; ?>
                                <div class="d-flex align-items-center">
                                    <?php if ($course['teacher_photo']): ?>
                                        <img src="<?php echo htmlspecialchars($course['teacher_photo']); ?>" 
                                             alt="Teacher" class="rounded-circle me-2" width="40">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center me-2" 
                                             style="width: 40px; height: 40px; font-size: 1rem;">
                                            <?php echo strtoupper(substr($course['teacher_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <span class="text-white"><?php echo htmlspecialchars($course['teacher_name']); ?></span>
                                </div>
                            </div>
                            <div class="col-md-4 text-md-end mt-4 mt-md-0">
                                <div class="card bg-white bg-opacity-10 border-0">
                                    <div class="card-body">
                                        <h5 class="card-title text-white">Хичээлийн мэдээлэл</h5>
                                        <ul class="list-unstyled text-white-50">
                                            <?php if (!empty($course['start_date']) && !empty($course['end_date'])): ?>
                                            <li class="mb-2">
                                                <i class="bi bi-calendar me-2"></i>
                                                <?php echo date('Y-m-d', strtotime($course['start_date'])); ?> - 
                                                <?php echo date('Y-m-d', strtotime($course['end_date'])); ?>
                                            </li>
                                            <?php endif; ?>
                                            <?php if (!empty($course['schedule'])): ?>
                                            <li class="mb-2">
                                                <i class="bi bi-clock me-2"></i>
                                                <?php echo htmlspecialchars($course['schedule']); ?>
                                            </li>
                                            <?php endif; ?>
                                            <?php if (!empty($course['location'])): ?>
                                            <li>
                                                <i class="bi bi-geo-alt me-2"></i>
                                                <?php echo htmlspecialchars($course['location']); ?>
                                            </li>
                                            <?php endif; ?>
                                            <?php if (empty($course['start_date']) && empty($course['end_date']) && empty($course['schedule']) && empty($course['location'])): ?>
                                            <li class="text-center">
                                                <i class="bi bi-info-circle me-2"></i>
                                                Хичээлийн мэдээлэл оруулаагүй байна
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course Content -->
                <div class="container">
                    <div class="row g-4">
                        <!-- Materials Section -->
                        <div class="col-md-6">
                            <div class="card course-card">
                                <div class="card-header bg-white border-0 py-3">
                                    <h5 class="card-title mb-0 text-primary">
                                        <i class="bi bi-file-earmark-text me-2"></i>Хичээлийн материал
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <?php if (empty($materials)): ?>
                                        <p class="text-muted text-center my-4">Одоогоор материал байхгүй байна.</p>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($materials as $material): ?>
                                                <a href="download.php?file=<?php echo urlencode(basename($material['file_path'])); ?>" 
                                                   class="list-group-item list-group-item-action material-item py-3">
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-file-earmark-text text-primary me-3" style="font-size: 1.5rem;"></i>
                                                        <div>
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($material['title']); ?></h6>
                                                            <small class="text-muted">
                                                                <?php echo date('Y-m-d H:i', strtotime($material['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Assignments Section -->
                        <div class="col-md-6">
                            <div class="card course-card">
                                <div class="card-header bg-white border-0 py-3">
                                    <h5 class="card-title mb-0 text-primary">
                                        <i class="bi bi-pencil-square me-2"></i>Даалгаврууд
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <?php if (empty($assignments)): ?>
                                        <p class="text-muted text-center my-4">Одоогоор даалгавар байхгүй байна.</p>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($assignments as $assignment): ?>
                                                <div class="list-group-item assignment-item py-3">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h6>
                                                            <small class="text-muted">
                                                                Дуусах: <?php echo date('Y-m-d H:i', strtotime($assignment['due_date'])); ?>
                                                            </small>
                                                        </div>
                                                        <div class="text-end">
                                                            <?php if ($assignment['submitted']): ?>
                                                                <span class="badge bg-success">Илгээсэн</span>
                                                                <?php if ($assignment['grade']): ?>
                                                                    <div class="mt-1">
                                                                        <small class="text-muted">Оноо: <?php echo $assignment['grade']; ?></small>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning">Илгээгээгүй</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Announcements Section -->
                        <div class="col-12">
                            <div class="card course-card">
                                <div class="card-header bg-white border-0 py-3">
                                    <h5 class="card-title mb-0 text-primary">
                                        <i class="bi bi-megaphone me-2"></i>Мэдэгдлүүд
                                    </h5>
                                </div>
                                <div class="card-body p-4">
                                    <?php if (empty($announcements)): ?>
                                        <p class="text-muted text-center my-4">Одоогоор мэдэгдэл байхгүй байна.</p>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush">
                                            <?php foreach ($announcements as $announcement): ?>
                                                <div class="list-group-item announcement-item py-3">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                                                            <p class="mb-1"><?php echo htmlspecialchars($announcement['content']); ?></p>
                                                            <small class="text-muted">
                                                                <?php echo date('Y-m-d H:i', strtotime($announcement['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 