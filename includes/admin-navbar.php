<?php
/**
 * Admin Top Navbar
 */
$admin_id = getCurrentUserId();
$admin_username = getCurrentUsername();
// $unread_notifications_count calculation moved down
?>

<div class="navbar bg-base-100 border-b border-base-300 px-4 sticky top-0 z-30 shadow-sm backdrop-blur bg-base-100/80">
    <div class="flex-none">
        <label for="drawer-toggle" class="btn btn-square btn-ghost">
            <i class="fa-solid fa-bars text-xl"></i>
        </label>
    </div>
    
    <div class="flex-1 px-2 mx-2">
        <div class="text-sm breadcrumbs hidden sm:block">
            <ul>
                <li><a href="index.php" class="flex items-center gap-1"><i class="fa-solid fa-house"></i> Admin</a></li>
                <?php if(isset($page_title) && $page_title != 'Admin Panel - DocShare'): ?>
                    <li class="font-bold text-base-content"><?= htmlspecialchars($page_title) ?></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="lg:hidden font-bold">
            <?= function_exists('getSetting') ? htmlspecialchars(getSetting('site_name', 'DocShare') . ' Admin') : 'DocShare Admin' ?>
        </div>
    </div>
    
    <div class="flex-none gap-2">
        <!-- Search bar (optional for admin) -->
        <div class="form-control hidden md:block">
            <div class="input-group relative">
                <input type="text" placeholder="Tìm kiếm nhanh..." class="input input-bordered input-sm w-48 transition-all focus:w-64" />
                <button class="btn btn-sm btn-square absolute right-0">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </button>
            </div>
        </div>

        <?php
        // Notifications Logic
        global $VSD;
        $unread_notifications_count = 0;
        $navbar_notifications = [];
        
        if (isset($VSD)) {
            $unread_notifications_count = $VSD->num_rows("SELECT id FROM admin_notifications WHERE admin_id=$admin_id AND is_read=0");
            $navbar_notifications = $VSD->get_list("SELECT * FROM admin_notifications WHERE admin_id=$admin_id ORDER BY created_at DESC LIMIT 5");
        }
        ?>

        <!-- Notifications -->
        <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="btn btn-ghost btn-circle">
                <div class="indicator">
                    <i class="fa-solid fa-bell text-xl text-base-content/80"></i>
                    <?php if($unread_notifications_count > 0): ?>
                        <span class="badge badge-error badge-xs indicator-item pulse"></span>
                    <?php endif; ?>
                </div>
            </div>
            <div tabindex="0" class="mt-3 z-[1] card card-compact dropdown-content w-80 bg-base-100 shadow-xl border border-base-300">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-bold text-lg">Thông báo</h3>
                        <span class="badge badge-error badge-sm"><?= $unread_notifications_count ?> mới</span>
                    </div>
                    
                    <div class="max-h-64 overflow-y-auto">
                        <?php if(count($navbar_notifications) > 0): ?>
                            <ul class="menu menu-sm bg-base-100 rounded-box p-0">
                                <?php foreach($navbar_notifications as $notif): ?>
                                    <li class="border-b border-base-200 last:border-none">
                                        <a href="<?= htmlspecialchars($notif['link'] ?? '#') ?>" class="block py-2 px-2 hover:bg-base-200 transition-colors <?= $notif['is_read'] == 0 ? 'bg-base-200/50' : '' ?>">
                                            <div class="flex justify-between items-start">
                                                <span class="text-xs text-base-content/60"><?= date('H:i d/m', strtotime($notif['created_at'])) ?></span>
                                                <?php if($notif['is_read'] == 0): ?>
                                                    <span class="w-2 h-2 rounded-full bg-error"></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm font-medium mt-1 text-base-content/80 line-clamp-2"><?= htmlspecialchars($notif['message']) ?></div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="flex flex-col items-center justify-center py-6 text-base-content/50">
                                <i class="fa-solid fa-bell-slash text-2xl mb-2"></i>
                                <span class="text-sm italic">Chưa có thông báo quan trọng...</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-actions mt-2 pt-2 border-t border-base-300">
                        <a href="notifications.php" class="btn btn-primary btn-block btn-sm">Xem tất cả thông báo</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Dropdown -->
        <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar online">
                <div class="w-10 rounded-full bg-secondary text-secondary-content grid place-items-center ring ring-secondary ring-offset-base-100">
                    <span class="text-lg font-bold"><?= strtoupper(substr($admin_username, 0, 1)) ?></span>
                </div>
            </div>
            <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow-xl bg-base-100 rounded-box w-52 border border-base-300">
                <li class="menu-title flex flex-col items-start px-4 py-2">
                    <span class="text-base-content font-bold underline decoration-primary"><?= htmlspecialchars($admin_username) ?></span>
                    <span class="text-xs opacity-60">ADMINISTRATOR</span>
                </li>
                <li><hr class="my-1 border-base-300"></li>
                <li><a href="../dashboard.php"><i class="fa-solid fa-earth-asia w-4"></i> Xem Website</a></li>
                <li><a href="settings.php"><i class="fa-solid fa-gear w-4"></i> Cài đặt</a></li>
                <li><a href="profile.php"><i class="fa-solid fa-user-gear w-4"></i> Hồ sơ</a></li>
                <li><hr class="my-1 border-base-300"></li>
                <li><a href="../logout.php" class="text-error font-bold"><i class="fa-solid fa-right-from-bracket w-4 text-error"></i> Đăng xuất</a></li>
            </ul>
        </div>
    </div>
</div>
