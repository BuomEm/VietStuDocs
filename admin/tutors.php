<?php
require_once __DIR__ . '/../includes/error_handler.php';
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';
require_once __DIR__ . '/../push/send_push.php';

redirectIfNotAdmin();
$admin_id = getCurrentUserId();
$page_title = "Quản lý Gia sư";
$pdo = getTutorDBConnection();

// Handle Actions (Logic giữ nguyên, chỉ chỉnh sửa message style nếu cần)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Process Registration
    if ($action === 'process_registration') {
        $tid = $_POST['tutor_id'] ?? 0;
        $status = $_POST['status'] ?? ''; // active, rejected, banned
        $pdo->prepare("UPDATE tutors SET status = ? WHERE id = ?")->execute([$status, $tid]);
        
        $uid = $pdo->query("SELECT user_id FROM tutors WHERE id=$tid")->fetchColumn();
        if($uid) {
            $msg = ($status === 'active') ? 'Hồ sơ Gia sư của bạn đã được duyệt!' : 'Hồ sơ Gia sư của bạn đã bị từ chối/khóa.';
            $VSD->insert('notifications', ['user_id'=>$uid, 'title'=>'Trạng thái hồ sơ Gia sư', 'message'=>$msg, 'type'=>'role_updated']);
            if($status === 'active') sendPushToUser($uid, ['title'=>'Chúc mừng! 🎉', 'body'=>'Bạn đã trở thành Gia sư.', 'url'=>'/tutor/dashboard.php']);
        }
        $flash = ($status === 'active') ? ['success', 'Đã duyệt hồ sơ gia sư.'] : ['info', 'Đã cập nhật trạng thái hồ sơ.'];
    }
    
    // Process Update
    elseif ($action === 'process_update') {
        $uid = $_POST['update_id'];
        $st = $_POST['status'];
        $note = $_POST['note'] ?? '';
        $res = processProfileUpdate($uid, $st, $note);
        $flash = [$res['success']?'success':'error', $res['message']];
    }
    
    // Update Prices
    elseif ($action === 'update_prices') {
        $tid = $_POST['tutor_id'];
        $pdo->prepare("UPDATE tutors SET price_basic=?, price_standard=?, price_premium=? WHERE id=?")
            ->execute([$_POST['price_basic'], $_POST['price_standard'], $_POST['price_premium'], $tid]);
        $flash = ['success', 'Đã cập nhật bảng giá.'];
    }
    
    // Toggle Verify
    elseif ($action === 'toggle_verification') {
        $tid = $_POST['tutor_id'];
        $ver = intval($_POST['verify_status']);
        $uid = $pdo->query("SELECT user_id FROM tutors WHERE id=$tid")->fetchColumn();
        if($uid) {
            // Note: Users table is in main DB, but this PDO connects to same DB name usually or we need $VSD?
            // Assuming same DB. If separated, use $VSD global.
            global $VSD; // Use main connection ensure
            $VSD->update('users', ['is_verified_tutor' => $ver], "id=$uid");
            $flash = ['success', $ver ? 'Đã cấp tích xanh.' : 'Đã hủy tích xanh.'];
        }
    }
    
    if(isset($flash)) {
        $_SESSION['flash_msg'] = $flash[1];
        $_SESSION['flash_type'] = $flash[0];
        header("Location: tutors.php"); exit;
    }
}

// Fetch Data
$pending_regs = $pdo->query("SELECT t.*, u.username, u.email, u.avatar FROM tutors t JOIN users u ON t.user_id = u.id WHERE t.status = 'pending'")->fetchAll();
$pending_updates = getPendingProfileUpdates();
$all_tutors = $pdo->query("SELECT t.*, u.username, u.email, u.avatar, u.is_verified_tutor, u.last_activity FROM tutors t JOIN users u ON t.user_id = u.id ORDER BY FIELD(t.status, 'pending','active','rejected','banned'), t.created_at DESC")->fetchAll();

$admin_active_page = 'tutors';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-base-200/30 via-base-100/20 to-base-200/40">
    <!-- Background Pattern -->
    <div class="fixed inset-0 opacity-5">
        <div class="absolute inset-0" style="background-image: radial-gradient(circle at 1px 1px, rgba(0,0,0,.15) 1px, transparent 0); background-size: 20px 20px;"></div>
    </div>

    <div class="relative z-10 p-4 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-8">

            <!-- Hero Header -->
            <div class="hero bg-gradient-to-r from-primary/10 via-secondary/10 to-accent/10 rounded-[3rem] shadow-2xl border border-base-200/50 overflow-hidden">
                <div class="hero-content text-center py-16">
                    <div class="max-w-2xl">
                        <div class="flex justify-center mb-6">
                            <div class="p-6 bg-gradient-to-br from-primary to-primary-focus rounded-full shadow-2xl animate-bounce-slow">
                                <i class="fa-solid fa-chalkboard-user text-4xl text-white"></i>
                            </div>
                        </div>
                        <h1 class="text-4xl lg:text-6xl font-black text-base-content mb-4">
                            <span class="bg-gradient-to-r from-primary via-secondary to-accent bg-clip-text text-transparent">
                                Quản lý Gia sư
                            </span>
                        </h1>
                        <p class="text-lg text-base-content/70 mb-8 leading-relaxed">
                            Quản lý chuyên nghiệp hồ sơ gia sư, duyệt đăng ký và theo dõi hoạt động
                        </p>

                        <!-- Stats Cards -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                            <div class="stat bg-base-100/80 backdrop-blur-sm rounded-2xl shadow-lg border border-base-200/50">
                                <div class="stat-figure text-primary">
                                    <i class="fa-solid fa-user-group text-2xl"></i>
                                </div>
                                <div class="stat-title text-xs opacity-70">Tổng gia sư</div>
                                <div class="stat-value text-2xl font-black"><?php echo count($all_tutors); ?></div>
                            </div>
                            <div class="stat bg-base-100/80 backdrop-blur-sm rounded-2xl shadow-lg border border-base-200/50">
                                <div class="stat-figure text-success">
                                    <i class="fa-solid fa-user-check text-2xl"></i>
                                </div>
                                <div class="stat-title text-xs opacity-70">Đang hoạt động</div>
                                <div class="stat-value text-2xl font-black text-success">
                                    <?php echo count(array_filter($all_tutors, fn($t) => $t['status'] === 'active')); ?>
                                </div>
                            </div>
                            <div class="stat bg-base-100/80 backdrop-blur-sm rounded-2xl shadow-lg border border-base-200/50">
                                <div class="stat-figure text-warning">
                                    <i class="fa-solid fa-clock text-2xl"></i>
                                </div>
                                <div class="stat-title text-xs opacity-70">Chờ duyệt</div>
                                <div class="stat-value text-2xl font-black text-warning">
                                    <?php echo count($pending_regs); ?>
                                </div>
                            </div>
                            <div class="stat bg-base-100/80 backdrop-blur-sm rounded-2xl shadow-lg border border-base-200/50">
                                <div class="stat-figure text-info">
                                    <i class="fa-solid fa-star text-2xl"></i>
                                </div>
                                <div class="stat-title text-xs opacity-70">Đã xác thực</div>
                                <div class="stat-value text-2xl font-black text-info">
                                    <?php echo count(array_filter($all_tutors, fn($t) => $t['is_verified_tutor'])); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Action Badges -->
                        <?php if(count($pending_regs) > 0 || count($pending_updates) > 0): ?>
                            <div class="flex flex-wrap justify-center gap-4">
                                <?php if(count($pending_regs)): ?>
                                    <div class="badge badge-error gap-2 p-4 animate-pulse shadow-lg">
                                        <i class="fa-solid fa-user-plus"></i>
                                        <span class="font-bold"><?= count($pending_regs) ?> Đăng ký mới cần duyệt</span>
                                    </div>
                                <?php endif; ?>
                                <?php if(count($pending_updates)): ?>
                                    <div class="badge badge-warning gap-2 p-4 animate-pulse shadow-lg">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                        <span class="font-bold"><?= count($pending_updates) ?> Yêu cầu cập nhật hồ sơ</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Notification -->
            <?php if(isset($_SESSION['flash_msg'])): ?>
                <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> shadow-xl border border-base-200/50 backdrop-blur-sm animate-fade-in">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-current/10 rounded-full">
                            <i class="fa-solid fa-circle-info text-lg"></i>
                        </div>
                        <span class="font-medium"><?= $_SESSION['flash_msg'] ?></span>
                    </div>
                </div>
                <?php unset($_SESSION['flash_msg'], $_SESSION['flash_type']); ?>
            <?php endif; ?>

            <!-- Pending Registrations Section -->
            <?php if(count($pending_regs) > 0): ?>
                <div class="space-y-6">
                    <div class="flex items-center gap-4">
                        <div class="p-4 bg-gradient-to-br from-primary to-primary-focus rounded-2xl shadow-xl">
                            <i class="fa-solid fa-user-plus text-2xl text-white"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-black text-base-content">Đăng ký mới cần duyệt</h2>
                            <p class="text-base-content/70 mt-1">Các ứng viên gia sư đang chờ phê duyệt</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach($pending_regs as $r): ?>
                            <div class="card bg-base-100/90 backdrop-blur-sm shadow-xl border border-primary/20 rounded-[2rem] overflow-hidden hover:shadow-2xl hover:scale-[1.02] transition-all duration-500 group">
                                <!-- Priority Indicator -->
                                <div class="absolute top-4 right-4">
                                    <div class="w-3 h-3 bg-primary rounded-full animate-ping shadow-lg shadow-primary/50"></div>
                                    <div class="w-3 h-3 bg-primary rounded-full absolute top-0 left-0"></div>
                                </div>

                                <div class="card-body p-6">
                                    <!-- Header -->
                                    <div class="flex items-center gap-4 mb-4">
                                        <div class="avatar placeholder">
                                            <div class="w-16 h-16 rounded-full bg-gradient-to-br from-primary to-primary-focus text-white font-bold shadow-lg">
                                                <span class="text-xl"><?= strtoupper(substr($r['username'],0,1)) ?></span>
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-bold text-lg text-base-content">
                                                <?= htmlspecialchars($r['username']) ?>
                                            </h3>
                                            <p class="text-sm text-base-content/60 flex items-center gap-1">
                                                <i class="fa-solid fa-envelope text-xs"></i>
                                                <?= htmlspecialchars($r['email']) ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Subjects -->
                                    <div class="mb-4">
                                        <div class="badge badge-primary badge-sm mb-2">Môn học</div>
                                        <p class="text-sm text-base-content/80 leading-relaxed">
                                            <?= htmlspecialchars($r['subjects']) ?>
                                        </p>
                                    </div>

                                    <!-- Bio -->
                                    <div class="mb-6">
                                        <div class="badge badge-ghost badge-sm mb-2">Giới thiệu</div>
                                        <p class="text-sm text-base-content/70 italic line-clamp-3">
                                            "<?= htmlspecialchars($r['bio']) ?>"
                                        </p>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex gap-3">
                                        <form method="POST" class="flex-1">
                                            <input type="hidden" name="action" value="process_registration">
                                            <input type="hidden" name="tutor_id" value="<?= $r['id'] ?>">
                                            <button name="status" value="active"
                                                    class="btn btn-primary btn-sm w-full rounded-full shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300">
                                                <i class="fa-solid fa-check mr-2"></i>
                                                Duyệt
                                            </button>
                                        </form>
                                        <form method="POST" class="flex-1">
                                            <input type="hidden" name="action" value="process_registration">
                                            <input type="hidden" name="tutor_id" value="<?= $r['id'] ?>">
                                            <button name="status" value="rejected"
                                                    class="btn btn-ghost btn-sm w-full rounded-full border-error text-error hover:bg-error hover:text-white transition-all duration-300">
                                                <i class="fa-solid fa-xmark mr-2"></i>
                                                Từ chối
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pending Updates Section -->
            <?php if(count($pending_updates) > 0): ?>
                <div class="space-y-6">
                    <div class="flex items-center gap-4">
                        <div class="p-4 bg-gradient-to-br from-warning to-orange-500 rounded-2xl shadow-xl">
                            <i class="fa-solid fa-pen-nib text-2xl text-white"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-black text-base-content">Yêu cầu cập nhật hồ sơ</h2>
                            <p class="text-base-content/70 mt-1">Các gia sư muốn cập nhật thông tin cá nhân</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <?php foreach($pending_updates as $u): ?>
                            <div class="card bg-base-100/90 backdrop-blur-sm shadow-xl border border-warning/30 rounded-[2rem] overflow-hidden hover:shadow-2xl transition-all duration-500 group">
                                <!-- Priority Indicator -->
                                <div class="absolute top-4 right-4">
                                    <div class="w-3 h-3 bg-warning rounded-full animate-ping shadow-lg shadow-warning/50"></div>
                                    <div class="w-3 h-3 bg-warning rounded-full absolute top-0 left-0"></div>
                                </div>

                                <div class="card-body p-0">
                                    <div class="flex">
                                        <!-- Old Version -->
                                        <div class="flex-1 p-6 bg-gradient-to-br from-base-200/50 to-base-300/30 border-r border-base-200/50">
                                            <div class="flex items-center gap-2 mb-4">
                                                <div class="p-2 bg-base-300/50 rounded-lg">
                                                    <i class="fa-solid fa-clock-rotate-left text-base-content/60"></i>
                                                </div>
                                                <h4 class="font-bold text-base-content uppercase tracking-wide text-sm">Hiện tại</h4>
                                            </div>

                                            <div class="space-y-3">
                                                <div>
                                                    <div class="text-xs font-semibold text-base-content/60 uppercase tracking-wide mb-1">Môn học</div>
                                                    <p class="text-sm text-base-content/80">
                                                        <?= htmlspecialchars($u['old_subjects']) ?>
                                                    </p>
                                                </div>
                                                <div>
                                                    <div class="text-xs font-semibold text-base-content/60 uppercase tracking-wide mb-1">Giới thiệu</div>
                                                    <p class="text-sm text-base-content/70 italic line-clamp-3">
                                                        "<?= htmlspecialchars($u['old_bio']) ?>"
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- New Version -->
                                        <div class="flex-1 p-6 bg-base-100">
                                            <div class="flex items-center gap-2 mb-4">
                                                <div class="p-2 bg-warning/10 rounded-lg">
                                                    <i class="fa-solid fa-pen-nib text-warning"></i>
                                                </div>
                                                <h4 class="font-bold text-warning uppercase tracking-wide text-sm">Cập nhật mới</h4>
                                            </div>

                                            <div class="space-y-3">
                                                <div>
                                                    <div class="text-xs font-semibold text-base-content/60 uppercase tracking-wide mb-1">Môn học</div>
                                                    <p class="text-sm text-base-content font-medium">
                                                        <?= htmlspecialchars($u['subjects']) ?>
                                                    </p>
                                                </div>
                                                <div>
                                                    <div class="text-xs font-semibold text-base-content/60 uppercase tracking-wide mb-1">Giới thiệu</div>
                                                    <p class="text-sm text-base-content italic border-l-2 border-warning pl-3">
                                                        "<?= htmlspecialchars($u['bio']) ?>"
                                                    </p>
                                                </div>

                                                <!-- Pricing -->
                                                <div class="bg-warning/5 p-3 rounded-xl border border-warning/20">
                                                    <div class="text-xs font-semibold text-base-content/60 uppercase tracking-wide mb-2">Bảng giá mới</div>
                                                    <div class="grid grid-cols-3 gap-2 text-center">
                                                        <div>
                                                            <div class="text-xs text-base-content/60">Cơ bản</div>
                                                            <div class="font-bold text-warning text-sm"><?= $u['price_basic'] ?> pts</div>
                                                        </div>
                                                        <div>
                                                            <div class="text-xs text-base-content/60">Tiêu chuẩn</div>
                                                            <div class="font-bold text-warning text-sm"><?= $u['price_standard'] ?> pts</div>
                                                        </div>
                                                        <div>
                                                            <div class="text-xs text-base-content/60">Cao cấp</div>
                                                            <div class="font-bold text-warning text-sm"><?= $u['price_premium'] ?> pts</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Action Buttons -->
                                                <div class="flex gap-2 mt-4">
                                                    <form method="POST" class="flex-1">
                                                        <input type="hidden" name="action" value="process_update">
                                                        <input type="hidden" name="update_id" value="<?= $u['id'] ?>">
                                                        <button name="status" value="approved"
                                                                class="btn btn-warning btn-sm w-full rounded-full shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300">
                                                            <i class="fa-solid fa-check mr-2"></i>
                                                            Chấp thuận
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="flex-1">
                                                        <input type="hidden" name="action" value="process_update">
                                                        <input type="hidden" name="update_id" value="<?= $u['id'] ?>">
                                                        <button name="status" value="rejected"
                                                                class="btn btn-ghost btn-sm w-full rounded-full border-error text-error hover:bg-error hover:text-white transition-all duration-300">
                                                            <i class="fa-solid fa-xmark mr-2"></i>
                                                            Từ chối
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- All Tutors Grid -->
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="p-4 bg-gradient-to-br from-secondary to-purple-500 rounded-2xl shadow-xl">
                            <i class="fa-solid fa-users text-2xl text-white"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-black text-base-content">Danh sách Gia sư</h2>
                            <p class="text-base-content/70 mt-1">Quản lý tất cả <?= count($all_tutors) ?> gia sư trong hệ thống</p>
                        </div>
                    </div>
                    <div class="text-sm text-base-content/60 bg-base-100/50 px-4 py-2 rounded-full border border-base-200/50">
                        <i class="fa-solid fa-sort mr-2"></i>
                        Sắp xếp theo trạng thái & hoạt động
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach($all_tutors as $t):
                        $st = getOnlineStatusString($t['last_activity']);
                        $is_online = $st['status'] === 'online';

                        $status_config = match($t['status']) {
                            'active' => ['badge-success', 'Hoạt động', 'fa-circle-check'],
                            'pending' => ['badge-warning', 'Chờ duyệt', 'fa-clock'],
                            'rejected' => ['badge-error', 'Từ chối', 'fa-xmark'],
                            'banned' => ['badge-neutral', 'Khóa', 'fa-lock'],
                            default => ['badge-ghost', 'Không rõ', 'fa-question']
                        };
                    ?>
                        <div class="card bg-base-100/90 backdrop-blur-sm shadow-xl border border-base-200/50 rounded-[2rem] overflow-hidden hover:shadow-2xl hover:scale-[1.02] transition-all duration-500 group">
                            <!-- Status Indicator -->
                            <div class="absolute top-4 right-4 flex items-center gap-2">
                                <?php if($t['is_verified_tutor']): ?>
                                    <div class="w-6 h-6 bg-info rounded-full flex items-center justify-center shadow-lg">
                                        <i class="fa-solid fa-check text-white text-xs"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="w-3 h-3 <?= $is_online ? 'bg-success' : 'bg-base-300' ?> rounded-full shadow-lg"></div>
                            </div>

                            <!-- Card Header -->
                            <div class="bg-gradient-to-r from-base-200/50 to-base-300/30 p-6 border-b border-base-200/30">
                                <div class="flex items-center gap-4">
                                    <div class="avatar placeholder <?= $is_online ? 'online' : 'offline' ?>">
                                        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-secondary to-purple-500 text-white font-bold shadow-lg">
                                            <span class="text-xl"><?= strtoupper(substr($t['username'], 0, 1)) ?></span>
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-bold text-lg text-base-content flex items-center gap-2">
                                            <?= htmlspecialchars($t['username']) ?>
                                            <?php if($t['is_verified_tutor']): ?>
                                                <i class="fa-solid fa-circle-check text-info text-sm" title="Đã xác thực"></i>
                                            <?php endif; ?>
                                        </h3>
                                        <p class="text-sm text-base-content/60 flex items-center gap-1">
                                            <i class="fa-solid fa-envelope text-xs"></i>
                                            <?= htmlspecialchars($t['email']) ?>
                                        </p>
                                        <div class="flex items-center gap-2 mt-2">
                                            <span class="badge <?= $status_config[0] ?> badge-sm gap-1">
                                                <i class="fa-solid <?= $status_config[2] ?> text-xs"></i>
                                                <?= $status_config[1] ?>
                                            </span>
                                            <span class="text-xs text-base-content/50 bg-base-200/50 px-2 py-1 rounded-full">
                                                <?= $st['label'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="card-body p-6">
                                <!-- Subjects -->
                                <div class="mb-4">
                                    <div class="text-xs font-semibold text-base-content/60 uppercase tracking-wide mb-2">Môn học</div>
                                    <p class="text-sm text-base-content/80 line-clamp-2">
                                        <?= htmlspecialchars($t['subjects']) ?>
                                    </p>
                                </div>

                                <!-- Rating -->
                                <div class="mb-4">
                                    <div class="text-xs font-semibold text-base-content/60 uppercase tracking-wide mb-2">Đánh giá</div>
                                    <?php if($t['rating'] > 0): ?>
                                        <div class="flex items-center gap-2">
                                            <div class="badge badge-accent gap-1 font-bold border-2 border-accent">
                                                <i class="fa-solid fa-star text-sm"></i>
                                                <span class="text-sm"><?= round($t['rating'], 1) ?>/5</span>
                                            </div>
                                            <div class="text-xs text-base-content/50">
                                                (Trên 5 sao)
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-sm text-base-content/40 italic">Chưa có đánh giá</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Pricing Form -->
                                <div class="mb-4">
                                    <div class="text-xs font-semibold text-base-content/60 uppercase tracking-wide mb-2">Bảng giá (điểm)</div>
                                    <form method="POST" class="bg-base-200/50 p-3 rounded-xl border border-base-200/50">
                                        <input type="hidden" name="action" value="update_prices">
                                        <input type="hidden" name="tutor_id" value="<?= $t['id'] ?>">
                                        <div class="grid grid-cols-3 gap-2 mb-3">
                                            <div class="text-center">
                                                <div class="text-xs text-base-content/60 mb-1">Cơ bản</div>
                                                <input class="input input-xs w-full text-center bg-base-100 border border-base-300 focus:border-primary transition-colors"
                                                       name="price_basic" value="<?= $t['price_basic'] ?>" placeholder="0">
                                            </div>
                                            <div class="text-center">
                                                <div class="text-xs text-base-content/60 mb-1">Tiêu chuẩn</div>
                                                <input class="input input-xs w-full text-center bg-base-100 border border-base-300 focus:border-primary transition-colors"
                                                       name="price_standard" value="<?= $t['price_standard'] ?>" placeholder="0">
                                            </div>
                                            <div class="text-center">
                                                <div class="text-xs text-base-content/60 mb-1">Cao cấp</div>
                                                <input class="input input-xs w-full text-center bg-base-100 border border-base-300 focus:border-primary transition-colors"
                                                       name="price_premium" value="<?= $t['price_premium'] ?>" placeholder="0">
                                            </div>
                                        </div>
                                        <button class="btn btn-primary btn-xs w-full rounded-full shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-300">
                                            <i class="fa-solid fa-save mr-2"></i>
                                            Lưu thay đổi
                                        </button>
                                    </form>
                                </div>

                                <!-- Actions -->
                                <div class="flex gap-2">
                                    <form method="POST" class="flex-1">
                                        <input type="hidden" name="action" value="toggle_verification">
                                        <input type="hidden" name="tutor_id" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="verify_status" value="<?= $t['is_verified_tutor'] ? 0 : 1 ?>">
                                        <button class="btn btn-outline btn-xs w-full rounded-full hover:bg-info hover:text-white transition-all duration-300">
                                            <i class="fa-solid <?= $t['is_verified_tutor'] ? 'fa-ban' : 'fa-check-circle' ?> mr-2"></i>
                                            <?= $t['is_verified_tutor'] ? 'Hủy Verified' : 'Cấp Verified' ?>
                                        </button>
                                    </form>

                                    <?php if($t['status'] === 'active'): ?>
                                        <form method="POST" class="flex-1">
                                            <input type="hidden" name="action" value="process_registration">
                                            <input type="hidden" name="tutor_id" value="<?= $t['id'] ?>">
                                            <button name="status" value="banned"
                                                    class="btn btn-outline btn-error btn-xs w-full rounded-full hover:bg-error hover:text-white transition-all duration-300">
                                                <i class="fa-solid fa-lock mr-2"></i>
                                                Khóa
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="flex-1">
                                            <input type="hidden" name="action" value="process_registration">
                                            <input type="hidden" name="tutor_id" value="<?= $t['id'] ?>">
                                            <button name="status" value="active"
                                                    class="btn btn-outline btn-success btn-xs w-full rounded-full hover:bg-success hover:text-white transition-all duration-300">
                                                <i class="fa-solid fa-unlock mr-2"></i>
                                                Kích hoạt
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
