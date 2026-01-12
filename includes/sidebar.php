<?php
// Make sure variables are defined
if(!isset($user_id)) $user_id = null;
if(!isset($is_premium)) $is_premium = false;
if(!isset($current_page)) $current_page = '';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/premium.php';
require_once __DIR__ . '/../config/settings.php';

$has_admin = isset($_SESSION['user_id']) && hasAdminAccess();

// Get user points if logged in
$user_points = null;
$user_info = null;
if(isset($_SESSION['user_id'])) {
    $user_id = getCurrentUserId();
    $user_points = getUserPoints($user_id);
    $user_info = getUserInfo($user_id);
    $is_premium = isPremium($user_id);
}
?>

<div class="drawer lg:drawer-open" id="main-drawer">
    <input id="drawer-toggle" type="checkbox" class="drawer-toggle" />
    
    <!-- Sidebar -->
    <div class="drawer-side z-50">
        <label for="drawer-toggle" class="drawer-overlay"></label>
        <aside class="w-64 min-h-full bg-base-100 border-r border-base-300">
            <!-- Sidebar Header -->
            <div class="p-4 border-b border-base-300">
                <?php 
                $site_name = function_exists('getSetting') ? getSetting('site_name', 'DocShare') : 'DocShare';
                $site_logo = function_exists('getSetting') ? getSetting('site_logo') : '';
                ?>
                <a href="/dashboard" class="text-xl font-bold text-primary flex items-center justify-center gap-2 overflow-hidden whitespace-nowrap h-12 w-full hover:bg-primary/5 transition-all">
                    <?php if (!empty($site_logo)): ?>
                        <img src="<?= htmlspecialchars($site_logo) ?>" alt="Logo" class="h-8 w-8 object-contain flex-shrink-0">
                    <?php else: ?>
                        <i class="fa-solid fa-file-contract text-lg w-8 flex-shrink-0 text-center"></i>
                    <?php endif; ?>
                    <span class="logo-text transition-all duration-300"><?= htmlspecialchars($site_name) ?></span>
                </a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <!-- <div class="mt-2 text-sm text-base-content/70">
                        <div class="font-semibold"><?= htmlspecialchars($user_info['username'] ?? getCurrentUsername()) ?></div>
                        <div class="text-xs"><?= htmlspecialchars($user_info['email'] ?? '') ?></div>
                    </div> -->
                <?php endif; ?>
            </div>
            
            <!-- Navigation Menu -->
            <ul class="menu p-4 w-full text-base-content">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <!-- Dashboard -->
                    <li>
                        <a href="/dashboard" class="<?= $current_page === 'dashboard' ? 'active' : '' ?>" data-tip="Trang chủ">
                            <i class="fa-solid fa-house w-5 h-5"></i>
                            <span class="menu-text">Trang chủ</span>
                        </a>
                    </li>
                    
                    <!-- Upload -->
                    <li>
                        <a href="/upload" class="<?= $current_page === 'upload' ? 'active' : '' ?>" data-tip="Đăng tài liệu">
                            <i class="fa-solid fa-cloud-arrow-up w-5 h-5"></i>
                            <span class="menu-text">Đăng tài liệu</span>
                        </a>
                    </li>

                    <!-- Tutor Dashboard -->
                    <li>
                        <a href="/tutors/dashboard" class="<?= ($current_page === 'tutor_dashboard' || strpos($_SERVER['PHP_SELF'], '/tutors/dashboard') !== false) ? 'active' : '' ?>" data-tip="Thuê Gia Sư">
                            <i class="fa-solid fa-user-graduate w-5 h-5"></i>
                            <span class="menu-text">Thuê Gia Sư</span>
                        </a>
                    </li>
                    
                    <!-- Saved -->
                    <li>
                        <a href="/saved" class="<?= $current_page === 'saved' ? 'active' : '' ?>" data-tip="Đã lưu">
                            <i class="fa-solid fa-bookmark w-5 h-5"></i>
                            <span class="menu-text">Đã lưu</span>
                        </a>
                    </li>
                    
                    <!-- History -->
                    <li>
                        <a href="/history" class="<?= $current_page === 'history' ? 'active' : '' ?>" data-tip="Lịch Sử">
                            <i class="fa-solid fa-clock-rotate-left w-5 h-5"></i>
                            <span class="menu-text">Lịch Sử</span>
                        </a>
                    </li>
                    
                    <!-- Premium -->
                    <li>
                        <a href="/premium" class="<?= $current_page === 'premium' ? 'active' : '' ?>" data-tip="Premium">
                            <i class="fa-solid fa-crown w-5 h-5"></i>
                            <span class="menu-text">
                                Premium
                                <?php if($is_premium): ?>
                                    <span class="badge badge-sm badge-primary ml-1">Active</span>
                                <?php endif; ?>
                            </span>
                        </a>
                    </li>
                    
                    <!-- Profile -->
                    <li>
                        <a href="/user_profile?id=<?= getCurrentUserId() ?>" class="<?= $current_page === 'profile' ? 'active' : '' ?>" data-tip="Hồ sơ cá nhân">
                            <i class="fa-solid fa-user w-5 h-5"></i>
                            <span class="menu-text">Hồ sơ cá nhân</span>
                        </a>
                    </li>
                    
                    <!-- Admin -->
                    <?php if($has_admin): ?>
                        <li>
                            <a href="/admin/index.php" class="<?= $current_page === 'admin' ? 'active' : '' ?>" data-tip="Quản trị">
                                <i class="fa-solid fa-user-shield w-5 h-5"></i>
                                <span class="menu-text">Quản trị</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="mt-4"><hr class="border-base-300"></li>
                    
                    <!-- Points Display -->
                    <?php if($user_points): ?>
                        <li class="mt-4 px-2 points-card">
                            <div class="group relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary/10 to-primary/5 p-4 transition-all hover:bg-primary/20">
                                <!-- Background Decoration -->
                                <div class="absolute -right-2 -bottom-2 text-primary/10 transition-transform group-hover:scale-110">
                                    <i class="fa-solid fa-coins text-5xl"></i>
                                </div>
                                
                                <div class="relative z-10 flex flex-col gap-1">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-base-content/60">Số dư điểm</span>
                                        <i class="fa-solid fa-circle-info text-[10px] text-primary/40"></i>
                                    </div>
                                    
                                    <div class="flex items-baseline gap-1">
                                        <span class="text-2xl font-black text-primary"><?= number_format($user_points['current_points']) ?></span>
                                        <span class="text-[10px] font-bold text-primary/60">VSD</span>
                                    </div>
                                    
                                    <div class="mt-2 flex items-center justify-between border-t border-primary/10 pt-2 text-[10px]">
                                        <div class="flex flex-col">
                                            <span class="text-base-content/50">Đã nhận</span>
                                            <span class="font-bold"><?= number_format($user_points['total_earned']) ?></span>
                                        </div>
                                        <div class="flex flex-col text-right">
                                            <span class="text-base-content/50">Đã dùng</span>
                                            <span class="font-bold"><?= number_format($user_points['total_spent']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Logout -->
                    <!-- <li class="mt-auto">
                        <a href="/logout.php" class="text-error">
                            <i class="fa-solid fa-right-from-bracket w-5 h-5"></i>
                            Logout
                        </a>
                    </li> -->
                <?php else: ?>
                    <li>
                        <a href="/login" data-tip="Đăng nhập">
                            <i class="fa-solid fa-right-to-bracket w-5 h-5"></i>
                            <span class="menu-text">Đăng nhập</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </aside>
    </div>
    <!-- Drawer will be closed by pages after drawer-content -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const drawerToggleBtns = document.querySelectorAll('label[for="drawer-toggle"]');
    const mainDrawer = document.getElementById('main-drawer');
    
    // Load state from localStorage
    if (localStorage.getItem('sidebar_collapsed') === 'true') {
        mainDrawer?.classList.add('is-drawer-close');
    }

    if(mainDrawer) {
        drawerToggleBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
                if(window.innerWidth >= 1024) {
                    e.preventDefault(); 
                    // Toggle collapsed mode (Icon only vs Full)
                    mainDrawer.classList.toggle('is-drawer-close');
                    // Save state
                    localStorage.setItem('sidebar_collapsed', mainDrawer.classList.contains('is-drawer-close'));
                }
            });
        });
    }
});
</script>
