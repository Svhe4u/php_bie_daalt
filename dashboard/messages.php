<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// Get all messages for the teacher
$stmt = $conn->prepare("
    SELECT m.*, 
           u.name as sender_name,
           c.name as course_name,
           CASE 
               WHEN m.course_id IS NOT NULL THEN c.name
               ELSE 'Хувийн мессэж'
           END as context
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    LEFT JOIN courses c ON m.course_id = c.id
    WHERE m.receiver_id = ?
    ORDER BY m.created_at DESC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Mark messages as read
$stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND is_read = 0");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мессэжүүд - Багшийн хяналтын самбар</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="teacher.php">
                <i class="bi bi-arrow-left me-2"></i>
                Буцах
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Мессэжүүд</h4>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                    <i class="bi bi-plus-circle"></i> Шинэ мессэж
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (empty($messages)): ?>
                    <div class="text-center py-4">
                        <div class="text-muted">
                            <i class="bi bi-envelope fs-4 d-block mb-2"></i>
                            Мессэж байхгүй байна
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="message-item p-3 border-bottom <?php echo !$message['is_read'] ? 'bg-light' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($message['sender_name']); ?></h6>
                                    <p class="mb-1"><?php echo htmlspecialchars($message['content']); ?></p>
                                    <small class="text-muted">
                                        <i class="bi bi-book me-1"></i><?php echo htmlspecialchars($message['context']); ?> - 
                                        <?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?>
                                    </small>
                                </div>
                                <?php if (!$message['is_read']): ?>
                                    <span class="badge bg-primary">Шинэ</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Шинэ мессэж</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="send_message.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="receiver_id" class="form-label">Хүлээн авагч</label>
                            <select class="form-select" id="receiver_id" name="receiver_id" required>
                                <option value="">Сонгох...</option>
                                <?php
                                $stmt = $conn->prepare("
                                    SELECT DISTINCT u.id, u.name, u.role
                                    FROM users u
                                    LEFT JOIN course_enrollments ce ON u.id = ce.student_id
                                    LEFT JOIN courses c ON ce.course_id = c.id
                                    WHERE c.teacher_id = ? OR u.role = 'teacher'
                                    ORDER BY u.name
                                ");
                                $stmt->bind_param("i", $_SESSION['user_id']);
                                $stmt->execute();
                                $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                foreach ($users as $user) {
                                    echo '<option value="' . $user['id'] . '">' . 
                                         htmlspecialchars($user['name']) . ' (' . 
                                         ($user['role'] === 'teacher' ? 'Багш' : 'Оюутан') . ')</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="course_id" class="form-label">Хичээл (optional)</label>
                            <select class="form-select" id="course_id" name="course_id">
                                <option value="">Сонгох...</option>
                                <?php
                                $stmt = $conn->prepare("SELECT id, name FROM courses WHERE teacher_id = ?");
                                $stmt->bind_param("i", $_SESSION['user_id']);
                                $stmt->execute();
                                $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                foreach ($courses as $course) {
                                    echo '<option value="' . $course['id'] . '">' . 
                                         htmlspecialchars($course['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Мессэж</label>
                            <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Болих</button>
                        <button type="submit" class="btn btn-primary">Илгээх</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 