<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';
require_once __DIR__ . '/../config/categories.php';
require_once __DIR__ . '/../push/send_push.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Quản lý toàn bộ tài liệu";

// --- LOGIC XỬ LÝ ACTION (Giữ nguyên logic cũ) ---
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $document_id = isset($_POST['document_id']) ? intval($_POST['document_id']) : 0;
    
    // Logic: Approve
    if($action === 'approve') {
        $points = intval($_POST['points']);
        $notes = $VSD->escape($_POST['notes'] ?? '');
        if($points > 0) {
            approveDocument($document_id, $admin_id, $points, $notes);
            $doc_info = $VSD->get_row("SELECT user_id, original_name FROM documents WHERE id=$document_id");
            if($doc_info) {
                $VSD->insert('notifications', ['user_id' => $doc_info['user_id'], 'title' => 'Tài liệu đã được duyệt', 'message' => "Tài liệu '{$doc_info['original_name']}' được duyệt. +{$points} điểm.", 'type' => 'document_approved', 'ref_id' => $document_id]);
                sendPushToUser($doc_info['user_id'], ['title' => 'Tài liệu đã được duyệt! 🎉', 'body' => "Bạn nhận được {$points} điểm.", 'url' => '/history.php?tab=notifications']);
            }
            header("Location: all-documents.php?msg=approved"); exit;
        }
    } 
    // Logic: Reject
    elseif($action === 'reject') {
        $reason = $VSD->escape($_POST['rejection_reason'] ?? '');
        $doc_info = $VSD->get_row("SELECT user_id, original_name FROM documents WHERE id=$document_id");
        rejectDocument($document_id, $admin_id, $reason);
        if($doc_info) {
            $VSD->insert('notifications', ['user_id' => $doc_info['user_id'], 'title' => 'Tài liệu bị từ chối', 'message' => "Tài liệu '{$doc_info['original_name']}' bị từ chối. Lý do: $reason", 'type' => 'document_rejected', 'ref_id' => $document_id]);
            sendPushToUser($doc_info['user_id'], ['title' => 'Tài liệu bị từ chối ❌', 'body' => "Nhấn để xem lý do.", 'url' => '/history.php?tab=notifications']);
        }
        header("Location: all-documents.php?msg=rejected"); exit;
    }
    // Logic: Delete / Bulk Delete
    elseif($action === 'delete' || $action === 'delete_bulk') {
        $ids = ($action === 'delete') ? [$document_id] : array_map('intval', explode(',', $_POST['ids'] ?? ''));
        foreach($ids as $id) {
            if(!$id) continue;
            $doc = $VSD->get_row("SELECT * FROM documents WHERE id=$id");
            if($doc) {
                @unlink("../uploads/" . $doc['file_name']);
                if(!empty($doc['converted_pdf_path'])) @unlink("../" . $doc['converted_pdf_path']);
                if(!empty($doc['thumbnail'])) @unlink("../uploads/thumbnails/" . $doc['thumbnail']);
                
                $tables = ['docs_points', 'admin_approvals', 'document_sales', 'point_transactions', 'admin_notifications'];
                foreach($tables as $t) $VSD->remove($t, "document_id=$id" . ($t === 'point_transactions' ? ' OR related_document_id='.$id : ''));
                $VSD->remove('documents', "id=$id");
                
                $VSD->insert('notifications', ['user_id' => $doc['user_id'], 'title' => 'Tài liệu bị xóa', 'message' => "Tài liệu '{$doc['original_name']}' đã bị xóa bởi Admin.", 'type' => 'document_deleted', 'ref_id' => $admin_id]);
            }
        }
        header("Location: all-documents.php?msg=deleted"); exit;
    }
}

// --- LOGIC LẤY DỮ LIỆU ---
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get Filter Parameters
$search = isset($_GET['search']) ? $VSD->escape($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $VSD->escape($_GET['status']) : 'all';
$level_filter = isset($_GET['level']) ? $VSD->escape($_GET['level']) : 'all';
$price_filter = isset($_GET['price_type']) ? $VSD->escape($_GET['price_type']) : 'all';
$sort_order = isset($_GET['sort']) ? $VSD->escape($_GET['sort']) : 'newest';

// Build WHERE Query
$where = [];
if($search) $where[] = "(d.original_name LIKE '%$search%' OR u.username LIKE '%$search%')";
if($status_filter !== 'all') $where[] = "d.status='$status_filter'";

// Filter by Level (using JOIN for performance if needed, or EXISTS)
if($level_filter !== 'all') {
    // This requires a join with document_categories
    $where[] = "EXISTS (SELECT 1 FROM document_categories dc WHERE dc.document_id = d.id AND dc.education_level = '$level_filter')";
}

// Filter by Price
if($price_filter === 'free') {
    // Free: user_price = 0 OR (user_price IS NULL AND admin_points = 0)
    $where[] = "(d.user_price = 0 OR (d.user_price IS NULL AND COALESCE(dp.admin_points, 0) = 0))";
} elseif($price_filter === 'paid') {
    // Paid: (user_price IS NOT NULL AND user_price > 0) OR (user_price IS NULL AND admin_points > 0)
    $where[] = "((d.user_price IS NOT NULL AND d.user_price > 0) OR (d.user_price IS NULL AND COALESCE(dp.admin_points, 0) > 0))";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Build ORDER BY
$order_sql = match($sort_order) {
    'oldest' => 'ORDER BY d.created_at ASC',
    'views_desc' => 'ORDER BY d.views DESC',
    'downloads_desc' => 'ORDER BY d.downloads DESC',
    'sales_desc' => 'ORDER BY sales DESC', // Relies on alias in SELECT
    'price_desc' => 'ORDER BY CASE WHEN d.user_price IS NULL THEN COALESCE(dp.admin_points, 0) ELSE d.user_price END DESC',
    default => 'ORDER BY d.created_at DESC' // newest
};

// Calculate Total
$total_docs = $VSD->get_row("SELECT COUNT(*) as c FROM documents d LEFT JOIN users u ON d.user_id = u.id $where_sql")['c'];
$total_pages = ceil($total_docs / $per_page);

// Fetch Data
$docs = $VSD->get_list("
    SELECT d.*, u.username, u.avatar, dp.admin_points,
           (SELECT COUNT(*) FROM document_sales WHERE document_id=d.id) as sales,
           (SELECT SUM(points_paid) FROM document_sales WHERE document_id=d.id) as earned
    FROM documents d
    LEFT JOIN users u ON d.user_id = u.id
    LEFT JOIN docs_points dp ON d.id = dp.document_id
    $where_sql
    $order_sql
    LIMIT $per_page OFFSET $offset
");

$stats = $VSD->get_row("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected
FROM documents");

$admin_active_page = 'documents';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-base-200/30 via-base-100/20 to-base-200/40">
    <!-- Background Pattern -->
    <div class="fixed inset-0 opacity-5">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,.15) 1px, transparent 0); background-size: 20px 20px;"></div>
            </div>
            
    <div class="relative z-10 p-4 lg:p-8">
        <!-- <div class="max-w-[1600px] mx-auto space-y-8">

            
        </div> -->

        <!-- Filter & Actions - Modern Design -->
        <div class="card bg-gradient-to-r from-base-100 to-base-100/80 shadow-xl border border-base-200/50 backdrop-blur-sm rounded-[2rem] relative z-50">
            <div class="card-body p-6">
                <form method="GET" id="filterForm" class="space-y-6">
                    <!-- Search Bar - Hero Style -->
                    <div class="relative">
                        <div class="absolute inset-0 bg-gradient-to-r from-primary/5 via-secondary/5 to-accent/5 rounded-2xl blur-xl"></div>
                        <div class="relative flex items-center gap-4">
                            <div class="flex-1 relative group">
                                <div class="absolute inset-0 bg-gradient-to-r from-primary/20 to-secondary/20 rounded-2xl opacity-0 group-focus-within:opacity-100 blur transition-all duration-500"></div>
                                <div class="relative flex items-center">
                                    <div class="absolute left-5 text-base-content/40 group-focus-within:text-primary transition-colors duration-300">
                                        <i class="fa-solid fa-magnifying-glass text-xl"></i>
                                    </div>
                                    <input type="text" 
                                           name="search" 
                                           value="<?= htmlspecialchars($search) ?>" 
                                           placeholder="Tìm kiếm tài liệu theo tên, người đăng..." 
                                           class="input input-lg w-full pl-14 pr-6 bg-base-200/50 border-2 border-base-300/50 rounded-2xl focus:border-primary focus:bg-base-100 focus:shadow-lg focus:shadow-primary/10 transition-all duration-300 text-base placeholder:text-base-content/40">
                                    <?php if($search): ?>
                                        <a href="all-documents.php" class="absolute right-5 text-base-content/40 hover:text-error transition-colors">
                                            <i class="fa-solid fa-circle-xmark"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg rounded-2xl shadow-lg hover:shadow-xl hover:shadow-primary/20 transition-all duration-300 px-8">
                                <i class="fa-solid fa-search mr-2"></i>
                                Tìm kiếm
                            </button>
                        </div>
                    </div>
                    
                    <!-- Filter Pills Row -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Status Filter -->
                        <div class="dropdown dropdown-hover">
                            <div tabindex="0" role="button" class="btn btn-sm bg-base-200/80 hover:bg-base-300 border-0 rounded-full gap-2 px-4 shadow-sm hover:shadow-md transition-all duration-300 <?= $status_filter !== 'all' ? 'ring-2 ring-primary ring-offset-2 ring-offset-base-100' : '' ?>">
                                <?php 
                                $status_icons = ['all' => '📋', 'pending' => '🕒', 'approved' => '✅', 'rejected' => '❌'];
                                $status_labels = ['all' => 'Tất cả trạng thái', 'pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối'];
                                ?>
                                <span class="text-lg"><?= $status_icons[$status_filter] ?? '📋' ?></span>
                                <span class="font-medium"><?= $status_labels[$status_filter] ?? 'Tất cả' ?></span>
                                <i class="fa-solid fa-chevron-down text-xs opacity-60"></i>
                            </div>
                            <ul tabindex="0" class="dropdown-content z-[1000] menu p-2 shadow-2xl bg-base-100 rounded-2xl w-52 border border-base-200/50 mt-2">
                                <li class="menu-title text-xs uppercase tracking-wider opacity-60 px-2 pt-2">Trạng thái</li>
                                <li><a href="?status=all&search=<?= urlencode($search) ?>&level=<?= $level_filter ?>&price_type=<?= $price_filter ?>&sort=<?= $sort_order ?>" class="rounded-xl <?= $status_filter === 'all' ? 'active' : '' ?>">📋 Tất cả trạng thái</a></li>
                                <li><a href="?status=pending&search=<?= urlencode($search) ?>&level=<?= $level_filter ?>&price_type=<?= $price_filter ?>&sort=<?= $sort_order ?>" class="rounded-xl <?= $status_filter === 'pending' ? 'active' : '' ?>">🕒 Chờ duyệt <span class="badge badge-warning badge-sm"><?= $stats['pending'] ?></span></a></li>
                                <li><a href="?status=approved&search=<?= urlencode($search) ?>&level=<?= $level_filter ?>&price_type=<?= $price_filter ?>&sort=<?= $sort_order ?>" class="rounded-xl <?= $status_filter === 'approved' ? 'active' : '' ?>">✅ Đã duyệt</a></li>
                                <li><a href="?status=rejected&search=<?= urlencode($search) ?>&level=<?= $level_filter ?>&price_type=<?= $price_filter ?>&sort=<?= $sort_order ?>" class="rounded-xl <?= $status_filter === 'rejected' ? 'active' : '' ?>">❌ Từ chối</a></li>
                            </ul>
                        </div>

                        <!-- Level Filter -->
                        <div class="dropdown dropdown-hover">
                            <div tabindex="0" role="button" class="btn btn-sm bg-base-200/80 hover:bg-base-300 border-0 rounded-full gap-2 px-4 shadow-sm hover:shadow-md transition-all duration-300 <?= $level_filter !== 'all' ? 'ring-2 ring-secondary ring-offset-2 ring-offset-base-100' : '' ?>">
                                <span class="text-lg">🎓</span>
                                <span class="font-medium"><?= $level_filter !== 'all' ? (EDUCATION_LEVELS[$level_filter]['name'] ?? 'Cấp học') : 'Cấp học' ?></span>
                                <i class="fa-solid fa-chevron-down text-xs opacity-60"></i>
                            </div>
                            <ul tabindex="0" class="dropdown-content z-[1000] menu p-2 shadow-2xl bg-base-100 rounded-2xl w-56 border border-base-200/50 mt-2 max-h-80 overflow-y-auto">
                                <li class="menu-title text-xs uppercase tracking-wider opacity-60 px-2 pt-2">Cấp học</li>
                                <li><a href="?level=all&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&price_type=<?= $price_filter ?>&sort=<?= $sort_order ?>" class="rounded-xl <?= $level_filter === 'all' ? 'active' : '' ?>">🎓 Tất cả cấp học</a></li>
                                <?php foreach(EDUCATION_LEVELS as $code => $info): ?>
                                    <li><a href="?level=<?= $code ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&price_type=<?= $price_filter ?>&sort=<?= $sort_order ?>" class="rounded-xl <?= $level_filter === $code ? 'active' : '' ?>"><?= $info['name'] ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <!-- Price Filter -->
                        <div class="dropdown dropdown-hover">
                            <div tabindex="0" role="button" class="btn btn-sm bg-base-200/80 hover:bg-base-300 border-0 rounded-full gap-2 px-4 shadow-sm hover:shadow-md transition-all duration-300 <?= $price_filter !== 'all' ? 'ring-2 ring-accent ring-offset-2 ring-offset-base-100' : '' ?>">
                                <?php 
                                $price_icons = ['all' => '💰', 'free' => '🆓', 'paid' => '💎'];
                                $price_labels = ['all' => 'Phí', 'free' => 'Miễn phí', 'paid' => 'Có phí'];
                                ?>
                                <span class="text-lg"><?= $price_icons[$price_filter] ?? '💰' ?></span>
                                <span class="font-medium"><?= $price_labels[$price_filter] ?? 'Phí' ?></span>
                                <i class="fa-solid fa-chevron-down text-xs opacity-60"></i>
                            </div>
                            <ul tabindex="0" class="dropdown-content z-[1000] menu p-2 shadow-2xl bg-base-100 rounded-2xl w-44 border border-base-200/50 mt-2">
                                <li class="menu-title text-xs uppercase tracking-wider opacity-60 px-2 pt-2">Loại phí</li>
                                <li><a href="?price_type=all&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&level=<?= $level_filter ?>&sort=<?= $sort_order ?>" class="rounded-xl <?= $price_filter === 'all' ? 'active' : '' ?>">💰 Tất cả</a></li>
                                <li><a href="?price_type=free&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&level=<?= $level_filter ?>&sort=<?= $sort_order ?>" class="rounded-xl <?= $price_filter === 'free' ? 'active' : '' ?>">🆓 Miễn phí</a></li>
                                <li><a href="?price_type=paid&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&level=<?= $level_filter ?>&sort=<?= $sort_order ?>" class="rounded-xl <?= $price_filter === 'paid' ? 'active' : '' ?>">💎 Có phí</a></li>
                            </ul>
                        </div>

                        <!-- Divider -->
                        <div class="w-px h-6 bg-base-300/50 hidden sm:block"></div>

                        <!-- Sort Dropdown -->
                        <div class="dropdown dropdown-hover dropdown-end">
                            <div tabindex="0" role="button" class="btn btn-sm bg-base-200/80 hover:bg-base-300 border-0 rounded-full gap-2 px-4 shadow-sm hover:shadow-md transition-all duration-300">
                                <?php 
                                $sort_icons = ['newest' => '📅', 'oldest' => '📆', 'views_desc' => '👁️', 'downloads_desc' => '📥', 'sales_desc' => '🛒', 'price_desc' => '💲'];
                                $sort_labels = ['newest' => 'Mới nhất', 'oldest' => 'Cũ nhất', 'views_desc' => 'Xem nhiều', 'downloads_desc' => 'Tải nhiều', 'sales_desc' => 'Bán chạy', 'price_desc' => 'Giá cao'];
                                ?>
                                <span class="text-lg"><?= $sort_icons[$sort_order] ?? '📅' ?></span>
                                <span class="font-medium"><?= $sort_labels[$sort_order] ?? 'Mới nhất' ?></span>
                                <i class="fa-solid fa-chevron-down text-xs opacity-60"></i>
                            </div>
                            <ul tabindex="0" class="dropdown-content z-[1000] menu p-2 shadow-2xl bg-base-100 rounded-2xl w-48 border border-base-200/50 mt-2">
                                <li class="menu-title text-xs uppercase tracking-wider opacity-60 px-2 pt-2">Sắp xếp theo</li>
                                <li><a href="?sort=newest&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&level=<?= $level_filter ?>&price_type=<?= $price_filter ?>" class="rounded-xl <?= $sort_order === 'newest' ? 'active' : '' ?>">📅 Mới nhất</a></li>
                                <li><a href="?sort=oldest&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&level=<?= $level_filter ?>&price_type=<?= $price_filter ?>" class="rounded-xl <?= $sort_order === 'oldest' ? 'active' : '' ?>">📆 Cũ nhất</a></li>
                                <li><a href="?sort=views_desc&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&level=<?= $level_filter ?>&price_type=<?= $price_filter ?>" class="rounded-xl <?= $sort_order === 'views_desc' ? 'active' : '' ?>">👁️ Xem nhiều</a></li>
                                <li><a href="?sort=downloads_desc&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&level=<?= $level_filter ?>&price_type=<?= $price_filter ?>" class="rounded-xl <?= $sort_order === 'downloads_desc' ? 'active' : '' ?>">📥 Tải nhiều</a></li>
                                <li><a href="?sort=sales_desc&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&level=<?= $level_filter ?>&price_type=<?= $price_filter ?>" class="rounded-xl <?= $sort_order === 'sales_desc' ? 'active' : '' ?>">🛒 Bán chạy</a></li>
                                <li><a href="?sort=price_desc&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&level=<?= $level_filter ?>&price_type=<?= $price_filter ?>" class="rounded-xl <?= $sort_order === 'price_desc' ? 'active' : '' ?>">💲 Giá cao</a></li>
                            </ul>
                        </div>

                        <!-- Active Filters & Clear -->
                        <?php 
                        $has_filters = $search || $status_filter !== 'all' || $level_filter !== 'all' || $price_filter !== 'all';
                        if($has_filters): 
                        ?>
                            <div class="flex items-center gap-2 ml-auto">
                                <span class="text-xs text-base-content/60 hidden sm:inline">Bộ lọc đang áp dụng:</span>
                                <a href="all-documents.php" class="btn btn-sm btn-ghost text-error hover:bg-error/10 rounded-full gap-2">
                                    <i class="fa-solid fa-filter-circle-xmark"></i>
                                    <span class="hidden sm:inline">Xóa bộ lọc</span>
                                </a>
                            </div>
                        <?php endif; ?>

                        <!-- Bulk Actions (Hidden by default) -->
                        <div id="bulk-actions" class="hidden ml-auto">
                            <div class="flex items-center gap-2 bg-error/10 border border-error/30 rounded-full px-4 py-2 animate-fade-in">
                                <div class="flex items-center gap-2 text-error">
                                    <i class="fa-solid fa-check-double"></i>
                                    <span class="font-bold text-sm" id="selected-count">0 được chọn</span>
                                </div>
                                <div class="w-px h-5 bg-error/30"></div>
                                <button type="button" onclick="bulkDelete()" class="btn btn-sm btn-error btn-circle shadow-lg hover:shadow-xl hover:scale-110 transition-all duration-300" title="Xóa các mục đã chọn">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                            </div>
                        </div>
                    </div>

                    <!-- Results Summary -->
                    <div class="flex items-center justify-between pt-4 border-t border-base-200/50">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-primary/10 rounded-lg">
                                <i class="fa-solid fa-file-lines text-primary"></i>
                            </div>
                            <div>
                                <span class="text-sm text-base-content/70">Tìm thấy</span>
                                <span class="font-bold text-primary mx-1"><?= number_format($total_docs) ?></span>
                                <span class="text-sm text-base-content/70">tài liệu</span>
                            </div>
                        </div>
                        <div class="text-sm text-base-content/60">
                            Trang <?= $page ?> / <?= max(1, $total_pages) ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Documents Table -->
        <div class="card bg-base-100 shadow-sm border border-base-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr class="bg-base-200/50 text-base-content/70">
                            <th class="w-10">
                                <label>
                                    <input type="checkbox" class="checkbox checkbox-sm" onchange="toggleAll(this)">
                                </label>
                            </th>
                            <th>Tài liệu</th>
                            <th>Phân loại</th>
                            <th>Người đăng</th>
                            <th class="text-center">Thống kê</th>
                            <th>Trạng thái</th>
                            <th class="text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($docs) > 0): ?>
                            <?php foreach($docs as $doc): 
                                $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                $icon = match($ext) { 'pdf'=>'fa-file-pdf', 'doc'=>'fa-file-word', 'docx'=>'fa-file-word', 'ppt'=>'fa-file-powerpoint', 'pptx'=>'fa-file-powerpoint', 'xls'=>'fa-file-excel', 'xlsx'=>'fa-file-excel', default=>'fa-file' };
                                $color = match($ext) { 'pdf'=>'text-error', 'doc'=>'text-info', 'docx'=>'text-info', 'ppt'=>'text-warning', 'pptx'=>'text-warning', 'xls'=>'text-success', 'xlsx'=>'text-success', default=>'text-base-content/50' };
                                
                                // Get category info
                                $cat = getDocumentCategoryWithNames($doc['id']);
                            ?>
                            <tr class="hover group">
                                <td>
                                    <label>
                                        <input type="checkbox" class="checkbox checkbox-sm doc-check" value="<?= $doc['id'] ?>" onchange="updateBulkState()">
                                    </label>
                                </td>
                                <td>
                                    <div class="flex items-start gap-3 max-w-xs">
                                        <div class="w-10 h-10 rounded-lg bg-base-200 grid place-items-center flex-shrink-0 text-xl <?= $color ?>">
                                            <i class="fa-solid <?= $icon ?>"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <a href="../admin/view-document.php?id=<?= $doc['id'] ?>" target="_blank" class="font-bold hover:text-primary truncate block" title="<?= htmlspecialchars($doc['original_name']) ?>">
                                                <?= htmlspecialchars($doc['original_name']) ?>
                                            </a>
                                            <div class="text-xs text-base-content/50 flex flex-wrap items-center gap-2 mt-1">
                                                <span class="badge badge-xs badge-ghost font-mono">.<?= strtoupper($ext) ?></span>
                                                <span><?= date('H:i d/m/Y', strtotime($doc['created_at'])) ?></span>
                                                <?php 
                                                    // user_price can be NULL, 0, or > 0
                                                    $user_price = isset($doc['user_price']) && $doc['user_price'] !== null ? intval($doc['user_price']) : null;
                                                    $admin_points = intval($doc['admin_points'] ?? 0);
                                                    // Logic: NULL -> admin_points, 0 -> 0 (free), > 0 -> user_price
                                                    if ($user_price === null) {
                                                        $price = $admin_points;
                                                    } else {
                                                        $price = $user_price;
                                                    }
                                                ?>
                                                <?php if($price > 0): ?>
                                                    <span class="text-warning font-semibold"><i class="fa-solid fa-coins text-[10px] mr-0.5"></i><?= number_format($price) ?></span>
                                                <?php else: ?>
                                                    <span class="text-success font-semibold">Miễn phí</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if($cat): ?>
                                        <div class="flex flex-col gap-1.5 text-xs">
                                            <span class="badge badge-sm badge-outline w-fit"><?= $cat['education_level_name'] ?></span>
                                            
                                            <?php if(isset($cat['grade_name'])): ?>
                                                <div class="flex gap-1 items-center">
                                                    <span class="font-medium text-base-content/80 whitespace-nowrap"><?= $cat['grade_name'] ?></span>
                                                    <span class="text-base-content/30">•</span>
                                                    <span class="font-bold text-primary truncate max-w-[120px]" title="<?= $cat['subject_name'] ?>"><?= $cat['subject_name'] ?></span>
                                                </div>
                                            <?php elseif(isset($cat['major_group_name'])): ?>
                                                <div class="flex flex-col">
                                                    <span class="opacity-70 truncate max-w-[150px]" title="<?= $cat['major_group_name'] ?>"><?= $cat['major_group_name'] ?></span>
                                                    <span class="font-bold text-primary truncate max-w-[150px]" title="<?= $cat['major_name'] ?>"><?= $cat['major_name'] ?></span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="text-base-content/60">
                                                <i class="fa-regular fa-file-lines mr-1"></i><?= $cat['doc_type_name'] ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs italic opacity-50">Chưa phân loại</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="avatar placeholder">
                                            <div class="w-8 h-8 rounded-full bg-base-200">
                                                <?php if($doc['avatar']): ?>
                                                    <img src="/uploads/avatars/<?= htmlspecialchars($doc['avatar']) ?>" alt="avatar">
                                                <?php else: ?>
                                                    <span class="text-xs font-bold"><?= strtoupper(substr($doc['username'],0,1)) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium hover:text-primary cursor-pointer" onclick="window.location.href='users.php?search=<?= urlencode($doc['username']) ?>'"><?= htmlspecialchars($doc['username']) ?></span>
                                            <span class="text-xs opacity-50">ID: <?= $doc['user_id'] ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                        <div class="text-right opacity-70" title="Lượt xem"><i class="fa-solid fa-eye mr-1"></i><?= number_format($doc['views'] ?? 0) ?></div>
                                        <div class="opacity-70" title="Lượt tải"><i class="fa-solid fa-download mr-1"></i><?= number_format($doc['downloads'] ?? 0) ?></div>
                                        <div class="text-right text-warning font-medium col-span-2 border-t border-base-200 pt-1 mt-1 flex justify-end gap-1" title="Doanh thu">
                                            <span><?= number_format($doc['sales'] ?? 0) ?> mua</span>
                                            <span class="opacity-30">|</span>
                                            <span><?= number_format($doc['earned'] ?? 0) ?> xu</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if($doc['status'] === 'approved'): ?>
                                        <div class="badge badge-success badge-sm gap-1 pl-1 pr-2 w-full justify-start font-medium min-w-[90px]">
                                            <i class="fa-solid fa-check-circle"></i> Đã duyệt
                                        </div>
                                    <?php elseif($doc['status'] === 'pending'): ?>
                                        <div class="badge badge-warning badge-sm gap-1 pl-1 pr-2 w-full justify-start font-medium animate-pulse min-w-[90px]">
                                            <i class="fa-solid fa-clock"></i> Chờ duyệt
                                        </div>
                                    <?php else: ?>
                                        <div class="badge badge-error badge-sm gap-1 pl-1 pr-2 w-full justify-start font-medium min-w-[90px]">
                                            <i class="fa-solid fa-circle-xmark"></i> Từ chối
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-1 text-[10px]">
                                        <?php if($doc['is_public']): ?>
                                            <span class="text-info"><i class="fa-solid fa-earth-americas mr-1"></i>Công khai</span>
                                        <?php else: ?>
                                            <span class="text-base-content/50"><i class="fa-solid fa-lock mr-1"></i>Riêng tư</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <div class="join shadow-sm">
                                        <?php if($doc['status'] === 'pending'): ?>
                                            <button onclick="openApproveModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['original_name'])) ?>')" class="btn btn-sm btn-success btn-square join-item text-white hover:scale-105 transition-transform" title="Duyệt">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                            <button onclick="openRejectModal(<?= $doc['id'] ?>)" class="btn btn-sm btn-warning btn-square join-item text-white hover:scale-105 transition-transform" title="Từ chối">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="confirmDelete(<?= $doc['id'] ?>)" class="btn btn-sm btn-ghost btn-square join-item text-error hover:bg-error/10" title="Xóa">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-16 text-base-content/50">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-16 h-16 bg-base-200 rounded-full flex items-center justify-center mb-4">
                                            <i class="fa-solid fa-folder-open text-3xl opacity-50"></i>
                                        </div>
                                        <h3 class="font-bold text-lg">Không tìm thấy tài liệu nào</h3>
                                        <p class="text-sm opacity-70 mt-1">Thử thay đổi bộ lọc hoặc tìm kiếm từ khóa khác</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
                <div class="p-4 border-t border-base-200 flex justify-center">
                    <div class="join">
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&sort=<?= $sort_order ?>&level=<?= $level_filter ?>&price_type=<?= $price_filter ?>" class="join-item btn btn-sm <?= $page === $i ? 'btn-active btn-primary' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        </div>
    </div>
</div>

<!-- Modals -->
<dialog id="approveModal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4 text-success"><i class="fa-solid fa-check-circle"></i> Duyệt tài liệu</h3>
        <form method="POST">
            <input type="hidden" name="document_id" id="approve_doc_id">
            <input type="hidden" name="action" value="approve">
            <div class="bg-base-200 p-3 rounded-lg mb-4 font-medium truncate" id="approve_doc_title"></div>
            
            <div class="form-control mb-4">
                <label class="label">Giá trị tài liệu (điểm)</label>
                <input type="number" name="points" class="input input-bordered" value="5" min="1" required>
            </div>
            <div class="form-control mb-6">
                <label class="label">Ghi chú (tùy chọn)</label>
                <textarea name="notes" class="textarea textarea-bordered"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn" onclick="approveModal.close()">Hủy</button>
                <button type="submit" class="btn btn-success text-white">Xác nhận duyệt</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<dialog id="rejectModal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4 text-error"><i class="fa-solid fa-circle-xmark"></i> Từ chối tài liệu</h3>
        <form method="POST">
            <input type="hidden" name="document_id" id="reject_doc_id">
            <input type="hidden" name="action" value="reject">
            
            <div class="form-control mb-6">
                <label class="label">Lý do từ chối <span class="text-error">*</span></label>
                <textarea name="rejection_reason" class="textarea textarea-bordered h-24" required placeholder="VD: Nội dung không phù hợp..."></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn" onclick="rejectModal.close()">Hủy</button>
                <button type="submit" class="btn btn-error text-white">Xác nhận từ chối</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<script>
    function openApproveModal(id, title) {
        document.getElementById('approve_doc_id').value = id;
        document.getElementById('approve_doc_title').textContent = title;
        document.getElementById('approveModal').showModal();
    }
    
    function openRejectModal(id) {
        document.getElementById('reject_doc_id').value = id;
        document.getElementById('rejectModal').showModal();
    }

    function confirmDelete(id) {
        if(confirm('Bạn có chắc chắn muốn xóa tài liệu này? Hành động này không thể hoàn tác.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="document_id" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Bulk Actions
    function toggleAll(source) {
        document.querySelectorAll('.doc-check').forEach(cb => cb.checked = source.checked);
        updateBulkState();
    }

    function updateBulkState() {
        const checked = document.querySelectorAll('.doc-check:checked');
        const bulkDiv = document.getElementById('bulk-actions');
        document.getElementById('selected-count').textContent = `${checked.length} được chọn`;
        
        if (checked.length > 0) {
            bulkDiv.classList.remove('hidden');
            bulkDiv.classList.add('flex');
        } else {
            bulkDiv.classList.add('hidden');
            bulkDiv.classList.remove('flex');
        }
    }

    function bulkDelete() {
        const ids = Array.from(document.querySelectorAll('.doc-check:checked')).map(cb => cb.value);
        if(ids.length === 0) return;
        
        if(confirm(`Xóa vĩnh viễn ${ids.length} tài liệu đã chọn?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action" value="delete_bulk"><input type="hidden" name="ids" value="${ids.join(',')}">`;
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
