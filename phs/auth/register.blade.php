@extends('phs.layouts.app')

@section('title', 'Register your Organization - PHS Portal')

@section('content')
<div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-blue-50 via-white to-green-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 px-4 py-12 sm:px-6 lg:px-8">
    <div class="w-full max-w-2xl">
        <!-- Logo & Header -->
        <div class="mb-8 text-center">
            <img src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" alt="PHS Portal" class="mx-auto mb-4 h-14 w-auto object-contain dark:hidden">
            <img src="{{ asset('assets/logo/phs-dark-logo.jpeg') }}" alt="PHS Portal" class="mx-auto mb-4 h-14 w-auto object-contain hidden dark:block">

            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Register your Organization</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Create a PHS Portal account. New institutions receive 100 signup tokens!</p>
        </div>

        <!-- Registration Form -->
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-lg sm:p-8">
            @if($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/30 p-4">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-700 dark:text-red-400">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('phs.register.submit') }}" class="space-y-5">
                @csrf

                <!-- Institution Name -->
                <div class="sm:col-span-2">
                    <label for="institution_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Institution Name</label>
                    <input
                        id="institution_name"
                        type="text"
                        name="institution_name"
                        value="{{ old('institution_name') }}"
                        required
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-700"
                        placeholder="e.g., Musa Trust Bank"
                    >
                </div>

                <!-- Institution Type -->
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="institution_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Institution Type</label>
                        <select
                            id="institution_type"
                            name="institution_type"
                            required
                            class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-700"
                        >
                            <option value="">-- Select Type --</option>
                            <option value="bank">Bank</option>
                            <option value="law_firm">Law Firm</option>
                            <option value="corporate">Corporate</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Phone -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone Number</label>
                        <input
                            id="phone"
                            type="tel"
                            name="phone"
                            value="{{ old('phone') }}"
                            class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-700"
                            placeholder="+234 ..."
                        >
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contact Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-700"
                        placeholder="contact@institution.com"
                    >
                </div>

                <!-- Password Fields -->
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-700"
                            placeholder="••••••••"
                        >
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">At least 8 characters</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm Password</label>
                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            required
                            class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-700"
                            placeholder="••••••••"
                        >
                    </div>
                </div>

                <!-- Terms Acceptance -->
                <label class="flex items-center gap-3">
                    <input
                        type="checkbox"
                        name="terms"
                        required
                        class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500"
                    >
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        I agree to the <a href="#" class="font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">Terms of Service</a> and <a href="#" class="font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">Privacy Policy</a>
                    </span>
                </label>

                <!-- Submit Button -->
                <button
                    type="submit"
                    class="mt-6 w-full rounded-lg bg-blue-600 py-2 font-semibold text-white transition hover:bg-blue-700 sm:py-3"
                >
                    <i data-lucide="building-2" class="mr-2 inline h-4 w-4"></i>
                   Register your Organization
                </button>
            </form>

            <!-- Divider -->
            <div class="my-6 flex items-center gap-4">
                <div class="flex-1 border-t border-gray-200 dark:border-gray-600"></div>
                <span class="text-sm text-gray-500 dark:text-gray-400">or</span>
                <div class="flex-1 border-t border-gray-200 dark:border-gray-600"></div>
            </div>

            <!-- Sign In Link -->
            <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                Already have an account?
                <a href="{{ route('phs.login') }}" class="font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                    Sign in here
                </a>
            </p>
        </div>

        <!-- Theme toggle + Back Link -->
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
@endsection
