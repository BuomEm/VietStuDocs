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
                <a href="/dashboard.php" class="text-xl font-bold text-primary flex items-center justify-center gap-2 overflow-hidden whitespace-nowrap h-12 w-full hover:bg-primary/5 transition-all">
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
                        <a href="/dashboard.php" class="<?= $current_page === 'dashboard' ? 'active' : '' ?>" data-tip="Dashboard">
                            <i class="fa-solid fa-house w-5 h-5"></i>
                            <span class="menu-text">Dashboard</span>
                        </a>
                    </li>
                    
                    <!-- Upload -->
                    <li>
                        <a href="/upload.php" class="<?= $current_page === 'upload' ? 'active' : '' ?>" data-tip="Upload">
                            <i class="fa-solid fa-cloud-arrow-up w-5 h-5"></i>
                            <span class="menu-text">Upload</span>
                        </a>
                    </li>

                    <!-- Tutor Dashboard -->
                    <li>
                        <a href="/tutors/dashboard" class="<?= ($current_page === 'tutor_dashboard' || strpos($_SERVER['PHP_SELF'], '/tutors/dashboard.php') !== false) ? 'active' : '' ?>" data-tip="Thuê Gia Sư">
                            <i class="fa-solid fa-user-graduate w-5 h-5"></i>
                            <span class="menu-text">Thuê Gia Sư</span>
                        </a>
                    </li>
                    
                    <!-- Saved -->
                    <li>
                        <a href="/saved.php" class="<?= $current_page === 'saved' ? 'active' : '' ?>" data-tip="Saved">
                            <i class="fa-solid fa-bookmark w-5 h-5"></i>
                            <span class="menu-text">Saved</span>
                        </a>
                    </li>
                    
                    <!-- History -->
                    <li>
                        <a href="/history.php" class="<?= $current_page === 'history' ? 'active' : '' ?>" data-tip="Lịch Sử">
                            <i class="fa-solid fa-clock-rotate-left w-5 h-5"></i>
                            <span class="menu-text">Lịch Sử</span>
                        </a>
                    </li>
                    
                    <!-- Premium -->
                    <li>
                        <a href="/premium.php" class="<?= $current_page === 'premium' ? 'active' : '' ?>" data-tip="Premium">
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
                        <a href="/profile.php" class="<?= $current_page === 'profile' ? 'active' : '' ?>" data-tip="Profile">
                            <i class="fa-solid fa-user w-5 h-5"></i>
                            <span class="menu-text">Profile</span>
                        </a>
                    </li>
                    
                    <!-- Admin -->
                    <?php if($has_admin): ?>
                        <li>
                            <a href="/admin/index.php" class="<?= $current_page === 'admin' ? 'active' : '' ?>" data-tip="Admin">
                                <i class="fa-solid fa-user-shield w-5 h-5"></i>
                                <span class="menu-text">Admin</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="mt-4"><hr class="border-base-300"></li>
                    
                    <!-- Points Display -->
                    <?php if($user_points): ?>
                        <li>
                            <div class="stats stats-vertical shadow-sm bg-primary/10">
                                <div class="stat py-3 px-4">
                                    <div class="stat-title text-xs">Points Balance</div>
                                    <div class="stat-value text-lg text-primary"><?= number_format($user_points['current_points']) ?></div>
                                    <div class="stat-desc text-xs">Earned: <?= number_format($user_points['total_earned']) ?></div>
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
                        <a href="/index.php" data-tip="Login">
                            <i class="fa-solid fa-right-to-bracket w-5 h-5"></i>
                            <span class="menu-text">Login</span>
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
