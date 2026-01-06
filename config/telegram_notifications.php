<?php
/**
 * Telegram Notifications Helper
 * Gửi thông báo đến Telegram Bot
 */

require_once __DIR__ . '/settings.php';

/**
 * Gửi thông báo đến Telegram
 * @param string|array $message Nội dung thông báo (có thể là chuỗi hoặc mảng dữ liệu)
 * @param string|null $notification_type Loại thông báo (new_document, document_sold, system_alert, report)
 * @param array|null $buttons Mảng các nút [['text' => 'Duyệt', 'url' => '...'] hoặc ['text' => 'Duyệt', 'callback_data' => '...']]
 * @return array ['success' => bool, 'message' => string]
 */
function sendTelegramNotification($message, $notification_type = null, $buttons = null) {
    // Kiểm tra Telegram có được bật không
    if (!isSettingEnabled('telegram_enabled')) {
        return ['success' => false, 'message' => 'Telegram notifications are disabled'];
    }
    
    if (!isSettingEnabled('notify_telegram_enabled')) {
        return ['success' => false, 'message' => 'Telegram notifications are globally disabled'];
    }
    
    // Kiểm tra loại notification cụ thể
    if ($notification_type) {
        $type_setting = 'notify_' . $notification_type . '_telegram';
        if (!isSettingEnabled($type_setting)) {
            return ['success' => false, 'message' => "Telegram notifications for $notification_type are disabled"];
        }
    }
    
    // Lấy token và chat ID
    $bot_token = getTelegramBotToken();
    $chat_id = getTelegramChatId();
    
    if (empty($bot_token)) {
        return ['success' => false, 'message' => 'Telegram Bot Token is not configured'];
    }
    
    if (empty($chat_id)) {
        return ['success' => false, 'message' => 'Telegram Chat ID is not configured'];
    }
    
    // Format message
    $formatted_message = formatTelegramMessage($message, $notification_type);
    
    // Gửi đến Telegram Bot API
    $api_url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    
    $data = [
        'chat_id' => $chat_id,
        'text' => $formatted_message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => false
    ];

    // Thêm Inline Keyboard nếu có buttons
    if ($buttons && is_array($buttons) && count($buttons) > 0) {
        $keyboard = [];
        $row = [];
                
        foreach ($buttons as $button) {
            $btn = [];
                        
            // URL Button - mở link trực tiếp
            if (isset($button['url'])) {
                $btn['text'] = $button['text'];
                $btn['url'] = $button['url'];
            }
            // Callback Button - gửi callback_data
            elseif (isset($button['callback_data'])) {
                $btn['text'] = $button['text'];
                $btn['callback_data'] = $button['callback_data'];
            }
                        
            if (!empty($btn)) {
                $row[] = $btn;
                                
                // Mỗi hàng tối đa 2 nút
                if (count($row) >= 2) {
                    $keyboard[] = $row;
                    $row = [];
                }
            }
        }
                
        // Thêm hàng cuối nếu còn
        if (count($row) > 0) {
            $keyboard[] = $row;
        }
                
        $data['reply_markup'] = json_encode([
            'inline_keyboard' => $keyboard
        ]);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        error_log("Telegram notification error: $curl_error");
        return ['success' => false, 'message' => "CURL Error: $curl_error"];
    }
    
    if ($http_code !== 200) {
        error_log("Telegram API error (HTTP $http_code): $response");
        return ['success' => false, 'message' => "Telegram API error: HTTP $http_code"];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['ok']) && $result['ok'] === true) {
        return ['success' => true, 'message' => 'Notification sent successfully'];
    } else {
        $error_msg = $result['description'] ?? 'Unknown error';
        error_log("Telegram API error: $error_msg");
        return ['success' => false, 'message' => "Telegram API error: $error_msg"];
    }
}

/**
 * Format message cho Telegram với emoji và HTML
 * @param string|array $message Nội dung thông báo
 * @param string|null $notification_type Loại thông báo
 * @return string Message đã được format
 */
function formatTelegramMessage($message, $notification_type = null) {
    $emoji_map = [
        'new_document' => '📄',
        'document_sold' => '💰',
        'system_alert' => '⚠️',
        'report' => '🚨'
    ];
    
    $type_labels = [
        'new_document' => 'Tài liệu mới',
        'document_sold' => 'Tài liệu đã bán',
        'system_alert' => 'Cảnh báo hệ thống',
        'report' => 'Báo cáo mới'
    ];
    
    $emoji = $emoji_map[$notification_type] ?? '🔔';
    $label = $type_labels[$notification_type] ?? 'Thông báo';
    
    $site_name = getSiteName();
    $timestamp = date('d/m/Y H:i:s');
    
    $formatted = "<b>{$emoji} {$label} - {$site_name}</b>\n\n";
    
    if (is_array($message)) {
        // Nếu là mảng, format theo key-value
        if (isset($message['title'])) {
            $formatted .= "<b>" . htmlspecialchars($message['title']) . "</b>\n\n";
        }
        
        foreach ($message as $key => $value) {
            if (in_array($key, ['title', 'footer', 'url'])) continue;
            
            // Format key: capitalize and replace underscrore
            $display_key = ucwords(str_replace('_', ' ', $key));
            $formatted .= "• <b>{$display_key}:</b> " . htmlspecialchars($value) . "\n";
        }
        
        if (isset($message['footer'])) {
            $formatted .= "\n<i>" . htmlspecialchars($message['footer']) . "</i>";
        }
        
        if (isset($message['url'])) {
            // Đảm bảo URL bắt đầu bằng http hoặc /
            $url = $message['url'];
            if (strpos($url, 'http') !== 0) {
                // Lấy base URL từ settings hoặc server
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com';
                $url = $protocol . "://" . $host . $url;
            }
            $formatted .= "\n\n🔗 <a href='{$url}'>Xem chi tiết</a>";
        }
    } else {
        // Nếu là chuỗi
        $formatted .= htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    }
    
    $formatted .= "\n\n<code>⏰ {$timestamp}</code>";
    
    return $formatted;
}

/**
 * Test kết nối Telegram
 * @return array ['success' => bool, 'message' => string]
 */
function testTelegramConnection() {
    $bot_token = getTelegramBotToken();
    $chat_id = getTelegramChatId();
    
    if (empty($bot_token)) {
        return ['success' => false, 'message' => 'Telegram Bot Token chưa được cấu hình'];
    }
    
    if (empty($chat_id)) {
        return ['success' => false, 'message' => 'Telegram Chat ID chưa được cấu hình'];
    }
    
    // Test bằng cách gửi message test
    $test_message = "🧪 <b>Test Notification</b>\n\nĐây là thông báo test từ hệ thống " . getSiteName() . ".\n\nNếu bạn nhận được tin nhắn này, cấu hình Telegram đã hoạt động đúng!";
    
    return sendTelegramNotification($test_message, null);
}

