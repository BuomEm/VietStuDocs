<?php
require_once __DIR__ . '/includes/error_handler.php';
session_start();

require_once 'config/db.php';
require_once 'config/settings.php';

$site_name = getSetting('site_name', 'DocShare');
$site_logo = getSetting('site_logo', '');
$site_desc = getSetting('site_description', 'Chia sẻ tri thức, kết nối cộng đồng');
$page_title = "Điều khoản sử dụng";
include 'includes/head.php';
?>

<style>
    body {
        background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%) !important;
        min-height: 100vh;
    }

    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.5);
        box-shadow: 0 25px 50px -12px rgba(128, 0, 0, 0.08);
    }

    .orb {
        position: fixed;
        border-radius: 50%;
        filter: blur(100px);
        z-index: 0;
        opacity: 0.2;
        pointer-events: none;
    }

    .orb-1 { width: 500px; height: 500px; background: #800000; top: -15%; left: -15%; }
    .orb-2 { width: 400px; height: 400px; background: #ffcc00; bottom: -10%; right: -10%; }

    .content-section h2 {
        color: #800000;
        font-weight: 800;
        font-size: 1.25rem;
        margin-top: 2rem;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .content-section h2:first-child {
        margin-top: 0;
    }

    .content-section p, .content-section li {
        color: #4a5568;
        line-height: 1.8;
    }

    .content-section ul {
        list-style: none;
        padding-left: 0;
    }

    .content-section ul li {
        position: relative;
        padding-left: 1.5rem;
        margin-bottom: 0.5rem;
    }

    .content-section ul li::before {
        content: "•";
        color: #800000;
        font-weight: bold;
        position: absolute;
        left: 0;
    }
</style>

<div class="p-4 py-8">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="w-full max-w-4xl mx-auto relative z-10">
        <!-- Header -->
        <div class="text-center mb-8">
            <a href="/dashboard" class="inline-flex items-center gap-3 mb-4 hover:opacity-80 transition-opacity">
                <?php if(!empty($site_logo)): ?>
                    <img src="<?= htmlspecialchars($site_logo) ?>" alt="Logo" class="w-12 h-12 object-contain">
                <?php else: ?>
                    <div class="w-12 h-12 bg-white rounded-2xl shadow-lg flex items-center justify-center">
                        <i class="fa-solid fa-file-contract text-xl text-[#800000]"></i>
                    </div>
                <?php endif; ?>
                <span class="text-2xl font-extrabold text-[#800000]"><?= htmlspecialchars($site_name) ?></span>
            </a>
            <h1 class="text-3xl font-extrabold text-gray-800 mb-2">Điều Khoản Sử Dụng</h1>
            <p class="text-gray-500">Cập nhật lần cuối: <?= date('d/m/Y') ?></p>
        </div>

        <!-- Content -->
        <div class="glass-card rounded-[2rem] p-8 md:p-12 content-section">
            <p class="text-gray-600 mb-6">
                Chào mừng bạn đến với <strong><?= htmlspecialchars($site_name) ?></strong>! Bằng việc sử dụng dịch vụ của chúng tôi, bạn đồng ý tuân thủ các điều khoản sau đây. Vui lòng đọc kỹ trước khi sử dụng.
            </p>

            <h2><i class="fa-solid fa-user-check"></i> 1. Điều kiện sử dụng</h2>
            <ul>
                <li>Bạn phải từ 13 tuổi trở lên để đăng ký tài khoản</li>
                <li>Thông tin đăng ký phải chính xác và trung thực</li>
                <li>Mỗi người chỉ được sở hữu một tài khoản</li>
                <li>Bạn chịu trách nhiệm bảo mật thông tin tài khoản của mình</li>
            </ul>

            <h2><i class="fa-solid fa-file-arrow-up"></i> 2. Quy định về tải lên tài liệu</h2>
            <ul>
                <li>Chỉ tải lên tài liệu mà bạn có quyền sở hữu hoặc được phép chia sẻ</li>
                <li>Nghiêm cấm tải lên nội dung vi phạm bản quyền, pháp luật</li>
                <li>Không được tải lên nội dung khiêu dâm, bạo lực, thù hận</li>
                <li>Tài liệu phải liên quan đến học tập, nghiên cứu, kiến thức</li>
                <li>Quản trị viên có quyền từ chối hoặc gỡ bỏ tài liệu vi phạm</li>
            </ul>

            <h2><i class="fa-solid fa-coins"></i> 3. Hệ thống điểm</h2>
            <ul>
                <li>Điểm được sử dụng để mua và tải xuống tài liệu</li>
                <li>Bạn nhận điểm khi tài liệu của bạn được người khác mua</li>
                <li>Điểm không có giá trị quy đổi thành tiền mặt</li>
                <li>Chúng tôi có quyền điều chỉnh số điểm nếu phát hiện gian lận</li>
            </ul>

            <h2><i class="fa-solid fa-crown"></i> 4. Tài khoản Premium</h2>
            <ul>
                <li>Premium mang lại các quyền lợi đặc biệt như tốc độ tải cao hơn</li>
                <li>Phí Premium không hoàn lại sau khi đã kích hoạt</li>
                <li>Quyền lợi Premium có thể thay đổi theo thời gian</li>
            </ul>

            <h2><i class="fa-solid fa-ban"></i> 5. Hành vi bị cấm</h2>
            <ul>
                <li>Sử dụng bot, script tự động để truy cập dịch vụ</li>
                <li>Cố gắng xâm nhập, phá hoại hệ thống</li>
                <li>Chia sẻ tài khoản cho người khác sử dụng</li>
                <li>Spam, quấy rối người dùng khác</li>
                <li>Tạo nhiều tài khoản để lạm dụng hệ thống</li>
            </ul>

            <h2><i class="fa-solid fa-gavel"></i> 6. Xử lý vi phạm</h2>
            <ul>
                <li>Vi phạm nhẹ: Cảnh cáo, khóa tính năng tạm thời</li>
                <li>Vi phạm nghiêm trọng: Khóa tài khoản vĩnh viễn</li>
                <li>Vi phạm pháp luật: Báo cáo cơ quan chức năng</li>
            </ul>

            <h2><i class="fa-solid fa-shield-halved"></i> 7. Miễn trừ trách nhiệm</h2>
            <ul>
                <li>Chúng tôi không chịu trách nhiệm về độ chính xác của tài liệu do người dùng tải lên</li>
                <li>Không đảm bảo dịch vụ hoạt động liên tục 24/7</li>
                <li>Không chịu trách nhiệm về thiệt hại do sử dụng tài liệu trên nền tảng</li>
            </ul>

            <h2><i class="fa-solid fa-rotate"></i> 8. Thay đổi điều khoản</h2>
            <p>
                Chúng tôi có quyền thay đổi điều khoản này bất cứ lúc nào. Người dùng sẽ được thông báo qua email hoặc thông báo trên website khi có thay đổi quan trọng. Việc tiếp tục sử dụng dịch vụ sau khi thay đổi đồng nghĩa với việc bạn chấp nhận điều khoản mới.
            </p>

            <h2><i class="fa-solid fa-envelope"></i> 9. Liên hệ</h2>
            <p>
                Nếu bạn có bất kỳ câu hỏi nào về Điều khoản sử dụng, vui lòng liên hệ với chúng tôi qua email hoặc các kênh hỗ trợ chính thức.
            </p>

            <!-- Actions -->
            <div class="mt-10 pt-8 border-t border-gray-200 flex flex-wrap gap-4 justify-center">
                <a href="/dashboard" class="btn bg-[#800000] hover:bg-[#a00000] text-white border-none rounded-xl px-6">
                    <i class="fa-solid fa-home mr-2"></i> Về trang chủ
                </a>
                <a href="/privacy" class="btn btn-ghost rounded-xl px-6">
                    <i class="fa-solid fa-shield mr-2"></i> Chính sách bảo mật
                </a>
                <a href="/signup" class="btn btn-ghost rounded-xl px-6">
                    <i class="fa-solid fa-user-plus mr-2"></i> Đăng ký
                </a>
            </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-gray-400 text-sm mt-8">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($site_name) ?>. Tất cả quyền được bảo lưu.
        </p>
    </div>
</body>
</html>

