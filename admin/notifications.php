<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Notifications - Admin Panel";

// Handle mark as read
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_read'])) {
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
    $notification_id = intval($_POST['notification_id']);
    mysqli_query($conn, "DELETE FROM admin_notifications WHERE id=$notification_id AND admin_id=$admin_id");
    echo json_encode(['success' => true]);
    exit;
}

// Handle test notification
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_notification'])) {
    $test_messages = [
        ['type' => 'new_document', 'msg' => 'Test: New document "Sample Document.pdf" uploaded by user John Doe'],
        ['type' => 'document_sold', 'msg' => 'Test: Document "Research Paper.docx" was purchased for 150 points'],
        ['type' => 'system_alert', 'msg' => 'Test: System notification - This is a test alert message'],
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

$unread_notifications = $stats['unread_count'];

// For shared admin sidebar
$admin_active_page = 'notifications';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .admin-sidebar {
            width: 260px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
        }

        .admin-sidebar h2 {
            font-size: 20px;
            margin-bottom: 30px;
            text-align: center;
            border-bottom: 2px solid rgba(255,255,255,0.2);
            padding-bottom: 15px;
        }

        .admin-sidebar nav a {
            display: block;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            margin-bottom: 5px;
            border-radius: 5px;
            transition: all 0.3s;
            font-size: 14px;
        }

        .admin-sidebar nav a:hover,
        .admin-sidebar nav a.active {
            background: rgba(255,255,255,0.2);
        }

        .admin-sidebar .logout {
            margin-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 15px;
        }

        .admin-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .admin-header h1 {
            font-size: 24px;
            color: #333;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #764ba2;
        }

        .btn-secondary {
            background: #999;
            color: white;
        }

        .btn-secondary:hover {
            background: #777;
        }

        .btn-test {
            background: #ff9800;
            color: white;
        }

        .btn-test:hover {
            background: #f57c00;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }

        .stat-card h3 {
            font-size: 12px;
            color: #999;
            font-weight: 600;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-card.unread {
            border-left-color: #ff4444;
        }

        .stat-card.unread .value {
            color: #ff4444;
        }

        .content-section {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .content-section h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }

        .filter-bar select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .notification-item {
            padding: 15px;
            border-left: 4px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            background: #f9f9f9;
            transition: all 0.3s;
            position: relative;
        }

        .notification-item:hover {
            background: #f0f0f0;
        }

        .notification-item.unread {
            background: #fff;
            border-left-color: #667eea;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .notification-item.read {
            opacity: 0.7;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 8px;
        }

        .notification-title {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .notification-time {
            font-size: 12px;
            color: #999;
        }

        .notification-message {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
        }

        .notification-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }

        .action-btn-primary {
            background: #667eea;
            color: white;
        }

        .action-btn-primary:hover {
            background: #764ba2;
        }

        .action-btn-danger {
            background: #f44336;
            color: white;
        }

        .action-btn-danger:hover {
            background: #d32f2f;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        .badge-new {
            background: #ff4444;
            color: white;
        }

        .badge-sold {
            background: #4caf50;
            color: white;
        }

        .badge-alert {
            background: #ff9800;
            color: white;
        }

        .pagination {
            display: flex;
            gap: 5px;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .notification-bell {
            position: relative;
            display: inline-block;
        }

        .notification-bell .badge-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                width: 200px;
            }

            .admin-content {
                margin-left: 200px;
            }
        }
</style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <!-- Main Content -->
        <div class="admin-content">
            <!-- Header -->
            <div class="admin-header">
                <h1>
                    🔔 Notifications
                    <?php if($unread_notifications > 0): ?>
                        <span class="badge badge-new"><?= $unread_notifications ?> unread</span>
                    <?php endif; ?>
                </h1>
                <div class="header-actions">
                    <button class="btn btn-test" onclick="testNotification()">🧪 Test Notification</button>
                    <?php if($unread_notifications > 0): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="mark_all_read" value="1">
                            <button type="submit" class="btn btn-primary">✓ Mark All as Read</button>
                        </form>
                    <?php endif; ?>
                    <button class="btn btn-secondary" onclick="refreshNotifications()">🔄 Refresh</button>
                </div>
            </div>

            <!-- Status Messages -->
            <?php if(isset($_GET['msg']) && $_GET['msg'] === 'all_read'): ?>
                <div class="alert alert-success">✓ All notifications marked as read!</div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card unread">
                    <h3>Unread Notifications</h3>
                    <div class="value" id="unread-count"><?= $stats['unread_count'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Notifications</h3>
                    <div class="value"><?= $stats['total_notifications'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>New Documents</h3>
                    <div class="value"><?= $stats['new_doc_count'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>Documents Sold</h3>
                    <div class="value"><?= $stats['sold_count'] ?></div>
                </div>
                <div class="stat-card">
                    <h3>System Alerts</h3>
                    <div class="value"><?= $stats['alert_count'] ?></div>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="content-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>All Notifications</h2>
                    <form method="GET" class="filter-bar" style="margin: 0;">
                        <select name="filter" onchange="this.form.submit()">
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>Unread Only</option>
                            <option value="read" <?= $filter === 'read' ? 'selected' : '' ?>>Read Only</option>
                        </select>
                    </form>
                </div>

                <div id="notifications-list">
                    <?php if(mysqli_num_rows($notifications) > 0): ?>
                        <?php while($notif = mysqli_fetch_assoc($notifications)): 
                            $type_badges = [
                                'new_document' => ['badge-new', '📄 New Document'],
                                'document_sold' => ['badge-sold', '💰 Document Sold'],
                                'system_alert' => ['badge-alert', '⚠️ System Alert']
                            ];
                            $badge = $type_badges[$notif['notification_type']] ?? ['badge-new', '📢 Notification'];
                        ?>
                            <div class="notification-item <?= $notif['is_read'] ? 'read' : 'unread' ?>" id="notif-<?= $notif['id'] ?>">
                                <div class="notification-header">
                                    <div class="notification-title">
                                        <span class="badge <?= $badge[0] ?>"><?= $badge[1] ?></span>
                                        <?php if(!$notif['is_read']): ?>
                                            <span class="badge badge-new">NEW</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notification-time">
                                        <?= date('M d, Y H:i', strtotime($notif['created_at'])) ?>
                                    </div>
                                </div>
                                <div class="notification-message">
                                    <?= htmlspecialchars($notif['message']) ?>
                                    <?php if($notif['document_id']): ?>
                                        <br><small style="color: #999;">
                                            Document: <strong><?= htmlspecialchars($notif['original_name'] ?? 'Unknown') ?></strong>
                                            <?php if($notif['document_owner']): ?>
                                                by <?= htmlspecialchars($notif['document_owner']) ?>
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-actions">
                                    <?php if(!$notif['is_read']): ?>
                                        <button class="action-btn action-btn-primary" onclick="markAsRead(<?= $notif['id'] ?>)">✓ Mark as Read</button>
                                    <?php endif; ?>
                                    <?php if($notif['document_id']): ?>
                                        <a href="../view.php?id=<?= $notif['document_id'] ?>" target="_blank" class="action-btn action-btn-primary">👁️ View Document</a>
                                    <?php endif; ?>
                                    <button class="action-btn action-btn-danger" onclick="deleteNotification(<?= $notif['id'] ?>)">🗑️ Delete</button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px; color: #999;">No notifications found</div>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&filter=<?= $filter ?>">« Prev</a>
                        <?php endif; ?>
                        
                        <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if($i == $page): ?>
                                <span class="active"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>&filter=<?= $filter ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&filter=<?= $filter ?>">Next »</a>
                        <?php endif; ?>
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
                    // Update last notification ID
                    if(data.last_id) {
                        lastNotificationId = data.last_id;
                    }
                    
                    if(data.new_count > 0) {
                        // Show browser notification if permission granted
                        if(Notification.permission === 'granted') {
                            new Notification('DocShare Admin - New Notification', {
                                body: `You have ${data.new_count} new notification(s)`,
                                icon: '/favicon.ico',
                                badge: '/favicon.ico',
                                tag: 'admin-notification',
                                requireInteraction: false
                            });
                        }
                        // Update unread count
                        const unreadCountElement = document.getElementById('unread-count');
                        if(unreadCountElement) {
                            unreadCountElement.textContent = data.unread_count;
                        }
                        // Refresh page if on unread filter
                        if(window.location.search.includes('filter=unread') || window.location.search.includes('filter=all') || window.location.search === '') {
                            refreshNotifications();
                        }
                    }
                })
                .catch(error => console.error('Error checking notifications:', error));
        }

        function refreshNotifications() {
            window.location.reload();
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
                    notifElement.classList.remove('unread');
                    notifElement.classList.add('read');
                    notifElement.querySelector('.notification-actions').innerHTML = 
                        '<button class="action-btn action-btn-danger" onclick="deleteNotification(' + notificationId + ')">🗑️ Delete</button>';
                    
                    // Update unread count
                    const unreadCount = parseInt(document.getElementById('unread-count').textContent);
                    document.getElementById('unread-count').textContent = Math.max(0, unreadCount - 1);
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function deleteNotification(notificationId) {
            if(confirm('Are you sure you want to delete this notification?')) {
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
                        refreshNotifications();
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
                    console.log('✓ Test notification created:', data.message);
                    
                    // Show immediate browser notification if permission granted
                    if(Notification.permission === 'granted') {
                        new Notification('DocShare Admin - Test Notification', {
                            body: 'This is a test notification. The system is working correctly!',
                            icon: '/favicon.ico',
                            badge: '/favicon.ico',
                            tag: 'test-notification',
                            requireInteraction: false
                        });
                    }
                    
                    // Wait a moment then refresh to show the new notification in the list
                    setTimeout(() => {
                        refreshNotifications();
                    }, 500);
                } else {
                    alert('Failed to create test notification: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error creating test notification');
            });
        }

        // Request notification permission on page load
        if('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                if(permission === 'granted') {
                    console.log('✓ Browser notification permission granted');
                } else {
                    console.log('⚠️ Browser notification permission denied');
                }
            });
        }
        
        // Initialize lastNotificationId from the latest notification on the page
        document.addEventListener('DOMContentLoaded', function() {
            const firstNotif = document.querySelector('.notification-item');
            if(firstNotif) {
                const notifId = firstNotif.id.replace('notif-', '');
                lastNotificationId = parseInt(notifId) || 0;
            }
            console.log('📢 Notification polling started. Last ID:', lastNotificationId);
        });

        // Clean up interval on page unload
        window.addEventListener('beforeunload', () => {
            clearInterval(refreshInterval);
        });
    </script>
</body>
</html>

<?php 
// Handle AJAX check for new notifications
if(isset($_GET['ajax']) && $_GET['ajax'] === 'check') {
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

mysqli_close($conn); 
?>

