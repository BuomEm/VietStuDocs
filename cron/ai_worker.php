<?php
/**
 * CRON JOB AI WORKER
 * Path: /cron/ai_worker.php
 * Thiết lập trong CPanel: /usr/local/bin/php /home/username/public_html/cron/ai_worker.php > /dev/null 2>&1
 */

// Đảm bảo không bị timeout bởi PHP
@set_time_limit(600); 
ignore_user_abort(true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/ai_review_handler.php';

// Log khởi động
$log_file = __DIR__ . '/ai_worker.log';
$timestamp = date('Y-m-d H:i:s');

// 1. Tìm tài liệu đang chờ (ai_status là NULL hoặc pending)
// Chỉ xử lý nếu đã biết số trang (total_pages > 0) và nằm trong ngưỡng (<= 180)
$query = "SELECT id, original_name FROM documents 
          WHERE (ai_status IS NULL OR ai_status = 'pending') 
          AND total_pages > 0 
          AND total_pages <= 180 
          ORDER BY created_at ASC 
          LIMIT 1"; // Xử lý mỗi lần 1 file để an toàn nhất trên Shared Hosting

$result = mysqli_query($conn, $query);

if ($row = mysqli_fetch_assoc($result)) {
    $doc_id = $row['id'];
    $name = $row['original_name'];
    
    file_put_contents($log_file, "[$timestamp] Bắt đầu xử lý: $name (ID: $doc_id)\n", FILE_APPEND);
    
    try {
        $ai_handler = new AIReviewHandler($conn);
        $ai_res = $ai_handler->reviewDocument($doc_id);
        
        if ($ai_res['success']) {
            file_put_contents($log_file, "[$timestamp] Thành công: $doc_id - Decision: {$ai_res['decision']}\n", FILE_APPEND);
        } else {
            file_put_contents($log_file, "[$timestamp] Lỗi AI: $doc_id - {$ai_res['error']}\n", FILE_APPEND);
        }
    } catch (Exception $e) {
        file_put_contents($log_file, "[$timestamp] Exception: $doc_id - " . $e->getMessage() . "\n", FILE_APPEND);
    }
} else {
    // Không có tài liệu cần xử lý
    // file_put_contents($log_file, "[$timestamp] Không có hàng đợi.\n", FILE_APPEND);
}

mysqli_close($conn);
?>
