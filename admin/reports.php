<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Quản lý báo cáo - Admin Panel";

// Handle actions
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $report_id = intval($_POST['report_id']);
    $action = $_POST['action'];
    
    if($action === 'mark_reviewed') {
        $admin_notes = mysqli_real_escape_string($conn, $_POST['admin_notes'] ?? '');
        mysqli_query($conn, "UPDATE reports SET status='reviewed', reviewed_by=$admin_id, reviewed_at=NOW(), admin_notes='$admin_notes' WHERE id=$report_id");
        header("Location: reports.php?msg=reviewed");
        exit;
    } elseif($action === 'dismiss') {
        $admin_notes = mysqli_real_escape_string($conn, $_POST['admin_notes'] ?? '');
        mysqli_query($conn, "UPDATE reports SET status='dismissed', reviewed_by=$admin_id, reviewed_at=NOW(), admin_notes='$admin_notes' WHERE id=$report_id");
        header("Location: reports.php?msg=dismissed");
        exit;
    } elseif($action === 'delete_document') {
        // Get report and document info
        $report = mysqli_fetch_assoc(mysqli_query($conn, "SELECT document_id FROM reports WHERE id=$report_id"));
        if($report) {
            $doc_id = $report['document_id'];
            $doc = mysqli_fetch_assoc(mysqli_query($conn, "SELECT file_name FROM documents WHERE id=$doc_id"));
            
            if($doc) {
                // Delete file
                $file_path = "../uploads/" . $doc['file_name'];
                if(file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // Delete document from database
                mysqli_query($conn, "DELETE FROM documents WHERE id=$doc_id");
                
                // Mark report as reviewed
                $admin_notes = "Đã xóa tài liệu";
                mysqli_query($conn, "UPDATE reports SET status='reviewed', reviewed_by=$admin_id, reviewed_at=NOW(), admin_notes='$admin_notes' WHERE id=$report_id");
                
                header("Location: reports.php?msg=doc_deleted");
                exit;
            }
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
$reason_filter = isset($_GET['reason']) ? mysqli_real_escape_string($conn, $_GET['reason']) : 'all';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_clauses = [];
if($status_filter !== 'all') {
    $where_clauses[] = "r.status='$status_filter'";
}
if($reason_filter !== 'all') {
    $where_clauses[] = "r.reason='$reason_filter'";
}
$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total count
$total_query = "SELECT COUNT(*) as total FROM reports r $where_sql";
$total_result = mysqli_fetch_assoc(mysqli_query($conn, $total_query));
$total_reports = intval($total_result['total'] ?? 0);
$total_pages = ceil($total_reports / $per_page);

// Get reports
$reports_query = "
    SELECT r.*, 
           d.original_name as doc_name, d.file_name, d.id as doc_id,
           u.username as reporter_name,
           a.username as reviewer_name
    FROM reports r
    LEFT JOIN documents d ON r.document_id = d.id
    LEFT JOIN users u ON r.reporter_user_id = u.id
    LEFT JOIN users a ON r.reviewed_by = a.id
    $where_sql
    ORDER BY r.created_at DESC
    LIMIT $per_page OFFSET $offset
";
$reports = mysqli_query($conn, $reports_query);

// Get statistics
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        COALESCE(SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END), 0) as pending_count,
        COALESCE(SUM(CASE WHEN status='reviewed' THEN 1 ELSE 0 END), 0) as reviewed_count,
        COALESCE(SUM(CASE WHEN status='dismissed' THEN 1 ELSE 0 END), 0) as dismissed_count
    FROM reports
"));

// Ensure all stats are integers (fallback to 0 if null)
$stats['total'] = intval($stats['total'] ?? 0);
$stats['pending_count'] = intval($stats['pending_count'] ?? 0);
$stats['reviewed_count'] = intval($stats['reviewed_count'] ?? 0);
$stats['dismissed_count'] = intval($stats['dismissed_count'] ?? 0);

// Get unread notifications count
$unread_notifications = mysqli_num_rows(mysqli_query($conn, 
    "SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0"));

// For shared admin sidebar
$admin_active_page = 'reports';

// Include header
include __DIR__ . '/../includes/admin-header.php';
?>

<!-- Page Header -->
<div class="p-6 bg-base-100 border-b border-base-300">
    <div class="container mx-auto max-w-7xl">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Quản Lý Báo Cáo
                </h2>
                <p class="text-base-content/70 mt-1">Tổng cộng <?= number_format($total_reports) ?> báo cáo</p>
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
                    'reviewed' => 'Báo cáo đã được đánh dấu là đã xem xét!',
                    'dismissed' => 'Báo cáo đã bị bỏ qua!',
                    'doc_deleted' => 'Tài liệu đã được xóa!'
                ];
                echo $messages[$_GET['msg']] ?? 'Thao tác thành công!';
                ?>
            </span>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Tổng báo cáo</div>
                    <div class="stat-value text-primary text-3xl font-bold"><?= number_format($stats['total']) ?></div>
                </div>
            </div>
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Chờ xử lý</div>
                    <div class="stat-value text-warning text-3xl font-bold"><?= number_format($stats['pending_count']) ?></div>
                </div>
            </div>
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Đã xem xét</div>
                    <div class="stat-value text-success text-3xl font-bold"><?= number_format($stats['reviewed_count']) ?></div>
                </div>
            </div>
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="text-xs uppercase font-semibold text-base-content/70">Đã bỏ qua</div>
                    <div class="stat-value text-base-content/50 text-3xl font-bold"><?= number_format($stats['dismissed_count']) ?></div>
                </div>
            </div>
        </div>

        <!-- Reports Table Card -->
        <div class="card bg-base-100 shadow">
            <div class="card-header bg-base-200">
                <h3 class="card-title">
                    <i class="fa-solid fa-list mr-2"></i>
                    Danh sách báo cáo
                </h3>
            </div>

            <!-- Filter Bar -->
            <div class="card-body border-b border-base-300">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="md:col-span-3">
                        <select name="status" class="select select-bordered w-full">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Tất cả trạng thái</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Chờ xử lý</option>
                            <option value="reviewed" <?= $status_filter === 'reviewed' ? 'selected' : '' ?>>Đã xem xét</option>
                            <option value="dismissed" <?= $status_filter === 'dismissed' ? 'selected' : '' ?>>Đã bỏ qua</option>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <select name="reason" class="select select-bordered w-full">
                            <option value="all" <?= $reason_filter === 'all' ? 'selected' : '' ?>>Tất cả lý do</option>
                            <option value="inappropriate" <?= $reason_filter === 'inappropriate' ? 'selected' : '' ?>>Nội dung không phù hợp</option>
                            <option value="copyright" <?= $reason_filter === 'copyright' ? 'selected' : '' ?>>Vi phạm bản quyền</option>
                            <option value="spam" <?= $reason_filter === 'spam' ? 'selected' : '' ?>>Spam / Quảng cáo</option>
                            <option value="misleading" <?= $reason_filter === 'misleading' ? 'selected' : '' ?>>Tiêu đề gây hiểu lầm</option>
                            <option value="low_quality" <?= $reason_filter === 'low_quality' ? 'selected' : '' ?>>Chất lượng kém</option>
                            <option value="duplicate" <?= $reason_filter === 'duplicate' ? 'selected' : '' ?>>Trùng lặp nội dung</option>
                            <option value="other" <?= $reason_filter === 'other' ? 'selected' : '' ?>>Lý do khác</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="btn btn-primary w-full">
                            <i class="fa-solid fa-filter mr-2"></i>Lọc
                        </button>
                    </div>
                    <?php if($status_filter !== 'all' || $reason_filter !== 'all'): ?>
                        <div class="md:col-span-2">
                            <a href="reports.php" class="btn btn-ghost w-full">
                                <i class="fa-solid fa-xmark mr-2"></i>Xóa bộ lọc
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table -->
            <?php if(mysqli_num_rows($reports) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tài liệu</th>
                                <th>Người báo cáo</th>
                                <th>Lý do</th>
                                <th>Trạng thái</th>
                                <th>Ngày báo cáo</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($report = mysqli_fetch_assoc($reports)): 
                                $reason_labels = [
                                    'inappropriate' => 'Không phù hợp',
                                    'copyright' => 'Vi phạm bản quyền',
                                    'spam' => 'Spam',
                                    'misleading' => 'Gây hiểu lầm',
                                    'low_quality' => 'Chất lượng kém',
                                    'duplicate' => 'Trùng lặp',
                                    'other' => 'Khác'
                                ];
                                $reason_label = $reason_labels[$report['reason']] ?? $report['reason'];
                            ?>
                                <tr class="hover">
                                    <td><?= $report['id'] ?></td>
                                    <td>
                                        <div class="max-w-[250px]">
                                            <div class="font-bold text-sm truncate" title="<?= htmlspecialchars($report['doc_name'] ?? 'N/A') ?>">
                                                <?= htmlspecialchars($report['doc_name'] ?? 'N/A') ?>
                                            </div>
                                            <?php if($report['doc_id']): ?>
                                                <a href="../view.php?id=<?= $report['doc_id'] ?>" target="_blank" class="text-xs text-primary hover:underline">
                                                    Xem tài liệu →
                                                </a>
                                            <?php else: ?>
                                                <span class="text-xs text-error">Đã xóa</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-sm"><?= htmlspecialchars($report['reporter_name'] ?? 'Unknown') ?></div>
                                    </td>
                                    <td>
                                        <div class="badge badge-warning badge-sm"><?= htmlspecialchars($reason_label) ?></div>
                                    </td>
                                    <td>
                                        <?php if($report['status'] === 'pending'): ?>
                                            <div class="badge badge-warning gap-1">
                                                <i class="fa-solid fa-clock text-xs"></i>
                                                Chờ xử lý
                                            </div>
                                        <?php elseif($report['status'] === 'reviewed'): ?>
                                            <div class="badge badge-success gap-1">
                                                <i class="fa-solid fa-circle-check text-xs"></i>
                                                Đã xem xét
                                            </div>
                                        <?php else: ?>
                                            <div class="badge badge-ghost gap-1">
                                                <i class="fa-solid fa-ban text-xs"></i>
                                                Đã bỏ qua
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="text-xs text-base-content/70">
                                            <?= date('d/m/Y H:i', strtotime($report['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-ghost btn-sm btn-square" onclick="viewReport(<?= htmlspecialchars(json_encode($report)) ?>)">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="card-footer flex items-center justify-between border-t border-base-300 p-4">
                    <div class="text-base-content/70 text-sm">
                        Hiển thị <span class="font-bold"><?= $offset + 1 ?></span> đến <span class="font-bold"><?= min($offset + $per_page, $total_reports) ?></span> trong <span class="font-bold"><?= $total_reports ?></span> kết quả
                    </div>
                    <div class="join">
                        <?php if($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&status=<?= $status_filter ?>&reason=<?= $reason_filter ?>" class="join-item btn btn-sm">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&status=<?= $status_filter ?>&reason=<?= $reason_filter ?>" 
                               class="join-item btn btn-sm <?= $i == $page ? 'btn-primary btn-active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&status=<?= $status_filter ?>&reason=<?= $reason_filter ?>" class="join-item btn btn-sm">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="card-body">
                    <div class="text-center py-12">
                        <i class="fa-solid fa-clipboard-list text-6xl text-base-content/30 mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Không có báo cáo nào</h3>
                        <p class="text-base-content/70 mb-4">Không tìm thấy báo cáo nào phù hợp với bộ lọc của bạn.</p>
                        <a href="reports.php" class="btn btn-primary">
                            <i class="fa-solid fa-rotate mr-2"></i>Xóa bộ lọc
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Report Modal -->
<input type="checkbox" id="viewReportModal" class="modal-toggle" />
<div class="modal">
    <div class="modal-box max-w-2xl">
        <h3 class="font-bold text-lg flex items-center gap-2 mb-4">
            <i class="fa-solid fa-triangle-exclamation text-warning"></i>
            Chi tiết báo cáo
        </h3>
        
        <div class="space-y-4" id="reportDetails"></div>
        
        <div class="modal-action">
            <label for="viewReportModal" class="btn btn-ghost">Đóng</label>
        </div>
    </div>
    <label class="modal-backdrop" for="viewReportModal">Close</label>
</div>

<!-- Action Modals -->
<input type="checkbox" id="reviewModal" class="modal-toggle" />
<div class="modal">
    <div class="modal-box">
        <form method="POST" id="reviewForm">
            <input type="hidden" name="report_id" id="review_report_id">
            <input type="hidden" name="action" value="mark_reviewed">
            
            <h3 class="font-bold text-lg flex items-center gap-2 mb-4">
                <i class="fa-solid fa-check text-success"></i>
                Đánh dấu đã xem xét
            </h3>
            
            <div class="form-control mb-3">
                <label class="label">
                    <span class="label-text">Ghi chú Admin</span>
                </label>
                <textarea name="admin_notes" class="textarea textarea-bordered" rows="3" placeholder="Thêm ghi chú về hành động đã thực hiện..."></textarea>
            </div>
            
            <div class="modal-action">
                <label for="reviewModal" class="btn btn-ghost">Hủy</label>
                <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-check mr-2"></i>Xác nhận
                </button>
            </div>
        </form>
    </div>
    <label class="modal-backdrop" for="reviewModal">Close</label>
</div>

<input type="checkbox" id="dismissModal" class="modal-toggle" />
<div class="modal">
    <div class="modal-box">
        <form method="POST" id="dismissForm">
            <input type="hidden" name="report_id" id="dismiss_report_id">
            <input type="hidden" name="action" value="dismiss">
            
            <h3 class="font-bold text-lg flex items-center gap-2 mb-4">
                <i class="fa-solid fa-ban text-base-content/50"></i>
                Bỏ qua báo cáo
            </h3>
            
            <div class="form-control mb-3">
                <label class="label">
                    <span class="label-text">Ghi chú Admin</span>
                </label>
                <textarea name="admin_notes" class="textarea textarea-bordered" rows="3" placeholder="Lý do bỏ qua báo cáo này..."></textarea>
            </div>
            
            <div class="modal-action">
                <label for="dismissModal" class="btn btn-ghost">Hủy</label>
                <button type="submit" class="btn btn-error">
                    <i class="fa-solid fa-ban mr-2"></i>Bỏ qua
                </button>
            </div>
        </form>
    </div>
    <label class="modal-backdrop" for="dismissModal">Close</label>
</div>

<input type="checkbox" id="deleteDocModal" class="modal-toggle" />
<div class="modal">
    <div class="modal-box">
        <form method="POST" id="deleteDocForm">
            <input type="hidden" name="report_id" id="delete_report_id">
            <input type="hidden" name="action" value="delete_document">
            
            <h3 class="font-bold text-lg flex items-center gap-2 mb-4 text-error">
                <i class="fa-solid fa-trash"></i>
                Xóa tài liệu
            </h3>
            
            <div class="alert alert-error mb-4">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Hành động này không thể hoàn tác! Tài liệu sẽ bị xóa vĩnh viễn.</span>
            </div>
            
            <p class="mb-4">Bạn có chắc chắn muốn xóa tài liệu này?</p>
            
            <div class="modal-action">
                <label for="deleteDocModal" class="btn btn-ghost">Hủy</label>
                <button type="submit" class="btn btn-error">
                    <i class="fa-solid fa-trash mr-2"></i>Xóa tài liệu
                </button>
            </div>
        </form>
    </div>
    <label class="modal-backdrop" for="deleteDocModal">Close</label>
</div>

<script>
    const reasonLabels = {
        'inappropriate': 'Nội dung không phù hợp',
        'copyright': 'Vi phạm bản quyền',
        'spam': 'Spam / Quảng cáo',
        'misleading': 'Tiêu đề gây hiểu lầm',
        'low_quality': 'Chất lượng kém',
        'duplicate': 'Trùng lặp nội dung',
        'other': 'Lý do khác'
    };
    
    function viewReport(report) {
        const detailsDiv = document.getElementById('reportDetails');
        
        let html = `
            <div class="card bg-base-200">
                <div class="card-body">
                    <h4 class="font-bold mb-2">Tài liệu</h4>
                    <p>${report.doc_name || 'N/A'}</p>
                    ${report.doc_id ? `<a href="../view.php?id=${report.doc_id}" target="_blank" class="text-primary hover:underline text-sm">Xem tài liệu →</a>` : '<span class="text-error text-sm">Đã xóa</span>'}
                </div>
            </div>
            
            <div class="card bg-base-200">
                <div class="card-body">
                    <h4 class="font-bold mb-2">Người báo cáo</h4>
                    <p>${report.reporter_name || 'Unknown'}</p>
                    <p class="text-sm text-base-content/70">Ngày báo cáo: ${new Date(report.created_at).toLocaleString('vi-VN')}</p>
                </div>
            </div>
            
            <div class="card bg-base-200">
                <div class="card-body">
                    <h4 class="font-bold mb-2">Lý do</h4>
                    <span class="badge badge-warning">${reasonLabels[report.reason] || report.reason}</span>
                </div>
            </div>
            
            ${report.description ? `
            <div class="card bg-base-200">
                <div class="card-body">
                    <h4 class="font-bold mb-2">Mô tả chi tiết</h4>
                    <p>${report.description}</p>
                </div>
            </div>
            ` : ''}
            
            ${report.admin_notes ? `
            <div class="card bg-base-200">
                <div class="card-body">
                    <h4 class="font-bold mb-2">Ghi chú Admin</h4>
                    <p>${report.admin_notes}</p>
                    <p class="text-sm text-base-content/70 mt-2">
                        Người xem xét: ${report.reviewer_name || 'N/A'}<br>
                        Ngày: ${report.reviewed_at ? new Date(report.reviewed_at).toLocaleString('vi-VN') : 'N/A'}
                    </p>
                </div>
            </div>
            ` : ''}
        `;
        
        // Add action buttons if pending
        if (report.status === 'pending' && report.doc_id) {
            html += `
            <div class="flex gap-2 mt-4">
                <button class="btn btn-success btn-sm flex-1" onclick="openReviewModal(${report.id})">
                    <i class="fa-solid fa-check mr-2"></i>Đã xem xét
                </button>
                <button class="btn btn-ghost btn-sm flex-1" onclick="openDismissModal(${report.id})">
                    <i class="fa-solid fa-ban mr-2"></i>Bỏ qua
                </button>
                <button class="btn btn-error btn-sm flex-1" onclick="openDeleteDocModal(${report.id})">
                    <i class="fa-solid fa-trash mr-2"></i>Xóa tài liệu
                </button>
            </div>
            `;
        }
        
        detailsDiv.innerHTML = html;
        document.getElementById('viewReportModal').checked = true;
    }
    
    function openReviewModal(reportId) {
        document.getElementById('review_report_id').value = reportId;
        document.getElementById('viewReportModal').checked = false;
        document.getElementById('reviewModal').checked = true;
    }
    
    function openDismissModal(reportId) {
        document.getElementById('dismiss_report_id').value = reportId;
        document.getElementById('viewReportModal').checked = false;
        document.getElementById('dismissModal').checked = true;
    }
    
    function openDeleteDocModal(reportId) {
        document.getElementById('delete_report_id').value = reportId;
        document.getElementById('viewReportModal').checked = false;
        document.getElementById('deleteDocModal').checked = true;
    }
</script>

<?php 
include __DIR__ . '/../includes/admin-footer.php';
mysqli_close($conn); 
?>

