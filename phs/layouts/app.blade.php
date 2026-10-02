<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <title>@yield('title', 'KLAES - Property History Search Portal')</title>
    <script>
        (function() {
            const t = localStorage.getItem('phs-theme');
            if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches))
                document.documentElement.classList.add('dark');
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: "#3b82f6",
                        "primary-foreground": "#ffffff",
                        muted: "#f3f4f6",
                        "muted-foreground": "#6b7280",
                        border: "#e5e7eb",
                        destructive: "#ef4444",
                        "destructive-foreground": "#ffffff",
                        secondary: "#f1f5f9",
                        "secondary-foreground": "#0f172a",
                    },
                },
            },
        };

        function phsToggleTheme() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('phs-theme', isDark ? 'dark' : 'light');
            if (window.lucide) window.lucide.createIcons();
        }
    </script>

    <style>
        :root {
            --phs-border: #e5e7eb;
            --phs-timeline-dot: #3b82f6;
            --phs-timeline-dot-border: white;
            --phs-timeline-dot-shadow: #e5e7eb;
            --phs-spinner-track: #e5e7eb;
            --phs-spinner-head: #3b82f6;
            --phs-table-hover: #f8fafc;
            --phs-preloader-bg: #ffffff;
        }
        .dark {
            --phs-border: #374151;
            --phs-timeline-dot: #60a5fa;
            --phs-timeline-dot-border: #1f2937;
            --phs-timeline-dot-shadow: #374151;
            --phs-spinner-track: #374151;
            --phs-spinner-head: #60a5fa;
            --phs-table-hover: #1f2937;
            --phs-preloader-bg: #111827;
        }

        .loading-spinner {
            width: 1rem;
            height: 1rem;
            border: 2px solid var(--phs-spinner-track);
            border-top: 2px solid var(--phs-spinner-head);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .timeline-item {
            border-left: 2px solid var(--phs-border);
            position: relative;
            padding-left: 1.5rem;
            padding-bottom: 1.5rem;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-item::before {
            content: "";
            position: absolute;
            left: -0.5rem;
            top: 0;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: var(--phs-timeline-dot);
            border: 2px solid var(--phs-timeline-dot-border);
            box-shadow: 0 0 0 2px var(--phs-timeline-dot-shadow);
        }

        .timeline-item.completed::before {
            background: #10b981;
        }

        .token-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .package-card {
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .package-card.selected {
            border: 2px solid #3b82f6 !important;
            background-color: #eff6ff;
        }
        .dark .package-card.selected {
            background-color: #1e3a5f;
        }

        .page {
            transition: opacity 0.3s ease;
        }

        .page-hidden {
            display: none;
        }

        .slider-container {
            position: relative;
            width: 100%;
            height: 500px;
            overflow: hidden;
            border-radius: 1rem;
        }

        .slide {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 0.5s ease-in-out;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .slide.active {
            opacity: 1;
        }

        .slide-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .slider-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(5px);
            color: white;
            border: none;
            padding: 1rem;
            cursor: pointer;
            border-radius: 50%;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .slider-btn:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: translateY(-50%) scale(1.1);
        }

        .slider-btn.prev {
            left: 1rem;
        }

        .slider-btn.next {
            right: 1rem;
        }

        .slider-dots {
            position: absolute;
            bottom: 1rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 0.5rem;
            z-index: 10;
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .dot.active {
            background: white;
            width: 20px;
            border-radius: 10px;
        }

        .mobile-menu-open {
            max-height: 300px;
            opacity: 1;
            visibility: visible;
        }

        .mobile-menu-closed {
            max-height: 0;
            opacity: 0;
            visibility: hidden;
        }

        .result-card {
            transition: all 0.3s ease;
        }

        .result-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -12px rgba(0, 0, 0, 0.15);
        }

        @media (max-width: 768px) {
            .slider-container {
                height: 300px;
            }
            
            .slide-content h3 {
                font-size: 1.25rem;
            }
            
            .slide-content p {
                font-size: 0.875rem;
            }
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .print-container {
                padding: 20px;
            }

            .timeline-item {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }

        .modal-overlay {
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .slide-in {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tab-active {
            border-bottom: 2px solid #3b82f6;
            color: #3b82f6;
        }

        .token-usage-bar {
            transition: width 0.5s ease;
        }

        .modal-scrollable {
            max-height: 70vh;
            overflow-y: auto;
        }

        .preview-card {
            transition: all 0.3s ease;
        }

        .preview-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .table-row {
            transition: all 0.2s ease;
        }

        .table-row:hover {
            background-color: var(--phs-table-hover);
        }

        .dropzone-active {
            border-color: #3b82f6 !important;
            background-color: #eff6ff !important;
        }

        .dropzone {
            transition: all 0.2s ease;
        }

        .user-card {
            transition: all 0.3s ease;
        }

        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>

    @yield('extra_styles')
</head>

<body class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-green-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 dark:text-gray-100">

    @yield('content')

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // CSRF token setup for AJAX
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    </script>

    @yield('extra_scripts')

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                setTimeout(function () {
                    preloader.style.opacity = '0';
                    preloader.style.transition = 'opacity 0.3s ease';
                    setTimeout(function () { preloader.style.display = 'none'; }, 300);
                }, 500);
            }
        });
    </script>
</body>
</html>
