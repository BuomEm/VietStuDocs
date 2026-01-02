<?php
if (!function_exists('getSetting')) require_once __DIR__ . '/../config/settings.php';
$site_name = getSetting('site_name', 'DocShare');
$site_desc = getSetting('site_description', 'Nền tảng chia sẻ tài liệu an toàn và hiệu quả');
?>
<footer class="footer footer-center p-10 bg-base-200 text-base-content border-t border-base-300 mt-20">
    <aside>
        <p class="font-bold text-lg">📄 <?= htmlspecialchars($site_name) ?></p>
        <p class="text-sm"><?= htmlspecialchars($site_desc) ?></p>
    </aside>
    <nav>
        <div class="grid grid-flow-col gap-4">
            <a href="dashboard.php" class="link link-hover">Trang chủ</a>
            <a href="premium.php" class="link link-hover">Premium</a>
            <a href="#" class="link link-hover">Điều khoản sử dụng</a>
            <a href="#" class="link link-hover">Chính sách bảo mật</a>
            <a href="#" class="link link-hover">Liên hệ</a>
        </div>
    </nav>
    <aside>
        <p class="text-xs opacity-70">&copy; <?= date('Y') ?> <?= htmlspecialchars($site_name) ?>. All rights reserved. | Powered by PHP & MySQL</p>
    </aside>
</footer>

<?php renderGlobalModal(); ?>
<?php require_once __DIR__ . '/chat_bubble.php'; ?>

</body>
</html>