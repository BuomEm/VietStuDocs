<?php
// Prevent any output before JSON
ob_start();

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/file.php';

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

$user_id = getCurrentUserId();
$doc_id = isset($_POST['doc_id']) ? intval($_POST['doc_id']) : 0;
$total_pages = isset($_POST['total_pages']) ? intval($_POST['total_pages']) : 0;

if($doc_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid document ID']);
    exit;
}

// Verify document belongs to user or user is admin
$doc_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, user_id FROM documents WHERE id=$doc_id"));
if(!$doc_check) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Document not found']);
    exit;
}

$is_admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM users WHERE id=$user_id"))['role'] === 'admin';
if($doc_check['user_id'] != $user_id && !$is_admin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Handle thumbnail image
$thumbnail_path = null;
if(isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
    $thumbnail_file = $_FILES['thumbnail'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $thumbnail_file['tmp_name']);
    finfo_close($finfo);
    
    if(!in_array($mime_type, $allowed_types)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid thumbnail file type']);
        exit;
    }
    
    // Generate unique filename
    $thumbnail_name = 'thumb_' . $doc_id . '_' . time() . '.jpg';
    $thumbnail_path_full = THUMBNAIL_DIR . $thumbnail_name;
    
    // Ensure thumbnail directory exists
    if(!is_dir(THUMBNAIL_DIR)) {
        mkdir(THUMBNAIL_DIR, 0755, true);
    }
    
    // Move uploaded file
    if(move_uploaded_file($thumbnail_file['tmp_name'], $thumbnail_path_full)) {
        $thumbnail_path = 'thumbnails/' . $thumbnail_name;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save thumbnail']);
        exit;
    }
}

// Update document with thumbnail and total_pages
$update_fields = [];
if($thumbnail_path) {
    $thumbnail_path_escaped = mysqli_real_escape_string($conn, $thumbnail_path);
    $update_fields[] = "thumbnail = '$thumbnail_path_escaped'";
}
if($total_pages > 0) {
    $update_fields[] = "total_pages = $total_pages";
}

if(!empty($update_fields)) {
    $update_query = "UPDATE documents SET " . implode(', ', $update_fields) . " WHERE id = $doc_id";
    if(!mysqli_query($conn, $update_query)) {
        error_log("Error updating document $doc_id: " . mysqli_error($conn));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
        exit;
    }
}

mysqli_close($conn);

echo json_encode([
    'success' => true,
    'message' => 'Thumbnail saved successfully',
    'thumbnail' => $thumbnail_path,
    'total_pages' => $total_pages
]);
exit;
?>
