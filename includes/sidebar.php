<?php
// Make sure variables are defined
if(!isset($user_id)) $user_id = null;
if(!isset($is_premium)) $is_premium = false;
if(!isset($current_page)) $current_page = '';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/premium.php';

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

<div class="drawer lg:drawer-open">
    <input id="drawer-toggle" type="checkbox" class="drawer-toggle" />
    
    <!-- Sidebar -->
    <div class="drawer-side z-50">
        <label for="drawer-toggle" class="drawer-overlay"></label>
        <aside class="w-64 min-h-full bg-base-100 border-r border-base-300">
            <!-- Sidebar Header -->
            <div class="p-4 border-b border-base-300">
                <h2 class="text-xl font-bold text-primary flex items-center gap-2">
                    <i class="fa-solid fa-file-contract text-lg"></i>
                    DocShare
                </h2>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <div class="mt-2 text-sm text-base-content/70">
                        <div class="font-semibold"><?= htmlspecialchars($user_info['username'] ?? getCurrentUsername()) ?></div>
                        <div class="text-xs"><?= htmlspecialchars($user_info['email'] ?? '') ?></div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Navigation Menu -->
            <ul class="menu p-4 w-full text-base-content">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <!-- Dashboard -->
                    <li>
                        <a href="/dashboard.php" class="<?= $current_page === 'dashboard' ? 'active' : '' ?>">
                            <i class="fa-solid fa-house w-5 h-5"></i>
                            Dashboard
                        </a>
                    </li>
                    
                    <!-- Upload -->
                    <li>
                        <a href="/upload.php" class="<?= $current_page === 'upload' ? 'active' : '' ?>">
                            <i class="fa-solid fa-cloud-arrow-up w-5 h-5"></i>
                            Upload
                        </a>
                    </li>

                    <!-- Tutor Dashboard -->
                    <li>
                        <a href="/tutors/dashboard" class="<?= ($current_page === 'tutor_dashboard' || strpos($_SERVER['PHP_SELF'], '/tutors/dashboard.php') !== false) ? 'active' : '' ?>">
                            <i class="fa-solid fa-user-graduate w-5 h-5"></i>
                            Thuê Gia Sư
                        </a>
                    </li>
                    
                    <!-- Saved -->
                    <li>
                        <a href="/saved.php" class="<?= $current_page === 'saved' ? 'active' : '' ?>">
                            <i class="fa-solid fa-bookmark w-5 h-5"></i>
                            Saved
                        </a>
                    </li>
                    
                    <!-- Premium -->
                    <li>
                        <a href="/premium.php" class="<?= $current_page === 'premium' ? 'active' : '' ?>">
                            <i class="fa-solid fa-crown w-5 h-5"></i>
                            Premium
                            <?php if($is_premium): ?>
                                <span class="badge badge-sm badge-primary">Active</span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Profile -->
                    <li>
                        <a href="/profile.php" class="<?= $current_page === 'profile' ? 'active' : '' ?>">
                            <i class="fa-solid fa-user w-5 h-5"></i>
                            Profile
                        </a>
                    </li>
                    
                    <!-- Admin -->
                    <?php if($has_admin): ?>
                        <li>
                            <a href="/admin/index.php" class="<?= $current_page === 'admin' ? 'active' : '' ?>">
                                <i class="fa-solid fa-user-shield w-5 h-5"></i>
                                Admin
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
                    <li class="mt-auto">
                        <a href="/logout.php" class="text-error">
                            <i class="fa-solid fa-right-from-bracket w-5 h-5"></i>
                            Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="/index.php">
                            <i class="fa-solid fa-right-to-bracket w-5 h-5"></i>
                            Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </aside>
    </div>
    <!-- Drawer will be closed by pages after drawer-content -->