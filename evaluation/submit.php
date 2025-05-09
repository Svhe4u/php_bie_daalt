<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

// Check if course_id is provided
if (!isset($_GET['course_id'])) {
    header("Location: ../dashboard/student.php");
    exit();
}

$course_id = filter_var($_GET['course_id'], FILTER_VALIDATE_INT);

// Check if student is enrolled in this course
$stmt = $conn->prepare("
    SELECT 1 FROM course_enrollments 
    WHERE course_id = ? AND student_id = ?
");
$stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
$stmt->execute();

if (!$stmt->get_result()->fetch_assoc()) {
    $_SESSION['error'] = "Та энэ хичээлд бүртгэлгүй байна.";
    header("Location: ../dashboard/student.php");
    exit();
}

// Check if student has already evaluated this course
$stmt = $conn->prepare("
    SELECT 1 FROM evaluations 
    WHERE course_id = ? AND student_id = ?
");
$stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
$stmt->execute();

if ($stmt->get_result()->fetch_assoc()) {
    $_SESSION['error'] = "Та энэ хичээлийг үнэлсэн байна.";
    header("Location: ../dashboard/student.php");
    exit();
}

// Get course details
$stmt = $conn->prepare("
    SELECT c.name as course_name, u.name as teacher_name
    FROM courses c
    JOIN users u ON c.teacher_id = u.id
    WHERE c.id = ?
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    header("Location: ../dashboard/student.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $score = filter_var($_POST['score'], FILTER_VALIDATE_INT);
    $comment = trim($_POST['comment']);

    if ($score >= 1 && $score <= 5 && !empty($comment)) {
        $stmt = $conn->prepare("
            INSERT INTO evaluations (course_id, student_id, score, comment)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiis", $course_id, $_SESSION['user_id'], $score, $comment);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Үнэлгээ амжилттай илгээгдлээ.";
            header("Location: ../dashboard/student.php");
            exit();
        } else {
            $error = "Үнэлгээ илгээхэд алдаа гарлаа.";
        }
    } else {
        $error = "Бүх талбарыг бөглөнө үү.";
    }
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Хичээл үнэлэх - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating input {
            display: none;
        }
        .rating label {
            cursor: pointer;
            font-size: 2rem;
            color: #ddd;
            padding: 0 0.1em;
        }
        .rating input:checked ~ label,
        .rating label:hover,
        .rating label:hover ~ label {
            color: #ffd700;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard/student.php">
                <img src="../assets/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                Хичээл үнэлэх
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
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0"><?php echo htmlspecialchars($course['course_name']); ?></h4>
                        <small class="text-muted">Багш: <?php echo htmlspecialchars($course['teacher_name']); ?></small>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-4">
                                <label class="form-label">Хичээлийн үнэлгээ</label>
                                <div class="rating">
                                    <input type="radio" name="score" value="5" id="star5" required>
                                    <label for="star5"><i class="bi bi-star-fill"></i></label>
                                    <input type="radio" name="score" value="4" id="star4">
                                    <label for="star4"><i class="bi bi-star-fill"></i></label>
                                    <input type="radio" name="score" value="3" id="star3">
                                    <label for="star3"><i class="bi bi-star-fill"></i></label>
                                    <input type="radio" name="score" value="2" id="star2">
                                    <label for="star2"><i class="bi bi-star-fill"></i></label>
                                    <input type="radio" name="score" value="1" id="star1">
                                    <label for="star1"><i class="bi bi-star-fill"></i></label>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="comment" class="form-label">Сэтгэгдэл</label>
                                <textarea class="form-control" id="comment" name="comment" rows="4" required 
                                          placeholder="Хичээлийн талаарх сэтгэгдлээ бичнэ үү..."></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="../dashboard/student.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Буцах
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send"></i> Илгээх
                                </button>
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