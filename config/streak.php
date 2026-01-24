<?php
/**
 * Daily Check-in and Streak Management Functions
 * Tracks user login streaks and handles manual point claims
 */

require_once __DIR__ . '/function.php';
require_once __DIR__ . '/points.php';

/**
 * Handle manual daily check-in
 * Called when user clicks the "Điểm danh" button
 */
function claimDailyStreak($user_id) {
    $user_id = intval($user_id);
    
    // Get current user streak data
    $user = db_get_row("SELECT current_streak, longest_streak, last_streak_date, streak_freezes FROM users WHERE id=$user_id");
    
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    $today = date('Y-m-d');
    $last_streak = $user['last_streak_date'];
    $current_streak = intval($user['current_streak']);
    $longest_streak = intval($user['longest_streak']);
    $freezes = intval($user['streak_freezes']);
    
    // 1. Check if already claimed today
    if ($last_streak === $today) {
        return ['success' => false, 'message' => 'Bạn đã giữ lửa hôm nay rồi!'];
    }
    
    // 2. Calculate new streak
    $new_streak = 1;
    $freeze_used = false;
    
    if ($last_streak) {
        $last_streak_timestamp = strtotime($last_streak);
        $today_timestamp = strtotime($today);
        $days_diff = floor(($today_timestamp - $last_streak_timestamp) / 86400);
        
        if ($days_diff == 1) {
            // Normal consecutive day
            $new_streak = $current_streak + 1;
        } elseif ($days_diff == 2 && $freezes > 0 && $current_streak > 0) {
            // Exactly 1 day missed, and user has freeze, and streak > 0
            // Freeze saves the missed day, current streak continues
            $new_streak = $current_streak + 1;
            $freeze_used = true;
            $freezes--;
            
            db_query("UPDATE users SET streak_freezes = $freezes WHERE id = $user_id");
        } else {
            // Either missed > 1 day, or no freezes, or streak already 0
            $new_streak = 1;
        }
    }
    
    $new_longest = max($new_streak, $longest_streak);
    
    // 3. Award points based on the 7-day cycle (Day 1-7)
    $cycle_day = (($new_streak - 1) % 7) + 1;
    
    require_once __DIR__ . '/settings.php';
    
    $reward_points = 0;
    if ($cycle_day >= 1 && $cycle_day <= 3) {
        $reward_points = intval(getSetting('streak_reward_1_3', 1));
    } elseif ($cycle_day == 4) {
        $reward_points = intval(getSetting('streak_reward_4', 2));
    } elseif ($cycle_day >= 5 && $cycle_day <= 6) {
        $reward_points = intval(getSetting('streak_reward_5_6', 1));
    } elseif ($cycle_day == 7) {
        $reward_points = intval(getSetting('streak_reward_7', 3));
    }
    
    $reason = "Điểm danh ngày thứ $new_streak (Ngày $cycle_day trong chuỗi 7 ngày)";
    if ($freeze_used) $reason .= " [🛡️ Đã dùng Streak Freeze để bảo vệ chuỗi]";

    // 4. Update Database
    db_query("UPDATE users SET 
        current_streak = $new_streak,
        longest_streak = $new_longest,
        last_streak_date = '$today'
        WHERE id = $user_id");
    
    // Use points system to add points
    addPoints($user_id, $reward_points, $reason, null, 'bonus');
    
    $success_msg = "🎉 Chuỗi +1! Bạn đã giữ lửa hôm nay";
    if ($freeze_used) {
        $success_msg = "🛡️ Streak Freeze đã được sử dụng! Chuỗi của bạn vẫn an toàn (Ngày $new_streak). Ai cũng có ngày bận rộn 💙";
    }

    return [
        'success' => true, 
        'message' => $success_msg, 
        'points_earned' => $reward_points,
        'new_streak' => $new_streak,
        'freeze_used' => $freeze_used
    ];
}

/**
 * Get user's streak information
 */
function getUserStreakInfo($user_id) {
    if (!$user_id) return null;
    $user_id = intval($user_id);
    
    $user = db_get_row("SELECT current_streak, longest_streak, last_streak_date, streak_freezes FROM users WHERE id=$user_id");
    
    if (!$user) {
        return [
            'current_streak' => 0,
            'longest_streak' => 0,
            'last_streak_date' => null,
            'streak_freezes' => 0,
            'can_claim' => false,
            'streak_status' => 'inactive',
            'ui_message' => 'Chỉ cần đăng nhập để không bị reset'
        ];
    }
    
    $today = date('Y-m-d');
    $last_streak = $user['last_streak_date'];
    $freezes = intval($user['streak_freezes']);
    $current_streak = intval($user['current_streak']);
    
    $can_claim = ($last_streak !== $today);
    
    // Determine streak status
    $streak_status = 'inactive';
    if (!empty($last_streak)) {
        $days_diff = floor((strtotime($today) - strtotime($last_streak)) / 86400);
        
        if ($days_diff == 0) {
            $streak_status = 'active'; // Claimed today
        } elseif ($days_diff == 1) {
            $streak_status = 'at_risk'; // Claimed yesterday, MUST claim today
        } elseif ($days_diff == 2 && $freezes > 0 && $current_streak > 0) {
            $streak_status = 'protected'; // Streak missed yesterday but protected by freeze
        } else {
            $streak_status = 'broken'; // Streak is broken
        }
    } else {
        $streak_status = 'not_started';
    }
    
    // Custom messages based on guidelines
    $ui_message = "Chỉ cần đăng nhập mỗi ngày để giữ chuỗi";
    if ($streak_status == 'active') {
        $ui_message = "🔥 Chuỗi $current_streak ngày vẫn đang cháy. Bạn đang duy trì thói quen rất tốt!";
    } elseif ($streak_status == 'at_risk') {
        $ui_message = "⚠️ Hôm nay chưa giữ chuỗi. Đừng để lửa tắt nhé!";
    } elseif ($streak_status == 'protected') {
        $ui_message = "🛡️ Chuỗi của bạn đang được bảo toàn. Đăng nhập ngay hôm nay!";
    } elseif ($streak_status == 'broken') {
        if ($current_streak == 0) {
            $ui_message = "Ngày đầu tiên của chuỗi mới đang chờ bạn 💙";
        } else {
            $ui_message = "😢 Chuỗi đã gián đoạn, nhưng không sao, hãy bắt đầu lại nào!";
        }
    }

    return [
        'current_streak' => $current_streak,
        'longest_streak' => intval($user['longest_streak']),
        'last_streak_date' => $last_streak,
        'streak_freezes' => $freezes,
        'can_claim' => $can_claim,
        'streak_status' => $streak_status,
        'ui_message' => $ui_message
    ];
}

/**
 * Automatically update/check streak status on login
 * Checks if the streak is broken and resets it if necessary.
 */
function updateLoginStreak($user_id) {
    $info = getUserStreakInfo($user_id);
    if ($info && $info['streak_status'] === 'broken' && $info['current_streak'] > 0) {
        $user_id = intval($user_id);
        db_query("UPDATE users SET current_streak = 0 WHERE id = $user_id");
    }
}

/**
 * Get streak badge/tier based on current streak
 */
function getStreakBadge($streak) {
    $streak = intval($streak);
    
    if ($streak >= 365) {
        return ['name' => '🥇 Huyền Thoại (365+ Ngày)', 'icon' => 'fa-crown', 'gradient' => 'linear-gradient(135deg, #FFD700 0%, #FFA500 100%)'];
    } elseif ($streak >= 180) {
        return ['name' => '🥈 Bất Bại (180+ Ngày)', 'icon' => 'fa-trophy', 'gradient' => 'linear-gradient(135deg, #E5E4E2 0%, #C0C0C0 100%)'];
    } elseif ($streak >= 90) {
        return ['name' => '🥉 Kiên Trì (90+ Ngày)', 'icon' => 'fa-medal', 'gradient' => 'linear-gradient(135deg, #CD7F32 0%, #8B4513 100%)'];
    } elseif ($streak >= 30) {
        return ['name' => '🥇 Chuỗi 30 ngày – Bạn thật sự nghiêm túc', 'icon' => 'fa-star', 'gradient' => 'linear-gradient(135deg, #9333EA 0%, #7C3AED 100%)'];
    } elseif ($streak >= 7) {
        return ['name' => '🥈 Chuỗi 7 ngày – Thói quen đang hình thành', 'icon' => 'fa-fire', 'gradient' => 'linear-gradient(135deg, #EF4444 0%, #DC2626 100%)'];
    } elseif ($streak >= 3) {
        return ['name' => '🥉 Chuỗi 3 ngày – Khởi động tốt!', 'icon' => 'fa-bolt', 'gradient' => 'linear-gradient(135deg, #F59E0B 0%, #D97706 100%)'];
    } else {
        return ['name' => 'Mới Bắt Đầu', 'icon' => 'fa-seedling', 'gradient' => 'linear-gradient(135deg, #10B981 0%, #059669 100%)'];
    }
}

/**
 * Get Streak Reminder Message (Gen Z / Positive Copy)
 * @param string $type morn, noon, night, lost, freeze_save, freeze_empty
 * @param int $streak current streak count
 * @return array [title, body]
 */
function getStreakReminder($type, $streak = 0) {
    switch ($type) {
        case 'morn':
            return [
                'title' => '🔥 Chuỗi học tập của bạn đang chờ hôm nay',
                'body' => 'Chỉ cần vào hệ thống để giữ chuỗi và giữ lửa hôm nay ☀️'
            ];
        case 'noon':
            return [
                'title' => '⏰ Bạn chưa giữ chuỗi hôm nay',
                'body' => "Chuỗi $streak ngày vẫn còn an toàn... nếu bạn vào ngay. Chỉ 10 giây để không bị gián đoạn! 📖"
            ];
        case 'night':
            return [
                'title' => "💔 Hôm nay sắp hết, chuỗi $streak ngày có nguy cơ bị ngắt",
                'body' => 'Vào nhanh để giữ chuỗi trước khi quá muộn. Đừng để lửa tắt hôm nay! 🌙'
            ];
        case 'lost':
            return [
                'title' => '😢 Chuỗi đã bị gián đoạn, nhưng không sao cả',
                'body' => 'Ngày đầu tiên của chuỗi mới đang chờ bạn. Quay lại là điều quan trọng nhất 💙'
            ];
        case 'protected':
            return [
                'title' => '🛡️ Chuỗi của bạn đã được bảo toàn hôm nay',
                'body' => 'Ai cũng có ngày bận rộn - chuỗi vẫn an toàn nhờ Freeze. Vào ngay để không lãng phí nhé! 🔥'
            ];
        case 'freeze_empty':
            return [
                'title' => '⚠️ Bạn đã dùng hết bảo toàn chuỗi',
                'body' => 'Đăng nhập hôm nay để tránh bị reset chuỗi nhé! Lửa sắp tắt rồi đó 📌'
            ];
    }
    return null;
}
?>
