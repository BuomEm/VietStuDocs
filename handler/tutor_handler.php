<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';

// Check if user is logged in
if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để thực hiện hành động này.']);
    exit;
}

$action = $_POST['action'] ?? '';
$user_id = getCurrentUserId();

switch ($action) {
    case 'register_tutor':
        $subjects = trim($_POST['subjects'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $prices = [
            'basic' => intval($_POST['price_basic'] ?? 20),
            'standard' => intval($_POST['price_standard'] ?? 50),
            'premium' => intval($_POST['price_premium'] ?? 100)
        ];
        
        if (empty($subjects) || empty($bio)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin.']);
            exit;
        }

        $result = registerTutor($user_id, $subjects, $bio, $prices);
        
        if ($result['success']) {
            require_once __DIR__ . '/../config/settings.php';
            $user_info = getUserInfo($user_id);
            $msg = "<b>🎓 Đăng ký Gia sư mới!</b>\n";
            $msg .= "👤 User: " . ($user_info['username'] ?? "ID: $user_id") . "\n";
            $msg .= "📚 Môn dạy: " . $subjects . "\n";
            $msg .= "⏰ Thời gian: " . date('H:i d/m/Y');
            sendTelegramNotification($msg);
        }

        echo json_encode($result);
        break;

    case 'create_request':
        $tutor_id = intval($_POST['tutor_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $package = $_POST['package_type'] ?? 'basic'; // basic, standard, premium
        
        if (!$tutor_id || empty($title) || empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Thiếu thông tin bắt buộc.']);
            exit;
        }

        $data = [
            'title' => $title,
            'content' => $content,
            'package_type' => $package,
            'points' => intval($_POST['points'] ?? 0)
        ];
        
        // Handle file upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            
            // Validate Logic: Only VIP can attach files
            if ($package !== 'vip') {
                echo json_encode(['success' => false, 'message' => 'Chỉ gói VIP mới được phép đính kèm tài liệu.']);
                exit;
            }

            $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'zip'];
            $filename = $_FILES['attachment']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_name = 'req_' . uniqid() . '_' . time() . '.' . $ext;
                $upload_dir = __DIR__ . '/../uploads/tutors/';
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $new_name)) {
                    $data['attachment'] = $new_name;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Lỗi: Không thể lưu file đính kèm.']);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Định dạng file không hỗ trợ. Chỉ nhận ảnh, PDF, Word, Zip.']);
                exit;
            }
        } elseif (isset($_FILES['attachment']) && $_FILES['attachment']['error'] != UPLOAD_ERR_NO_FILE) {
            echo json_encode(['success' => false, 'message' => 'Lỗi upload file: Mã lỗi ' . $_FILES['attachment']['error']]);
            exit;
        }

        $result = createTutorRequest($user_id, $tutor_id, $data);
        echo json_encode($result);
        break;

    case 'send_chat_message': // Chat messages from both tutor and student
    case 'answer_request':
        $request_id = intval($_POST['request_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        
        if (!$request_id || empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập nội dung tin nhắn.']);
            exit;
        }

        // Validate user is part of this request (either tutor or student)
        $request = getRequestDetails($request_id);
        if (!$request || ($request['tutor_id'] != $user_id && $request['student_id'] != $user_id)) {
            echo json_encode(['success' => false, 'message' => 'Bạn không có quyền gửi tin nhắn cho yêu cầu này.']);
            exit;
        }

        $attachment = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'zip'];
            $filename = $_FILES['attachment']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_name = 'chat_' . uniqid() . '_' . time() . '.' . $ext;
                $upload_dir = __DIR__ . '/../uploads/tutors/';
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $new_name)) {
                    $attachment = $new_name;
                }
            }
        }

        // Use unified chat function
        $result = answerTutorRequest($user_id, $request_id, $content, $attachment);
        echo json_encode($result);
        break;

    case 'rate_tutor':
        $request_id = intval($_POST['request_id'] ?? 0);
        $rating = floatval($_POST['rating'] ?? 0);
        $review = trim($_POST['review'] ?? '');
        
        if (!$request_id || $rating < 0.5 || $rating > 5) {
            echo json_encode(['success' => false, 'message' => 'Đánh giá không hợp lệ.']);
            exit;
        }
        
        if (empty($review)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập nhận xét.']);
            exit;
        }

        $result = rateTutor($user_id, $request_id, $rating, $review);
        echo json_encode($result);
        break;

    case 'create_offer':
        if (!isTutor($user_id)) {
            echo json_encode(['success' => false, 'message' => 'Bạn không phải là gia sư.']);
            exit;
        }
        $request_id = intval($_POST['request_id'] ?? 0);
        $points = intval($_POST['points'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        
        $result = createTutorOffer($user_id, $request_id, $points, $reason);
        echo json_encode($result);
        break;

    case 'request_withdrawal':
        if (!isTutor($user_id)) {
            echo json_encode(['success' => false, 'message' => 'Bạn không phải là gia sư.']);
            exit;
        }
        $points = intval($_POST['points'] ?? 0);
        $bank_info = trim($_POST['bank_info'] ?? '');
        
        if ($points < 50) {
            echo json_encode(['success' => false, 'message' => 'Số điểm rút tối thiểu là 50.']);
            exit;
        }
        if (empty($bank_info)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập thông tin ngân hàng.']);
            exit;
        }

        $result = requestWithdrawal($user_id, $points, $bank_info);
        echo json_encode($result);
        break;

    case 'accept_offer':
        $offer_id = intval($_POST['offer_id'] ?? 0);
        $result = acceptTutorOffer($user_id, $offer_id);
        echo json_encode($result);
        break;

    case 'student_extend_time':
        $request_id = intval($_POST['request_id'] ?? 0);
        $hours = intval($_POST['hours'] ?? 0);
        
        if (!$request_id || $hours <= 0) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
            exit;
        }

        $request = getRequestDetails($request_id);
        if (!$request || $request['student_id'] != $user_id) {
             echo json_encode(['success' => false, 'message' => 'Không có quyền.']);
             exit;
        }

        $pdo = getTutorDBConnection();
        try {
            $pdo->beginTransaction();

            // Update Request SLA only (No points involved)
            $stmt = $pdo->prepare("UPDATE tutor_requests SET sla_deadline = DATE_ADD(sla_deadline, INTERVAL ? HOUR) WHERE id = ?");
            $stmt->execute([$hours, $request_id]);
            
            $pdo->commit();

            // Notify Tutor
            global $VSD;
            $VSD->insert('notifications', [
                'user_id' => $request['tutor_id'],
                'title' => 'Thời gian được gia hạn',
                'message' => "Học viên đã gia hạn thêm {$hours} giờ cho yêu cầu #$request_id",
                'type' => 'tutor_extended',
                'ref_id' => $request_id
            ]);

            echo json_encode(['success' => true, 'message' => "Đã gia hạn thêm {$hours} giờ thành công!"]);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        }
        break;



    default:
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
        break;
}
?>
