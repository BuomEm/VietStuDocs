<?php
$error_code = $_GET['code'] ?? 404;
$valid_codes = [
    400 => ['code' => '400', 'title' => 'Yêu cầu không hợp lệ', 'desc' => 'Máy chủ không thể xử lý yêu cầu.'],
    401 => ['code' => '401', 'title' => 'Chưa xác thực', 'desc' => 'Vui lòng đăng nhập để tiếp tục.'],
    403 => ['code' => '403', 'title' => 'Quyền truy cập bị từ chối', 'desc' => 'Bạn không có quyền truy cập trang này.'],
    404 => ['code' => '404', 'title' => 'Không tìm thấy trang', 'desc' => 'URL được yêu cầu không tồn tại trên máy chủ này.'],
    500 => ['code' => '500', 'title' => 'Lỗi máy chủ nội bộ', 'desc' => 'Đã xảy ra lỗi trên máy chủ.'],
    'session_expired' => ['code' => 'ERR_SESSION_EXPIRED', 'title' => 'Trang này hiện không hoạt động', 'desc' => 'Phiên đăng nhập của bạn đã hết hạn hoặc không hợp lệ.']
];

$info = $valid_codes[$error_code] ?? $valid_codes[404];

// If custom message passed
if (isset($_GET['msg'])) {
    $info['desc'] = htmlspecialchars($_GET['msg']);
}

// Danh sách các câu nói truyền cảm hứng về học tập
$quotes = [
    "Học, học nữa, học mãi. - V.I. Lênin",
    "Đầu tư vào kiến thức luôn mang lại lãi suất cao nhất. - Benjamin Franklin",
    "Tri thức là sức mạnh. - Francis Bacon",
    "Học mà không suy nghĩ thì vô ích; suy nghĩ mà không học thì hiểm nghèo. - Khổng Tử",
    "Rễ của sự học tuy đắng nhưng quả của nó rất ngọt. - Aristoteles",
    "Giáo dục là vũ khí mạnh nhất để thay đổi thế giới. - Nelson Mandela",
    "Việc học không bao giờ làm trí óc mệt mỏi. - Leonardo da Vinci",
    "Thay đổi là kết quả cuối cùng của việc học tập thực sự. - Leo Buscaglia",
    "Nhân tài mà không học cũng giống như viên ngọc không được mài giũa. - Khuyết danh",
    "Đừng học để thành công, hãy học để có giá trị. - Albert Einstein"
];
$random_quote = $quotes[array_rand($quotes)];
?>
<!DOCTYPE html>
<html lang="vi" data-theme="vietstudocs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $info['title'] ?> - DocShare</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Patrick+Hand&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    
    <style>
        :root {
            --primary-maroon: #800000;
            --base-cream: #f9f6f2;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 25px 50px -12px rgba(128, 0, 0, 0.12);
        }

        .error-title-glitch {
            background: linear-gradient(to right, #800000, #ff4d4d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 2px 4px rgba(128, 0, 0, 0.1));
        }

        @keyframes float-hero {
            0%, 100% { transform: translateY(0) rotate(-2deg); }
            50% { transform: translateY(-10px) rotate(2deg); }
        }

        .hero-animation {
            animation: float-hero 6s ease-in-out infinite;
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

        .btn-premium {
            background: #800000;
            color: white;
            border: none;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .btn-premium:hover {
            background: #a00000;
            transform: translateY(-2px) scale(1.02);
            box-shadow: 0 10px 20px -5px rgba(128, 0, 0, 0.3);
        }

        .btn-outline-premium {
            border: 2px solid #800000;
            color: #800000;
            background: transparent;
            transition: all 0.3s ease;
        }

        .btn-outline-premium:hover {
            background: rgba(128, 0, 0, 0.05);
            border-color: #a00000;
            color: #a00000;
        }

        .fade-in {
            opacity: 0;
            transform: translateY(15px);
            animation: fadeInUp 0.6s ease forwards;
        }

        @keyframes fadeInUp {
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center relative p-4">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="container mx-auto px-6 relative z-10 py-10">
        <div class="glass-card max-w-2xl mx-auto rounded-[3rem] p-12 md:p-16 text-center border-white/50">
            <div class="relative mb-12 inline-block">
                <div class="hero-animation">
                    <div class="w-48 h-48 bg-white/80 backdrop-blur-md rounded-[3.5rem] shadow-2xl flex items-center justify-center rotate-3 border border-white">
                        <?php 
                        $icon = 'fa-ghost';
                        $icon_color = 'text-maroon-700';
                        if($error_code == 403) $icon = 'fa-shield-halved';
                        if($error_code == 500) $icon = 'fa-gears';
                        if($error_code == 'session_expired') $icon = 'fa-clock-rotate-left';
                        ?>
                        <i class="fa-solid <?= $icon ?> text-8xl" style="color: #800000;"></i>
                    </div>
                </div>
                <div class="absolute -top-6 -right-6 w-12 h-12 bg-yellow-400 rounded-full shadow-lg pulse-bg hidden md:block" style="animation: pulse 2s infinite"></div>
                <div class="absolute -bottom-4 -left-8 w-16 h-16 bg-maroon-100 rounded-2xl rotate-45 hidden md:block" style="background: rgba(128, 0, 0, 0.1);"></div>
            </div>

            <div class="fade-in" style="animation-delay: 0.2s">
                <span class="inline-block px-4 py-1.5 rounded-full bg-maroon-50 text-maroon-900 text-xs font-bold tracking-[0.2em] mb-4 border border-maroon-100" style="background: rgba(128, 0, 0, 0.05); color: #800000;">
                    STATUS CODE <?= $info['code'] ?>
                </span>
                
                <h1 class="font-extrabold mb-4 error-title-glitch leading-tight">
                    <?= $info['code'] == '404' ? 'Oops! Lạc đường rồi' : $info['title'] ?>
                </h1>

                <p class="text-base md:text-lg text-gray-600 mb-8 leading-relaxed max-w-md mx-auto">
                    <?= $info['desc'] ?>
                    <span class="block text-xs font-medium mt-2 text-gray-400">
                        <?php if($error_code == 'session_expired'): ?>
                            Hãy đăng nhập lại để tiếp tục hành trình của bạn nhé!
                        <?php else: ?>
                            Đừng quá lo lắng, hãy thử sử dụng các điều hướng bên dưới.
                        <?php endif; ?>
                    </span>
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-5 fade-in" style="animation-delay: 0.4s">
                <a href="javascript:location.reload()" class="btn btn-premium btn-lg w-full sm:w-auto px-10 rounded-2xl h-16 font-bold shadow-xl shadow-red-900/10">
                    <i class="fa-solid fa-arrow-rotate-right mr-2 transition-transform duration-500 group-hover:rotate-180"></i>
                    Tải lại trang
                </a>

                <?php if($error_code == 'session_expired' || $error_code == 403 || $error_code == 401): ?>
                    <a href="/login.php" class="btn btn-outline-premium btn-lg w-full sm:w-auto px-10 rounded-2xl h-16 font-bold">
                        <i class="fa-solid fa-user-lock mr-2"></i>
                        Đăng nhập ngay
                    </a>
                <?php else: ?>
                    <a href="/dashboard.php" class="btn btn-outline-premium btn-lg w-full sm:w-auto px-10 rounded-2xl h-16 font-bold">
                        <i class="fa-solid fa-house-chimney mr-2"></i>
                        Về trang chủ
                    </a>
                <?php endif; ?>
            </div>

            <div class="mt-12 pt-8 border-t border-gray-100 fade-in" style="animation-delay: 0.6s">
                <p class="text-sm text-gray-400 italic">
                    <i class="fa-solid fa-quote-left mr-2 opacity-50"></i>
                    <?= $random_quote ?>
                    <i class="fa-solid fa-quote-right ml-2 opacity-50"></i>
                </p>
            </div>
        </div>
    </div>
</body>
</html>