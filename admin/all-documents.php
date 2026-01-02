<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';
require_once __DIR__ . '/../push/send_push.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Tất cả tài liệu - Admin Panel";

// Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $document_id = intval($_POST['document_id']);
    $action = $_POST['action'];
    
    if($action === 'approve') {
        $points = intval($_POST['points']);
        $notes = $VSD->escape($_POST['notes'] ?? '');
        
        if($points > 0) {
            approveDocument($document_id, $admin_id, $points, $notes);
            // Get user_id for notification
            $doc_info = $VSD->get_row("SELECT user_id, original_name FROM documents WHERE id=$document_id");
            if($doc_info) {
                $VSD->insert('notifications', [
                    'user_id' => $doc_info['user_id'],
                    'title' => 'Tài liệu đã được duyệt',
                    'message' => "Tài liệu '{$doc_info['original_name']}' của bạn đã được duyệt bởi Admin. +{$points} điểm.",
                    'type' => 'document_approved',
                    'ref_id' => $document_id
                ]);
                sendPushToUser($doc_info['user_id'], [
                    'title' => 'Tài liệu đã được duyệt! 🎉',
                    'body' => "Tài liệu '{$doc_info['original_name']}' đã được duyệt. Bạn nhận được {$points} điểm.",
                    'url' => '/history.php?tab=notifications'
                ]);
            }
            header("Location: all-documents.php?msg=approved&id=$document_id");
            exit;
        }
    } elseif($action === 'reject') {
        $reason = $VSD->escape($_POST['rejection_reason'] ?? '');
        $doc_info = $VSD->get_row("SELECT user_id, original_name FROM documents WHERE id=$document_id");
        rejectDocument($document_id, $admin_id, $reason);
        if($doc_info) {
            $VSD->insert('notifications', [
                'user_id' => $doc_info['user_id'],
                'title' => 'Tài liệu bị từ chối',
                'message' => "Tài liệu '{$doc_info['original_name']}' đã bị từ chối. Lý do: $reason",
                'type' => 'document_rejected',
                'ref_id' => $document_id
            ]);
            sendPushToUser($doc_info['user_id'], [
                'title' => 'Tài liệu bị từ chối ❌',
                'body' => "Tài liệu '{$doc_info['original_name']}' đã bị từ chối. Nhấn để xem lý do.",
                'url' => '/history.php?tab=notifications'
            ]);
        }
        header("Location: all-documents.php?msg=rejected&id=$document_id");
        exit;
    } elseif($action === 'delete') {
        $doc = $VSD->get_row("SELECT * FROM documents WHERE id=$document_id");
        if($doc) {
            $file_path = "../uploads/" . $doc['file_name'];
            if(file_exists($file_path)) {
                unlink($file_path);
            }
            $VSD->remove('documents', "id=$document_id");
            
            // Notify user of deletion
            $VSD->insert('notifications', [
                'user_id' => $doc['user_id'],
                'title' => 'Tài liệu bị xóa',
                'message' => "Tài liệu '{$doc['original_name']}' của bạn đã bị Admin xóa khỏi hệ thống.",
                'type' => 'document_deleted',
                'ref_id' => $admin_id
            ]);
            sendPushToUser($doc['user_id'], [
                'title' => 'Tài liệu đã bị xóa 🗑️',
                'body' => "Tài liệu '{$doc['original_name']}' đã bị Admin gỡ bỏ.",
                'url' => '/history.php?tab=notifications'
            ]);

            header("Location: all-documents.php?msg=deleted");
            exit;
        }
    } elseif($action === 'change_status') {
        $new_status = $VSD->escape($_POST['new_status']);
        if(in_array($new_status, ['pending', 'approved', 'rejected'])) {
            $VSD->update('documents', ['status' => $new_status], "id=$document_id");
            
            // Notify user
            $doc_info = $VSD->get_row("SELECT user_id, original_name FROM documents WHERE id=$document_id");
            if($doc_info) {
                 $VSD->insert('notifications', [
                    'user_id' => $doc_info['user_id'],
                    'title' => 'Thay đổi trạng thái tài liệu',
                    'message' => "Admin đã thay đổi trạng thái tài liệu '{$doc_info['original_name']}' sang: " . strtoupper($new_status),
                    'type' => 'document_status_updated',
                    'ref_id' => $document_id
                ]);
                sendPushToUser($doc_info['user_id'], [
                    'title' => 'Cập nhật trạng thái! 🔄',
                    'body' => "Tài liệu của bạn đã được chuyển sang trạng thái " . strtoupper($new_status),
                    'url' => '/history.php?tab=notifications'
                ]);
            }

            header("Location: all-documents.php?msg=status_changed&id=$document_id");
            exit;
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? $VSD->escape($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $VSD->escape($_GET['status']) : 'all';
$user_filter = isset($_GET['user']) ? intval($_GET['user']) : 0;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_clauses = [];
if($search) {
    $where_clauses[] = "(d.original_name LIKE '%$search%' OR u.username LIKE '%$search%' OR d.description LIKE '%$search%')";
}
if($status_filter !== 'all') {
    $where_clauses[] = "d.status='$status_filter'";
}
if($user_filter > 0) {
    $where_clauses[] = "d.user_id=$user_filter";
}
$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$total_query = "
    SELECT COUNT(*) as total 
    FROM documents d
    LEFT JOIN users u ON d.user_id = u.id
    $where_sql
";
$total_result = $VSD->get_row($total_query);
$total_documents = $total_result['total'] ?? 0;
$total_pages = ceil($total_documents / $per_page);

// Get documents
$documents_query = "
    SELECT d.*, 
           u.username, u.email, u.avatar,
           dp.admin_points as assigned_points,
           aa.reviewed_by, aa.reviewed_at, aa.rejection_reason,
           (SELECT COUNT(*) FROM document_sales WHERE document_id=d.id) as sales_count,
           (SELECT SUM(points_paid) FROM document_sales WHERE document_id=d.id) as total_earned
    FROM documents d
    LEFT JOIN users u ON d.user_id = u.id
    LEFT JOIN docs_points dp ON d.id = dp.document_id
    LEFT JOIN admin_approvals aa ON d.id = aa.document_id
    $where_sql
    ORDER BY d.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$documents = $VSD->get_list($documents_query);

// Get statistics
$stats = $VSD->get_row("
    SELECT 
        COUNT(*) as total_documents,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected_count,
        SUM(CASE WHEN is_public=1 THEN 1 ELSE 0 END) as public_count,
        SUM(CASE WHEN is_public=0 THEN 1 ELSE 0 END) as private_count,
        SUM(admin_points) as total_points_assigned,
        (SELECT COUNT(*) FROM document_sales) as total_sales,
        (SELECT SUM(points_paid) FROM document_sales) as total_points_earned
    FROM documents
");

// Get users for filter
$users_list = $VSD->get_list("
    SELECT DISTINCT u.id, u.username
    FROM users u
    JOIN documents d ON u.id = d.user_id
    ORDER BY u.username
");

$unread_notifications = $VSD->num_rows("SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0");

// For shared admin sidebar
$admin_active_page = 'documents';

// Include header
include __DIR__ . '/../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="p-6 bg-base-100 border-b border-base-300">
    <div class="container mx-auto max-w-7xl">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-files"></i>
                    Quản lý tài liệu
                </h2>
                <p class="text-base-content/70 mt-1">Tổng cộng <?= number_format($total_documents) ?> tài liệu</p>
            </div>
            <div class="flex gap-2">
                <button type="button" class="btn btn-success" onclick="openBatchThumbnailModal()">
                    <i class="fa-solid fa-image mr-2"></i>
                    Tạo Thumbnails
                </button>
            </div>
        </div>
    </div>
            </div>

<!-- Page Body -->
<div class="p-6">
    <div class="container mx-auto max-w-7xl">
            <!-- Status Messages -->
            <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success mb-4">
                <i class="fa-solid fa-check-circle"></i>
                <span>
                    <?php 
                    $messages = [
                        'approved' => 'Tài liệu đã được duyệt thành công!',
                        'rejected' => 'Tài liệu đã bị từ chối!',
                        'deleted' => 'Tài liệu đã bị xóa!',
                        'status_changed' => 'Trạng thái tài liệu đã được thay đổi!'
                    ];
                    echo $messages[$_GET['msg']] ?? 'Thao tác thành công!';
                    ?>
                </span>
            </div>
            <?php endif; ?>

            <!-- Statistics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 xl:grid-cols-5 2xl:grid-cols-8 gap-4 mb-6 auto-fill-stats">
            <?php
            // Infobox definitions
            $infoboxes = [
                [
                    'label' => 'Tổng tài liệu',
                    'value' => number_format($stats['total_documents']),
                    'class' => 'text-primary',
                    'icon'  => 'fa-solid fa-layer-group',
                ],
                [
                    'label' => 'Chờ duyệt',
                    'value' => number_format($stats['pending_count']),
                    'class' => 'text-warning',
                    'icon'  => 'fa-solid fa-hourglass-half',
                ],
                [
                    'label' => 'Đã duyệt',
                    'value' => number_format($stats['approved_count']),
                    'class' => 'text-success',
                    'icon'  => 'fa-solid fa-circle-check',
                ],
                [
                    'label' => 'Từ chối',
                    'value' => number_format($stats['rejected_count']),
                    'class' => 'text-error',
                    'icon'  => 'fa-solid fa-times-circle',
                ],
                [
                    'label' => 'Public',
                    'value' => number_format($stats['public_count']),
                    'class' => 'text-info',
                    'icon'  => 'fa-solid fa-earth-asia',
                ],
            ];
            // Fill with placeholder infoboxes if too few, for auto-fill look & spacing
            $total_boxes = 5; // Adjust total number of columns
            $count = count($infoboxes);
            for ($i = 0; $i < $total_boxes; $i++):
                $has_data = $i < $count;
                $item = $has_data ? $infoboxes[$i] : [];
            ?>
            <div class="card bg-base-100 shadow min-h-[100px] flex flex-col justify-center">
                <div class="card-body flex flex-col items-center justify-center p-4 gap-2 <?= $has_data ? '' : 'opacity-40' ?>">
                    <?php if ($has_data): ?>
                        <div class="flex items-center gap-2 mb-1">
                            <i class="<?= $item['icon'] ?> text-lg <?= $item['class'] ?>"></i>
                            <span class="text-xs uppercase font-semibold text-base-content/70"><?= $item['label'] ?></span>
                        </div>
                        <div class="stat-value <?= $item['class'] ?> text-3xl font-bold"><?= $item['value'] ?></div>
                    <?php else: ?>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs uppercase font-semibold text-base-content/30">Trống</span>
                        </div>
                        <div class="stat-value text-base-content/20 text-3xl font-bold">-</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endfor; ?>
        </div>
        <style>
            /* Optional: Responsive "auto-fill" look for the stats bar */
            @media (min-width: 1536px) {
                .auto-fill-stats {
                    grid-template-columns: repeat(8, minmax(0,1fr)) !important;
                }
            }
        </style>

        <!-- Documents Table Card -->
        <div class="card bg-base-100 shadow">
            <div class="card-header bg-base-200">
                <h3 class="card-title">
                    <i class="fa-solid fa-list mr-2"></i>
                    Danh sách tài liệu
                </h3>
            </div>

                <!-- Filter Bar -->
            <div class="card-body border-b border-base-300">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-4">
                        <input type="text" name="search" class="input input-bordered w-full" placeholder="Tìm kiếm..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="md:col-span-2">
                        <select name="status" class="select select-bordered w-full">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Tất cả</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Chờ duyệt</option>
                            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Đã duyệt</option>
                            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Từ chối</option>
                    </select>
                    </div>
                    <div class="md:col-span-2">
                        <select name="user" class="select select-bordered w-full">
                            <option value="0">Tất cả người dùng</option>
                        <?php foreach($users_list as $user): ?>
                            <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($user['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="btn btn-primary w-full">
                            <i class="fa-solid fa-filter mr-2"></i>Lọc
                        </button>
                    </div>
                    <?php if($search || $status_filter !== 'all' || $user_filter > 0): ?>
                        <div class="md:col-span-2">
                            <a href="all-documents.php" class="btn btn-ghost w-full">
                                <i class="fa-solid fa-xmark mr-2"></i>Xóa bộ lọc
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table -->
            <?php 
            // Format file size helper function
            if(!function_exists('formatBytes')) {
                function formatBytes($bytes) {
                    if ($bytes === 0) return '0 B';
                    $k = 1024;
                    $sizes = ['B', 'KB', 'MB', 'GB'];
                    $i = floor(log($bytes) / log($k));
                    return round($bytes / pow($k, $i), 1) . ' ' . $sizes[$i];
                }
            }
            
            if(count($documents) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tài liệu</th>
                                <th>Chủ sở hữu</th>
                                <th>Trạng thái</th>
                                <th>Thông tin</th>
                                <th>Điểm/Bán</th>
                                <th>Ngày tạo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($documents as $doc): 
                                $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                $icon_map = [
                                    'pdf' => 'fa-file-pdf',
                                    'doc' => 'fa-file-word',
                                    'docx' => 'fa-file-word',
                                    'txt' => 'fa-file-lines',
                                    'xlsx' => 'fa-file-excel',
                                    'xls' => 'fa-file-excel',
                                    'ppt' => 'fa-file-powerpoint',
                                    'pptx' => 'fa-file-powerpoint',
                                    'jpg' => 'fa-file-image',
                                    'jpeg' => 'fa-file-image',
                                    'png' => 'fa-file-image',
                                    'zip' => 'fa-file-zipper',
                                    'rar' => 'fa-file-zipper'
                                ];
                                $icon = $icon_map[$ext] ?? 'fa-file';
                                
                                $color_map = [
                                    'pdf' => 'bg-error',
                                    'doc' => 'bg-info',
                                    'docx' => 'bg-info',
                                    'txt' => 'bg-base-300',
                                    'xlsx' => 'bg-success',
                                    'xls' => 'bg-success',
                                    'ppt' => 'bg-warning',
                                    'pptx' => 'bg-warning',
                                    'jpg' => 'bg-secondary',
                                    'jpeg' => 'bg-secondary',
                                    'png' => 'bg-secondary'
                                ];
                                $bg_color = $color_map[$ext] ?? 'bg-neutral';
                                
                                // Get file path for size calculation
                                $file_path = "../uploads/" . $doc['file_name'];
                                $file_size = file_exists($file_path) ? filesize($file_path) : 0;
                            ?>
                                <tr class="hover">
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar placeholder">
                                                <div class="<?= $bg_color ?> text-white rounded w-12 h-12">
                                                    <i class="fa-solid <?= $icon ?> text-xl"></i>
                                                </div>
                                            </div>
                                            <div class="max-w-[250px]">
                                                <a href="view-document.php?id=<?= $doc['id'] ?>" class="font-bold text-sm truncate hover:text-primary hover:underline cursor-pointer block" title="<?= htmlspecialchars($doc['original_name']) ?>">
                                                    <?= htmlspecialchars($doc['original_name']) ?>
                                                </a>
                                                <?php if($doc['description']): ?>
                                                    <div class="text-base-content/70 text-xs truncate">
                                                        <?= htmlspecialchars(substr($doc['description'], 0, 50)) ?>...
                                                    </div>
                                                <?php endif; ?>
                                                <div class="flex gap-2 mt-1">
                                                    <span class="badge badge-xs badge-ghost">.<?= strtoupper($ext) ?></span>
                                                    <span class="badge badge-xs badge-ghost">ID: <?= $doc['id'] ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="avatar <?= !empty($doc['avatar']) ? '' : 'placeholder' ?>">
                                                <div class="bg-neutral text-neutral-content rounded-full w-8 overflow-hidden ring ring-offset-base-100 ring-2 ring-primary/10">
                                                    <?php if(!empty($doc['avatar']) && file_exists('../uploads/avatars/' . $doc['avatar'])): ?>
                                                        <img src="../uploads/avatars/<?= $doc['avatar'] ?>" alt="Avatar" />
                                                    <?php else: ?>
                                                        <span class="text-xs font-bold"><?= strtoupper(substr($doc['username'] ?? 'U', 0, 2)) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="font-medium text-sm"><?= htmlspecialchars($doc['username'] ?? 'Unknown') ?></div>
                                                <div class="text-base-content/70 text-xs"><?= htmlspecialchars(substr($doc['email'] ?? '', 0, 20)) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="space-y-1">
                                            <?php if($doc['status'] === 'pending'): ?>
                                                <div class="badge badge-warning gap-1">
                                                    <i class="fa-solid fa-clock text-xs"></i>
                                                    Chờ
                                                </div>
                                            <?php elseif($doc['status'] === 'approved'): ?>
                                                <div class="badge badge-success gap-1">
                                                    <i class="fa-solid fa-circle-check text-xs"></i>
                                                    Duyệt
                                                </div>
                                            <?php else: ?>
                                                <div class="badge badge-error gap-1">
                                                    <i class="fa-solid fa-circle-xmark text-xs"></i>
                                                    Từ chối
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if($doc['is_public']): ?>
                                                <div class="badge badge-info badge-sm gap-1">
                                                    <i class="fa-solid fa-globe text-xs"></i>
                                                    Public
                                                </div>
                                            <?php else: ?>
                                                <div class="badge badge-outline badge-sm gap-1">
                                                    <i class="fa-solid fa-lock text-xs"></i>
                                                    Private
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="space-y-1 text-xs">
                                            <div class="flex items-center gap-1">
                                                <i class="fa-solid fa-file text-base-content/50"></i>
                                                <span><?= formatBytes($file_size) ?></span>
                                            </div>
                                            <?php if($doc['total_pages'] > 0): ?>
                                                <div class="flex items-center gap-1">
                                                    <i class="fa-solid fa-file-lines text-base-content/50"></i>
                                                    <span><?= number_format($doc['total_pages']) ?> trang</span>
                                                </div>
                                        <?php endif; ?>
                                            <div class="flex items-center gap-1">
                                                <i class="fa-solid fa-eye text-base-content/50"></i>
                                                <span><?= number_format($doc['views'] ?? 0) ?> lượt xem</span>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <i class="fa-solid fa-download text-base-content/50"></i>
                                                <span><?= number_format($doc['downloads'] ?? 0) ?> lượt tải</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($doc['status'] === 'approved'): ?>
                                            <div class="space-y-1">
                                                <div class="flex items-center gap-1">
                                                    <i class="fa-solid fa-coins text-warning text-xs"></i>
                                                    <span class="font-bold text-sm"><?= number_format($doc['admin_points'] ?? $doc['assigned_points'] ?? 0) ?></span>
                                                    <span class="text-xs text-base-content/70">điểm</span>
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    <i class="fa-solid fa-cart-shopping text-success text-xs"></i>
                                                    <span class="font-bold text-sm"><?= $doc['sales_count'] ?? 0 ?></span>
                                                    <span class="text-xs text-base-content/70">bán</span>
                                                </div>
                                                <?php if(($doc['total_earned'] ?? 0) > 0): ?>
                                                    <div class="text-success font-bold text-xs">
                                                        <i class="fa-solid fa-sack-dollar"></i>
                                                        <?= number_format($doc['total_earned']) ?> đ
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-base-content/50 text-xs">Chưa duyệt</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="text-xs text-base-content/70">
                                            <?= date('d/m/Y', strtotime($doc['created_at'])) ?>
                                            <div class="text-xs text-base-content/50">
                                                <?= date('H:i', strtotime($doc['created_at'])) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex gap-1">
                                            <a href="view-document.php?id=<?= $doc['id'] ?>" class="btn btn-ghost btn-sm btn-square" title="Xem chi tiết">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                            <div class="dropdown dropdown-end">
                                                <label tabindex="0" class="btn btn-ghost btn-sm btn-square">
                                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                                </label>
                                                <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                                                    <li>
                                                        <a href="view-document.php?id=<?= $doc['id'] ?>">
                                                            <i class="fa-solid fa-eye"></i>Xem chi tiết
                                                        </a>
                                                    </li>
                                            <?php if($doc['status'] === 'pending'): ?>
                                                        <li>
                                                            <a class="text-success" onclick="openApproveModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['original_name'])) ?>')">
                                                                <i class="fa-solid fa-check"></i>Duyệt
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="text-warning" onclick="openRejectModal(<?= $doc['id'] ?>)">
                                                                <i class="fa-solid fa-xmark"></i>Từ chối
                                                            </a>
                                                        </li>
                                            <?php elseif($doc['status'] === 'approved'): ?>
                                                        <li>
                                                            <a onclick="openChangeStatusModal(<?= $doc['id'] ?>, '<?= $doc['status'] ?>')">
                                                                <i class="fa-solid fa-arrows-rotate"></i>Đổi trạng thái
                                                            </a>
                                                        </li>
                                            <?php elseif($doc['status'] === 'rejected'): ?>
                                                        <li>
                                                            <a class="text-success" onclick="openApproveModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['original_name'])) ?>')">
                                                                <i class="fa-solid fa-check"></i>Duyệt
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a onclick="openChangeStatusModal(<?= $doc['id'] ?>, '<?= $doc['status'] ?>')">
                                                                <i class="fa-solid fa-arrows-rotate"></i>Đổi trạng thái
                                                            </a>
                                                        </li>
                                            <?php endif; ?>
                                                    <li class="menu-title"><span>Nguy hiểm</span></li>
                                                    <li>
                                                        <a class="text-error" onclick="deleteDocument(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['original_name'])) ?>')">
                                                            <i class="fa-solid fa-trash"></i>Xóa
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                    <div class="card-footer flex items-center justify-between border-t border-base-300 p-4">
                        <div class="text-base-content/70 text-sm">
                            Hiển thị <span class="font-bold"><?= $offset + 1 ?></span> đến <span class="font-bold"><?= min($offset + $per_page, $total_documents) ?></span> trong <span class="font-bold"><?= $total_documents ?></span> kết quả
                        </div>
                        <div class="join">
                            <?php if($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&user=<?= $user_filter ?>" class="join-item btn btn-sm">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&user=<?= $user_filter ?>" 
                                   class="join-item btn btn-sm <?= $i == $page ? 'btn-primary btn-active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>&user=<?= $user_filter ?>" class="join-item btn btn-sm">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                <div class="card-body">
                    <div class="text-center py-12">
                        <i class="fa-solid fa-file-circle-xmark text-6xl text-base-content/30 mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Không tìm thấy tài liệu</h3>
                        <p class="text-base-content/70 mb-4">Không có tài liệu nào phù hợp với bộ lọc của bạn.</p>
                        <a href="all-documents.php" class="btn btn-primary">
                            <i class="fa-solid fa-rotate mr-2"></i>Xóa bộ lọc
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Approve Modal -->
<input type="checkbox" id="approveModal" class="modal-toggle" />
<div class="modal">
    <div class="modal-box">
            <form method="POST">
                <input type="hidden" name="document_id" id="approve_doc_id">
                <input type="hidden" name="action" value="approve">

            <h3 class="font-bold text-lg flex items-center gap-2 mb-4">
                <i class="fa-solid fa-check text-success"></i>
                Duyệt tài liệu
            </h3>
            
            <div class="form-control mb-3">
                <label class="label">
                    <span class="label-text">Tài liệu</span>
                </label>
                <input type="text" id="doc_title" class="input input-bordered" readonly>
                </div>

            <div class="form-control mb-3">
                <label class="label">
                    <span class="label-text">Giá trị điểm <span class="text-error">*</span></span>
                </label>
                <div class="join w-full">
                    <span class="join-item btn btn-disabled"><i class="fa-solid fa-coins"></i></span>
                    <input type="number" id="points" name="points" class="input input-bordered join-item flex-1" min="1" max="1000" value="50" required>
                    <span class="join-item btn btn-disabled">điểm</span>
                </div>
                <label class="label">
                    <span class="label-text-alt">Đây là giá tối đa người dùng có thể đặt để bán tài liệu này</span>
                </label>
                </div>

            <div class="form-control mb-3">
                <label class="label">
                    <span class="label-text">Ghi chú Admin</span>
                </label>
                <textarea id="notes" name="notes" class="textarea textarea-bordered" rows="3" placeholder="Thêm ghi chú..."></textarea>
                </div>

            <div class="modal-action">
                <label for="approveModal" class="btn btn-ghost">Hủy</label>
                <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-check mr-2"></i>Duyệt
                </button>
                </div>
            </form>
        </div>
    <label class="modal-backdrop" for="approveModal">Close</label>
    </div>

    <!-- Reject Modal -->
<input type="checkbox" id="rejectModal" class="modal-toggle" />
<div class="modal">
    <div class="modal-box">
            <form method="POST">
                <input type="hidden" name="document_id" id="reject_doc_id">
                <input type="hidden" name="action" value="reject">

            <h3 class="font-bold text-lg flex items-center gap-2 mb-4">
                <i class="fa-solid fa-xmark text-error"></i>
                Từ chối tài liệu
            </h3>
            
            <div class="form-control mb-3">
                <label class="label">
                    <span class="label-text">Lý do từ chối <span class="text-error">*</span></span>
                </label>
                <textarea id="reason" name="rejection_reason" class="textarea textarea-bordered" rows="4" placeholder="Giải thích lý do..." required></textarea>
                </div>

            <div class="alert alert-warning mb-3">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Người dùng sẽ nhận được thông báo về lý do từ chối này.</span>
            </div>
            
            <div class="modal-action">
                <label for="rejectModal" class="btn btn-ghost">Hủy</label>
                <button type="submit" class="btn btn-error">
                    <i class="fa-solid fa-xmark mr-2"></i>Từ chối
                </button>
                </div>
            </form>
        </div>
    <label class="modal-backdrop" for="rejectModal">Close</label>
    </div>

    <!-- Change Status Modal -->
<input type="checkbox" id="changeStatusModal" class="modal-toggle" />
<div class="modal">
    <div class="modal-box">
            <form method="POST">
                <input type="hidden" name="document_id" id="change_status_doc_id">
                <input type="hidden" name="action" value="change_status">

            <h3 class="font-bold text-lg flex items-center gap-2 mb-4">
                <i class="fa-solid fa-arrows-rotate"></i>
                Đổi trạng thái
            </h3>
            
            <div class="form-control mb-3">
                <label class="label">
                    <span class="label-text">Trạng thái mới <span class="text-error">*</span></span>
                </label>
                <select id="new_status" name="new_status" class="select select-bordered" required>
                    <option value="pending">Chờ duyệt</option>
                    <option value="approved">Đã duyệt</option>
                    <option value="rejected">Từ chối</option>
                    </select>
                </div>

            <div class="modal-action">
                <label for="changeStatusModal" class="btn btn-ghost">Hủy</label>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check mr-2"></i>Lưu thay đổi
                </button>
                </div>
            </form>
        </div>
    <label class="modal-backdrop" for="changeStatusModal">Close</label>
    </div>

    <!-- Batch Generate Thumbnails Modal -->
<input type="checkbox" id="batchThumbnailModal" class="modal-toggle" />
<div class="modal">
    <div class="modal-box max-w-3xl">
        <h3 class="font-bold text-lg flex items-center gap-2 mb-4">
            <i class="fa-solid fa-image"></i>
            Tạo Thumbnails hàng loạt
        </h3>
        
        <div id="batchThumbnailStatus" class="mb-3">
            <p class="text-base-content/70">Click "Bắt đầu" để tạo thumbnails cho tất cả tài liệu chưa có.</p>
            </div>
        
                <div id="batchThumbnailProgress" style="display: none;">
            <div class="mb-3">
                <div class="flex justify-between mb-1">
                    <span class="font-bold">Tiến độ:</span>
                    <span id="batchProgressText">0/0</span>
                    </div>
                <progress id="batchProgressBar" class="progress progress-success w-full" value="0" max="100"></progress>
                    </div>
            
            <div id="batchThumbnailLog" class="bg-base-200 rounded p-3 overflow-y-auto font-mono text-sm" style="max-height: 300px;">
                    </div>
                </div>
        
        <div class="modal-action">
            <label for="batchThumbnailModal" class="btn btn-ghost" id="batchCloseBtn">Đóng</label>
            <button type="button" class="btn btn-success" onclick="startBatchThumbnailGeneration()" id="batchStartBtn">
                <i class="fa-solid fa-play mr-2"></i>Bắt đầu
            </button>
                </div>
            </div>
    <label class="modal-backdrop" for="batchThumbnailModal">Close</label>
    </div>

    <!-- PDF.js for thumbnail generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="../js/pdf-functions.js"></script>

    <script>
        function openApproveModal(docId, docTitle) {
            document.getElementById('approve_doc_id').value = docId;
            document.getElementById('doc_title').value = docTitle;
        document.getElementById('approveModal').checked = true;
        }

        function openRejectModal(docId) {
            document.getElementById('reject_doc_id').value = docId;
        document.getElementById('rejectModal').checked = true;
        }

        function openChangeStatusModal(docId, currentStatus) {
            document.getElementById('change_status_doc_id').value = docId;
            document.getElementById('new_status').value = currentStatus;
        document.getElementById('changeStatusModal').checked = true;
        }

        function deleteDocument(docId, docTitle) {
            vsdConfirm({
                title: 'Xác nhận xóa vĩnh viễn',
                message: `Bạn có chắc chắn muốn xóa "${docTitle}"?\n\nHành động này không thể hoàn tác!`,
                confirmText: 'Xóa vĩnh viễn',
                type: 'error',
                onConfirm: () => {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="document_id" value="${docId}">
                        <input type="hidden" name="action" value="delete">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function openBatchThumbnailModal() {
        document.getElementById('batchThumbnailModal').checked = true;
            document.getElementById('batchThumbnailProgress').style.display = 'none';
        document.getElementById('batchThumbnailStatus').innerHTML = '<p class="text-base-content/70">Click "Bắt đầu" để tạo thumbnails cho tất cả tài liệu chưa có.</p>';
            document.getElementById('batchThumbnailLog').innerHTML = '';
            document.getElementById('batchStartBtn').disabled = false;
        }

        async function startBatchThumbnailGeneration() {
            const statusDiv = document.getElementById('batchThumbnailStatus');
            const progressDiv = document.getElementById('batchThumbnailProgress');
            const progressBar = document.getElementById('batchProgressBar');
            const progressText = document.getElementById('batchProgressText');
            const logDiv = document.getElementById('batchThumbnailLog');
            const startBtn = document.getElementById('batchStartBtn');

            startBtn.disabled = true;
            progressDiv.style.display = 'block';
        statusDiv.innerHTML = '<p class="text-info font-bold">Đang tải danh sách tài liệu...</p>';

            try {
                const response = await fetch('../handler/batch_generate_thumbnails.php');
                
                if (!response.ok) {
                    const text = await response.text();
                    throw new Error(`HTTP ${response.status}: ${text.substring(0, 100)}`);
                }
                
                const responseText = await response.text();
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('Response text:', responseText);
                throw new Error('Invalid JSON response: ' + parseError.message);
                }

                if (!data.success) {
                    throw new Error(data.message || 'Failed to fetch documents');
                }

                const documents = data.documents || [];
                const total = documents.length;

                if (total === 0) {
                statusDiv.innerHTML = '<p class="text-success font-bold"><i class="fa-solid fa-check mr-2"></i>Tất cả tài liệu đã có thumbnails!</p>';
                    startBtn.disabled = false;
                    return;
                }

            statusDiv.innerHTML = `<p class="text-info font-bold">Tìm thấy ${total} tài liệu cần tạo thumbnail...</p>`;
                logDiv.innerHTML = '';

                let successCount = 0;
                let failCount = 0;
                let skipCount = 0;

                for (let i = 0; i < documents.length; i++) {
                    const doc = documents[i];
                    const current = i + 1;
                    const percent = Math.round((current / total) * 100);

                progressBar.value = percent;
                    progressText.textContent = `${current}/${total} (${percent}%)`;

                    const logEntry = document.createElement('div');
                logEntry.className = 'mb-1';
                logEntry.innerHTML = `<span class="text-base-content/70">[${current}/${total}]</span> Đang xử lý: ${doc.original_name}...`;
                    logDiv.appendChild(logEntry);
                    logDiv.scrollTop = logDiv.scrollHeight;

                    try {
                        if (!['pdf', 'docx', 'doc'].includes(doc.file_ext)) {
                            logEntry.innerHTML = `<span class="text-base-content/70">[${current}/${total}]</span> <span class="text-warning">⏭️ Bỏ qua: ${doc.original_name} (Định dạng .${doc.file_ext} không hỗ trợ tự động)</span>`;
                            skipCount++;
                            continue;
                        }

                        const docInfoResponse = await fetch('../handler/batch_generate_thumbnails.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ doc_id: doc.id })
                        });

                        if (!docInfoResponse.ok) {
                            const text = await docInfoResponse.text();
                            throw new Error(`HTTP ${docInfoResponse.status}: ${text.substring(0, 100)}`);
                        }

                        const docInfoText = await docInfoResponse.text();
                        
                        if (docInfoText.trim().startsWith('<')) {
                            throw new Error('Server returned HTML instead of JSON');
                        }
                        
                        let docInfo;
                        try {
                            docInfo = JSON.parse(docInfoText);
                        } catch (parseError) {
                            throw new Error('Invalid JSON response');
                        }

                        if (!docInfo.success) {
                            throw new Error(docInfo.message || 'Failed to get document info');
                        }

                        if (['docx', 'doc'].includes(doc.file_ext)) {
                            const delaySeconds = 5;
                            logEntry.innerHTML = `<span class="text-base-content/70">[${current}/${total}]</span> <span class="text-warning">⏳ Đợi ${delaySeconds}s (Đang chuyển đổi)...</span>`;
                            
                            for (let countdown = delaySeconds; countdown > 0; countdown--) {
                                await new Promise(resolve => setTimeout(resolve, 1000));
                                logEntry.innerHTML = `<span class="text-base-content/70">[${current}/${total}]</span> <span class="text-warning">⏳ Đợi ${countdown}s (Đang chuyển đổi)...</span>`;
                            }
                            
                            if (docInfo.skip === true || !docInfo.converted_pdf_path) {
                                const skipReason = docInfo.message || 'Không thể chuyển đổi sang PDF';
                                logEntry.innerHTML = `<span class="text-base-content/70">[${current}/${total}]</span> <span class="text-warning">⏭️ Bỏ qua: ${doc.original_name} (${skipReason})</span>`;
                                skipCount++;
                                continue;
                            }
                            
                            docInfo.file_path = docInfo.converted_pdf_path;
                            docInfo.file_ext = 'pdf';
                        }

                        if (docInfo.file_ext === 'pdf') {
                            const pdfPath = docInfo.file_path;
                            
                            if (!pdfPath) {
                                throw new Error('No PDF path available');
                            }

                            let pdfUrl = pdfPath;
                            if (!pdfPath.startsWith('http://') && !pdfPath.startsWith('https://') && !pdfPath.startsWith('data:')) {
                                if (!pdfPath.startsWith('../') && !pdfPath.startsWith('/')) {
                                    pdfUrl = '../' + pdfPath;
                                } else if (pdfPath.startsWith('/')) {
                                    pdfUrl = '..' + pdfPath;
                                }
                            }

                            const result = await processPdfDocument(pdfUrl, docInfo.doc_id, {
                                countPages: true,
                                generateThumbnail: true,
                                thumbnailWidth: 400
                            });
                            
                            let successMessage = `<span class="text-base-content/70">[${current}/${total}]</span> <span class="text-success">✓ ${doc.original_name}`;
                            if (result.pages && result.pages > 0) {
                                successMessage += ` (${result.pages} trang)`;
                            }
                            successMessage += '</span>';
                            
                            logEntry.innerHTML = successMessage;
                            successCount++;
                        } else {
                            const skipReason = docInfo.message || 'Định dạng không được hỗ trợ';
                            logEntry.innerHTML = `<span class="text-base-content/70">[${current}/${total}]</span> <span class="text-warning">⏭️ Bỏ qua: ${doc.original_name} (${skipReason})</span>`;
                            skipCount++;
                        }
                    } catch (error) {
                    logEntry.innerHTML = `<span class="text-base-content/70">[${current}/${total}]</span> <span class="text-error">✗ ${doc.original_name} - ${error.message}</span>`;
                        failCount++;
                        console.error('Error processing document:', doc.id, error);
                    }

                    await new Promise(resolve => setTimeout(resolve, 100));
                }

                let summaryDetails = [];
            summaryDetails.push(`<span class="text-success">✓ Thành công: ${successCount}</span>`);
            summaryDetails.push(`<span class="text-error">✗ Thất bại: ${failCount}</span>`);
            summaryDetails.push(`<span class="text-warning">⏭️ Bỏ qua: ${skipCount}</span>`);
                
                statusDiv.innerHTML = `
                <p class="text-success font-bold"><i class="fa-solid fa-check mr-2"></i>Hoàn tất!</p>
                    <p>${summaryDetails.join(' | ')}</p>
                `;
            progressBar.value = 100;
            progressText.textContent = `Hoàn tất: ${successCount} thành công, ${failCount} thất bại, ${skipCount} bỏ qua`;

                startBtn.disabled = false;

            } catch (error) {
            statusDiv.innerHTML = `<p class="text-error font-bold"><i class="fa-solid fa-circle-exclamation mr-2"></i>Lỗi:</strong> ${error.message}</p>`;
            logDiv.innerHTML += `<div class="text-error">Error: ${error.message}</div>`;
                startBtn.disabled = false;
                console.error('Batch thumbnail generation error:', error);
            }
        }
    </script>

<?php 
include __DIR__ . '/../includes/admin-footer.php';
// db connection cleaned up by app flow
?>
