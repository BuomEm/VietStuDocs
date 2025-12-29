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
 * @param string $type Loại thông báo (new_document, document_sold, system_alert, report)
 * @param string $message Nội dung thông báo
 * @param int|null $document_id ID của tài liệu (nếu có)
 * @return array ['success' => bool, 'notification_id' => int|null, 'telegram_sent' => bool]
 */
function sendAdminNotification($admin_id, $type, $message, $document_id = null) {
    global $conn;
    
    $admin_id = intval($admin_id);
    $type = mysqli_real_escape_string($conn, $type);
    $message = mysqli_real_escape_string($conn, $message);
    $document_id = $document_id ? intval($document_id) : 'NULL';
    
    // Validate notification type
    $valid_types = ['new_document', 'document_sold', 'system_alert', 'report'];
    if (!in_array($type, $valid_types)) {
        error_log("Invalid notification type: $type");
        return ['success' => false, 'notification_id' => null, 'telegram_sent' => false];
    }
    
    // Insert vào database
    $insert_query = "INSERT INTO admin_notifications (admin_id, notification_type, document_id, message, created_at) 
                     VALUES ($admin_id, '$type', $document_id, '$message', NOW())";
    
    if (!mysqli_query($conn, $insert_query)) {
        error_log("Failed to insert notification: " . mysqli_error($conn));
        return ['success' => false, 'notification_id' => null, 'telegram_sent' => false];
    }
    
    $notification_id = mysqli_insert_id($conn);
    
    // Check settings và gửi notifications
    $telegram_sent = false;
    
    // Check Browser Push
    $browser_enabled = isSettingEnabled('notify_browser_push_enabled') && 
                       isSettingEnabled('notify_' . $type . '_browser');isSettingEnabled('notify_' . $type . '_browser');
    
    // Check Telegram
    $telegram_enabled = isSettingEnabled('telegram_enabled') && 
                        isSettingEnabled('notify_telegram_enabled') && 
                        isSettingEnabled('notify_' . $type . '_telegram');
    
    // Gửi Telegram (async, không block)
    if ($telegram_enabled) {
        // Gửi trong background để không block request
        $telegram_result = sendTelegramNotification($message, $type);
        $telegram_sent = $telegram_result['success'] ?? false;
        
        if (!$telegram_sent) {
            error_log("Telegram notification failed: " . ($telegram_result['message'] ?? 'Unknown error'));
        }
    }
    
    // Browser Push sẽ được xử lý bởi client-side JavaScript khi admin mở trang notifications
    // Không cần gửi từ server-side
    
    return [
        'success' => true,
        'notification_id' => $notification_id,
        'telegram_sent' => $telegram_sent
    ];
}

/**
 * Gửi thông báo cho tất cả admin
 * @param string $type Loại thông báo
 * @param string $message Nội dung thông báo
 * @param int|null $document_id ID của tài liệu (nếu có)
 * @return array ['success' => bool, 'sent_count' => int, 'telegram_sent' => bool]
 */
function sendNotificationToAllAdmins($type, $message, $document_id = null) {
    global $conn;
    
    $admins = mysqli_query($conn, "SELECT id FROM users WHERE role='admin'");
    $sent_count = 0;
    $telegram_sent = false;
    
    if ($admins) {
        while ($admin = mysqli_fetch_assoc($admins)) {
            $result = sendAdminNotification($admin['id'], $type, $message, $document_id);
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

