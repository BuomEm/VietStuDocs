<?php
require_once __DIR__ . '/function.php';
require_once __DIR__ . '/../push/send_push.php';

// ============ USER POINTS FUNCTIONS ============

function getUserPoints($user_id) {
    $user_id = intval($user_id);
    
    $row = db_get_row("SELECT current_points, topup_points, bonus_points, total_earned, total_spent FROM user_points WHERE user_id=$user_id");
    if($row) {
        return $row;
    }
    
    // If user doesn't have points record, create one with zero balance
    db_query("INSERT INTO user_points (user_id, current_points, topup_points, bonus_points, total_earned, total_spent) VALUES ($user_id, 0, 0, 0, 0, 0)");
    return ['current_points' => 0, 'topup_points' => 0, 'bonus_points' => 0, 'total_earned' => 0, 'total_spent' => 0];
}

function addPoints($user_id, $points, $reason, $document_id = null, $type = 'topup') {
    $user_id = intval($user_id);
    $points = intval($points);
    $reason = db_escape($reason);
    $type = ($type === 'bonus') ? 'bonus' : 'topup';
    
    // IP and Device
    $ip = db_escape($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $device = db_escape(substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255));
    
    // Add transaction record
    $doc_id = $document_id ? intval($document_id) : 'NULL';
    $query = "INSERT INTO point_transactions (user_id, transaction_type, points, point_type, related_document_id, reason, status, ip_address, device_id) 
              VALUES ($user_id, 'earn', $points, '$type', $doc_id, '$reason', 'completed', '$ip', '$device')";
    
    if(!db_query($query)) {
        return false;
    }
    
    // Update user points balance
    $column = ($type === 'bonus') ? 'bonus_points' : 'topup_points';
    $update = "UPDATE user_points SET 
              current_points = current_points + $points,
              $column = $column + $points,
              total_earned = total_earned + $points
              WHERE user_id=$user_id";
    
    return db_query($update);
}

function deductPoints($user_id, $points, $reason, $document_id = null) {
    $user_id = intval($user_id);
    $points = intval($points);
    $reason = db_escape($reason);
    
    // Check if user has enough points
    $points_data = getUserPoints($user_id);
    if($points_data['current_points'] < $points) {
        return false;
    }
    
    // Logic: Deduct from bonus points first, then topup
    $bonus_to_deduct = min($points_data['bonus_points'], $points);
    $topup_to_deduct = $points - $bonus_to_deduct;
    
    // IP and Device for Anti-Abuse
    $ip = db_escape($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $device = db_escape(substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255));
    
    // Add transaction record
    $doc_id = $document_id ? intval($document_id) : 'NULL';
    $query = "INSERT INTO point_transactions (user_id, transaction_type, points, bonus_points_deducted, topup_points_deducted, related_document_id, reason, status, ip_address, device_id) 
              VALUES ($user_id, 'spend', $points, $bonus_to_deduct, $topup_to_deduct, $doc_id, '$reason', 'completed', '$ip', '$device')";
    
    if(!db_query($query)) {
        return false;
    }
    
    $transaction_id = db_insert_id();
    
    // Update user points balance
    $update = "UPDATE user_points SET 
              current_points = current_points - $points,
              bonus_points = bonus_points - $bonus_to_deduct,
              topup_points = topup_points - $topup_to_deduct,
              total_spent = total_spent + $points
              WHERE user_id=$user_id";
    
    if(!db_query($update)) {
        return false;
    }
    
    return $transaction_id;
}

// ============ ESCROW FUNCTIONS ============

function lockPoints($user_id, $points, $reason, $ref_type = 'tutor_request', $ref_id = null) {
    $user_id = intval($user_id);
    $points = intval($points);
    $reason = db_escape($reason);
    $ref_type = db_escape($ref_type);
    $ref_id = $ref_id ? intval($ref_id) : 'NULL';
    
    // Check if user has enough points
    $points_data = getUserPoints($user_id);
    if($points_data['current_points'] < $points) {
        return false;
    }
    
    // Deduct from bonus points first, then topup
    $bonus_to_lock = min($points_data['bonus_points'], $points);
    $topup_to_lock = $points - $bonus_to_lock;
    
    // IP and Device
    $ip = db_escape($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $device = db_escape(substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255));
    
    // Add transaction record with 'locked' status
    $query = "INSERT INTO point_transactions (user_id, transaction_type, points, bonus_points_deducted, topup_points_deducted, related_id, related_type, reason, status, ip_address, device_id) 
              VALUES ($user_id, 'lock', $points, $bonus_to_lock, $topup_to_lock, $ref_id, '$ref_type', '$reason', 'locked', '$ip', '$device')";
    
    if(!db_query($query)) {
        return false;
    }
    
    $transaction_id = db_insert_id();
    
    // Update user points balance (reduce current, but keep track of locked)
    $update = "UPDATE user_points SET 
              current_points = current_points - $points,
              bonus_points = bonus_points - $bonus_to_lock,
              topup_points = topup_points - $topup_to_lock,
              locked_points = locked_points + $points
              WHERE user_id=$user_id";
    
    if(!db_query($update)) {
        return false;
    }
    
    return $transaction_id;
}

function settleEscrow($transaction_id, $tutor_id, $admin_share_percent) {
    $transaction_id = intval($transaction_id);
    $tutor_id = intval($tutor_id);
    
    $tx = db_get_row("SELECT * FROM point_transactions WHERE id = $transaction_id");
    if (!$tx) return false;

    if ($tx['status'] === 'settled') {
        return true;
    }

    if ($tx['status'] !== 'locked') {
        return false;
    }
    
    $total_points = intval($tx['points']);
    $user_id = intval($tx['user_id']);
    
    $admin_points = floor($total_points * ($admin_share_percent / 100));
    $tutor_points = $total_points - $admin_points;
    
    // 1. Mark transaction as settled
    db_query("UPDATE point_transactions SET status = 'settled' WHERE id = $transaction_id");
    
    // 2. Update user's locked_points (reduce)
    db_query("UPDATE user_points SET locked_points = locked_points - $total_points, total_spent = total_spent + $total_points WHERE user_id = $user_id");
    
    // 3. Add Topup Points to Tutor (Tutor always receives Topup points that can be withdrawn)
    addPoints($tutor_id, $tutor_points, "Thù lao từ yêu cầu #{$tx['related_id']}", null, 'topup');
    
    // 4. Record Admin Commission (if needed for reporting)
    // We could have an admin_vault table or just log it
    db_query("INSERT INTO admin_earnings (amount, source_type, source_id) VALUES ($admin_points, 'tutor_request', {$tx['related_id']})");
    
    return true;
}

function refundEscrow($transaction_id, $reason = 'Yêu cầu bị hủy') {
    $transaction_id = intval($transaction_id);
    $tx = db_get_row("SELECT * FROM point_transactions WHERE id = $transaction_id AND status = 'locked'");
    if (!$tx) return false;
    
    $total_points = intval($tx['points']);
    $user_id = intval($tx['user_id']);
    $bonus_refund = intval($tx['bonus_points_deducted']);
    $topup_refund = intval($tx['topup_points_deducted']);
    
    // 1. Mark transaction as refunded
    db_query("UPDATE point_transactions SET status = 'refunded' WHERE id = $transaction_id");
    
    // 2. Return points to user
    $update = "UPDATE user_points SET 
              current_points = current_points + $total_points,
              bonus_points = bonus_points + $bonus_refund,
              topup_points = topup_points + $topup_refund,
              locked_points = locked_points - $total_points
              WHERE user_id=$user_id";
    
    return db_query($update);
}

// ============ DOCUMENT POINTS FUNCTIONS ============

function getDocumentPoints($document_id) {
    $document_id = intval($document_id);
    
    // Use LEFT JOIN to ensure we get document data even if docs_points doesn't exist
    return db_get_row("
        SELECT d.user_price, d.user_id, dp.admin_points
        FROM documents d
        LEFT JOIN docs_points dp ON d.id = dp.document_id
        WHERE d.id = $document_id
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
               d.ai_status, d.ai_score, d.ai_decision, d.ai_price,
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
    
    if(!db_query($approval_query)) {
        return false;
    }

    // Check if reward points on approval is enabled
    require_once __DIR__ . '/settings.php';
    $reward_enabled = isSettingEnabled('reward_points_on_approval');
    $reward_msg = "";
    
    if($reward_enabled && $points > 0) {
        $doc_name = db_get_row("SELECT original_name FROM documents WHERE id=$document_id")['original_name'] ?? 'tài liệu';
        addPoints($owner_id, $points, "Thưởng điểm khi tài liệu được duyệt: " . $doc_name, $document_id, 'bonus');
        $reward_msg = " và bạn được tặng " . number_format($points) . " điểm";
    }

    // Notify user
    global $VSD;
    $VSD->insert('notifications', [
        'user_id' => $owner_id,
        'type' => 'document_approved',
        'ref_id' => $document_id,
        'message' => "Tài liệu của bạn đã được duyệt thành công" . ($reward_enabled ? "$reward_msg." : " và định giá " . number_format($points) . " điểm.")
    ]);
    sendPushToUser($owner_id, [
        'title' => 'Tài liệu đã được duyệt! 🎉',
        'body' => "Tài liệu của bạn đã được duyệt" . ($reward_enabled ? ", + " . number_format($points) . " điểm." : "."),
        'url' => '/history.php?tab=notifications'
    ]);

    return true;
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
    
    if(!db_query($approval_query)) {
        return false;
    }

    // Notify user
    $doc_info = db_get_row("SELECT user_id FROM documents WHERE id=$document_id");
    if($doc_info) {
        $owner_id = intval($doc_info['user_id']);
        global $VSD;
        $VSD->insert('notifications', [
            'user_id' => $owner_id,
            'type' => 'document_rejected',
            'ref_id' => $document_id,
            'message' => "Tài liệu của bạn đã bị từ chối. Lý do: $reason"
        ]);
        sendPushToUser($owner_id, [
            'title' => 'Tài liệu bị từ chối',
            'body' => "Tài liệu của bạn đã bị từ chối. Nhấn để xem lý do.",
            'url' => '/history.php?tab=notifications'
        ]);
    }

    return true;
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
    
    // Get points to pay - logic: NULL -> admin_points, 0 -> 0 (free), > 0 -> user_price
    $user_price = isset($doc['user_price']) && $doc['user_price'] !== null ? intval($doc['user_price']) : null;
    $admin_points = intval($doc['admin_points'] ?? 0);
    
    // Logic: NULL -> admin_points, 0 -> 0 (free), > 0 -> user_price
    if ($user_price === null) {
        $points_to_pay = $admin_points;
    } else {
        $points_to_pay = $user_price;
    }
    
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
    
    // Award points to seller (AS BONUS POINTS - Non-convertible)
    addPoints($seller_id, $points_to_pay, "Tài liệu của bạn đã được mua: " . $doc_name, $document_id, 'bonus');
    
    // Notify Seller
    global $VSD;
    $VSD->insert('notifications', [
        'user_id' => $seller_id,
        'type' => 'document_sold',
        'ref_id' => $document_id,
        'message' => "Tài liệu '$doc_name' của bạn vừa được mua. Bạn nhận được $points_to_pay điểm."
    ]);
    sendPushToUser($seller_id, [
        'title' => 'Tài liệu đã được bán',
        'body' => "Tài liệu của bạn vừa được mua. +$points_to_pay điểm.",
        'url' => '/history.php?tab=notifications'
    ]);

    // Notify admin
    try {
        if(file_exists(__DIR__ . '/notifications.php')) {
            require_once __DIR__ . '/notifications.php';
            
            $buyer_info = db_get_row("SELECT username FROM users WHERE id=$buyer_id");
            $buyer_name = $buyer_info['username'] ?? "Người dùng #$buyer_id";
            
            $message = "Tài liệu đã được bán với giá $points_to_pay điểm";
            $extra_data = ['buyer_name' => $buyer_name];
            
            sendNotificationToAllAdmins('document_sold', $message, $document_id, $extra_data);
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
