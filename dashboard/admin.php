<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle user registration
if (isset($_POST['register_user'])) {
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $role = filter_var($_POST['role'], FILTER_SANITIZE_STRING);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Имэйл хаяг буруу байна";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        if (!$stmt) {
            $_SESSION['error'] = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $_SESSION['error'] = "Энэ имэйл хаяг бүртгэлтэй байна";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                if (!$stmt) {
                    $_SESSION['error'] = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Хэрэглэгч амжилттай бүртгэгдлээ";
                    } else {
                        $_SESSION['error'] = "Хэрэглэгч бүртгэхэд алдаа гарлаа: " . $stmt->error;
                    }
                }
            }
        }
    }
    header("Location: admin.php");
    exit();
}

// Handle course addition
if (isset($_POST['add_course'])) {
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $teacher_id = filter_var($_POST['teacher_id'], FILTER_SANITIZE_NUMBER_INT);
    
    $stmt = $conn->prepare("INSERT INTO courses (name, teacher_id) VALUES (?, ?)");
    $stmt->bind_param("si", $name, $teacher_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Хичээл амжилттай нэмэгдлээ";
    } else {
        $_SESSION['error'] = "Хичээл нэмэхэд алдаа гарлаа";
    }
    header("Location: admin.php");
    exit();
}

// Get all users
$stmt = $conn->prepare("
    SELECT id, name, email, role, created_at
    FROM users
    ORDER BY created_at DESC
");
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all courses with evaluation and grade statistics
$stmt = $conn->prepare("
    SELECT 
        c.id,
        c.name,
        c.teacher_id,
        u.name as teacher_name,
        c.created_at,
        (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) as student_count,
        (SELECT COUNT(*) FROM evaluations WHERE course_id = c.id) as total_evaluations,
        (SELECT AVG(score) FROM evaluations WHERE course_id = c.id) as average_score,
        (SELECT COUNT(*) FROM grades WHERE course_id = c.id) as graded_count,
        (SELECT AVG(grade) FROM grades WHERE course_id = c.id) as average_grade
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    GROUP BY c.id
    ORDER BY c.name
");
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all teachers for the dropdown
$stmt = $conn->prepare("
    SELECT id, name 
    FROM users 
    WHERE role = 'teacher' 
    ORDER BY name
");
$stmt->execute();
$teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get overall statistics
$stmt = $conn->prepare("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
        (SELECT COUNT(*) FROM users WHERE role = 'teacher') as total_teachers,
        (SELECT COUNT(*) FROM courses) as total_courses,
        (SELECT COUNT(*) FROM evaluations) as total_evaluations,
        (SELECT AVG(score) FROM evaluations) as average_score,
        (SELECT COUNT(*) FROM grades) as total_grades,
        (SELECT AVG(grade) FROM grades) as average_grade
");
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get all students
$stmt = $conn->prepare("
    SELECT id, name, email, created_at
    FROM users
    WHERE role = 'student'
    ORDER BY name
");
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ хяналтын самбар - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="admin.php">
                <img src="../assets/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                Админ хяналтын самбар
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

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Нийт оюутан</h5>
                        <h2 class="mb-0"><?php echo $stats['total_students']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Нийт багш</h5>
                        <h2 class="mb-0"><?php echo $stats['total_teachers']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Нийт хичээл</h5>
                        <h2 class="mb-0"><?php echo $stats['total_courses']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Дундаж дүн</h5>
                        <h2 class="mb-0">
                            <?php if ($stats['total_grades'] > 0): ?>
                                <?php echo number_format($stats['average_grade'], 1); ?>
                                <small class="fs-6">(<?php echo $stats['total_grades']; ?>)</small>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Нийт үнэлгээ</h5>
                        <h2 class="mb-0">
                            <?php echo $stats['total_evaluations']; ?>
                            <?php if ($stats['total_evaluations'] > 0): ?>
                                <small class="fs-6">
                                    (<?php echo number_format($stats['average_score'], 1); ?>/5)
                                </small>
                            <?php endif; ?>
                        </h2>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Нийт дүн</h5>
                        <h2 class="mb-0">
                            <?php echo $stats['total_grades']; ?>
                            <?php if ($stats['total_grades'] > 0): ?>
                                <small class="fs-6">
                                    (<?php echo number_format($stats['average_grade'], 1); ?> дундаж)
                                </small>
                            <?php endif; ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Шинэ хэрэглэгч бүртгэх</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label">Бүтэн нэр</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Имэйл хаяг</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Нууц үг</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">Үүрэг</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Үүрэг сонгох</option>
                                    <option value="student">Оюутан</option>
                                    <option value="teacher">Багш</option>
                                </select>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="register_user" class="btn btn-primary">Бүртгэх</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Хэрэглэгчид</h4>
                        <a href="../users/manage.php" class="btn btn-primary btn-sm">Бүрэн удирдлага</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Нэр</th>
                                        <th>Имэйл</th>
                                        <th>Үүрэг</th>
                                        <th>Бүртгүүлсэн</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'teacher' ? 'success' : 'primary'); ?>">
                                                    <?php echo $user['role'] === 'admin' ? 'Админ' : ($user['role'] === 'teacher' ? 'Багш' : 'Оюутан'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Хичээлийн жагсаалт</h4>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                            <i class="bi bi-plus-circle"></i> Шинэ хичээл нэмэх
                        </button>
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
                                            <th>Оюутны тоо</th>
                                            <th>Үнэлгээ</th>
                                            <th>Дундаж оноо</th>
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
                                                <td><?php echo htmlspecialchars($course['teacher_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <i class="bi bi-people me-1"></i>
                                                        <?php echo $course['student_count']; ?> оюутан
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($course['total_evaluations'] > 0): ?>
                                                        <div class="rating">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="bi bi-star<?php echo $i <= round($course['average_score']) ? '-fill' : ''; ?> text-warning"></i>
                                                            <?php endfor; ?>
                                                            <span class="ms-1"><?php echo number_format($course['average_score'], 1); ?>/5</span>
                                                            <small class="text-muted">
                                                                (<?php echo $course['total_evaluations']; ?>)
                                                            </small>
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
                                                           class="btn btn-primary btn-sm" 
                                                           title="Үнэлгээ харах">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-info btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#studentsModal<?php echo $course['id']; ?>"
                                                                title="Оюутан харах">
                                                            <i class="bi bi-people"></i>
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-warning btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editModal<?php echo $course['id']; ?>"
                                                                title="Засах">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <form action="../courses/delete.php" method="POST" class="d-inline">
                                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                            <button type="submit" 
                                                                    class="btn btn-danger btn-sm"
                                                                    onclick="return confirm('Энэ хичээлийг устгах уу?')"
                                                                    title="Устгах">
                                                                <i class="bi bi-trash"></i>
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

                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Оюутнууд</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($students)): ?>
                            <div class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-people fs-4 d-block mb-2"></i>
                                    Оюутан бүртгэгдээгүй байна
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Оюутан</th>
                                            <th>И-мэйл</th>
                                            <th>Бүртгүүлсэн</th>
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
                                                    <span class="text-muted">
                                                        <i class="bi bi-calendar me-1"></i>
                                                        <?php echo date('Y-m-d', strtotime($student['created_at'])); ?>
                                                    </span>
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
                                <label for="teacher_id<?php echo $course['id']; ?>" class="form-label">Багш</label>
                                <select class="form-select" id="teacher_id<?php echo $course['id']; ?>" name="teacher_id" required>
                                    <option value="">Багш сонгоно уу</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>" 
                                                <?php echo $teacher['id'] == $course['teacher_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($teacher['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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
                                                <?php
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
                                                foreach ($available_students as $student):
                                                ?>
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

                        <?php
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
                        $enrolled_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        ?>

                        <?php if (empty($enrolled_students)): ?>
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
                                        <?php foreach ($enrolled_students as $student): ?>
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

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Шинэ хичээл нэмэх</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="../courses/create.php" method="POST">
                        <div class="mb-3">
                            <label for="name" class="form-label">Хичээлийн нэр</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="teacher_id" class="form-label">Багш</label>
                            <select class="form-select" id="teacher_id" name="teacher_id" required>
                                <option value="">Багш сонгоно уу</option>
                                <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>">
                                        <?php echo htmlspecialchars($teacher['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Нэмэх
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 