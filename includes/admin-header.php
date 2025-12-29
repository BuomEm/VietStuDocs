<?php
/**
 * Admin Panel Header - Tabler UI
 * Include this at the top of each admin page
 * 
 * Required variables before including:
 * - $page_title (optional): Page title
 * - $admin_active_page (optional): Current active page for sidebar highlighting
 */

// Ensure required variables are set
if (!isset($page_title)) $page_title = 'Admin Panel - DocShare';
if (!isset($admin_active_page)) $admin_active_page = '';
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title><?= htmlspecialchars($page_title) ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- DaisyUI -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <!-- Font Awesome -->
    <link rel="stylesheet" href="../css/fontawesome/all.css" />
    <script src="../css/fontawesome/all.js"></script>
    
    <!-- Custom Admin Styles -->
    <style>
        /* Drawer styles */
        .drawer {
            position: relative;
        }
        .drawer-content {
            position: relative;
        }
    </style>
</head>
<body class="min-h-screen bg-base-200">
    <div class="drawer lg:drawer-open">
        <input id="drawer-toggle" type="checkbox" class="drawer-toggle" />
        
        <!-- Sidebar -->
        <?php include __DIR__ . '/admin-sidebar.php'; ?>
        
        <!-- Page Wrapper -->
        <div class="drawer-content flex flex-col">
            <!-- Mobile Navbar Toggle -->
            <div class="navbar bg-base-100 lg:hidden shadow-sm">
                <div class="flex-none">
                    <label for="drawer-toggle" class="btn btn-square btn-ghost">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </label>
                </div>
                <div class="flex-1">
                    <a href="index.php" class="btn btn-ghost text-xl">DocShare Admin</a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="flex-1 bg-base-200 min-h-screen">

