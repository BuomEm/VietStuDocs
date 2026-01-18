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

// Phân quyền tải
if(!$is_admin && $doc['user_id'] != $user_id) {
    if($doc['status'] !== 'approved' || !$doc['is_public']) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Tài liệu chưa được phê duyệt hoặc không công khai']);
        exit;
    }
}

$can_download = false;
if($is_admin || $doc['user_id'] == $user_id) {
    $can_download = true;
} else {
    $can_download = canUserDownloadDocument($user_id, $doc_id);
}

if(!$can_download) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Bạn cần mua tài liệu này để tải xuống']);
    exit;
}

// Kiểm tra file thực tế
$file_path = UPLOAD_DIR . $doc['file_name'];
if(!file_exists($file_path)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'File tài liệu không tồn tại trên server']);
    exit;
}

// Tăng counter
if($doc['user_id'] != $user_id) {
    incrementDocumentDownloads($doc_id);
}

// Tốc độ tải (KB/s)
$speed_limit_kbps = (int)getSetting('limit_download_speed_free', 100);
if($is_premium || $doc['user_id'] == $user_id || $is_admin) {
    $speed_limit_kbps = (int)getSetting('limit_download_speed_premium', 1000);
}

// --- Bắt đầu quá trình tải ---
$file_size = filesize($file_path);
$actual_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$download_filename = ($doc['original_name'] ?: basename($file_path));

// Fix extension nếu thiếu
if($actual_ext && strtolower(pathinfo($download_filename, PATHINFO_EXTENSION)) !== $actual_ext) {
    $download_filename .= '.' . $actual_ext;
}

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
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'
];
$content_type = $mime_types[$actual_ext] ?? 'application/octet-stream';

// Headers chặn buffering (Fix lỗi đứng 0%)
header('Content-Type: ' . $content_type);
header('Content-Disposition: attachment; filename="' . htmlspecialchars($download_filename, ENT_QUOTES, 'UTF-8') . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Accel-Buffering: no'); 
header('Content-Encoding: identity');
header('X-Content-Type-Options: nosniff');

// Tắt output buffering
while (ob_get_level()) ob_end_clean();

$file = fopen($file_path, 'rb');
if ($file) {
    $chunk_size = 8 * 1024; // 8KB mỗi chunk giúp progress bar chạy mượt
    $bytes_sent = 0;
    
    // Tính delay cho mỗi 8KB dựa trên giới hạn KB/s
    // Formula: (chunk_size_in_kb / speed_limit_kbps) * 1,000,000 microseconds
    $delay_per_chunk = (($chunk_size / 1024) / $speed_limit_kbps) * 1000000;

    while (!feof($file) && $bytes_sent < $file_size) {
        if (connection_aborted()) break;
        
        $chunk = fread($file, $chunk_size);
        echo $chunk;
        flush();
        
        $bytes_sent += strlen($chunk);
        
        if ($bytes_sent < $file_size) {
            usleep($delay_per_chunk);
        }
    }
    fclose($file);
}
exit;
