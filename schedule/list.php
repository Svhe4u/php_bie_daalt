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
$result = $stmt->get_result();
$course = $result->fetch_assoc();

if (!$course) {
    header('Location: ../dashboard/' . $_SESSION['role'] . '.php');
    exit();
}

// Check if student is enrolled
if ($_SESSION['role'] === 'student') {
    $stmt = $conn->prepare("SELECT * FROM course_enrollments WHERE course_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $course_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $enrolled = $result->num_rows > 0;
}

// Get all schedule entries for the course
$stmt = $conn->prepare("
    SELECT s.*, u.name as teacher_name 
    FROM schedule s 
    JOIN users u ON s.teacher_id = u.id 
    WHERE s.course_id = ? 
    ORDER BY s.start_time DESC
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$result = $stmt->get_result();
$schedules = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule - <?php echo htmlspecialchars($course['title']); ?></title>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><?php echo htmlspecialchars($course['title']); ?></h2>
                <p class="text-muted">Teacher: <?php echo htmlspecialchars($course['teacher_name']); ?></p>
            </div>
            <?php if ($_SESSION['role'] === 'teacher' && $course['teacher_id'] === $_SESSION['user_id']): ?>
                <a href="create.php?course_id=<?php echo $course_id; ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Schedule Entry
                </a>
            <?php endif; ?>
        </div>

        <?php if ($_SESSION['role'] === 'student' && !$enrolled): ?>
            <div class="alert alert-warning">
                You are not enrolled in this course. Please contact the teacher to enroll.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Operation completed successfully.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">An error occurred. Please try again.</div>
        <?php endif; ?>

        <?php if (empty($schedules)): ?>
            <div class="alert alert-info">No schedule entries have been created yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Location</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($schedule['title']); ?></td>
                                <td>
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
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($schedule['start_time'])); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($schedule['end_time'])); ?></td>
                                <td><?php echo htmlspecialchars($schedule['location']); ?></td>
                                <td>
                                    <a href="view.php?id=<?php echo $schedule['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($_SESSION['role'] === 'teacher' && $schedule['teacher_id'] === $_SESSION['user_id']): ?>
                                        <a href="edit.php?id=<?php echo $schedule['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="delete.php?id=<?php echo $schedule['id']; ?>" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 