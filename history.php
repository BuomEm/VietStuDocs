<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit();
}

require_once 'config/db.php';
require_once 'config/function.php';
require_once 'config/auth.php';
require_once 'config/premium.php';
require_once 'config/points.php';

$user_id = getCurrentUserId();
$is_premium = isPremium($user_id);
$page_title = "Lịch Sử Hoạt Động - DocShare";
$current_page = 'history';

// Get active tab
$active_tab = $_GET['tab'] ?? 'purchases';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get Notifications History
$notifs_query = "
    SELECT *
    FROM notifications
    WHERE user_id = $user_id
    ORDER BY created_at DESC
    LIMIT $per_page OFFSET $offset
";
$notifs_result = $VSD->get_list($notifs_query);
$total_notifs = $VSD->num_rows("SELECT id FROM notifications WHERE user_id = $user_id");

// Get Purchase History
$purchases_query = "
    SELECT 
        ds.id,
        ds.buyer_user_id as user_id,
        ds.document_id,
        ds.points_paid as points_spent,
        ds.purchased_at,
        d.original_name,
        d.file_name,
        d.thumbnail,
        u.username as seller_name
    FROM document_sales ds
    JOIN documents d ON ds.document_id = d.id
    LEFT JOIN users u ON ds.seller_user_id = u.id
    WHERE ds.buyer_user_id = $user_id
    ORDER BY ds.purchased_at DESC
    LIMIT $per_page OFFSET $offset
";
$purchases_result = $VSD->get_list($purchases_query);
$total_purchases = $VSD->num_rows("SELECT id FROM document_sales WHERE buyer_user_id = $user_id");

// Get Point Transactions History
$points_query = "
    SELECT *
    FROM point_transactions
    WHERE user_id = $user_id
    ORDER BY created_at DESC
    LIMIT $per_page OFFSET $offset
";
$points_result = $VSD->get_list($points_query);
$total_points = $VSD->num_rows("SELECT id FROM point_transactions WHERE user_id = $user_id");

// Get Premium Transactions (check if table exists first)
if(db_table_exists('transactions')) {
    $premium_query = "
        SELECT *
        FROM transactions
        WHERE user_id = $user_id
        ORDER BY created_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $premium_result = $VSD->get_list($premium_query);
    $total_premium = $VSD->num_rows("SELECT id FROM transactions WHERE user_id = $user_id");
} else {
    // Create empty array if table doesn't exist
    $premium_result = [];
    $total_premium = 0;
}

// Get Upload History
$uploads_query = "
    SELECT *
    FROM documents
    WHERE user_id = $user_id
    ORDER BY created_at DESC
    LIMIT $per_page OFFSET $offset
";
$uploads_result = $VSD->get_list($uploads_query);
$total_uploads = $VSD->num_rows("SELECT id FROM documents WHERE user_id = $user_id");

// Get Statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM document_sales WHERE buyer_user_id = $user_id) as total_purchased,
        (SELECT SUM(points_paid) FROM document_sales WHERE buyer_user_id = $user_id) as total_spent,
        (SELECT COUNT(*) FROM documents WHERE user_id = $user_id) as total_uploaded,
        (SELECT SUM(views) FROM documents WHERE user_id = $user_id) as total_views,
        (SELECT SUM(downloads) FROM documents WHERE user_id = $user_id) as total_downloads
";
$stats = $VSD->get_row($stats_query);


?>
<?php include 'includes/head.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<style>
    .history-card {
        transition: all 0.3s ease;
    }
    .history-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 16px;
        padding: 24px;
        transition: transform 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
    }
    
    .stat-card-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    
    .stat-card-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .stat-card-info {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    }
    
    .timeline-item::before {
        content: '';
        position: absolute;
        left: 20px;
        top: 30px;
        bottom: -20px;
        width: 2px;
        background: linear-gradient(to bottom, #e5e7eb, transparent);
    }
    
    .timeline-item:last-child::before {
        display: none;
    }
</style>

<div class="drawer-content flex flex-col">
    <?php include 'includes/navbar.php'; ?>
    
    <main class="flex-1 p-6 bg-base-200">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                <i class="fa-solid fa-clock-rotate-left text-3xl text-primary"></i>
                <h1 class="text-3xl font-bold">Lịch Sử Hoạt Động</h1>
            </div>
            <p class="text-base-content/70">Theo dõi tất cả hoạt động của bạn trên nền tảng</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card stat-card-success">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-white/80 text-sm font-medium">Tài Liệu Đã Mua</span>
                    <i class="fa-solid fa-shopping-cart text-2xl opacity-30"></i>
                </div>
                <div class="text-3xl font-bold"><?= number_format($stats['total_purchased'] ?? 0) ?></div>
                <div class="text-white/70 text-xs mt-1">
                    Tổng chi: <?= number_format($stats['total_spent'] ?? 0) ?> điểm
                </div>
            </div>
            
            <div class="stat-card stat-card-info">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-white/80 text-sm font-medium">Tài Liệu Đã Tải Lên</span>
                    <i class="fa-solid fa-cloud-upload text-2xl opacity-30"></i>
                </div>
                <div class="text-3xl font-bold"><?= number_format($stats['total_uploaded'] ?? 0) ?></div>
                <div class="text-white/70 text-xs mt-1">
                    <?= number_format($stats['total_views'] ?? 0) ?> lượt xem
                </div>
            </div>
            
            <div class="stat-card stat-card-warning">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-white/80 text-sm font-medium">Lượt Tải Xuống</span>
                    <i class="fa-solid fa-download text-2xl opacity-30"></i>
                </div>
                <div class="text-3xl font-bold"><?= number_format($stats['total_downloads'] ?? 0) ?></div>
                <div class="text-white/70 text-xs mt-1">
                    Từ tài liệu của bạn
                </div>
            </div>
            
            <div class="stat-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-white/80 text-sm font-medium">Điểm Hiện Tại</span>
                    <i class="fa-solid fa-coins text-2xl opacity-30"></i>
                </div>
                <div class="text-3xl font-bold"><?= number_format(getUserPoints($user_id)['current_points']) ?></div>
                <div class="text-white/70 text-xs mt-1">
                    <?= $is_premium ? '⭐ Premium Member' : 'Free Member' ?>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs tabs-boxed bg-base-100 mb-6 p-1">
            <a href="?tab=purchases" class="tab <?= $active_tab === 'purchases' ? 'tab-active' : '' ?>">
                <i class="fa-solid fa-shopping-bag mr-2"></i>
                Lịch Sử Mua Tài Liệu
            </a>
            <a href="?tab=points" class="tab <?= $active_tab === 'points' ? 'tab-active' : '' ?>">
                <i class="fa-solid fa-coins mr-2"></i>
                Giao Dịch Điểm
            </a>
            <a href="?tab=premium" class="tab <?= $active_tab === 'premium' ? 'tab-active' : '' ?>">
                <i class="fa-solid fa-crown mr-2"></i>
                Giao Dịch Premium
            </a>
            <a href="?tab=uploads" class="tab <?= $active_tab === 'uploads' ? 'tab-active' : '' ?>">
                <i class="fa-solid fa-cloud-upload mr-2"></i>
                Lịch Sử Upload
            </a>
            <a href="?tab=notifications" class="tab <?= $active_tab === 'notifications' ? 'tab-active' : '' ?>">
                <i class="fa-solid fa-bell mr-2"></i>
                Thông báo
            </a>
        </div>

        <!-- Tab Content -->
        <div class="bg-base-100 rounded-box shadow-lg p-6">
            
            <!-- Notifications Tab -->
            <?php if($active_tab === 'notifications'): ?>
                <div class="space-y-4">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold flex items-center gap-2">
                            <i class="fa-solid fa-bell text-primary"></i>
                            Thông báo của bạn
                        </h2>
                        <button onclick="markRead()" class="btn btn-sm btn-ghost text-primary text-xs">Đánh dấu tất cả là đã đọc</button>
                    </div>
                    
                    <?php if(count($notifs_result) > 0): ?>
                        <div class="divide-y divide-base-200">
                            <?php foreach($notifs_result as $notif): ?>
                                <div class="py-4 flex items-start gap-4 <?= $notif['is_read'] == 0 ? 'bg-primary/5 rounded-lg px-4' : '' ?>">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center bg-base-200 shrink-0">
                                        <?php
                                        $icon = 'fa-info-circle';
                                        $icon_color = 'text-primary';
                                        
                                        switch($notif['type']) {
                                            case 'document_approved': $icon = 'fa-check-circle'; $icon_color = 'text-success'; break;
                                            case 'document_rejected': $icon = 'fa-times-circle'; $icon_color = 'text-error'; break;
                                            case 'document_deleted': $icon = 'fa-trash-can'; $icon_color = 'text-error'; break;
                                            case 'points_added': $icon = 'fa-coins'; $icon_color = 'text-success'; break;
                                            case 'points_deducted': $icon = 'fa-circle-minus'; $icon_color = 'text-error'; break;
                                            case 'tutor_request_new': $icon = 'fa-graduation-cap'; $icon_color = 'text-info'; break;
                                            case 'tutor_answer': $icon = 'fa-comment-dots'; $icon_color = 'text-success'; break;
                                            case 'tutor_rated': $icon = 'fa-star'; $icon_color = 'text-warning'; break;
                                            case 'dispute_resolved': $icon = 'fa-handshake'; $icon_color = 'text-info'; break;
                                            case 'admin_reply': $icon = 'fa-user-shield'; $icon_color = 'text-secondary'; break;
                                            case 'role_updated': $icon = 'fa-user-gear'; $icon_color = 'text-accent'; break;
                                        }
                                        ?>
                                        <i class="fa-solid <?= $icon ?> <?= $icon_color ?>"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm font-bold text-primary"><?= htmlspecialchars($notif['title'] ?? 'DocShare') ?></p>
                                        <p class="text-sm mt-0.5"><?= htmlspecialchars($notif['message']) ?></p>
                                        <p class="text-[10px] opacity-50 mt-1"><?= date('H:i:s d/m/Y', strtotime($notif['created_at'])) ?></p>
                                    </div>
                                    <?php if($notif['is_read'] == 1): ?>
                                        <span class="badge badge-sm opacity-50">Đã xem</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination for Notifications -->
                        <?php if($total_notifs > $per_page): ?>
                            <div class="flex justify-center mt-8">
                                <div class="join">
                                    <?php 
                                    $total_pages = ceil($total_notifs / $per_page);
                                    for($i = 1; $i <= $total_pages; $i++): ?>
                                        <a href="?tab=notifications&page=<?= $i ?>" class="join-item btn btn-sm <?= $page == $i ? 'btn-active' : '' ?>"><?= $i ?></a>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fa-solid fa-bell-slash text-4xl opacity-20 mb-3"></i>
                            <p class="opacity-50 italic">Bạn chưa có thông báo nào.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Purchase History Tab -->
            <?php if($active_tab === 'purchases'): ?>
                <div class="space-y-4">
                    <?php if(count($purchases_result) > 0): ?>
                        <?php foreach($purchases_result as $purchase): ?>
                            <div class="history-card bg-base-200 rounded-lg p-4 flex items-center gap-4 relative timeline-item">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 bg-success rounded-full flex items-center justify-center z-10">
                                    <i class="fa-solid fa-check text-white"></i>
                                </div>
                                
                                <div class="ml-16 flex-1">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <h3 class="font-bold text-lg flex items-center gap-2">
                                                <i class="fa-regular fa-file text-primary"></i>
                                                <?= htmlspecialchars($purchase['original_name']) ?>
                                            </h3>
                                            <div class="flex flex-wrap items-center gap-4 mt-2 text-sm text-base-content/70">
                                                <span class="flex items-center gap-1">
                                                    <i class="fa-regular fa-calendar"></i>
                                                    <?= date('d/m/Y H:i', strtotime($purchase['purchased_at'])) ?>
                                                </span>
                                                <span class="flex items-center gap-1">
                                                    <i class="fa-solid fa-coins text-warning"></i>
                                                    <?= number_format($purchase['points_spent']) ?> điểm
                                                </span>
                                                <?php if($purchase['seller_name']): ?>
                                                    <span class="flex items-center gap-1">
                                                        <i class="fa-regular fa-user"></i>
                                                        bởi <?= htmlspecialchars($purchase['seller_name']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <a href="view.php?id=<?= $purchase['document_id'] ?>" class="btn btn-sm btn-primary">
                                            <i class="fa-regular fa-eye"></i>
                                            Xem
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fa-regular fa-folder-open text-6xl text-base-content/30 mb-4"></i>
                            <p class="text-base-content/70">Bạn chưa mua tài liệu nào</p>
                            <a href="search.php" class="btn btn-primary mt-4">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                Khám Phá Tài Liệu
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Points Transaction Tab -->
            <?php if($active_tab === 'points'): ?>
                <div class="space-y-4">
                    <?php if(count($points_result) > 0): ?>
                        <?php foreach($points_result as $trans): ?>
                            <?php 
                                $is_earn = $trans['transaction_type'] === 'earn';
                                $icon_class = $is_earn ? 'fa-arrow-up text-success' : 'fa-arrow-down text-error';
                                $bg_class = $is_earn ? 'bg-success/10' : 'bg-error/10';
                            ?>
                            <div class="history-card bg-base-200 rounded-lg p-4 flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full <?= $bg_class ?> flex items-center justify-center">
                                    <i class="fa-solid <?= $icon_class ?> text-xl"></i>
                                </div>
                                
                                <div class="flex-1">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h3 class="font-bold">
                                                <?= htmlspecialchars($trans['reason'] ?? '') ?>
                                            </h3>
                                            <div class="text-sm text-base-content/70 mt-1">
                                                <i class="fa-regular fa-calendar mr-1"></i>
                                                <?= date('d/m/Y H:i:s', strtotime($trans['created_at'])) ?>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-2xl font-bold <?= $is_earn ? 'text-success' : 'text-error' ?>">
                                                <?= $is_earn ? '+' : '-' ?><?= number_format($trans['points']) ?>
                                            </div>
                                            <div class="text-xs text-base-content/60">
                                                <?= $is_earn ? 'Nhận được' : 'Đã chi' ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fa-solid fa-coins text-6xl text-base-content/30 mb-4"></i>
                            <p class="text-base-content/70">Chưa có giao dịch điểm nào</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Premium Transaction Tab -->
            <?php if($active_tab === 'premium'): ?>
                <div class="space-y-4">
                    <?php if(count($premium_result) > 0): ?>
                        <?php foreach($premium_result as $trans): ?>
                            <?php 
                                $status_classes = [
                                    'completed' => 'badge-success',
                                    'pending' => 'badge-warning',
                                    'failed' => 'badge-error'
                                ];
                                $status_labels = [
                                    'completed' => 'Thành Công',
                                    'pending' => 'Đang Xử Lý',
                                    'failed' => 'Thất Bại'
                                ];
                            ?>
                            <div class="history-card bg-base-200 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 bg-warning/20 rounded-full flex items-center justify-center">
                                            <i class="fa-solid fa-crown text-warning text-xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-lg">
                                                Premium <?= ucfirst($trans['transaction_type']) ?>
                                            </h3>
                                            <p class="text-sm text-base-content/70">
                                                <i class="fa-regular fa-calendar mr-1"></i>
                                                <?= date('d/m/Y H:i', strtotime($trans['created_at'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold text-primary">
                                            <?= number_format($trans['amount']) ?>đ
                                        </div>
                                        <span class="badge <?= $status_classes[$trans['status']] ?? 'badge-ghost' ?> mt-1">
                                            <?= $status_labels[$trans['status']] ?? $trans['status'] ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fa-solid fa-crown text-6xl text-base-content/30 mb-4"></i>
                            <p class="text-base-content/70 mb-4">Chưa có giao dịch Premium nào</p>
                            <a href="premium.php" class="btn btn-primary">
                                <i class="fa-solid fa-star"></i>
                                Nâng Cấp Premium
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Upload History Tab -->
            <?php if($active_tab === 'uploads'): ?>
                <div class="space-y-4">
                    <?php if(count($uploads_result) > 0): ?>
                        <?php foreach($uploads_result as $doc): ?>
                            <?php 
                                $status_classes = [
                                    'approved' => 'badge-success',
                                    'pending' => 'badge-warning',
                                    'rejected' => 'badge-error'
                                ];
                                $status_labels = [
                                    'approved' => 'Đã Duyệt',
                                    'pending' => 'Đang Duyệt',
                                    'rejected' => 'Bị Từ Chối'
                                ];
                            ?>
                            <div class="history-card bg-base-200 rounded-lg p-4">
                                <div class="flex items-start gap-4">
                                    <div class="w-16 h-16 bg-base-300 rounded-lg overflow-hidden flex-shrink-0">
                                        <?php if($doc['thumbnail']): ?>
                                            <img src="uploads/<?= htmlspecialchars($doc['thumbnail']) ?>" 
                                                 alt="Thumbnail" 
                                                 class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center">
                                                <i class="fa-regular fa-file text-2xl text-base-content/30"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex-1">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <h3 class="font-bold text-lg">
                                                    <?= htmlspecialchars($doc['original_name']) ?>
                                                </h3>
                                                <div class="flex flex-wrap gap-3 mt-2 text-sm text-base-content/70">
                                                    <span>
                                                        <i class="fa-regular fa-calendar mr-1"></i>
                                                        <?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?>
                                                    </span>
                                                    <span>
                                                        <i class="fa-regular fa-eye mr-1"></i>
                                                        <?= number_format($doc['views']) ?> lượt xem
                                                    </span>
                                                    <span>
                                                        <i class="fa-solid fa-download mr-1"></i>
                                                        <?= number_format($doc['downloads']) ?> tải xuống
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex flex-col items-end gap-2">
                                                <span class="badge <?= $status_classes[$doc['status']] ?? 'badge-ghost' ?>">
                                                    <?= $status_labels[$doc['status']] ?? $doc['status'] ?>
                                                </span>
                                                <a href="view.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-ghost">
                                                    <i class="fa-regular fa-eye"></i>
                                                    Xem
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <i class="fa-solid fa-cloud-upload text-6xl text-base-content/30 mb-4"></i>
                            <p class="text-base-content/70 mb-4">Bạn chưa tải lên tài liệu nào</p>
                            <a href="upload.php" class="btn btn-primary">
                                <i class="fa-solid fa-plus"></i>
                                Tải Lên Tài Liệu
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Pagination -->
            <?php 
                $total_items = 0;
                if($active_tab === 'purchases') $total_items = $total_purchases;
                elseif($active_tab === 'points') $total_items = $total_points;
                elseif($active_tab === 'premium') $total_items = $total_premium;
                elseif($active_tab === 'uploads') $total_items = $total_uploads;
                
                $total_pages = ceil($total_items / $per_page);
            ?>
            
            <?php if($total_pages > 1): ?>
                <div class="flex justify-center mt-8">
                    <div class="join">
                        <!-- Previous Page -->
                        <a href="?tab=<?= $active_tab ?>&page=<?= max(1, $page-1) ?>" 
                           class="join-item btn btn-sm <?= $page <= 1 ? 'btn-disabled' : '' ?>">
                            «
                        </a>

                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if($start > 1): ?>
                            <a href="?tab=<?= $active_tab ?>&page=1" class="join-item btn btn-sm">1</a>
                            <?php if($start > 2): ?>
                                <button class="join-item btn btn-sm btn-disabled">...</button>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for($i = $start; $i <= $end; $i++): ?>
                            <a href="?tab=<?= $active_tab ?>&page=<?= $i ?>" 
                               class="join-item btn btn-sm <?= $i === $page ? 'btn-active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if($end < $total_pages): ?>
                            <?php if($end < $total_pages - 1): ?>
                                <button class="join-item btn btn-sm btn-disabled">...</button>
                            <?php endif; ?>
                            <a href="?tab=<?= $active_tab ?>&page=<?= $total_pages ?>" class="join-item btn btn-sm"><?= $total_pages ?></a>
                        <?php endif; ?>

                        <!-- Next Page -->
                        <a href="?tab=<?= $active_tab ?>&page=<?= min($total_pages, $page+1) ?>" 
                           class="join-item btn btn-sm <?= $page >= $total_pages ? 'btn-disabled' : '' ?>">
                            »
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</div>
</div>

<?php // db connection cleaned up by app flow ?>
