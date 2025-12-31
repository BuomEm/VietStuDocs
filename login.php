<?php
require_once __DIR__ . '/includes/error_handler.php';
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard");
    exit();
}

require_once 'config/db.php';
require_once 'config/auth.php';

$error = '';
$success = '';

// Handle Registration
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    if($_POST['password'] !== $_POST['password_confirm']) {
        $error = "Mật khẩu xác nhận không khớp!";
    } else {
        $result = registerUser($_POST['username'], $_POST['email'], $_POST['password']);
        if($result['success']) {
            $success = "Đăng ký thành công! Hãy đăng nhập.";
        } else {
            $error = $result['message'];
        }
    }
}

// Handle Login
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    if(loginUser($email, $password)) {
        header("Location: dashboard");
        exit();
    } else {
        $error = "Email hoặc mật khẩu không chính xác";
    }
}
?>
<!DOCTYPE html>
<html lang="vi" data-theme="vietstudocs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - DocShare</title>
    
    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS & DaisyUI -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
            overflow-x: hidden;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 25px 50px -12px rgba(128, 0, 0, 0.12);
        }

        .orb {
            position: fixed; /* Fixed to prevent pushing the body height */
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

        .hidden-form {
            display: none;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center relative p-4">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="w-full max-w-md relative z-10">
        <!-- Logo Section -->
        <div class="text-center mb-8">
            <div class="flex items-center justify-center gap-4 mb-3">
                <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 bg-white rounded-2xl shadow-xl rotate-3 border border-white">
                    <i class="fa-solid fa-file-contract text-2xl" style="color: #800000;"></i>
                </div>
                <h1 class="text-4xl font-extrabold text-[#800000] tracking-tight">DocShare</h1>
            </div>
            <p class="text-gray-500 font-medium">Chia sẻ tri thức, kết nối cộng đồng</p>
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
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <div id="login-section" class="form-fade">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Chào mừng trở lại!</h2>
                <form method="POST" action="login" class="space-y-5">
                    <div class="form-control">
                        <label class="label px-1">
                            <span class="label-text font-bold text-gray-600">Email</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                                <i class="fa-solid fa-envelope"></i>
                            </span>
                            <input type="email" name="email" placeholder="email@example.com" class="input input-bordered w-full pl-11 rounded-xl input-premium" required>
                        </div>
                    </div>

                    <div class="form-control">
                        <label class="label px-1">
                            <span class="label-text font-bold text-gray-600">Mật khẩu</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <input type="password" name="password" placeholder="••••••••" class="input input-bordered w-full pl-11 rounded-xl input-premium" required>
                        </div>
                        <div class="text-right mt-2">
                            <a href="#" class="text-xs font-bold text-[#800000] hover:underline">Quên mật khẩu?</a>
                        </div>
                    </div>

                    <button type="submit" name="login" class="btn btn-premium w-full h-12 rounded-xl text-base font-bold mt-4">
                        Đăng nhập ngay
                    </button>
                </form>

                <div class="relative my-8">
                    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
                    <div class="relative flex justify-center text-xs uppercase"><span class="bg-transparent px-2 text-gray-400 font-bold">Hoặc</span></div>
                </div>

                <p class="text-center text-gray-500 text-sm font-medium">
                    Chưa có tài khoản? 
                    <button onclick="toggleAuth()" class="text-[#800000] font-extrabold hover:underline ml-1">Đăng ký miễn phí</button>
                </p>
            </div>

            <!-- Register Form -->
            <div id="register-section" class="form-fade hidden-form">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Tạo tài khoản mới</h2>
                <form method="POST" action="login" class="space-y-4">
                    <div class="form-control">
                        <label class="label px-1">
                            <span class="label-text font-bold text-gray-600">Tên người dùng</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                                <i class="fa-solid fa-user"></i>
                            </span>
                            <input type="text" name="username" placeholder="Nguyễn Văn A" class="input input-bordered w-full pl-11 rounded-xl input-premium" required>
                        </div>
                    </div>

                    <div class="form-control">
                        <label class="label px-1">
                            <span class="label-text font-bold text-gray-600">Email</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-400">
                                <i class="fa-solid fa-envelope"></i>
                            </span>
                            <input type="email" name="email" placeholder="email@example.com" class="input input-bordered w-full pl-11 rounded-xl input-premium" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="form-control">
                            <label class="label px-1">
                                <span class="label-text font-bold text-gray-600">Mật khẩu</span>
                            </label>
                            <input type="password" name="password" placeholder="••••••••" class="input input-bordered w-full rounded-xl input-premium" required>
                        </div>
                        <div class="form-control">
                            <label class="label px-1">
                                <span class="label-text font-bold text-gray-600">Xác nhận</span>
                            </label>
                            <input type="password" name="password_confirm" placeholder="••••••••" class="input input-bordered w-full rounded-xl input-premium" required>
                        </div>
                    </div>

                    <button type="submit" name="register" class="btn btn-premium w-full h-12 rounded-xl text-base font-bold mt-6">
                        Tham gia ngay
                    </button>
                </form>

                <p class="text-center text-gray-500 text-sm font-medium mt-8">
                    Đã có tài khoản? 
                    <button onclick="toggleAuth()" class="text-[#800000] font-extrabold hover:underline ml-1">Đăng nhập</button>
                </p>
            </div>
        </div>
        
        <!-- Footer Info -->
        <p class="text-center text-gray-400 text-xs mt-8">
            &copy; <?= date('Y') ?> DocShare. Tất cả quyền được bảo lưu.
        </p>
    </div>

    <script>
        function toggleAuth() {
            const loginSection = document.getElementById('login-section');
            const registerSection = document.getElementById('register-section');
            
            if (loginSection.classList.contains('hidden-form')) {
                loginSection.classList.remove('hidden-form');
                registerSection.classList.add('hidden-form');
            } else {
                loginSection.classList.add('hidden-form');
                registerSection.classList.remove('hidden-form');
            }
        }
    </script>
</body>
</html>
