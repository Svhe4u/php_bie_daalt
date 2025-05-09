<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle course deletion
if (isset($_POST['delete_course'])) {
    $course_id = filter_var($_POST['course_id'], FILTER_SANITIZE_NUMBER_INT);
    
    $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
    $stmt->bind_param("i", $course_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Хичээл амжилттай устгагдлаа";
    } else {
        $_SESSION['error'] = "Хичээл устгахад алдаа гарлаа";
    }
    header("Location: manage.php");
    exit();
}

// Handle course update
if (isset($_POST['update_course'])) {
    $course_id = filter_var($_POST['course_id'], FILTER_SANITIZE_NUMBER_INT);
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $teacher_id = filter_var($_POST['teacher_id'], FILTER_SANITIZE_NUMBER_INT);
    
    $stmt = $conn->prepare("UPDATE courses SET name = ?, teacher_id = ? WHERE id = ?");
    $stmt->bind_param("sii", $name, $teacher_id, $course_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Хичээл амжилттай шинэчлэгдлээ";
    } else {
        $_SESSION['error'] = "Хичээл шинэчлэхэд алдаа гарлаа";
    }
    header("Location: manage.php");
    exit();
}

// Handle student enrollment
if (isset($_POST['enroll_student'])) {
    $course_id = filter_var($_POST['course_id'], FILTER_SANITIZE_NUMBER_INT);
    $student_id = filter_var($_POST['student_id'], FILTER_SANITIZE_NUMBER_INT);
    
    $stmt = $conn->prepare("INSERT INTO course_enrollments (student_id, course_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $student_id, $course_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Оюутан амжилттай бүртгэгдлээ";
    } else {
        $_SESSION['error'] = "Оюутан бүртгэхэд алдаа гарлаа";
    }
    header("Location: manage.php");
    exit();
}

// Handle student unenrollment
if (isset($_POST['unenroll_student'])) {
    $enrollment_id = filter_var($_POST['enrollment_id'], FILTER_SANITIZE_NUMBER_INT);
    
    $stmt = $conn->prepare("DELETE FROM course_enrollments WHERE id = ?");
    $stmt->bind_param("i", $enrollment_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Оюутан амжилттай хасагдлаа";
    } else {
        $_SESSION['error'] = "Оюутан хасахад алдаа гарлаа";
    }
    header("Location: manage.php");
    exit();
}

// Handle new course addition
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
    header("Location: manage.php");
    exit();
}

// Get all courses with teacher names and student counts
$stmt = $conn->prepare("
    SELECT 
        c.id, 
        c.name, 
        c.teacher_id, 
        u.name as teacher_name, 
        c.created_at,
        (SELECT COUNT(*) FROM course_enrollments WHERE course_id = c.id) as student_count
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    ORDER BY c.created_at DESC
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

// Get all students for the dropdown
$stmt = $conn->prepare("
    SELECT id, name, email
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
    <title>Хичээлийн удирдлага - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard/admin.php">
                <img src="../assets/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                Хичээлийн удирдлага
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard/admin.php">Хяналтын самбар руу буцах</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Шинэ хичээл нэмэх</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="name" class="form-label">Хичээлийн нэр</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="teacher_id" class="form-label">Багш</label>
                                <select class="form-select" id="teacher_id" name="teacher_id" required>
                                    <option value="">Багш сонгох</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="add_course" class="btn btn-primary">Нэмэх</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Хичээлийн жагсаалт</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Хичээл</th>
                                        <th>Багш</th>
                                        <th>Оюутны тоо</th>
                                        <th>Нэмсэн</th>
                                        <th>Үйлдэл</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($courses)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4">
                                                <div class="text-muted">
                                                    <i class="bi bi-book fs-4 d-block mb-2"></i>
                                                    Хичээл байхгүй байна
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
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
                                                    <a href="#" class="text-decoration-none" 
                                                       data-bs-toggle="modal" 
                                                       data-bs-target="#studentsModal<?php echo $course['id']; ?>">
                                                        <span class="badge bg-info">
                                                            <i class="bi bi-people me-1"></i>
                                                            <?php echo $course['student_count']; ?> оюутан
                                                        </span>
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="text-muted">
                                                        <i class="bi bi-calendar me-1"></i>
                                                        <?php echo date('Y-m-d', strtotime($course['created_at'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-primary btn-sm" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editModal<?php echo $course['id']; ?>"
                                                                title="Засах">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-info btn-sm"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#studentsModal<?php echo $course['id']; ?>"
                                                                title="Оюутнууд">
                                                            <i class="bi bi-people"></i>
                                                        </button>
                                                        <form method="POST" action="" class="d-inline" 
                                                              onsubmit="return confirm('Энэ хичээлийг устгахдаа итгэлтэй байна уу?');">
                                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                                            <button type="submit" name="delete_course" class="btn btn-danger btn-sm"
                                                                    title="Устгах">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit and Student Modals -->
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
                            <form method="POST" action="">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <div class="mb-3">
                                    <label for="edit_name<?php echo $course['id']; ?>" class="form-label">Хичээлийн нэр</label>
                                    <input type="text" class="form-control" id="edit_name<?php echo $course['id']; ?>" 
                                           name="name" value="<?php echo htmlspecialchars($course['name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="edit_teacher<?php echo $course['id']; ?>" class="form-label">Багш</label>
                                    <select class="form-select" id="edit_teacher<?php echo $course['id']; ?>" name="teacher_id" required>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?php echo $teacher['id']; ?>" 
                                                    <?php echo $teacher['id'] == $course['teacher_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($teacher['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" name="update_course" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Хадгалах
                                    </button>
                                </div>
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
                            <!-- Add Student Form -->
                            <form method="POST" action="" class="mb-4">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <select name="student_id" class="form-select" required>
                                            <option value="">Оюутан сонгох</option>
                                            <?php foreach ($students as $student): ?>
                                                <option value="<?php echo $student['id']; ?>">
                                                    <?php echo htmlspecialchars($student['name'] . ' (' . $student['email'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" name="enroll_student" class="btn btn-primary w-100">
                                            <i class="bi bi-person-plus"></i> Оюутан нэмэх
                                        </button>
                                    </div>
                                </div>
                            </form>

                            <!-- Enrolled Students Table -->
                            <?php
                            // Get enrolled students for this course
                            $stmt = $conn->prepare("
                                SELECT 
                                    ce.id as enrollment_id,
                                    u.id as student_id,
                                    u.name,
                                    u.email,
                                    ce.enrolled_at,
                                    e.score,
                                    e.comment
                                FROM course_enrollments ce
                                JOIN users u ON ce.student_id = u.id
                                LEFT JOIN evaluations e ON e.student_id = u.id AND e.course_id = ce.course_id
                                WHERE ce.course_id = ?
                                ORDER BY u.name
                            ");
                            $stmt->bind_param("i", $course['id']);
                            $stmt->execute();
                            $enrolled_students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Оюутны нэр</th>
                                            <th>Имэйл</th>
                                            <th>Үнэлгээ</th>
                                            <th>Сэтгэгдэл</th>
                                            <th>Бүртгүүлсэн</th>
                                            <th>Үйлдэл</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($enrolled_students)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="bi bi-people fs-4 d-block mb-2"></i>
                                                        Оюутан бүртгэгдээгүй байна
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($enrolled_students as $student): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($student['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                                    <td>
                                                        <?php if ($student['score']): ?>
                                                            <div class="rating">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <i class="bi bi-star<?php echo $i <= $student['score'] ? '-fill' : ''; ?> text-warning"></i>
                                                                <?php endfor; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <span class="text-muted">Үнэлгээ байхгүй</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $student['comment'] ? htmlspecialchars($student['comment']) : '<span class="text-muted">Сэтгэгдэл байхгүй</span>'; ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($student['enrolled_at'])); ?></td>
                                                    <td>
                                                        <form method="POST" action="" class="d-inline" 
                                                              onsubmit="return confirm('Энэ оюутныг хасахад итгэлтэй байна уу?');">
                                                            <input type="hidden" name="enrollment_id" value="<?php echo $student['enrollment_id']; ?>">
                                                            <button type="submit" name="unenroll_student" class="btn btn-danger btn-sm">
                                                                <i class="bi bi-person-dash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 