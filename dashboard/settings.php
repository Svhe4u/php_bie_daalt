<?php
session_start();
require_once '../db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Get teacher's profile information
$stmt = $conn->prepare("
    SELECT u.*, ts.office_hours, ts.notification_preferences, ts.availability
    FROM users u
    LEFT JOIN teacher_settings ts ON u.id = ts.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $office_hours = trim($_POST['office_hours']);
    $notification_preferences = json_encode([
        'email' => isset($_POST['notify_email']),
        'sms' => isset($_POST['notify_sms'])
    ]);
    $availability = json_encode([
        'monday' => isset($_POST['available_monday']),
        'tuesday' => isset($_POST['available_tuesday']),
        'wednesday' => isset($_POST['available_wednesday']),
        'thursday' => isset($_POST['available_thursday']),
        'friday' => isset($_POST['available_friday'])
    ]);

    // Update user information
    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $email, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        // Update or insert teacher settings
        $stmt = $conn->prepare("
            INSERT INTO teacher_settings (user_id, office_hours, notification_preferences, availability)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            office_hours = VALUES(office_hours),
            notification_preferences = VALUES(notification_preferences),
            availability = VALUES(availability)
        ");
        $stmt->bind_param("isss", $_SESSION['user_id'], $office_hours, $notification_preferences, $availability);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Тохиргоо амжилттай хадгалагдлаа.";
            header("Location: settings.php");
            exit();
        } else {
            $_SESSION['error'] = "Тохиргоо хадгалахад алдаа гарлаа.";
        }
    } else {
        $_SESSION['error'] = "Профайл мэдээлэл хадгалахад алдаа гарлаа.";
    }
}

// Parse notification preferences and availability
$notifications = json_decode($profile['notification_preferences'] ?? '{"email":true,"sms":false}', true);
$availability = json_decode($profile['availability'] ?? '{"monday":true,"tuesday":true,"wednesday":true,"thursday":true,"friday":true}', true);
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тохиргоо - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">Тохиргоо</h4>
                    </div>
                    <div class="card-body">
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

                        <form method="POST" action="">
                            <!-- Profile Information -->
                            <h5 class="mb-3">Профайл мэдээлэл</h5>
                            <div class="mb-3">
                                <label for="name" class="form-label">Нэр</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($profile['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">И-мэйл</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($profile['email']); ?>" required>
                            </div>

                            <!-- Office Hours -->
                            <h5 class="mb-3 mt-4">Ажлын цаг</h5>
                            <div class="mb-3">
                                <label for="office_hours" class="form-label">Оюутнуудтай уулзах цаг</label>
                                <textarea class="form-control" id="office_hours" name="office_hours" rows="2"><?php echo htmlspecialchars($profile['office_hours'] ?? ''); ?></textarea>
                                <div class="form-text">Жишээ: Даваа, Мягмар гараг 14:00-16:00</div>
                            </div>

                            <!-- Notification Preferences -->
                            <h5 class="mb-3 mt-4">Мэдэгдлийн тохиргоо</h5>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="notify_email" name="notify_email"
                                           <?php echo ($notifications['email'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_email">И-мэйлээр мэдэгдэл хүлээх</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="notify_sms" name="notify_sms"
                                           <?php echo ($notifications['sms'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notify_sms">SMS-ээр мэдэгдэл хүлээх</label>
                                </div>
                            </div>

                            <!-- Availability -->
                            <h5 class="mb-3 mt-4">Хичээл заах боломжтой цаг</h5>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="available_monday" name="available_monday"
                                           <?php echo ($availability['monday'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="available_monday">Даваа</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="available_tuesday" name="available_tuesday"
                                           <?php echo ($availability['tuesday'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="available_tuesday">Мягмар</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="available_wednesday" name="available_wednesday"
                                           <?php echo ($availability['wednesday'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="available_wednesday">Лхагва</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="available_thursday" name="available_thursday"
                                           <?php echo ($availability['thursday'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="available_thursday">Пүрэв</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="available_friday" name="available_friday"
                                           <?php echo ($availability['friday'] ?? false) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="available_friday">Баасан</label>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="teacher.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Буцах
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Хадгалах
                                </button>
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