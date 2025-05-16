<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get material ID from URL
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get material details with course and teacher info
$stmt = $conn->prepare("
    SELECT m.*, c.title as course_title, c.id as course_id,
           u.name as teacher_name, u.email as teacher_email
    FROM materials m 
    JOIN courses c ON m.course_id = c.id 
    JOIN users u ON m.created_by = u.id
    WHERE m.id = ?
");
$stmt->bind_param("i", $material_id);
$stmt->execute();
$result = $stmt->get_result();
$material = $result->fetch_assoc();

if (!$material) {
    header('Location: ../dashboard/' . $_SESSION['role'] . '.php');
    exit();
}

// Check if student is enrolled in the course
$is_enrolled = false;
if ($_SESSION['role'] === 'student') {
    $stmt = $conn->prepare("
        SELECT 1 FROM course_enrollments 
        WHERE course_id = ? AND student_id = ? AND status = 'approved'
    ");
    $stmt->bind_param("ii", $material['course_id'], $_SESSION['user_id']);
    $stmt->execute();
    $is_enrolled = $stmt->get_result()->num_rows > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Material - <?php echo htmlspecialchars($material['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard/<?php echo $_SESSION['role']; ?>.php">
                <?php echo ucfirst($_SESSION['role']); ?> Dashboard
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard/<?php echo $_SESSION['role']; ?>.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard/settings.php">Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Material Details</h4>
                        <?php if ($_SESSION['role'] === 'teacher' && $material['created_by'] === $_SESSION['user_id']): ?>
                            <a href="edit.php?id=<?php echo $material_id; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit"></i> Edit Material
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($material['title']); ?></h5>
                        <h6 class="card-subtitle mb-3 text-muted">Course: <?php echo htmlspecialchars($material['course_title']); ?></h6>

                        <div class="mb-4">
                            <h6>Description:</h6>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($material['description'])); ?></p>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Type:</h6>
                                <p><?php echo ucfirst($material['type']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Created by:</h6>
                                <p><?php echo htmlspecialchars($material['teacher_name']); ?></p>
                            </div>
                        </div>

                        <?php if ($_SESSION['role'] === 'student'): ?>
                            <?php if (!$is_enrolled): ?>
                                <div class="alert alert-warning">
                                    You are not enrolled in this course.
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($material['file_path'] && ($_SESSION['role'] === 'teacher' || $is_enrolled)): ?>
                            <div class="mb-4">
                                <h6>Material File:</h6>
                                <a href="../<?php echo htmlspecialchars($material['file_path']); ?>" target="_blank" class="btn btn-info">
                                    <i class="fas fa-file"></i> Download Material
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="d-grid gap-2">
                            <a href="list.php?course_id=<?php echo $material['course_id']; ?>" class="btn btn-secondary">
                                Back to Materials List
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 