<header class="border-b border-slate-200 dark:border-gray-700 bg-white dark:bg-gray-900">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <a href="{{ route('phs.dashboard') }}" class="flex items-center gap-3">
            <img src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" alt="PHS Portal" class="h-16 w-auto object-contain dark:hidden">
            <img src="{{ asset('assets/logo/phs-dark-logo.jpeg') }}" alt="PHS Portal" class="h-16 w-auto object-contain hidden dark:block">
            <span>
                <span class="block text-sm font-extrabold leading-4 dark:text-gray-100">PHS Portal</span>
                <span class="block text-xs text-slate-500 dark:text-gray-400">{{ $institution->name ?? 'Institution' }}</span>
            </span>
        </a>
        <nav class="flex items-center gap-2">
            <a href="{{ route('phs.dashboard') }}" class="rounded-md px-3 py-2 text-sm font-semibold {{ request()->routeIs('phs.dashboard') ? 'bg-slate-100 dark:bg-gray-700 text-slate-950 dark:text-gray-100' : 'text-slate-600 dark:text-gray-300 hover:bg-slate-100 dark:hover:bg-gray-700' }}">Search</a>
            @if(($member ?? null) && $member->isSuperAdmin())
                <a href="{{ route('phs.org.index') }}" class="rounded-md px-3 py-2 text-sm font-semibold {{ request()->routeIs('phs.org.*') ? 'bg-slate-100 dark:bg-gray-700 text-slate-950 dark:text-gray-100' : 'text-slate-600 dark:text-gray-300 hover:bg-slate-100 dark:hover:bg-gray-700' }}">Organization</a>
            @endif

            <!-- Dark mode toggle -->
            <button onclick="phsToggleTheme()" title="Toggle dark mode"
                class="rounded-md p-2 text-slate-600 dark:text-gray-300 hover:bg-slate-100 dark:hover:bg-gray-700 transition-colors">
                <i data-lucide="sun" class="h-4 w-4 dark:hidden"></i>
                <i data-lucide="moon" class="h-4 w-4 hidden dark:block"></i>
            </button>

            <form method="POST" action="{{ route('phs.logout') }}">
                @csrf
                <button class="inline-flex items-center gap-2 rounded-md border border-slate-200 dark:border-gray-600 px-3 py-2 text-sm font-semibold text-slate-600 dark:text-gray-300 hover:bg-slate-100 dark:hover:bg-gray-700 transition-colors">
                    <i data-lucide="log-out" class="h-4 w-4"></i>
                    Logout
                </button>
            </form>
        </nav>
    </div>
</header>
