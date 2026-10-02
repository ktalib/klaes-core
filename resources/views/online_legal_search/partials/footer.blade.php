{{--
    Online Legal Search — shared footer.
    Footer logo: http://app.klaes.ng/assets/logo/Left_Logo.png
--}}
<footer class="bg-gray-900 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex flex-col sm:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-3">
                <img src="{{ asset('assets/logo/Left_Logo.png') }}" alt="LAnd ADmin Enterprise System"
                     class="h-12 w-auto rounded bg-white p-1"
                     onerror="this.style.display='none';">
                <div class="leading-tight text-left">
                    <span class="block text-sm font-semibold text-white"> LAnd ADmin Enterprise System</span>
                    <span class="block text-xs text-gray-400">Online Legal Search Portal</span>
                </div>
            </div>
            <div class="text-center sm:text-right text-sm text-gray-400">
                <p>&copy; {{ date('Y') }}  LAnd ADmin Enterprise System (KLAES). All rights reserved.</p>
                <p class="mt-1 text-xs">Empowering Kano with transparent and efficient land administration.</p>
            </div>
        </div>
    </div>
</footer>
