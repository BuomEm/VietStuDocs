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

$page_title = "Chỉnh sửa hồ sơ Gia sư";

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

// Check if there's a pending update
$pdo = getTutorDBConnection();
$stmt = $pdo->prepare("SELECT * FROM tutor_profile_updates WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending = $stmt->fetch();

require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="drawer-content flex flex-col">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <main class="flex-1 p-6 bg-base-200/50">
        <div class="max-w-3xl mx-auto">
            <a href="/tutors/dashboard.php" class="btn btn-ghost btn-sm mb-4 gap-2">
                <i class="fa-solid fa-arrow-left"></i> Quay lại Dashboard
            </a>

            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title text-2xl font-black mb-1">Cấu hình hồ sơ Gia sư</h2>
                    <p class="text-base-content/60 text-sm mb-6">Mọi thay đổi cần được Admin phê duyệt trước khi hiển thị công khai.</p>

                    <?php if($pending): ?>
                        <div class="alert alert-warning mb-6 py-3">
                            <i class="fa-solid fa-clock"></i>
                            <div class="text-xs">
                                <strong>Bạn có một yêu cầu đang chờ duyệt!</strong><br>
                                Gửi lúc: <?= date('d/m/Y H:i', strtotime($pending['created_at'])) ?>. Yêu cầu mới sẽ thay thế yêu cầu cũ.
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if(isset($_SESSION['flash_message'])): ?>
                        <div class="alert alert-<?= $_SESSION['flash_type'] ?> mb-6">
                            <span><?= $_SESSION['flash_message'] ?></span>
                        </div>
                        <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <div class="form-control">
                            <label class="label"><span class="label-text font-bold">Môn học hỗ trợ</span></label>
                            <input type="text" name="subjects" value="<?= htmlspecialchars($tutor['subjects']) ?>" class="input input-bordered w-full" placeholder="Ví dụ: Toán Cao Cấp, Lập trình C++, Kinh tế vĩ mô..." required>
                            <label class="label"><span class="label-text-alt opacity-60">Ngăn cách các môn bằng dấu phẩy</span></label>
                        </div>

                        <div class="form-control">
                            <label class="label"><span class="label-text font-bold">Giới thiệu bản thân & Kinh nghiệm</span></label>
                            <textarea name="bio" class="textarea textarea-bordered h-32 w-full" placeholder="Hãy viết gì đó ấn tượng để học viên tin tưởng bạn..." required><?= htmlspecialchars($tutor['bio']) ?></textarea>
                        </div>

                        <div class="bg-base-200/50 p-6 rounded-2xl border border-base-300">
                            <h3 class="font-bold mb-4 flex items-center gap-2"><i class="fa-solid fa-coins text-warning"></i> Cấu hình phí dịch vụ (Points)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="form-control">
                                    <label class="label"><span class="label-text text-xs uppercase font-bold">Gói Cơ Bản</span></label>
                                    <div class="join">
                                        <input type="number" name="price_basic" value="<?= $tutor['price_basic'] ?>" class="input input-bordered join-item w-full" required>
                                        <span class="btn btn-disabled join-item">pts</span>
                                    </div>
                                </div>
                                <div class="form-control">
                                    <label class="label"><span class="label-text text-xs uppercase font-bold">Gói Tiêu Chuẩn</span></label>
                                    <div class="join">
                                        <input type="number" name="price_standard" value="<?= $tutor['price_standard'] ?>" class="input input-bordered join-item w-full" required>
                                        <span class="btn btn-disabled join-item">pts</span>
                                    </div>
                                </div>
                                <div class="form-control">
                                    <label class="label"><span class="label-text text-xs uppercase font-bold">Gói Cao Cấp</span></label>
                                    <div class="join">
                                        <input type="number" name="price_premium" value="<?= $tutor['price_premium'] ?>" class="input input-bordered join-item w-full" required>
                                        <span class="btn btn-disabled join-item">pts</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-actions justify-end mt-8">
                            <button type="submit" class="btn btn-primary px-10">Gửi yêu cầu phê duyệt</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>
</div>
</body>
</html>
