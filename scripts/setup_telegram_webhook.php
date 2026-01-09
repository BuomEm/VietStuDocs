<?php
/**
 * Script thiết lập Webhook cho Telegram Bot
 * Chạy file này 1 lần duy nhất để kích hoạt tính năng Callback Button
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/settings.php';

$bot_token = getTelegramBotToken();
$is_localhost = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) || strpos($_SERVER['HTTP_HOST'], 'localhost') !== false;
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];

// Cấu hình URL Webhook
$webhook_url = $base_url . "/api/telegram_webhook.php?token=vsd_secure_callback_2026";

echo "<h2>Cấu hình Telegram Webhook</h2>";

if ($is_localhost) {
    echo "<div style='background:#fff3cd; color:#856404; padding:15px; border-radius:5px; margin-bottom:20px;'>";
    echo "<b>⚠️ Cảnh báo:</b> Bạn đang chạy trên Localhost. Telegram <b>KHÔNG THỂ</b> gửi dữ liệu về máy tính của bạn trừ khi bạn sử dụng công cụ như Ngrok hoặc Cloudflare Tunnel.<br>";
    echo "Nếu bạn muốn tắt Webhook để tránh lỗi, hãy nhấn nút 'Gỡ bỏ Webhook' bên dưới.";
    echo "</div>";
}

echo "Bot Token: " . (empty($bot_token) ? "<b style='color:red'>Chưa cấu hình</b>" : "OK") . "<br>";
echo "Webhook URL dự kiến: <code>$webhook_url</code><br><br>";

if (empty($bot_token)) {
    echo "<b>Lỗi:</b> Vui lòng cấu hình Telegram Bot Token trong trang cài đặt trước.";
    exit;
}

// Xử lý hành động
$action = $_GET['action'] ?? 'set';

if ($action === 'delete') {
    $api_url = "https://api.telegram.org/bot{$bot_token}/deleteWebhook";
    $params = ['drop_pending_updates' => true];
} else {
    $api_url = "https://api.telegram.org/bot{$bot_token}/setWebhook";
    $params = ['url' => $webhook_url];
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($http_code == 200 && isset($result['ok']) && $result['ok'] === true) {
    echo "<b style='color:green'>Thành công!</b> Thao tác đã được thực hiện.<br>";
    echo "Kết quả: " . $result['description'];
} else {
    echo "<b style='color:red'>Thất bại!</b> Không thể thực hiện thao tác.<br>";
    echo "Chi tiết: " . ($result['description'] ?? 'Unknown error');
}

echo "<hr>";
echo "<div style='margin-top:20px; display:flex; gap:10px;'>";
echo "<a href='setup_telegram_webhook.php?action=set' style='background: #007bff; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Thiết lập Webhook</a>";
echo "<a href='setup_telegram_webhook.php?action=delete' style='background: #dc3545; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Gỡ bỏ Webhook</a>";
echo "</div>";

if (!$is_localhost) {
    echo "<br><p><b>Lưu ý quan trọng:</b> Website của bạn phải dùng HTTPS (có SSL) và có thể truy cập từ internet thì Telegram mới gửi được callback về.</p>";
}
