<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Quản lý giao dịch - Admin Panel";

// Get filter parameters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : 'all';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 30;
$offset = ($page - 1) * $per_page;

// Build query
$where_clauses = [];
if($search) {
    $where_clauses[] = "(u.username LIKE '%$search%' OR u.email LIKE '%$search%' OR d.original_name LIKE '%$search%')";
}
if($type_filter !== 'all') {
    $where_clauses[] = "pt.transaction_type='$type_filter'";
}
if($status_filter !== 'all') {
    $where_clauses[] = "pt.status='$status_filter'";
}
$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$total_query = "
    SELECT COUNT(*) as total 
    FROM point_transactions pt
    LEFT JOIN users u ON pt.user_id = u.id
    LEFT JOIN documents d ON pt.related_document_id = d.id
    $where_sql
";
$total_result = mysqli_fetch_assoc(mysqli_query($conn, $total_query));
$total_transactions = $total_result['total'];
$total_pages = ceil($total_transactions / $per_page);

// Get transactions
$transactions_query = "
    SELECT pt.*, 
           u.username, u.email,
           d.original_name, d.id as document_id
    FROM point_transactions pt
    LEFT JOIN users u ON pt.user_id = u.id
    LEFT JOIN documents d ON pt.related_document_id = d.id
    $where_sql
    ORDER BY pt.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$transactions = mysqli_query($conn, $transactions_query);

// Get statistics
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(CASE WHEN transaction_type='earn' THEN 1 ELSE 0 END), 0) as earn_count,
        COALESCE(SUM(CASE WHEN transaction_type='spend' THEN 1 ELSE 0 END), 0) as spend_count,
        COALESCE(SUM(CASE WHEN transaction_type='earn' THEN points ELSE 0 END), 0) as total_earned,
        COALESCE(SUM(CASE WHEN transaction_type='spend' THEN points ELSE 0 END), 0) as total_spent,
        COALESCE(SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END), 0) as completed_count,
        COALESCE(SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END), 0) as pending_count
    FROM point_transactions
"));

$unread_notifications = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0"));

// For shared admin sidebar
$admin_active_page = 'transactions';

// Include header
include __DIR__ . '/../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="p-6 bg-base-100 border-b border-base-300">
    <div class="container mx-auto max-w-7xl">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-coins"></i>
                    Quản lý giao dịch
                </h2>
                <p class="text-base-content/70 mt-1">Tổng cộng <?= number_format($total_transactions) ?> giao dịch</p>
            </div>
        </div>
    </div>
</div>

<!-- Page Body -->
<div class="p-6">
    <div class="container mx-auto max-w-7xl">
        <!-- Statistics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Tổng giao dịch</div>
                    <div class="stat-value text-primary text-3xl font-bold"><?= number_format($stats['total_transactions'] ?? 0) ?></div>
                </div>
            </div>
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Giao dịch cộng điểm</div>
                    <div class="stat-value text-success text-3xl font-bold"><?= number_format($stats['earn_count'] ?? 0) ?></div>
                </div>
            </div>
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Giao dịch trừ điểm</div>
                    <div class="stat-value text-error text-3xl font-bold"><?= number_format($stats['spend_count'] ?? 0) ?></div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card bg-base-100 shadow">
            <div class="card-header bg-base-200">
                <h3 class="card-title">Lịch sử giao dịch</h3>
            </div>
            
            <!-- Filter Bar -->
            <div class="card-body border-b border-base-300">
                <form method="GET" class="flex flex-wrap gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <div class="join w-full">
                            <div class="join-item bg-base-200 px-4 flex items-center">
                                <i class="fa-solid fa-search"></i>
                            </div>
                            <input type="text" name="search" class="input input-bordered join-item flex-1" placeholder="Tìm theo user hoặc tài liệu..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="w-40">
                        <select name="type" class="select select-bordered w-full">
                            <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>Tất cả loại</option>
                            <option value="earn" <?= $type_filter === 'earn' ? 'selected' : '' ?>>Cộng điểm</option>
                            <option value="spend" <?= $type_filter === 'spend' ? 'selected' : '' ?>>Trừ điểm</option>
                        </select>
                    </div>
                    <div class="w-40">
                        <select name="status" class="select select-bordered w-full">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Tất cả trạng thái</option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-filter mr-2"></i>Lọc
                    </button>
                    <?php if($search || $type_filter !== 'all' || $status_filter !== 'all'): ?>
                        <a href="transactions.php" class="btn btn-ghost">
                            <i class="fa-solid fa-xmark mr-2"></i>Xóa bộ lọc
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table -->
            <?php if(mysqli_num_rows($transactions) > 0): ?>
                <div class="card-body p-0">
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Người dùng</th>
                                    <th>Loại</th>
                                    <th>Điểm</th>
                                    <th>Tài liệu</th>
                                    <th>Lý do</th>
                                    <th>Trạng thái</th>
                                    <th>Thời gian</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($trans = mysqli_fetch_assoc($transactions)): ?>
                                    <tr>
                                        <td class="text-base-content/70"><?= $trans['id'] ?></td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <div class="avatar placeholder">
                                                    <div class="bg-info text-info-content rounded-full w-8">
                                                        <span class="text-xs"><?= strtoupper(substr($trans['username'] ?? 'U', 0, 2)) ?></span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="font-bold"><?= htmlspecialchars($trans['username'] ?? 'Unknown') ?></div>
                                                    <div class="text-xs text-base-content/70"><?= htmlspecialchars($trans['email'] ?? '') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if($trans['transaction_type'] === 'earn'): ?>
                                                <span class="badge badge-success badge-sm">
                                                    <i class="fa-solid fa-arrow-up mr-1"></i>Cộng
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-error badge-sm">
                                                    <i class="fa-solid fa-arrow-down mr-1"></i>Trừ
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="font-bold <?= $trans['transaction_type'] === 'earn' ? 'text-success' : 'text-error' ?>">
                                                <?= $trans['transaction_type'] === 'earn' ? '+' : '-' ?><?= number_format($trans['points']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if($trans['document_id']): ?>
                                                <a href="../view.php?id=<?= $trans['document_id'] ?>" target="_blank" class="link link-hover truncate max-w-[150px] inline-block">
                                                    <i class="fa-regular fa-file mr-1"></i>
                                                    <?= htmlspecialchars(substr($trans['original_name'] ?? 'Unknown', 0, 25)) ?>...
                                                </a>
                                            <?php else: ?>
                                                <span class="text-base-content/70">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-base-content/70 truncate max-w-[150px]">
                                            <?= htmlspecialchars($trans['reason'] ?? '-') ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'completed' => 'badge-success',
                                                'pending' => 'badge-warning',
                                                'cancelled' => 'badge-error'
                                            ];
                                            $status_texts = [
                                                'completed' => 'Completed',
                                                'pending' => 'Pending',
                                                'cancelled' => 'Cancelled'
                                            ];
                                            ?>
                                            <span class="badge <?= $status_badges[$trans['status']] ?? 'badge-ghost' ?> badge-sm">
                                                <?= $status_texts[$trans['status']] ?? ucfirst($trans['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-base-content/70"><?= date('d/m/Y H:i', strtotime($trans['created_at'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                    <div class="card-footer bg-base-200 flex items-center justify-between">
                        <p class="text-base-content/70">Hiển thị <span><?= $offset + 1 ?></span> đến <span><?= min($offset + $per_page, $total_transactions) ?></span> trong <span><?= $total_transactions ?></span> kết quả</p>
                        <div class="join">
                            <?php if($page > 1): ?>
                                <a class="join-item btn btn-sm" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&type=<?= $type_filter ?>&status=<?= $status_filter ?>">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a class="join-item btn btn-sm <?= $i == $page ? 'btn-active' : '' ?>" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= $type_filter ?>&status=<?= $status_filter ?>"><?= $i ?></a>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                                <a class="join-item btn btn-sm" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&type=<?= $type_filter ?>&status=<?= $status_filter ?>">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card-body">
                    <div class="flex flex-col items-center justify-center py-12">
                        <i class="fa-regular fa-receipt text-6xl text-base-content/30 mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Không tìm thấy giao dịch</h3>
                        <p class="text-base-content/70 text-center mb-6">
                            Không có giao dịch nào phù hợp với bộ lọc.
                        </p>
                        <a href="transactions.php" class="btn btn-primary">
                            <i class="fa-solid fa-refresh mr-2"></i>Xóa bộ lọc
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
include __DIR__ . '/../includes/admin-footer.php';
mysqli_close($conn); 
?>
