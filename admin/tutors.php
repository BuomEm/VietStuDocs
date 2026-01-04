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

    if ($action === 'update_prices') {
        $tutor_id = $_POST['tutor_id'] ?? 0;
        $price_basic = intval($_POST['price_basic'] ?? 0);
        $price_standard = intval($_POST['price_standard'] ?? 0);
        $price_premium = intval($_POST['price_premium'] ?? 0);
        
        try {
            $stmt = $pdo->prepare("UPDATE tutors SET price_basic = ?, price_standard = ?, price_premium = ? WHERE id = ?");
            $stmt->execute([$price_basic, $price_standard, $price_premium, $tutor_id]);
            
            // Get user_id for notification
            $stmt = $pdo->prepare("SELECT user_id FROM tutors WHERE id = ?");
            $stmt->execute([$tutor_id]);
            $uid = $stmt->fetchColumn();
            
            global $VSD;
            $msg = "Admin đã điều chỉnh mức giá dịch vụ của bạn thành: $price_basic / $price_standard / $price_premium pts.";
            $VSD->insert('notifications', [
                'user_id' => $uid,
                'title' => 'Điều chỉnh mức giá gia sư',
                'message' => $msg,
                'type' => 'price_updated'
            ]);
            
            require_once __DIR__ . '/../push/send_push.php';
            sendPushToUser($uid, [
                'title' => 'Cập nhật mức giá 💰',
                'body' => "Admin đã điều chỉnh lại mức giá gia sư của bạn.",
                'url' => '/history.php?tab=notifications'
            ]);

            $_SESSION['flash_message'] = "Đã cập nhật mức giá và gửi thông báo cho gia sư.";
            $_SESSION['flash_type'] = "success";
        } catch(Exception $e) {
            $_SESSION['flash_message'] = "Lỗi: " . $e->getMessage();
            $_SESSION['flash_type'] = "error";
        }
    }
    if ($action === 'toggle_verification') {
        $tutor_id = $_POST['tutor_id'] ?? 0;
        $verify_status = intval($_POST['verify_status'] ?? 0);
        
        try {
            // Get user_id first
            $stmt = $pdo->prepare("SELECT user_id FROM tutors WHERE id = ?");
            $stmt->execute([$tutor_id]);
            $uid = $stmt->fetchColumn();
            
            if ($uid) {
                // We need to use main DB connection logic or direct query since users is in main DB
                // Since this file uses getTutorDBConnection which connects to same DB_NAME, we can query users directly
                $stmt = $pdo->prepare("UPDATE users SET is_verified_tutor = ? WHERE id = ?");
                $stmt->execute([$verify_status, $uid]);
                
                $_SESSION['flash_message'] = $verify_status ? "Đã cấp tick xanh cho gia sư." : "Đã hủy tick xanh của gia sư.";
                $_SESSION['flash_type'] = "success";
            }
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

// Get all tutors for the full list
$stmt = $pdo->prepare("SELECT t.*, u.username, u.email, u.is_verified_tutor, u.last_activity 
                      FROM tutors t 
                      JOIN users u ON t.user_id = u.id 
                      ORDER BY CASE t.status 
                        WHEN 'pending' THEN 1 
                        WHEN 'active' THEN 2 
                        WHEN 'rejected' THEN 3 
                        WHEN 'banned' THEN 4 
                      END ASC, t.created_at DESC");
$stmt->execute();
$all_tutors = $stmt->fetchAll();

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

        <!-- ALL TUTORS Section -->
        <div class="divider my-10 uppercase tracking-widest opacity-30 text-[10px] font-bold">Danh sách tất cả Gia sư</div>
        
        <div class="card bg-base-100 shadow-xl border border-base-300">
            <div class="overflow-x-auto min-h-[400px] pb-24">
                <table class="table table-zebra w-full text-xs">
                    <thead>
                        <tr>
                            <th>Gia sư</th>
                            <th>Trạng thái</th>
                            <th>Môn học</th>
                            <th>Giá (B/S/P)</th>
                            <th>Đánh giá</th>
                            <th>Hồ sơ</th>
                            <th class="text-right">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_tutors as $tutor): 
                            $online_status = getOnlineStatusString($tutor['last_activity'] ?? null);
                            $status_badge = ($online_status['status'] === 'online') ? 'badge-success' : 'badge-ghost opacity-50';
                        ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="avatar placeholder">
                                            <div class="bg-neutral-focus text-neutral-content rounded-full w-8">
                                                <span><?= substr($tutor['username'], 0, 1) ?></span>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="font-bold whitespace-nowrap flex items-center gap-1">
                                                <?= htmlspecialchars($tutor['username']) ?>
                                                <?php if($tutor['is_verified_tutor']): ?>
                                                    <i class="fa-solid fa-circle-check text-blue-500" title="Đã xác minh"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-[10px] opacity-50"><?= htmlspecialchars($tutor['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="badge <?= $status_badge ?> badge-xs gap-1 py-2 px-2 whitespace-nowrap" title="<?= $online_status['text'] ?>">
                                        <?= $online_status['label'] ?>
                                    </div>
                                </td>
                                <td class="max-w-[150px] truncate"><?= htmlspecialchars($tutor['subjects']) ?></td>
                                <td class="font-mono">
                                    <form method="POST" class="flex flex-col gap-1 py-1">
                                        <input type="hidden" name="action" value="update_prices">
                                        <input type="hidden" name="tutor_id" value="<?= $tutor['id'] ?>">
                                        <div class="flex items-center gap-1">
                                            <input type="number" name="price_basic" value="<?= $tutor['price_basic'] ?>" class="input input-bordered input-xs w-14 h-5 px-1 text-[10px]" title="Basic">
                                            <input type="number" name="price_standard" value="<?= $tutor['price_standard'] ?>" class="input input-bordered input-xs w-14 h-5 px-1 text-[10px]" title="Standard">
                                            <input type="number" name="price_premium" value="<?= $tutor['price_premium'] ?>" class="input input-bordered input-xs w-14 h-5 px-1 text-[10px]" title="Premium">
                                            <button type="submit" class="btn btn-ghost btn-xs btn-square h-5 min-h-0 w-5 opacity-40 hover:opacity-100 hover:text-primary transition-all">
                                                <i class="fa-solid fa-floppy-disk text-[9px]"></i>
                                            </button>
                                        </div>
                                    </form>
                                </td>
                                <td>
                                    <?php if($tutor['rating'] > 0): ?>
                                        <div class="badge badge-warning badge-sm gap-1 font-bold h-auto py-0.5">
                                            <i class="fa-solid fa-star text-[8px]"></i> <?= (float)$tutor['rating'] ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="opacity-30">--</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($tutor['status'] === 'active'): ?>
                                        <span class="badge badge-success badge-xs py-2 px-2">Hoạt động</span>
                                    <?php elseif($tutor['status'] === 'pending'): ?>
                                        <span class="badge badge-warning badge-xs py-2 px-2">Chờ duyệt</span>
                                    <?php elseif($tutor['status'] === 'rejected'): ?>
                                        <span class="badge badge-error badge-xs badge-outline py-2 px-2">Từ chối</span>
                                    <?php elseif($tutor['status'] === 'banned'): ?>
                                        <span class="badge badge-ghost badge-xs py-2 px-2">Bị khóa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <div class="dropdown dropdown-bottom dropdown-end dropdown-hover">
                                        <div tabindex="0" role="button" class="btn btn-ghost btn-xs btn-square"><i class="fa-solid fa-ellipsis-vertical"></i></div>
                                        <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-48 border border-base-200">
                                            <!-- Verified Toggle -->
                                            <li>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="toggle_verification">
                                                    <input type="hidden" name="tutor_id" value="<?= $tutor['id'] ?>">
                                                    <?php if($tutor['is_verified_tutor']): ?>
                                                        <button name="verify_status" value="0" class="text-error py-2 text-xs"><i class="fa-solid fa-ban"></i> Hủy Verified</button>
                                                    <?php else: ?>
                                                        <button name="verify_status" value="1" class="text-primary py-2 text-xs"><i class="fa-solid fa-circle-check"></i> Cấp Verified</button>
                                                    <?php endif; ?>
                                                </form>
                                            </li>
                                            
                                            <?php if($tutor['status'] !== 'active'): ?>
                                                <li>
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="process_registration">
                                                        <input type="hidden" name="tutor_id" value="<?= $tutor['id'] ?>">
                                                        <button name="status" value="active" class="text-success py-2 text-xs"><i class="fa-solid fa-check"></i> Kích hoạt Hồ Sơ</button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            <?php if($tutor['status'] !== 'banned'): ?>
                                                <li>
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="process_registration">
                                                        <input type="hidden" name="tutor_id" value="<?= $tutor['id'] ?>">
                                                        <button name="status" value="banned" class="text-warning py-2 text-xs"><i class="fa-solid fa-lock"></i> Khóa Hồ Sơ</button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            <?php if($tutor['status'] === 'active'): ?>
                                                <li>
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="process_registration">
                                                        <input type="hidden" name="tutor_id" value="<?= $tutor['id'] ?>">
                                                        <button name="status" value="rejected" class="opacity-50 py-2 text-xs"><i class="fa-solid fa-xmark"></i> Hủy duyệt</button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
