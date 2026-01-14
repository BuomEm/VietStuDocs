<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/ai_review_handler.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "AI Review Queue";

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $document_id = intval($_POST['document_id']);
    $action = $_POST['action'];

    if ($action === 'run_ai') {
        $handler = new AIReviewHandler($VSD);
        $result = $handler->runReviewProcess($document_id);
        echo json_encode($result);
        exit;
    }
}

// Fetch Stats
$stats = [
    'pending' => (int)$VSD->num_rows("SELECT id FROM documents WHERE (ai_status IS NULL OR ai_status = 'pending')"),
    'completed' => (int)$VSD->num_rows("SELECT id FROM documents WHERE ai_status = 'completed'"),
    'failed' => (int)$VSD->num_rows("SELECT id FROM documents WHERE ai_status = 'failed'"),
    'total' => (int)$VSD->num_rows("SELECT id FROM documents")
];

// Fetch Documents
$filter = $_GET['filter'] ?? 'pending';
$sql = "SELECT d.*, u.username FROM documents d JOIN users u ON d.user_id = u.id ";
if ($filter === 'pending') {
    $sql .= "WHERE (d.ai_status IS NULL OR d.ai_status = 'pending') ";
} elseif ($filter === 'completed') {
    $sql .= "WHERE d.ai_status = 'completed' ";
} elseif ($filter === 'failed') {
    $sql .= "WHERE d.ai_status = 'failed' ";
}
// Default 'all' filter shows everything
$sql .= "ORDER BY d.created_at DESC LIMIT 50";
$docs = $VSD->get_results($sql);

$admin_active_page = 'ai-queue';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="min-h-screen bg-base-200/50 p-4 lg:p-6">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-primary"></i>
                    AI Review Queue
                </h1>
                <p class="text-base-content/60 text-sm mt-1">Quản lý và theo dõi quá trình AI chấm điểm tài liệu</p>
            </div>
            
            <div class="join bg-base-100 shadow-sm border border-base-300">
                <a href="?filter=all" class="join-item btn btn-sm <?= ($filter === 'all' || !isset($_GET['filter'])) ? 'btn-primary' : 'btn-ghost' ?>">
                    Tất cả (<?= (int)$stats['total'] ?>)
                </a>
                <a href="?filter=pending" class="join-item btn btn-sm <?= $filter === 'pending' ? 'btn-primary' : 'btn-ghost' ?>">
                    Chờ xử lý (<?= (int)$stats['pending'] ?>)
                </a>
                <a href="?filter=completed" class="join-item btn btn-sm <?= $filter === 'completed' ? 'btn-primary' : 'btn-ghost' ?>">
                    Hoàn thành (<?= (int)$stats['completed'] ?>)
                </a>
                <a href="?filter=failed" class="join-item btn btn-sm <?= $filter === 'failed' ? 'btn-primary' : 'btn-ghost' ?>">
                    Lỗi (<?= (int)$stats['failed'] ?>)
                </a>
            </div>
        </div>

        <!-- Documents Table -->
        <div class="card bg-base-100 shadow-sm border border-base-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr class="bg-base-200/50">
                            <th class="w-16">ID</th>
                            <th>Tài liệu</th>
                            <th>Tác giả</th>
                            <th>Ngày tải</th>
                            <th>Trạng thái AI</th>
                            <th>Điểm/Giá</th>
                            <th class="text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($docs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-12 opacity-50">
                                    <i class="fa-solid fa-folder-open text-4xl mb-2 block"></i>
                                    Chưa có dữ liệu nào trong danh sách này
                                </td>
                            </tr>
                        <?php else: foreach ($docs as $doc): ?>
                            <tr id="row-<?= $doc['id'] ?>">
                                <td class="font-mono text-xs opacity-50"><?= $doc['id'] ?></td>
                                <td>
                                    <div class="font-bold text-sm line-clamp-1"><?= htmlspecialchars($doc['original_name']) ?></div>
                                    <div class="text-[10px] opacity-50"><?= htmlspecialchars($doc['file_name']) ?></div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="avatar placeholder">
                                            <div class="bg-neutral text-neutral-content rounded-full w-6 h-6">
                                                <span class="text-[10px]"><?= strtoupper(substr($doc['username'], 0, 1)) ?></span>
                                            </div>
                                        </div>
                                        <span class="text-sm"><?= htmlspecialchars($doc['username']) ?></span>
                                    </div>
                                </td>
                                <td class="text-xs opacity-70">
                                    <?= date('d/m/Y', strtotime($doc['created_at'])) ?><br>
                                    <span class="text-[10px]"><?= date('H:i', strtotime($doc['created_at'])) ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $status_badge = match($doc['ai_status']) {
                                        'completed' => 'badge-success',
                                        'processing' => 'badge-info animate-pulse',
                                        'failed' => 'badge-error',
                                        default => 'badge-ghost opacity-60'
                                    };
                                    $status_label = match($doc['ai_status']) {
                                        'completed' => 'Đã duyệt',
                                        'processing' => 'Đang xử lý',
                                        'failed' => 'Lỗi',
                                        default => 'Chờ duyệt'
                                    };
                                    ?>
                                    <span class="badge <?= $status_badge ?> badge-sm font-medium whitespace-nowrap">
                                        <?= $status_label ?>
                                    </span>
                                    <?php if($doc['ai_status'] === 'failed' && !empty($doc['error_message'])): ?>
                                        <div class="text-[9px] text-error mt-1 truncate max-w-[150px]" title="<?= htmlspecialchars($doc['error_message']) ?>">
                                            <?= htmlspecialchars($doc['error_message']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($doc['ai_status'] === 'completed'): ?>
                                            <div class="flex flex-col gap-1.5">
                                                <div class="flex items-center gap-2">
                                                    <span class="text-xs font-mono font-bold"><?= $doc['ai_score'] ?>/100</span>
                                                    <?php
                                                    $decision_label = $doc['ai_decision'];
                                                    $decision_class = '';
                                                    if ($decision_label === 'APPROVED' || $decision_label === 'Chấp Nhận') {
                                                        $decision_label = 'Chấp Nhận';
                                                        $decision_class = 'bg-success/20 text-success border-success/30';
                                                    } elseif ($decision_label === 'CONDITIONAL' || $decision_label === 'Xem Xét') {
                                                        $decision_label = 'Xem Xét';
                                                        $decision_class = 'bg-warning/20 text-warning border-warning/30';
                                                    } elseif ($decision_label === 'REJECTED' || $decision_label === 'Từ Chối') {
                                                        $decision_label = 'Từ Chối';
                                                        $decision_class = 'bg-error/20 text-error border-error/30';
                                                    }
                                                    ?>
                                                    <div class="px-2 py-0.5 rounded-md text-[10px] font-bold border uppercase tracking-wider <?= $decision_class ?>">
                                                        <?= $decision_label ?>
                                                    </div>
                                                </div>
                                                <div class="text-xs font-bold text-primary flex items-center gap-1.5 opacity-90">
                                                    <div class="w-4 h-4 rounded-full bg-warning/20 flex items-center justify-center">
                                                        <i class="fa-solid fa-coins text-[9px] text-warning"></i>
                                                    </div> 
                                                    <?= number_format($doc['ai_price']) ?> VSD
                                                </div>
                                            </div>
                                    <?php else: ?>
                                        <span class="opacity-30">---</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <?php if($doc['ai_status'] !== 'processing'): ?>
                                            <button onclick="runArtificialIntelligence(<?= $doc['id'] ?>)" class="btn btn-ghost btn-xs btn-square hover:bg-primary/20 hover:text-primary" title="Chạy AI Review">
                                                <i class="fa-solid fa-play"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="view-document.php?id=<?= $doc['id'] ?>" class="btn btn-ghost btn-xs btn-square" title="Xem chi tiết">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="p-4 bg-base-200/50 flex items-center justify-between">
                <div class="text-xs opacity-50">Hiển thị 50 tài liệu mới nhất</div>
                <div class="flex gap-2">
                    <button class="btn btn-xs" disabled>Trước</button>
                    <button class="btn btn-xs" disabled>Sau</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function runArtificialIntelligence(id) {
    const btn = event.currentTarget;
    const oldHtml = btn.innerHTML;
    const row = document.getElementById('row-' + id);
    
    btn.disabled = true;
    btn.innerHTML = '<span class="loading loading-spinner loading-xs text-primary"></span>';
    row.classList.add('bg-primary/5');

    try {
        const formData = new FormData();
        formData.append('document_id', id);
        formData.append('action', 'run_ai');

        const response = await fetch('ai-queue.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Thành công',
                text: 'AI đã duyệt xong tài liệu. Kết quả: ' + result.decision + ' (' + result.score + 'đ)',
                confirmButtonColor: 'oklch(var(--p))'
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Lỗi AI',
                text: result.error || 'Có lỗi xảy ra khi gọi OpenAI API.',
                confirmButtonColor: 'oklch(var(--er))'
            });
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Lỗi',
            text: 'Không thể kết nối đến server.',
            confirmButtonColor: 'oklch(var(--er))'
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = oldHtml;
        row.classList.remove('bg-primary/5');
    }
}
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
