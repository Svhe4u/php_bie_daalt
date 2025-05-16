<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid material ID.');
}
$material_id = intval($_GET['id']);

$stmt = $conn->prepare('SELECT file_path, title, file_type FROM materials WHERE id = ?');
$stmt->bind_param('i', $material_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die('Material not found.');
}
$material = $result->fetch_assoc();
$file = $material['file_path'];
$title = $material['title'];
$type = $material['file_type'] ?? 'application/octet-stream';

if (!file_exists($file)) {
    die('File not found.');
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $type);
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file));
readfile($file);
exit(); 