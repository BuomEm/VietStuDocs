<?php
/**
 * Secure Document Download Handler
 * Xử lý download tài liệu với kiểm tra quyền đầy đủ
 * 
 * Usage: handler/download.php?id=DOCUMENT_ID
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/premium.php';
require_once __DIR__ . '/../config/file.php';
require_once __DIR__ . '/../config/points.php';
require_once __DIR__ . '/../config/document_stats.php';
require_once __DIR__ . '/../config/settings.php';

// Kiểm tra user đã đăng nhập
if(!isUserLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để tải tài liệu']);
    exit;
}

// Lấy document ID
$doc_id = intval($_GET['id'] ?? 0);

if($doc_id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'ID tài liệu không hợp lệ']);
    exit;
}

$user_id = getCurrentUserId();
$is_premium = isPremium($user_id);
$is_admin = isAdmin($user_id);

// Lấy thông tin document
$query = "SELECT d.*, u.username FROM documents d 
          JOIN users u ON d.user_id = u.id 
          WHERE d.id = $doc_id";
$doc = db_get_row($query);

// Kiểm tra document tồn tại
if(!$doc) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Tài liệu không tồn tại']);
    exit;
}

// Admin có thể tải bất kỳ document nào (bất kể trạng thái)
// Owner có thể tải document của mình (bất kể trạng thái)
// User khác chỉ có thể tải document đã được approve và public
if(!$is_admin && $doc['user_id'] != $user_id) {
    if($doc['status'] !== 'approved' || !$doc['is_public']) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Tài liệu chưa được phê duyệt hoặc không công khai']);
        exit;
    }
}

// Kiểm tra quyền download
$can_download = false;

// Admin có thể tải bất kỳ document nào
if($is_admin) {
    $can_download = true;
}
// Owner có thể tải document của mình
elseif($doc['user_id'] == $user_id) {
    $can_download = true;
}
// User khác phải mua document trước
else {
    $can_download = canUserDownloadDocument($user_id, $doc_id);
}

if(!$can_download) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Bạn cần mua tài liệu này để tải xuống']);
    exit;
}

// Kiểm tra file tồn tại
$file_path = UPLOAD_DIR . $doc['file_name'];
if(!file_exists($file_path)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'File tài liệu không tồn tại trên server']);
    exit;
}

// Tăng số lượt download (chỉ tăng cho user khác, không tăng khi owner tự tải)
if($doc['user_id'] != $user_id) {
    incrementDocumentDownloads($doc_id);
}

// Xác định tốc độ download
$download_speed_kbps = (int)getSetting('limit_download_speed_free', 100);

// Premium users hoặc document owners có tốc độ download nhanh hơn
if($is_premium || $doc['user_id'] == $user_id || $is_admin) {
    $download_speed_kbps = (int)getSetting('limit_download_speed_premium', 500);
}

// Function để download file với speed limit
function downloadFileWithSpeedLimit($file_path, $speed_limit_kbps = 100, $original_name = null) {
    // Mở file
    $file = fopen($file_path, 'rb');
    if (!$file) {
        return false;
    }
    
    // Lấy kích thước file
    $file_size = filesize($file_path);
    
    // Tính toán chunk size (100KB mỗi chunk)
    $chunk_size = 100 * 1024; // 100KB
    
    // Tính toán delay giữa các chunk để đạt tốc độ mong muốn
    $delay_microseconds = (($chunk_size / 1024) / $speed_limit_kbps) * 1000000;
    
    // Sử dụng original_name nếu có, nếu không thì dùng basename
    $download_filename = $original_name ? $original_name : basename($file_path);
    
    // Xác định MIME type
    $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $mime_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'zip' => 'application/zip',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png'
    ];
    $content_type = $mime_types[$file_ext] ?? 'application/octet-stream';
    
    // Gửi headers
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . htmlspecialchars($download_filename, ENT_QUOTES, 'UTF-8') . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('X-Content-Type-Options: nosniff');
    
    // Tắt output buffering
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Gửi file theo chunks với giới hạn tốc độ
    $bytes_sent = 0;
    while (!feof($file) && $bytes_sent < $file_size) {
        // Kiểm tra client có ngắt kết nối không
        if (connection_aborted()) {
            break;
        }
        
        // Đọc chunk
        $chunk = fread($file, $chunk_size);
        if ($chunk === false) {
            break;
        }
        
        // Gửi chunk
        echo $chunk;
        flush();
        
        // Cập nhật số byte đã gửi
        $bytes_sent += strlen($chunk);
        
        // Áp dụng delay để giới hạn tốc độ (trừ chunk cuối)
        if ($bytes_sent < $file_size && $delay_microseconds > 0) {
            usleep($delay_microseconds);
        }
    }
    
    fclose($file);
    return true;
}

// Download file
downloadFileWithSpeedLimit($file_path, $download_speed_kbps, $doc['original_name']);
exit;
