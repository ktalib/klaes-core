@extends('online_legal_search.layout')

@section('title', 'Create Account')

@section('body')
<div class="min-h-screen flex flex-col bg-gradient-to-br from-cyan-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">
    @include('online_legal_search.partials.header')

    <div class="flex-1 flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-2xl">
        <!-- Page heading -->
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Create Account</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Register to conduct Online Legal Searches</p>
        </div>

        <!-- Register Form Card -->
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-lg sm:p-8">
            @if($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/30 p-4">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-700 dark:text-red-400">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('ols.register.submit') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Full Name</label>
                    <input
                        id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                        autocomplete="name"
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 dark:focus:ring-cyan-700"
                        placeholder="Musa Abdullahi">
                </div>

                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                    <input
                        id="username" type="text" name="username" value="{{ old('username') }}" required
                        autocomplete="username"
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 dark:focus:ring-cyan-700"
                        placeholder="e.g. musa_a">
                    <p class="mt-1 text-xs text-gray-400">Letters, numbers, dashes and underscores. You'll sign in with this.</p>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                    <input
                        id="email" type="email" name="email" value="{{ old('email') }}" required
                        autocomplete="email"
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 dark:focus:ring-cyan-700"
                        placeholder="you@example.com">
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone Number <span class="text-gray-400"></span></label>
                    <input
                        id="phone" type="tel" name="phone" value="{{ old('phone') }}"
                        autocomplete="tel"
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 dark:focus:ring-cyan-700"
                        placeholder="08012345678">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                    <input
                        id="password" type="password" name="password" required
                        autocomplete="new-password"
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 dark:focus:ring-cyan-700"
                        placeholder="Minimum 8 characters">
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm Password</label>
                    <input
                        id="password_confirmation" type="password" name="password_confirmation" required
                        autocomplete="new-password"
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 dark:focus:ring-cyan-700"
                        placeholder="Re-enter password">
                </div>

                <button type="submit" class="sm:col-span-2 w-full rounded-lg bg-cyan-600 px-4 py-2.5 font-semibold text-white shadow hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2">
                    Create Account
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
                Already have an account?
                <a href="{{ route('ols.login') }}" class="font-medium text-cyan-600 hover:text-cyan-800 dark:text-cyan-400">Sign in</a>
            </p>
        </div>

        <p class="mt-6 text-center text-xs text-gray-500 dark:text-gray-400">
            <a href="{{ route('ols.landing') }}" class="hover:text-cyan-600">&larr; Back to home</a>
        </p>
    </div>
    </div>

    @include('online_legal_search.partials.footer')
</div>
@endsection
