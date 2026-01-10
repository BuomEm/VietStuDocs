<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/function.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/settings.php';

$page_title = 'Câu hỏi thường gặp - DocShare';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/sidebar.php';

$faqs = [
    [
        'question' => 'Làm thế nào để tải lên tài liệu?',
        'answer' => 'Bạn cần đăng nhập vào tài khoản, sau đó nhấn vào nút "Tải lên tài liệu" trên thanh menu hoặc trang chủ. Điền đầy đủ thông tin và chọn tệp cần tải lên.'
    ],
    [
        'question' => 'Tôi có thể kiếm tiền từ tài liệu của mình không?',
        'answer' => 'Có! Bạn có thể đặt giá bán cho tài liệu của mình. Khi có người dùng mua tài liệu, bạn sẽ nhận được số điểm tương ứng (trừ phí nền tảng).'
    ],
    [
        'question' => 'Làm sao để nạp điểm?',
        'answer' => 'Truy cập vào trang "Gói Premium" hoặc ví cá nhân để xem các phương thức nạp điểm hỗ trợ (Thẻ cào, Chuyển khoản, Momo...).'
    ],
    [
        'question' => 'Tài liệu của tôi bị từ chối, tôi phải làm sao?',
        'answer' => 'Vui lòng kiểm tra lý do từ chối trong thông báo. Thông thường do vi phạm bản quyền, nội dung không phù hợp hoặc chất lượng kém. Hãy chỉnh sửa và tải lên lại.'
    ],
    [
        'question' => 'Gói Premium có lợi ích gì?',
        'answer' => 'Thành viên Premium được tải tài liệu không giới hạn tốc độ, xem trước toàn bộ nội dung, và không bị làm phiền bởi quảng cáo.'
    ]
];
?>

<div class="drawer-content flex flex-col min-h-screen bg-base-200/30">
    <?php include __DIR__ . '/includes/navbar.php'; ?>
    
    <main class="flex-1 w-full max-w-5xl mx-auto p-4 md:p-8 space-y-8">
        <!-- Header -->
        <div class="text-center space-y-4 py-8">
            <h1 class="text-4xl md:text-5xl font-black bg-gradient-to-r from-primary to-secondary bg-clip-text text-transparent">
                Câu Hỏi Thường Gặp
            </h1>
            <p class="text-base-content/60 max-w-2xl mx-auto text-lg">
                Giải đáp những thắc mắc phổ biến nhất của người dùng khi sử dụng DocShare.
            </p>
        </div>

        <!-- FAQ List -->
        <div class="space-y-4">
            <?php foreach($faqs as $index => $faq): ?>
            <div class="collapse collapse-plus bg-base-100 border border-base-200 shadow-sm rounded-2xl hover:shadow-md transition-all duration-300">
                <input type="radio" name="faq-accordion" <?= $index === 0 ? 'checked' : '' ?> /> 
                <div class="collapse-title text-lg font-bold flex items-center gap-3">
                    <span class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center flex-shrink-0 text-sm">
                        Q<?= $index + 1 ?>
                    </span>
                    <?= htmlspecialchars($faq['question']) ?>
                </div>
                <div class="collapse-content"> 
                    <div class="pl-11 pr-4 pb-4 text-base-content/70 leading-relaxed">
                        <?= htmlspecialchars($faq['answer']) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Contact CTA -->
        <div class="card bg-gradient-to-br from-primary/5 to-secondary/5 border border-primary/10 shadow-xl overflow-hidden relative mt-12">
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full blur-3xl translate-x-1/2 -translate-y-1/2"></div>
            <div class="card-body text-center py-12 relative z-10">
                <h3 class="text-2xl font-bold mb-2">Vẫn còn thắc mắc?</h3>
                <p class="text-base-content/60 mb-6">Đội ngũ hỗ trợ của chúng tôi luôn sẵn sàng giúp đỡ bạn.</p>
                <div class="flex justify-center gap-4">
                    <a href="contact.php" class="btn btn-primary rounded-xl px-8 shadow-lg shadow-primary/20">Liên hệ hỗ trợ</a>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</div>
</div>
</body>
</html>
