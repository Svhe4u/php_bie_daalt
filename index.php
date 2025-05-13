<?php
session_start();
require_once 'db.php';

// Check if user is already logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $is_logged_in ? $_SESSION['role'] : null;
$user_name = $is_logged_in ? $_SESSION['user_name'] : null;
?>
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
                Сургалтын Үнэлгээний Систем
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if ($is_logged_in): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i>
                                <?php echo htmlspecialchars($user_name); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="dashboard/<?php echo $user_role; ?>.php">
                                        <i class="bi bi-speedometer2 me-2"></i>Хяналтын самбар
                                    </a>
                                </li>
                                <?php if ($user_role === 'admin'): ?>
                                    <li>
                                        <a class="dropdown-item" href="users/manage.php">
                                            <i class="bi bi-people me-2"></i>Хэрэглэгчид
                                        </a>
                                    </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i>Гарах
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Нэвтрэх</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="signup.php">Бүртгүүлэх</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <?php if ($is_logged_in): ?>
            <div class="row justify-content-center">
                <div class="col-md-8 text-center">
                    <h1 class="display-4 mb-4">Сайн байна уу, <?php echo htmlspecialchars($user_name); ?>!</h1>
                    <p class="lead mb-5">
                        <?php
                        switch ($user_role) {
                            case 'admin':
                                echo 'Системийн удирдлага, хэрэглэгчдийн удирдлага болон бусад үйл ажиллагааг хийх боломжтой.';
                                break;
                            case 'teacher':
                                echo 'Хичээлүүдээ удирдах, оюутнуудын үнэлгээг харах боломжтой.';
                                break;
                            case 'student':
                                echo 'Хичээлүүдээ үнэлэх, санал хүсэлтээ өгөх боломжтой.';
                                break;
                        }
                        ?>
                    </p>
                    <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                        <a href="dashboard/<?php echo $user_role; ?>.php" class="btn btn-primary btn-lg px-4 gap-3">
                            <i class="bi bi-speedometer2 me-2"></i>Хяналтын самбар руу очих
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-md-8 text-center">
                    <h1 class="display-4 mb-4">Сургалтын Үнэлгээний Системд тавтай морил</h1>
                    <p class="lead mb-5">Оюутнуудын санал хүсэлтийг цуглуулж, боловсролын чанарыг сайжруулах платформ.</p>
                    
                    <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                        <a href="login.php" class="btn btn-primary btn-lg px-4 gap-3">Нэвтрэх</a>
                        <a href="signup.php" class="btn btn-outline-secondary btn-lg px-4">Бүртгүүлэх</a>
                    </div>
                </div>
            </div>

            <div class="row mt-5">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-mortarboard text-primary me-2"></i>
                                Оюутнуудад
                            </h5>
                            <p class="card-text">Сургалтын талаар санал хүсэлтээ өгөөд сургалтын чанарыг сайжруулахад хувь нэмэр оруул.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-person-workspace text-success me-2"></i>
                                Багш нарт
                            </h5>
                            <p class="card-text">Оюутнуудын санал хүсэлтийг хараад заах арга барилаа сайжруул.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-gear text-danger me-2"></i>
                                Админ удирдлагад
                            </h5>
                            <p class="card-text">Үнэлгээний мэдээллийг хянаж, шийдвэр гаргахад дэмжлэг үзүүл.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer class="footer mt-5 py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">© 2024 Сургалтын Үнэлгээний Систем. Бүх эрх хуулиар хамгаалагдсан.</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
