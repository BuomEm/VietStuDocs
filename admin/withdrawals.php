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

require_once __DIR__ . '/../includes/admin-header.php';

$status_filter = $_GET['status'] ?? 'pending';
$withdrawals = getAllWithdrawalRequests($status_filter);
$admin_active_page = 'withdrawals';
$page_title = "Quản lý Rút tiền";

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
?>

<div class="p-6 max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight">Quản lý Rút tiền</h1>
            <p class="text-slate-500 text-sm mt-1">Phê duyệt hoặc từ chối yêu cầu rút VSD của Gia sư</p>
        </div>
        
        <div class="flex bg-slate-100 p-1 rounded-xl shadow-inner">
            <a href="?status=pending" class="px-4 py-2 rounded-lg text-sm font-bold transition-all <?= $status_filter === 'pending' ? 'bg-white shadow text-primary' : 'text-slate-500 hover:text-slate-700' ?>">Đang chờ</a>
            <a href="?status=approved" class="px-4 py-2 rounded-lg text-sm font-bold transition-all <?= $status_filter === 'approved' ? 'bg-white shadow text-success' : 'text-slate-500 hover:text-slate-700' ?>">Đã duyệt</a>
            <a href="?status=rejected" class="px-4 py-2 rounded-lg text-sm font-bold transition-all <?= $status_filter === 'rejected' ? 'bg-white shadow text-error' : 'text-slate-500 hover:text-slate-700' ?>">Từ chối</a>
            <a href="?status=all" class="px-4 py-2 rounded-lg text-sm font-bold transition-all <?= $status_filter === 'all' ? 'bg-white shadow text-slate-700' : 'text-slate-500 hover:text-slate-700' ?>">Tất cả</a>
        </div>
    </div>

    <?php if (empty($withdrawals)): ?>
        <div class="bg-white rounded-3xl p-20 text-center shadow-sm border border-slate-100">
            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fa-solid fa-money-bill-transfer text-3xl text-slate-300"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-700">Không có yêu cầu nào</h3>
            <p class="text-slate-400 mt-2">Hiện tại không có yêu cầu rút tiền nào trong danh sách này.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <?php foreach ($withdrawals as $w): ?>
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 hover:shadow-md transition-all">
                    <div class="flex justify-between items-start mb-6">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary font-black text-lg">
                                <?= strtoupper(substr($w['username'], 0, 1)) ?>
                            </div>
                            <div>
                                <h3 class="font-black text-slate-800"><?= htmlspecialchars($w['username']) ?></h3>
                                <p class="text-[10px] text-slate-400 font-bold tracking-widest uppercase"><?= htmlspecialchars($w['email']) ?></p>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <div class="text-2xl font-black text-slate-900"><?= number_format($w['amount_vnd']) ?> <span class="text-xs text-slate-400">VNĐ</span></div>
                            <div class="text-[10px] font-black <?= $w['status'] === 'pending' ? 'text-orange-500' : ($w['status'] === 'approved' ? 'text-emerald-500' : 'text-rose-500') ?> uppercase tracking-widest mt-1">
                                <?= $w['status'] === 'pending' ? 'Đang xử lý' : ($w['status'] === 'approved' ? 'Đã hoàn thành' : 'Đã từ chối') ?>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 rounded-2xl p-4 mb-6">
                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Thông tin ngân hàng</div>
                        <div class="text-sm font-bold text-slate-700 whitespace-pre-wrap"><?= htmlspecialchars($w['bank_info']) ?></div>
                    </div>

                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-400 mb-6">
                        <span>Yêu cầu: <?= date('H:i d/m/Y', strtotime($w['created_at'])) ?></span>
                        <span><?= $w['points'] ?> VSD</span>
                    </div>

                    <?php if ($w['status'] === 'pending'): ?>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="request_id" value="<?= $w['id'] ?>">
                            <textarea name="admin_note" rows="2" class="w-100 border-none bg-slate-50 rounded-2xl p-4 text-sm font-medium focus:ring-2 focus:ring-primary/20" placeholder="Ghi chú cho gia sư (Không bắt buộc)..."></textarea>
                            
                            <div class="flex gap-3">
                                <button type="submit" name="action" value="approve" class="flex-1 h-12 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-black text-xs uppercase tracking-widest transition-all" onclick="return confirm('Bạn có chắc chắn muốn DUYỆT yêu cầu này?')">
                                    <i class="fa-solid fa-check mr-2"></i> Duyệt và Chuyển
                                </button>
                                <button type="submit" name="action" value="reject" class="flex-1 h-12 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl font-black text-xs uppercase tracking-widest transition-all" onclick="return confirm('Bạn có chắc chắn muốn TỪ CHỐI yêu cầu này?')">
                                    <i class="fa-solid fa-xmark mr-2"></i> Từ chối
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="border-t border-slate-50 pt-4 flex justify-between items-center text-xs">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center">
                                    <i class="fa-solid fa-user-shield text-[10px] text-slate-400"></i>
                                </span>
                                <span class="font-bold text-slate-500">Người duyệt: #<?= $w['admin_id'] ?></span>
                            </div>
                            <?php if ($w['admin_note']): ?>
                                <div class="italic text-slate-400">"<?= htmlspecialchars($w['admin_note']) ?>"</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
