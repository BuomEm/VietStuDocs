<?php
session_start();
require_once __DIR__ . '/../config/function.php';
global $VSD;
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

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
    $url = '#';
    switch ($n['type']) {
        case 'document_approved':
        case 'document_rejected':
            $url = '/view?id=' . $n['ref_id'];
            break;
        case 'tutor_request_accepted':
        case 'tutor_request_rejected':
        case 'new_tutor_request':
            $url = '/tutors/request?id=' . $n['ref_id'];
            break;
        case 'payment_success':
        case 'points_added':
        case 'points_deducted':
            $url = '/history';
            break;
        case 'welcome':
            $url = '/dashboard';
            break;
    }

    $notifications[] = [
        'id' => $n['id'],
        'title' => $n['title'] ?? 'DocShare',
        'type' => $n['type'],
        'message' => $n['message'],
        'is_read' => $n['is_read'],
        'time' => date('H:i d/m', strtotime($n['created_at'])),
        'url' => $url
    ];
}

echo json_encode(['count' => $count, 'notifications' => $notifications]);
?>
