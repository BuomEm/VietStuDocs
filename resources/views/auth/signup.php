<?php
// Giao diện Đăng ký
$site_name = getSetting('site_name', 'DocShare');
$site_logo = getSetting('site_logo', '');
?>
<!DOCTYPE html>
<html lang="vi" data-theme="vietstudocs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - <?= htmlspecialchars($site_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <style>
        body { font-family: 'Outfit', sans-serif; background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%); }
        .glass-card { background: rgba(255, 255, 255, 0.82); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.5); }
        .orb { position: fixed; border-radius: 50%; filter: blur(100px); opacity: 0.3; }
        .orb-1 { width: 500px; height: 500px; background: #800000; top: -15%; left: -15%; }
        .avatar-upload-small { width: 56px; height: 56px; position: relative; cursor: pointer; }
        .avatar-preview-small { width: 100%; height: 100%; border-radius: 14px; border: 2px dashed #800000; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        .avatar-preview-small img { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="orb orb-1"></div>

    <div class="w-full max-w-lg relative z-10">
        <div class="text-center mb-6">
            <h1 class="text-3xl font-extrabold text-[#800000]"><?= htmlspecialchars($site_name) ?></h1>
        </div>

        <div class="glass-card rounded-[2.5rem] p-8 md:p-10">
            <h2 class="text-2xl font-bold mb-6 text-center">Tạo tài khoản mới</h2>

            <form method="POST" action="/signup" enctype="multipart/form-data" class="space-y-4">
                <div class="form-control">
                    <label class="label"><span class="label-text font-bold">Tên đăng nhập</span></label>
                    <div class="flex gap-3 items-center">
                        <input type="text" name="username" class="input input-bordered w-full rounded-xl" required>
                        <label for="avatarInput" class="avatar-upload-small">
                            <div class="avatar-preview-small" id="avatarPreview"><i class="fa-solid fa-user text-xl opacity-20"></i></div>
                            <input type="file" id="avatarInput" name="avatar" class="hidden" accept="image/*" required>
                        </label>
                    </div>
                </div>

                <div class="form-control">
                    <label class="label"><span class="label-text font-bold">Email</span></label>
                    <input type="email" name="email" class="input input-bordered rounded-xl" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">Mật khẩu</span></label>
                        <input type="password" name="password" class="input input-bordered rounded-xl" required minlength="8">
                    </div>
                    <div class="form-control">
                        <label class="label"><span class="label-text font-bold">Xác nhận</span></label>
                        <input type="password" name="password_confirm" class="input input-bordered rounded-xl" required minlength="8">
                    </div>
                </div>

                <button type="submit" name="register" class="btn bg-[#800000] text-white w-full rounded-xl mt-4">Đăng ký ngay</button>
            </form>

            <p class="text-center mt-6 text-sm">
                Đã có tài khoản? <a href="/login" class="text-[#800000] font-bold">Đăng nhập</a>
            </p>
        </div>
    </div>

    <script>
        document.getElementById('avatarInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').innerHTML = '<img src="' + e.target.result + '">';
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>

