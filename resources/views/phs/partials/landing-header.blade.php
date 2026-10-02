{{-- Shared public/landing header for PHS marketing pages (landing, get-started). --}}
<nav class="sticky top-0 z-20 bg-white/80 dark:bg-gray-900/90 backdrop-blur-md shadow-sm border-b border-transparent dark:border-gray-700">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-28 items-center justify-between">
            <a href="{{ route('phs.landing') }}" class="flex items-center">
                <img src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" alt="PHS Portal" class="h-24 w-auto object-contain dark:hidden">
                <img src="{{ asset('assets/logo/phs-dark-logo.jpeg') }}" alt="PHS Portal" class="h-24 w-auto object-contain hidden dark:block">
            </a>

            <!-- Desktop Menu -->
            <div class="hidden items-center space-x-3 md:flex">
                <a href="{{ route('phs.login') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 transition-all hover:bg-gray-50 dark:hover:bg-gray-700">
                    Sign In
                </a>
                <a href="{{ route('phs.get_started') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 transition-all hover:bg-gray-50 dark:hover:bg-gray-700">
                    Get Started
                </a>
                <a href="{{ route('phs.request.form') }}" class="inline-flex items-center justify-center rounded-md border-0 bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-blue-700">
                    Request Access
                </a>
                <button onclick="phsToggleTheme()" title="Toggle dark mode"
                    class="rounded-md p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <i data-lucide="sun" class="h-4 w-4 dark:hidden"></i>
                    <i data-lucide="moon" class="h-4 w-4 hidden dark:block"></i>
                </button>
            </div>

            <!-- Mobile right side -->
            <div class="flex items-center gap-2 md:hidden">
                <button onclick="phsToggleTheme()" title="Toggle dark mode"
                    class="rounded-md p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <i data-lucide="sun" class="h-4 w-4 dark:hidden"></i>
                    <i data-lucide="moon" class="h-4 w-4 hidden dark:block"></i>
                </button>
                <button id="mobile-menu-btn" class="rounded-lg p-2 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i data-lucide="menu" class="h-6 w-6 text-gray-700 dark:text-gray-300"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobile-menu" class="invisible max-h-0 overflow-hidden border-t border-gray-200 dark:border-gray-700 opacity-0 transition-all duration-300 ease-in-out md:hidden">
            <div class="space-y-3 py-4">
                <a href="{{ route('phs.login') }}" class="block rounded-md border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2 text-center text-sm font-medium text-gray-700 dark:text-gray-300 transition-all hover:bg-gray-50 dark:hover:bg-gray-700">
                    Sign In
                </a>
                <a href="{{ route('phs.get_started') }}" class="block rounded-md border border-gray-300 dark:border-gray-600 bg-transparent px-4 py-2 text-center text-sm font-medium text-gray-700 dark:text-gray-300 transition-all hover:bg-gray-50 dark:hover:bg-gray-700">
                    Get Started
                </a>
                <a href="{{ route('phs.request.form') }}" class="block rounded-md border-0 bg-blue-600 px-4 py-2 text-center text-sm font-medium text-white transition-all hover:bg-blue-700">
                    Request Access
                </a>
            </div>
        </div>
    </div>
</nav>

<script>
    document.getElementById('mobile-menu-btn')?.addEventListener('click', function () {
        const menu = document.getElementById('mobile-menu');
        if (!menu) return;
        menu.classList.toggle('invisible');
        menu.classList.toggle('max-h-0');
        menu.classList.toggle('opacity-0');
    });
</script>
