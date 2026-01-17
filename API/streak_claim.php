<?php
header('Content-Type: application/json');
session_start();

require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../config/streak.php';

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập!']);
    exit;
}

/*
$user_id = getCurrentUserId();
$result = claimDailyStreak($user_id);

echo json_encode($result);
*/
echo json_encode(['success' => false, 'message' => 'Tính năng hiện đang tạm khóa.']);
