<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle POST request for updating course
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = filter_var($_POST['course_id'], FILTER_SANITIZE_NUMBER_INT);
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $teacher_id = filter_var($_POST['teacher_id'], FILTER_SANITIZE_NUMBER_INT);
    
    // Validate inputs
    if (!$course_id || !$name || !$teacher_id) {
        $_SESSION['error'] = "Бүх талбарыг бөглөнө үү";
        header("Location: ../dashboard/admin.php");
        exit();
    }
    
    // Check if course exists
    $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $_SESSION['error'] = "Хичээл олдсонгүй";
        header("Location: ../dashboard/admin.php");
        exit();
    }
    
    // Check if teacher exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $_SESSION['error'] = "Багш олдсонгүй";
        header("Location: ../dashboard/admin.php");
        exit();
    }
    
    // Update course
    $stmt = $conn->prepare("UPDATE courses SET name = ?, teacher_id = ? WHERE id = ?");
    $stmt->bind_param("sii", $name, $teacher_id, $course_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Хичээл амжилттай шинэчлэгдлээ";
    } else {
        $_SESSION['error'] = "Хичээл шинэчлэхэд алдаа гарлаа";
    }
    
    header("Location: ../dashboard/admin.php");
    exit();
}

// Handle GET request to show update form
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $course_id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
    
    // Get course details
    $stmt = $conn->prepare("
        SELECT c.id, c.name, c.teacher_id, u.name as teacher_name
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.id = ?
    ");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();
    
    if (!$course) {
        $_SESSION['error'] = "Хичээл олдсонгүй";
        header("Location: ../dashboard/admin.php");
        exit();
    }
    
    // Get all teachers for the dropdown
    $stmt = $conn->prepare("
        SELECT id, name 
        FROM users 
        WHERE role = 'teacher' 
        ORDER BY name
    ");
    $stmt->execute();
    $teachers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Show update form
    ?>
    <!DOCTYPE html>
    <html lang="mn">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Хичээл засварлах - Сургалтын Үнэлгээний Систем</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
        <link rel="stylesheet" href="../css/style.css">
    </head>
    <body class="bg-light">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="../dashboard/admin.php">
                    <img src="../assets/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                    Хичээл засварлах
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
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h4 class="mb-0">Хичээл засварлах</h4>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="update.php">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Хичээлийн нэр</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($course['name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="teacher_id" class="form-label">Багш</label>
                                    <select class="form-select" id="teacher_id" name="teacher_id" required>
                                        <option value="">Багш сонгоно уу</option>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?php echo $teacher['id']; ?>" 
                                                    <?php echo $teacher['id'] == $course['teacher_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($teacher['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Хадгалах
                                    </button>
                                    <a href="../dashboard/admin.php" class="btn btn-secondary">
                                        <i class="bi bi-x-circle"></i> Цуцлах
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit();
}

// If no valid request method or parameters, redirect to admin dashboard
header("Location: ../dashboard/admin.php");
exit();
?> 