<?php
session_start();
require_once 'config/db.php';
require_once 'config/function.php';
require_once 'config/auth.php';
require_once 'config/premium.php';

redirectIfNotLoggedIn();

$user_id = getCurrentUserId();
$user = getUserInfo($user_id);
$is_premium = isPremium($user_id);
$premium_info = getPremiumInfo($user_id);

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

$page_title = "Cài đặt tài khoản - DocShare";
$current_page = 'profile';

$error = '';
$success = '';

// Handle avatar upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
    if (!file_exists('uploads/avatars')) {
        mkdir('uploads/avatars', 0777, true);
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['avatar']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {
        $new_name = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
        $destination = 'uploads/avatars/' . $new_name;

        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
            // Delete old avatar
            if (!empty($user['avatar']) && file_exists('uploads/avatars/' . $user['avatar'])) {
                @unlink('uploads/avatars/' . $user['avatar']);
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

include 'includes/head.php'; 
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
</style>

<body class="bg-base-100">
    <?php include 'includes/sidebar.php'; ?>

    <div class="drawer-content flex flex-col min-h-screen">
        <?php include 'includes/navbar.php'; ?>

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
                                    <?php if(!empty($user['avatar']) && file_exists('uploads/avatars/' . $user['avatar'])): ?>
                                        <img src="uploads/avatars/<?= $user['avatar'] ?>" />
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

        <?php include 'includes/footer.php'; ?>
    </div>

    <script>
        async function handlePushToggle(el) {
            if (typeof checkNotificationStatus !== 'function') {
                alert('Hệ thống thông báo chưa được khởi tạo.');
                el.checked = false;
                return;
            }
            const status = checkNotificationStatus();
            if (status === 'denied') {
                el.checked = false;
                alert('Bạn đã chặn thông báo. Vui lòng đặt lại quyền trên trình duyệt.');
                return;
            }
            if (el.checked) {
                if (status === 'default') {
                    const result = await Notification.requestPermission();
                    if (result === 'granted') { await subscribePush(); } 
                    else { el.checked = false; }
                } else { await subscribePush(); }
            } else {
                await unsubscribePush();
                alert('Đã tắt thông báo trên thiết bị này.');
            }
        }
    </script>
</body>
</html>
