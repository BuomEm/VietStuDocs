<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/points.php';
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
    if (!$last_activity) return ['status' => 'offline', 'text' => 'Offline', 'label' => 'Offline'];
    
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
        return ['status' => 'offline', 'text' => "Offline {$mins} phút trước", 'label' => "{$mins}p trước"];
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return ['status' => 'offline', 'text' => "Offline {$hours} giờ trước", 'label' => "{$hours}h trước"];
    } else {
        $days = floor($diff / 86400);
        return ['status' => 'offline', 'text' => "Offline {$days} ngày trước", 'label' => "{$days}d trước"];
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

    // 2. Calculate points cost
    $package = $data['package_type']; // basic, standard, premium
    $price_column = "price_" . $package;
    if (!isset($tutor[$price_column])) {
        return ['success' => false, 'message' => 'Gói câu hỏi không hợp lệ.'];
    }
    $cost = intval($tutor[$price_column]);

    // Apply Premium Discount (10%)
    $is_premium_student = isPremium($student_id);
    if($is_premium_student) {
        $cost = intval($cost * 0.9);
    }

    // 3. Check student points
    $student_points = getUserPoints($student_id);
    if ($student_points['current_points'] < $cost) {
        return ['success' => false, 'message' => "Bạn không đủ điểm. Cần $cost điểm."];
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
            $package, 
            $cost, 
            $data['attachment'] ?? null
        ]);
        $request_id = $pdo->lastInsertId();

        // Step 4.2: Deduct Points
        // We temporarily commit PDO trans to allow external function call if strictly needed, 
        // but let's try to keep it simple.
        $deduct = deductPoints($student_id, $cost, "Đặt câu hỏi cho Gia sư #$tutor_id (Request #$request_id)");
        
        if (!$deduct) {
            // Failed to deduct, remove request
            $pdo->rollBack(); // This rolls back step 4.1
            return ['success' => false, 'message' => 'Lỗi trừ điểm. Giao dịch bị hủy.'];
        }

        $pdo->commit();
        
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

        return ['success' => true, 'message' => 'Đã gửi câu trả lời thành công! Điểm sẽ được cộng khi học viên đánh giá tốt.'];

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
            // Pay Tutor
            $points = $request['points_used'];
            $tutor_id = $request['tutor_id'];
            // We use addPoints from points.php
            $add = addPoints($tutor_id, $points, "Trả lời câu hỏi #$request_id (Được đánh giá $rating sao)", null);
             if (!$add) {
                 throw new Exception("Lỗi cộng điểm cho gia sư.");
             }
             $msg = "Cảm ơn bạn đã đánh giá! Gia sư đã nhận được điểm.";
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
            $notif_msg .= " Bạn nhận được {$request['points_used']} points.";
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
?>
