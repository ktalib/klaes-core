<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('page-title', 'KLAES')</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="app-base-url" content="{{ url('/') }}">
  @auth
    {{-- Read by the Flutter WebView wrapper to decide whether to apply FLAG_SECURE.
         Match on user-is-super-admin, not on the role string. --}}
    <meta name="user-role" content="{{ auth()->user()->assign_role }}">
    <meta name="user-is-super-admin" content="{{ auth()->user()->isSuperAdmin() ? 'true' : 'false' }}">
  @endauth
  <meta name="activity-personal-alert" content="{{ route('activity-monitoring.personal-alert') }}">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link rel="icon" type="image/x-icon" href="{{ asset('favicon_io/favicon.ico') }}">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon_io/favicon-32x32.png') }}">
  <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon_io/favicon-16x16.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('favicon_io/apple-touch-icon.png') }}">


  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/jquery.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.7.1/css/buttons.dataTables.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    [x-cloak] {
      display: none !important;
    }
  </style>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/1.7.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
  <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/1.7.1/js/buttons.print.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
  <!-- Lucide Icons (pinned for stability) -->
  <script src="https://unpkg.com/lucide@0.429.0/dist/umd/lucide.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link rel="stylesheet" href="{{ asset('css/notification-flash.css') }}">
  <link rel="stylesheet" href="{{ asset('css/app-layout.css') }}?v={{ filemtime(public_path('css/app-layout.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/user-profile-card.css') }}?v={{ filemtime(public_path('css/user-profile-card.css')) }}">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.8.0/html2pdf.bundle.min.js"
    integrity="sha512-w3u9q/DeneCSwUDjhiMNibTRh/1i/gScBVp2imNVAMCt6cUHIw6xzhzcPFIaL3Q1EbI2l+nu17q2aLJJLo4ZYg=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"
    integrity="sha512-qZvrmS2ekKPF2mSznTQsxqPgnpkI4DNTlrdUmTzrDgektczlKNRRhy5X5AAOnx5S09ydFYWWNSfcEqDTTHgtNA=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="{{ asset('js/app-layout.js') }}" defer></script>
  {{-- window.PropertyLocation: the one place that decides what a full property
       location contains. Loaded here, without defer, because the cards that
       compose a location call it from inline handlers that can run before a
       deferred script would have finished. --}}
  <script src="{{ asset('js/property-location.js') }}?v={{ filemtime(public_path('js/property-location.js')) }}"></script>

  @yield('styles')
  @stack('styles')

  @livewireStyles
  <script>
    (function() {
      const state = localStorage.getItem('sidebar_state') || 'expanded';
      if (state === 'collapsed') {
        document.documentElement.classList.add('sidebar-collapsed');
      }
    })();
  </script>
</head>

<body class="bg-gray-100 flex h-screen font-sans antialiased">
  <!-- Preloader -->
    <div id="preloader" class="fixed inset-0 bg-white bg-opacity-80 flex items-center justify-center z-50">
    <img src="http://app.klaes.ng/assets/logo/klas_logo.gif" alt="Loading..." style="width: 300px; height: auto;">
  </div>  

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const preloader = document.getElementById('preloader');
      setTimeout(function () {
        preloader.style.display = 'none';
      }, 9000);
    });
  </script>  
  <!-- Sidebar -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const payload = {
        success: @json(session('success')),
        error: @json(session('error')),
        validation: @json($errors->any() ? $errors->all() : []),
      };

      if (window.AppLayout && typeof window.AppLayout.showAlerts === 'function') {
        window.AppLayout.showAlerts(payload);
      } else {
        window.AppLayoutPendingAlerts = payload;
      }
    });
  </script>


  <!-- Mobile Sidebar Overlay Backdrop -->
  <div id="sidebar-backdrop" class="fixed inset-0 bg-gray-900/50 z-30 opacity-0 pointer-events-none transition-opacity duration-300"></div>

  <!-- Sidebar Menu -->
  @include('admin.menu')
  <!-- Main Content (Placeholder) -->

  @include('admin.content')

  {{-- Borrowed session (ImpersonationController). Fixed to the viewport rather than placed
       in the flow: <body> is a flex row holding the sidebar and the content pane, and a
       third child there would sit beside them instead of across the bottom. It is also the
       only way back -- without it the admin would have to clear the session by hand. --}}
  @if (function_exists('is_impersonating') && is_impersonating())
    {{-- Block form. The inline expression form does not survive this Blade version with a
         ::class inside the parentheses: it compiles to an open PHP tag that is never
         closed, and the rest of the file is swallowed as PHP. Note also that a raw PHP
         open tag must never appear in this file even inside a comment -- Blade runs
         token_get_all() over the template, so the tokenizer takes it literally and stops
         compiling directives from there on. --}}
    @php
      $klaesImpersonator = app(\Lab404\Impersonate\Services\ImpersonateManager::class)->getImpersonator();
    @endphp
    <div class="fixed bottom-0 inset-x-0 z-[9998] bg-amber-500 text-amber-950 shadow-[0_-2px_10px_rgba(0,0,0,0.25)] print:hidden">
      <div class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 px-4 py-2 text-sm">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
        </svg>
        <span>
          {{ __('You are signed in as') }}
          <strong>{{ auth()->user()?->name }}</strong>
          @if ($klaesImpersonator)
            — {{ __('your own account is :name', ['name' => $klaesImpersonator->name]) }}
          @endif
        </span>
        <form method="POST" action="{{ route('impersonate.leave') }}" class="inline">
          @csrf
          <button type="submit" class="rounded-md bg-amber-950 px-3 py-1 text-xs font-semibold text-amber-50 hover:bg-black transition-colors">
            {{ __('Return to my account') }}
          </button>
        </form>
      </div>
    </div>
  @endif

  @php
    $forcePersonalAlert = request()->boolean('force_alert') && (config('app.debug') || app()->environment(['local', 'development']));
  @endphp
  <div id="global-personal-alert" class="personal-alert-toast" data-disabled="true"
    data-endpoint="{{ route('activity-monitoring.personal-alert') }}" data-refresh-minutes="10"
    data-force-alert="{{ $forcePersonalAlert ? '1' : '0' }}" aria-live="polite" aria-atomic="true" hidden></div>


  @yield('footer-scripts')
  @stack('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      window.AppLayout?.ensureLucide?.();
    });
  </script>
  {{-- Avatar renderer shared by the file-tracking screens (Quick Search, Log a File). --}}
  <script src="{{ asset('js/user-avatar.js') }}?v={{ filemtime(public_path('js/user-avatar.js')) }}"></script>

  {{-- Face detection: paths + the single place the thresholds are tuned. The library
       itself (~1.3MB) is NOT loaded here — js/face-detection/loader.js fetches it the
       first time a profile card actually needs it. --}}
  @auth
    <script>
      window.FaceDetectionAssets = {
        api: @json(asset('js/face-detection/face-api.js')),
        detector: @json(asset('js/face-detection/profile-picture-detector.js')),
        models: @json(asset('models/face-detection')),
        thresholds: {
          // 0.60 rather than the test page's 0.75: a genuine passport photo in this
          // project's own sample scored 73.6% and would have been refused.
          minConfidence: 0.60,
          minFaceCoverage: 0.05,
          rejectIllustrations: true,
          maxFlatness: 0.62
        }
      };
    </script>
    <script src="{{ asset('js/face-detection/loader.js') }}?v={{ filemtime(public_path('js/face-detection/loader.js')) }}"></script>

    {{-- One-time check of the picture this user ALREADY has on file, emitted only while
         no verdict has been recorded for it. An account carrying a stock cartoon avatar
         is held exactly like one carrying no picture at all, and this is how the server
         learns which is which — detection has no PHP implementation, so a browser has to
         judge the stored picture once and report it. Recorded once, never emitted again. --}}
    @if (auth()->user()->needs_photo_face_check)
      <script>
        window.ProfilePhotoSelfCheck = {
          photoUrl: @json(auth()->user()->profile_url),
          endpoint: @json(route('profile.picture.face-check')),
          csrf: @json(csrf_token())
        };
      </script>
      <script src="{{ asset('js/profile-photo-self-check.js') }}?v={{ filemtime(public_path('js/profile-photo-self-check.js')) }}" defer></script>
    @endif
  @endauth
  <script src="{{ asset('js/print-manager.js') }}"></script>
  <script src="{{ asset('js/tailwind-modal.js') }}?v={{ time() }}"></script>
  <x-print-manager />
  {{-- Shared creator/indexer profile card, global for the same reason the print manager is:
       any table that shows who created a record can open it. --}}
  @auth
    <x-user-profile-card />
    <script src="{{ asset('js/user-profile-card.js') }}?v={{ filemtime(public_path('js/user-profile-card.js')) }}" defer></script>
  @endauth
  {{-- The proofing stage in front of it: the White Copy card and the
       "has this been proofread" gate. Global for the same reason the manager is. --}}
  <x-white-copy />
  <x-assign-security-paper-modal />
  <x-reset-security-paper-modal />
  @livewireScripts
</body>

</html>
