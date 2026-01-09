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
    <?php
    require_once __DIR__ . '/../config/settings.php';
    $site_name = function_exists('getSetting') ? getSetting('site_name', 'DocShare') : 'DocShare';
    $site_logo = function_exists('getSetting') ? getSetting('site_logo') : '/favicon.ico';
    $site_logo = !empty($site_logo) ? $site_logo : '/favicon.ico';
    
    // Clean up page title
    $clean_title = isset($page_title) ? str_replace([' - DocShare Admin', ' - DocShare', 'Admin Panel - '], '', $page_title) : 'Admin Panel';
    if ($clean_title == 'Admin Panel - DocShare') $clean_title = 'Admin Panel';
    
    $display_title = "$site_name | $clean_title";
    ?>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title><?= htmlspecialchars($display_title) ?></title>
    <link rel="icon" href="<?= htmlspecialchars($site_logo) ?>">
    <link rel="shortcut icon" href="<?= htmlspecialchars($site_logo) ?>">
    
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

        /* Collapsible Sidebar Styles */
        .drawer-side aside {
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-x: hidden;
            width: 16rem;
        }

        /* Icon Only Mode - Collapsed */
        .is-drawer-close .drawer-side aside {
            width: 5rem !important; /* w-20 */
        }
        
        /* Text Hiding Logic */
        .is-drawer-close .drawer-side .menu-text,
        .is-drawer-close .drawer-side .logo-text,
        .is-drawer-close .drawer-side .badge,
        .is-drawer-close .drawer-side .stats,
        .is-drawer-close .drawer-side hr {
            display: none !important;
            opacity: 0;
        }

        /* Profile Info Hiding Logic */
        .is-drawer-close .drawer-side .profile-info {
            display: none !important;
        }

        /* Adjust Menu for Collapsed State */
        .is-drawer-close .drawer-side .menu li a {
            justify-content: center;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
            min-height: 3rem;
        }
        
        .is-drawer-close .drawer-side .menu li a i {
            margin-right: 0;
            font-size: 1.25rem;
        }

        /* Tooltip behavior for collapsed state */
        .is-drawer-close .drawer-side .menu li a:hover::after {
            content: attr(data-tip);
            position: absolute;
            left: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            background: oklch(var(--n));
            color: oklch(var(--nc));
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            white-space: nowrap;
            z-index: 100;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            pointer-events: none;
        }

        /* Triangle for Tooltip */
        .is-drawer-close .drawer-side .menu li a:hover::before {
            content: '';
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            border-width: 5px;
            border-style: solid;
            border-color: transparent oklch(var(--n)) transparent transparent;
            z-index: 100;
            margin-left: 0;
            pointer-events: none;
        }

        /* Custom animations and utilities for admin pages */
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .animate-bounce-slow {
            animation: bounce-slow 2s ease-in-out infinite;
        }

        @keyframes bounce-slow {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        /* Enhanced card hover effects */
        .card:hover {
            transform: translateY(-2px) scale(1.01);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Gradient text utilities */
        .bg-clip-text {
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Glass morphism effects */
        .backdrop-blur-sm {
            backdrop-filter: blur(4px);
        }

        .backdrop-blur-xl {
            backdrop-filter: blur(24px);
        }

        /* Custom badge styles */
        .badge-lg {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        /* Stats card enhancements */
        .stat {
            padding: 1rem;
        }

        .stat-figure {
            margin-bottom: 0.5rem;
        }

        .stat-title {
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
    </style>
    <script>
    /**
     * Global Anti-Double-Submit Protection
     */
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.classList.contains('allow-double-submit')) return;
        if (form.dataset.isSubmitting === 'true') {
            e.preventDefault();
            return false;
        }
        if (form.checkValidity()) {
            form.dataset.isSubmitting = 'true';
            const submitBtns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            submitBtns.forEach(btn => {
                if (btn.tagName === 'BUTTON') {
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> Đang xử lý...';
                    btn.dataset.originalContent = originalText;
                }
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            });
            setTimeout(() => {
                form.dataset.isSubmitting = 'false';
                submitBtns.forEach(btn => {
                    btn.disabled = false;
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                    if (btn.dataset.originalContent) btn.innerHTML = btn.dataset.originalContent;
                });
            }, 10000);
        }
    });
    </script>
</head>
<body class="min-h-screen bg-base-200">
    <div class="drawer lg:drawer-open" id="main-drawer">
        <input id="drawer-toggle" type="checkbox" class="drawer-toggle" />
        
        <!-- Sidebar -->
        <?php include __DIR__ . '/admin-sidebar.php'; ?>
        
        <!-- Page Wrapper -->
        <div class="drawer-content flex flex-col">
            <!-- Admin Navbar -->
            <?php include __DIR__ . '/admin-navbar.php'; ?>
            
            <!-- Main Content -->
            <div class="flex-1 bg-base-200 min-h-screen">

