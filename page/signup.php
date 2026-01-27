<?php
require_once __DIR__ . '/../includes/error_handler.php';
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard");
    exit();
}

require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../config/settings.php';
require_once '../config/telegram_notifications.php';

$site_name = getSetting('site_name', 'VietStuDocs');
$site_logo = getSetting('site_logo', '');
$site_desc = getSetting('site_description', 'Chia sẻ tri thức, kết nối cộng đồng');

$error = '';
$success = '';

// Handle Registration
if($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['register']) || isset($_POST['username']))) {
    // Validate terms acceptance
    if(!isset($_POST['terms']) || $_POST['terms'] !== 'on') {
        $error = "Bạn phải đồng ý với điều khoản sử dụng!";
    } elseif(!preg_match('/^[a-zA-Z0-9_]+$/', $_POST['username'])) {
        $error = "Tên đăng nhập chỉ được chứa chữ cái không dấu, số và dấu gạch dưới (_)!";
    } elseif(strlen($_POST['username']) < 3 || strlen($_POST['username']) > 30) {
        $error = "Tên đăng nhập phải từ 3-30 ký tự!";
    } elseif($_POST['password'] !== $_POST['password_confirm']) {
        $error = "Mật khẩu xác nhận không khớp!";
    } elseif(strlen($_POST['password']) < 8) {
        $error = "Mật khẩu phải có ít nhất 8 ký tự!";
    } else {
        // Handle avatar upload
        $avatar_filename = null;
        
        if(isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 15 * 1024 * 1024; // 15MB
            
            if(!in_array($_FILES['avatar']['type'], $allowed_types)) {
                $error = "Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP)!";
            } elseif($_FILES['avatar']['size'] > $max_size) {
                $error = "Kích thước ảnh tối đa là 15MB!";
            } else {
                $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $avatar_filename = 'avatar_' . time() . '_' . uniqid() . '.' . $ext;
                $upload_path = '../uploads/avatars/' . $avatar_filename;
                
                if(!is_dir('../uploads/avatars')) {
                    mkdir('../uploads/avatars', 0755, true);
                }
                
                if(!move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                    $error = "Không thể tải lên avatar!";
                    $avatar_filename = null;
                }
            }
        } else {
            // Use default avatar
            $default_source = '../assets/img/user.png';
            if(file_exists($default_source)) {
                $avatar_filename = 'avatar_' . time() . '_' . uniqid() . '.png';
                $upload_path = '../uploads/avatars/' . $avatar_filename;
                
                if(!is_dir('../uploads/avatars')) {
                    mkdir('../uploads/avatars', 0755, true);
                }
                
                if(!copy($default_source, $upload_path)) {
                    // If copy fails, fallback to null (initials)
                    $avatar_filename = null;
                }
            }
        }
        
        if(empty($error)) {
            $result = registerUser($_POST['username'], $_POST['email'], $_POST['password']);
            if($result['success']) {
                // Update avatar if uploaded
                if($avatar_filename && isset($result['user_id'])) {
                    $user_id = $result['user_id'];
                    $VSD->query("UPDATE users SET avatar = '" . $VSD->escape($avatar_filename) . "' WHERE id = $user_id");
                }
                
                // Send Telegram Notification
                $msg = "<b>🔔 Người dùng mới đăng ký!</b>\n";
                $msg .= "👤 Username: " . $_POST['username'] . "\n";
                $msg .= "📧 Email: " . $_POST['email'] . "\n";
                $msg .= "⏰ Thời gian: " . date('H:i d/m/Y');
                sendTelegramNotification($msg);

                $success = "Đăng ký thành công! Hãy đăng nhập.";
            } else {
                $error = $result['message'];
                // Delete uploaded avatar if registration failed
                if($avatar_filename && file_exists('../uploads/avatars/' . $avatar_filename)) {
                    unlink('../uploads/avatars/' . $avatar_filename);
                }
            }
        }
    }
}
$page_title = "Đăng ký";
include '../includes/head.php';
?>

<style>
    body {
        background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%) !important;
        overflow-x: hidden;
    }

    .glass-card {
        background: rgba(255, 255, 255, 0.82);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        box-shadow: 0 25px 50px -12px rgba(128, 0, 0, 0.12);
    }

    .orb {
        position: fixed;
        border-radius: 50%;
        filter: blur(100px);
        z-index: 0;
        opacity: 0.3;
    }

    .orb-1 { width: 500px; height: 500px; background: #800000; top: -15%; left: -15%; }
    .orb-2 { width: 400px; height: 400px; background: #ffcc00; bottom: -10%; right: -10%; }
    .orb-3 { width: 300px; height: 300px; background: #4a90d9; top: 50%; right: -5%; }

    .input-premium {
        background: rgba(255, 255, 255, 0.5);
        border: 1px solid rgba(128, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .input-premium:focus {
        background: white;
        border-color: #800000;
        box-shadow: 0 0 0 4px rgba(128, 0, 0, 0.1);
    }

    .btn-premium {
        background: #800000;
        color: white;
        border: none;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .btn-premium:hover {
        background: #a00000;
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -5px rgba(128, 0, 0, 0.3);
    }

    .btn-google {
        background: white;
        color: #333;
        border: 1px solid #e5e5e5;
        transition: all 0.3s ease;
    }

    .btn-google:hover {
        background: #f8f8f8;
        border-color: #ddd;
    }

    .btn-google.disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .form-fade {
        animation: fadeIn 0.5s ease forwards;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Avatar Upload (next to username) */
    .avatar-upload-small {
        position: relative;
        width: 56px;
        height: 56px;
        flex-shrink: 0;
        cursor: pointer;
    }

    .avatar-upload-small .avatar-preview-small {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 2px dashed rgba(128, 0, 0, 0.3);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .avatar-upload-small:hover .avatar-preview-small {
        border-color: #800000;
        border-style: solid;
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(128, 0, 0, 0.15);
    }

    .avatar-upload-small .avatar-preview-small img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 12px;
    }

    .avatar-upload-small .avatar-edit-small {
        position: absolute;
        bottom: -4px;
        right: -4px;
        width: 22px;
        height: 22px;
        background: #800000;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
        color: white;
        border: 2px solid white;
        transition: all 0.3s ease;
        box-shadow: 0 2px 6px rgba(128,0,0,0.3);
    }

    .avatar-upload-small:hover .avatar-edit-small {
        background: #a00000;
        transform: scale(1.1);
    }

    .avatar-upload-small input[type="file"] {
        display: none;
    }

    .coming-soon-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: linear-gradient(135deg, #ff6b6b, #ee5a5a);
        color: white;
        font-size: 9px;
        font-weight: 800;
        padding: 3px 8px;
        border-radius: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 8px rgba(255, 107, 107, 0.4);
    }

    .checkbox-premium {
        --chkbg: #800000;
        --chkfg: white;
        border: 2px solid #800000 !important;
        border-radius: 4px;
    }
    
    .checkbox-premium:not(:checked) {
        background-color: white;
        border-color: #999 !important;
    }
    
    .checkbox-premium:hover {
        border-color: #800000 !important;
    }
</style>
<body class="min-h-screen w-full flex flex-col items-center justify-center relative p-4 py-8 overflow-hidden">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="w-full max-w-lg relative z-10 mx-auto">
        <!-- Logo Section -->
        <div class="text-center mb-6">
            <div class="flex items-center justify-center gap-4 mb-3">
                <?php if(!empty($site_logo)): ?>
                     <img src="<?= htmlspecialchars($site_logo) ?>" alt="Logo" class="w-14 h-14 object-contain drop-shadow-lg">
                <?php else: ?>
                    <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 bg-white rounded-2xl shadow-xl rotate-3 border border-white">
                        <i class="fa-solid fa-file-contract text-2xl" style="color: #800000;"></i>
                    </div>
                <?php endif; ?>
                <h1 class="text-3xl font-extrabold text-[#800000] tracking-tight"><?= htmlspecialchars($site_name) ?></h1>
            </div>
            <p class="text-gray-500 font-medium text-sm"><?= htmlspecialchars($site_desc) ?></p>
        </div>

        <div class="glass-card rounded-[2.5rem] p-8 md:p-10">
            <!-- Alert Messages -->
            <?php if($error): ?>
                <div class="alert alert-error mb-6 rounded-2xl bg-red-50 border-red-100 text-red-800">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span class="text-sm font-semibold"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert alert-success mb-6 rounded-2xl bg-green-50 border-green-100 text-green-800">
                    <i class="fa-solid fa-circle-check"></i>
                    <span class="text-sm font-semibold"><?= htmlspecialchars($success) ?></span>
                    <a href="/login" class="btn btn-sm btn-success ml-2">Đăng nhập ngay</a>
                </div>
            <?php endif; ?>

            <!-- Register Form -->
            <div class="form-fade">
                <h2 class="text-2xl font-bold text-gray-800 mb-2 text-center">Tạo tài khoản mới</h2>
                <p class="text-gray-500 text-sm text-center mb-6">Tham gia cộng đồng chia sẻ tài liệu</p>

                <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
                    <!-- Username + Avatar Row -->
                    <div class="form-control">
                        <label class="label px-1">
                            <span class="label-text font-bold text-gray-600">Tên đăng nhập <span class="text-red-500">*</span></span>
                        </label>
                        <div class="flex gap-3 items-center">
                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                                    <i class="fa-solid fa-at"></i>
                                </span>
                                <input type="text" name="username" placeholder="nguyen_van_a" class="input input-bordered w-full pl-11 rounded-xl input-premium" required minlength="3" maxlength="30" pattern="^[a-zA-Z0-9_]+$" title="Chỉ chứa chữ cái không dấu, số và dấu _">
                            </div>
                            
                            <!-- Avatar Upload (Optional) -->
                            <label for="avatarInput" class="avatar-upload-small" title="Tải lên avatar">
                                <div class="avatar-preview-small" id="avatarPreview">
                                    <i class="fa-solid fa-user text-xl text-gray-400"></i>
                                </div>
                                <span class="avatar-edit-small">
                                    <i class="fa-solid fa-camera text-[10px]"></i>
                                </span>
                                <input type="file" id="avatarInput" name="avatar" accept="image/*">
                            </label>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="form-control">
                        <label class="label px-1">
                            <span class="label-text font-bold text-gray-600">Email <span class="text-red-500">*</span></span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                                <i class="fa-solid fa-envelope"></i>
                            </span>
                            <input type="email" name="email" placeholder="email@example.com" class="input input-bordered w-full pl-11 rounded-xl input-premium" required>
                        </div>
                    </div>

                    <!-- Password Fields -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-control">
                            <label class="label px-1">
                                <span class="label-text font-bold text-gray-600">Mật khẩu <span class="text-red-500">*</span></span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                                    <i class="fa-solid fa-lock"></i>
                                </span>
                                <input type="password" name="password" placeholder="••••••••" class="input input-bordered w-full pl-11 rounded-xl input-premium" required minlength="8">
                            </div>
                        </div>
                        <div class="form-control">
                            <label class="label px-1">
                                <span class="label-text font-bold text-gray-600">Xác nhận <span class="text-red-500">*</span></span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                                    <i class="fa-solid fa-lock"></i>
                                </span>
                                <input type="password" name="password_confirm" placeholder="••••••••" class="input input-bordered w-full pl-11 rounded-xl input-premium" required minlength="8">
                            </div>
                        </div>
                    </div>

                    <!-- Terms Checkbox -->
                    <div class="form-control mt-4">
                        <label class="flex items-start gap-3 cursor-pointer p-3 rounded-xl bg-gray-50/50 border border-gray-200 hover:border-[#800000]/30 transition-colors">
                            <input type="checkbox" name="terms" class="checkbox checkbox-sm checkbox-premium mt-0.5" required>
                            <span class="text-gray-600 text-xs leading-relaxed">
                                Tôi đồng ý với 
                                <a href="/terms" class="text-[#800000] font-bold hover:underline">Điều khoản sử dụng</a> 
                                và 
                                <a href="/privacy" class="text-[#800000] font-bold hover:underline">Chính sách bảo mật</a>
                            </span>
                        </label>
                    </div>

                    <button type="submit" name="register" class="btn btn-premium w-full h-12 rounded-xl text-base font-bold mt-4">
                        <i class="fa-solid fa-user-plus mr-2"></i>
                        Đăng ký ngay
                    </button>
                </form>

                <!-- Divider -->
                <div class="relative my-6">
                    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                    <div class="relative flex justify-center text-xs uppercase"><span class="bg-transparent px-2 text-gray-400 font-bold">Hoặc</span></div>
                </div>

                <!-- Google Sign Up (Coming Soon) -->
                <div class="relative">
                    <span class="coming-soon-badge">Coming Soon</span>
                    <button type="button" class="btn btn-google w-full h-12 rounded-xl text-base font-semibold disabled text-white" disabled>
                        <svg class="w-5 h-5 mr-2" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Đăng ký với Google
                    </button>
                </div>

                <p class="text-center text-gray-500 text-sm font-medium mt-6">
                    Đã có tài khoản? 
                    <a href="/login" class="text-[#800000] font-extrabold hover:underline ml-1">Đăng nhập</a>
                </p>
            </div>
        </div>
        
        <!-- Footer Info -->
        <p class="text-center text-gray-400 text-xs mt-6">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($site_name) ?>. Tất cả quyền được bảo lưu.
        </p>
    </div>

    <script>
        // Avatar Preview
        document.getElementById('avatarInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('avatarPreview');
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Avatar Preview">';
                }
                reader.readAsDataURL(file);
            }
        });

        // Username validation (only allow a-z, A-Z, 0-9, _)
        const usernameInput = document.querySelector('input[name="username"]');
        usernameInput.addEventListener('input', function(e) {
            // Remove invalid characters in real-time
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
        });

        // Password match validation
        const password = document.querySelector('input[name="password"]');
        const passwordConfirm = document.querySelector('input[name="password_confirm"]');

        passwordConfirm.addEventListener('input', function() {
            if (password.value !== passwordConfirm.value) {
                passwordConfirm.setCustomValidity('Mật khẩu xác nhận không khớp');
            } else {
                passwordConfirm.setCustomValidity('');
            }
        });
    </script>
</body>
</html>

