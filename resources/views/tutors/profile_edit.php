<?php
session_start();
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/tutor.php';

redirectIfNotLoggedIn();
$user_id = getCurrentUserId();
$tutor = getTutorProfile($user_id);

if (!$tutor) {
    header("Location: /tutors/dashboard.php");
    exit;
}

$page_title = "Cấu hình hồ sơ Gia sư";

// Check if there's a pending update
$pdo = getTutorDBConnection();
$stmt = $pdo->prepare("SELECT * FROM tutor_profile_updates WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'subjects' => $_POST['subjects'] ?? '',
        'bio' => $_POST['bio'] ?? '',
        'price_basic' => (int)($_POST['price_basic'] ?? 0),
        'price_standard' => (int)($_POST['price_standard'] ?? 0),
        'price_premium' => (int)($_POST['price_premium'] ?? 0)
    ];
    
    $res = requestTutorProfileUpdate($user_id, $data);
    $_SESSION['flash_message'] = $res['message'];
    $_SESSION['flash_type'] = $res['success'] ? 'success' : 'error';
    if ($res['success']) {
        header("Location: /tutors/dashboard.php");
        exit;
    }
}

require_once __DIR__ . '/../includes/head.php';
?>

<style>
    :root {
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.2);
        --vsd-red: #991b1b;
        --red-gradient: linear-gradient(135deg, #991b1b 0%, #7f1d1d 100%);
    }
    
    [data-theme="dark"] {
        --glass-bg: rgba(15, 23, 42, 0.7);
        --glass-border: rgba(255, 255, 255, 0.1);
    }

    .edit-profile-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 24px;
    }

    /* Glass Card Style */
    .glass-card-vsd {
        background: var(--glass-bg);
        backdrop-filter: blur(30px);
        -webkit-backdrop-filter: blur(30px);
        border: 1px solid var(--glass-border);
        border-radius: 3rem;
        padding: 60px;
        box-shadow: 0 40px 80px -20px rgba(0, 0, 0, 0.1);
    }

    .vsd-form-label {
        font-size: 10px;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        opacity: 0.4;
        margin-bottom: 12px;
        display: block;
    }

    .vsd-input {
        background: oklch(var(--b2) / 0.5) !important;
        border: 1px solid oklch(var(--bc) / 0.05) !important;
        border-radius: 1.5rem !important;
        font-weight: 700 !important;
        padding: 0 24px !important;
        transition: all 0.3s ease !important;
    }

    .vsd-textarea {
        background: oklch(var(--b2) / 0.5) !important;
        border: 1px solid oklch(var(--bc) / 0.05) !important;
        border-radius: 1.5rem !important;
        padding: 24px !important;
        font-weight: 600 !important;
        transition: all 0.3s ease !important;
    }

    .vsd-input:focus, .vsd-textarea:focus {
        border-color: var(--vsd-red) !important;
        box-shadow: 0 0 0 4px rgba(153, 27, 27, 0.1) !important;
    }

    .pricing-grid-vsd {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        background: oklch(var(--bc) / 0.03);
        padding: 32px;
        border-radius: 2rem;
        margin: 32px 0;
    }

    .btn-save-vsd {
        background: var(--vsd-red);
        color: white;
        height: 72px;
        padding: 0 48px;
        border-radius: 1.75rem;
        font-weight: 1000;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        font-size: 0.9rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
        box-shadow: 0 20px 40px -10px rgba(153, 27, 27, 0.4);
    }

    .btn-save-vsd:hover {
        transform: translateY(-4px);
        filter: brightness(1.1);
        box-shadow: 0 30px 60px -15px rgba(153, 27, 27, 0.5);
    }

    .alert-warning-vsd {
        background: rgba(251, 191, 36, 0.1);
        border: 1px solid rgba(251, 191, 36, 0.2);
        border-radius: 1.5rem;
        padding: 24px;
        display: flex;
        gap: 16px;
        align-items: center;
        color: #92400e;
        font-weight: 800;
        font-size: 0.85rem;
        margin-bottom: 40px;
    }

    .page-header-vsd {
        margin-bottom: 48px;
    }

    .page-header-vsd h1 {
        font-size: 2.5rem;
        font-weight: 1000;
        letter-spacing: -0.05em;
        margin-bottom: 8px;
    }

    .page-header-vsd p {
        font-size: 1rem;
        font-weight: 600;
        opacity: 0.5;
    }

</style>

<body class="bg-base-100">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <div class="drawer-content flex flex-col min-h-screen">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>
        
        <main class="flex-1">
            <div class="edit-profile-container">
                
                <div class="mb-10 animate-in fade-in slide-in-from-left duration-500">
                    <a href="/tutors/dashboard.php" class="btn btn-ghost rounded-2xl gap-3 font-black text-[10px] tracking-widest uppercase opacity-40 hover:opacity-100">
                        <i class="fa-solid fa-arrow-left"></i> Quay lại bảng điều khiển
                    </a>
                </div>

                <div class="max-w-3xl mx-auto">
                    <div class="glass-card-vsd animate-in fade-in slide-in-from-bottom duration-700">
                        
                        <div class="page-header-vsd">
                            <h1>Cấu hình hồ sơ</h1>
                            <p>Cập nhật thông tin chuyên môn và biểu phí hỗ trợ của bạn.</p>
                        </div>

                        <?php if($pending): ?>
                            <div class="alert-warning-vsd animate-pulse">
                                <i class="fa-solid fa-clock-rotate-left text-2xl"></i>
                                <div>
                                    <h4 class="font-black uppercase text-[10px] tracking-widest mb-1">Đang chờ phê duyệt</h4>
                                    <p class="opacity-80">Bạn có một yêu cầu thay đổi gửi lúc <?= date('d/m/Y H:i', strtotime($pending['created_at'])) ?> đang được Admin xem xét.</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if(isset($_SESSION['flash_message'])): ?>
                            <div class="alert alert-<?= $_SESSION['flash_type'] ?> mb-10 rounded-2xl font-black text-sm">
                                <span><i class="fa-solid fa-circle-info mr-2"></i> <?= $_SESSION['flash_message'] ?></span>
                            </div>
                            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
                        <?php endif; ?>

                        <form method="POST" class="space-y-10">
                            <div class="form-control">
                                <label class="vsd-form-label">Môn học bạn hỗ trợ</label>
                                <input type="text" name="subjects" value="<?= htmlspecialchars($tutor['subjects']) ?>" required placeholder="Ví dụ: Toán Cao Cấp, Lập trình C++, Kinh tế vĩ mô..." class="input vsd-input h-16 w-full" />
                                <p class="text-[9px] font-black uppercase tracking-tight opacity-30 mt-3 pl-1">Phân cách các môn bằng dấu phẩy (,)</p>
                            </div>

                            <div class="form-control">
                                <label class="vsd-form-label">Giới thiệu bản thân & Kinh nghiệm</label>
                                <textarea name="bio" required class="textarea vsd-textarea h-48 w-full" placeholder="Kể về trình độ, kinh nghiệm và phong cách hỗ trợ của bạn để học viên tin tưởng..."><?= htmlspecialchars($tutor['bio']) ?></textarea>
                            </div>

                            <div class="space-y-4">
                                <label class="vsd-form-label">Cấu hình biểu phí Points mới</label>
                                <div class="pricing-grid-vsd">
                                    <div class="form-control">
                                        <label class="text-[9px] font-black uppercase opacity-40 mb-3 text-center">Gói Basic</label>
                                        <div class="relative">
                                            <input type="number" name="price_basic" value="<?= $tutor['price_basic'] ?>" min="10" class="input vsd-input h-14 w-full text-center pr-12" required />
                                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-black opacity-30">VSD</span>
                                        </div>
                                    </div>
                                    <div class="form-control">
                                        <label class="text-[9px] font-black uppercase opacity-40 mb-3 text-center">Standard</label>
                                        <div class="relative">
                                            <input type="number" name="price_standard" value="<?= $tutor['price_standard'] ?>" min="20" class="input vsd-input h-14 w-full text-center pr-12" required />
                                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-black opacity-30">VSD</span>
                                        </div>
                                    </div>
                                    <div class="form-control">
                                        <label class="text-[9px] font-black uppercase opacity-40 mb-3 text-center">Premium</label>
                                        <div class="relative">
                                            <input type="number" name="price_premium" value="<?= $tutor['price_premium'] ?>" min="50" class="input vsd-input h-14 w-full text-center pr-12" required />
                                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-[10px] font-black opacity-30">VSD</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-6 border-t border-base-content/5 flex justify-end">
                                <button type="submit" class="btn-save-vsd">Lưu & Gửi phê duyệt</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </main>
        
        <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    </div>
</body>
</html>
