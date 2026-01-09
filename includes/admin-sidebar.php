<?php
/**
 * Admin Sidebar - Premium Design
 * Vertical navigation with glassmorphism and modern styling
 */

// Ensure required variables are set
if (!isset($admin_active_page)) $admin_active_page = '';
if (!isset($unread_notifications)) $unread_notifications = 0;
if (!isset($admin_pending_count)) {
    if (function_exists('getPendingDocumentsCount')) {
        $admin_pending_count = getPendingDocumentsCount();
    } else {
        $admin_pending_count = 0;
    }
}

// Get current admin info
$admin_username = function_exists('getCurrentUsername') ? getCurrentUsername() : 'Admin';
$admin_avatar = function_exists('getCurrentUserAvatar') ? getCurrentUserAvatar() : null;

// Get counts for badges
$pending_tutors = 0;
$pending_reports = 0;
$disputed_requests = 0;

try {
    if (isset($GLOBALS['conn'])) {
        $pending_tutors_query = mysqli_query($GLOBALS['conn'], "SELECT COUNT(*) as count FROM tutors WHERE status='pending'");
        $pending_tutors = mysqli_fetch_assoc($pending_tutors_query)['count'] ?? 0;
        
        $pending_reports = mysqli_num_rows(mysqli_query($GLOBALS['conn'], "SELECT id FROM reports WHERE status='pending'"));
        
        $pdo_check = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $stmt = $pdo_check->query("SELECT COUNT(*) FROM tutor_requests WHERE status = 'disputed'");
        $disputed_requests = $stmt->fetchColumn();
    }
} catch(Exception $e) {}

$site_name = function_exists('getSetting') ? getSetting('site_name', 'DocShare') : 'DocShare';
$site_logo = function_exists('getSetting') ? getSetting('site_logo') : '';

// Menu items configuration
$menu_items = [
    'main' => [
        ['id' => 'dashboard', 'icon' => 'fa-solid fa-gauge-high', 'label' => 'Dashboard', 'href' => 'index.php', 'badge' => null],
        ['id' => 'pending', 'icon' => 'fa-solid fa-hourglass-half', 'label' => 'Chờ duyệt', 'href' => 'pending-docs.php', 'badge' => $admin_pending_count, 'badge_type' => 'warning'],
        ['id' => 'documents', 'icon' => 'fa-solid fa-folder-open', 'label' => 'Tài liệu', 'href' => 'all-documents.php', 'badge' => null],
    ],
    'management' => [
        ['id' => 'tutors', 'icon' => 'fa-solid fa-chalkboard-user', 'label' => 'Gia sư', 'href' => 'tutors.php', 'badge' => $pending_tutors, 'badge_type' => 'warning'],
        ['id' => 'tutor_requests', 'icon' => 'fa-solid fa-comments', 'label' => 'Hỏi đáp & Dispute', 'href' => 'tutor_requests.php', 'badge' => $disputed_requests, 'badge_type' => 'error'],
        ['id' => 'users', 'icon' => 'fa-solid fa-users', 'label' => 'Người dùng', 'href' => 'users.php', 'badge' => null],
        ['id' => 'categories', 'icon' => 'fa-solid fa-tags', 'label' => 'Danh mục', 'href' => 'categories.php', 'badge' => null],
    ],
    'analytics' => [
        ['id' => 'reports', 'icon' => 'fa-solid fa-flag', 'label' => 'Báo cáo', 'href' => 'reports.php', 'badge' => $pending_reports, 'badge_type' => 'error'],
        ['id' => 'transactions', 'icon' => 'fa-solid fa-receipt', 'label' => 'Giao dịch', 'href' => 'transactions.php', 'badge' => null],
        ['id' => 'notifications', 'icon' => 'fa-solid fa-bell', 'label' => 'Thông báo', 'href' => 'notifications.php', 'badge' => $unread_notifications, 'badge_type' => 'info'],
    ],
];
?>

<style>
/* Admin Sidebar Styles */
.admin-sidebar {
    background: linear-gradient(180deg, 
        oklch(var(--b1)) 0%, 
        oklch(var(--b2) / 0.95) 100%
    );
    backdrop-filter: blur(20px);
}

.admin-sidebar .sidebar-header {
    background: linear-gradient(135deg, 
        oklch(var(--p) / 0.15) 0%, 
        oklch(var(--s) / 0.1) 100%
    );
    border-bottom: 1px solid oklch(var(--bc) / 0.1);
}

.admin-sidebar .menu-section-title {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: oklch(var(--bc) / 0.4);
    padding: 0.75rem 1rem 0.5rem;
}

.admin-sidebar .menu li > a {
    border-radius: 0.75rem;
    margin: 0.125rem 0;
    padding: 0.75rem 1rem;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.admin-sidebar .menu li > a::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 0;
    background: linear-gradient(180deg, oklch(var(--p)), oklch(var(--s)));
    border-radius: 0 4px 4px 0;
    transition: height 0.2s ease;
}

.admin-sidebar .menu li > a:hover {
    background: oklch(var(--p) / 0.1);
}

.admin-sidebar .menu li > a.active {
    background: linear-gradient(90deg, oklch(var(--p) / 0.2), oklch(var(--p) / 0.05));
    font-weight: 600;
}

.admin-sidebar .menu li > a.active::before {
    height: 60%;
}

.admin-sidebar .menu li > a .menu-icon {
    width: 1.25rem;
    height: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    flex-shrink: 0;
}

.admin-sidebar .menu li > a.active .menu-icon {
    color: oklch(var(--p));
}

.admin-sidebar .user-card {
    background: linear-gradient(135deg, 
        oklch(var(--p) / 0.1) 0%, 
        oklch(var(--s) / 0.08) 100%
    );
    border: 1px solid oklch(var(--bc) / 0.08);
    border-radius: 1rem;
    padding: 1rem;
    margin: 1rem;
}

.admin-sidebar .user-card .avatar-ring {
    background: linear-gradient(135deg, oklch(var(--p)), oklch(var(--s)));
    padding: 2px;
    border-radius: 50%;
}

.admin-sidebar .badge-pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* Collapsed state */
#main-drawer.is-drawer-close .admin-sidebar {
    width: 5rem;
}

#main-drawer.is-drawer-close .menu-text,
#main-drawer.is-drawer-close .menu-section-title,
#main-drawer.is-drawer-close .user-card .user-info,
#main-drawer.is-drawer-close .sidebar-header .logo-text {
    display: none;
}

#main-drawer.is-drawer-close .menu li > a {
    justify-content: center;
    padding: 0.75rem;
}

#main-drawer.is-drawer-close .user-card {
    padding: 0.75rem;
    display: flex;
    justify-content: center;
}
</style>

<!-- Drawer Side -->
<div class="drawer-side z-50">
    <label for="drawer-toggle" class="drawer-overlay"></label>
    <aside class="admin-sidebar w-64 min-h-full flex flex-col text-base-content">
        <!-- Sidebar Header -->
        <div class="sidebar-header p-4">
            <a href="index.php" class="flex items-center gap-3 group">
                <?php if (!empty($site_logo)): ?>
                    <div class="w-10 h-10 rounded-xl bg-base-100 shadow-lg flex items-center justify-center overflow-hidden group-hover:scale-105 transition-transform">
                        <img src="<?= htmlspecialchars($site_logo) ?>" alt="Logo" class="w-8 h-8 object-contain">
                    </div>
                <?php else: ?>
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-secondary shadow-lg flex items-center justify-center group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-book-open text-primary-content text-lg"></i>
                    </div>
                <?php endif; ?>
                <div class="logo-text flex flex-col">
                    <span class="font-bold text-lg leading-tight"><?= htmlspecialchars($site_name) ?></span>
                    <span class="badge badge-xs badge-primary">ADMIN</span>
                </div>
            </a>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="flex-1 overflow-y-auto px-3 py-2">
            <!-- Main Section -->
            <div class="menu-section-title">Tổng quan</div>
            <ul class="menu w-full p-0">
                <?php foreach($menu_items['main'] as $item): ?>
                    <li>
                        <a href="<?= $item['href'] ?>" class="<?= $admin_active_page === $item['id'] ? 'active' : '' ?>">
                            <span class="menu-icon"><i class="<?= $item['icon'] ?>"></i></span>
                            <span class="menu-text flex-1"><?= $item['label'] ?></span>
                            <?php if($item['badge'] > 0): ?>
                                <span class="badge badge-sm badge-<?= $item['badge_type'] ?? 'primary' ?> badge-pulse"><?= $item['badge'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Management Section -->
            <div class="menu-section-title mt-4">Quản lý</div>
            <ul class="menu w-full p-0">
                <?php foreach($menu_items['management'] as $item): ?>
                    <li>
                        <a href="<?= $item['href'] ?>" class="<?= $admin_active_page === $item['id'] ? 'active' : '' ?>">
                            <span class="menu-icon"><i class="<?= $item['icon'] ?>"></i></span>
                            <span class="menu-text flex-1"><?= $item['label'] ?></span>
                            <?php if($item['badge'] > 0): ?>
                                <span class="badge badge-sm badge-<?= $item['badge_type'] ?? 'primary' ?> badge-pulse"><?= $item['badge'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Analytics Section -->
            <div class="menu-section-title mt-4">Báo cáo & Phân tích</div>
            <ul class="menu w-full p-0">
                <?php foreach($menu_items['analytics'] as $item): ?>
                    <li>
                        <a href="<?= $item['href'] ?>" class="<?= $admin_active_page === $item['id'] ? 'active' : '' ?>">
                            <span class="menu-icon"><i class="<?= $item['icon'] ?>"></i></span>
                            <span class="menu-text flex-1"><?= $item['label'] ?></span>
                            <?php if($item['badge'] > 0): ?>
                                <span class="badge badge-sm badge-<?= $item['badge_type'] ?? 'primary' ?> badge-pulse"><?= $item['badge'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <!-- Settings -->
            <div class="menu-section-title mt-4">Hệ thống</div>
            <ul class="menu w-full p-0">
                <li>
                    <a href="settings.php" class="<?= $admin_active_page === 'settings' ? 'active' : '' ?>">
                        <span class="menu-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span class="menu-text">Cài đặt</span>
                    </a>
                </li>
                <li>
                    <a href="../dashboard.php" class="text-base-content/70 hover:text-base-content">
                        <span class="menu-icon"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
                        <span class="menu-text">Xem Website</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- User Card at Bottom -->
        <div class="user-card mt-auto">
            <div class="flex items-center gap-3">
                <div class="avatar-ring">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary/20 to-secondary/20 flex items-center justify-center">
                        <i class="fa-solid fa-user-shield text-primary"></i>
                    </div>
                </div>
                <div class="user-info flex-1 min-w-0">
                    <div class="font-semibold text-sm truncate"><?= htmlspecialchars($admin_username) ?></div>
                    <div class="text-xs text-base-content/60 flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-success"></span>
                        Online
                    </div>
                </div>
                <a href="../logout.php" class="btn btn-ghost btn-sm btn-square text-error hover:bg-error/10" title="Đăng xuất">
                    <i class="fa-solid fa-power-off"></i>
                </a>
            </div>
        </div>
    </aside>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const drawerToggleBtn = document.querySelector('label[for="drawer-toggle"]');
    const mainDrawer = document.getElementById('main-drawer');
    
    // Check localStorage
    if (localStorage.getItem('admin_sidebar_collapsed') === 'true') {
        mainDrawer?.classList.add('is-drawer-close');
    }

    if(drawerToggleBtn && mainDrawer) {
        drawerToggleBtn.addEventListener('click', function(e) {
            if(window.innerWidth >= 1024) {
                e.preventDefault(); 
                mainDrawer.classList.toggle('is-drawer-close');
                localStorage.setItem('admin_sidebar_collapsed', mainDrawer.classList.contains('is-drawer-close'));
            }
        });
    }
});
</script>
