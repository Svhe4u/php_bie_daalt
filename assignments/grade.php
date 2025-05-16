<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

$error = '';
$success = '';

// Get submission ID from URL
$submission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get submission details with assignment and student info
$stmt = $conn->prepare("
    SELECT s.*, a.title as assignment_title, a.max_score, a.course_id,
           u.name as student_name, u.email as student_email
    FROM assignment_submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN users u ON s.student_id = u.id
    WHERE s.id = ? AND a.created_by = ?
");
$stmt->bind_param("ii", $submission_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$submission = $result->fetch_assoc();

if (!$submission) {
    header('Location: ../dashboard/teacher.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $score = (int)$_POST['score'];
    $feedback = trim($_POST['feedback']);

    if ($score < 0 || $score > $submission['max_score']) {
        $error = 'Score must be between 0 and ' . $submission['max_score'];
    } else {
        $stmt = $conn->prepare("
            UPDATE assignment_submissions 
            SET score = ?, feedback = ?, status = 'graded', 
                graded_by = ?, graded_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->bind_param("isii", $score, $feedback, $_SESSION['user_id'], $submission_id);

        if ($stmt->execute()) {
            $success = 'Submission graded successfully';
            // Refresh submission data
            $stmt = $conn->prepare("
                SELECT s.*, a.title as assignment_title, a.max_score, a.course_id,
                       u.name as student_name, u.email as student_email
                FROM assignment_submissions s
                JOIN assignments a ON s.assignment_id = a.id
                JOIN users u ON s.student_id = u.id
                WHERE s.id = ?
            ");
            $stmt->bind_param("i", $submission_id);
            $stmt->execute();
            $submission = $stmt->get_result()->fetch_assoc();
        } else {
            $error = 'Failed to grade submission';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Submission - <?php echo htmlspecialchars($submission['assignment_title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard/teacher.php">Teacher Dashboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard/teacher.php">Dashboard</a>
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
                    <div class="card-header">
                        <h4 class="mb-0">Grade Submission</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <h5 class="card-title"><?php echo htmlspecialchars($submission['assignment_title']); ?></h5>
                        <h6 class="card-subtitle mb-3 text-muted">
                            Student: <?php echo htmlspecialchars($submission['student_name']); ?>
                            (<?php echo htmlspecialchars($submission['student_email']); ?>)
                        </h6>

                        <div class="mb-4">
                            <h6>Submission Details:</h6>
                            <p>Submitted on: <?php echo date('F j, Y g:i A', strtotime($submission['submitted_at'])); ?></p>
                            
                            <?php if ($submission['file_path']): ?>
                                <a href="../<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank" class="btn btn-info">
                                    <i class="fas fa-file"></i> View Submission
                                </a>
                            <?php endif; ?>
                        </div>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Score (out of <?php echo $submission['max_score']; ?>)</label>
                                <input type="number" class="form-control" name="score" 
                                       value="<?php echo $submission['score'] ?? ''; ?>" 
                                       min="0" max="<?php echo $submission['max_score']; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Feedback</label>
                                <textarea class="form-control" name="feedback" rows="4"><?php echo htmlspecialchars($submission['feedback'] ?? ''); ?></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Submit Grade</button>
                                <a href="view.php?id=<?php echo $submission['assignment_id']; ?>" class="btn btn-secondary">Back to Assignment</a>
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