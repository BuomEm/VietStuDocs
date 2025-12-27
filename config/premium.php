<?php
require_once __DIR__ . '/db.php';

function isPremium($user_id) {
    global $conn;
    
    $result = mysqli_query($conn, "
        SELECT * FROM premium 
        WHERE user_id = $user_id 
        AND is_active = TRUE 
        AND end_date > NOW()
        LIMIT 1
    ");
    
    return mysqli_num_rows($result) > 0;
}

function getPremiumInfo($user_id) {
    global $conn;
    
    $result = mysqli_query($conn, "
        SELECT * FROM premium 
        WHERE user_id = $user_id 
        AND is_active = TRUE 
        AND end_date > NOW()
        LIMIT 1
    ");
    
    return mysqli_fetch_assoc($result);
}

// Hàm lấy số tài liệu xác minh trong chu kỳ hiện tại
function getVerifiedDocumentsCount($user_id) {
    global $conn;
    
    // Lấy Premium info hiện tại
    $premium_info = getPremiumInfo($user_id);
    
    // Nếu đang có Premium -> tính tài liệu từ khi Premium bắt đầu
    if($premium_info) {
        $start_date = $premium_info['start_date'];
        $result = mysqli_query($conn, "
            SELECT COUNT(*) as count FROM documents d
            JOIN document_verification dv ON d.id = dv.document_id
            WHERE d.user_id = $user_id 
            AND dv.is_verified = TRUE
            AND d.created_at >= '$start_date'
        ");
    } else {
        // Nếu hết Premium -> tính tài liệu từ sau khi Premium cuối cùng hết hạn
        $last_premium = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT end_date FROM premium 
            WHERE user_id = $user_id 
            ORDER BY end_date DESC 
            LIMIT 1
        "));
        
        if($last_premium) {
            // Premium đã hết -> reset count từ lần cuối hết hạn
            $reset_date = $last_premium['end_date'];
            $result = mysqli_query($conn, "
                SELECT COUNT(*) as count FROM documents d
                JOIN document_verification dv ON d.id = dv.document_id
                WHERE d.user_id = $user_id 
                AND dv.is_verified = TRUE
                AND d.created_at > '$reset_date'
            ");
        } else {
            // Chưa bao giờ có Premium -> tính từ đầu
            $result = mysqli_query($conn, "
                SELECT COUNT(*) as count FROM documents d
                JOIN document_verification dv ON d.id = dv.document_id
                WHERE d.user_id = $user_id AND dv.is_verified = TRUE
            ");
        }
    }
    
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}

function activateFreeTrial($user_id) {
    global $conn;
    
    $check = mysqli_query($conn, "
        SELECT id FROM premium 
        WHERE user_id = $user_id AND plan_type = 'free_trial'
    ");
    
    if(mysqli_num_rows($check) == 0) {
        $start_date = date('Y-m-d H:i:s');
        $end_date = date('Y-m-d H:i:s', strtotime('+7 days'));
        mysqli_query($conn, "
            INSERT INTO premium (user_id, plan_type, start_date, end_date, is_active) 
            VALUES ($user_id, 'free_trial', '$start_date', '$end_date', 1)
        ");
        return true;
    }
    
    return false;
}

function activateMonthlyPremium($user_id) {
    global $conn;
    
    $start_date = date('Y-m-d H:i:s');
    $end_date = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    return mysqli_query($conn, "
        INSERT INTO premium (user_id, plan_type, start_date, end_date, is_active) 
        VALUES ($user_id, 'monthly', '$start_date', '$end_date', 1)
    ");
}
?>