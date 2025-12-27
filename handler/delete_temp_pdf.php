<?php
// Prevent any output before JSON
ob_start();

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

// Clear any output buffer
ob_clean();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if(!isUserLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if($_SERVER["REQUEST_METHOD"] != "POST") {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$pdf_path = isset($input['pdf_path']) ? $input['pdf_path'] : '';

if(empty($pdf_path)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'PDF path required']);
    exit;
}

// Ensure path is within uploads/thumbnails directory for security
$full_path = __DIR__ . '/../' . $pdf_path;
$real_path = realpath($full_path);
$thumbnails_dir = realpath(__DIR__ . '/../uploads/thumbnails/');

if(!$real_path || strpos($real_path, $thumbnails_dir) !== 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid path']);
    exit;
}

// Delete the file
if(file_exists($real_path)) {
    if(unlink($real_path)) {
        echo json_encode(['success' => true, 'message' => 'File deleted']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete file']);
    }
} else {
    echo json_encode(['success' => true, 'message' => 'File already deleted']);
}
exit;
?>
