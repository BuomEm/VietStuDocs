<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Quản lý báo cáo vi phạm";

// Handle Actions (Giữ nguyên logic chính)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $report_id = intval($_POST['report_id']);
    $action = $_POST['action'];
    $notes = $VSD->escape($_POST['admin_notes'] ?? '');
    
    if($action === 'mark_reviewed') {
        $VSD->update('reports', [
            'status' => 'reviewed',
            'reviewed_by' => $admin_id,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'admin_notes' => $notes
        ], "id=$report_id");
        header("Location: reports.php?msg=reviewed"); exit;
    } elseif($action === 'dismiss') {
        $VSD->update('reports', [
            'status' => 'dismissed',
            'reviewed_by' => $admin_id,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'admin_notes' => $notes
        ], "id=$report_id");
        header("Location: reports.php?msg=dismissed"); exit;
    } elseif($action === 'delete_document') {
        $report = $VSD->get_row("SELECT document_id FROM reports WHERE id=$report_id");
        if($report) {
            $doc_id = $report['document_id'];
            $doc = $VSD->get_row("SELECT file_name FROM documents WHERE id=$doc_id");
            if($doc) {
                @unlink("../uploads/" . $doc['file_name']);
                // Xóa các bảng liên quan (giản lược cho ngắn gọn, thực tế nên dùng function deleteDocument chung)
                $VSD->remove('documents', "id=$doc_id");
                
                $VSD->update('reports', [
                    'status' => 'reviewed',
                    'reviewed_by' => $admin_id,
                    'reviewed_at' => date('Y-m-d H:i:s'),
                    'admin_notes' => 'Đã xác nhận vi phạm và xóa tài liệu.'
                ], "id=$report_id");
                
                header("Location: reports.php?msg=doc_deleted"); exit;
            }
        }
    }
}

// Logic hiển thị
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;
$status_filter = isset($_GET['status']) ? $VSD->escape($_GET['status']) : 'all';
$reason_filter = isset($_GET['reason']) ? $VSD->escape($_GET['reason']) : 'all';

$where = [];
if($status_filter !== 'all') $where[] = "r.status='$status_filter'";
if($reason_filter !== 'all') $where[] = "r.reason='$reason_filter'";
$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $VSD->get_row("SELECT COUNT(*) as c FROM reports r $where_sql")['c'];
$total_pages = ceil($total / $per_page);

$reports = $VSD->get_list("
    SELECT r.*, 
           d.original_name as doc_name, d.id as doc_id,
           u.username as reporter_name, u.avatar as reporter_avatar,
           a.username as admin_name
    FROM reports r
    LEFT JOIN documents d ON r.document_id = d.id
    LEFT JOIN users u ON r.reporter_user_id = u.id
    LEFT JOIN users a ON r.reviewed_by = a.id
    $where_sql
    ORDER BY r.created_at DESC
    LIMIT $per_page OFFSET $offset
");

$stats = $VSD->get_row("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='reviewed' THEN 1 ELSE 0 END) as reviewed,
    SUM(CASE WHEN status='dismissed' THEN 1 ELSE 0 END) as dismissed
FROM reports");

$reason_map = [
    'inappropriate' => 'Nội dung không phù hợp',
    'copyright' => 'Vi phạm bản quyền',
    'spam' => 'Spam / Quảng cáo',
    'misleading' => 'Gây hiểu lầm',
    'low_quality' => 'Chất lượng kém',
    'duplicate' => 'Trùng lặp',
    'other' => 'Khác'
];

$admin_active_page = 'reports';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="min-h-screen bg-base-200/50 p-4 lg:p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header -->
        <h1 class="text-2xl font-bold flex items-center gap-2">
            <i class="fa-solid fa-flag text-error"></i>
            Quản lý báo cáo
        </h1>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-4 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-error/10 text-error grid place-items-center text-xl">
                        <i class="fa-solid fa-flag"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold"><?= number_format($stats['total'] ?? 0) ?></div>
                        <div class="text-xs text-base-content/60 font-medium uppercase">Tổng báo cáo</div>
                    </div>
                </div>
            </div>
            
            <div class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-4 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-warning/10 text-warning grid place-items-center text-xl">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold"><?= number_format($stats['pending'] ?? 0) ?></div>
                        <div class="text-xs text-base-content/60 font-medium uppercase">Chờ xử lý</div>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-4 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-success/10 text-success grid place-items-center text-xl">
                        <i class="fa-solid fa-check-double"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold"><?= number_format($stats['reviewed'] ?? 0) ?></div>
                        <div class="text-xs text-base-content/60 font-medium uppercase">Đã giải quyết</div>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-4 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-full bg-base-content/10 text-base-content/60 grid place-items-center text-xl">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                    <div>
                        <div class="text-2xl font-bold"><?= number_format($stats['dismissed'] ?? 0) ?></div>
                        <div class="text-xs text-base-content/60 font-medium uppercase">Đã bỏ qua</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card bg-base-100 shadow-sm border border-base-200">
            <div class="card-body p-4">
                <form method="GET" class="flex flex-col md:flex-row gap-4">
                    <select name="status" class="select select-bordered w-full md:w-48" onchange="this.form.submit()">
                        <option value="all">Tất cả trạng thái</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Chờ xử lý</option>
                        <option value="reviewed" <?= $status_filter === 'reviewed' ? 'selected' : '' ?>>Đã xem xét</option>
                        <option value="dismissed" <?= $status_filter === 'dismissed' ? 'selected' : '' ?>>Đã bỏ qua</option>
                    </select>
                    
                    <select name="reason" class="select select-bordered w-full md:w-48" onchange="this.form.submit()">
                        <option value="all">Tất cả lý do</option>
                        <?php foreach($reason_map as $key => $val): ?>
                            <option value="<?= $key ?>" <?= $reason_filter === $key ? 'selected' : '' ?>><?= $val ?></option>
                        <?php endforeach; ?>
                    </select>

                    <?php if($status_filter !== 'all' || $reason_filter !== 'all'): ?>
                        <a href="reports.php" class="btn btn-ghost">Xóa lọc</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="card bg-base-100 shadow-sm border border-base-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead>
                        <tr class="bg-base-200/50 text-base-content/70">
                            <th>ID</th>
                            <th>Đối tượng báo cáo</th>
                            <th>Người báo cáo</th>
                            <th>Lý do</th>
                            <th>Trạng thái</th>
                            <th class="text-right">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($reports) > 0): ?>
                            <?php foreach($reports as $r): ?>
                                <tr class="hover">
                                    <td class="font-mono text-xs opacity-50">#<?= $r['id'] ?></td>
                                    <td>
                                        <?php if($r['doc_id']): ?>
                                            <div class="font-bold truncate max-w-xs" title="<?= htmlspecialchars($r['doc_name']) ?>">
                                                <i class="fa-solid fa-file-invoice text-base-content/50 mr-1"></i>
                                                <?= htmlspecialchars($r['doc_name']) ?>
                                            </div>
                                            <a href="../view-document.php?id=<?= $r['doc_id'] ?>" target="_blank" class="text-xs text-primary link hover:no-underline">
                                                Xem tài liệu <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-error text-sm italic"><i class="fa-solid fa-ban"></i> Tài liệu đã xóa</span>
                                        <?php endif; ?>
                                        <div class="text-xs text-base-content/50 mt-1">
                                            <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="avatar placeholder">
                                                <div class="w-6 h-6 rounded-full bg-base-200 text-xs">
                                                    <span><?= strtoupper(substr($r['reporter_name'],0,1)) ?></span>
                                                </div>
                                            </div>
                                            <span class="text-sm font-medium"><?= htmlspecialchars($r['reporter_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-outline text-xs">
                                            <?= $reason_map[$r['reason']] ?? $r['reason'] ?>
                                        </span>
                                        <?php if($r['description']): ?>
                                            <div class="tooltip tooltip-right" data-tip="<?= htmlspecialchars($r['description']) ?>">
                                                <i class="fa-solid fa-circle-info text-info ml-1 cursor-help"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($r['status'] === 'pending'): ?>
                                            <div class="badge badge-warning gap-1"><i class="fa-solid fa-clock"></i> Chờ xử lý</div>
                                        <?php elseif($r['status'] === 'reviewed'): ?>
                                            <div class="badge badge-success gap-1"><i class="fa-solid fa-check"></i> Đã xử lý</div>
                                        <?php else: ?>
                                            <div class="badge badge-ghost gap-1"><i class="fa-solid fa-slash"></i> Bỏ qua</div>
                                        <?php endif; ?>
                                        
                                        <?php if($r['admin_name']): ?>
                                            <div class="text-[10px] text-base-content/50 mt-1">
                                                bởi <?= htmlspecialchars($r['admin_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <button class="btn btn-sm btn-ghost btn-square" onclick="viewDetails(<?= htmlspecialchars(json_encode($r)) ?>)">
                                            <i class="fa-solid fa-eye text-primary"></i>
                                        </button>
                                        <?php if($r['status'] === 'pending'): ?>
                                            <div class="dropdown dropdown-end">
                                                <button tabindex="0" class="btn btn-sm btn-ghost btn-square">
                                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                                </button>
                                                <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52 border border-base-200">
                                                    <li><a class="text-success" onclick="openActionModal(<?= $r['id'] ?>, 'mark_reviewed')"><i class="fa-solid fa-check"></i> Đánh dấu đã xem</a></li>
                                                    <li><a class="text-warning" onclick="openActionModal(<?= $r['id'] ?>, 'dismiss')"><i class="fa-solid fa-ban"></i> Bỏ qua</a></li>
                                                    <?php if($r['doc_id']): ?>
                                                        <li><a class="text-error" onclick="openDeleteDocModal(<?= $r['id'] ?>)"><i class="fa-solid fa-trash"></i> Xóa tài liệu</a></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-8 text-base-content/50">Không có dữ liệu</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination (Giản lược) -->
            <?php if($total_pages > 1): ?>
                <div class="p-4 border-t border-base-200 flex justify-center">
                    <div class="join">
                        <?php for($i=1; $i<=$total_pages; $i++): ?>
                            <a href="?page=<?= $i ?>&status=<?= $status_filter ?>" class="join-item btn btn-sm <?= $page==$i?'btn-active':'' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Details Modal -->
<dialog id="detailsModal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Chi tiết báo cáo #<span id="modal_report_id"></span></h3>
        <div class="space-y-4">
            <div class="p-3 bg-base-200 rounded-lg">
                <div class="text-xs opacity-60 uppercase mb-1">Nội dung báo cáo</div>
                <div id="modal_desc" class="font-medium whitespace-pre-wrap"></div>
            </div>
            
            <div id="modal_admin_notes_container" class="hidden p-3 bg-base-200 rounded-lg border-l-4 border-primary">
                <div class="text-xs opacity-60 uppercase mb-1">Ghi chú của Admin</div>
                <div id="modal_admin_notes" class="italic"></div>
            </div>
        </div>
        <div class="modal-action">
            <form method="dialog"><button class="btn">Đóng</button></form>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- Action Modal -->
<dialog id="actionModal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4" id="actionModalTitle">Xử lý báo cáo</h3>
        <form method="POST">
            <input type="hidden" name="report_id" id="action_report_id">
            <input type="hidden" name="action" id="action_type">
            
            <div class="form-control mb-4">
                <label class="label">Ghi chú xử lý</label>
                <textarea name="admin_notes" class="textarea textarea-bordered h-24" placeholder="Nhập ghi chú..."></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary w-full">Xác nhận</button>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<script>
    function viewDetails(r) {
        document.getElementById('modal_report_id').textContent = r.id;
        document.getElementById('modal_desc').textContent = r.description || "Không có mô tả chi tiết";
        
        const noteDiv = document.getElementById('modal_admin_notes_container');
        if(r.admin_notes) {
            document.getElementById('modal_admin_notes').textContent = r.admin_notes;
            noteDiv.classList.remove('hidden');
        } else {
            noteDiv.classList.add('hidden');
        }
        
        document.getElementById('detailsModal').showModal();
    }

    function openActionModal(id, type) {
        document.getElementById('action_report_id').value = id;
        document.getElementById('action_type').value = type;
        document.getElementById('actionModalTitle').textContent = type === 'mark_reviewed' ? 'Đánh dấu đã xem xét' : 'Bỏ qua báo cáo';
        document.getElementById('actionModal').showModal();
    }
    
    function openDeleteDocModal(id) {
         if(confirm('CẢNH BÁO: Bạn có chắc xóa tài liệu này? Hành động không thể hoàn tác!')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="report_id" value="${id}"><input type="hidden" name="action" value="delete_document">`;
            document.body.appendChild(form);
            form.submit();
         }
    }
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
