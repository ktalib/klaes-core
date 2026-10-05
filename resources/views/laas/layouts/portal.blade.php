@extends('laas.layouts.app')

@section('body')
@php
    $applicant = auth('laas')->user();

    // [route, label, icon, routes that also light it up]
    $links = [
        ['laas.dashboard',     'Dashboard',       'layout-dashboard', ['laas.application.*']],
        ['laas.folio.index',   'Folio',           'folder-open',      []],
        ['laas.apply.form',    'New Application', 'file-plus-2',      []],
        ['laas.notifications', 'Updates',         'bell',             []],
    ];
@endphp

{{--
    Sidebar shell. Fixed on large screens; on smaller ones it slides in from the
    left behind the menu button in the top bar, over a dimmed backdrop.
--}}
<div class="min-h-screen lg:pl-64">

    <div id="laasSidebarBackdrop" class="fixed inset-0 z-40 hidden bg-black/50 lg:hidden" onclick="laasSidebar(false)"></div>

    <aside id="laasSidebar"
           class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r transition-transform duration-200 lg:translate-x-0"
           style="background: var(--surface-card); border-color: var(--border);" aria-label="Portal">

        <div class="flex items-center justify-between gap-2 border-b px-4 py-4" style="border-color: var(--border);">
            <a href="{{ route('laas.dashboard') }}" class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-white ring-1"
                      style="--tw-ring-color: var(--border);">
                    <img src="{{ asset('assets/logo/ministry2.png') }}"
                         alt="Seal of the Kano State Ministry of Land and Physical Planning"
                         class="h-10 w-10 rounded-full object-contain">
                </span>
                <img src="{{ asset('assets/logo/laas-light-logo.jpeg') }}" alt="LAAS Portal"
                     class="h-9 w-auto object-contain dark:hidden">
                <img src="{{ asset('assets/logo/laas-dark-logo.jpeg') }}" alt="LAAS Portal"
                     class="hidden h-9 w-auto object-contain dark:block">
            </a>
            <button type="button" onclick="laasSidebar(false)" class="rounded-lg p-2 lg:hidden" style="color: var(--ink-soft);">
                <i data-lucide="x" class="h-5 w-5" aria-hidden="true"></i>
                <span class="sr-only">Close menu</span>
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            <p class="px-3 pb-2 text-[10px] font-extrabold uppercase tracking-widest" style="color: var(--ink-faint);">Menu</p>
            @foreach($links as [$route, $label, $icon, $also])
                @php $active = request()->routeIs($route, ...$also); @endphp
                <a href="{{ route($route) }}" @if($active) aria-current="page" @endif
                   class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-bold transition hover:bg-[var(--brand-tint)]"
                   style="{{ $active ? 'background: var(--brand-tint); color: var(--brand);' : 'color: var(--ink-soft);' }}">
                    <i data-lucide="{{ $icon }}" class="h-4 w-4 flex-shrink-0" aria-hidden="true"></i>
                    <span class="flex-1">{{ $label }}</span>
                    @if($route === 'laas.notifications' && ($unreadUpdates ?? 0) > 0)
                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-black"
                              style="background: var(--brand); color: var(--on-brand);">{{ $unreadUpdates }}</span>
                    @endif
                </a>
            @endforeach

            <p class="px-3 pb-2 pt-5 text-[10px] font-extrabold uppercase tracking-widest" style="color: var(--ink-faint);">Account</p>
            @php $active = request()->routeIs('laas.profile.*'); @endphp
            <a href="{{ route('laas.profile.show') }}" @if($active) aria-current="page" @endif
               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-bold transition hover:bg-[var(--brand-tint)]"
               style="{{ $active ? 'background: var(--brand-tint); color: var(--brand);' : 'color: var(--ink-soft);' }}">
                <i data-lucide="user" class="h-4 w-4 flex-shrink-0" aria-hidden="true"></i> My Profile
            </a>
            <button type="button" onclick="laasToggleTheme()"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-bold transition hover:bg-[var(--brand-tint)]"
                    style="color: var(--ink-soft);">
                <i data-lucide="moon" class="h-4 w-4 flex-shrink-0 dark:hidden" aria-hidden="true"></i>
                <i data-lucide="sun" class="hidden h-4 w-4 flex-shrink-0 dark:block" aria-hidden="true"></i>
                <span data-theme-label>Dark mode</span>
            </button>
        </nav>

        <div class="border-t p-3" style="border-color: var(--border);">
            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full"
                      style="background: var(--brand-tint); color: var(--brand);">
                    <i data-lucide="user" class="h-4 w-4" aria-hidden="true"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold leading-tight" style="color: var(--ink);">{{ $applicant->name ?? '' }}</span>
                    <span class="block truncate text-xs" style="color: var(--ink-soft);">{{ $applicant->username ?: ($applicant->phone ?? '') }}</span>
                </span>
                <form method="POST" action="{{ route('laas.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg p-2 transition hover:bg-[var(--brand-tint)]" style="color: var(--ink-soft);" title="Sign out">
                        <i data-lucide="log-out" class="h-4 w-4" aria-hidden="true"></i>
                        <span class="sr-only">Sign out</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="flex min-h-screen flex-col">
        {{-- Government identification strip --}}
        <div style="background: var(--brand-deep);">
            <div class="flex items-center gap-2.5 px-4 py-2 sm:px-6">
                <img src="{{ asset('assets/logo/Nigerian-Coat-of-Arms.png') }}" alt="" aria-hidden="true" class="h-5 w-auto">
                <p class="text-[11px] font-semibold tracking-wide" style="color: var(--on-deep-soft);">
                    An official portal of the <span class="text-white">Kano State Government</span>, Nigeria
                </p>
            </div>
        </div>

        {{-- Top bar: small screens only, where the sidebar is hidden. --}}
        <header class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b px-4 py-2.5 lg:hidden"
                style="background: var(--surface-card); border-color: var(--border);">
            <button type="button" onclick="laasSidebar(true)" class="rounded-lg p-2" style="color: var(--ink);">
                <i data-lucide="menu" class="h-5 w-5" aria-hidden="true"></i>
                <span class="sr-only">Open menu</span>
            </button>
            <a href="{{ route('laas.dashboard') }}">
                <img src="{{ asset('assets/logo/laas-light-logo.jpeg') }}" alt="LAAS Portal" class="h-8 w-auto object-contain dark:hidden">
                <img src="{{ asset('assets/logo/laas-dark-logo.jpeg') }}" alt="LAAS Portal" class="hidden h-8 w-auto object-contain dark:block">
            </a>
            <a href="{{ route('laas.notifications') }}" class="relative rounded-lg p-2" style="color: var(--ink-soft);">
                <i data-lucide="bell" class="h-5 w-5" aria-hidden="true"></i>
                @if(($unreadUpdates ?? 0) > 0)
                    <span class="absolute right-1 top-1 h-2 w-2 rounded-full" style="background: var(--brand);"></span>
                @endif
                <span class="sr-only">Updates</span>
            </a>
        </header>

        <main id="laas-main" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 sm:py-8">
            @if(session('status'))
                <div role="status" class="mb-6 flex items-start gap-3 rounded-xl border p-4"
                     style="border-color: var(--brand-line); background: var(--brand-tint);">
                    <i data-lucide="check-circle-2" class="mt-0.5 h-5 w-5 flex-shrink-0"
                       style="color: var(--brand);" aria-hidden="true"></i>
                    <p class="text-sm font-medium" style="color: var(--ink);">{{ session('status') }}</p>
                </div>
            @endif

            @if(session('error'))
                <div role="alert" class="mb-6 flex items-start gap-3 rounded-xl border p-4"
                     style="border-color: var(--danger); background: rgba(159,18,57,.07);">
                    <i data-lucide="triangle-alert" class="mt-0.5 h-5 w-5 flex-shrink-0"
                       style="color: var(--danger);" aria-hidden="true"></i>
                    <p class="text-sm font-medium" style="color: var(--ink);">{{ session('error') }}</p>
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="border-t py-6" style="background: var(--surface-card); border-color: var(--border);">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 sm:flex-row sm:px-6">
                <p class="text-xs" style="color: var(--ink-soft);">
                    &copy; {{ date('Y') }} Kano State Ministry of Land &amp; Physical Planning — LAAS Portal
                </p>
                <p class="text-xs font-semibold" style="color: var(--ink-faint);">
                    The Ministry never asks for payment through private accounts or agents.
                </p>
            </div>
        </footer>
    </div>
</div>

<script>
    function laasSidebar(open) {
        document.getElementById('laasSidebar').classList.toggle('-translate-x-full', !open);
        document.getElementById('laasSidebarBackdrop').classList.toggle('hidden', !open);
        document.body.style.overflow = open ? 'hidden' : '';
    }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') laasSidebar(false); });
    // The label reflects the mode it switches TO.
    document.addEventListener('DOMContentLoaded', function () {
        var dark = document.documentElement.classList.contains('dark');
        document.querySelectorAll('[data-theme-label]').forEach(function (el) { el.textContent = dark ? 'Light mode' : 'Dark mode'; });
    });
</script>
@endsection
