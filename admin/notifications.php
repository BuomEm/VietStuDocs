<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/telegram_notifications.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Thông báo hệ thống";

// AJAX: Check new notifications
if(isset($_GET['ajax']) && $_GET['ajax'] === 'check') {
    header('Content-Type: application/json');
    $last_id = intval($_GET['last_id'] ?? 0);
    $new = $VSD->get_row("SELECT COUNT(*) as c, MAX(id) as m FROM admin_notifications WHERE admin_id=$admin_id AND id > $last_id AND is_read=0");
    $total_unread = $VSD->get_row("SELECT COUNT(*) as c FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0");
    echo json_encode([
        'new_count' => intval($new['c']),
        'unread_count' => intval($total_unread['c']),
        'last_id' => intval($new['m'] ?? $last_id)
    ]);
    exit;
}

// POST Actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['mark_read'])) {
        $id = intval($_POST['notification_id']);
        $VSD->update('admin_notifications', ['is_read' => 1], "id=$id AND admin_id=$admin_id");
        echo json_encode(['success' => true]); exit;
    }
    if(isset($_POST['mark_all_read'])) {
        $VSD->update('admin_notifications', ['is_read' => 1], "admin_id=$admin_id AND is_read=0");
        header("Location: notifications.php?msg=all_read"); exit;
    }
    if(isset($_POST['delete_notification'])) {
        $id = intval($_POST['notification_id']);
        $VSD->remove('admin_notifications', "id=$id AND admin_id=$admin_id");
        echo json_encode(['success' => true]); exit;
    }
    if(isset($_POST['test_notification'])) {
        header('Content-Type: application/json');
        require_once __DIR__ . '/../config/notifications.php';
        // Test logic simplified
        $msg = "🔔 Test Notification: Hệ thống hoạt động bình thường lúc " . date('H:i:s');
        $result = sendAdminNotification($admin_id, 'system_alert', $msg, null, null, [['text' => 'Xem cài đặt', 'url' => getBaseUrl() . '/admin/settings.php']]);
        echo json_encode(['success' => true, 'message' => 'Đã gửi thông báo test!']); exit;
    }
}

// Stats & List
$filter = isset($_GET['filter']) ? $VSD->escape($_GET['filter']) : 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

$where = ["admin_id=$admin_id"];
if($filter === 'unread') $where[] = "is_read=0";
elseif($filter === 'read') $where[] = "is_read=1";
$where_sql = 'WHERE ' . implode(' AND ', $where);

$total = $VSD->get_row("SELECT COUNT(*) as c FROM admin_notifications $where_sql")['c'];
$total_pages = ceil($total / $per_page);
$notifications = $VSD->get_list("
    SELECT an.*, d.original_name, d.id as document_id, u.username as owner 
    FROM admin_notifications an
    LEFT JOIN documents d ON an.document_id = d.id
    LEFT JOIN users u ON d.user_id = u.id
    $where_sql ORDER BY an.created_at DESC LIMIT $per_page OFFSET " . ($page-1)*$per_page
);

$stats = $VSD->get_row("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_read=0 THEN 1 ELSE 0 END) as unread,
    SUM(CASE WHEN notification_type='new_document' THEN 1 ELSE 0 END) as new_docs
FROM admin_notifications WHERE admin_id=$admin_id");

$unread_notifications = $stats['unread'] ?? 0;
$admin_active_page = 'notifications';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="min-h-screen bg-base-200/50 p-4 lg:p-6">
    <div class="max-w-4xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-bell text-primary"></i>
                    Thông báo
                </h1>
                <p class="text-base-content/60 text-sm mt-1">
                    Bạn có <span class="font-bold text-error" id="unread_counter"><?= $unread_notifications ?></span> thông báo mới chưa đọc
                </p>
            </div>
            
            <div class="flex gap-2">
                <button onclick="testNotification()" class="btn btn-sm btn-ghost">
                    <i class="fa-solid fa-flask"></i> Test
                </button>
                <?php if($unread_notifications > 0): ?>
                    <form method="POST">
                        <input type="hidden" name="mark_all_read" value="1">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fa-solid fa-check-double"></i> Đọc tất cả
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="tabs tabs-boxed bg-base-100 p-1 rounded-box">
            <a href="?filter=all" class="tab w-1/3 <?= $filter==='all'?'tab-active bg-primary text-white':'' ?>">Tất cả</a>
            <a href="?filter=unread" class="tab w-1/3 <?= $filter==='unread'?'tab-active bg-primary text-white':'' ?>">Chưa đọc</a>
            <a href="?filter=read" class="tab w-1/3 <?= $filter==='read'?'tab-active bg-primary text-white':'' ?>">Đã đọc</a>
        </div>

        <!-- List -->
        <div class="space-y-3">
            <?php if(count($notifications) > 0): ?>
                <?php foreach($notifications as $n): 
                    $is_unread = !$n['is_read'];
                    $type_cfg = [
                        'new_document' => ['icon'=>'fa-file-circle-plus', 'cls'=>'bg-info/10 text-info'],
                        'document_sold' => ['icon'=>'fa-sack-dollar', 'cls'=>'bg-success/10 text-success'],
                        'system_alert' => ['icon'=>'fa-triangle-exclamation', 'cls'=>'bg-warning/10 text-warning'],
                        'report' => ['icon'=>'fa-flag', 'cls'=>'bg-error/10 text-error']
                    ];
                    $cfg = $type_cfg[$n['notification_type']] ?? ['icon'=>'fa-bell', 'cls'=>'bg-base-200 text-base-content/50'];
                ?>
                <div id="notif-<?= $n['id'] ?>" class="card bg-base-100 border <?= $is_unread ? 'border-primary/50 shadow-md' : 'border-base-200 shadow-sm opacity-80 hover:opacity-100' ?> transition-all">
                    <div class="card-body p-4 flex flex-row gap-4 items-start">
                        <div class="w-10 h-10 rounded-full flex-shrink-0 grid place-items-center text-lg <?= $cfg['cls'] ?>">
                            <i class="fa-solid <?= $cfg['icon'] ?>"></i>
                        </div>
                        
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <h3 class="font-bold text-sm <?= $is_unread ? 'text-base-content' : 'text-base-content/70' ?>">
                                    <?= $n['message'] ?>
                                </h3>
                                <time class="text-[10px] whitespace-nowrap opacity-50 ml-2">
                                    <?= date('d/m H:i', strtotime($n['created_at'])) ?>
                                </time>
                            </div>
                            
                            <?php if($n['document_id']): ?>
                                <div class="mt-2 text-xs flex items-center gap-2 p-2 bg-base-200/50 rounded-lg">
                                    <i class="fa-solid fa-file text-base-content/40"></i>
                                    <span class="truncate font-medium"><?= htmlspecialchars($n['original_name']) ?></span>
                                    <?php if($n['owner']): ?>
                                        <span class="opacity-50">• <?= htmlspecialchars($n['owner']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="mt-3 flex gap-2">
                                <?php if($is_unread): ?>
                                    <button onclick="markRead(<?= $n['id'] ?>)" class="btn btn-xs btn-primary btn-outline">Đã đọc</button>
                                <?php endif; ?>
                                <?php if($n['document_id']): ?>
                                    <a href="../view-document.php?id=<?= $n['document_id'] ?>" target="_blank" class="btn btn-xs btn-ghost">Xem</a>
                                <?php endif; ?>
                                <button onclick="deleteNotif(<?= $n['id'] ?>)" class="btn btn-xs btn-ghost text-error ml-auto"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-12 text-base-content/40">
                    <i class="fa-solid fa-bell-slash text-4xl mb-3 block"></i>
                    Không có thông báo nào
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="flex justify-center mt-6">
            <div class="join">
                <?php for($i=1; $i<=$total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>&filter=<?= $filter ?>" class="join-item btn btn-sm <?= $i==$page?'btn-active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function markRead(id) {
    fetch('notifications.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'mark_read=1&notification_id='+id
    }).then(r=>r.json()).then(d=>{
        if(d.success) {
            const card = document.getElementById('notif-'+id);
            card.classList.remove('border-primary/50', 'shadow-md');
            card.classList.add('border-base-200', 'shadow-sm', 'opacity-80');
            card.querySelector('button.btn-primary')?.remove();
            updateCounter(-1);
        }
    });
}

function deleteNotif(id) {
    if(!confirm('Xóa thông báo này?')) return;
    fetch('notifications.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'delete_notification=1&notification_id='+id
    }).then(r=>r.json()).then(d=>{
        if(d.success) document.getElementById('notif-'+id).remove();
    });
}

function testNotification() {
    fetch('notifications.php', {
        method: 'POST', 
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: 'test_notification=1'
    }).then(r=>r.json()).then(d=>alert(d.message));
}

function updateCounter(change) {
    const el = document.getElementById('unread_counter');
    let val = parseInt(el.innerText) + change;
    el.innerText = Math.max(0, val);
}
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
