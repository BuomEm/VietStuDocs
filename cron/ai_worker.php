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
require_once __DIR__ . '/../config/file.php';
require_once __DIR__ . '/../includes/ai_review_handler.php';

// Log khởi động
$log_file = __DIR__ . '/ai_worker.log';
$timestamp = date('Y-m-d H:i:s');

/**
 * Write a line to the AI worker log with a consistent prefix.
 */
function aiWorkerLog($log_file, $message) {
    $ts = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$ts] $message\n", FILE_APPEND);
}

/**
 * Ensure basic preprocessing is done before AI review:
 * 1. DOC/DOCX -> converted PDF exists
 * 2. Image files -> thumbnail exists
 *
 * Returns true if preprocessing is satisfied (or not required), false if it failed.
 */
function ensureDocumentPreprocessed($conn, $doc, $log_file) {
    $doc_id = intval($doc['id']);
    $file_name = $doc['file_name'] ?? '';
    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $uploads_dir = __DIR__ . '/../uploads/';
    $full_path = $uploads_dir . $file_name;

    if (empty($file_name) || !file_exists($full_path)) {
        aiWorkerLog($log_file, "Preprocess skip: file missing for doc_id=$doc_id");
        return false;
    }

    // 1) DOC/DOCX conversion if needed
    if (in_array($ext, ['doc', 'docx'], true)) {
        $converted_pdf_path = $doc['converted_pdf_path'] ?? '';
        $converted_pdf_full = $converted_pdf_path ? (__DIR__ . '/../' . $converted_pdf_path) : '';
        $has_converted_pdf = $converted_pdf_path && file_exists($converted_pdf_full);

        if (!$has_converted_pdf) {
            aiWorkerLog($log_file, "Preprocess: converting DOCX to PDF for doc_id=$doc_id");
            $pdf_filename = $doc_id . '_converted_' . time() . '.pdf';
            $conversion_error = '';
            $conversion_result = convertDocxToPdf($full_path, UPLOAD_DIR, $pdf_filename, $conversion_error);

            if (!$conversion_result || empty($conversion_result['pdf_path']) || !file_exists($conversion_result['pdf_path'])) {
                $err = $conversion_error ?: 'Unknown conversion error';
                aiWorkerLog($log_file, "Preprocess failed: DOCX->PDF doc_id=$doc_id error=$err");
                return false;
            }

            $new_pdf_rel = mysqli_real_escape_string($conn, $conversion_result['pdf_url']);
            mysqli_query($conn, "UPDATE documents SET converted_pdf_path = '$new_pdf_rel' WHERE id = $doc_id");
            aiWorkerLog($log_file, "Preprocess success: converted_pdf_path set for doc_id=$doc_id");
        }
    }

    // 2) Thumbnail generation for images if missing
    $thumbnail = trim($doc['thumbnail'] ?? '');
    if ($thumbnail === '' && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        aiWorkerLog($log_file, "Preprocess: generating thumbnail for image doc_id=$doc_id");
        $thumb_rel = generateThumbnail($full_path, $ext, $doc_id);

        if ($thumb_rel) {
            $thumb_esc = mysqli_real_escape_string($conn, $thumb_rel);
            mysqli_query($conn, "UPDATE documents SET thumbnail = '$thumb_esc' WHERE id = $doc_id");
            aiWorkerLog($log_file, "Preprocess success: thumbnail set for doc_id=$doc_id");
        } else {
            aiWorkerLog($log_file, "Preprocess warning: thumbnail not generated for doc_id=$doc_id");
            // For images this is useful but not blocking AI review.
        }
    }

    return true;
}

// 1. Tìm tài liệu đang chờ (ai_status là NULL hoặc pending)
// Chỉ xử lý nếu đã biết số trang (total_pages > 0) và nằm trong ngưỡng (<= 180)
$query = "SELECT id, original_name, file_name, thumbnail, converted_pdf_path, total_pages FROM documents 
          WHERE (ai_status IS NULL OR ai_status = 'pending') 
          AND total_pages > 0 
          AND total_pages <= 180 
          ORDER BY created_at ASC 
          LIMIT 1"; // Xử lý mỗi lần 1 file để an toàn nhất trên Shared Hosting

$result = mysqli_query($conn, $query);

if ($row = mysqli_fetch_assoc($result)) {
    $doc_id = $row['id'];
    $name = $row['original_name'];
    
    aiWorkerLog($log_file, "Bắt đầu xử lý: $name (ID: $doc_id)");
    
    try {
        // Preprocessing before AI review
        $pre_ok = ensureDocumentPreprocessed($conn, $row, $log_file);
        if (!$pre_ok) {
            aiWorkerLog($log_file, "Bỏ qua AI review do preprocess lỗi: doc_id=$doc_id");
            mysqli_close($conn);
            exit;
        }

        $ai_handler = new AIReviewHandler($conn);
        $ai_res = $ai_handler->reviewDocument($doc_id);
        
        if ($ai_res['success']) {
            aiWorkerLog($log_file, "Thành công: $doc_id - Decision: {$ai_res['decision']}");
        } else {
            $err = $ai_res['error'] ?? 'Unknown AI error';
            aiWorkerLog($log_file, "Lỗi AI: $doc_id - $err");
        }
    } catch (Exception $e) {
        aiWorkerLog($log_file, "Exception: $doc_id - " . $e->getMessage());
    }
} else {
    // Không có tài liệu cần xử lý
    // file_put_contents($log_file, "[$timestamp] Không có hàng đợi.\n", FILE_APPEND);
}

mysqli_close($conn);
?>
