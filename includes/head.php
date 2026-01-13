<!DOCTYPE html>
<html lang="en" data-theme="vietstudocs">
<head>
    <?php
    require_once __DIR__ . '/../config/settings.php';
    if(function_exists('updateLastActivity')) {
        updateLastActivity();
    }
    $site_name = function_exists('getSetting') ? getSetting('site_name', 'DocShare') : 'DocShare';
    $site_logo = function_exists('getSetting') ? getSetting('site_logo') : '/favicon.ico';
    $site_logo = !empty($site_logo) ? $site_logo : '/favicon.ico';

    // Format title: Site Name | Page Title
    $display_title = $site_name;
    if (isset($page_title) && !empty($page_title)) {
        // Remove legacy suffixes if present to avoid duplication
        $clean_title = str_replace([' - DocShare', ' | DocShare'], '', $page_title);
        if ($clean_title !== $site_name) {
            $display_title = "$site_name | $clean_title";
        }
    }

    // Dynamic Description & Keywords
    $default_desc = function_exists('getSetting') ? getSetting('site_description', 'Nền tảng chia sẻ tài liệu học tập, giáo án, đề thi và luận văn chất lượng cao cho học sinh, sinh viên và giáo viên Việt Nam.') : 'Nền tảng chia sẻ tài liệu học tập hàng đầu Việt Nam';
    $display_description = isset($page_description) && !empty($page_description) ? $page_description : $default_desc;
    
    $default_keywords = "tài liệu học tập, đề thi, giáo án, luận văn, sách giáo khoa, bài giảng, đại học, thpt, thcs, tiểu học, vietstudocs, docshare";
    $display_keywords = isset($page_keywords) && !empty($page_keywords) ? $page_keywords . ", " . $default_keywords : $default_keywords;

    // Canonical URL
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $current_url = $protocol . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    // Remove query strings for canonical if needed, but keeping it simple for now
    $canonical_url = isset($page_canonical) ? $page_canonical : strtok($current_url, '?');
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Primary Meta Tags -->
    <title><?= htmlspecialchars($display_title) ?></title>
    <meta name="title" content="<?= htmlspecialchars($display_title) ?>">
    <meta name="description" content="<?= htmlspecialchars($display_description) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($display_keywords) ?>">
    <meta name="robots" content="index, follow">
    <meta name="language" content="Vietnamese">
    <meta name="author" content="<?= htmlspecialchars($site_name) ?>">
    
    <!-- Canonical -->
    <link rel="canonical" href="<?= htmlspecialchars($canonical_url) ?>" />

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars($current_url) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($display_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($display_description) ?>">
    <meta property="og:image" content="<?= $protocol . "://$_SERVER[HTTP_HOST]" . ($site_logo ?? '/assets/images/og-image.png') ?>">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?= htmlspecialchars($current_url) ?>">
    <meta property="twitter:title" content="<?= htmlspecialchars($display_title) ?>">
    <meta property="twitter:description" content="<?= htmlspecialchars($display_description) ?>">
    <meta property="twitter:image" content="<?= $protocol . "://$_SERVER[HTTP_HOST]" . ($site_logo ?? '/assets/images/og-image.png') ?>">

    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "<?= htmlspecialchars($site_name) ?>",
      "url": "<?= $protocol . "://$_SERVER[HTTP_HOST]" ?>",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "<?= $protocol . "://$_SERVER[HTTP_HOST]" ?>/search.php?q={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    }
    </script>
    <!-- DNS Preconnect -->
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdn.tailwindcss.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <?php 
    // Validate and normalize favicon path for security
    $favicon_path = $site_logo;
    $favicon_valid = false;
    $safe_default_path = '/favicon.ico';
    
    // Validation: path must be a non-empty string
    if (is_string($favicon_path) && strlen($favicon_path) > 0) {
        // Check for null bytes (potential null byte injection)
        if (strpos($favicon_path, "\0") === false) {
            // Ensure path starts with '/'
            if ($favicon_path[0] !== '/') {
                $favicon_path = '/' . $favicon_path;
            }
            
            // Normalize the path and check for directory traversal
            $normalized_path = str_replace(['\\', '//'], '/', $favicon_path);
            
            // Check for '..' sequences which indicate directory traversal
            if (strpos($normalized_path, '..') === false) {
                // Additional check: ensure the resolved path stays within document root
                $full_path = $_SERVER['DOCUMENT_ROOT'] . $normalized_path;
                $real_document_root = realpath($_SERVER['DOCUMENT_ROOT']);
                $real_full_path = realpath(dirname($full_path)) . DIRECTORY_SEPARATOR . basename($full_path);
                
                // Only proceed if realpath succeeds and path is within document root
                if ($real_document_root !== false && 
                    strpos($real_full_path, $real_document_root) === 0) {
                    $favicon_path = $normalized_path;
                    $favicon_valid = true;
                }
            }
        }
    }
    
    // Determine favicon version: use filemtime if valid and file exists, otherwise use time()
    if ($favicon_valid) {
        $full_favicon_path = $_SERVER['DOCUMENT_ROOT'] . $favicon_path;
        if (file_exists($full_favicon_path) && is_file($full_favicon_path)) {
            $favicon_version = filemtime($full_favicon_path);
        } else {
            // File doesn't exist, use current time
            $favicon_version = time();
        }
    } else {
        // Validation failed: fallback to safe default or treat as missing
        $favicon_path = $safe_default_path;
        $full_favicon_path = $_SERVER['DOCUMENT_ROOT'] . $favicon_path;
        if (file_exists($full_favicon_path) && is_file($full_favicon_path)) {
            $favicon_version = filemtime($full_favicon_path);
        } else {
            $favicon_version = time();
        }
    }
    
    $favicon_ext = strtolower(pathinfo($favicon_path, PATHINFO_EXTENSION));
    $favicon_type = 'image/x-icon';
    if ($favicon_ext === 'png') $favicon_type = 'image/png';
    elseif ($favicon_ext === 'jpg' || $favicon_ext === 'jpeg') $favicon_type = 'image/jpeg';
    elseif ($favicon_ext === 'svg') $favicon_type = 'image/svg+xml';
    elseif ($favicon_ext === 'gif') $favicon_type = 'image/gif';
    ?>
    <link rel="icon" type="<?= $favicon_type ?>" href="<?= htmlspecialchars($favicon_path) ?>?v=<?= $favicon_version ?>">
    <link rel="shortcut icon" type="<?= $favicon_type ?>" href="<?= htmlspecialchars($favicon_path) ?>?v=<?= $favicon_version ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($favicon_path) ?>?v=<?= $favicon_version ?>">
    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "oklch(40% 0.18 29)",
                        secondary: "oklch(80% 0.12 45)",
                        accent: "oklch(85% 0.18 90)",
                        neutral: "oklch(25% 0.02 30)",
                    }
                }
            },
            daisyui: {
                themes: [
                    {
                        vietstudocs: {
                            "primary": "#800000", /* Deep Red Marble */
                            "secondary": "#FFB88C", /* Soft Peach */
                            "accent": "#FFD700", /* Golden */
                            "neutral": "#2B2B2B",
                            "base-100": "#FFFBFA", /* Creamy White */
                            "info": "#3ABFF8",
                            "success": "#36D399",
                            "warning": "#FBBD23",
                            "error": "#F87272",
                        },
                    },
                ],
            },
        }
    </script>
    <!-- DaisyUI -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <!-- Font Awesome 7.1.0 (Local) -->
    <link rel="stylesheet" href="/css/fontawesome/all.css?v=7.1.0" />
    <script src="/css/fontawesome/all.js?v=7.1.0" defer></script>
    
    <style>
        /* Define vietstudocs theme using OKLCH (Standard for DaisyUI 4) */
        /* I have converted your HSL values to OKLCH to preserve the exact colors */
        [data-theme='vietstudocs'] {
            /* Primary: Đỏ đô (HSL 0 100% 25%) */
            --p: 40% 0.18 29;
            --pc: 100% 0 0;

            /* Secondary: Cam đào (HSL 18 91% 73%) */
            --s: 80% 0.12 45;
            --sc: 25% 0.02 20;

            /* Accent: Vàng ngôi sao (HSL 48 100% 50%) */
            --a: 85% 0.18 90;
            --ac: 100% 0 0;

            /* Neutral: Nâu đen (HSL 20 10% 15%) */
            --n: 25% 0.02 30;
            --nc: 90% 0.01 70;

            /* Base: Nền trắng kem (HSL 30 20% 98%) */
            --b1: 98% 0.01 70;
            --b2: 95% 0.02 70;
            --b3: 90% 0.03 70;
            --bc: 20% 0.02 20;

            /* Status (Converted to OKLCH) */
            --in: 72% 0.11 231;
            --su: 76% 0.15 153;
            --wa: 82% 0.15 80;
            --er: 62% 0.21 29;

            /* UI Settings */
            --rounded-box: 1rem;
            --rounded-btn: 0.5rem;
            --rounded-badge: 1.9rem;
            --animation-btn: 0.25s;
            --animation-input: 0.2s;
            --btn-text-case: none;
            --btn-focus-scale: 0.95;
            --border-btn: 1px;
            --tab-border: 1px;
            --tab-radius: 0.5rem;
        }

        /* Sticky navbar with theme-aware background */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 40;
            backdrop-filter: blur(8px);
            background-color: oklch(var(--b1) / 0.8);
            border-bottom: 1px solid oklch(var(--b3));
        }
        
        /* Ensure sidebar has correct base background and hide scrollbar */
        .drawer-side, 
        .drawer-side aside, 
        .drawer-side .menu {
            scrollbar-width: none !important; /* Firefox */
            -ms-overflow-style: none !important;  /* IE and Edge */
        }

        .drawer-side::-webkit-scrollbar, 
        .drawer-side aside::-webkit-scrollbar, 
        .drawer-side .menu::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
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
        
        /* Text Hiding Logic for Collapsed State */
        .is-drawer-close .drawer-side .menu-text,
        .is-drawer-close .drawer-side .logo-text,
        .is-drawer-close .drawer-side .badge,
        .is-drawer-close .drawer-side .stats,
        .is-drawer-close .drawer-side .points-card,
        .is-drawer-close .drawer-side li:has(hr),
        .is-drawer-close .drawer-side hr {
            display: none !important;
            height: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            opacity: 0;
            visibility: hidden;
        }

        /* Profile Info Hiding Logic */
        .is-drawer-close .drawer-side .profile-info {
            display: none !important;
        }

        .is-drawer-close .drawer-side .menu {
            padding-left: 0;
            padding-right: 0;
            align-items: stretch;
        }

        .is-drawer-close .drawer-side .menu li {
            width: 100%;
            display: block;
        }

        .is-drawer-close .drawer-side .menu li a {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-start !important;
            width: auto !important;
            height: auto !important;
            padding: 0.65rem 1rem !important;
            margin: 1px 0.75rem !important;
            border-radius: 1rem;
        }
        
        .is-drawer-close .drawer-side .menu li a i {
            margin: 0 !important;
            padding: 0 !important;
            width: 1.5rem !important;
            text-align: center !important;
            font-size: 1.1rem !important;
        }

        /* Hide active indicator dot when collapsed */
        .is-drawer-close .drawer-side .menu li a.active::after {
            display: none !important;
        }

        /* Logo alignment in collapsed state */
        .is-drawer-close .drawer-side aside .p-4 {
            padding: 1rem 0; /* Match standard padding */
            display: flex;
            justify-content: center;
        }

        .is-drawer-close .drawer-side aside .logo-text {
            display: none !important;
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

        /* Sidebar Link Aesthetics */
        .drawer-side .menu li a {
            font-weight: 600;
            color: oklch(var(--bc) / 0.7);
            padding: 0.65rem 1rem;
            margin: 1px 0.75rem;
            border-radius: 1rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }

        .drawer-side .menu li a:hover {
            background-color: oklch(var(--p) / 0.08);
            color: oklch(var(--p));
            border-color: oklch(var(--p) / 0.1);
        }

        .drawer-side .menu li a.active {
            background-color: oklch(var(--p));
            color: oklch(var(--pc));
            box-shadow: 0 8px 20px -4px oklch(var(--p) / 0.4);
            border-color: transparent;
            position: relative;
        }

        .drawer-side .menu li a i {
            width: 1.5rem;
            text-align: center;
            font-size: 1.1rem;
            opacity: 0.8;
            transition: transform 0.2s ease;
        }

        .drawer-side .menu li a.active i {
            opacity: 1;
            transform: scale(1.1);
        }

        .drawer-side .menu li a:hover i {
            transform: scale(1.1);
            opacity: 1;
        }

        /* Active Indicator Dot for Sidebar Items */
        .drawer-side .menu li a.active::after {
            content: '';
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            width: 5px;
            height: 5px;
            background-color: oklch(var(--pc));
            border-radius: 50%;
            box-shadow: 0 0 8px oklch(var(--pc) / 0.8);
            animation: dot-pulse 2s infinite;
        }

        @keyframes dot-pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.4); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Custom Premium Animations */
        @keyframes pulse-slow {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.05); }
        }
        .animate-pulse-slow {
            animation: pulse-slow 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes bounce-slow {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }
        .animate-bounce-slow {
            animation: bounce-slow 2s ease-in-out infinite;
        }

        /* Custom Modern Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: oklch(var(--b2));
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: oklch(var(--p) / 0.3);
            border-radius: 10px;
            border: 2px solid oklch(var(--b2));
            transition: all 0.3s ease;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: oklch(var(--p) / 0.6);
        }

        /* Firefox Support */
        * {
            scrollbar-width: thin;
            scrollbar-color: oklch(var(--p) / 0.3) oklch(var(--b2));
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/favico.js@0.3.10/favico.min.js"></script>
    <script src="/assets/js/notifications.js?v=9"></script>
    
    <script>
    /**
     * Global Anti-Double-Submit Protection
     * Automatically prevents multiple clicks on all forms across the site.
     */
    document.addEventListener('submit', function(e) {
        const form = e.target;
        
        // Skip for forms that should allow multiple submissions (if any)
        if (form.classList.contains('allow-double-submit')) return;

        // Check if form is already submitting
        if (form.dataset.isSubmitting === 'true') {
            e.preventDefault();
            return false;
        }

        // Only disable if the form passes browser validation
        if (form.checkValidity()) {
            form.dataset.isSubmitting = 'true';
            
            // Find submit buttons
            const submitBtns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            submitBtns.forEach(btn => {
                // Add loading state for DaisyUI buttons
                if (btn.tagName === 'BUTTON') {
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<span class="loading loading-spinner loading-xs"></span> ' + (btn.innerText || 'Processing...');
                    btn.dataset.originalContent = originalText;
                }
                btn.classList.add('opacity-50', 'cursor-not-allowed');
                // IMPORTANT: Disable in next tick so the name/value of the button is included in the POST request
                setTimeout(() => {
                    btn.disabled = true;
                }, 0);
            });
            
            // Failsafe: Re-enable buttons after 15 seconds if page doesn't reload
            setTimeout(() => {
                form.dataset.isSubmitting = 'false';
                submitBtns.forEach(btn => {
                    btn.disabled = false;
                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                    if (btn.dataset.originalContent) btn.innerHTML = btn.dataset.originalContent;
                });
            }, 15000);
        }
    });

    // Also handle AJAX requests (Fetch API) if you use them globally
    const _originalFetch = window.fetch;
    window.fetch = function() {
        return _originalFetch.apply(this, arguments).catch(err => {
            // If fetch fails, we might want to re-enable some global state here
            throw err;
        });
    };
    </script>
</head>
<body class="min-h-screen bg-base-200" 
      data-loggedin="<?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>"
      data-vapid-key="<?= $_ENV['VAPID_PUBLIC_KEY'] ?? '' ?>">
