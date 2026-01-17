<?php
if (!function_exists('getSetting')) require_once __DIR__ . '/../config/settings.php';
$site_name = getSetting('site_name', 'VietStuDocs');
$site_desc = getSetting('site_description', 'Nền tảng chia sẻ tài liệu an toàn và hiệu quả');
$site_logo = getSetting('site_logo');
?>

<footer class="relative mt-20 overflow-hidden">
    <!-- Decorative Background -->
    <div class="absolute inset-0 bg-gradient-to-br from-base-300 via-base-200 to-base-300"></div>
    <div class="absolute inset-0 opacity-30">
        <div class="absolute top-0 left-0 w-96 h-96 bg-primary/10 rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-secondary/10 rounded-full blur-3xl translate-x-1/2 translate-y-1/2"></div>
    </div>
    <div class="absolute inset-0 opacity-5" style="background-image: radial-gradient(circle at 1px 1px, currentColor 1px, transparent 0); background-size: 24px 24px;"></div>
    
    <!-- Main Footer Content -->
    <div class="relative z-10">
        <!-- Top Section -->
        <div class="border-b border-base-content/10">
            <div class="max-w-7xl mx-auto px-6 py-16">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12">
                    
                    <!-- Brand Column -->
                    <div class="lg:col-span-1">
                        <div class="flex items-center gap-3 mb-6">
                            <?php if (!empty($site_logo)): ?>
                                <img src="<?= htmlspecialchars($site_logo) ?>" alt="Logo" class="w-12 h-12 object-contain">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-primary to-primary-focus flex items-center justify-center shadow-lg shadow-primary/20">
                                    <i class="fa-solid fa-file-lines text-xl text-white"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h3 class="text-xl font-black text-base-content"><?= htmlspecialchars($site_name) ?></h3>
                                <span class="text-xs font-medium text-primary uppercase tracking-wider">Knowledge Hub</span>
                            </div>
                        </div>
                        <p class="text-sm text-base-content/60 leading-relaxed mb-6">
                            <?= htmlspecialchars($site_desc) ?>. Kết nối tri thức, chia sẻ giá trị cho cộng đồng học tập.
                        </p>
                        
                        <!-- Social Icons -->
                        <div class="flex gap-2">
                            <a href="#" class="btn btn-circle btn-ghost btn-sm hover:bg-primary hover:text-white transition-all duration-300">
                                <i class="fa-brands fa-facebook-f"></i>
                            </a>
                            <a href="#" class="btn btn-circle btn-ghost btn-sm hover:bg-info hover:text-white transition-all duration-300">
                                <i class="fa-brands fa-twitter"></i>
                            </a>
                            <a href="#" class="btn btn-circle btn-ghost btn-sm hover:bg-error hover:text-white transition-all duration-300">
                                <i class="fa-brands fa-youtube"></i>
                            </a>
                            <a href="#" class="btn btn-circle btn-ghost btn-sm hover:bg-pink-500 hover:text-white transition-all duration-300">
                                <i class="fa-brands fa-tiktok"></i>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Quick Links -->
                    <div>
                        <h4 class="font-bold text-base-content mb-6 flex items-center gap-2">
                            <span class="w-8 h-0.5 bg-primary rounded-full"></span>
                            Khám phá
                        </h4>
                        <ul class="space-y-3">
                            <li>
                                <a href="/dashboard" class="text-sm text-base-content/60 hover:text-primary hover:translate-x-1 transition-all duration-300 inline-flex items-center gap-2 group">
                                    <i class="fa-solid fa-chevron-right text-[10px] opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    Trang chủ
                                </a>
                            </li>
                            <li>
                                <a href="/documents" class="text-sm text-base-content/60 hover:text-primary hover:translate-x-1 transition-all duration-300 inline-flex items-center gap-2 group">
                                    <i class="fa-solid fa-chevron-right text-[10px] opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    Thư viện tài liệu
                                </a>
                            </li>
                            <li>
                                <a href="/upload" class="text-sm text-base-content/60 hover:text-primary hover:translate-x-1 transition-all duration-300 inline-flex items-center gap-2 group">
                                    <i class="fa-solid fa-chevron-right text-[10px] opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    Tải lên tài liệu
                                </a>
                            </li>
                            <li>
                                <a href="/premium" class="text-sm text-base-content/60 hover:text-primary hover:translate-x-1 transition-all duration-300 inline-flex items-center gap-2 group">
                                    <i class="fa-solid fa-chevron-right text-[10px] opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    Gói Premium
                                </a>
                            </li>
                            <li>
                                <a href="/tutors" class="text-sm text-base-content/60 hover:text-primary hover:translate-x-1 transition-all duration-300 inline-flex items-center gap-2 group">
                                    <i class="fa-solid fa-chevron-right text-[10px] opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    Tìm gia sư
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Support -->
                    <div>
                        <h4 class="font-bold text-base-content mb-6 flex items-center gap-2">
                            <span class="w-8 h-0.5 bg-secondary rounded-full"></span>
                            Hỗ trợ
                        </h4>
                        <ul class="space-y-3">
                            <li>
                                <a href="/faq" class="text-sm text-base-content/60 hover:text-secondary hover:translate-x-1 transition-all duration-300 inline-flex items-center gap-2 group">
                                    <i class="fa-solid fa-chevron-right text-[10px] opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    Câu hỏi thường gặp
                                </a>
                            </li>
                            <li>
                                <a href="/contact" class="text-sm text-base-content/60 hover:text-secondary hover:translate-x-1 transition-all duration-300 inline-flex items-center gap-2 group">
                                    <i class="fa-solid fa-chevron-right text-[10px] opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    Liên hệ
                                </a>
                            </li>
                            <li>
                                <a href="/terms" class="text-sm text-base-content/60 hover:text-secondary hover:translate-x-1 transition-all duration-300 inline-flex items-center gap-2 group">
                                    <i class="fa-solid fa-chevron-right text-[10px] opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    Điều khoản sử dụng
                                </a>
                            </li>
                            <li>
                                <a href="/privacy" class="text-sm text-base-content/60 hover:text-secondary hover:translate-x-1 transition-all duration-300 inline-flex items-center gap-2 group">
                                    <i class="fa-solid fa-chevron-right text-[10px] opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                    Chính sách bảo mật
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Newsletter -->
                    <div>
                        <h4 class="font-bold text-base-content mb-6 flex items-center gap-2">
                            <span class="w-8 h-0.5 bg-accent rounded-full"></span>
                            Nhận thông báo
                        </h4>
                        <p class="text-sm text-base-content/60 mb-4">
                            Đăng ký để nhận thông báo về tài liệu mới và ưu đãi hấp dẫn!
                        </p>
                        <form class="space-y-3">
                            <div class="join w-full shadow-sm bg-base-100 p-1 rounded-xl border border-base-200 focus-within:border-primary transition-colors">
                            <input type="email" placeholder="Email của bạn..." class="input input-ghost join-item w-full focus:outline-none focus:bg-transparent placeholder:text-base-content/40 text-sm h-auto min-h-0 py-2">
                                <button type="submit" class="btn btn-primary join-item rounded-lg px-4 h-auto min-h-0 py-2">
                                    <i class="fa-solid fa-paper-plane text-xs"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bottom Bar -->
        <div class="max-w-7xl mx-auto px-6 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-sm text-base-content/50 text-center md:text-left">
                    &copy; <?= date('Y') ?> <span class="font-semibold text-base-content/70"><?= htmlspecialchars($site_name) ?></span>. 
                    All rights reserved.
                </p>
            </div>
        </div>
    </div>
</footer>

<?php renderGlobalModal(); ?>
<?php require_once __DIR__ . '/chat_bubble.php'; ?>
<?php require_once __DIR__ . '/bottom_nav.php'; ?>

</body>
</html>
