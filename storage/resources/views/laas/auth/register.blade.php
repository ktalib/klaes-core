@extends('laas.layouts.app')

@section('title', 'Create an account — LAAS Portal')

@section('body')
<div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">

    {{-- ---------- Form ---------- --}}
    <main id="laas-main" class="flex flex-col justify-center px-4 py-10 sm:px-8 lg:px-14">
        <div class="mx-auto w-full max-w-xl">

            <a href="{{ route('laas.landing') }}" class="mb-8 inline-flex items-center gap-3">
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

            <h1 class="text-3xl font-extrabold tracking-tight" style="color: var(--ink);">Create your account</h1>
            <p class="mt-2 text-sm" style="color: var(--ink-soft);">
                One account lets you apply, upload documents, and follow every application you make.
            </p>

            @if($errors->any())
                <div role="alert" class="mt-6 rounded-xl border p-4"
                     style="border-color: var(--danger); background: rgba(159,18,57,.07);">
                    <p class="mb-1.5 text-sm font-bold" style="color: var(--danger);">Please correct the following:</p>
                    <ul class="list-inside list-disc space-y-1">
                        @foreach($errors->all() as $error)
                            <li class="text-sm" style="color: var(--danger);">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('laas.register.submit') }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-bold" style="color: var(--ink);">
                        Full name <span style="color: var(--danger);">*</span>
                    </label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                           class="laas-input mt-2">
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="phone" class="block text-sm font-bold" style="color: var(--ink);">
                            Phone number <span style="color: var(--danger);">*</span>
                        </label>
                        <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required
                               placeholder="08031234567" aria-describedby="phone-help" class="laas-input mt-2">
                        <p id="phone-help" class="mt-1.5 text-xs" style="color: var(--ink-soft);">
                            Every update is sent here by SMS.
                        </p>
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-bold" style="color: var(--ink);">
                            Email address <span style="color: var(--danger);">*</span>
                        </label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required
                               class="laas-input mt-2">
                    </div>
                </div>

                <div>
                    <label for="nin" class="block text-sm font-bold" style="color: var(--ink);">
                        National Identification Number (NIN)
                    </label>
                    <input id="nin" type="text" name="nin" value="{{ old('nin') }}" class="laas-input mt-2">
                </div>

                <div>
                    <label for="address" class="block text-sm font-bold" style="color: var(--ink);">Contact address</label>
                    <textarea id="address" name="address" rows="2" class="laas-input mt-2">{{ old('address') }}</textarea>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="password" class="block text-sm font-bold" style="color: var(--ink);">
                            Password <span style="color: var(--danger);">*</span>
                        </label>
                        <input id="password" type="password" name="password" required autocomplete="new-password"
                               aria-describedby="password-help" class="laas-input mt-2">
                        <p id="password-help" class="mt-1.5 text-xs" style="color: var(--ink-soft);">
                            At least 8 characters.
                        </p>
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-bold" style="color: var(--ink);">
                            Confirm password <span style="color: var(--danger);">*</span>
                        </label>
                        <input id="password_confirmation" type="password" name="password_confirmation" required
                               autocomplete="new-password" class="laas-input mt-2">
                    </div>
                </div>

                <button type="submit" class="laas-btn w-full py-3.5 text-base">
                    <i data-lucide="user-plus" class="h-5 w-5" aria-hidden="true"></i> Create account
                </button>
            </form>

            <p class="mt-8 text-sm" style="color: var(--ink-soft);">
                Already have an account?
                <a href="{{ route('laas.login') }}" class="font-bold underline underline-offset-2"
                   style="color: var(--brand);">Sign in</a>
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

    {{-- ---------- What happens next ---------- --}}
    <aside class="relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-center"
           style="background: var(--brand-deep);">
        <div class="laas-grid-texture absolute inset-0" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -right-32 top-1/4 h-[460px] w-[460px] rounded-full opacity-35 blur-3xl"
             style="background: radial-gradient(circle, #12A06A 0%, transparent 68%);" aria-hidden="true"></div>

        <div class="relative px-14 py-16">
            <p class="mb-5 inline-flex items-center gap-2.5 rounded-full border py-2 pl-2 pr-4"
               style="border-color: rgba(245,179,1,.45); background: rgba(245,179,1,.10);">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white">
                    <img src="{{ asset('assets/logo/ministry2.png') }}" alt="" aria-hidden="true"
                         class="h-5 w-5 rounded-full object-contain">
                </span>
                <span class="laas-eyebrow" style="color: #FFD97A;">Official Government Portal</span>
            </p>

            <h2 class="max-w-md text-4xl font-black leading-tight text-white">
                What happens after you register
            </h2>

            <ol class="mt-10 space-y-7">
                @foreach([
                    ['Fill the application', 'About ten minutes. Save and come back whenever you like.'],
                    ['Submit it',            'You are texted at once that processing has started.'],
                    ['Follow every stage',   'Approval, file number, survey, recommendation, and your signed RoFO.'],
                ] as $i => [$title, $desc])
                    <li class="flex gap-4">
                        <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-sm font-black"
                              style="background: var(--gold); color: #1A1200;">{{ $i + 1 }}</span>
                        <div>
                            <p class="font-bold text-white">{{ $title }}</p>
                            <p class="mt-1 max-w-sm text-sm leading-6" style="color: var(--on-deep-soft);">{{ $desc }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

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
