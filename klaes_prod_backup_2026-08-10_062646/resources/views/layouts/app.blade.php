<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('page-title', 'KLAES')</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="app-base-url" content="{{ url('/') }}">
  <meta name="activity-personal-alert" content="{{ route('activity-monitoring.personal-alert') }}">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('storage/favicon_io/favicon-32x32.png') }}">
  <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('storage/favicon_io/favicon-16x16.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('storage/favicon_io/apple-touch-icon.png') }}">


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
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.8.0/html2pdf.bundle.min.js"
    integrity="sha512-w3u9q/DeneCSwUDjhiMNibTRh/1i/gScBVp2imNVAMCt6cUHIw6xzhzcPFIaL3Q1EbI2l+nu17q2aLJJLo4ZYg=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"
    integrity="sha512-qZvrmS2ekKPF2mSznTQsxqPgnpkI4DNTlrdUmTzrDgektczlKNRRhy5X5AAOnx5S09ydFYWWNSfcEqDTTHgtNA=="
    crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="{{ asset('js/app-layout.js') }}" defer></script>

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
  {{-- <div id="preloader" class="fixed inset-0 bg-white bg-opacity-80 flex items-center justify-center z-50">
    <img src="http://app.klaes.ng/storage/upload/logo/4.gif" alt="Loading..." style="width: 300px; height: auto;">
  </div> --}}


  {{-- <script>
    document.addEventListener('DOMContentLoaded', function () {
      const preloader = document.getElementById('preloader');
      setTimeout(function () {
        preloader.style.display = 'none';
      }, 9000);
    });
  </script> --}}
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
  <script src="{{ asset('js/print-manager.js') }}"></script>
  <script src="{{ asset('js/tailwind-modal.js') }}?v={{ time() }}"></script>
  <x-print-manager />
  <x-assign-security-paper-modal />
  @livewireScripts
</body>

</html>