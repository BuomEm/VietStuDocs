<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để báo cáo']);
    exit;
}

$user_id = getCurrentUserId();

// Handle report submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $document_id = intval($data['document_id'] ?? 0);
    $reason = mysqli_real_escape_string($conn, $data['reason'] ?? '');
    $description = mysqli_real_escape_string($conn, $data['description'] ?? '');
    
    // Validate input
    if ($document_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Tài liệu không hợp lệ']);
        exit;
    }
    
    if (empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Vui lòng chọn lý do báo cáo']);
        exit;
    }
    
    // Check if document exists
    $doc_check = mysqli_query($conn, "SELECT id FROM documents WHERE id=$document_id");
    if (mysqli_num_rows($doc_check) === 0) {
        echo json_encode(['success' => false, 'message' => 'Tài liệu không tồn tại']);
        exit;
    }
    
    // Check if user has already reported this document
    $existing_report = mysqli_query($conn, 
        "SELECT id FROM reports 
         WHERE document_id=$document_id AND reporter_user_id=$user_id AND status='pending'");
    
    if (mysqli_num_rows($existing_report) > 0) {
        echo json_encode(['success' => false, 'message' => 'Bạn đã báo cáo tài liệu này rồi']);
        exit;
    }
    
    // Insert report
    $insert_query = "INSERT INTO reports (document_id, reporter_user_id, reason, description) 
                     VALUES ($document_id, $user_id, '$reason', '$description')";
    
    if (mysqli_query($conn, $insert_query)) {
        // Create notification for admins using unified sender
        require_once __DIR__ . '/../config/notifications.php';
        
        $doc_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT original_name FROM documents WHERE id=$document_id"));
        $doc_name = $doc_info ? htmlspecialchars($doc_info['original_name']) : "Tài liệu #$document_id";
        
        $notification_message = "Báo cáo mới cho tài liệu: $doc_name";
        sendNotificationToAllAdmins('report', $notification_message, $document_id);
        
        echo json_encode(['success' => true, 'message' => 'Báo cáo của bạn đã được gửi thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Có lỗi xảy ra khi gửi báo cáo']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

mysqli_close($conn);
?>

