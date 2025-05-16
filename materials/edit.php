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

// Get material ID from URL
$material_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get material details
$stmt = $conn->prepare("
    SELECT m.*, c.title as course_title 
    FROM materials m 
    JOIN courses c ON m.course_id = c.id 
    WHERE m.id = ? AND m.created_by = ?
");
$stmt->bind_param("ii", $material_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$material = $result->fetch_assoc();

if (!$material) {
    header('Location: ../dashboard/teacher.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];

    if (empty($title)) {
        $error = 'Please fill in all required fields';
    } else {
        // Handle file upload if a new file is provided
        $file_path = $material['file_path'];
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/materials/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'ppt', 'pptx'];

            if (!in_array($file_extension, $allowed_extensions)) {
                $error = 'Invalid file type. Allowed types: PDF, DOC, DOCX, TXT, ZIP, RAR, PPT, PPTX';
            } else {
                $new_filename = uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['file']['tmp_name'], $upload_path)) {
                    // Delete old file if exists
                    if ($file_path && file_exists('../' . $file_path)) {
                        unlink('../' . $file_path);
                    }
                    $file_path = 'uploads/materials/' . $new_filename;
                } else {
                    $error = 'Failed to upload file';
                }
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare("
                UPDATE materials 
                SET title = ?, description = ?, type = ?, file_path = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ? AND created_by = ?
            ");
            $stmt->bind_param("ssssii", $title, $description, $type, $file_path, $material_id, $_SESSION['user_id']);

            if ($stmt->execute()) {
                $success = 'Material updated successfully';
                // Refresh material data
                $stmt = $conn->prepare("SELECT * FROM materials WHERE id = ?");
                $stmt->bind_param("i", $material_id);
                $stmt->execute();
                $material = $stmt->get_result()->fetch_assoc();
            } else {
                $error = 'Failed to update material';
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
    <title>Edit Material - <?php echo htmlspecialchars($material['title']); ?></title>
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
                        <h4 class="mb-0">Edit Material</h4>
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
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($material['course_title']); ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($material['title']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="4"><?php echo htmlspecialchars($material['description']); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="type">
                                    <option value="lecture" <?php echo $material['type'] === 'lecture' ? 'selected' : ''; ?>>Lecture</option>
                                    <option value="reading" <?php echo $material['type'] === 'reading' ? 'selected' : ''; ?>>Reading</option>
                                    <option value="resource" <?php echo $material['type'] === 'resource' ? 'selected' : ''; ?>>Resource</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Material File</label>
                                <?php if ($material['file_path']): ?>
                                    <div class="mb-2">
                                        <a href="../<?php echo htmlspecialchars($material['file_path']); ?>" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-file"></i> Current File
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" class="form-control" name="file">
                                <small class="text-muted">Leave empty to keep current file. Allowed types: PDF, DOC, DOCX, TXT, ZIP, RAR, PPT, PPTX</small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Update Material</button>
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