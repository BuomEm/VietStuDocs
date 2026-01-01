<?php
// Kiểm tra nếu là Windows thì set đường dẫn openssl.cnf
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $path = "D:\laragon\bin\php\php-8.3.28-Win32-vs16-x64\extras\ssl\openssl.cnf"; // Thay 'php-8.x.x' bằng phiên bản bạn đang dùng
    putenv("OPENSSL_CONF=$path");
}
require_once __DIR__ . '/../config/function.php';
global $VSD;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // If no vendor folder, we can't send push notifications.
    // Logging this as an error for the developer.
    error_log("Minishlink\WebPush not found. Please run 'composer require minishlink/web-push'");
}

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Send Web Push notifications to a specific user
 * @param int $user_id
 * @param array $payload ['title' => '...', 'body' => '...', 'url' => '...']
 */
function sendPushToUser($user_id, $payload = []) {
    global $VSD;

    if (!class_exists('Minishlink\WebPush\WebPush')) {
        return false;
    }

    $subs_res = $VSD->query("SELECT * FROM push_subscriptions WHERE user_id = " . intval($user_id));
    
    // VAPID Auth
    $auth = [
        'VAPID' => [
            'subject' => $_ENV['VAPID_SUBJECT'] ?? 'mailto:admin@docshare.com',
            'publicKey' => $_ENV['VAPID_PUBLIC_KEY'] ?? '',
            'privateKey' => $_ENV['VAPID_PRIVATE_KEY'] ?? '',
        ],
    ];

    $webPush = new WebPush($auth);
    $has_subs = false;

    while ($s = mysqli_fetch_assoc($subs_res)) {
        $has_subs = true;
        $sub_data = json_decode($s['subscription'], true);
        if (!$sub_data) continue;

        $subscription = Subscription::create($sub_data);
        $webPush->queueNotification(
            $subscription,
            json_encode($payload)
        );
    }

    if (!$has_subs) {
        return false;
    }

    foreach ($webPush->flush() as $report) {
        $endpoint = $report->getEndpoint();
        if ($report->isSuccess()) {
            // Success
            $VSD->query("UPDATE notifications SET is_pushed = 1 WHERE user_id = " . intval($user_id) . " AND is_pushed = 0");
        } else {
            // Failure - subscription might be expired
            if ($report->isSubscriptionExpired()) {
                $VSD->query("DELETE FROM push_subscriptions WHERE user_id = " . intval($user_id) . " AND subscription LIKE '%$endpoint%'");
            }
            error_log("Push failed: {$report->getReason()}");
        }
    }

    return true;
}
?>
