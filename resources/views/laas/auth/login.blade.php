@extends('laas.layouts.app')

@section('title', 'Sign in — LAAS Portal')

@section('body')
<div class="grid min-h-screen lg:grid-cols-[1fr_1.05fr]">

    {{-- ---------- Form ---------- --}}
    <main id="laas-main" class="flex flex-col justify-center px-4 py-10 sm:px-8 lg:px-14">
        <div class="mx-auto w-full max-w-md">

            <a href="{{ route('laas.landing') }}" class="mb-10 inline-flex items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white ring-1"
                      style="--tw-ring-color: var(--border);">
                    <img src="{{ asset('assets/logo/ministry2.png') }}" alt="" aria-hidden="true"
                         class="h-11 w-11 rounded-full object-contain">
                </span>
                <img src="{{ asset('assets/logo/laas-light-logo.jpeg') }}" alt="LAAS Portal"
                     class="h-10 w-auto object-contain dark:hidden">
                <img src="{{ asset('assets/logo/laas-dark-logo.jpeg') }}" alt="LAAS Portal"
                     class="hidden h-10 w-auto object-contain dark:block">
            </a>

            <h1 class="text-3xl font-extrabold tracking-tight" style="color: var(--ink);">Sign in</h1>
            <p class="mt-2 text-sm" style="color: var(--ink-soft);">
                Track your land allocation application and download your documents.
            </p>

            @if($errors->any())
                <div role="alert" class="mt-6 rounded-xl border p-4"
                     style="border-color: var(--danger); background: rgba(159,18,57,.07);">
                    @foreach($errors->all() as $error)
                        <p class="text-sm font-medium" style="color: var(--danger);">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('laas.login.submit') }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="phone" class="block text-sm font-bold" style="color: var(--ink);">
                        Phone number or email
                    </label>
                    <input id="phone" type="text" name="phone" value="{{ old('phone') }}" required autofocus
                           autocapitalize="none" autocomplete="username" spellcheck="false"
                           placeholder="08031234567" class="laas-input mt-2">
                </div>

                <div>
                    <label for="password" class="block text-sm font-bold" style="color: var(--ink);">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password"
                           placeholder="••••••••" class="laas-input mt-2">
                </div>

                <label class="flex items-center gap-2.5">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded"
                           style="accent-color: var(--brand);">
                    <span class="text-sm" style="color: var(--ink-soft);">Keep me signed in</span>
                </label>

                <button type="submit" class="laas-btn w-full py-3.5 text-base">
                    <i data-lucide="log-in" class="h-5 w-5" aria-hidden="true"></i> Sign in
                </button>
            </form>

            <p class="mt-8 text-sm" style="color: var(--ink-soft);">
                First time here?
                <a href="{{ route('laas.register') }}" class="font-bold underline underline-offset-2"
                   style="color: var(--brand);">Create an account</a>
            </p>

            <div class="mt-10 flex items-center justify-between border-t pt-6" style="border-color: var(--border);">
                <a href="{{ route('laas.landing') }}" class="inline-flex items-center gap-2 text-sm font-semibold"
                   style="color: var(--ink-soft);">
                    <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i> Back to LAAS Portal
                </a>
                <button type="button" onclick="laasToggleTheme()" class="rounded-lg p-2" style="color: var(--ink-soft);">
                    <i data-lucide="sun" class="h-4 w-4 dark:hidden" aria-hidden="true"></i>
                    <i data-lucide="moon" class="hidden h-4 w-4 dark:block" aria-hidden="true"></i>
                    <span class="sr-only" data-theme-label>Dark mode</span>
                </button>
            </div>
        </div>
    </main>

    {{-- ---------- Assurance panel ---------- --}}
    <aside class="relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-center"
           style="background: var(--brand-deep);">
        <div class="laas-grid-texture absolute inset-0" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -left-32 top-1/3 h-[460px] w-[460px] rounded-full opacity-35 blur-3xl"
             style="background: radial-gradient(circle, rgba(255,255,255,.06) 0%, transparent 70%);" aria-hidden="true"></div>

        <div class="relative px-14 py-16">
            <p class="mb-5 inline-flex items-center gap-2.5 rounded-full border py-2 pl-2 pr-4"
               style="border-color: rgba(255,255,255,.22); background: rgba(255,255,255,.06);">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white">
                    <img src="{{ asset('assets/logo/ministry2.png') }}" alt="" aria-hidden="true"
                         class="h-5 w-5 rounded-full object-contain">
                </span>
                <span class="laas-eyebrow" style="color: var(--on-deep-soft);">Official Government Portal</span>
            </p>

            <h2 class="max-w-md text-4xl font-black leading-tight text-white">
                Your application, from submission to signed RoFO.
            </h2>

            <ul class="mt-10 space-y-6">
                @foreach([
                    ['shield-check', 'Only you can see your file',
                     'Your documents and file number are tied to your account and nobody else&rsquo;s.'],
                    ['message-square-check', 'Updates reach your phone',
                     'Every stage is texted to the number on your account, and recorded here.'],
                    ['triangle-alert', 'Beware of fraud',
                     'The Ministry never asks for payment through private accounts or agents.'],
                ] as [$icon, $title, $desc])
                    <li class="flex gap-4">
                        <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl"
                              style="background: rgba(255,255,255,.10);">
                            <i data-lucide="{{ $icon }}" class="h-5 w-5" style="color: var(--on-deep-soft);" aria-hidden="true"></i>
                        </span>
                        <div>
                            <p class="font-bold text-white">{{ $title }}</p>
                            <p class="mt-1 max-w-sm text-sm leading-6" style="color: var(--on-deep-soft);">{!! $desc !!}</p>
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="mt-14 flex items-center gap-3 border-t pt-7" style="border-color: rgba(255,255,255,.16);">
                <img src="{{ asset('assets/logo/Nigerian-Coat-of-Arms.png') }}" alt="" aria-hidden="true" class="h-8 w-auto">
                <p class="text-xs font-semibold" style="color: var(--on-deep-soft);">
                    Kano State Ministry of Land &amp; Physical Planning<br>Federal Republic of Nigeria
                </p>
            </div>
        </div>
    </aside>
</div>
@endsection
