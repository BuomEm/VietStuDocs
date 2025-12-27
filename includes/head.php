<!DOCTYPE html>
<html lang="en" data-theme="cupcake">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'DocShare' ?></title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- DaisyUI -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet" type="text/css" />
    <!-- Font Awesome 7.0.1 -->
    <link rel="stylesheet" href="./css/fontawesome/all.css" integrity="sha512-1PKOgIY59xJ8Co8+NE6FZ+LOAZKjy+KY8iq0G4B3CyeY6wYHN3yt9PW0XpSriVlkMXe40PTKnXrLnZ9+fkDaog==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="./css/fontawesome/all.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            daisyui: {
                themes: [
                    {
                        cupcake: {
                            "primary": "oklch(85% 0.138 181.071)",
                            "primary-content": "oklch(43% 0.078 188.216)",
                            "secondary": "oklch(89% 0.061 343.231)",
                            "secondary-content": "oklch(45% 0.187 3.815)",
                            "accent": "oklch(90% 0.076 70.697)",
                            "accent-content": "oklch(47% 0.157 37.304)",
                            "neutral": "oklch(27% 0.006 286.033)",
                            "neutral-content": "oklch(92% 0.004 286.32)",
                            "base-100": "oklch(97.788% 0.004 56.375)",
                            "base-200": "oklch(93.982% 0.007 61.449)",
                            "base-300": "oklch(91.586% 0.006 53.44)",
                            "base-content": "oklch(23.574% 0.066 313.189)",
                            "info": "oklch(68% 0.169 237.323)",
                            "info-content": "oklch(29% 0.066 243.157)",
                            "success": "oklch(69% 0.17 162.48)",
                            "success-content": "oklch(26% 0.051 172.552)",
                            "warning": "oklch(79% 0.184 86.047)",
                            "warning-content": "oklch(28% 0.066 53.813)",
                            "error": "oklch(64% 0.246 16.439)",
                            "error-content": "oklch(27% 0.105 12.094)",
                            "--rounded-box": "1rem",
                            "--rounded-btn": "2rem",
                            "--rounded-badge": "1rem",
                            "--animation-btn": "0.25s",
                            "--animation-input": "0.2s",
                            "--btn-focus-scale": "0.95",
                            "--border-btn": "2px",
                            "--tab-border": "1px",
                            "--tab-radius": "0.5rem",
                        }
                    }
                ],
            },
        }
    </script>
    <style>
        /* Ensure drawer works correctly */
        .drawer {
            position: relative;
        }
        .drawer-content {
            position: relative;
        }
        /* Prevent duplicate navbars */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 40;
        }
    </style>
</head>
<body class="min-h-screen bg-base-200">