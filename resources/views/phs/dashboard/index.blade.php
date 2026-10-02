<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
  @include('phs.partials.favicon')
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>KLAES Enterprise - Organizational Search History Platform</title>
  <script>
    (function() {
      const t = localStorage.getItem('phs-theme');
      if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches))
        document.documentElement.classList.add('dark');
    })();
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: "#3b82f6",
            "primary-foreground": "#ffffff",
            muted: "#f3f4f6",
            "muted-foreground": "#6b7280",
            border: "#e5e7eb",
            destructive: "#ef4444",
            "destructive-foreground": "#ffffff",
            secondary: "#f1f5f9",
            "secondary-foreground": "#0f172a",
          },
        },
      },
    };

    function phsToggleTheme() {
      const isDark = document.documentElement.classList.toggle('dark');
      localStorage.setItem('phs-theme', isDark ? 'dark' : 'light');
      if (window.lucide) window.lucide.createIcons();
    }
  </script>

  <style>
    /* Select2 custom overrides for premium look and feel */
    .select2-container { width: 100% !important; }
    .select2-container--default .select2-selection--single {
      height: 3rem;
      border-color: #d1d5db;
      border-radius: 0.5rem;
      padding-left: 0.75rem;
      display: flex;
      align-items: center;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
      color: #1f2937;
      font-size: 0.95rem;
      padding-left: 0;
    }
    .select2-container--default .select2-selection--single .select2-selection__placeholder { color: #9ca3af; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 3rem; right: 0.5rem; }
    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--single {
      border-color: #3b82f6;
      box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }
    .dark .select2-container--default .select2-selection--single {
      background-color: #374151;
      border-color: #4b5563;
    }
    .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
      color: #f3f4f6;
    }
    .dark .select2-dropdown {
      background-color: #374151;
      border-color: #4b5563;
    }
    .dark .select2-container--default .select2-results__option {
      color: #f3f4f6;
    }
    .dark .select2-container--default .select2-results__option--highlighted[aria-selected] {
      background-color: #3b82f6;
      color: #ffffff;
    }
    .dark .select2-container--default .select2-search--dropdown .select2-search__field {
      background-color: #1f2937;
      border-color: #4b5563;
      color: #ffffff;
    }
    .select2-dropdown { border-color:#d1d5db; border-radius:.5rem; box-shadow:0 10px 25px rgba(0,0,0,.08); background:#fff; }
    .select2-container--default .select2-search--dropdown .select2-search__field {
      border-color: #d1d5db;
      border-radius: 0.375rem;
      padding: 6px 12px;
    }
    .select2-container--default .select2-results__option { color:#111827; }
    .select2-container--default .select2-results__option--highlighted[aria-selected] { background:#2563eb; color:#fff; }
    .select2-results__message { color:#6b7280; }

    :root {
      --phs-border: #e5e7eb;
      --phs-timeline-dot: #3b82f6;
      --phs-timeline-dot-border: white;
      --phs-timeline-dot-shadow: #e5e7eb;
      --phs-spinner-track: #e5e7eb;
      --phs-spinner-head: #3b82f6;
      --phs-table-hover: #f8fafc;
      --phs-preloader-bg: #ffffff;
    }
    .dark {
      --phs-border: #374151;
      --phs-timeline-dot: #60a5fa;
      --phs-timeline-dot-border: #1f2937;
      --phs-timeline-dot-shadow: #374151;
      --phs-spinner-track: #374151;
      --phs-spinner-head: #60a5fa;
      --phs-table-hover: #1f2937;
      --phs-preloader-bg: #111827;
    }

    .loading-spinner {
      width: 1rem;
      height: 1rem;
      border: 2px solid var(--phs-spinner-track);
      border-top: 2px solid var(--phs-spinner-head);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }

    .timeline-item {
      border-left: 2px solid var(--phs-border);
      position: relative;
      padding-left: 1.5rem;
      padding-bottom: 1.5rem;
    }

    .timeline-item:last-child {
      padding-bottom: 0;
    }

    .timeline-item::before {
      content: "";
      position: absolute;
      left: -0.5rem;
      top: 0;
      width: 1rem;
      height: 1rem;
      border-radius: 50%;
      background: var(--phs-timeline-dot);
      border: 2px solid var(--phs-timeline-dot-border);
      box-shadow: 0 0 0 2px var(--phs-timeline-dot-shadow);
    }

    .timeline-item.completed::before {
      background: #10b981;
    }

    .token-display {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    /* Source tags */
    .phs-source-tag {
      display: inline-flex;
      align-items: center;
      padding: 0.15rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.6rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      white-space: nowrap;
      border: 1px solid transparent;
    }

    .phs-source-tag-file_history_staging { background: #dbeafe; color: #1e40af; border-color: #bfdbfe; }
    .phs-source-tag-CofO_staging         { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
    .phs-source-tag-pra                  { background: #fef3c7; color: #92400e; border-color: #fde68a; }
    .phs-source-tag-deed_registrations   { background: #ede9fe; color: #5b21b6; border-color: #ddd6fe; }
    /* Synthetic LS report rows (File Commissioning / Temporary File), slugified in portal.js */
    .phs-source-tag-file-commissioning   { background: #f3f4f6; color: #374151; border-color: #e5e7eb; }
    .phs-source-tag-temporary-file       { background: #f3f4f6; color: #374151; border-color: #e5e7eb; }

    /* Dark mode source tag overrides */
    .dark .phs-source-tag-file_history_staging { background: #1e3a5f; color: #93c5fd; border-color: #1d4ed8; }
    .dark .phs-source-tag-CofO_staging         { background: #064e3b; color: #6ee7b7; border-color: #065f46; }
    .dark .phs-source-tag-pra                  { background: #451a03; color: #fcd34d; border-color: #92400e; }
    .dark .phs-source-tag-deed_registrations   { background: #2e1065; color: #c4b5fd; border-color: #5b21b6; }
    .dark .phs-source-tag-file-commissioning   { background: #1f2937; color: #d1d5db; border-color: #374151; }
    .dark .phs-source-tag-temporary-file       { background: #1f2937; color: #d1d5db; border-color: #374151; }

    .package-card {
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .package-card.selected {
      border: 2px solid #3b82f6 !important;
      background-color: #eff6ff;
    }
    .dark .package-card.selected {
      background-color: #1e3a5f;
    }

    .page {
      transition: opacity 0.3s ease;
    }

    .page-hidden {
      display: none;
    }

    .result-card {
      transition: all 0.3s ease;
    }

    .result-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 20px 25px -12px rgba(0, 0, 0, 0.15);
    }

    /* Slider Styles */
    .slider-container {
      position: relative;
      width: 100%;
      height: 500px;
      overflow: hidden;
      border-radius: 1rem;
    }

    .slide {
      position: absolute;
      width: 100%;
      height: 100%;
      opacity: 0;
      transition: opacity 0.5s ease-in-out;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
    }

    .slide.active {
      opacity: 1;
    }

    .slide-content {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
      color: white;
      padding: 2rem;
      text-align: center;
    }

    .slider-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(255, 255, 255, 0.3);
      backdrop-filter: blur(5px);
      color: white;
      border: none;
      padding: 1rem;
      cursor: pointer;
      border-radius: 50%;
      transition: all 0.3s ease;
      z-index: 10;
    }

    .slider-btn:hover {
      background: rgba(255, 255, 255, 0.5);
      transform: translateY(-50%) scale(1.1);
    }

    .slider-btn.prev {
      left: 1rem;
    }

    .slider-btn.next {
      right: 1rem;
    }

    .slider-dots {
      position: absolute;
      bottom: 1rem;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 0.5rem;
      z-index: 10;
    }

    .dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.5);
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .dot.active {
      background: white;
      width: 20px;
      border-radius: 10px;
    }

    /* Mobile menu styles */
    .mobile-menu-open {
      max-height: 300px;
      opacity: 1;
      visibility: visible;
    }

    .mobile-menu-closed {
      max-height: 0;
      opacity: 0;
      visibility: hidden;
    }

    /* Responsive slider */
    @media (max-width: 768px) {
      .slider-container {
        height: 300px;
      }
      
      .slide-content h3 {
        font-size: 1.25rem;
      }
      
      .slide-content p {
        font-size: 0.875rem;
      }
    }

    /* Print styles */
    @media print {
      .no-print {
        display: none !important;
      }

      body {
        background: white;
        padding: 0;
        margin: 0;
      }

      .print-container {
        padding: 20px;
      }

      .timeline-item {
        break-inside: avoid;
        page-break-inside: avoid;
      }
    }
  </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-green-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800 dark:text-gray-100">

  <!-- Preloader -->
  <div id="preloader" style="position:fixed;inset:0;background:var(--phs-preloader-bg,#fff);display:flex;align-items:center;justify-content:center;z-index:9999;">
    <img src="http://app.klaes.ng/storage/upload/logo/klas_logo.gif" alt="Loading..." style="width:200px;height:auto;">
  </div>

  <!-- ==================== LANDING PAGE ==================== -->
  <div id="landing-page" class="page page-hidden">
    <div class="relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-blue-600/5 via-transparent to-green-600/5"></div>

      <!-- Responsive Navigation -->
      <nav class="relative z-10 bg-white/80 dark:bg-gray-900/80 backdrop-blur-md shadow-sm sticky top-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-4">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center overflow-hidden bg-gradient-to-r from-blue-600 to-purple-600">
                <img src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" alt="PHS Logo" class="w-full h-full object-cover dark:hidden" />
                <img src="{{ asset('assets/logo/phs-dark-logo.jpeg') }}" alt="PHS Logo" class="w-full h-full object-cover hidden dark:block" />
              </div>
              <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">KLAES</h1>
              </div>
            </div>

            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center space-x-4">
              <button onclick="phsToggleTheme()" title="Toggle dark mode"
                class="rounded-md p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <i data-lucide="sun" class="w-5 h-5 dark:hidden"></i>
                <i data-lucide="moon" class="w-5 h-5 hidden dark:block"></i>
              </button>
              <button id="landing-signin-btn"
                class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                Sign In
              </button>
              <button id="landing-register-btn"
                class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700">
                Register
              </button>
            </div>

            <!-- Mobile Menu Button -->
            <button id="mobile-menu-btn" class="md:hidden p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
              <i data-lucide="menu" class="w-6 h-6 text-gray-700 dark:text-gray-300"></i>
            </button>
          </div>

          <!-- Mobile Menu Dropdown -->
          <div id="mobile-menu" class="md:hidden overflow-hidden transition-all duration-300 ease-in-out max-h-0 opacity-0 invisible">
            <div class="py-4 border-t border-gray-200 dark:border-gray-700 space-y-3">
              <button onclick="phsToggleTheme()"
                class="w-full inline-flex items-center justify-center gap-2 rounded-md font-medium text-sm px-4 py-2 bg-transparent border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                <i data-lucide="sun" class="w-4 h-4 dark:hidden"></i>
                <i data-lucide="moon" class="w-4 h-4 hidden dark:block"></i>
                Toggle Theme
              </button>
              <button id="mobile-landing-signin-btn"
                class="w-full inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                Sign In
              </button>
              <button id="mobile-landing-register-btn"
                class="w-full inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700">
                Register
              </button>
            </div>
          </div>
        </div>
      </nav>

      <!-- Image Slider Banner - Responsive -->
      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="slider-container">
          <div class="slide active" style="background-image: url('imagebgKlase.png')">
            <div class="slide-content">
              <h3 class="text-2xl font-bold">Official Land Records</h3>
              <p class="text-lg">
                Access verified property information directly from government
                database
              </p>
            </div>
          </div>
          <div class="slide" style="background-image: url('imagebg2.jpeg')">
            <div class="slide-content">
              <h3 class="text-2xl font-bold">
                Bank & Legal Institution Search
              </h3>
              <p class="text-lg">
                Trusted by leading banks and law firms across Nigeria
              </p>
            </div>
          </div>
          <div class="slide" style="background-image: url('imagebg3.jpeg')">
            <div class="slide-content">
              <h3 class="text-2xl font-bold">Token-Based System</h3>
              <p class="text-lg">
                Flexible packages starting from 2,000 tokens
              </p>
            </div>
          </div>
          <div class="slide" style="background-image: url('imagebg4.jpeg')">
            <div class="slide-content">
              <h3 class="text-2xl font-bold">Instant Search Results</h3>
              <p class="text-lg">
                Get official search slips instantly with just 1 token
              </p>
            </div>
          </div>
          <button class="slider-btn prev" id="prevSlide">â®</button>
          <button class="slider-btn next" id="nextSlide">â¯</button>
          <div class="slider-dots" id="sliderDots"></div>
        </div>
      </div>

      <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
        <div class="text-center">
          <div
            class="inline-flex items-center rounded-full text-xs font-medium px-3 py-1 bg-blue-100 text-blue-700 mb-6">
            <i data-lucide="crown" class="w-4 h-4 mr-2"></i> Official
            Government Platform
          </div>
          <h1 class="text-3xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold text-gray-900 mb-6 leading-tight">
            Kano State Ministry of Land
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-green-600">and Physical
              Planning Property History Search (PHS)
              Portal</span>
          </h1>
          <p class="text-base sm:text-lg md:text-xl text-gray-600 max-w-3xl mx-auto mb-10">
            Secure, token-based Search History for banks, law firms, and
            corporate institutions.
          </p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <button id="hero-signin-btn"
              class="inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-all shadow-lg hover:shadow-xl">
              Get Started
              <i data-lucide="arrow-right" class="ml-2 w-5 h-5"></i>
            </button>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-900 py-12 sm:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="text-center mb-8 sm:mb-12">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 dark:text-gray-100 mb-4">
              Why Choose KLAES Enterprise?
            </h2>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8">
            <div class="text-center p-4 sm:p-6">
              <div class="w-16 h-16 sm:w-20 sm:h-20 bg-blue-100 dark:bg-blue-900/30 rounded-2xl flex items-center justify-center mx-auto mb-4 sm:mb-5">
                <i data-lucide="coins" class="w-8 h-8 sm:w-10 sm:h-10 text-blue-600 dark:text-blue-400"></i>
              </div>
              <h3 class="text-lg sm:text-xl font-semibold mb-2 sm:mb-3 dark:text-gray-100">Token-Based System</h3>
              <p class="text-sm sm:text-base text-gray-600 dark:text-gray-400">
                Pay-as-you-go with flexible token packages. Each search consumes 1 token.
              </p>
            </div>
            <div class="text-center p-4 sm:p-6">
              <div class="w-16 h-16 sm:w-20 sm:h-20 bg-green-100 dark:bg-green-900/30 rounded-2xl flex items-center justify-center mx-auto mb-4 sm:mb-5">
                <i data-lucide="shield" class="w-8 h-8 sm:w-10 sm:h-10 text-green-600 dark:text-green-400"></i>
              </div>
              <h3 class="text-lg sm:text-xl font-semibold mb-2 sm:mb-3 dark:text-gray-100">Official & Secure</h3>
              <p class="text-sm sm:text-base text-gray-600 dark:text-gray-400">
                Government-verified records with official search slips.
              </p>
            </div>
            <div class="text-center p-4 sm:p-6">
              <div class="w-16 h-16 sm:w-20 sm:h-20 bg-purple-100 dark:bg-purple-900/30 rounded-2xl flex items-center justify-center mx-auto mb-4 sm:mb-5">
                <i data-lucide="clock" class="w-8 h-8 sm:w-10 sm:h-10 text-purple-600 dark:text-purple-400"></i>
              </div>
              <h3 class="text-lg sm:text-xl font-semibold mb-2 sm:mb-3 dark:text-gray-100">Instant Results</h3>
              <p class="text-sm sm:text-base text-gray-600 dark:text-gray-400">
                Real-time searches with downloadable official slips.
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="py-12 sm:py-20 bg-gradient-to-br from-gray-50 to-blue-50 dark:from-gray-800 dark:to-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="text-center mb-8 sm:mb-12">
            <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 dark:text-gray-100 mb-4">
              Flexible Token Packages
            </h2>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 sm:p-8 text-center">
              <h3 class="text-xl sm:text-2xl font-bold text-green-600 dark:text-green-400 mb-2">Starter</h3>
              <div class="text-3xl sm:text-4xl font-bold mb-2 dark:text-gray-100">₦50,000</div>
              <p class="text-gray-500 dark:text-gray-400 mb-4">2,000 Tokens</p>
              <button
                class="landing-package-btn w-full py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                data-tokens="2000" data-price="50000" data-name="Starter">
                Get Started
              </button>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 sm:p-8 text-center border-2 border-blue-500 relative">
              <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 px-4 py-1 bg-gradient-to-r from-blue-500 to-pink-500 text-white text-xs font-semibold rounded-full whitespace-nowrap">
                POPULAR
              </div>
              <h3 class="text-xl sm:text-2xl font-bold text-blue-600 dark:text-blue-400 mb-2">Professional</h3>
              <div class="text-3xl sm:text-4xl font-bold mb-2 dark:text-gray-100">₦100,000</div>
              <p class="text-gray-500 dark:text-gray-400 mb-4">5,000 Tokens</p>
              <button
                class="landing-package-btn w-full py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                data-tokens="5000" data-price="100000" data-name="Professional">
                Get Started
              </button>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg p-6 sm:p-8 text-center sm:col-span-2 lg:col-span-1">
              <h3 class="text-xl sm:text-2xl font-bold text-purple-600 dark:text-purple-400 mb-2">Enterprise</h3>
              <div class="text-3xl sm:text-4xl font-bold mb-2 dark:text-gray-100">₦180,000</div>
              <p class="text-gray-500 dark:text-gray-400 mb-4">10,000 Tokens</p>
              <button
                class="landing-package-btn w-full py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition"
                data-tokens="10000" data-price="180000" data-name="Enterprise">
                Get Started
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-gradient-to-r from-blue-600 to-purple-700 py-12 sm:py-16">
        <div class="max-w-4xl mx-auto text-center px-4">
          <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white mb-4">
            Ready to Transform Your Search History Process?
          </h2>
          <button id="cta-signin-btn"
            class="inline-flex items-center px-6 sm:px-8 py-3 sm:py-4 bg-white text-blue-600 rounded-lg font-semibold hover:shadow-xl transition text-sm sm:text-base">
            Start Your Journey
            <i data-lucide="arrow-right" class="ml-2 w-5 h-5"></i>
          </button>
        </div>
      </div>

      <footer class="bg-gray-900 text-white py-12 sm:py-12 mt-16 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 sm:gap-10">
            <div>
              <div class="flex items-center space-x-4">
                <div
                  class="w-10 h-10 rounded-lg flex items-center justify-center overflow-hidden bg-gradient-to-r from-blue-600 to-purple-600">
                  <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo" class="w-full h-full object-cover"
                    onerror="this.src='https://placehold.co/40x40/3b82f6/white?text=K'" />
                </div>
                <div>
                  <h1 class="text-xl font-bold text-white">KLAES</h1>
                </div>
              </div>
              <p class="text-gray-400 text-sm leading-relaxed mt-4">
                Official government platform for Search History services and
                land record verification in Kano State.
              </p>
            </div>
            <div>
              <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">
                Services
              </h3>
              <ul class="space-y-2 text-sm">
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">Search History</a>
                </li>
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">Property Verification</a>
                </li>
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">Title Investigation</a>
                </li>
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">Due Diligence Reports</a>
                </li>
              </ul>
            </div>
            <div>
              <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">
                Support
              </h3>
              <ul class="space-y-2 text-sm">
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">Help Center</a>
                </li>
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">Contact Support</a>
                </li>
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">API Documentation</a>
                </li>
                <li>
                  <a href="#" class="text-gray-400 hover:text-white transition-colors">System Status</a>
                </li>
              </ul>
            </div>
            <div>
              <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">
                Contact Us
              </h3>
              <div class="space-y-3 text-sm">
                <div class="flex items-center space-x-2.5 text-gray-400">
                  <i data-lucide="mail" class="w-4 h-4 flex-shrink-0"></i><a href="mailto:support@klas.gov.ng"
                    class="hover:text-white transition-colors">support@klas.gov.ng</a>
                </div>
                <div class="flex items-center space-x-2.5 text-gray-400">
                  <i data-lucide="phone" class="w-4 h-4 flex-shrink-0"></i><a href="tel:+23491234567"
                    class="hover:text-white transition-colors">+234 (0) 9 123 4567</a>
                </div>
                <div class="flex items-start space-x-2.5 text-gray-400">
                  <i data-lucide="map-pin" class="w-4 h-4 flex-shrink-0 mt-0.5"></i><span>KLAES Headquarters, Kano,
                    Nigeria</span>
                </div>
              </div>
            </div>
          </div>
          <div class="border-t border-gray-800 mt-8 sm:mt-10 pt-8 text-center text-gray-500 text-xs sm:text-sm">
            <p>
              &copy; 2026  LAnd ADmin Enterprise System (KLAES). All
              rights reserved.
            </p>
            <p class="mt-1">
              Empowering Kano with transparent and efficient land
              administration.
            </p>
          </div>
        </div>
      </footer>
    </div>
  </div>

  <!-- ==================== SIGN IN PAGE ==================== -->
  <div id="signin-page" class="page page-hidden">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-blue-50 via-white to-green-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">
      <div class="max-w-md w-full space-y-8">
        <div class="text-center">
          <div class="flex justify-center">
            <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center">
              <i data-lucide="building" class="h-8 w-8 text-white"></i>
            </div>
          </div>
          <h2 class="mt-6 text-3xl font-extrabold text-gray-900 dark:text-gray-100">Welcome Back</h2>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8">
          <div class="mb-6 p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg border border-blue-200 dark:border-blue-700">
            <div class="flex items-start gap-2">
              <i data-lucide="info" class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5"></i>
              <div class="text-sm text-blue-800 dark:text-blue-300">
                <p class="font-medium">Demo Accounts:</p>
                <p class="text-xs mt-1">Bank: Musa Trust Bank / Password: password</p>
                <p class="text-xs">Law Firm: Musa Chambers / Password: password</p>
              </div>
            </div>
          </div>
          <form id="signin-form" class="space-y-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Institution Name</label>
              <div class="relative">
                <i data-lucide="building-2" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                <input type="text" id="institution-name" required
                  class="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Enter your institution name" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
              <div class="relative">
                <i data-lucide="lock" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5"></i>
                <input type="password" id="password" required
                  class="w-full pl-10 pr-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Enter your password" />
              </div>
            </div>
            <button type="submit"
              class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition">
              Sign In
            </button>
          </form>
          <div class="mt-6 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Don't have an account?
              <button id="switch-to-register" class="font-medium text-blue-600 dark:text-blue-400 hover:text-blue-500">Register here</button>
            </p>
          </div>
          <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button id="back-to-landing"
              class="w-full flex items-center justify-center gap-2 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
              <i data-lucide="arrow-left" class="w-4 h-4"></i>Back to Home
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== REGISTER PAGE ==================== -->
  <div id="register-page" class="page page-hidden">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-br from-blue-50 via-white to-green-50 dark:from-gray-900 dark:via-gray-900 dark:to-gray-800">
      <div class="max-w-md w-full space-y-8">
        <div class="text-center">
          <div class="flex justify-center">
            <div class="w-16 h-16 bg-green-600 rounded-2xl flex items-center justify-center">
              <i data-lucide="users" class="h-8 w-8 text-white"></i>
            </div>
          </div>
          <h2 class="mt-6 text-3xl font-extrabold text-gray-900 dark:text-gray-100">Register Your Institution</h2>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8">
          <form id="register-form" class="space-y-5">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Institution Type</label>
              <select id="institution-type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg">
                <option value="bank">Bank / Financial Institution</option>
                <option value="law_firm">Law Firm</option>
                <option value="corporate">Corporate Organization</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Institution Name *</label>
              <input type="text" id="reg-institution-name" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address *</label>
              <input type="email" id="reg-email" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone Number *</label>
              <input type="tel" id="reg-phone" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password *</label>
              <input type="password" id="reg-password" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm Password *</label>
              <input type="password" id="reg-confirm-password" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-lg" />
            </div>
            <label class="flex items-center">
              <input type="checkbox" required class="rounded border-gray-300 dark:border-gray-600 text-blue-600" />
              <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">I agree to the Terms of Service and Privacy Policy</span>
            </label>
            <button type="submit"
              class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 transition">
              Register Institution
            </button>
          </form>
          <div class="mt-6 text-center">
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Already have an account?
              <button id="switch-to-signin" class="font-medium text-blue-600 dark:text-blue-400 hover:text-blue-500">Sign In</button>
            </p>
          </div>
          <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button id="back-to-landing-from-register"
              class="w-full flex items-center justify-center gap-2 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
              <i data-lucide="arrow-left" class="w-4 h-4"></i>Back to Home
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ==================== DASHBOARD with Dynamic Organization Branding ==================== -->
  <div id="dashboard-page" class="page lg:pl-64">
    <aside class="hidden lg:flex fixed inset-y-0 left-0 z-50 w-64 flex-col border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 no-print">
      <div class="h-16 px-5 flex items-center gap-3 border-b border-gray-100 dark:border-gray-700">
        <div id="dashboard-logo" class="w-10 h-10 rounded-xl bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center overflow-hidden shadow-sm">
          <img src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" class="w-full h-full object-cover dark:hidden" alt="PHS">
          <img src="{{ asset('assets/logo/phs-dark-logo.jpeg') }}" class="w-full h-full object-cover hidden dark:block" alt="PHS">
        </div>
        <div class="min-w-0">
          <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate" id="sidebar-dashboard-org-name">{{ $institution->name ?? 'Organization' }}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 truncate">Manage workspace</p>
        </div>
      </div>
      <nav class="flex-1 px-3 py-5 space-y-1">
        <a href="{{ route('phs.dashboard') }}" id="sidebar-dashboard-link" data-view="dashboard" class="phs-nav-link flex items-center gap-3 rounded-xl bg-blue-50 dark:bg-blue-900/30 px-3 py-2.5 text-sm font-semibold text-blue-700 dark:text-blue-300">
          <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
          Dashboard
        </a>
        <div>
          <button type="button" id="search-menu-toggle"
            onclick="const s=document.getElementById('dash-search-submenu');s.classList.toggle('hidden');document.getElementById('dash-search-chevron').classList.toggle('rotate-180');window.lucide?.createIcons();"
            class="w-full flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
            <span class="flex items-center gap-3">
              <i data-lucide="search" class="w-4 h-4"></i>
              Search
            </span>
            <i data-lucide="chevron-down" id="dash-search-chevron" class="w-4 h-4 transition-transform duration-200"></i>
          </button>
          <div id="dash-search-submenu" class="hidden pl-4 mt-0.5 space-y-0.5">
            <a href="#" id="sidebar-search-now-link" data-view="search"
              class="phs-nav-link flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
              <i data-lucide="search" class="w-3.5 h-3.5"></i>
              Search Now
            </a>
            <a href="#" id="sidebar-search-history-link"
              class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
              <i data-lucide="history" class="w-3.5 h-3.5"></i>
              Search History
            </a>
          </div>
        </div>
        @if ($member->isSuperAdmin())
          <a href="{{ route('phs.org.index') }}?tab=users" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
            <i data-lucide="users" class="w-4 h-4"></i>
            Team Members
          </a>
          <a href="{{ route('phs.org.index') }}?tab=roles" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
            <i data-lucide="shield" class="w-4 h-4"></i>
            Roles &amp; Permissions
          </a>
          <a href="{{ route('phs.org.index') }}?tab=activity" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
            <i data-lucide="activity" class="w-4 h-4"></i>
            Activity Log
          </a>
          <a href="{{ route('phs.org.index') }}?tab=branding" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
            <i data-lucide="palette" class="w-4 h-4"></i>
            Branding
          </a>
          <a href="{{ route('phs.org.index') }}?tab=subscription" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100">
            <i data-lucide="credit-card" class="w-4 h-4"></i>
            Subscription
          </a>
        @endif
      </nav>
      <div class="border-t border-gray-100 dark:border-gray-700 p-4 space-y-3">
        <div class="rounded-xl bg-gray-50 dark:bg-gray-800 px-3 py-3" style="display:none">
          <p class="text-xs text-gray-500 dark:text-gray-400">Available Tokens</p>
          <p class="text-2xl font-bold text-blue-600 dark:text-blue-400" id="sidebar-token-display">0</p>
        </div>
        <!-- Theme toggle in sidebar footer -->
        <button onclick="phsToggleTheme()" title="Toggle dark mode"
          class="w-full flex items-center justify-center gap-2 rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
          <i data-lucide="sun" class="w-4 h-4 dark:hidden"></i>
          <i data-lucide="moon" class="w-4 h-4 hidden dark:block"></i>
          <span class="dark:hidden">Light Mode</span>
          <span class="hidden dark:block">Dark Mode</span>
        </button>
        <button id="sidebar-dashboard-logout-btn" type="button" class="w-full flex items-center justify-center gap-2 rounded-xl border border-gray-300 dark:border-gray-600 px-3 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
          <i data-lucide="log-out" class="w-4 h-4"></i>
          Logout
        </button>
      </div>
    </aside>
    <!-- Responsive Dashboard Header with Collapsible Mobile Menu -->
    <header class="bg-white dark:bg-gray-900 shadow-sm border-b border-gray-200 dark:border-gray-700 sticky top-0 z-40 no-print">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <div class="flex items-center space-x-4">
            <div>
              <h1 id="dashboard-org-name" class="text-lg sm:text-xl font-bold text-gray-900 dark:text-gray-100">KLAES</h1>
              <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Organizational Search History Platform</p>
            </div>
          </div>

          <!-- Desktop Menu -->
          <div class="hidden md:flex items-center space-x-4">
            <div class="text-right mr-4" style="display:none">
              <p class="text-xs text-gray-500 dark:text-gray-400">Available Tokens</p>
              <p class="text-lg sm:text-xl font-bold text-blue-600 dark:text-blue-400" id="token-display-header">0</p>
            </div>
            <!-- Theme toggle -->
            <button onclick="phsToggleTheme()" title="Toggle dark mode"
              class="rounded-md p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
              <i data-lucide="sun" class="w-5 h-5 dark:hidden"></i>
              <i data-lucide="moon" class="w-5 h-5 hidden dark:block"></i>
            </button>
            <button id="dashboard-logout-btn"
              class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 bg-transparent border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
              Logout
            </button>
            @if ($member->isSuperAdmin())
            <button id="open-user-management-btn"
              class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 bg-purple-600 text-white hover:bg-purple-700 transition ml-2"
              onclick="window.location.href='{{ route('phs.org.index') }}'">
              <i data-lucide="settings" class="w-4 h-4 mr-2"></i>Manage Organization
            </button>
            @endif
          </div>

          <!-- Mobile Menu Button -->
          <button id="dashboard-mobile-menu-btn" class="md:hidden p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
            <i data-lucide="menu" class="w-6 h-6 text-gray-700 dark:text-gray-300"></i>
          </button>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="dashboard-mobile-menu" class="md:hidden overflow-hidden transition-all duration-300 ease-in-out max-h-0 opacity-0 invisible">
          <div class="py-4 border-t border-gray-200 dark:border-gray-700 space-y-4">
            <div class="flex items-center justify-between">
              <div style="display:none">
                <p class="text-xs text-gray-500 dark:text-gray-400">Available Tokens</p>
                <p class="text-xl font-bold text-blue-600 dark:text-blue-400" id="mobile-token-display">0</p>
              </div>
              <div class="flex items-center space-x-2">
                <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white">
                  <span id="mobile-institution-initial">B</span>
                </div>
                <span class="text-sm font-medium dark:text-gray-200" id="mobile-institution-name">Bank Name</span>
              </div>
            </div>
            <div class="space-y-2">
              <button onclick="phsToggleTheme()"
                class="w-full inline-flex items-center justify-center gap-2 rounded-md font-medium text-sm px-4 py-2 bg-transparent border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                <i data-lucide="sun" class="w-4 h-4 dark:hidden"></i>
                <i data-lucide="moon" class="w-4 h-4 hidden dark:block"></i>
                Toggle Theme
              </button>
              <button id="mobile-dashboard-logout-btn"
                class="w-full inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 bg-transparent border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                Logout
              </button>
              @if ($member->isSuperAdmin())
              <button id="mobile-open-user-management-btn"
                class="w-full inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 bg-purple-600 text-white hover:bg-purple-700 transition"
                onclick="window.location.href='{{ route('phs.org.index') }}'">
                <i data-lucide="settings" class="w-4 h-4 mr-2"></i>Manage Organization
              </button>
              @endif
            </div>
          </div>
        </div>
      </div>
    </header>

    <!-- Dynamic Organization Banner - Responsive -->
    <div id="org-dashboard-banner"
      class="max-w-7xl mx-auto h-48 sm:h-64 md:h-80 bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center relative overflow-hidden">
      <div class="absolute inset-0 bg-black/20"></div>
      <div class="relative z-10 text-center px-4">
        <p id="banner-text-dashboard" class="text-white text-xl sm:text-2xl font-bold">
         Welcome to Your Kano State<br>
          Ministry of Land and Physical Planning <br>
          Property History Search Portal
        </p>
        <p id="banner-subtext" class="text-white/80 text-xs sm:text-sm mt-2">
          Secure, fast, and reliable property records
        </p>
      </div>
    </div>

    <main class="flex-1">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

        <!-- ==================== DASHBOARD OVERVIEW ==================== -->
        <div id="dashboard-view">
          @if ($stats['token_balance'] < 100)
          <div class="mb-5 flex items-start gap-3 rounded-xl border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 px-4 py-3">
            <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0"></i>
            <div class="text-sm text-amber-800 dark:text-amber-300">
              <p class="font-semibold">Low token balance</p>
              <p>You have <strong>{{ number_format($stats['token_balance']) }}</strong> tokens left.
                @if ($member->isSuperAdmin())
                  <a href="{{ route('phs.org.index') }}?tab=subscription" class="font-semibold underline hover:text-amber-900">Top up now</a> to avoid interruptions.
                @else
                  Please contact your organization administrator to top up.
                @endif
              </p>
            </div>
          </div>
          @endif

          <!-- Stat Cards -->
          <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 pb-6 sm:pb-8">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center flex-shrink-0">
                <i data-lucide="coins" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
              </div>
              <div class="min-w-0">
                <p class="text-xs text-gray-500 dark:text-gray-400">Available Tokens</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['token_balance']) }}</p>
              </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl bg-green-50 dark:bg-green-900/30 flex items-center justify-center flex-shrink-0">
                <i data-lucide="search" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
              </div>
              <div class="min-w-0">
                <p class="text-xs text-gray-500 dark:text-gray-400">Total Searches</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['total_searches']) }}</p>
              </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center flex-shrink-0">
                <i data-lucide="calendar" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
              </div>
              <div class="min-w-0">
                <p class="text-xs text-gray-500 dark:text-gray-400">Searches This Month</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['searches_this_month']) }}</p>
              </div>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl bg-orange-50 dark:bg-orange-900/30 flex items-center justify-center flex-shrink-0">
                <i data-lucide="users" class="w-6 h-6 text-orange-600 dark:text-orange-400"></i>
              </div>
              <div class="min-w-0">
                <p class="text-xs text-gray-500 dark:text-gray-400">Team Members</p>
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($stats['member_count']) }}</p>
              </div>
            </div>
          </section>

          <!-- Quick action + Recent searches -->
          <section id="recent-searches-section" class="pb-8 scroll-mt-24">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
              <div class="flex items-center justify-between p-4 sm:p-6 border-b border-gray-100 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Recent Searches</h2>
                <button id="dashboard-new-search-btn"
                  class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition">
                  <i data-lucide="search" class="w-4 h-4"></i> Search Now
                </button>
              </div>
              @if ($recentSearches->isEmpty())
                <div class="p-10 text-center">
                  <i data-lucide="file-search" class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4"></i>
                  <p class="text-gray-500 dark:text-gray-400">No searches yet. Run your first Property History Search.</p>
                </div>
              @else
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                  @foreach ($recentSearches as $log)
                    <div class="flex items-center justify-between gap-4 px-4 sm:px-6 py-3.5">
                      <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $log->file_number ?: $log->query }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                          {{ $log->member->name ?? 'Member' }} ·
                          {{ $log->result_count }} {{ \Illuminate\Support\Str::plural('result', $log->result_count) }}
                        </p>
                      </div>
                      <div class="text-right flex-shrink-0">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $log->created_at?->diffForHumans() }}</p>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500 font-mono">{{ $log->reference_no }}</p>
                      </div>
                    </div>
                  @endforeach
                </div>
              @endif
            </div>
          </section>
        </div>

        <!-- ==================== SEARCH HISTORY VIEW ==================== -->
        <div id="search-view" class="hidden">
        <!-- Search Section - Responsive -->
        <section class="pb-6 sm:pb-8 no-print">
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700">
            <div class="p-4 sm:p-6 md:p-8">
              <h2 class="text-lg sm:text-xl font-semibold mb-4 dark:text-gray-100">
                Property History Search
              </h2>
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <div class="w-full sm:max-w-md">
                  <select id="search-query" name="query" class="w-full"></select>
                </div>
                <button id="search-btn"
                  class="inline-flex items-center justify-center rounded-lg font-medium px-5 sm:px-7 py-2 sm:py-3 h-10 sm:h-12 bg-blue-600 text-white hover:bg-blue-700 transition text-sm sm:text-base w-full sm:w-auto">
                  <i data-lucide="search" class="w-4 h-4 sm:w-5 sm:h-5 mr-2"></i>1 Token per Search
                </button>
              </div>

              {{-- ── Additional Filters (dropdown-based, mirrors /onpremise) ──── --}}
              <div class="border-t border-gray-100 dark:border-gray-700 mt-3 pt-3">
                <button type="button" id="phs-toggle-filters-btn" class="flex items-center gap-2 cursor-pointer group">
                  <i data-lucide="chevron-right" id="phs-filters-chevron"
                    class="w-4 h-4 text-gray-400 transition-transform duration-200"></i>
                  <span class="text-sm font-medium text-gray-600 dark:text-gray-300 group-hover:text-gray-800 dark:group-hover:text-gray-100">Additional Filters</span>
                  <span id="phs-active-filter-count"
                    class="hidden inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs font-bold">0</span>
                </button>

                {{-- Filter selector dropdown (hidden by default) --}}
                <div id="phs-filters-panel" class="hidden mt-3">
                  <select id="phs-filter-selector"
                    class="w-full md:w-64 px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 mb-3">
                    <option value="">— Select a filter to add —</option>
                    <option value="guarantorName">Party 1</option>
                    <option value="guaranteeName">Party 2</option>
                    <option value="lga">LGA</option>
                    <option value="district">District</option>
                    <option value="location">Location</option>
                    <option value="plotNumber">Plot Number</option>
                    <option value="planNumber">Plan Number</option>
                    <option value="size">Size</option>
                    <option value="caveat">Caveat</option>
                  </select>

                  {{-- Active filters container --}}
                  <div id="phs-filters-container" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3"></div>

                  {{-- Hidden filter templates (used by JS to build the input rows) --}}
                  <template id="phs-tpl-guarantorName">
                    <input type="text" data-filter-key="guarantorName" placeholder="Enter party 1 name"
                      class="phs-filter-input flex-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                  </template>
                  <template id="phs-tpl-guaranteeName">
                    <input type="text" data-filter-key="guaranteeName" placeholder="Enter party 2 name"
                      class="phs-filter-input flex-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                  </template>
                  <template id="phs-tpl-lga">
                    <select data-filter-key="lga"
                      class="phs-filter-input flex-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                      <option value="">All LGAs</option>
                      <option value="Dala">Dala</option>
                      <option value="Fagge">Fagge</option>
                      <option value="Gwale">Gwale</option>
                      <option value="Kano Municipal">Kano Municipal</option>
                      <option value="Nassarawa">Nassarawa</option>
                      <option value="Tarauni">Tarauni</option>
                      <option value="Ungogo">Ungogo</option>
                    </select>
                  </template>
                  <template id="phs-tpl-district">
                    <input type="text" data-filter-key="district" placeholder="Enter district"
                      class="phs-filter-input flex-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                  </template>
                  <template id="phs-tpl-location">
                    <input type="text" data-filter-key="location" placeholder="Enter location"
                      class="phs-filter-input flex-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                  </template>
                  <template id="phs-tpl-plotNumber">
                    <input type="text" data-filter-key="plotNumber" placeholder="Enter plot number"
                      class="phs-filter-input flex-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                  </template>
                  <template id="phs-tpl-planNumber">
                    <input type="text" data-filter-key="planNumber" placeholder="Enter plan number"
                      class="phs-filter-input flex-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                  </template>
                  <template id="phs-tpl-size">
                    <input type="text" data-filter-key="size" placeholder="Enter size"
                      class="phs-filter-input flex-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                  </template>
                  <template id="phs-tpl-caveat">
                    <select data-filter-key="caveat"
                      class="phs-filter-input flex-1 w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 rounded-md text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                      <option value="">Any</option>
                      <option value="Yes">Yes</option>
                      <option value="No">No</option>
                    </select>
                  </template>
                </div>
              </div>

              <div class="flex items-center justify-between mt-2.5 gap-3 flex-wrap">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  Examples: "COM-RES-2021-78", "KN12345", "John Doe"
                </p>
              </div>
            </div>
          </div>
        </section>

        <!-- Loading -->
        <section id="loading-section" class="hidden pb-12">
          <div class="flex justify-center items-center py-20">
            <div class="loading-spinner"></div>
          </div>
        </section>

        <!-- Search Results -->
        <section id="results-section" class="hidden pb-12">
          <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
            <h2 class="text-xl sm:text-2xl font-semibold text-gray-800 dark:text-gray-200">
              Search Results
              <span id="results-count" class="text-gray-500 dark:text-gray-400 font-normal">(0 found)</span>
            </h2>
          </div>
          <div id="no-results" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg hidden">
            <div class="p-10 text-center">
              <i data-lucide="file-search" class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-6"></i>
              <h3 class="text-2xl font-semibold text-gray-700 dark:text-gray-300 mb-3">No Results Found</h3>
              <button id="try-new-search" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                Try a New Search
              </button>
            </div>
          </div>
          <div id="cards-results" class="grid grid-cols-1 md:grid-cols-2 gap-5"></div>
        </section>

        <!-- File Details Section -->
        <section id="file-details-section" class="hidden pb-12">
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4 sm:p-6 border-b border-gray-200 dark:border-gray-700">
              <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between flex-wrap gap-4">
                <div>
                  <h2 class="text-xl sm:text-2xl font-bold dark:text-gray-100">Search History Slip</h2>
                  <p class="text-sm sm:text-base text-gray-500 dark:text-gray-400">
                    Official search result for file
                    <span id="file-reference" class="font-medium text-gray-700 dark:text-gray-300">--</span>
                  </p>
                </div>
                <div class="flex items-center gap-2 no-print w-full sm:w-auto">
                  <button id="back-to-dashboard-btn"
                    class="flex-1 sm:flex-initial inline-flex items-center justify-center px-3 sm:px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>Back
                  </button>
                  {{-- Raises a correction request with the PHS-P Admin. Distinct from the
                       general feedback FAB: this one is tied to THIS result and, once the
                       admin returns it, entitles the member to one free re-run. --}}
                  <button id="phs-edit-request-btn" type="button"
                    class="flex-1 sm:flex-initial inline-flex items-center justify-center px-3 sm:px-4 py-2 border border-amber-300 dark:border-amber-700 rounded-lg text-xs sm:text-sm font-medium text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/30 hover:bg-amber-100 dark:hover:bg-amber-900/50">
                    <i data-lucide="file-warning" class="w-4 h-4 mr-2"></i>Send Edit Request
                  </button>
                  <button id="print-slip-btn"
                    class="flex-1 sm:flex-initial inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-black dark:bg-white text-white dark:text-black rounded-lg text-xs sm:text-sm font-medium hover:bg-black/90 dark:hover:bg-gray-200">
                    <i data-lucide="printer" class="w-4 h-4 mr-2"></i>Print
                  </button>
                </div>
              </div>
            </div>
            {{-- An edit request is open on this file: tells the member where it stands
                 so they do not raise it again or wonder if it was received. --}}
            <div id="phs-edit-request-status" class="hidden no-print mx-4 sm:mx-6 mt-4 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3">
              <div class="flex items-start gap-3">
                <i data-lucide="clock" class="w-4 h-4 mt-0.5 text-amber-600 dark:text-amber-400 shrink-0"></i>
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                    Edit Requested
                  </p>
                  <p class="text-xs text-amber-800 dark:text-amber-300" id="phs-edit-request-status-msg">
                    Your correction request is with the PHS-P Admin.
                  </p>
                </div>
              </div>
            </div>

            {{-- The correction has been returned. This is the free re-run: the button is
                 labelled "Re-run", and the copy states plainly that nothing is charged. --}}
            <div id="phs-rerun-banner" class="hidden no-print mx-4 sm:mx-6 mt-4 rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/20 px-4 py-3">
              <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex items-start gap-3 min-w-0 flex-1">
                  <i data-lucide="check-circle" class="w-4 h-4 mt-0.5 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-200">
                      Your search result has been corrected
                    </p>
                    <p class="text-xs text-emerald-800 dark:text-emerald-300" id="phs-rerun-msg">
                      Click Re-run Search to generate the updated result. No token will be deducted for this re-run.
                    </p>
                    <p class="text-[11px] text-emerald-700 dark:text-emerald-400 mt-1 hidden" id="phs-rerun-note"></p>
                  </div>
                </div>
                <button id="phs-rerun-btn" type="button" data-file-number=""
                  class="shrink-0 inline-flex items-center justify-center px-4 py-2 rounded-lg text-xs sm:text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700">
                  <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i>Re-run
                </button>
              </div>
            </div>

            <div class="p-4 sm:p-6 relative overflow-hidden">
              {{-- Watermark: logo behind content --}}
              <img src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" aria-hidden="true"
                class="dark:hidden pointer-events-none select-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[55%] max-w-[420px] opacity-[0.06] z-0">
              <img src="{{ asset('assets/logo/phs-dark-logo.jpeg') }}" aria-hidden="true"
                class="hidden dark:block pointer-events-none select-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[55%] max-w-[420px] opacity-[0.06] z-0">
              {{-- Watermark: text diagonal --}}
              <div aria-hidden="true"
                class="pointer-events-none select-none absolute top-1/2 left-1/2 z-0 text-center leading-snug font-black uppercase tracking-widest whitespace-nowrap -translate-x-1/2 -translate-y-1/2 -rotate-[30deg] text-green-800 dark:text-green-300 opacity-[0.10] text-xl sm:text-2xl">
                PROPERTY HISTORY SEARCH (PHS)<br>&nbsp;&bull;&nbsp;Not For Sale
              </div>
              <div class="relative z-10">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 sm:p-4">
                  <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center text-sm sm:text-base">
                    <i data-lucide="home" class="w-4 h-4 mr-2"></i>Property Information
                  </h3>
                  <div class="space-y-2">
                    <div class="flex justify-between py-1"><span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">File Number:</span><span class="font-medium text-xs sm:text-sm dark:text-gray-200" id="file-number-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">File Title:</span><span class="font-semibold text-xs sm:text-sm dark:text-gray-200" id="file-title-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">Plot No:</span><span class="font-medium text-xs sm:text-sm dark:text-gray-200" id="plot-number-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">Size:</span><span class="font-medium text-xs sm:text-sm dark:text-gray-200" id="size-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">TP No:</span><span class="font-medium text-xs sm:text-sm dark:text-gray-200" id="tpno-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">District:</span><span class="font-medium text-xs sm:text-sm dark:text-gray-200" id="district-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">LGA:</span><span class="font-medium text-xs sm:text-sm dark:text-gray-200" id="lga-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">Land Use:</span><span class="font-medium text-xs sm:text-sm dark:text-gray-200" id="property-type-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">Last Transaction:</span><span class="font-medium text-xs sm:text-sm dark:text-gray-200" id="last-transaction-value">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">Status:</span><span class="font-medium text-green-600 dark:text-green-400 text-xs sm:text-sm" id="status-value">Active</span></div>
                  </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 sm:p-4">
                  <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center text-sm sm:text-base">
                    <i data-lucide="building-2" class="w-4 h-4 mr-2"></i>Search Details
                  </h3>
                  <div class="space-y-2">
                    <div class="flex justify-between py-1"><span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">Institution:</span><span class="font-medium text-xs sm:text-sm dark:text-gray-200" id="requesting-institution">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">Search Date:</span><span class="font-medium text-xs sm:text-sm dark:text-gray-200" id="search-date">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">Reference No.:</span><span class="font-medium text-xs sm:text-sm dark:text-gray-200" id="reference-no">--</span></div>
                    <div class="flex justify-between py-1"><span class="text-gray-500 dark:text-gray-400 text-xs sm:text-sm">Tokens Used:</span><span class="font-medium text-xs sm:text-sm dark:text-gray-200">1</span></div>
                  </div>
                </div>
              </div>
              <div class="flex flex-col gap-3 mb-4">
                <h3 class="font-semibold text-gray-700 dark:text-gray-300 flex items-center text-sm sm:text-base">
                  <i data-lucide="calendar" class="w-4 h-4 mr-2"></i>Property Timeline
                  <span id="timeline-total-count" class="ml-2 inline-flex items-center justify-center min-w-[1.5rem] h-[1.5rem] rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 text-xs font-bold px-2">0</span>
                </h3>
                <div id="timeline-source-badges" class="flex flex-wrap gap-2"></div>
              </div>
              <div id="timeline-container" class="space-y-0">
                <div class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm sm:text-base">
                  Select a file to view transaction history
                </div>
              </div>
              {{-- Report notices (caveat / W-R-C / CoFO / ground rent / litigation / encumbrance) --}}
              <div id="timeline-notices" class="mt-4 space-y-2"></div>
              </div>{{-- /relative z-10 --}}
            </div>
          </div>
        </section>

        <iframe id="print-frame" style="
              position: absolute;
              width: 0;
              height: 0;
              border: none;
              visibility: hidden;
            "></iframe>
        </div><!-- /#search-view -->
      </div>
    </main>

    <footer class="bg-gray-900 dark:bg-gray-950 text-white py-8 sm:py-12 mt-8 sm:mt-16 no-print">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 sm:gap-10">
          <div>
            <div class="flex items-center space-x-4">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center overflow-hidden bg-gradient-to-r from-blue-600 to-purple-600">
                <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo" class="w-full h-full object-cover"
                  onerror="this.src='https://placehold.co/40x40/3b82f6/white?text=K'" />
              </div>
              <div>
                <h1 class="text-xl font-bold text-white">KLAES</h1>
              </div>
            </div>
            <p class="text-gray-400 text-sm leading-relaxed mt-4">
              Official government platform for Search History services and land
              record verification in Kano State.
            </p>
          </div>
          <div>
            <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">
              Services
            </h3>
            <ul class="space-y-2 text-sm">
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Search History</a></li>
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Property Verification</a></li>
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Title Investigation</a></li>
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Due Diligence Reports</a></li>
            </ul>
          </div>
          <div>
            <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">
              Support
            </h3>
            <ul class="space-y-2 text-sm">
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Help Center</a></li>
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">Contact Support</a></li>
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">API Documentation</a></li>
              <li><a href="#" class="text-gray-400 hover:text-white transition-colors">System Status</a></li>
            </ul>
          </div>
          <div>
            <h3 class="text-base font-semibold uppercase tracking-wider text-gray-300 mb-4">
              Contact Us
            </h3>
            <div class="flex items-start gap-4">
              <div class="space-y-3 text-sm">
                <div class="flex items-center space-x-2.5 text-gray-400">
                  <i data-lucide="mail" class="w-4 h-4 flex-shrink-0"></i><a href="mailto:support@klas.gov.ng"
                    class="hover:text-white transition-colors">support@klas.gov.ng</a>
                </div>
                <div class="flex items-center space-x-2.5 text-gray-400">
                  <i data-lucide="phone" class="w-4 h-4 flex-shrink-0"></i><a href="tel:+23491234567"
                    class="hover:text-white transition-colors">+234 (0) 9 123 4567</a>
                </div>
                <div class="flex items-start space-x-2.5 text-gray-400">
                  <i data-lucide="map-pin" class="w-4 h-4 flex-shrink-0 mt-0.5"></i><span>KLAES Headquarters, Kano, Nigeria</span>
                </div>
              </div>
              <div class="w-14 h-14 flex-shrink-0 rounded-xl bg-white/10 flex items-center justify-center overflow-hidden shadow-sm">
                <img src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" class="w-full h-full object-cover dark:hidden" alt="PHS" />
                <img src="{{ asset('assets/logo/phs-dark-logo.jpeg') }}" class="w-full h-full object-cover hidden dark:block" alt="PHS" />
              </div>
            </div>
          </div>
        </div>
        <div class="border-t border-gray-800 mt-8 sm:mt-10 pt-8 text-center text-gray-500 text-xs sm:text-sm">
          <p>&copy; 2026  LAnd ADmin Enterprise System (KLAES). All rights reserved.</p>
          <p class="mt-1">Empowering Kano with transparent and efficient land administration.</p>
        </div>
      </div>
    </footer>

    <!-- ==================== FEEDBACK SIDEBAR (complaints) ==================== -->
    <!-- Floating launcher -->
    {{-- Send Edit Request dialog. Reasons come from PhsEditRequest::REASONS so the
         member's wording and the admin queue's filter cannot drift apart. --}}
    <div id="phs-edit-request-backdrop"
      class="no-print fixed inset-0 z-[60] bg-black/40 opacity-0 invisible transition-opacity duration-300"></div>

    <div id="phs-edit-request-modal" role="dialog" aria-modal="true" aria-labelledby="phs-edit-request-title"
      class="no-print fixed inset-0 z-[70] hidden items-center justify-center p-4">
      <div class="w-full max-w-lg rounded-xl bg-white dark:bg-gray-900 shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex items-center gap-2 min-w-0">
            <i data-lucide="file-warning" class="w-5 h-5 text-amber-600 shrink-0"></i>
            <h2 id="phs-edit-request-title" class="text-base font-bold text-gray-900 dark:text-gray-100 truncate">
              Send Edit Request
            </h2>
          </div>
          <button type="button" id="phs-edit-request-close"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-full p-1">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>

        <div id="phs-edit-request-success" class="hidden text-center px-5 py-10">
          <i data-lucide="check-circle" class="w-12 h-12 mx-auto text-emerald-500 mb-3"></i>
          <p class="text-sm text-gray-700 dark:text-gray-300 mb-5" id="phs-edit-request-success-msg"></p>
          <button type="button" id="phs-edit-request-done"
            class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-medium">
            Close
          </button>
        </div>

        <form id="phs-edit-request-form" class="px-5 py-4 space-y-4">
          <div id="phs-edit-request-error"
            class="hidden rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700"></div>

          <div class="rounded-lg bg-gray-50 dark:bg-gray-800 px-3 py-2 text-xs text-gray-600 dark:text-gray-400">
            File number
            <span class="font-semibold text-gray-900 dark:text-gray-100" id="phs-edit-request-file">--</span>
            <span id="phs-edit-request-ref-wrap" class="hidden">
              &nbsp;&bull;&nbsp;Ref <span class="font-mono" id="phs-edit-request-ref"></span>
            </span>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
              What is wrong with this result? <span class="text-red-500">*</span>
            </label>
            <select id="phs-edit-request-reason-category" required
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 px-3 py-2 text-sm">
              <option value="">Select a reason</option>
              @foreach (\App\Models\Phs\PhsEditRequest::REASONS as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
              Describe the problem <span class="text-red-500">*</span>
            </label>
            <textarea id="phs-edit-request-reason" rows="4" required maxlength="4000"
              placeholder="Tell the PHS-P Admin what is missing, wrong, or does not belong to this file."
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 px-3 py-2 text-sm"></textarea>
          </div>

          <p class="text-[11px] text-gray-500 dark:text-gray-400">
            The result you are looking at is sent with your request so the admin can see exactly
            what you saw. Once corrected you can re-run the search free of charge.
          </p>

          <div class="flex items-center justify-end gap-2 pt-1">
            <button type="button" id="phs-edit-request-cancel"
              class="px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm text-gray-700 dark:text-gray-300">
              Cancel
            </button>
            <button type="submit" id="phs-edit-request-submit"
              class="px-4 py-2 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold">
              Send Edit Request
            </button>
          </div>
        </form>
      </div>
    </div>

    <button id="phs-feedback-fab" type="button"
      class="no-print fixed bottom-5 right-5 z-50 inline-flex items-center gap-2 rounded-full bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-lg hover:bg-blue-700 transition focus:outline-none focus:ring-4 focus:ring-blue-600/30"
      aria-haspopup="dialog" aria-controls="phs-feedback-panel">
      <i data-lucide="message-square-warning" class="w-5 h-5"></i>
      <span class="hidden sm:inline">Feedback</span>
    </button>

    <!-- Backdrop -->
    <div id="phs-feedback-backdrop"
      class="no-print fixed inset-0 z-[60] bg-black/40 opacity-0 invisible transition-opacity duration-300"></div>

    <!-- Slide-in panel -->
    <aside id="phs-feedback-panel" role="dialog" aria-modal="true" aria-labelledby="phs-feedback-title"
      class="no-print fixed top-0 right-0 z-[70] h-full w-full max-w-md translate-x-full transition-transform duration-300 ease-in-out bg-white dark:bg-gray-900 shadow-2xl flex flex-col">
      <header class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center flex-shrink-0">
            <i data-lucide="message-square-warning" class="w-5 h-5 text-amber-600 dark:text-amber-400"></i>
          </div>
          <div>
            <h2 id="phs-feedback-title" class="text-base font-bold text-gray-900 dark:text-gray-100">Report a Transaction Issue</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Tell us about an incomplete or wrong transaction.</p>
          </div>
        </div>
        <button id="phs-feedback-close" type="button"
          class="rounded-md p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </header>

      <div class="flex-1 overflow-y-auto px-5 py-4">
        <!-- Success state -->
        <div id="phs-feedback-success" class="hidden text-center py-10">
          <div class="w-14 h-14 rounded-full bg-green-50 dark:bg-green-900/30 flex items-center justify-center mx-auto mb-4">
            <i data-lucide="check-circle-2" class="w-8 h-8 text-green-600 dark:text-green-400"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">Feedback Submitted</h3>
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-5" id="phs-feedback-success-msg">Thank you. Your feedback has been received.</p>
          <button type="button" id="phs-feedback-another"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
            <i data-lucide="plus" class="w-4 h-4"></i> Submit another
          </button>
        </div>

        <form id="phs-feedback-form" class="space-y-4">
          <div id="phs-feedback-error"
            class="hidden rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-3 py-2 text-sm text-red-700 dark:text-red-300"></div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type of issue *</label>
            <select id="phs-feedback-category" name="category" required
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="incomplete_transaction">Incomplete transaction</option>
              <option value="wrong_transaction">Wrong / incorrect transaction</option>
              <option value="missing_record">Missing record</option>
              <option value="other">Other</option>
            </select>
            <div id="phs-feedback-other-wrap" class="hidden mt-2">
              <input type="text" id="phs-feedback-category-other" name="category_other" maxlength="150"
                placeholder="Please specify the type of issue *"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">File number</label>
            <input type="text" id="phs-feedback-fileno" name="file_number" placeholder="e.g. COM-RES-2021-78"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Auto-filled from the slip you're viewing, if any.</p>
          </div>

          <input type="hidden" id="phs-feedback-reference" name="reference_no" />

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subject</label>
            <input type="text" id="phs-feedback-subject" name="subject" maxlength="255" placeholder="Short summary (optional)"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Describe the problem *</label>
            <textarea id="phs-feedback-message" name="message" rows="5" required maxlength="2000"
              placeholder="Which transaction is wrong or missing, and what should it say?"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 resize-y"></textarea>
          </div>

          <button type="submit" id="phs-feedback-submit"
            class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition disabled:opacity-60 disabled:cursor-not-allowed">
            <i data-lucide="send" class="w-4 h-4"></i>
            <span id="phs-feedback-submit-label">Submit Feedback</span>
          </button>
        </form>
      </div>
    </aside>
  </div>

  <script>
    (function () {
      var fab      = document.getElementById('phs-feedback-fab');
      var panel    = document.getElementById('phs-feedback-panel');
      var backdrop = document.getElementById('phs-feedback-backdrop');
      var closeBtn = document.getElementById('phs-feedback-close');
      var form     = document.getElementById('phs-feedback-form');
      var errorBox = document.getElementById('phs-feedback-error');
      var success  = document.getElementById('phs-feedback-success');
      var another  = document.getElementById('phs-feedback-another');
      var submitBtn = document.getElementById('phs-feedback-submit');
      var submitLbl = document.getElementById('phs-feedback-submit-label');
      var categorySel = document.getElementById('phs-feedback-category');
      var otherWrap   = document.getElementById('phs-feedback-other-wrap');
      var otherInput  = document.getElementById('phs-feedback-category-other');
      if (!fab || !panel) return;

      // Show the "Please specify" box only when "Other" is the chosen issue type,
      // and make it required while visible (a hidden required field can't submit).
      function syncOther() {
        var isOther = categorySel.value === 'other';
        otherWrap.classList.toggle('hidden', !isOther);
        if (isOther) {
          otherInput.setAttribute('required', 'required');
        } else {
          otherInput.removeAttribute('required');
          otherInput.value = '';
        }
      }
      categorySel.addEventListener('change', syncOther);

      // Resolve at call time: window.PHS_PORTAL is defined in a later <script>, so
      // reading it here at parse time would yield an empty endpoint (which would
      // make fetch() POST back to the current page — phs/dashboard).
      function feedbackEndpoint() {
        return (window.PHS_PORTAL && window.PHS_PORTAL.routes && window.PHS_PORTAL.routes.feedback) || '';
      }
      var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

      function textOf(id) {
        var el = document.getElementById(id);
        if (!el) return '';
        var v = (el.textContent || '').trim();
        return (v === '--' || v === '') ? '' : v;
      }

      function open() {
        // Pre-fill from the slip currently on screen (if a search was run).
        document.getElementById('phs-feedback-fileno').value = textOf('file-number-value');
        document.getElementById('phs-feedback-reference').value = textOf('reference-no');
        syncOther();
        backdrop.classList.remove('invisible');
        backdrop.classList.add('opacity-100');
        panel.classList.remove('translate-x-full');
        document.body.style.overflow = 'hidden';
        if (window.lucide) window.lucide.createIcons();
      }
      function close() {
        backdrop.classList.add('invisible');
        backdrop.classList.remove('opacity-100');
        panel.classList.add('translate-x-full');
        document.body.style.overflow = '';
      }
      function resetForm() {
        success.classList.add('hidden');
        form.classList.remove('hidden');
        errorBox.classList.add('hidden');
        form.reset();
        syncOther();
      }

      fab.addEventListener('click', open);
      closeBtn.addEventListener('click', close);
      backdrop.addEventListener('click', close);
      another.addEventListener('click', resetForm);
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.classList.contains('translate-x-full')) close();
      });

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        errorBox.classList.add('hidden');
        submitBtn.disabled = true;
        submitLbl.textContent = 'Submitting...';

        var payload = {
          category:       document.getElementById('phs-feedback-category').value,
          category_other: otherInput.value,
          file_number:  document.getElementById('phs-feedback-fileno').value,
          reference_no: document.getElementById('phs-feedback-reference').value,
          subject:      document.getElementById('phs-feedback-subject').value,
          message:      document.getElementById('phs-feedback-message').value
        };

        fetch(feedbackEndpoint(), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf
          },
          body: JSON.stringify(payload)
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
          if (res.ok && res.data && res.data.success) {
            document.getElementById('phs-feedback-success-msg').textContent =
              res.data.message || 'Thank you. Your feedback has been received.';
            form.classList.add('hidden');
            success.classList.remove('hidden');
            if (window.lucide) window.lucide.createIcons();
          } else {
            var msg = (res.data && (res.data.message)) || 'Could not submit your feedback. Please try again.';
            if (res.data && res.data.errors) {
              msg = Object.keys(res.data.errors).map(function (k) { return res.data.errors[k][0]; }).join(' ');
            }
            errorBox.textContent = msg;
            errorBox.classList.remove('hidden');
          }
        })
        .catch(function () {
          errorBox.textContent = 'Network error. Please check your connection and try again.';
          errorBox.classList.remove('hidden');
        })
        .finally(function () {
          submitBtn.disabled = false;
          submitLbl.textContent = 'Submit Feedback';
        });
      });
    })();
  </script>

  <script>
    window.PHS_PORTAL = {
      institution: @json($institution),
      member: @json($member),
      packages: @json($packages),
      routes: {
        search: "{{ route('phs.search') }}",
        logout: "{{ route('phs.logout') }}",
        organization: "{{ route('phs.org.index') }}",
        payOnline: "{{ route('phs.tokens.payOnline') }}",
        requestInvoice: "{{ route('phs.tokens.requestInvoice') }}",
        transactions: "{{ route('phs.tokens.transactions') }}",
        print: "{{ route('phs.slip.print') }}",
        feedback: "{{ route('phs.feedback.store') }}",
        editRequestStore: "{{ route('phs.edit-requests.store') }}",
        editRequestIndex: "{{ route('phs.edit-requests.index') }}"
      },
      assets: {
        logo: "{{ asset('assets/logo/logo.png') }}",
        fallbackLogo: "{{ asset('assets/logo/las.jpg') }}",
        organizationLogo: "{{ $institution->logo_path ? asset('storage/' . $institution->logo_path) : '' }}",
        organizationBanner: "{{ $institution->banner_path ? asset('storage/' . $institution->banner_path) : '' }}"
      }
    };
  </script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="{{ asset('js/phs/portal.js') }}"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var preloader = document.getElementById('preloader');
      if (preloader) {
        setTimeout(function () {
          preloader.style.transition = 'opacity 0.3s ease';
          preloader.style.opacity = '0';
          setTimeout(function () { preloader.style.display = 'none'; }, 300);
        }, 500);
      }
    });
  </script>

  <script>
    (function () {
      document.addEventListener('DOMContentLoaded', function () {
        var $querySelect = $('#search-query');
        if ($querySelect.length) {
          $querySelect.select2({
            placeholder: 'Search or select a file number...',
            allowClear: true,
            width: '100%',
            minimumInputLength: 2,
            ajax: {
              url: "{{ route('ols.file-numbers') }}",
              dataType: 'json',
              delay: 250,
              data: function (params) {
                return { term: params.term || '' };
              },
              processResults: function (data) {
                return { results: data.results || [] };
              },
              cache: true
            },
            language: {
              inputTooShort: function () { return 'Type at least 2 characters…'; },
              searching: function () { return 'Searching file indexings…'; },
              noResults: function () { return 'No matching file found'; }
            }
          });
        }

        initAdditionalFilters();
        if (window.lucide) window.lucide.createIcons();
      });

      // ── Additional Filters (mirrors the /onpremise Search Land Records card) ──
      var FILTER_LABELS = {
        guarantorName: 'Party 1', guaranteeName: 'Party 2', lga: 'LGA',
        district: 'District', location: 'Location', plotNumber: 'Plot Number',
        planNumber: 'Plan Number', size: 'Size', caveat: 'Caveat'
      };

      function updateActiveFilterCount() {
        var container = document.getElementById('phs-filters-container');
        var badge = document.getElementById('phs-active-filter-count');
        if (!container || !badge) return;
        var count = container.querySelectorAll('[data-filter-cell]').length;
        badge.textContent = count;
        badge.classList.toggle('hidden', count === 0);
      }

      function addFilter(key) {
        var container = document.getElementById('phs-filters-container');
        var tpl = document.getElementById('phs-tpl-' + key);
        if (!container || !tpl || container.querySelector('[data-filter-cell="' + key + '"]')) return;

        var cell = document.createElement('div');
        cell.setAttribute('data-filter-cell', key);
        cell.className = 'flex flex-col gap-1';
        cell.innerHTML =
          '<div class="flex items-center justify-between">' +
            '<label class="text-xs font-medium text-gray-600 dark:text-gray-400">' + (FILTER_LABELS[key] || key) + '</label>' +
            '<button type="button" data-remove-filter="' + key + '" class="text-gray-400 hover:text-red-500" title="Remove filter">' +
              '<i data-lucide="x" class="w-3.5 h-3.5"></i>' +
            '</button>' +
          '</div>' +
          '<div class="flex items-center"></div>';
        cell.querySelector('div.flex.items-center').appendChild(tpl.content.cloneNode(true));
        container.appendChild(cell);
        updateActiveFilterCount();
        if (window.lucide) window.lucide.createIcons();
      }

      function initAdditionalFilters() {
        var toggle = document.getElementById('phs-toggle-filters-btn');
        var panel = document.getElementById('phs-filters-panel');
        var chevron = document.getElementById('phs-filters-chevron');
        var selector = document.getElementById('phs-filter-selector');
        var container = document.getElementById('phs-filters-container');
        if (!toggle || !panel || !selector || !container) return;

        toggle.addEventListener('click', function () {
          panel.classList.toggle('hidden');
          if (chevron) chevron.classList.toggle('rotate-90');
        });

        selector.addEventListener('change', function () {
          if (this.value) { addFilter(this.value); this.value = ''; }
        });

        container.addEventListener('click', function (e) {
          var btn = e.target.closest('[data-remove-filter]');
          if (!btn) return;
          var cell = container.querySelector('[data-filter-cell="' + btn.getAttribute('data-remove-filter') + '"]');
          if (cell) cell.remove();
          updateActiveFilterCount();
        });
      }

      // Exposed for portal.js performSearch() to merge into the search payload.
      window.phsGetActiveFilters = function () {
        var filters = {};
        document.querySelectorAll('#phs-filters-container .phs-filter-input').forEach(function (el) {
          var key = el.getAttribute('data-filter-key');
          var val = (el.value || '').trim();
          if (key && val) filters[key] = val;
        });
        return filters;
      };
    })();
  </script>
</body>
</html>
