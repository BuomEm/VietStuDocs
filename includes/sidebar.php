<?php
// Make sure variables are defined
if(!isset($user_id)) $user_id = null;
if(!isset($is_premium)) $is_premium = false;
if(!isset($current_page)) $current_page = '';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/points.php';
require_once __DIR__ . '/../config/db.php';

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
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m-3-8h.01M4 6a2 2 0 012-2h8l4 4v10a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" />
                    </svg>
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
                        <a href="dashboard.php" class="<?= $current_page === 'dashboard' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    
                    <!-- Upload -->
                    <li>
                        <a href="upload.php" class="<?= $current_page === 'upload' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            Upload
                        </a>
                    </li>
                    
                    <!-- Saved -->
                    <li>
                        <a href="saved.php" class="<?= $current_page === 'saved' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                            </svg>
                            Saved
                        </a>
                    </li>
                    
                    <!-- Premium -->
                    <li>
                        <a href="premium.php" class="<?= $current_page === 'premium' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                            </svg>
                            Premium
                            <?php if($is_premium): ?>
                                <span class="badge badge-sm badge-primary">Active</span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Profile -->
                    <li>
                        <a href="profile.php" class="<?= $current_page === 'profile' ? 'active' : '' ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Profile
                        </a>
                    </li>
                    
                    <!-- Admin -->
                    <?php if($has_admin): ?>
                        <li>
                            <a href="admin/index.php" class="<?= $current_page === 'admin' ? 'active' : '' ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
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
                        <a href="logout.php" class="text-error">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="index.php">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            Login
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </aside>
    </div>
    <!-- Drawer will be closed by pages after drawer-content -->