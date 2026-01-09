<?php
require_once __DIR__ . '/../includes/error_handler.php';
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Dashboard - Admin Panel";

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
        u.username,
        aa.admin_points as value
    FROM admin_approvals aa
    JOIN documents d ON aa.document_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE aa.status = 'approved'
    ORDER BY aa.reviewed_at DESC
    LIMIT 10
");

// Get top documents by sales
$top_documents = $VSD->get_list("
    SELECT 
        d.id,
        d.original_name,
        d.admin_points,
        u.username,
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
        SUM(CASE WHEN role='admin' THEN 1 ELSE 0 END) as admin_count
    FROM users
");

$unread_notifications = $VSD->num_rows("SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0");

// For shared admin sidebar
$admin_active_page = 'dashboard';
$admin_pending_count = $stats['pending_documents'];

// Include header
include __DIR__ . '/../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="p-6 bg-base-100 border-b border-base-300">
    <div class="container mx-auto max-w-7xl">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-gauge"></i>
                    Dashboard
                </h2>
                <p class="text-base-content/70 mt-1">Tổng quan hệ thống quản lý tài liệu</p>
            </div>
            <div class="flex gap-2">
                <a href="pending-docs.php" class="btn btn-primary">
                    <i class="fa-regular fa-clock mr-2"></i>
                    Duyệt tài liệu
                    <?php if($admin_pending_count > 0): ?>
                        <span class="badge badge-warning badge-sm ml-2"><?= $admin_pending_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Page Body -->
<div class="p-6">
    <div class="container mx-auto max-w-7xl">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex items-center">
                        <div class="text-xs uppercase font-semibold text-base-content/70">Tổng tài liệu</div>
                    </div>
                    <div class="stat-value text-primary text-3xl font-bold"><?= number_format($stats['total_documents']) ?></div>
                    <div class="mt-2">
                        <span class="text-base-content/70 text-sm">
                            <i class="fa-regular fa-files mr-1"></i>
                            Tất cả trạng thái
                        </span>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex items-center">
                        <div class="text-xs uppercase font-semibold text-base-content/70">Chờ duyệt</div>
                    </div>
                    <div class="stat-value text-warning text-3xl font-bold"><?= number_format($stats['pending_documents']) ?></div>
                    <div class="mt-2">
                        <?php if($stats['pending_documents'] > 0): ?>
                            <a href="pending-docs.php" class="text-warning text-sm hover:underline">
                                <i class="fa-solid fa-arrow-right mr-1"></i>
                                Xem ngay
                            </a>
                        <?php else: ?>
                            <span class="text-success text-sm">
                                <i class="fa-solid fa-check mr-1"></i>
                                Đã xử lý hết
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex items-center">
                        <div class="text-xs uppercase font-semibold text-base-content/70">Đã duyệt</div>
                    </div>
                    <div class="stat-value text-success text-3xl font-bold"><?= number_format($stats['approved_documents']) ?></div>
                    <div class="mt-2">
                        <span class="text-base-content/70 text-sm">
                            <i class="fa-solid fa-circle-check mr-1"></i>
                            Đang hoạt động
                        </span>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex items-center">
                        <div class="text-xs uppercase font-semibold text-base-content/70">Từ chối</div>
                    </div>
                    <div class="stat-value text-error text-3xl font-bold"><?= number_format($stats['rejected_documents']) ?></div>
                    <div class="mt-2">
                        <span class="text-base-content/70 text-sm">
                            <i class="fa-solid fa-circle-xmark mr-1"></i>
                            Không đạt yêu cầu
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex items-center">
                        <div class="text-xs uppercase font-semibold text-base-content/70">Tổng người dùng</div>
                    </div>
                    <div class="stat-value text-info text-3xl font-bold"><?= number_format($user_stats['total_users']) ?></div>
                    <div class="mt-2">
                        <a href="users.php" class="text-info text-sm hover:underline">
                            <i class="fa-solid fa-arrow-right mr-1"></i>
                            Quản lý
                        </a>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex items-center">
                        <div class="text-xs uppercase font-semibold text-base-content/70">Admin</div>
                    </div>
                    <div class="stat-value text-secondary text-3xl font-bold"><?= number_format($user_stats['admin_count']) ?></div>
                    <div class="mt-2">
                        <span class="text-base-content/70 text-sm">
                            <i class="fa-solid fa-shield-halved mr-1"></i>
                            Quản trị viên
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Activities -->
            <div class="card bg-base-100 shadow">
                <div class="card-header bg-base-200">
                    <h3 class="card-title">
                        <i class="fa-solid fa-chart-line mr-2"></i>
                        Hoạt động gần đây
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>Tài liệu</th>
                                    <th>Người dùng</th>
                                    <th>Điểm</th>
                                    <th>Thời gian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($recent_activities) > 0): ?>
                                <?php foreach($recent_activities as $activity): ?>
                                    <tr>
                                        <td>
                                            <div class="truncate max-w-[150px]" title="<?= htmlspecialchars($activity['original_name']) ?>">
                                                <?= htmlspecialchars(substr($activity['original_name'], 0, 30)) ?>...
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-info badge-sm"><?= htmlspecialchars($activity['username']) ?></span>
                                        </td>
                                        <td>
                                            <span class="text-success font-bold">+<?= $activity['value'] ?></span>
                                        </td>
                                        <td class="text-base-content/70">
                                            <?= date('d/m H:i', strtotime($activity['timestamp'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-base-content/70 py-8">
                                            <i class="fa-regular fa-face-meh text-4xl block mb-2"></i>
                                            <div>Chưa có hoạt động nào</div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Top Documents -->
            <div class="card bg-base-100 shadow">
                <div class="card-header bg-base-200">
                    <h3 class="card-title">
                        <i class="fa-solid fa-star mr-2"></i>
                        Top tài liệu bán chạy
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>Tài liệu</th>
                                    <th>Tác giả</th>
                                    <th>Lượt bán</th>
                                    <th>Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($top_documents) > 0): ?>
                                <?php foreach($top_documents as $doc): ?>
                                    <tr>
                                        <td>
                                            <div class="truncate max-w-[150px]" title="<?= htmlspecialchars($doc['original_name']) ?>">
                                                <?= htmlspecialchars(substr($doc['original_name'], 0, 30)) ?>...
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-cyan badge-sm"><?= htmlspecialchars($doc['username']) ?></span>
                                        </td>
                                        <td>
                                            <span class="font-bold"><?= $doc['sales_count'] ?></span>
                                        </td>
                                        <td>
                                            <span class="text-success font-bold"><?= number_format($doc['total_points_earned'] ?? 0) ?> điểm</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-base-content/70 py-8">
                                            <i class="fa-regular fa-chart-bar text-4xl block mb-2"></i>
                                            <div>Chưa có dữ liệu bán hàng</div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-6">
            <div class="card bg-base-100 shadow">
                <div class="card-header bg-base-200">
                    <h3 class="card-title">
                        <i class="fa-solid fa-bolt mr-2"></i>
                        Thao tác nhanh
                    </h3>
                </div>
                <div class="card-body">
                    <div class="flex flex-wrap gap-3">
                        <a href="pending-docs.php" class="btn btn-primary">
                            <i class="fa-regular fa-clock mr-2"></i>
                            Duyệt tài liệu
                        </a>
                        <a href="users.php" class="btn btn-info">
                            <i class="fa-solid fa-users mr-2"></i>
                            Quản lý người dùng
                        </a>
                        <a href="transactions.php" class="btn btn-success">
                            <i class="fa-solid fa-coins mr-2"></i>
                            Xem giao dịch
                        </a>
                        <a href="all-documents.php" class="btn btn-secondary">
                            <i class="fa-regular fa-files mr-2"></i>
                            Tất cả tài liệu
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
include __DIR__ . '/../includes/admin-footer.php';
?>
