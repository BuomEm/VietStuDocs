<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/search.php';

header('Content-Type: application/json; charset=utf-8');

$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($keyword) || strlen($keyword) < 2) {
    echo json_encode(['success' => false, 'suggestions' => []]);
    exit;
}

try {
    $suggestions = getSearchSuggestions($keyword, 8);
    
    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions
    ], JSON_UNESCAPED_UNICODE);
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
