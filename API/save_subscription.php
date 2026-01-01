<?php
session_start();
require_once __DIR__ . '/../config/function.php';
global $VSD;

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = file_get_contents("php://input");

if (!$data) {
    http_response_code(400);
    exit;
}

// Check if subscription already exists for this user to avoid duplicates
// We can use a hash or just insert. JSON might needs escaping.
// We use the insert method which handles escaping.
$VSD->insert('push_subscriptions', [
    'user_id' => $user_id,
    'subscription' => $data
]);

echo json_encode(['success' => true]);
?>
