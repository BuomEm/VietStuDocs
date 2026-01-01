<?php
session_start();
require_once __DIR__ . '/../config/function.php';
global $VSD;

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);

if (isset($data['endpoint'])) {
    $endpoint = $VSD->escape($data['endpoint']);
    $VSD->query("DELETE FROM push_subscriptions WHERE user_id = $user_id AND subscription LIKE '%$endpoint%'");
} else {
    // If no specific endpoint, delete all for this user (nuclear option)
    $VSD->query("DELETE FROM push_subscriptions WHERE user_id = $user_id");
}

echo json_encode(['success' => true]);
?>
