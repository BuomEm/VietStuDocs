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
<html lang="vi" data-theme="dim">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title><?= htmlspecialchars($page_title) ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            daisyui: {
                themes: [
                    {
                        dim: {
                            "primary": "#96f1b0",
                            "primary-content": "#1a3a24",
                            "secondary": "#ff9e88",
                            "secondary-content": "#4a1d14",
                            "accent": "#f397ff",
                            "accent-content": "#3d1341",
                            "neutral": "#2a2e37",
                            "neutral-content": "#d1d5db",
                            "base-100": "#2a303c",
                            "base-200": "#242933",
                            "base-300": "#1d232a",
                            "base-content": "#d1d5db",
                            "info": "#7dd3f7",
                            "info-content": "#082f49",
                            "success": "#34d399",
                            "success-content": "#064e3b",
                            "warning": "#fbbf24",
                            "warning-content": "#451a03",
                            "error": "#fb7185",
                            "error-content": "#4c0519",
                            "--radius-selector": "1rem",
                            "--radius-field": "0.5rem",
                            "--radius-box": "1rem",
                            "--size-selector": "0.25rem",
                            "--size-field": "0.25rem",
                            "--border": "1px",
                            "--depth": "0",
                            "--noise": "0",
                        },
                    },
                ],
            },
        }
    </script>
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

        /* Badge Contrast Fix */
        .badge {
            font-weight: 600;
        }
        .badge.bg-primary, .badge.badge-primary { color: #1a3a24 !important; }
        .badge.bg-secondary, .badge.badge-secondary { color: #4a1d14 !important; }
        .badge.bg-accent, .badge.badge-accent { color: #3d1341 !important; }
        .badge.bg-info, .badge.badge-info { color: #082f49 !important; }
        .badge.bg-success, .badge.badge-success { color: #064e3b !important; }
        .badge.bg-warning, .badge.badge-warning { color: #451a03 !important; }
        .badge.bg-error, .badge.badge-error { color: #4c0519 !important; }
        
        /* Ensure NEW badge stands out */
        .badge-error {
            box-shadow: 0 0 10px rgba(251, 113, 133, 0.2);
        }

        /* Pulse animation for notifications */
        @keyframes pulse {
            0% { transform: scale(0.95); opacity: 0.7; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.7; }
        }
        .pulse {
            animation: pulse 2s infinite;
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
            <!-- Admin Navbar -->
            <?php include __DIR__ . '/admin-navbar.php'; ?>
            
            <!-- Main Content -->
            <div class="flex-1 bg-base-200 min-h-screen">

