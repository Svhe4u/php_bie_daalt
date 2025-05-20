<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get all messages for the user
$stmt = $conn->prepare("
    SELECT m.*, 
           u1.name as sender_name, u1.profile_picture as sender_photo,
           u2.name as receiver_name, u2.profile_picture as receiver_photo
    FROM messages m
    JOIN users u1 ON m.sender_id = u1.id
    JOIN users u2 ON m.receiver_id = u2.id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    ORDER BY m.created_at DESC
");
$stmt->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all users for the recipient dropdown
$stmt = $conn->prepare("
    SELECT id, name, profile_picture, role 
    FROM users 
    WHERE id != ? 
    ORDER BY name ASC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $subject = trim($_POST['subject']);
    $content = trim($_POST['content']);
    
    if (!empty($receiver_id) && !empty($subject) && !empty($content)) {
        $stmt = $conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, subject, content) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiss", $_SESSION['user_id'], $receiver_id, $subject, $content);
        
        if ($stmt->execute()) {
            header("Location: messages.php?success=1");
            exit();
        } else {
            $error = "Мессеж илгээхэд алдаа гарлаа.";
        }
    } else {
        $error = "Бүх талбарыг бөглөнө үү.";
    }
}
?>

<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мессежүүд - Сургалтын Үнэлгээний Систем</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .message-card {
            transition: transform 0.2s;
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .message-card:hover {
            transform: translateY(-2px);
        }
        .message-item {
            transition: all 0.2s;
            border-radius: 8px;
        }
        .message-item:hover {
            background-color: #f8f9fa;
        }
        .message-content {
            max-height: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
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
                            <a class="nav-link" href="student.php">
                                <i class="bi bi-house-door"></i> Хяналтын самбар
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="student_courses.php">
                                <i class="bi bi-book"></i> Хичээлүүд
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="messages.php">
                                <i class="bi bi-envelope"></i> Мессежүүд
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tabs/settings.php">
                                <i class="bi bi-gear"></i> Тохиргоо
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Гарах
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Мессежүүд</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                        <i class="bi bi-plus-lg"></i> Шинэ мессеж
                    </button>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Мессеж амжилттай илгээгдлээ.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Messages List -->
                <div class="row g-4">
                    <?php if (empty($messages)): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                Одоогоор мессеж байхгүй байна.
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="col-md-6">
                                <div class="card message-card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center mb-3">
                                            <?php if ($message['sender_id'] === $_SESSION['user_id']): ?>
                                                <?php if ($message['receiver_photo']): ?>
                                                    <img src="<?php echo htmlspecialchars($message['receiver_photo']); ?>" 
                                                         alt="Receiver" class="rounded-circle me-2" width="40">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 40px; height: 40px; font-size: 1rem;">
                                                        <?php echo strtoupper(substr($message['receiver_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($message['receiver_name']); ?></h6>
                                                    <small class="text-muted">Хүлээн авагч</small>
                                                </div>
                                            <?php else: ?>
                                                <?php if ($message['sender_photo']): ?>
                                                    <img src="<?php echo htmlspecialchars($message['sender_photo']); ?>" 
                                                         alt="Sender" class="rounded-circle me-2" width="40">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 40px; height: 40px; font-size: 1rem;">
                                                        <?php echo strtoupper(substr($message['sender_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($message['sender_name']); ?></h6>
                                                    <small class="text-muted">Илгээгч</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <h5 class="card-title"><?php echo htmlspecialchars($message['subject']); ?></h5>
                                        <p class="card-text message-content"><?php echo htmlspecialchars($message['content']); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?>
                                            </small>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewMessageModal<?php echo $message['id']; ?>">
                                                Дэлгэрэнгүй
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- View Message Modal -->
                            <div class="modal fade" id="viewMessageModal<?php echo $message['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><?php echo htmlspecialchars($message['subject']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <strong>Илгээгч:</strong> <?php echo htmlspecialchars($message['sender_name']); ?>
                                            </div>
                                            <div class="mb-3">
                                                <strong>Хүлээн авагч:</strong> <?php echo htmlspecialchars($message['receiver_name']); ?>
                                            </div>
                                            <div class="mb-3">
                                                <strong>Огноо:</strong> <?php echo date('Y-m-d H:i', strtotime($message['created_at'])); ?>
                                            </div>
                                            <hr>
                                            <div class="message-content">
                                                <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Хаах</button>
                                            <?php if ($message['sender_id'] === $_SESSION['user_id']): ?>
                                                <button type="button" class="btn btn-danger" 
                                                        onclick="deleteMessage(<?php echo $message['id']; ?>)">
                                                    Устгах
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- New Message Modal -->
    <div class="modal fade" id="newMessageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Шинэ мессеж</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="receiver_id" class="form-label">Хүлээн авагч</label>
                            <select class="form-select" id="receiver_id" name="receiver_id" required>
                                <option value="">Хүлээн авагчийг сонгоно уу</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>">
                                        <?php echo htmlspecialchars($u['name']); ?> 
                                        (<?php echo $u['role'] === 'teacher' ? 'Багш' : 'Оюутан'; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="subject" class="form-label">Гарчиг</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        <div class="mb-3">
                            <label for="content" class="form-label">Агуулга</label>
                            <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Хаах</button>
                        <button type="submit" name="send_message" class="btn btn-primary">Илгээх</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteMessage(messageId) {
            if (confirm('Энэ мессежийг устгахдаа итгэлтэй байна уу?')) {
                window.location.href = `delete_message.php?id=${messageId}`;
            }
        }
    </script>
</body>
</html> 