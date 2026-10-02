@extends('phs.layouts.app')

@section('title', 'PHS Portal - Complete Registration')

@section('content')
<div id="preloader" style="position:fixed;inset:0;background:var(--phs-preloader-bg,#fff);display:flex;align-items:center;justify-content:center;z-index:9999;">
    <img src="http://app.klaes.ng/storage/upload/logo/klas_logo.gif" alt="Loading..." style="width:200px;height:auto;">
</div>
<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-green-50">
    <!-- Navigation -->
    <nav class="sticky top-0 z-10 bg-white/80 backdrop-blur-md shadow-sm">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-between">
                <div class="flex items-center">
                    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="h-11 w-auto object-contain">
                </div>
                <div class="hidden items-center space-x-4 md:flex">
                    <a href="{{ route('phs.login') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-transparent px-4 py-2 text-sm font-medium text-gray-700 transition-all hover:bg-gray-50">
                        Sign In
                    </a>
                    <a href="{{ route('phs.landing') }}" class="inline-flex items-center justify-center rounded-md border-0 bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-all hover:bg-blue-700">
                        Back
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Form Content -->
<div class="container mx-auto py-8 px-4">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md p-8">
            <h1 class="text-3xl font-bold mb-2">Complete Your Registration</h1>
            <p class="text-gray-600 mb-6">Your onboarding request has been approved. Complete registration to activate your account.</p>

            <!-- Pre-filled Information -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
                <h3 class="font-semibold text-blue-900 mb-4">Organization Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-blue-700">Organization Name</p>
                        <p class="font-medium">{{ $onboardingRequest->organization_name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-blue-700">Type</p>
                        <p class="font-medium capitalize">{{ str_replace('_', ' ', $onboardingRequest->organization_type) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-blue-700">Contact Name</p>
                        <p class="font-medium">{{ $onboardingRequest->contact_name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-blue-700">Email</p>
                        <p class="font-medium">{{ $onboardingRequest->contact_email }}</p>
                    </div>
                </div>
            </div>

            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                    <strong>Please correct the following errors:</strong>
                    <ul class="list-disc list-inside mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($onboardingRequest->activation_token_expires_at)
                <div class="mb-6 p-4 bg-yellow-100 border border-yellow-400 text-yellow-800 rounded">
                    This registration link expires on <strong>{{ $onboardingRequest->activation_token_expires_at->format('F j, Y \a\t g:i A') }}</strong>
                </div>
            @endif

            <form method="POST" action="{{ route('phs.register.token.submit', ['token' => $token]) }}" class="space-y-6">
                @csrf

                {{-- Row 1: Activation Code + Username --}}
                <fieldset>
                    <legend class="text-lg font-semibold mb-1">Organization Account</legend>
                    <p class="text-sm text-gray-500 mb-4">Confirm your activation code and choose a username for your organization.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="activation_code" class="block text-sm font-medium text-gray-700">Activation Code</label>
                            <div class="relative mt-1">
                                <input type="text" id="activation_code" value="{{ $token }}" readonly
                                    class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm bg-gray-50 text-gray-500 cursor-not-allowed focus:outline-none font-mono text-sm pr-10 truncate"
                                    onclick="this.select()">
                                <svg class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </div>
                            <p class="text-xs text-gray-400 mt-1.5">One-time code. It cannot be changed.</p>
                        </div>

                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                            <input type="text" id="username" name="username" readonly
                                value="{{ old('username', $suggestedUsername) }}"
                                pattern="[a-z0-9_]+"
                                class="mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm bg-gray-100 text-gray-600 cursor-not-allowed focus:outline-none"
                                placeholder="organization_username">
                            <p class="text-xs text-gray-400 mt-1.5">Auto-assigned from your organization name.</p>
                            <p class="text-xs text-blue-500 mt-1 flex items-start gap-1">
                                <svg class="h-3.5 w-3.5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                                You can update this later under Organization &rsaquo; Branding settings.
                            </p>
                        </div>
                    </div>
                </fieldset>

                {{-- Row 2: Password + Confirm Password --}}
                <fieldset>
                    <legend class="text-lg font-semibold mb-1">Create Your Password</legend>
                    <p class="text-sm text-gray-500 mb-4">Use a strong password with a mix of letters, numbers, and symbols.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                            <input type="password" id="password" name="password" required
                                class="mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Minimum 6 characters">
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password <span class="text-red-500">*</span></label>
                            <input type="password" id="password_confirmation" name="password_confirmation" required
                                class="mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Re-enter your password">
                        </div>
                    </div>
                </fieldset>

                <div class="flex items-start gap-3">
                    <div class="flex h-5 items-center">
                        <input id="agree_terms" name="agree_terms" type="checkbox" value="1" required
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </div>
                    <label for="agree_terms" class="text-sm text-gray-700">
                        I agree to the <a href="#" class="font-semibold text-blue-600 hover:text-blue-700">Terms of Service</a> and <a href="#" class="font-semibold text-blue-600 hover:text-blue-700">Privacy Policy</a>
                    </label>
                </div>

                <div class="flex justify-between items-center pt-6 border-t">
                    <a href="{{ route('phs.landing') }}" class="text-blue-600 hover:text-blue-800">Back to Home</a>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-medium">
                       Register your Organization
                    </button>
                </div>
            </form>
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
