<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = $_POST['course_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    
    // Validate input
    if (empty($title) || empty($_FILES['file'])) {
        $_SESSION['error'] = "Гарчиг болон файл оруулна уу.";
        header("Location: ../dashboard/course.php?id=" . $course_id);
        exit();
    }
    
    // Handle file upload
    $upload_dir = '../uploads/materials/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = time() . '_' . basename($_FILES['file']['name']);
    $file_path = 'uploads/materials/' . $file_name;
    $file_type = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir . $file_name)) {
        $_SESSION['error'] = "Файл хуулахад алдаа гарлаа.";
        header("Location: ../dashboard/course.php?id=" . $course_id);
        exit();
    }
    
    // Insert material
    $stmt = $conn->prepare("
        INSERT INTO materials (course_id, title, description, file_path, file_type, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("issss", $course_id, $title, $description, $file_path, $file_type);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Материал амжилттай нэмэгдлээ.";
    } else {
        $_SESSION['error'] = "Материал нэмэхэд алдаа гарлаа.";
    }
    
    header("Location: ../dashboard/course.php?id=" . $course_id);
    exit();
} else {
    header("Location: ../dashboard/teacher.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Шинэ материал - <?php echo htmlspecialchars($course['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard/teacher.php">
                <img src="../assets/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                Сургалтын Үнэлгээний Систем
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard/settings.php">
                            <i class="bi bi-person-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Гарах</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">
                            <i class="bi bi-file-earmark-plus text-primary me-2"></i>
                            Шинэ материал - <?php echo htmlspecialchars($course['name']); ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="title" class="form-label">Гарчиг</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                                <div class="invalid-feedback">
                                    Гарчиг оруулна уу
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Тайлбар</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="file" class="form-label">Файл</label>
                                <input type="file" class="form-control" id="file" name="file">
                                <div class="form-text">
                                    Зөвшөөрөгдөх төрөл: PDF, DOC, DOCX, PPT, PPTX, TXT, ZIP, RAR
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php?course_id=<?php echo $course_id; ?>" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Буцах
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-2"></i>Хадгалах
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
    </script>
</body>
</html> 