<?php
/**
 * Telegram Webhook Handler
 * Xử lý các callback từ Telegram Bot (Duyệt/Từ chối tài liệu, gia sư, v.v.)
 */

// Đặt Secret Token để bảo mật (Kẻ gian không biết token này sẽ không giả mạo được request)
define('TELEGRAM_WEBHOOK_TOKEN', 'vsd_secure_callback_2026'); 

if (!isset($_GET['token']) || $_GET['token'] !== TELEGRAM_WEBHOOK_TOKEN) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';
require_once __DIR__ . '/../config/tutor.php';
require_once __DIR__ . '/../config/telegram_notifications.php';

// Lấy webhook data từ Telegram
$content = file_get_contents('php://input');
$update = json_decode($content, true);

if (!$update) {
    exit;
}

$bot_token = getTelegramBotToken();

/**
 * Hàm gửi request nhanh tới Telegram
 */
function tgRequest($method, $params = []) {
    global $bot_token;
    $api_url = "https://api.telegram.org/bot{$bot_token}/{$method}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// 1. XỬ LÝ CALLBACK QUERY (Khi bấm nút)
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $data = $callback['data'];
    $chat_id = $callback['message']['chat']['id'];
    $message_id = $callback['message']['message_id'];
    $tg_user_id = $callback['from']['id'];

    // KIỂM TRA BẢO MẬT: Chỉ cho phép admin thực hiện
    // Lấy danh sách admin telegram IDs từ settings (dấu phẩy ngăn cách)
    $allowed_ids = explode(',', getSetting('telegram_admin_ids', ''));
    if (!in_array($tg_user_id, $allowed_ids)) {
        tgRequest('answerCallbackQuery', [
            'callback_query_id' => $callback['id'],
            'text' => '❌ Bạn không có quyền thực hiện hành động này!',
            'show_alert' => true
        ]);
        exit;
    }

    $parts = explode(':', $data);
    $action = $parts[0];
    $target_id = intval($parts[1]);

    switch ($action) {
        case 'approve_doc':
            // Yêu cầu nhập điểm
            tgRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "👉 <b>Duyệt tài liệu #{$target_id}</b>\n\nVui lòng <b>Phản hồi (Reply)</b> tin nhắn này với định dạng:\n<code>Điểm:Nhận xét</code>\n\nVí dụ: <code>50:Tài liệu chất lượng</code>",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['force_reply' => true])
            ]);
            tgRequest('answerCallbackQuery', ['callback_query_id' => $callback['id']]);
            break;

        case 'reject_doc':
            // Yêu cầu nhập lý do từ chối
            tgRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "👉 <b>Từ chối tài liệu #{$target_id}</b>\n\nVui lòng <b>Phản hồi (Reply)</b> tin nhắn này với lý do từ chối.",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['force_reply' => true])
            ]);
            tgRequest('answerCallbackQuery', ['callback_query_id' => $callback['id']]);
            break;

        case 'approve_tutor':
            // Phê duyệt gia sư ngay lập tức (mặc định)
            $pdo = getTutorDBConnection();
            $stmt = $pdo->prepare("UPDATE tutors SET status = 'active' WHERE id = ?");
            $stmt->execute([$target_id]);
            
            // Lấy UID để thông báo
            $stmt = $pdo->prepare("SELECT user_id FROM tutors WHERE id = ?");
            $stmt->execute([$target_id]);
            $uid = $stmt->fetchColumn();
            
            if ($uid) {
                global $VSD;
                $VSD->insert('notifications', [
                    'user_id' => $uid,
                    'title' => 'Đăng ký Gia sư thành công',
                    'message' => 'Chúc mừng! Bạn đã chính thức trở thành Gia sư trên hệ thống.',
                    'type' => 'role_updated'
                ]);
            }

            tgRequest('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => $callback['message']['text'] . "\n\n✅ <b>Đã kích hoạt gia sư #{$target_id}</b>",
                'parse_mode' => 'HTML'
            ]);
            tgRequest('answerCallbackQuery', ['callback_query_id' => $callback['id'], 'text' => 'Đã kích hoạt gia sư!']);
            break;

        case 'reject_tutor':
            // Từ chối hồ sơ gia sư
            tgRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "👉 <b>Từ chối hồ sơ Gia sư #{$target_id}</b>\n\nVui lòng <b>Phản hồi (Reply)</b> tin nhắn này với lý do từ chối.",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(['force_reply' => true])
            ]);
            tgRequest('answerCallbackQuery', ['callback_query_id' => $callback['id']]);
            break;

        case 'dismiss_report':
            // Bỏ qua báo cáo
            $admin_result = mysqli_query($conn, "SELECT id FROM users WHERE role='admin' LIMIT 1");
            $admin_id = mysqli_fetch_assoc($admin_result)['id'] ?? 1;
            
            mysqli_query($conn, "UPDATE reports SET status='dismissed', reviewed_by=$admin_id, reviewed_at=NOW(), admin_notes='Bỏ qua qua Telegram' WHERE id=$target_id");
            
            tgRequest('editMessageText', [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => $callback['message']['text'] . "\n\n✅ <b>Đã bỏ qua báo cáo #{$target_id}</b>",
                'parse_mode' => 'HTML'
            ]);
            tgRequest('answerCallbackQuery', ['callback_query_id' => $callback['id'], 'text' => 'Đã bỏ qua báo cáo!']);
            break;

        case 'delete_doc_report':
            // Xóa tài liệu từ báo cáo
            $report = mysqli_fetch_assoc(mysqli_query($conn, "SELECT document_id FROM reports WHERE id=$target_id"));
            if ($report && $report['document_id']) {
                $doc_id = $report['document_id'];
                $doc = mysqli_fetch_assoc(mysqli_query($conn, "SELECT file_name FROM documents WHERE id=$doc_id"));
                
                if ($doc) {
                    $file_path = __DIR__ . "/../uploads/" . $doc['file_name'];
                    if (file_exists($file_path)) unlink($file_path);
                    mysqli_query($conn, "DELETE FROM documents WHERE id=$doc_id");
                    
                    $admin_result = mysqli_query($conn, "SELECT id FROM users WHERE role='admin' LIMIT 1");
                    $admin_id = mysqli_fetch_assoc($admin_result)['id'] ?? 1;
                    mysqli_query($conn, "UPDATE reports SET status='reviewed', reviewed_by=$admin_id, reviewed_at=NOW(), admin_notes='Đã xóa tài liệu qua Telegram' WHERE id=$target_id");

                    tgRequest('editMessageText', [
                        'chat_id' => $chat_id,
                        'message_id' => $message_id,
                        'text' => $callback['message']['text'] . "\n\n🗑️ <b>Đã xóa tài liệu và đóng báo cáo #{$target_id}</b>",
                        'parse_mode' => 'HTML'
                    ]);
                    tgRequest('answerCallbackQuery', ['callback_query_id' => $callback['id'], 'text' => 'Đã xóa tài liệu!']);
                }
            } else {
                tgRequest('answerCallbackQuery', ['callback_query_id' => $callback['id'], 'text' => 'Lỗi: Không tìm thấy tài liệu!', 'show_alert' => true]);
            }
            break;
    }
}

// 2. XỬ LÝ MESSAGE REPLY (Khi admin nhập nội dung phản hồi)
if (isset($update['message']['reply_to_message'])) {
    $msg = $update['message'];
    $reply_to = $msg['reply_to_message']['text'];
    $input_text = $msg['text'];
    $chat_id = $msg['chat']['id'];
    $tg_user_id = $msg['from']['id'];

    // Kiểm tra bảo mật
    $allowed_ids = explode(',', getSetting('telegram_admin_ids', ''));
    if (!in_array($tg_user_id, $allowed_ids)) exit;

    // Phân tích xem đang reply cho hành động nào
    if (preg_match('/Duyệt tài liệu #(\d+)/', $reply_to, $matches)) {
        $doc_id = intval($matches[1]);
        $parts = explode(':', $input_text, 2);
        $points = intval($parts[0] ?? 0);
        $notes = trim($parts[1] ?? 'Duyệt bởi Admin qua Telegram');

        // Thực hiện duyệt tài liệu
        // Lấy admin_id (giả định admin đầu tiên trong DB hoặc map tg_user với user_id)
        // Ở đây ta dùng user_id = 1 hoặc tìm admin đầu tiên để làm người duyệt
        $admin_result = mysqli_query($conn, "SELECT id FROM users WHERE role='admin' LIMIT 1");
        $admin_data = mysqli_fetch_assoc($admin_result);
        $admin_id = $admin_data['id'] ?? 1;

        if (approveDocument($doc_id, $admin_id, $points, $notes)) {
            tgRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "✅ <b>Thành công!</b>\nĐã duyệt tài liệu #{$doc_id}\nĐiểm: {$points}\nNhận xét: {$notes}",
                'parse_mode' => 'HTML'
            ]);
        } else {
            tgRequest('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ Thất bại khi duyệt tài liệu #{$doc_id}"]);
        }
    } 
    elseif (preg_match('/Từ chối tài liệu #(\d+)/', $reply_to, $matches)) {
        $doc_id = intval($matches[1]);
        $reason = trim($input_text);
        
        $admin_result = mysqli_query($conn, "SELECT id FROM users WHERE role='admin' LIMIT 1");
        $admin_data = mysqli_fetch_assoc($admin_result);
        $admin_id = $admin_data['id'] ?? 1;

        if (rejectDocument($doc_id, $admin_id, $reason)) {
            tgRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "❌ <b>Đã từ chối tài liệu #{$doc_id}</b>\nLý do: {$reason}",
                'parse_mode' => 'HTML'
            ]);
        }
    }
    elseif (preg_match('/Từ chối hồ sơ Gia sư #(\d+)/', $reply_to, $matches)) {
        $tutor_id = intval($matches[1]);
        $reason = trim($input_text);
        
        $pdo = getTutorDBConnection();
        $stmt = $pdo->prepare("UPDATE tutors SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$tutor_id]);
        
        // Thông báo cho gia sư
        $stmt = $pdo->prepare("SELECT user_id FROM tutors WHERE id = ?");
        $stmt->execute([$tutor_id]);
        $uid = $stmt->fetchColumn();
        
        if ($uid) {
            global $VSD;
            $VSD->insert('notifications', [
                'user_id' => $uid,
                'title' => 'Hồ sơ Gia sư bị từ chối',
                'message' => 'Hồ sơ của bạn không được chấp nhận. Lý do: ' . $reason,
                'type' => 'role_updated'
            ]);
        }

        tgRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "❌ <b>Đã từ chối gia sư #{$tutor_id}</b>\nLý do: {$reason}",
            'parse_mode' => 'HTML'
        ]);
    }
}

http_response_code(200);
echo "OK";
