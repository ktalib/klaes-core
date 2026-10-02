@extends('laas.layouts.app')

@section('body')
@php
    $applicant = auth('laas')->user();

    $links = [
        ['laas.dashboard',     'Dashboard',       'layout-dashboard'],
        ['laas.apply.form',    'New Application', 'file-plus-2'],
        ['laas.notifications', 'Updates',         'bell'],
    ];

    // On desktop the profile is reached through the account block on the right;
    // there is no room for that on mobile, so it joins the scrolling nav.
    $mobileLinks = array_merge($links, [
        ['laas.profile.show', 'My Profile', 'user'],
    ]);
@endphp

<div class="flex min-h-screen flex-col">

    {{-- Government identification strip --}}
    <div style="background: var(--brand-deep);">
        <div class="mx-auto flex max-w-7xl items-center gap-2.5 px-4 py-2 sm:px-6">
            <img src="{{ asset('assets/logo/Nigerian-Coat-of-Arms.png') }}" alt="" aria-hidden="true" class="h-5 w-auto">
            <p class="text-[11px] font-semibold tracking-wide" style="color: var(--on-deep-soft);">
                An official portal of the <span class="text-white">Kano State Government</span>, Nigeria
            </p>
        </div>
    </div>

    <header class="sticky top-0 z-40 border-b" style="background: var(--surface-card); border-color: var(--border);">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">

            <a href="{{ route('laas.dashboard') }}" class="flex items-center gap-3">
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

            <nav class="hidden items-center gap-1 md:flex" aria-label="Portal">
                @foreach($links as [$route, $label, $icon])
                    @php $active = request()->routeIs($route); @endphp
                    <a href="{{ route($route) }}" @if($active) aria-current="page" @endif
                       class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-bold transition"
                       style="{{ $active
                            ? 'background: var(--brand-tint); color: var(--brand);'
                            : 'color: var(--ink-soft);' }}">
                        <i data-lucide="{{ $icon }}" class="h-4 w-4" aria-hidden="true"></i>
                        {{ $label }}
                        @if($route === 'laas.notifications' && ($unreadUpdates ?? 0) > 0)
                            <span class="ml-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-black"
                                  style="background: var(--brand); color: var(--on-brand);">{{ $unreadUpdates }}</span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="flex items-center gap-2">
                <button type="button" onclick="laasToggleTheme()" class="rounded-lg p-2" style="color: var(--ink-soft);">
                    <i data-lucide="sun" class="h-4 w-4 dark:hidden" aria-hidden="true"></i>
                    <i data-lucide="moon" class="hidden h-4 w-4 dark:block" aria-hidden="true"></i>
                    <span class="sr-only" data-theme-label>Dark mode</span>
                </button>

                <a href="{{ route('laas.profile.show') }}"
                   class="hidden items-center gap-2.5 rounded-lg px-2 py-1.5 text-right transition hover:bg-[var(--brand-tint)] sm:flex">
                    <span>
                        <span class="block text-sm font-bold leading-tight" style="color: var(--ink);">{{ $applicant->name ?? '' }}</span>
                        <span class="block text-xs" style="color: var(--ink-soft);">{{ $applicant->phone ?? '' }}</span>
                    </span>
                    <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full"
                          style="background: var(--brand-tint); color: var(--brand);">
                        <i data-lucide="user" class="h-4 w-4" aria-hidden="true"></i>
                    </span>
                    <span class="sr-only">My profile</span>
                </a>

                <form method="POST" action="{{ route('laas.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg p-2 transition hover:bg-[var(--brand-tint)]"
                            style="color: var(--ink-soft);">
                        <i data-lucide="log-out" class="h-4 w-4" aria-hidden="true"></i>
                        <span class="sr-only">Sign out</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Mobile nav --}}
        <nav class="flex items-center gap-1 overflow-x-auto border-t px-4 py-2 md:hidden"
             style="border-color: var(--border);" aria-label="Portal">
            @foreach($mobileLinks as [$route, $label, $icon])
                @php $active = request()->routeIs($route); @endphp
                <a href="{{ route($route) }}" @if($active) aria-current="page" @endif
                   class="inline-flex flex-shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-bold"
                   style="{{ $active
                        ? 'background: var(--brand-tint); color: var(--brand);'
                        : 'color: var(--ink-soft);' }}">
                    <i data-lucide="{{ $icon }}" class="h-3.5 w-3.5" aria-hidden="true"></i>{{ $label }}
                </a>
            @endforeach
        </nav>
    </header>

    <main id="laas-main" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 sm:py-9">
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
@endsection
