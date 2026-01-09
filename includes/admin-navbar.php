<?php
/**
 * Admin Top Navbar - Premium Design
 * Modern glassmorphism navbar with enhanced UX
 */
$admin_id = getCurrentUserId();
$admin_username = getCurrentUsername();
$admin_avatar = function_exists('getCurrentUserAvatar') ? getCurrentUserAvatar() : null;

// Notifications Logic
global $VSD;
$unread_notifications_count = 0;
$navbar_notifications = [];

if (isset($VSD)) {
    $unread_notifications_count = $VSD->num_rows("SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0");
    $navbar_notifications = $VSD->get_list("SELECT * FROM admin_notifications WHERE admin_id=$admin_id ORDER BY created_at DESC LIMIT 5");
}

$site_name = function_exists('getSetting') ? getSetting('site_name', 'DocShare') : 'DocShare';
?>

<style>
/* Admin Navbar Styles */
.admin-navbar {
    background: linear-gradient(90deg, 
        oklch(var(--b1) / 0.95) 0%, 
        oklch(var(--b1) / 0.9) 100%
    );
    backdrop-filter: blur(20px) saturate(180%);
    border-bottom: 1px solid oklch(var(--bc) / 0.08);
}

.admin-navbar .nav-title {
    background: linear-gradient(135deg, oklch(var(--p)), oklch(var(--s)));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.admin-navbar .search-box {
    background: oklch(var(--b2) / 0.8);
    border: 1px solid oklch(var(--bc) / 0.1);
    border-radius: 0.75rem;
    transition: all 0.3s ease;
}

.admin-navbar .search-box:focus-within {
    background: oklch(var(--b1));
    border-color: oklch(var(--p) / 0.5);
    box-shadow: 0 0 0 3px oklch(var(--p) / 0.1);
}

.admin-navbar .search-box input {
    background: transparent;
    border: none;
}

.admin-navbar .search-box input:focus {
    outline: none;
}

.admin-navbar .nav-btn {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    position: relative;
}

.admin-navbar .nav-btn:hover {
    background: oklch(var(--bc) / 0.1);
}

.admin-navbar .nav-btn .indicator-dot {
    position: absolute;
    top: 0.25rem;
    right: 0.25rem;
    width: 0.5rem;
    height: 0.5rem;
    background: oklch(var(--er));
    border-radius: 50%;
    animation: pulse-dot 2s infinite;
}

@keyframes pulse-dot {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.2); opacity: 0.7; }
}

.admin-navbar .notification-dropdown {
    background: oklch(var(--b1));
    border: 1px solid oklch(var(--bc) / 0.1);
    border-radius: 1rem;
    box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);
    overflow: hidden;
}

.admin-navbar .notification-item {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid oklch(var(--bc) / 0.05);
    transition: background 0.2s ease;
}

.admin-navbar .notification-item:hover {
    background: oklch(var(--bc) / 0.05);
}

.admin-navbar .notification-item.unread {
    background: oklch(var(--p) / 0.05);
    border-left: 3px solid oklch(var(--p));
}

.admin-navbar .quick-actions {
    display: flex;
    gap: 0.5rem;
}

.admin-navbar .quick-action-btn {
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.375rem;
    transition: all 0.2s ease;
}
</style>

<div class="admin-navbar navbar px-4 lg:px-6 sticky top-0 z-30">
    <!-- Left: Toggle & Breadcrumb -->
    <div class="flex-none flex items-center gap-2">
        <label for="drawer-toggle" class="nav-btn cursor-pointer hover:bg-base-200">
            <i class="fa-solid fa-bars text-lg"></i>
        </label>
        
        <div class="hidden sm:flex items-center gap-2 ml-2">
            <div class="breadcrumbs text-sm">
                <ul>
                    <li>
                        <a href="index.php" class="flex items-center gap-1.5 text-base-content/70 hover:text-primary transition-colors">
                            <i class="fa-solid fa-gauge-high text-xs"></i>
                            <span>Admin</span>
                        </a>
                    </li>
                    <?php if(isset($page_title) && !empty($page_title)): ?>
                        <li>
                            <span class="font-semibold text-base-content"><?= htmlspecialchars(str_replace(' - Admin Panel', '', $page_title)) ?></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <!-- Mobile Title -->
        <div class="lg:hidden font-bold nav-title ml-2">
            <?= htmlspecialchars($site_name) ?>
        </div>
    </div>
    
    <!-- Center/Right: Actions -->
    <div class="flex-1"></div>
    
    <div class="flex items-center gap-2">
        <!-- Search (Desktop) -->
        <div class="search-box hidden md:flex items-center px-3 py-1.5 w-48 lg:w-64 transition-all focus-within:w-80">
            <i class="fa-solid fa-magnifying-glass text-base-content/40 text-sm mr-2"></i>
            <input type="text" placeholder="Tìm kiếm..." class="flex-1 text-sm bg-transparent">
            <kbd class="kbd kbd-xs bg-base-300/50">⌘K</kbd>
        </div>

        <!-- Quick Actions (Desktop) -->
        <div class="quick-actions hidden lg:flex">
            <a href="pending-docs.php" class="quick-action-btn bg-warning/10 text-warning hover:bg-warning/20">
                <i class="fa-solid fa-clock"></i>
                <span>Duyệt</span>
            </a>
        </div>

        <!-- Divider -->
        <div class="hidden lg:block w-px h-6 bg-base-content/10 mx-2"></div>

        <!-- Notifications -->
        <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="nav-btn cursor-pointer">
                <i class="fa-regular fa-bell text-lg"></i>
                <?php if($unread_notifications_count > 0): ?>
                    <span class="indicator-dot"></span>
                <?php endif; ?>
            </div>
            <div tabindex="0" class="notification-dropdown dropdown-content mt-4 w-80 lg:w-96">
                <!-- Header -->
                <div class="p-4 border-b border-base-content/10 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-base">Thông báo</h3>
                        <p class="text-xs text-base-content/60"><?= $unread_notifications_count ?> chưa đọc</p>
                    </div>
                    <?php if($unread_notifications_count > 0): ?>
                        <button class="btn btn-ghost btn-xs">Đọc tất cả</button>
                    <?php endif; ?>
                </div>
                
                <!-- List -->
                <div class="max-h-80 overflow-y-auto">
                    <?php if(count($navbar_notifications) > 0): ?>
                        <?php foreach($navbar_notifications as $notif): ?>
                            <a href="<?= htmlspecialchars($notif['link'] ?? '#') ?>" 
                               class="notification-item block <?= $notif['is_read'] == 0 ? 'unread' : '' ?>">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
                                        <i class="fa-solid fa-bell text-primary text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm line-clamp-2"><?= htmlspecialchars($notif['message']) ?></p>
                                        <span class="text-xs text-base-content/50 mt-1 block">
                                            <?= date('H:i - d/m/Y', strtotime($notif['created_at'])) ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="py-12 text-center text-base-content/50">
                            <i class="fa-regular fa-bell-slash text-3xl mb-3 block"></i>
                            <p class="text-sm">Không có thông báo mới</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Footer -->
                <div class="p-3 border-t border-base-content/10">
                    <a href="notifications.php" class="btn btn-primary btn-block btn-sm">
                        <i class="fa-solid fa-arrow-right mr-2"></i>
                        Xem tất cả
                    </a>
                </div>
            </div>
        </div>

        <!-- User Menu -->
        <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="flex items-center gap-2 cursor-pointer hover:bg-base-content/5 rounded-lg px-2 py-1.5 transition-colors">
                <div class="avatar placeholder">
                    <div class="w-9 h-9 rounded-xl ring-2 ring-primary/20 bg-gradient-to-br from-primary/20 to-secondary/20 grid place-items-center">
                        <i class="fa-solid fa-user-shield text-primary text-base"></i>
                    </div>
                </div>
                <div class="hidden lg:block text-left">
                    <div class="text-sm font-semibold leading-tight"><?= htmlspecialchars($admin_username) ?></div>
                    <div class="text-xs text-base-content/60">Admin</div>
                </div>
                <i class="fa-solid fa-chevron-down text-xs text-base-content/40 hidden lg:block"></i>
            </div>
            
            <ul tabindex="0" class="dropdown-content mt-3 z-[1] menu menu-sm p-2 shadow-xl bg-base-100 rounded-xl w-56 border border-base-content/10">
                <li class="px-3 py-2 border-b border-base-content/10 mb-1">
                    <div class="flex items-center gap-3 pointer-events-none">
                        <div class="avatar placeholder">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary/20 to-secondary/20 grid place-items-center">
                                <i class="fa-solid fa-user-shield text-primary text-lg"></i>
                            </div>
                        </div>
                        <div>
                            <div class="font-bold"><?= htmlspecialchars($admin_username) ?></div>
                            <div class="text-xs text-base-content/60 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-success"></span>
                                Administrator
                            </div>
                        </div>
                    </div>
                </li>
                <li>
                    <a href="../dashboard.php" class="flex items-center gap-2">
                        <i class="fa-solid fa-globe w-4 text-center"></i>
                        Xem Website
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="flex items-center gap-2">
                        <i class="fa-solid fa-sliders w-4 text-center"></i>
                        Cài đặt hệ thống
                    </a>
                </li>
                <li>
                    <a href="profile.php" class="flex items-center gap-2">
                        <i class="fa-solid fa-user-pen w-4 text-center"></i>
                        Hồ sơ cá nhân
                    </a>
                </li>
                <li class="my-1"><hr class="border-base-content/10"></li>
                <li>
                    <a href="../logout.php" class="flex items-center gap-2 text-error hover:bg-error/10 hover:text-error">
                        <i class="fa-solid fa-power-off w-4 text-center"></i>
                        Đăng xuất
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
