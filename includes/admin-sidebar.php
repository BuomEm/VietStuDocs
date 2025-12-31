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
            <a href="index.php" class="flex items-center gap-2 text-xl font-bold">
                <i class="fa-solid fa-file-contract text-lg"></i>
                <span>DocShare</span>
                <span class="badge badge-sm badge-secondary ml-2">ADMIN</span>
            </a>
        </div>
        
        <!-- Navigation Menu -->
        <ul class="menu p-4 w-full">
            <!-- Dashboard -->
            <li>
                <a href="index.php" class="<?= $admin_active_page === 'dashboard' ? 'active' : '' ?>">
                    <i class="fa-solid fa-house w-5 h-5"></i>
                    Dashboard
                </a>
            </li>
            
            <!-- Pending Documents -->
            <li>
                <a href="pending-docs.php" class="<?= $admin_active_page === 'pending' ? 'active' : '' ?>">
                    <i class="fa-solid fa-clock-rotate-left w-5 h-5"></i>
                    Chờ duyệt
                    <?php if($admin_pending_count > 0): ?>
                        <span class="badge badge-sm badge-warning"><?= intval($admin_pending_count) ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- All Documents -->
            <li>
                <a href="all-documents.php" class="<?= $admin_active_page === 'documents' ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-lines w-5 h-5"></i>
                    Tài liệu
                </a>
            </li>
            
            <!-- Users -->
            <li>
                <a href="users.php" class="<?= $admin_active_page === 'users' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users w-5 h-5"></i>
                    Người dùng
                </a>
            </li>
            
            <!-- Categories -->
            <li>
                <a href="categories.php" class="<?= $admin_active_page === 'categories' ? 'active' : '' ?>">
                    <i class="fa-solid fa-layer-group w-5 h-5"></i>
                    Phân loại
                </a>
            </li>
            
            <!-- Reports -->
            <li>
                <a href="reports.php" class="<?= $admin_active_page === 'reports' ? 'active' : '' ?>">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Báo cáo
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
                <a href="transactions.php" class="<?= $admin_active_page === 'transactions' ? 'active' : '' ?>">
                    <i class="fa-solid fa-money-bill-transfer w-5 h-5"></i>
                    Giao dịch
                </a>
            </li>
            
            <!-- Notifications -->
            <li>
                <a href="notifications.php" class="<?= $admin_active_page === 'notifications' ? 'active' : '' ?>">
                    <i class="fa-solid fa-bell w-5 h-5"></i>
                    Thông báo
                    <?php if($unread_notifications > 0): ?>
                        <span class="badge badge-sm badge-error"><?= intval($unread_notifications) ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Divider -->
            <li class="mt-4"><hr class="border-base-300"></li>

            <!-- Settings -->
            <li>
                <a href="settings.php" class="<?= $admin_active_page === 'settings' ? 'active' : '' ?>">
                    <i class="fa-solid fa-gear w-5 h-5"></i>
                    Cài đặt hệ thống
                </a>
            </li>
            
            <!-- Divider -->
            <li class="mt-2"><hr class="border-base-300"></li>
            
            <!-- Back to Site -->
            <li>
                <a href="../dashboard.php">
                    <i class="fa-solid fa-arrow-left w-5 h-5"></i>
                    Quay lại trang chính
                </a>
            </li>
            
            <!-- Logout -->
            <li>
                <a href="../logout.php" class="text-error">
                    <i class="fa-solid fa-right-from-bracket w-5 h-5"></i>
                    Đăng xuất
                </a>
            </li>
        </ul>
        
        <!-- User info at bottom (desktop only) -->
        <div class="mt-auto p-4 border-t border-base-300 hidden lg:block">
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
    </aside>
</div>
