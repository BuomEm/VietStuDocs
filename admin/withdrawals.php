<?php
require_once __DIR__ . '/../includes/error_handler.php';
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';

if (!hasAdminAccess()) {
    header('Location: /login.php');
    exit;
}

// 1. Handle POST Logic BEFORE any HTML output to avoid "Headers already sent"
$status_filter = $_GET['status'] ?? 'pending';
$action = $_POST['action'] ?? '';
$request_id = $_POST['request_id'] ?? '';
$admin_note = $_POST['admin_note'] ?? '';

if ($action && $request_id) {
    if ($action === 'approve') {
        $result = approveWithdrawal($request_id, $_SESSION['user_id'], $admin_note);
    } elseif ($action === 'reject') {
        $result = rejectWithdrawal($request_id, $_SESSION['user_id'], $admin_note);
    }
    
    if (isset($result)) {
        $_SESSION['flash_message'] = $result['message'];
        $_SESSION['flash_type'] = $result['success'] ? 'success' : 'error';
        header("Location: withdrawals.php?status=$status_filter");
        exit;
    }
}

// 2. Fetch data
$withdrawals = getAllWithdrawalRequests($status_filter);
$admin_active_page = 'withdrawals';
$page_title = "Quản lý Rút tiền";

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="p-4 lg:p-10 max-w-7xl mx-auto animate-fade-in">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
        <div>
            <h1 class="text-4xl font-black tracking-tight text-white mb-2">
                Quản lý <span class="bg-clip-text bg-gradient-to-r from-primary to-emerald-400">Rút tiền</span>
            </h1>
            <p class="text-slate-400 font-medium">Phê duyệt hoặc từ chối yêu cầu rút VSD của Gia sư hệ thống.</p>
        </div>
        
        <div class="flex bg-base-300/50 p-1.5 rounded-2xl border border-white/5 backdrop-blur-xl shadow-2xl">
            <?php
            $tabs = [
                ['id' => 'pending', 'label' => 'Đang chờ', 'color' => 'primary'],
                ['id' => 'approved', 'label' => 'Đã duyệt', 'color' => 'success'],
                ['id' => 'rejected', 'label' => 'Từ chối', 'color' => 'error'],
                ['id' => 'all', 'label' => 'Tất cả', 'color' => 'neutral']
            ];
            foreach ($tabs as $tab):
                $isActive = ($status_filter === $tab['id']);
            ?>
            <a href="?status=<?= $tab['id'] ?>" 
               class="px-5 py-2.5 rounded-xl text-xs font-black uppercase tracking-widest transition-all duration-300 <?= $isActive ? "bg-{$tab['color']} text-{$tab['color']}-content shadow-lg scale-105" : 'text-slate-500 hover:text-slate-300' ?>">
                <?= $tab['label'] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Stats & Filters Info -->
    <div class="mb-8 flex items-center gap-4 text-xs font-bold text-slate-500 uppercase tracking-widest">
        <i class="fa-solid fa-list-ul text-primary"></i>
        <span>Danh sách: <?= count($withdrawals) ?> yêu cầu <?= $status_filter ?></span>
    </div>

    <?php if (empty($withdrawals)): ?>
        <div class="card bg-base-100 border border-white/5 shadow-2xl p-20 text-center rounded-[2rem]">
            <div class="w-24 h-24 bg-base-200 rounded-3xl flex items-center justify-center mx-auto mb-8 animate-bounce-slow">
                <i class="fa-solid fa-money-bill-transfer text-4xl text-slate-600"></i>
            </div>
            <h3 class="text-2xl font-black text-white mb-2">Trống trơn!</h3>
            <p class="text-slate-500 max-w-sm mx-auto">Hiện tại không có yêu cầu rút tiền nào cần xử lý trong mục này.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
            <?php foreach ($withdrawals as $w): 
                $status_config = [
                    'pending' => ['color' => 'warning', 'icon' => 'clock', 'label' => 'ĐANG CHỜ'],
                    'approved' => ['color' => 'success', 'icon' => 'check-double', 'label' => 'HOÀN TẤT'],
                    'rejected' => ['color' => 'error', 'icon' => 'xmark', 'label' => 'TỪ CHỐI']
                ];
                $sc = $status_config[$w['status']] ?? $status_config['pending'];
            ?>
                <div class="card bg-base-100 border border-white/5 shadow-xl hover:shadow-primary/5 hover:border-primary/20 transition-all duration-500 group rounded-[2.5rem] overflow-hidden">
                    <!-- Status Header -->
                    <div class="absolute top-6 right-6 z-10">
                        <div class="badge badge-lg bg-<?= $sc['color'] ?> shadow-lg shadow-<?= $sc['color'] ?>/20 border-none font-black text-[10px] tracking-tighter">
                            <i class="fa-solid fa-<?= $sc['icon'] ?> mr-1.5"></i> <?= $sc['label'] ?>
                        </div>
                    </div>

                    <div class="card-body p-8">
                        <!-- User Info -->
                        <div class="flex items-center gap-4 mb-8">
                            <div class="avatar">
                                <div class="w-16 h-16 rounded-[1.5rem] ring ring-primary/20 ring-offset-base-100 ring-offset-2">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($w['username']) ?>&background=random" />
                                </div>
                            </div>
                            <div class="overflow-hidden">
                                <h3 class="font-black text-white text-lg truncate"><?= htmlspecialchars($w['username']) ?></h3>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest truncate">ID: #<?= $w['user_id'] ?> | <?= htmlspecialchars($w['email']) ?></p>
                            </div>
                        </div>

                        <!-- Amount Info -->
                        <div class="bg-base-200/50 rounded-[2rem] p-6 mb-8 border border-white/5 group-hover:bg-primary/5 transition-colors">
                            <div class="flex justify-between items-end mb-1">
                                <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Số tiền rút</span>
                                <span class="text-[10px] font-black text-primary uppercase tracking-widest"><?= number_format($w['points']) ?> VSD</span>
                            </div>
                            <div class="text-3xl font-black text-white">
                                <?= number_format($w['amount_vnd']) ?> <span class="text-sm font-bold opacity-30">VNĐ</span>
                            </div>
                        </div>

                        <!-- Bank Details -->
                        <div class="space-y-4 mb-8">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-2xl bg-base-300 flex items-center justify-center shrink-0 border border-white/5">
                                    <i class="fa-solid fa-university text-slate-500 text-sm"></i>
                                </div>
                                <div class="flex-1 overflow-hidden">
                                    <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Thông tin thanh toán</div>
                                    <div class="text-xs font-bold text-white whitespace-pre-wrap leading-relaxed"><?= htmlspecialchars($w['bank_info']) ?></div>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-2xl bg-base-300 flex items-center justify-center shrink-0 border border-white/5">
                                    <i class="fa-solid fa-calendar-day text-slate-500 text-sm"></i>
                                </div>
                                <div>
                                    <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-0.5">Thời gian gửi</div>
                                    <div class="text-xs font-bold text-slate-300"><?= date('H:i • d/m/Y', strtotime($w['created_at'])) ?></div>
                                </div>
                            </div>
                        </div>

                        <?php if ($w['status'] === 'pending'): ?>
                            <form method="POST" class="space-y-4 pt-4 border-t border-white/5" onsubmit="return handleWithdrawalSubmit(this)">
                                <input type="hidden" name="request_id" value="<?= $w['id'] ?>">
                                <input type="hidden" name="action" id="action_input_<?= $w['id'] ?>" value="">
                                
                                <div class="relative">
                                    <textarea name="admin_note" rows="2" 
                                              class="w-full bg-base-300 border border-white/5 rounded-[1.5rem] p-4 text-xs font-medium focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all outline-none" 
                                              placeholder="Ghi chú admin (Mã giao dịch, lý do từ chối...)"></textarea>
                                </div>
                                
                                <div class="flex gap-3">
                                    <button type="submit" onclick="setAction('approve', <?= $w['id'] ?>)" class="flex-1 h-12 bg-success text-success-content hover:scale-105 active:scale-95 rounded-2xl font-black text-[10px] uppercase tracking-tighter transition-all shadow-lg shadow-success/10">
                                        <i class="fa-solid fa-check mr-2"></i> Duyệt & Trả
                                    </button>
                                    <button type="submit" onclick="setAction('reject', <?= $w['id'] ?>)" class="flex-1 h-12 bg-error text-error-content hover:scale-105 active:scale-95 rounded-2xl font-black text-[10px] uppercase tracking-tighter transition-all shadow-lg shadow-error/10">
                                        <i class="fa-solid fa-xmark mr-2"></i> Từ chối
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <!-- Processed Info -->
                            <div class="pt-6 border-t border-white/5 mt-auto">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-slate-800 flex items-center justify-center">
                                            <i class="fa-solid fa-user-shield text-[10px] text-slate-500"></i>
                                        </div>
                                        <span class="text-[10px] font-black text-slate-500 uppercase">Admin: #<?= $w['admin_id'] ?></span>
                                    </div>
                                    <span class="text-[10px] font-bold text-slate-600 italic">Verified Access</span>
                                </div>
                                
                                <?php if ($w['admin_note']): ?>
                                    <div class="p-4 bg-base-200/50 rounded-2xl border border-white/5 italic text-slate-400 text-xs leading-relaxed">
                                        "<?= htmlspecialchars($w['admin_note']) ?>"
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function setAction(action, id) {
    document.getElementById('action_input_' + id).value = action;
}

function handleWithdrawalSubmit(form) {
    const action = form.querySelector('input[name="action"]').value;
    const actionText = action === 'approve' ? 'DUYỆT và CHUYỂN TIỀN' : 'TỪ CHỐI';
    return confirm(`Bạn có chắc chắn muốn ${actionText} yêu cầu rút tiền này?`);
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
