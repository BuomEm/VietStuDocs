<?php
require_once __DIR__ . '/db.php';

// ============ USER POINTS FUNCTIONS ============

function getUserPoints($user_id) {
    global $conn;
    $user_id = intval($user_id);
    
    $result = mysqli_query($conn, "SELECT current_points, total_earned, total_spent FROM user_points WHERE user_id=$user_id");
    if(mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    // If user doesn't have points record, create one with zero balance
    // Users start with 0 points and only earn when their documents are purchased
    mysqli_query($conn, "INSERT INTO user_points (user_id, current_points, total_earned, total_spent) VALUES ($user_id, 0, 0, 0)");
    return ['current_points' => 0, 'total_earned' => 0, 'total_spent' => 0];
}

function addPoints($user_id, $points, $reason, $document_id = null) {
    global $conn;
    $user_id = intval($user_id);
    $points = intval($points);
    $reason = mysqli_real_escape_string($conn, $reason);
    
    // Add transaction record
    $doc_id = $document_id ? intval($document_id) : 'NULL';
    $query = "INSERT INTO point_transactions (user_id, transaction_type, points, related_document_id, reason, status) 
              VALUES ($user_id, 'earn', $points, $doc_id, '$reason', 'completed')";
    
    if(!mysqli_query($conn, $query)) {
        return false;
    }
    
    // Update user points balance
    $update = "UPDATE user_points SET 
              current_points = current_points + $points,
              total_earned = total_earned + $points
              WHERE user_id=$user_id";
    
    return mysqli_query($conn, $update);
}

function deductPoints($user_id, $points, $reason, $document_id = null) {
    global $conn;
    $user_id = intval($user_id);
    $points = intval($points);
    $reason = mysqli_real_escape_string($conn, $reason);
    
    // Check if user has enough points
    $current = getUserPoints($user_id);
    if($current['current_points'] < $points) {
        return false;
    }
    
    // Add transaction record
    $doc_id = $document_id ? intval($document_id) : 'NULL';
    $query = "INSERT INTO point_transactions (user_id, transaction_type, points, related_document_id, reason, status) 
              VALUES ($user_id, 'spend', $points, $doc_id, '$reason', 'completed')";
    
    if(!mysqli_query($conn, $query)) {
        return false;
    }
    
    // Get the transaction ID immediately after insert
    $transaction_id = mysqli_insert_id($conn);
    
    // Update user points balance
    $update = "UPDATE user_points SET 
              current_points = current_points - $points,
              total_spent = total_spent + $points
              WHERE user_id=$user_id";
    
    if(!mysqli_query($conn, $update)) {
        return false;
    }
    
    // Return transaction ID if successful
    return $transaction_id;
}

// ============ DOCUMENT POINTS FUNCTIONS ============

function getDocumentPoints($document_id) {
    global $conn;
    $document_id = intval($document_id);
    
    $result = mysqli_query($conn, "
        SELECT dp.admin_points, d.user_price, d.user_id
        FROM docs_points dp
        JOIN documents d ON dp.document_id = d.id
        WHERE dp.document_id=$document_id
        LIMIT 1
    ");
    
    if(mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

function setDocumentPoints($document_id, $points, $admin_id, $notes = '') {
    global $conn;
    $document_id = intval($document_id);
    $points = intval($points);
    $admin_id = intval($admin_id);
    $notes = mysqli_real_escape_string($conn, $notes);
    
    // Check if already has points assigned
    $existing = mysqli_query($conn, "SELECT id FROM docs_points WHERE document_id=$document_id");
    
    if(mysqli_num_rows($existing) > 0) {
        // Update existing
        $query = "UPDATE docs_points SET admin_points=$points, notes='$notes', assigned_at=NOW() WHERE document_id=$document_id";
    } else {
        // Insert new
        $query = "INSERT INTO docs_points (document_id, admin_points, assigned_by, notes) 
                  VALUES ($document_id, $points, $admin_id, '$notes')";
    }
    
    $result = mysqli_query($conn, $query);
    
    if($result) {
        // Also update documents table
        mysqli_query($conn, "UPDATE documents SET admin_points=$points WHERE id=$document_id");
    }
    
    return $result;
}

// ============ DOCUMENT APPROVAL FUNCTIONS ============

function getPendingDocuments() {
    global $conn;
    
    $result = mysqli_query($conn, "
        SELECT d.id, d.original_name, d.description, u.username, d.created_at, d.file_name,
               aa.reviewed_by, aa.reviewed_at
        FROM documents d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN admin_approvals aa ON d.id = aa.document_id
        WHERE d.status = 'pending'
        ORDER BY d.created_at DESC
    ");
    
    return $result;
}

function getPendingDocumentsCount() {
    global $conn;
    
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM documents WHERE status='pending'");
    $row = mysqli_fetch_assoc($result);
    
    return $row['count'];
}

function getDocumentForApproval($document_id) {
    global $conn;
    $document_id = intval($document_id);
    
    $result = mysqli_query($conn, "
        SELECT d.*, u.username, u.email,
               aa.reviewed_by, aa.status as approval_status, aa.admin_points, aa.rejection_reason, aa.reviewed_at,
               dp.admin_points as assigned_points
        FROM documents d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN admin_approvals aa ON d.id = aa.document_id
        LEFT JOIN docs_points dp ON d.id = dp.document_id
        WHERE d.id=$document_id
    ");
    
    if(mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return null;
}

function approveDocument($document_id, $admin_id, $points, $notes = '') {
    global $conn;
    $document_id = intval($document_id);
    $admin_id = intval($admin_id);
    $points = intval($points);
    $notes = mysqli_real_escape_string($conn, $notes);
    
    // Get current document info (to know owner & previous status)
    $doc_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, status FROM documents WHERE id=$document_id"));
    if(!$doc_info) {
        return false;
    }
    $owner_id = intval($doc_info['user_id']);
    $previous_status = $doc_info['status'];
    
    // Update documents table to approved and set admin_points
    $doc_query = "UPDATE documents SET status='approved', admin_points=$points WHERE id=$document_id";
    
    if(!mysqli_query($conn, $doc_query)) {
        return false;
    }
    
    // Insert/Update docs_points (stores admin's academic score / notes)
    setDocumentPoints($document_id, $points, $admin_id, $notes);
    
    // Insert/Update admin_approvals
    $existing = mysqli_query($conn, "SELECT id FROM admin_approvals WHERE document_id=$document_id");
    
    if(mysqli_num_rows($existing) > 0) {
        $approval_query = "UPDATE admin_approvals SET 
                          status='approved', admin_points=$points, reviewed_by=$admin_id, reviewed_at=NOW()
                          WHERE document_id=$document_id";
    } else {
        $approval_query = "INSERT INTO admin_approvals (document_id, reviewed_by, status, admin_points, reviewed_at)
                          VALUES ($document_id, $admin_id, 'approved', $points, NOW())";
    }
    
    if(!mysqli_query($conn, $approval_query)) {
        return false;
    }

    // Points are NOT awarded when document is approved
    // Points are only awarded when the document is purchased by another user

    return true;
}

function rejectDocument($document_id, $admin_id, $reason = '') {
    global $conn;
    $document_id = intval($document_id);
    $admin_id = intval($admin_id);
    $reason = mysqli_real_escape_string($conn, $reason);
    
    // Update documents table
    $doc_query = "UPDATE documents SET status='rejected' WHERE id=$document_id";
    
    if(!mysqli_query($conn, $doc_query)) {
        return false;
    }
    
    // Insert/Update admin_approvals
    $existing = mysqli_query($conn, "SELECT id FROM admin_approvals WHERE document_id=$document_id");
    
    if(mysqli_num_rows($existing) > 0) {
        $approval_query = "UPDATE admin_approvals SET 
                          status='rejected', reviewed_by=$admin_id, reviewed_at=NOW(), rejection_reason='$reason'
                          WHERE document_id=$document_id";
    } else {
        $approval_query = "INSERT INTO admin_approvals (document_id, reviewed_by, status, rejection_reason, reviewed_at)
                          VALUES ($document_id, $admin_id, 'rejected', '$reason', NOW())";
    }
    
    return mysqli_query($conn, $approval_query);
}

// ============ DOCUMENT PURCHASE FUNCTIONS ============

function canUserDownloadDocument($user_id, $document_id) {
    global $conn;
    $user_id = intval($user_id);
    $document_id = intval($document_id);
    
    // Get document info
    $doc = mysqli_fetch_assoc(mysqli_query($conn, "SELECT user_id, user_price FROM documents WHERE id=$document_id"));
    
    if(!$doc) {
        return false;
    }
    
    // Owner can always download
    if($doc['user_id'] == $user_id) {
        return true;
    }
    
    // Check if already purchased
    $purchased = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT id FROM document_sales WHERE document_id=$document_id AND buyer_user_id=$user_id"));
    
    if($purchased) {
        return true;
    }
    
    return false;
}

function purchaseDocument($buyer_id, $document_id) {
    global $conn;
    $buyer_id = intval($buyer_id);
    $document_id = intval($document_id);
    
    // Get document and pricing info
    $doc = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT d.user_id, d.user_price, d.original_name, dp.admin_points 
         FROM documents d 
         LEFT JOIN docs_points dp ON d.id = dp.document_id
         WHERE d.id=$document_id AND d.status='approved'"));
    
    if(!$doc) {
        return ['success' => false, 'message' => 'Tài liệu không tồn tại hoặc chưa được phê duyệt'];
    }
    
    // Check if owner trying to buy their own document
    if($doc['user_id'] == $buyer_id) {
        return ['success' => false, 'message' => 'Bạn không thể mua tài liệu của chính mình'];
    }
    
    // Get document points from docs_points table if available
    $doc_points = getDocumentPoints($document_id);
    if($doc_points) {
        $points_to_pay = $doc_points['user_price'] > 0 ? $doc_points['user_price'] : ($doc_points['admin_points'] ?? 0);
    } else {
        $points_to_pay = $doc['user_price'] > 0 ? $doc['user_price'] : ($doc['admin_points'] ?? 0);
    }
    
    // If document is free, just record the purchase without deducting points
    if($points_to_pay <= 0) {
        // Record free purchase
        $sales_query = "INSERT INTO document_sales (document_id, buyer_user_id, seller_user_id, points_paid, transaction_id)
                        VALUES ($document_id, $buyer_id, {$doc['user_id']}, 0, NULL)";
        
        if(!mysqli_query($conn, $sales_query)) {
            return ['success' => false, 'message' => 'Không thể ghi nhận giao dịch'];
        }
        
        return ['success' => true, 'message' => 'Mua tài liệu miễn phí thành công'];
    }
    
    // Check if user has enough points
    $user_points = getUserPoints($buyer_id);
    if($user_points['current_points'] < $points_to_pay) {
        $needed = number_format($points_to_pay, 0, ',', '.');
        $current = number_format($user_points['current_points'], 0, ',', '.');
        return ['success' => false, 'message' => "Bạn không đủ điểm. Bạn cần $needed điểm nhưng chỉ có $current điểm"];
    }
    
    // Deduct points from buyer (this already creates a transaction record)
    $doc_name = mysqli_real_escape_string($conn, $doc['original_name']);
    $transaction_id = deductPoints($buyer_id, $points_to_pay, "Mua tài liệu: " . $doc_name, $document_id);
    
    if(!$transaction_id) {
        return ['success' => false, 'message' => 'Không thể xử lý thanh toán'];
    }
    
    // Record in document_sales
    $seller_id = $doc['user_id'];
    $sales_query = "INSERT INTO document_sales (document_id, buyer_user_id, seller_user_id, points_paid, transaction_id)
                    VALUES ($document_id, $buyer_id, $seller_id, $points_to_pay, $transaction_id)";
    
    if(!mysqli_query($conn, $sales_query)) {
        // Rollback: refund points if sale record fails
        addPoints($buyer_id, $points_to_pay, "Hoàn tiền do lỗi ghi nhận giao dịch", $document_id);
        return ['success' => false, 'message' => 'Không thể ghi nhận giao dịch. Điểm đã được hoàn lại.'];
    }
    
    // Award points to seller (document owner) when document is purchased
    $seller_id = $doc['user_id'];
    $doc_name = mysqli_real_escape_string($conn, $doc['original_name']);
    if($seller_id > 0 && $points_to_pay > 0) {
        addPoints($seller_id, $points_to_pay, "Tài liệu của bạn đã được mua: " . $doc_name, $document_id);
    }
    
    // Notify admin (optional, don't fail if this fails)
    require_once __DIR__ . '/notifications.php';
    $message = "Tài liệu đã được bán với giá $points_to_pay điểm";
    sendNotificationToAllAdmins('document_sold', $message, $document_id);
    
    return ['success' => true, 'message' => 'Mua tài liệu thành công', 'transaction_id' => $transaction_id];
}

// ============ TRANSACTION HISTORY FUNCTIONS ============

function getTransactionHistory($user_id, $limit = 20, $offset = 0) {
    global $conn;
    $user_id = intval($user_id);
    $limit = intval($limit);
    $offset = intval($offset);
    
    $result = mysqli_query($conn, "
        SELECT pt.*, d.original_name
        FROM point_transactions pt
        LEFT JOIN documents d ON pt.related_document_id = d.id
        WHERE pt.user_id=$user_id
        ORDER BY pt.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    
    return $result;
}

function getTransactionHistoryCount($user_id) {
    global $conn;
    $user_id = intval($user_id);
    
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM point_transactions WHERE user_id=$user_id");
    $row = mysqli_fetch_assoc($result);
    
    return $row['count'];
}
?>
