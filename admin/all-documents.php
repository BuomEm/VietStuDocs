<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';
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
$search = isset($_GET['search']) ? $VSD->escape($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $VSD->escape($_GET['status']) : 'all';

$where = [];
if($search) $where[] = "(d.original_name LIKE '%$search%' OR u.username LIKE '%$search%')";
if($status_filter !== 'all') $where[] = "d.status='$status_filter'";
$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$total_docs = $VSD->get_row("SELECT COUNT(*) as c FROM documents d LEFT JOIN users u ON d.user_id = u.id $where_sql")['c'];
$total_pages = ceil($total_docs / $per_page);

$docs = $VSD->get_list("
    SELECT d.*, u.username, u.avatar,
           (SELECT COUNT(*) FROM document_sales WHERE document_id=d.id) as sales,
           (SELECT SUM(points_paid) FROM document_sales WHERE document_id=d.id) as earned
    FROM documents d
    LEFT JOIN users u ON d.user_id = u.id
    $where_sql
    ORDER BY d.created_at DESC
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

<div class="min-h-screen bg-base-200/50 p-4 lg:p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header & Stats -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-folder-tree text-primary"></i>
                    Quản lý tài liệu
                </h1>
                <p class="text-base-content/60 text-sm mt-1">Kho lưu trữ toàn bộ <?= number_format($stats['total']) ?> tài liệu của hệ thống</p>
            </div>
            
            <div class="flex gap-2">
                <div class="stats shadow bg-base-100 text-xs md:text-sm">
                    <div class="stat px-4 py-2 place-items-center">
                        <div class="stat-title text-warning font-bold">Chờ duyệt</div>
                        <div class="stat-value text-warning text-xl"><?= number_format($stats['pending']) ?></div>
                    </div>
                    <div class="stat px-4 py-2 place-items-center border-l border-base-200">
                        <div class="stat-title text-success font-bold">Đã duyệt</div>
                        <div class="stat-value text-success text-xl"><?= number_format($stats['approved']) ?></div>
                    </div>
                    <div class="stat px-4 py-2 place-items-center border-l border-base-200">
                        <div class="stat-title text-error font-bold">Từ chối</div>
                        <div class="stat-value text-error text-xl"><?= number_format($stats['rejected']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Actions -->
        <div class="card bg-base-100 shadow-sm border border-base-200">
            <div class="card-body p-4">
                <div class="flex flex-col md:flex-row gap-4 justify-between items-center">
                    <form method="GET" class="flex-1 w-full flex gap-3">
                        <div class="relative flex-1">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Tìm tên tài liệu, người đăng..." class="input input-bordered w-full pl-10">
                        </div>
                        <select name="status" class="select select-bordered" onchange="this.form.submit()">
                            <option value="all">Tất cả trạng thái</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Chờ duyệt</option>
                            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Đã duyệt</option>
                            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Đã từ chối</option>
                        </select>
                    </form>
                    
                    <div id="bulk-actions" class="hidden gap-2 items-center bg-base-200 px-3 py-1 rounded-lg animate-fade-in">
                        <span class="text-sm font-bold" id="selected-count">0 được chọn</span>
                        <div class="h-4 w-px bg-base-content/20 mx-1"></div>
                        <button onclick="bulkDelete()" class="btn btn-xs btn-error btn-outline">
                            <i class="fa-solid fa-trash"></i> Xóa
                        </button>
                    </div>
                </div>
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
                            <th>Người đăng</th>
                            <th class="text-center">Số liệu</th>
                            <th>Trạng thái</th>
                            <th class="text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($docs) > 0): ?>
                            <?php foreach($docs as $doc): 
                                $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                $icon = match($ext) { 'pdf'=>'fa-file-pdf', 'doc'=>'fa-file-word', 'docx'=>'fa-file-word', default=>'fa-file' };
                                $color = match($ext) { 'pdf'=>'text-error', 'doc'=>'text-info', 'docx'=>'text-info', default=>'text-base-content/50' };
                            ?>
                            <tr class="hover group">
                                <td>
                                    <label>
                                        <input type="checkbox" class="checkbox checkbox-sm doc-check" value="<?= $doc['id'] ?>" onchange="updateBulkState()">
                                    </label>
                                </td>
                                <td>
                                    <div class="flex items-start gap-3 max-w-sm">
                                        <div class="w-10 h-10 rounded-lg bg-base-200 grid place-items-center flex-shrink-0 text-xl <?= $color ?>">
                                            <i class="fa-solid <?= $icon ?>"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <a href="../admin/view-document.php?id=<?= $doc['id'] ?>" target="_blank" class="font-bold hover:text-primary truncate block" title="<?= htmlspecialchars($doc['original_name']) ?>">
                                                <?= htmlspecialchars($doc['original_name']) ?>
                                            </a>
                                            <div class="text-xs text-base-content/50 flex items-center gap-2 mt-0.5">
                                                <span class="badge badge-xs badge-ghost font-mono"><?= strtoupper($ext) ?></span>
                                                <span><?= date('d/m/Y', strtotime($doc['created_at'])) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="avatar placeholder">
                                            <div class="w-6 h-6 rounded-full bg-base-200">
                                                <span class="text-xs"><?= strtoupper(substr($doc['username'],0,1)) ?></span>
                                            </div>
                                        </div>
                                        <span class="text-sm font-medium"><?= htmlspecialchars($doc['username']) ?></span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="flex flex-col text-xs">
                                        <span class="font-bold whitespace-nowrap"><i class="fa-solid fa-eye text-base-content/40 mr-1"></i><?= number_format($doc['views'] ?? 0) ?></span>
                                        <span class="opacity-70 whitespace-nowrap"><i class="fa-solid fa-download text-base-content/40 mr-1"></i><?= number_format($doc['downloads'] ?? 0) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if($doc['status'] === 'approved'): ?>
                                        <div class="badge badge-success badge-sm gap-1 pl-1 pr-2">
                                            <i class="fa-solid fa-check-circle"></i> Đã duyệt
                                        </div>
                                    <?php elseif($doc['status'] === 'pending'): ?>
                                        <div class="badge badge-warning badge-sm gap-1 pl-1 pr-2">
                                            <i class="fa-solid fa-clock"></i> Chờ duyệt
                                        </div>
                                    <?php else: ?>
                                        <div class="badge badge-error badge-sm gap-1 pl-1 pr-2">
                                            <i class="fa-solid fa-circle-xmark"></i> Từ chối
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="join">
                                        <?php if($doc['status'] === 'pending'): ?>
                                            <button onclick="openApproveModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['original_name'])) ?>')" class="btn btn-sm btn-success btn-square join-item text-white" title="Duyệt">
                                                <i class="fa-solid fa-check"></i>
                                            </button>
                                            <button onclick="openRejectModal(<?= $doc['id'] ?>)" class="btn btn-sm btn-warning btn-square join-item text-white" title="Từ chối">
                                                <i class="fa-solid fa-xmark"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="confirmDelete(<?= $doc['id'] ?>)" class="btn btn-sm btn-ghost btn-square join-item text-error" title="Xóa">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-12 text-base-content/50">
                                    <i class="fa-solid fa-folder-open text-4xl mb-3 block"></i>
                                    Không tìm thấy tài liệu nào
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
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status_filter ?>" class="join-item btn btn-sm <?= $page === $i ? 'btn-active btn-primary' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
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
        
        if (checked.length > 0) bulkDiv.classList.remove('hidden', 'flex'); 
        if (checked.length > 0) bulkDiv.classList.add('flex');
        else bulkDiv.classList.add('hidden');
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
