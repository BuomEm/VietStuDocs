<?php
/**
 * Web-based Push Notification Test
 * Access via browser: http://localhost/test_push_web.php
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/function.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/push/send_push.php';

// Must be logged in
redirectIfNotLoggedIn();
$user_id = getCurrentUserId();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Push Notification Test</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200">
    <div class="container mx-auto p-8">
        <div class="card bg-base-100 shadow-xl max-w-2xl mx-auto">
            <div class="card-body">
                <h2 class="card-title text-2xl">🔔 Push Notification Test</h2>
                
                <div class="divider"></div>
                
                <div class="space-y-4">
                    <div class="alert alert-info">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <div>
                            <div class="font-bold">Test Push Notification System</div>
                            <div class="text-sm">Click the button below to send yourself a test notification</div>
                        </div>
                    </div>
                    
                    <?php
                    if (isset($_POST['send_test'])) {
                        echo '<div class="alert alert-info"><span class="loading loading-spinner"></span> Sending...</div>';
                        
                        $result = sendPushToUser($user_id, [
                            'title' => '🎉 Test Notification',
                            'body' => 'Push notification system is working perfectly!',
                            'url' => '/dashboard.php'
                        ]);
                        
                        if ($result) {
                            echo '<div class="alert alert-success">✅ Push notification sent successfully!</div>';
                        } else {
                            echo '<div class="alert alert-warning">⚠️ No active subscriptions found. Please enable notifications first.</div>';
                        }
                    }
                    ?>
                    
                    <form method="POST">
                        <button type="submit" name="send_test" class="btn btn-primary btn-block">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                            Send Test Notification
                        </button>
                    </form>
                    
                    <div class="divider">System Info</div>
                    
                    <div class="stats stats-vertical lg:stats-horizontal shadow w-full">
                        <div class="stat">
                            <div class="stat-title">PHP Version</div>
                            <div class="stat-value text-sm"><?= PHP_VERSION ?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-title">OpenSSL</div>
                            <div class="stat-value text-sm"><?= OPENSSL_VERSION_TEXT ?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-title">SAPI</div>
                            <div class="stat-value text-sm"><?= php_sapi_name() ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="card-actions justify-end mt-4">
                    <a href="/dashboard.php" class="btn btn-ghost">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
