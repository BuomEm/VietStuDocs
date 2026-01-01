<?php
require_once __DIR__ . '/function.php';

// ============ USER POINTS FUNCTIONS ============

function getUserPoints($user_id) {
    $user_id = intval($user_id);
    
    $row = db_get_row("SELECT current_points, total_earned, total_spent FROM user_points WHERE user_id=$user_id");
    if($row) {
        return $row;
    }
    
    // If user doesn't have points record, create one with zero balance
    db_query("INSERT INTO user_points (user_id, current_points, total_earned, total_spent) VALUES ($user_id, 0, 0, 0)");
    return ['current_points' => 0, 'total_earned' => 0, 'total_spent' => 0];
}

function addPoints($user_id, $points, $reason, $document_id = null) {
    $user_id = intval($user_id);
    $points = intval($points);
    $reason = db_escape($reason);
    
    // Add transaction record
    $doc_id = $document_id ? intval($document_id) : 'NULL';
    $query = "INSERT INTO point_transactions (user_id, transaction_type, points, related_document_id, reason, status) 
              VALUES ($user_id, 'earn', $points, $doc_id, '$reason', 'completed')";
    
    if(!db_query($query)) {
        return false;
    }
    
    // Update user points balance
    $update = "UPDATE user_points SET 
              current_points = current_points + $points,
              total_earned = total_earned + $points
              WHERE user_id=$user_id";
    
    return db_query($update);
}

function deductPoints($user_id, $points, $reason, $document_id = null) {
    $user_id = intval($user_id);
    $points = intval($points);
    $reason = db_escape($reason);
    
    // Check if user has enough points
    $current = getUserPoints($user_id);
    if($current['current_points'] < $points) {
        return false;
    }
    
    // Add transaction record
    $doc_id = $document_id ? intval($document_id) : 'NULL';
    $query = "INSERT INTO point_transactions (user_id, transaction_type, points, related_document_id, reason, status) 
              VALUES ($user_id, 'spend', $points, $doc_id, '$reason', 'completed')";
    
    if(!db_query($query)) {
        return false;
    }
    
    // Get the transaction ID immediately after insert
    $transaction_id = db_insert_id();
    
    // Update user points balance
    $update = "UPDATE user_points SET 
              current_points = current_points - $points,
              total_spent = total_spent + $points
              WHERE user_id=$user_id";
    
    if(!db_query($update)) {
        return false;
    }
    
    // Return transaction ID if successful
    return $transaction_id;
}

// ============ DOCUMENT POINTS FUNCTIONS ============

function getDocumentPoints($document_id) {
    $document_id = intval($document_id);
    
    return db_get_row("
        SELECT dp.admin_points, d.user_price, d.user_id
        FROM docs_points dp
        JOIN documents d ON dp.document_id = d.id
        WHERE dp.document_id=$document_id
        LIMIT 1
    ");
}

function setDocumentPoints($document_id, $points, $admin_id, $notes = '') {
    $document_id = intval($document_id);
    $points = intval($points);
    $admin_id = intval($admin_id);
    $notes = db_escape($notes);
    
    // Check if already has points assigned
    $exists = db_num_rows("SELECT id FROM docs_points WHERE document_id=$document_id") > 0;
    
    if($exists) {
        // Update existing
        $query = "UPDATE docs_points SET admin_points=$points, notes='$notes', assigned_at=NOW() WHERE document_id=$document_id";
    } else {
        // Insert new
        $query = "INSERT INTO docs_points (document_id, admin_points, assigned_by, notes) 
                  VALUES ($document_id, $points, $admin_id, '$notes')";
    }
    
    $result = db_query($query);
    
    if($result) {
        // Also update documents table
        db_query("UPDATE documents SET admin_points=$points WHERE id=$document_id");
    }
    
    return $result;
}

// ============ DOCUMENT APPROVAL FUNCTIONS ============

function getPendingDocuments() {
    return db_get_results("
        SELECT d.id, d.original_name, d.description, u.username, d.created_at, d.file_name,
               aa.reviewed_by, aa.reviewed_at
        FROM documents d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN admin_approvals aa ON d.id = aa.document_id
        WHERE d.status = 'pending'
        ORDER BY d.created_at DESC
    ");
}

function getPendingDocumentsCount() {
    $row = db_get_row("SELECT COUNT(*) as count FROM documents WHERE status='pending'");
    return $row['count'] ?? 0;
}

function getDocumentForApproval($document_id) {
    $document_id = intval($document_id);
    
    return db_get_row("
        SELECT d.*, u.username, u.email,
               aa.reviewed_by, aa.status as approval_status, aa.admin_points, aa.rejection_reason, aa.reviewed_at,
               dp.admin_points as assigned_points
        FROM documents d
        JOIN users u ON d.user_id = u.id
        LEFT JOIN admin_approvals aa ON d.id = aa.document_id
        LEFT JOIN docs_points dp ON d.id = dp.document_id
        WHERE d.id=$document_id
    ");
}

function approveDocument($document_id, $admin_id, $points, $notes = '') {
    $document_id = intval($document_id);
    $admin_id = intval($admin_id);
    $points = intval($points);
    $notes = db_escape($notes);
    
    // Get current document info (to know owner & previous status)
    $doc_info = db_get_row("SELECT user_id, status FROM documents WHERE id=$document_id");
    if(!$doc_info) {
        return false;
    }
    $owner_id = intval($doc_info['user_id']);
    
    // Update documents table to approved and set admin_points
    $doc_query = "UPDATE documents SET status='approved', admin_points=$points WHERE id=$document_id";
    
    if(!db_query($doc_query)) {
        return false;
    }
    
    // Insert/Update docs_points (stores admin's academic score / notes)
    setDocumentPoints($document_id, $points, $admin_id, $notes);
    
    // Insert/Update admin_approvals
    $exists = db_num_rows("SELECT id FROM admin_approvals WHERE document_id=$document_id") > 0;
    
    if($exists) {
        $approval_query = "UPDATE admin_approvals SET 
                          status='approved', admin_points=$points, reviewed_by=$admin_id, reviewed_at=NOW()
                          WHERE document_id=$document_id";
    } else {
        $approval_query = "INSERT INTO admin_approvals (document_id, reviewed_by, status, admin_points, reviewed_at)
                          VALUES ($document_id, $admin_id, 'approved', $points, NOW())";
    }
    
    return db_query($approval_query);
}

function rejectDocument($document_id, $admin_id, $reason = '') {
    $document_id = intval($document_id);
    $admin_id = intval($admin_id);
    $reason = db_escape($reason);
    
    // Update documents table
    $doc_query = "UPDATE documents SET status='rejected' WHERE id=$document_id";
    
    if(!db_query($doc_query)) {
        return false;
    }
    
    // Insert/Update admin_approvals
    $exists = db_num_rows("SELECT id FROM admin_approvals WHERE document_id=$document_id") > 0;
    
    if($exists) {
        $approval_query = "UPDATE admin_approvals SET 
                          status='rejected', reviewed_by=$admin_id, reviewed_at=NOW(), rejection_reason='$reason'
                          WHERE document_id=$document_id";
    } else {
        $approval_query = "INSERT INTO admin_approvals (document_id, reviewed_by, status, rejection_reason, reviewed_at)
                          VALUES ($document_id, $admin_id, 'rejected', '$reason', NOW())";
    }
    
    return db_query($approval_query);
}

// ============ DOCUMENT PURCHASE FUNCTIONS ============

function canUserDownloadDocument($user_id, $document_id) {
    $user_id = intval($user_id);
    $document_id = intval($document_id);
    
    // Get document info
    $doc = db_get_row("SELECT user_id, user_price FROM documents WHERE id=$document_id");
    
    if(!$doc) {
        return false;
    }
    
    // Owner can always download
    if($doc['user_id'] == $user_id) {
        return true;
    }
    
    // Check if already purchased
    $purchased = db_num_rows("SELECT id FROM document_sales WHERE document_id=$document_id AND buyer_user_id=$user_id") > 0;
    
    if($purchased) {
        return true;
    }
    
    return false;
}

function purchaseDocument($buyer_id, $document_id) {
    $buyer_id = intval($buyer_id);
    $document_id = intval($document_id);
    
    // Get document and pricing info
    $doc = db_get_row("SELECT d.user_id, d.user_price, d.original_name, dp.admin_points 
         FROM documents d 
         LEFT JOIN docs_points dp ON d.id = dp.document_id
         WHERE d.id=$document_id AND d.status='approved'");
    
    if(!$doc) {
        return ['success' => false, 'message' => 'Tài liệu không tồn tại hoặc chưa được phê duyệt'];
    }
    
    $seller_id = intval($doc['user_id']);
    
    // Check if owner trying to buy their own document
    if($seller_id == $buyer_id) {
        return ['success' => false, 'message' => 'Bạn không thể mua tài liệu của chính mình'];
    }
    
    // Get points to pay
    $points_to_pay = intval($doc['user_price']) > 0 ? intval($doc['user_price']) : intval($doc['admin_points'] ?? 0);
    
    // If document is free
    if($points_to_pay <= 0) {
        $sales_query = "INSERT INTO document_sales (document_id, buyer_user_id, seller_user_id, points_paid, transaction_id)
                        VALUES ($document_id, $buyer_id, $seller_id, 0, NULL)";
        
        if(!db_query($sales_query)) {
            return ['success' => false, 'message' => 'Không thể ghi nhận giao dịch'];
        }
        
        return ['success' => true, 'message' => 'Mua tài liệu miễn phí thành công'];
    }
    
    // Check if user has enough points
    $user_points = getUserPoints($buyer_id);
    $current_points = intval($user_points['current_points'] ?? 0);
    
    if($current_points < $points_to_pay) {
        $needed = number_format($points_to_pay, 0, ',', '.');
        $current = number_format($current_points, 0, ',', '.');
        return ['success' => false, 'message' => "Bạn không đủ điểm. Bạn cần $needed điểm nhưng chỉ có $current điểm"];
    }
    
    // Deduct points from buyer
    $doc_name = db_escape($doc['original_name'] ?? 'Unknown');
    $transaction_id = deductPoints($buyer_id, $points_to_pay, "Mua tài liệu: " . $doc_name, $document_id);
    
    if(!$transaction_id) {
        return ['success' => false, 'message' => 'Không thể xử lý thanh toán.'];
    }
    
    // Record in document_sales
    $sales_query = "INSERT INTO document_sales (document_id, buyer_user_id, seller_user_id, points_paid, transaction_id)
                    VALUES ($document_id, $buyer_id, $seller_id, $points_to_pay, $transaction_id)";
    
    if(!db_query($sales_query)) {
        // Rollback
        addPoints($buyer_id, $points_to_pay, "Hoàn tiền do lỗi ghi nhận giao dịch", $document_id);
        return ['success' => false, 'message' => 'Không thể ghi nhận giao dịch. Điểm đã được hoàn lại.'];
    }
    
    // Award points to seller
    addPoints($seller_id, $points_to_pay, "Tài liệu của bạn đã được mua: " . $doc_name, $document_id);
    
    // Notify admin
    try {
        if(file_exists(__DIR__ . '/notifications.php')) {
            require_once __DIR__ . '/notifications.php';
            $message = "Tài liệu đã được bán với giá $points_to_pay điểm";
            sendNotificationToAllAdmins('document_sold', $message, $document_id);
        }
    } catch(Exception $e) {
        error_log("Purchase warning: " . $e->getMessage());
    }
    
    return ['success' => true, 'message' => 'Mua tài liệu thành công', 'transaction_id' => $transaction_id];
}

// ============ TRANSACTION HISTORY FUNCTIONS ============

function getTransactionHistory($user_id, $limit = 20, $offset = 0) {
    $user_id = intval($user_id);
    $limit = intval($limit);
    $offset = intval($offset);
    
    return db_get_results("
        SELECT pt.*, d.original_name
        FROM point_transactions pt
        LEFT JOIN documents d ON pt.related_document_id = d.id
        WHERE pt.user_id=$user_id
        ORDER BY pt.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
}

function getTransactionHistoryCount($user_id) {
    $user_id = intval($user_id);
    $row = db_get_row("SELECT COUNT(*) as count FROM point_transactions WHERE user_id=$user_id");
    return $row['count'] ?? 0;
}
?>
