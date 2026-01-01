<?php
session_start();
require_once 'config/db.php';
require_once 'config/function.php';
require_once 'config/auth.php';
require_once 'push/send_push.php';

// Only admins can access this test page
if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

$message = "";
$status = "";
$details = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $target_username = $VSD->escape($_POST['username']);
    $title = $VSD->escape($_POST['title']);
    $body = $VSD->escape($_POST['body']);
    $url = $VSD->escape($_POST['url'] ?? '/history.php?tab=notifications');

    // Find user ID and their subscription status
    $user_row = $VSD->get_row("SELECT id FROM users WHERE username = '$target_username' LIMIT 1");
    
    if ($user_row) {
        $user_id = $user_row['id'];
        
        // Count active subscriptions
        $sub_count = $VSD->num_rows("SELECT id FROM push_subscriptions WHERE user_id = $user_id");

        // 1. Insert into database notifications (for the in-app list)
        $VSD->insert('notifications', [
            'user_id' => $user_id,
            'type' => 'test',
            'ref_id' => 0,
            'message' => $body,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        if ($sub_count > 0) {
            // 2. Send Web Push
            $push_result = sendPushToUser($user_id, [
                'title' => $title,
                'body' => $body,
                'url' => $url
            ]);

            if ($push_result) {
                $status = "success";
                $message = "Đã gửi thông báo thành công!";
                $details = "Thông báo đã được gửi tới <strong>$target_username</strong> trên <strong>$sub_count</strong> thiết bị.";
            } else {
                $status = "error";
                $message = "Lỗi khi gửi Push!";
                $details = "Hệ thống không thể kết nối tới Google/Mozilla Push Service.";
            }
        } else {
            $status = "warning";
            $message = "Đã lưu thông báo vào DB!";
            $details = "Người dùng <strong>$target_username</strong> chưa kích hoạt (ON) thông báo đẩy trên thiết bị nào.";
        }
    } else {
        $status = "error";
        $message = "Không tìm thấy người dùng!";
        $details = "Tên đăng nhập <strong>$target_username</strong> không tồn tại trong hệ thống.";
    }
}

// Get some recent users who have active push subscriptions
$active_users = $VSD->get_list("
    SELECT u.username, COUNT(ps.id) as device_count 
    FROM users u 
    JOIN push_subscriptions ps ON u.id = ps.user_id 
    GROUP BY u.id 
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="vi" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Center - DocShare Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; color: #1e293b; }
        .glass-panel { 
            background: rgba(255, 255, 255, 0.8); 
            backdrop-filter: blur(12px); 
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .gradient-text {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
</head>
<body class="min-h-screen py-12 px-4">

    <div class="max-w-xl mx-auto">
        <div class="glass-panel p-8 rounded-3xl shadow-xl">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center">
                    <i class="fa-solid fa-bell-circle-exclamation text-2xl text-blue-600"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold gradient-text">Notification Center</h1>
                    <p class="text-sm text-slate-500 font-medium">Kiểm tra hệ thống Web Push Notifications</p>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="mb-8 p-4 rounded-2xl border flex gap-4 items-start 
                <?= $status === 'success' ? 'bg-emerald-50 border-emerald-100 text-emerald-800' : ($status === 'warning' ? 'bg-amber-50 border-amber-100 text-amber-800' : 'bg-rose-50 border-rose-100 text-rose-800') ?>">
                <div class="mt-1">
                    <?php if($status === 'success'): ?>
                        <i class="fa-solid fa-circle-check text-xl"></i>
                    <?php elseif($status === 'warning'): ?>
                        <i class="fa-solid fa-circle-exclamation text-xl"></i>
                    <?php else: ?>
                        <i class="fa-solid fa-circle-xmark text-xl"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <p class="font-bold text-lg"><?= $message ?></p>
                    <p class="text-sm opacity-90"><?= $details ?></p>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold text-slate-700">Gửi đến (Username)</span>
                    </label>
                    <div class="relative">
                        <i class="fa-solid fa-at absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="username" list="active-list" placeholder="Nhập username..." 
                               class="input input-bordered w-full pl-12 bg-slate-50 border-slate-200 focus:border-blue-500 font-medium" required>
                        <datalist id="active-list">
                            <?php foreach($active_users as $au): ?>
                                <option value="<?= htmlspecialchars($au['username']) ?>">Thiết bị: <?= $au['device_count'] ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-6">
                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold text-slate-700">Tiêu đề thông báo</span>
                        </label>
                        <input type="text" name="title" value="🔔 Bạn có thông báo mới!" 
                               class="input input-bordered w-full bg-slate-50 border-slate-200" required>
                    </div>

                    <div class="form-control">
                        <label class="label">
                            <span class="label-text font-semibold text-slate-700">Nội dung hiển thị</span>
                        </label>
                        <textarea name="body" class="textarea textarea-bordered w-full h-24 bg-slate-50 border-slate-200" 
                                  placeholder="Nội dung gửi tới người dùng..." required>DocShare: Có ai đó vừa tải tài liệu của bạn!</textarea>
                    </div>
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold text-slate-700">Đường dẫn khi click (URL)</span>
                    </label>
                    <div class="join w-full">
                        <span class="join-item btn bg-slate-200 border-slate-200 no-animation">/</span>
                        <input type="text" name="url" value="history.php?tab=notifications" 
                               class="input input-bordered join-item w-full bg-slate-50 border-slate-200" placeholder="dashboard">
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="btn btn-primary w-full h-14 rounded-2xl text-white font-bold shadow-lg shadow-blue-200 gap-2 overflow-hidden relative group">
                        <span class="relative z-10 flex items-center gap-2">
                            <i class="fa-solid fa-paper-plane group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform"></i>
                            GỬI THÔNG BÁO TEST
                        </span>
                    </button>
                    <p class="text-center text-[11px] text-slate-400 mt-4 leading-relaxed italic">
                        Lưu ý: Thông báo sẽ được lưu vào lịch sử thông báo của người dùng<br>
                        và được gửi thông qua giao thức Web Push (VAPID).
                    </p>
                </div>
            </form>

            <div class="mt-8 pt-8 border-t border-slate-100">
                <div class="flex justify-between items-center bg-slate-50 p-4 rounded-2xl">
                    <a href="/admin/index.php" class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition-colors flex items-center gap-2">
                        <i class="fa-solid fa-arrow-left"></i> Admin Dashboard
                    </a>
                    <div class="flex items-center gap-2 text-slate-300">
                        <span class="text-[10px] font-bold tracking-widest uppercase">VSD System</span>
                        <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
