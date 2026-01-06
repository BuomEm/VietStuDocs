<?php
/**
 * Unified Admin Notification Sender
 * Tạo notification và gửi qua Browser Push và Telegram theo settings
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/telegram_notifications.php';

/**
 * Gửi thông báo cho admin
 * @param int $admin_id ID của admin
 * @param string $message Nội dung thông báo
 * @param int|null $document_id ID của tài liệu (nếu có)
 * @param array|null $extra_data Dữ liệu bổ sung cho Telegram (ví dụ: ['price' => 100, 'buyer' => 'user1'])
 * @param array|null $buttons Các nút bổ sung (nếu không truyền sẽ tự động tạo từ type)
 * @return array ['success' => bool, 'notification_id' => int|null, 'telegram_sent' => bool]
 */
function sendAdminNotification($admin_id, $type, $message, $document_id = null, $extra_data = null, $buttons = null) {
    global $conn;
    
    $admin_id = intval($admin_id);
    $type = mysqli_real_escape_string($conn, $type);
    $esc_message = mysqli_real_escape_string($conn, $message);
    $document_id = $document_id && $document_id !== 'NULL' ? intval($document_id) : 'NULL';
    
    // Validate notification type
    $valid_types = ['new_document', 'document_sold', 'system_alert', 'report', 'new_tutor'];
    if (!in_array($type, $valid_types)) {
        error_log("Invalid notification type: $type");
        return ['success' => false, 'notification_id' => null, 'telegram_sent' => false];
    }
    
    // Insert vào database (với type new_tutor, document_id có thể là user_id hoặc null)
    $insert_query = "INSERT INTO admin_notifications (admin_id, notification_type, document_id, message, created_at) 
                     VALUES ($admin_id, '$type', $document_id, '$esc_message', NOW())";
    
    if (!mysqli_query($conn, $insert_query)) {
        error_log("Failed to insert notification: " . mysqli_error($conn));
        return ['success' => false, 'notification_id' => null, 'telegram_sent' => false];
    }
    
    $notification_id = mysqli_insert_id($conn);
    
    // Check settings và gửi notifications
    $telegram_sent = false;
    
    // Check Telegram
    $telegram_enabled = isSettingEnabled('telegram_enabled') && 
                         isSettingEnabled('notify_telegram_enabled') && 
                         (isSettingEnabled('notify_' . $type . '_telegram') || $type === 'new_tutor'); // new_tutor check is optional
    
    if ($telegram_enabled) {
        $telegram_message = $message;
        $telegram_buttons = $buttons;
        
        // Làm giàu thông tin cho Telegram
        $rich_info = buildRichTelegramMessage($type, $message, $document_id, $extra_data);
        $telegram_message = $rich_info['data'];
        
        // Nếu không truyền buttons, lấy mặc định từ buildRichTelegramMessage
        if (!$telegram_buttons && !empty($rich_info['buttons'])) {
            $telegram_buttons = $rich_info['buttons'];
        }
        
        $telegram_result = sendTelegramNotification($telegram_message, $type, $telegram_buttons);
        $telegram_sent = $telegram_result['success'] ?? false;
        
        if (!$telegram_sent) {
            error_log("Telegram notification failed: " . ($telegram_result['message'] ?? 'Unknown error'));
        }
    }
    
    return [
        'success' => true,
        'notification_id' => $notification_id,
        'telegram_sent' => $telegram_sent
    ];
}

/**
 * Xây dựng nội dung chi tiết và nút bấm cho Telegram
 */
function buildRichTelegramMessage($type, $default_message, $document_id, $extra_data = null) {
    global $conn;
    $document_id = ($document_id !== 'NULL') ? intval($document_id) : null;
    
    $data = ['title' => $default_message];
    $buttons = [];
    
    if ($type === 'new_document' && $document_id) {
        $doc_query = "SELECT d.original_name, u.username as owner_name FROM documents d 
                      LEFT JOIN users u ON d.user_id = u.id WHERE d.id = $document_id";
        $doc = mysqli_fetch_assoc(mysqli_query($conn, $doc_query));
        if ($doc) {
            $data['document'] = $doc['original_name'];
            $data['uploader'] = $doc['owner_name'];
            $data['url'] = "/admin/view-document.php?id=" . $document_id;
            
            $buttons[] = ['text' => '✅ Duyệt', 'callback_data' => "approve_doc:{$document_id}"];
            $buttons[] = ['text' => '❌ Từ chối', 'callback_data' => "reject_doc:{$document_id}"];
            $buttons[] = ['text' => '👁️ Xem', 'url' => getBaseUrl() . "/admin/view-document.php?id=" . $document_id];
        }
    } elseif ($type === 'new_tutor') {
        // Trong trường hợp new_tutor, document_id là tutor_id (id trong bảng tutors)
        $tutor_query = "SELECT t.id, u.username, t.subjects FROM tutors t JOIN users u ON t.user_id = u.id WHERE t.id = $document_id";
        $tutor = mysqli_fetch_assoc(mysqli_query($conn, $tutor_query));
        if ($tutor) {
            $data['tutor'] = $tutor['username'];
            $data['subjects'] = $tutor['subjects'];
            $data['url'] = "/admin/tutors.php";
            
            $buttons[] = ['text' => '✅ Kích hoạt', 'callback_data' => "approve_tutor:{$tutor['id']}"];
            $buttons[] = ['text' => '❌ Từ chối', 'callback_data' => "reject_tutor:{$tutor['id']}"];
        }
    } elseif ($type === 'report' && $document_id) {
        // Lấy report_id từ extra_data nếu có
        $report_id = $extra_data['report_id'] ?? null;
        
        $doc_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT original_name FROM documents WHERE id=$document_id"));
        $data['document'] = $doc_info['original_name'] ?? "Tài liệu #$document_id";
        if (isset($extra_data['reason'])) $data['reason'] = $extra_data['reason'];
        if (isset($extra_data['reporter_name'])) $data['reporter'] = $extra_data['reporter_name'];
        $data['url'] = "/admin/reports.php";

        if ($report_id) {
            $buttons[] = ['text' => '✅ Bỏ qua', 'callback_data' => "dismiss_report:{$report_id}"];
            $buttons[] = ['text' => '🗑️ Xóa tài liệu', 'callback_data' => "delete_doc_report:{$report_id}"];
        }
        $buttons[] = ['text' => '🚩 Xem báo cáo', 'url' => getBaseUrl() . "/admin/reports.php"];
    } elseif ($type === 'document_sold' && $document_id) {
        $doc_query = "SELECT d.original_name, d.user_price, d.admin_points FROM documents d WHERE d.id = $document_id";
        $doc = mysqli_fetch_assoc(mysqli_query($conn, $doc_query));
        if ($doc) {
            $data['document'] = $doc['original_name'];
            $data['price'] = ($doc['user_price'] > 0 ? $doc['user_price'] : $doc['admin_points']) . " điểm";
            if (isset($extra_data['buyer_name'])) $data['buyer'] = $extra_data['buyer_name'];
        }
    } elseif ($type === 'system_alert' && is_array($extra_data)) {
        $data = array_merge($data, $extra_data);
    }
    
    
    // KIỂM TRA LOCALHOST: Nếu đang ở localhost, gỡ bỏ tất cả buttons callback_data
    // vì Telegram không thể gửi callback về localhost.
    $is_localhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false;
    if ($is_localhost) {
        $buttons = array_filter($buttons, function($btn) {
            return isset($btn['url']); // Chỉ giữ lại các nút mở link (URL)
        });
        
        // Có thể thêm 1 nút cảnh báo Localhost
        if (empty($buttons)) {
            $buttons[] = ['text' => '⚠️ Localhost - No Actions', 'url' => getBaseUrl()];
        }
    }

    return ['data' => $data, 'buttons' => array_values($buttons)];
}

/**
 * Gửi thông báo cho tất cả admin
 * @param string $type Loại thông báo
 * @param string $message Nội dung thông báo
 * @param int|null $document_id ID của tài liệu (nếu có)
 * @param array|null $extra_data Dữ liệu bổ sung
 * @param array|null $buttons Các nút bấm
 * @return array ['success' => bool, 'sent_count' => int, 'telegram_sent' => bool]
 */
function sendNotificationToAllAdmins($type, $message, $document_id = null, $extra_data = null, $buttons = null) {
    global $conn;
    
    $admins = mysqli_query($conn, "SELECT id FROM users WHERE role='admin'");
    $sent_count = 0;
    $telegram_sent = false;
    
    if ($admins) {
        while ($admin = mysqli_fetch_assoc($admins)) {
            $result = sendAdminNotification($admin['id'], $type, $message, $document_id, $extra_data, $buttons);
            if ($result['success']) {
                $sent_count++;
            }
            if ($result['telegram_sent']) {
                $telegram_sent = true;
            }
        }
    }
    
    return [
        'success' => $sent_count > 0,
        'sent_count' => $sent_count,
        'telegram_sent' => $telegram_sent
    ];
}

/**
 * Lấy Base URL của hệ thống
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'vietstudocs.com';
    return $protocol . "://" . $host;
}

