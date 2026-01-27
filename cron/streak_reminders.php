<?php
/**
 * Cron Script for Streak Reminders
 * Run this script every 1-4 hours to notify users who haven't checked in.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/streak.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../push/send_push.php';

// Set timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

$hour = intval(date('H'));
$today = date('Y-m-d');
$today_start = $today . ' 00:00:00';

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

// Get users who haven't claimed today and have an active streak to protect
// Important: Use parentheses to avoid AND/OR precedence bugs
$users = $VSD->get_list("
    SELECT id, username, current_streak, last_streak_date
    FROM users
    WHERE (last_streak_date IS NULL OR last_streak_date <> '$today')
      AND current_streak > 0
");

if (!$users) {
    echo "No users need reminders.\n";
    exit;
}

$sent = 0;
$skipped = 0;

foreach ($users as $user) {
    $user_id = intval($user['id']);
    $current_streak = intval($user['current_streak']);

    // Double-check eligibility using the canonical streak logic
    $info = getUserStreakInfo($user_id);
    if (!$info || !$info['can_claim']) {
        $skipped++;
        continue;
    }

    $reminder = getStreakReminder($type, $current_streak);
    if (!$reminder) {
        $skipped++;
        continue;
    }

    // Avoid duplicate reminders of the same type within the same day
    $notif_type = "streak_reminder_" . $type;
    $already_sent = $VSD->get_row("
        SELECT id
        FROM notifications
        WHERE user_id = $user_id
          AND type = '" . $VSD->escape($notif_type) . "'
          AND created_at >= '$today_start'
        LIMIT 1
    ");

    if ($already_sent) {
        $skipped++;
        continue;
    }

    echo "Notifying {$user['username']} (Streak: {$current_streak})...\n";

    // 1. In-app notification
    $message = $reminder['title'] . ' — ' . $reminder['body'];
    $VSD->insert('notifications', [
        'user_id' => $user_id,
        'type' => $notif_type,
        'ref_id' => null,
        'message' => $message
    ]);

    // 2. Web push notification (best effort)
    sendPushToUser($user_id, [
        'title' => $reminder['title'],
        'body' => $reminder['body'],
        'url' => '/dashboard'
    ]);

    $sent++;
}

echo "Done. Processed " . count($users) . " users. Sent: $sent. Skipped: $skipped.\n";
