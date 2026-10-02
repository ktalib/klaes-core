@extends('phs.layouts.app')

@section('title', 'Sign In - PHS Portal')

@section('content')
@php
    $phsDisclaimer = [
        'The Property History Search (PHS) Portal and Online Legal Search results provided by the Kano State Ministry of Land and Physical Planning are generated strictly for informational and preliminary due diligence purposes. The Ministry shall not be held liable or responsible for any loss, damage, or legal consequences arising from the reliance on, alteration, forgery, or misrepresentation of any search report or land document generated through this platform. All genuine reports contain unique verification codes and QR codes; it is the sole responsibility of the user to verify the authenticity of any document via the Ministry&rsquo;s official verification portal before making any financial or legal decisions.',
        'Please be strictly advised that any attempt to alter, forge, tamper with, or misuse any search result or land document issued by or accessible through the Ministry is a severe criminal offense. Such acts are punishable under the Criminal Code Act, the Cybercrimes (Prohibition, Prevention, etc.) Act 2015, and the Land Use Act of 1978. The Ministry actively monitors system access and will promptly report any suspected fraudulent activities to law enforcement agencies for full prosecution to the maximum extent of the law.',
    ];
@endphp

<style>
    .phs-right-panel { height: 100vh; overflow-y: auto; -ms-overflow-style: none; scrollbar-width: none; }
    .phs-right-panel::-webkit-scrollbar { display: none; }
</style>

<div id="preloader" style="position:fixed;inset:0;background:#fff;display:flex;align-items:center;justify-content:center;z-index:9999;">
    <img src="http://app.klaes.ng/storage/upload/logo/klas_logo.gif" alt="Loading..." style="width:200px;height:auto;">
</div>

<div class="h-screen flex overflow-hidden">
    <!-- LEFT — Login Form (flyer sits in the background) -->
    <div class="w-full md:w-1/2 relative flex flex-col items-center justify-center px-6 py-10 overflow-y-auto">

        <!-- Flyer — background -->
        <img src="{{ asset('assets/logo/PHS-P Flyer.png') }}" alt="PHS Portal Flyer"
             class="absolute inset-0 w-full h-full object-cover object-top">

        <!-- Readability tint so the flyer shows through faintly behind the form -->
        <div class="absolute inset-0 bg-gradient-to-br from-blue-50/90 via-white/90 to-green-50/90 dark:from-gray-900/90 dark:via-gray-900/90 dark:to-gray-800/90 backdrop-blur-[1px]"></div>

        <div class="relative z-10 w-full max-w-md">
            <!-- Logo & Header -->
            <div class="mb-8 text-center">
                <img src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" alt="PHS Portal" class="mx-auto mb-4 h-24 w-auto object-contain dark:hidden">
                <img src="{{ asset('assets/logo/phs-dark-logo.jpeg') }}" alt="PHS Portal" class="mx-auto mb-4 h-24 w-auto object-contain hidden dark:block">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">PHS Portal</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Sign in to your Organization account</p>
            </div>

            <!-- Login Form Card -->
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-lg sm:p-8">
                @if($errors->any())
                    <div class="mb-4 rounded-lg border border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/30 p-4">
                        @foreach($errors->all() as $error)
                            <p class="text-sm text-red-700 dark:text-red-400">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('phs.login.submit') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                        <input
                            id="username" type="text" name="username" value="{{ old('username') }}" required autofocus
                            autocapitalize="none" autocomplete="username" spellcheck="false"
                            class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-700"
                            placeholder="your_organization_username"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                        <input
                            id="password" type="password" name="password" required
                            class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-700"
                            placeholder="••••••••"
                        >
                    </div>

                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="remember" value="1"
                            class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Remember me</span>
                    </label>

                    <button type="submit"
                        class="mt-6 w-full rounded-lg py-2 font-semibold text-white transition sm:py-3" style="background-color:#1a6b3c;" onmouseover="this.style.backgroundColor='#155a32'" onmouseout="this.style.backgroundColor='#1a6b3c'">
                        <i data-lucide="log-in" class="mr-2 inline h-4 w-4"></i>
                        Sign In
                    </button>
                </form>

                <div class="my-6 flex items-center gap-4">
                    <div class="flex-1 border-t border-gray-200 dark:border-gray-600"></div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">or</span>
                    <div class="flex-1 border-t border-gray-200 dark:border-gray-600"></div>
                </div>

                <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                    Don't have an account?
                    <a href="{{ route('phs.register') }}" class="font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                        Register your Organization
                    </a>
                </p>
            </div>

            <!-- Footer links -->
            <div class="mt-6 flex items-center justify-center gap-4">
                <a href="{{ route('phs.landing') }}" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    Back to PHS Portal
                </a>
                <button onclick="phsToggleTheme()" title="Toggle dark mode"
                    class="rounded-md p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <i data-lucide="sun" class="h-4 w-4 dark:hidden"></i>
                    <i data-lucide="moon" class="h-4 w-4 hidden dark:block"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- RIGHT — Full Disclaimer panel (hidden on mobile) -->
    <div class="hidden md:block md:w-1/2 h-screen bg-[#f0f7f2] border-l border-green-200 overflow-hidden">

        <!-- Disclaimer (always fully shown, scrollable) -->
        <div class="flex flex-col h-full">

            <!-- Header bar -->
            <div class="flex-none px-8 py-4 bg-[#1a6b3c] flex items-center gap-3">
                <i data-lucide="shield-alert" class="h-5 w-5 text-white flex-shrink-0"></i>
                <div>
                    <h2 class="text-sm font-extrabold uppercase tracking-widest text-white">Disclaimer &amp; Legal Notice</h2>
                    <p class="text-[11px] text-green-200">Kano State Ministry of Land &amp; Physical Planning — PHS Portal</p>
                </div>
            </div>

            <!-- Content -->
            <div class="phs-right-panel flex-1 overflow-y-auto px-8 py-6">
                @foreach($phsDisclaimer as $para)
                    <p class="mb-5 text-[13px] leading-7 text-green-950/90 text-justify">{!! $para !!}</p>
                @endforeach

                <div class="my-5 border-t border-green-200"></div>

                <!-- Agreement checklist -->
                <div class="mb-5 rounded-xl bg-white border border-green-200 p-4 shadow-sm">
                    <p class="mb-3 text-[11px] font-extrabold uppercase tracking-widest text-[#1a6b3c]">By logging in, you confirm that:</p>
                    <ul class="space-y-2 text-[12.5px] text-green-900">
                        <li class="flex items-start gap-2"><i data-lucide="check-circle-2" class="mt-0.5 h-4 w-4 flex-shrink-0 text-[#1a6b3c]"></i>You have read and understood this disclaimer.</li>
                        <li class="flex items-start gap-2"><i data-lucide="check-circle-2" class="mt-0.5 h-4 w-4 flex-shrink-0 text-[#1a6b3c]"></i>Search results will only be used for authorized due diligence purposes.</li>
                        <li class="flex items-start gap-2"><i data-lucide="check-circle-2" class="mt-0.5 h-4 w-4 flex-shrink-0 text-[#1a6b3c]"></i>You will not alter, forge, or misrepresent any report from this portal.</li>
                        <li class="flex items-start gap-2"><i data-lucide="check-circle-2" class="mt-0.5 h-4 w-4 flex-shrink-0 text-[#1a6b3c]"></i>Your organization is bound by Nigerian law and your subscription terms.</li>
                    </ul>
                </div>

                <!-- Criminal warning -->
                <div class="mb-5 rounded-xl bg-red-50 border border-red-200 p-4">
                    <p class="mb-2 flex items-center gap-2 text-[11px] font-extrabold uppercase tracking-widest text-red-700">
                        <i data-lucide="triangle-alert" class="h-4 w-4"></i> Criminal Offense Warning
                    </p>
                    <p class="text-[12.5px] leading-6 text-red-900/85">Forging, altering, or misusing any document from this portal is a <strong>severe criminal offense</strong> under the <strong>Criminal Code Act</strong>, <strong>Cybercrimes Act 2015</strong>, and <strong>Land Use Act 1978</strong>. Offenders will be prosecuted to the fullest extent of the law.</p>
                </div>

                <p class="text-center text-[11px] text-green-700 pb-1">&copy; {{ date('Y') }} Kano State Ministry of Land &amp; Physical Planning. All rights reserved.</p>
            </div>
        </div>
    </div>
</div>
@endsection
