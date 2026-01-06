<?php
/**
 * Script thiết lập Webhook cho Telegram Bot
 * Chạy file này 1 lần duy nhất để kích hoạt tính năng Callback Button
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/settings.php';

$bot_token = getTelegramBotToken();
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];

// Cấu hình URL Webhook với token bảo mật đã định nghĩa trong api/telegram_webhook.php
$webhook_url = $base_url . "/api/telegram_webhook.php?token=vsd_secure_callback_2026";

echo "<h2>Cấu hình Telegram Webhook</h2>";
echo "Bot Token: " . (empty($bot_token) ? "<b style='color:red'>Chưa cấu hình</b>" : "OK") . "<br>";
echo "Webhook URL: <code>$webhook_url</code><br><br>";

if (empty($bot_token)) {
    echo "<b>Lỗi:</b> Vui lòng cấu hình Telegram Bot Token trong trang cài đặt trước.";
    exit;
}

// Gọi API setWebhook của Telegram
$api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook";
$data = ['url' => $webhook_url];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($http_code == 200 && isset($result['ok']) && $result['ok'] === true) {
    echo "<b style='color:green'>Thành công!</b> Webhook đã được thiết lập.<br>";
    echo "Kết quả: " . $result['description'];
} else {
    echo "<b style='color:red'>Thất bại!</b> Không thể thiết lập Webhook.<br>";
    echo "Chi tiết: " . ($result['description'] ?? 'Unknown error');
}

echo "<br><br><p><b>Lưu ý quan trọng:</b> Website của bạn phải dùng HTTPS (có SSL) và có thể truy cập từ internet thì Telegram mới gửi được callback về.</p>";
