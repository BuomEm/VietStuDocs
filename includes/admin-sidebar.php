<?php
// Shared Admin Sidebar + basic mobile improvements
if (!isset($admin_active_page)) $admin_active_page = '';
if (!isset($unread_notifications)) $unread_notifications = 0;
if (!isset($admin_pending_count)) {
    // Try to use helper if available
    if (function_exists('getPendingDocumentsCount')) {
        $admin_pending_count = getPendingDocumentsCount();
    } else {
        $admin_pending_count = 0;
    }
}
?>

<style>
    /* Extra mobile-friendly tweaks for all admin pages */
    @media (max-width: 768px) {
        .admin-container {
            flex-direction: column;
        }
        .admin-sidebar {
            position: static;
            width: 100% !important;
            height: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .admin-content {
            margin-left: 0 !important;
            padding: 20px !important;
        }
        .admin-sidebar nav a {
            display: inline-block;
            width: auto;
            margin-right: 6px;
        }
        .admin-sidebar .logout {
            border-top: 1px solid rgba(255,255,255,0.25);
            margin-top: 10px;
            padding-top: 10px;
        }
    }
</style>

<div class="admin-sidebar">
    <h2>🔧 Admin Panel</h2>
    <nav>
        <a href="index.php" class="<?= $admin_active_page === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
        <a href="pending-docs.php" class="<?= $admin_active_page === 'pending' ? 'active' : '' ?>">
            📋 Pending Documents<?= $admin_pending_count > 0 ? ' (' . intval($admin_pending_count) . ')' : '' ?>
        </a>
        <a href="all-documents.php" class="<?= $admin_active_page === 'documents' ? 'active' : '' ?>">📚 All Documents</a>
        <a href="users.php" class="<?= $admin_active_page === 'users' ? 'active' : '' ?>">👥 Users</a>
        <a href="categories.php" class="<?= $admin_active_page === 'categories' ? 'active' : '' ?>">📂 Categories</a>
        <a href="transactions.php" class="<?= $admin_active_page === 'transactions' ? 'active' : '' ?>">💰 Transactions</a>
        <a href="notifications.php" class="<?= $admin_active_page === 'notifications' ? 'active' : '' ?>">
            🔔 Notifications
            <?php if($unread_notifications > 0): ?>
                <span style="display: inline-block; background: #ff4444; color: white; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-left: 5px;">
                    <?= intval($unread_notifications) ?>
                </span>
            <?php endif; ?>
        </a>
        <div class="logout">
            <a href="../logout.php">🚪 Logout</a>
        </div>
    </nav>
</div>


