<?php
// Note: This requires composer require minishlink/web-push
// If not available, this script will fail gracefully with error logging.

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
    
    // VAPID Auth (Should be in settings or db)
    // For demo/setup purposes, using placeholder. Admin should generate real keys.
    $auth = [
        'VAPID' => [
            'subject' => 'mailto:admin@docshare.com',
            'publicKey' => 'BBsE_KxcQN94F9I4WGHeH9SFTYJSCGpFcmmG3eGE1Zz8o0sP8xvnt6bnPdWCAcLyw90PeuwbW_4JslPIrEbletw',
            'privateKey' => 'rdTI_ipIk47i2zlGruYCtfPymKAmDhDWX8JI7fvKh_0',
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
