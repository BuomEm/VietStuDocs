<?php
require_once __DIR__ . '/../includes/error_handler.php';
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';

redirectIfNotAdmin();
require_once __DIR__ . '/../config/tutor.php';

// Auto-trigger SLA completions (Lazy Cron)
checkSLAExpirations();

$admin_id = getCurrentUserId();
$page_title = "Dashboard";

// Get Core Statistics early for usage in logic
$stats = $VSD->get_row("
    SELECT 
        COUNT(DISTINCT d.id) as total_documents,
        COUNT(DISTINCT d.user_id) as total_users,
        SUM(CASE WHEN d.status='approved' THEN 1 ELSE 0 END) as approved_documents,
        SUM(CASE WHEN d.status='rejected' THEN 1 ELSE 0 END) as rejected_documents,
        SUM(CASE WHEN d.status='pending' THEN 1 ELSE 0 END) as pending_documents
    FROM documents d
");

// 1. Document Growth (Last 7 days)
$chart_growth = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $count = (int)$VSD->num_rows("SELECT id FROM documents WHERE DATE(created_at) = '$date'");
    $chart_growth[] = [
        'label' => date('d/m', strtotime($date)),
        'value' => $count
    ];
}

// 2. Revenue Trend (VSD Earned from Sales - Last 7 days)
$chart_revenue = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $earned = $VSD->get_row("SELECT SUM(points_paid) as total FROM document_sales WHERE DATE(purchased_at) = '$date'")['total'] ?? 0;
    $chart_revenue[] = [
        'label' => date('d/m', strtotime($date)),
        'value' => (int)$earned
    ];
}

// 3. Category Distribution (Education Levels)
$category_stats = $VSD->get_results("
    SELECT education_level, COUNT(*) as count 
    FROM document_categories 
    GROUP BY education_level 
    ORDER BY count DESC 
    LIMIT 5
");
// Map level codes to names
require_once __DIR__ . '/../config/categories.php';
$chart_categories = [];
foreach($category_stats as $cs) {
    $lv_info = getEducationLevelInfo($cs['education_level']);
    $chart_categories[] = [
        'label' => $lv_info['name'] ?? $cs['education_level'],
        'value' => (int)$cs['count']
    ];
}

// 4. AI Decision Distribution
$ai_stats = [
    'approved' => (int)$VSD->num_rows("SELECT id FROM documents WHERE ai_decision IN ('APPROVED', 'Chấp Nhận')"),
    'conditional' => (int)$VSD->num_rows("SELECT id FROM documents WHERE ai_decision IN ('CONDITIONAL', 'Xem Xét')"),
    'rejected' => (int)$VSD->num_rows("SELECT id FROM documents WHERE ai_decision IN ('REJECTED', 'Từ Chối')"),
];

// For shared admin sidebar
$admin_active_page = 'dashboard';
$admin_pending_count = (int)($stats['pending_documents'] ?? 0);

// Include header
include __DIR__ . '/../includes/admin-header.php';

// Re-fetch remaining data for display
$user_stats = $VSD->get_row("SELECT COUNT(*) as total_users, SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_users_week FROM users");
$pending_withdrawals = $VSD->get_row("SELECT COUNT(*) as count, SUM(points) as total_vsd FROM withdrawal_requests WHERE status='pending'");
$disputed_requests = $VSD->get_row("SELECT COUNT(*) as count FROM tutor_requests WHERE status='disputed'");
$pending_tutors = $VSD->get_row("SELECT COUNT(*) as count FROM tutors WHERE status='pending'");
$top_documents = $VSD->get_list("SELECT d.id, d.original_name, d.admin_points, u.username, COUNT(ds.id) as sales_count, SUM(ds.points_paid) as total_points_earned FROM documents d JOIN users u ON d.user_id = u.id LEFT JOIN document_sales ds ON d.id = ds.document_id WHERE d.status = 'approved' GROUP BY d.id ORDER BY sales_count DESC LIMIT 5");
$recent_users = $VSD->get_list("SELECT id, username, avatar, email, created_at, role FROM users ORDER BY created_at DESC LIMIT 5");
$recent_activities = $VSD->get_list("SELECT 'document_approved' as activity_type, aa.reviewed_at as timestamp, d.original_name, d.id as doc_id, u.username, aa.admin_points as value FROM admin_approvals aa JOIN documents d ON aa.document_id = d.id JOIN users u ON d.user_id = u.id WHERE aa.status = 'approved' ORDER BY aa.reviewed_at DESC LIMIT 8");
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

            <!-- Actionable Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="withdrawals.php" class="stat-card hover:bg-base-200 cursor-pointer" style="--card-accent: oklch(var(--er) / 0.15)">
                    <div class="flex items-center gap-4">
                        <div class="stat-icon bg-error/10 text-error">
                            <i class="fa-solid fa-money-bill-transfer"></i>
                        </div>
                        <div>
                            <div class="stat-value text-error text-xl"><?= number_format($pending_withdrawals['count'] ?? 0) ?></div>
                            <div class="stat-label">Yêu cầu rút tiền</div>
                            <?php if(($pending_withdrawals['count'] ?? 0) > 0): ?>
                            <div class="text-xs text-error mt-1 animate-pulse"><?= number_format($pending_withdrawals['total_vsd'] ?? 0) ?> VSD chờ duyệt</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>

                <a href="tutor_requests.php?status=disputed" class="stat-card hover:bg-base-200 cursor-pointer" style="--card-accent: oklch(var(--wa) / 0.15)">
                    <div class="flex items-center gap-4">
                        <div class="stat-icon bg-warning/10 text-warning">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <div class="stat-value text-warning text-xl"><?= number_format($disputed_requests['count'] ?? 0) ?></div>
                            <div class="stat-label">Khiếu nại cần xử lý</div>
                        </div>
                    </div>
                </a>

                <a href="tutors.php" class="stat-card hover:bg-base-200 cursor-pointer" style="--card-accent: oklch(var(--s) / 0.15)">
                    <div class="flex items-center gap-4">
                        <div class="stat-icon bg-secondary/10 text-secondary">
                            <i class="fa-solid fa-user-graduate"></i>
                        </div>
                        <div>
                            <div class="stat-value text-secondary text-xl"><?= number_format($pending_tutors['count'] ?? 0) ?></div>
                            <div class="stat-label">Gia sư chờ duyệt</div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Charts Row 1: Content & AI -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Document Growth Chart -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title text-sm">
                            <i class="fa-solid fa-chart-line text-primary"></i>
                            Tăng trưởng tài liệu (7 ngày qua)
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="h-[200px] w-full">
                            <canvas id="growthChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- AI Statistics Pie Chart -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title text-sm">
                            <i class="fa-solid fa-robot text-secondary"></i>
                            Phân bổ kết quả AI Review
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="h-[200px] w-full">
                            <canvas id="aiPieChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2: Revenue & Categories -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Revenue Trend Chart -->
                <div class="dashboard-card border-l-4 border-warning">
                    <div class="card-header">
                        <h3 class="card-title text-sm">
                            <i class="fa-solid fa-coins text-warning"></i>
                            Doanh thu hệ thống (VSD)
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="h-[200px] w-full">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Category Distribution Chart -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title text-sm">
                            <i class="fa-solid fa-layer-group text-info"></i>
                            Phân bổ theo Cấp học
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="h-[200px] w-full">
                            <canvas id="categoryChart"></canvas>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Growth Chart
    const growthCtx = document.getElementById('growthChart').getContext('2d');
    const growthData = <?= json_encode($chart_growth) ?>;
    const growthGradient = growthCtx.createLinearGradient(0, 0, 0, 200);
    growthGradient.addColorStop(0, 'rgba(150, 241, 176, 0.3)');
    growthGradient.addColorStop(1, 'rgba(150, 241, 176, 0)');
    new Chart(growthCtx, {
        type: 'line',
        data: {
            labels: growthData.map(d => d.label),
            datasets: [{
                label: 'Tài liệu',
                data: growthData.map(d => d.value),
                borderColor: '#96f1b0',
                backgroundColor: growthGradient,
                fill: true, tension: 0.4, borderWidth: 2, pointRadius: 3
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
            scales: { 
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.4)', stepSize: 1 } },
                x: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,0.4)' } }
            }
        }
    });

    // 2. AI Pie Chart
    const aiCtx = document.getElementById('aiPieChart').getContext('2d');
    const aiDataValue = <?= json_encode(array_values($ai_stats)) ?>;
    new Chart(aiCtx, {
        type: 'doughnut',
        data: {
            labels: ['Chấp Nhận', 'Xem Xét', 'Từ Chối'],
            datasets: [{
                data: aiDataValue,
                backgroundColor: ['#34d399', '#fbbf24', '#fb7185'],
                borderWidth: 0, hoverOffset: 10
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '70%',
            plugins: { legend: { position: 'right', labels: { color: 'rgba(255,255,255,0.6)', usePointStyle: true, font: { size: 10 } } } }
        }
    });

    // 3. Revenue Chart
    const revCtx = document.getElementById('revenueChart').getContext('2d');
    const revData = <?= json_encode($chart_revenue) ?>;
    const revGradient = revCtx.createLinearGradient(0, 0, 0, 200);
    revGradient.addColorStop(0, 'rgba(251, 191, 36, 0.3)');
    revGradient.addColorStop(1, 'rgba(251, 191, 36, 0)');
    new Chart(revCtx, {
        type: 'line',
        data: {
            labels: revData.map(d => d.label),
            datasets: [{
                label: 'VSD Earned',
                data: revData.map(d => d.value),
                borderColor: '#fbbf24',
                backgroundColor: revGradient,
                fill: true, tension: 0.4, borderWidth: 2, pointRadius: 3
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
            scales: { 
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.4)' } },
                x: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,0.4)' } }
            }
        }
    });

    // 4. Category Chart
    const catCtx = document.getElementById('categoryChart').getContext('2d');
    const catData = <?= json_encode($chart_categories) ?>;
    new Chart(catCtx, {
        type: 'bar',
        data: {
            labels: catData.map(d => d.label),
            datasets: [{
                data: catData.map(d => d.value),
                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                borderColor: '#3b82f6',
                borderWidth: 1, borderRadius: 4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
            scales: { 
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: 'rgba(255,255,255,0.4)', stepSize: 1 } },
                x: { grid: { display: false }, ticks: { color: 'rgba(255,255,255,0.4)', font: { size: 9 } } }
            }
        }
    });
});
</script>
