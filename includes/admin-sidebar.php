<?php
/**
 * Admin Sidebar - DaisyUI
 * Vertical navigation sidebar for admin panel
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

// Get current admin username if available
$admin_username = function_exists('getCurrentUsername') ? getCurrentUsername() : 'Admin';
?>

<!-- Drawer Side -->
<div class="drawer-side z-50">
    <label for="drawer-toggle" class="drawer-overlay"></label>
    <aside class="w-64 min-h-full bg-base-100 border-r border-base-300 text-base-content">
        <!-- Sidebar Header -->
        <div class="p-4 border-b border-base-300">
            <?php 
            $site_name = function_exists('getSetting') ? getSetting('site_name', 'DocShare') : 'DocShare';
            $site_logo = function_exists('getSetting') ? getSetting('site_logo') : '';
            ?>
            <a href="index.php" class="flex items-center justify-center gap-2 text-xl font-bold overflow-hidden whitespace-nowrap h-12 w-full hover:bg-base-200 transition-all">
                <?php if (!empty($site_logo)): ?>
                    <img src="<?= htmlspecialchars($site_logo) ?>" alt="Logo" class="h-8 w-8 object-contain flex-shrink-0">
                <?php else: ?>
                    <i class="fa-solid fa-file-contract text-lg w-8 flex-shrink-0 text-center"></i>
                <?php endif; ?>
                <div class="flex flex-col logo-text">
                    <span class="transition-opacity duration-300"><?= htmlspecialchars($site_name) ?></span>
                    <!-- <span class="badge badge-xs badge-secondary">ADMIN</span> -->
                </div>
            </a>
        </div>
        
        <!-- Navigation Menu -->
        <ul class="menu p-4 w-full">
            <!-- Dashboard -->
            <li>
                <a href="index.php" class="<?= $admin_active_page === 'dashboard' ? 'active' : '' ?>" data-tip="Dashboard">
                    <i class="fa-solid fa-house w-5 h-5"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            
            <!-- Pending Documents -->
            <li>
                <a href="pending-docs.php" class="<?= $admin_active_page === 'pending' ? 'active' : '' ?>" data-tip="Chờ duyệt">
                    <i class="fa-solid fa-clock-rotate-left w-5 h-5"></i>
                    <span class="menu-text">Chờ duyệt</span>
                    <?php if($admin_pending_count > 0): ?>
                        <span class="badge badge-sm badge-warning"><?= intval($admin_pending_count) ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- All Documents -->
            <li>
                <a href="all-documents.php" class="<?= $admin_active_page === 'documents' ? 'active' : '' ?>" data-tip="Tài liệu">
                    <i class="fa-solid fa-file-lines w-5 h-5"></i>
                    <span class="menu-text">Tài liệu</span>
                </a>
            </li>
            
            <!-- Tutors (New) -->
            <li>
                <a href="tutors.php" class="<?= $admin_active_page === 'tutors' ? 'active' : '' ?>" data-tip="Quản lý Gia sư">
                    <i class="fa-solid fa-chalkboard-user w-5 h-5"></i>
                    <span class="menu-text">Quản lý Gia sư</span>
                    <?php 
                    $pending_tutors_query = mysqli_query($GLOBALS['conn'], "SELECT COUNT(*) as count FROM tutors WHERE status='pending'");
                    $pending_tutors = mysqli_fetch_assoc($pending_tutors_query)['count'] ?? 0;
                    if($pending_tutors > 0): 
                    ?>
                        <span class="badge badge-sm badge-warning"><?= $pending_tutors ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <!-- Tutor Requests (Disputes) -->
            <li>
                <a href="tutor_requests.php" class="<?= $admin_active_page === 'tutor_requests' ? 'active' : '' ?>" data-tip="Hỏi đáp & Khiếu nại">
                    <i class="fa-solid fa-gavel w-5 h-5"></i>
                    <span class="menu-text">Hỏi đáp & Khiếu nại</span>
                    <?php 
                    try {
                        $pdo_check = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
                        $stmt = $pdo_check->query("SELECT COUNT(*) FROM tutor_requests WHERE status = 'disputed'");
                        $count_disputed = $stmt->fetchColumn();
                        if($count_disputed > 0): 
                        ?>
                            <span class="badge badge-sm badge-error"><?= $count_disputed ?></span>
                        <?php 
                        endif;
                    } catch(Exception $e) {} 
                    ?>
                </a>
            </li>
            
            <!-- Users -->
            <li>
                <a href="users.php" class="<?= $admin_active_page === 'users' ? 'active' : '' ?>" data-tip="Người dùng">
                    <i class="fa-solid fa-users w-5 h-5"></i>
                    <span class="menu-text">Người dùng</span>
                </a>
            </li>
            
            <!-- Categories -->
            <li>
                <a href="categories.php" class="<?= $admin_active_page === 'categories' ? 'active' : '' ?>" data-tip="Phân loại">
                    <i class="fa-solid fa-layer-group w-5 h-5"></i>
                    <span class="menu-text">Phân loại</span>
                </a>
            </li>
            
            <!-- Reports -->
            <li>
                <a href="reports.php" class="<?= $admin_active_page === 'reports' ? 'active' : '' ?>" data-tip="Báo cáo">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span class="menu-text">Báo cáo</span>
                    <?php 
                    $pending_reports_count = mysqli_num_rows(mysqli_query($GLOBALS['conn'], "SELECT id FROM reports WHERE status='pending'"));
                    if($pending_reports_count > 0): 
                    ?>
                        <span class="badge badge-sm badge-error"><?= $pending_reports_count ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Transactions -->
            <li>
                <a href="transactions.php" class="<?= $admin_active_page === 'transactions' ? 'active' : '' ?>" data-tip="Giao dịch">
                    <i class="fa-solid fa-money-bill-transfer w-5 h-5"></i>
                    <span class="menu-text">Giao dịch</span>
                </a>
            </li>
            
            <!-- Notifications -->
            <li>
                <a href="notifications.php" class="<?= $admin_active_page === 'notifications' ? 'active' : '' ?>" data-tip="Thông báo">
                    <i class="fa-solid fa-bell w-5 h-5"></i>
                    <span class="menu-text">Thông báo</span>
                    <?php if($unread_notifications > 0): ?>
                        <span class="badge badge-sm badge-error"><?= intval($unread_notifications) ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Divider -->
            <li class="mt-4"><hr class="border-base-300"></li>

            <!-- Settings -->
            <li>
                <a href="settings.php" class="<?= $admin_active_page === 'settings' ? 'active' : '' ?>" data-tip="Cài đặt hệ thống">
                    <i class="fa-solid fa-gear w-5 h-5"></i>
                    <span class="menu-text">Cài đặt hệ thống</span>
                </a>
            </li>
            
            <!-- Divider -->
            <li class="mt-2"><hr class="border-base-300"></li>
            
            <!-- Back to Site -->
            <li>
                <a href="../dashboard.php" data-tip="Quay lại trang chính">
                    <i class="fa-solid fa-arrow-left w-5 h-5"></i>
                    <span class="menu-text">Quay lại trang chính</span>
                </a>
            </li>
            
            <!-- Logout -->
            <li>
                <a href="../logout.php" class="text-error" data-tip="Đăng xuất">
                    <i class="fa-solid fa-right-from-bracket w-5 h-5"></i>
                    <span class="menu-text">Đăng xuất</span>
                </a>
            </li>
        </ul>
        
        <!-- User info at bottom (desktop only) -->
        <div class="mt-auto p-4 border-t border-base-300 hidden lg:block profile-info">
            <div class="flex items-center gap-3">
                <div class="avatar placeholder">
                    <div class="bg-secondary text-secondary-content rounded-full w-10">
                        <span class="text-sm font-bold"><?= strtoupper(substr($admin_username, 0, 2)) ?></span>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="font-semibold"><?= htmlspecialchars($admin_username) ?></div>
                    <div class="text-xs opacity-70">Administrator</div>
                </div>
            </div>
        </div>
<?php /* This uses the same script from sidebar.php which should be included or defined here too */ ?>
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
    </aside>
</div>
