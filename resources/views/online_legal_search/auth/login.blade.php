@extends('online_legal_search.layout')

@section('title', 'Sign In')

@section('body')
<div class="min-h-screen flex flex-col bg-gradient-to-br from-cyan-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">
    @include('online_legal_search.partials.header')

    <div class="flex-1 flex items-center justify-center px-4 py-12">
    <div class="w-full max-w-5xl grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 items-center">
        <!-- Showcase image -->
        <div class="hidden lg:block">
            <div class="relative overflow-hidden rounded-2xl shadow-2xl ring-1 ring-gray-200 dark:ring-gray-700">
                <img src="{{ asset('assets/images/pages/c.png') }}" alt="Conducting an official legal search online"
                     class="w-full h-full max-h-[36rem] object-cover" loading="lazy">
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-6">
                    <p class="text-lg font-semibold text-white">Official Legal Search, anywhere.</p>
                    <p class="mt-1 text-sm text-gray-200">Verify land records securely from any device.</p>
                </div>
            </div>
        </div>

        <!-- Form column -->
        <div class="w-full max-w-md mx-auto">
        <!-- Page heading -->
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Sign In</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Sign in to conduct legal searches</p>
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

            @if(session('status'))
                <div class="mb-4 rounded-lg border border-green-200 dark:border-green-700 bg-green-50 dark:bg-green-900/30 p-4">
                    <p class="text-sm text-green-700 dark:text-green-400">{{ session('status') }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('ols.login.submit') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                    <input
                        id="username" type="text" name="username" value="{{ old('username') }}" required autofocus
                        autocomplete="username"
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 dark:focus:ring-cyan-700"
                        placeholder="your_username">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                    <input
                        id="password" type="password" name="password" required
                        autocomplete="current-password"
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 dark:focus:ring-cyan-700"
                        placeholder="••••••••">
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-cyan-600 focus:ring-cyan-500">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Remember me</span>
                    </label>
                    <a href="{{ route('ols.password.email') }}" class="text-sm text-cyan-600 hover:text-cyan-800 dark:text-cyan-400">
                        Forgot password?
                    </a>
                </div>

                <button type="submit" class="w-full rounded-lg bg-cyan-600 px-4 py-2.5 font-semibold text-white shadow hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2">
                    Sign In
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-600 dark:text-gray-400">
                Don't have an account?
                <a href="{{ route('ols.register') }}" class="font-medium text-cyan-600 hover:text-cyan-800 dark:text-cyan-400">Create one</a>
            </p>
        </div>

        <p class="mt-6 text-center text-xs text-gray-500 dark:text-gray-400">
            <a href="{{ route('ols.landing') }}" class="hover:text-cyan-600">&larr; Back to home</a>
        </p>
        </div>
    </div>
    </div>

    @include('online_legal_search.partials.footer')
</div>
@endsection
