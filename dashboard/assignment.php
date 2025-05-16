<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid assignment ID.');
}
$assignment_id = intval($_GET['id']);

$stmt = $conn->prepare('SELECT a.*, c.name as course_name, u.name as teacher_name FROM assignments a JOIN courses c ON a.course_id = c.id JOIN users u ON c.teacher_id = u.id WHERE a.id = ?');
$stmt->bind_param('i', $assignment_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die('Assignment not found.');
}
$assignment = $result->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Даалгаврын дэлгэрэнгүй</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                <span class="badge bg-primary">Хичээл: <?php echo htmlspecialchars($assignment['course_name']); ?></span>
                <span class="badge bg-info">Багш: <?php echo htmlspecialchars($assignment['teacher_name']); ?></span>
            </div>
            <div class="card-body">
                <p><strong>Тайлбар:</strong> <?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                <p><strong>Дуусах огноо:</strong> <?php echo htmlspecialchars($assignment['due_date']); ?></p>
                <p><strong>Онооны дээд хязгаар:</strong> <?php echo htmlspecialchars($assignment['max_score'] ?? '100'); ?></p>
                <p><strong>Сүүлд шинэчлэгдсэн:</strong> <?php echo htmlspecialchars($assignment['created_at']); ?></p>
                <?php if (!empty($assignment['file_path'])): ?>
                    <a href="../<?php echo htmlspecialchars($assignment['file_path']); ?>" class="btn btn-success" download>Хавсралт татах</a>
                <?php endif; ?>
            </div>
        </div>
        <a href="course.php?id=<?php echo $assignment['course_id']; ?>" class="btn btn-secondary mt-3">Буцах</a>
    </div>
</body>
</html> 