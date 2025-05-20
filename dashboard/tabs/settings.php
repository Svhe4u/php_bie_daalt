<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/../../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Create user_settings table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS user_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    language VARCHAR(10) DEFAULT 'mn',
    timezone VARCHAR(50) DEFAULT 'Asia/Ulaanbaatar',
    date_format VARCHAR(20) DEFAULT 'Y-m-d',
    theme VARCHAR(20) DEFAULT 'light',
    font_size VARCHAR(20) DEFAULT 'medium',
    email_notifications TINYINT(1) DEFAULT 1,
    system_notifications TINYINT(1) DEFAULT 1,
    email_assignments TINYINT(1) DEFAULT 1,
    email_messages TINYINT(1) DEFAULT 1,
    notification_assignments TINYINT(1) DEFAULT 1,
    notification_messages TINYINT(1) DEFAULT 1,
    notification_announcements TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

$conn->query($create_table_sql);

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header("Location: ../../login.php");
    exit();
}

// Get user settings
$stmt = $conn->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();

// If no settings exist, create default settings
if (!$settings) {
    $default_settings = [
        'language' => 'mn',
        'timezone' => 'Asia/Ulaanbaatar',
        'date_format' => 'Y-m-d',
        'theme' => 'light',
        'font_size' => 'medium',
        'email_notifications' => 1,
        'system_notifications' => 1,
        'email_assignments' => 1,
        'email_messages' => 1,
        'notification_assignments' => 1,
        'notification_messages' => 1,
        'notification_announcements' => 1
    ];
    
    $stmt = $conn->prepare("INSERT INTO user_settings (user_id, language, timezone, date_format, theme, font_size, 
                           email_notifications, system_notifications, email_assignments, email_messages,
                           notification_assignments, notification_messages, notification_announcements) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("isssssiiiiiii", 
        $_SESSION['user_id'],
        $default_settings['language'],
        $default_settings['timezone'],
        $default_settings['date_format'],
        $default_settings['theme'],
        $default_settings['font_size'],
        $default_settings['email_notifications'],
        $default_settings['system_notifications'],
        $default_settings['email_assignments'],
        $default_settings['email_messages'],
        $default_settings['notification_assignments'],
        $default_settings['notification_messages'],
        $default_settings['notification_announcements']
    );
    
    if (!$stmt->execute()) {
        error_log("Failed to create user settings: " . $stmt->error);
        die("An error occurred while setting up your preferences. Please try again later.");
    }
    
    $settings = $default_settings;
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тохиргоо - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        .settings-card {
            transition: transform 0.2s;
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            height: 100%;
        }
        .settings-card:hover {
            transform: translateY(-5px);
        }
        .nav-link {
            color: #6c757d;
            transition: all 0.2s;
        }
        .nav-link:hover {
            color: #0d6efd;
            background-color: #f8f9fa;
        }
        .nav-link.active {
            color: #0d6efd;
            background-color: #e9ecef;
        }
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <?php if ($user['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile" class="rounded-circle" width="80">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto" 
                                 style="width: 80px; height: 80px; font-size: 2rem;">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <h5 class="mt-2"><?php echo htmlspecialchars($user['name']); ?></h5>
                        <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../student.php">
                                <i class="bi bi-house-door"></i> Хяналтын самбар
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../student_courses.php">
                                <i class="bi bi-book"></i> Хичээлүүд
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="settings.php">
                                <i class="bi bi-gear"></i> Тохиргоо
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Гарах
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Тохиргоо</h1>
                </div>

                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card settings-card mb-4">
                            <div class="card-body text-center p-4">
                                <div class="mb-4">
                                    <?php if ($user['profile_picture']): ?>
                                        <img src="<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                             alt="Profile" class="rounded-circle img-fluid shadow-sm" style="width: 150px; height: 150px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto shadow-sm" 
                                             style="width: 150px; height: 150px; font-size: 4rem;">
                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <h5 class="card-title mb-1"><?php echo htmlspecialchars($user['name']); ?></h5>
                                <p class="text-muted mb-3"><?php echo ucfirst($user['role']); ?></p>
                                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#changePhotoModal">
                                    <i class="bi bi-camera me-1"></i> Зураг солих
                                </button>
                            </div>
                        </div>

                        <div class="card settings-card">
                            <div class="card-header bg-white border-0 py-3">
                                <h5 class="card-title mb-0 text-primary">Хэрэглэгчийн тохиргоо</h5>
                            </div>
                            <div class="list-group list-group-flush">
                                <a href="#profile" class="list-group-item list-group-item-action active border-0 py-3" data-bs-toggle="list">
                                    <i class="bi bi-person me-2"></i> Хувийн мэдээлэл
                                </a>
                                <a href="#security" class="list-group-item list-group-item-action border-0 py-3" data-bs-toggle="list">
                                    <i class="bi bi-shield-lock me-2"></i> Нууц үг
                                </a>
                                <a href="#notifications" class="list-group-item list-group-item-action border-0 py-3" data-bs-toggle="list">
                                    <i class="bi bi-bell me-2"></i> Мэдэгдэл
                                </a>
                                <a href="#preferences" class="list-group-item list-group-item-action border-0 py-3" data-bs-toggle="list">
                                    <i class="bi bi-gear me-2"></i> Тохиргоо
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="tab-content">
                            <!-- Profile Settings -->
                            <div class="tab-pane fade show active" id="profile">
                                <div class="card settings-card">
                                    <div class="card-header bg-white border-0 py-3">
                                        <h5 class="card-title mb-0 text-primary">Хувийн мэдээлэл</h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <div id="profile-message" class="alert d-none" role="alert"></div>
                                        <form id="profile-form" action="update_profile.php" method="POST">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="name" class="form-label">Нэр</label>
                                                    <input type="text" class="form-control" id="name" name="name" 
                                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="email" class="form-label">И-мэйл</label>
                                                    <input type="email" class="form-control" id="email" name="email" 
                                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="phone" class="form-label">Утас</label>
                                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                                           value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label for="description" class="form-label">Товч танилцуулга</label>
                                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($user['description'] ?? ''); ?></textarea>
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary px-4">Хадгалах</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Settings -->
                            <div class="tab-pane fade" id="security">
                                <div class="card settings-card">
                                    <div class="card-header bg-white border-0 py-3">
                                        <h5 class="card-title mb-0 text-primary">Нууц үг солих</h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <div id="password-message" class="alert d-none" role="alert"></div>
                                        <form id="password-form" action="change_password.php" method="POST">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <label for="current_password" class="form-label">Одоогийн нууц үг</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <label for="new_password" class="form-label">Шинэ нууц үг</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </div>
                                                    <div class="form-text">
                                                        Нууц үг дор хаяж 8 тэмдэгт, том үсэг, жижиг үсэг, тоо агуулсан байх ёстой.
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <label for="confirm_password" class="form-label">Шинэ нууц үг давтах</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary px-4">Нууц үг солих</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Notification Settings -->
                            <div class="tab-pane fade" id="notifications">
                                <div class="card settings-card">
                                    <div class="card-header bg-white border-0 py-3">
                                        <h5 class="card-title mb-0 text-primary">Мэдэгдлийн тохиргоо</h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <div id="notifications-message" class="alert d-none" role="alert"></div>
                                        <form id="notifications-form" action="update_notifications.php" method="POST">
                                            <div class="row g-4">
                                                <div class="col-12">
                                                    <div class="card bg-light border-0">
                                                        <div class="card-body">
                                                            <h6 class="card-title mb-3">И-мэйл мэдэгдэл</h6>
                                                            <div class="mb-3">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                                                           <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="email_notifications">И-мэйл мэдэгдэл идэвхжүүлэх</label>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" id="email_assignments" name="email_assignments" 
                                                                           <?php echo $settings['email_assignments'] ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="email_assignments">Шинэ даалгавар</label>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" id="email_messages" name="email_messages" 
                                                                           <?php echo $settings['email_messages'] ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="email_messages">Шинэ мессэж</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12">
                                                    <div class="card bg-light border-0">
                                                        <div class="card-body">
                                                            <h6 class="card-title mb-3">Системийн мэдэгдэл</h6>
                                                            <div class="mb-3">
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" id="system_notifications" name="system_notifications" 
                                                                           <?php echo $settings['system_notifications'] ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="system_notifications">Системийн мэдэгдэл идэвхжүүлэх</label>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" id="notification_assignments" name="notification_assignments" 
                                                                           <?php echo $settings['notification_assignments'] ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="notification_assignments">Шинэ даалгавар</label>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" id="notification_messages" name="notification_messages" 
                                                                           <?php echo $settings['notification_messages'] ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="notification_messages">Шинэ мессэж</label>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" id="notification_announcements" name="notification_announcements" 
                                                                           <?php echo $settings['notification_announcements'] ? 'checked' : ''; ?>>
                                                                    <label class="form-check-label" for="notification_announcements">Шинэ мэдэгдэл</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary px-4">Хадгалах</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Preferences Settings -->
                            <div class="tab-pane fade" id="preferences">
                                <div class="card settings-card">
                                    <div class="card-header bg-white border-0 py-3">
                                        <h5 class="card-title mb-0 text-primary">Тохиргоо</h5>
                                    </div>
                                    <div class="card-body p-4">
                                        <div id="preferences-message" class="alert d-none" role="alert"></div>
                                        <form id="preferences-form" action="update_preferences.php" method="POST">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="language" class="form-label">Хэл</label>
                                                    <select class="form-select" id="language" name="language">
                                                        <option value="mn" <?php echo $settings['language'] === 'mn' ? 'selected' : ''; ?>>Монгол</option>
                                                        <option value="en" <?php echo $settings['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="timezone" class="form-label">Цагийн бүс</label>
                                                    <select class="form-select" id="timezone" name="timezone">
                                                        <option value="Asia/Ulaanbaatar" <?php echo $settings['timezone'] === 'Asia/Ulaanbaatar' ? 'selected' : ''; ?>>Улаанбаатар (UTC+8)</option>
                                                        <option value="Asia/Tokyo" <?php echo $settings['timezone'] === 'Asia/Tokyo' ? 'selected' : ''; ?>>Токио (UTC+9)</option>
                                                        <option value="America/New_York" <?php echo $settings['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>Нью Йорк (UTC-5)</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="date_format" class="form-label">Огнооны формат</label>
                                                    <select class="form-select" id="date_format" name="date_format">
                                                        <option value="Y-m-d" <?php echo $settings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                                        <option value="d/m/Y" <?php echo $settings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                                        <option value="m/d/Y" <?php echo $settings['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="theme" class="form-label">Загвар</label>
                                                    <select class="form-select" id="theme" name="theme">
                                                        <option value="light" <?php echo $settings['theme'] === 'light' ? 'selected' : ''; ?>>Гэрэлт</option>
                                                        <option value="dark" <?php echo $settings['theme'] === 'dark' ? 'selected' : ''; ?>>Харанхуй</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="font_size" class="form-label">Үсгийн хэмжээ</label>
                                                    <select class="form-select" id="font_size" name="font_size">
                                                        <option value="small" <?php echo $settings['font_size'] === 'small' ? 'selected' : ''; ?>>Жижиг</option>
                                                        <option value="medium" <?php echo $settings['font_size'] === 'medium' ? 'selected' : ''; ?>>Дунд</option>
                                                        <option value="large" <?php echo $settings['font_size'] === 'large' ? 'selected' : ''; ?>>Том</option>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary px-4">Хадгалах</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Change Photo Modal -->
    <div class="modal fade" id="changePhotoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-primary">Профайл зураг солих</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="photo-message" class="alert d-none" role="alert"></div>
                    <form id="photo-form" action="update_photo.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="profile_picture" class="form-label">Зураг сонгох</label>
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*" required>
                            <div class="form-text">
                                Зөвхөн JPG, PNG, GIF зургууд. Хамгийн их хэмжээ: 2MB
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary px-4">Зураг солих</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching
        const tabLinks = document.querySelectorAll('[data-bs-toggle="list"]');
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = this.getAttribute('href');
                
                // Remove active class from all links and panes
                tabLinks.forEach(l => l.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('show', 'active'));
                
                // Add active class to clicked link and show corresponding pane
                this.classList.add('active');
                document.querySelector(target).classList.add('show', 'active');
            });
        });

        // Form submission handlers
        const forms = {
            'profile-form': 'profile-message',
            'password-form': 'password-message',
            'notifications-form': 'notifications-message',
            'preferences-form': 'preferences-message',
            'photo-form': 'photo-message'
        };

        Object.entries(forms).forEach(([formId, messageId]) => {
            const form = document.getElementById(formId);
            const messageDiv = document.getElementById(messageId);
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch(this.action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        messageDiv.textContent = data.message;
                        messageDiv.classList.remove('d-none', 'alert-success', 'alert-danger');
                        messageDiv.classList.add(data.success ? 'alert-success' : 'alert-danger');
                        
                        if (data.success) {
                            if (formId === 'password-form') {
                                form.reset();
                            }
                            if (formId === 'photo-form') {
                                location.reload();
                            }
                        }
                        
                        setTimeout(() => {
                            messageDiv.classList.add('d-none');
                        }, 5000);
                    })
                    .catch(error => {
                        messageDiv.textContent = 'Алдаа гарлаа. Дараа дахин оролдоно уу.';
                        messageDiv.classList.remove('d-none', 'alert-success');
                        messageDiv.classList.add('alert-danger');
                        
                        setTimeout(() => {
                            messageDiv.classList.add('d-none');
                        }, 5000);
                    });
                });
            }
        });
    });

    // Password visibility toggle
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const icon = input.nextElementSibling.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
    </script>
</body>
</html> 