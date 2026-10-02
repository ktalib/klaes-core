@extends('phs.layouts.app')

@section('title', 'Get Started — PHS')

@section('content')
<div id="preloader" style="position:fixed;inset:0;background:var(--phs-preloader-bg,#fff);display:flex;align-items:center;justify-content:center;z-index:9999;">
    <img src="http://app.klaes.ng/storage/upload/logo/klas_logo.gif" alt="Loading..." style="width:200px;height:auto;">
</div>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', sans-serif; background: #f0f2ff; min-height: 100vh; }

    .blob-1 { position: fixed; top: -120px; left: -120px; width: 480px; height: 480px; border-radius: 50%; background: #6c63ff; opacity: .14; pointer-events: none; z-index: 0; }
    .blob-2 { position: fixed; bottom: -160px; right: -100px; width: 520px; height: 520px; border-radius: 50%; background: #06d6a0; opacity: .11; pointer-events: none; z-index: 0; }
    .blob-3 { position: fixed; top: 40%; right: 6%; width: 240px; height: 240px; border-radius: 50%; background: #6c63ff; opacity: .06; pointer-events: none; z-index: 0; }

    .navbar { position: sticky; top: 0; z-index: 50; display: flex; align-items: center; justify-content: space-between; padding: 10px 32px; background: rgba(255,255,255,.88); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(108,99,255,.10); box-shadow: 0 1px 0 rgba(0,0,0,.04); min-height: 80px; }
    .navbar-brand { display: flex; align-items: center; gap: 10px; }
    .navbar-brand img { height: 64px; width: auto; }
    .navbar-brand span { font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 600; color: #3d3a4e; letter-spacing: .04em; }
    .navbar-actions { display: flex; align-items: center; gap: 10px; }
    .btn-ghost { padding: 8px 18px; border: 1.5px solid #d0cfe8; border-radius: 8px; background: transparent; font-size: 13px; font-weight: 500; color: #3d3a4e; text-decoration: none; cursor: pointer; transition: background .15s; }
    .btn-ghost:hover { background: #f4f3ff; }
    .btn-primary { padding: 8px 18px; border: none; border-radius: 8px; background: #6c63ff; font-size: 13px; font-weight: 500; color: #fff; text-decoration: none; cursor: pointer; transition: background .15s; }
    .btn-primary:hover { background: #5a52e0; }
    .mobile-menu-btn { display: none; border: none; background: transparent; padding: 8px; cursor: pointer; border-radius: 8px; }
    .mobile-menu { display: none; flex-direction: column; gap: 10px; padding: 16px 32px; background: rgba(255,255,255,.96); border-bottom: 1px solid rgba(108,99,255,.10); }
    .mobile-menu a { text-align: center; }
    @media (max-width: 768px) { .navbar-actions { display: none; } .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } .mobile-menu.open { display: flex; } }

    .page-wrapper { position: relative; z-index: 1; max-width: 1060px; margin: 0 auto; padding: 40px 20px 80px; }

    .step-badge { display: inline-flex; align-items: center; gap: 6px; font-family: 'Sora', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #6c63ff; background: #ededff; border-radius: 20px; padding: 5px 14px; margin-bottom: 28px; }
    .step-badge::before { content: ''; display: inline-block; width: 6px; height: 6px; background: #6c63ff; border-radius: 50%; }

    .main-grid { display: grid; grid-template-columns: 1fr 1.55fr; gap: 24px; align-items: start; }
    @media (max-width: 820px) { .main-grid { grid-template-columns: 1fr; } }

    /* Sidebar */
    .sidebar-card { background: #6c63ff; border-radius: 20px; padding: 32px 28px; color: #fff; position: sticky; top: 84px; }
    .sb-logo { height: 30px; width: auto; margin-bottom: 24px; filter: brightness(0) invert(1); opacity: .85; }
    .sb-eyebrow { font-size: 10px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.55); margin-bottom: 10px; }
    .sb-title { font-family: 'Sora', sans-serif; font-size: 22px; font-weight: 700; line-height: 1.25; color: #fff; margin-bottom: 14px; }
    .sb-desc { font-size: 13px; line-height: 1.7; color: rgba(255,255,255,.72); margin-bottom: 24px; }

    /* Package cards in sidebar */
    .pkg-list { display: flex; flex-direction: column; gap: 10px; }
    .pkg-item { background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.14); border-radius: 12px; padding: 13px 15px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background .15s; }
    .pkg-item:hover { background: rgba(255,255,255,.17); }
    .pkg-item.active { background: rgba(255,255,255,.22); border-color: rgba(255,255,255,.45); }
    .pk-name { font-size: 13px; font-weight: 600; color: #fff; }
    .pk-tok { font-size: 10px; color: rgba(255,255,255,.55); margin-top: 2px; }
    .pk-right { display: flex; align-items: center; gap: 8px; }
    .pk-price { font-family: 'Sora', sans-serif; font-size: 14px; font-weight: 700; color: #fff; }
    .pk-dot { width: 8px; height: 8px; border-radius: 50%; border: 2px solid rgba(255,255,255,.35); transition: all .15s; flex-shrink: 0; }
    .pkg-item.active .pk-dot { background: #fff; border-color: #fff; }

    /* Form card */
    .form-card { background: #fff; border-radius: 20px; border: 1px solid rgba(108,99,255,.10); box-shadow: 0 4px 24px rgba(108,99,255,.07); padding: 32px 32px; }
    .section-title { font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 700; color: #1e1b3a; letter-spacing: .04em; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    .section-title .icon { width: 28px; height: 28px; border-radius: 8px; background: #ededff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .section-title .icon svg { width: 14px; height: 14px; stroke: #6c63ff; fill: none; stroke-width: 2; }
    .section-divider { height: 1px; background: #eeecfa; margin: 24px 0; }

    /* Fields */
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    @media (max-width: 540px) { .field-row { grid-template-columns: 1fr; } }
    .field { display: flex; flex-direction: column; }
    .field-label { font-size: 12px; font-weight: 600; color: #4a4760; margin-bottom: 7px; letter-spacing: .03em; }
    .field-label .req { color: #6c63ff; margin-left: 2px; }
    .field-input, .field-select, .field-textarea { width: 100%; padding: 11px 16px; background: #fafafa; border: 1.5px solid #e2e0f0; border-radius: 10px; font-family: inherit; font-size: 13px; color: #1e1b3a; outline: none; transition: border-color .2s, box-shadow .2s; appearance: none; }
    .field-input:focus, .field-select:focus, .field-textarea:focus { border-color: #6c63ff; box-shadow: 0 0 0 3px rgba(108,99,255,.12); background: #fff; }
    .field-input::placeholder, .field-textarea::placeholder { color: #bcbbcc; }
    .select-wrap { position: relative; }
    .select-wrap::after { content: ''; position: absolute; right: 14px; top: 50%; transform: translateY(-50%); width: 0; height: 0; border-left: 4px solid transparent; border-right: 4px solid transparent; border-top: 5px solid #8b88aa; pointer-events: none; }
    .field-select { padding-right: 36px; }
    .field-textarea { resize: vertical; min-height: 90px; line-height: 1.6; }

    /* Error */
    .error-box { background: #fff5f5; border: 1px solid #ffc5c5; border-radius: 12px; padding: 14px 18px; margin-bottom: 24px; }
    .error-box p { font-size: 13px; font-weight: 600; color: #c0392b; margin-bottom: 8px; }
    .error-box ul { padding-left: 18px; }
    .error-box li { font-size: 13px; color: #c0392b; line-height: 1.7; }

    /* Actions */
    .action-row { display: flex; align-items: center; justify-content: space-between; margin-top: 30px; gap: 12px; flex-wrap: wrap; }
    .btn-cancel { display: inline-flex; align-items: center; gap: 7px; padding: 11px 22px; border-radius: 10px; border: 1.5px solid #d6d4ee; background: #fff; font-size: 13px; font-weight: 600; color: #4a4760; text-decoration: none; cursor: pointer; transition: background .15s; }
    .btn-cancel:hover { background: #f4f3ff; }
    .btn-submit { display: inline-flex; align-items: center; gap: 8px; padding: 12px 30px; border-radius: 10px; border: none; background: #6c63ff; font-size: 14px; font-weight: 600; color: #fff; cursor: pointer; transition: background .15s, transform .1s; box-shadow: 0 4px 18px rgba(108,99,255,.35); }
    .btn-submit:hover { background: #5a52e0; }
    .btn-submit:active { transform: scale(.98); }
</style>

<div class="min-h-screen" style="background:#f0f2ff;">
    <div class="blob-1"></div>
    <div class="blob-2"></div>
    <div class="blob-3"></div>

    <div class="page-content-layer">
    <!-- Responsive Navigation -->
    @include('phs.partials.landing-header')

    <!-- Existing Get Started Content -->
    <div class="min-h-screen bg-white">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="grid gap-8 lg:grid-cols-12 lg:items-center">
                <div class="lg:col-span-6">
                    <h2 class="mb-6 text-3xl font-bold text-gray-900">Explore your options to get started</h2>
                    <p class="mb-6 text-gray-600">Your PHS account unlocks different ways to experience the platform. Choose the option that best fits your organisation—create an account, request Organizational access, or purchase tokens.</p>
                    <div class="flex gap-4">
                        <a href="{{ route('phs.request.form') }}" class="rounded-md bg-blue-600 px-4 py-2 text-white font-semibold">Request Organizational Access</a>
                        <a href="{{ route('phs.login') }}" class="rounded-md border border-gray-300 px-4 py-2 text-gray-700">Sign In</a>
                    </div>
                </div>
                <div class="hidden lg:block lg:col-span-6">
                    <img src="https://media.istockphoto.com/id/1273000181/photo/portrait-of-african-muslim-woman-in-hijab-and-headset-at-home.jpg?s=612x612&w=0&k=20&c=AajXM_EvxRQdAt5txp6SaFCaZwejLQWsbpYFECGxstM=" alt="Get started" class="w-full rounded-lg shadow-lg object-cover">
                </div>
            </div>

            <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                <div class="p-6 text-center rounded-2xl border">
                    <div class="mb-4">
                        <i data-lucide="user" class="mx-auto h-8 w-8 text-blue-600"></i>
                    </div>
                    <h3 class="mb-2 font-semibold text-lg">Create a public account</h3>
                    <p class="text-sm text-gray-600">A free personal account for limited searches and learning.</p>
                    <div class="mt-4"><a href="{{ route('phs.request.form') }}" class="text-blue-600">Create an account →</a></div>
                </div>

                <div class="p-6 text-center rounded-2xl border">
                    <div class="mb-4">
                        <i data-lucide="clock" class="mx-auto h-8 w-8 text-green-600"></i>
                    </div>
                    <h3 class="mb-2 font-semibold text-lg">Start a free trial</h3>
                    <p class="text-sm text-gray-600">Trial access for evaluation of platform features.</p>
                    <div class="mt-4"><a href="{{ route('phs.request.form') }}" class="text-blue-600">Create a trial account →</a></div>
                </div>

                <div class="p-6 text-center rounded-2xl border">
                    <div class="mb-4">
                        <i data-lucide="shopping-cart" class="mx-auto h-8 w-8 text-purple-600"></i>
                    </div>
                    <h3 class="mb-2 font-semibold text-lg">Purchase Tokens</h3>
                    <p class="text-sm text-gray-600">Buy token packages for Organizational searches.</p>
                    <div class="mt-4"><a href="{{ route('phs.request.form') }}" class="text-blue-600">Buy tokens →</a></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pricing Section (Token Packages) — moved to last -->
    <div class="bg-gradient-to-br from-gray-50 to-blue-50 py-12 sm:py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-8 text-center sm:mb-12">
                <h2 class="mb-4 text-2xl font-bold text-gray-900 sm:text-3xl md:text-4xl">
                    Flexible Token Packages
                </h2>
            </div>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 sm:gap-8">
                <!-- Starter Package -->
                <div class="rounded-2xl bg-white p-6 shadow-lg sm:p-8">
                    <h3 class="mb-2 text-xl font-bold text-green-600 sm:text-2xl">Starter</h3>
                    <div class="mb-2 text-3xl font-bold sm:text-4xl">₦50,000</div>
                    <p class="mb-4 text-gray-500">2,000 Tokens</p>
                    <button class="landing-package-btn w-full rounded-lg bg-green-600 py-3 text-white transition hover:bg-green-700" data-tokens="2000" data-price="50000" data-name="Starter">
                        Get Started
                    </button>
                </div>

                <!-- Professional Package -->
                <div class="relative rounded-2xl border-2 border-blue-500 bg-white p-6 shadow-lg sm:p-8">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 transform whitespace-nowrap rounded-full bg-gradient-to-r from-blue-500 to-pink-500 px-4 py-1 text-xs font-semibold text-white">
                        POPULAR
                    </div>
                    <h3 class="mb-2 text-xl font-bold text-blue-600 sm:text-2xl">Professional</h3>
                    <div class="mb-2 text-3xl font-bold sm:text-4xl">₦100,000</div>
                    <p class="mb-4 text-gray-500">5,000 Tokens</p>
                    <button class="landing-package-btn w-full rounded-lg bg-blue-600 py-3 text-white transition hover:bg-blue-700" data-tokens="5000" data-price="100000" data-name="Professional">
                        Get Started
                    </button>
                </div>

                <!-- Enterprise Package -->
                <div class="rounded-2xl bg-white p-6 shadow-lg sm:col-span-2 lg:col-span-1 sm:p-8">
                    <h3 class="mb-2 text-xl font-bold text-purple-600 sm:text-2xl">Enterprise</h3>
                    <div class="mb-2 text-3xl font-bold sm:text-4xl">₦180,000</div>
                    <p class="mb-4 text-gray-500">10,000 Tokens</p>
                    <button class="landing-package-btn w-full rounded-lg bg-purple-600 py-3 text-white transition hover:bg-purple-700" data-tokens="10000" data-price="180000" data-name="Enterprise">
                        Get Started
                    </button>
                </div>
            </div>
        </div>
    </div>

    @include('phs.partials.landing-footer')

    <script>
    // Handle package button clicks to redirect to onboarding request
    document.querySelectorAll('.landing-package-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const name = this.dataset.name;
            window.location.href = "{{ route('phs.request.form') }}?package=" + encodeURIComponent(name);
        });
    });
    </script>
    </div>
</div>
@endsection
