@extends('phs.layouts.app')

@section('title', 'KLAES - Property History Search Portal')

@section('content')
<div id="preloader" style="position:fixed;inset:0;background:var(--phs-preloader-bg,#fff);display:flex;align-items:center;justify-content:center;z-index:9999;">
    <img src="http://app.klaes.ng/storage/upload/logo/klas_logo.gif" alt="Loading..." style="width:200px;height:auto;">
</div>
<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-green-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">
    <!-- Responsive Navigation -->
    @include('phs.partials.landing-header')

    <!-- Image Slider Banner -->
    <div class="relative mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8">
        <div class="slider-container shadow-xl" id="hero-slider">
            <div class="slide active" style="background-image: url('http://app.klaes.ng/storage/upload/logo/5.jpeg')">
                <div class="slide-content">
                    <h3 class="text-2xl font-bold">Official Land Records</h3>
                    <p class="text-lg">Access verified property information directly from the government database</p>
                </div>
            </div>
            <div class="slide" style="background-image: url('http://app.klaes.ng/storage/upload/logo/4.jpeg')">
                <div class="slide-content">
                    <h3 class="text-2xl font-bold">Bank &amp; Legal Institution Search</h3>
                    <p class="text-lg">Trusted by leading banks and law firms across Nigeria</p>
                </div>
            </div>
            <div class="slide" style="background-image: url('http://app.klaes.ng/storage/upload/logo/2.jpeg')">
                <div class="slide-content">
                    <h3 class="text-2xl font-bold">Token-Based System</h3>
                    <p class="text-lg">Flexible packages starting from 2,000 tokens</p>
                </div>
            </div>
            <div class="slide" style="background-image: url('http://app.klaes.ng/storage/upload/logo/3.jpeg')">
                <div class="slide-content">
                    <h3 class="text-2xl font-bold">Instant Search Results</h3>
                    <p class="text-lg">Get official search slips instantly with just 1 token</p>
                </div>
            </div>
            <button class="slider-btn prev" id="prevSlide" aria-label="Previous slide">&#10094;</button>
            <button class="slider-btn next" id="nextSlide" aria-label="Next slide">&#10095;</button>
            <div class="slider-dots" id="sliderDots"></div>
        </div>
    </div>

    <!-- Hero Section -->
    <div class="relative mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 md:py-16">
        <div class="text-center">
            <div class="mb-6 inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900/40 px-3 py-1 text-xs font-medium text-blue-700 dark:text-blue-300">
                <i data-lucide="crown" class="mr-2 h-4 w-4"></i> Official Government Platform
            </div>
            <h1 class="mb-6 text-3xl font-extrabold leading-tight text-gray-900 dark:text-gray-100 sm:text-5xl md:text-6xl lg:text-7xl">
                Kano State Ministry of Land
                <span class="bg-gradient-to-r from-blue-600 to-green-600 bg-clip-text text-transparent">and Physical Planning Property History Search (PHS) Portal</span>
            </h1>
            <p class="mx-auto mb-10 max-w-3xl text-base text-gray-600 dark:text-gray-400 sm:text-lg md:text-xl">
                Secure, token-based legal search for banks, law firms, and corporate institutions.
            </p>
            <div class="flex flex-col justify-center gap-4 sm:flex-row">
                <a href="{{ route('phs.login') }}" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white shadow-lg transition-all hover:bg-blue-700 hover:shadow-xl sm:px-8 sm:py-4">
                    Get Started
                    <i data-lucide="arrow-right" class="ml-2 h-5 w-5"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="bg-white dark:bg-gray-800 py-12 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8 text-center sm:mb-12">
                <h2 class="mb-4 text-2xl font-bold text-gray-900 dark:text-gray-100 sm:text-3xl md:text-4xl">
                    Why Choose KLAES Enterprise?
                </h2>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 md:grid-cols-3 sm:gap-8">
                <div class="p-4 text-center sm:p-6">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-100 dark:bg-blue-900/30 sm:mb-5 sm:h-20 sm:w-20">
                        <i data-lucide="coins" class="h-8 w-8 text-blue-600 dark:text-blue-400 sm:h-10 sm:w-10"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold dark:text-gray-100 sm:mb-3 sm:text-xl">Token-Based System</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 sm:text-base">
                        Pay-as-you-go with flexible token packages. Each search consumes 1 token.
                    </p>
                </div>
                <div class="p-4 text-center sm:p-6">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-green-100 dark:bg-green-900/30 sm:mb-5 sm:h-20 sm:w-20">
                        <i data-lucide="shield" class="h-8 w-8 text-green-600 dark:text-green-400 sm:h-10 sm:w-10"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold dark:text-gray-100 sm:mb-3 sm:text-xl">Official & Secure</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 sm:text-base">
                        Government-verified records with official search slips.
                    </p>
                </div>
                <div class="p-4 text-center sm:p-6">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-purple-100 dark:bg-purple-900/30 sm:mb-5 sm:h-20 sm:w-20">
                        <i data-lucide="clock" class="h-8 w-8 text-purple-600 dark:text-purple-400 sm:h-10 sm:w-10"></i>
                    </div>
                    <h3 class="mb-2 text-lg font-semibold dark:text-gray-100 sm:mb-3 sm:text-xl">Instant Results</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 sm:text-base">
                        Real-time searches with downloadable official slips.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pricing Section -->
    <div class="bg-gradient-to-br from-gray-50 to-blue-50 dark:from-gray-900 dark:to-gray-800 py-12 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8 text-center sm:mb-12">
                <h2 class="mb-4 text-2xl font-bold text-gray-900 dark:text-gray-100 sm:text-3xl md:text-4xl">
                    Flexible Token Packages
                </h2>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 sm:gap-8">
                <!-- Starter Package -->
                <div class="rounded-2xl bg-white dark:bg-gray-800 dark:border dark:border-gray-700 p-6 shadow-lg sm:p-8">
                    <h3 class="mb-2 text-xl font-bold text-green-600 dark:text-green-400 sm:text-2xl">Starter</h3>
                    <div class="mb-2 text-3xl font-bold dark:text-gray-100 sm:text-4xl">₦50,000</div>
                    <p class="mb-4 text-gray-500 dark:text-gray-400">2,000 Tokens</p>
                    <button class="landing-package-btn w-full rounded-lg bg-green-600 py-3 text-white transition hover:bg-green-700" data-tokens="2000" data-price="50000" data-name="Starter">
                        Get Started
                    </button>
                </div>

                <!-- Professional Package -->
                <div class="relative rounded-2xl border-2 border-blue-500 bg-white dark:bg-gray-800 p-6 shadow-lg sm:p-8">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 transform whitespace-nowrap rounded-full bg-gradient-to-r from-blue-500 to-pink-500 px-4 py-1 text-xs font-semibold text-white">
                        POPULAR
                    </div>
                    <h3 class="mb-2 text-xl font-bold text-blue-600 dark:text-blue-400 sm:text-2xl">Professional</h3>
                    <div class="mb-2 text-3xl font-bold dark:text-gray-100 sm:text-4xl">₦100,000</div>
                    <p class="mb-4 text-gray-500 dark:text-gray-400">5,000 Tokens</p>
                    <button class="landing-package-btn w-full rounded-lg bg-blue-600 py-3 text-white transition hover:bg-blue-700" data-tokens="5000" data-price="100000" data-name="Professional">
                        Get Started
                    </button>
                </div>

                <!-- Enterprise Package -->
                <div class="rounded-2xl bg-white dark:bg-gray-800 dark:border dark:border-gray-700 p-6 shadow-lg sm:col-span-2 lg:col-span-1 sm:p-8">
                    <h3 class="mb-2 text-xl font-bold text-purple-600 dark:text-purple-400 sm:text-2xl">Enterprise</h3>
                    <div class="mb-2 text-3xl font-bold dark:text-gray-100 sm:text-4xl">₦180,000</div>
                    <p class="mb-4 text-gray-500 dark:text-gray-400">10,000 Tokens</p>
                    <button class="landing-package-btn w-full rounded-lg bg-purple-600 py-3 text-white transition hover:bg-purple-700" data-tokens="10000" data-price="180000" data-name="Enterprise">
                        Get Started
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-700 py-12 sm:py-16">
        <div class="mx-auto max-w-4xl px-4 text-center">
            <h2 class="mb-4 text-2xl font-bold text-white sm:text-3xl md:text-4xl">
                Ready to Transform Your Legal Search Process?
            </h2>
            <a href="{{ route('phs.request.form') }}" class="inline-flex items-center rounded-lg bg-white px-6 font-semibold text-blue-600 transition hover:shadow-xl sm:px-8 sm:py-4 py-3 text-sm sm:text-base">
                Start Your Journey
                <i data-lucide="arrow-right" class="ml-2 h-5 w-5"></i>
            </a>
        </div>
    </div>

    <!-- Footer -->
    @include('phs.partials.landing-footer')
</div>

<script>
// ---- Hero image slider ----
(function () {
    const slides = Array.from(document.querySelectorAll('#hero-slider .slide'));
    const dotsContainer = document.getElementById('sliderDots');
    if (!slides.length || !dotsContainer) return;

    let current = 0;
    let timer = null;

    slides.forEach((_, i) => {
        const dot = document.createElement('div');
        dot.className = 'dot' + (i === 0 ? ' active' : '');
        dot.addEventListener('click', () => goTo(i));
        dotsContainer.appendChild(dot);
    });
    const dots = Array.from(dotsContainer.children);

    function render() {
        slides.forEach((s, i) => s.classList.toggle('active', i === current));
        dots.forEach((d, i) => d.classList.toggle('active', i === current));
    }
    function goTo(i) { current = (i + slides.length) % slides.length; render(); restart(); }
    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }
    function restart() { clearInterval(timer); timer = setInterval(next, 5000); }

    document.getElementById('nextSlide')?.addEventListener('click', next);
    document.getElementById('prevSlide')?.addEventListener('click', prev);
    restart();
})();

// Handle package button clicks to redirect to onboarding request
document.querySelectorAll('.landing-package-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const name = this.dataset.name;
        window.location.href = "{{ route('phs.request.form') }}?package=" + encodeURIComponent(name);
    });
});
</script>
@endsection
