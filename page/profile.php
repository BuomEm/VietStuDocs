<?php
session_start();
require_once '../config/db.php';
require_once '../config/function.php';
require_once '../config/auth.php';
require_once '../config/premium.php';

redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$user = getUserInfo($user_id);
$is_premium = isPremium($user_id);
$premium_info = getPremiumInfo($user_id);

// Get streak information
require_once '../config/streak.php';
$streak_info = getUserStreakInfo($user_id);
$streak_badge = getStreakBadge($streak_info['current_streak']);


// Calculate days remaining for Premium
$days_remaining = 0;
$hours_remaining = 0;
$show_countdown = false;

if($is_premium && $premium_info) {
    try {
        $end_date = new DateTime($premium_info['end_date']);
        $now = new DateTime();
        
        if ($end_date > $now) {
            $interval = $now->diff($end_date);
            $days_remaining = intval($interval->days);
            $hours_remaining = intval($interval->h);
            
            if($days_remaining < 7) {
                $show_countdown = true;
            }
        }
    } catch (Exception $e) {}
}

$page_title = "Cài đặt tài khoản - VietStuDocs";
$current_page = 'profile';

$error = '';
$success = '';

// Handle avatar upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
    if (!file_exists('../uploads/avatars')) {
        mkdir('../uploads/avatars', 0777, true);
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['avatar']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {
        $new_name = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
        $destination = '../uploads/avatars/' . $new_name;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
            // Delete old avatar
            if (!empty($user['avatar']) && file_exists('../uploads/avatars/' . $user['avatar'])) {
                @unlink('../uploads/avatars/' . $user['avatar']);
            }

            if ($VSD->query("UPDATE users SET avatar = '$new_name' WHERE id = $user_id")) {
                $success = "Cập nhật ảnh đại diện thành công!";
                $user = getUserInfo($user_id);
            } else {
                $error = "Lỗi cập nhật cơ sở dữ liệu.";
            }
        } else {
            $error = "Không thể tải tệp lên.";
        }
    } else {
        $error = "Định dạng không hợp lệ. Chỉ chấp nhận JPG, PNG, GIF.";
    }
}

// Handle profile update
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    
    if(strlen($username) < 3) {
        $error = "Tên đăng nhập phải có ít nhất 3 ký tự.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email không hợp lệ.";
    } elseif(updateUserProfile($user_id, $username, $email)) {
        $success = "Cập nhật thông tin thành công!";
        $user = getUserInfo($user_id);
    } else {
        $error = "Cập nhật thông tin thất bại.";
    }
}

// Handle password change
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if(empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Vui lòng nhập đầy đủ các trường.";
    } elseif($new_password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp.";
    } elseif(strlen($new_password) < 6) {
        $error = "Mật khẩu mới phải có ít nhất 6 ký tự.";
    } elseif(changePassword($user_id, $old_password, $new_password)) {
        $success = "Đổi mật khẩu thành công!";
    } else {
        $error = "Mật khẩu cũ không chính xác.";
    }
}

// Count user's documents
$my_docs_count = intval($VSD->num_rows("SELECT id FROM documents WHERE user_id=$user_id") ?: 0);
$saved_docs_count = intval($VSD->num_rows("SELECT DISTINCT d.id FROM documents d JOIN document_interactions di ON d.id = di.document_id WHERE di.user_id=$user_id AND di.type='save'") ?: 0);

// Handle Admin Streak Reset (TESTING ONLY)
if(isset($_GET['reset_streak']) && isAdmin($user_id)) {
    db_query("UPDATE users SET current_streak = 0, longest_streak = 0, streak_freezes = 2, last_streak_date = NULL WHERE id = $user_id");
    header("Location: profile?msg=streak_reset");
    exit;
}


include '../includes/head.php'; 
?>
<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.2);
    }
    
    [data-theme="dark"] {
        --glass-bg: rgba(15, 23, 42, 0.7);
        --glass-border: rgba(255, 255, 255, 0.1);
    }

    .profile-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 40px 24px;
    }

    .glass-card {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 2.5rem;
        padding: 32px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.05);
        margin-bottom: 32px;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        color: oklch(var(--p));
    }

    /* Avatar Section */
    .avatar-wrapper {
        position: relative;
        width: 120px;
        height: 120px;
    }

    .avatar-ring {
        width: 100%;
        height: 100%;
        border-radius: 2.5rem;
        padding: 6px;
        background: linear-gradient(135deg, oklch(var(--p)), oklch(var(--s)));
        box-shadow: 0 15px 30px -10px oklch(var(--p) / 0.4);
    }

    .avatar-inner {
        width: 100%;
        height: 100%;
        border-radius: calc(2.5rem - 6px);
        background: var(--glass-bg);
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .avatar-inner img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .avatar-edit-btn {
        position: absolute;
        bottom: -5px;
        right: -5px;
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: oklch(var(--b1));
        border: 1px solid var(--glass-border);
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1);
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .avatar-edit-btn:hover {
        transform: scale(1.1);
        background: oklch(var(--p));
        color: white;
    }

    /* Stats Grid */
    .stats-vsd-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-top: 32px;
    }

    .stat-vsd-card {
        padding: 24px;
        border-radius: 2rem;
        background: oklch(var(--bc) / 0.03);
        border: 1px solid oklch(var(--bc) / 0.05);
        text-align: center;
    }

    .stat-vsd-val {
        font-size: 2rem;
        font-weight: 900;
        color: oklch(var(--p));
        line-height: 1;
        margin-bottom: 4px;
    }

    .stat-vsd-label {
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: oklch(var(--bc) / 0.4);
    }

    /* Form Styling */
    .form-group-vsd {
        margin-bottom: 24px;
    }

    .label-vsd {
        font-size: 11px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: oklch(var(--bc) / 0.5);
        margin-bottom: 8px;
        display: block;
    }

    .input-vsd {
        width: 100%;
        height: 56px;
        border-radius: 1.25rem;
        background: oklch(var(--bc) / 0.03);
        border: 1px solid oklch(var(--bc) / 0.05);
        padding: 0 20px;
        font-weight: 700;
        transition: all 0.3s ease;
    }

    .input-vsd:focus {
        background: oklch(var(--b1));
        border-color: oklch(var(--p));
        outline: none;
        box-shadow: 0 0 0 4px oklch(var(--p) / 0.1);
    }

    .btn-vsd-save {
        width: 100%;
        height: 56px;
        border-radius: 1.25rem;
        background: oklch(var(--p));
        color: white;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        box-shadow: 0 15px 30px -10px oklch(var(--p) / 0.3);
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
    }

    .btn-vsd-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 20px 40px -12px oklch(var(--p) / 0.4);
    }

    /* Premium Countdown Premium */
    .premium-box-vsd {
        background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
        color: white;
        border-radius: 2rem;
        padding: 32px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 32px;
    }

    .countdown-unit {
        text-align: center;
        background: rgba(255,255,255,0.2);
        padding: 12px 20px;
        border-radius: 1.25rem;
        min-width: 80px;
    }

    .countdown-val {
        font-size: 1.75rem;
        font-weight: 900;
        line-height: 1;
    }

    .countdown-label {
        font-size: 9px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1em;
    }

    .streak-week-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 12px;
        margin: 24px 0;
    }

    .streak-day-box {
        background: oklch(var(--bc) / 0.03);
        border: 1px solid oklch(var(--bc) / 0.05);
        border-radius: 1.5rem;
        padding: 16px 8px;
        text-align: center;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        position: relative;
    }

    .streak-day-box.active {
        background: oklch(var(--p) / 0.1);
        border-color: oklch(var(--p) / 0.3);
        transform: translateY(-4px);
        box-shadow: 0 10px 20px -5px oklch(var(--p) / 0.2);
    }

    .streak-day-label {
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        opacity: 0.5;
    }

    .streak-day-box.active .streak-day-label {
        opacity: 1;
        color: oklch(var(--p));
    }

    .flame-icon {
        font-size: 1.5rem;
        color: oklch(var(--bc) / 0.1);
        transition: all 0.5s ease;
    }

    .streak-day-box.active .flame-icon {
        color: #ef4444;
        filter: drop-shadow(0 0 8px #ef4444);
        animation: flicker 1.5s infinite;
    }

    .streak-reward-tag {
        font-size: 9px;
        font-weight: 900;
        background: oklch(var(--bc) / 0.05);
        padding: 2px 8px;
        border-radius: 999px;
    }

    .streak-day-box.active .streak-reward-tag {
        background: oklch(var(--p));
        color: white;
    }

    @keyframes flicker {
        0%, 100% { transform: scale(1) rotate(-1deg); opacity: 1; }
        25% { transform: scale(1.1) rotate(2deg); opacity: 0.9; }
        50% { transform: scale(0.95) rotate(-2deg); opacity: 1; }
        75% { transform: scale(1.05) rotate(1deg); opacity: 0.95; }
    }


</style>

<body class="bg-base-100">
    <?php include '../includes/sidebar.php'; ?>

    <div class="drawer-content flex flex-col min-h-screen">
        <?php include '../includes/navbar.php'; ?>

        <main class="flex-1">
            <div class="profile-container">
                
                <!-- Alerts -->
                <?php if($error): ?>
                    <div class="glass-card !py-4 border-l-4 border-error mb-6">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-circle-xmark text-error text-xl"></i>
                            <span class="font-bold text-sm"><?= htmlspecialchars($error) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if($success): ?>
                    <div class="glass-card !py-4 border-l-4 border-success mb-6">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-circle-check text-success text-xl"></i>
                            <span class="font-bold text-sm"><?= htmlspecialchars($success) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Header / Profile Info -->
                <div class="glass-card">
                    <div class="flex flex-col md:flex-row items-center gap-8">
                        <div class="avatar-wrapper">
                            <div class="avatar-ring">
                                <div class="avatar-inner">
                                    <?php if(!empty($user['avatar']) && file_exists('../uploads/avatars/' . $user['avatar'])): ?>
                                        <img src="../uploads/avatars/<?= $user['avatar'] ?>" />
                                    <?php else: ?>
                                        <i class="fa-solid fa-user text-5xl text-primary/50"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button onclick="document.getElementById('avatar-input').click()" class="avatar-edit-btn">
                                <i class="fa-solid fa-camera"></i>
                            </button>
                            <form id="avatar-form" method="POST" enctype="multipart/form-data" class="hidden">
                                <input type="file" id="avatar-input" name="avatar" accept="image/*" onchange="document.getElementById('avatar-form').submit()">
                            </form>
                        </div>

                        <div class="flex-1 text-center md:text-left">
                            <h1 class="text-3xl font-black tracking-tight mb-2"><?= htmlspecialchars($user['username']) ?></h1>
                            <div class="flex flex-wrap justify-center md:justify-start gap-4">
                                <span class="bg-base-200 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest opacity-60">
                                    <i class="fa-solid fa-envelope mr-1"></i> <?= htmlspecialchars($user['email']) ?>
                                </span>
                                <span class="bg-base-200 px-4 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest opacity-60">
                                    <i class="fa-solid fa-calendar mr-1"></i> Tham gia <?= date('m/Y', strtotime($user['created_at'])) ?>
                                </span>
                            </div>
                        </div>

                        <div class="flex gap-2">
                             <?php if($is_premium): ?>
                                <div class="badge badge-warning p-4 font-black text-[10px] tracking-widest rounded-xl">
                                    <i class="fa-solid fa-crown mr-1"></i> PREMIUM
                                </div>
                             <?php else: ?>
                                <a href="premium.php" class="btn btn-ghost btn-sm rounded-xl font-bold">Nâng cấp <i class="fa-solid fa-arrow-right ml-1"></i></a>
                             <?php endif; ?>
                        </div>
                    </div>

                    <div class="stats-vsd-grid">
                        <div class="stat-vsd-card">
                            <div class="stat-vsd-val"><?= intval($my_docs_count) ?></div>
                            <div class="stat-vsd-label">Tải lên</div>
                        </div>
                        <div class="stat-vsd-card">
                            <div class="stat-vsd-val"><?= intval($saved_docs_count) ?></div>
                            <div class="stat-vsd-label">Đã lưu</div>
                        </div>
                        <div class="stat-vsd-card">
                            <div class="stat-vsd-val">
                                <i class="fa-solid fa-<?= $is_premium ? 'circle-check text-success' : 'circle-xmark text-base-content/20' ?>"></i>
                            </div>
                            <div class="stat-vsd-label">Trạng thái Premium</div>
                        </div>
                    </div>
                </div>

                <!-- Premium Status Box -->
                <?php if($is_premium): ?>
                    <div class="premium-box-vsd mb-8">
                        <div class="flex-1">
                            <h3 class="font-black text-xl mb-1 uppercase tracking-tighter">Premium đang hoạt động</h3>
                            <p class="text-xs font-bold opacity-80 uppercase tracking-widest">Hết hạn vào <?= date('d/m/Y', strtotime($premium_info['end_date'])) ?></p>
                        </div>
                        <?php if($show_countdown): ?>
                            <div class="flex gap-2">
                                <div class="countdown-unit">
                                    <div class="countdown-val"><?= $days_remaining ?></div>
                                    <div class="countdown-label">Ngày</div>
                                </div>
                                <div class="countdown-unit">
                                    <div class="countdown-val"><?= $hours_remaining ?></div>
                                    <div class="countdown-label">Giờ</div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <!-- Login Streak Section -->
                <div class="glass-card" id="streak">
                    <div class="flex flex-col md:flex-row items-center justify-between gap-6 mb-8">
                        <div>
                            <h2 class="section-title !mb-1"><i class="fa-solid fa-fire text-red-500"></i> Chuỗi Đăng Nhập</h2>
                            <p class="text-[11px] font-bold opacity-50 uppercase tracking-widest">
                                <?= $streak_info['ui_message'] ?>
                            </p>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <?php if(isAdmin($user_id)): ?>
                                <button type="button" class="btn btn-xs btn-error btn-outline rounded-lg" onclick="vsdConfirm({title: 'Reset Test', message: 'Bạn có muốn reset chuỗi và khôi phục 2 Freeze để test không?', type: 'error', onConfirm: () => window.location.href='?reset_streak=1'})">
                                    <i class="fa-solid fa-rotate-left mr-1"></i> Reset Test
                                </button>
                            <?php endif; ?>
                            
                            <div class="text-right">
                                <div class="text-2xl font-black text-primary leading-none"><?= $streak_info['current_streak'] ?></div>
                                <div class="text-[9px] font-black opacity-40 uppercase tracking-tighter">Ngày hiện tại</div>
                            </div>
                            <div class="h-8 w-px bg-base-content/10"></div>
                            <div class="text-right">
                                <div class="text-2xl font-black opacity-60 leading-none"><?= $streak_info['longest_streak'] ?></div>
                                <div class="text-[9px] font-black opacity-40 uppercase tracking-tighter">Kỷ lục</div>
                            </div>
                            <div class="h-8 w-px bg-base-content/10"></div>
                            <div class="text-right">
                                <div class="text-2xl font-black text-blue-500 leading-none flex items-center gap-1 justify-end">
                                    <i class="fa-solid fa-snowflake text-xs"></i>
                                    <?= $streak_info['streak_freezes'] ?>
                                </div>
                                <div class="text-[9px] font-black opacity-40 uppercase tracking-tighter">Bảo vệ (Freeze)</div>
                            </div>
                        </div>
                    </div>

                    <?php
                    // Calculate which day of the 7-day cycle we are on
                    // If they already claimed today, current_streak reflects the completed day
                    // If they haven't claimed, current_streak is the last completed day, next one is current_streak + 1
                    $display_streak = $streak_info['current_streak'];
                    $has_claimed = !$streak_info['can_claim'];
                    
                    // The cycle starts from 1 to 7
                    // If streak is 0, they are on day 1 (not claimed)
                    // If streak is 7, they finished day 7 (claimed) -> cycle starts over at 1
                    
                    $current_cycle_index = (($display_streak - ($has_claimed ? 1 : 0)) % 7); // 0 to 6
                    $rewards = [
                        1 => getSetting('streak_reward_1_3', 1),
                        2 => getSetting('streak_reward_1_3', 1),
                        3 => getSetting('streak_reward_1_3', 1),
                        4 => getSetting('streak_reward_4', 2),
                        5 => getSetting('streak_reward_5_6', 1),
                        6 => getSetting('streak_reward_5_6', 1),
                        7 => getSetting('streak_reward_7', 3),
                    ];
                    ?>

                    <div class="streak-week-grid">
                        <?php for($i = 1; $i <= 7; $i++): 
                            $is_active = false;
                            $day_in_cycle = (($display_streak - 1) % 7) + 1; // 1-7
                            
                            if ($has_claimed) {
                                // If claimed, days up to current cycle day are active
                                if ($i <= $day_in_cycle) $is_active = true;
                            } else {
                                // If not claimed, only days strictly before the one they are about to claim are active
                                // Example: streak 0, about to claim day 1 -> none active
                                // Example: streak 1, about to claim day 2 -> day 1 active
                                if ($i <= ($display_streak % 7) && $display_streak > 0 && ($display_streak % 7) != 0) $is_active = true;
                                // Special case: finished a full week (streak 7), none active yet for next week
                            }
                        ?>
                            <div class="streak-day-box <?= $is_active ? 'active' : '' ?>">
                                <span class="streak-day-label">Ngày <?= $i ?></span>
                                <i class="fa-solid fa-fire flame-icon"></i>
                                <span class="streak-reward-tag">+<?= $rewards[$i] ?> VSD</span>
                            </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Helper Text -->
                    <div class="my-6 p-4 bg-primary/5 border border-primary/10 rounded-2xl flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary shrink-0">
                            <i class="fa-solid fa-lightbulb"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-wider text-primary mb-1">Mẹo giữ lửa</h4>
                            <p class="text-[11px] text-base-content/60 font-medium leading-relaxed">
                                Bạn không cần làm gì thêm – chỉ cần quay lại hệ thống mỗi ngày để không bị gián đoạn chuỗi. Đơn giản vậy thôi! ✨
                            </p>
                        </div>
                    </div>

                    <?php if($streak_info['can_claim']): ?>
                        <button onclick="claimStreak(this)" class="btn btn-primary btn-lg w-full rounded-2xl font-black group overflow-hidden relative">
                            <div class="absolute inset-0 bg-white/20 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></div>
                            <span class="relative flex items-center gap-2">
                                <i class="fa-solid fa-gift text-xl group-hover:scale-110 transition-transform"></i>
                                ĐIỂM DANH NHẬN QUÀ
                            </span>
                        </button>
                    <?php else: ?>
                        <div class="bg-success/10 border border-success/20 rounded-2xl p-4 text-center">
                            <span class="text-success font-black text-sm uppercase tracking-tight">
                                <i class="fa-solid fa-circle-check mr-2"></i> Đã hoàn thành điểm danh hôm nay
                            </span>
                        </div>
                    <?php endif; ?>
                </div>


                <!-- Toast Container -->
                <div id="vsd-toast-container"></div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Update Profile -->
                    <div class="glass-card">
                        <h2 class="section-title"><i class="fa-solid fa-user-pen"></i> Cài đặt tài khoản</h2>
                        <form method="POST">
                            <div class="form-group-vsd">
                                <label class="label-vsd">Tên đăng nhập</label>
                                <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" class="input-vsd">
                            </div>
                            <div class="form-group-vsd">
                                <label class="label-vsd">Địa chỉ Email</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="input-vsd">
                            </div>
                            <button type="submit" name="update_profile" class="btn-vsd-save">Cập nhật thông tin</button>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="glass-card">
                        <h2 class="section-title"><i class="fa-solid fa-lock"></i> Bảo mật</h2>
                        <form method="POST">
                            <div class="form-group-vsd">
                                <label class="label-vsd">Mật khẩu hiện tại</label>
                                <input type="password" name="old_password" class="input-vsd" placeholder="••••••••">
                            </div>
                            <div class="form-group-vsd">
                                <label class="label-vsd">Mật khẩu mới</label>
                                <input type="password" name="new_password" class="input-vsd" placeholder="Tối thiểu 6 ký tự">
                            </div>
                            <div class="form-group-vsd">
                                <label class="label-vsd">Xác nhận mật khẩu mới</label>
                                <input type="password" name="confirm_password" class="input-vsd" placeholder="Nhập lại mật khẩu mới">
                            </div>
                            <button type="submit" name="change_password" class="btn-vsd-save">Đổi mật khẩu</button>
                        </form>
                    </div>
                </div>

                <!-- Notifications Settings -->
                <div class="glass-card">
                    <h2 class="section-title"><i class="fa-solid fa-bell"></i> Thông báo</h2>
                    <div class="flex items-center justify-between p-6 bg-base-200/30 rounded-3xl border border-base-content/5">
                        <div>
                            <span class="font-black text-sm uppercase tracking-tight block">Thông báo trình duyệt (Web Push)</span>
                            <span class="text-[10px] font-bold opacity-30 mt-1 uppercase tracking-widest" id="push-status">Trạng thái: Đang kiểm tra...</span>
                        </div>
                        <input type="checkbox" id="btn-toggle-push" class="toggle toggle-primary toggle-lg" onchange="handlePushToggle(this)" />
                    </div>
                </div>

            </div>
        </main>

        <?php include '../includes/footer.php'; ?>
    </div>

    <script>
        async function handlePushToggle(el) {
            if (typeof checkNotificationStatus !== 'function') {
                showAlert('Hệ thống thông báo chưa được khởi tạo. Vui lòng tải lại trang.', 'error');
                el.checked = false;
                return;
            }
            
            const status = checkNotificationStatus();
            
            if (status === 'denied') {
                el.checked = false;
                vsdConfirm({
                    title: 'Quyền thông báo bị chặn',
                    message: 'Bạn đã chặn thông báo trên trình duyệt. Vui lòng đặt lại quyền (Click vào biểu tượng ổ khóa trên thanh địa chỉ) để nhận được các nhắc nhở quan trọng nhé!',
                    confirmText: 'Tôi đã hiểu',
                    type: 'warning',
                    onConfirm: () => {}
                });
                return;
            }

            try {
                if (el.checked) {
                    let granted = false;
                    if (status === 'default') {
                        const result = await Notification.requestPermission();
                        granted = (result === 'granted');
                    } else {
                        granted = true;
                    }

                    if (granted) {
                        const success = await subscribePush();
                        if (!success) {
                            throw new Error('Đăng ký thất bại');
                        }
                    } else {
                        el.checked = false;
                    }
                } else {
                    const success = await unsubscribePush();
                    if (success) {
                        showAlert('Đã tắt thông báo trên thiết bị này.', 'info');
                    } else {
                        // Even if API fail, we visually turn it off
                        console.warn('Unsubscribe API warn');
                    }
                }
            } catch (e) {
                console.error(e);
                el.checked = !el.checked; // Revert state
                showAlert('Có lỗi xảy ra khi cập nhật trạng thái thông báo.', 'error');
            }
        }

        // Show reset message if exists
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'streak_reset'): ?>
        document.addEventListener('DOMContentLoaded', () => {
            showAlert('Đã reset chuỗi và khôi phục 2 Freeze để test!', 'success');
        });
        <?php endif; ?>

        async function claimStreak(btn) {
            btn.disabled = true;
            const originalContent = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Đang xử lý...';
            
            try {
                const response = await fetch('../api/streak_claim.php');
                const result = await response.json();
                
                if (result.success) {
                    showAlert(`${result.message} +${result.points_earned} VSD`, 'success');
                    
                    // Reload after a short delay to show the toast
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert(result.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            } catch (error) {
                console.error('Error claiming streak:', error);
                showAlert('Có lỗi xảy ra khi điểm danh.', 'error');
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }

    </script>
</body>
</html>
