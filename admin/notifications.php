<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

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
    $test_messages = [
        ['type' => 'new_document', 'msg' => 'Test: Tài liệu mới "Sample Document.pdf" được upload bởi user John Doe'],
        ['type' => 'document_sold', 'msg' => 'Test: Tài liệu "Research Paper.docx" đã được mua với 150 điểm'],
        ['type' => 'system_alert', 'msg' => 'Test: Thông báo hệ thống - Đây là thông báo test'],
    ];
    
    $random_test = $test_messages[array_rand($test_messages)];
    $message = mysqli_real_escape_string($conn, $random_test['msg']);
    $type = mysqli_real_escape_string($conn, $random_test['type']);
    
    mysqli_query($conn, "
        INSERT INTO admin_notifications (admin_id, notification_type, message, created_at) 
        VALUES ($admin_id, '$type', '$message', NOW())
    ");
    
    if(mysqli_affected_rows($conn) > 0) {
        echo json_encode(['success' => true, 'message' => 'Test notification created successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create test notification']);
    }
    exit;
}

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
                                'system_alert' => ['icon' => 'fa-circle-exclamation', 'color' => 'bg-warning', 'label' => 'Hệ thống']
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
                    if(Notification.permission === 'granted') {
                        new Notification('DocShare Admin - Thông báo mới', {
                            body: `Bạn có ${data.new_count} thông báo mới`,
                            icon: '/favicon.ico',
                            tag: 'admin-notification',
                            requireInteraction: false
                        });
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
        const formData = new FormData();
        formData.append('test_notification', '1');

        fetch('notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                if(Notification.permission === 'granted') {
                    new Notification('DocShare Admin - Test Notification', {
                        body: 'Đây là thông báo test. Hệ thống hoạt động bình thường!',
                        icon: '/favicon.ico',
                        tag: 'test-notification'
                    });
                }
                setTimeout(() => window.location.reload(), 500);
            } else {
                alert('Lỗi tạo thông báo test: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Lỗi khi tạo thông báo test');
        });
    }

    // Request notification permission
    if('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
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
