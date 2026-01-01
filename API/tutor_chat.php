<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';

header('Content-Type: application/json');

if (!isUserLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = getCurrentUserId();
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_chats':
        $chats = getActiveTutorChats($user_id);
        echo json_encode(['success' => true, 'chats' => $chats]);
        break;

    case 'get_messages':
        $request_id = $_GET['request_id'] ?? 0;
        $details = getRequestDetails($request_id);
        
        if (!$details || ($details['student_id'] != $user_id && $details['tutor_id'] != $user_id)) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }
        
        echo json_encode([
            'success' => true, 
            'messages' => $details['answers'], 
            'request' => [
                'id' => $details['id'],
                'student_id' => $details['student_id'],
                'package_type' => $details['package_type'],
                'title' => $details['title'],
                'initial_content' => $details['content'],
                'initial_attachment' => $details['attachment'],
                'created_at' => $details['created_at'],
                'student_name' => $details['student_name'],
                'status' => $details['status'],
                'other_party' => ($details['student_id'] == $user_id) ? $details['tutor_name'] : $details['student_name']
            ]
        ]);
        break;

    case 'poll_messages':
        $request_id = $_GET['request_id'] ?? 0;
        $last_id = $_GET['last_id'] ?? 0;
        
        $details = getRequestDetails($request_id);
        if (!$details || ($details['student_id'] != $user_id && $details['tutor_id'] != $user_id)) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            exit;
        }

        // Filter messages newer than last_id
        $new_messages = [];
        foreach($details['answers'] as $msg) {
            if($msg['id'] > $last_id) {
                $new_messages[] = $msg;
            }
        }

        echo json_encode([
            'success' => true,
            'messages' => $new_messages,
            'status' => $details['status']
        ]);
        break;

    case 'send_message':
        $request_id = $_POST['request_id'] ?? 0;
        $content = $_POST['message'] ?? '';
        
        if (empty($content) && !isset($_FILES['attachment'])) {
            echo json_encode(['success' => false, 'message' => 'Nội dung không được để trống']);
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
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $new_name)) {
                    $attachment = $new_name;
                }
            }
        }
        
        $result = sendTutorChatMessage($user_id, $request_id, $content, $attachment);
        echo json_encode($result);
        break;

    case 'finish_request':
        $request_id = $_POST['request_id'] ?? 0;
        $result = finishTutorRequest($user_id, $request_id);
        echo json_encode($result);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
        break;
}
