<?php
require_once __DIR__ . '/includes/error_handler.php';
session_start();

// Nếu đã đăng nhập, chuyển đến Dashboard
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard");
    exit();
}

// Nếu chưa đăng nhập, chuyển đến trang Login mới
header("Location: login");
exit();
?>
