<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('phs.partials.favicon')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('page-title', 'PHS Portal')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind = { config: { darkMode: 'class' } };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        (function() {
            const t = localStorage.getItem('phs-theme');
            if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches))
                document.documentElement.classList.add('dark');
        })();
    </script>
    <script src="https://unpkg.com/lucide@0.429.0/dist/umd/lucide.min.js" crossorigin="anonymous"></script>
    @stack('styles')
</head>
<body class="min-h-screen bg-slate-100 dark:bg-gray-900 text-slate-900 dark:text-gray-100 antialiased" style="font-family: Inter, sans-serif;">
    @yield('body')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });

        function phsToggleTheme() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('phs-theme', isDark ? 'dark' : 'light');
            if (window.lucide) window.lucide.createIcons();
        }
    </script>
    @stack('scripts')
</body>
</html>
