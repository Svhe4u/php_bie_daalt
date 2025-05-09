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
    
    // Check if this is the last admin
    $stmt = $conn->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'");
    $stmt->execute();
    $admin_count = $stmt->get_result()->fetch_assoc()['admin_count'];
    
    if ($admin_count <= 1) {
        $_SESSION['error'] = "Сүүлчийн админ хэрэглэгчийг устгах боломжгүй";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Хэрэглэгч амжилттай устгагдлаа";
        } else {
            $_SESSION['error'] = "Хэрэглэгчийг устгахад алдаа гарлаа";
        }
    }
    header("Location: manage.php");
    exit();
}

// Handle role update
if (isset($_POST['update_role'])) {
    $user_id = filter_var($_POST['user_id'], FILTER_SANITIZE_NUMBER_INT);
    $new_role = filter_var($_POST['new_role'], FILTER_SANITIZE_STRING);
    
    // Check if this is the last admin
    if ($new_role !== 'admin') {
        $stmt = $conn->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'");
        $stmt->execute();
        $admin_count = $stmt->get_result()->fetch_assoc()['admin_count'];
        
        if ($admin_count <= 1) {
            $_SESSION['error'] = "Сүүлчийн админ хэрэглэгчийн үүргийг өөрчлөх боломжгүй";
            header("Location: manage.php");
            exit();
        }
    }
    
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->bind_param("si", $new_role, $user_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Хэрэглэгчийн үүрэг амжилттай шинэчлэгдлээ";
    } else {
        $_SESSION['error'] = "Хэрэглэгчийн үүргийг шинэчлэхэд алдаа гарлаа";
    }
    header("Location: manage.php");
    exit();
}

// Get all users
$stmt = $conn->prepare("
    SELECT id, name, email, role, created_at
    FROM users
    ORDER BY created_at DESC
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
                <h4 class="mb-0">Хэрэглэгчдийн жагсаалт</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Нэр</th>
                                <th>Имэйл</th>
                                <th>Үүрэг</th>
                                <th>Бүртгүүлсэн</th>
                                <th>Үйлдэл</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <select name="new_role" class="form-select form-select-sm" onchange="this.form.submit()">
                                                <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Оюутан</option>
                                                <option value="teacher" <?php echo $user['role'] === 'teacher' ? 'selected' : ''; ?>>Багш</option>
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Админ</option>
                                            </select>
                                            <input type="hidden" name="update_role" value="1">
                                        </form>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" action="" class="d-inline" 
                                              onsubmit="return confirm('Энэ хэрэглэгчийг устгахдаа итгэлтэй байна уу?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-danger btn-sm">
                                                Устгах
                                            </button>
                                        </form>
                                    </td>
                                </tr>
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