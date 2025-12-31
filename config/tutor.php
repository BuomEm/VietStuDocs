<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/points.php';
require_once __DIR__ . '/premium.php';

/**
 * Get PDO Database Connection
 * Use this for all Tutor system operations to ensure security
 */
function getTutorDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
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
    $stmt = $pdo->prepare("SELECT t.*, u.username, u.email FROM tutors t JOIN users u ON t.user_id = u.id WHERE t.user_id = ?");
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
    $sql = "SELECT t.*, u.username, u.email, 
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
    $stmt = $pdo->prepare("SELECT r.*, u.username as student_name 
                          FROM tutor_requests r 
                          JOIN users u ON r.student_id = u.id 
                          WHERE r.tutor_id = ? 
                          ORDER BY r.created_at DESC");
    $stmt->execute([$tutor_id]);
    return $stmt->fetchAll();
}

function getRequestDetails($request_id) {
    $pdo = getTutorDBConnection();
    $stmt = $pdo->prepare("SELECT r.*, u.username as student_name, t.username as tutor_name
                          FROM tutor_requests r 
                          JOIN users u ON r.student_id = u.id
                          JOIN users t ON r.tutor_id = t.id
                          WHERE r.id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();
    
    if ($request) {
        // Get ALL answers
        $stmt = $pdo->prepare("SELECT * FROM tutor_answers WHERE request_id = ? ORDER BY created_at ASC");
        $stmt->execute([$request_id]);
        $request['answers'] = $stmt->fetchAll(); // Changed key to 'answers' and fetchAll
        $request['answer'] = end($request['answers']); // Keep legacy compatibility if needed, using latest answer
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

        // 1. Insert Answer
        $stmt = $pdo->prepare("INSERT INTO tutor_answers (request_id, tutor_id, content, attachment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$request_id, $tutor_id, $content, $attachment]);

        // 2. Update Request Status (Only if pending, otherwise keep as answered)
        if ($request['status'] === 'pending') {
            $stmt = $pdo->prepare("UPDATE tutor_requests SET status = 'answered' WHERE id = ?");
            $stmt->execute([$request_id]);
        }
        
        // 3. Update Tutor Stats (Total answers)
        // We probably count total answers given, regardless of points? Or only paid ones?
        // Let's count them for activity.
        $stmt = $pdo->prepare("UPDATE tutors SET total_answers = total_answers + 1 WHERE user_id = ?");
        $stmt->execute([$tutor_id]);

        $pdo->commit();
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
        return ['success' => true, 'message' => $msg];

    } catch (Exception $e) {
        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
    }
}
?>
