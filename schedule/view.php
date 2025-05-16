<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get schedule ID from URL
$schedule_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get schedule details
$stmt = $conn->prepare("
    SELECT s.*, c.title as course_title, c.id as course_id, u.name as teacher_name 
    FROM schedule s 
    JOIN courses c ON s.course_id = c.id 
    JOIN users u ON s.teacher_id = u.id 
    WHERE s.id = ?
");
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$result = $stmt->get_result();
$schedule = $result->fetch_assoc();

if (!$schedule) {
    header('Location: ../dashboard/' . $_SESSION['role'] . '.php');
    exit();
}

// Check if student is enrolled
if ($_SESSION['role'] === 'student') {
    $stmt = $conn->prepare("SELECT * FROM course_enrollments WHERE course_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $schedule['course_id'], $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $enrolled = $result->num_rows > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($schedule['title']); ?> - Schedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard/<?php echo $_SESSION['role']; ?>.php">Dashboard</a>
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
        <?php if ($_SESSION['role'] === 'student' && !$enrolled): ?>
            <div class="alert alert-warning">
                You are not enrolled in this course. Please contact the teacher to enroll.
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0"><?php echo htmlspecialchars($schedule['title']); ?></h4>
                            <span class="badge bg-<?php 
                                echo match($schedule['type']) {
                                    'lecture' => 'primary',
                                    'lab' => 'success',
                                    'exam' => 'danger',
                                    'assignment' => 'warning',
                                    default => 'secondary'
                                };
                            ?>">
                                <?php echo ucfirst($schedule['type']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h5>Course</h5>
                            <p><?php echo htmlspecialchars($schedule['course_title']); ?></p>
                        </div>

                        <div class="mb-4">
                            <h5>Description</h5>
                            <p><?php echo nl2br(htmlspecialchars($schedule['description'])); ?></p>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Start Time</h5>
                                <p><?php echo date('M d, Y H:i', strtotime($schedule['start_time'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>End Time</h5>
                                <p><?php echo date('M d, Y H:i', strtotime($schedule['end_time'])); ?></p>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h5>Location</h5>
                            <p><?php echo htmlspecialchars($schedule['location']); ?></p>
                        </div>

                        <div class="mb-4">
                            <h5>Teacher</h5>
                            <p><?php echo htmlspecialchars($schedule['teacher_name']); ?></p>
                        </div>

                        <div class="d-grid gap-2">
                            <a href="list.php?course_id=<?php echo $schedule['course_id']; ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Schedule
                            </a>
                            <?php if ($_SESSION['role'] === 'teacher' && $schedule['teacher_id'] === $_SESSION['user_id']): ?>
                                <a href="edit.php?id=<?php echo $schedule['id']; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit"></i> Edit Schedule Entry
                                </a>
                                <a href="delete.php?id=<?php echo $schedule['id']; ?>" class="btn btn-danger">
                                    <i class="fas fa-trash"></i> Delete Schedule Entry
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 