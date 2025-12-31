<?php
session_start();
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
            'package_type' => $package
        ];
        
        // Handle file upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
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

    case 'answer_request':
        // Check if user is actually a tutor
        if (!isTutor($user_id)) {
            echo json_encode(['success' => false, 'message' => 'Bạn không phải là gia sư.']);
            exit;
        }

        $request_id = intval($_POST['request_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        
        if (!$request_id || empty($content)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập nội dung trả lời.']);
            exit;
        }

        $attachment = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'zip']; // Allow more for answers
            $filename = $_FILES['attachment']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $new_name = 'ans_' . uniqid() . '_' . time() . '.' . $ext;
                $upload_dir = __DIR__ . '/../uploads/tutors/';
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $new_name)) {
                    $attachment = $new_name;
                }
            }
        }

        // Logic handled in config/tutor.php
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

    default:
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ.']);
        break;
}
?>
