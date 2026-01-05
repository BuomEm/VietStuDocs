<?php
// Validating active state
$current_uri = $_SERVER['REQUEST_URI'];
$is_dashboard = strpos($current_uri, 'dashboard.php') !== false || $current_uri == '/' || $current_uri == '/index.php';
$is_tutor = strpos($current_uri, 'tutor') !== false;
$is_upload = strpos($current_uri, 'upload') !== false;
$is_profile = strpos($current_uri, 'profile') !== false;
// For "More", we can check if it's none of the above, or specific pages?
// Actually if active it usually highlights. For "More" dropdown, we don't necessarily highlight the trigger if a sub-page is active, or we can.
?>

<!-- Bottom Navigation for Mobile -->
<div class="fixed bottom-0 left-0 right-0 z-[100] bg-base-100/60 backdrop-blur-2xl border-t border-base-content/5 shadow-[0_-10px_30px_-15px_rgba(0,0,0,0.1)] pb-[env(safe-area-inset-bottom)] lg:hidden transition-all duration-300">
    <div class="flex items-center justify-around p-2 h-[4.5rem]">
        
        <!-- Home -->
        <a href="/dashboard.php" class="flex flex-col items-center gap-1 p-2 w-[20%] <?= $is_dashboard ? 'text-primary' : 'text-base-content/50' ?> hover:text-primary transition-colors">
            <i class="fa-solid fa-house text-base mb-0.5"></i>
            <span class="text-[10px] font-medium">Trang chủ</span>
        </a>

        <!-- Tutor -->
        <a href="/tutors/dashboard.php" class="flex flex-col items-center gap-1 p-2 w-[20%] <?= $is_tutor ? 'text-primary' : 'text-base-content/50' ?> hover:text-primary transition-colors">
            <i class="fa-solid fa-user-graduate text-base mb-0.5"></i>
            <span class="text-[10px] font-medium">Gia Sư</span>
        </a>

        <!-- Upload (Center) -->
        <div class="w-[20%] flex justify-center relative">
            <!-- Glow Effect -->
            <div class="absolute -top-12 left-1/2 -translate-x-1/2 w-24 h-24 bg-gradient-to-tr from-red-600 via-orange-500 to-yellow-400 rounded-full blur-2xl opacity-60 animate-pulse-slow pointer-events-none"></div>
            
            <a href="/upload.php" class="absolute -top-8 flex items-center justify-center w-14 h-14 rounded-full bg-white text-primary shadow-lg shadow-primary/40 hover:scale-110 active:scale-95 transition-all outline outline-4 outline-base-100/50 z-10">
                <i class="fa-solid fa-cloud-arrow-up text-3xl"></i>
            </a>
        </div>

        <!-- Points Display -->
         <a href="/history.php" class="flex flex-col items-center gap-1 p-2 w-[20%] text-base-content/50 hover:text-primary transition-colors group">
            <div class="flex items-center justify-center w-6 h-6 rounded-full bg-primary/10 text-primary group-hover:bg-primary group-hover:text-primary-content transition-colors">
                <i class="fa-solid fa-coins text-xs"></i>
            </div>
            <span class="text-[10px] font-bold text-primary"><?= isset($user_points['current_points']) ? number_format($user_points['current_points']) : '0' ?></span>
        </a>

        <!-- More (Dropdown) -->
        <div class="dropdown dropdown-top dropdown-end w-[20%] flex justify-center">
            <div tabindex="0" role="button" class="flex flex-col items-center gap-1 p-2 text-base-content/50 hover:text-base-content transition-colors">
                <i class="fa-solid fa-grip text-base mb-0.5"></i>
                <span class="text-[10px] font-medium">Thêm</span>
            </div>
            <!-- Dropdown Menu -->
            <ul tabindex="0" class="dropdown-content menu p-2 shadow-2xl bg-base-100/70 backdrop-blur-xl rounded-2xl w-56 mb-4 border border-base-content/10 text-base-content z-50 bottom-full right-0 animate-bounce-slow origin-bottom-right">
                <li><a href="/saved.php" class="py-3 font-semibold"><i class="fa-solid fa-bookmark w-6 text-center text-primary"></i> Đã lưu</a></li>
                <li><a href="/history.php" class="py-3 font-semibold"><i class="fa-solid fa-clock-rotate-left w-6 text-center text-info"></i> Lịch sử</a></li>
                <li><a href="/premium.php" class="py-3 font-semibold"><i class="fa-solid fa-crown w-6 text-center text-warning"></i> Premium</a></li>
                <li><a href="/user_profile.php?id=<?= isset($user_id) ? $user_id : '' ?>" class="py-3 font-semibold"><i class="fa-solid fa-user w-6 text-center text-secondary"></i> Hồ sơ cá nhân</a></li>
                <div class="divider my-1"></div>
                <?php if(isset($has_admin) && $has_admin): ?>
                    <li><a href="/admin/" class="py-3 font-semibold"><i class="fa-solid fa-user-shield w-6 text-center text-error"></i> Quản trị</a></li>
                <?php endif; ?>
                <li><a href="/logout.php" class="py-3 font-semibold text-error"><i class="fa-solid fa-power-off w-6 text-center"></i> Đăng xuất</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Spacer to prevent content from being hidden behind bottom nav -->
<div class="h-24 lg:hidden"></div>
