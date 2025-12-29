<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/telegram_notifications.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Thông báo - Admin Panel";

// Handle AJAX check for new notifications
if(isset($_GET['ajax']) && $_GET['ajax'] === 'check') {
    header('Content-Type: application/json');
    $last_id = intval($_GET['last_id'] ?? 0);
    $new_notifications = mysqli_query($conn, "
        SELECT COUNT(*) as count, MAX(id) as max_id
        FROM admin_notifications
        WHERE admin_id=$admin_id AND id > $last_id AND is_read=0
    ");
    $new_data = mysqli_fetch_assoc($new_notifications);
    
    $unread_count = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(*) as count
        FROM admin_notifications
        WHERE admin_id=$admin_id AND is_read=0
    "));
    
    echo json_encode([
        'new_count' => intval($new_data['count']),
        'unread_count' => intval($unread_count['count']),
        'last_id' => intval($new_data['max_id'] ?? $last_id)
    ]);
    mysqli_close($conn);
    exit;
}

// Handle mark as read
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_read'])) {
    header('Content-Type: application/json');
    $notification_id = intval($_POST['notification_id']);
    mysqli_query($conn, "UPDATE admin_notifications SET is_read=1 WHERE id=$notification_id AND admin_id=$admin_id");
    echo json_encode(['success' => true]);
    exit;
}

// Handle mark all as read
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_all_read'])) {
    mysqli_query($conn, "UPDATE admin_notifications SET is_read=1 WHERE admin_id=$admin_id AND is_read=0");
    header("Location: notifications.php?msg=all_read");
    exit;
}

// Handle delete notification
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_notification'])) {
    header('Content-Type: application/json');
    $notification_id = intval($_POST['notification_id']);
    mysqli_query($conn, "DELETE FROM admin_notifications WHERE id=$notification_id AND admin_id=$admin_id");
    echo json_encode(['success' => true]);
    exit;
}

// Handle test notification
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_notification'])) {
    header('Content-Type: application/json');
    require_once __DIR__ . '/../config/notifications.php';
    
    $test_messages = [
        ['type' => 'new_document', 'msg' => 'Test: Tài liệu mới "Sample Document.pdf" được upload bởi user John Doe'],
        ['type' => 'document_sold', 'msg' => 'Test: Tài liệu "Research Paper.docx" đã được mua với 150 điểm'],
        ['type' => 'system_alert', 'msg' => 'Test: Thông báo hệ thống - Đây là thông báo test'],
    ];
    
    $random_test = $test_messages[array_rand($test_messages)];
    $message = $random_test['msg'];
    $type = $random_test['type'];
    
    // Use unified notification sender which will send to Telegram if enabled
    $result = sendAdminNotification($admin_id, $type, $message, null);
    
    if($result['success']) {
        $response = ['success' => true, 'message' => 'Test notification created successfully!'];
        if($result['telegram_sent']) {
            $response['telegram_sent'] = true;
            $response['message'] = 'Test notification đã được tạo và gửi đến Telegram thành công!';
        } else {
            $response['telegram_sent'] = false;
            $response['message'] = 'Test notification đã được tạo. Telegram chưa được gửi (có thể chưa bật hoặc chưa cấu hình).';
        }
        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create test notification']);
    }
    exit;
}

// Handle save settings
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $settings_to_save = json_decode($input, true);
    
    // Check if this is a settings save request (has JSON data with settings keys)
    if ($settings_to_save && (isset($settings_to_save['notify_browser_push_enabled']) || isset($settings_to_save['site_name']))) {
        header('Content-Type: application/json');
        
        $errors = [];
        foreach ($settings_to_save as $name => $value) {
            // Determine category based on setting name
            $category = 'general';
            if (strpos($name, 'notify_') === 0 || strpos($name, 'telegram_') === 0) {
                $category = strpos($name, 'telegram_') === 0 ? 'telegram' : 'notifications';
            } elseif (strpos($name, 'site_') === 0) {
                $category = 'site';
            }
            
            if (!setSetting($name, $value, null, $category)) {
                $errors[] = "Failed to save setting: $name";
            }
        }
        
        if (empty($errors)) {
            echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        }
        exit;
    }
}

// Handle test Telegram
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_telegram'])) {
    header('Content-Type: application/json');
    $result = testTelegramConnection();
    echo json_encode($result);
    exit;
}

// Load current settings for display
$notification_settings = getSettingsByCategory('notifications');
$telegram_settings = getSettingsByCategory('telegram');
$site_settings = getSettingsByCategory('site');

// Get filter parameters
$filter = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : 'all';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_clauses = ["admin_id=$admin_id"];
if($filter === 'unread') {
    $where_clauses[] = "is_read=0";
} elseif($filter === 'read') {
    $where_clauses[] = "is_read=1";
}
$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

// Get total count
$total_query = "SELECT COUNT(*) as total FROM admin_notifications $where_sql";
$total_result = mysqli_fetch_assoc(mysqli_query($conn, $total_query));
$total_notifications = $total_result['total'];
$total_pages = ceil($total_notifications / $per_page);

// Get notifications
$notifications_query = "
    SELECT an.*, 
           d.original_name, d.id as document_id,
           u.username as document_owner
    FROM admin_notifications an
    LEFT JOIN documents d ON an.document_id = d.id
    LEFT JOIN users u ON d.user_id = u.id
    $where_sql
    ORDER BY an.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$notifications = mysqli_query($conn, $notifications_query);

// Get statistics
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_notifications,
        SUM(CASE WHEN is_read=0 THEN 1 ELSE 0 END) as unread_count,
        SUM(CASE WHEN is_read=1 THEN 1 ELSE 0 END) as read_count,
        SUM(CASE WHEN notification_type='new_document' THEN 1 ELSE 0 END) as new_doc_count,
        SUM(CASE WHEN notification_type='document_sold' THEN 1 ELSE 0 END) as sold_count,
        SUM(CASE WHEN notification_type='system_alert' THEN 1 ELSE 0 END) as alert_count
    FROM admin_notifications
    WHERE admin_id=$admin_id
"));

$unread_notifications = $stats['unread_count'] ?? 0;

// For shared admin sidebar
$admin_active_page = 'notifications';

// Include header
include __DIR__ . '/../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="p-6 bg-base-100 border-b border-base-300">
    <div class="container mx-auto max-w-7xl">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-bell"></i>
                    Thông báo
                    <?php if($unread_notifications > 0): ?>
                        <span class="badge badge-error"><?= $unread_notifications ?> chưa đọc</span>
                    <?php endif; ?>
                </h2>
            </div>
            <div class="flex gap-2">
                <button type="button" class="btn btn-info" onclick="openSettingsModal()">
                    <i class="fa-solid fa-gear mr-2"></i>
                    Cài đặt thông báo
                </button>
                <button type="button" class="btn btn-warning" onclick="testNotification()">
                    <i class="fa-solid fa-flask mr-2"></i>
                    Test
                </button>
                <?php if($unread_notifications > 0): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="mark_all_read" value="1">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-check-double mr-2"></i>
                            Đánh dấu tất cả đã đọc
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Page Body -->
<div class="p-6">
    <div class="container mx-auto max-w-7xl">
        <!-- Status Messages -->
        <?php if(isset($_GET['msg']) && $_GET['msg'] === 'all_read'): ?>
            <div class="alert alert-success mb-4">
                <i class="fa-solid fa-check-circle"></i>
                <span>Tất cả thông báo đã được đánh dấu là đã đọc!</span>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Chưa đọc</div>
                    <div class="stat-value text-error text-3xl font-bold" id="unread-count"><?= $stats['unread_count'] ?? 0 ?></div>
                </div>
            </div>
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Tổng thông báo</div>
                    <div class="stat-value text-primary text-3xl font-bold"><?= $stats['total_notifications'] ?? 0 ?></div>
                </div>
            </div>
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Tài liệu mới</div>
                    <div class="stat-value text-info text-3xl font-bold"><?= $stats['new_doc_count'] ?? 0 ?></div>
                </div>
            </div>
        </div>

        <!-- Notifications List -->
        <div class="card bg-base-100 shadow">
            <div class="card-header bg-base-200 flex items-center justify-between">
                <h3 class="card-title">Danh sách thông báo</h3>
                <form method="GET">
                    <select name="filter" class="select select-bordered select-sm" onchange="this.form.submit()">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Tất cả</option>
                        <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>Chưa đọc</option>
                        <option value="read" <?= $filter === 'read' ? 'selected' : '' ?>>Đã đọc</option>
                    </select>
                </form>
            </div>

            <div id="notifications-list">
                <?php if(mysqli_num_rows($notifications) > 0): ?>
                    <div class="divide-y divide-base-300">
                        <?php while($notif = mysqli_fetch_assoc($notifications)): 
                            $type_info = [
                                'new_document' => ['icon' => 'fa-file-circle-plus', 'color' => 'bg-info', 'label' => 'Tài liệu mới'],
                                'document_sold' => ['icon' => 'fa-cart-shopping', 'color' => 'bg-success', 'label' => 'Đã bán'],
                                'system_alert' => ['icon' => 'fa-circle-exclamation', 'color' => 'bg-warning', 'label' => 'Hệ thống'],
                                'report' => ['icon' => 'fa-flag', 'color' => 'bg-error', 'label' => 'Báo cáo']
                            ];
                            $info = $type_info[$notif['notification_type']] ?? ['icon' => 'fa-bell', 'color' => 'bg-secondary', 'label' => 'Thông báo'];
                        ?>
                            <div class="p-4 <?= $notif['is_read'] ? '' : 'bg-info/10' ?>" id="notif-<?= $notif['id'] ?>">
                                <div class="flex items-start gap-4">
                                    <div class="avatar placeholder">
                                        <div class="<?= $info['color'] ?> text-white rounded w-12">
                                            <i class="fa-solid <?= $info['icon'] ?> text-xl"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="badge <?= $info['color'] ?>"><?= $info['label'] ?></span>
                                                <?php if(!$notif['is_read']): ?>
                                                    <span class="badge badge-error">NEW</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="text-base-content/70 text-sm">
                                                <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                                            </span>
                                        </div>
                                        <div class="text-base-content mb-2">
                                            <?= htmlspecialchars($notif['message']) ?>
                                        </div>
                                        <?php if($notif['document_id']): ?>
                                            <div class="text-base-content/70 text-sm mb-2">
                                                <i class="fa-solid fa-file mr-1"></i>
                                                <strong><?= htmlspecialchars($notif['original_name'] ?? 'Unknown') ?></strong>
                                                <?php if($notif['document_owner']): ?>
                                                    by <?= htmlspecialchars($notif['document_owner']) ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex gap-2">
                                            <?php if(!$notif['is_read']): ?>
                                                <button class="btn btn-sm btn-primary" onclick="markAsRead(<?= $notif['id'] ?>)">
                                                    <i class="fa-solid fa-check mr-1"></i>Đánh dấu đã đọc
                                                </button>
                                            <?php endif; ?>
                                            <?php if($notif['document_id']): ?>
                                                <a href="../view.php?id=<?= $notif['document_id'] ?>" target="_blank" class="btn btn-sm btn-ghost">
                                                    <i class="fa-solid fa-eye mr-1"></i>Xem tài liệu
                                                </a>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-ghost text-error" onclick="deleteNotification(<?= $notif['id'] ?>)">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="p-12 text-center">
                        <i class="fa-solid fa-bell-slash text-6xl text-base-content/30 mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Không có thông báo</h3>
                        <p class="text-base-content/70">Bạn đã xem hết tất cả thông báo.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
                <div class="card-footer flex items-center justify-between border-t border-base-300 p-4">
                    <div class="text-base-content/70 text-sm">
                        Hiển thị <span class="font-bold"><?= $offset + 1 ?></span> đến <span class="font-bold"><?= min($offset + $per_page, $total_notifications) ?></span> trong <span class="font-bold"><?= $total_notifications ?></span> kết quả
                    </div>
                    <div class="join">
                        <?php if($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&filter=<?= $filter ?>" class="join-item btn btn-sm">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&filter=<?= $filter ?>" 
                               class="join-item btn btn-sm <?= $i == $page ? 'btn-primary btn-active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&filter=<?= $filter ?>" class="join-item btn btn-sm">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Settings Modal -->
<dialog id="settings-modal" class="modal">
    <div class="modal-box w-11/12 max-w-5xl">
        <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        </form>
        <h3 class="font-bold text-lg mb-4">
            <i class="fa-solid fa-gear mr-2"></i>
            Cài đặt thông báo
        </h3>
        
        <!-- Tabs -->
        <div class="tabs tabs-boxed mb-4">
            <a class="tab tab-active" onclick="switchTab('notifications')">Thông báo</a>
            <a class="tab" onclick="switchTab('site')">Cài đặt Website</a>
        </div>
        
        <!-- Notifications Tab -->
        <div id="tab-notifications" class="tab-panel">
            <div class="space-y-6">
                <!-- Global Settings -->
                <div class="card bg-base-200">
                    <div class="card-body">
                        <h4 class="font-bold mb-4">Cài đặt chung</h4>
                        <div class="space-y-4">
                            <label class="label cursor-pointer">
                                <span class="label-text">Bật thông báo Browser Push</span>
                                <input type="checkbox" class="toggle toggle-primary" id="notify_browser_push_enabled" 
                                       <?= isSettingEnabled('notify_browser_push_enabled') ? 'checked' : '' ?>>
                            </label>
                            <label class="label cursor-pointer">
                                <span class="label-text">Bật thông báo Telegram</span>
                                <input type="checkbox" class="toggle toggle-primary" id="notify_telegram_enabled" 
                                       <?= isSettingEnabled('notify_telegram_enabled') ? 'checked' : '' ?>>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Telegram Configuration -->
                <div class="card bg-base-200">
                    <div class="card-body">
                        <h4 class="font-bold mb-4">Cấu hình Telegram</h4>
                        <div class="space-y-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Telegram Bot Token</span>
                                </label>
                                <input type="text" class="input input-bordered" id="telegram_bot_token" 
                                       value="<?= htmlspecialchars(getSetting('telegram_bot_token', '')) ?>" 
                                       placeholder="Nhập Bot Token từ @BotFather">
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Telegram Chat ID</span>
                                </label>
                                <input type="text" class="input input-bordered" id="telegram_chat_id" 
                                       value="<?= htmlspecialchars(getSetting('telegram_chat_id', '')) ?>" 
                                       placeholder="Nhập Chat ID của group/channel">
                            </div>
                            <div class="form-control">
                                <label class="label cursor-pointer">
                                    <span class="label-text">Bật Telegram</span>
                                    <input type="checkbox" class="toggle toggle-primary" id="telegram_enabled" 
                                           <?= isSettingEnabled('telegram_enabled') ? 'checked' : '' ?>>
                                </label>
                            </div>
                            <button type="button" class="btn btn-info" onclick="testTelegram()">
                                <i class="fa-solid fa-paper-plane mr-2"></i>
                                Test Telegram
                            </button>
                            <div id="telegram-test-result" class="mt-2"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Notification Type Settings -->
                <div class="card bg-base-200">
                    <div class="card-body">
                        <h4 class="font-bold mb-4">Cài đặt theo loại</h4>
                        <div class="space-y-4">
                            <?php 
                            $notification_types = [
                                'new_document' => ['label' => 'Tài liệu mới', 'icon' => 'fa-file-circle-plus'],
                                'document_sold' => ['label' => 'Tài liệu đã bán', 'icon' => 'fa-cart-shopping'],
                                'system_alert' => ['label' => 'Cảnh báo hệ thống', 'icon' => 'fa-circle-exclamation'],
                                'report' => ['label' => 'Báo cáo mới', 'icon' => 'fa-flag']
                            ];
                            foreach ($notification_types as $type => $info): 
                            ?>
                                <div class="border border-base-300 rounded-lg p-4">
                                    <div class="flex items-center gap-2 mb-3">
                                        <i class="fa-solid <?= $info['icon'] ?>"></i>
                                        <span class="font-semibold"><?= $info['label'] ?></span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <label class="label cursor-pointer">
                                            <span class="label-text">Browser Push</span>
                                            <input type="checkbox" class="toggle toggle-success" 
                                                   id="notify_<?= $type ?>_browser" 
                                                   <?= isSettingEnabled('notify_' . $type . '_browser') ? 'checked' : '' ?>>
                                        </label>
                                        <label class="label cursor-pointer">
                                            <span class="label-text">Telegram</span>
                                            <input type="checkbox" class="toggle toggle-info" 
                                                   id="notify_<?= $type ?>_telegram" 
                                                   <?= isSettingEnabled('notify_' . $type . '_telegram') ? 'checked' : '' ?>>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Site Settings Tab -->
        <div id="tab-site" class="tab-panel hidden">
            <div class="space-y-4">
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Tên website</span>
                    </label>
                    <input type="text" class="input input-bordered" id="site_name" 
                           value="<?= htmlspecialchars(getSetting('site_name', 'DocShare')) ?>">
                </div>
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Logo (đường dẫn)</span>
                    </label>
                    <input type="text" class="input input-bordered" id="site_logo" 
                           value="<?= htmlspecialchars(getSetting('site_logo', '')) ?>" 
                           placeholder="/path/to/logo.png">
                </div>
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Mô tả</span>
                    </label>
                    <textarea class="textarea textarea-bordered" id="site_description" rows="3"><?= htmlspecialchars(getSetting('site_description', '')) ?></textarea>
                </div>
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Từ khóa (SEO)</span>
                    </label>
                    <input type="text" class="input input-bordered" id="site_keywords" 
                           value="<?= htmlspecialchars(getSetting('site_keywords', '')) ?>" 
                           placeholder="keyword1, keyword2, keyword3">
                </div>
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">Tác giả</span>
                    </label>
                    <input type="text" class="input input-bordered" id="site_author" 
                           value="<?= htmlspecialchars(getSetting('site_author', '')) ?>">
                </div>
            </div>
        </div>
        
        <div class="modal-action">
            <button type="button" class="btn btn-primary" onclick="saveSettings()">
                <i class="fa-solid fa-save mr-2"></i>
                Lưu cài đặt
            </button>
            <form method="dialog">
                <button class="btn">Đóng</button>
            </form>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
    // Auto-refresh notifications every 30 seconds
    let refreshInterval = setInterval(checkNewNotifications, 30000);
    let lastNotificationId = 0;

    function checkNewNotifications() {
        fetch('notifications.php?ajax=check&last_id=' + lastNotificationId)
            .then(response => response.json())
            .then(data => {
                if(data.last_id) {
                    lastNotificationId = data.last_id;
                }
                
                if(data.new_count > 0) {
                    // Check if browser push is enabled (would need to check settings, but for now just check permission)
                    if(Notification.permission === 'granted') {
                        showBrowserNotification(
                            'DocShare Admin - Thông báo mới',
                            `Bạn có ${data.new_count} thông báo mới chưa đọc`,
                            '/favicon.ico',
                            'admin-notification',
                            { url: 'notifications.php?filter=unread' }
                        );
                    }
                    document.getElementById('unread-count').textContent = data.unread_count;
                    if(window.location.search.includes('filter=unread') || window.location.search.includes('filter=all') || window.location.search === '') {
                        window.location.reload();
                    }
                }
            })
            .catch(error => console.error('Error checking notifications:', error));
    }

    function markAsRead(notificationId) {
        const formData = new FormData();
        formData.append('mark_read', '1');
        formData.append('notification_id', notificationId);

        fetch('notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                const notifElement = document.getElementById('notif-' + notificationId);
                notifElement.classList.remove('bg-info/10');
                notifElement.querySelector('.badge.badge-error')?.remove();
                const btnContainer = notifElement.querySelector('.flex.gap-2');
                const markReadBtn = btnContainer.querySelector('button.btn-primary');
                if(markReadBtn) markReadBtn.remove();
                
                const unreadCount = parseInt(document.getElementById('unread-count').textContent);
                document.getElementById('unread-count').textContent = Math.max(0, unreadCount - 1);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function deleteNotification(notificationId) {
        if(confirm('Bạn có chắc muốn xóa thông báo này?')) {
            const formData = new FormData();
            formData.append('delete_notification', '1');
            formData.append('notification_id', notificationId);

            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('notif-' + notificationId).remove();
                }
            })
            .catch(error => console.error('Error:', error));
        }
    }

    function testNotification() {
        // Check if Notification API is supported
        if (!('Notification' in window)) {
            alert('Trình duyệt của bạn không hỗ trợ thông báo!');
            return;
        }
        
        // Request permission if not granted
        if (Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    createTestNotification();
                } else if (permission === 'denied') {
                    alert('Thông báo đã bị chặn. Vui lòng bật lại trong cài đặt trình duyệt.');
                }
            });
        } else if (Notification.permission === 'denied') {
            alert('Thông báo đã bị chặn. Vui lòng bật lại trong cài đặt trình duyệt.');
        } else {
            // Permission is granted, create test notification
            createTestNotification();
        }
    }
    
    function createTestNotification() {
        const formData = new FormData();
        formData.append('test_notification', '1');

        fetch('notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                // Show browser notification
                if(Notification.permission === 'granted') {
                    showBrowserNotification(
                        'DocShare Admin - Test Notification',
                        'Đây là thông báo test. Hệ thống hoạt động bình thường!',
                        '/favicon.ico',
                        'test-notification'
                    );
                }
                
                // Show success message with Telegram status
                let successMsg = data.message || 'Thông báo test đã được tạo thành công!';
                if(data.telegram_sent !== undefined) {
                    if(data.telegram_sent) {
                        successMsg += '\n\n✓ Đã gửi đến Telegram thành công!';
                    } else {
                        successMsg += '\n\n⚠ Telegram chưa được gửi (có thể chưa bật hoặc chưa cấu hình trong Cài đặt thông báo).';
                    }
                }
                alert(successMsg);
                setTimeout(() => window.location.reload(), 1000);
            } else {
                alert('Lỗi tạo thông báo test: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Lỗi khi tạo thông báo test');
        });
    }

    // Request notification permission with better UX
    let notificationPermission = 'default';
    if('Notification' in window) {
        notificationPermission = Notification.permission;
        
        if(notificationPermission === 'default') {
            // Show a friendly message to request permission
            const permissionBadge = document.createElement('div');
            permissionBadge.id = 'permission-badge';
            permissionBadge.className = 'alert alert-info mb-4';
            permissionBadge.innerHTML = `
                <i class="fa-solid fa-info-circle"></i>
                <span>Để nhận thông báo, vui lòng cho phép trình duyệt hiển thị thông báo.</span>
                <button class="btn btn-sm btn-primary ml-2" onclick="requestNotificationPermission()">Cho phép</button>
            `;
            const pageBody = document.querySelector('.p-6');
            if(pageBody) {
                pageBody.insertBefore(permissionBadge, pageBody.firstChild);
            }
        } else if(notificationPermission === 'denied') {
            const permissionBadge = document.createElement('div');
            permissionBadge.id = 'permission-badge';
            permissionBadge.className = 'alert alert-warning mb-4';
            permissionBadge.innerHTML = `
                <i class="fa-solid fa-exclamation-triangle"></i>
                <span>Thông báo đã bị chặn. Vui lòng bật lại trong cài đặt trình duyệt.</span>
            `;
            const pageBody = document.querySelector('.p-6');
            if(pageBody) {
                pageBody.insertBefore(permissionBadge, pageBody.firstChild);
            }
        }
    }
    
    function requestNotificationPermission() {
        if('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                if(permission === 'granted') {
                    const badge = document.getElementById('permission-badge');
                    if(badge) {
                        badge.className = 'alert alert-success mb-4';
                        badge.innerHTML = '<i class="fa-solid fa-check-circle"></i><span>Đã bật thông báo thành công!</span>';
                        setTimeout(() => badge.remove(), 3000);
                    }
                    // Show a test notification
                    new Notification('DocShare Admin', {
                        body: 'Thông báo đã được bật thành công!',
                        icon: '/favicon.ico',
                        tag: 'permission-granted'
                    });
                } else {
                    const badge = document.getElementById('permission-badge');
                    if(badge) {
                        badge.className = 'alert alert-error mb-4';
                        badge.innerHTML = '<i class="fa-solid fa-times-circle"></i><span>Thông báo đã bị từ chối.</span>';
                    }
                }
            });
        }
    }
    
    // Settings Modal Functions
    function openSettingsModal() {
        const modal = document.getElementById('settings-modal');
        modal.showModal();
        
        // Ensure first tab is visible
        document.querySelectorAll('.tab-panel').forEach((panel, index) => {
            if(index === 0) {
                panel.classList.remove('hidden');
            } else {
                panel.classList.add('hidden');
            }
        });
        
        // Ensure first tab is active
        document.querySelectorAll('.tab').forEach((tab, index) => {
            if(index === 0) {
                tab.classList.add('tab-active');
            } else {
                tab.classList.remove('tab-active');
            }
        });
    }
    
    function switchTab(tabName) {
        // Hide all tab panels
        document.querySelectorAll('.tab-panel').forEach(panel => {
            panel.classList.add('hidden');
        });
        // Remove active state from all tabs
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('tab-active');
        });
        
        // Show selected tab panel
        const selectedPanel = document.getElementById('tab-' + tabName);
        if(selectedPanel) {
            selectedPanel.classList.remove('hidden');
        }
        
        // Activate the clicked tab
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            const tabText = tab.textContent.trim();
            if((tabName === 'notifications' && tabText === 'Thông báo') || 
               (tabName === 'site' && tabText === 'Cài đặt Website')) {
                tab.classList.add('tab-active');
            }
        });
    }
    
    function saveSettings() {
        const settings = {};
        
        // Notification settings
        settings['notify_browser_push_enabled'] = document.getElementById('notify_browser_push_enabled').checked ? 'on' : 'off';
        settings['notify_telegram_enabled'] = document.getElementById('notify_telegram_enabled').checked ? 'on' : 'off';
        settings['telegram_bot_token'] = document.getElementById('telegram_bot_token').value;
        settings['telegram_chat_id'] = document.getElementById('telegram_chat_id').value;
        settings['telegram_enabled'] = document.getElementById('telegram_enabled').checked ? 'on' : 'off';
        
        // Notification type settings
        ['new_document', 'document_sold', 'system_alert', 'report'].forEach(type => {
            settings['notify_' + type + '_browser'] = document.getElementById('notify_' + type + '_browser').checked ? 'on' : 'off';
            settings['notify_' + type + '_telegram'] = document.getElementById('notify_' + type + '_telegram').checked ? 'on' : 'off';
        });
        
        // Site settings
        settings['site_name'] = document.getElementById('site_name').value;
        settings['site_logo'] = document.getElementById('site_logo').value;
        settings['site_description'] = document.getElementById('site_description').value;
        settings['site_keywords'] = document.getElementById('site_keywords').value;
        settings['site_author'] = document.getElementById('site_author').value;
        
        fetch('notifications.php', {
            method: 'POST',
            body: JSON.stringify(settings),
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Cài đặt đã được lưu thành công!');
                document.getElementById('settings-modal').close();
            } else {
                alert('Lỗi: ' + (data.message || 'Không thể lưu cài đặt'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Lỗi khi lưu cài đặt');
        });
    }
    
    function testTelegram() {
        const resultDiv = document.getElementById('telegram-test-result');
        resultDiv.innerHTML = '<div class="loading loading-spinner loading-sm"></div> Đang kiểm tra...';
        
        fetch('notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'test_telegram=1'
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                resultDiv.innerHTML = '<div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> ' + data.message + '</div>';
            } else {
                resultDiv.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-times-circle"></i> ' + data.message + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            resultDiv.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-times-circle"></i> Lỗi khi kiểm tra kết nối</div>';
        });
    }
    
    // Improved browser push notifications
    function showBrowserNotification(title, body, icon, tag, data = {}) {
        if('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification(title, {
                body: body,
                icon: icon || '/favicon.ico',
                badge: '/favicon.ico',
                tag: tag,
                requireInteraction: false,
                data: data
            });
            
            notification.onclick = function() {
                window.focus();
                if(data.url) {
                    window.location.href = data.url;
                } else {
                    window.location.href = 'notifications.php';
                }
                notification.close();
            };
            
            notification.onclose = function() {
                console.log('Notification closed');
            };
            
            // Auto close after 5 seconds
            setTimeout(() => {
                notification.close();
            }, 5000);
            
            return notification;
        }
        return null;
    }
    
    // Initialize lastNotificationId
    document.addEventListener('DOMContentLoaded', function() {
        const firstNotif = document.querySelector('[id^="notif-"]');
        if(firstNotif) {
            const notifId = firstNotif.id.replace('notif-', '');
            lastNotificationId = parseInt(notifId) || 0;
        }
    });

    window.addEventListener('beforeunload', () => {
        clearInterval(refreshInterval);
    });
</script>

<?php 
include __DIR__ . '/../includes/admin-footer.php';
mysqli_close($conn); 
?>
