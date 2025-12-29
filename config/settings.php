<?php
/**
 * Settings Helper Functions
 * Quản lý tất cả cấu hình hệ thống từ bảng settings
 */

require_once __DIR__ . '/db.php';

/**
 * Lấy giá trị setting
 * @param string $name Tên setting
 * @param mixed $default Giá trị mặc định nếu không tìm thấy
 * @return mixed Giá trị setting hoặc default
 */
function getSetting($name, $default = null) {
    global $conn;
    $name = mysqli_real_escape_string($conn, $name);
    $result = mysqli_query($conn, "SELECT value FROM settings WHERE name='$name' LIMIT 1");
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['value'];
    }
    
    return $default;
}

/**
 * Set hoặc update setting
 * @param string $name Tên setting
 * @param mixed $value Giá trị
 * @param string|null $description Mô tả (deprecated, không sử dụng)
 * @param string $category Phân loại (deprecated, không sử dụng)
 * @return bool Thành công hay không
 */
function setSetting($name, $value, $description = null, $category = 'general') {
    global $conn;
    $name = mysqli_real_escape_string($conn, $name);
    $value = mysqli_real_escape_string($conn, $value);
    
    // Check if setting exists
    $check = mysqli_query($conn, "SELECT id FROM settings WHERE name='$name' LIMIT 1");
    
    if (mysqli_num_rows($check) > 0) {
        // Update existing
        $update_query = "UPDATE settings SET value='$value' WHERE name='$name'";
        return mysqli_query($conn, $update_query);
    } else {
        // Insert new
        $insert_query = "INSERT INTO settings (name, value) VALUES ('$name', '$value')";
        return mysqli_query($conn, $insert_query);
    }
}

/**
 * Lấy tất cả settings theo category (filter by name pattern)
 * @param string $category Phân loại (site, telegram, notifications)
 * @return array Mảng các settings
 */
function getSettingsByCategory($category) {
    global $conn;
    $category = mysqli_real_escape_string($conn, $category);
    
    // Map category to name patterns
    $patterns = [
        'site' => ['site_name', 'site_logo', 'site_description', 'site_keywords', 'site_author'],
        'telegram' => ['telegram_bot_token', 'telegram_chat_id', 'telegram_enabled'],
        'notifications' => [
            'notify_browser_push_enabled', 'notify_telegram_enabled',
            'notify_new_document_browser', 'notify_new_document_telegram',
            'notify_document_sold_browser', 'notify_document_sold_telegram',
            'notify_system_alert_browser', 'notify_system_alert_telegram',
            'notify_report_browser', 'notify_report_telegram'
        ]
    ];
    
    $settings = [];
    
    if (isset($patterns[$category])) {
        $name_list = "'" . implode("','", array_map(function($name) use ($conn) {
            return mysqli_real_escape_string($conn, $name);
        }, $patterns[$category])) . "'";
        
        $result = mysqli_query($conn, "SELECT name, value FROM settings WHERE name IN ($name_list) ORDER BY name");
        
        while ($row = mysqli_fetch_assoc($result)) {
            $settings[$row['name']] = [
                'value' => $row['value']
            ];
        }
    }
    
    return $settings;
}

/**
 * Kiểm tra setting có được bật không (on/off)
 * @param string $name Tên setting
 * @return bool True nếu là "on", false nếu không
 */
function isSettingEnabled($name) {
    $value = getSetting($name, 'off');
    return strtolower($value) === 'on';
}

/**
 * Lấy tên website
 * @return string
 */
function getSiteName() {
    return getSetting('site_name', 'DocShare');
}

/**
 * Lấy logo website
 * @return string
 */
function getSiteLogo() {
    return getSetting('site_logo', '');
}

/**
 * Lấy mô tả website
 * @return string
 */
function getSiteDescription() {
    return getSetting('site_description', 'Platform chia sẻ tài liệu học tập');
}

/**
 * Lấy từ khóa website
 * @return string
 */
function getSiteKeywords() {
    return getSetting('site_keywords', '');
}

/**
 * Lấy tác giả website
 * @return string
 */
function getSiteAuthor() {
    return getSetting('site_author', '');
}

/**
 * Lấy Telegram Bot Token (ưu tiên settings, fallback .env)
 * @return string
 */
function getTelegramBotToken() {
    $token = getSetting('telegram_bot_token', '');
    if (empty($token) && isset($_ENV['TELEGRAM_BOT_TOKEN'])) {
        $token = $_ENV['TELEGRAM_BOT_TOKEN'];
    }
    return $token;
}

/**
 * Lấy Telegram Chat ID (ưu tiên settings, fallback .env)
 * @return string
 */
function getTelegramChatId() {
    $chat_id = getSetting('telegram_chat_id', '');
    if (empty($chat_id) && isset($_ENV['TELEGRAM_CHAT_ID'])) {
        $chat_id = $_ENV['TELEGRAM_CHAT_ID'];
    }
    return $chat_id;
}

