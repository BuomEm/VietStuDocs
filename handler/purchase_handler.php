<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';

header('Content-Type: application/json; charset=utf-8');

// Check if user is logged in
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập để mua tài liệu'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = getCurrentUserId();
$document_id = intval($_POST['document_id'] ?? 0);

if($document_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tài liệu không hợp lệ'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check if already purchased
if(canUserDownloadDocument($user_id, $document_id)) {
    echo json_encode(['success' => false, 'message' => 'Bạn đã mua tài liệu này rồi'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Purchase the document
try {
    $result = purchaseDocument($user_id, $document_id);
    
    if(!$result || !is_array($result)) {
        error_log("Purchase error: purchaseDocument returned invalid result for user_id=$user_id, document_id=$document_id");
        echo json_encode([
            'success' => false, 
            'message' => 'Đã xảy ra lỗi không xác định. Vui lòng thử lại sau.'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
} catch(Exception $e) {
    error_log("Purchase exception: " . $e->getMessage() . " for user_id=$user_id, document_id=$document_id");
    echo json_encode([
        'success' => false, 
        'message' => 'Đã xảy ra lỗi: ' . htmlspecialchars($e->getMessage())
    ], JSON_UNESCAPED_UNICODE);
} catch(Error $e) {
    error_log("Purchase fatal error: " . $e->getMessage() . " for user_id=$user_id, document_id=$document_id");
    echo json_encode([
        'success' => false, 
        'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.'
    ], JSON_UNESCAPED_UNICODE);
}

mysqli_close($conn);
?>

