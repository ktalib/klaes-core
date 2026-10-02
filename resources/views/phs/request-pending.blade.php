@extends('phs.layouts.app')

@section('title', 'PHS Portal - Request Submitted')

@section('content')
<div id="preloader" style="position:fixed;inset:0;background:var(--phs-preloader-bg,#fff);display:flex;align-items:center;justify-content:center;z-index:9999;">
    <img src="http://app.klaes.ng/storage/upload/logo/klas_logo.gif" alt="Loading..." style="width:200px;height:auto;">
</div>
<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-green-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">
    <!-- Navigation -->
    <nav class="sticky top-0 z-10 bg-white/80 dark:bg-gray-900/80 backdrop-blur-md shadow-sm border-b border-gray-100 dark:border-gray-800">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center">
                    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="h-11 w-auto object-contain">
                </div>
                <div class="hidden items-center space-x-4 md:flex">
                    <button onclick="phsToggleTheme()" title="Toggle dark mode"
                        class="rounded-md p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-850 transition-colors">
                        <i data-lucide="sun" class="h-4.5 w-4.5 dark:hidden"></i>
                        <i data-lucide="moon" class="h-4.5 w-4.5 hidden dark:block"></i>
                    </button>
                    <a href="{{ route('phs.login') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 transition-all hover:bg-gray-50 dark:hover:bg-gray-800">
                        Sign In
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Content -->
<div class="container mx-auto py-12 px-4">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 border border-gray-100 dark:border-gray-750 text-center text-gray-900 dark:text-gray-100">
            <div class="mb-6">
                <div class="mx-auto w-16 h-16 bg-green-100 dark:bg-green-950/40 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
            </div>

            <h1 class="text-3xl font-bold mb-4 text-gray-900 dark:text-gray-100">Request Submitted Successfully</h1>
            <p class="text-gray-600 dark:text-gray-400 text-lg mb-8">
                Thank you for submitting your onboarding request to the PHS portal.
            </p>

            <div class="bg-blue-50 dark:bg-blue-950/20 border-l-4 border-blue-500 dark:border-blue-700 p-6 mb-8 text-left">
                <h2 class="font-semibold text-blue-900 dark:text-blue-300 mb-4">What Happens Next</h2>
                <ol class="space-y-3 text-blue-800 dark:text-blue-400">
                    <li class="flex items-start">
                        <span class="font-bold mr-3">1.</span>
                        <span>Our team reviews your documents and contacts you if any additional information is needed.</span>
                    </li>
                    <li class="flex items-start">
                        <span class="font-bold mr-3">2.</span>
                        <span>Once approved, you'll receive a link to select your subscription package, then download, sign, and upload your Service Level Agreement (SLA) — no payment at this stage.</span>
                    </li>
                    <li class="flex items-start">
                        <span class="font-bold mr-3">3.</span>
                        <span>After our Legal team approves your signed SLA, you'll receive a payment &amp; onboarding link to complete payment and register your account.</span>
                    </li>
                    <li class="flex items-start">
                        <span class="font-bold mr-3">4.</span>
                        <span>After registration, you can immediately start searching property records.</span>
                    </li>
                </ol>
            </div>

            <div class="bg-yellow-50 dark:bg-yellow-950/20 border border-yellow-200 dark:border-yellow-800/60 rounded-lg p-6 mb-8 text-left">
                <p class="text-sm text-yellow-800 dark:text-yellow-400">
                    <strong>Note:</strong> You should expect to hear from us within 1-2 business days.
                    Make sure to check your email (including spam folder) for our communications.
                </p>
            </div>

            <div class="space-y-4">
                <p class="text-gray-700 dark:text-gray-300">
                    If you have any questions, please don't hesitate to contact our support team.
                </p>
                <div class="flex flex-wrap gap-4 justify-center">
                    @if ($request->invoice_pdf_path)
                        <a href="{{ route('phs.request.invoice', ['id' => $request->id]) }}" class="inline-flex items-center gap-2 px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                            Download Invoice
                        </a>
                    @endif
                    <a href="{{ route('phs.landing') }}" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium">
                        Back to Home
                    </a>
                    <a href="{{ route('phs.login') }}" class="px-6 py-2 border border-blue-600 dark:border-blue-500 text-blue-600 dark:text-blue-400 rounded-md hover:bg-blue-50 dark:hover:bg-gray-800 font-medium">
                        Sign In
                    </a>
                </div>
                @if ($request->invoice_number)
                    <p class="mt-3 text-xs text-gray-400 dark:text-gray-500">Invoice No: {{ $request->invoice_number }}</p>
                @endif
            </div>
        </div>

        <!-- Mobile-friendly Theme toggle button at bottom -->
        <div class="mt-6 flex items-center justify-center gap-4">
            <a href="{{ route('phs.landing') }}" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                <i data-lucide="arrow-left" class="h-4 w-4"></i>
                Back to PHS Portal
            </a>
            <button onclick="phsToggleTheme()" title="Toggle dark mode"
                class="rounded-md p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                <i data-lucide="sun" class="h-4.5 w-4.5 dark:hidden"></i>
                <i data-lucide="moon" class="h-4.5 w-4.5 hidden dark:block"></i>
            </button>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="bg-gray-900 py-12 text-white">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4 sm:gap-10">
            <div>
                <div class="flex items-center">
                    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="h-11 w-auto object-contain bg-white rounded-md p-1">
                </div>
                <p class="mt-4 text-sm leading-relaxed text-gray-400">
                    Official government platform for legal search services and land record verification in Kano State.
                </p>
            </div>
            <div>
                <h3 class="mb-4 text-base font-semibold uppercase tracking-wider text-gray-300">Services</h3>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="#" class="hover:text-white transition">Legal Search</a></li>
                    <li><a href="#" class="hover:text-white transition">Property Records</a></li>
                    <li><a href="#" class="hover:text-white transition">Certification</a></li>
                </ul>
            </div>
            <div>
                <h3 class="mb-4 text-base font-semibold uppercase tracking-wider text-gray-300">Company</h3>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="#" class="hover:text-white transition">About</a></li>
                    <li><a href="#" class="hover:text-white transition">Contact</a></li>
                    <li><a href="#" class="hover:text-white transition">Support</a></li>
                </ul>
            </div>
            <div>
                <h3 class="mb-4 text-base font-semibold uppercase tracking-wider text-gray-300">Legal</h3>
                <ul class="space-y-2 text-sm text-gray-400">
                    <li><a href="#" class="hover:text-white transition">Privacy</a></li>
                    <li><a href="#" class="hover:text-white transition">Terms</a></li>
                    <li><a href="#" class="hover:text-white transition">Cookies</a></li>
                </ul>
            </div>
        </div>
        <div class="mt-8 border-t border-gray-800 pt-8 text-center text-sm text-gray-400">
            <p>&copy; {{ date('Y') }} Kano State Ministry of Land and Physical Planning. All rights reserved.</p>
        </div>
    </div>
</footer>
@endsection
