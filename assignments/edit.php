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

// Get assignment ID from URL
$assignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get assignment details
$stmt = $conn->prepare("
    SELECT a.*, c.title as course_title 
    FROM assignments a 
    JOIN courses c ON a.course_id = c.id 
    WHERE a.id = ? AND a.created_by = ?
");
$stmt->bind_param("ii", $assignment_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$assignment = $result->fetch_assoc();

if (!$assignment) {
    header('Location: ../dashboard/teacher.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $max_score = (int)$_POST['max_score'];
    $allow_late = isset($_POST['allow_late']) ? 1 : 0;

    if (empty($title) || empty($due_date) || $max_score <= 0) {
        $error = 'Please fill in all required fields';
    } else {
        // Handle file upload if a new file is provided
        $file_path = $assignment['file_path'];
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/assignments/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];

            if (!in_array($file_extension, $allowed_extensions)) {
                $error = 'Invalid file type. Allowed types: PDF, DOC, DOCX, TXT, ZIP, RAR';
            } else {
                $new_filename = uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['file']['tmp_name'], $upload_path)) {
                    // Delete old file if exists
                    if ($file_path && file_exists('../' . $file_path)) {
                        unlink('../' . $file_path);
                    }
                    $file_path = 'uploads/assignments/' . $new_filename;
                } else {
                    $error = 'Failed to upload file';
                }
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare("
                UPDATE assignments 
                SET title = ?, description = ?, due_date = ?, max_score = ?, 
                    allow_late = ?, file_path = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND created_by = ?
            ");
            $stmt->bind_param("sssiisii", $title, $description, $due_date, $max_score, 
                            $allow_late, $file_path, $assignment_id, $_SESSION['user_id']);

            if ($stmt->execute()) {
                $success = 'Assignment updated successfully';
                // Refresh assignment data
                $stmt = $conn->prepare("SELECT * FROM assignments WHERE id = ?");
                $stmt->bind_param("i", $assignment_id);
                $stmt->execute();
                $assignment = $stmt->get_result()->fetch_assoc();
            } else {
                $error = 'Failed to update assignment';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Assignment - <?php echo htmlspecialchars($assignment['title']); ?></title>
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
                        <h4 class="mb-0">Edit Assignment</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Course</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($assignment['course_title']); ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($assignment['title']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="4"><?php echo htmlspecialchars($assignment['description']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Due Date <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" name="due_date" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($assignment['due_date'])); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Maximum Score <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="max_score" 
                                       value="<?php echo $assignment['max_score']; ?>" min="1" required>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="allow_late" id="allow_late" 
                                           <?php echo $assignment['allow_late'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="allow_late">Allow Late Submissions</label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Assignment File</label>
                                <?php if ($assignment['file_path']): ?>
                                    <div class="mb-2">
                                        <a href="../<?php echo htmlspecialchars($assignment['file_path']); ?>" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-file"></i> Current File
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" name="file">
                                <small class="text-muted">Leave empty to keep current file. Allowed types: PDF, DOC, DOCX, TXT, ZIP, RAR</small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Update Assignment</button>
                                <a href="../dashboard/teacher.php" class="btn btn-secondary">Cancel</a>
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