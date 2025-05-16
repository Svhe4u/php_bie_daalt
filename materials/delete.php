<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['material_id'])) {
    $material_id = $_POST['material_id'];
    $course_id = $_POST['course_id'];
    
    // Get file path before deleting
    $stmt = $conn->prepare("SELECT file_path FROM materials WHERE id = ? AND course_id = ?");
    $stmt->bind_param("ii", $material_id, $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $material = $result->fetch_assoc();
    
    // Delete material
    $stmt = $conn->prepare("DELETE FROM materials WHERE id = ? AND course_id = ?");
    $stmt->bind_param("ii", $material_id, $course_id);
    
    if ($stmt->execute()) {
        // Delete file if exists
        if ($material && $material['file_path']) {
            $file_path = '../' . $material['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        $_SESSION['success'] = "Материал амжилттай устгагдлаа.";
    } else {
        $_SESSION['error'] = "Материал устгахад алдаа гарлаа.";
    }
    
    header("Location: ../dashboard/course.php?id=" . $course_id);
    exit();
} else {
    header("Location: ../dashboard/teacher.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Material - <?php echo htmlspecialchars($material['title']); ?></title>
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
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Delete Material</h4>
                    </div>
                    <div class="card-body">
                        <p class="mb-4">Are you sure you want to delete the material "<?php echo htmlspecialchars($material['title']); ?>"?</p>
                        <p class="text-danger">This action cannot be undone.</p>

                        <form method="POST">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-danger">Delete Material</button>
                                <a href="list.php?course_id=<?php echo $material['course_id']; ?>" class="btn btn-secondary">Cancel</a>
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