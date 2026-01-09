<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Quản lý giao dịch";

// Logic lọc dữ liệu
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

$where = [];
if($search) {
    $s = $VSD->escape($search);
    $where[] = "(u.username LIKE '%$s%' OR u.email LIKE '%$s%' OR d.original_name LIKE '%$s%')";
}
if($type !== 'all') $where[] = "pt.transaction_type = '" . $VSD->escape($type) . "'";
if($status !== 'all') $where[] = "pt.status = '" . $VSD->escape($status) . "'";

$where_sql = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";

// Count Total
$total_res = $VSD->get_row("SELECT COUNT(*) as c FROM point_transactions pt LEFT JOIN users u ON pt.user_id=u.id LEFT JOIN documents d ON pt.related_document_id=d.id $where_sql");
$total = $total_res['c'];
$pages = ceil($total / $per_page);

// Fetch Data
$transactions = $VSD->get_list("
    SELECT pt.*, u.username, u.email, u.avatar, d.original_name, d.id as doc_id 
    FROM point_transactions pt 
    LEFT JOIN users u ON pt.user_id = u.id 
    LEFT JOIN documents d ON pt.related_document_id = d.id 
    $where_sql 
    ORDER BY pt.created_at DESC 
    LIMIT $per_page OFFSET " . ($page - 1) * $per_page
);

// Stats logic
$stats = $VSD->get_row("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN transaction_type='earn' THEN points ELSE 0 END) as earned,
    SUM(CASE WHEN transaction_type='spend' THEN points ELSE 0 END) as spent,
    SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as success
    FROM point_transactions"
);

$admin_active_page = 'transactions';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="min-h-screen bg-base-200/50 p-4 lg:p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-money-bill-transfer text-primary"></i>
                    Lịch sử giao dịch
                </h1>
                <p class="text-base-content/60 text-sm mt-1">
                    Theo dõi dòng tiền ra vào của hệ thống
                </p>
            </div>
            
            <div class="stats shadow bg-base-100 text-xs md:text-sm">
                <div class="stat px-4 py-2 place-items-center">
                    <div class="stat-title text-success font-bold flex items-center gap-1"><i class="fa-solid fa-arrow-up"></i> Tổng nạp</div>
                    <div class="stat-value text-success"><?= number_format($stats['earned']) ?></div>
                </div>
                <div class="stat px-4 py-2 place-items-center border-l border-base-200">
                    <div class="stat-title text-error font-bold flex items-center gap-1"><i class="fa-solid fa-arrow-down"></i> Tổng chi</div>
                    <div class="stat-value text-error"><?= number_format($stats['spent']) ?></div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card bg-base-100 shadow-sm border border-base-200">
            <div class="card-body p-4">
                <form method="GET" class="flex flex-col md:flex-row gap-3 items-center">
                    <div class="relative flex-1 w-full">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm user, email, tên tài liệu..." class="input input-bordered w-full pl-10">
                    </div>
                    
                    <select name="type" class="select select-bordered w-full md:w-40" onchange="this.form.submit()">
                        <option value="all">Tất cả loại</option>
                        <option value="earn" <?= $type==='earn'?'selected':'' ?>>Nạp điểm (+)</option>
                        <option value="spend" <?= $type==='spend'?'selected':'' ?>>Tiêu điểm (-)</option>
                    </select>

                    <select name="status" class="select select-bordered w-full md:w-40" onchange="this.form.submit()">
                        <option value="all">Mọi trạng thái</option>
                        <option value="completed" <?= $status==='completed'?'selected':'' ?>>Thành công</option>
                        <option value="pending" <?= $status==='pending'?'selected':'' ?>>Đang xử lý</option>
                        <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Đã hủy</option>
                    </select>

                    <?php if($search || $type!=='all' || $status!=='all'): ?>
                        <a href="transactions.php" class="btn btn-ghost btn-square" title="Xóa lọc"><i class="fa-solid fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="card bg-base-100 shadow-sm border border-base-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead class="bg-base-200/50 text-base-content/70">
                        <tr>
                            <th class="w-20">Mã GD</th>
                            <th>Người dùng</th>
                            <th class="text-right">Số điểm</th>
                            <th>Nội dung / Tài liệu</th>
                            <th>Loại</th>
                            <th>Thời gian</th>
                            <th class="text-right">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($transactions) > 0): ?>
                            <?php foreach($transactions as $t): 
                                $is_earn = ($t['transaction_type'] === 'earn');
                                $color = $is_earn ? 'text-success' : 'text-error';
                                $sign = $is_earn ? '+' : '-';
                                $icon = $is_earn ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down';
                            ?>
                            <tr class="hover">
                                <td class="font-mono text-xs opacity-50">#<?= $t['id'] ?></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="avatar placeholder">
                                            <div class="w-8 h-8 rounded-full bg-base-300 text-xs">
                                                <span><?= strtoupper(substr($t['username'] ?? 'U',0,2)) ?></span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="font-bold text-sm"><?= htmlspecialchars($t['username'] ?? 'Unknown') ?></div>
                                            <div class="text-[10px] opacity-60"><?= htmlspecialchars($t['email'] ?? '') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-right font-bold font-mono <?= $color ?>">
                                    <?= $sign . number_format($t['points']) ?>
                                </td>
                                <td>
                                    <div class="max-w-xs truncate text-sm" title="<?= htmlspecialchars($t['reason']) ?>">
                                        <?= htmlspecialchars($t['reason']) ?>
                                    </div>
                                    <?php if($t['doc_id']): ?>
                                        <a href="../view-document.php?id=<?= $t['doc_id'] ?>" target="_blank" class="flex items-center gap-1 text-xs text-primary mt-0.5 hover:underline">
                                            <i class="fa-regular fa-file"></i> <?= htmlspecialchars($t['original_name']) ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="badge badge-sm gap-1 <?= $is_earn ? 'badge-ghost text-success' : 'badge-ghost text-error' ?>">
                                        <i class="fa-solid <?= $icon ?> text-[10px]"></i>
                                        <?= $is_earn ? 'Nhận' : 'Chi' ?>
                                    </div>
                                </td>
                                <td class="text-xs text-base-content/60">
                                    <?= date('d/m H:i', strtotime($t['created_at'])) ?>
                                </td>
                                <td class="text-right">
                                    <?php 
                                        $st_cls = match($t['status']){
                                            'completed' => 'badge-success',
                                            'pending' => 'badge-warning',
                                            'cancelled' => 'badge-error',
                                            default => 'badge-ghost'
                                        };
                                    ?>
                                    <span class="badge <?= $st_cls ?> badge-sm capitalize"><?= $t['status'] ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-8 text-base-content/40 italic">Không có giao dịch nào</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if($pages > 1): ?>
                <div class="p-4 border-t border-base-200 flex justify-center">
                    <div class="join">
                        <?php for($i=1; $i<=$pages; $i++): ?>
                            <a href="?page=<?= $i ?>&type=<?= $type ?>&status=<?= $status ?>&search=<?= urlencode($search) ?>" class="join-item btn btn-sm <?= $i==$page?'btn-active':'' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
