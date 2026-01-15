<?php
require_once __DIR__ . '/../includes/error_handler.php';
session_start();

require_once '../config/db.php';
require_once '../config/settings.php';

$site_name = getSetting('site_name', 'DocShare');
$site_logo = getSetting('site_logo', '');
$site_desc = getSetting('site_description', 'Chia sẻ tri thức, kết nối cộng đồng');
$page_title = "Chính sách bảo mật";
include '../includes/head.php';
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

    .orb-1 { width: 500px; height: 500px; background: #800000; top: -15%; right: -15%; }
    .orb-2 { width: 400px; height: 400px; background: #4a90d9; bottom: -10%; left: -10%; }

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

    .info-box {
        background: linear-gradient(135deg, rgba(128, 0, 0, 0.05) 0%, rgba(128, 0, 0, 0.02) 100%);
        border-left: 4px solid #800000;
        padding: 1rem 1.5rem;
        border-radius: 0 1rem 1rem 0;
        margin: 1.5rem 0;
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
            <h1 class="text-3xl font-extrabold text-gray-800 mb-2">Chính Sách Bảo Mật</h1>
            <p class="text-gray-500">Cập nhật lần cuối: <?= date('d/m/Y') ?></p>
        </div>

        <!-- Content -->
        <div class="glass-card rounded-[2rem] p-8 md:p-12 content-section">
            <div class="info-box">
                <p class="text-gray-700 font-medium">
                    <i class="fa-solid fa-shield-halved text-[#800000] mr-2"></i>
                    Chúng tôi cam kết bảo vệ quyền riêng tư và dữ liệu cá nhân của bạn. Chính sách này giải thích cách chúng tôi thu thập, sử dụng và bảo vệ thông tin của bạn.
                </p>
            </div>

            <h2><i class="fa-solid fa-database"></i> 1. Thông tin chúng tôi thu thập</h2>
            <p><strong>Thông tin bạn cung cấp:</strong></p>
            <ul>
                <li>Tên đăng nhập, email, mật khẩu (được mã hóa)</li>
                <li>Ảnh đại diện (avatar)</li>
                <li>Tài liệu bạn tải lên</li>
                <li>Nội dung bình luận, đánh giá</li>
            </ul>
            
            <p><strong>Thông tin tự động thu thập:</strong></p>
            <ul>
                <li>Địa chỉ IP, loại trình duyệt, thiết bị</li>
                <li>Thời gian và trang bạn truy cập</li>
                <li>Cookies và dữ liệu phiên làm việc</li>
            </ul>

            <h2><i class="fa-solid fa-bullseye"></i> 2. Mục đích sử dụng thông tin</h2>
            <ul>
                <li>Tạo và quản lý tài khoản của bạn</li>
                <li>Cung cấp và cải thiện dịch vụ</li>
                <li>Xử lý giao dịch điểm, Premium</li>
                <li>Gửi thông báo quan trọng về dịch vụ</li>
                <li>Ngăn chặn gian lận và lạm dụng</li>
                <li>Phân tích và cải thiện trải nghiệm người dùng</li>
                <li>Tuân thủ yêu cầu pháp lý</li>
            </ul>

            <h2><i class="fa-solid fa-share-nodes"></i> 3. Chia sẻ thông tin</h2>
            <p>Chúng tôi <strong>KHÔNG</strong> bán thông tin cá nhân của bạn cho bên thứ ba. Thông tin chỉ được chia sẻ trong các trường hợp:</p>
            <ul>
                <li>Khi bạn đồng ý cho phép</li>
                <li>Với các nhà cung cấp dịch vụ hỗ trợ vận hành (hosting, email...)</li>
                <li>Khi có yêu cầu từ cơ quan pháp luật</li>
                <li>Để bảo vệ quyền lợi và an toàn của người dùng</li>
            </ul>

            <h2><i class="fa-solid fa-cookie-bite"></i> 4. Cookies</h2>
            <p>Chúng tôi sử dụng cookies để:</p>
            <ul>
                <li>Duy trì phiên đăng nhập của bạn</li>
                <li>Ghi nhớ tùy chọn cá nhân (theme, ngôn ngữ)</li>
                <li>Phân tích lưu lượng truy cập website</li>
                <li>Cải thiện hiệu suất và trải nghiệm</li>
            </ul>
            <p>Bạn có thể tắt cookies trong cài đặt trình duyệt, nhưng một số tính năng có thể không hoạt động.</p>

            <h2><i class="fa-solid fa-lock"></i> 5. Bảo mật dữ liệu</h2>
            <ul>
                <li>Mật khẩu được mã hóa bằng thuật toán bcrypt</li>
                <li>Kết nối được bảo vệ bằng HTTPS/SSL</li>
                <li>Dữ liệu được sao lưu định kỳ</li>
                <li>Hạn chế quyền truy cập vào dữ liệu nhạy cảm</li>
                <li>Giám sát và phát hiện các hoạt động bất thường</li>
            </ul>

            <h2><i class="fa-solid fa-user-gear"></i> 6. Quyền của bạn</h2>
            <p>Bạn có quyền:</p>
            <ul>
                <li><strong>Truy cập:</strong> Xem thông tin cá nhân của bạn</li>
                <li><strong>Chỉnh sửa:</strong> Cập nhật thông tin trong hồ sơ</li>
                <li><strong>Xóa:</strong> Yêu cầu xóa tài khoản và dữ liệu</li>
                <li><strong>Tải xuống:</strong> Yêu cầu bản sao dữ liệu của bạn</li>
                <li><strong>Từ chối:</strong> Hủy đăng ký nhận email marketing</li>
            </ul>

            <h2><i class="fa-solid fa-child"></i> 7. Bảo vệ trẻ em</h2>
            <p>
                Dịch vụ của chúng tôi không dành cho trẻ em dưới 13 tuổi. Chúng tôi không cố ý thu thập thông tin từ trẻ em. Nếu phát hiện tài khoản của trẻ dưới 13 tuổi, chúng tôi sẽ xóa ngay lập tức.
            </p>

            <h2><i class="fa-solid fa-clock-rotate-left"></i> 8. Lưu trữ dữ liệu</h2>
            <ul>
                <li>Thông tin tài khoản: Lưu trữ cho đến khi bạn yêu cầu xóa</li>
                <li>Tài liệu đã tải lên: Lưu trữ vĩnh viễn hoặc cho đến khi bạn xóa</li>
                <li>Logs hệ thống: Lưu trữ tối đa 90 ngày</li>
                <li>Sau khi xóa tài khoản: Dữ liệu được xóa trong vòng 30 ngày</li>
            </ul>

            <h2><i class="fa-solid fa-globe"></i> 9. Chuyển dữ liệu quốc tế</h2>
            <p>
                Dữ liệu của bạn có thể được lưu trữ trên máy chủ ở các quốc gia khác. Chúng tôi đảm bảo các biện pháp bảo vệ phù hợp được áp dụng theo quy định pháp luật.
            </p>

            <h2><i class="fa-solid fa-rotate"></i> 10. Cập nhật chính sách</h2>
            <p>
                Chúng tôi có thể cập nhật Chính sách bảo mật này theo thời gian. Khi có thay đổi quan trọng, chúng tôi sẽ thông báo qua email hoặc thông báo trên website. Ngày cập nhật sẽ được ghi ở đầu trang.
            </p>

            <h2><i class="fa-solid fa-headset"></i> 11. Liên hệ</h2>
            <p>
                Nếu bạn có câu hỏi về Chính sách bảo mật hoặc muốn thực hiện các quyền của mình, vui lòng liên hệ:
            </p>
            <ul>
                <li>Email: support@<?= strtolower(str_replace(' ', '', $site_name)) ?>.com</li>
                <li>Hoặc thông qua các kênh hỗ trợ chính thức trên website</li>
            </ul>

            <!-- Actions -->
            <div class="mt-10 pt-8 border-t border-gray-200 flex flex-wrap gap-4 justify-center">
                <a href="/dashboard" class="btn bg-[#800000] hover:bg-[#a00000] text-white border-none rounded-xl px-6">
                    <i class="fa-solid fa-home mr-2"></i> Về trang chủ
                </a>
                <a href="/terms" class="btn btn-ghost rounded-xl px-6">
                    <i class="fa-solid fa-file-contract mr-2"></i> Điều khoản sử dụng
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

