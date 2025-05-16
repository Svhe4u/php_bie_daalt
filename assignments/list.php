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

// Get assignments
$stmt = $conn->prepare("
    SELECT a.*, 
           (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id) as submission_count,
           (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.status = 'graded') as graded_count
    FROM assignments a 
    WHERE a.course_id = ?
    ORDER BY a.due_date DESC
");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$assignments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - <?php echo htmlspecialchars($course['title']); ?></title>
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
                        <i class="fas fa-plus"></i> Create Assignment
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($_SESSION['role'] === 'student' && !$is_enrolled): ?>
            <div class="alert alert-warning">
                You are not enrolled in this course.
            </div>
        <?php else: ?>
            <?php if (empty($assignments)): ?>
                <div class="alert alert-info">
                    No assignments have been created yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Due Date</th>
                                <th>Max Score</th>
                                <?php if ($_SESSION['role'] === 'teacher'): ?>
                                    <th>Submissions</th>
                                    <th>Graded</th>
                                <?php endif; ?>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignments as $assignment): ?>
                                <tr>
                                    <td>
                                        <a href="view.php?id=<?php echo $assignment['id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($assignment['title']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php 
                                        $due_date = strtotime($assignment['due_date']);
                                        $now = time();
                                        $class = $due_date < $now ? 'text-danger' : 'text-success';
                                        echo "<span class='$class'>" . date('M j, Y g:i A', $due_date) . "</span>";
                                        ?>
                                    </td>
                                    <td><?php echo $assignment['max_score']; ?> points</td>
                                    <?php if ($_SESSION['role'] === 'teacher'): ?>
                                        <td><?php echo $assignment['submission_count']; ?></td>
                                        <td><?php echo $assignment['graded_count']; ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($_SESSION['role'] === 'teacher' && $course['teacher_id'] === $_SESSION['user_id']): ?>
                                                <a href="edit.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 