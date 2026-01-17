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

$site_name = getSetting('site_name', 'VietStuDocs');
$site_logo = getSetting('site_logo', '');
$site_desc = getSetting('site_description', 'Chia sẻ tri thức, kết nối cộng đồng');

$error = '';
$success = '';

// Handle Login
if($_SERVER["REQUEST_METHOD"] == "POST" && (isset($_POST['login']) || isset($_POST['email']))) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    if(loginUser($email, $password)) {
        header("Location: dashboard");
        exit();
    } else {
        $error = "Email hoặc mật khẩu không chính xác";
    }
}

$page_title = "Đăng nhập";
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

    .form-fade {
        animation: fadeIn 0.5s ease forwards;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="min-h-screen flex items-center justify-center relative p-4">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="w-full max-w-md relative z-10">
        <!-- Logo Section -->
        <div class="text-center mb-8">
            <div class="flex items-center justify-center gap-4 mb-3">
                <?php if(!empty($site_logo)): ?>
                     <img src="<?= htmlspecialchars($site_logo) ?>" alt="Logo" class="w-16 h-16 object-contain drop-shadow-lg">
                <?php else: ?>
                    <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 bg-white rounded-2xl shadow-xl rotate-3 border border-white">
                        <i class="fa-solid fa-file-contract text-2xl" style="color: #800000;"></i>
                    </div>
                <?php endif; ?>
                <h1 class="text-4xl font-extrabold text-[#800000] tracking-tight"><?= htmlspecialchars($site_name) ?></h1>
            </div>
            <p class="text-gray-500 font-medium"><?= htmlspecialchars($site_desc) ?></p>
        </div>

        <div class="glass-card rounded-[3rem] p-10">
            <!-- Alert Messages -->
            <?php if($error): ?>
                <div class="alert alert-error mb-6 rounded-2xl bg-red-50 border-red-100 text-red-800">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span class="text-sm font-semibold"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <div class="form-fade">
                <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Chào mừng trở lại!</h2>
                
                <form method="POST" action="" class="space-y-5">
                    <div class="form-control">
                        <label class="label px-1">
                            <span class="label-text font-bold text-gray-600">Email hoặc Tên đăng nhập</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                                <i class="fa-solid fa-envelope"></i>
                            </span>
                            <input type="text" name="email" placeholder="email@example.com" class="input input-bordered w-full pl-11 rounded-2xl input-premium" required>
                        </div>
                    </div>

                    <div class="form-control">
                        <label class="label px-1">
                            <span class="label-text font-bold text-gray-600">Mật khẩu</span>
                            <a href="#" class="label-text-alt text-[#800000] font-bold hover:underline">Quên mật khẩu?</a>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <input type="password" name="password" placeholder="••••••••" class="input input-bordered w-full pl-11 rounded-2xl input-premium" required>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-1">
                        <label class="label cursor-pointer gap-2">
                            <input type="checkbox" name="remember" class="checkbox checkbox-sm border-gray-300" />
                            <span class="label-text text-gray-500 text-xs">Ghi nhớ đăng nhập</span>
                        </label>
                    </div>

                    <button type="submit" name="login" class="btn btn-premium w-full h-14 rounded-2xl text-lg font-bold mt-2 shadow-lg shadow-red-900/20 hover:shadow-red-900/40">
                        <i class="fa-solid fa-right-to-bracket mr-2"></i>
                        Đăng nhập
                    </button>
                </form>

                <div class="relative my-8">
                    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-100"></div></div>
                    <div class="relative flex justify-center text-xs uppercase"><span class="bg-transparent px-4 text-gray-400 font-bold">Hoặc</span></div>
                </div>

                <a href="/signup" class="btn btn-ghost w-full h-14 rounded-2xl text-gray-600 border border-gray-100 hover:bg-gray-50 mb-4">
                    <i class="fa-solid fa-user-plus mr-2"></i>
                    Tạo tài khoản mới
                </a>
            </div>
        </div>
        
        <!-- Footer Info -->
        <p class="text-center text-gray-400 text-xs mt-8">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($site_name) ?>. Tất cả quyền được bảo lưu.
        </p>
    </div>
</div>
</body>
</html>
