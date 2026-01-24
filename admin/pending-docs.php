<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';
require_once __DIR__ . '/../push/send_push.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Duyệt tài liệu";

// Handle Actions (Approve/Reject)
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $document_id = intval($_POST['document_id']);
    $action = $_POST['action'];
    
    if($action === 'approve') {
        $points = intval($_POST['points']);
        $notes = $VSD->escape($_POST['notes'] ?? '');
        
        if($points > 0) {
            approveDocument($document_id, $admin_id, $points, $notes);
            header("Location: pending-docs.php?status=approved"); exit;
        }
    } elseif($action === 'reject') {
        $reason = $VSD->escape($_POST['rejection_reason'] ?? '');
        $doc_info = $VSD->get_row("SELECT user_id, original_name FROM documents WHERE id=$document_id");
        rejectDocument($document_id, $admin_id, $reason);
        if($doc_info) {
            $VSD->insert('notifications', ['user_id' => $doc_info['user_id'], 'title' => 'Tài liệu bị từ chối', 'message' => "Tài liệu '{$doc_info['original_name']}' bị từ chối. Lý do: $reason", 'type' => 'document_rejected', 'ref_id' => $document_id]);
            sendPushToUser($doc_info['user_id'], ['title' => 'Tài liệu bị từ chối ❌', 'body' => "Nhấn để xem lý do.", 'url' => '/history.php?tab=notifications']);
        }
        header("Location: pending-docs.php?status=rejected"); exit;
    }
}

$pending_docs = getPendingDocuments();
$pending_count = count($pending_docs);

$admin_active_page = 'pending';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="min-h-screen bg-base-200/50 p-4 lg:p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-warning"></i>
                    Tài liệu chờ duyệt
                </h1>
                <p class="text-base-content/60 text-sm mt-1">Cần xem xét và xử lý <span class="font-bold text-base-content"><?= $pending_count ?></span> tài liệu</p>
            </div>
            
            <a href="all-documents.php" class="btn btn-ghost btn-sm">
                Xem tất cả tài liệu <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <!-- Alert Messages -->
        <?php if(isset($_GET['status'])): ?>
            <?php if($_GET['status'] === 'approved'): ?>
                <div role="alert" class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Tài liệu đã được duyệt thành công!</span>
                </div>
            <?php elseif($_GET['status'] === 'rejected'): ?>
                <div role="alert" class="alert alert-error">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span>Đã từ chối tài liệu!</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Grid -->
        <?php if($pending_count > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-6">
                <?php foreach($pending_docs as $doc): 
                    $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                    $bg_soft = match($ext) {
                        'pdf' => 'bg-error/10 text-error',
                        'doc', 'docx' => 'bg-info/10 text-info',
                        'xls', 'xlsx' => 'bg-success/10 text-success',
                        'ppt', 'pptx' => 'bg-warning/10 text-warning',
                        'zip', 'rar' => 'bg-secondary/10 text-secondary',
                        default => 'bg-base-content/10 text-base-content/70'
                    };
                    $icon_class = match($ext) {
                        'pdf' => 'fa-file-pdf',
                        'doc', 'docx' => 'fa-file-word',
                        'xls', 'xlsx' => 'fa-file-excel',
                        'ppt', 'pptx' => 'fa-file-powerpoint',
                        'zip', 'rar' => 'fa-file-zipper',
                        'jpg', 'jpeg', 'png' => 'fa-file-image',
                        default => 'fa-file'
                    };
                ?>
                <div class="card bg-base-100 shadow-sm border border-base-200 hover:shadow-md transition-all group">
                    <div class="card-body p-5">
                        <!-- Top Metadata -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl <?= $bg_soft ?> flex items-center justify-center text-2xl group-hover:scale-110 transition-transform">
                                <i class="fa-solid <?= $icon_class ?>"></i>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <span class="badge badge-warning badge-sm gap-1">
                                    <i class="fa-solid fa-hourglass-half text-[10px]"></i> Chờ duyệt
                                </span>
                                <?php if($doc['ai_status'] === 'completed'): ?>
                                    <?php 
                                        $ai_dec = strtoupper($doc['ai_decision'] ?? '');
                                        $ai_badge = match(true) {
                                            str_contains($ai_dec, 'CHẤP') || str_contains($ai_dec, 'APPROV') => 'badge-success',
                                            str_contains($ai_dec, 'XEM') || str_contains($ai_dec, 'CONDIT') => 'badge-warning',
                                            str_contains($ai_dec, 'TỪ') || str_contains($ai_dec, 'REJECT') => 'badge-error',
                                            default => 'badge-ghost'
                                        };
                                    ?>
                                    <div class="flex flex-col items-end">
                                        <div class="badge <?= $ai_badge ?> badge-xs font-bold py-2 whitespace-nowrap">AI: <?= $doc['ai_decision'] ?></div>
                                        <div class="text-[10px] font-bold mt-1 text-primary"><?= $doc['ai_score'] ?>/100 pts</div>
                                    </div>
                                <?php elseif($doc['ai_status'] === 'processing'): ?>
                                    <span class="badge badge-info badge-xs p-2 animate-pulse">AI Đang chấm...</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Title & Desc -->
                        <h3 class="font-bold text-lg leading-tight line-clamp-2 min-h-[3rem]" title="<?= htmlspecialchars($doc['original_name']) ?>">
                            <?= htmlspecialchars($doc['original_name']) ?>
                        </h3>
                        <p class="text-sm text-base-content/60 line-clamp-2 mt-2 min-h-[2.5rem]">
                            <?= !empty($doc['description']) ? htmlspecialchars($doc['description']) : 'Không có mô tả...' ?>
                        </p>

                        <!-- AI Suggestion Box -->
                        <?php if($doc['ai_status'] === 'completed' && $doc['ai_price'] > 0): ?>
                            <div class="mt-3 p-3 bg-primary/5 rounded-xl border border-primary/10 border-dashed flex items-center justify-between">
                                <div class="text-[10px] uppercase font-bold text-primary/60">AI Gợi ý giá</div>
                                <div class="text-sm font-black text-primary flex items-center gap-1">
                                    <i class="fa-solid fa-coins text-warning"></i>
                                    <?= number_format($doc['ai_price']) ?> VSD
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Uploader -->
                        <div class="flex items-center gap-2 mt-4 pt-4 border-t border-base-200">
                            <div class="avatar placeholder">
                                <div class="w-8 h-8 rounded-full bg-base-200 grid place-items-center">
                                    <i class="fa-solid fa-user text-xs text-base-content/50"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium truncate"><?= htmlspecialchars($doc['username']) ?></div>
                                <div class="text-xs text-base-content/50">Tác giả</div>
                            </div>
                            <a href="view-document.php?id=<?= $doc['id'] ?>" target="_blank" class="btn btn-ghost btn-xs">
                                <i class="fa-solid fa-eye"></i> Xem
                            </a>
                        </div>

                        <!-- Actions -->
                        <div class="grid grid-cols-2 gap-3 mt-4">
                            <button onclick="openRejectModal(<?= $doc['id'] ?>)" class="btn btn-outline btn-error btn-sm">
                                Từ chối
                            </button>
                            <button onclick="openApproveModal(<?= $doc['id'] ?>, '<?= addslashes(htmlspecialchars($doc['original_name'])) ?>', <?= (int)($doc['ai_price'] ?? 5) ?>)" class="btn btn-success btn-sm text-white">
                                Duyệt
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card bg-base-100 shadow py-12 text-center">
                <div class="max-w-md mx-auto">
                    <div class="w-20 h-20 bg-success/10 text-success rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fa-solid fa-check text-3xl"></i>
                    </div>
                    <h2 class="text-xl font-bold">Tuyệt vời! Đã xử lý hết</h2>
                    <p class="text-base-content/60 mt-2">Hiện tại không còn tài liệu nào đang chờ duyệt. Hãy quay lại sau.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Duyệt -->
<dialog id="approveModal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-success"></i> Duyệt tài liệu
        </h3>
        <form method="POST">
            <input type="hidden" name="document_id" id="approve_doc_id">
            <input type="hidden" name="action" value="approve">
            
            <div class="alert bg-base-200 mb-4 text-sm">
                <i class="fa-solid fa-file text-base-content/50"></i>
                <div class="font-medium truncate" id="doc_title_display">Filename.pdf</div>
            </div>

            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Định giá (điểm)</span></label>
                <div class="join w-full">
                    <span class="join-item btn btn-active"><i class="fa-solid fa-coins text-warning"></i></span>
                    <input type="number" name="points" class="input input-bordered join-item w-full" value="5" min="1" required>
                </div>
                <label class="label"><span class="label-text-alt opacity-60">Đây là số điểm mặc định người dùng phải trả để tải</span></label>
            </div>

            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Ghi chú (tùy chọn)</span></label>
                <textarea name="notes" class="textarea textarea-bordered" placeholder="Gửi lời nhắn đến tác giả..."></textarea>
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="this.closest('dialog').close()">Hủy</button>
                <button type="submit" class="btn btn-success text-white px-8">Xác nhận Duyệt</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<!-- Modal Từ Chối -->
<dialog id="rejectModal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg mb-4 flex items-center gap-2">
            <i class="fa-solid fa-circle-xmark text-error"></i> Từ chối tài liệu
        </h3>
        <form method="POST">
            <input type="hidden" name="document_id" id="reject_doc_id">
            <input type="hidden" name="action" value="reject">

            <div class="form-control mb-4">
                <label class="label"><span class="label-text">Lý do từ chối <span class="text-error">*</span></span></label>
                <textarea name="rejection_reason" class="textarea textarea-bordered h-24" placeholder="Vui lòng nêu rõ lý do (VD: Nội dung sơ sài, sai định dạng...)" required></textarea>
            </div>

            <div class="modal-action">
                <button type="button" class="btn" onclick="this.closest('dialog').close()">Hủy</button>
                <button type="submit" class="btn btn-error text-white px-8">Xác nhận Từ chối</button>
            </div>
        </form>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
</dialog>

<script>
function openApproveModal(id, title, suggestedPrice = 5) {
    document.getElementById('approve_doc_id').value = id;
    document.getElementById('doc_title_display').innerText = title;
    
    // Set suggested price from AI if available
    const pointsInput = document.querySelector('#approveModal input[name="points"]');
    if (pointsInput) {
        pointsInput.value = suggestedPrice;
    }
    
    document.getElementById('approveModal').showModal();
}
function openRejectModal(id) {
    document.getElementById('reject_doc_id').value = id;
    document.getElementById('rejectModal').showModal();
}
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
