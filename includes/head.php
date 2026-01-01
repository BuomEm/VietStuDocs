<!DOCTYPE html>
<html lang="en" data-theme="vietstudocs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'DocShare' ?></title>
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
    </style>
</head>
<body class="min-h-screen bg-base-200" data-loggedin="<?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>">
