<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$error = '';
$success = '';

// Get assignment ID from URL
$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get assignment details with course and teacher info
$stmt = $conn->prepare("
    SELECT a.*, c.title as course_title, c.id as course_id,
           u.name as teacher_name, u.email as teacher_email
    FROM assignments a 
    JOIN courses c ON a.course_id = c.id 
    JOIN users u ON a.created_by = u.id
    WHERE a.id = ?
");
$stmt->bind_param("i", $assignment_id);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result->fetch_assoc();

if (!$assignment) {
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
    $stmt->bind_param("ii", $assignment['course_id'], $_SESSION['user_id']);
    $stmt->execute();
    $is_enrolled = $stmt->get_result()->num_rows > 0;
}

// Get submission details if student
$submission = null;
if ($_SESSION['role'] === 'student' && $is_enrolled) {
    $stmt = $conn->prepare("
        SELECT s.*, u.name as graded_by_name
        FROM assignment_submissions s
        LEFT JOIN users u ON s.graded_by = u.id
        WHERE s.assignment_id = ? AND s.student_id = ?
    ");
    $stmt->bind_param("ii", $assignment_id, $_SESSION['user_id']);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['role'] === 'student' && $is_enrolled) {
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/submissions/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];

        if (!in_array($file_extension, $allowed_extensions)) {
            $error = 'Invalid file type. Allowed types: PDF, DOC, DOCX, TXT, ZIP, RAR';
        } else {
            $new_filename = uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $upload_path)) {
                $file_path = 'uploads/submissions/' . $new_filename;
                
                if ($submission) {
                    // Update existing submission
                    $stmt = $conn->prepare("
                        UPDATE assignment_submissions 
                        SET file_path = ?, submitted_at = CURRENT_TIMESTAMP, status = 'submitted'
                        WHERE assignment_id = ? AND student_id = ?
                    ");
                    $stmt->bind_param("sii", $file_path, $assignment_id, $_SESSION['user_id']);
                } else {
                    // Create new submission
                    $stmt = $conn->prepare("
                        INSERT INTO assignment_submissions 
                        (assignment_id, student_id, file_path, submitted_at, status)
                        VALUES (?, ?, ?, CURRENT_TIMESTAMP, 'submitted')
                    ");
                    $stmt->bind_param("iis", $assignment_id, $_SESSION['user_id'], $file_path);
                }

                if ($stmt->execute()) {
                    $success = 'Assignment submitted successfully';
                    // Refresh submission data
                    $stmt = $conn->prepare("
                        SELECT s.*, u.name as graded_by_name
                        FROM assignment_submissions s
                        LEFT JOIN users u ON s.graded_by = u.id
                        WHERE s.assignment_id = ? AND s.student_id = ?
                    ");
                    $stmt->bind_param("ii", $assignment_id, $_SESSION['user_id']);
                    $stmt->execute();
                    $submission = $stmt->get_result()->fetch_assoc();
                } else {
                    $error = 'Failed to submit assignment';
                }
            } else {
                $error = 'Failed to upload file';
            }
        }
    } else {
        $error = 'Please select a file to submit';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Assignment - <?php echo htmlspecialchars($assignment['title']); ?></title>
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
                        <h4 class="mb-0">Assignment Details</h4>
                        <?php if ($_SESSION['role'] === 'teacher' && $assignment['created_by'] === $_SESSION['user_id']): ?>
                            <a href="edit.php?id=<?php echo $assignment_id; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-edit"></i> Edit Assignment
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <h5 class="card-title"><?php echo htmlspecialchars($assignment['title']); ?></h5>
                        <h6 class="card-subtitle mb-3 text-muted">Course: <?php echo htmlspecialchars($assignment['course_title']); ?></h6>

                        <div class="mb-4">
                            <h6>Description:</h6>
                            <p class="card-text"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Due Date:</h6>
                                <p><?php echo date('F j, Y g:i A', strtotime($assignment['due_date'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6>Maximum Score:</h6>
                                <p><?php echo $assignment['max_score']; ?> points</p>
                            </div>
                        </div>

                        <?php if ($assignment['file_path']): ?>
                            <div class="mb-4">
                                <h6>Assignment File:</h6>
                                <a href="../<?php echo htmlspecialchars($assignment['file_path']); ?>" target="_blank" class="btn btn-info">
                                    <i class="fas fa-file"></i> Download Assignment
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($_SESSION['role'] === 'student'): ?>
                            <?php if (!$is_enrolled): ?>
                                <div class="alert alert-warning">
                                    You are not enrolled in this course.
                                </div>
                            <?php else: ?>
                                <div class="card mt-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Your Submission</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($submission): ?>
                                            <div class="mb-3">
                                                <h6>Status: 
                                                    <span class="badge bg-<?php 
                                                        echo $submission['status'] === 'graded' ? 'success' : 
                                                            ($submission['status'] === 'submitted' ? 'primary' : 'warning'); 
                                                    ?>">
                                                        <?php echo ucfirst($submission['status']); ?>
                                                    </span>
                                                </h6>
                                                <p>Submitted on: <?php echo date('F j, Y g:i A', strtotime($submission['submitted_at'])); ?></p>
                                                
                                                <?php if ($submission['file_path']): ?>
                                                    <a href="../<?php echo htmlspecialchars($submission['file_path']); ?>" target="_blank" class="btn btn-info btn-sm">
                                                        <i class="fas fa-file"></i> View Submission
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($submission['status'] === 'graded'): ?>
                                                    <div class="mt-3">
                                                        <h6>Grade: <?php echo $submission['score']; ?>/<?php echo $assignment['max_score']; ?></h6>
                                                        <?php if ($submission['feedback']): ?>
                                                            <h6>Feedback:</h6>
                                                            <p><?php echo nl2br(htmlspecialchars($submission['feedback'])); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!$submission || $submission['status'] !== 'graded'): ?>
                                            <form method="POST" enctype="multipart/form-data">
                                                <div class="mb-3">
                                                    <label class="form-label">Submit Your Work</label>
                                                    <input type="file" class="form-control" name="submission_file" required>
                                                    <small class="text-muted">Allowed types: PDF, DOC, DOCX, TXT, ZIP, RAR</small>
                                                </div>
                                                <button type="submit" class="btn btn-primary">
                                                    <?php echo $submission ? 'Update Submission' : 'Submit Assignment'; ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($_SESSION['role'] === 'teacher' && $assignment['created_by'] === $_SESSION['user_id']): ?>
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Submissions</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $stmt = $conn->prepare("
                                        SELECT s.*, u.name as student_name, u.email as student_email
                                        FROM assignment_submissions s
                                        JOIN users u ON s.student_id = u.id
                                        WHERE s.assignment_id = ?
                                        ORDER BY s.submitted_at DESC
                                    ");
                                    $stmt->bind_param("i", $assignment_id);
                                    $stmt->execute();
                                    $submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                    ?>

                                    <?php if (empty($submissions)): ?>
                                        <p class="text-muted">No submissions yet.</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>Student</th>
                                                        <th>Submitted</th>
                                                        <th>Status</th>
                                                        <th>Score</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($submissions as $sub): ?>
                                                        <tr>
                                                            <td>
                                                                <?php echo htmlspecialchars($sub['student_name']); ?><br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($sub['student_email']); ?></small>
                                                            </td>
                                                            <td><?php echo date('M j, Y g:i A', strtotime($sub['submitted_at'])); ?></td>
                                                            <td>
                                                                <span class="badge bg-<?php 
                                                                    echo $sub['status'] === 'graded' ? 'success' : 
                                                                        ($sub['status'] === 'submitted' ? 'primary' : 'warning'); 
                                                                ?>">
                                                                    <?php echo ucfirst($sub['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <?php echo $sub['status'] === 'graded' ? 
                                                                    $sub['score'] . '/' . $assignment['max_score'] : '-'; ?>
                                                            </td>
                                                            <td>
                                                                <a href="grade.php?id=<?php echo $sub['id']; ?>" class="btn btn-sm btn-primary">
                                                                    <i class="fas fa-check"></i> Grade
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 