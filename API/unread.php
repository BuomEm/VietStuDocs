<?php
session_start();
require_once __DIR__ . '/../config/function.php';
global $VSD;

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get unread count
$res = $VSD->query("SELECT COUNT(*) as c FROM notifications WHERE user_id = $user_id AND is_read = 0");
$row = mysqli_fetch_assoc($res);
$count = $row['c'] ?? 0;

// Get latest 5 notifications
$notifs_res = $VSD->query("SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 5");
$notifications = [];
while ($n = mysqli_fetch_assoc($notifs_res)) {
    $notifications[] = [
        'id' => $n['id'],
        'type' => $n['type'],
        'message' => $n['message'],
        'is_read' => $n['is_read'],
        'time' => date('H:i d/m', strtotime($n['created_at']))
    ];
}

echo json_encode(['count' => $count, 'notifications' => $notifications]);
?>
