<?php
session_start();
require_once __DIR__ . '/../config/function.php';
global $VSD;

if (!isset($_SESSION['user_id'])) {
    exit;
}

$user_id = $_SESSION['user_id'];
$VSD->update('users', ['last_seen' => date('Y-m-d H:i:s')], "id = $user_id");
?>
