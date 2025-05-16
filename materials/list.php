<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get course ID from URL
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Verify course exists and user has access
$stmt = $conn->prepare("
    SELECT c.*, u.name as teacher_name 
    FROM courses c 
    JOIN users u ON c.teacher_id = u.id 
    WHERE c.id = ?
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    header('Location: ../dashboard/' . $_SESSION['role'] . '.php');
    exit();
}

// Check if student is enrolled
$is_enrolled = false;
if ($_SESSION['role'] === 'student') {
    $stmt = $conn->prepare("
        SELECT 1 FROM course_enrollments 
        WHERE course_id = ? AND student_id = ? AND status = 'approved'
    ");
    $stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
    $stmt->execute();
    $is_enrolled = $stmt->get_result()->num_rows > 0;
}

// Get materials
$stmt = $conn->prepare("
    SELECT m.*, u.name as teacher_name
    FROM materials m 
    JOIN users u ON m.created_by = u.id
    WHERE m.course_id = ?
    ORDER BY m.created_at DESC
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$materials = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Materials - <?php echo htmlspecialchars($course['title']); ?></title>
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
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><?php echo htmlspecialchars($course['title']); ?></h2>
                <p class="text-muted">Teacher: <?php echo htmlspecialchars($course['teacher_name']); ?></p>
            </div>
            <div class="col-md-4 text-end">
                <?php if ($_SESSION['role'] === 'teacher' && $course['teacher_id'] === $_SESSION['user_id']): ?>
                    <a href="create.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Material
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($_SESSION['role'] === 'student' && !$is_enrolled): ?>
            <div class="alert alert-warning">
                You are not enrolled in this course.
            </div>
        <?php else: ?>
            <?php if (empty($materials)): ?>
                <div class="alert alert-info">
                    No materials have been added yet.
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($materials as $material): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($material['title']); ?></h5>
                                    <h6 class="card-subtitle mb-2 text-muted">
                                        Type: <?php echo ucfirst($material['type']); ?>
                                    </h6>
                                    <p class="card-text">
                                        <?php 
                                        $description = $material['description'];
                                        echo strlen($description) > 100 ? 
                                            htmlspecialchars(substr($description, 0, 100)) . '...' : 
                                            htmlspecialchars($description);
                                        ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            Added by: <?php echo htmlspecialchars($material['teacher_name']); ?><br>
                                            <?php echo date('M j, Y', strtotime($material['created_at'])); ?>
                                        </small>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($_SESSION['role'] === 'teacher' && $course['teacher_id'] === $_SESSION['user_id']): ?>
                                                <a href="edit.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 