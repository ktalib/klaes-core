@extends('online_legal_search.layout')

@section('title', 'Reset Password')

@section('body')
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-cyan-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 px-4 py-12">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <a href="{{ route('ols.landing') }}" class="inline-block">
                <img src="{{ asset('storage/upload/logo/logo.png') }}" alt="KLAES" class="mx-auto mb-4 h-16 w-auto"
                     onerror="this.onerror=null;this.src='{{ asset('assets/logo/logo.png') }}';">
            </a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Reset Password</h1>
        </div>

        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-lg sm:p-8">
            @if($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/30 p-4">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-700 dark:text-red-400">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('ols.password.update') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
                    <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}" required readonly
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 px-4 py-2.5 text-gray-900 dark:text-gray-100">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
                    <input id="password" type="password" name="password" required
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200"
                        placeholder="Minimum 8 characters">
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm Password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required
                        class="mt-2 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-gray-900 dark:text-gray-100 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200"
                        placeholder="Re-enter password">
                </div>

                <button type="submit" class="w-full rounded-lg bg-cyan-600 px-4 py-2.5 font-semibold text-white shadow hover:bg-cyan-700">
                    Reset Password
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
