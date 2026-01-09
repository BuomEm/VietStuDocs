<?php
// Giao diện Đăng nhập
$site_name = getSetting('site_name', 'DocShare');
$site_logo = getSetting('site_logo', '');
$site_desc = getSetting('site_description', 'Chia sẻ tri thức');
?>
<!DOCTYPE html>
<html lang="vi" data-theme="vietstudocs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - <?= htmlspecialchars($site_name) ?></title>
    <link rel="icon" href="<?= htmlspecialchars(!empty($site_logo) ? $site_logo : '/favicon.ico') ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <style>
        /* Paste CSS cũ từ login.php */
        body { font-family: 'Outfit', sans-serif; background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%); }
        .glass-card { background: rgba(255, 255, 255, 0.82); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.5); }
        .orb { position: fixed; border-radius: 50%; filter: blur(100px); z-index: 0; opacity: 0.3; }
        .orb-1 { width: 500px; height: 500px; background: #800000; top: -15%; left: -15%; }
        .orb-2 { width: 400px; height: 400px; background: #ffcc00; bottom: -10%; right: -10%; }
        .btn-premium { background: #800000; color: white; border: none; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center relative p-4">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="w-full max-w-md relative z-10">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-extrabold text-[#800000]"><?= htmlspecialchars($site_name) ?></h1>
            <p class="text-gray-500"><?= htmlspecialchars($site_desc) ?></p>
        </div>

        <div class="glass-card rounded-[2.5rem] p-8">
            <?php if($error): ?>
                <div class="alert alert-error mb-6 rounded-2xl">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="/login" class="space-y-5">
                <div class="form-control">
                    <label class="label"><span class="label-text font-bold">Email</span></label>
                    <input type="email" name="email" class="input input-bordered rounded-xl" required>
                </div>
                <div class="form-control">
                    <label class="label"><span class="label-text font-bold">Mật khẩu</span></label>
                    <input type="password" name="password" class="input input-bordered rounded-xl" required>
                </div>
                <button type="submit" name="login" class="btn btn-premium w-full rounded-xl font-bold mt-4">
                    Đăng nhập ngay
                </button>
            </form>

            <p class="text-center mt-8 text-sm">
                Chưa có tài khoản? <a href="/signup" class="text-[#800000] font-bold">Đăng ký ngay</a>
            </p>
        </div>
    </div>
</body>
</html>

