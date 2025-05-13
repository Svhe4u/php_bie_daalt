<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle user deletion
if (isset($_POST['delete_user'])) {
    $user_id = filter_var($_POST['user_id'], FILTER_SANITIZE_NUMBER_INT);
    
    // Don't allow deleting yourself
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "Өөрийгөө устгах боломжгүй";
        header("Location: manage.php");
        exit();
    }
    
    // Check if this is the last admin
    $stmt = $conn->prepare("
        SELECT COUNT(*) as admin_count 
        FROM users 
        WHERE role = 'admin' AND id != ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['admin_count'] == 0) {
        $_SESSION['error'] = "Сүүлчийн админ хэрэглэгчийг устгах боломжгүй";
        header("Location: manage.php");
        exit();
    }
    
    // Delete user
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Хэрэглэгч амжилттай устгагдлаа";
    } else {
        $_SESSION['error'] = "Хэрэглэгч устгахад алдаа гарлаа";
    }
    header("Location: manage.php");
    exit();
}

// Handle user update
if (isset($_POST['update_user'])) {
    $user_id = filter_var($_POST['user_id'], FILTER_SANITIZE_NUMBER_INT);
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $role = filter_var($_POST['role'], FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
    
    // Validate role
    if (!in_array($role, ['admin', 'teacher', 'student'])) {
        $_SESSION['error'] = "Буруу хэрэглэгчийн төрөл";
        header("Location: manage.php");
        exit();
    }
    
    // If changing role of last admin, prevent it
    if ($role !== 'admin') {
        // First get the current user's role
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $current_role = $stmt->get_result()->fetch_assoc()['role'];
        
        // Then check if this is the last admin
        if ($current_role === 'admin') {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as admin_count 
                FROM users 
                WHERE role = 'admin' AND id != ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $other_admins = $stmt->get_result()->fetch_assoc()['admin_count'];
            
            if ($other_admins == 0) {
                $_SESSION['error'] = "Сүүлчийн админ хэрэглэгчийн төрлийг өөрчлөх боломжгүй";
                header("Location: manage.php");
                exit();
            }
        }
    }
    
    // Update user
    if ($password) {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $email, $role, $password, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $email, $role, $user_id);
    }
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Хэрэглэгч амжилттай шинэчлэгдлээ";
    } else {
        $_SESSION['error'] = "Хэрэглэгч шинэчлэхэд алдаа гарлаа";
    }
    header("Location: manage.php");
    exit();
}

// Get all users
$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.name,
        u.email,
        u.role,
        u.created_at,
        (SELECT COUNT(*) FROM courses WHERE teacher_id = u.id) as course_count,
        (SELECT COUNT(*) FROM course_enrollments WHERE student_id = u.id) as enrolled_courses
    FROM users u
    ORDER BY u.created_at DESC
");
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Хэрэглэгчдийн удирдлага - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard/admin.php">
                <img src="../assets/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                Хэрэглэгчдийн удирдлага
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard/admin.php">Хяналтын самбар руу буцах</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h4 class="mb-0">Хэрэглэгчид</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Нэр</th>
                                <th>И-мэйл</th>
                                <th>Төрөл</th>
                                <th>Хичээл</th>
                                <th>Бүртгүүлсэн</th>
                                <th>Үйлдэл</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-person text-primary me-2"></i>
                                            <?php echo htmlspecialchars($user['name']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $user['role'] === 'admin' ? 'danger' : 
                                                ($user['role'] === 'teacher' ? 'success' : 'primary'); 
                                        ?>">
                                            <?php 
                                            echo $user['role'] === 'admin' ? 'Админ' : 
                                                ($user['role'] === 'teacher' ? 'Багш' : 'Оюутан'); 
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['role'] === 'teacher'): ?>
                                            <span class="badge bg-info">
                                                <i class="bi bi-book me-1"></i>
                                                <?php echo $user['course_count']; ?> хичээл
                                            </span>
                                        <?php elseif ($user['role'] === 'student'): ?>
                                            <span class="badge bg-info">
                                                <i class="bi bi-book me-1"></i>
                                                <?php echo $user['enrolled_courses']; ?> хичээл
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-primary btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?php echo $user['id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button type="button" 
                                                        class="btn btn-danger btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal<?php echo $user['id']; ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal<?php echo $user['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Хэрэглэгч засварлах</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST" action="">
                                                <div class="modal-body">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label for="name<?php echo $user['id']; ?>" class="form-label">Нэр</label>
                                                        <input type="text" class="form-control" id="name<?php echo $user['id']; ?>" 
                                                               name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="email<?php echo $user['id']; ?>" class="form-label">И-мэйл</label>
                                                        <input type="email" class="form-control" id="email<?php echo $user['id']; ?>" 
                                                               name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="role<?php echo $user['id']; ?>" class="form-label">Төрөл</label>
                                                        <select class="form-select" id="role<?php echo $user['id']; ?>" name="role" required>
                                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Админ</option>
                                                            <option value="teacher" <?php echo $user['role'] === 'teacher' ? 'selected' : ''; ?>>Багш</option>
                                                            <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Оюутан</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="password<?php echo $user['id']; ?>" class="form-label">Шинэ нууц үг (хоосон үлдээх)</label>
                                                        <input type="password" class="form-control" id="password<?php echo $user['id']; ?>" 
                                                               name="password" minlength="6">
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Цуцлах</button>
                                                    <button type="submit" name="update_user" class="btn btn-primary">Хадгалах</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Modal -->
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <div class="modal fade" id="deleteModal<?php echo $user['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Хэрэглэгч устгах</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Та <?php echo htmlspecialchars($user['name']); ?> хэрэглэгчийг устгахдаа итгэлтэй байна уу?</p>
                                                    <?php if ($user['role'] === 'admin'): ?>
                                                        <div class="alert alert-warning">
                                                            <i class="bi bi-exclamation-triangle"></i>
                                                            Админ хэрэглэгчийг устгахад болгоомжтой байна уу!
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Цуцлах</button>
                                                    <form method="POST" action="" class="d-inline">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" name="delete_user" class="btn btn-danger">Устгах</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 