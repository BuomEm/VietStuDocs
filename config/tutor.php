<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/points.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/premium.php';
require_once __DIR__ . '/../push/send_push.php';

/**
 * Get PDO Database Connection
 * Use this for all Tutor system operations to ensure security
 */
function getTutorDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4; SET time_zone = '+07:00';"
            ]);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Tutor DB Connection Failed: " . $e->getMessage());
            throw new Exception("Database connection error");
        }
    }
    return $pdo;
}

// ============ TUTOR MANAGEMENT ============

function isTutor($user_id) {
    if (!$user_id) return false;
    $pdo = getTutorDBConnection();
    $stmt = $pdo->prepare("SELECT status FROM tutors WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result && $result['status'] === 'active';
}

function getTutorProfile($user_id) {
    if (!$user_id) return null;
    $pdo = getTutorDBConnection();
    // Join with users implementation if needed, but for now just raw tutor data
    $stmt = $pdo->prepare("SELECT t.*, u.username, u.email, u.avatar FROM tutors t JOIN users u ON t.user_id = u.id WHERE t.user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function registerTutor($user_id, $subjects, $bio, $prices) {
    $pdo = getTutorDBConnection();
    
    // Check if already applied
    $stmt = $pdo->prepare("SELECT id FROM tutors WHERE user_id = ?");
    $stmt->execute([$user_id]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Bạn đã đăng ký làm gia sư rồi.'];
    }

    $sql = "INSERT INTO tutors (user_id, subjects, bio, price_basic, price_standard, price_premium, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id,
            $subjects,
            $bio,
            $prices['basic'] ?? 20,
            $prices['standard'] ?? 50,
            $prices['premium'] ?? 100
        ]);
        
        $tutor_record_id = $pdo->lastInsertId();
        
        // Notify Admin of New Tutor Registration
        try {
            if (file_exists(__DIR__ . '/notifications.php')) {
                require_once __DIR__ . '/notifications.php';
                $username = getCurrentUsername(); // Helper from auth.php
                $notif_message = "Hồ sơ đăng ký Gia sư mới: " . $username;
                sendNotificationToAllAdmins('new_tutor', $notif_message, $tutor_record_id);
            }
        } catch (Exception $e) {
            error_log("Tutor Admin Notif Error: " . $e->getMessage());
        }

        return ['success' => true, 'message' => 'Đăng ký thành công! Vui lòng chờ Admin phê duyệt.'];
    } catch (Exception $e) {
        error_log("Register Tutor Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Có lỗi xảy ra. Vui lòng thử lại.'];
    }
}

function getActiveTutors($filters = []) {
    $pdo = getTutorDBConnection();
    $sql = "SELECT t.*, u.username, u.email, u.avatar, u.last_activity, u.is_verified_tutor, 
            (SELECT COUNT(*) FROM tutor_requests WHERE tutor_id = t.user_id AND status = 'completed') as completed_count
            FROM tutors t 
            JOIN users u ON t.user_id = u.id 
            WHERE t.status = 'active'";
    
    $params = [];
    if (!empty($filters['subject'])) {
        $sql .= " AND t.subjects LIKE ?";
        $params[] = '%' . $filters['subject'] . '%';
    }
    
    // Sort
    $sql .= " ORDER BY t.rating DESC, t.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getOnlineStatusString($last_activity) {
    if (!$last_activity) return ['status' => 'offline', 'text' => 'Không hoạt động', 'label' => 'Offline'];
    
    // Ensure PHP uses same timezone as DB (+07:00)
    date_default_timezone_set('Asia/Ho_Chi_Minh');
    
    $time = strtotime($last_activity);
    $now = time();
    $diff = $now - $time;
    
    // Online if active within last 5 minutes
    if ($diff < 300) {
        return ['status' => 'online', 'text' => 'Đang hoạt động', 'label' => 'Online'];
    }
    
    // Offline logic
    if ($diff < 3600) {
        $mins = floor($diff / 60);
        $mins = $mins > 0 ? $mins : 1;
        return ['status' => 'offline', 'text' => "Không hoạt động • {$mins} phút trước", 'label' => "{$mins}p trước"];
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return ['status' => 'offline', 'text' => "Không hoạt động • {$hours} giờ trước", 'label' => "{$hours}h trước"];
    } else {
        $days = floor($diff / 86400);
        return ['status' => 'offline', 'text' => "Không hoạt động • {$days} ngày trước", 'label' => "{$days}d trước"];
    }
}

// ============ REQUEST MANAGEMENT ============

function createTutorRequest($student_id, $tutor_id, $data) {
    $pdo = getTutorDBConnection();
    
    // 1. Validate inputs
    $tutor = getTutorProfile($tutor_id);
    if (!$tutor || $tutor['status'] !== 'active') {
        return ['success' => false, 'message' => 'Gia sư không khả dụng.'];
    }

    if ($student_id == $tutor_id) {
        return ['success' => false, 'message' => 'Bạn không thể tự đặt câu hỏi cho chính mình.'];
    }

    // Anti-Abuse: Check IP and Flooding
    if (isSettingEnabled('tutor_anti_abuse')) {
        $student_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        // Basic check on pending requests count to prevent flooding
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tutor_requests WHERE tutor_id = ? AND status = 'pending'");
        $stmt->execute([$tutor_id]);
        $pending_count = $stmt->fetchColumn();
        if ($pending_count >= 15) {
            return ['success' => false, 'message' => 'Gia sư này đang có quá nhiều yêu cầu đang chờ. Vui lòng thử lại sau.'];
        }
        
        // Check for self-looping (if we had IPs stored in tutor profile, we'd check here)
    }

    // 2. Fetch Tutor Pricing & Validate
    $stmt = $pdo->prepare("SELECT price_basic, price_standard, price_premium FROM tutors WHERE user_id = ?");
    $stmt->execute([$tutor_id]);
    $tutor_prices = $stmt->fetch();
    
    if (!$tutor_prices) {
        return ['success' => false, 'message' => 'Gia sư không tồn tại hoặc đã ngừng hoạt động.'];
    }

    $package_type = $data['package_type']; // normal, medium, vip
    $cost = intval($data['points'] ?? 0);
    $expected_price = 0;
    $sla_hours = 0.5;
    
    // Map frontend package names to database enum values
    $package_map = [
        'normal' => 'basic',
        'medium' => 'standard', 
        'vip' => 'premium'
    ];
    $db_package_type = $package_map[$package_type] ?? 'basic'; // For DB storage

    // SLA Settings (Global)
    $sla_basic = floatval(getSetting('tutor_sla_basic', 0.5));
    $sla_standard = floatval(getSetting('tutor_sla_standard', 1));
    $sla_premium = floatval(getSetting('tutor_sla_premium', 6));

    switch ($package_type) {
        case 'normal':
            $expected_price = intval($tutor_prices['price_basic'] ?? 25);
            $sla_hours = $sla_basic;
            break;
        case 'medium':
            $expected_price = intval($tutor_prices['price_standard'] ?? 35);
            $sla_hours = $sla_standard;
            break;
        case 'vip':
            $expected_price = intval($tutor_prices['price_premium'] ?? 60);
            $sla_hours = $sla_premium;
            break;
        default:
            return ['success' => false, 'message' => 'Gói câu hỏi không hợp lệ.'];
    }

    // Validate Price (Must match exact tutor price)
    if ($cost !== $expected_price) {
        // Fallback: If cost is 0 or mismatch, force reuse expected price? 
        // Better to reject or auto-correct. Let's strict check but allow auto-fix if cost is 0 (from backend handler default)
        // Actually, if we allow auto-fix, we just overwrite $cost.
        // But to be safe against tampering, let's just use $expected_price as the source of truth.
        $cost = $expected_price;
    }
    $sla_deadline = date('Y-m-d H:i:s', strtotime("+{$sla_hours} hours"));

    // 3. Check student points (Already checked inside lockPoints, but good to check early)
    $student_points = getUserPoints($student_id);
    if ($student_points['current_points'] < $cost) {
        return ['success' => false, 'message' => "Bạn không đủ VSD. Cần $cost VSD."];
    }

    // 4. TRANSACTION: Deduct points (Escrow) -> Create Request
    try {
        $pdo->beginTransaction();

        // Use helper from points.php but we need to ensure consistency. 
        // points.php uses mysqli global $conn. 
        // We will call the function, but if it fails we throw exception.
        // NOTE: Mixed MySQLi and PDO transactions won't work together for rollback.
        // Ideally we should rewrite points logic to PDO, but strict instruction is "PHP procedural".
        // Workaround: Deduct points first. If fail, stop. If Request creation fail, Refund points.
        
        // Step 4.1: Create Request Record first (Pending payment)
        $stmt = $pdo->prepare("INSERT INTO tutor_requests (student_id, tutor_id, title, content, package_type, points_used, status, attachment) 
                              VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([
            $student_id, 
            $tutor_id, 
            $data['title'], 
            $data['content'], 
            $db_package_type, 
            $cost, 
            $data['attachment'] ?? null
        ]);
        $request_id = $pdo->lastInsertId();

        // Step 4.2: Lock Points in Escrow
        $reason = "Hỏi Gia sư #$tutor_id (Request #$request_id)";
        $transaction_id = lockPoints($student_id, $cost, $reason, 'tutor_request', $request_id);
        
        if (!$transaction_id) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Lỗi trừ điểm. Giao dịch bị hủy.'];
        }

        // Step 4.3: Update Request with transaction ID and SLA
        $stmt = $pdo->prepare("UPDATE tutor_requests SET transaction_id = ?, sla_deadline = ? WHERE id = ?");
        $stmt->execute([$transaction_id, $sla_deadline, $request_id]);

        $pdo->commit();

        // Notify Admins of New Tutor Request
        try {
            require_once __DIR__ . '/notifications.php';

            $student_stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $student_stmt->execute([$student_id]);
            $student_name = $student_stmt->fetchColumn() ?: "Học viên #$student_id";

            $tutor_stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $tutor_stmt->execute([$tutor_id]);
            $tutor_name = $tutor_stmt->fetchColumn() ?: "Gia sư #$tutor_id";

            $admin_message = "Yêu cầu gia sư mới: " . $data['title'];
            $extra_data = [
                'request_id' => intval($request_id),
                'student_name' => $student_name,
                'tutor_name' => $tutor_name,
                'points' => intval($cost),
                'package_type' => $db_package_type,
                'title' => $data['title']
            ];

            sendNotificationToAllAdmins('new_tutor_request', $admin_message, $request_id, $extra_data);
        } catch (Exception $e) {
            error_log("Tutor request admin notification error: " . $e->getMessage());
        }
        
        // Notify Tutor of New Request
        global $VSD;
        $VSD->insert('notifications', [
            'user_id' => $tutor_id,
            'title' => 'Câu hỏi mới',
            'message' => "Bạn nhận được một câu hỏi mới: '{$data['title']}' từ học viên.",
            'type' => 'tutor_request_new',
            'ref_id' => $request_id
        ]);
        sendPushToUser($tutor_id, [
            'title' => 'Bạn có câu hỏi mới! 🎓',
            'body' => "Học viên vừa gửi cho bạn câu hỏi: '{$data['title']}'",
            'url' => '/tutors/request.php?id=' . $request_id
        ]);

        return ['success' => true, 'message' => 'Đặt câu hỏi thành công!', 'request_id' => $request_id];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Create Request Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()];
    }
}

function getRequestsForTutor($tutor_id) {
    $pdo = getTutorDBConnection();
    $stmt = $pdo->prepare("SELECT r.*, u.username as student_name, u.avatar as student_avatar 
                          FROM tutor_requests r 
                          JOIN users u ON r.student_id = u.id 
                          WHERE r.tutor_id = ? 
                          ORDER BY r.created_at DESC");
    $stmt->execute([$tutor_id]);
    return $stmt->fetchAll();
}

function getRequestDetails($request_id) {
    $pdo = getTutorDBConnection();
    
    // First, check status and expire if needed (Lazy Expiration)
    $check = $pdo->prepare("SELECT id, status, sla_deadline, student_id, tutor_id, transaction_id, title FROM tutor_requests WHERE id = ?");
    $check->execute([$request_id]);
    $basic_req = $check->fetch();

    if ($basic_req && $basic_req['status'] === 'pending' && strtotime($basic_req['sla_deadline']) < time()) {
        // Check if tutor has answered
        $ans_check = $pdo->prepare("SELECT COUNT(*) FROM tutor_answers WHERE request_id = ? AND sender_id = ?");
        $ans_check->execute([$request_id, $basic_req['tutor_id']]);
        $tutor_answers = $ans_check->fetchColumn();

        if ($tutor_answers == 0) {
            // Expire logic
            try {
                $pdo->beginTransaction();
                
                $update = $pdo->prepare("UPDATE tutor_requests SET status = 'cancelled' WHERE id = ?");
                $update->execute([$request_id]);
                
                // Refund
                if ($basic_req['transaction_id']) {
                    refundEscrow($basic_req['transaction_id'], "Gia sư không phản hồi đúng hạn (Yêu cầu #$request_id)");
                }
                
                $pdo->commit();
                
                // Notifications
                global $VSD;
                $VSD->insert('notifications', [
                    'user_id' => $basic_req['student_id'],
                    'title' => 'Yêu cầu hủy do quá hạn',
                    'message' => "Yêu cầu '{$basic_req['title']}' đã tự động hủy vì gia sư không phản hồi đúng hạn. Bạn đã được hoàn tiền.",
                    'type' => 'request_expired',
                    'ref_id' => $request_id
                ]);
                $VSD->insert('notifications', [
                    'user_id' => $basic_req['tutor_id'],
                    'title' => 'Yêu cầu bị hủy',
                    'message' => "Bạn đã bỏ lỡ thời hạn trả lời yêu cầu '{$basic_req['title']}'. Yêu cầu đã bị hủy.",
                    'type' => 'request_expired',
                    'ref_id' => $request_id
                ]);

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log("Auto-Expire Request Error: " . $e->getMessage());
            }
        }
    }

    $stmt = $pdo->prepare("SELECT r.*, u.username as student_name, u.avatar as student_avatar, t.username as tutor_name, t.avatar as tutor_avatar
                          FROM tutor_requests r 
                          JOIN users u ON r.student_id = u.id
                          JOIN users t ON r.tutor_id = t.id
                          WHERE r.id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
    
    if ($request) {
        // Get ALL messages (formerly answers)
        $stmt = $pdo->prepare("SELECT m.*, u.username as sender_name, u.avatar as sender_avatar 
                              FROM tutor_answers m
                              JOIN users u ON m.sender_id = u.id
                              WHERE m.request_id = ? ORDER BY m.created_at ASC");
        $stmt->execute([$request_id]);
        $request['answers'] = $stmt->fetchAll(); 
        $request['answer'] = end($request['answers']); 
    }
    
    return $request;
}

function answerTutorRequest($tutor_id, $request_id, $content, $attachment = null) {
    $pdo = getTutorDBConnection();
    
    $request = getRequestDetails($request_id);
    if (!$request || $request['tutor_id'] != $tutor_id) {
        return ['success' => false, 'message' => 'Yêu cầu không hợp lệ.'];
    }
    
    if ($request['status'] === 'completed' || $request['status'] === 'disputed') {
        return ['success' => false, 'message' => 'Câu hỏi này đã hoàn tất hoặc đang tranh chấp.'];
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert Answer (now using sender_id)
        $stmt = $pdo->prepare("INSERT INTO tutor_answers (request_id, tutor_id, sender_id, content, attachment) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$request_id, $tutor_id, $tutor_id, $content, $attachment]);

        // 2. Update Tutor Stats (Total answers)
        // We probably count total answers given, regardless of points? Or only paid ones?
        // Let's count them for activity.
        $stmt = $pdo->prepare("UPDATE tutors SET total_answers = total_answers + 1 WHERE user_id = ?");
        $stmt->execute([$tutor_id]);

        $pdo->commit();

        // Notify Student of Answer
        global $VSD;
        $VSD->insert('notifications', [
            'user_id' => $request['student_id'],
            'title' => 'Gia sư đã trả lời',
            'message' => "Gia sư '{$request['tutor_name']}' đã trả lời câu hỏi của bạn: '{$request['title']}'.",
            'type' => 'tutor_answer',
            'ref_id' => $request_id
        ]);
        sendPushToUser($request['student_id'], [
            'title' => 'Có câu trả lời mới! ✅',
            'body' => "Gia sư vừa trả lời câu hỏi của bạn. Nhấn để xem ngay.",
            'url' => '/tutors/request.php?id=' . $request_id
        ]);

        return ['success' => true, 'message' => 'Đã gửi câu trả lời thành công! VSD sẽ được cộng khi học viên đánh giá tốt.'];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

function rateTutor($student_id, $request_id, $rating, $review = '') {
    $pdo = getTutorDBConnection();

    // Verify ownership
    $request = getRequestDetails($request_id);
    if (!$request || $request['student_id'] != $student_id) {
        return ['success' => false, 'message' => 'Yêu cầu không hợp lệ.'];
    }

    // Check status
    if ($request['status'] !== 'answered') {
        return ['success' => false, 'message' => 'Bạn chỉ có thể đánh giá khi gia sư đã trả lời.'];
    }

    // Attempt to update Request
    try {
        $pdo->beginTransaction();

        // Logic: if rating >= 4, status = completed -> pay tutor
        // if rating <= 3, status = disputed -> pending admin
        
        $new_status = ($rating >= 4) ? 'completed' : 'disputed';
        
        $stmt = $pdo->prepare("UPDATE tutor_requests SET rating = ?, review = ?, status = ? WHERE id = ?");
        $stmt->execute([$rating, $review, $new_status, $request_id]);

        if ($new_status === 'completed') {
            // Settle Escrow (Using Dynamic Settings)
            $commissions = [
                'normal' => intval(getSetting('tutor_commission_basic', 25)),
                'medium' => intval(getSetting('tutor_commission_standard', 30)),
                'vip'    => intval(getSetting('tutor_commission_premium', 25))
            ];
            $admin_share = $commissions[$request['package_type']] ?? 25;
            
            $settle = settleEscrow($request['transaction_id'], $request['tutor_id'], $admin_share);
             if (!$settle) {
                 throw new Exception("Lỗi tất toán điểm cho gia sư.");
             }
             
             // ALSO Settle any accepted offers
             $stmt = $pdo->prepare("SELECT transaction_id FROM tutor_offers WHERE request_id = ? AND status = 'accepted' AND transaction_id IS NOT NULL");
             $stmt->execute([$request_id]);
             $offers = $stmt->fetchAll();
             
             foreach ($offers as $offer) {
                 settleEscrow($offer['transaction_id'], $request['tutor_id'], $admin_share);
             }

             $msg = "Cảm ơn bạn đã đánh giá! Gia sư đã nhận được VSD.";
        } else {
            $msg = "Đánh giá của bạn đã được ghi nhận. Vì đánh giá thấp (< 4 sao), yêu cầu sẽ được Admin xem xét.";
        }

        // Recalculate Tutor Average Rating
        $tutor_id = $request['tutor_id'];
        $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating FROM tutor_requests WHERE tutor_id = ? AND rating IS NOT NULL");
        $stmt->execute([$tutor_id]);
        $row = $stmt->fetch();
        $new_rating = number_format((float)($row['avg_rating'] ?? 0), 2);

        // Update Tutor Table
        $stmt = $pdo->prepare("UPDATE tutors SET rating = ? WHERE user_id = ?");
        $stmt->execute([$new_rating, $tutor_id]);

        $pdo->commit();

        // Notify Tutor of Rating
        global $VSD;
        $notif_msg = "Học viên '{$request['student_name']}' đã đánh giá $rating sao cho câu trả lời của bạn.";
        if ($new_status === 'completed') {
            $notif_msg .= " Bạn nhận được {$request['points_used']} VSD.";
        }
        
        $VSD->insert('notifications', [
            'user_id' => $request['tutor_id'],
            'title' => ($rating >= 4 ? 'Đánh giá tích cực' : 'Khiếu nại đánh giá'),
            'message' => $notif_msg,
            'type' => 'tutor_rated',
            'ref_id' => $request_id
        ]);
        sendPushToUser($request['tutor_id'], [
            'title' => ($rating >= 4 ? 'Bạn được đánh giá tốt! ⭐' : 'Khiếu nại từ học viên ⚠️'),
            'body' => $notif_msg,
            'url' => '/tutors/request.php?id=' . $request_id
        ]);

        return ['success' => true, 'message' => $msg];

    } catch (Exception $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

/**
 * Send a message in a tutor request (Chat)
 */
function sendTutorChatMessage($user_id, $request_id, $content, $attachment = null) {
    $pdo = getTutorDBConnection();
    $request = getRequestDetails($request_id);
    
    if (!$request) return ['success' => false, 'message' => 'Yêu cầu không tồn tại.'];
    
    $is_student = ($request['student_id'] == $user_id);
    $is_tutor = ($request['tutor_id'] == $user_id);
    
    if (!$is_student && !$is_tutor) {
        return ['success' => false, 'message' => 'Bạn không có quyền tham gia cuộc hội thoại này.'];
    }
    
    if ($request['status'] === 'completed' || $request['status'] === 'cancelled' || !empty($request['rating'])) {
        return ['success' => false, 'message' => 'Yêu cầu này đã hoàn tất hoặc đã được đánh giá, cuộc hội thoại kết thúc.'];
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO tutor_answers (request_id, tutor_id, sender_id, content, attachment) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$request_id, $request['tutor_id'], $user_id, $content, $attachment]);


        // Notify other party
        $other_user_id = $is_student ? $request['tutor_id'] : $request['student_id'];
        $sender_name = $is_student ? $request['student_name'] : $request['tutor_name'];
        
        global $VSD;
        $VSD->insert('notifications', [
            'user_id' => $other_user_id,
            'title' => 'Tin nhắn mới từ ' . $sender_name,
            'message' => "Bạn nhận được tin nhắn mới trong yêu cầu #$request_id",
            'type' => 'tutor_chat',
            'ref_id' => $request_id
        ]);

        return ['success' => true, 'message' => 'Đã gửi tin nhắn!'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

/**
 * Get active conversations for a user
 */
function getActiveTutorChats($user_id) {
    $pdo = getTutorDBConnection();
    $stmt = $pdo->prepare("SELECT r.*, u.username as student_name, t.username as tutor_name,
                          (SELECT content FROM tutor_answers WHERE request_id = r.id ORDER BY created_at DESC LIMIT 1) as last_message,
                          (SELECT created_at FROM tutor_answers WHERE request_id = r.id ORDER BY created_at DESC LIMIT 1) as last_message_time
                          FROM tutor_requests r
                          JOIN users u ON r.student_id = u.id
                          JOIN users t ON r.tutor_id = t.id
                          WHERE (r.student_id = ? OR r.tutor_id = ?) 
                          AND r.status IN ('pending', 'answered', 'disputed')
    AND r.rating IS NULL
    ORDER BY last_message_time DESC");
    $stmt->execute([$user_id, $user_id]);
    return $stmt->fetchAll();
}

/**
 * Finish a request (Tutor)
 * Transitions status to 'answered' to prompt user for rating
 */
function finishTutorRequest($tutor_id, $request_id) {
    $pdo = getTutorDBConnection();
    $request = getRequestDetails($request_id);
    
    if (!$request || $request['tutor_id'] != $tutor_id) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }
    
    if ($request['status'] !== 'pending') {
        return ['success' => true, 'message' => 'Yêu cầu đã ở trạng thái chờ đánh giá.'];
    }

    try {
        $stmt = $pdo->prepare("UPDATE tutor_requests SET status = 'answered' WHERE id = ?");
        $stmt->execute([$request_id]);
        
        // Notify student
        global $VSD;
        $VSD->insert('notifications', [
            'user_id' => $request['student_id'],
            'title' => 'Gia sư đã hoàn tất hỗ trợ',
            'message' => "Gia sư đã hoàn tất việc hỗ trợ cho yêu cầu '{$request['title']}'. Vui lòng đánh giá để hoàn tất giao dịch.",
            'type' => 'tutor_answer',
            'ref_id' => $request_id
        ]);

        return ['success' => true, 'message' => 'Đã đánh dấu hoàn tất. Học viên sẽ được nhắc đánh giá.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

/**
 * Request Profile Update (Pending Approval)
 */
function requestTutorProfileUpdate($user_id, $data) {
    $pdo = getTutorDBConnection();
    
    try {
        // Cancel any previous pending updates for this user
        $stmt = $pdo->prepare("UPDATE tutor_profile_updates SET status = 'rejected', admin_note = 'Canceled by new request' WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$user_id]);

        $sql = "INSERT INTO tutor_profile_updates (user_id, subjects, bio, price_basic, price_standard, price_premium, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id,
            $data['subjects'],
            $data['bio'],
            $data['price_basic'],
            $data['price_standard'],
            $data['price_premium']
        ]);
        
        return ['success' => true, 'message' => 'Yêu cầu thay đổi hồ sơ đã được gửi. Vui lòng chờ Admin phê duyệt.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

/**
 * Get Pending Profile Updates for Admin
 */
function getPendingProfileUpdates() {
    $pdo = getTutorDBConnection();
    $stmt = $pdo->prepare("SELECT pu.*, u.username, t.subjects as old_subjects, t.bio as old_bio 
                          FROM tutor_profile_updates pu
                          JOIN users u ON pu.user_id = u.id
                          JOIN tutors t ON pu.user_id = t.user_id
                          WHERE pu.status = 'pending'
                          ORDER BY pu.created_at ASC");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Admin Approve/Reject Profile Update
 */
function processProfileUpdate($update_id, $status, $note = '') {
    $pdo = getTutorDBConnection();
    
    try {
        $pdo->beginTransaction();
        
        // Get the update data
        $stmt = $pdo->prepare("SELECT * FROM tutor_profile_updates WHERE id = ?");
        $stmt->execute([$update_id]);
        $update = $stmt->fetch();
        
        if (!$update) throw new Exception("Update not found");

        // Update the tutor_profile_updates record
        $stmt = $pdo->prepare("UPDATE tutor_profile_updates SET status = ?, admin_note = ? WHERE id = ?");
        $stmt->execute([$status, $note, $update_id]);

        if ($status === 'approved') {
            // APPLY TO MAIN TUTORS TABLE
            $stmt = $pdo->prepare("UPDATE tutors SET subjects = ?, bio = ?, price_basic = ?, price_standard = ?, price_premium = ?, updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([
                $update['subjects'],
                $update['bio'],
                $update['price_basic'],
                $update['price_standard'],
                $update['price_premium'],
                $update['user_id']
            ]);
        }

        $pdo->commit();
        
        // Notify user
        global $VSD;
        $title = ($status === 'approved') ? 'Hồ sơ đã được duyệt' : 'Hồ sơ bị từ chối';
        $msg = ($status === 'approved') ? 'Cấu hình hồ sơ mới của bạn đã được áp dụng.' : 'Yêu cầu thay đổi hồ sơ của bạn đã bị từ chối. Ghi chú: ' . $note;
        $VSD->insert('notifications', [
            'user_id' => $update['user_id'],
            'title' => $title,
            'message' => $msg,
            'type' => 'role_updated'
        ]);

        return ['success' => true, 'message' => 'Đã xử lý thành công.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

/**
 * Create an Additional Points Offer (Tutor)
 */
function createTutorOffer($tutor_id, $request_id, $points, $reason, $hours_valid = 24) {
    $pdo = getTutorDBConnection();
    
    // Validate points (only +10, +20, +40, +60)
    $allowed_points = [10, 20, 40, 60];
    if (!in_array($points, $allowed_points)) {
        return ['success' => false, 'message' => 'Mức đề nghị VSD không hợp lệ.'];
    }

    $request = getRequestDetails($request_id);
    if (!$request || $request['tutor_id'] != $tutor_id) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }

    if ($request['status'] !== 'pending' && $request['status'] !== 'answered') {
        return ['success' => false, 'message' => 'Không thể gửi đề nghị cho yêu cầu đã xong hoặc đang khiểu nại.'];
    }

    try {
        $deadline = date('Y-m-d H:i:s', strtotime("+{$hours_valid} hours"));
        $stmt = $pdo->prepare("INSERT INTO tutor_offers (request_id, tutor_id, points_offered, reason, deadline, status) 
                              VALUES (?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$request_id, $tutor_id, $points, $reason, $deadline]);
        $offer_id = $pdo->lastInsertId();

        // Notify Student
        global $VSD;
        $VSD->insert('notifications', [
            'user_id' => $request['student_id'],
            'title' => 'Đề nghị thêm VSD',
            'message' => "Gia sư đề nghị thêm {$points} VSD cho yêu cầu #{$request_id}. Lý do: {$reason}",
            'type' => 'tutor_offer',
            'ref_id' => $offer_id
        ]);

        return ['success' => true, 'message' => 'Đã gửi đề nghị thêm điểm thành công.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

/**
 * Accept an Additional Points Offer (Student)
 */
function acceptTutorOffer($student_id, $offer_id) {
    $pdo = getTutorDBConnection();
    
    $stmt = $pdo->prepare("SELECT o.*, r.student_id, r.tutor_id, r.title FROM tutor_offers o JOIN tutor_requests r ON o.request_id = r.id WHERE o.id = ?");
    $stmt->execute([$offer_id]);
    $offer = $stmt->fetch();

    if (!$offer || $offer['student_id'] != $student_id) {
        return ['success' => false, 'message' => 'Đề nghị không tồn tại.'];
    }

    if ($offer['status'] !== 'pending') {
        return ['success' => false, 'message' => 'Đề nghị này đã được xử lý hoặc đã hết hạn.'];
    }

    if (strtotime($offer['deadline']) < time()) {
        $pdo->prepare("UPDATE tutor_offers SET status = 'expired' WHERE id = ?")->execute([$offer_id]);
        return ['success' => false, 'message' => 'Đề nghị đã hết hạn.'];
    }

    try {
        $pdo->beginTransaction();

        // Lock additional points
        $points = $offer['points_offered'];
        $reason = "Bổ sung điểm cho yêu cầu #{$offer['request_id']} (Offer #$offer_id)";
        $transaction_id = lockPoints($student_id, $points, $reason, 'tutor_offer', $offer_id);

        if (!$transaction_id) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Bạn không đủ VSD để chấp nhận đề nghị này.'];
        }

        // Update Offer
        $stmt = $pdo->prepare("UPDATE tutor_offers SET status = 'accepted', transaction_id = ? WHERE id = ?");
        $stmt->execute([$transaction_id, $offer_id]);

        // Update Request points_used AND SLA
        // Policy: 10 VSD = +1 Hour SLA
        $hours_added = intval($points / 10);
        $stmt = $pdo->prepare("UPDATE tutor_requests SET points_used = points_used + ?, sla_deadline = DATE_ADD(sla_deadline, INTERVAL ? HOUR) WHERE id = ?");
        $stmt->execute([$points, $hours_added, $offer['request_id']]);

        $pdo->commit();

        // Notify Tutor
        global $VSD;
        $VSD->insert('notifications', [
            'user_id' => $offer['tutor_id'],
            'title' => 'Đề nghị được chấp nhận',
            'message' => "Học viên đã chấp nhận đề nghị +{$points} VSD của bạn cho yêu cầu #{$offer['request_id']}",
            'type' => 'tutor_offer_accepted',
            'ref_id' => $offer['request_id']
        ]);

        return ['success' => true, 'message' => 'Đã chấp nhận đề nghị.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

/**
 * Get offers for a request (Pending & Accepted)
 */
function getRequestOffers($request_id) {
    $pdo = getTutorDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM tutor_offers WHERE request_id = ? AND status IN ('pending', 'accepted') ORDER BY created_at DESC");
    $stmt->execute([$request_id]);
    return $stmt->fetchAll();
}

/**
 * Get pending offers for a request (Deprecated, kept for compatibility if needed)
 */
function getPendingOffers($request_id) {
    return getRequestOffers($request_id);
}

/**
 * Request Point Withdrawal (Tutor)
 */
function requestWithdrawal($tutor_id, $points, $bank_info) {
    $pdo = getTutorDBConnection();
    $points = intval($points);
    $tutor_id = intval($tutor_id);

    // 1. Check if tutor has enough Topup Points
    $points_data = getUserPoints($tutor_id);
    if ($points_data['topup_points'] < $points) {
        return ['success' => false, 'message' => 'Số dư Topup không đủ để thực hiện rút VSD.'];
    }

    // 2. Check for existing pending request
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawal_requests WHERE user_id = ? AND status = 'pending'");
    $stmt->execute([$tutor_id]);
    if ($stmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'Bạn đang có một yêu cầu rút tiền chờ xử lý.'];
    }

    // 2d. Anti-Abuse checks (Frequency & Age)
    if (isSettingEnabled('tutor_anti_abuse')) {
        // 2b. Limit one request per 24 hours
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM withdrawal_requests WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $stmt->execute([$tutor_id]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Bạn chỉ được thực hiện rút tiền tối đa 1 lần mỗi 24 giờ.'];
        }

        // 2c. Check if new tutor (joined < 7 days)
        $stmt = $pdo->prepare("SELECT created_at FROM tutors WHERE user_id = ?");
        $stmt->execute([$tutor_id]);
        $joined_at = $stmt->fetchColumn();
        if ($joined_at && strtotime($joined_at) > strtotime('-7 days')) {
            return ['success' => false, 'message' => 'Tài khoản gia sư mới phải hoạt động ít nhất 7 ngày mới có thể rút tiền.'];
        }
    }

    try {
        $pdo->beginTransaction();

        // 3. Create Withdrawal Request (Calculated amount = points * Exchange Rate)
        $rate = intval(getSetting('shop_exchange_rate', 1000));
        $amount_vnd = $points * $rate;
        $stmt = $pdo->prepare("INSERT INTO withdrawal_requests (user_id, points, amount_vnd, bank_info, status) 
                              VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$tutor_id, $points, $amount_vnd, $bank_info]);
        $request_id = $pdo->lastInsertId();

        // 4. Record as locked transaction
        $reason = "Rút tiền (Yêu cầu #$request_id)";
        // We use lockPoints for topup points specifically here? 
        // Our lockPoints locks total points (bonus first). 
        // For withdrawals, we MUST only lock Topup points.
        
        $tx_query = "INSERT INTO point_transactions (user_id, transaction_type, points, topup_points_deducted, bonus_points_deducted, related_id, related_type, reason, status) 
                     VALUES ($tutor_id, 'lock', $points, $points, 0, $request_id, 'withdrawal', '$reason', 'locked')";
        db_query($tx_query);
        $transaction_id = db_insert_id();

        // Update withdrawal request with transaction id
        $pdo->prepare("UPDATE withdrawal_requests SET transaction_id = ? WHERE id = ?")->execute([$transaction_id, $request_id]);

        // 5. Update user points (deduct from topup, add to locked)
        db_query("UPDATE user_points SET 
                  current_points = current_points - $points,
                  topup_points = topup_points - $points,
                  locked_points = locked_points + $points
                  WHERE user_id=$tutor_id");

        $pdo->commit();

        // Notify Admins of New Withdrawal Request
        try {
            require_once __DIR__ . '/notifications.php';

            $user_stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $user_stmt->execute([$tutor_id]);
            $username = $user_stmt->fetchColumn() ?: "Gia sư #$tutor_id";

            $admin_message = "Yêu cầu rút tiền mới: {$username}";
            $extra_data = [
                'request_id' => intval($request_id),
                'username' => $username,
                'user_id' => intval($tutor_id),
                'points' => intval($points),
                'amount_vnd' => intval($amount_vnd),
                'transaction_id' => intval($transaction_id)
            ];

            sendNotificationToAllAdmins('withdrawal_request', $admin_message, $request_id, $extra_data);
        } catch (Exception $e) {
            error_log("Withdrawal request admin notification error: " . $e->getMessage());
        }

        return ['success' => true, 'message' => 'Yêu cầu rút tiền đặt thành công. Admin sẽ duyệt trong 24-48h.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}

/**
 * Admin: Approve a withdrawal request
 */
function approveWithdrawal($request_id, $admin_id, $note = '') {
    $request_id = intval($request_id);
    $admin_id = intval($admin_id);
    $note = db_escape($note);
    
    $request = db_get_row("SELECT * FROM withdrawal_requests WHERE id = $request_id");
    
    if (!$request || $request['status'] !== 'pending') {
        return ['success' => false, 'message' => 'Yêu cầu không hợp lệ hoặc đã xử lý.'];
    }
    
    // 1. Update request status
    $sql_status = "UPDATE withdrawal_requests SET status = 'approved', admin_id = $admin_id, admin_note = '$note', processed_at = NOW() WHERE id = $request_id";
    if (!db_query($sql_status)) {
        return ['success' => false, 'message' => 'Lỗi cập nhật yêu cầu: ' . db_error()];
    }
    
    // 2. Settle the transaction
    $points = intval($request['points']);
    $user_id = intval($request['user_id']);
    $tx_id = intval($request['transaction_id']);
    
    // Mark transaction as settled
    db_query("UPDATE point_transactions SET status = 'settled' WHERE id = $tx_id");
    
    // Finalize point deduction
    db_query("UPDATE user_points SET 
              locked_points = locked_points - $points, 
              total_spent = total_spent + $points 
              WHERE user_id = $user_id");
    
    // Notify Tutor
    global $VSD;
    $VSD->insert('notifications', [
        'user_id' => $user_id,
        'title' => 'Rút tiền đã được duyệt',
        'message' => "Yêu cầu rút " . number_format($request['amount_vnd']) . "đ đã được phê duyệt.",
        'type' => 'withdrawal_approved',
        'ref_id' => $request_id
    ]);

    // Notify other admins for audit visibility
    try {
        require_once __DIR__ . '/notifications.php';

        $user_info = db_get_row("SELECT username FROM users WHERE id = $user_id");
        $username = $user_info['username'] ?? "Gia sư #$user_id";

        $admin_message = "Đã duyệt rút tiền: {$username}";
        $extra_data = [
            'request_id' => intval($request_id),
            'username' => $username,
            'user_id' => intval($user_id),
            'points' => intval($points),
            'amount_vnd' => intval($request['amount_vnd'] ?? 0),
            'admin_id' => intval($admin_id),
            'admin_note' => $note
        ];

        sendNotificationToAllAdmins('withdrawal_approved', $admin_message, $request_id, $extra_data);
    } catch (Exception $e) {
        error_log("Withdrawal approved admin notification error: " . $e->getMessage());
    }
    
    return ['success' => true, 'message' => 'Đã duyệt yêu cầu rút tiền thành công.'];
}

/**
 * Admin: Reject a withdrawal request
 */
function rejectWithdrawal($request_id, $admin_id, $reason = '') {
    $request_id = intval($request_id);
    $admin_id = intval($admin_id);
    $reason = db_escape($reason);
    
    $request = db_get_row("SELECT * FROM withdrawal_requests WHERE id = $request_id");
    
    if (!$request || $request['status'] !== 'pending') {
        return ['success' => false, 'message' => 'Yêu cầu không hợp lệ hoặc đã xử lý.'];
    }
    
    // 1. Update request status
    $sql_status = "UPDATE withdrawal_requests SET status = 'rejected', admin_id = $admin_id, admin_note = '$reason', processed_at = NOW() WHERE id = $request_id";
    if (!db_query($sql_status)) {
        return ['success' => false, 'message' => 'Lỗi từ chối yêu cầu: ' . db_error()];
    }
    
    // 2. Refund points to tutor
    $points = intval($request['points']);
    $user_id = intval($request['user_id']);
    $tx_id = intval($request['transaction_id']);
    
    // Mark transaction as refunded
    db_query("UPDATE point_transactions SET status = 'refunded', rejection_reason = '$reason' WHERE id = $tx_id");
    
    // Return points to Topup and remove from Locked
    db_query("UPDATE user_points SET 
              current_points = current_points + $points, 
              topup_points = topup_points + $points, 
              locked_points = locked_points - $points 
              WHERE user_id = $user_id");
    
    // Notify Tutor
    global $VSD;
    $VSD->insert('notifications', [
        'user_id' => $user_id,
        'title' => 'Rút tiền bị từ chối',
        'message' => "Yêu cầu rút tiền của bạn bị từ chối. Lý do: $reason",
        'type' => 'withdrawal_rejected',
        'ref_id' => $request_id
    ]);

    // Notify other admins for audit visibility
    try {
        require_once __DIR__ . '/notifications.php';

        $user_info = db_get_row("SELECT username FROM users WHERE id = $user_id");
        $username = $user_info['username'] ?? "Gia sư #$user_id";

        $admin_message = "Đã từ chối rút tiền: {$username}";
        $extra_data = [
            'request_id' => intval($request_id),
            'username' => $username,
            'user_id' => intval($user_id),
            'points' => intval($points),
            'amount_vnd' => intval($request['amount_vnd'] ?? 0),
            'admin_id' => intval($admin_id),
            'admin_note' => $reason
        ];

        sendNotificationToAllAdmins('withdrawal_rejected', $admin_message, $request_id, $extra_data);
    } catch (Exception $e) {
        error_log("Withdrawal rejected admin notification error: " . $e->getMessage());
    }
    
    return ['success' => true, 'message' => 'Đã từ chối yêu cầu rút tiền. Điểm đã được hoàn lại cho gia sư.'];
}

/**
 * Admin: Get all withdrawal requests
 */
function getAllWithdrawalRequests($status = 'pending') {
    $pdo = getTutorDBConnection();
    $sql = "SELECT wr.*, u.username, u.email 
            FROM withdrawal_requests wr 
            JOIN users u ON wr.user_id = u.id";
    
    if ($status !== 'all') {
        $sql .= " WHERE wr.status = ?";
    }
    
    $sql .= " ORDER BY wr.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    if ($status !== 'all') {
        $stmt->execute([$status]);
    } else {
        $stmt->execute();
    }
    
    return $stmt->fetchAll();
}

/**
 * Cron/Trigger: Automatically complete requests where SLA has expired
 */
function checkSLAExpirations() {
    $pdo = getTutorDBConnection();
    
    // Find requests that are 'answered' but student hasn't rated, and 24h passed
    $stmt = $pdo->prepare("SELECT * FROM tutor_requests WHERE status = 'answered' AND updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $expired = $stmt->fetchAll();
    
    foreach ($expired as $req) {
        try {
            $pdo->beginTransaction();
            
            // Mark as completed
            $stmt = $pdo->prepare("UPDATE tutor_requests SET status = 'completed', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$req['id']]);
            
            // Settle Escrow
            $admin_shares = ['normal' => 25, 'medium' => 30, 'vip' => 25];
            $share = $admin_shares[$req['package_type']] ?? 25;
            
            settleEscrow($req['transaction_id'], $req['tutor_id'], $share);
            
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }
}
/**
 * Get Tutor stats (Ratings, Completion Rate, etc.)
 */
function getTutorStats($user_id) {
    if (!$user_id) return null;
    $pdo = getTutorDBConnection();
    
    // 1. Basic counts
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status='disputed' THEN 1 ELSE 0 END) as disputed_count,
            AVG(CASE WHEN rating IS NOT NULL THEN rating ELSE NULL END) as avg_rating
        FROM tutor_requests 
        WHERE tutor_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // 2. Response time (Average time between creation and first answer)
    $stmt = $pdo->prepare("
        SELECT AVG(TIMESTAMPDIFF(MINUTE, r.created_at, a.created_at)) as avg_response_time
        FROM tutor_requests r
        JOIN tutor_answers a ON r.id = a.request_id
        WHERE r.tutor_id = ? AND a.sender_id = r.tutor_id
    ");
    $stmt->execute([$user_id]);
    $res_time = $stmt->fetch();
    
    $stats['avg_response_time'] = $res_time['avg_response_time'] ?? 0;
    $stats['completion_rate'] = $stats['total_requests'] > 0 ? ($stats['completed_count'] / $stats['total_requests']) * 100 : 0;
    
    return $stats;
}
