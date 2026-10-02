{{--
    Online Legal Search — centered brand block for auth screens.
    Header logo: the ONLS lockup (assets/logo/ols-light-logo.jpeg, dark variant swaps on .dark).
    Optional: pass $subtitle to override the tagline.
--}}
<div class="mb-8 text-center">
    <a href="{{ route('ols.landing') }}" class="inline-block">
        <img src="{{ asset('assets/logo/ols-light-logo.jpeg') }}" alt="Online Legal Search"
             class="mx-auto mb-4 h-20 w-auto object-contain dark:hidden">
        <img src="{{ asset('assets/logo/ols-dark-logo.jpeg') }}" alt="Online Legal Search"
             class="mx-auto mb-4 h-20 w-auto object-contain hidden dark:block">
    </a>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Online Legal Search</h1>
    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $subtitle ?? ' LAnd ADmin Enterprise System' }}</p>
</div>
