<?php
session_start();
require_once __DIR__ . '/../config/function.php';
global $VSD;
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$user_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $VSD->update('notifications', ['is_read' => 1], "id = $id AND user_id = $user_id");
} else {
    // Mark ALL as read
    $VSD->update('notifications', ['is_read' => 1], "user_id = $user_id");
}

echo json_encode(['success' => true]);
?>
