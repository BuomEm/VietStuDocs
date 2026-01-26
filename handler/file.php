<?php
ob_start();
error_reporting(0);
set_time_limit(0);
ini_set('memory_limit', '512M');
/**
 * Secure File View Handler
 * Xử lý xem file (preview) với kiểm tra quyền
 * Cho phép preview document cho user có quyền xem (không cần mua để preview)
 * 
 * Usage: handler/file.php?doc_id=DOCUMENT_ID hoặc handler/file.php?file=FILE_NAME
 */

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/file.php';

// Lấy document ID (bắt buộc)
$doc_id = intval($_GET['doc_id'] ?? 0);

if($doc_id <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Document ID is required';
    exit;
}

// Lấy thông tin document
$query = "SELECT d.*, u.username FROM documents d 
          JOIN users u ON d.user_id = u.id 
          WHERE d.id = $doc_id";
$doc = db_get_row($query);

if(!$doc) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Document not found';
    exit;
}

$file_name = $doc['file_name'];
$user_id = isset($_SESSION['user_id']) ? getCurrentUserId() : null;
$is_admin = $user_id ? isAdmin($user_id) : false;

// Kiểm tra quyền xem document
// Admin có thể xem bất kỳ document nào (bất kể trạng thái)
// Owner có thể xem document của mình (bất kể trạng thái)
// User khác chỉ có thể xem document đã được approve và public
if(!$is_admin && $doc['user_id'] != $user_id) {
    if($doc['status'] !== 'approved' || !$doc['is_public']) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Access denied. Document is not approved or not public.';
        exit;
    }
}

// Xác định file nào sẽ được serve
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
$file_path = null;

// Chỉ sử dụng converted_pdf_path cho DOCX/DOC files
if(in_array($file_ext, ['docx', 'doc'])) {
    $converted_path = $doc['converted_pdf_path'] ?? '';
    if(!empty($converted_path)) {
        // Kiểm tra converted_pdf_path có phải là đường dẫn tương đối không
        if(strpos($converted_path, 'uploads/') === 0) {
            // Đường dẫn tương đối từ root: uploads/xxx.pdf
            $file_path = __DIR__ . '/../' . $converted_path;
        } elseif(strpos($converted_path, '\\uploads\\') !== false || strpos($converted_path, '/uploads/') !== false) {
            // Đường dẫn tương đối với backslash hoặc forward slash
            $file_path = __DIR__ . '/../' . str_replace('\\', '/', $converted_path);
        } else {
            // Đường dẫn tuyệt đối hoặc tương đối từ handler
            $file_path = $converted_path;
        }
        
        // Kiểm tra file có tồn tại không
        if(!file_exists($file_path)) {
            // Fallback về file gốc nếu converted PDF không tồn tại
            $file_path = UPLOAD_DIR . $file_name;
        }
    } else {
        // Không có converted PDF, sử dụng file gốc (DOCX)
        $file_path = UPLOAD_DIR . $file_name;
    }
} else {
    // Với PDF và các file khác, luôn sử dụng file gốc
    $file_path = UPLOAD_DIR . $file_name;
}

// Kiểm tra file tồn tại
if(!file_exists($file_path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'File not found';
    exit;
}

// Xác định MIME type dựa trên file thực tế đang được serve (có thể là converted PDF)
$actual_file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
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
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'ico' => 'image/x-icon'
];
$content_type = $mime_types[$actual_file_ext] ?? 'application/octet-stream';

// Xóa SẠCH tất cả buffer để đảm bảo không có khoảng trắng thừa làm hỏng file binary
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Lấy file size
$file_size = filesize($file_path);

// Hỗ trợ HTTP Range requests cho PDF.js
$range = isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : null;
$start = 0;
$end = $file_size - 1;

if ($range && preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
    $start = !empty($matches[1]) ? intval($matches[1]) : 0;
    $end = !empty($matches[2]) ? intval($matches[2]) : $file_size - 1;
    
    // Validate range
    if ($start > $end || $start < 0 || $end >= $file_size) {
        http_response_code(416); // Range Not Satisfiable
        header('Content-Range: bytes */' . $file_size);
        exit;
    }
    
    // Partial content
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $file_size);
    header('Content-Length: ' . ($end - $start + 1));
} else {
    // Full file
    header('Content-Length: ' . $file_size);
    header('Accept-Ranges: bytes');
}

// Gửi headers (không có Content-Disposition: attachment để cho phép preview)
header('Content-Type: ' . $content_type);
header('Cache-Control: public, max-age=3600');
header('Pragma: public');
header('X-Content-Type-Options: nosniff');

// Cho phép CORS nếu cần (cho PDF.js)
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, OPTIONS, HEAD');
header('Access-Control-Allow-Headers: Range, Content-Type');
header('Access-Control-Expose-Headers: Content-Range, Content-Length, Accept-Ranges');

// Handle OPTIONS request (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle HEAD request
if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
    exit;
}

// Mở file và gửi phần được yêu cầu
$file = fopen($file_path, 'rb');
if (!$file) {
    http_response_code(500);
    echo 'Error opening file';
    exit;
}

// Seek to start position
if ($start > 0) {
    fseek($file, $start);
}

// Send file in chunks
$chunk_size = 1024 * 1024; // 1MB chunks
$remaining = $end - $start + 1;

while ($remaining > 0 && !feof($file)) {
    if (connection_aborted()) {
        break;
    }
    
    $read_size = min($chunk_size, $remaining);
    $chunk = fread($file, $read_size);
    
    if ($chunk === false) {
        break;
    }
    
    echo $chunk;
    flush();
    $remaining -= strlen($chunk);
}

fclose($file);
exit;
