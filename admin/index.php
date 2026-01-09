<?php
require_once __DIR__ . '/../includes/error_handler.php';
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Dashboard";

// Get statistics
$pending_docs = getPendingDocumentsCount();

$stats = $VSD->get_row("
    SELECT 
        COUNT(DISTINCT d.id) as total_documents,
        COUNT(DISTINCT d.user_id) as total_users,
        SUM(CASE WHEN d.status='approved' THEN 1 ELSE 0 END) as approved_documents,
        SUM(CASE WHEN d.status='rejected' THEN 1 ELSE 0 END) as rejected_documents,
        SUM(CASE WHEN d.status='pending' THEN 1 ELSE 0 END) as pending_documents
    FROM documents d
");

// Get recent activities
$recent_activities = $VSD->get_list("
    SELECT 
        'document_approved' as activity_type,
        aa.reviewed_at as timestamp,
        d.original_name,
        d.id as doc_id,
        u.username,
        u.avatar,
        aa.admin_points as value
    FROM admin_approvals aa
    JOIN documents d ON aa.document_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE aa.status = 'approved'
    ORDER BY aa.reviewed_at DESC
    LIMIT 8
");

// Get top documents by sales
$top_documents = $VSD->get_list("
    SELECT 
        d.id,
        d.original_name,
        d.admin_points,
        u.username,
        u.avatar,
        COUNT(ds.id) as sales_count,
        SUM(ds.points_paid) as total_points_earned
    FROM documents d
    JOIN users u ON d.user_id = u.id
    LEFT JOIN document_sales ds ON d.id = ds.document_id
    WHERE d.status = 'approved'
    GROUP BY d.id
    ORDER BY sales_count DESC
    LIMIT 5
");

// Get user statistics
$user_stats = $VSD->get_row("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role='admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_users_week
    FROM users
");

// Get transaction stats
$transaction_stats = $VSD->get_row("
    SELECT 
        COALESCE(SUM(CASE WHEN transaction_type='earn' THEN points ELSE 0 END), 0) as total_earned,
        COALESCE(SUM(CASE WHEN transaction_type='spend' THEN points ELSE 0 END), 0) as total_spent,
        COUNT(*) as total_transactions
    FROM point_transactions
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");

// Get recent users
$recent_users = $VSD->get_list("
    SELECT id, username, avatar, email, created_at, role
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
");

$unread_notifications = $VSD->num_rows("SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0");

// For shared admin sidebar
$admin_active_page = 'dashboard';
$admin_pending_count = $stats['pending_documents'];

// Include header
include __DIR__ . '/../includes/admin-header.php';
?>

<style>
/* Dashboard Styles */
.stat-card {
    background: oklch(var(--b1));
    border: 1px solid oklch(var(--bc) / 0.08);
    border-radius: 1rem;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 40px -10px oklch(var(--bc) / 0.15);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: radial-gradient(circle at top right, var(--card-accent, oklch(var(--p) / 0.1)), transparent 70%);
    pointer-events: none;
}

.stat-card .stat-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1;
    letter-spacing: -0.025em;
}

.stat-card .stat-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: oklch(var(--bc) / 0.5);
}

.stat-card .stat-change {
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.stat-card .stat-change.positive {
    color: oklch(var(--su));
}

.stat-card .stat-change.negative {
    color: oklch(var(--er));
}

.dashboard-card {
    background: oklch(var(--b1));
    border: 1px solid oklch(var(--bc) / 0.08);
    border-radius: 1rem;
    overflow: hidden;
}

.dashboard-card .card-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid oklch(var(--bc) / 0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dashboard-card .card-title {
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.dashboard-card .card-body {
    padding: 1rem 1.5rem;
}

.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid oklch(var(--bc) / 0.05);
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-item .activity-avatar {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.75rem;
    overflow: hidden;
    flex-shrink: 0;
}

.activity-item .activity-content {
    flex: 1;
    min-width: 0;
}

.quick-action-card {
    background: linear-gradient(135deg, var(--action-from), var(--action-to));
    border-radius: 1rem;
    padding: 1.5rem;
    color: white;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    overflow: hidden;
}

.quick-action-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
    pointer-events: none;
}

.quick-action-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px -10px var(--action-shadow);
}

.quick-action-card .action-icon {
    width: 3rem;
    height: 3rem;
    background: rgba(255,255,255,0.2);
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.welcome-banner {
    background: linear-gradient(135deg, oklch(var(--p)) 0%, oklch(var(--s)) 100%);
    border-radius: 1.5rem;
    padding: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
}

.welcome-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -25%;
    width: 50%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 60%);
    pointer-events: none;
}

.welcome-banner::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -10%;
    width: 40%;
    height: 150%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
    pointer-events: none;
}

.user-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 0.75rem;
    transition: background 0.2s ease;
}

.user-row:hover {
    background: oklch(var(--bc) / 0.05);
}
</style>

<div class="min-h-screen bg-base-200/50">
    <div class="p-4 lg:p-6">
        <div class="max-w-7xl mx-auto space-y-6">
            
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="relative z-10">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <h1 class="text-2xl lg:text-3xl font-bold mb-2">
                                Xin chào, <?= htmlspecialchars(getCurrentUsername()) ?>! 👋
                            </h1>
                            <p class="text-white/80">
                                <?php 
                                $hour = date('H');
                                if ($hour < 12) echo "Buổi sáng tốt lành!";
                                elseif ($hour < 18) echo "Buổi chiều vui vẻ!";
                                else echo "Buổi tối an lành!";
                                ?>
                                Đây là tổng quan hệ thống của bạn hôm nay.
                            </p>
                        </div>
                        <?php if($admin_pending_count > 0): ?>
                            <a href="pending-docs.php" class="btn btn-lg bg-white/20 hover:bg-white/30 border-none text-white gap-2">
                                <i class="fa-solid fa-clock"></i>
                                <span><?= $admin_pending_count ?> tài liệu chờ duyệt</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Documents -->
                <div class="stat-card" style="--card-accent: oklch(var(--p) / 0.15)">
                    <div class="flex items-start justify-between mb-4">
                        <div class="stat-icon bg-primary/10 text-primary">
                            <i class="fa-solid fa-file-lines"></i>
                        </div>
                    </div>
                    <div class="stat-value text-primary"><?= number_format($stats['total_documents']) ?></div>
                    <div class="stat-label mt-1">Tổng tài liệu</div>
                </div>

                <!-- Pending -->
                <div class="stat-card" style="--card-accent: oklch(var(--wa) / 0.15)">
                    <div class="flex items-start justify-between mb-4">
                        <div class="stat-icon bg-warning/10 text-warning">
                            <i class="fa-solid fa-hourglass-half"></i>
                        </div>
                        <?php if($stats['pending_documents'] > 0): ?>
                            <span class="badge badge-warning badge-sm animate-pulse">Cần xử lý</span>
                        <?php endif; ?>
                    </div>
                    <div class="stat-value text-warning"><?= number_format($stats['pending_documents']) ?></div>
                    <div class="stat-label mt-1">Chờ duyệt</div>
                </div>

                <!-- Approved -->
                <div class="stat-card" style="--card-accent: oklch(var(--su) / 0.15)">
                    <div class="flex items-start justify-between mb-4">
                        <div class="stat-icon bg-success/10 text-success">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                    <div class="stat-value text-success"><?= number_format($stats['approved_documents']) ?></div>
                    <div class="stat-label mt-1">Đã duyệt</div>
                </div>

                <!-- Users -->
                <div class="stat-card" style="--card-accent: oklch(var(--in) / 0.15)">
                    <div class="flex items-start justify-between mb-4">
                        <div class="stat-icon bg-info/10 text-info">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <?php if($user_stats['new_users_week'] > 0): ?>
                            <span class="stat-change positive">
                                <i class="fa-solid fa-arrow-up text-xs"></i>
                                +<?= $user_stats['new_users_week'] ?> tuần này
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="stat-value text-info"><?= number_format($user_stats['total_users']) ?></div>
                    <div class="stat-label mt-1">Người dùng</div>
                </div>
            </div>

            <!-- Secondary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="stat-card" style="--card-accent: oklch(var(--su) / 0.15)">
                    <div class="flex items-center gap-4">
                        <div class="stat-icon bg-success/10 text-success">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                        <div>
                            <div class="stat-value text-success text-xl"><?= number_format($transaction_stats['total_earned']) ?></div>
                            <div class="stat-label">Điểm phát ra (30 ngày)</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card" style="--card-accent: oklch(var(--er) / 0.15)">
                    <div class="flex items-center gap-4">
                        <div class="stat-icon bg-error/10 text-error">
                            <i class="fa-solid fa-cart-shopping"></i>
                        </div>
                        <div>
                            <div class="stat-value text-error text-xl"><?= number_format($transaction_stats['total_spent']) ?></div>
                            <div class="stat-label">Điểm tiêu thụ (30 ngày)</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card" style="--card-accent: oklch(var(--s) / 0.15)">
                    <div class="flex items-center gap-4">
                        <div class="stat-icon bg-secondary/10 text-secondary">
                            <i class="fa-solid fa-exchange-alt"></i>
                        </div>
                        <div>
                            <div class="stat-value text-secondary text-xl"><?= number_format($transaction_stats['total_transactions']) ?></div>
                            <div class="stat-label">Giao dịch (30 ngày)</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <a href="pending-docs.php" class="quick-action-card" style="--action-from: #f59e0b; --action-to: #d97706; --action-shadow: rgba(245, 158, 11, 0.4)">
                    <div class="action-icon"><i class="fa-solid fa-clock"></i></div>
                    <div>
                        <div class="font-bold">Duyệt tài liệu</div>
                        <div class="text-sm opacity-80"><?= $admin_pending_count ?> đang chờ</div>
                    </div>
                </a>

                <a href="users.php" class="quick-action-card" style="--action-from: #3b82f6; --action-to: #2563eb; --action-shadow: rgba(59, 130, 246, 0.4)">
                    <div class="action-icon"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <div class="font-bold">Người dùng</div>
                        <div class="text-sm opacity-80"><?= number_format($user_stats['total_users']) ?> thành viên</div>
                    </div>
                </a>

                <a href="transactions.php" class="quick-action-card" style="--action-from: #10b981; --action-to: #059669; --action-shadow: rgba(16, 185, 129, 0.4)">
                    <div class="action-icon"><i class="fa-solid fa-receipt"></i></div>
                    <div>
                        <div class="font-bold">Giao dịch</div>
                        <div class="text-sm opacity-80">Xem lịch sử</div>
                    </div>
                </a>

                <a href="settings.php" class="quick-action-card" style="--action-from: #8b5cf6; --action-to: #7c3aed; --action-shadow: rgba(139, 92, 246, 0.4)">
                    <div class="action-icon"><i class="fa-solid fa-sliders"></i></div>
                    <div>
                        <div class="font-bold">Cài đặt</div>
                        <div class="text-sm opacity-80">Tùy chỉnh hệ thống</div>
                    </div>
                </a>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Recent Activities -->
                <div class="lg:col-span-2 dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fa-solid fa-clock-rotate-left text-primary"></i>
                            Hoạt động gần đây
                        </h3>
                        <a href="all-documents.php" class="btn btn-ghost btn-sm">Xem tất cả</a>
                    </div>
                    <div class="card-body">
                        <?php if(count($recent_activities) > 0): ?>
                            <?php foreach($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-avatar bg-gradient-to-br from-primary/20 to-secondary/20 grid place-items-center text-primary">
                                        <i class="fa-solid fa-user text-lg"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-semibold text-sm"><?= htmlspecialchars($activity['username']) ?></span>
                                            <span class="text-base-content/50 text-sm">đã được duyệt tài liệu</span>
                                        </div>
                                        <a href="../view.php?id=<?= $activity['doc_id'] ?>" class="text-sm text-primary hover:underline line-clamp-1">
                                            <?= htmlspecialchars($activity['original_name']) ?>
                                        </a>
                                        <div class="flex items-center gap-3 mt-1">
                                            <span class="badge badge-success badge-sm">+<?= $activity['value'] ?> điểm</span>
                                            <span class="text-xs text-base-content/40"><?= date('H:i - d/m', strtotime($activity['timestamp'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-12 text-base-content/50">
                                <i class="fa-regular fa-clock text-4xl mb-3 block"></i>
                                <p>Chưa có hoạt động nào</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <!-- Top Documents -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title text-sm">
                                <i class="fa-solid fa-trophy text-warning"></i>
                                Top tài liệu
                            </h3>
                        </div>
                        <div class="card-body p-3">
                            <?php if(count($top_documents) > 0): ?>
                                <?php foreach($top_documents as $index => $doc): ?>
                                    <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-base-200/50 transition-colors">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-sm
                                            <?= $index === 0 ? 'bg-warning/20 text-warning' : ($index === 1 ? 'bg-base-content/10 text-base-content' : 'bg-base-content/5 text-base-content/60') ?>">
                                            <?= $index + 1 ?>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium truncate"><?= htmlspecialchars(substr($doc['original_name'], 0, 25)) ?>...</div>
                                            <div class="text-xs text-base-content/50">
                                                <?= $doc['sales_count'] ?> lượt bán • <?= number_format($doc['total_points_earned'] ?? 0) ?> điểm
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-base-content/50 text-sm">
                                    <i class="fa-regular fa-chart-bar text-2xl mb-2 block"></i>
                                    <p>Chưa có dữ liệu</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Users -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title text-sm">
                                <i class="fa-solid fa-user-plus text-info"></i>
                                Thành viên mới
                            </h3>
                            <a href="users.php" class="text-xs text-primary hover:underline">Xem tất cả</a>
                        </div>
                        <div class="card-body p-3">
                            <?php foreach($recent_users as $user): ?>
                                <div class="user-row">
                                    <div class="avatar placeholder">
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-info/20 to-primary/20 grid place-items-center text-info">
                                            <i class="fa-solid fa-user text-lg"></i>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium truncate"><?= htmlspecialchars($user['username']) ?></div>
                                        <div class="text-xs text-base-content/50"><?= date('d/m/Y', strtotime($user['created_at'])) ?></div>
                                    </div>
                                    <?php if($user['role'] === 'admin'): ?>
                                        <span class="badge badge-primary badge-xs">Admin</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php 
include __DIR__ . '/../includes/admin-footer.php';
?>
