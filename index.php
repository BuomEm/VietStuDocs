<?php
require_once __DIR__ . '/includes/error_handler.php';
session_start();

// Luôn chuyển đến Dashboard (cả đăng nhập và chưa đăng nhập)
header("Location: dashboard.php");
exit();
?>
