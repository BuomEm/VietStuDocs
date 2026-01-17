<?php
/**
 * Cron Script for Streak Reminders
 * Run this script every 1-4 hours to notify users who haven't checked in.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/streak.php';
require_once __DIR__ . '/../config/telegram_notifications.php';
require_once __DIR__ . '/../config/settings.php';

// Set timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

$hour = intval(date('H'));
$today = date('Y-m-d');

// Determine reminder type based on time
$type = '';
if ($hour >= 7 && $hour < 12) {
    $type = 'morn';
} elseif ($hour >= 13 && $hour < 18) {
    $type = 'noon';
} elseif ($hour >= 19 && $hour <= 23) {
    $type = 'night';
}

if (!$type) {
    echo "Skipping... Not a reminder window.\n";
    exit;
}

echo "Running streak reminders for type: $type (Hour: $hour)\n";

// Get users who:
// 1. Are active
// 2. Haven't claimed today
// 3. (Optional) Have some streak already
$users = $VSD->get_list("SELECT id, username, current_streak, telegram_id FROM users 
                         WHERE last_streak_date != '$today' OR last_streak_date IS NULL
                         AND current_streak > 0");

if (!$users) {
    echo "No users need reminders.\n";
    exit;
}

foreach ($users as $user) {
    $reminder = getStreakReminder($type, $user['current_streak']);
    
    if (!$reminder) continue;

    echo "Notifying {$user['username']} (Streak: {$user['current_streak']})...\n";
    
    // 1. Logic for Web Push (if implemented in future)
    // sendWebPush($user['id'], $reminder['title'], $reminder['body']);

    // 2. Logic for Telegram (if user linked their account)
    // For this to work, users table needs a 'telegram_id' column
    if (!empty($user['telegram_id'])) {
        $msg = "<b>{$reminder['title']}</b>\n\n{$reminder['body']}";
        // Using a modified sendTelegramNotification or direct API call
        // sendToUserTelegram($user['telegram_id'], $msg);
    }
    
    // 3. Log notification for debugging
    // error_log("Streak Reminder ($type) sent to user {$user['id']}");
}

echo "Done. Processed " . count($users) . " users.\n";
