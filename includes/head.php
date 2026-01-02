<!DOCTYPE html>
<html lang="en" data-theme="vietstudocs">
<head>
    <?php
    require_once __DIR__ . '/../config/settings.php';
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
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($display_title) ?></title>
    <link rel="icon" href="<?= htmlspecialchars($site_logo) ?>">
    <link rel="shortcut icon" href="<?= htmlspecialchars($site_logo) ?>">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- DaisyUI -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <!-- Font Awesome 7.0.1 -->
    <link rel="stylesheet" href="/css/fontawesome/all.css" />
    <script src="/css/fontawesome/all.js"></script>
    
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
        
        /* Ensure sidebar has correct base background */
        .drawer-side aside {
            background-color: oklch(var(--b1));
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
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed');
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
