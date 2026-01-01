<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Chờ duyệt - Admin Panel";

// Handle approval/rejection
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $document_id = intval($_POST['document_id']);
    $action = $_POST['action'];
    
    if($action === 'approve') {
        $points = intval($_POST['points']);
        $notes = $VSD->escape($_POST['notes'] ?? '');
        
        if($points > 0) {
            approveDocument($document_id, $admin_id, $points, $notes);
            header("Location: pending-docs.php?status=approved");
            exit;
        }
    } elseif($action === 'reject') {
        $reason = $VSD->escape($_POST['rejection_reason'] ?? '');
        rejectDocument($document_id, $admin_id, $reason);
        header("Location: pending-docs.php?status=rejected");
        exit;
    }
}

// Handle viewing document details
$view_doc_id = isset($_GET['view']) ? intval($_GET['view']) : null;
$view_doc = null;

if($view_doc_id) {
    $view_doc = getDocumentForApproval($view_doc_id);
}

// Get all pending documents
$pending_docs = getPendingDocuments();

// Get unread notifications count
$unread_notifications = $VSD->num_rows("SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0");

// For shared admin sidebar
$admin_active_page = 'pending';

// Include header
include __DIR__ . '/../includes/admin-header.php';

$pending_count = count($pending_docs);
?>

<!-- Page Header -->
<div class="p-6 bg-base-100 border-b border-base-300">
    <div class="container mx-auto max-w-7xl">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div>
                <h2 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-regular fa-clock"></i>
                    Tài liệu chờ duyệt
                </h2>
                <p class="text-base-content/70 mt-1">Có <?= $pending_count ?> tài liệu đang chờ xem xét</p>
            </div>
        </div>
    </div>
</div>

<!-- Page Body -->
<div class="p-6">
    <div class="container mx-auto max-w-7xl">
        <!-- Status Messages -->
        <?php if(isset($_GET['status']) && $_GET['status'] === 'approved'): ?>
        <div class="alert alert-success mb-4">
            <i class="fa-solid fa-check-circle"></i>
            <span>Tài liệu đã được duyệt thành công!</span>
        </div>
        <?php elseif(isset($_GET['status']) && $_GET['status'] === 'rejected'): ?>
        <div class="alert alert-warning mb-4">
            <i class="fa-solid fa-xmark-circle"></i>
            <span>Tài liệu đã bị từ chối!</span>
        </div>
        <?php endif; ?>

        <!-- Pending Documents -->
        <?php if($pending_count > 0): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach($pending_docs as $doc): 
                $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                
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
                $bg_color = $color_map[$ext] ?? 'bg-secondary';
            ?>
                <div class="card bg-base-100 shadow">
                    <!-- Document Header -->
                    <div class="card-header bg-base-200">
                        <div class="flex items-center gap-3">
                            <div class="avatar placeholder">
                                <div class="<?= $bg_color ?> text-white rounded w-12">
                                    <?php if($ext === 'pdf'): ?>
                                        <i class="fa-solid fa-file-pdf text-xl"></i>
                                    <?php elseif(in_array($ext, ['doc', 'docx'])): ?>
                                        <i class="fa-solid fa-file-word text-xl"></i>
                                    <?php elseif(in_array($ext, ['xls', 'xlsx'])): ?>
                                        <i class="fa-solid fa-file-excel text-xl"></i>
                                    <?php elseif(in_array($ext, ['ppt', 'pptx'])): ?>
                                        <i class="fa-solid fa-file-powerpoint text-xl"></i>
                                    <?php elseif(in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <i class="fa-solid fa-file-image text-xl"></i>
                                    <?php elseif(in_array($ext, ['zip', 'rar'])): ?>
                                        <i class="fa-solid fa-file-zipper text-xl"></i>
                                    <?php else: ?>
                                        <i class="fa-regular fa-file text-xl"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-medium truncate" title="<?= htmlspecialchars($doc['original_name']) ?>">
                                    <?= htmlspecialchars(substr($doc['original_name'], 0, 35)) ?>...
                                </div>
                                <div class="text-base-content/70 text-sm truncate">
                                    <i class="fa-solid fa-user mr-1"></i>
                                    <?= htmlspecialchars($doc['username']) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Document Body -->
                    <div class="card-body">
                        <div class="flex gap-2 mb-2">
                            <span class="badge badge-warning badge-sm">
                                <i class="fa-regular fa-clock mr-1"></i>
                                Chờ duyệt
                            </span>
                            <span class="badge badge-info badge-sm">.<?= strtoupper($ext) ?></span>
                        </div>
                        
                        <div class="text-base-content/70 text-sm mb-2">
                            <i class="fa-regular fa-calendar mr-1"></i>
                            <?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?>
                        </div>
                        
                        <?php if($doc['description']): ?>
                            <div class="text-base-content/70 text-sm truncate" title="<?= htmlspecialchars($doc['description']) ?>">
                                <i class="fa-regular fa-file-lines mr-1"></i>
                                <?= htmlspecialchars(substr($doc['description'], 0, 60)) ?>...
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Document Actions -->
                    <div class="card-footer bg-base-200">
                        <div class="flex gap-2">
                            <button type="button" class="btn btn-success btn-sm flex-1" 
                                    onclick="openApproveModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['original_name'])) ?>')">
                                <i class="fa-solid fa-check mr-1"></i>
                                Duyệt
                            </button>
                            <button type="button" class="btn btn-error btn-sm flex-1" 
                                    onclick="openRejectModal(<?= $doc['id'] ?>)">
                                <i class="fa-solid fa-xmark mr-1"></i>
                                Từ chối
                            </button>
                            <a href="view-document.php?id=<?= $doc['id'] ?>" class="btn btn-ghost btn-sm" target="_blank">
                                <i class="fa-regular fa-eye"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex flex-col items-center justify-center py-12">
                        <i class="fa-regular fa-face-smile text-6xl text-success mb-4"></i>
                        <h3 class="text-xl font-bold mb-2">Không có tài liệu nào đang chờ duyệt</h3>
                        <p class="text-base-content/70 text-center mb-6">
                            Tất cả tài liệu đã được xem xét. Quay lại sau để kiểm tra thêm.
                        </p>
                        <a href="all-documents.php" class="btn btn-primary">
                            <i class="fa-regular fa-files mr-2"></i>
                            Xem tất cả tài liệu
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Approve Modal -->
<dialog id="approveModal" class="modal">
    <div class="modal-box">
        <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        </form>
        <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
            <i class="fa-solid fa-check text-success"></i>
            Duyệt tài liệu
        </h3>
        <form method="POST">
            <input type="hidden" name="document_id" id="approve_doc_id">
            <input type="hidden" name="action" value="approve">

            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text font-semibold">Tài liệu</span>
                </label>
                <input type="text" id="doc_title" class="input input-bordered" readonly>
            </div>
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text font-semibold">Giá trị điểm <span class="text-error">*</span></span>
                </label>
                <div class="join">
                    <div class="join-item bg-base-200 px-4 flex items-center">
                        <i class="fa-solid fa-coins"></i>
                    </div>
                    <input type="number" id="points" name="points" class="input input-bordered join-item flex-1" 
                           min="1" max="1000" value="50" required>
                    <div class="join-item bg-base-200 px-4 flex items-center">điểm</div>
                </div>
                <label class="label">
                    <span class="label-text-alt">Đây là giá tối đa người dùng có thể đặt để bán tài liệu này</span>
                </label>
            </div>

            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text font-semibold">Ghi chú Admin</span>
                </label>
                <textarea id="notes" name="notes" class="textarea textarea-bordered" rows="3" 
                          placeholder="Thêm ghi chú về tài liệu này..."></textarea>
            </div>

            <div class="modal-action">
                <form method="dialog">
                    <button class="btn btn-ghost">Hủy</button>
                </form>
                <button type="submit" class="btn btn-success">
                    <i class="fa-solid fa-check mr-2"></i>
                    Duyệt tài liệu
                </button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<!-- Reject Modal -->
<dialog id="rejectModal" class="modal">
    <div class="modal-box">
        <form method="dialog">
            <button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        </form>
        <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
            <i class="fa-solid fa-xmark text-error"></i>
            Từ chối tài liệu
        </h3>
        <form method="POST">
            <input type="hidden" name="document_id" id="reject_doc_id">
            <input type="hidden" name="action" value="reject">
            
            <div class="form-control mb-4">
                <label class="label">
                    <span class="label-text font-semibold">Lý do từ chối <span class="text-error">*</span></span>
                </label>
                <textarea id="reason" name="rejection_reason" class="textarea textarea-bordered" rows="4" 
                          placeholder="Giải thích tại sao bạn từ chối tài liệu này..." required></textarea>
            </div>
            
            <div class="alert alert-warning mb-4">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Người dùng sẽ nhận được thông báo về lý do từ chối này.</span>
            </div>

            <div class="modal-action">
                <form method="dialog">
                    <button class="btn btn-ghost">Hủy</button>
                </form>
                <button type="submit" class="btn btn-error">
                    <i class="fa-solid fa-xmark mr-2"></i>
                    Từ chối
                </button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button>close</button>
    </form>
</dialog>

<script>
function openApproveModal(docId, docTitle) {
    document.getElementById('approve_doc_id').value = docId;
    document.getElementById('doc_title').value = docTitle;
    document.getElementById('approveModal').showModal();
}

function openRejectModal(docId) {
    document.getElementById('reject_doc_id').value = docId;
    document.getElementById('rejectModal').showModal();
}
</script>

<?php 
include __DIR__ . '/../includes/admin-footer.php';
?>
