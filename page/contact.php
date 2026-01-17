<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/function.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/settings.php';

$page_title = 'Liên hệ - DocShare';
include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/sidebar.php';

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $VSD->escape($_POST['name'] ?? '');
    $email = $VSD->escape($_POST['email'] ?? '');
    $subject = $VSD->escape($_POST['subject'] ?? '');
    $message = $VSD->escape($_POST['message'] ?? '');
    
    // Validate
    if (empty($name) || empty($email) || empty($message)) {
        $error_msg = 'Vui lòng điền đầy đủ các trường bắt buộc.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Địa chỉ email không hợp lệ.';
    } else {
        // In a real app, save to DB or send email
        // $VSD->insert('contact_messages', [...]);
        $success_msg = 'Cảm ơn bạn đã liên hệ! Chúng tôi sẽ phản hồi sớm nhất có thể.';
    }
}
?>

<div class="drawer-content flex flex-col min-h-screen bg-base-200/30">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    
    <main class="flex-1 w-full max-w-6xl mx-auto p-4 md:p-8">
        <div class="grid lg:grid-cols-2 gap-8 lg:gap-16 items-center">
            
            <!-- Contact Info -->
            <div class="space-y-8">
                <div>
                    <h1 class="text-4xl md:text-5xl font-black bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent mb-6">
                        Liên Hệ Với Chúng Tôi
                    </h1>
                    <p class="text-lg text-base-content/70 leading-relaxed">
                        Chúng tôi luôn lắng nghe ý kiến đóng góp của bạn để phát triển DocShare ngày càng tốt hơn. 
                        Đừng ngần ngại gửi tin nhắn cho chúng tôi.
                    </p>
                </div>

                <div class="space-y-6">
                    <div class="flex items-start gap-4 p-4 bg-base-100 rounded-2xl shadow-sm border border-base-200">
                        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center text-primary text-xl flex-shrink-0">
                            <i class="fa-solid fa-envelope"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg mb-1">Email</h3>
                            <a href="mailto:support@VietStuDocs.site" class="text-base-content/70 hover:text-primary transition-colors">support@docshare.vn</a>
                            <p class="text-xs text-base-content/50 mt-1">Phản hồi trong vòng 24h</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4 p-4 bg-base-100 rounded-2xl shadow-sm border border-base-200">
                        <div class="w-12 h-12 rounded-xl bg-secondary/10 flex items-center justify-center text-secondary text-xl flex-shrink-0">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg mb-1">Địa chỉ</h3>
                            <p class="text-base-content/70">Tầng 12, Tòa nhà Innovation, Quận 1, TP. Hồ Chí Minh</p>
                        </div>
                    </div>
                </div>

                <!-- Map Placeholder -->
                <div class="h-64 bg-base-200 rounded-3xl overflow-hidden relative">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3919.424681648074!2d106.68784831526017!3d10.778747462100863!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31752f385570472f%3A0x1787491df0ed8d6a!2sIndependence%20Palace!5e0!3m2!1sen!2s!4v1655000000000!5m2!1sen!2s" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" class="grayscale opacity-60 hover:grayscale-0 hover:opacity-100 transition-all duration-500"></iframe>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="card bg-base-100 shadow-xl border border-base-200">
                <div class="card-body p-8">
                    <h2 class="card-title text-2xl mb-6">Gửi tin nhắn</h2>
                    
                    <?php if($success_msg): ?>
                        <div class="alert alert-success text-white mb-6 animate-fade-in-up">
                            <i class="fa-solid fa-circle-check"></i>
                            <span><?= $success_msg ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if($error_msg): ?>
                        <div class="alert alert-error text-white mb-6 animate-fade-in-up">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span><?= $error_msg ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <div class="form-control">
                            <label class="label"><span class="label-text font-medium">Họ và tên <span class="text-error">*</span></span></label>
                            <input type="text" name="name" placeholder="Ví dụ: Nguyễn Văn A" class="input input-bordered focus:input-primary bg-base-200/50 focus:bg-base-100 transition-all" required>
                        </div>

                        <div class="form-control">
                            <label class="label"><span class="label-text font-medium">Email <span class="text-error">*</span></span></label>
                            <input type="email" name="email" placeholder="email@domain.com" class="input input-bordered focus:input-primary bg-base-200/50 focus:bg-base-100 transition-all" required>
                        </div>

                        <div class="form-control">
                            <label class="label"><span class="label-text font-medium">Chủ đề</span></label>
                            <select name="subject" class="select select-bordered focus:select-primary bg-base-200/50 focus:bg-base-100 transition-all">
                                <option value="general">Hỏi đáp chung</option>
                                <option value="support">Hỗ trợ kỹ thuật</option>
                                <option value="billing">Thanh toán & Nạp điểm</option>
                                <option value="report">Báo cáo vi phạm</option>
                                <option value="partnership">Hợp tác</option>
                            </select>
                        </div>

                        <div class="form-control">
                            <label class="label"><span class="label-text font-medium">Nội dung <span class="text-error">*</span></span></label>
                            <textarea name="message" class="textarea textarea-bordered h-32 focus:textarea-primary bg-base-200/50 focus:bg-base-100 transition-all" placeholder="Nhập nội dung tin nhắn..." required></textarea>
                        </div>

                        <div class="form-control mt-6">
                            <button type="submit" class="btn btn-primary btn-block rounded-xl shadow-lg shadow-primary/20">
                                <i class="fa-solid fa-paper-plane mr-2"></i> Gửi Tin Nhắn
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</div>
</div>
</body>
</html>
