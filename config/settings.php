<?php
/**
 * Settings Helper Functions
 * Quản lý tất cả cấu hình hệ thống từ bảng settings
 */

require_once __DIR__ . '/function.php';

/**
 * Lấy giá trị setting
 * @param string $name Tên setting
 * @param mixed $default Giá trị mặc định nếu không tìm thấy
 * @return mixed Giá trị setting hoặc default
 */
function getSetting($name, $default = null) {
    global $VSD;
    $name = $VSD->escape($name);
    $row = $VSD->get_row("SELECT value FROM settings WHERE name='$name' LIMIT 1");
    
    if ($row) {
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
    global $VSD;
    $name = $VSD->escape($name);
    $value = $VSD->escape($value);
    
    // Check if setting exists
    $count = $VSD->num_rows("SELECT id FROM settings WHERE name='$name' LIMIT 1");
    
    if ($count > 0) {
        // Update existing
        return $VSD->update('settings', ['value' => $value, 'category' => $category], "name='$name'");
    } else {
        // Insert new
        return $VSD->insert('settings', ['name' => $name, 'value' => $value, 'category' => $category]);
    }
}

/**
 * Lấy tất cả settings theo category (filter by name pattern)
 * @param string $category Phân loại (site, telegram, notifications)
 * @return array Mảng các settings
 */
function getSettingsByCategory($category) {
    global $VSD;
    
    // Map category to name patterns
    $patterns = [
        'site' => ['site_name', 'site_logo', 'site_description', 'site_keywords', 'site_author'],
        'telegram' => ['telegram_bot_token', 'telegram_chat_id', 'telegram_enabled', 'telegram_admin_ids'],
        'apis' => ['cloudconvert_api_key'],
        'tutor' => [
            'tutor_anti_abuse', 
            'tutor_commission_basic', 'tutor_commission_standard', 'tutor_commission_premium',
            'tutor_sla_basic', 'tutor_sla_standard', 'tutor_sla_premium',
            'tutor_min_basic', 'tutor_max_basic',
            'tutor_min_standard', 'tutor_max_standard',
            'tutor_min_premium', 'tutor_max_premium'
        ],
        'shop' => [
            'shop_bank_name', 'shop_bank_number', 'shop_bank_owner',
            'shop_pkg1_price', 'shop_pkg1_topup', 'shop_pkg1_bonus', 'shop_pkg1_popular',
            'shop_pkg2_price', 'shop_pkg2_topup', 'shop_pkg2_bonus', 'shop_pkg2_popular',
            'shop_pkg3_price', 'shop_pkg3_topup', 'shop_pkg3_bonus', 'shop_pkg3_popular',
            'shop_pkg4_price', 'shop_pkg4_topup', 'shop_pkg4_bonus', 'shop_pkg4_popular',
            'shop_pkg5_price', 'shop_pkg5_topup', 'shop_pkg5_bonus', 'shop_pkg5_popular',
            'reward_points_on_approval'
        ],
        'notifications' => [
            'notify_browser_push_enabled', 'notify_telegram_enabled',
            'notify_new_document_browser', 'notify_new_document_telegram',
            'notify_document_sold_browser', 'notify_document_sold_telegram',
            'notify_system_alert_browser', 'notify_system_alert_telegram',
            'notify_report_browser', 'notify_report_telegram'
        ],
        'streak' => [
            'streak_reward_1_3', 'streak_reward_4', 'streak_reward_5_6', 'streak_reward_7'
        ]
    ];
    
    $settings = [];
    
    if (isset($patterns[$category])) {
        $name_list = "'" . implode("','", array_map(function($name) use ($VSD) {
            return $VSD->escape($name);
        }, $patterns[$category])) . "'";
        
        $rows = $VSD->get_list("SELECT name, value FROM settings WHERE name IN ($name_list) ORDER BY name");
        
        foreach ($rows as $row) {
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
    return getSetting('site_name', 'VietStuDocs');
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

