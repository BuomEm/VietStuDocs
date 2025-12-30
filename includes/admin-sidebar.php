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
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m-3-8h.01M4 6a2 2 0 012-2h8l4 4v10a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" />
                </svg>
                <span>DocShare</span>
                <span class="badge badge-sm badge-secondary ml-2">ADMIN</span>
            </a>
        </div>
        
        <!-- Navigation Menu -->
        <ul class="menu p-4 w-full">
            <!-- Dashboard -->
            <li>
                <a href="index.php" class="<?= $admin_active_page === 'dashboard' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Dashboard
                </a>
            </li>
            
            <!-- Pending Documents -->
            <li>
                <a href="pending-docs.php" class="<?= $admin_active_page === 'pending' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Chờ duyệt
                    <?php if($admin_pending_count > 0): ?>
                        <span class="badge badge-sm badge-warning"><?= intval($admin_pending_count) ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- All Documents -->
            <li>
                <a href="all-documents.php" class="<?= $admin_active_page === 'documents' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m-3-8h.01M4 6a2 2 0 012-2h8l4 4v10a2 2 0 01-2 2H6a2 2 0 01-2-2V6z" />
                    </svg>
                    Tài liệu
                </a>
            </li>
            
            <!-- Users -->
            <li>
                <a href="users.php" class="<?= $admin_active_page === 'users' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    Người dùng
                </a>
            </li>
            
            <!-- Categories -->
            <li>
                <a href="categories.php" class="<?= $admin_active_page === 'categories' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
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
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Giao dịch
                </a>
            </li>
            
            <!-- Notifications -->
            <li>
                <a href="notifications.php" class="<?= $admin_active_page === 'notifications' ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    Thông báo
                    <?php if($unread_notifications > 0): ?>
                        <span class="badge badge-sm badge-error"><?= intval($unread_notifications) ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Divider -->
            <li class="mt-4"><hr class="border-base-300"></li>
            
            <!-- Back to Site -->
            <li>
                <a href="../dashboard.php">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Quay lại trang chính
                </a>
            </li>
            
            <!-- Logout -->
            <li>
                <a href="../logout.php" class="text-error">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
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
