<?php
session_start();
require_once __DIR__ . '/../config/function.php';
global $VSD;
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if (!isset($_SESSION['user_id'])) {
    exit;
}

$user_id = $_SESSION['user_id'];
$VSD->update('users', ['last_seen' => date('Y-m-d H:i:s')], "id = $user_id");
?>
