<?php
require_once __DIR__ . '/function.php';

function isPremium($user_id) {
    $user_id = intval($user_id);
    
    $row = db_get_row("
        SELECT id FROM premium 
        WHERE user_id = $user_id 
        AND is_active = TRUE 
        AND end_date > NOW()
        LIMIT 1
    ");
    
    return $row ? true : false;
}

function getPremiumInfo($user_id) {
    $user_id = intval($user_id);
    
    return db_get_row("
        SELECT * FROM premium 
        WHERE user_id = $user_id 
        AND is_active = TRUE 
        AND end_date > NOW()
        LIMIT 1
    ");
}

// Hàm lấy số tài liệu xác minh trong chu kỳ hiện tại
function getVerifiedDocumentsCount($user_id) {
    $user_id = intval($user_id);
    
    // Lấy Premium info hiện tại
    $premium_info = getPremiumInfo($user_id);
    
    // Nếu đang có Premium -> tính tài liệu từ khi Premium bắt đầu
    if($premium_info) {
        $start_date = $premium_info['start_date'];
        $row = db_get_row("
            SELECT COUNT(*) as count FROM documents d
            JOIN document_verification dv ON d.id = dv.document_id
            WHERE d.user_id = $user_id 
            AND dv.is_verified = TRUE
            AND d.created_at >= '$start_date'
        ");
    } else {
        // Nếu hết Premium -> tính tài liệu từ sau khi Premium cuối cùng hết hạn
        $last_premium = db_get_row("
            SELECT end_date FROM premium 
            WHERE user_id = $user_id 
            ORDER BY end_date DESC 
            LIMIT 1
        ");
        
        if($last_premium) {
            // Premium đã hết -> reset count từ lần cuối hết hạn
            $reset_date = $last_premium['end_date'];
            $row = db_get_row("
                SELECT COUNT(*) as count FROM documents d
                JOIN document_verification dv ON d.id = dv.document_id
                WHERE d.user_id = $user_id 
                AND dv.is_verified = TRUE
                AND d.created_at > '$reset_date'
            ");
        } else {
            // Chưa bao giờ có Premium -> tính từ đầu
            $row = db_get_row("
                SELECT COUNT(*) as count FROM documents d
                JOIN document_verification dv ON d.id = dv.document_id
                WHERE d.user_id = $user_id AND dv.is_verified = TRUE
            ");
        }
    }
    
    return $row['count'] ?? 0;
}

function activateFreeTrial($user_id) {
    $user_id = intval($user_id);
    
    $exists = db_num_rows("
        SELECT id FROM premium 
        WHERE user_id = $user_id AND plan_type = 'free_trial'
    ") > 0;
    
    if(!$exists) {
        $start_date = date('Y-m-d H:i:s');
        $end_date = date('Y-m-d H:i:s', strtotime('+7 days'));
        return db_query("
            INSERT INTO premium (user_id, plan_type, start_date, end_date, is_active) 
            VALUES ($user_id, 'free_trial', '$start_date', '$end_date', 1)
        ");
    }
    
    return false;
}

function activateMonthlyPremium($user_id) {
    $user_id = intval($user_id);
    
    $start_date = date('Y-m-d H:i:s');
    $end_date = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    return db_query("
        INSERT INTO premium (user_id, plan_type, start_date, end_date, is_active) 
        VALUES ($user_id, 'monthly', '$start_date', '$end_date', 1)
    ");
}
?>