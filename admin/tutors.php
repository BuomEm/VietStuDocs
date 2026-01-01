<?php
require_once __DIR__ . '/../includes/error_handler.php';
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';

redirectIfNotAdmin();

$admin_id = getCurrentUserId();
$page_title = "Quản lý Gia sư - Admin Panel";
$admin_active_page = 'tutors';

$pdo = getTutorDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'process_update') {
        $update_id = $_POST['update_id'] ?? 0;
        $status = $_POST['status'] ?? '';
        $note = $_POST['note'] ?? '';
        $res = processProfileUpdate($update_id, $status, $note);
        $_SESSION['flash_message'] = $res['message'];
        $_SESSION['flash_type'] = $res['success'] ? 'success' : 'error';
    }

    if ($action === 'process_registration') {
        $tutor_id = $_POST['tutor_id'] ?? 0;
        $status = $_POST['status'] ?? '';
        
        try {
            $stmt = $pdo->prepare("UPDATE tutors SET status = ? WHERE id = ?");
            $stmt->execute([$status, $tutor_id]);
            
            // Get user_id to notify
            $stmt = $pdo->prepare("SELECT user_id FROM tutors WHERE id = ?");
            $stmt->execute([$tutor_id]);
            $uid = $stmt->fetchColumn();
            
            global $VSD;
            $title = ($status === 'active') ? 'Đăng ký Gia sư thành công' : 'Đăng ký Gia sư bị từ chối';
            $msg = ($status === 'active') ? 'Chúc mừng! Bạn đã chính thức trở thành Gia sư trên hệ thống.' : 'Hồ sơ đăng ký gia sư của bạn không được chấp nhận.';
            $VSD->insert('notifications', [
                'user_id' => $uid,
                'title' => $title,
                'message' => $msg,
                'type' => 'role_updated'
            ]);
            
            $_SESSION['flash_message'] = "Đã cập nhật trạng thái đăng ký.";
            $_SESSION['flash_type'] = "success";
        } catch(Exception $e) {
            $_SESSION['flash_message'] = "Lỗi: " . $e->getMessage();
            $_SESSION['flash_type'] = "error";
        }
    }
}

// Get pending registrations
$stmt = $pdo->prepare("SELECT t.*, u.username, u.email FROM tutors t JOIN users u ON t.user_id = u.id WHERE t.status = 'pending'");
$stmt->execute();
$pending_registrations = $stmt->fetchAll();

// Get pending profile updates
$pending_updates = getPendingProfileUpdates();

include __DIR__ . '/../includes/admin-header.php';
?>

<div class="p-6 bg-base-100 border-b border-base-300">
    <div class="container mx-auto max-w-7xl">
        <h2 class="text-2xl font-bold flex items-center gap-2">
            <i class="fa-solid fa-chalkboard-user"></i>
            Quản lý Gia sư
        </h2>
        <p class="text-base-content/70 mt-1">Phê duyệt đăng ký mới và thay đổi thông tin hồ sơ.</p>
    </div>
</div>

<div class="p-6">
    <div class="container mx-auto max-w-7xl">
        <?php if(isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= $_SESSION['flash_type'] ?> mb-6">
                <span><?= $_SESSION['flash_message'] ?></span>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <!-- NEW REGISTRATIONS Section -->
        <h3 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="fa-solid fa-user-plus text-primary"></i> Đăng ký mới (<?= count($pending_registrations) ?>)</h3>
        <?php if(empty($pending_registrations)): ?>
            <div class="bg-base-100 border border-base-300 rounded-xl p-6 text-center opacity-50 mb-8">Chưa có đơn đăng ký mới.</div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-10">
                <?php foreach($pending_registrations as $reg): ?>
                    <div class="card bg-base-100 shadow border border-base-300">
                        <div class="card-body p-5">
                            <div class="flex justify-between">
                                <div class="font-bold"><?= htmlspecialchars($reg['username']) ?> (<?= htmlspecialchars($reg['email']) ?>)</div>
                                <div class="text-[10px] opacity-50"><?= date('d/m/Y', strtotime($reg['created_at'])) ?></div>
                            </div>
                            <div class="text-xs mt-2 italic px-3 border-l-2 border-primary"><?= htmlspecialchars($reg['bio']) ?></div>
                            <div class="mt-3 text-xs font-bold">Môn học: <span class="font-normal"><?= htmlspecialchars($reg['subjects']) ?></span></div>
                            <div class="card-actions justify-end mt-4">
                                <form method="POST" class="flex gap-2">
                                    <input type="hidden" name="action" value="process_registration">
                                    <input type="hidden" name="tutor_id" value="<?= $reg['id'] ?>">
                                    <button name="status" value="active" class="btn btn-success btn-xs">Duyệt</button>
                                    <button name="status" value="rejected" class="btn btn-error btn-xs btn-outline">Từ chối</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- PROFILE UPDATES Section -->
        <h3 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="fa-solid fa-pen-to-square text-info"></i> Thay đổi thông tin (<?= count($pending_updates) ?>)</h3>
        <?php if(empty($pending_updates)): ?>
            <div class="bg-base-100 border border-base-300 rounded-xl p-6 text-center opacity-50">Không có yêu cầu thay đổi nào.</div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach($pending_updates as $pu): ?>
                    <div class="card bg-base-100 shadow border border-base-300 overflow-hidden">
                        <div class="flex flex-col lg:flex-row">
                            <div class="flex-1 p-5 border-r border-base-300">
                                <div class="font-bold mb-3"><?= htmlspecialchars($pu['username']) ?> đổi thông tin</div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[11px]">
                                    <div class="opacity-60 bg-base-200 p-3 rounded-lg">
                                        <div class="font-bold mb-1 uppercase text-[9px]">Cũ</div>
                                        <div>Môn: <?= htmlspecialchars($pu['old_subjects']) ?></div>
                                        <div class="line-clamp-2"><?= htmlspecialchars($pu['old_bio']) ?></div>
                                    </div>
                                    <div class="bg-primary/5 p-3 rounded-lg border border-primary/20">
                                        <div class="font-bold mb-1 uppercase text-[9px] text-primary">Mới</div>
                                        <div>Môn: <?= htmlspecialchars($pu['subjects']) ?></div>
                                        <div class="italic"><?= htmlspecialchars($pu['bio']) ?></div>
                                        <div class="mt-2 font-bold">Giá: <?= number_format($pu['price_basic']) ?> / <?= number_format($pu['price_standard']) ?> / <?= number_format($pu['price_premium']) ?> pts</div>
                                    </div>
                                </div>
                            </div>
                            <div class="p-5 lg:w-72 bg-base-200/20 flex flex-col justify-center">
                                <form method="POST" class="space-y-3">
                                    <input type="hidden" name="action" value="process_update">
                                    <input type="hidden" name="update_id" value="<?= $pu['id'] ?>">
                                    <textarea name="note" class="textarea textarea-bordered textarea-xs w-full h-16" placeholder="Ghi chú..."></textarea>
                                    <div class="flex gap-2">
                                        <button name="status" value="approved" class="btn btn-success btn-xs flex-1">Duyệt</button>
                                        <button name="status" value="rejected" class="btn btn-error btn-outline btn-xs flex-1">Từ chối</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
