<?php
// Prevent any output before JSON
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/file.php';

// Clear any output buffer
ob_clean();

// Set JSON header
header('Content-Type: application/json');

/**
 * Generate a CSRF token for PDF operations
 */
function pdf_ajax_token() {
    if (empty($_SESSION['pdf_token'])) {
        $_SESSION['pdf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['pdf_token'];
}

/**
 * Verify CSRF token
 */
function verify_pdf_token($token) {
    return isset($_SESSION['pdf_token']) && hash_equals($_SESSION['pdf_token'], $token);
}

/**
 * AJAX Router
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_pdf_pages':
            save_pdf_pages();
            break;
        case 'save_thumbnail':
            save_thumbnail();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    exit;
}

// If not POST, return token for GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_token'])) {
    // Check if user is logged in
    if(!isUserLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'token' => pdf_ajax_token()
    ]);
    exit;
}

/**
 * Save PDF page count to database
 */
function save_pdf_pages() {
    global $conn;
    
    // Check if user is logged in
    if(!isUserLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    // Verify CSRF token
    $token = $_POST['token'] ?? '';
    if (!verify_pdf_token($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        return;
    }
    
    $doc_id = isset($_POST['doc_id']) ? intval($_POST['doc_id']) : 0;
    $pages = isset($_POST['pages']) ? intval($_POST['pages']) : 0;
    
    if ($doc_id <= 0 || $pages <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        return;
    }
    
    $user_id = getCurrentUserId();
    
    // Verify document exists and user has permission
    $doc_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, user_id FROM documents WHERE id=$doc_id"));
    if(!$doc_check) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        return;
    }
    
    $is_admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM users WHERE id=$user_id"))['role'] === 'admin';
    if($doc_check['user_id'] != $user_id && !$is_admin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
    }
    
    // Update document with page count
    $update_query = "UPDATE documents SET total_pages = $pages WHERE id = $doc_id";
    if(!mysqli_query($conn, $update_query)) {
        error_log("Error updating page count for document $doc_id: " . mysqli_error($conn));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
        return;
    }
    
    error_log("Document $doc_id: Page count updated to $pages via client-side PDF.js");

    error_log("Document $doc_id: Page count updated to $pages. AI will process via Cron Job.");

    // Đảm bảo trạng thái là pending để worker tìm thấy
    mysqli_query($conn, "UPDATE documents SET ai_status = 'pending' WHERE id=$doc_id AND ai_status IS NULL");
    
    echo json_encode([
        'success' => true,
        'pages' => $pages,
        'doc_id' => $doc_id
    ]);
}

/**
 * Save thumbnail image to server
 */
function save_thumbnail() {
    global $conn;
    
    // Check if user is logged in
    if(!isUserLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    // Verify CSRF token
    $token = $_POST['token'] ?? '';
    if (!verify_pdf_token($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        return;
    }
    
    $doc_id = isset($_POST['doc_id']) ? intval($_POST['doc_id']) : 0;
    
    if($doc_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid document ID']);
        return;
    }
    
    $user_id = getCurrentUserId();
    
    // Verify document exists and user has permission
    $doc_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, user_id FROM documents WHERE id=$doc_id"));
    if(!$doc_check) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        return;
    }
    
    $is_admin = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM users WHERE id=$user_id"))['role'] === 'admin';
    if($doc_check['user_id'] != $user_id && !$is_admin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        return;
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
            return;
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
            return;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No thumbnail file provided']);
        return;
    }
    
    // Update document with thumbnail
    if($thumbnail_path) {
        $thumbnail_path_escaped = mysqli_real_escape_string($conn, $thumbnail_path);
        $update_query = "UPDATE documents SET thumbnail = '$thumbnail_path_escaped' WHERE id = $doc_id";
        if(!mysqli_query($conn, $update_query)) {
            error_log("Error updating thumbnail for document $doc_id: " . mysqli_error($conn));
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database update failed']);
            return;
        }
    }
    
    error_log("Document $doc_id: Thumbnail saved via client-side PDF.js at $thumbnail_path");
    
    echo json_encode([
        'success' => true,
        'message' => 'Thumbnail saved successfully',
        'thumbnail' => $thumbnail_path,
        'doc_id' => $doc_id
    ]);
}
?>

