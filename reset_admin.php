<?php
session_start();
require_once 'db.php';

// Reset admin password to "Admin123!"
$admin_email = "admin@system.com";
$new_password = password_hash("Admin123!", PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->bind_param("ss", $new_password, $admin_email);

if ($stmt->execute()) {
    echo "Admin password has been reset successfully. You can now login with:<br>";
    echo "Email: admin@system.com<br>";
    echo "Password: Admin123!";
} else {
    echo "Error resetting password: " . $stmt->error;
}
?> 