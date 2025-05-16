<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get schedule ID from URL
$schedule_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get schedule details
$stmt = $conn->prepare("
    SELECT s.*, c.name as course_name 
    FROM schedule s 
    JOIN courses c ON s.course_id = c.id 
    WHERE s.id = ? AND s.teacher_id = ?
");
$stmt->bind_param("ii", $schedule_id, $_SESSION['user_id']);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_assoc();

if (!$schedule) {
    header("Location: ../dashboard/teacher.php");
    exit();
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $location = trim($_POST['location']);
    $type = $_POST['type'];
    
    if (empty($title)) {
        $error = "Гарчиг оруулна уу";
    } else if (empty($start_time)) {
        $error = "Эхлэх цаг оруулна уу";
    } else if (empty($end_time)) {
        $error = "Дуусах цаг оруулна уу";
    } else if (strtotime($end_time) <= strtotime($start_time)) {
        $error = "Дуусах цаг эхлэх цагаас хойш байх ёстой";
    } else {
        $stmt = $conn->prepare("
            UPDATE schedule 
            SET title = ?, description = ?, start_time = ?, end_time = ?, 
                location = ?, type = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND teacher_id = ?
        ");
        $stmt->bind_param(
            "ssssssii", 
            $title, $description, $start_time, $end_time, 
            $location, $type, $schedule_id, $_SESSION['user_id']
        );
        
        if ($stmt->execute()) {
            $success = "Хуваарь амжилттай шинэчлэгдлээ";
            // Refresh schedule data
            $stmt = $conn->prepare("SELECT * FROM schedule WHERE id = ?");
            $stmt->bind_param("i", $schedule_id);
            $stmt->execute();
            $schedule = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Хуваарь шинэчлэхэд алдаа гарлаа";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Хуваарь засварлах - <?php echo htmlspecialchars($schedule['course_name']); ?></title>
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
                            <i class="bi bi-calendar-event text-primary me-2"></i>
                            Хуваарь засварлах - <?php echo htmlspecialchars($schedule['course_name']); ?>
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

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="title" class="form-label">Гарчиг</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($schedule['title']); ?>" required>
                                <div class="invalid-feedback">
                                    Гарчиг оруулна уу
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Тайлбар</label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3"><?php echo htmlspecialchars($schedule['description']); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="start_time" class="form-label">Эхлэх цаг</label>
                                    <input type="datetime-local" class="form-control" id="start_time" name="start_time" 
                                           value="<?php echo date('Y-m-d\TH:i', strtotime($schedule['start_time'])); ?>" required>
                                    <div class="invalid-feedback">
                                        Эхлэх цаг оруулна уу
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="end_time" class="form-label">Дуусах цаг</label>
                                    <input type="datetime-local" class="form-control" id="end_time" name="end_time" 
                                           value="<?php echo date('Y-m-d\TH:i', strtotime($schedule['end_time'])); ?>" required>
                                    <div class="invalid-feedback">
                                        Дуусах цаг оруулна уу
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="location" class="form-label">Байршил</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($schedule['location']); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="type" class="form-label">Төрөл</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="lecture" <?php echo $schedule['type'] === 'lecture' ? 'selected' : ''; ?>>Лекц</option>
                                    <option value="exam" <?php echo $schedule['type'] === 'exam' ? 'selected' : ''; ?>>Шалгалт</option>
                                    <option value="assignment" <?php echo $schedule['type'] === 'assignment' ? 'selected' : ''; ?>>Даалгавар</option>
                                    <option value="other" <?php echo $schedule['type'] === 'other' ? 'selected' : ''; ?>>Бусад</option>
                                </select>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="../dashboard/teacher.php" class="btn btn-secondary">
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